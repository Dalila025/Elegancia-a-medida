<?php
declare(strict_types=1);

/**
 * Configuración general — Elegancia a Medida
 *
 * Centraliza la zona horaria y las reglas de validación para que el
 * formulario (index.php) y el procesador (procesar_presupuesto.php)
 * usen exactamente los mismos límites.
 *
 * Corrige: P03 (longitudes inconsistentes) y P04 (zona horaria).
 */

// Una sola zona horaria para toda la aplicación.
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Longitudes coherentes con la tabla solicitudes_presupuesto
const NOMBRE_MAX       = 100;   // varchar(100)
const TELEFONO_MAX     = 30;    // varchar(30)
const TELEFONO_MIN_DIG = 10;    // mínimo de dígitos reales
const PRENDA_MAX       = 100;   // varchar(100)
const DESC_MIN         = 10;
const DESC_MAX         = 1000;  // límite propio: la columna es TEXT

// Única definición de los servicios válidos (antes estaba duplicada)
const SERVICIOS_VALIDOS = [
    'Confección',
    'Arreglos',
    'Diseño personalizado',
    'Otro',
];