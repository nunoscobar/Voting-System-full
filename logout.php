<?php
/** logout.php – termina a sessão (apenas via POST com token CSRF). */
declare(strict_types=1);

require __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    destroy_session();
    session_start();
    session_regenerate_id(true);
    flash_set('success', 'Sessão terminada com segurança.');
}
header('Location: login.php');
exit;
