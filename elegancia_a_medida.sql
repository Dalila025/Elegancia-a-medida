-- =====================================================================
--  ELEGANCIA A MEDIDA — Base de datos completa (versión 4)
--  Proyecto de Prácticas Profesionalizantes II — Grupo 2 · Ciclo 2026
--
--  Probado en MariaDB 10.4+ (XAMPP) y MySQL 8.
--
--  ATENCIÓN: este script BORRA las tablas existentes y las vuelve a
--  crear. Si tiene datos reales cargados, exporte antes los datos.
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
--  · Sí/No:          TINYINT(1) 0/1            → activo, leida
--  · Contraseñas:    clave_hash con password_hash() (nunca en texto)
--
--  MAPA DE TABLAS
--  ---------------------------------------------------------------------
--  EN USO
--   1. estados_solicitud         estados de una solicitud de presupuesto
--   2. tipos_servicio            Confección, Arreglos, Diseño personalizado, Otro
--   3. clientes                  cuentas de clientes (registro.php, login.php)
--   4. usuarios                  cuentas del panel: la modista (panel_login.php)
--   5. categorias                ocasión: Fiesta, Novias, Egresados…
--   6. tipos_producto            tipo de prenda: Vestido, Traje, Falda…
--   7. productos                 prendas de la tienda (venta y/o alquiler), con oferta y temporada
--   8. solicitudes_presupuesto   confección / arreglos a medida (solicitud.php)
--   9. tarifas_plazo             recargo según los días hasta la entrega
--  10. presupuestos              respuesta de la modista y respuesta del cliente
--  11. estados_pedido            estados de un pedido del carrito
--  12. pedidos                   compra o alquiler hecho desde el carrito
--  13. items_pedido              prendas de cada pedido (con el precio de ese momento)
--  14. items_carrito             carrito de cada cliente (antes de confirmar)
--  15. notificaciones            avisos al cliente (de solicitudes y de pedidos)
--  16. datos_sitio               logo, historia y datos de contacto (una sola fila)
--  17. banners                   imágenes del carrusel y del mosaico del inicio
--
--  EVOLUCIÓN POSTERIOR (se crean vacías; ningún archivo PHP las usa todavía)
--  18. mensajes_contacto         formulario de contacto
--  19. pagos                     pagos (señas / saldos) de un presupuesto
--
--  RELACIONES  (tabla.clave_foránea → tabla.clave_primaria)
--  ---------------------------------------------------------------------
--  productos.id_categorias                      → categorias.id_categorias
--  productos.id_tipos_producto                  → tipos_producto.id_tipos_producto
--  solicitudes_presupuesto.id_clientes          → clientes.id_clientes
--  solicitudes_presupuesto.id_tipos_servicio    → tipos_servicio.id_tipos_servicio
--  solicitudes_presupuesto.id_estados_solicitud → estados_solicitud.id_estados_solicitud
--  solicitudes_presupuesto.id_productos         → productos.id_productos      (opcional)
--  presupuestos.id_solicitudes_presupuesto      → solicitudes_presupuesto.id_solicitudes_presupuesto (1 a 1)
--  presupuestos.id_tarifas_plazo                → tarifas_plazo.id_tarifas_plazo (opcional)
--  pedidos.id_clientes                          → clientes.id_clientes
--  pedidos.id_estados_pedido                    → estados_pedido.id_estados_pedido
--  items_pedido.id_pedidos                      → pedidos.id_pedidos
--  items_pedido.id_productos                    → productos.id_productos      (NULL si se borró)
--  items_carrito.id_clientes                    → clientes.id_clientes
--  items_carrito.id_productos                   → productos.id_productos
--  notificaciones.id_solicitudes_presupuesto    → solicitudes_presupuesto.id_solicitudes_presupuesto (o NULL)
--  notificaciones.id_pedidos                    → pedidos.id_pedidos          (o NULL)
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
DROP TABLE IF EXISTS `banners`;
DROP TABLE IF EXISTS `datos_sitio`;
DROP TABLE IF EXISTS `notificaciones`;
DROP TABLE IF EXISTS `items_carrito`;
DROP TABLE IF EXISTS `items_pedido`;
DROP TABLE IF EXISTS `pedidos`;
DROP TABLE IF EXISTS `estados_pedido`;
DROP TABLE IF EXISTS `presupuestos`;
DROP TABLE IF EXISTS `tarifas_plazo`;
DROP TABLE IF EXISTS `solicitudes_presupuesto`;
DROP TABLE IF EXISTS `productos`;
DROP TABLE IF EXISTS `tipos_producto`;
DROP TABLE IF EXISTS `categorias`;
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
--    Los id_estados_solicitud DEBEN coincidir con las constantes
--    ESTADO_* de config.php. No cambiar un id existente.
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
-- 3. clientes
--    Cuentas de los clientes (registro.php, login.php).
--    Solo un cliente con cuenta puede pedir presupuestos y aceptarlos
--    o rechazarlos. Tras 5 claves incorrectas seguidas, la cuenta queda
--    bloqueada 15 minutos (bloqueado_hasta).
-- ---------------------------------------------------------------------
CREATE TABLE `clientes` (
  `id_clientes`       INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `nombre`            VARCHAR(100)     NOT NULL,
  `telefono`          VARCHAR(30)      NOT NULL,
  `email`             VARCHAR(150)     NOT NULL COMMENT 'Se usa para iniciar sesión',
  `clave_hash`        VARCHAR(255)     NOT NULL COMMENT 'password_hash(); nunca la clave en texto',
  `activo`            TINYINT(1)       NOT NULL DEFAULT 1 COMMENT '0 = no puede iniciar sesión',
  `intentos_fallidos` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `bloqueado_hasta`   DATETIME         NULL DEFAULT NULL,
  `ultimo_acceso`     DATETIME         NULL DEFAULT NULL,
  `fecha_alta`        TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_clientes`),
  UNIQUE KEY `uq_clientes_email` (`email`),
  KEY `idx_clientes_telefono` (`telefono`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Cuentas de clientes';


-- ---------------------------------------------------------------------
-- 4. usuarios
--    Cuentas del panel de gestión (la modista). Se ingresa por
--    panel_login.php. debe_cambiar_clave = 1 obliga a elegir una clave
--    nueva en el próximo ingreso (así se entrega el usuario inicial).
--    Mismo bloqueo por intentos fallidos que los clientes.
-- ---------------------------------------------------------------------
CREATE TABLE `usuarios` (
  `id_usuarios`        INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `nombre`             VARCHAR(100)     NOT NULL,
  `email`              VARCHAR(150)     NOT NULL COMMENT 'Se usa para iniciar sesión',
  `clave_hash`         VARCHAR(255)     NOT NULL COMMENT 'password_hash(); nunca la clave en texto',
  `debe_cambiar_clave` TINYINT(1)       NOT NULL DEFAULT 0,
  `activo`             TINYINT(1)       NOT NULL DEFAULT 1,
  `intentos_fallidos`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `bloqueado_hasta`    DATETIME         NULL DEFAULT NULL,
  `ultimo_acceso`      DATETIME         NULL DEFAULT NULL,
  `fecha_alta`         TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_usuarios`),
  UNIQUE KEY `uq_usuarios_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Usuarios del panel de gestión (modista)';


-- ---------------------------------------------------------------------
-- 5. categorias
--    Ocasión o línea de la prenda (Vestidos de fiesta, Novias…).
--    Se administran desde categorias.php. activo = 0 la oculta del
--    inicio junto con todos sus productos.
-- ---------------------------------------------------------------------
CREATE TABLE `categorias` (
  `id_categorias` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(60)       NOT NULL,
  `descripcion`   VARCHAR(255)      NOT NULL DEFAULT '',
  `activo`        TINYINT(1)        NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_categorias`),
  UNIQUE KEY `uq_categorias_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Categorías del catálogo';


-- ---------------------------------------------------------------------
-- 6. tipos_producto
--    Qué prenda es (Vestido, Traje / saco, Falda…). Es el filtro
--    "Tipo de prenda" del inicio. Se administra en categorias.php.
-- ---------------------------------------------------------------------
CREATE TABLE `tipos_producto` (
  `id_tipos_producto` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`            VARCHAR(60)       NOT NULL,
  `activo`            TINYINT(1)        NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_tipos_producto`),
  UNIQUE KEY `uq_tipos_producto_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Tipos de prenda del catálogo';


-- ---------------------------------------------------------------------
-- 7. productos
--    Prendas de la tienda (index.php, tienda.php, prenda.php); las ve cualquiera.
--    Las que están en venta o alquiler se pueden agregar al carrito.
--    Se cargan desde productos.php / producto.php en el panel.
--    · en_venta / en_alquiler: modalidades (filtro "Venta" / "Alquiler").
--      El precio puede quedar NULL = "a consultar".
--    · descuento_porcentaje > 0: aparece en "Ofertas" con el precio
--      rebajado, hasta oferta_hasta (NULL = sin fecha de fin).
--    · de_temporada = 1: aparece en la sección de temporada.
--    · imagen: nombre del archivo dentro de img/productos/ (o NULL).
--    · activo = 0 lo oculta del sitio sin borrarlo.
-- ---------------------------------------------------------------------
CREATE TABLE `productos` (
  `id_productos`         INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `id_categorias`        SMALLINT UNSIGNED NOT NULL,
  `id_tipos_producto`    SMALLINT UNSIGNED NOT NULL,
  `nombre`               VARCHAR(100)      NOT NULL,
  `descripcion`          TEXT              NOT NULL,
  `en_venta`             TINYINT(1)        NOT NULL DEFAULT 1 COMMENT 'Se confecciona / vende',
  `precio_venta`         DECIMAL(10,2)     NULL DEFAULT NULL COMMENT 'Precio de referencia; NULL = a consultar',
  `en_alquiler`          TINYINT(1)        NOT NULL DEFAULT 0 COMMENT 'Se alquila',
  `precio_alquiler`      DECIMAL(10,2)     NULL DEFAULT NULL COMMENT 'NULL = a consultar',
  `descuento_porcentaje` TINYINT UNSIGNED  NOT NULL DEFAULT 0 COMMENT '0 = sin oferta',
  `oferta_hasta`         DATE              NULL DEFAULT NULL COMMENT 'Último día de la oferta; NULL = sin fin',
  `de_temporada`         TINYINT(1)        NOT NULL DEFAULT 0,
  `imagen`               VARCHAR(255)      NULL DEFAULT NULL COMMENT 'Archivo en img/productos/',
  `activo`               TINYINT(1)        NOT NULL DEFAULT 1,
  `fecha_alta`           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion`  DATETIME          NULL DEFAULT NULL,
  PRIMARY KEY (`id_productos`),
  KEY `idx_productos_categoria_activo` (`id_categorias`, `activo`),
  KEY `idx_productos_tipo` (`id_tipos_producto`),
  KEY `idx_productos_oferta` (`activo`, `descuento_porcentaje`, `oferta_hasta`),
  CONSTRAINT `fk_productos_categoria`
      FOREIGN KEY (`id_categorias`) REFERENCES `categorias` (`id_categorias`)
      ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_productos_tipo`
      FOREIGN KEY (`id_tipos_producto`) REFERENCES `tipos_producto` (`id_tipos_producto`)
      ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_productos_precios` CHECK ((`precio_venta` IS NULL OR `precio_venta` > 0)
                                       AND (`precio_alquiler` IS NULL OR `precio_alquiler` > 0)),
  CONSTRAINT `chk_productos_modalidad` CHECK (`en_venta` = 1 OR `en_alquiler` = 1),
  CONSTRAINT `chk_productos_descuento` CHECK (`descuento_porcentaje` <= 90)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Catálogo de productos';


-- ---------------------------------------------------------------------
-- 8. solicitudes_presupuesto
--    Lo que envía el cliente desde solicitud.php → procesar_presupuesto.php.
--    Siempre pertenece a un cliente con cuenta (id_clientes).
--    nombre y telefono son una copia de los datos de la cuenta al
--    momento de pedir (si el cliente los cambia después, la solicitud
--    conserva los originales).
--    id_productos: prenda del catálogo que tomó como referencia (opcional).
-- ---------------------------------------------------------------------
CREATE TABLE `solicitudes_presupuesto` (
  `id_solicitudes_presupuesto` INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `id_clientes`                INT UNSIGNED     NOT NULL COMMENT 'Dueño de la solicitud',
  `id_productos`               INT UNSIGNED     NULL DEFAULT NULL COMMENT 'Producto del catálogo usado como referencia',
  `codigo_seguimiento`         CHAR(8)          NOT NULL COMMENT 'Código corto para identificar la solicitud',
  `nombre`                     VARCHAR(100)     NOT NULL,
  `telefono`                   VARCHAR(30)      NOT NULL,
  `id_tipos_servicio`          TINYINT UNSIGNED NOT NULL,
  `tipo_prenda`                VARCHAR(100)     NOT NULL,
  `descripcion`                TEXT             NOT NULL,
  `fecha_requerida`            DATE             NOT NULL COMMENT 'Fecha que pide el cliente',
  `fecha_solicitud`            TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_estados_solicitud`       TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = Pendiente',
  PRIMARY KEY (`id_solicitudes_presupuesto`),
  UNIQUE KEY `uq_solicitudes_codigo_seguimiento` (`codigo_seguimiento`),
  KEY `idx_solicitudes_estado_fecha` (`id_estados_solicitud`, `fecha_solicitud`),
  KEY `idx_solicitudes_nombre` (`nombre`),
  KEY `idx_solicitudes_tipo_servicio` (`id_tipos_servicio`),
  KEY `idx_solicitudes_cliente` (`id_clientes`),
  KEY `idx_solicitudes_producto` (`id_productos`),
  CONSTRAINT `fk_solicitudes_estado`
      FOREIGN KEY (`id_estados_solicitud`) REFERENCES `estados_solicitud` (`id_estados_solicitud`)
      ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_solicitudes_tipo_servicio`
      FOREIGN KEY (`id_tipos_servicio`) REFERENCES `tipos_servicio` (`id_tipos_servicio`)
      ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_solicitudes_cliente`
      FOREIGN KEY (`id_clientes`) REFERENCES `clientes` (`id_clientes`)
      ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_solicitudes_producto`
      FOREIGN KEY (`id_productos`) REFERENCES `productos` (`id_productos`)
      ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Solicitudes de presupuesto enviadas por los clientes';


-- ---------------------------------------------------------------------
-- 9. tarifas_plazo
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
-- 10. presupuestos
--    Respuesta de la modista a una solicitud (una por solicitud) y la
--    respuesta del cliente (fecha_respuesta_cliente, comentario_cliente).
--    recargo_porcentaje y monto_total se guardan "congelados": si la
--    modista cambia las tarifas después, los presupuestos ya hechos
--    no cambian.  monto_total = monto_base × (100 + recargo) / 100
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
  `fecha_respuesta_cliente`    DATETIME          NULL DEFAULT NULL COMMENT 'Cuándo el cliente aceptó o rechazó',
  `comentario_cliente`         VARCHAR(300)      NOT NULL DEFAULT '' COMMENT 'Motivo opcional al rechazar',
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
  COMMENT='Presupuesto de la modista y respuesta del cliente';


-- ---------------------------------------------------------------------
-- 11. estados_pedido
--     Los id_estados_pedido DEBEN coincidir con las constantes PEDIDO_*
--     de config.php. No cambiar un id existente.
-- ---------------------------------------------------------------------
CREATE TABLE `estados_pedido` (
  `id_estados_pedido` TINYINT UNSIGNED NOT NULL,
  `nombre`            VARCHAR(30)      NOT NULL,
  `descripcion`       VARCHAR(150)     NOT NULL DEFAULT '',
  PRIMARY KEY (`id_estados_pedido`),
  UNIQUE KEY `uq_estados_pedido_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Estados de un pedido del carrito';


-- ---------------------------------------------------------------------
-- 12. pedidos
--     Se crea cuando el cliente confirma su carrito (carrito.php).
--     No hay pago online: la modista lo confirma o lo rechaza desde el
--     panel (pedidos.php) y coordina el pago y el retiro.
--     fecha_retiro / fecha_devolucion: solo si hay prendas en alquiler.
--     total_estimado: suma de los precios conocidos; tiene_a_consultar = 1
--     si alguna prenda no tenía precio publicado.
-- ---------------------------------------------------------------------
CREATE TABLE `pedidos` (
  `id_pedidos`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `id_clientes`         INT UNSIGNED     NOT NULL,
  `codigo`              CHAR(8)          NOT NULL COMMENT 'Número corto del pedido',
  `id_estados_pedido`   TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = Pendiente',
  `fecha_retiro`        DATE             NULL DEFAULT NULL COMMENT 'Alquiler: cuándo retira',
  `fecha_devolucion`    DATE             NULL DEFAULT NULL COMMENT 'Alquiler: cuándo devuelve',
  `comentario_cliente`  VARCHAR(500)     NOT NULL DEFAULT '',
  `nota_modista`        VARCHAR(500)     NOT NULL DEFAULT '' COMMENT 'Lo ve el cliente',
  `total_estimado`      DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  `tiene_a_consultar`   TINYINT(1)       NOT NULL DEFAULT 0,
  `fecha_pedido`        TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` DATETIME         NULL DEFAULT NULL,
  PRIMARY KEY (`id_pedidos`),
  UNIQUE KEY `uq_pedidos_codigo` (`codigo`),
  KEY `idx_pedidos_estado_fecha` (`id_estados_pedido`, `fecha_pedido`),
  KEY `idx_pedidos_cliente` (`id_clientes`),
  CONSTRAINT `fk_pedidos_cliente`
      FOREIGN KEY (`id_clientes`) REFERENCES `clientes` (`id_clientes`)
      ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_pedidos_estado`
      FOREIGN KEY (`id_estados_pedido`) REFERENCES `estados_pedido` (`id_estados_pedido`)
      ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_pedidos_fechas` CHECK (`fecha_devolucion` IS NULL OR `fecha_retiro` IS NULL OR `fecha_devolucion` >= `fecha_retiro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Pedidos de compra o alquiler hechos desde el carrito';


-- ---------------------------------------------------------------------
-- 13. items_pedido
--     Cada prenda de un pedido. Nombre, precio y descuento se copian
--     al confirmar: si después cambia el catálogo, el pedido no cambia.
--     precio_unitario NULL = "a consultar".
-- ---------------------------------------------------------------------
CREATE TABLE `items_pedido` (
  `id_items_pedido`      INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `id_pedidos`           INT UNSIGNED     NOT NULL,
  `id_productos`         INT UNSIGNED     NULL DEFAULT NULL COMMENT 'NULL si el producto se borró',
  `nombre_producto`      VARCHAR(100)     NOT NULL,
  `modalidad`            ENUM('venta','alquiler') NOT NULL,
  `cantidad`             TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `precio_unitario`      DECIMAL(10,2)    NULL DEFAULT NULL COMMENT 'Con el descuento ya aplicado',
  `descuento_porcentaje` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_items_pedido`),
  KEY `idx_items_pedido_pedido` (`id_pedidos`),
  KEY `idx_items_pedido_producto` (`id_productos`),
  CONSTRAINT `fk_items_pedido_pedido`
      FOREIGN KEY (`id_pedidos`) REFERENCES `pedidos` (`id_pedidos`)
      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_items_pedido_producto`
      FOREIGN KEY (`id_productos`) REFERENCES `productos` (`id_productos`)
      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_items_pedido_cantidad` CHECK (`cantidad` BETWEEN 1 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Prendas de cada pedido';


-- ---------------------------------------------------------------------
-- 14. items_carrito
--     El carrito de cada cliente (hace falta iniciar sesión).
--     Una fila por producto + modalidad; agregarlo de nuevo suma cantidad.
--     fecha_agregado: el aviso "Deshacer" se muestra 5 minutos desde acá.
-- ---------------------------------------------------------------------
CREATE TABLE `items_carrito` (
  `id_items_carrito` INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `id_clientes`      INT UNSIGNED     NOT NULL,
  `id_productos`     INT UNSIGNED     NOT NULL,
  `modalidad`        ENUM('venta','alquiler') NOT NULL,
  `cantidad`         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `fecha_agregado`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_items_carrito`),
  UNIQUE KEY `uq_items_carrito_cliente_producto` (`id_clientes`, `id_productos`, `modalidad`),
  KEY `idx_items_carrito_producto` (`id_productos`),
  CONSTRAINT `fk_items_carrito_cliente`
      FOREIGN KEY (`id_clientes`) REFERENCES `clientes` (`id_clientes`)
      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_items_carrito_producto`
      FOREIGN KEY (`id_productos`) REFERENCES `productos` (`id_productos`)
      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `chk_items_carrito_cantidad` CHECK (`cantidad` BETWEEN 1 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Carrito de compras de cada cliente';


-- ---------------------------------------------------------------------
-- 15. notificaciones
--     Avisos para el cliente. Se crean solos cuando la modista responde
--     una solicitud o cambia el estado de un pedido (notificar() y
--     notificar_pedido() en funciones.php).
--     Cada aviso es de UNA solicitud o de UN pedido (nunca de los dos);
--     lo garantizan notificar() y notificar_pedido(). (MariaDB no permite un
--     CHECK sobre columnas con claves foráneas en cascada.)
--     leida = 1 cuando el cliente lo abrió.
-- ---------------------------------------------------------------------
CREATE TABLE `notificaciones` (
  `id_notificaciones`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `id_solicitudes_presupuesto` INT UNSIGNED     NULL DEFAULT NULL,
  `id_pedidos`                 INT UNSIGNED     NULL DEFAULT NULL,
  `id_estados_solicitud`       TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Estado al que pasó la solicitud',
  `titulo`                     VARCHAR(100)     NOT NULL,
  `mensaje`                    VARCHAR(500)     NOT NULL,
  `leida`                      TINYINT(1)       NOT NULL DEFAULT 0,
  `fecha_creacion`             TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_lectura`              DATETIME         NULL DEFAULT NULL,
  PRIMARY KEY (`id_notificaciones`),
  KEY `idx_notificaciones_solicitud_leida` (`id_solicitudes_presupuesto`, `leida`),
  KEY `idx_notificaciones_pedido_leida` (`id_pedidos`, `leida`),
  KEY `idx_notificaciones_estado` (`id_estados_solicitud`),
  CONSTRAINT `fk_notificaciones_solicitud`
      FOREIGN KEY (`id_solicitudes_presupuesto`) REFERENCES `solicitudes_presupuesto` (`id_solicitudes_presupuesto`)
      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_notificaciones_pedido`
      FOREIGN KEY (`id_pedidos`) REFERENCES `pedidos` (`id_pedidos`)
      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_notificaciones_estado`
      FOREIGN KEY (`id_estados_solicitud`) REFERENCES `estados_solicitud` (`id_estados_solicitud`)
      ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Avisos al cliente sobre solicitudes y pedidos';


-- ---------------------------------------------------------------------
-- 16. datos_sitio
--     Logo, textos y datos de contacto que se ven en el encabezado, el
--     inicio y el pie de página. Tiene UNA sola fila (id_datos_sitio = 1) y la modista la
--     edita desde el panel: Datos del sitio (sitio.php).
-- ---------------------------------------------------------------------
CREATE TABLE `datos_sitio` (
  `id_datos_sitio`    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `logo`              VARCHAR(255)     NULL DEFAULT NULL COMMENT 'Archivo en img/sitio/; NULL = logo de texto',
  `nombre_modista`    VARCHAR(100)     NOT NULL DEFAULT '' COMMENT 'Firma de la historia',
  `historia_titulo`   VARCHAR(100)     NOT NULL DEFAULT 'Nuestra historia',
  `historia_texto`    TEXT             NOT NULL,
  `temporada_titulo`  VARCHAR(60)      NOT NULL DEFAULT 'Temporada' COMMENT 'Ej: Primavera-Verano 2026',
  `telefono`          VARCHAR(30)      NOT NULL DEFAULT '',
  `whatsapp`          VARCHAR(30)      NOT NULL DEFAULT '' COMMENT 'Con código de país, ej: 54 9 11 1234-5678',
  `email`             VARCHAR(150)     NOT NULL DEFAULT '',
  `instagram`         VARCHAR(60)      NOT NULL DEFAULT '' COMMENT 'Usuario sin @',
  `facebook`          VARCHAR(100)     NOT NULL DEFAULT '' COMMENT 'Nombre de la página',
  `direccion`         VARCHAR(150)     NOT NULL DEFAULT '',
  `horarios`          VARCHAR(150)     NOT NULL DEFAULT '',
  `fecha_actualizacion` DATETIME       NULL DEFAULT NULL,
  PRIMARY KEY (`id_datos_sitio`),
  CONSTRAINT `chk_datos_sitio_una_fila` CHECK (`id_datos_sitio` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Historia de la modista y datos de contacto del sitio';


-- ---------------------------------------------------------------------
-- 17. banners
--     Imágenes grandes del inicio, editables desde el panel (banners.php).
--     ubicacion = 'carrusel': pantallas que pasan arriba de todo
--                 ('Bienvenidos', 'Venta', 'Alquiler', 'Accesorios'…).
--     ubicacion = 'mosaico' : los recuadros que van debajo del carrusel.
--     enlace: página del sitio a la que lleva el botón (ej: tienda.php?modalidad=alquiler).
-- ---------------------------------------------------------------------
CREATE TABLE `banners` (
  `id_banners`  SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ubicacion`   ENUM('carrusel','mosaico') NOT NULL DEFAULT 'carrusel',
  `titulo`      VARCHAR(60)       NOT NULL,
  `subtitulo`   VARCHAR(160)      NOT NULL DEFAULT '',
  `texto_boton` VARCHAR(30)       NOT NULL DEFAULT '',
  `enlace`      VARCHAR(255)      NOT NULL DEFAULT '',
  `imagen`      VARCHAR(255)      NULL DEFAULT NULL COMMENT 'Archivo en img/banners/',
  `orden`       SMALLINT          NOT NULL DEFAULT 0,
  `activo`      TINYINT(1)        NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_banners`),
  KEY `idx_banners_ubicacion_orden` (`ubicacion`, `activo`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Carrusel y mosaico del inicio';


-- =====================================================================
--  TABLAS DE EVOLUCIÓN POSTERIOR (vacías, todavía sin código PHP)
--  Son un borrador: revisar las columnas cuando se implemente cada una.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 18. mensajes_contacto — formulario de contacto
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
-- 19. pagos — señas y saldos de un presupuesto
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
  (4, 'Cancelado',     'Lo canceló la modista (antes o durante el trabajo)'),
  (5, 'Rechazado',     'La modista no toma el trabajo'),
  (6, 'Presupuestado', 'La modista cargó fecha de entrega y monto; espera respuesta del cliente'),
  (7, 'No aceptado',   'El cliente rechazó el presupuesto; la modista puede enviarle otro');

INSERT INTO `estados_pedido` (`id_estados_pedido`, `nombre`, `descripcion`) VALUES
  (1, 'Pendiente',          'El cliente confirmó el carrito; la modista todavía no respondió'),
  (2, 'Confirmado',         'La modista aceptó el pedido'),
  (3, 'Listo para retirar', 'Las prendas están listas'),
  (4, 'Entregado',          'El cliente retiró las prendas'),
  (5, 'Devuelto',           'Alquiler: el cliente devolvió las prendas'),
  (6, 'Cancelado',          'Lo canceló el cliente'),
  (7, 'Rechazado',          'La modista no puede tomar el pedido');

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

-- Usuario inicial del panel.  Email: modista@elegancia.com  ·  Clave: Modista2026
-- Al primer ingreso el sistema obliga a cambiar la clave.
INSERT INTO `usuarios` (`id_usuarios`, `nombre`, `email`, `clave_hash`, `debe_cambiar_clave`) VALUES
  (1, 'Modista', 'modista@elegancia.com', '$2y$10$geOkm8PKCE9gR0IY.j4l7eEhqINq59AqzyFDNa2xxjHleUUrh7uxC', 1);

INSERT INTO `categorias` (`id_categorias`, `nombre`, `descripcion`) VALUES
  (1, 'Fiesta',     'Vestidos y conjuntos para eventos'),
  (2, 'Novias',     'Vestidos de novia y civil'),
  (3, 'Sastrería',  'Trajes y sacos a medida'),
  (4, 'Egresados',  'Conjuntos para fiestas de egresados'),
  (5, 'Día a día',  'Prendas para usar todos los días');

INSERT INTO `tipos_producto` (`id_tipos_producto`, `nombre`) VALUES
  (1, 'Vestido'),
  (2, 'Traje / saco'),
  (3, 'Conjunto'),
  (4, 'Falda'),
  (5, 'Pantalón'),
  (6, 'Accesorio');

-- Textos de ejemplo: la modista los reemplaza desde el panel (Datos del sitio).
INSERT INTO `datos_sitio`
  (`id_datos_sitio`, `nombre_modista`, `historia_titulo`, `historia_texto`, `temporada_titulo`,
   `telefono`, `whatsapp`, `email`, `instagram`, `facebook`, `direccion`, `horarios`)
VALUES
  (1, '', 'Nuestra historia',
   'Elegancia a Medida nació de la pasión por la costura y por las prendas hechas con tiempo y cuidado.\n\nCada trabajo empieza con una charla: qué querés usar, para qué ocasión y cómo te querés sentir. A partir de ahí tomamos medidas, elegimos juntas la tela y hacemos las pruebas necesarias hasta que la prenda te quede perfecta.\n\nConfeccionamos a medida, hacemos arreglos y diseñamos piezas únicas, y también tenemos prendas para alquilar.',
   'Primavera-Verano',
   '11 2345-6789', '54 9 11 2345-6789', 'contacto@eleganciaamedida.com', 'eleganciaamedida', 'Elegancia a Medida',
   'Atención con turno previo', 'Lunes a viernes de 10 a 19 h · Sábados de 10 a 13 h');


-- Portada: carrusel y mosaico del inicio (la modista los cambia desde el panel).
-- Fotos de ejemplo de la plantilla TailStore (licencia MIT): reemplazar por fotos propias.
INSERT INTO `banners` (`ubicacion`, `titulo`, `subtitulo`, `texto_boton`, `enlace`, `imagen`, `orden`) VALUES
  ('carrusel', 'Bienvenidos',  'Prendas a medida, arreglos y alquiler para cada ocasión.', 'Ver la tienda',     'tienda.php',                     'ejemplo_bienvenidos.jpg', 1),
  ('carrusel', 'Venta',        'Prendas listas para que las hagamos a tu medida.',         'Ver prendas',       'tienda.php?modalidad=venta',     'ejemplo_venta.jpg',       2),
  ('carrusel', 'Alquiler',     'Vestidos y trajes para tu evento, sin comprarlos.',        'Ver alquileres',    'tienda.php?modalidad=alquiler',  'ejemplo_alquiler.jpg',    3),
  ('carrusel', 'Accesorios',   'El detalle que completa tu look.',                         'Ver accesorios',    'tienda.php?tipo=6',              'ejemplo_accesorios.jpg',  4),
  ('mosaico',  'Venta',        '',                                                         'Comprar',           'tienda.php?modalidad=venta',     'ejemplo_mosaico_venta.jpg',      1),
  ('mosaico',  'Alquiler',     '',                                                         'Alquilar',          'tienda.php?modalidad=alquiler',  'ejemplo_mosaico_alquiler.jpg',   2),
  ('mosaico',  'Accesorios',   '',                                                         'Ver accesorios',    'tienda.php?tipo=6',              'ejemplo_mosaico_accesorios.jpg', 3);


-- =====================================================================
--  DATOS DE PRUEBA (opcionales — borrar este bloque en producción)
-- =====================================================================

-- Productos de ejemplo. Las fotos de ejemplo vienen de la plantilla TailStore (licencia MIT).
-- Algunos con oferta (descuento_porcentaje) y otros de temporada.
INSERT INTO `productos`
  (`id_productos`, `id_categorias`, `id_tipos_producto`, `nombre`, `descripcion`,
   `en_venta`, `precio_venta`, `en_alquiler`, `precio_alquiler`,
   `descuento_porcentaje`, `oferta_hasta`, `de_temporada`)
VALUES
  (1, 1, 1, 'Vestido largo bordó',     'Escote en V, espalda descubierta y falda con caída. Se confecciona a medida en crepe o satén.', 1, 95000.00,  1, 30000.00, 15, CURDATE() + INTERVAL 20 DAY, 0),
  (2, 1, 1, 'Vestido cóctel negro',    'Corte recto a la rodilla, mangas tres cuartos. Ideal para eventos de noche.',                   1, 70000.00,  1, 22000.00,  0, NULL, 0),
  (3, 2, 1, 'Vestido de novia sirena', 'Corsé con encaje y falda sirena con cola corta. Incluye tres pruebas.',                          1, NULL,      0, NULL,      0, NULL, 0),
  (4, 2, 1, 'Vestido de civil',        'Vestido midi en crepe marfil con mangas de gasa.',                                               1, 85000.00,  0, NULL,     10, NULL, 1),
  (5, 3, 2, 'Traje sastre dos piezas', 'Saco entallado y pantalón recto. Tela a elección.',                                              1, 120000.00, 1, 35000.00,  0, NULL, 0),
  (6, 4, 3, 'Conjunto top y falda',    'Top con brillo y falda larga con tajo. Colores a elección del grupo.',                           1, 65000.00,  0, NULL,      0, NULL, 1),
  (7, 5, 4, 'Falda midi plisada',      'Falda plisada en gasa liviana, cintura con elástico oculto. Ideal para primavera.',              1, 38000.00,  0, NULL,     20, NULL, 1),
  (8, 5, 2, 'Saco de lino',            'Saco liviano sin forrar, en lino natural. Colores claros de temporada.',                         1, 72000.00,  0, NULL,      0, NULL, 1),
  (9, 1, 6, 'Tocado de flores',        'Tocado artesanal con flores de tela y perlas. Combina con vestidos de fiesta y novia.',          1, 15000.00,  1, 5000.00,   0, NULL, 0),
  (10, 1, 1, 'Vestido corto con lentejuelas', 'Vestido corto bordado con lentejuelas. Solo alquiler, talles S a L.',                     0, NULL,      1, 28000.00,  0, NULL, 0);

UPDATE `productos` SET `imagen` = CONCAT('ejemplo_', `id_productos`, '.jpg') WHERE `id_productos` IN (1, 2, 3, 4, 5, 6, 8);

-- Clientes de prueba. Clave de los dos: demo1234
INSERT INTO `clientes` (`id_clientes`, `nombre`, `telefono`, `email`, `clave_hash`) VALUES
  (1, 'María González', '11 2345-6789', 'maria@demo.com', '$2y$10$4amcaFpntGBzeoFYdWtx8.fRLhPb8EY00C4xmUO6tjL4dy6A4DrOa'),
  (2, 'Sofía Ramírez',  '11 9876-5432', 'sofia@demo.com', '$2y$10$4amcaFpntGBzeoFYdWtx8.fRLhPb8EY00C4xmUO6tjL4dy6A4DrOa');

INSERT INTO `solicitudes_presupuesto`
  (`id_solicitudes_presupuesto`, `id_clientes`, `id_productos`, `codigo_seguimiento`, `nombre`, `telefono`,
   `id_tipos_servicio`, `tipo_prenda`, `descripcion`, `fecha_requerida`, `id_estados_solicitud`)
VALUES
  (1, 1, 1, 'MGZ7K3QA', 'María González', '11 2345-6789', 1, 'Vestido largo bordó',
   'Como el del catálogo pero con mangas largas de gasa. Talle M.',
   CURDATE() + INTERVAL 20 DAY, 1),
  (2, 1, NULL, 'MGZ4N8WD', 'María González', '11 2345-6789', 2, 'Pantalón de vestir',
   'Acortar ruedo 4 cm y entallar cintura. Tela de lino.',
   CURDATE() + INTERVAL 5 DAY, 1),
  (3, 2, 6, 'SRM9P2HT', 'Sofía Ramírez', '11 9876-5432', 3, 'Conjunto top y falda',
   'Para la fiesta de egresados, en satén azul.',
   CURDATE() + INTERVAL 45 DAY, 6);

INSERT INTO `presupuestos`
  (`id_solicitudes_presupuesto`, `id_tarifas_plazo`, `monto_base`, `fecha_entrega`, `plazo_dias`,
   `recargo_porcentaje`, `monto_total`, `observaciones`)
VALUES
  (3, 3, 85000.00, CURDATE() + INTERVAL 40 DAY, 40, 0.00, 85000.00,
   'Incluye dos pruebas. La tela la aporta la clienta.');

INSERT INTO `notificaciones`
  (`id_solicitudes_presupuesto`, `id_estados_solicitud`, `titulo`, `mensaje`)
VALUES
  (3, 6, 'Tu presupuesto está listo',
   'Trabajo: conjunto top y falda. Total: $ 85.000,00. Entrega estimada: en 40 días. Incluye dos pruebas. La tela la aporta la clienta.');
