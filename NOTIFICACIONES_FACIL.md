# Notificaciones fáciles: correo + WhatsApp

## 1. Importa la migración
En phpMyAdmin, dentro de `farmacia_db`, importa una sola vez:

`migracion_v5_a_v6_notificaciones.sql`

## 2. WhatsApp manual (funciona sin API)
No requiere configuración. En Administración > Pedidos aparece `WhatsApp manual`.
Abre WhatsApp con el mensaje del pedido ya escrito; el administrador solo pulsa Enviar.

## 3. Correo automático (recomendado para empezar)
El proyecto usa PHPMailer por SMTP porque XAMPP/Windows normalmente no incluye un servidor de correo local.

### Instalar PHPMailer
Instala Composer y ejecuta `instalar_correo.bat`, o dentro de la carpeta del proyecto:

`composer install --no-dev`

### Gmail
Usa una cuenta destinada a la farmacia. Activa verificación en 2 pasos y crea una contraseña de aplicación.
No uses la contraseña normal de Gmail como `SMTP_PASS`.

En `C:\xampp\apache\conf\httpd.conf` agrega, sin `#`:

```apache
SetEnv SMTP_ENABLED "1"
SetEnv SMTP_HOST "smtp.gmail.com"
SetEnv SMTP_PORT "587"
SetEnv SMTP_SECURE "tls"
SetEnv SMTP_USER "correo.de.la.farmacia@gmail.com"
SetEnv SMTP_PASS "CONTRASENA_DE_APLICACION"
SetEnv SMTP_FROM_EMAIL "correo.de.la.farmacia@gmail.com"
SetEnv SMTP_FROM_NAME "Farmacia Doña Lupe"
```

Reinicia Apache (Stop > Start).

## 4. WhatsApp automático (opcional)
Para que los mensajes salgan solos se usa Meta WhatsApp Cloud API.
El cliente debe activar WhatsApp en `Mi perfil`.

Crea una plantilla Utility con cuatro variables. Texto sugerido:

`Hola {{1}}. Tu pedido {{2}} ahora está {{3}}. {{4}}`

Cuando Meta la apruebe, configura:

```apache
SetEnv WHATSAPP_ENABLED "1"
SetEnv WHATSAPP_GRAPH_VERSION "v26.0"
SetEnv WHATSAPP_PHONE_NUMBER_ID "TU_PHONE_NUMBER_ID"
SetEnv WHATSAPP_ACCESS_TOKEN "TU_TOKEN_PERMANENTE"
SetEnv WHATSAPP_TEMPLATE_ENVIO "actualizacion_envio"
SetEnv WHATSAPP_TEMPLATE_LANG "es_MX"
```

Reinicia Apache.

## 5. Comprobar configuración
Como administrador abre:

`http://localhost/Trabajo_v2/diagnostico_notificaciones.php`

No muestra contraseñas ni tokens, solo indica qué está configurado.

## 6. Cuándo se envían avisos
Al cambiar un pedido a:
- pagado
- preparando
- enviado
- entregado
- cancelado

Si una notificación falla, el pedido queda actualizado igualmente y el admin recibe un aviso.

## 7. Guía de envío
Cuando cambies a `enviado`, escribe primero el número de guía en el panel. El correo/WhatsApp incluirá esa guía.
