-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 25-07-2025 a las 22:22:35
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `nikenza_store`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paquetes`
--

CREATE TABLE `paquetes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `items` text NOT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `paquetes`
--

INSERT INTO `paquetes` (`id`, `name`, `description`, `price`, `items`, `featured`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Paquete Básico', 'Paquete básico con productos esenciales', 250.00, '["1 Taza estándar", "1 Llavero de acero con impresión frente y vuelta", "1 Rompecabezas tamaño carta", "Diseño personalizado a tu elección"]', 0, 1, '2025-07-25 18:03:03', '2025-07-25 18:03:03'),
(2, 'Paquete Viajero', 'Paquete ideal para viajeros', 320.00, '["1 Bolsa ecológica chica", "2 Llaveros de acero con impresión frente y vuelta", "1 Cojín 20 x 30 cm", "Diseño personalizado a tu elección"]', 1, 1, '2025-07-25 18:03:03', '2025-07-25 18:03:03'),
(3, 'Paquete Deportivo', 'Paquete para deportistas', 575.00, '["1 Playera deportiva Dryfit impresa al frente", "1 Gorra combinada (color a elegir)", "1 Vaso alto de acero (blanco o plata)", "Diseño personalizado a tu elección"]', 0, 1, '2025-07-25 18:03:03', '2025-07-25 18:03:03'),
(4, 'Paquete Potterhead', 'Paquete temático de Harry Potter', 635.00, '["1 Playera deportiva Dryfit con diseño de Quidditch", "1 Termo cafetero con asa y escudo de Hogwarts", "1 Llavero de acero con impresión frente y vuelta", "Diseño de tu casa de Hogwarts y fecha de cumpleaños"]', 0, 1, '2025-07-25 18:03:03', '2025-07-25 18:03:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(50) NOT NULL,
  `stock_actual` int(10) unsigned NOT NULL DEFAULT 0,
  `stock_minimo` int(10) unsigned NOT NULL DEFAULT 0,
  `estrategia_logistica` varchar(150) NOT NULL DEFAULT 'Reabastecimiento estándar',
  `features` text DEFAULT NULL,
  `image_icon` varchar(50) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `name`, `description`, `price`, `category`, `features`, `image_icon`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Teléfono Smartphone Pro', 'Teléfono Smartphone Pro', 12500.00, 'general', '[]', 'fas fa-mobile-alt', 1, '2025-07-25 18:03:03', '2025-07-25 18:03:03'),
(2, 'Televisión Smart TV 55"', 'Televisión Smart TV 55"', 9800.00, 'general', '[]', 'fas fa-tv', 1, '2025-07-25 18:03:03', '2025-07-25 18:03:03'),
(3, 'Computadora Portátil i7', 'Computadora Portátil i7', 18900.00, 'general', '[]', 'fas fa-laptop', 1, '2025-07-25 18:03:03', '2025-07-25 18:03:03'),
(4, 'Camisas Uniforme', 'Camisas de uniforme en gabardina peinada', 195.00, 'uniformes', '["Gabardina peinada, muy durable", "Para dama y caballero", "Tallas: CH, M, G, EG, 2XL, 3XL, 4XL", "Frente: $195 - $535", "Frente y vuelta: $510 - $550"]', 'fas fa-user-tie', 1, '2025-07-25 18:03:03', '2025-07-25 18:03:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios` (Clientes y Administradores - CRM Etapa 1)
--

CREATE TABLE `usuarios` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `nombre` VARCHAR(120) DEFAULT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `empresa` VARCHAR(100) DEFAULT NULL,
  `role` ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
  `fecha_registro` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `estado` ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  `etapa_crm` ENUM('Prospecto', 'Activo', 'Frecuente', 'Inactivo') NOT NULL DEFAULT 'Prospecto',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `username`, `nombre`, `email`, `password_hash`, `telefono`, `empresa`, `role`, `estado`, `etapa_crm`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'Administrador Principal', 'admin@nikenza.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '5550001122', 'LYM Store', 'admin', 'activo', 'Activo', '2025-07-25 18:03:03', '2025-07-25 18:03:03'),
(2, 'lalo', 'Eduardo Cisneros', 'eduardocisnerossoriano@gmail.com', '$2y$10$oqGlUntIqU25rzCqTe2GGuhhEsjIIvG.m1HsMb0kQ1.zvvvUAAw8G', '5559998877', 'LYM Store', 'admin', 'activo', 'Activo', '2025-07-25 18:03:37', '2025-07-25 18:04:03'),
(3, 'carlos_m', 'Carlos Mendoza', 'carlos@empresa.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '5551234567', 'Tech Solutions', 'cliente', 'activo', 'Prospecto', '2025-07-25 18:10:00', '2025-07-25 18:10:00'),
(4, 'pedro_f', 'Pedro F', 'pedro@gmail.com', '$2y$10$JNWNR0qJq3RO1OBqrGegH.T1JGetKLm3AbMwKjuAnuRSTC2GqvIpS', '', 'LYM Store', 'cliente', 'activo', 'Prospecto', CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP()),
(5, 'jorge', 'Jorge', 'jorge@gmail.com', '$2y$10$oWomNDyQWLZ.bIaiJDHjjuElKAdtE1EICF2kJo3R56L1xWO8MRDUm', '', NULL, 'cliente', 'activo', 'Prospecto', CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP()),
(6, 'admin_principal', 'Administrador Principal', 'admin@techzone.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '4491112233', 'TechZone', 'admin', 'activo', 'Activo', CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP()),
(7, 'roberto_sanchez', 'Roberto Sánchez', 'roberto@techzone.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '4494445566', 'TechZone', 'cliente', 'activo', 'Prospecto', CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP()),
(8, 'carlos_mendoza', 'Carlos Mendoza', 'carlos.mendoza@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '4491234567', NULL, 'cliente', 'activo', 'Activo', '2025-01-15 08:00:00', CURRENT_TIMESTAMP()),
(9, 'ana_torres', 'Ana Sofía Torres', 'ana.torres@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '4499876543', NULL, 'cliente', 'activo', 'Activo', '2025-02-10 08:00:00', CURRENT_TIMESTAMP()),
(10, 'luis_gomez', 'Luis Alberto Gómez', 'luis.gomez@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '4495557890', NULL, 'cliente', 'inactivo', 'Inactivo', '2025-02-28 08:00:00', CURRENT_TIMESTAMP());

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `interacciones` (Historial CRM Etapa 1)
--

CREATE TABLE `interacciones` (
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

--
-- Volcado de datos para la tabla `interacciones`
--

INSERT INTO `interacciones` (`id`, `cliente_id`, `usuario_id`, `tipo`, `descripcion`) VALUES
(1, 3, 1, 'llamada', 'Contacto inicial con el cliente. Solicita presupuesto de tazas corporativas.');
(2, 8, 6, 'llamada', 'Llamada de seguimiento por cotización de Laptop.'),
(3, 8, 6, 'correo', 'Envío de recibo de pago.'),
(4, 9, 6, 'mensaje', 'Confirmación de entrega de Smart TV.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas` (Registro del panel administrativo)
--

CREATE TABLE `ventas` (
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

INSERT INTO `ventas` (`folio`, `cliente_id`, `producto_id`, `total`) VALUES
('#VNT-101', 3, 1, 12500.00),
('#VNT-102', 3, 3, 18900.00),
('#VNT-103', 3, 2, 4900.00);

--
-- AUTO_INCREMENT de las tablas volcadas
--

ALTER TABLE `paquetes` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `productos` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `usuarios` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
ALTER TABLE `interacciones` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `ventas` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;