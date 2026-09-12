<?php

declare(strict_types=1);
require_once __DIR__ . '/proteger_cliente.php';
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$uid = (int) usuario_actual()['id'];

if (!$id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Pedido inválido.']);
    exit;
}

$stmt = $conexion->prepare('SELECT id, folio, estado, pago_estado, pago_proveedor, pago_referencia FROM pedidos WHERE id=? AND usuario_id=?');
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();

if (!$pedido) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'mensaje' => 'Pedido no encontrado.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'id' => (int) $pedido['id'],
    'folio' => (string) $pedido['folio'],
    'estado' => (string) $pedido['estado'],
    'pago_estado' => (string) $pedido['pago_estado'],
    'pago_proveedor' => (string) $pedido['pago_proveedor'],
    'pago_referencia' => (string) ($pedido['pago_referencia'] ?? ''),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
