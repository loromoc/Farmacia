<?php

declare(strict_types=1);
require_once __DIR__ . '/proteger_admin.php';
require_once __DIR__ . '/conexion.php';

$resultado = $conexion->query('SELECT * FROM productos ORDER BY id DESC');
$promocionesVigentes = obtener_promociones_activas($conexion);
$productosPromo = $conexion->query('SELECT id, nombre FROM productos WHERE activo=1 ORDER BY nombre');

$stats = $conexion->query("SELECT COUNT(*) productos, COALESCE(SUM(stock),0) unidades FROM productos")->fetch_assoc();
$pedidosPendientes = (int) $conexion->query("SELECT COUNT(*) total FROM pedidos WHERE estado IN ('pendiente_pago','pagado','preparando')")->fetch_assoc()['total'];

try {
    $promos = $conexion->query(
        "SELECT pr.*, p.nombre producto_nombre
         FROM promociones pr
         LEFT JOIN productos p ON p.id=pr.producto_id
         ORDER BY pr.id DESC"
    );
    $promosActivas = (int) $conexion->query(
        "SELECT COUNT(*) total FROM promociones
         WHERE activa=1 AND fecha_inicio<=NOW() AND fecha_fin>=NOW()"
    )->fetch_assoc()['total'];
} catch (mysqli_sql_exception $e) {
    $promos = null;
    $promosActivas = 0;
}

$flashes = obtener_flashes();

function estado_promocion_admin(array $p): array
{
    if (!(int) $p['activa']) return ['INACTIVA', 'status-rechazado'];
    $ahora = time();
    $inicio = strtotime((string) $p['fecha_inicio']);
    $fin = strtotime((string) $p['fecha_fin']);
    if ($inicio > $ahora) return ['PROGRAMADA', 'status-pendiente'];
    if ($fin < $ahora) return ['FINALIZADA', 'status-cancelado'];
    return ['ACTIVA', 'status-aprobado'];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Administración - <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="panel_admin.php"><span class="brand-mark">✚</span><span>Administración</span></a>
    <nav>
        <a href="index.php">Ver tienda</a>
        <a href="#promociones">Promociones</a>
        <a href="panel_pedidos.php">Pedidos</a>
        <form method="post" action="logout.php" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button class="link-button" type="submit">Cerrar sesión</button>
        </form>
    </nav>
</header>

<main class="admin-main">
    <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div>
    <?php endforeach; ?>

    <section class="stats-grid">
        <div class="stat-card"><span>Productos</span><strong><?= (int) $stats['productos'] ?></strong></div>
        <div class="stat-card"><span>Unidades en stock</span><strong><?= (int) $stats['unidades'] ?></strong></div>
        <div class="stat-card"><span>Pedidos por atender</span><strong><?= $pedidosPendientes ?></strong></div>
        <div class="stat-card"><span>Promociones activas</span><strong><?= $promosActivas ?></strong></div>
    </section>

    <section class="admin-panel" id="promociones">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Marketing</p>
                <h1>Descuentos y promociones</h1>
                <p class="muted">Aplica un descuento a un producto o a todo el catálogo. El servidor recalcula el precio al crear el pedido.</p>
            </div>
        </div>

        <?php if ($promos === null): ?>
            <div class="alert alert-warning">
                Primero importa <strong>migracion_integracion_v7.sql</strong> para activar promociones.
            </div>
        <?php else: ?>
            <form action="guardar_promocion.php" method="post" class="form-grid promo-admin-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label>Nombre de la promoción
                    <input name="nombre" maxlength="140" placeholder="Ej. Martes de vitaminas" required>
                </label>
                <label>Producto
                    <select name="producto_id">
                        <option value="">Todo el catálogo</option>
                        <?php while ($pp = $productosPromo->fetch_assoc()): ?>
                            <option value="<?= (int) $pp['id'] ?>"><?= e($pp['nombre']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </label>
                <label>Tipo de descuento
                    <select name="tipo" required>
                        <option value="porcentaje">Porcentaje (%)</option>
                        <option value="fijo">Monto fijo (MXN)</option>
                    </select>
                </label>
                <label>Valor
                    <input type="number" name="valor" min="0.01" step="0.01" required>
                </label>
                <label>Inicio
                    <input type="datetime-local" name="fecha_inicio" value="<?= e(date('Y-m-d\TH:i')) ?>">
                </label>
                <label>Fin
                    <input type="datetime-local" name="fecha_fin" value="<?= e(date('Y-m-d\T23:59')) ?>">
                </label>
                <label class="span-2">Descripción
                    <input name="descripcion" maxlength="255" placeholder="Texto opcional que aparecerá en la promoción destacada">
                </label>
                <label class="check-label"><input type="checkbox" name="solo_hoy" value="1"> Promoción solo por hoy</label>
                <label class="check-label"><input type="checkbox" name="destacada" value="1" checked> Mostrar destacada en la tienda</label>
                <button class="btn btn-primary span-2" type="submit">Crear promoción</button>
            </form>

            <div class="admin-table-wrap promo-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr><th>Promoción</th><th>Aplica a</th><th>Descuento</th><th>Vigencia</th><th>Estado</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($promos->num_rows === 0): ?>
                        <tr><td colspan="6">Todavía no hay promociones.</td></tr>
                    <?php endif; ?>
                    <?php while ($pr = $promos->fetch_assoc()): [$estadoPromo, $clasePromo] = estado_promocion_admin($pr); ?>
                        <tr>
                            <td><strong><?= e($pr['nombre']) ?></strong><small><?= e($pr['descripcion'] ?? '') ?></small></td>
                            <td><?= e($pr['producto_nombre'] ?: 'Todo el catálogo') ?></td>
                            <td><strong><?= e(promocion_texto_descuento($pr)) ?></strong></td>
                            <td><small><?= e(date('d/m/Y H:i', strtotime($pr['fecha_inicio']))) ?><br>al <?= e(date('d/m/Y H:i', strtotime($pr['fecha_fin']))) ?></small></td>
                            <td><span class="status <?= e($clasePromo) ?>"><?= e($estadoPromo) ?></span></td>
                            <td class="actions">
                                <form action="cambiar_promocion.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $pr['id'] ?>">
                                    <button class="btn-small" type="submit"><?= (int) $pr['activa'] ? 'Pausar' : 'Activar' ?></button>
                                </form>
                                <form action="eliminar_promocion.php" method="post" onsubmit="return confirm('¿Eliminar esta promoción?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $pr['id'] ?>">
                                    <button class="btn-small danger" type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-panel">
        <div class="section-heading"><div><p class="eyebrow">Catálogo</p><h1>Nuevo producto</h1></div></div>
        <form action="guardar_producto.php" method="post" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>Nombre<input name="nombre" maxlength="160" required></label>
            <label>Categoría<input name="categoria" maxlength="100" required></label>
            <label>Principio activo<input name="principio_activo" maxlength="160"></label>
            <label>Presentación<input name="presentacion" maxlength="160"></label>
            <label class="span-2">Descripción<textarea name="descripcion" maxlength="2000" required></textarea></label>
            <label>Precio (MXN)<input type="number" step="0.01" min="0.01" name="precio" required></label>
            <label>Stock<input type="number" min="0" name="stock" required></label>
            <label>Peso (kg)<input type="number" step="0.001" min="0.05" name="peso_kg" value="0.250" required></label>
            <label>Alto (cm)<input type="number" step="0.1" min="0" name="alto_cm"></label>
            <label>Ancho (cm)<input type="number" step="0.1" min="0" name="ancho_cm"></label>
            <label>Largo (cm)<input type="number" step="0.1" min="0" name="largo_cm"></label>
            <label class="span-2">Imagen JPG/PNG/WebP (máx. 3 MB)<input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" required></label>
            <label class="check-label"><input type="checkbox" name="requiere_receta" value="1"> Requiere validación/receta</label>
            <label class="check-label"><input type="checkbox" name="envio_permitido" value="1" checked> Permitir envío nacional</label>
            <button class="btn btn-primary span-2" type="submit">Guardar producto</button>
        </form>
    </section>

    <section class="section">
        <div class="section-heading"><div><p class="eyebrow">Inventario</p><h2>Productos registrados</h2></div></div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Producto</th><th>Categoría</th><th>Precio</th><th>Stock</th><th>Envío</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php while ($p = $resultado->fetch_assoc()): $precioAdmin = aplicar_mejor_promocion($p, $promocionesVigentes); ?>
                    <tr>
                        <td>
                            <div class="table-product">
                                <?php if ($p['imagen']): ?><img src="uploads/<?= e(basename($p['imagen'])) ?>" alt=""><?php endif; ?>
                                <div><strong><?= e($p['nombre']) ?></strong><small><?= e($p['principio_activo'] ?? '') ?></small></div>
                            </div>
                        </td>
                        <td><?= e($p['categoria']) ?></td>
                        <td>
                            <?php if ((float)$precioAdmin['precio_final'] < (float)$precioAdmin['precio_original']): ?>
                                <small><s>$<?= number_format((float)$precioAdmin['precio_original'], 2) ?></s></small>
                                <strong>$<?= number_format((float)$precioAdmin['precio_final'], 2) ?></strong>
                            <?php else: ?>
                                $<?= number_format((float) $p['precio'], 2) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $p['stock'] ?></td>
                        <td><?= (int) $p['envio_permitido'] ? 'Sí' : 'No' ?></td>
                        <td class="actions">
                            <a class="btn-small" href="editar_producto.php?id=<?= (int) $p['id'] ?>">Editar</a>
                            <form action="eliminar_producto.php" method="post" onsubmit="return confirm('¿Eliminar este producto?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button class="btn-small danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<script src="ui.js" defer></script>
</body>
</html>
