# V6 - Notificaciones de envíos

## Incluido
- Correo automático por SMTP + PHPMailer.
- WhatsApp automático mediante Meta WhatsApp Cloud API (opcional).
- WhatsApp manual de un clic desde el panel de pedidos, sin API.
- Preferencias de notificación en Mi perfil.
- Consentimiento separado para WhatsApp.
- Registro de intentos en la tabla `notificaciones`.
- Los fallos al enviar un aviso nunca deshacen la actualización del pedido.
- Diagnóstico en `diagnostico_notificaciones.php`.

## Estados que disparan avisos
`pagado`, `preparando`, `enviado`, `entregado` y `cancelado`.

## Plantilla de WhatsApp recomendada
Crear una plantilla de categoría Utility con 4 variables, por ejemplo:

`Hola {{1}}. Tu pedido {{2}} ahora está {{3}}. {{4}}`

Guarda el nombre aprobado en `WHATSAPP_TEMPLATE_ENVIO`.

## Correo - Gmail sencillo
1. Instala Composer.
2. Ejecuta `instalar_correo.bat`.
3. Activa verificación en 2 pasos en la cuenta Gmail remitente.
4. Crea una contraseña de aplicación.
5. Configura las variables SMTP en Apache `httpd.conf`.

## Seguridad
No guardes SMTP_PASS ni WHATSAPP_ACCESS_TOKEN directamente en JavaScript ni en archivos públicos versionados.
