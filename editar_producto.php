<?php

declare(strict_types=1);
require_once __DIR__ . '/proteger_admin.php';
require_once __DIR__ . '/conexion.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { header('Location: panel_admin.php'); exit; }
$stmt = $conexion->prepare('SELECT * FROM productos WHERE id = ?'); $stmt->bind_param('i', $id); $stmt->execute(); $p = $stmt->get_result()->fetch_assoc();
if (!$p) { http_response_code(404); exit('Producto no encontrado.'); }
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Editar producto</title><link rel="stylesheet" href="style.css"></head><body>
<header class="site-header"><a class="brand" href="panel_admin.php">Administración</a><nav><a href="panel_admin.php">Volver</a></nav></header>
<main class="admin-main"><section class="admin-panel"><h1>Editar <?= e($p['nombre']) ?></h1>
<form action="actualizar_producto.php" method="post" enctype="multipart/form-data" class="form-grid">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
<label>Nombre<input name="nombre" maxlength="160" value="<?= e($p['nombre']) ?>" required></label><label>Categoría<input name="categoria" maxlength="100" value="<?= e($p['categoria']) ?>" required></label>
<label>Principio activo<input name="principio_activo" value="<?= e($p['principio_activo']) ?>"></label><label>Presentación<input name="presentacion" value="<?= e($p['presentacion']) ?>"></label>
<label class="span-2">Descripción<textarea name="descripcion" required><?= e($p['descripcion']) ?></textarea></label>
<label>Precio<input type="number" step="0.01" min="0.01" name="precio" value="<?= e($p['precio']) ?>" required></label><label>Stock<input type="number" min="0" name="stock" value="<?= (int)$p['stock'] ?>" required></label>
<label>Peso kg<input type="number" step="0.001" min="0.05" name="peso_kg" value="<?= e($p['peso_kg']) ?>" required></label><label>Alto cm<input type="number" step="0.1" min="0" name="alto_cm" value="<?= e($p['alto_cm']) ?>"></label><label>Ancho cm<input type="number" step="0.1" min="0" name="ancho_cm" value="<?= e($p['ancho_cm']) ?>"></label><label>Largo cm<input type="number" step="0.1" min="0" name="largo_cm" value="<?= e($p['largo_cm']) ?>"></label>
<label class="span-2">Cambiar imagen (opcional)<input type="file" name="imagen" accept="image/jpeg,image/png,image/webp"></label>
<label class="check-label"><input type="checkbox" name="requiere_receta" value="1" <?= (int)$p['requiere_receta'] ? 'checked' : '' ?>> Requiere validación/receta</label><label class="check-label"><input type="checkbox" name="envio_permitido" value="1" <?= (int)$p['envio_permitido'] ? 'checked' : '' ?>> Permitir envío nacional</label><label class="check-label"><input type="checkbox" name="activo" value="1" <?= (int)$p['activo'] ? 'checked' : '' ?>> Producto activo</label>
<button class="btn btn-primary span-2" type="submit">Guardar cambios</button></form></section></main><script src="ui.js" defer></script>
</body></html>
