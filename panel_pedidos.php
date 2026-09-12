<?php

declare(strict_types=1);

require_once __DIR__ . '/proteger_admin.php';
require_once __DIR__ . '/conexion.php';

$res = $conexion->query(
    'SELECT p.*, u.nombre cliente, u.correo, u.notificar_email, u.notificar_whatsapp
     FROM pedidos p
     JOIN usuarios u ON u.id=p.usuario_id
     ORDER BY p.id DESC'
);
$flashes = obtener_flashes();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pedidos - Administración</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="panel_admin.php"><span class="brand-mark">✚</span><span>Administración</span></a>
    <nav>
        <a href="panel_admin.php">Productos y promociones</a>
        <a href="diagnostico_notificaciones.php">Notificaciones</a>
        <a href="index.php">Tienda</a>
    </nav>
</header>

<main class="admin-main">
    <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['tipo']) ?>"><?= e($f['mensaje']) ?></div>
    <?php endforeach; ?>

    <div class="section-heading">
        <div>
            <p class="eyebrow">Operación</p>
            <h1>Pedidos y envíos</h1>
            <p class="muted">Actualiza el estado, agrega guía y usa los accesos de correo o WhatsApp para informar al cliente.</p>
        </div>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
            <tr>
                <th>Pedido</th>
                <th>Cliente</th>
                <th>Entrega</th>
                <th>Total</th>
                <th>Pago</th>
                <th>Estado / guía</th>
                <th>Avisar</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($res->num_rows === 0): ?>
                <tr><td colspan="7">Todavía no hay pedidos.</td></tr>
            <?php endif; ?>

            <?php while ($p = $res->fetch_assoc()):
                $wa = whatsapp_pedido_url($p, (string) $p['estado']);
                $asunto = rawurlencode(APP_NAME . ' · Pedido ' . $p['folio']);
                $cuerpo = rawurlencode(
                    'Hola ' . $p['cliente'] . ".\n\n" .
                    'Tu pedido ' . $p['folio'] . ' está ' . str_replace('_', ' ', $p['estado']) . '.' .
                    ($p['guia'] ? "\nGuía: " . $p['guia'] : '') .
                    "\n\nGracias por comprar en " . APP_NAME . '.'
                );
                $mail = 'mailto:' . rawurlencode((string) $p['correo']) . '?subject=' . $asunto . '&body=' . $cuerpo;
            ?>
                <tr>
                    <td>
                        <strong><?= e($p['folio']) ?></strong>
                        <small><?= e(date('d/m/Y H:i', strtotime($p['creado_en']))) ?></small>
                    </td>
                    <td>
                        <?= e($p['cliente']) ?>
                        <small><?= e($p['correo']) ?><br><?= e($p['telefono']) ?></small>
                    </td>
                    <td>
                        CP <?= e($p['codigo_postal']) ?>
                        <small><?= e($p['municipio'] . ', ' . $p['estado_destino']) ?></small>
                    </td>
                    <td>$<?= number_format((float) $p['total'], 2) ?></td>
                    <td>
                        <span class="status status-<?= e($p['pago_estado']) ?>"><?= e($p['pago_estado']) ?></span>
                    </td>
                    <td>
                        <form action="actualizar_estado_pedido.php" method="post" class="order-admin-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <select name="estado" <?= $p['estado'] === 'cancelado' ? 'disabled' : '' ?>>
                                <?php foreach (['pendiente_pago','pagado','preparando','enviado','entregado','cancelado'] as $estado): ?>
                                    <option value="<?= e($estado) ?>" <?= $p['estado'] === $estado ? 'selected' : '' ?>>
                                        <?= e(ucfirst(str_replace('_', ' ', $estado))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input name="guia" maxlength="120" value="<?= e($p['guia']) ?>" placeholder="Número de guía">
                            <button class="btn-small" type="submit" <?= $p['estado'] === 'cancelado' ? 'disabled' : '' ?>>Actualizar</button>
                        </form>
                    </td>
                    <td>
                        <div class="notify-actions">
                            <?php if ($wa && (int) $p['notificar_whatsapp'] === 1): ?>
                                <a class="btn-small whatsapp" href="<?= e($wa) ?>" target="_blank" rel="noopener">
                                    WhatsApp
                                </a>
                            <?php else: ?>
                                <span class="btn-small is-disabled">WhatsApp no autorizado</span>
                            <?php endif; ?>
                            <a class="btn-small" href="<?= e($mail) ?>">Correo</a>
                            <small>
                                <?= (int) $p['notificar_email'] ? 'Email autorizado' : 'Email desactivado' ?><br>
                                <?= (int) $p['notificar_whatsapp'] ? 'WhatsApp autorizado' : 'WhatsApp no autorizado' ?>
                            </small>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>
<script src="ui.js" defer></script>
</body>
</html>
