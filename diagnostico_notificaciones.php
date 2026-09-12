<?php
declare(strict_types=1);
require_once __DIR__ . '/proteger_admin.php';
require_once __DIR__ . '/config.php';

$autoload = is_file(__DIR__ . '/vendor/autoload.php');
$checks = [
    'SMTP activado' => SMTP_ENABLED,
    'PHPMailer instalado' => $autoload,
    'SMTP usuario configurado' => SMTP_USER !== '',
    'SMTP contraseña configurada' => SMTP_PASS !== '',
    'Correo remitente configurado' => SMTP_FROM_EMAIL !== '',
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Diagnóstico de notificaciones</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header"><a class="brand" href="panel_admin.php"><span class="brand-mark">✚</span><span>Administración</span></a><nav><a href="panel_pedidos.php">Pedidos</a></nav></header>
<main class="admin-main">
<section class="admin-panel">
<p class="eyebrow">Diagnóstico</p>
<h1>Notificaciones por correo</h1>
<p class="muted">Esta página no muestra contraseñas.</p>
<div class="diagnostic-list">
<?php foreach ($checks as $label => $ok): ?>
<div class="summary-line"><span><?= e($label) ?></span><strong><?= $ok ? 'OK' : 'FALTA' ?></strong></div>
<?php endforeach; ?>
</div>
<?php if (!$autoload): ?><div class="alert alert-warning">Ejecuta <code>composer install</code> dentro de la carpeta del proyecto para instalar PHPMailer.</div><?php endif; ?>
</section>
</main>
</body>
</html>
