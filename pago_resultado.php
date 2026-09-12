<?php

declare(strict_types=1);
require_once __DIR__ . '/proteger_cliente.php';
require_once __DIR__ . '/conexion.php';

$id = filter_input(INPUT_GET, 'pedido', FILTER_VALIDATE_INT);
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

// Si Mercado Pago nos devuelve payment_id, verificamos el pago directamente
// contra su API. Nunca confiamos solamente en ?status=success.
$paymentId = preg_replace('/\D+/', '', (string) ($_GET['payment_id'] ?? $_GET['collection_id'] ?? ''));
if ($paymentId !== '' && MERCADOPAGO_ACCESS_TOKEN !== '' && function_exists('curl_init') && $pedido['pago_estado'] !== 'aprobado') {
    $ch = curl_init('https://api.mercadopago.com/v1/payments/' . rawurlencode($paymentId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . MERCADOPAGO_ACCESS_TOKEN],
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body !== false && $http === 200) {
        $data = json_decode($body, true) ?: [];
        $referenciaPedido = (string) ($data['external_reference'] ?? '');
        $statusMp = (string) ($data['status'] ?? '');
        $montoMp = (float) ($data['transaction_amount'] ?? 0);
        $monedaMp = (string) ($data['currency_id'] ?? '');

        if ($referenciaPedido === (string) $id && $monedaMp === 'MXN' && abs($montoMp - (float) $pedido['total']) <= 0.01) {
            $conexion->begin_transaction();
            try {
                $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $stmtPago = $conexion->prepare("INSERT INTO pagos (pedido_id,proveedor,referencia_externa,estado,monto,moneda,respuesta_json) VALUES (?,'mercadopago',?,?,?,?,?) ON DUPLICATE KEY UPDATE estado=VALUES(estado),monto=VALUES(monto),moneda=VALUES(moneda),respuesta_json=VALUES(respuesta_json)");
                $stmtPago->bind_param('issdss', $id, $paymentId, $statusMp, $montoMp, $monedaMp, $json);
                $stmtPago->execute();

                if ($statusMp === 'approved') {
                    $stmtUp = $conexion->prepare("UPDATE pedidos SET pago_estado='aprobado',estado=IF(estado='pendiente_pago','pagado',estado),pago_proveedor='mercadopago',pago_referencia=? WHERE id=?");
                    $stmtUp->bind_param('si', $paymentId, $id);
                    $stmtUp->execute();
                } elseif ($statusMp === 'rejected') {
                    $stmtUp = $conexion->prepare("UPDATE pedidos SET pago_estado='rechazado',pago_proveedor='mercadopago',pago_referencia=? WHERE id=? AND pago_estado<>'aprobado'");
                    $stmtUp->bind_param('si', $paymentId, $id);
                    $stmtUp->execute();
                }
                $conexion->commit();

                $stmt = $conexion->prepare('SELECT * FROM pedidos WHERE id=? AND usuario_id=?');
                $stmt->bind_param('ii', $id, $uid);
                $stmt->execute();
                $pedido = $stmt->get_result()->fetch_assoc();
            } catch (Throwable $e) {
                $conexion->rollback();
                error_log('Verificación de retorno Mercado Pago: ' . $e->getMessage());
            }
        }
    }
}

$pagoEstado = (string) $pedido['pago_estado'];
$retorno = (string) ($_GET['status'] ?? 'pending');
$aprobado = $pagoEstado === 'aprobado';
$rechazado = $pagoEstado === 'rechazado' || $retorno === 'failure';

if ($aprobado) {
    $titulo = '¡Pago aprobado!';
    $texto = 'Tu pago fue confirmado correctamente. Ya comenzaremos a preparar tu pedido.';
} elseif ($rechazado) {
    $titulo = 'Pago no aprobado';
    $texto = 'La operación no se completó. Puedes volver a intentar el pago desde tu pedido.';
} else {
    $titulo = 'Estamos confirmando tu pago';
    $texto = 'Recibimos el regreso de la pasarela y estamos esperando la confirmación segura del pago.';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php"><span class="brand-mark">✚</span><span><?= e(APP_NAME) ?></span></a>
    <nav><a href="index.php">Tienda</a><a href="mis_pedidos.php">Mis pedidos</a><a href="perfil.php">Mi perfil</a></nav>
</header>
<main class="checkout-main">
    <section class="checkout-card payment-result <?= $aprobado ? 'payment-result-ok' : ($rechazado ? 'payment-result-error' : 'payment-result-pending') ?>">
        <div class="payment-result-icon" aria-hidden="true"><?= $aprobado ? '✓' : ($rechazado ? '×' : '…') ?></div>
        <p class="eyebrow">Estado del pago</p>
        <h1 id="resultado-titulo"><?= e($titulo) ?></h1>
        <p id="resultado-texto"><?= e($texto) ?></p>

        <div class="checkout-summary result-summary">
            <div class="summary-line"><span>Pedido</span><strong><?= e($pedido['folio']) ?></strong></div>
            <div class="summary-line"><span>Total</span><strong>$<?= number_format((float) $pedido['total'], 2) ?></strong></div>
            <div class="summary-line"><span>Pago</span><strong id="resultado-estado"><?= e($pagoEstado) ?></strong></div>
            <div class="summary-line"><span>Proveedor</span><strong><?= e($pedido['pago_proveedor'] ?: 'Pendiente') ?></strong></div>
            <?php if (!empty($pedido['pago_referencia'])): ?>
                <div class="summary-line"><span>Referencia</span><strong><?= e($pedido['pago_referencia']) ?></strong></div>
            <?php endif; ?>
        </div>

        <?php if (!$aprobado && !$rechazado): ?>
            <div class="alert alert-warning" id="verificando">Verificando confirmación del pago… esta pantalla se actualizará automáticamente.</div>
        <?php endif; ?>

        <div class="checkout-actions">
            <a class="btn btn-primary" href="pedido.php?id=<?= (int) $pedido['id'] ?>">Ver mi pedido</a>
            <?php if ($rechazado): ?>
                <a class="btn" href="pago.php?id=<?= (int) $pedido['id'] ?>">Intentar de nuevo</a>
            <?php endif; ?>
            <a class="btn" href="index.php">Volver a la tienda</a>
        </div>
    </section>
</main>
<?php if (!$aprobado && !$rechazado): ?>
<script>
(() => {
    const pedidoId = <?= (int) $pedido['id'] ?>;
    let intentos = 0;
    const maxIntentos = 20;

    const consultar = async () => {
        intentos++;
        try {
            const r = await fetch(`estado_pago.php?id=${pedidoId}`, {cache: 'no-store'});
            const data = await r.json();
            if (data.ok && data.pago_estado === 'aprobado') {
                location.reload();
                return;
            }
            if (data.ok && data.pago_estado === 'rechazado') {
                location.reload();
                return;
            }
        } catch (_) {}

        if (intentos < maxIntentos) {
            setTimeout(consultar, 2000);
        } else {
            const aviso = document.getElementById('verificando');
            if (aviso) aviso.textContent = 'La confirmación está tardando. Puedes abrir “Ver mi pedido” y actualizar más tarde.';
        }
    };
    setTimeout(consultar, 1500);
})();
</script>
<?php endif; ?>
<script src="ui.js" defer></script>
</body>
</html>
