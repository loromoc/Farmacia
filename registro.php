<?php

declare(strict_types=1);
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion_segura();

$mensaje = '';
$valores = [
    'nombre' => '',
    'correo' => '',
    'telefono' => '',
    'calle' => '',
    'numero_ext' => '',
    'numero_int' => '',
    'colonia' => '',
    'codigo_postal' => '',
    'municipio' => '',
    'estado_destino' => '',
    'referencias' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf($_POST['csrf_token'] ?? null);

    foreach ($valores as $campo => $_) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    $nombre = $valores['nombre'];
    $correo = normalizar_correo($valores['correo']);
    $password = (string) ($_POST['password'] ?? '');
    $confirmar = (string) ($_POST['confirmar_password'] ?? '');
    $direccion = datos_direccion_desde_post($_POST);
    $guardarDireccion = isset($_POST['agregar_direccion']);

    try {
        if (strlen($nombre) < 3 || strlen($nombre) > 120) {
            throw new RuntimeException('Escribe un nombre válido.');
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Escribe un correo electrónico válido.');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
        }
        if ($password !== $confirmar) {
            throw new RuntimeException('Las contraseñas no coinciden.');
        }
        if ($guardarDireccion) {
            validar_direccion_envio($direccion, true);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $conexion->begin_transaction();

        $stmt = $conexion->prepare(
            "INSERT INTO usuarios
            (nombre, correo, password, rol, telefono, calle, numero_ext, numero_int, colonia, codigo_postal, municipio, estado_destino, referencias)
            VALUES (?, ?, ?, 'cliente', ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $telefono = $guardarDireccion ? $direccion['telefono'] : null;
        $calle = $guardarDireccion ? $direccion['calle'] : null;
        $numeroExt = $guardarDireccion ? $direccion['numero_ext'] : null;
        $numeroInt = $guardarDireccion && $direccion['numero_int'] !== '' ? $direccion['numero_int'] : null;
        $colonia = $guardarDireccion ? $direccion['colonia'] : null;
        $cp = $guardarDireccion ? $direccion['codigo_postal'] : null;
        $municipio = $guardarDireccion ? $direccion['municipio'] : null;
        $estado = $guardarDireccion ? $direccion['estado_destino'] : null;
        $referencias = $guardarDireccion && $direccion['referencias'] !== '' ? $direccion['referencias'] : null;

        $stmt->bind_param(
            'ssssssssssss',
            $nombre,
            $correo,
            $hash,
            $telefono,
            $calle,
            $numeroExt,
            $numeroInt,
            $colonia,
            $cp,
            $municipio,
            $estado,
            $referencias
        );
        $stmt->execute();
        $conexion->commit();

        flash('success', $guardarDireccion
            ? 'Cuenta creada y dirección guardada. Ya puedes iniciar sesión.'
            : 'Cuenta creada. Podrás agregar tu dirección desde Mi perfil.');
        header('Location: login.php');
        exit;
    } catch (mysqli_sql_exception $e) {
        try { $conexion->rollback(); } catch (Throwable $ignore) {}
        $mensaje = $e->getCode() === 1062 ? 'Ese correo ya está registrado.' : 'No fue posible crear la cuenta.';
    } catch (Throwable $e) {
        try { $conexion->rollback(); } catch (Throwable $ignore) {}
        $mensaje = $e->getMessage();
    }

    $valores['correo'] = $correo;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php"><span class="brand-mark">✚</span><span><?= e(APP_NAME) ?></span></a>
    <nav><a href="index.php">Inicio</a><a class="nav-cta" href="login.php">Iniciar sesión</a></nav>
</header>

<main class="auth-page auth-page-wide">
    <section class="auth-intro">
        <p class="eyebrow">Tu cuenta</p>
        <h1>Compra más rápido en tus próximos pedidos</h1>
        <p>Guarda tus datos y, si quieres, tu dirección principal. Después podrás editarla desde <strong>Mi perfil</strong>.</p>
        <div class="feature-stack">
            <span>✓ Dirección precargada en checkout</span>
            <span>✓ Historial de pedidos</span>
            <span>✓ Seguimiento de pagos y envíos</span>
        </div>
    </section>

    <section class="auth-card auth-card-wide">
        <div class="section-heading compact">
            <div><p class="eyebrow">Registro</p><h2>Crear perfil</h2></div>
        </div>

        <?php if ($mensaje): ?><div class="alert alert-error"><?= e($mensaje) ?></div><?php endif; ?>

        <form method="post" class="form-grid" id="registro-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <label>Nombre completo
                <input name="nombre" maxlength="120" autocomplete="name" value="<?= e($valores['nombre']) ?>" required>
            </label>
            <label>Correo electrónico
                <input type="email" name="correo" maxlength="190" autocomplete="email" value="<?= e($valores['correo']) ?>" required>
            </label>
            <label>Contraseña
                <input type="password" name="password" minlength="8" autocomplete="new-password" required>
            </label>
            <label>Confirmar contraseña
                <input type="password" name="confirmar_password" minlength="8" autocomplete="new-password" required>
            </label>

            <div class="span-2 optional-section">
                <label class="switch-row">
                    <span>
                        <strong>Guardar dirección de envío ahora</strong>
                        <small>Opcional. También puedes hacerlo después desde Mi perfil.</small>
                    </span>
                    <input id="agregar-direccion" type="checkbox" name="agregar_direccion" value="1" <?= isset($_POST['agregar_direccion']) ? 'checked' : '' ?>>
                </label>
            </div>

            <div id="registro-direccion" class="span-2 address-fields <?= isset($_POST['agregar_direccion']) ? '' : 'is-collapsed' ?>">
                <div class="address-section-title">
                    <span class="address-icon">⌂</span>
                    <div><strong>Dirección principal</strong><small>La usaremos para precargar tus futuros envíos.</small></div>
                </div>
                <div class="form-grid nested-grid">
                    <label>Teléfono
                        <input name="telefono" maxlength="30" inputmode="tel" autocomplete="tel" value="<?= e($valores['telefono']) ?>" data-address-required>
                    </label>
                    <label>Código postal
                        <input name="codigo_postal" maxlength="5" inputmode="numeric" pattern="\d{5}" autocomplete="postal-code" value="<?= e($valores['codigo_postal']) ?>" data-address-required>
                    </label>
                    <label class="span-2">Calle
                        <input name="calle" maxlength="160" autocomplete="address-line1" value="<?= e($valores['calle']) ?>" data-address-required>
                    </label>
                    <label>Número exterior
                        <input name="numero_ext" maxlength="30" value="<?= e($valores['numero_ext']) ?>" data-address-required>
                    </label>
                    <label>Número interior
                        <input name="numero_int" maxlength="30" value="<?= e($valores['numero_int']) ?>">
                    </label>
                    <label>Colonia
                        <input name="colonia" maxlength="120" autocomplete="address-line2" value="<?= e($valores['colonia']) ?>" data-address-required>
                    </label>
                    <label>Municipio / alcaldía
                        <input name="municipio" maxlength="120" value="<?= e($valores['municipio']) ?>" data-address-required>
                    </label>
                    <label class="span-2">Estado
                        <input name="estado_destino" maxlength="120" autocomplete="address-level1" value="<?= e($valores['estado_destino']) ?>" data-address-required>
                    </label>
                    <label class="span-2">Referencias
                        <textarea name="referencias" maxlength="255" placeholder="Entre calles, color de fachada, indicaciones de acceso..."><?= e($valores['referencias']) ?></textarea>
                    </label>
                </div>
            </div>

            <button class="btn btn-primary span-2 btn-lg" type="submit">Crear mi cuenta</button>
        </form>
        <p class="auth-foot">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>.</p>
    </section>
</main>

<script src="ui.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const check = document.getElementById('agregar-direccion');
    const box = document.getElementById('registro-direccion');
    const required = box.querySelectorAll('[data-address-required]');
    const sync = () => {
        box.classList.toggle('is-collapsed', !check.checked);
        required.forEach(input => input.required = check.checked);
    };
    check.addEventListener('change', sync);
    sync();
});
</script>
</body>
</html>
