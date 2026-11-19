<?php
/**
 * config.php – configuração, ligação à base de dados e segurança da sessão.
 * Incluído por todas as páginas (via auth.php).
 */
declare(strict_types=1);

/* ===================== Configuração ===================== */
const APP_ENV  = 'development';   // 'production' => exige HTTPS e cookie Secure
const DB_HOST  = '127.0.0.1';
const DB_PORT  = 3306;
const DB_NAME  = 'votacao';
const DB_USER  = 'root';          // em produção use um utilizador com privilégios mínimos
const DB_PASS  = '';

const MAX_TENTATIVAS      = 5;      // falhas consecutivas antes de bloquear
const BLOQUEIO_MINUTOS    = 15;     // duração do bloqueio
const SESSAO_INATIVIDADE  = 1800;   // 30 min sem atividade => termina a sessão
const SESSAO_MAXIMA       = 28800;  // 8 h desde o login => termina a sessão
const REGISTO_ABERTO      = true;   // false => só o administrador cria contas

/* ===================== Base de dados (PDO) ===================== */
function db(): PDO
{
    static $pdo = null;
    return $pdo ??= new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // prepared statements reais (anti SQL Injection)
        ]
    );
}

/* ===================== HTTPS e cabeçalhos de segurança ===================== */
function is_https(): bool
{
    // Não confiamos em X-Forwarded-Proto (falsificável). Atrás de proxy, configure o servidor.
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/** Nonce de CSP (um por pedido) para os <script> inline. */
function csp_nonce(): string
{
    static $n = null;
    return $n ??= base64_encode(random_bytes(16));
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    $csp = "default-src 'self'; "
         . "script-src 'self' 'nonce-" . csp_nonce() . "' https://cdn.jsdelivr.net https://code.jquery.com; "
         . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
         . "font-src 'self' data: https://cdn.jsdelivr.net https://fonts.gstatic.com; "
         . "img-src 'self' data:; connect-src 'self'; object-src 'none'; "
         . "base-uri 'self'; form-action 'self'; frame-ancestors 'none'";
    header('Content-Security-Policy: ' . $csp);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/* ===================== Sessão segura ===================== */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (APP_ENV === 'production' && !is_https()) {
        http_response_code(426);
        exit('HTTPS obrigatório.');
    }
    ini_set('session.use_strict_mode', '1');    // rejeita IDs de sessão não emitidos pelo servidor
    ini_set('session.use_only_cookies', '1');   // nunca aceitar o ID na URL
    session_name('VOTACAO_SID');
    session_set_cookie_params([
        'lifetime' => 0,                                   // cookie de sessão (apaga ao fechar o browser)
        'path'     => '/',
        'secure'   => APP_ENV === 'production' || is_https(),
        'httponly' => true,                                // inacessível a JavaScript
        'samesite' => 'Strict',                            // não enviado em pedidos de outros sites
    ]);
    session_start();

    // Expiração por inatividade / duração máxima / mudança de browser (User-Agent)
    if (isset($_SESSION['uid'])) {
        $now = time();
        $ua  = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($now - ($_SESSION['atividade'] ?? 0) > SESSAO_INATIVIDADE
            || $now - ($_SESSION['inicio'] ?? 0) > SESSAO_MAXIMA
            || !hash_equals($_SESSION['ua'] ?? '', $ua)) {
            destroy_session();
            session_start();
        } else {
            $_SESSION['atividade'] = $now;
        }
    }
}

function destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600, 'path' => $p['path'], 'domain' => $p['domain'],
            'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Strict',
        ]);
    }
    session_destroy();
}

send_security_headers();
start_secure_session();
