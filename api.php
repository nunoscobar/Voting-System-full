<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';   // config + sessão segura + CSRF + utilizador atual

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

final class ApiError extends RuntimeException {}

/* ---------- utilitários ---------- */
function out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }
    if (!json_validate($raw)) {
        throw new ApiError('JSON inválido.', 400);
    }
    $d = json_decode($raw, true);
    return is_array($d) ? $d : throw new ApiError('Corpo inválido.', 400);
}

function tx(callable $fn): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $r = $fn($pdo);
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function fmt(array $r): array
{
    return [
        'id'    => (int) $r['id'],
        'name'  => $r['nome'],
        'role'  => $r['funcao'],
        'photo' => $r['foto'] ?? '',
        'votes' => (int) $r['votos'],
    ];
}

function clean_text(mixed $v, int $max = 60): string
{
    $s = trim((string) $v);
    if ($s === '' || mb_strlen($s) > $max) {
        throw new ApiError("Texto inválido (1 a $max caracteres).", 422);
    }
    return $s;
}

function clean_photo(mixed $v): ?string
{
    if ($v === null || $v === '') {
        return null;
    }
    if (!is_string($v) || strlen($v) > 700_000
        || !preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]++$#', $v)) {
        throw new ApiError('Fotografia inválida ou demasiado grande.', 422);
    }
    return $v;
}

function clean_votes(mixed $v): int
{
    if (!is_numeric($v) || (int) $v < 0 || (int) $v > 2_000_000_000) {
        throw new ApiError('Número de votos inválido.', 422);
    }
    return (int) $v;
}

function log_votes(PDO $pdo, int $id, int $delta, string $tipo): void
{
    $pdo->prepare('INSERT INTO log_votos (candidato_id, utilizador_id, delta, tipo, ip) VALUES (?, ?, ?, ?, ?)')
        ->execute([$id, current_user()['id'] ?? null, $delta, $tipo, client_ip()]);
}

/* ---------- ações ---------- */
function listar(): array
{
    $rows = db()->query('SELECT id, nome, funcao, foto, votos FROM candidatos ORDER BY id')->fetchAll();
    return ['candidatos' => array_map(fmt(...), $rows)];
}

function votar(array $b): array
{
    $id = (int) ($b['id'] ?? 0);
    return tx(function (PDO $pdo) use ($id) {
        $u = $pdo->prepare('UPDATE candidatos SET votos = votos + 1 WHERE id = ?');
        $u->execute([$id]);
        if ($u->rowCount() === 0) {
            throw new ApiError('Candidato não encontrado.', 404);
        }
        $s = $pdo->prepare('SELECT votos FROM candidatos WHERE id = ?');
        $s->execute([$id]);
        log_votes($pdo, $id, 1, 'voto');
        return ['id' => $id, 'votos' => (int) $s->fetchColumn()];
    });
}

function definir(array $b): array
{
    $id   = (int) ($b['id'] ?? 0);
    $novo = clean_votes($b['votos'] ?? null);
    return tx(function (PDO $pdo) use ($id, $novo) {
        $s = $pdo->prepare('SELECT votos FROM candidatos WHERE id = ? FOR UPDATE');
        $s->execute([$id]);
        $atual = $s->fetchColumn();
        if ($atual === false) {
            throw new ApiError('Candidato não encontrado.', 404);
        }
        $pdo->prepare('UPDATE candidatos SET votos = ? WHERE id = ?')->execute([$novo, $id]);
        if ($novo !== (int) $atual) {
            log_votes($pdo, $id, $novo - (int) $atual, 'ajuste');
        }
        return ['id' => $id, 'votos' => $novo];
    });
}

function criar(array $b): array
{
    $nome = clean_text($b['name'] ?? '');
    $func = clean_text($b['role'] ?? '');
    $foto = clean_photo($b['photo'] ?? null);
    $pdo = db();
    $pdo->prepare('INSERT INTO candidatos (nome, funcao, foto) VALUES (?, ?, ?)')->execute([$nome, $func, $foto]);
    return ['id' => (int) $pdo->lastInsertId()];
}

function remover(array $b): array
{
    $d = db()->prepare('DELETE FROM candidatos WHERE id = ?');
    $d->execute([(int) ($b['id'] ?? 0)]);
    if ($d->rowCount() === 0) {
        throw new ApiError('Candidato não encontrado.', 404);
    }
    return ['ok' => true];
}

function zerar(): array
{
    return tx(function (PDO $pdo) {
        $pdo->prepare("INSERT INTO log_votos (candidato_id, utilizador_id, delta, tipo)
                       SELECT id, ?, -CAST(votos AS SIGNED), 'zerar' FROM candidatos WHERE votos > 0")
            ->execute([current_user()['id'] ?? null]);
        $pdo->exec('UPDATE candidatos SET votos = 0');
        return ['ok' => true];
    });
}

function exportar(): never
{
    $data = [
        'app'          => 'votacao',
        'versao'       => 1,
        'exportado_em' => date(DATE_ATOM),
        'candidatos'   => listar()['candidatos'],
    ];
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="votacao-' . date('Ymd-His') . '.json"');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function importar(array $b): array
{
    $modo  = ($b['modo'] ?? '') === 'substituir' ? 'substituir' : 'acrescentar';
    $lista = $b['candidatos'] ?? null;
    if (!is_array($lista) || $lista === [] || count($lista) > 500) {
        throw new ApiError('Lista de candidatos inválida (1 a 500).', 422);
    }
    // valida tudo antes de tocar na base de dados
    $itens = array_map(static fn($c) => is_array($c) ? [
        clean_text($c['name'] ?? ''), clean_text($c['role'] ?? ''),
        clean_photo($c['photo'] ?? null), clean_votes($c['votes'] ?? 0),
    ] : throw new ApiError('Item inválido.', 422), $lista);

    return tx(function (PDO $pdo) use ($itens, $modo) {
        if ($modo === 'substituir') {
            $pdo->exec('DELETE FROM candidatos');
        }
        $ins = $pdo->prepare('INSERT INTO candidatos (nome, funcao, foto, votos) VALUES (?, ?, ?, ?)');
        foreach ($itens as [$n, $f, $p, $v]) {
            $ins->execute([$n, $f, $p, $v]);
            if ($v > 0) {
                log_votes($pdo, (int) $pdo->lastInsertId(), $v, 'importacao');
            }
        }
        return ['importados' => count($itens)];
    });
}

/* ---------- roteamento ---------- */
try {
    $acao   = $_GET['action'] ?? 'listar';
    $metodo = $_SERVER['REQUEST_METHOD'];
    $post   = ['votar', 'definir', 'criar', 'remover', 'zerar', 'importar'];

    if (in_array($acao, $post, true)) {
        if ($metodo !== 'POST') {
            throw new ApiError('Método não permitido.', 405);
        }
        if (!str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            throw new ApiError('Content-Type deve ser application/json.', 415);
        }
    }

    // --- Autenticação e autorização ---
    $user = current_user();
    if (!$user) {
        throw new ApiError('Sessão expirada. Inicie sessão novamente.', 401);
    }
    $soAdmin = ['definir', 'criar', 'remover', 'zerar', 'importar', 'exportar'];
    if (in_array($acao, $soAdmin, true) && $user['papel'] !== 'admin') {
        throw new ApiError('Sem permissão para esta ação.', 403);
    }
    if (in_array($acao, $post, true) && !csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        throw new ApiError('Token CSRF inválido. Recarregue a página.', 403);
    }
    session_write_close();   // liberta o bloqueio da sessão (cliques rápidos não ficam em fila)

    match ($acao) {
        'listar'   => out(listar()),
        'exportar' => exportar(),
        'votar'    => out(votar(body())),
        'definir'  => out(definir(body())),
        'criar'    => out(criar(body()), 201),
        'remover'  => out(remover(body())),
        'zerar'    => out(zerar()),
        'importar' => out(importar(body())),
        default    => throw new ApiError('Ação desconhecida.', 404),
    };
} catch (ApiError $e) {
    out(['erro' => $e->getMessage()], $e->getCode() >= 400 ? $e->getCode() : 400);
} catch (Throwable $e) {
    error_log($e->getMessage());
    out(['erro' => 'Erro interno do servidor.'], 500);
}
