<?php

declare(strict_types=1);
require_once __DIR__ . '/conexion.php'; require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
if(MERCADOPAGO_ACCESS_TOKEN===''){http_response_code(503);echo json_encode(['ok'=>false]);exit;}
$payload=json_decode(file_get_contents('php://input'),true) ?: [];
$type=(string)($payload['type']??$_GET['type']??$_GET['topic']??'');
$paymentId=$payload['data']['id']??$_GET['data_id']??$_GET['id']??null;
if($type!=='payment'||!$paymentId){echo json_encode(['ok'=>true]);exit;}
$ch=curl_init('https://api.mercadopago.com/v1/payments/'.rawurlencode((string)$paymentId));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.MERCADOPAGO_ACCESS_TOKEN],CURLOPT_TIMEOUT=>15]);$body=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
if($body===false||$http!==200){http_response_code(502);echo json_encode(['ok'=>false]);exit;}
$data=json_decode($body,true);$pedidoId=filter_var($data['external_reference']??null,FILTER_VALIDATE_INT);$status=(string)($data['status']??'');$amount=(float)($data['transaction_amount']??0);$currency=(string)($data['currency_id']??'');
if(!$pedidoId){echo json_encode(['ok'=>true]);exit;}
$stmt=$conexion->prepare('SELECT id,total,pago_estado FROM pedidos WHERE id=?');$stmt->bind_param('i',$pedidoId);$stmt->execute();$pedido=$stmt->get_result()->fetch_assoc();if(!$pedido){echo json_encode(['ok'=>true]);exit;}
if($currency!=='MXN'||abs($amount-(float)$pedido['total'])>0.01){error_log('Pago MP no coincide con pedido '.$pedidoId);http_response_code(409);echo json_encode(['ok'=>false]);exit;}
$conexion->begin_transaction();
try{
    $estadoPago=$status==='approved'?'aprobado':($status==='rejected'?'rechazado':'pendiente');$ref=(string)$paymentId;$json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $stmt=$conexion->prepare("INSERT INTO pagos (pedido_id,proveedor,referencia_externa,estado,monto,moneda,respuesta_json) VALUES (?,'mercadopago',?,?,?,?,?) ON DUPLICATE KEY UPDATE estado=VALUES(estado),monto=VALUES(monto),moneda=VALUES(moneda),respuesta_json=VALUES(respuesta_json)");$stmt->bind_param('issdss',$pedidoId,$ref,$status,$amount,$currency,$json);$stmt->execute();
    if($estadoPago==='aprobado'){$stmt=$conexion->prepare("UPDATE pedidos SET pago_estado='aprobado',estado=IF(estado='pendiente_pago','pagado',estado),pago_referencia=? WHERE id=?");$stmt->bind_param('si',$ref,$pedidoId);$stmt->execute();}
    elseif($estadoPago==='rechazado'){$stmt=$conexion->prepare("UPDATE pedidos SET pago_estado='rechazado',pago_referencia=? WHERE id=? AND pago_estado<>'aprobado'");$stmt->bind_param('si',$ref,$pedidoId);$stmt->execute();}
    $conexion->commit();echo json_encode(['ok'=>true]);
}catch(Throwable $e){$conexion->rollback();error_log('webhook MP: '.$e->getMessage());http_response_code(500);echo json_encode(['ok'=>false]);}
