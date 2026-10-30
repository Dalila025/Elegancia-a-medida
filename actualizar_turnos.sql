-- =====================================================================
--  Elegancia a Medida — actualización: turnos, horarios y gastos por trabajo
--  NO borra datos. Agrega:
--    turnos_prueba.motivo         para qué es el turno (prueba, traer, retirar)
--    turnos_prueba.tomar_medidas  si se aprovecha para tomarle las medidas
--    turnos_prueba.origen         quién eligió el horario (modista o clienta)
--    turnos_prueba.resultado      al retirar: se lo llevó o hubo que retocar
--    horarios_recepcion           horarios en que la modista recibe prendas
--    dias_sin_atencion            días en que no atiende
--    etapa 5 «Prueba final» pasa a llamarse «Lista para retirar»
--    gastos.id_solicitudes_presupuesto  para qué trabajo fue el gasto (opcional)
--    turnos para que le tomen las medidas a una clienta (sin trabajo)
--    tela sugerida y materiales del presupuesto (y quién los consigue)
--  Cómo: phpMyAdmin → clic en tu base (elegancia_a_medida) → pestaña SQL
--  → pegá todo esto → Continuar. Si ya corriste una versión anterior de
--  este archivo, no pasa nada: lo que ya existe se saltea.
-- =====================================================================
ALTER TABLE `turnos_prueba`
  ADD COLUMN IF NOT EXISTS `motivo` ENUM('prueba','traer','retirar') NOT NULL DEFAULT 'prueba'
      COMMENT 'prueba = probarse, traer = trae la prenda (arreglos), retirar = se prueba y retira'
      AFTER `fecha_hora`,
  ADD COLUMN IF NOT EXISTS `tomar_medidas` TINYINT(1) NOT NULL DEFAULT 0
      COMMENT '1 = se aprovecha el turno para tomarle las medidas'
      AFTER `motivo`,
  ADD COLUMN IF NOT EXISTS `origen` ENUM('modista','clienta') NOT NULL DEFAULT 'modista'
      COMMENT 'Quién eligió el horario'
      AFTER `tomar_medidas`,
  ADD COLUMN IF NOT EXISTS `resultado` VARCHAR(20) NOT NULL DEFAULT ''
      COMMENT 'Turno para retirar: retiro (se lo llevó) o retoque (hubo que ajustar)'
      AFTER `estado`,
  COMMENT = 'Turnos del taller: pruebas, traer la prenda y retirar el trabajo';

CREATE TABLE IF NOT EXISTS `horarios_recepcion` (
  `id_horarios_recepcion` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dia_semana`            TINYINT UNSIGNED  NOT NULL,
  `hora`                  TIME              NOT NULL,
  PRIMARY KEY (`id_horarios_recepcion`),
  UNIQUE KEY `uq_horarios_recepcion` (`dia_semana`, `hora`),
  CONSTRAINT `chk_horarios_dia` CHECK (`dia_semana` <= 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Horarios de la semana en que la modista recibe prendas';

CREATE TABLE IF NOT EXISTS `dias_sin_atencion` (
  `fecha`  DATE         NOT NULL,
  `motivo` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'Solo lo ve la modista',
  PRIMARY KEY (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Días en que la modista no atiende';

-- Horarios de ejemplo, solo si la tabla está vacía
INSERT INTO `horarios_recepcion` (`dia_semana`, `hora`)
SELECT x.dia, x.hora FROM (
    SELECT d.dia, h.hora
      FROM (SELECT 1 AS dia UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) d
     CROSS JOIN (SELECT '10:00:00' AS hora UNION SELECT '10:30:00' UNION SELECT '11:00:00' UNION SELECT '11:30:00'
                 UNION SELECT '17:00:00' UNION SELECT '17:30:00' UNION SELECT '18:00:00' UNION SELECT '18:30:00') h
    UNION ALL
    SELECT 6, h.hora FROM (SELECT '10:00:00' AS hora UNION SELECT '10:30:00' UNION SELECT '11:00:00' UNION SELECT '11:30:00') h
) x
WHERE NOT EXISTS (SELECT 1 FROM `horarios_recepcion`);

-- La etapa 5 es cuando la prenda está terminada y la clienta elige cuándo retirarla
UPDATE `etapas_taller`
   SET `nombre` = 'Lista para retirar',
       `texto_cliente` = '¡Terminamos tu prenda! Elegí cuándo venir a probártela y retirarla.'
 WHERE `id_etapas_taller` = 5;

-- Gastos de un trabajo (tela, avíos… de una prenda en particular)
ALTER TABLE `gastos`
  ADD COLUMN IF NOT EXISTS `id_solicitudes_presupuesto` INT UNSIGNED NULL DEFAULT NULL
      COMMENT 'Trabajo para el que se compró (NULL = gasto general, ej. la luz)'
      AFTER `id_categorias_gasto`,
  ADD INDEX IF NOT EXISTS `idx_gastos_solicitud` (`id_solicitudes_presupuesto`),
  ADD CONSTRAINT `fk_gastos_solicitud` FOREIGN KEY IF NOT EXISTS (`id_solicitudes_presupuesto`)
      REFERENCES `solicitudes_presupuesto` (`id_solicitudes_presupuesto`)
      ON UPDATE CASCADE ON DELETE SET NULL;

-- Turnos solo para tomar medidas (la clienta no sabe medirse)
ALTER TABLE `turnos_prueba`
  MODIFY `id_solicitudes_presupuesto` INT UNSIGNED NULL DEFAULT NULL
      COMMENT 'Trabajo del turno (NULL en un turno solo para tomar medidas)',
  ADD COLUMN IF NOT EXISTS `id_clientes` INT UNSIGNED NULL DEFAULT NULL
      COMMENT 'Clienta del turno para tomar medidas (sin trabajo)'
      AFTER `id_solicitudes_presupuesto`,
  MODIFY `motivo` ENUM('prueba','traer','retirar','medidas') NOT NULL DEFAULT 'prueba'
      COMMENT 'prueba = probarse, traer = trae la prenda, retirar = se prueba y retira, medidas = solo tomar medidas',
  ADD INDEX IF NOT EXISTS `idx_turnos_prueba_cliente` (`id_clientes`),
  ADD CONSTRAINT `fk_turnos_prueba_cliente` FOREIGN KEY IF NOT EXISTS (`id_clientes`)
      REFERENCES `clientes` (`id_clientes`) ON UPDATE CASCADE ON DELETE CASCADE,
  COMMENT = 'Turnos del taller: traer la prenda, pruebas, retirar y tomar medidas';

-- Tela sugerida y materiales de cada presupuesto
ALTER TABLE `presupuestos`
  ADD COLUMN IF NOT EXISTS `nota_materiales` VARCHAR(255) NOT NULL DEFAULT ''
      COMMENT 'Por qué sugiere esa tela o esos materiales' AFTER `observaciones`,
  ADD COLUMN IF NOT EXISTS `materiales_quien` ENUM('','clienta','modista') NOT NULL DEFAULT ''
      COMMENT 'Quién consigue los materiales (lo elige la clienta al aceptar)' AFTER `nota_materiales`,
  ADD COLUMN IF NOT EXISTS `monto_materiales` DECIMAL(10,2) NOT NULL DEFAULT 0.00
      COMMENT 'Sumado al total si los compra la modista' AFTER `materiales_quien`;

CREATE TABLE IF NOT EXISTS `materiales_presupuesto` (
  `id_materiales_presupuesto` INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `id_presupuestos`           INT UNSIGNED     NOT NULL,
  `tipo`                      ENUM('tela','material') NOT NULL DEFAULT 'material',
  `descripcion`               VARCHAR(150)     NOT NULL COMMENT 'Ej: Satén azul, Cierre invisible 40 cm',
  `cantidad`                  VARCHAR(30)      NOT NULL DEFAULT '' COMMENT 'Ej: 3 m, 6 u.',
  `precio`                    DECIMAL(10,2)    NULL DEFAULT NULL COMMENT 'Costo aproximado si lo compra la modista; NULL = a confirmar',
  `orden`                     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_materiales_presupuesto`),
  KEY `idx_materiales_presupuesto` (`id_presupuestos`, `orden`),
  CONSTRAINT `fk_materiales_presupuesto`
      FOREIGN KEY (`id_presupuestos`) REFERENCES `presupuestos` (`id_presupuestos`)
      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `chk_materiales_precio` CHECK (`precio` IS NULL OR `precio` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Tela sugerida y materiales de cada presupuesto';
