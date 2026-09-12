<?php

declare(strict_types=1);
require_once __DIR__ . '/conexion.php'; require_once __DIR__ . '/funciones.php'; iniciar_sesion_segura();
header('Content-Type: application/json; charset=utf-8');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['error'=>'Método no permitido']);exit;}
try{
    $data=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
    verificar_csrf($data['csrf_token']??null);
    $cp=trim((string)($data['codigo_postal']??'')); if(!validar_cp_mexico($cp)) throw new RuntimeException('Escribe un código postal mexicano de 5 dígitos.');
    $carrito=is_array($data['carrito']??null)?$data['carrito']:[]; $calc=procesar_carrito($conexion,$carrito,false);
    $opciones=[tarifa_envio_manual($calc['peso_kg'],'estandar'),tarifa_envio_manual($calc['peso_kg'],'express')];
    echo json_encode(['subtotal'=>$calc['subtotal'],'peso_kg'=>$calc['peso_kg'],'detalles'=>$calc['detalles'],'opciones'=>$opciones],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(422);echo json_encode(['error'=>$e instanceof RuntimeException?$e->getMessage():'No fue posible cotizar el envío.'],JSON_UNESCAPED_UNICODE);}
