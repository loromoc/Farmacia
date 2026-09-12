<?php
declare(strict_types=1);

require_once __DIR__ . '/proteger_admin.php';
require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panel_admin.php#promociones');
    exit;
}

verificar_csrf($_POST['csrf_token'] ?? null);

try {
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
    $tipo = (string) ($_POST['tipo'] ?? '');
    $valor = (float) ($_POST['valor'] ?? 0);
    $productoRaw = trim((string) ($_POST['producto_id'] ?? ''));
    $productoId = $productoRaw === '' ? null : filter_var($productoRaw, FILTER_VALIDATE_INT);
    $destacada = isset($_POST['destacada']) ? 1 : 0;

    if (strlen($nombre) < 3 || strlen($nombre) > 140) {
        throw new RuntimeException('Escribe un nombre de promoción válido.');
    }
    if (!in_array($tipo, ['porcentaje', 'fijo'], true)) {
        throw new RuntimeException('Tipo de descuento inválido.');
    }
    if ($valor <= 0) {
        throw new RuntimeException('El descuento debe ser mayor que cero.');
    }
    if ($tipo === 'porcentaje' && $valor > 95) {
        throw new RuntimeException('El porcentaje máximo permitido es 95%.');
    }

    if ($productoId !== null) {
        if (!$productoId) {
            throw new RuntimeException('Producto inválido.');
        }
        $check = $conexion->prepare('SELECT id FROM productos WHERE id=? LIMIT 1');
        $check->bind_param('i', $productoId);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            throw new RuntimeException('El producto seleccionado no existe.');
        }
    }

    if (isset($_POST['solo_hoy'])) {
        $inicio = date('Y-m-d H:i:s');
        $fin = date('Y-m-d 23:59:59');
    } else {
        $inicioTs = strtotime(trim((string) ($_POST['fecha_inicio'] ?? '')));
        $finTs = strtotime(trim((string) ($_POST['fecha_fin'] ?? '')));
        if (!$inicioTs || !$finTs) {
            throw new RuntimeException('Selecciona una fecha de inicio y fin válida.');
        }
        if ($finTs <= $inicioTs) {
            throw new RuntimeException('La fecha de fin debe ser posterior al inicio.');
        }
        $inicio = date('Y-m-d H:i:s', $inicioTs);
        $fin = date('Y-m-d H:i:s', $finTs);
    }

    $descripcionDb = $descripcion !== '' ? $descripcion : null;
    $stmt = $conexion->prepare(
        'INSERT INTO promociones
        (nombre, descripcion, producto_id, tipo, valor, fecha_inicio, fecha_fin, activa, destacada)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)'
    );
    $stmt->bind_param(
        'ssisdssi',
        $nombre,
        $descripcionDb,
        $productoId,
        $tipo,
        $valor,
        $inicio,
        $fin,
        $destacada
    );
    $stmt->execute();

    flash('success', 'Promoción creada correctamente.');
} catch (Throwable $e) {
    $mensaje = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'No fue posible crear la promoción. Verifica la migración V7.';
    flash('error', $mensaje);
}

header('Location: panel_admin.php#promociones');
exit;
