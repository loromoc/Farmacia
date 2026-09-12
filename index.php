<?php

declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion_segura();

$usuario = usuario_actual();
$flashes = obtener_flashes();
$promociones = obtener_promociones_activas($conexion);

function icono_categoria(string $categoria): string
{
    $c = strtolower($categoria);
    if (str_contains($c, 'vitamin') || str_contains($c, 'suplement')) return '🌿';
    if (str_contains($c, 'dermo') || str_contains($c, 'piel') || str_contains($c, 'cosm')) return '🧴';
    if (str_contains($c, 'beb') || str_contains($c, 'mam')) return '🍼';
    if (str_contains($c, 'dispositivo') || str_contains($c, 'equipo')) return '🩺';
    if (str_contains($c, 'higiene') || str_contains($c, 'personal')) return '🪥';
    if (str_contains($c, 'natural')) return '🌱';
    if (str_contains($c, 'analg') || str_contains($c, 'medic')) return '💊';
    return '✚';
}

$productos = [];
$res = $conexion->query('SELECT * FROM productos WHERE activo=1 ORDER BY id DESC');
while ($p = $res->fetch_assoc()) {
    $calc = aplicar_mejor_promocion($p, $promociones);
    $promo = $calc['promocion'];

    $badge = '';
    $badgeText = '';
    if ($promo) {
        $badge = 'offer';
        $badgeText = promocion_texto_descuento($promo);
    } elseif ((int) $p['requiere_receta']) {
        $badge = 'rx';
        $badgeText = 'Rx';
    } elseif ((int) $p['stock'] > 0 && (int) $p['stock'] <= 5) {
        $badge = 'hot';
        $badgeText = 'Últimas';
    }

    $productos[] = [
        'id' => (int) $p['id'],
        'name' => (string) $p['nombre'],
        'desc' => (string) $p['descripcion'],
        'category' => (string) $p['categoria'],
        'activeIngredient' => (string) ($p['principio_activo'] ?? ''),
        'presentation' => (string) ($p['presentacion'] ?? ''),
        'price' => (float) $calc['precio_final'],
        'oldPrice' => $calc['precio_final'] < $calc['precio_original'] ? (float) $calc['precio_original'] : null,
        'stock' => (int) $p['stock'],
        'image' => $p['imagen'] ? 'uploads/' . basename((string) $p['imagen']) : '',
        'requiresRx' => (bool) $p['requiere_receta'],
        'shippingAllowed' => (bool) $p['envio_permitido'],
        'badge' => $badge,
        'badgeText' => $badgeText,
        'promoName' => $promo['nombre'] ?? null,
    ];
}

$categoryCounts = [];
foreach ($productos as $p) {
    $cat = $p['category'];
    $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
}
ksort($categoryCounts, SORT_NATURAL | SORT_FLAG_CASE);

$categorias = [[
    'id' => 'all',
    'name' => 'Todo',
    'icon' => '🏪',
    'count' => count($productos),
]];
foreach ($categoryCounts as $cat => $count) {
    $categorias[] = [
        'id' => 'cat-' . substr(sha1($cat), 0, 10),
        'name' => $cat,
        'icon' => icono_categoria($cat),
        'count' => $count,
        'raw' => $cat,
    ];
}

$ventasPorProducto = [];
try {
    $r = $conexion->query(
        "SELECT d.producto_id, COALESCE(SUM(d.cantidad),0) vendidos
         FROM pedido_detalles d
         JOIN pedidos p ON p.id=d.pedido_id
         WHERE p.pago_estado='aprobado' AND d.producto_id IS NOT NULL
         GROUP BY d.producto_id"
    );
    while ($v = $r->fetch_assoc()) {
        $ventasPorProducto[(int) $v['producto_id']] = (int) $v['vendidos'];
    }
} catch (Throwable $e) {
}

foreach ($productos as &$p) {
    $p['sold'] = $ventasPorProducto[$p['id']] ?? 0;
}
unset($p);

$destacados = $productos;
usort($destacados, static fn(array $a, array $b): int =>
    ($b['sold'] <=> $a['sold']) ?: ($b['id'] <=> $a['id'])
);
$destacados = array_slice($destacados, 0, 5);

$promoDestacada = null;
foreach ($promociones as $promo) {
    if ((int) ($promo['destacada'] ?? 0) === 1) {
        $promoDestacada = $promo;
        break;
    }
}
if (!$promoDestacada && $promociones) {
    $promoDestacada = $promociones[0];
}

$storeData = [
    'products' => $productos,
    'categories' => $categorias,
    'featured' => array_column($destacados, 'id'),
    'user' => [
        'loggedIn' => $usuario !== null,
        'role' => $usuario['rol'] ?? null,
        'name' => $usuario['nombre'] ?? '',
    ],
    'checkoutUrl' => 'checkout.php',
    'loginUrl' => 'login.php',
    'csrf' => csrf_token(),
    'promoEndsAt' => $promoDestacada['fecha_fin'] ?? null,
];

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(APP_NAME) ?> | Farmacia en línea</title>
  <meta name="description" content="Catálogo, pedidos, dirección de entrega, promociones, pago y seguimiento en <?= e(APP_NAME) ?>.">
  <meta name="theme-color" content="#1565C0">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <link rel="stylesheet" href="assets/css/variables.css">
  <link rel="stylesheet" href="assets/css/animations.css">
  <link rel="stylesheet" href="assets/css/storefront.css">
  <link rel="stylesheet" href="assets/css/storefront-overrides.css">
</head>
<body>

<?php foreach ($flashes as $flash): ?>
  <div class="store-flash store-flash-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div>
<?php endforeach; ?>

<div class="announcement-bar">
  <span><i class="fas fa-truck-fast"></i> Envíos nacionales desde el checkout</span>
  <span><i class="fas fa-location-dot"></i> Guarda tu dirección en tu perfil</span>
  <span><i class="fas fa-lock"></i> Pago y pedidos validados en servidor</span>
</div>

<nav class="navbar" id="navbar">
  <a href="index.php" class="nav-logo" aria-label="<?= e(APP_NAME) ?>">
    <div class="nav-logo-icon"><i class="fas fa-capsules"></i></div>
    <div>
      <span class="nav-logo-text"><?= e(APP_NAME) ?></span>
      <span class="nav-logo-sub">Farmacia en línea</span>
    </div>
  </a>

  <div class="nav-search">
    <label class="sr-only" for="searchInput">Buscar productos</label>
    <input type="search" id="searchInput" placeholder="Buscar por nombre, ingrediente o categoría…">
    <button class="nav-search-btn" type="button" id="searchBtn" aria-label="Buscar"><i class="fas fa-search"></i></button>
  </div>

  <ul class="nav-links">
    <li><a href="#categorias">Categorías</a></li>
    <li><a href="#productos">Catálogo</a></li>
    <li><a href="#ofertas">Promociones</a></li>
  </ul>

  <div class="nav-actions">
    <?php if (!$usuario || $usuario['rol'] === 'cliente'): ?>
      <button class="nav-icon-btn" id="wishlistBtn" type="button" title="Favoritos" aria-label="Favoritos">
        <i class="far fa-heart"></i>
      </button>
      <button class="nav-icon-btn" id="cartToggleBtn" type="button" title="Carrito de compras" aria-label="Carrito">
        <i class="fas fa-shopping-cart"></i>
        <span class="nav-badge" id="cartBadge">0</span>
      </button>
    <?php endif; ?>

    <?php if ($usuario): ?>
      <?php if ($usuario['rol'] === 'admin'): ?>
        <a class="btn-primary desktop-action" href="panel_admin.php">Administración</a>
        <a class="nav-icon-btn mobile-account" href="panel_admin.php" title="Administración" aria-label="Administración"><i class="fas fa-user-shield"></i></a>
      <?php else: ?>
        <a class="nav-account-link desktop-action" href="mis_pedidos.php">Mis pedidos</a>
        <a class="btn-primary desktop-action" href="perfil.php">Mi perfil</a>
        <a class="nav-icon-btn mobile-account" href="perfil.php" title="Mi perfil" aria-label="Mi perfil"><i class="fas fa-user"></i></a>
      <?php endif; ?>
    <?php else: ?>
      <a class="nav-account-link desktop-action" href="login.php">Entrar</a>
      <a class="btn-primary desktop-action" href="registro.php">Crear cuenta</a>
      <a class="nav-icon-btn mobile-account" href="login.php" title="Iniciar sesión" aria-label="Iniciar sesión"><i class="fas fa-user"></i></a>
    <?php endif; ?>
  </div>
</nav>

<section class="hero">
  <div class="floating-pill"><i class="fas fa-truck-fast"></i> Envío nacional</div>
  <div class="floating-pill"><i class="fas fa-tags"></i> Promociones reales</div>
  <div class="floating-pill"><i class="fas fa-box"></i> Seguimiento de pedido</div>

  <div class="hero-grid">
    <div>
      <div class="hero-badge"><i class="fas fa-circle-check"></i> Compra organizada y segura</div>
      <h1 class="hero-title">
        Tu farmacia,<br>
        ahora <span>más fácil de usar</span>
      </h1>
      <p class="hero-desc">
        Consulta productos disponibles, aprovecha promociones, guarda tu dirección, calcula el envío y continúa al pago desde un mismo flujo.
      </p>
      <div class="hero-ctas">
        <a href="#productos" class="btn-hero-primary"><i class="fas fa-bag-shopping"></i> Ver catálogo</a>
        <?php if ($usuario && $usuario['rol'] === 'cliente'): ?>
          <a href="perfil.php" class="btn-hero-secondary"><i class="fas fa-location-dot"></i> Mi dirección</a>
        <?php else: ?>
          <a href="registro.php" class="btn-hero-secondary"><i class="fas fa-user-plus"></i> Crear perfil</a>
        <?php endif; ?>
      </div>
      <div class="hero-stats">
        <div class="hero-stat">
          <div class="hero-stat-num"><?= count($productos) ?></div>
          <div class="hero-stat-label">Productos activos</div>
        </div>
        <div class="hero-stat">
          <div class="hero-stat-num"><?= count($categoryCounts) ?></div>
          <div class="hero-stat-label">Categorías</div>
        </div>
        <div class="hero-stat">
          <div class="hero-stat-num"><?= count($promociones) ?></div>
          <div class="hero-stat-label">Promos vigentes</div>
        </div>
        <div class="hero-stat">
          <div class="hero-stat-num">2</div>
          <div class="hero-stat-label">Tipos de envío</div>
        </div>
      </div>
    </div>

    <div class="hero-visual">
      <div class="hero-cards-grid">
        <?php foreach (array_slice(array_values(array_filter($categorias, static fn($c) => $c['id'] !== 'all')), 0, 4) as $cat): ?>
          <button class="hero-card js-category" type="button" data-category="<?= e($cat['raw']) ?>">
            <div class="hero-card-icon"><?= e($cat['icon']) ?></div>
            <div class="hero-card-title"><?= e($cat['name']) ?></div>
            <div class="hero-card-sub"><?= (int) $cat['count'] ?> producto<?= (int) $cat['count'] === 1 ? '' : 's' ?></div>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<div class="trust-strip">
  <div class="trust-strip-inner">
    <div class="trust-item"><i class="fas fa-magnifying-glass"></i><span>Búsqueda por producto e ingrediente</span></div>
    <div class="trust-item"><i class="fas fa-house"></i><span>Dirección guardada en tu perfil</span></div>
    <div class="trust-item"><i class="fas fa-truck"></i><span>Cotización de envío en checkout</span></div>
    <div class="trust-item"><i class="fas fa-receipt"></i><span>Historial de pedidos</span></div>
  </div>
</div>

<section class="section" id="categorias">
  <div class="container">
    <div class="section-header">
      <div class="section-label">Clasificaciones</div>
      <h2 class="section-title">Explora por <span>categoría</span></h2>
      <p class="section-sub">Las categorías y cantidades se generan directamente desde tu inventario.</p>
    </div>
    <div class="categories-grid" id="categoriesGrid"></div>
  </div>
</section>

<section class="section catalog-section" id="productos">
  <div class="container">
    <div class="section-header">
      <div class="section-label">Farmacia en línea</div>
      <h2 class="section-title">Productos <span>disponibles</span></h2>
      <p class="section-sub">Los precios, promociones y existencias que ves provienen de la base de datos.</p>
    </div>

    <div class="products-header">
      <span id="productCount" class="product-count"></span>
      <div class="products-controls">
        <select class="filter-select" id="sortSelect" aria-label="Ordenar productos">
          <option value="default">Ordenar: Relevancia</option>
          <option value="price-asc">Precio: menor a mayor</option>
          <option value="price-desc">Precio: mayor a menor</option>
          <option value="name">Nombre A-Z</option>
        </select>
        <div class="view-toggle">
          <button class="view-btn active" id="gridViewBtn" type="button" data-view="grid" title="Vista cuadrícula"><i class="fas fa-table-cells-large"></i></button>
          <button class="view-btn" id="listViewBtn" type="button" data-view="list" title="Vista lista"><i class="fas fa-list"></i></button>
        </div>
      </div>
    </div>

    <div class="products-grid" id="productsGrid"></div>
  </div>
</section>

<section class="section" id="ofertas">
  <div class="container">
    <div class="promo-banner <?= $promoDestacada ? '' : 'promo-banner-generic' ?>">
      <div class="promo-content">
        <?php if ($promoDestacada): ?>
          <div class="promo-label"><i class="fas fa-tags"></i> Promoción vigente</div>
          <h2 class="promo-title"><?= e($promoDestacada['nombre']) ?></h2>
          <p class="promo-desc">
            <?= e($promoDestacada['descripcion'] ?: 'Aprovecha el descuento mientras esté vigente. El precio se valida nuevamente antes de crear tu pedido.') ?>
          </p>
          <a href="#productos" class="promo-cta"><i class="fas fa-bag-shopping"></i> Ver productos</a>
        <?php else: ?>
          <div class="promo-label"><i class="fas fa-truck-fast"></i> Compra en línea</div>
          <h2 class="promo-title">Consulta envío y total <span>antes de pagar</span></h2>
          <p class="promo-desc">Agrega productos al carrito, captura tu código postal y el checkout calculará las opciones de entrega disponibles.</p>
          <a href="#productos" class="promo-cta"><i class="fas fa-bag-shopping"></i> Empezar pedido</a>
        <?php endif; ?>
      </div>

      <?php if ($promoDestacada): ?>
        <div class="promo-visual">
          <div class="promo-countdown" id="promoCountdown">
            <div class="countdown-item"><div class="countdown-num" id="cdD">00</div><div class="countdown-label">Días</div></div>
            <div class="countdown-item"><div class="countdown-num" id="cdH">00</div><div class="countdown-label">Horas</div></div>
            <div class="countdown-item"><div class="countdown-num" id="cdM">00</div><div class="countdown-label">Min</div></div>
            <div class="countdown-item"><div class="countdown-num" id="cdS">00</div><div class="countdown-label">Seg</div></div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($destacados): ?>
<section class="section featured-section">
  <div class="container">
    <div class="section-header">
      <div class="section-label">Destacados</div>
      <h2 class="section-title">Productos <span>más solicitados</span></h2>
      <p class="section-sub">Se priorizan productos con ventas confirmadas; si aún no hay ventas, se muestran los más recientes.</p>
    </div>
    <div class="featured-grid">
      <div id="featuredHero"></div>
      <div class="bestsellers-list" id="bestsellersList"></div>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="newsletter-section">
  <h2 class="newsletter-title">📬 Recibe promociones y novedades</h2>
  <p class="newsletter-sub">Guarda tu correo para futuras comunicaciones de la farmacia. Puedes darte de baja posteriormente.</p>
  <form class="newsletter-form" id="newsletterForm">
    <label class="sr-only" for="newsletterEmail">Correo electrónico</label>
    <input type="email" id="newsletterEmail" name="correo" placeholder="ejemplo@correo.com" required>
    <button class="newsletter-btn" type="submit">Suscribirme</button>
  </form>
</div>

<footer id="contacto">
  <div class="footer-grid">
    <div>
      <div class="footer-logo">
        <div class="footer-logo-icon"><i class="fas fa-capsules"></i></div>
        <span class="footer-logo-text"><?= e(APP_NAME) ?></span>
      </div>
      <p class="footer-desc">Catálogo, cuenta de cliente, dirección de entrega, pedidos, promociones y pagos integrados en una sola aplicación.</p>
    </div>
    <div>
      <div class="footer-col-title">Comprar</div>
      <ul class="footer-links">
        <li><a href="#categorias">Categorías</a></li>
        <li><a href="#productos">Catálogo</a></li>
        <li><a href="#ofertas">Promociones</a></li>
      </ul>
    </div>
    <div>
      <div class="footer-col-title">Mi cuenta</div>
      <ul class="footer-links">
        <?php if ($usuario && $usuario['rol'] === 'cliente'): ?>
          <li><a href="perfil.php">Mi perfil y dirección</a></li>
          <li><a href="mis_pedidos.php">Mis pedidos</a></li>
        <?php else: ?>
          <li><a href="login.php">Iniciar sesión</a></li>
          <li><a href="registro.php">Crear cuenta</a></li>
        <?php endif; ?>
      </ul>
    </div>
    <div>
      <div class="footer-col-title">Compra</div>
      <ul class="footer-links">
        <li><a href="checkout.php">Checkout</a></li>
        <li><a href="mis_pedidos.php">Seguimiento</a></li>
        <?php if ($usuario && $usuario['rol'] === 'admin'): ?><li><a href="panel_admin.php">Administración</a></li><?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <p class="footer-bottom-text">© <?= date('Y') ?> <?= e(APP_NAME) ?>. Proyecto de farmacia en línea.</p>
    <div class="footer-badges">
      <span class="footer-badge">PHP + MySQL</span>
      <span class="footer-badge">Mercado Pago</span>
      <span class="footer-badge">Envíos nacionales</span>
    </div>
  </div>
</footer>

<?php if (!$usuario || $usuario['rol'] === 'cliente'): ?>
<div class="cart-overlay" id="cartOverlay"></div>
<aside class="cart-sidebar" id="cartSidebar" aria-label="Carrito de compras" aria-hidden="true">
  <div class="cart-header">
    <div class="cart-title"><i class="fas fa-shopping-cart"></i> Tu carrito</div>
    <button class="cart-close" id="cartCloseBtn" type="button" aria-label="Cerrar carrito"><i class="fas fa-times"></i></button>
  </div>
  <div class="cart-body" id="cartBody"></div>
  <div class="cart-footer" id="cartFooter" hidden>
    <div class="cart-subtotal"><span>Subtotal estimado</span><span id="cartSubtotal">$0.00 MXN</span></div>
    <div class="cart-note">El total final y las promociones vigentes se vuelven a validar en el servidor.</div>
    <div class="cart-total"><span>Total productos</span><span id="cartTotal">$0.00 MXN</span></div>
    <button class="btn-checkout" id="checkoutBtn" type="button"><i class="fas fa-lock"></i> Continuar al envío</button>
  </div>
</aside>
<?php endif; ?>

<div class="toast-container" id="toastContainer" aria-live="polite"></div>

<button class="back-top" id="backTop" type="button" title="Subir al inicio" aria-label="Volver arriba">
  <i class="fas fa-arrow-up"></i>
</button>

<script>
window.FARMA_STORE = <?= json_encode($storeData, $jsonFlags) ?>;
</script>
<script src="assets/js/cart.js" defer></script>
<script src="assets/js/render.js" defer></script>
<script src="assets/js/main.js" defer></script>
</body>
</html>
