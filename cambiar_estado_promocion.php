<?php

declare(strict_types=1);
require_once __DIR__ . '/proteger_admin.php';
require_once __DIR__ . '/conexion.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: panel_admin.php'); exit; }
verificar_csrf($_POST['csrf_token'] ?? null);
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) { flash('error','Promoción inválida.'); header('Location: panel_admin.php'); exit; }
$stmt = $conexion->prepare('UPDATE promociones SET activo = IF(activo=1,0,1) WHERE id=?');
$stmt->bind_param('i',$id); $stmt->execute();
flash('success','Estado de la promoción actualizado.');
header('Location: panel_admin.php');
