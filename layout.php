<?php
/** layout.php – cabeçalho/rodapé partilhados pelas páginas de autenticação e gestão. */
declare(strict_types=1);

function html_head(string $titulo, string $bodyClass = ''): void
{ ?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($titulo) ?> – Votação</title>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="style.css" rel="stylesheet">
</head>
<body class="<?= e($bodyClass) ?>">
<?php }

function auth_start(string $titulo, string $subtitulo = ''): void
{
    html_head($titulo); ?>
<main class="auth-wrap"><div class="auth-card">
  <div class="brand"><i class="bi bi-check2-square"></i>CPAT-VOTAÇÃO</div>
  <h1 class="h5 mb-1"><?= e($titulo) ?></h1>
  <p class="text-secondary small mb-4"><?= e($subtitulo) ?></p>
<?php }

function auth_end(): void
{ ?>
</div></main>
</body></html>
<?php }

function alert_html(?array $f): void
{
    if ($f) {
        echo '<div class="alert alert-' . e($f[0]) . ' py-2" role="alert">' . e($f[1]) . '</div>';
    }
}
