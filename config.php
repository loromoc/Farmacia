<?php

declare(strict_types=1);

const APP_NAME = 'Farmacia Doña Lupe';

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

date_default_timezone_set((string) env_value('APP_TIMEZONE', 'America/Mexico_City'));

define('APP_URL', rtrim((string) env_value('APP_URL', 'http://localhost/Trabajo_v2'), '/'));
define('DB_HOST', env_value('DB_HOST', 'localhost'));
define('DB_PORT', (int) env_value('DB_PORT', '3306'));
define('DB_NAME', env_value('DB_NAME', 'farmacia_db'));
define('DB_USER', env_value('DB_USER', 'root'));
define('DB_PASS', env_value('DB_PASS', ''));
define('MERCADOPAGO_ACCESS_TOKEN', env_value('MERCADOPAGO_ACCESS_TOKEN', ''));
define('MERCADOPAGO_USE_SANDBOX', env_value('MERCADOPAGO_USE_SANDBOX', '1') === '1');
define('PAGO_INTERNO_DEMO', env_value('PAGO_INTERNO_DEMO', '0') === '1');

define('SMTP_ENABLED', env_value('SMTP_ENABLED', '0') === '1');
define('SMTP_HOST', env_value('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int) env_value('SMTP_PORT', '587'));
define('SMTP_SECURE', env_value('SMTP_SECURE', 'tls'));
define('SMTP_USER', env_value('SMTP_USER', ''));
define('SMTP_PASS', env_value('SMTP_PASS', ''));
define('SMTP_FROM_EMAIL', env_value('SMTP_FROM_EMAIL', SMTP_USER));
define('SMTP_FROM_NAME', env_value('SMTP_FROM_NAME', APP_NAME));

define('MAX_UPLOAD_BYTES', 3 * 1024 * 1024);
