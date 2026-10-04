-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 04-10-2026 a las 03:34:35
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
-- Base de datos: `elegancia_a_medida`
--
CREATE DATABASE IF NOT EXISTS `elegancia_a_medida` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `elegancia_a_medida`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_presupuesto`
--

CREATE TABLE `solicitudes_presupuesto` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(30) NOT NULL,
  `tipo_servicio` varchar(50) NOT NULL,
  `tipo_prenda` varchar(100) NOT NULL,
  `descripcion` text NOT NULL,
  `fecha_requerida` date NOT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `solicitudes_presupuesto`
--
ALTER TABLE `solicitudes_presupuesto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_estado_fecha` (`estado`,`fecha_solicitud`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `solicitudes_presupuesto`
--
ALTER TABLE `solicitudes_presupuesto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
