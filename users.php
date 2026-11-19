<?php
/** users.php – gestão de utilizadores (apenas administradores). */
declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/layout.php';

$admin = require_admin();
$pdo   = db();

/* ---------------- Ações (POST + CSRF) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $acao = (string) ($_POST['acao'] ?? '');
    $id   = (int) ($_POST['id'] ?? 0);

    try {
        if ($acao === 'criar') {
            $nome  = clean_nome((string) ($_POST['nome'] ?? ''));
            $email = clean_email((string) ($_POST['email'] ?? ''));
            $senha = (string) ($_POST['senha'] ?? '');
            $papel = ($_POST['papel'] ?? '') === 'admin' ? 'admin' : 'utilizador';
            if (!$nome || !$email)                          { throw new RuntimeException('Nome ou email inválido.'); }
            if ($m = pw_validate($senha, $email))            { throw new RuntimeException($m); }
            if (!create_user($nome, $email, $senha, $papel)) { throw new RuntimeException('Esse email já está registado.'); }
            flash_set('success', 'Utilizador criado.');
        } else {
            // Ações sobre uma conta existente: nunca sobre a própria (evita auto-bloqueio do administrador)
            if ($id === (int) $admin['id']) {
                throw new RuntimeException('Não pode executar esta ação na sua própria conta.');
            }
            $ok = match ($acao) {
                'alternar'    => $pdo->prepare('UPDATE utilizadores SET ativo = 1 - ativo, sessao_versao = sessao_versao + 1 WHERE id = ?')->execute([$id]),
                'papel'       => $pdo->prepare('UPDATE utilizadores SET papel = ?, sessao_versao = sessao_versao + 1 WHERE id = ?')
                                      ->execute([($_POST['papel'] ?? '') === 'admin' ? 'admin' : 'utilizador', $id]),
                'desbloquear' => $pdo->prepare('UPDATE utilizadores SET tentativas_falhadas = 0, bloqueado_ate = NULL WHERE id = ?')->execute([$id]),
                'apagar'      => $pdo->prepare('DELETE FROM utilizadores WHERE id = ?')->execute([$id]),
                'senha'       => (function () use ($pdo, $id): bool {
                    $s = $pdo->prepare('SELECT email FROM utilizadores WHERE id = ?');
                    $s->execute([$id]);
                    $email = $s->fetchColumn();
                    if ($email === false) { throw new RuntimeException('Utilizador não encontrado.'); }
                    $nova = (string) ($_POST['nova_senha'] ?? '');
                    if ($m = pw_validate($nova, (string) $email)) { throw new RuntimeException($m); }
                    // Nova palavra-passe + invalida todas as sessões ativas da conta
                    return $pdo->prepare('UPDATE utilizadores SET senha_hash = ?, tentativas_falhadas = 0, bloqueado_ate = NULL,
                                          sessao_versao = sessao_versao + 1 WHERE id = ?')->execute([pw_hash($nova), $id]);
                })(),
                default       => throw new RuntimeException('Ação desconhecida.'),
            };
            flash_set('success', 'Alteração guardada.');
        }
    } catch (RuntimeException $ex) {
        flash_set('danger', $ex->getMessage());
    }
    header('Location: users.php');   // padrão Post/Redirect/Get
    exit;
}

$users = $pdo->query(
    'SELECT id, nome, email, papel, ativo, ultimo_login,
            (bloqueado_ate IS NOT NULL AND bloqueado_ate > NOW()) AS bloqueado
     FROM utilizadores ORDER BY nome'
)->fetchAll();

html_head('Gestão de utilizadores');
$nonce = csp_nonce();
?>
<div class="page-wrap"><div class="page-card">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-people me-2 text-secondary"></i>Gestão de utilizadores</h1>
    <a class="btn btn-outline-secondary btn-sm" href="index.php"><i class="bi bi-arrow-left me-1"></i>Voltar à votação</a>
  </div>
  <?php alert_html(flash_get()); ?>

  <div class="row g-4">
    <div class="col-lg-4"><div class="cardx">
      <h2 class="h6">Novo utilizador</h2>
      <form method="post" autocomplete="off">
        <?= csrf_field() ?><input type="hidden" name="acao" value="criar">
        <div class="mb-2"><input class="form-control" name="nome" placeholder="Nome" maxlength="80" required></div>
        <div class="mb-2"><input class="form-control" type="email" name="email" placeholder="Email" maxlength="190" required></div>
        <div class="mb-2"><input class="form-control" type="password" name="senha" placeholder="Palavra-passe (mín. 12)" maxlength="128" autocomplete="new-password" required></div>
        <div class="mb-3"><select class="form-select" name="papel"><option value="utilizador">Utilizador</option><option value="admin">Administrador</option></select></div>
        <button class="btn btn-pri w-100"><i class="bi bi-plus-lg me-1"></i>Criar</button>
      </form>
    </div></div>

    <div class="col-lg-8"><div class="cardx">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Utilizador</th><th>Papel</th><th>Estado</th><th>Último login</th><th></th></tr></thead>
          <tbody>
<?php foreach ($users as $u): $self = (int) $u['id'] === (int) $admin['id']; ?>
            <tr>
              <td><div class="fw-semibold"><?= e($u['nome']) ?></div><div class="small text-secondary"><?= e($u['email']) ?></div></td>
              <td><span class="badge text-bg-<?= $u['papel'] === 'admin' ? 'primary' : 'secondary' ?>"><?= e($u['papel']) ?></span></td>
              <td>
                <?php if (!$u['ativo']): ?><span class="badge text-bg-danger">Inativo</span>
                <?php elseif ($u['bloqueado']): ?><span class="badge text-bg-warning">Bloqueado</span>
                <?php else: ?><span class="badge text-bg-success">Ativo</span><?php endif; ?>
              </td>
              <td class="small text-secondary"><?= e($u['ultimo_login'] ?? '—') ?></td>
              <td class="text-end">
<?php if (!$self): ?>
                <div class="dropdown">
                  <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="Ações"><i class="bi bi-three-dots-vertical"></i></button>
                  <ul class="dropdown-menu dropdown-menu-end">
<?php foreach ([
    ['alternar',    $u['ativo'] ? 'Desativar' : 'Ativar', null,                                   null],
    ['desbloquear', 'Desbloquear',                        null,                                   null],
    ['papel',       $u['papel'] === 'admin' ? 'Tornar utilizador' : 'Tornar administrador', $u['papel'] === 'admin' ? 'utilizador' : 'admin', null],
    ['apagar',      'Apagar',                             null,                                   'Apagar este utilizador?'],
] as [$a, $label, $papel, $conf]): ?>
                    <li><form method="post" <?= $conf ? 'data-confirm="' . e($conf) . '"' : '' ?>>
                      <?= csrf_field() ?><input type="hidden" name="acao" value="<?= $a ?>"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                      <?php if ($papel): ?><input type="hidden" name="papel" value="<?= $papel ?>"><?php endif; ?>
                      <button class="dropdown-item <?= $a === 'apagar' ? 'text-danger' : '' ?>"><?= e($label) ?></button></form></li>
<?php endforeach; ?>
                    <li><button class="dropdown-item pw-btn" data-id="<?= (int) $u['id'] ?>" data-nome="<?= e($u['nome']) ?>">Redefinir palavra-passe</button></li>
                  </ul>
                </div>
<?php endif; ?>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div></div>
  </div>
</div></div>

<div class="modal fade" id="pwModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content" autocomplete="off">
  <?= csrf_field() ?><input type="hidden" name="acao" value="senha"><input type="hidden" name="id" id="pwId">
  <div class="modal-header"><h5 class="modal-title">Redefinir palavra-passe</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><p class="small text-secondary" id="pwNome"></p>
    <input class="form-control" type="password" name="nova_senha" placeholder="Nova palavra-passe (mín. 12)" maxlength="128" autocomplete="new-password" required>
    <div class="form-text">Todas as sessões ativas deste utilizador serão terminadas.</div></div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-pri">Guardar</button></div>
</form></div></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script nonce="<?= e($nonce) ?>">
$(function(){
  const m=new bootstrap.Modal('#pwModal');
  $('.pw-btn').on('click',function(){$('#pwId').val($(this).data('id'));$('#pwNome').text($(this).data('nome'));m.show()});
  $('form[data-confirm]').on('submit',function(){return confirm($(this).data('confirm'))});
});
</script>
</body></html>
