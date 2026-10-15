-- =====================================================================
--  ELEGANCIA A MEDIDA — Base de datos completa (versión 2)
--  Proyecto de Prácticas Profesionalizantes II — Grupo 2 · Ciclo 2026
--
--  Probado en MariaDB 10.4+ (XAMPP) y MySQL 8.
--
--  ATENCIÓN: este script BORRA las tablas existentes y las vuelve a
--  crear. Si tiene solicitudes reales cargadas, exporte antes los datos.
-- =====================================================================
--
--  CONVENCIONES (para no perderse al modificar)
--  ---------------------------------------------------------------------
--  · Tablas: en plural y snake_case            → solicitudes_presupuesto
--  · Clave primaria: id_<nombre de la tabla>   → usuarios.id_usuarios
--  · Clave foránea:  MISMO nombre que la clave primaria a la que apunta
--                    → presupuestos.id_solicitudes_presupuesto apunta a
--                      solicitudes_presupuesto.id_solicitudes_presupuesto
--  · Índices:        idx_<tabla>_<columnas>
--  · Claves foráneas: fk_<tabla>_<referencia>
--  · Fechas de alta: fecha_<acción>            → fecha_solicitud, fecha_alta
--  · Importes:       DECIMAL(10,2)             (nunca FLOAT para dinero)
--  · Sí/No:          TINYINT(1) 0/1            → activo, leido
--
--  MAPA DE TABLAS
--  ---------------------------------------------------------------------
--  EN USO (MVP + presupuesto de la modista)
--   1. estados_solicitud         catálogo de estados de una solicitud
--   2. tipos_servicio            Confección, Arreglos, Diseño personalizado, Otro
--   3. solicitudes_presupuesto   lo que carga el cliente en index.php
--   4. tarifas_plazo             recargo según los días hasta la entrega
--   5. presupuestos              respuesta de la modista: fecha, plazo y monto
--
--  EVOLUCIÓN POSTERIOR (se crean vacías; ningún archivo PHP las usa todavía)
--   6. clientes                  registro e inicio de sesión de clientes
--   7. usuarios                  login de la modista / administrador
--   8. categorias                categorías del catálogo
--   9. productos                 catálogo y ABM de productos
--  10. mensajes_contacto         formulario de contacto
--  11. alquileres                alquiler de prendas del catálogo
--  12. pagos                     pagos (señas / saldos) de un presupuesto
--
--  RELACIONES  (tabla.clave_foránea → tabla.clave_primaria)
--  ---------------------------------------------------------------------
--  solicitudes_presupuesto.id_estados_solicitud → estados_solicitud.id_estados_solicitud
--  solicitudes_presupuesto.id_tipos_servicio    → tipos_servicio.id_tipos_servicio
--  solicitudes_presupuesto.id_clientes          → clientes.id_clientes          (opcional)
--  presupuestos.id_solicitudes_presupuesto      → solicitudes_presupuesto.id_solicitudes_presupuesto (1 a 1)
--  presupuestos.id_tarifas_plazo                → tarifas_plazo.id_tarifas_plazo (opcional)
--  productos.id_categorias                      → categorias.id_categorias
--  alquileres.id_productos                      → productos.id_productos
--  alquileres.id_clientes                       → clientes.id_clientes
--  pagos.id_presupuestos                        → presupuestos.id_presupuestos
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET NAMES utf8mb4;
SET time_zone = "-03:00";

CREATE DATABASE IF NOT EXISTS `elegancia_a_medida`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE `elegancia_a_medida`;

-- Se desactivan las FK solo para poder borrar en cualquier orden.
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `pagos`;
DROP TABLE IF EXISTS `alquileres`;
DROP TABLE IF EXISTS `mensajes_contacto`;
DROP TABLE IF EXISTS `productos`;
DROP TABLE IF EXISTS `categorias`;
DROP TABLE IF EXISTS `presupuestos`;
DROP TABLE IF EXISTS `tarifas_plazo`;
DROP TABLE IF EXISTS `solicitudes_presupuesto`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `clientes`;
DROP TABLE IF EXISTS `tipos_servicio`;
DROP TABLE IF EXISTS `estados_solicitud`;

SET FOREIGN_KEY_CHECKS = 1;


-- =====================================================================
--  TABLAS EN USO
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. estados_solicitud
--    Los id_estados_solicitud DEBEN coincidir con las constantes ESTADO_* de config.php.
--    No cambiar un id existente: las solicitudes guardadas lo usan.
-- ---------------------------------------------------------------------
CREATE TABLE `estados_solicitud` (
  `id_estados_solicitud` TINYINT UNSIGNED NOT NULL,
  `nombre`               VARCHAR(30)      NOT NULL,
  `descripcion`          VARCHAR(150)     NOT NULL DEFAULT '',
  PRIMARY KEY (`id_estados_solicitud`),
  UNIQUE KEY `uq_estados_solicitud_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Catálogo de estados de una solicitud de presupuesto';


-- ---------------------------------------------------------------------
-- 2. tipos_servicio
--    El formulario (index.php) arma el desplegable con las filas activas.
--    Para dejar de ofrecer un servicio: activo = 0 (no borrar la fila).
-- ---------------------------------------------------------------------
CREATE TABLE `tipos_servicio` (
  `id_tipos_servicio` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`            VARCHAR(50)      NOT NULL,
  `activo`            TINYINT(1)       NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_tipos_servicio`),
  UNIQUE KEY `uq_tipos_servicio_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Servicios que el cliente puede elegir en el formulario';


-- ---------------------------------------------------------------------
-- 6. clientes  (EVOLUCIÓN POSTERIOR — se crea antes porque
--    solicitudes_presupuesto la referencia)
--    Login de clientes e historial de pedidos.
-- ---------------------------------------------------------------------
CREATE TABLE `clientes` (
  `id_clientes` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(100) NOT NULL,
  `telefono`    VARCHAR(30)  NOT NULL,
  `email`       VARCHAR(150) NULL DEFAULT NULL,
  `clave_hash`  VARCHAR(255) NULL DEFAULT NULL COMMENT 'password_hash(); NULL si nunca se registró',
  `activo`      TINYINT(1)   NOT NULL DEFAULT 1,
  `fecha_alta`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_clientes`),
  UNIQUE KEY `uq_clientes_email` (`email`),
  KEY `idx_clientes_telefono` (`telefono`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='[Evolución posterior] Clientes registrados';


-- ---------------------------------------------------------------------
-- 3. solicitudes_presupuesto
--    Lo que envía el cliente desde index.php → procesar_presupuesto.php.
--    nombre y telefono se guardan aquí porque hoy no hay login;
--    id_clientes queda NULL hasta que exista el registro de clientes.
-- ---------------------------------------------------------------------
CREATE TABLE `solicitudes_presupuesto` (
  `id_solicitudes_presupuesto` INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `id_clientes`                INT UNSIGNED     NULL DEFAULT NULL COMMENT 'NULL mientras no haya login de clientes',
  `nombre`                     VARCHAR(100)     NOT NULL,
  `telefono`                   VARCHAR(30)      NOT NULL,
  `id_tipos_servicio`          TINYINT UNSIGNED NOT NULL,
  `tipo_prenda`                VARCHAR(100)     NOT NULL,
  `descripcion`                TEXT             NOT NULL,
  `fecha_requerida`            DATE             NOT NULL COMMENT 'Fecha que pide el cliente',
  `fecha_solicitud`            TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_estados_solicitud`       TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = Pendiente',
  PRIMARY KEY (`id_solicitudes_presupuesto`),
  KEY `idx_solicitudes_estado_fecha` (`id_estados_solicitud`, `fecha_solicitud`),
  KEY `idx_solicitudes_nombre` (`nombre`),
  KEY `idx_solicitudes_tipo_servicio` (`id_tipos_servicio`),
  KEY `idx_solicitudes_cliente` (`id_clientes`),
  CONSTRAINT `fk_solicitudes_estado`
      FOREIGN KEY (`id_estados_solicitud`) REFERENCES `estados_solicitud` (`id_estados_solicitud`)
      ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_solicitudes_tipo_servicio`
      FOREIGN KEY (`id_tipos_servicio`) REFERENCES `tipos_servicio` (`id_tipos_servicio`)
      ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_solicitudes_cliente`
      FOREIGN KEY (`id_clientes`) REFERENCES `clientes` (`id_clientes`)
      ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Solicitudes de presupuesto enviadas por los clientes';


-- ---------------------------------------------------------------------
-- 4. tarifas_plazo
--    La modista define cuánto se recarga (o descuenta) según los días
--    que faltan hasta la entrega. Se edita desde tarifas.php.
--    Se elige la fila con el menor dias_hasta que sea >= al plazo.
--    dias_hasta = NULL significa "sin límite" (cualquier plazo mayor).
--    recargo_porcentaje negativo = descuento.
-- ---------------------------------------------------------------------
CREATE TABLE `tarifas_plazo` (
  `id_tarifas_plazo`   SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`             VARCHAR(50)       NOT NULL COMMENT 'Ej: Urgente, Rápido, Normal',
  `dias_hasta`         SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Plazo máximo en días; NULL = sin límite',
  `recargo_porcentaje` DECIMAL(5,2)      NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_tarifas_plazo`),
  UNIQUE KEY `uq_tarifas_plazo_dias_hasta` (`dias_hasta`),
  CONSTRAINT `chk_tarifas_plazo_recargo` CHECK (`recargo_porcentaje` BETWEEN -50 AND 300)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Recargo por plazo de entrega definido por la modista';


-- ---------------------------------------------------------------------
-- 5. presupuestos
--    Respuesta de la modista a una solicitud (una por solicitud).
--    recargo_porcentaje y monto_total se guardan "congelados": si la
--    modista cambia las tarifas después, los presupuestos ya hechos
--    no cambian.
--    monto_total = monto_base × (1 + recargo_porcentaje / 100)
-- ---------------------------------------------------------------------
CREATE TABLE `presupuestos` (
  `id_presupuestos`            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `id_solicitudes_presupuesto` INT UNSIGNED      NOT NULL,
  `id_tarifas_plazo`           SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Tarifa aplicada; NULL si luego se borró',
  `monto_base`                 DECIMAL(10,2)     NOT NULL COMMENT 'Precio del trabajo sin recargo',
  `fecha_entrega`              DATE              NOT NULL COMMENT 'Fecha que propone la modista',
  `plazo_dias`                 SMALLINT UNSIGNED NOT NULL COMMENT 'Días entre el presupuesto y la entrega',
  `recargo_porcentaje`         DECIMAL(5,2)      NOT NULL DEFAULT 0.00,
  `monto_total`                DECIMAL(10,2)     NOT NULL,
  `observaciones`              VARCHAR(500)      NOT NULL DEFAULT '',
  `fecha_creacion`             TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion`        DATETIME          NULL DEFAULT NULL,
  PRIMARY KEY (`id_presupuestos`),
  UNIQUE KEY `uq_presupuestos_solicitud` (`id_solicitudes_presupuesto`),
  KEY `idx_presupuestos_fecha_entrega` (`fecha_entrega`),
  KEY `idx_presupuestos_tarifa` (`id_tarifas_plazo`),
  CONSTRAINT `fk_presupuestos_solicitud`
      FOREIGN KEY (`id_solicitudes_presupuesto`) REFERENCES `solicitudes_presupuesto` (`id_solicitudes_presupuesto`)
      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_presupuestos_tarifa_plazo`
      FOREIGN KEY (`id_tarifas_plazo`) REFERENCES `tarifas_plazo` (`id_tarifas_plazo`)
      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_presupuestos_montos` CHECK (`monto_base` > 0 AND `monto_total` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Presupuesto de la modista: fecha de entrega, plazo y monto';


-- =====================================================================
--  TABLAS DE EVOLUCIÓN POSTERIOR (vacías, todavía sin código PHP)
--  Son un borrador: revisar las columnas cuando se implemente cada una.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 7. usuarios — login de la modista / administrador
-- ---------------------------------------------------------------------
CREATE TABLE `usuarios` (
  `id_usuarios`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(100) NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `clave_hash`    VARCHAR(255) NOT NULL COMMENT 'password_hash()',
  `rol`           ENUM('modista','admin') NOT NULL DEFAULT 'modista',
  `activo`        TINYINT(1)   NOT NULL DEFAULT 1,
  `fecha_alta`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_acceso` DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id_usuarios`),
  UNIQUE KEY `uq_usuarios_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='[Evolución posterior] Usuarios del panel (modista/admin)';


-- ---------------------------------------------------------------------
-- 8. categorias — categorías del catálogo
-- ---------------------------------------------------------------------
CREATE TABLE `categorias` (
  `id_categorias` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(60)       NOT NULL,
  `descripcion`   VARCHAR(255)      NOT NULL DEFAULT '',
  `activo`        TINYINT(1)        NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_categorias`),
  UNIQUE KEY `uq_categorias_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='[Evolución posterior] Categorías del catálogo';


-- ---------------------------------------------------------------------
-- 9. productos — catálogo y ABM de productos
-- ---------------------------------------------------------------------
CREATE TABLE `productos` (
  `id_productos`    INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `id_categorias`   SMALLINT UNSIGNED NOT NULL,
  `nombre`          VARCHAR(100)      NOT NULL,
  `descripcion`     TEXT              NOT NULL,
  `precio_venta`    DECIMAL(10,2)     NULL DEFAULT NULL COMMENT 'NULL si no se vende',
  `precio_alquiler` DECIMAL(10,2)     NULL DEFAULT NULL COMMENT 'NULL si no se alquila',
  `imagen`          VARCHAR(255)      NULL DEFAULT NULL COMMENT 'Ruta relativa, ej: img/productos/12.jpg',
  `activo`          TINYINT(1)        NOT NULL DEFAULT 1,
  `fecha_alta`      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_productos`),
  KEY `idx_productos_categoria` (`id_categorias`),
  CONSTRAINT `fk_productos_categoria`
      FOREIGN KEY (`id_categorias`) REFERENCES `categorias` (`id_categorias`)
      ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='[Evolución posterior] Catálogo de productos';


-- ---------------------------------------------------------------------
-- 10. mensajes_contacto — formulario de contacto
-- ---------------------------------------------------------------------
CREATE TABLE `mensajes_contacto` (
  `id_mensajes_contacto` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`               VARCHAR(100) NOT NULL,
  `telefono`             VARCHAR(30)  NULL DEFAULT NULL,
  `email`                VARCHAR(150) NULL DEFAULT NULL,
  `mensaje`              TEXT         NOT NULL,
  `leido`                TINYINT(1)   NOT NULL DEFAULT 0,
  `fecha_envio`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_mensajes_contacto`),
  KEY `idx_mensajes_contacto_leido` (`leido`, `fecha_envio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='[Evolución posterior] Mensajes del formulario de contacto';


-- ---------------------------------------------------------------------
-- 11. alquileres — alquiler de prendas del catálogo
--     estado: 1 Reservado, 2 Retirado, 3 Devuelto, 4 Cancelado
-- ---------------------------------------------------------------------
CREATE TABLE `alquileres` (
  `id_alquileres`    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `id_productos`     INT UNSIGNED     NOT NULL,
  `id_clientes`      INT UNSIGNED     NOT NULL,
  `fecha_retiro`     DATE             NOT NULL,
  `fecha_devolucion` DATE             NOT NULL,
  `monto`            DECIMAL(10,2)    NOT NULL,
  `estado`           TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 Reservado, 2 Retirado, 3 Devuelto, 4 Cancelado',
  `fecha_registro`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_alquileres`),
  KEY `idx_alquileres_producto_fechas` (`id_productos`, `fecha_retiro`, `fecha_devolucion`),
  KEY `idx_alquileres_cliente` (`id_clientes`),
  CONSTRAINT `fk_alquileres_producto`
      FOREIGN KEY (`id_productos`) REFERENCES `productos` (`id_productos`)
      ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_alquileres_cliente`
      FOREIGN KEY (`id_clientes`) REFERENCES `clientes` (`id_clientes`)
      ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_alquileres_fechas` CHECK (`fecha_devolucion` >= `fecha_retiro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='[Evolución posterior] Alquiler de prendas';


-- ---------------------------------------------------------------------
-- 12. pagos — señas y saldos de un presupuesto
-- ---------------------------------------------------------------------
CREATE TABLE `pagos` (
  `id_pagos`        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `id_presupuestos` INT UNSIGNED  NOT NULL,
  `monto`           DECIMAL(10,2) NOT NULL,
  `metodo`          VARCHAR(30)   NOT NULL COMMENT 'Efectivo, Transferencia, Mercado Pago…',
  `concepto`        ENUM('sena','saldo','total') NOT NULL DEFAULT 'sena',
  `fecha_pago`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `observaciones`   VARCHAR(255)  NOT NULL DEFAULT '',
  PRIMARY KEY (`id_pagos`),
  KEY `idx_pagos_presupuesto` (`id_presupuestos`),
  CONSTRAINT `fk_pagos_presupuesto`
      FOREIGN KEY (`id_presupuestos`) REFERENCES `presupuestos` (`id_presupuestos`)
      ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_pagos_monto` CHECK (`monto` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='[Evolución posterior] Pagos de presupuestos';


-- =====================================================================
--  DATOS INICIALES (obligatorios: el sistema los necesita)
-- =====================================================================

INSERT INTO `estados_solicitud` (`id_estados_solicitud`, `nombre`, `descripcion`) VALUES
  (1, 'Pendiente',     'Recién enviada por el cliente; la modista todavía no respondió'),
  (2, 'En proceso',    'El cliente aceptó el presupuesto y el trabajo está en curso'),
  (3, 'Completado',    'Trabajo terminado y entregado'),
  (4, 'Cancelado',     'Se canceló después de presupuestar o durante el trabajo'),
  (5, 'Rechazado',     'La modista no toma el trabajo'),
  (6, 'Presupuestado', 'La modista cargó fecha de entrega y monto; espera respuesta del cliente');

INSERT INTO `tipos_servicio` (`id_tipos_servicio`, `nombre`) VALUES
  (1, 'Confección'),
  (2, 'Arreglos'),
  (3, 'Diseño personalizado'),
  (4, 'Otro');

-- Valores de ejemplo: la modista los ajusta desde tarifas.php
INSERT INTO `tarifas_plazo` (`id_tarifas_plazo`, `nombre`, `dias_hasta`, `recargo_porcentaje`) VALUES
  (1, 'Urgente', 7,    40.00),
  (2, 'Rápido',  15,   20.00),
  (3, 'Normal',  NULL,  0.00);


-- =====================================================================
--  DATOS DE PRUEBA (opcionales — borrar este bloque en producción)
-- =====================================================================

INSERT INTO `solicitudes_presupuesto`
  (`nombre`, `telefono`, `id_tipos_servicio`, `tipo_prenda`, `descripcion`, `fecha_requerida`, `id_estados_solicitud`)
VALUES
  ('María González', '11 2345-6789', 1, 'Vestido de fiesta',
   'Vestido largo color bordó para casamiento, talle M, con espalda descubierta.',
   CURDATE() + INTERVAL 20 DAY, 1),
  ('Lucía Fernández', '351 456-7890', 2, 'Pantalón de vestir',
   'Acortar ruedo 4 cm y entallar cintura. Tela de lino.',
   CURDATE() + INTERVAL 5 DAY, 1),
  ('Sofía Ramírez', '11 9876-5432', 3, 'Traje de egresada',
   'Diseño de conjunto de dos piezas en satén azul para fiesta de egresados.',
   CURDATE() + INTERVAL 45 DAY, 6);

INSERT INTO `presupuestos`
  (`id_solicitudes_presupuesto`, `id_tarifas_plazo`, `monto_base`, `fecha_entrega`, `plazo_dias`,
   `recargo_porcentaje`, `monto_total`, `observaciones`)
VALUES
  (3, 3, 85000.00, CURDATE() + INTERVAL 40 DAY, 40, 0.00, 85000.00,
   'Incluye dos pruebas. La tela la aporta la clienta.');
