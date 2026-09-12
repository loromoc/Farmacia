<?php

declare(strict_types=1);

require_once __DIR__ . '/proteger_cliente.php';
require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit;
}

verificar_csrf($_POST['csrf_token'] ?? null);

try {
    $cart = json_decode((string) ($_POST['cart_json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($cart)) {
        throw new RuntimeException('Carrito inválido.');
    }

    $direccion = datos_direccion_desde_post($_POST);
    validar_direccion_envio($direccion, true);

    $nombre = trim((string) ($_POST['nombre_recibe'] ?? ''));
    if ($nombre === '') {
        throw new RuntimeException('Escribe el nombre de quien recibirá el pedido.');
    }

    $metodo = ($_POST['envio_metodo'] ?? 'estandar') === 'express' ? 'express' : 'estandar';
    $guardarDireccion = isset($_POST['guardar_direccion']);
    $uid = (int) usuario_actual()['id'];

    $conexion->begin_transaction();

    $calc = procesar_carrito($conexion, $cart, true);
    $envio = tarifa_envio_manual($calc['peso_kg'], $metodo);
    $total = round($calc['subtotal'] + $envio['precio'], 2);
    $folio = generar_folio();

    $telefono = $direccion['telefono'];
    $calle = $direccion['calle'];
    $ext = $direccion['numero_ext'];
    $int = $direccion['numero_int'] !== '' ? $direccion['numero_int'] : null;
    $colonia = $direccion['colonia'];
    $cp = $direccion['codigo_postal'];
    $municipio = $direccion['municipio'];
    $estado = $direccion['estado_destino'];
    $refs = $direccion['referencias'] !== '' ? $direccion['referencias'] : null;
    $proveedor = $envio['proveedor'];
    $subtotal = $calc['subtotal'];
    $costo = $envio['precio'];

    $stmt = $conexion->prepare(
        'INSERT INTO pedidos
        (folio, usuario_id, subtotal, costo_envio, total, envio_metodo, envio_proveedor, nombre_recibe, telefono, calle, numero_ext, numero_int, colonia, codigo_postal, municipio, estado_destino, referencias)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'sidddssssssssssss',
        $folio,
        $uid,
        $subtotal,
        $costo,
        $total,
        $metodo,
        $proveedor,
        $nombre,
        $telefono,
        $calle,
        $ext,
        $int,
        $colonia,
        $cp,
        $municipio,
        $estado,
        $refs
    );
    $stmt->execute();
    $pedidoId = (int) $conexion->insert_id;

    $stmtDetalle = $conexion->prepare(
        'INSERT INTO pedido_detalles
        (pedido_id, producto_id, producto_nombre, precio_original, precio_unitario, descuento_unitario, promocion_nombre, cantidad, total_linea)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmtStock = $conexion->prepare('UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?');

    foreach ($calc['detalles'] as $d) {
        $pid = $d['id'];
        $pn = $d['nombre'];
        $po = $d['precio_original'];
        $pp = $d['precio'];
        $du = $d['descuento_unitario'];
        $promoNombre = $d['promocion_nombre'];
        $qty = $d['cantidad'];
        $line = $d['total'];

        $stmtDetalle->bind_param('iisdddsid', $pedidoId, $pid, $pn, $po, $pp, $du, $promoNombre, $qty, $line);
        $stmtDetalle->execute();

        $stmtStock->bind_param('iii', $qty, $pid, $qty);
        $stmtStock->execute();
        if ($stmtStock->affected_rows !== 1) {
            throw new RuntimeException('El stock cambió mientras procesábamos el pedido. Inténtalo de nuevo.');
        }
    }

    if ($guardarDireccion) {
        guardar_direccion_usuario($conexion, $uid, $direccion);
    }

    $conexion->commit();
    $_SESSION['ultimo_pedido_id'] = $pedidoId;

    header('Location: pago.php?id=' . $pedidoId);
    exit;
} catch (Throwable $e) {
    try {
        $conexion->rollback();
    } catch (Throwable $ignore) {
    }

    error_log('crear_pedido: ' . $e->getMessage());
    $mensaje = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'No fue posible crear el pedido. Verifica que la migración de la base de datos esté aplicada.';

    flash('error', $mensaje);
    header('Location: checkout.php');
    exit;
}
