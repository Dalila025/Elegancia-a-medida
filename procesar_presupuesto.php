<?php
declare(strict_types=1);

/**
 * Procesamiento de solicitud de presupuesto — Elegancia a Medida
 *
 * Recibe POST, valida y registra en solicitudes_presupuesto.
 *
 * Correcciones aplicadas en TP9:
 *  P01 — una sola inclusión de conexion.php, con __DIR__
 *  P03 — longitud de teléfono coherente con el formulario y la BD (30)
 *  P04 — zona horaria definida en config.php (compartida con index.php)
 *  P06 — límite máximo de caracteres en la descripción
 *  P07 — token de un solo uso contra el reenvío duplicado
 */

session_start();

require_once __DIR__ . '/config.php';

// Solo se acepta POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

// Conexión a la base de datos (una sola inclusión).
require_once __DIR__ . '/conexion.php';


// =====================================================
// FUNCIONES AUXILIARES
// =====================================================

/** Devuelve el campo POST ya recortado, o cadena vacía. */
function campo(string $nombre): string
{
    return isset($_POST[$nombre]) && is_string($_POST[$nombre])
        ? trim($_POST[$nombre])
        : '';
}

/** Vuelve al formulario conservando los datos cargados. */
function volver_con(array $errores, array $datos): void
{
    $query = http_build_query(array_merge(
        ['error' => implode('|', $errores)],
        $datos
    ));

    header('Location: index.php?' . $query);
    exit;
}


// =====================================================
// RECUPERAR Y LIMPIAR DATOS
// =====================================================

$nombre          = campo('nombre');
$telefono        = campo('telefono');
$tipo_servicio   = campo('tipo_servicio');
$tipo_prenda     = campo('tipo_prenda');
$descripcion     = campo('descripcion');
$fecha_requerida = campo('fecha_requerida');

$datos = [
    'nombre'          => $nombre,
    'telefono'        => $telefono,
    'tipo_servicio'   => $tipo_servicio,
    'tipo_prenda'     => $tipo_prenda,
    'descripcion'     => $descripcion,
    'fecha_requerida' => $fecha_requerida,
];

$errores = [];


// =====================================================
// TOKEN DE ENVÍO (anti duplicado / origen del formulario)
// =====================================================

$token_enviado = campo('token');

if ($token_enviado === ''
    || empty($_SESSION['token_formulario'])
    || !hash_equals($_SESSION['token_formulario'], $token_enviado)) {

    volver_con(
        ['La solicitud expiró o ya fue enviada. Verifique la carga y vuelva a intentarlo.'],
        $datos
    );
}


// =====================================================
// VALIDACIONES
// =====================================================

// ---------- NOMBRE ----------

if ($nombre === '') {

    $errores[] = 'El nombre es obligatorio.';

} elseif (mb_strlen($nombre) > NOMBRE_MAX) {

    $errores[] = 'El nombre no puede superar los ' . NOMBRE_MAX . ' caracteres.';

} elseif (!preg_match("/^[\p{L}\s'.-]+$/u", $nombre)) {

    $errores[] = 'El nombre solo puede contener letras, espacios, puntos, guiones y apóstrofes.';

} elseif (!preg_match('/\p{L}/u', $nombre)) {

    // Evita nombres formados solo por signos, por ejemplo "--" o "..".
    $errores[] = 'El nombre debe contener al menos una letra.';
}


// ---------- TELÉFONO ----------

if ($telefono === '') {

    $errores[] = 'El teléfono es obligatorio.';

} elseif (mb_strlen($telefono) > TELEFONO_MAX) {

    $errores[] = 'El teléfono no puede superar los ' . TELEFONO_MAX . ' caracteres.';

} elseif (!preg_match('/^[\d\s+\-()]+$/', $telefono)) {

    $errores[] = 'El teléfono solo puede contener números, espacios, +, - y paréntesis.';

} else {

    // Se cuentan únicamente los dígitos reales.
    $digitos_telefono = preg_replace('/\D/', '', $telefono);

    if (strlen($digitos_telefono) < TELEFONO_MIN_DIG) {

        $errores[] = 'El teléfono debe contener al menos ' . TELEFONO_MIN_DIG . ' dígitos.';

    } elseif (strlen($digitos_telefono) > 15) {

        // Máximo del estándar E.164.
        $errores[] = 'El teléfono no puede contener más de 15 dígitos.';
    }
}


// ---------- TIPO DE SERVICIO ----------

if ($tipo_servicio === '') {

    $errores[] = 'Debe seleccionar un tipo de servicio.';

} elseif (!in_array($tipo_servicio, SERVICIOS_VALIDOS, true)) {

    $errores[] = 'El tipo de servicio seleccionado no es válido.';
}


// ---------- TIPO DE PRENDA ----------

if ($tipo_prenda === '') {

    $errores[] = 'El tipo de prenda es obligatorio.';

} elseif (mb_strlen($tipo_prenda) > PRENDA_MAX) {

    $errores[] = 'El tipo de prenda no puede superar los ' . PRENDA_MAX . ' caracteres.';
}


// ---------- DESCRIPCIÓN ----------

if ($descripcion === '') {

    $errores[] = 'La descripción es obligatoria.';

} elseif (mb_strlen($descripcion) < DESC_MIN) {

    $errores[] = 'La descripción debe tener al menos ' . DESC_MIN . ' caracteres.';

} elseif (mb_strlen($descripcion) > DESC_MAX) {

    $errores[] = 'La descripción no puede superar los ' . DESC_MAX . ' caracteres.';
}


// ---------- FECHA ----------

if ($fecha_requerida === '') {

    $errores[] = 'La fecha requerida es obligatoria.';

} else {

    $fecha = DateTime::createFromFormat('!Y-m-d', $fecha_requerida);
    $hoy   = new DateTime('today');

    if (!$fecha || $fecha->format('Y-m-d') !== $fecha_requerida) {

        $errores[] = 'La fecha requerida no tiene un formato válido.';

    } elseif ($fecha < $hoy) {

        $errores[] = 'La fecha requerida no puede ser anterior a hoy.';
    }
}


// =====================================================
// SI HAY ERRORES, VOLVER AL FORMULARIO
// =====================================================

if (!empty($errores)) {
    volver_con($errores, $datos);
}


// =====================================================
// INSERTAR SOLICITUD
// =====================================================

try {

    $sql = "
        INSERT INTO solicitudes_presupuesto
        (
            nombre,
            telefono,
            tipo_servicio,
            tipo_prenda,
            descripcion,
            fecha_requerida,
            estado
        )
        VALUES
        (
            :nombre,
            :telefono,
            :tipo_servicio,
            :tipo_prenda,
            :descripcion,
            :fecha_requerida,
            1
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':nombre'          => $nombre,
        ':telefono'        => $telefono,
        ':tipo_servicio'   => $tipo_servicio,
        ':tipo_prenda'     => $tipo_prenda,
        ':descripcion'     => $descripcion,
        ':fecha_requerida' => $fecha_requerida,
    ]);

    // El token se consume: un segundo envío del mismo formulario no inserta de nuevo.
    unset($_SESSION['token_formulario']);

    header('Location: index.php?ok=1');
    exit;

} catch (PDOException $e) {

    // El detalle va al log; el usuario recibe un mensaje genérico.
    error_log('Error al insertar solicitud: ' . $e->getMessage());

    volver_con(
        ['Error al guardar la solicitud. Intente nuevamente más tarde.'],
        $datos
    );
}