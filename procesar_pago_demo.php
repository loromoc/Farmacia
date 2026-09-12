<?php

declare(strict_types=1);
require_once __DIR__ . '/proteger_cliente.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/config.php';

if (!PAGO_INTERNO_DEMO) {
    http_response_code(404);
    exit('Modo de pago interno no habilitado.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verificar_csrf($_POST['csrf_token'] ?? null);

$id = filter_var($_POST['pedido_id'] ?? null, FILTER_VALIDATE_INT);
$uid = (int) usuario_actual()['id'];
$numero = preg_replace('/\D+/', '', (string) ($_POST['numero'] ?? ''));
$cvv = preg_replace('/\D+/', '', (string) ($_POST['cvv'] ?? ''));
$vencimiento = trim((string) ($_POST['vencimiento'] ?? ''));

if (!$id) {
    http_response_code(400);
    exit('Pedido inválido.');
}

if ($numero !== '4111111111111111' || $cvv !== '123' || $vencimiento !== '12/30') {
    flash('error', 'Los datos de demostración no coinciden. Usa únicamente los valores ficticios mostrados en la pantalla.');
    header('Location: pago_interno_demo.php?id=' . $id);
    exit;
}

$stmt = $conexion->prepare('SELECT id,total,pago_estado FROM pedidos WHERE id=? AND usuario_id=? FOR UPDATE');
$conexion->begin_transaction();
try {
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    if (!$pedido) {
        throw new RuntimeException('Pedido no encontrado.');
    }

    if ($pedido['pago_estado'] === 'aprobado') {
        $conexion->commit();
        header('Location: pago_resultado.php?status=success&pedido=' . $id);
        exit;
    }

    $referencia = 'DEMO-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $estado = 'approved';
    $monto = (float) $pedido['total'];
    $moneda = 'MXN';
    $respuesta = json_encode([
        'modo' => 'demostracion',
        'resultado' => 'approved',
        'nota' => 'No se procesó dinero real ni se almacenaron datos de tarjeta.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $stmtPago = $conexion->prepare("INSERT INTO pagos (pedido_id,proveedor,referencia_externa,estado,monto,moneda,respuesta_json) VALUES (?,'interno_demo',?,?,?,?,?)");
    $stmtPago->bind_param('issdss', $id, $referencia, $estado, $monto, $moneda, $respuesta);
    $stmtPago->execute();

    $stmtPedido = $conexion->prepare("UPDATE pedidos SET pago_estado='aprobado', estado=IF(estado='pendiente_pago','pagado',estado), pago_proveedor='interno_demo', pago_referencia=? WHERE id=?");
    $stmtPedido->bind_param('si', $referencia, $id);
    $stmtPedido->execute();

    $conexion->commit();
    header('Location: pago_resultado.php?status=success&pedido=' . $id . '&demo=1');
    exit;
} catch (Throwable $e) {
    $conexion->rollback();
    error_log('Pago demo: ' . $e->getMessage());
    flash('error', 'No fue posible completar la simulación de pago.');
    header('Location: pago_interno_demo.php?id=' . $id);
    exit;
}
