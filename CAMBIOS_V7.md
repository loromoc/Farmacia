# CAMBIOS V7 — integración de las dos aplicaciones

## Interfaz
La portada de la aplicación PHP fue reconstruida tomando como base la estructura visual de FarmaPlus: barra de anuncios, navegación sticky, búsqueda, categorías, catálogo grid/list, banner de promociones, destacados, newsletter, footer, carrito lateral, toast y animaciones.

## Cambio clave
Se eliminó la dependencia de productos ficticios hardcodeados del prototipo visual. `index.php` obtiene productos y categorías de MySQL y los entrega de forma segura a los módulos JavaScript.

## Catálogo
- búsqueda instantánea;
- categorías reales;
- orden por precio/nombre;
- cuadrícula/lista;
- favoritos locales;
- imágenes reales;
- stock;
- productos con receta restringidos;
- productos sin envío restringidos.

## Carrito
El carrito visual se conectó al mismo `farmacia_cart_v2` que usa el checkout, por lo que la nueva interfaz no rompe la compra existente.

## Promociones
Nuevas tablas/columnas mediante `migracion_integracion_v7.sql`. El admin puede crear descuentos por porcentaje o cantidad fija y programar su vigencia.

## Pedidos
Los detalles conservan precio original, precio cobrado, descuento y nombre de promoción. También se añadió seguimiento visual.

## Notificaciones
El perfil permite consentimiento para email/WhatsApp. Admin dispone de WhatsApp manual; SMTP automático es opcional mediante PHPMailer.

## Newsletter
El formulario visual dejó de ser una simulación y guarda el correo en la tabla `suscriptores`.

## Archivos aportados como referencia
Los conceptos visuales y de interacción de `variables.css`, `style.css`, `animations.css`, `render.js`, `main.js`, `data.js`, `cart.js` e `index.html` fueron adaptados a la arquitectura PHP/MySQL existente.
