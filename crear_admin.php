<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/conexion.php';

$correo = $argv[1] ?? '';
$password = $argv[2] ?? '';
$nombre = $argv[3] ?? 'Administrador';

if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
    fwrite(STDERR, "Uso: php crear_admin.php correo@dominio.com 'ContraseñaSegura' 'Nombre'\n");
    fwrite(STDERR, "La contraseña debe tener al menos 10 caracteres.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conexion->prepare("INSERT INTO usuarios (nombre, correo, password, rol) VALUES (?, ?, ?, 'admin') ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), password=VALUES(password), rol='admin'");
$stmt->bind_param('sss', $nombre, $correo, $hash);
$stmt->execute();

echo "Administrador creado/actualizado correctamente.\n";
