<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion_segura();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

try {
    $data = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $token = (string) ($data['csrf_token'] ?? '');
    $actual = (string) ($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $actual === '' || !hash_equals($actual, $token)) {
        throw new RuntimeException('La sesión expiró. Actualiza la página e inténtalo de nuevo.');
    }

    $correo = normalizar_correo((string) ($data['correo'] ?? ''));
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 190) {
        throw new RuntimeException('Escribe un correo electrónico válido.');
    }

    $stmt = $conexion->prepare(
        'INSERT INTO suscriptores (correo, activo)
         VALUES (?, 1)
         ON DUPLICATE KEY UPDATE activo=1, actualizado_en=CURRENT_TIMESTAMP'
    );
    $stmt->bind_param('s', $correo);
    $stmt->execute();

    echo json_encode(
        ['mensaje' => 'Tu correo quedó registrado para futuras novedades.'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(
        ['error' => $e instanceof RuntimeException ? $e->getMessage() : 'No fue posible guardar el correo.'],
        JSON_UNESCAPED_UNICODE
    );
}
