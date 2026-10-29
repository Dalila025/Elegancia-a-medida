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
