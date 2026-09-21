USE `nikenza_store`;

ALTER TABLE `productos`
  ADD COLUMN IF NOT EXISTS `stock_actual` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `category`,
  ADD COLUMN IF NOT EXISTS `stock_minimo` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `stock_actual`,
  ADD COLUMN IF NOT EXISTS `estrategia_logistica` VARCHAR(150) NOT NULL DEFAULT 'Reabastecimiento estándar' AFTER `stock_minimo`;

ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `nombre` VARCHAR(120) DEFAULT NULL AFTER `username`,
  ADD COLUMN IF NOT EXISTS `telefono` VARCHAR(20) DEFAULT NULL AFTER `password_hash`,
  ADD COLUMN IF NOT EXISTS `empresa` VARCHAR(100) DEFAULT NULL AFTER `telefono`,
  ADD COLUMN IF NOT EXISTS `estado` ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo' AFTER `role`,
  ADD COLUMN IF NOT EXISTS `etapa_crm` ENUM('Prospecto', 'Activo', 'Frecuente', 'Inactivo') NOT NULL DEFAULT 'Prospecto' AFTER `estado`,
  ADD COLUMN IF NOT EXISTS `fecha_registro` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() AFTER `etapa_crm`;

CREATE TABLE IF NOT EXISTS `interacciones` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` INT(11) NOT NULL,
  `usuario_id` INT(11) NOT NULL,
  `tipo` ENUM('llamada', 'correo', 'reunion', 'mensaje') NOT NULL,
  `descripcion` TEXT NOT NULL,
  `fecha` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `fk_interacciones_cliente` (`cliente_id`),
  KEY `fk_interacciones_usuario` (`usuario_id`),
  CONSTRAINT `fk_interacciones_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_interacciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ventas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `folio` VARCHAR(30) NOT NULL,
  `cliente_id` INT(11) DEFAULT NULL,
  `producto_id` INT(11) DEFAULT NULL,
  `total` DECIMAL(10,2) NOT NULL,
  `fecha` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ventas_folio` (`folio`),
  KEY `fk_ventas_cliente` (`cliente_id`),
  KEY `fk_ventas_producto` (`producto_id`),
  CONSTRAINT `fk_ventas_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ventas_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE productos SET
  name = 'Teléfono Smartphone Pro',
  description = 'Teléfono Smartphone Pro',
  price = 12500.00,
  category = 'general',
  features = '[]',
  image_icon = 'fas fa-mobile-alt'
WHERE id = 1;

UPDATE productos SET
  name = 'Televisión Smart TV 55"',
  description = 'Televisión Smart TV 55"',
  price = 9800.00,
  category = 'general',
  features = '[]',
  image_icon = 'fas fa-tv'
WHERE id = 2;

UPDATE productos SET
  name = 'Computadora Portátil i7',
  description = 'Computadora Portátil i7',
  price = 18900.00,
  category = 'general',
  features = '[]',
  image_icon = 'fas fa-laptop'
WHERE id = 3;

INSERT INTO productos (name, description, price, category, features, image_icon, active)
SELECT 'Producto de ejemplo', 'Producto de ejemplo', 100.00, 'general', '[]', 'fas fa-box', 1
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE id = 1);

INSERT INTO usuarios (username, nombre, email, password_hash, telefono, empresa, role, estado, etapa_crm, fecha_registro)
SELECT 'admin_principal', 'Administrador Principal', 'admin@techzone.com',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       '4491112233', 'TechZone', 'admin', 'activo', 'Activo', '2025-01-01 08:00:00'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'admin@techzone.com');

INSERT INTO usuarios (username, nombre, email, password_hash, telefono, empresa, role, estado, etapa_crm, fecha_registro)
SELECT 'roberto_sanchez', 'Roberto Sánchez', 'roberto@techzone.com',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       '4494445566', 'TechZone', 'cliente', 'activo', 'Prospecto', '2025-01-10 08:00:00'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'roberto@techzone.com');

INSERT INTO usuarios (username, nombre, email, password_hash, telefono, empresa, role, estado, etapa_crm, fecha_registro)
SELECT 'carlos_mendoza', 'Carlos Mendoza', 'carlos.mendoza@email.com',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       '4491234567', NULL, 'cliente', 'activo', 'Activo', '2025-01-15 08:00:00'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'carlos.mendoza@email.com');

INSERT INTO usuarios (username, nombre, email, password_hash, telefono, empresa, role, estado, etapa_crm, fecha_registro)
SELECT 'ana_torres', 'Ana Sofía Torres', 'ana.torres@email.com',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       '4499876543', NULL, 'cliente', 'activo', 'Activo', '2025-02-10 08:00:00'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'ana.torres@email.com');

INSERT INTO usuarios (username, nombre, email, password_hash, telefono, empresa, role, estado, etapa_crm, fecha_registro)
SELECT 'luis_gomez', 'Luis Alberto Gómez', 'luis.gomez@email.com',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       '4495557890', NULL, 'cliente', 'inactivo', 'Inactivo', '2025-02-28 08:00:00'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'luis.gomez@email.com');

INSERT INTO interacciones (cliente_id, usuario_id, tipo, descripcion, fecha)
SELECT c.id, (SELECT id FROM usuarios WHERE role = 'admin' ORDER BY id LIMIT 1),
       'llamada', 'Llamada de seguimiento por cotización de Laptop', '2025-03-01 10:30:00'
FROM usuarios c
WHERE c.email = 'carlos.mendoza@email.com'
  AND NOT EXISTS (SELECT 1 FROM interacciones i WHERE i.cliente_id = c.id AND i.tipo = 'llamada');

INSERT INTO interacciones (cliente_id, usuario_id, tipo, descripcion, fecha)
SELECT c.id, (SELECT id FROM usuarios WHERE role = 'admin' ORDER BY id LIMIT 1),
       'correo', 'Envío de recibo de pago', '2025-03-02 14:15:00'
FROM usuarios c
WHERE c.email = 'carlos.mendoza@email.com'
  AND NOT EXISTS (SELECT 1 FROM interacciones i WHERE i.cliente_id = c.id AND i.tipo = 'correo');

INSERT INTO interacciones (cliente_id, usuario_id, tipo, descripcion, fecha)
SELECT c.id, (SELECT id FROM usuarios WHERE role = 'admin' ORDER BY id LIMIT 1),
       'mensaje', 'Confirmación de entrega de Smart TV', '2025-03-03 16:45:00'
FROM usuarios c
WHERE c.email = 'ana.torres@email.com'
  AND NOT EXISTS (SELECT 1 FROM interacciones i WHERE i.cliente_id = c.id AND i.tipo = 'mensaje');

INSERT INTO `usuarios` (`username`, `nombre`, `email`, `password_hash`, `empresa`, `role`, `estado`, `etapa_crm`)
SELECT 'pedro_f', 'Pedro F', 'pedro@gmail.com',
       '$2y$10$JNWNR0qJq3RO1OBqrGegH.T1JGetKLm3AbMwKjuAnuRSTC2GqvIpS',
       'LYM Store', 'cliente', 'activo', 'Prospecto'
WHERE NOT EXISTS (SELECT 1 FROM `usuarios` WHERE `email` = 'pedro@gmail.com');

INSERT INTO `usuarios` (`username`, `nombre`, `email`, `password_hash`, `role`, `estado`, `etapa_crm`)
SELECT 'jorge', 'Jorge', 'jorge@gmail.com',
       '$2y$10$oWomNDyQWLZ.bIaiJDHjjuElKAdtE1EICF2kJo3R56L1xWO8MRDUm',
       'cliente', 'activo', 'Prospecto'
WHERE NOT EXISTS (SELECT 1 FROM `usuarios` WHERE `email` = 'jorge@gmail.com');

INSERT INTO `interacciones` (`cliente_id`, `usuario_id`, `tipo`, `descripcion`)
SELECT c.id, (SELECT id FROM usuarios WHERE role = 'admin' ORDER BY id LIMIT 1),
       'llamada', 'Contacto inicial con el cliente.'
FROM usuarios c
WHERE c.email = 'jorge@gmail.com'
  AND NOT EXISTS (
    SELECT 1 FROM interacciones i WHERE i.cliente_id = c.id
  );
