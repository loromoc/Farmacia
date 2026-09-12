# Cambios V5 — Descuentos y promociones

- Panel de administrador para crear promociones por porcentaje o monto fijo.
- Promociones aplicables a un producto o a todo el catálogo.
- Programación por fecha/hora y opción rápida “solo por hoy”.
- Activar, pausar y eliminar promociones desde administración.
- Banner de promoción destacada en la tienda.
- Precio anterior tachado, precio promocional y badge animado.
- El descuento se recalcula en PHP: carrito, cotización, pedido y Mercado Pago usan el total real del servidor.
- Historial del pedido guarda precio original, descuento unitario y nombre de promoción.

## Instalación sobre V4
Importa una sola vez `migracion_v4_a_v5.sql` en `farmacia_db`.
