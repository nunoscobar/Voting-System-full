<?php
/** login.php – processamento e formulário de início de sessão. */
declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/layout.php';

if (current_user()) {
    header('Location: index.php');
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha = (string) ($_POST['senha'] ?? '');

    // Limites de tamanho evitam pedidos abusivos ao algoritmo de hash
    if (mb_strlen($email) > 190 || strlen($senha) > 1024) {
        $erro = MSG_LOGIN_FALHOU;
    } elseif (attempt_login($email, $senha)) {
        header('Location: index.php');
        exit;
    } else {
        usleep(random_int(150_000, 350_000));   // atraso aleatório: dificulta automatização
        $erro = MSG_LOGIN_FALHOU;               // mensagem genérica, igual para todas as falhas
    }
}

auth_start('Iniciar sessão', 'Introduza as suas credenciais para gerir.');
alert_html($erro ? ['danger', $erro] : flash_get());
?>
  <form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="email">Email</label>
      <input class="form-control" type="email" id="email" name="email" maxlength="190"
             value="<?= e($_POST['email'] ?? '') ?>" autocomplete="username" required autofocus>
    </div>
    <div class="mb-4">
      <label class="form-label" for="senha">Palavra-passe</label>
      <input class="form-control" type="password" id="senha" name="senha" maxlength="1024"
             autocomplete="current-password" required>
    </div>
    <button class="btn btn-pri w-100" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i>Entrar</button>
  </form>
<?php if (REGISTO_ABERTO): ?>
  <p class="text-center small mt-4 mb-0">Ainda não tem conta? <a href="register.php">Registar-se</a></p>
<?php endif;
auth_end();
