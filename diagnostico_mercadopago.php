<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

$tokenConfigurado = defined('MERCADOPAGO_ACCESS_TOKEN') && MERCADOPAGO_ACCESS_TOKEN !== '';
$curlDisponible = function_exists('curl_init');
$appUrl = defined('APP_URL') ? APP_URL : '(APP_URL no definido)';
$phpIni = php_ini_loaded_file() ?: '(no detectado)';
$modEnv = getenv('MERCADOPAGO_ACCESS_TOKEN') !== false && getenv('MERCADOPAGO_ACCESS_TOKEN') !== '';

function estado(bool $ok): string {
    return $ok
        ? '<strong style="color:#087252">OK</strong>'
        : '<strong style="color:#b42318">FALTA / NO DETECTADO</strong>';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Diagnóstico Mercado Pago</title>
<style>
body{font-family:Arial,sans-serif;max-width:850px;margin:40px auto;padding:0 20px;line-height:1.55}
.card{border:1px solid #ddd;border-radius:12px;padding:20px;margin:18px 0}
code{background:#f4f4f4;padding:2px 6px;border-radius:5px}
.ok{background:#eaf7ef}
.bad{background:#fff1f0}
</style>
</head>
<body>
<h1>Diagnóstico de Mercado Pago</h1>

<div class="card <?= ($tokenConfigurado && $curlDisponible) ? 'ok' : 'bad' ?>">
<p><b>Access Token leído por config.php:</b> <?= estado($tokenConfigurado) ?></p>
<p><b>Variable de Apache visible con getenv():</b> <?= estado($modEnv) ?></p>
<p><b>Extensión cURL de PHP:</b> <?= estado($curlDisponible) ?></p>
<p><b>APP_URL:</b> <code><?= htmlspecialchars((string)$appUrl, ENT_QUOTES, 'UTF-8') ?></code></p>
<p><b>php.ini cargado:</b> <code><?= htmlspecialchars((string)$phpIni, ENT_QUOTES, 'UTF-8') ?></code></p>
</div>

<?php if (!$tokenConfigurado): ?>
<div class="card">
<h2>El token no está llegando a PHP</h2>
<p>Revisa que en <code>C:\xampp\apache\conf\httpd.conf</code> tengas las líneas <b>sin # al inicio</b>:</p>
<pre>SetEnv MERCADOPAGO_ACCESS_TOKEN "TU_ACCESS_TOKEN"
SetEnv MERCADOPAGO_USE_SANDBOX "1"
SetEnv APP_URL "TU_URL_PUBLICA/Trabajo_v2"</pre>
<p>Guarda el archivo y reinicia Apache completamente: Stop → Start.</p>
</div>
<?php endif; ?>

<?php if (!$curlDisponible): ?>
<div class="card">
<h2>cURL no está habilitado</h2>
<p>Abre el archivo <code><?= htmlspecialchars((string)$phpIni, ENT_QUOTES, 'UTF-8') ?></code>, busca:</p>
<pre>;extension=curl</pre>
<p>y déjalo así:</p>
<pre>extension=curl</pre>
<p>Después guarda y reinicia Apache.</p>
</div>
<?php endif; ?>

<?php if ($tokenConfigurado && $curlDisponible): ?>
<div class="card ok">
<h2>Todo está listo</h2>
<p>Mercado Pago ya debería aparecer habilitado en <code>pago.php</code>.</p>
<p>Por seguridad, este diagnóstico nunca muestra el contenido de tu Access Token.</p>
</div>
<?php endif; ?>
</body>
</html>
