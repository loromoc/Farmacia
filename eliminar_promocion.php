<?php
declare(strict_types=1);
require_once __DIR__ . '/proteger_admin.php';
require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panel_admin.php#promociones');
    exit;
}
verificar_csrf($_POST['csrf_token'] ?? null);
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

try {
    if (!$id) throw new RuntimeException('Promoción inválida.');
    $stmt = $conexion->prepare('DELETE FROM promociones WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    flash('success', 'Promoción eliminada.');
} catch (Throwable $e) {
    flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'No fue posible eliminar la promoción.');
}
header('Location: panel_admin.php#promociones');
exit;
