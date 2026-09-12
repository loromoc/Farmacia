<?php

declare(strict_types=1);

require_once __DIR__ . '/proteger_cliente.php';
require_once __DIR__ . '/conexion.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$uid = (int) usuario_actual()['id'];

if (!$id) {
    header('Location: mis_pedidos.php');
    exit;
}

$stmt = $conexion->prepare('SELECT * FROM pedidos WHERE id=? AND usuario_id=?');
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();

if (!$p) {
    http_response_code(404);
    exit('Pedido no encontrado.');
}

$stmt = $conexion->prepare('SELECT * FROM pedido_detalles WHERE pedido_id=? ORDER BY id');
$stmt->bind_param('i', $id);
$stmt->execute();
$detalles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pasos = [
    'pendiente_pago' => 0,
    'pagado' => 1,
    'preparando' => 2,
    'enviado' => 3,
    'entregado' => 4,
];
$indiceActual = $pasos[$p['estado']] ?? 0;
$cancelado = $p['estado'] === 'cancelado';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($p['folio']) ?> - <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php"><span class="brand-mark">✚</span><span><?= e(APP_NAME) ?></span></a>
    <nav>
        <a href="index.php">Tienda</a>
        <a href="mis_pedidos.php">Mis pedidos</a>
        <a href="perfil.php">Mi perfil</a>
    </nav>
</header>

<main class="checkout-main order-page">
<section class="checkout-card">
    <div class="order-title">
        <div>
            <p class="eyebrow">Pedido</p>
            <h1><?= e($p['folio']) ?></h1>
            <p class="muted">Creado el <?= e(date('d/m/Y H:i', strtotime($p['creado_en']))) ?></p>
        </div>
        <span class="status status-<?= e($p['estado']) ?>"><?= e(ucfirst(str_replace('_',' ',$p['estado']))) ?></span>
    </div>

    <?php if ($cancelado): ?>
        <div class="alert alert-error">Este pedido fue cancelado.</div>
    <?php else: ?>
        <div class="order-progress" aria-label="Seguimiento del pedido">
            <?php foreach (['Pedido', 'Pagado', 'Preparando', 'Enviado', 'Entregado'] as $i => $label): ?>
                <div class="order-progress-step <?= $i <= $indiceActual ? 'done' : '' ?> <?= $i === $indiceActual ? 'current' : '' ?>">
                    <span><?= $i < $indiceActual ? '✓' : ($i + 1) ?></span>
                    <small><?= e($label) ?></small>
                </div>
                <?php if ($i < 4): ?><i class="<?= $i < $indiceActual ? 'done' : '' ?>"></i><?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="order-details">
        <div>
            <h2>Productos</h2>
            <?php foreach ($detalles as $d): ?>
                <div class="summary-line order-product-line">
                    <span>
                        <strong><?= e($d['producto_nombre']) ?> × <?= (int) $d['cantidad'] ?></strong>
                        <?php if ((float)($d['descuento_unitario'] ?? 0) > 0): ?>
                            <small>
                                <?= e($d['promocion_nombre'] ?: 'Promoción') ?> ·
                                precio normal $<?= number_format((float)($d['precio_original'] ?? $d['precio_unitario']), 2) ?>
                            </small>
                        <?php endif; ?>
                    </span>
                    <strong>$<?= number_format((float) $d['total_linea'], 2) ?></strong>
                </div>
            <?php endforeach; ?>
            <hr>
            <div class="summary-line"><span>Subtotal</span><strong>$<?= number_format((float) $p['subtotal'], 2) ?></strong></div>
            <div class="summary-line"><span>Envío</span><strong>$<?= number_format((float) $p['costo_envio'], 2) ?></strong></div>
            <div class="summary-line total"><span>Total</span><strong>$<?= number_format((float) $p['total'], 2) ?></strong></div>
        </div>

        <div>
            <h2>Entrega</h2>
            <p>
                <?= e($p['nombre_recibe']) ?><br>
                <?= e($p['calle'] . ' ' . $p['numero_ext'] . ($p['numero_int'] ? ' Int. ' . $p['numero_int'] : '')) ?><br>
                <?= e($p['colonia']) ?>, CP <?= e($p['codigo_postal']) ?><br>
                <?= e($p['municipio'] . ', ' . $p['estado_destino']) ?><br>
                <?= e($p['telefono']) ?>
            </p>
            <p>
                <strong>Método:</strong> <?= e(ucfirst($p['envio_metodo'])) ?><br>
                <strong>Guía:</strong> <?= e($p['guia'] ?: 'Pendiente') ?>
            </p>

            <h2>Pago</h2>
            <p>
                <span class="status status-<?= e($p['pago_estado']) ?>"><?= e($p['pago_estado']) ?></span><br>
                <strong>Proveedor:</strong> <?= e($p['pago_proveedor'] ?: 'Pendiente') ?><br>
                <strong>Referencia:</strong> <?= e($p['pago_referencia'] ?: 'Pendiente') ?>
            </p>
        </div>
    </div>

    <div class="checkout-actions">
        <?php if ($p['pago_estado'] !== 'aprobado' && !$cancelado): ?>
            <a class="btn btn-primary" href="pago.php?id=<?= (int) $p['id'] ?>">Continuar al pago</a>
        <?php endif; ?>
        <a class="btn" href="mis_pedidos.php">Volver a mis pedidos</a>
    </div>
</section>
</main>

<script>
if (new URLSearchParams(location.search).get('limpiar') === '1') {
    localStorage.removeItem('farmacia_cart_v2');
}
</script>
<script src="ui.js" defer></script>
</body>
</html>
