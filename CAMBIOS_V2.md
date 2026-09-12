# Cambios principales respecto al ZIP original

## Corregido
- Se añadió `script.js` y un carrito funcional con cantidades, eliminación y subtotal.
- Se eliminó el error `unclick()` y la eliminación ahora usa POST + CSRF.
- Se eliminó el administrador fijo `admin@farmacia.com / admin123`.
- `crear_admin.php` solo funciona por consola.
- Conexión MySQL en `utf8mb4` y errores internos enviados al log.
- Inicio de sesión con regeneración de sesión y mensaje genérico de credenciales.
- Registro con validación de correo, contraseña y restricción UNIQUE en base de datos.
- Escape HTML centralizado para reducir XSS.
- Subida de imágenes validada por tipo real, tamaño y extensión permitida.
- Se bloquea la ejecución de PHP dentro de `uploads/` en Apache.
- Se añadieron etiquetas accesibles, foco visible y viewport móvil.

## Nuevo
- Buscador de productos.
- Edición de productos: CRUD completo.
- Campos de farmacia y logística: principio activo, presentación, receta/validación, envío permitido, peso y dimensiones.
- Checkout con dirección nacional.
- Cotización de envío estándar/express basada en peso (tarifa interna configurable).
- Validación de precio, stock y envío nuevamente en PHP.
- Pedidos y detalle de pedidos.
- Historial "Mis pedidos" para clientes.
- Panel de pedidos para administrador, estados y número de guía.
- Integración preparada con Mercado Pago Checkout Pro y webhook de verificación.
- SQL para instalación limpia y SQL de migración desde la versión original.

## Pendiente de credenciales externas
- Tarifas reales, creación de guía y rastreo automático con una paquetería/agregador.
- Cobros reales con Mercado Pago (requiere Access Token y dominio HTTPS).
