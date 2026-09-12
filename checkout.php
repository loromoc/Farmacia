<?php

declare(strict_types=1);
require_once __DIR__ . '/proteger_cliente.php';
require_once __DIR__ . '/conexion.php';

$usuario = usuario_actual();
$perfil = obtener_perfil_usuario($conexion, (int) $usuario['id']) ?: [];
$flashes = obtener_flashes();
$direccionLista = porcentaje_perfil_direccion($perfil) === 100;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php"><span class="brand-mark">✚</span><span><?= e(APP_NAME) ?></span></a>
    <nav>
        <a href="index.php#carrito">Carrito</a>
        <a href="mis_pedidos.php">Mis pedidos</a>
        <a href="perfil.php">Mi perfil</a>
    </nav>
</header>

<main class="checkout-main checkout-shell">
    <div class="checkout-stepper" aria-label="Progreso de compra">
        <div class="step done"><span>1</span><div><strong>Carrito</strong><small>Productos</small></div></div>
        <div class="step-line"></div>
        <div class="step active"><span>2</span><div><strong>Envío</strong><small>Dirección</small></div></div>
        <div class="step-line"></div>
        <div class="step"><span>3</span><div><strong>Pago</strong><small>Finalizar</small></div></div>
    </div>

    <section class="checkout-card">
        <div class="section-heading compact">
            <div>
                <p class="eyebrow">Entrega nacional</p>
                <h1>Dirección y envío</h1>
                <p class="muted">Confirma dónde quieres recibir tu pedido y selecciona el tipo de envío.</p>
            </div>
            <?php if ($direccionLista): ?>
                <span class="saved-address-badge">✓ Dirección de perfil cargada</span>
            <?php else: ?>
                <a class="btn btn-soft btn-smallish" href="perfil.php">Completar perfil</a>
            <?php endif; ?>
        </div>

        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div>
        <?php endforeach; ?>
        <div id="checkout-error" class="alert alert-error hidden" role="alert"></div>

        <form id="checkout-form" action="crear_pedido.php" method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="cart_json" id="cart_json">
            <input type="hidden" name="envio_metodo" id="envio_metodo" value="estandar">

            <div class="span-2 form-section-header">
                <span class="card-icon">⌂</span>
                <div><strong>Datos de entrega</strong><small>Los datos marcados son obligatorios.</small></div>
            </div>

            <label>Nombre de quien recibe
                <input name="nombre_recibe" maxlength="120" autocomplete="name" value="<?= e($perfil['nombre'] ?? $usuario['nombre'] ?? '') ?>" required>
            </label>
            <label>Teléfono
                <input name="telefono" maxlength="30" inputmode="tel" autocomplete="tel" value="<?= e($perfil['telefono'] ?? '') ?>" required>
            </label>
            <label class="span-2">Calle
                <input name="calle" maxlength="160" autocomplete="address-line1" value="<?= e($perfil['calle'] ?? '') ?>" required>
            </label>
            <label>Número exterior
                <input name="numero_ext" maxlength="30" value="<?= e($perfil['numero_ext'] ?? '') ?>" required>
            </label>
            <label>Número interior
                <input name="numero_int" maxlength="30" value="<?= e($perfil['numero_int'] ?? '') ?>">
            </label>
            <label>Colonia
                <input name="colonia" maxlength="120" autocomplete="address-line2" value="<?= e($perfil['colonia'] ?? '') ?>" required>
            </label>
            <label>Código postal
                <input id="codigo_postal" name="codigo_postal" maxlength="5" inputmode="numeric" pattern="\d{5}" autocomplete="postal-code" value="<?= e($perfil['codigo_postal'] ?? '') ?>" required>
            </label>
            <label>Municipio / alcaldía
                <input name="municipio" maxlength="120" value="<?= e($perfil['municipio'] ?? '') ?>" required>
            </label>
            <label>Estado
                <input name="estado_destino" maxlength="120" autocomplete="address-level1" value="<?= e($perfil['estado_destino'] ?? '') ?>" required>
            </label>
            <label class="span-2">Referencias
                <textarea name="referencias" maxlength="255" placeholder="Entre calles, color de fachada, indicaciones para encontrar el domicilio..."><?= e($perfil['referencias'] ?? '') ?></textarea>
            </label>

            <label class="span-2 save-address-row">
                <input type="checkbox" name="guardar_direccion" value="1" checked>
                <span><strong>Guardar estos datos como mi dirección principal</strong><small>Si haces un cambio aquí, también se actualizará en Mi perfil.</small></span>
            </label>

            <div class="span-2 shipping-options">
                <div class="form-section-header">
                    <span class="card-icon">↗</span>
                    <div><strong>Opciones de envío</strong><small>La tarifa se calcula en el servidor según el peso del carrito.</small></div>
                </div>
                <div id="shipping-options"><p class="muted">Escribe un código postal para cotizar.</p></div>
            </div>

            <div class="span-2 checkout-summary elevated-summary">
                <div class="form-section-header">
                    <span class="card-icon">▤</span>
                    <div><strong>Resumen del pedido</strong><small>Revisa el total antes de continuar.</small></div>
                </div>
                <div id="checkout-items"></div>
                <div class="summary-line"><span>Subtotal</span><strong id="checkout-subtotal">$0.00</strong></div>
                <div class="summary-line"><span>Envío</span><strong id="checkout-shipping">Por calcular</strong></div>
                <div class="summary-line total"><span>Total estimado</span><strong id="checkout-total">$0.00</strong></div>
                <p class="muted">El precio final, el stock y el envío se vuelven a validar en PHP antes de registrar el pedido.</p>
            </div>

            <button type="submit" id="create-order" class="btn btn-primary btn-lg span-2" disabled>
                Crear pedido y continuar al pago
            </button>
        </form>
    </section>
</main>

<script src="ui.js" defer></script>
<script src="checkout.js" defer></script>
</body>
</html>
