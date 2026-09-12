# Farmacia Doña Lupe — Cambios V4

## Diseño y experiencia
- Rediseño visual completo con una interfaz más moderna, limpia y adaptable a móvil.
- Nuevo hero de portada con tarjetas flotantes y microanimaciones.
- Animaciones de entrada al hacer scroll con `IntersectionObserver`.
- Respeto por `prefers-reduced-motion` para usuarios que desactivan animaciones.
- Tarjetas de productos con hover, zoom suave de imagen y estados visuales.
- Notificación tipo toast al agregar o retirar productos del carrito.
- Carrito con controles de cantidad más claros y resumen mejorado.
- Checkout con indicador de pasos: Carrito → Envío → Pago.
- Cotización de envío con estado de carga animado.
- Pantalla de pago aprobado con animación y confeti visual.
- Mejoras de diseño en login, registro, administración, pedidos y pasarelas.

## Perfil y dirección
Se añadieron estos campos a `usuarios`:
- `telefono`
- `calle`
- `numero_ext`
- `numero_int`
- `colonia`
- `codigo_postal`
- `municipio`
- `estado_destino`
- `referencias`

### Registro
El cliente puede elegir guardar su dirección principal al crear su cuenta.

### Mi perfil
Nuevo archivo `perfil.php`:
- Cambiar nombre y correo.
- Guardar o modificar domicilio.
- Guardar teléfono.
- Cambiar contraseña de manera opcional.
- Indicador de porcentaje de dirección completada.

### Checkout
- Precarga automáticamente la dirección guardada.
- Si el cliente modifica la dirección, puede guardar los cambios como su nueva dirección principal.
- Cada pedido conserva una copia propia del domicilio utilizado. Cambiar el perfil después no modifica pedidos anteriores.

## Base de datos
Para una base que ya usa V3, importar únicamente:

`migracion_v3_a_v4.sql`

No es necesario volver a ejecutar `migracion_desde_v1.sql`.

Para instalaciones nuevas usar `database.sql`.
