<?php

declare(strict_types=1);
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion_segura();

$mensaje = '';
$flashes = obtener_flashes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf($_POST['csrf_token'] ?? null);
    $correo = normalizar_correo((string) ($_POST['correo'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = $conexion->prepare('SELECT id, nombre, correo, password, rol FROM usuarios WHERE correo = ? LIMIT 1');
    $stmt->bind_param('s', $correo);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();

    if ($usuario && password_verify($password, $usuario['password'])) {
        session_regenerate_id(true);
        $_SESSION['id'] = (int) $usuario['id'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['correo'] = $usuario['correo'];
        $_SESSION['rol'] = $usuario['rol'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $destino = $_SESSION['redirect_after_login'] ?? ($usuario['rol'] === 'admin' ? 'panel_admin.php' : 'index.php');
        unset($_SESSION['redirect_after_login']);
        header('Location: ' . $destino);
        exit;
    }

    $mensaje = 'Correo o contraseña incorrectos.';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php"><span class="brand-mark">✚</span><span><?= e(APP_NAME) ?></span></a>
    <nav><a href="index.php">Inicio</a><a class="nav-cta" href="registro.php">Crear cuenta</a></nav>
</header>

<main class="auth-page">
    <section class="auth-intro">
        <p class="eyebrow">Bienvenido de nuevo</p>
        <h1>Tu cuenta, pedidos y dirección en un solo lugar.</h1>
        <p>Inicia sesión para continuar una compra, revisar pagos y consultar el estado de tus envíos.</p>
        <div class="feature-stack">
            <span>✓ Carrito y checkout</span>
            <span>✓ Dirección principal guardada</span>
            <span>✓ Historial de pedidos</span>
        </div>
    </section>

    <section class="auth-card">
        <div class="section-heading compact">
            <div><p class="eyebrow">Acceso</p><h2>Iniciar sesión</h2></div>
        </div>

        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div>
        <?php endforeach; ?>
        <?php if ($mensaje): ?><div class="alert alert-error"><?= e($mensaje) ?></div><?php endif; ?>

        <form method="post" autocomplete="on" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label class="span-2" for="correo">Correo electrónico
                <input id="correo" type="email" name="correo" maxlength="190" autocomplete="email" required>
            </label>
            <label class="span-2" for="password">Contraseña
                <input id="password" type="password" name="password" autocomplete="current-password" required>
            </label>
            <button class="btn btn-primary btn-lg span-2" type="submit">Ingresar a mi cuenta</button>
        </form>

        <p class="auth-foot">¿No tienes cuenta? <a href="registro.php">Crea tu perfil</a>.</p>
    </section>
</main>

<script src="ui.js" defer></script>
</body>
</html>
