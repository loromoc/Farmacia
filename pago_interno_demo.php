<?php

declare(strict_types=1);
require_once __DIR__ . '/proteger_cliente.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/config.php';

if (!PAGO_INTERNO_DEMO) {
    http_response_code(404);
    exit('Modo de pago interno no habilitado.');
}

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
    header('Location: pago_resultado.php?status=success&pedido=' . $id);
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago interno de demostración - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php"><span class="brand-mark">✚</span><span><?= e(APP_NAME) ?></span></a>
    <nav><a href="pago.php?id=<?= (int) $pedido['id'] ?>">Volver al pago</a><a href="perfil.php">Mi perfil</a></nav>
</header>
<main class="checkout-main">
    <section class="checkout-card">
        <p class="eyebrow">Pasarela interna</p>
        <h1>Pago de demostración</h1>
        <div class="alert alert-warning"><strong>Solo demostración.</strong> No procesa dinero real. No introduzcas datos de una tarjeta real.</div>

        <div class="demo-card-preview" aria-hidden="true">
            <span>FARMACIA · DEMO</span>
            <strong>•••• •••• •••• 1111</strong>
            <small>PRUEBA · NO REAL</small>
        </div>

        <div class="checkout-summary">
            <div class="summary-line"><span>Pedido</span><strong><?= e($pedido['folio']) ?></strong></div>
            <div class="summary-line total"><span>Total de demostración</span><strong>$<?= number_format((float) $pedido['total'], 2) ?></strong></div>
        </div>

        <form class="form-grid" method="post" action="procesar_pago_demo.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="pedido_id" value="<?= (int) $pedido['id'] ?>">

            <label class="span-2">Nombre del titular de prueba
                <input name="titular" required maxlength="80" value="CLIENTE DE PRUEBA">
            </label>
            <label class="span-2">Número de tarjeta ficticia
                <input name="numero" inputmode="numeric" required value="4111 1111 1111 1111" aria-describedby="tarjeta-ayuda">
                <small id="tarjeta-ayuda" class="muted">La simulación solo acepta 4111 1111 1111 1111.</small>
            </label>
            <label>Vencimiento de prueba
                <input name="vencimiento" required value="12/30" maxlength="5">
            </label>
            <label>CVV ficticio
                <input name="cvv" inputmode="numeric" required value="123" maxlength="3">
            </label>
            <div class="span-2 checkout-actions">
                <button class="btn btn-primary" type="submit">Simular pago aprobado</button>
                <a class="btn" href="pago.php?id=<?= (int) $pedido['id'] ?>">Cancelar</a>
            </div>
        </form>
    </section>
</main>
<script src="ui.js" defer></script>
</body>
</html>
