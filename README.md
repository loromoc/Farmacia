# Farmacia Doña Lupe — V7 integrada

Esta versión combina la aplicación PHP/MySQL existente con la interfaz visual FarmaPlus aportada como referencia. La portada dejó de ser un prototipo con productos hardcodeados: ahora categorías, productos, existencias, imágenes, promociones y precios salen de `farmacia_db`.

## Qué se integró

- Portada premium azul clínico + verde farmacéutico.
- Barra informativa, navbar sticky, buscador instantáneo, categorías dinámicas.
- Vista catálogo en cuadrícula/lista.
- Favoritos en `localStorage`.
- Carrito lateral animado conectado al checkout PHP real.
- Imágenes reales del inventario en lugar de emojis de productos.
- Promociones administrables por porcentaje o monto fijo.
- Promoción destacada con cuenta regresiva real según `fecha_fin`.
- Precio promocional revalidado en PHP.
- Perfil con dirección principal precargada en checkout.
- Mercado Pago y pasarela demo existentes.
- Seguimiento visual del pedido.
- Newsletter que guarda correos en MySQL.
- Preferencias de notificación por correo y WhatsApp.
- Botón manual de WhatsApp desde Pedidos, respetando el opt-in del cliente.
- Correo SMTP opcional con PHPMailer cuando el admin cambia el estado del pedido.

## Instalación si ya tienes la versión anterior funcionando

1. Haz un respaldo de `farmacia_db`.
2. Reemplaza la carpeta `C:\xampp\htdocs\Trabajo_v2` por esta.
3. En phpMyAdmin selecciona `farmacia_db`.
4. Importa **una sola vez** `migracion_integracion_v7.sql`.
5. Reinicia Apache.
6. Abre `http://localhost/Trabajo_v2/`.

La migración no borra usuarios, productos, pedidos ni pagos. Añade promociones, suscriptores, notificaciones y campos necesarios para guardar el descuento aplicado en el historial del pedido.

## Instalación limpia

Importa `database.sql` y luego crea un administrador desde consola:

```bash
php crear_admin.php admin@tudominio.com "UnaClaveMuySegura" "Administrador"
```

## Mercado Pago

Se conservan las variables existentes:

```apache
SetEnv MERCADOPAGO_ACCESS_TOKEN "TU_ACCESS_TOKEN"
SetEnv MERCADOPAGO_USE_SANDBOX "1"
SetEnv APP_URL "https://TU-TUNEL.trycloudflare.com/Trabajo_v2"
```

Para producción usa HTTPS y credenciales de producción.

## Promociones

Entra como administrador en `panel_admin.php`.

Puedes elegir:

- un producto concreto o todo el catálogo;
- porcentaje o monto fijo;
- inicio y fin;
- “solo por hoy”;
- promoción destacada.

Si varias promociones coinciden, PHP aplica la que deje el precio final más bajo. El navegador no decide el precio final.

## Dirección del cliente

El cliente puede guardar desde `perfil.php`:

- teléfono;
- calle y números;
- colonia;
- código postal;
- municipio/alcaldía;
- estado;
- referencias.

`checkout.php` precarga esa dirección. El pedido guarda una copia histórica, por lo que cambiar el perfil después no modifica pedidos anteriores.

## Notificaciones fáciles

### WhatsApp manual

No requiere API. El cliente debe activar “Autorizo avisos de seguimiento por WhatsApp” en `perfil.php`.

Después, en `panel_pedidos.php`, aparece el botón WhatsApp para abrir una conversación con mensaje precargado. Si el pedido está enviado e incluye guía, el texto incluye la guía.

### Correo automático con SMTP

Instala Composer y desde la carpeta del proyecto ejecuta:

```bash
composer install
```

Luego agrega en `C:\xampp\apache\conf\httpd.conf`:

```apache
SetEnv SMTP_ENABLED "1"
SetEnv SMTP_HOST "smtp.gmail.com"
SetEnv SMTP_PORT "587"
SetEnv SMTP_SECURE "tls"
SetEnv SMTP_USER "correo.farmacia@gmail.com"
SetEnv SMTP_PASS "CONTRASENA_DE_APLICACION"
SetEnv SMTP_FROM_EMAIL "correo.farmacia@gmail.com"
SetEnv SMTP_FROM_NAME "Farmacia Doña Lupe"
```

Reinicia Apache. Puedes revisar el estado en `diagnostico_notificaciones.php`.

No guardes la contraseña normal de Gmail. Si utilizas Gmail, usa una contraseña de aplicación de una cuenta configurada para ello.

## Envíos

La versión actual conserva `tarifa_envio_manual()`:

- estándar;
- express;
- cálculo según peso.

El precio y stock se validan nuevamente desde PHP. Más adelante esta función puede sustituirse por una API de paquetería sin rehacer carrito/checkout.

## Archivos visuales integrados

La nueva portada usa:

- `assets/css/variables.css`
- `assets/css/animations.css`
- `assets/css/storefront.css`
- `assets/css/storefront-overrides.css`
- `assets/js/cart.js`
- `assets/js/render.js`
- `assets/js/main.js`

Los productos ya no están definidos en un `data.js` estático: `index.php` genera los datos desde MySQL.

## Seguridad

Se conservan:

- MySQLi con consultas preparadas;
- `password_hash()` / `password_verify()`;
- CSRF;
- escape HTML;
- sesión HttpOnly/SameSite;
- validación del servidor para carrito, stock, precios y envío;
- subida de imágenes JPG/PNG/WebP validada;
- bloqueo de ejecución PHP en `uploads`.

Además, los productos marcados `requiere_receta=1` no se pueden completar desde el carrito mientras no exista un flujo real de validación de receta.

## Pruebas recomendadas

1. Registro y login.
2. Guardar dirección en Perfil.
3. Crear una promoción desde Admin.
4. Comprobar precio tachado/nuevo en portada.
5. Agregar productos y cambiar cantidades.
6. Ir a Checkout y comprobar que el servidor conserva el precio promocional.
7. Crear pedido.
8. Pagar en sandbox.
9. Cambiar pedido a Preparando/Enviado.
10. Añadir guía y probar WhatsApp/correo.
