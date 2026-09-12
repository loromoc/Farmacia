<?php

declare(strict_types=1);
require_once __DIR__ . '/proteger_cliente.php'; require_once __DIR__ . '/conexion.php'; require_once __DIR__ . '/config.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT); $uid=(int)usuario_actual()['id'];
if(!$id){header('Location:mis_pedidos.php');exit;}
$stmt=$conexion->prepare('SELECT * FROM pedidos WHERE id=? AND usuario_id=?');$stmt->bind_param('ii',$id,$uid);$stmt->execute();$pedido=$stmt->get_result()->fetch_assoc();
if(!$pedido){http_response_code(404);exit('Pedido no encontrado.');}
if($pedido['pago_estado']==='aprobado'){header('Location: pedido.php?id='.$id);exit;}

$error='';
if(MERCADOPAGO_ACCESS_TOKEN===''){
    flash('error', 'Mercado Pago todavía no está configurado. Agrega MERCADOPAGO_ACCESS_TOKEN en el servidor.');
    header('Location: pago.php?id='.$id);
    exit;
}elseif(!function_exists('curl_init')){
    flash('error', 'La extensión cURL de PHP no está habilitada. Actívala en php.ini para conectar con Mercado Pago.');
    header('Location: pago.php?id='.$id);
    exit;
}else{
    $payload=[
        'items'=>[[
            'id'=>(string)$pedido['folio'],
            'title'=>'Pedido '.$pedido['folio'].' - '.APP_NAME,
            'quantity'=>1,
            'currency_id'=>'MXN',
            'unit_price'=>(float)$pedido['total'],
        ]],
        'external_reference'=>(string)$pedido['id'],
        'back_urls'=>[
            'success'=>APP_URL.'/pago_resultado.php?status=success&pedido='.$pedido['id'],
            'pending'=>APP_URL.'/pago_resultado.php?status=pending&pedido='.$pedido['id'],
            'failure'=>APP_URL.'/pago_resultado.php?status=failure&pedido='.$pedido['id'],
        ],
        'auto_return'=>'approved',
        'notification_url'=>APP_URL.'/mercadopago_webhook.php',
        'statement_descriptor'=>'FARMACIA D LUPE',
    ];
    $ch=curl_init('https://api.mercadopago.com/checkout/preferences');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.MERCADOPAGO_ACCESS_TOKEN,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),CURLOPT_TIMEOUT=>20]);
    $body=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$curlError=curl_error($ch);curl_close($ch);
    if($body===false||$http<200||$http>=300){error_log('MercadoPago preference '.$http.' '.$curlError.' '.$body);$error='No fue posible iniciar el pago. Inténtalo de nuevo o contacta a la farmacia.';}
    else{
        $data=json_decode($body,true);$pref=$data['id']??null;$url=MERCADOPAGO_USE_SANDBOX?($data['sandbox_init_point']??$data['init_point']??null):($data['init_point']??null);
        if($pref&&$url){$stmt=$conexion->prepare('UPDATE pedidos SET pago_preferencia=? WHERE id=?');$stmt->bind_param('si',$pref,$id);$stmt->execute();header('Location: '.$url);exit;}
        $error='Mercado Pago no devolvió un enlace de pago válido.';
    }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Error al iniciar pago</title><link rel="stylesheet" href="style.css"></head><body><main class="checkout-main"><section class="checkout-card"><h1>No se pudo iniciar el pago</h1><div class="alert alert-error"><?= e($error) ?></div><a class="btn btn-primary" href="pago.php?id=<?= (int)$pedido['id'] ?>">Volver al pago</a></section></main><script src="ui.js" defer></script>
</body></html>
