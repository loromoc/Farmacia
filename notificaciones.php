<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function etiqueta_estado_pedido(string $estado): string
{
    return match ($estado) {
        'pendiente_pago' => 'pendiente de pago',
        'pagado' => 'pagado',
        'preparando' => 'en preparación',
        'enviado' => 'enviado',
        'entregado' => 'entregado',
        'cancelado' => 'cancelado',
        default => str_replace('_', ' ', $estado),
    };
}

function detalle_estado_pedido(array $pedido): string
{
    $estado = (string) $pedido['estado'];
    if ($estado === 'enviado') {
        $guia = trim((string) ($pedido['guia'] ?? ''));
        return $guia !== ''
            ? 'Tu guía de envío es ' . $guia . '. Puedes consultar el seguimiento desde tu cuenta.'
            : 'Tu paquete ya salió de la farmacia. La guía aparecerá en tu cuenta cuando esté disponible.';
    }
    return match ($estado) {
        'pagado' => 'Recibimos la confirmación de tu pago.',
        'preparando' => 'Estamos preparando tu pedido para enviarlo.',
        'entregado' => 'Tu pedido fue marcado como entregado. Gracias por tu compra.',
        'cancelado' => 'Tu pedido fue cancelado. Si necesitas ayuda, contáctanos.',
        default => 'Consulta los detalles de tu pedido desde tu cuenta.',
    };
}

function normalizar_numero_whatsapp(string $telefono): ?string
{
    $numero = preg_replace('/\D+/', '', $telefono) ?? '';
    if ($numero === '') {
        return null;
    }
    // Si el cliente guardó un número mexicano de 10 dígitos, agregamos +52.
    if (strlen($numero) === 10) {
        $numero = '52' . $numero;
    }
    return strlen($numero) >= 11 && strlen($numero) <= 15 ? $numero : null;
}

function url_whatsapp_manual(array $pedido): ?string
{
    $telefono = normalizar_numero_whatsapp((string) ($pedido['telefono'] ?? ''));
    if (!$telefono) {
        return null;
    }
    $texto = sprintf(
        "Hola %s. Tu pedido %s está %s. %s",
        trim((string) ($pedido['nombre_recibe'] ?? '')),
        (string) ($pedido['folio'] ?? ''),
        etiqueta_estado_pedido((string) ($pedido['estado'] ?? '')),
        detalle_estado_pedido($pedido)
    );
    return 'https://wa.me/' . $telefono . '?text=' . rawurlencode($texto);
}

function registrar_notificacion(mysqli $conexion, int $pedidoId, int $usuarioId, string $canal, string $estadoPedido, string $destinatario, string $resultado, ?string $detalle = null): void
{
    try {
        $stmt = $conexion->prepare(
            'INSERT INTO notificaciones (pedido_id, usuario_id, canal, estado_pedido, destinatario, resultado, detalle)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('iisssss', $pedidoId, $usuarioId, $canal, $estadoPedido, $destinatario, $resultado, $detalle);
        $stmt->execute();
    } catch (Throwable $e) {
        // El registro de auditoría nunca debe bloquear la actualización del pedido.
    }
}

function enviar_correo_pedido(array $pedido): array
{
    if (!SMTP_ENABLED) {
        return ['ok' => false, 'omitido' => true, 'mensaje' => 'Correo SMTP desactivado.'];
    }
    $correo = trim((string) ($pedido['correo'] ?? ''));
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'omitido' => true, 'mensaje' => 'El cliente no tiene un correo válido.'];
    }

    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return ['ok' => false, 'omitido' => false, 'mensaje' => 'Falta instalar PHPMailer con Composer.'];
    }
    require_once $autoload;

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->CharSet = 'UTF-8';
        if (SMTP_SECURE === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif (SMTP_SECURE === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom(SMTP_FROM_EMAIL ?: SMTP_USER, SMTP_FROM_NAME ?: APP_NAME);
        $mail->addAddress($correo, (string) ($pedido['cliente_nombre'] ?? $pedido['nombre_recibe'] ?? 'Cliente'));
        $mail->isHTML(true);
        $estado = etiqueta_estado_pedido((string) $pedido['estado']);
        $detalle = detalle_estado_pedido($pedido);
        $urlPedido = APP_URL . '/pedido.php?id=' . (int) $pedido['id'];
        $mail->Subject = 'Actualización de tu pedido ' . (string) $pedido['folio'];
        $mail->Body = '<div style="font-family:Arial,sans-serif;max-width:620px;margin:auto;color:#20302c">'
            . '<h2 style="color:#087252">' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<p>Hola <strong>' . htmlspecialchars((string) ($pedido['nombre_recibe'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
            . '<p>Tu pedido <strong>' . htmlspecialchars((string) $pedido['folio'], ENT_QUOTES, 'UTF-8') . '</strong> ahora está <strong>' . htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p>' . htmlspecialchars($detalle, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Total:</strong> $' . number_format((float) $pedido['total'], 2) . ' MXN</p>'
            . '<p><a href="' . htmlspecialchars($urlPedido, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#087252;color:white;padding:12px 18px;border-radius:8px;text-decoration:none">Ver mi pedido</a></p>'
            . '<p style="font-size:12px;color:#667">Este mensaje fue enviado automáticamente porque activaste las notificaciones de tu cuenta.</p>'
            . '</div>';
        $mail->AltBody = "Tu pedido {$pedido['folio']} ahora está {$estado}. {$detalle} Ver pedido: {$urlPedido}";
        $mail->send();
        return ['ok' => true, 'omitido' => false, 'mensaje' => 'Correo enviado.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'omitido' => false, 'mensaje' => 'Error SMTP: ' . $e->getMessage()];
    }
}

function enviar_whatsapp_pedido(array $pedido): array
{
    if (!WHATSAPP_ENABLED) {
        return ['ok' => false, 'omitido' => true, 'mensaje' => 'WhatsApp automático desactivado.'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'omitido' => false, 'mensaje' => 'PHP cURL no está habilitado.'];
    }
    if (WHATSAPP_PHONE_NUMBER_ID === '' || WHATSAPP_ACCESS_TOKEN === '' || WHATSAPP_TEMPLATE_ENVIO === '') {
        return ['ok' => false, 'omitido' => false, 'mensaje' => 'Faltan credenciales o plantilla de WhatsApp.'];
    }
    $telefono = normalizar_numero_whatsapp((string) ($pedido['telefono'] ?? ''));
    if (!$telefono) {
        return ['ok' => false, 'omitido' => true, 'mensaje' => 'El cliente no tiene un teléfono válido para WhatsApp.'];
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $telefono,
        'type' => 'template',
        'template' => [
            'name' => WHATSAPP_TEMPLATE_ENVIO,
            'language' => ['code' => WHATSAPP_TEMPLATE_LANG],
            'components' => [[
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => (string) ($pedido['nombre_recibe'] ?? 'Cliente')],
                    ['type' => 'text', 'text' => (string) $pedido['folio']],
                    ['type' => 'text', 'text' => etiqueta_estado_pedido((string) $pedido['estado'])],
                    ['type' => 'text', 'text' => detalle_estado_pedido($pedido)],
                ],
            ]],
        ],
    ];

    $url = 'https://graph.facebook.com/' . rawurlencode(WHATSAPP_GRAPH_VERSION) . '/' . rawurlencode(WHATSAPP_PHONE_NUMBER_ID) . '/messages';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $respuesta = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($respuesta === false || $codigo < 200 || $codigo >= 300) {
        $detalle = $error !== '' ? $error : ('HTTP ' . $codigo . ': ' . substr((string) $respuesta, 0, 300));
        return ['ok' => false, 'omitido' => false, 'mensaje' => 'Error WhatsApp: ' . $detalle];
    }
    return ['ok' => true, 'omitido' => false, 'mensaje' => 'WhatsApp enviado.'];
}

function notificar_estado_pedido(mysqli $conexion, int $pedidoId, string $estadoAnterior, string $estadoNuevo): array
{
    if ($estadoAnterior === $estadoNuevo) {
        return ['enviadas' => 0, 'errores' => []];
    }
    if (!in_array($estadoNuevo, ['pagado', 'preparando', 'enviado', 'entregado', 'cancelado'], true)) {
        return ['enviadas' => 0, 'errores' => []];
    }

    $stmt = $conexion->prepare(
        'SELECT p.*, u.nombre AS cliente_nombre, u.correo, u.notificar_email, u.notificar_whatsapp
         FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id
         WHERE p.id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $pedidoId);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    if (!$pedido) {
        return ['enviadas' => 0, 'errores' => ['No se encontró el pedido para notificar.']];
    }

    $enviadas = 0;
    $errores = [];
    $usuarioId = (int) $pedido['usuario_id'];

    if ((int) $pedido['notificar_email'] === 1) {
        $r = enviar_correo_pedido($pedido);
        registrar_notificacion($conexion, $pedidoId, $usuarioId, 'email', $estadoNuevo, (string) $pedido['correo'], $r['ok'] ? 'enviada' : ($r['omitido'] ? 'omitida' : 'error'), $r['mensaje']);
        if ($r['ok']) $enviadas++;
        elseif (!$r['omitido']) $errores[] = $r['mensaje'];
    }

    if ((int) $pedido['notificar_whatsapp'] === 1) {
        $r = enviar_whatsapp_pedido($pedido);
        registrar_notificacion($conexion, $pedidoId, $usuarioId, 'whatsapp', $estadoNuevo, (string) $pedido['telefono'], $r['ok'] ? 'enviada' : ($r['omitido'] ? 'omitida' : 'error'), $r['mensaje']);
        if ($r['ok']) $enviadas++;
        elseif (!$r['omitido']) $errores[] = $r['mensaje'];
    }

    return ['enviadas' => $enviadas, 'errores' => $errores];
}
