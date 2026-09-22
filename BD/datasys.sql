-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 27, 2026 at 06:12 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `datasys`
--

-- --------------------------------------------------------

--
-- Table structure for table `camaristas`
--

CREATE TABLE `camaristas` (
  `id_camarista` int(11) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `estatus` enum('no_disponible','descanso','activo') DEFAULT 'no_disponible',
  `usuario` varchar(25) NOT NULL,
  `contraseña` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `camaristas`
--

INSERT INTO `camaristas` (`id_camarista`, `foto`, `nombre_completo`, `telefono`, `correo`, `estatus`, `usuario`, `contraseña`) VALUES
(1, NULL, 'Carlos Gomez Gomez', '9614658732', 'carlos@gmail.com', 'no_disponible', 'carlos', '2005'),
(2, NULL, 'Charbel Lopez Dominguez', '9191583200', 'charbel@gmail.com', 'no_disponible', 'charbel', '12092005'),
(3, 'img_camaristas/3301b4ecc795ac50485d8a1d920bca64.jpg', 'Josue Adrian Diaz Diaz', '9931286649', 'josue@gmail.com', 'no_disponible', 'Josue', '12092005');

-- --------------------------------------------------------

--
-- Table structure for table `estancias`
--

CREATE TABLE `estancias` (
  `id_estancia` int(11) NOT NULL,
  `id_huesped` int(11) NOT NULL,
  `id_habitacion` int(11) NOT NULL,
  `fecha_entrada` datetime NOT NULL,
  `fecha_salida` datetime NOT NULL,
  `estatus_estancia` enum('Activa','Finalizada') NOT NULL DEFAULT 'Activa',
  `id_usuario_checkin` int(11) NOT NULL,
  `id_usuario_checkout` int(11) DEFAULT NULL,
  `id_turno` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `estancias`
--

INSERT INTO `estancias` (`id_estancia`, `id_huesped`, `id_habitacion`, `fecha_entrada`, `fecha_salida`, `estatus_estancia`, `id_usuario_checkin`, `id_usuario_checkout`, `id_turno`) VALUES
(1, 1, 1, '2026-07-24 21:34:00', '2026-07-25 14:41:28', 'Finalizada', 2, 2, 1),
(2, 1, 1, '2026-07-26 22:42:00', '2026-07-27 14:43:27', 'Finalizada', 2, 2, 3),
(3, 1, 1, '2026-07-26 22:42:00', '2026-07-26 14:46:14', 'Finalizada', 2, 2, 3),
(4, 1, 1, '2026-07-26 22:46:00', '2026-07-26 14:46:56', 'Finalizada', 2, 2, 3),
(5, 1, 1, '2026-07-26 22:46:00', '2026-07-27 22:46:00', 'Finalizada', 2, 2, 3),
(6, 1, 1, '2026-07-26 22:46:00', '2026-07-27 22:46:00', 'Finalizada', 2, 2, 3),
(7, 1, 1, '2026-07-26 22:55:00', '2026-07-26 14:56:01', 'Finalizada', 2, 2, 3),
(8, 1, 1, '2026-07-28 02:43:00', '2026-07-27 19:08:15', 'Finalizada', 2, 2, 4),
(9, 1, 1, '2026-07-28 03:09:00', '2026-07-27 19:10:30', 'Finalizada', 2, 2, 4),
(10, 1, 1, '2026-07-28 03:11:00', '2026-07-27 19:14:48', 'Finalizada', 2, 2, 4),
(11, 1, 1, '2026-07-28 03:18:00', '2026-07-27 19:20:43', 'Finalizada', 2, 2, 4),
(12, 1, 1, '2026-07-28 03:22:00', '2026-07-27 19:22:28', 'Finalizada', 2, 2, 4),
(13, 1, 1, '2026-07-28 03:25:00', '2026-07-27 19:30:59', 'Finalizada', 2, 2, 4),
(14, 1, 1, '2026-07-28 03:31:00', '2026-07-27 19:32:51', 'Finalizada', 2, 2, 4),
(15, 1, 1, '2026-07-28 03:35:00', '2026-07-27 19:36:04', 'Finalizada', 2, 2, 4),
(16, 1, 1, '2026-07-28 03:38:00', '2026-07-27 19:38:19', 'Finalizada', 1, 1, 6),
(17, 1, 1, '2026-07-28 03:38:00', '2026-07-27 19:38:59', 'Finalizada', 1, 1, 6),
(18, 1, 1, '2026-07-28 03:43:00', '2026-07-27 19:43:34', 'Finalizada', 1, 1, 6),
(19, 1, 1, '2026-07-28 03:47:00', '2026-07-27 19:47:27', 'Finalizada', 1, 1, 6),
(20, 1, 1, '2026-07-28 03:57:00', '2026-07-27 19:57:58', 'Finalizada', 2, 2, 4),
(21, 1, 1, '2026-07-28 04:08:00', '2026-07-27 20:17:14', 'Finalizada', 2, 2, 4),
(22, 1, 1, '2026-07-28 04:17:00', '2026-07-27 20:19:48', 'Finalizada', 2, 2, 4),
(23, 1, 1, '2026-07-28 04:21:00', '2026-07-27 20:21:58', 'Finalizada', 2, 2, 4),
(24, 1, 1, '2026-07-29 07:27:00', '2026-07-28 23:29:05', 'Finalizada', 1, 1, 38),
(25, 1, 1, '2026-07-29 07:29:00', '2026-07-28 23:36:02', 'Finalizada', 1, 1, 39),
(26, 1, 1, '2026-07-29 07:36:00', '2026-07-29 00:58:46', 'Finalizada', 1, 2, 40),
(27, 1, 1, '2026-07-29 08:57:00', '2026-07-29 01:21:33', 'Finalizada', 2, 1, 66),
(28, 1, 1, '2026-07-29 09:21:00', '2026-08-26 15:47:07', 'Finalizada', 1, 1, 68),
(29, 2, 2, '2026-07-29 09:23:00', '2026-08-26 15:47:13', 'Finalizada', 1, 1, 68),
(30, 3, 3, '2026-07-29 09:25:00', '2026-08-26 15:47:18', 'Finalizada', 1, 1, 68),
(31, 4, 4, '2026-07-29 09:28:00', '2026-08-26 15:47:23', 'Finalizada', 1, 1, 68),
(32, 1, 1, '2026-08-26 15:49:08', '2026-08-27 15:49:08', 'Activa', 1, NULL, 86),
(33, 5, 2, '2026-08-26 15:50:48', '2026-08-27 15:50:48', 'Activa', 1, NULL, 86),
(34, 1, 3, '2026-08-26 16:03:43', '2026-08-30 16:03:43', 'Activa', 1, NULL, 86);

-- --------------------------------------------------------

--
-- Table structure for table `habitaciones`
--

CREATE TABLE `habitaciones` (
  `id_habitacion` int(11) NOT NULL,
  `tipo_habitacion` varchar(100) NOT NULL,
  `numero_habitacion` varchar(20) NOT NULL,
  `estatus` enum('disponible','ocupada','mantenimiento','limpieza') NOT NULL DEFAULT 'disponible',
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cantidad_personas` int(11) NOT NULL DEFAULT 1,
  `id_huesped` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `habitaciones`
--

INSERT INTO `habitaciones` (`id_habitacion`, `tipo_habitacion`, `numero_habitacion`, `estatus`, `precio`, `cantidad_personas`, `id_huesped`) VALUES
(1, 'familiar', '101', 'ocupada', 770.00, 4, 1),
(2, 'familiar', '102', 'ocupada', 770.00, 4, 5),
(3, 'familiar', '103', 'ocupada', 770.00, 4, 1),
(4, 'familiar', '104', 'disponible', 770.00, 4, NULL),
(5, 'familiar', '105', 'disponible', 770.00, 4, NULL),
(6, 'familiar', '106', 'disponible', 770.00, 4, NULL),
(7, 'familiar', '107', 'disponible', 770.00, 4, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `huesped`
--

CREATE TABLE `huesped` (
  `id_huesped` int(11) NOT NULL,
  `identificador` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido_p` varchar(100) NOT NULL,
  `apellido_m` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `nacionalidad` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `huesped`
--

INSERT INTO `huesped` (`id_huesped`, `identificador`, `nombre`, `apellido_p`, `apellido_m`, `telefono`, `correo`, `nacionalidad`) VALUES
(1, 'HDP001', 'Josue Adrian', 'Diaz', 'Diaz', '9931286649', 'josue@gmail.com', 'México'),
(2, 'HDP002', 'Carlos Manuel', 'Lopez', 'Diaz', '9618766752', 'carlos@gmail.com', 'México'),
(3, 'HDP003', 'Carla', 'Castillo', 'Mendoza', '9924789876', 'carla@gmail.com', 'México'),
(4, 'HDP004', 'Benjamín', 'Lopez', 'Castillo', '9639874765', 'benjamin@gmail.com', 'México'),
(5, 'HDP005', 'Carlos Manuel', 'Lopez', 'Mendoza', '9931286649', 'carlos@gmail.com', 'México');

-- --------------------------------------------------------

--
-- Table structure for table `incidencias`
--

CREATE TABLE `incidencias` (
  `id_incidencia` int(11) NOT NULL,
  `id_habitacion` int(11) NOT NULL,
  `id_camarista` int(11) NOT NULL,
  `descripcion` text NOT NULL,
  `fecha_reporte` datetime NOT NULL,
  `estatus` enum('atendido','en_camino','pendiente') NOT NULL DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incidencias`
--

INSERT INTO `incidencias` (`id_incidencia`, `id_habitacion`, `id_camarista`, `descripcion`, `fecha_reporte`, `estatus`) VALUES
(25, 1, 2, 'llevar 2 toallas extras', '2026-07-27 01:27:13', 'atendido');

-- --------------------------------------------------------

--
-- Table structure for table `pagos_estancias`
--

CREATE TABLE `pagos_estancias` (
  `id_pago` int(11) NOT NULL,
  `id_estancia` int(11) NOT NULL,
  `metodo_pago` enum('Efectivo','Trasferencia') NOT NULL,
  `monto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fecha_pago` datetime NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_turno` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pagos_estancias`
--

INSERT INTO `pagos_estancias` (`id_pago`, `id_estancia`, `metodo_pago`, `monto`, `fecha_pago`, `id_usuario`, `id_turno`) VALUES
(1, 1, 'Efectivo', 3080.00, '2026-07-26 13:35:05', 2, 1),
(2, 2, 'Efectivo', 1540.00, '2026-07-26 14:43:01', 2, 3),
(3, 3, 'Efectivo', 1540.00, '2026-07-26 14:44:34', 2, 3),
(4, 4, 'Efectivo', 770.00, '2026-07-26 14:46:51', 2, 3),
(5, 5, 'Efectivo', 770.00, '2026-07-26 14:47:48', 2, 3),
(6, 6, 'Efectivo', 770.00, '2026-07-26 14:48:19', 2, 3),
(7, 7, 'Efectivo', 770.00, '2026-07-26 14:55:39', 2, 3),
(8, 8, 'Efectivo', 770.00, '2026-07-27 18:44:07', 2, 4),
(9, 8, 'Efectivo', 1540.00, '2026-07-27 18:55:13', 2, 4),
(10, 8, 'Efectivo', 770.00, '2026-07-27 18:55:25', 2, 4),
(11, 9, 'Efectivo', 770.00, '2026-07-27 19:09:44', 2, 4),
(12, 10, 'Efectivo', 770.00, '2026-07-27 19:11:40', 2, 4),
(13, 11, 'Efectivo', 770.00, '2026-07-27 19:18:21', 2, 4),
(14, 12, 'Efectivo', 770.00, '2026-07-27 19:22:24', 2, 4),
(15, 13, 'Efectivo', 770.00, '2026-07-27 19:25:58', 2, 4),
(16, 14, 'Efectivo', 770.00, '2026-07-27 19:31:35', 2, 4),
(17, 15, 'Efectivo', 770.00, '2026-07-27 19:35:46', 2, 4),
(18, 16, 'Efectivo', 770.00, '2026-07-27 19:38:11', 1, 6),
(19, 17, 'Efectivo', 770.00, '2026-07-27 19:38:53', 1, 6),
(20, 18, 'Efectivo', 770.00, '2026-07-27 19:43:06', 1, 6),
(21, 19, 'Efectivo', 770.00, '2026-07-27 19:47:23', 1, 6),
(22, 20, 'Efectivo', 770.00, '2026-07-27 19:57:52', 2, 4),
(23, 21, 'Efectivo', 770.00, '2026-07-27 20:09:06', 2, 4),
(24, 22, 'Efectivo', 770.00, '2026-07-27 20:18:03', 2, 4),
(25, 23, 'Efectivo', 770.00, '2026-07-27 20:21:44', 2, 4),
(26, 24, 'Efectivo', 770.00, '2026-07-28 23:27:16', 1, 38),
(27, 25, 'Efectivo', 770.00, '2026-07-28 23:29:34', 1, 39),
(28, 26, 'Efectivo', 770.00, '2026-07-28 23:36:34', 1, 40),
(29, 27, 'Efectivo', 770.00, '2026-07-29 00:57:20', 2, 66),
(30, 28, 'Efectivo', 2310.00, '2026-07-29 01:22:13', 1, 68),
(31, 29, 'Efectivo', 770.00, '2026-07-29 01:25:02', 1, 68),
(32, 30, 'Efectivo', 3080.00, '2026-07-29 01:27:09', 1, 68),
(33, 31, 'Trasferencia', 4620.00, '2026-07-29 01:29:44', 1, 68),
(34, 32, 'Efectivo', 770.00, '2026-08-26 15:49:08', 1, 86),
(35, 33, 'Efectivo', 770.00, '2026-08-26 15:50:48', 1, 86),
(36, 34, 'Efectivo', 770.00, '2026-08-26 16:03:43', 1, 86),
(37, 34, 'Efectivo', 2310.00, '2026-08-26 16:04:02', 1, 86);

-- --------------------------------------------------------

--
-- Table structure for table `pagos_servicios`
--

CREATE TABLE `pagos_servicios` (
  `id_pago` int(11) NOT NULL,
  `id_servicio` int(11) NOT NULL,
  `estado_pago` enum('Pendiente','Pagado') NOT NULL DEFAULT 'Pendiente',
  `metodo_pago` enum('Efectivo','Trasferencia','Cuenta') NOT NULL,
  `monto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fecha_pago` datetime NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_turno` int(11) NOT NULL,
  `id_estancia` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pagos_servicios`
--

INSERT INTO `pagos_servicios` (`id_pago`, `id_servicio`, `estado_pago`, `metodo_pago`, `monto`, `fecha_pago`, `id_usuario`, `id_turno`, `id_estancia`) VALUES
(1, 1, 'Pagado', 'Efectivo', 170.00, '2026-07-26 14:01:47', 2, 3, 1),
(2, 1, 'Pagado', 'Efectivo', 170.00, '2026-07-26 14:03:05', 2, 3, 1),
(3, 1, 'Pagado', 'Efectivo', 340.00, '2026-07-26 14:08:24', 2, 3, 1),
(4, 1, 'Pagado', 'Efectivo', 170.00, '2026-07-26 14:39:02', 2, 3, 1),
(5, 1, 'Pagado', 'Efectivo', 340.00, '2026-07-26 14:12:06', 2, 3, 1),
(6, 1, 'Pagado', 'Efectivo', 170.00, '2026-07-26 14:39:03', 2, 3, 1),
(7, 1, 'Pagado', 'Efectivo', 170.00, '2026-07-26 14:30:10', 2, 3, 1),
(8, 1, 'Pagado', 'Efectivo', 170.00, '2026-07-27 19:08:10', 2, 4, 8),
(9, 1, 'Pagado', 'Efectivo', 170.00, '2026-07-27 19:14:47', 2, 4, 10),
(10, 1, 'Pendiente', 'Efectivo', 170.00, '2026-07-27 19:26:06', 2, 4, 10),
(11, 1, 'Pagado', 'Efectivo', 170.00, '2026-07-27 19:26:51', 2, 4, 13),
(12, 1, 'Pagado', 'Efectivo', 170.00, '2026-07-27 19:32:51', 2, 4, 14),
(13, 1, 'Pagado', 'Efectivo', 170.00, '2026-07-27 19:43:34', 1, 6, 18),
(14, 1, 'Pagado', 'Efectivo', 170.00, '2026-07-29 01:04:58', 2, 66, 27),
(15, 1, 'Pagado', 'Trasferencia', 340.00, '2026-07-29 01:05:41', 2, 66, 27);

-- --------------------------------------------------------

--
-- Table structure for table `servicios`
--

CREATE TABLE `servicios` (
  `id_servicio` int(11) NOT NULL,
  `tipo_servicio` varchar(100) NOT NULL,
  `nombre_servicio` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `id_usuario` int(11) NOT NULL,
  `id_turno` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `servicios`
--

INSERT INTO `servicios` (`id_servicio`, `tipo_servicio`, `nombre_servicio`, `descripcion`, `precio`, `id_usuario`, `id_turno`) VALUES
(1, 'Restaurant', 'Desayuno', 'Huevo con Jamón', 170.00, 2, 3);

-- --------------------------------------------------------

--
-- Table structure for table `turnos`
--

CREATE TABLE `turnos` (
  `id_turno` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `numero_caja` varchar(20) NOT NULL DEFAULT 'caja_1',
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `monto_inicial` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monto_final` decimal(10,2) DEFAULT NULL,
  `estatus` enum('abierto','cerrado') NOT NULL DEFAULT 'abierto'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `turnos`
--

INSERT INTO `turnos` (`id_turno`, `id_usuario`, `numero_caja`, `fecha_inicio`, `fecha_fin`, `monto_inicial`, `monto_final`, `estatus`) VALUES
(1, 2, 'caja_1', '2026-07-26 13:33:43', '2026-07-26 13:41:03', 2500.00, 5580.00, 'cerrado'),
(2, 2, 'caja_1', '2026-07-26 13:41:56', '2026-07-26 13:46:05', 5580.00, 5580.00, 'cerrado'),
(3, 2, 'caja_1', '2026-07-26 13:48:23', '2026-07-26 14:58:34', 0.00, 5390.00, 'cerrado'),
(4, 2, 'caja_1', '2026-07-26 14:58:55', '2026-07-28 00:29:13', 0.00, 11550.00, 'cerrado'),
(6, 1, 'caja_1', '2026-07-27 19:37:28', '2026-07-27 19:57:09', 0.00, 0.00, 'cerrado'),
(7, 1, 'caja_1', '2026-07-28 00:13:17', '2026-07-28 00:13:32', 0.00, 0.00, 'cerrado'),
(8, 1, 'caja_1', '2026-07-28 00:14:53', '2026-07-28 00:16:45', 0.00, 0.00, 'cerrado'),
(9, 1, 'caja_1', '2026-07-28 00:16:53', '2026-07-28 00:22:03', 0.00, 0.00, 'cerrado'),
(10, 1, 'caja_1', '2026-07-28 00:22:16', '2026-07-28 00:24:24', 0.00, 0.00, 'cerrado'),
(11, 1, 'caja_1', '2026-07-28 00:24:30', '2026-07-28 00:29:36', 0.00, 0.00, 'cerrado'),
(12, 2, 'caja_1', '2026-07-28 00:29:47', '2026-07-28 00:32:25', 0.00, 0.00, 'cerrado'),
(13, 1, 'caja_1', '2026-07-28 00:30:53', '2026-07-28 00:47:21', 0.00, 0.00, 'cerrado'),
(14, 1, 'caja_1', '2026-07-28 00:48:09', '2026-07-28 00:48:14', 0.00, 0.00, 'cerrado'),
(15, 1, 'caja_1', '2026-07-28 00:59:06', '2026-07-28 00:59:11', 0.00, 0.00, 'cerrado'),
(16, 1, 'caja_1', '2026-07-28 21:10:49', '2026-07-28 21:10:53', 0.00, 0.00, 'cerrado'),
(17, 1, 'caja_1', '2026-07-28 21:54:54', '2026-07-28 21:55:04', 0.00, 0.00, 'cerrado'),
(18, 1, 'caja_1', '2026-07-28 22:07:58', '2026-07-28 22:08:19', 0.00, 0.00, 'cerrado'),
(19, 1, 'caja_1', '2026-07-28 22:08:30', '2026-07-28 22:10:46', 0.00, 0.00, 'cerrado'),
(20, 1, 'caja_1', '2026-07-28 22:10:53', '2026-07-28 22:12:21', 0.00, 0.00, 'cerrado'),
(21, 1, 'caja_1', '2026-07-28 22:12:34', '2026-07-28 22:14:03', 0.00, 0.00, 'cerrado'),
(22, 1, 'caja_1', '2026-07-28 22:14:06', '2026-07-28 23:05:25', 0.00, 0.00, 'cerrado'),
(23, 1, 'caja_1', '2026-07-28 23:05:27', '2026-07-28 23:07:56', 0.00, 0.00, 'cerrado'),
(24, 1, 'caja_1', '2026-07-28 23:08:05', '2026-07-28 23:08:23', 0.00, 0.00, 'cerrado'),
(25, 1, 'caja_1', '2026-07-28 23:08:49', '2026-07-28 23:09:41', 0.00, 0.00, 'cerrado'),
(26, 1, 'caja_1', '2026-07-28 23:10:07', '2026-07-28 23:10:10', 0.00, 0.00, 'cerrado'),
(27, 1, 'caja_1', '2026-07-28 23:10:18', '2026-07-28 23:10:27', 0.00, 0.00, 'cerrado'),
(28, 1, 'caja_1', '2026-07-28 23:10:38', '2026-07-28 23:12:46', 0.00, 0.00, 'cerrado'),
(29, 1, 'caja_1', '2026-07-28 23:12:48', '2026-07-28 23:12:50', 0.00, 0.00, 'cerrado'),
(30, 1, 'caja_1', '2026-07-28 23:13:48', '2026-07-28 23:15:46', 0.00, 0.00, 'cerrado'),
(31, 1, 'caja_1', '2026-07-28 23:15:51', '2026-07-28 23:19:01', 0.00, 0.00, 'cerrado'),
(32, 1, 'caja_1', '2026-07-28 23:19:05', '2026-07-28 23:19:15', 0.00, 0.00, 'cerrado'),
(33, 1, 'caja_1', '2026-07-28 23:19:21', '2026-07-28 23:19:28', 0.00, 0.00, 'cerrado'),
(34, 1, 'caja_1', '2026-07-28 23:19:37', '2026-07-28 23:24:05', 0.00, 0.00, 'cerrado'),
(35, 1, 'caja_1', '2026-07-28 23:24:07', '2026-07-28 23:24:14', 0.00, 0.00, 'cerrado'),
(36, 1, 'caja_1', '2026-07-28 23:24:28', '2026-07-28 23:24:31', 0.00, 0.00, 'cerrado'),
(37, 1, 'caja_1', '2026-07-28 23:26:19', '2026-07-28 23:26:27', 0.00, 0.00, 'cerrado'),
(38, 1, 'caja_1', '2026-07-28 23:26:57', '2026-07-28 23:28:07', 0.00, 770.00, 'cerrado'),
(39, 1, 'caja_1', '2026-07-28 23:28:39', '2026-07-28 23:31:51', 770.00, 1540.00, 'cerrado'),
(40, 1, 'caja_1', '2026-07-28 23:35:12', '2026-07-28 23:37:08', 1000.00, 1770.00, 'cerrado'),
(41, 1, 'caja_1', '2026-07-28 23:38:17', '2026-07-28 23:39:49', 0.00, 100.00, 'cerrado'),
(42, 1, 'caja_1', '2026-07-28 23:40:53', '2026-07-28 23:43:44', 0.00, 100.00, 'cerrado'),
(43, 1, 'caja_1', '2026-07-28 23:43:49', '2026-07-28 23:43:58', 1000.00, 1000.00, 'cerrado'),
(44, 1, 'caja_1', '2026-07-28 23:46:16', '2026-07-28 23:48:05', 1.00, 1.00, 'cerrado'),
(45, 1, 'caja_1', '2026-07-28 23:48:15', '2026-07-28 23:50:34', 1000.00, 1000.00, 'cerrado'),
(46, 1, 'caja_1', '2026-07-28 23:50:40', '2026-07-28 23:50:45', 1000.00, 1000.00, 'cerrado'),
(47, 1, 'caja_1', '2026-07-28 23:51:00', '2026-07-28 23:51:04', 1000.00, 1000.00, 'cerrado'),
(48, 1, 'caja_1', '2026-07-28 23:51:10', '2026-07-28 23:51:15', 100.00, 100.00, 'cerrado'),
(49, 1, 'caja_1', '2026-07-28 23:51:41', '2026-07-28 23:51:51', 1.50, 1.50, 'cerrado'),
(50, 1, 'caja_1', '2026-07-28 23:52:03', '2026-07-28 23:54:31', 12000.50, 12000.50, 'cerrado'),
(51, 1, 'caja_1', '2026-07-28 23:56:59', '2026-07-29 00:00:15', 0.00, 0.00, 'cerrado'),
(52, 1, 'caja_1', '2026-07-29 00:03:38', '2026-07-29 00:03:46', 0.00, 1200.00, 'cerrado'),
(53, 1, 'caja_1', '2026-07-29 00:03:49', '2026-07-29 00:03:57', 0.00, 1000.00, 'cerrado'),
(54, 1, 'caja_1', '2026-07-29 00:05:40', '2026-07-29 00:05:47', 1000.00, 1000.00, 'cerrado'),
(55, 2, 'caja_1', '2026-07-29 00:06:43', '2026-07-29 00:08:48', 0.00, 1000.00, 'cerrado'),
(56, 3, 'caja_1', '2026-07-29 00:08:03', '2026-07-29 00:16:20', 0.00, 1000.00, 'cerrado'),
(57, 3, 'caja_1', '2026-07-29 00:17:04', '2026-07-29 00:22:35', 1000.00, 1000.00, 'cerrado'),
(58, 3, 'caja_1', '2026-07-29 00:24:54', '2026-07-29 00:25:03', 1000.00, 1000.00, 'cerrado'),
(59, 3, 'caja_1', '2026-07-29 00:27:14', '2026-07-29 00:36:34', 0.00, 1000.00, 'cerrado'),
(60, 1, 'caja_2', '2026-07-29 00:39:18', '2026-07-29 00:39:30', 1000.00, 1000.00, 'cerrado'),
(61, 1, 'caja_1', '2026-07-29 00:48:21', '2026-07-29 00:48:25', 0.00, 0.00, 'cerrado'),
(62, 2, 'caja_1', '2026-07-29 00:49:06', '2026-07-29 00:50:17', 0.00, 0.00, 'cerrado'),
(63, 3, 'caja_1', '2026-07-29 00:50:33', '2026-07-29 00:50:56', 200.00, 200.00, 'cerrado'),
(64, 2, 'caja_1', '2026-07-29 00:51:33', '2026-07-29 00:53:48', 200.00, 200.00, 'cerrado'),
(65, 2, 'caja_1', '2026-07-29 00:53:52', '2026-07-29 00:54:38', 200.00, 200.00, 'cerrado'),
(66, 2, 'caja_1', '2026-07-29 00:54:59', '2026-07-29 01:15:38', 200.00, 1140.00, 'cerrado'),
(67, 1, 'caja_1', '2026-07-29 01:15:57', '2026-07-29 01:16:01', 0.00, 0.00, 'cerrado'),
(68, 1, 'caja_2', '2026-07-29 01:16:11', '2026-07-29 13:19:17', 1000.00, 7160.00, 'cerrado'),
(69, 2, 'caja_1', '2026-07-29 01:20:08', '2026-07-29 01:20:15', 1140.00, 1140.00, 'cerrado'),
(70, 2, 'caja_2', '2026-07-29 01:20:19', '2026-07-29 01:20:24', 0.00, 0.00, 'cerrado'),
(71, 2, 'caja_1', '2026-07-29 01:20:29', '2026-07-29 01:33:51', 1140.00, 1140.00, 'cerrado'),
(72, 3, 'caja_1', '2026-07-29 13:14:48', '2026-07-29 13:19:33', 1140.00, 1140.00, 'cerrado'),
(73, 1, 'caja_1', '2026-07-29 13:19:27', '2026-07-29 13:20:37', 0.00, 0.00, 'cerrado'),
(74, 3, 'caja_1', '2026-07-29 13:19:48', '2026-07-29 13:19:56', 1140.00, 1140.00, 'cerrado'),
(75, 3, 'caja_1', '2026-07-29 13:20:17', '2026-07-29 13:20:27', 1140.00, 1140.00, 'cerrado'),
(76, 1, 'caja_1', '2026-07-29 13:20:45', '2026-07-29 13:20:52', 0.00, 0.00, 'cerrado'),
(77, 1, 'caja_1', '2026-07-29 13:21:09', '2026-07-29 13:21:12', 0.00, 0.00, 'cerrado'),
(78, 1, 'caja_1', '2026-07-29 13:21:23', '2026-07-29 13:21:25', 0.00, 0.00, 'cerrado'),
(79, 3, 'caja_1', '2026-07-29 13:21:57', '2026-07-29 13:31:17', 1140.00, 1140.00, 'cerrado'),
(80, 1, 'caja_1', '2026-07-29 13:23:27', '2026-07-29 14:12:12', 0.00, 0.00, 'cerrado'),
(81, 3, 'caja_1', '2026-07-29 13:42:55', '2026-07-29 13:42:59', 1140.00, 1140.00, 'cerrado'),
(82, 3, 'caja_1', '2026-07-29 13:43:10', '2026-07-29 13:43:23', 1140.00, 1140.00, 'cerrado'),
(83, 3, 'caja_1', '2026-07-29 13:57:09', '2026-07-29 13:57:15', 1140.00, 1140.00, 'cerrado'),
(84, 3, 'caja_1', '2026-07-29 13:58:46', '2026-07-29 14:08:24', 1140.00, 1140.00, 'cerrado'),
(85, 1, 'caja_2', '2026-07-29 14:12:17', '2026-07-29 14:12:23', 7160.00, 7160.00, 'cerrado'),
(86, 1, 'caja_1', '2026-08-26 15:45:31', NULL, 0.00, 0.00, 'abierto');

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `identificador` varchar(20) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `contraseña` varchar(255) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `rol` enum('Administrador','Recepcionista','Gobernante','Camarista','Contador','Vendedor') NOT NULL,
  `foto` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `identificador`, `usuario`, `contraseña`, `nombre_completo`, `telefono`, `correo`, `rol`, `foto`) VALUES
(1, 'EMP001', 'Josue', '4233d79c3ac147ee141d2a0fbaf671f5ce68d0163cf4139809f2e8e2f4ee9827', 'Josue Adrian Diaz Diaz', '9931286649', 'josue@gmail.com', 'Administrador', 'img_empleados/emp_6a665b7bea00c.jpg'),
(2, 'EMP002', 'Adrian', '4233d79c3ac147ee141d2a0fbaf671f5ce68d0163cf4139809f2e8e2f4ee9827', 'Adrian Diaz Diaz', '9931286649', 'adrian@gmail.com', 'Recepcionista', 'img_empleados/emp_6a665f75aa92b.webp'),
(3, 'EMP003', 'Charbel', '4233d79c3ac147ee141d2a0fbaf671f5ce68d0163cf4139809f2e8e2f4ee9827', 'Charbel Lopez Dominguez', '9931286649', 'charbel@gmail.com', 'Recepcionista', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `camaristas`
--
ALTER TABLE `camaristas`
  ADD PRIMARY KEY (`id_camarista`);

--
-- Indexes for table `estancias`
--
ALTER TABLE `estancias`
  ADD PRIMARY KEY (`id_estancia`),
  ADD KEY `fk_estancias_huesped` (`id_huesped`),
  ADD KEY `fk_estancias_habitacion` (`id_habitacion`),
  ADD KEY `fk_estancias_usuario_checkin` (`id_usuario_checkin`),
  ADD KEY `fk_estancias_usuario_checkout` (`id_usuario_checkout`),
  ADD KEY `fk_estancias_turno` (`id_turno`);

--
-- Indexes for table `habitaciones`
--
ALTER TABLE `habitaciones`
  ADD PRIMARY KEY (`id_habitacion`),
  ADD UNIQUE KEY `numero_habitacion` (`numero_habitacion`),
  ADD KEY `fk_habitaciones_huesped` (`id_huesped`);

--
-- Indexes for table `huesped`
--
ALTER TABLE `huesped`
  ADD PRIMARY KEY (`id_huesped`),
  ADD UNIQUE KEY `identificador` (`identificador`);

--
-- Indexes for table `incidencias`
--
ALTER TABLE `incidencias`
  ADD PRIMARY KEY (`id_incidencia`),
  ADD KEY `fk_incidencias_habitacion` (`id_habitacion`),
  ADD KEY `fk_incidencias_camarista` (`id_camarista`);

--
-- Indexes for table `pagos_estancias`
--
ALTER TABLE `pagos_estancias`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `fk_pagos_estancias_estancia` (`id_estancia`),
  ADD KEY `fk_pagos_estancias_usuario` (`id_usuario`),
  ADD KEY `fk_pagos_estancias_turno` (`id_turno`);

--
-- Indexes for table `pagos_servicios`
--
ALTER TABLE `pagos_servicios`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `fk_pagos_servicios_servicio` (`id_servicio`),
  ADD KEY `fk_pagos_servicios_usuario` (`id_usuario`),
  ADD KEY `fk_pagos_servicios_turno` (`id_turno`),
  ADD KEY `fk_pagos_servicios_estancia` (`id_estancia`);

--
-- Indexes for table `servicios`
--
ALTER TABLE `servicios`
  ADD PRIMARY KEY (`id_servicio`),
  ADD KEY `fk_servicios_usuario` (`id_usuario`),
  ADD KEY `fk_servicios_turno` (`id_turno`);

--
-- Indexes for table `turnos`
--
ALTER TABLE `turnos`
  ADD PRIMARY KEY (`id_turno`),
  ADD KEY `fk_turnos_usuario` (`id_usuario`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `identificador` (`identificador`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `camaristas`
--
ALTER TABLE `camaristas`
  MODIFY `id_camarista` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `estancias`
--
ALTER TABLE `estancias`
  MODIFY `id_estancia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `habitaciones`
--
ALTER TABLE `habitaciones`
  MODIFY `id_habitacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `huesped`
--
ALTER TABLE `huesped`
  MODIFY `id_huesped` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `incidencias`
--
ALTER TABLE `incidencias`
  MODIFY `id_incidencia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `pagos_estancias`
--
ALTER TABLE `pagos_estancias`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `pagos_servicios`
--
ALTER TABLE `pagos_servicios`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `servicios`
--
ALTER TABLE `servicios`
  MODIFY `id_servicio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `turnos`
--
ALTER TABLE `turnos`
  MODIFY `id_turno` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `estancias`
--
ALTER TABLE `estancias`
  ADD CONSTRAINT `fk_estancias_habitacion` FOREIGN KEY (`id_habitacion`) REFERENCES `habitaciones` (`id_habitacion`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_estancias_huesped` FOREIGN KEY (`id_huesped`) REFERENCES `huesped` (`id_huesped`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_estancias_turno` FOREIGN KEY (`id_turno`) REFERENCES `turnos` (`id_turno`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_estancias_usuario_checkin` FOREIGN KEY (`id_usuario_checkin`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_estancias_usuario_checkout` FOREIGN KEY (`id_usuario_checkout`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `habitaciones`
--
ALTER TABLE `habitaciones`
  ADD CONSTRAINT `fk_habitaciones_huesped` FOREIGN KEY (`id_huesped`) REFERENCES `huesped` (`id_huesped`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `incidencias`
--
ALTER TABLE `incidencias`
  ADD CONSTRAINT `fk_incidencias_camarista` FOREIGN KEY (`id_camarista`) REFERENCES `camaristas` (`id_camarista`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_incidencias_habitacion` FOREIGN KEY (`id_habitacion`) REFERENCES `habitaciones` (`id_habitacion`) ON UPDATE CASCADE;

--
-- Constraints for table `pagos_estancias`
--
ALTER TABLE `pagos_estancias`
  ADD CONSTRAINT `fk_pagos_estancias_estancia` FOREIGN KEY (`id_estancia`) REFERENCES `estancias` (`id_estancia`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pagos_estancias_turno` FOREIGN KEY (`id_turno`) REFERENCES `turnos` (`id_turno`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pagos_estancias_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Constraints for table `pagos_servicios`
--
ALTER TABLE `pagos_servicios`
  ADD CONSTRAINT `fk_pagos_servicios_estancia` FOREIGN KEY (`id_estancia`) REFERENCES `estancias` (`id_estancia`),
  ADD CONSTRAINT `fk_pagos_servicios_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pagos_servicios_turno` FOREIGN KEY (`id_turno`) REFERENCES `turnos` (`id_turno`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pagos_servicios_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Constraints for table `servicios`
--
ALTER TABLE `servicios`
  ADD CONSTRAINT `fk_servicios_turno` FOREIGN KEY (`id_turno`) REFERENCES `turnos` (`id_turno`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Constraints for table `turnos`
--
ALTER TABLE `turnos`
  ADD CONSTRAINT `fk_turnos_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
