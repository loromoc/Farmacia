<?php

declare(strict_types=1);
require_once __DIR__ . '/proteger_cliente.php';
require_once __DIR__ . '/conexion.php';

$usuarioSesion = usuario_actual();
$uid = (int) $usuarioSesion['id'];
$mensaje = '';
$tipoMensaje = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf($_POST['csrf_token'] ?? null);

    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $correo = normalizar_correo((string) ($_POST['correo'] ?? ''));
    $direccion = datos_direccion_desde_post($_POST);
    $passwordActual = (string) ($_POST['password_actual'] ?? '');
    $passwordNueva = (string) ($_POST['password_nueva'] ?? '');
    $notificarEmail = isset($_POST['notificar_email']) ? 1 : 0;
    $notificarWhatsapp = isset($_POST['notificar_whatsapp']) ? 1 : 0;

    try {
        if (strlen($nombre) < 3 || strlen($nombre) > 120) {
            throw new RuntimeException('Escribe un nombre válido.');
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Escribe un correo electrónico válido.');
        }

        $direccionCompleta = direccion_tiene_datos($direccion);
        if ($direccionCompleta) {
            validar_direccion_envio($direccion, true);
        } elseif ($direccion['telefono'] !== '' && strlen((string) preg_replace('/\D+/', '', $direccion['telefono'])) < 10) {
            throw new RuntimeException('Escribe un teléfono válido.');
        }
        if ($notificarWhatsapp && strlen((string) preg_replace('/\D+/', '', $direccion['telefono'])) < 10) {
            throw new RuntimeException('Agrega un teléfono válido antes de activar avisos por WhatsApp.');
        }

        $conexion->begin_transaction();

        $stmt = $conexion->prepare('SELECT password FROM usuarios WHERE id=? FOR UPDATE');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $actual = $stmt->get_result()->fetch_assoc();
        if (!$actual) {
            throw new RuntimeException('No se encontró el perfil.');
        }

        if ($passwordNueva !== '') {
            if (strlen($passwordNueva) < 8) {
                throw new RuntimeException('La nueva contraseña debe tener al menos 8 caracteres.');
            }
            if ($passwordActual === '' || !password_verify($passwordActual, $actual['password'])) {
                throw new RuntimeException('La contraseña actual no es correcta.');
            }
            $hash = password_hash($passwordNueva, PASSWORD_DEFAULT);
            $stmtPass = $conexion->prepare('UPDATE usuarios SET password=? WHERE id=?');
            $stmtPass->bind_param('si', $hash, $uid);
            $stmtPass->execute();
        }

        $numeroInt = $direccion['numero_int'] !== '' ? $direccion['numero_int'] : null;
        $referencias = $direccion['referencias'] !== '' ? $direccion['referencias'] : null;
        $telefono = $direccion['telefono'] !== '' ? $direccion['telefono'] : null;
        $calle = $direccion['calle'] !== '' ? $direccion['calle'] : null;
        $numeroExt = $direccion['numero_ext'] !== '' ? $direccion['numero_ext'] : null;
        $colonia = $direccion['colonia'] !== '' ? $direccion['colonia'] : null;
        $cp = $direccion['codigo_postal'] !== '' ? $direccion['codigo_postal'] : null;
        $municipio = $direccion['municipio'] !== '' ? $direccion['municipio'] : null;
        $estado = $direccion['estado_destino'] !== '' ? $direccion['estado_destino'] : null;

        $stmt = $conexion->prepare(
            'UPDATE usuarios
             SET nombre=?, correo=?, telefono=?, calle=?, numero_ext=?, numero_int=?, colonia=?, codigo_postal=?, municipio=?, estado_destino=?, referencias=?,
                 notificar_email=?, notificar_whatsapp=?
             WHERE id=?'
        );
        $stmt->bind_param(
            'sssssssssssiii',
            $nombre,
            $correo,
            $telefono,
            $calle,
            $numeroExt,
            $numeroInt,
            $colonia,
            $cp,
            $municipio,
            $estado,
            $referencias,
            $notificarEmail,
            $notificarWhatsapp,
            $uid
        );
        $stmt->execute();
        $conexion->commit();

        $_SESSION['nombre'] = $nombre;
        $_SESSION['correo'] = $correo;
        $mensaje = 'Tu perfil se actualizó correctamente.';
        $tipoMensaje = 'success';
    } catch (mysqli_sql_exception $e) {
        try { $conexion->rollback(); } catch (Throwable $ignore) {}
        $mensaje = $e->getCode() === 1062 ? 'Ese correo ya está registrado en otra cuenta.' : 'No fue posible actualizar el perfil.';
    } catch (Throwable $e) {
        try { $conexion->rollback(); } catch (Throwable $ignore) {}
        $mensaje = $e->getMessage();
    }
}

$perfil = obtener_perfil_usuario($conexion, $uid);
if (!$perfil) {
    http_response_code(404);
    exit('Perfil no encontrado.');
}
$porcentaje = porcentaje_perfil_direccion($perfil);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi perfil - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php"><span class="brand-mark">✚</span><span><?= e(APP_NAME) ?></span></a>
    <nav>
        <a href="index.php">Tienda</a>
        <a href="mis_pedidos.php">Mis pedidos</a>
        <a class="nav-active" href="perfil.php">Mi perfil</a>
        <form method="post" action="logout.php" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button class="link-button" type="submit">Cerrar sesión</button>
        </form>
    </nav>
</header>

<main class="profile-main">
    <section class="profile-hero">
        <div class="avatar-circle"><?= e(strtoupper(substr($perfil['nombre'], 0, 1))) ?></div>
        <div>
            <p class="eyebrow">Cuenta del cliente</p>
            <h1><?= e($perfil['nombre']) ?></h1>
            <p><?= e($perfil['correo']) ?></p>
        </div>
        <div class="profile-progress-card">
            <span>Dirección de envío</span>
            <strong><?= $porcentaje ?>%</strong>
            <div class="progress-track"><span style="width:<?= $porcentaje ?>%"></span></div>
            <small><?= $porcentaje === 100 ? 'Lista para usar en checkout.' : 'Completa tus datos para comprar más rápido.' ?></small>
        </div>
    </section>

    <?php if ($mensaje): ?><div class="alert alert-<?= e($tipoMensaje) ?> page-alert profile-alert"><?= e($mensaje) ?></div><?php endif; ?>

    <section class="profile-grid">
        <form method="post" class="profile-card form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="span-2 card-heading">
                <span class="card-icon">☺</span>
                <div><p class="eyebrow">Datos personales</p><h2>Información de la cuenta</h2></div>
            </div>

            <label>Nombre completo
                <input name="nombre" maxlength="120" autocomplete="name" value="<?= e($perfil['nombre']) ?>" required>
            </label>
            <label>Correo electrónico
                <input type="email" name="correo" maxlength="190" autocomplete="email" value="<?= e($perfil['correo']) ?>" required>
            </label>

            <div class="span-2 card-divider"></div>
            <div class="span-2 card-heading">
                <span class="card-icon">⌂</span>
                <div><p class="eyebrow">Entrega</p><h2>Dirección principal</h2><p class="muted">Se cargará automáticamente cuando hagas un pedido.</p></div>
            </div>

            <label>Teléfono
                <input name="telefono" maxlength="30" inputmode="tel" autocomplete="tel" value="<?= e($perfil['telefono'] ?? '') ?>">
            </label>
            <label>Código postal
                <input name="codigo_postal" maxlength="5" inputmode="numeric" pattern="\d{5}" autocomplete="postal-code" value="<?= e($perfil['codigo_postal'] ?? '') ?>">
            </label>
            <label class="span-2">Calle
                <input name="calle" maxlength="160" autocomplete="address-line1" value="<?= e($perfil['calle'] ?? '') ?>">
            </label>
            <label>Número exterior
                <input name="numero_ext" maxlength="30" value="<?= e($perfil['numero_ext'] ?? '') ?>">
            </label>
            <label>Número interior
                <input name="numero_int" maxlength="30" value="<?= e($perfil['numero_int'] ?? '') ?>">
            </label>
            <label>Colonia
                <input name="colonia" maxlength="120" autocomplete="address-line2" value="<?= e($perfil['colonia'] ?? '') ?>">
            </label>
            <label>Municipio / alcaldía
                <input name="municipio" maxlength="120" value="<?= e($perfil['municipio'] ?? '') ?>">
            </label>
            <label class="span-2">Estado
                <input name="estado_destino" maxlength="120" autocomplete="address-level1" value="<?= e($perfil['estado_destino'] ?? '') ?>">
            </label>
            <label class="span-2">Referencias
                <textarea name="referencias" maxlength="255" placeholder="Entre calles, color de fachada, indicaciones para la entrega..."><?= e($perfil['referencias'] ?? '') ?></textarea>
            </label>

            <div class="span-2 card-divider"></div>
            <div class="span-2 card-heading">
                <span class="card-icon">✉</span>
                <div><p class="eyebrow">Notificaciones</p><h2>Avisos de tus pedidos</h2><p class="muted">El correo puede enviarse automáticamente cuando SMTP esté configurado. WhatsApp queda como contacto autorizado y acceso rápido para el administrador.</p></div>
            </div>
            <label class="check-label">
                <input type="checkbox" name="notificar_email" value="1" <?= (int)($perfil['notificar_email'] ?? 1) ? 'checked' : '' ?>>
                Recibir avisos por correo electrónico
            </label>
            <label class="check-label">
                <input type="checkbox" name="notificar_whatsapp" value="1" <?= (int)($perfil['notificar_whatsapp'] ?? 0) ? 'checked' : '' ?>>
                Autorizo avisos de seguimiento por WhatsApp
            </label>

            <div class="span-2 card-divider"></div>
            <div class="span-2 card-heading">
                <span class="card-icon">●</span>
                <div><p class="eyebrow">Seguridad</p><h2>Cambiar contraseña</h2><p class="muted">Déjalo vacío si no quieres cambiarla.</p></div>
            </div>
            <label>Contraseña actual
                <input type="password" name="password_actual" autocomplete="current-password">
            </label>
            <label>Nueva contraseña
                <input type="password" name="password_nueva" minlength="8" autocomplete="new-password">
            </label>

            <button class="btn btn-primary btn-lg span-2" type="submit">Guardar cambios</button>
        </form>

        <aside class="profile-side">
            <div class="side-card">
                <span class="side-card-icon">⌂</span>
                <h3>Tu dirección en un clic</h3>
                <p>Al entrar al checkout, estos datos se llenarán automáticamente. Puedes modificarlos para un pedido sin perder tu dirección principal.</p>
                <a class="btn btn-soft" href="index.php#productos">Ir a comprar</a>
            </div>
            <div class="side-card">
                <span class="side-card-icon">▤</span>
                <h3>Pedidos</h3>
                <p>Consulta pagos, estado de preparación, envío y número de guía desde tu cuenta.</p>
                <a class="btn btn-soft" href="mis_pedidos.php">Ver mis pedidos</a>
            </div>
        </aside>
    </section>
</main>

<script src="ui.js" defer></script>
</body>
</html>
