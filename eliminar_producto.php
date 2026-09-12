<?php

declare(strict_types=1);
require_once __DIR__ . '/proteger_admin.php'; require_once __DIR__ . '/conexion.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location:panel_admin.php');exit;} verificar_csrf($_POST['csrf_token']??null);
$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT); if(!$id){flash('error','Producto inválido.');header('Location:panel_admin.php');exit;}
$stmt=$conexion->prepare('SELECT imagen FROM productos WHERE id=?');$stmt->bind_param('i',$id);$stmt->execute();$p=$stmt->get_result()->fetch_assoc();
if(!$p){flash('error','Producto no encontrado.');header('Location:panel_admin.php');exit;}
try{$stmt=$conexion->prepare('DELETE FROM productos WHERE id=?');$stmt->bind_param('i',$id);$stmt->execute();eliminar_imagen_local($p['imagen']);flash('success','Producto eliminado.');}
catch(Throwable $e){error_log('eliminar_producto: '.$e->getMessage());flash('error','No se puede eliminar si ya forma parte de un pedido; desactívalo desde Editar.');}
header('Location: panel_admin.php');exit;
