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
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `items` text NOT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `paquetes`
--

INSERT INTO `paquetes` (`id`, `name`, `description`, `price`, `items`, `featured`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Paquete Básico', 'Paquete básico con productos esenciales', 250.00, '[\"1 Taza estándar\", \"1 Llavero de acero con impresión frente y vuelta\", \"1 Rompecabezas tamaño carta\", \"Diseño personalizado a tu elección\"]', 0, 1, '2025-07-25 18:03:03', '2025-07-25 18:03:03'),
(2, 'Paquete Viajero', 'Paquete ideal para viajeros', 320.00, '[\"1 Bolsa ecológica chica\", \"2 Llaveros de acero con impresión frente y vuelta\", \"1 Cojín 20 x 30 cm\", \"Diseño personalizado a tu elección\"]', 1, 1, '2025-07-25 18:03:03', '2025-07-25 18:03:03'),
(3, 'Paquete Deportivo', 'Paquete para deportistas', 575.00, '[\"1 Playera deportiva Dryfit impresa al frente\", \"1 Gorra combinada (color a elegir)\", \"1 Vaso alto de acero (blanco o plata)\", \"Diseño personalizado a tu elección\"]', 0, 1, '2025-07-25 18:03:03', '2025-07-25 18:03:03'),
(4, 'Paquete Potterhead', 'Paquete temático de Harry Potter', 635.00, '[\"1 Playera deportiva Dryfit con diseño de Quidditch\", \"1 Termo cafetero con asa y escudo de Hogwarts\", \"1 Llavero de acero con impresión frente y vuelta\", \"Diseño de tu casa de Hogwarts y fecha de cumpleaños\"]', 0, 1, '2025-07-25 18:03:03', '2025-07-25 18:03:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(50) NOT NULL,
  `features` text DEFAULT NULL,
  `image_icon` varchar(50) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `name`, `description`, `price`, `category`, `features`, `image_icon`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Taza Estándar', 'Taza estándar de cerámica personalizada', 85.00, 'tazas', '[\"Taza estándar de cerámica - $85\", \"Taza con asa de color - $95\", \"Taza mágica - $120\", \"Par de tazas corazón - $215\", \"Taza recta grande 444ml - $130\"]', 'fas fa-mug-hot', 1, '2025-07-25 18:03:03', '2025-07-25 18:03:03'),
(2, 'Tapete Afelpado', 'Tapete blanco afelpado suave para interior', 300.00, 'tapetes', '[\"Tapete blanco afelpado suave\", \"Para interior, revés antideslizante\", \"Medida: 34 x 58 cm\", \"Impresión máxima: 30 x 43 cm\", \"Diseño personalizado\"]', 'fas fa-home', 1, '2025-07-25 18:03:03', '2025-07-25 18:03:03'),
(3, 'Sudaderas', 'Sudaderas personalizadas de algodón', 425.00, 'sudaderas', '[\"Sudadera sencilla - $425\", \"Sudadera con gorra - $500\", \"Algodón, sencilla, cerrada\", \"Cuello redondo\", \"Diseño personalizado\"]', 'fas fa-tshirt', 1, '2025-07-25 18:03:03', '2025-07-25 18:03:03'),
(4, 'Camisas Uniforme', 'Camisas de uniforme en gabardina peinada', 195.00, 'uniformes', '[\"Gabardina peinada, muy durable\", \"Para dama y caballero\", \"Tallas: CH, M, G, EG, 2XL, 3XL, 4XL\", \"Frente: $195 - $535\", \"Frente y vuelta: $510 - $550\"]', 'fas fa-user-tie', 1, '2025-07-25 18:03:03', '2025-07-25 18:03:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('cliente','admin') NOT NULL DEFAULT 'cliente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `username`, `email`, `password_hash`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@nikenza.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2025-07-25 18:03:03', '2025-07-25 18:03:03'),
(2, 'lalo', 'eduardocisnerossoriano@gmail.com', '$2y$10$oqGlUntIqU25rzCqTe2GGuhhEsjIIvG.m1HsMb0kQ1.zvvvUAAw8G', 'admin', '2025-07-25 18:03:37', '2025-07-25 18:04:03');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `paquetes`
--
ALTER TABLE `paquetes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `paquetes`
--
ALTER TABLE `paquetes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
