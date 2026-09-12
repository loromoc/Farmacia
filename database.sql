CREATE DATABASE IF NOT EXISTS farmacia_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE farmacia_db;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    correo VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
    telefono VARCHAR(30) NULL,
    calle VARCHAR(160) NULL,
    numero_ext VARCHAR(30) NULL,
    numero_int VARCHAR(30) NULL,
    colonia VARCHAR(120) NULL,
    codigo_postal CHAR(5) NULL,
    municipio VARCHAR(120) NULL,
    estado_destino VARCHAR(120) NULL,
    referencias VARCHAR(255) NULL,
    notificar_email TINYINT(1) NOT NULL DEFAULT 1,
    notificar_whatsapp TINYINT(1) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS productos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(160) NOT NULL,
    descripcion TEXT NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    principio_activo VARCHAR(160) NULL,
    presentacion VARCHAR(160) NULL,
    precio DECIMAL(10,2) UNSIGNED NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    imagen VARCHAR(255) NULL,
    requiere_receta TINYINT(1) NOT NULL DEFAULT 0,
    envio_permitido TINYINT(1) NOT NULL DEFAULT 1,
    peso_kg DECIMAL(8,3) UNSIGNED NOT NULL DEFAULT 0.250,
    alto_cm DECIMAL(8,2) UNSIGNED NULL,
    ancho_cm DECIMAL(8,2) UNSIGNED NULL,
    largo_cm DECIMAL(8,2) UNSIGNED NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_productos_categoria (categoria),
    INDEX idx_productos_activo (activo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedidos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    folio VARCHAR(40) NOT NULL UNIQUE,
    usuario_id INT UNSIGNED NOT NULL,
    estado ENUM('pendiente_pago','pagado','preparando','enviado','entregado','cancelado') NOT NULL DEFAULT 'pendiente_pago',
    pago_estado ENUM('pendiente','aprobado','rechazado','reembolsado') NOT NULL DEFAULT 'pendiente',
    pago_proveedor VARCHAR(40) NOT NULL DEFAULT 'mercadopago',
    pago_referencia VARCHAR(120) NULL,
    pago_preferencia VARCHAR(120) NULL,
    subtotal DECIMAL(10,2) UNSIGNED NOT NULL,
    costo_envio DECIMAL(10,2) UNSIGNED NOT NULL,
    total DECIMAL(10,2) UNSIGNED NOT NULL,
    envio_metodo ENUM('estandar','express') NOT NULL,
    envio_proveedor VARCHAR(80) NOT NULL DEFAULT 'Tarifa interna',
    guia VARCHAR(120) NULL,
    nombre_recibe VARCHAR(120) NOT NULL,
    telefono VARCHAR(30) NOT NULL,
    calle VARCHAR(160) NOT NULL,
    numero_ext VARCHAR(30) NOT NULL,
    numero_int VARCHAR(30) NULL,
    colonia VARCHAR(120) NOT NULL,
    codigo_postal CHAR(5) NOT NULL,
    municipio VARCHAR(120) NOT NULL,
    estado_destino VARCHAR(120) NOT NULL,
    referencias VARCHAR(255) NULL,
    notificar_email TINYINT(1) NOT NULL DEFAULT 1,
    notificar_whatsapp TINYINT(1) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_pedidos_usuario (usuario_id),
    INDEX idx_pedidos_estado (estado),
    INDEX idx_pedidos_fecha (creado_en)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedido_detalles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id BIGINT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NULL,
    producto_nombre VARCHAR(160) NOT NULL,
    precio_original DECIMAL(10,2) UNSIGNED NULL,
    precio_unitario DECIMAL(10,2) UNSIGNED NOT NULL,
    descuento_unitario DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
    promocion_nombre VARCHAR(140) NULL,
    cantidad INT UNSIGNED NOT NULL,
    total_linea DECIMAL(10,2) UNSIGNED NOT NULL,
    CONSTRAINT fk_detalle_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    CONSTRAINT fk_detalle_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL,
    INDEX idx_detalle_pedido (pedido_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pagos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id BIGINT UNSIGNED NOT NULL,
    proveedor VARCHAR(40) NOT NULL,
    referencia_externa VARCHAR(120) NOT NULL,
    estado VARCHAR(40) NOT NULL,
    monto DECIMAL(10,2) UNSIGNED NOT NULL,
    moneda CHAR(3) NOT NULL DEFAULT 'MXN',
    respuesta_json JSON NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pago_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_pago_proveedor_referencia (proveedor, referencia_externa)
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS promociones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(140) NOT NULL,
    descripcion VARCHAR(255) NULL,
    producto_id INT UNSIGNED NULL,
    tipo ENUM('porcentaje','fijo') NOT NULL,
    valor DECIMAL(10,2) UNSIGNED NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    destacada TINYINT(1) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_promocion_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    INDEX idx_promociones_vigencia (activa, fecha_inicio, fecha_fin),
    INDEX idx_promociones_producto (producto_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notificaciones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id BIGINT UNSIGNED NOT NULL,
    canal ENUM('email','whatsapp') NOT NULL,
    destino VARCHAR(190) NOT NULL,
    estado ENUM('enviada','error','omitida') NOT NULL,
    detalle TEXT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notificacion_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    INDEX idx_notificaciones_pedido (pedido_id),
    INDEX idx_notificaciones_fecha (creado_en)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS suscriptores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    correo VARCHAR(190) NOT NULL UNIQUE,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Datos de muestra opcionales. El administrador real se crea con crear_admin.php desde CLI.
INSERT INTO productos
(nombre, descripcion, categoria, principio_activo, presentacion, precio, stock, imagen, requiere_receta, envio_permitido, peso_kg, activo)
SELECT 'Paracetamol', 'Analgésico y antipirético. Producto de demostración.', 'Analgésicos', 'Paracetamol', 'Caja', 65.00, 20, 'paracetamol.jpg', 0, 1, 0.200, 1
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'Paracetamol');

INSERT INTO productos
(nombre, descripcion, categoria, principio_activo, presentacion, precio, stock, imagen, requiere_receta, envio_permitido, peso_kg, activo)
SELECT 'Metformina', 'Producto de demostración. Validar requisitos de venta antes de publicar.', 'Medicamentos', 'Metformina', 'Caja', 95.00, 15, 'metformina.jpg', 1, 0, 0.180, 1
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'Metformina');
