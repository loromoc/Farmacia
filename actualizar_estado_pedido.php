<?php

declare(strict_types=1);

require_once __DIR__ . '/proteger_admin.php';
require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panel_pedidos.php');
    exit;
}

verificar_csrf($_POST['csrf_token'] ?? null);

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$nuevo = (string) ($_POST['estado'] ?? '');
$guia = trim((string) ($_POST['guia'] ?? '')) ?: null;
$validos = ['pendiente_pago', 'pagado', 'preparando', 'enviado', 'entregado', 'cancelado'];

if (!$id || !in_array($nuevo, $validos, true)) {
    flash('error', 'Datos de pedido inválidos.');
    header('Location: panel_pedidos.php');
    exit;
}

$pedidoNotificacion = null;
$estadoAnterior = '';

$conexion->begin_transaction();

try {
    $stmt = $conexion->prepare(
        'SELECT p.*, u.nombre cliente, u.correo, u.notificar_email, u.notificar_whatsapp
         FROM pedidos p
         JOIN usuarios u ON u.id=p.usuario_id
         WHERE p.id=?
         FOR UPDATE'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();

    if (!$pedido) {
        throw new RuntimeException('Pedido no encontrado.');
    }

    $estadoAnterior = (string) $pedido['estado'];

    if ($estadoAnterior === 'cancelado') {
        throw new RuntimeException('Un pedido cancelado no puede modificarse desde este panel.');
    }

    if ($nuevo === 'cancelado' && $estadoAnterior !== 'cancelado') {
        $stmt = $conexion->prepare(
            'SELECT producto_id, cantidad
             FROM pedido_detalles
             WHERE pedido_id=? AND producto_id IS NOT NULL'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $det = $stmt->get_result();

        $stock = $conexion->prepare('UPDATE productos SET stock=stock+? WHERE id=?');
        while ($d = $det->fetch_assoc()) {
            $qty = (int) $d['cantidad'];
            $pid = (int) $d['producto_id'];
            $stock->bind_param('ii', $qty, $pid);
            $stock->execute();
        }
    }

    $stmt = $conexion->prepare('UPDATE pedidos SET estado=?, guia=? WHERE id=?');
    $stmt->bind_param('ssi', $nuevo, $guia, $id);
    $stmt->execute();

    $conexion->commit();

    $pedido['estado'] = $nuevo;
    $pedido['guia'] = $guia;
    $pedidoNotificacion = $pedido;

    $mensaje = 'Pedido actualizado.';
    $estadosNotificables = ['pagado', 'preparando', 'enviado', 'entregado', 'cancelado'];

    if ($estadoAnterior !== $nuevo
        && in_array($nuevo, $estadosNotificables, true)
        && (int) ($pedido['notificar_email'] ?? 0) === 1
    ) {
        $enviado = enviar_correo_estado_pedido($conexion, $pedido, $nuevo);
        if ($enviado) {
            $mensaje .= ' Correo enviado al cliente.';
        } elseif (defined('SMTP_ENABLED') && SMTP_ENABLED) {
            $mensaje .= ' El cambio se guardó, pero el correo no pudo enviarse.';
        }
    }

    flash('success', $mensaje);
} catch (Throwable $e) {
    try {
        $conexion->rollback();
    } catch (Throwable $ignore) {
    }

    flash(
        'error',
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'No fue posible actualizar el pedido.'
    );
}

header('Location: panel_pedidos.php');
exit;
