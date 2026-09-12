<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }
}

function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    iniciar_sesion_segura();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verificar_csrf(?string $token): void
{
    iniciar_sesion_segura();
    $actual = $_SESSION['csrf_token'] ?? '';
    if (!$token || !$actual || !hash_equals($actual, $token)) {
        http_response_code(419);
        exit('La sesión del formulario expiró. Regresa e inténtalo de nuevo.');
    }
}

function usuario_actual(): ?array
{
    iniciar_sesion_segura();
    if (empty($_SESSION['id'])) {
        return null;
    }
    return [
        'id' => (int) $_SESSION['id'],
        'nombre' => (string) ($_SESSION['nombre'] ?? ''),
        'correo' => (string) ($_SESSION['correo'] ?? ''),
        'rol' => (string) ($_SESSION['rol'] ?? 'cliente'),
    ];
}

function es_admin(): bool
{
    $usuario = usuario_actual();
    return $usuario !== null && $usuario['rol'] === 'admin';
}

function requerir_login(): void
{
    if (!usuario_actual()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header('Location: login.php');
        exit;
    }
}

function requerir_admin(): void
{
    requerir_login();
    if (!es_admin()) {
        http_response_code(403);
        exit('No tienes permisos para acceder a esta sección.');
    }
}

function flash(string $tipo, string $mensaje): void
{
    iniciar_sesion_segura();
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function obtener_flashes(): array
{
    iniciar_sesion_segura();
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensajes;
}

function normalizar_correo(string $correo): string
{
    return strtolower(trim($correo));
}

function validar_cp_mexico(string $cp): bool
{
    return (bool) preg_match('/^\d{5}$/', trim($cp));
}

function tarifa_envio_manual(float $pesoKg, string $metodo): array
{
    $pesoKg = max(0.1, $pesoKg);
    $metodo = $metodo === 'express' ? 'express' : 'estandar';

    $tramos = [
        1.0 => ['estandar' => 119.00, 'express' => 179.00],
        3.0 => ['estandar' => 149.00, 'express' => 229.00],
        5.0 => ['estandar' => 189.00, 'express' => 299.00],
        10.0 => ['estandar' => 279.00, 'express' => 429.00],
    ];

    foreach ($tramos as $limite => $precios) {
        if ($pesoKg <= $limite) {
            return [
                'metodo' => $metodo,
                'nombre' => $metodo === 'express' ? 'Envío express' : 'Envío estándar',
                'precio' => $precios[$metodo],
                'dias' => $metodo === 'express' ? '1-3 días hábiles' : '3-6 días hábiles',
                'proveedor' => 'Tarifa interna',
            ];
        }
    }

    $extra = ceil($pesoKg - 10);
    $base = $metodo === 'express' ? 429.00 : 279.00;
    $porKg = $metodo === 'express' ? 38.00 : 25.00;

    return [
        'metodo' => $metodo,
        'nombre' => $metodo === 'express' ? 'Envío express' : 'Envío estándar',
        'precio' => $base + ($extra * $porKg),
        'dias' => $metodo === 'express' ? '1-3 días hábiles' : '3-6 días hábiles',
        'proveedor' => 'Tarifa interna',
    ];
}


function obtener_promociones_activas(mysqli $conexion): array
{
    try {
        $sql = "SELECT id, nombre, descripcion, producto_id, tipo, valor, fecha_inicio, fecha_fin, destacada
                FROM promociones
                WHERE activa = 1
                  AND fecha_inicio <= NOW()
                  AND fecha_fin >= NOW()
                ORDER BY destacada DESC, valor DESC, id DESC";
        $res = $conexion->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } catch (mysqli_sql_exception $e) {
        return [];
    }
}

function calcular_precio_promocional(float $precio, array $promocion): float
{
    $valor = max(0.0, (float) ($promocion['valor'] ?? 0));
    if (($promocion['tipo'] ?? '') === 'porcentaje') {
        $valor = min($valor, 95.0);
        return max(0.01, round($precio * (1 - ($valor / 100)), 2));
    }
    return max(0.01, round($precio - $valor, 2));
}

function aplicar_mejor_promocion(array $producto, array $promociones): array
{
    $precioOriginal = (float) ($producto['precio'] ?? 0);
    $mejorPrecio = $precioOriginal;
    $mejor = null;
    $productoId = (int) ($producto['id'] ?? 0);

    foreach ($promociones as $promo) {
        $promoProducto = $promo['producto_id'] === null ? null : (int) $promo['producto_id'];
        if ($promoProducto !== null && $promoProducto !== $productoId) {
            continue;
        }

        $candidato = calcular_precio_promocional($precioOriginal, $promo);
        if ($candidato < $mejorPrecio) {
            $mejorPrecio = $candidato;
            $mejor = $promo;
        }
    }

    $producto['precio_original'] = round($precioOriginal, 2);
    $producto['precio_final'] = round($mejorPrecio, 2);
    $producto['descuento_unitario'] = round($precioOriginal - $mejorPrecio, 2);
    $producto['promocion'] = $mejor;

    return $producto;
}

function promocion_texto_descuento(array $promocion): string
{
    if (($promocion['tipo'] ?? '') === 'porcentaje') {
        $valor = rtrim(rtrim(number_format((float) $promocion['valor'], 2, '.', ''), '0'), '.');
        return '-' . $valor . '%';
    }
    return '-$' . number_format((float) ($promocion['valor'] ?? 0), 2);
}

function telefono_whatsapp(?string $telefono): string
{
    $digits = preg_replace('/\D+/', '', (string) $telefono);
    if (!$digits) {
        return '';
    }

    // Si el usuario captura 10 dígitos asumimos México (+52).
    if (strlen($digits) === 10) {
        return '52' . $digits;
    }

    return $digits;
}

function whatsapp_pedido_url(array $pedido, string $estado): string
{
    $telefono = telefono_whatsapp($pedido['telefono'] ?? '');
    if ($telefono === '') {
        return '';
    }

    $folio = (string) ($pedido['folio'] ?? '');
    $guia = trim((string) ($pedido['guia'] ?? ''));
    $estadoTexto = str_replace('_', ' ', $estado);
    $mensaje = "Hola. Tu pedido {$folio} ahora está {$estadoTexto}.";
    if ($estado === 'enviado' && $guia !== '') {
        $mensaje .= " Tu número de guía es {$guia}.";
    }
    $mensaje .= " Gracias por comprar en " . APP_NAME . ".";

    return 'https://wa.me/' . rawurlencode($telefono) . '?text=' . rawurlencode($mensaje);
}

function registrar_notificacion(mysqli $conexion, int $pedidoId, string $canal, string $destino, string $estado, string $detalle = ''): void
{
    try {
        $stmt = $conexion->prepare(
            'INSERT INTO notificaciones (pedido_id, canal, destino, estado, detalle)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issss', $pedidoId, $canal, $destino, $estado, $detalle);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('registrar_notificacion: ' . $e->getMessage());
    }
}

function enviar_correo_estado_pedido(mysqli $conexion, array $pedido, string $estado): bool
{
    if (!defined('SMTP_ENABLED') || !SMTP_ENABLED) {
        return false;
    }

    $correo = trim((string) ($pedido['correo'] ?? ''));
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        registrar_notificacion($conexion, (int) $pedido['id'], 'email', $correo, 'omitida', 'PHPMailer no está instalado.');
        return false;
    }

    require_once $autoload;

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        if (SMTP_SECURE !== '') {
            $mail->SMTPSecure = SMTP_SECURE;
        }
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($correo, (string) ($pedido['cliente'] ?? 'Cliente'));

        $estadoTexto = ucfirst(str_replace('_', ' ', $estado));
        $mail->Subject = APP_NAME . ' · Pedido ' . $pedido['folio'] . ' · ' . $estadoTexto;

        $guia = trim((string) ($pedido['guia'] ?? ''));
        $extra = ($estado === 'enviado' && $guia !== '')
            ? '<p><strong>Guía:</strong> ' . e($guia) . '</p>'
            : '';

        $mail->isHTML(true);
        $mail->Body =
            '<h2>' . e(APP_NAME) . '</h2>' .
            '<p>Tu pedido <strong>' . e((string) $pedido['folio']) . '</strong> cambió de estado.</p>' .
            '<p><strong>Estado:</strong> ' . e($estadoTexto) . '</p>' .
            $extra .
            '<p>Puedes consultar el detalle desde tu cuenta.</p>';

        $mail->AltBody = "Tu pedido {$pedido['folio']} ahora está {$estadoTexto}" .
            (($estado === 'enviado' && $guia !== '') ? ". Guía: {$guia}" : '') . '.';

        $mail->send();
        registrar_notificacion($conexion, (int) $pedido['id'], 'email', $correo, 'enviada');
        return true;
    } catch (Throwable $e) {
        registrar_notificacion($conexion, (int) $pedido['id'], 'email', $correo, 'error', $e->getMessage());
        error_log('Correo pedido: ' . $e->getMessage());
        return false;
    }
}


function generar_folio(): string
{
    return 'FDL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function procesar_carrito(mysqli $conexion, array $carrito, bool $bloquear = false): array
{
    if (!$carrito) {
        throw new RuntimeException('El carrito está vacío.');
    }

    $detalles = [];
    $subtotal = 0.0;
    $pesoTotal = 0.0;
    $promociones = obtener_promociones_activas($conexion);

    $sql = 'SELECT id, nombre, precio, stock, peso_kg, activo, envio_permitido, requiere_receta
            FROM productos WHERE id = ?' . ($bloquear ? ' FOR UPDATE' : '');
    $stmt = $conexion->prepare($sql);

    foreach ($carrito as $item) {
        $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
        $cantidad = filter_var($item['cantidad'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || !$cantidad || $cantidad < 1 || $cantidad > 99) {
            throw new RuntimeException('El carrito contiene una cantidad o producto inválido.');
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $producto = $stmt->get_result()->fetch_assoc();

        if (!$producto || !(int) $producto['activo']) {
            throw new RuntimeException('Uno de los productos ya no está disponible.');
        }
        if (!(int) $producto['envio_permitido']) {
            throw new RuntimeException($producto['nombre'] . ' no está habilitado para envío nacional.');
        }
        if ((int) $producto['requiere_receta']) {
            throw new RuntimeException($producto['nombre'] . ' requiere validación/receta antes de poder comprarse en línea.');
        }
        if ((int) $producto['stock'] < $cantidad) {
            throw new RuntimeException('Stock insuficiente para ' . $producto['nombre'] . '.');
        }

        $calculado = aplicar_mejor_promocion($producto, $promociones);
        $precioOriginal = (float) $calculado['precio_original'];
        $precio = (float) $calculado['precio_final'];
        $descuento = (float) $calculado['descuento_unitario'];
        $linea = round($precio * $cantidad, 2);
        $subtotal += $linea;
        $pesoTotal += max(0.05, (float) $producto['peso_kg']) * $cantidad;

        $detalles[] = [
            'id' => (int) $producto['id'],
            'nombre' => $producto['nombre'],
            'precio_original' => $precioOriginal,
            'precio' => $precio,
            'descuento_unitario' => $descuento,
            'promocion_nombre' => $calculado['promocion']['nombre'] ?? null,
            'cantidad' => $cantidad,
            'total' => $linea,
        ];
    }

    return [
        'detalles' => $detalles,
        'subtotal' => round($subtotal, 2),
        'peso_kg' => round($pesoTotal, 3),
    ];
}


function obtener_perfil_usuario(mysqli $conexion, int $usuarioId): ?array
{
    $stmt = $conexion->prepare(
        'SELECT id, nombre, correo, telefono, calle, numero_ext, numero_int, colonia, codigo_postal, municipio, estado_destino, referencias, notificar_email, notificar_whatsapp
         FROM usuarios
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $perfil = $stmt->get_result()->fetch_assoc();
    return $perfil ?: null;
}

function datos_direccion_desde_post(array $origen): array
{
    return [
        'telefono' => trim((string) ($origen['telefono'] ?? '')),
        'calle' => trim((string) ($origen['calle'] ?? '')),
        'numero_ext' => trim((string) ($origen['numero_ext'] ?? '')),
        'numero_int' => trim((string) ($origen['numero_int'] ?? '')),
        'colonia' => trim((string) ($origen['colonia'] ?? '')),
        'codigo_postal' => trim((string) ($origen['codigo_postal'] ?? '')),
        'municipio' => trim((string) ($origen['municipio'] ?? '')),
        'estado_destino' => trim((string) ($origen['estado_destino'] ?? '')),
        'referencias' => trim((string) ($origen['referencias'] ?? '')),
    ];
}

function direccion_tiene_datos(array $direccion): bool
{
    foreach (['calle', 'numero_ext', 'colonia', 'codigo_postal', 'municipio', 'estado_destino'] as $campo) {
        if (trim((string) ($direccion[$campo] ?? '')) !== '') {
            return true;
        }
    }
    return false;
}

function validar_direccion_envio(array $direccion, bool $obligatoria = true): void
{
    if (!$obligatoria && !direccion_tiene_datos($direccion)) {
        return;
    }

    foreach (['telefono', 'calle', 'numero_ext', 'colonia', 'codigo_postal', 'municipio', 'estado_destino'] as $campo) {
        if (trim((string) ($direccion[$campo] ?? '')) === '') {
            throw new RuntimeException('Completa todos los datos obligatorios de la dirección.');
        }
    }

    if (!validar_cp_mexico((string) $direccion['codigo_postal'])) {
        throw new RuntimeException('El código postal debe contener exactamente 5 dígitos.');
    }

    $telefono = preg_replace('/\D+/', '', (string) $direccion['telefono']);
    if (strlen($telefono) < 10 || strlen($telefono) > 15) {
        throw new RuntimeException('Escribe un teléfono válido de 10 a 15 dígitos.');
    }
}

function guardar_direccion_usuario(mysqli $conexion, int $usuarioId, array $direccion): void
{
    $numeroInt = $direccion['numero_int'] !== '' ? $direccion['numero_int'] : null;
    $referencias = $direccion['referencias'] !== '' ? $direccion['referencias'] : null;

    $stmt = $conexion->prepare(
        'UPDATE usuarios
         SET telefono = ?, calle = ?, numero_ext = ?, numero_int = ?, colonia = ?, codigo_postal = ?, municipio = ?, estado_destino = ?, referencias = ?
         WHERE id = ?'
    );
    $stmt->bind_param(
        'sssssssssi',
        $direccion['telefono'],
        $direccion['calle'],
        $direccion['numero_ext'],
        $numeroInt,
        $direccion['colonia'],
        $direccion['codigo_postal'],
        $direccion['municipio'],
        $direccion['estado_destino'],
        $referencias,
        $usuarioId
    );
    $stmt->execute();
}

function porcentaje_perfil_direccion(array $perfil): int
{
    $campos = ['telefono', 'calle', 'numero_ext', 'colonia', 'codigo_postal', 'municipio', 'estado_destino'];
    $completos = 0;
    foreach ($campos as $campo) {
        if (trim((string) ($perfil[$campo] ?? '')) !== '') {
            $completos++;
        }
    }
    return (int) round(($completos / count($campos)) * 100);
}

function guardar_imagen_subida(array $archivo): string
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo recibir la imagen.');
    }
    if (($archivo['size'] ?? 0) > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('La imagen supera el límite de 3 MB.');
    }

    $tmp = (string) $archivo['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info) {
        throw new RuntimeException('El archivo seleccionado no es una imagen válida.');
    }

    $permitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mime = $info['mime'] ?? '';
    if (!isset($permitidos[$mime])) {
        throw new RuntimeException('Solo se permiten imágenes JPG, PNG o WebP.');
    }

    $nombre = bin2hex(random_bytes(16)) . '.' . $permitidos[$mime];
    $destino = __DIR__ . '/uploads/' . $nombre;
    if (!move_uploaded_file($tmp, $destino)) {
        throw new RuntimeException('No fue posible guardar la imagen.');
    }
    return $nombre;
}

function eliminar_imagen_local(?string $nombre): void
{
    if (!$nombre) {
        return;
    }
    $seguro = basename($nombre);
    $ruta = __DIR__ . '/uploads/' . $seguro;
    if (is_file($ruta)) {
        @unlink($ruta);
    }
}
