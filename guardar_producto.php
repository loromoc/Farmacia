<?php

declare(strict_types=1);
require_once __DIR__ . '/proteger_admin.php';
require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: panel_admin.php'); exit; }
verificar_csrf($_POST['csrf_token'] ?? null);

try {
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $categoria = trim((string)($_POST['categoria'] ?? ''));
    $principio = trim((string)($_POST['principio_activo'] ?? '')) ?: null;
    $presentacion = trim((string)($_POST['presentacion'] ?? '')) ?: null;
    $precio = filter_var($_POST['precio'] ?? null, FILTER_VALIDATE_FLOAT);
    $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
    $peso = filter_var($_POST['peso_kg'] ?? null, FILTER_VALIDATE_FLOAT);
    $alto = ($_POST['alto_cm'] ?? '') !== '' ? (float)$_POST['alto_cm'] : null;
    $ancho = ($_POST['ancho_cm'] ?? '') !== '' ? (float)$_POST['ancho_cm'] : null;
    $largo = ($_POST['largo_cm'] ?? '') !== '' ? (float)$_POST['largo_cm'] : null;
    $requiere = isset($_POST['requiere_receta']) ? 1 : 0;
    $envio = isset($_POST['envio_permitido']) ? 1 : 0;

    if ($nombre === '' || $descripcion === '' || $categoria === '' || $precio === false || $precio <= 0 || $stock === false || $stock < 0 || $peso === false || $peso <= 0) {
        throw new RuntimeException('Revisa nombre, descripción, categoría, precio, stock y peso.');
    }

    $imagen = guardar_imagen_subida($_FILES['imagen'] ?? []);
    $stmt = $conexion->prepare('INSERT INTO productos (nombre, descripcion, categoria, principio_activo, presentacion, precio, stock, imagen, requiere_receta, envio_permitido, peso_kg, alto_cm, ancho_cm, largo_cm) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $types = 'sssssdisiidddd';
    $stmt->bind_param($types, $nombre, $descripcion, $categoria, $principio, $presentacion, $precio, $stock, $imagen, $requiere, $envio, $peso, $alto, $ancho, $largo);
    $stmt->execute();
    flash('success', 'Producto guardado correctamente.');
} catch (Throwable $e) {
    if (!empty($imagen)) eliminar_imagen_local($imagen);
    error_log('guardar_producto: ' . $e->getMessage());
    flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'No fue posible guardar el producto.');
}
header('Location: panel_admin.php');
exit;
