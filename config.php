<?php
declare(strict_types=1);

/**
 * Configuración general — Elegancia a Medida
 *
 * Centraliza la zona horaria y las reglas de validación para que el
 * formulario, el panel de la modista y los procesadores usen
 * exactamente los mismos límites.
 *
 * Corrige: P03 (longitudes inconsistentes) y P04 (zona horaria).
 */

// Una sola zona horaria para toda la aplicación.
date_default_timezone_set('America/Argentina/Buenos_Aires');


// =====================================================
// FORMULARIO DEL CLIENTE (tabla solicitudes_presupuesto)
// =====================================================

const NOMBRE_MAX       = 100;   // varchar(100)
const TELEFONO_MAX     = 30;    // varchar(30)
const TELEFONO_MIN_DIG = 10;    // mínimo de dígitos reales
const PRENDA_MAX       = 100;   // varchar(100)
const DESC_MIN         = 10;
const DESC_MAX         = 1000;  // límite propio: la columna es TEXT

// Rango permitido para fecha_requerida: desde hoy hasta hoy + FECHA_MAX_DIAS.
const FECHA_MAX_DIAS   = 180;   // ~6 meses

// Los tipos de servicio ya NO están acá: se leen de la tabla tipos_servicio
// (ver obtener_tipos_servicio() en funciones.php).


// =====================================================
// ESTADOS (tabla estados_solicitud)
// Los números DEBEN coincidir con los id de estados_solicitud.
// =====================================================

const ESTADO_PENDIENTE     = 1;
const ESTADO_EN_PROCESO    = 2;
const ESTADO_COMPLETADO    = 3;
const ESTADO_CANCELADO     = 4;
const ESTADO_RECHAZADO     = 5;
const ESTADO_PRESUPUESTADO = 6;

// Ordenados según el recorrido de una solicitud (así se muestran en el panel).
const ESTADOS = [
    ESTADO_PENDIENTE     => 'Pendiente',
    ESTADO_PRESUPUESTADO => 'Presupuestado',
    ESTADO_EN_PROCESO    => 'En proceso',
    ESTADO_COMPLETADO    => 'Completado',
    ESTADO_CANCELADO     => 'Cancelado',
    ESTADO_RECHAZADO     => 'Rechazado',
];

// Cambios de estado con un botón del panel (actualizar_estado.php).
// estado actual => [estado nuevo => texto del botón]
// Pasar a "Presupuestado" NO está aquí: se hace cargando el presupuesto
// (presupuesto.php → guardar_presupuesto.php).
const TRANSICIONES = [
    ESTADO_PENDIENTE     => [ESTADO_RECHAZADO  => 'Rechazar'],
    ESTADO_PRESUPUESTADO => [ESTADO_EN_PROCESO => 'Cliente aceptó',
                             ESTADO_CANCELADO  => 'Cliente no aceptó'],
    ESTADO_EN_PROCESO    => [ESTADO_COMPLETADO => 'Completar',
                             ESTADO_CANCELADO  => 'Cancelar'],
    ESTADO_COMPLETADO    => [],
    ESTADO_CANCELADO     => [ESTADO_PENDIENTE  => 'Reabrir'],
    ESTADO_RECHAZADO     => [ESTADO_PENDIENTE  => 'Reabrir'],
];

// Estados en los que la modista puede cargar o corregir el presupuesto.
const ESTADOS_PRESUPUESTABLES = [ESTADO_PENDIENTE, ESTADO_PRESUPUESTADO];

// Pasar a estos estados pide confirmación en el panel.
const ESTADOS_CON_CONFIRMACION = [ESTADO_CANCELADO, ESTADO_RECHAZADO];


// =====================================================
// PRESUPUESTO DE LA MODISTA (tablas presupuestos y tarifas_plazo)
// =====================================================

const MONTO_MIN          = 1;
const MONTO_MAX          = 99999999.99;  // DECIMAL(10,2)
const PLAZO_MAX_DIAS     = 365;          // fecha de entrega: hasta 1 año
const OBSERVACIONES_MAX  = 500;          // varchar(500)

const TARIFA_NOMBRE_MAX  = 50;           // varchar(50)
const TARIFA_DIAS_MAX    = 365;
const RECARGO_MIN        = -50;          // coincide con el CHECK de la tabla
const RECARGO_MAX        = 300;
