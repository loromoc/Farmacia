# Farmacia Doña Lupe — Sistema web de farmacia V7

## Descripción del proyecto

**Farmacia Doña Lupe** es una aplicación web de farmacia desarrollada con **PHP, MySQL/MariaDB, HTML, CSS y JavaScript**. El proyecto comenzó como un catálogo sencillo con registro de usuarios, inicio de sesión y administración básica de productos, y posteriormente fue evolucionando hasta convertirse en una tienda en línea con carrito, checkout, pedidos, pagos, promociones, perfil del cliente, direcciones de envío, notificaciones y una interfaz visual más completa.

La versión actual también integra lo mejor de una segunda propuesta visual llamada **FarmaPlus**, conservando el backend y la lógica real de Farmacia Doña Lupe. El resultado es una aplicación que combina una interfaz moderna con la funcionalidad real de una tienda PHP/MySQL.

---

# ¿Qué se hizo en el proyecto?

## 1. Se mejoró la estructura inicial de la farmacia

El proyecto original ya contaba con:

- registro de usuarios;
- inicio y cierre de sesión;
- roles de **administrador** y **cliente**;
- catálogo de productos;
- panel administrativo;
- carga de imágenes;
- conexión con MySQL.

A partir de esa base se reorganizó y amplió el sistema para convertirlo en una aplicación de comercio electrónico más completa.

## 2. Se corrigieron problemas de la versión original

Se corrigieron varios puntos que podían causar errores o problemas de seguridad:

- se agregó el JavaScript que faltaba para el carrito;
- se corrigió la eliminación de imágenes/productos;
- se mejoró la validación de archivos subidos;
- se eliminaron credenciales administrativas fijas del código;
- se añadieron consultas preparadas y validaciones del lado del servidor;
- se incorporó protección CSRF en acciones importantes;
- se mejoró el manejo de sesiones;
- se agregó escape de contenido HTML para reducir riesgos de XSS;
- se cambió la eliminación de registros sensibles a solicitudes `POST`;
- se mejoró el manejo de errores para que el usuario reciba mensajes claros.

## 3. Se creó un carrito de compras real

El catálogo ahora permite:

- agregar productos al carrito;
- aumentar o disminuir cantidades;
- eliminar productos;
- ver subtotal;
- conservar el carrito en el navegador;
- validar nuevamente precio y stock desde PHP antes de crear un pedido.

El servidor **no confía en precios enviados desde JavaScript**. Antes de registrar la compra, PHP vuelve a consultar los productos en la base de datos y calcula el total correcto.

## 4. Se implementó un checkout completo

Se creó un flujo de compra:

```text
Catálogo
   ↓
Carrito
   ↓
Dirección de envío
   ↓
Cotización de envío
   ↓
Pedido
   ↓
Método de pago
   ↓
Confirmación
```

El checkout valida:

- productos existentes;
- cantidades;
- stock disponible;
- precio vigente;
- promociones;
- posibilidad de envío;
- datos de dirección.

## 5. Se agregó soporte para envíos nacionales

El sistema incluye una estructura preparada para envíos nacionales.

Actualmente dispone de una tarifa interna configurable con opciones como:

- envío estándar;
- envío express;
- cálculo utilizando el peso del pedido.

Los productos pueden guardar datos logísticos como:

- peso;
- alto;
- ancho;
- largo;
- si permiten envío nacional.

La función actual puede sustituirse en el futuro por una API de paquetería sin necesidad de rehacer el carrito o el checkout.

## 6. Se creó el sistema de pedidos

Se añadieron tablas y pantallas para manejar pedidos reales.

Cada pedido puede guardar:

- folio;
- cliente;
- productos;
- cantidades;
- subtotal;
- costo de envío;
- total;
- dirección utilizada;
- estado del pedido;
- estado del pago;
- proveedor de pago;
- guía de envío.

Los estados utilizados permiten representar el flujo de atención:

```text
Pendiente de pago
      ↓
Pagado
      ↓
Preparando
      ↓
Enviado
      ↓
Entregado
```

También existe la posibilidad de cancelar pedidos cuando corresponda.

## 7. Se creó el área “Mis pedidos” para clientes

El cliente puede consultar sus compras y revisar:

- folio;
- fecha;
- total;
- estado;
- productos;
- dirección de entrega;
- número de guía cuando exista.

Se incorporó además un seguimiento visual para que el cliente pueda reconocer con mayor facilidad en qué etapa se encuentra su pedido.

## 8. Se integró Mercado Pago

Se preparó la integración con **Mercado Pago Checkout Pro**.

El backend puede:

- crear una preferencia de pago;
- enviar a Mercado Pago el pedido real;
- utilizar el total calculado por el servidor;
- regresar al sitio después del pago;
- consultar el estado del pago;
- recibir notificaciones mediante Webhook;
- actualizar el pedido cuando el pago es aprobado.

La configuración se realiza mediante variables del servidor, por ejemplo:

```apache
SetEnv MERCADOPAGO_ACCESS_TOKEN "TU_ACCESS_TOKEN"
SetEnv MERCADOPAGO_USE_SANDBOX "1"
SetEnv APP_URL "https://TU-DOMINIO-O-TUNEL/Trabajo_v2"
```

El Access Token no se guarda directamente en JavaScript.

## 9. Se agregó una pasarela interna de demostración

Además de Mercado Pago se creó una **pasarela interna de prueba**.

Esta opción sirve para demostrar el flujo completo del proyecto sin realizar un cobro real. Permite simular un pago aprobado y comprobar cómo se actualizan pedido y pago.

> La pasarela interna es únicamente demostrativa. No procesa tarjetas reales ni almacena CVV o números de tarjeta.

## 10. Se añadió una pantalla clara de pago aprobado

Después de un pago exitoso, el usuario puede visualizar una confirmación con información como:

- pedido;
- total;
- estado del pago;
- proveedor;
- referencia de pago.

El sistema también puede consultar periódicamente el estado cuando la confirmación todavía se encuentra pendiente.

## 11. Se mejoró el perfil del cliente

Se creó una sección **Mi perfil** donde cada cliente puede administrar:

- nombre;
- correo;
- teléfono;
- contraseña;
- preferencias de notificación.

## 12. Se añadió dirección de envío al perfil

El cliente puede guardar una dirección principal con:

- calle;
- número exterior;
- número interior;
- colonia;
- código postal;
- municipio o alcaldía;
- estado;
- referencias.

El checkout carga automáticamente esa información para evitar que el cliente tenga que escribirla en cada compra.

La dirección utilizada se copia también al pedido. De esta manera, si el cliente cambia posteriormente su dirección en el perfil, los pedidos anteriores conservan los datos originales con los que fueron realizados.

## 13. Se implementaron promociones y descuentos desde Administración

El administrador puede crear descuentos desde el panel.

Las promociones pueden configurarse por:

- producto individual;
- todo el catálogo;
- porcentaje;
- cantidad fija en pesos;
- fecha y hora de inicio;
- fecha y hora de finalización;
- promoción únicamente por el día;
- promoción destacada en la tienda;
- estado activa/inactiva.

Si coinciden varias promociones, PHP utiliza la opción que deje el menor precio final.

Los pedidos guardan un historial del descuento aplicado:

- precio original;
- precio final;
- descuento por unidad;
- nombre de la promoción.

Por lo tanto, eliminar o finalizar una promoción posteriormente no modifica los pedidos ya realizados.

## 14. Se añadieron notificaciones para el seguimiento de pedidos

Se preparó un sistema de notificaciones para avisar al cliente sobre cambios de estado.

### Correo electrónico

Puede utilizarse **PHPMailer + SMTP** para enviar correos cuando un pedido cambie de estado.

Estados que pueden generar avisos:

- pagado;
- preparando;
- enviado;
- entregado;
- cancelado.

### WhatsApp manual

El administrador dispone de un botón que abre WhatsApp con un mensaje precargado para el cliente. Esto funciona sin configurar una API externa.

### WhatsApp automático

La arquitectura también quedó preparada para una integración posterior con WhatsApp Cloud API, siempre respetando el consentimiento del cliente.

El cliente puede decidir desde su perfil si desea recibir:

- avisos por correo;
- avisos por WhatsApp.

## 15. Se añadió historial de notificaciones

La base de datos puede registrar el resultado de los avisos como:

```text
enviada
error
omitida
```

Un fallo al enviar una notificación no revierte el cambio de estado del pedido.

## 16. Se integró el diseño FarmaPlus con Farmacia Doña Lupe

Se tomó la propuesta visual FarmaPlus y se adaptó a la aplicación PHP/MySQL.

Se conservaron e integraron elementos como:

- azul clínico y verde farmacéutico;
- barra informativa;
- navbar sticky;
- buscador;
- hero principal;
- tarjetas de categorías;
- catálogo en cuadrícula;
- vista de lista;
- favoritos;
- carrito lateral;
- banner de promociones;
- productos destacados;
- newsletter;
- footer completo;
- notificaciones tipo toast;
- botón para volver al inicio;
- animaciones y transiciones.

La diferencia principal es que ahora esos componentes **no trabajan con productos ficticios de JavaScript**. Los productos, precios, imágenes, stock y promociones provienen de `farmacia_db`.

## 17. Se añadieron animaciones y mejoras visuales

Se incorporaron efectos como:

- gradientes animados;
- efectos hover;
- entrada de elementos;
- carrito lateral animado;
- mensajes toast;
- animaciones al agregar productos;
- transición del navbar al hacer scroll;
- animación de pago aprobado;
- cuenta regresiva de promociones;
- diseño responsive para teléfonos y tablets.

También se contempla `prefers-reduced-motion` para usuarios que prefieren reducir animaciones.

## 18. Se creó una newsletter conectada a la base de datos

El formulario de suscripción dejó de ser únicamente visual.

Ahora permite guardar correos en una tabla de suscriptores para futuras campañas o avisos, evitando duplicados cuando corresponda.

## 19. Se reforzó la seguridad

Entre las medidas implementadas se encuentran:

- `password_hash()` y `password_verify()`;
- consultas preparadas con MySQLi;
- tokens CSRF;
- sesiones con opciones seguras;
- regeneración del ID de sesión al autenticar;
- validaciones del lado del servidor;
- escape con `htmlspecialchars()`;
- control de roles;
- validación de imágenes JPG/PNG/WebP;
- nombres aleatorios para archivos subidos;
- bloqueo de ejecución de PHP dentro de `uploads`;
- comprobación de stock y precios antes de registrar pedidos;
- variables de entorno para credenciales sensibles.

## 20. Se mejoró el manejo de medicamentos

Los productos pueden incluir datos adicionales como:

- principio activo;
- presentación;
- categoría;
- peso y dimensiones;
- requerimiento de receta;
- disponibilidad para envío;
- estado activo/inactivo.

Los productos marcados como `requiere_receta=1` quedan restringidos mientras no exista un proceso formal de validación de receta.

---

# Tecnologías utilizadas

| Tecnología | Uso |
|---|---|
| PHP 8 | Backend, sesiones, pedidos, pagos y administración |
| MySQL / MariaDB | Base de datos |
| HTML5 | Estructura de páginas |
| CSS3 | Diseño, responsive y animaciones |
| JavaScript | Carrito, filtros, buscador, interacciones y UI |
| MySQLi | Comunicación segura con la base de datos |
| Mercado Pago API | Pasarela de pago |
| PHPMailer | Notificaciones por correo SMTP |
| Cloudflare Tunnel | Pruebas públicas del sitio local y Webhooks |
| XAMPP | Entorno local Apache + PHP + MariaDB |

---

# Módulos principales

```text
Farmacia Doña Lupe
│
├── Tienda / catálogo
├── Categorías y búsqueda
├── Carrito
├── Registro e inicio de sesión
├── Perfil y dirección
├── Checkout
├── Envíos
├── Pedidos
├── Mis pedidos
├── Mercado Pago
├── Pago interno de demostración
├── Promociones
├── Newsletter
├── Notificaciones
│   ├── Correo
│   └── WhatsApp
└── Administración
    ├── Productos
    ├── Inventario
    ├── Promociones
    └── Pedidos
```

---

# Base de datos

Las tablas principales utilizadas por el proyecto son:

```text
usuarios
productos
pedidos
pedido_detalles
pagos
promociones
notificaciones
suscriptores
```

## `usuarios`

Contiene información de autenticación, rol, perfil, dirección y preferencias de contacto.

## `productos`

Contiene catálogo, stock, precio, imagen, información farmacéutica y datos para envío.

## `pedidos`

Guarda la información general de cada compra y una copia de la dirección utilizada.

## `pedido_detalles`

Guarda cada producto comprado, cantidades y precios históricos, incluyendo descuentos.

## `pagos`

Permite guardar proveedor, referencia y estado de pago.

## `promociones`

Gestiona descuentos, vigencia, producto relacionado y estado.

## `notificaciones`

Permite registrar los intentos de correo/WhatsApp y su resultado.

## `suscriptores`

Almacena los correos registrados mediante la newsletter.

---

# Flujo general del cliente

```text
Crear cuenta / iniciar sesión
          ↓
Completar perfil y dirección
          ↓
Buscar productos
          ↓
Agregar al carrito
          ↓
Revisar carrito
          ↓
Checkout
          ↓
Elegir envío
          ↓
Crear pedido
          ↓
Elegir método de pago
          ↓
Pago aprobado
          ↓
Seguimiento del pedido
          ↓
Preparando → Enviado → Entregado
```

---

# Funciones del administrador

El administrador puede:

- registrar productos;
- editar productos;
- eliminar productos;
- controlar stock;
- subir imágenes;
- crear promociones;
- pausar y activar promociones;
- eliminar promociones;
- consultar pedidos;
- cambiar estados de pedido;
- agregar guía de envío;
- abrir WhatsApp con mensaje de seguimiento;
- disparar notificaciones por correo cuando estén configuradas.

---

# Instalación si ya existe una versión anterior

1. Haz respaldo de `farmacia_db` desde phpMyAdmin.
2. Haz respaldo de tu carpeta actual `C:\xampp\htdocs\Trabajo_v2`.
3. Reemplaza la carpeta por la nueva versión.
4. Selecciona `farmacia_db` en phpMyAdmin.
5. Importa **una sola vez**:

```text
migracion_integracion_v7.sql
```

6. Reinicia Apache y MySQL.
7. Abre:

```text
http://localhost/Trabajo_v2/
```

La migración está diseñada para conservar usuarios, productos, pedidos y pagos existentes.

## Reparación de promociones para bases provenientes de versiones anteriores

Si el panel muestra un error similar a:

```text
Unknown column 'activa' in 'where clause'
```

importa una sola vez:

```text
reparar_promociones_v7.sql
```

Este parche agrega las columnas de estado requeridas por el módulo de promociones sin borrar datos existentes.

---

# Instalación limpia

Si se va a crear una base desde cero:

1. Inicia Apache y MySQL en XAMPP.
2. Importa `database.sql` en phpMyAdmin.
3. Coloca la carpeta en:

```text
C:\xampp\htdocs\Trabajo_v2
```

4. Crea un administrador desde consola:

```bash
php crear_admin.php admin@tudominio.com "UnaClaveMuySegura" "Administrador"
```

5. Abre:

```text
http://localhost/Trabajo_v2/
```

---

# Configuración de Mercado Pago

En Apache se pueden configurar variables como:

```apache
SetEnv MERCADOPAGO_ACCESS_TOKEN "TU_ACCESS_TOKEN"
SetEnv MERCADOPAGO_USE_SANDBOX "1"
SetEnv APP_URL "https://TU-DOMINIO-O-TUNEL/Trabajo_v2"
```

Después se debe reiniciar Apache.

Para probar Webhooks en desarrollo se puede utilizar Cloudflare Tunnel.

Ejemplo:

```powershell
cloudflared tunnel --url http://localhost:80
```

El túnel genera una URL pública temporal. Esa URL debe utilizarse también en `APP_URL` y en la configuración de Webhooks de Mercado Pago mientras se realicen pruebas.

---

# Configuración de correo SMTP

El proyecto puede utilizar PHPMailer.

Instala dependencias:

```bash
composer install
```

Ejemplo de configuración en Apache:

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

Después reinicia Apache.

Puedes comprobar la configuración desde:

```text
diagnostico_notificaciones.php
```

---

# Archivos importantes

| Archivo | Función |
|---|---|
| `index.php` | Tienda principal |
| `registro.php` | Registro de clientes |
| `login.php` | Inicio de sesión |
| `perfil.php` | Perfil y dirección |
| `checkout.php` | Datos de entrega y cotización |
| `crear_pedido.php` | Validación y creación del pedido |
| `pago.php` | Selección de método de pago |
| `pagar_pedido.php` | Integración con Mercado Pago |
| `mercadopago_webhook.php` | Notificaciones de Mercado Pago |
| `pago_resultado.php` | Resultado y confirmación de pago |
| `mis_pedidos.php` | Historial del cliente |
| `pedido.php` | Detalle y seguimiento de pedido |
| `panel_admin.php` | Productos y promociones |
| `panel_pedidos.php` | Administración de pedidos |
| `funciones.php` | Funciones compartidas |
| `config.php` | Configuración general |
| `conexion.php` | Conexión a MySQL |
| `assets/css/` | Diseño integrado FarmaPlus |
| `assets/js/` | Interacciones de la tienda |

---

# Pruebas recomendadas

Antes de presentar o publicar el proyecto conviene comprobar:

1. Crear una cuenta nueva.
2. Iniciar y cerrar sesión.
3. Guardar una dirección en Mi perfil.
4. Crear un producto como administrador.
5. Editar el producto.
6. Crear una promoción.
7. Comprobar el precio promocional en la tienda.
8. Agregar productos al carrito.
9. Cambiar cantidades.
10. Continuar al checkout.
11. Comprobar que aparezca la dirección guardada.
12. Cotizar envío.
13. Crear el pedido.
14. Probar pago interno de demostración.
15. Probar Mercado Pago en sandbox.
16. Comprobar la pantalla de pago aprobado.
17. Cambiar pedido a Preparando.
18. Cambiar pedido a Enviado y colocar una guía.
19. Probar WhatsApp manual.
20. Probar correo SMTP si está configurado.
21. Marcar el pedido como Entregado.

---

# Resultado final del proyecto

El proyecto pasó de ser un catálogo básico de farmacia a una aplicación web que integra:

```text
CATÁLOGO
+ INVENTARIO
+ USUARIOS
+ PERFILES
+ DIRECCIONES
+ CARRITO
+ CHECKOUT
+ ENVÍOS
+ PEDIDOS
+ PAGOS
+ PROMOCIONES
+ NOTIFICACIONES
+ SEGUIMIENTO
+ ADMINISTRACIÓN
+ DISEÑO RESPONSIVE
```

La aplicación conserva una arquitectura sencilla basada en PHP/MySQL para que pueda seguir desarrollándose fácilmente en XAMPP, pero al mismo tiempo deja preparada la estructura para conectar en el futuro servicios reales de paquetería, notificaciones automáticas por WhatsApp y otras formas de pago.

---

# Mejoras futuras sugeridas

- conectar una API real de paquetería para tarifas y guías automáticas;
- validar recetas mediante un flujo administrativo;
- dashboard con estadísticas de ventas;
- reportes por día, mes y producto;
- comprobantes o facturación;
- recuperación de contraseña por correo;
- verificación de correo electrónico;
- WhatsApp Cloud API automático;
- administración de múltiples direcciones por cliente;
- panel de métricas de promociones;
- pruebas automatizadas.

---

## Proyecto académico / demostrativo

Esta aplicación se desarrolló como un proyecto web de farmacia y comercio electrónico. Las funciones relacionadas con medicamentos, recetas, pagos y envíos deben adaptarse a la normativa, infraestructura y proveedores reales antes de utilizarse en un entorno comercial de producción.
