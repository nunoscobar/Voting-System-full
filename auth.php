<?php
/**
 * auth.php – funções de autenticação, CSRF, XSS e gestão de bloqueios.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

const MSG_LOGIN_FALHOU = 'Não foi possível iniciar sessão. Verifique os dados ou tente mais tarde.';

/* ===================== XSS: escape de saída ===================== */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/* ===================== CSRF ===================== */
function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $t): bool
{
    return is_string($t) && $t !== '' && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

/** Exige token válido em formulários (campo "csrf") ou AJAX (cabeçalho X-CSRF-Token). */
function csrf_require(): void
{
    if (!csrf_valid($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        http_response_code(403);
        exit('Pedido inválido (CSRF). Volte atrás, recarregue a página e tente de novo.');
    }
}

/* ===================== Mensagens flash ===================== */
function flash_set(string $tipo, string $msg): void { $_SESSION['flash'] = [$tipo, $msg]; }

function flash_get(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/* ===================== Palavras-passe ===================== */
function pw_algo(): string
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
}

function pw_opts(): array
{
    return pw_algo() === PASSWORD_BCRYPT
        ? ['cost' => 12]
        : ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1];
}

function pw_hash(string $senha): string
{
    return password_hash($senha, pw_algo(), pw_opts());
}

/** Devolve a mensagem de erro, ou null se a palavra-passe cumprir a política. */
function pw_validate(string $senha, string $email = ''): ?string
{
    $maxBytes = pw_algo() === PASSWORD_BCRYPT ? 72 : 128;   // bcrypt ignora além de 72 bytes
    if (mb_strlen($senha) < 12)                { return 'A palavra-passe deve ter pelo menos 12 caracteres.'; }
    if (strlen($senha) > $maxBytes)            { return 'A palavra-passe é demasiado longa.'; }
    if (!preg_match('/\p{Ll}/u', $senha) || !preg_match('/\p{Lu}/u', $senha)
        || !preg_match('/\d/', $senha) || !preg_match('/[^\p{L}\d]/u', $senha)) {
        return 'Use maiúsculas, minúsculas, números e pelo menos um símbolo.';
    }
    $local = (string) strstr($email, '@', true);
    if (strlen($local) >= 4 && stripos($senha, $local) !== false) {
        return 'A palavra-passe não pode conter o seu email.';
    }
    return null;
}

/* ===================== Utilizador atual ===================== */
function current_user(bool $refresh = false): ?array
{
    static $user = null, $loaded = false;
    if (!$loaded || $refresh) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $s = db()->prepare('SELECT id, nome, email, papel, ativo, sessao_versao FROM utilizadores WHERE id = ?');
            $s->execute([$_SESSION['uid']]);
            $r = $s->fetch();
            // A conta tem de existir, estar ativa e a sessão não pode ter sido revogada
            if ($r && $r['ativo'] && (int) $r['sessao_versao'] === (int) ($_SESSION['v'] ?? 0)) {
                $user = $r;
            } else {
                destroy_session();
                session_start();
            }
        }
        $loaded = true;
    }
    return $user;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['papel'] !== 'admin') {
        http_response_code(403);
        exit('Acesso negado.');
    }
    return $u;
}

/* ===================== Anti-força-bruta ===================== */
function client_ip(): string
{
    return inet_pton($_SERVER['REMOTE_ADDR'] ?? '') ?: "\0\0\0\0";   // só REMOTE_ADDR (não falsificável)
}

/** Regista uma falha: incrementa contadores e bloqueia ao atingir o limite. Usa a hora do MySQL. */
function register_failure(?int $userId, string $ip): void
{
    $pdo = db();
    $max = MAX_TENTATIVAS;
    $min = BLOQUEIO_MINUTOS;
    $pdo->beginTransaction();
    try {
        // IP: se o bloqueio anterior expirou, recomeça a contagem
        $pdo->prepare(
            'INSERT INTO bloqueios_ip (ip, tentativas) VALUES (?, 1)
             ON DUPLICATE KEY UPDATE
               tentativas    = IF(bloqueado_ate IS NOT NULL AND bloqueado_ate <= NOW(), 1, tentativas + 1),
               bloqueado_ate = IF(bloqueado_ate IS NOT NULL AND bloqueado_ate <= NOW(), NULL, bloqueado_ate)'
        )->execute([$ip]);
        $pdo->prepare("UPDATE bloqueios_ip SET bloqueado_ate = DATE_ADD(NOW(), INTERVAL $min MINUTE)
                       WHERE ip = ? AND tentativas >= $max AND bloqueado_ate IS NULL")->execute([$ip]);

        // Conta (só se o email existir)
        if ($userId !== null) {
            $pdo->prepare(
                'UPDATE utilizadores SET
                   tentativas_falhadas = IF(bloqueado_ate IS NOT NULL AND bloqueado_ate <= NOW(), 1, tentativas_falhadas + 1),
                   bloqueado_ate       = IF(bloqueado_ate IS NOT NULL AND bloqueado_ate <= NOW(), NULL, bloqueado_ate)
                 WHERE id = ?'
            )->execute([$userId]);
            $pdo->prepare("UPDATE utilizadores SET bloqueado_ate = DATE_ADD(NOW(), INTERVAL $min MINUTE)
                           WHERE id = ? AND tentativas_falhadas >= $max AND bloqueado_ate IS NULL")->execute([$userId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Tenta autenticar. Devolve true/false SEM revelar o motivo da falha
 * (email inexistente, palavra-passe errada, conta inativa ou bloqueada).
 */
function attempt_login(string $email, string $senha): bool
{
    $pdo = db();
    $ip  = client_ip();

    $s = $pdo->prepare('SELECT (bloqueado_ate IS NOT NULL AND bloqueado_ate > NOW()) FROM bloqueios_ip WHERE ip = ?');
    $s->execute([$ip]);
    $ipBloqueado = (bool) $s->fetchColumn();

    $s = $pdo->prepare(
        'SELECT id, senha_hash, ativo, sessao_versao,
                (bloqueado_ate IS NOT NULL AND bloqueado_ate > NOW()) AS bloqueado
         FROM utilizadores WHERE email = ?'
    );
    $s->execute([$email]);
    $u = $s->fetch();

    // Custo de CPU idêntico quer o utilizador exista ou não (evita enumeração por tempo de resposta)
    if ($u) {
        $ok = password_verify($senha, $u['senha_hash']);
    } else {
        pw_hash($senha);
        $ok = false;
    }

    if ($ipBloqueado || !$u || !$ok || !$u['ativo'] || $u['bloqueado']) {
        register_failure($u ? (int) $u['id'] : null, $ip);
        return false;
    }

    // Sucesso: atualiza o hash se o algoritmo/custo mudou e limpa os contadores
    if (password_needs_rehash($u['senha_hash'], pw_algo(), pw_opts())) {
        $pdo->prepare('UPDATE utilizadores SET senha_hash = ? WHERE id = ?')->execute([pw_hash($senha), $u['id']]);
    }
    $pdo->prepare('UPDATE utilizadores SET tentativas_falhadas = 0, bloqueado_ate = NULL, ultimo_login = NOW() WHERE id = ?')
        ->execute([$u['id']]);
    $pdo->prepare('DELETE FROM bloqueios_ip WHERE ip = ?')->execute([$ip]);

    session_regenerate_id(true);          // evita Session Fixation
    $_SESSION = [                         // também gera um novo token CSRF
        'uid'       => (int) $u['id'],
        'v'         => (int) $u['sessao_versao'],
        'inicio'    => time(),
        'atividade' => time(),
        'ua'        => hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''),
    ];
    current_user(true);
    return true;
}

/* ===================== Criação de utilizadores ===================== */
/**
 * Cria um utilizador. Devolve false se o email já existe.
 * Com $papel = null: o primeiro utilizador do sistema torna-se administrador.
 */
function create_user(string $nome, string $email, string $senha, ?string $papel = null): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($papel === null) {
            $n     = (int) $pdo->query('SELECT COUNT(*) FROM utilizadores FOR UPDATE')->fetchColumn();
            $papel = $n === 0 ? 'admin' : 'utilizador';
        }
        $pdo->prepare('INSERT INTO utilizadores (nome, email, senha_hash, papel) VALUES (?, ?, ?, ?)')
            ->execute([$nome, $email, pw_hash($senha), $papel]);
        $pdo->commit();
        return true;
    } catch (PDOException $ex) {
        $pdo->rollBack();
        if ($ex->getCode() === '23000') {    // violação de UNIQUE (email repetido)
            return false;
        }
        throw $ex;
    }
}

function clean_email(string $v): ?string
{
    $v = mb_strtolower(trim($v));
    return (mb_strlen($v) <= 190 && filter_var($v, FILTER_VALIDATE_EMAIL)) ? $v : null;
}

function clean_nome(string $v): ?string
{
    $v = trim(preg_replace('/\s+/u', ' ', $v) ?? '');
    return (mb_strlen($v) >= 2 && mb_strlen($v) <= 80) ? $v : null;
}
