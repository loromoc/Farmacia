<?php

declare(strict_types=1);
require_once __DIR__ . '/proteger_cliente.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$uid = (int) usuario_actual()['id'];
if (!$id) {
    header('Location: mis_pedidos.php');
    exit;
}

$stmt = $conexion->prepare('SELECT * FROM pedidos WHERE id=? AND usuario_id=?');
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
if (!$pedido) {
    http_response_code(404);
    exit('Pedido no encontrado.');
}

if ($pedido['pago_estado'] === 'aprobado') {
    header('Location: pedido.php?id=' . $id);
    exit;
}

$flashes = obtener_flashes();
$mercadoPagoListo = MERCADOPAGO_ACCESS_TOKEN !== '' && function_exists('curl_init');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php"><span class="brand-mark">✚</span><span><?= e(APP_NAME) ?></span></a>
    <nav><a href="index.php">Tienda</a><a href="mis_pedidos.php">Mis pedidos</a><a href="perfil.php">Mi perfil</a></nav>
</header>
<main class="checkout-main">
    <section class="checkout-card">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Pago</p>
                <h1><?= e($pedido['folio']) ?></h1>
            </div>
        </div>

        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div>
        <?php endforeach; ?>

        <div class="checkout-summary">
            <div class="summary-line"><span>Subtotal</span><strong>$<?= number_format((float)$pedido['subtotal'], 2) ?></strong></div>
            <div class="summary-line"><span>Envío <?= e($pedido['envio_metodo']) ?></span><strong>$<?= number_format((float)$pedido['costo_envio'], 2) ?></strong></div>
            <div class="summary-line total"><span>Total a pagar</span><strong>$<?= number_format((float)$pedido['total'], 2) ?></strong></div>
        </div>

        <div class="payment-box">
            <h2>Método de pago</h2>
            <p>El pedido ya quedó registrado. El siguiente paso es completar el pago.</p>

            <div class="payment-methods">
                <div class="payment-method">
                    <h3>Mercado Pago</h3>
                    <?php if ($mercadoPagoListo): ?>
                        <a class="btn btn-primary" href="pagar_pedido.php?id=<?= (int)$pedido['id'] ?>">Pagar con Mercado Pago</a>
                        <p class="muted">Serás redirigido al checkout seguro de Mercado Pago.</p>
                    <?php else: ?>
                        <div class="alert alert-warning">Mercado Pago todavía no está activado en este servidor.</div>
                    <?php endif; ?>
                </div>

                <?php if (PAGO_INTERNO_DEMO): ?>
                    <div class="payment-method">
                        <h3>Pago interno de demostración</h3>
                        <a class="btn" href="pago_interno_demo.php?id=<?= (int)$pedido['id'] ?>">Abrir pasarela interna</a>
                        <p class="muted">Solo para pruebas del proyecto. No cobra dinero real y no debe usarse con tarjetas reales.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="checkout-actions">
            <a class="btn" href="pedido.php?id=<?= (int)$pedido['id'] ?>">Ver pedido</a>
            <a class="btn" href="index.php">Volver a la tienda</a>
        </div>
    </section>
</main>
<script>localStorage.removeItem('farmacia_cart_v2');</script>
<script src="ui.js" defer></script>
</body>
</html>
