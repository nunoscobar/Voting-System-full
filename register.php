<?php
/** register.php – registo de novas contas. */
declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/layout.php';

if (current_user()) {
    header('Location: index.php');
    exit;
}

// Registo fechado (exceto enquanto não existir nenhum utilizador: cria o 1.º administrador)
$vazio = (int) db()->query('SELECT COUNT(*) FROM utilizadores')->fetchColumn() === 0;
if (!REGISTO_ABERTO && !$vazio) {
    http_response_code(403);
    exit('O registo está desativado. Peça uma conta ao administrador.');
}

$erro = null;
$nome = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $nome  = (string) ($_POST['nome'] ?? '');
    $email = (string) ($_POST['email'] ?? '');
    $senha = (string) ($_POST['senha'] ?? '');
    $conf  = (string) ($_POST['confirmar'] ?? '');

    $nomeOk  = clean_nome($nome);
    $emailOk = clean_email($email);

    if (($_POST['website'] ?? '') !== '') {
        // Honeypot preenchido (bot): finge sucesso sem criar nada
        flash_set('success', 'Pedido concluído. Já pode iniciar sessão.');
        header('Location: login.php');
        exit;
    }
    if (!$nomeOk)                    { $erro = 'Indique um nome válido (2 a 80 caracteres).'; }
    elseif (!$emailOk)               { $erro = 'Indique um email válido.'; }
    elseif ($senha !== $conf)        { $erro = 'As palavras-passe não coincidem.'; }
    elseif ($err = pw_validate($senha, $emailOk)) { $erro = $err; }
    else {
        create_user($nomeOk, $emailOk, $senha);   // resultado ignorado de propósito (não revela emails existentes)
        flash_set('success', 'Pedido concluído. Se os dados forem válidos, já pode iniciar sessão.');
        header('Location: login.php');
        exit;
    }
}

auth_start('Criar conta', $vazio ? 'A primeira conta criada será o administrador.' : 'Preencha os dados para se registar.');
alert_html($erro ? ['danger', $erro] : null);
?>
  <form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
    <div class="mb-3"><label class="form-label" for="nome">Nome</label>
      <input class="form-control" id="nome" name="nome" maxlength="80" value="<?= e($nome) ?>" autocomplete="name" required autofocus></div>
    <div class="mb-3"><label class="form-label" for="email">Email</label>
      <input class="form-control" type="email" id="email" name="email" maxlength="190" value="<?= e($email) ?>" autocomplete="email" required></div>
    <div class="mb-3"><label class="form-label" for="senha">Palavra-passe</label>
      <input class="form-control" type="password" id="senha" name="senha" maxlength="128" autocomplete="new-password" required>
      <div class="form-text">Mínimo 12 caracteres, com maiúsculas, minúsculas, números e um símbolo.</div></div>
    <div class="mb-4"><label class="form-label" for="confirmar">Confirmar palavra-passe</label>
      <input class="form-control" type="password" id="confirmar" name="confirmar" maxlength="128" autocomplete="new-password" required></div>
    <button class="btn btn-pri w-100" type="submit"><i class="bi bi-person-plus me-1"></i>Criar conta</button>
  </form>
  <p class="text-center small mt-4 mb-0">Já tem conta? <a href="login.php">Iniciar sessão</a></p>
<?php auth_end();
