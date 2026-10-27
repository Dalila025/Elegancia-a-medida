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
 *  P04 — zona horaria definida en config.php (compartida con solicitud.php)
 *  P06 — límite máximo de caracteres en la descripción
 *  P07 — token de un solo uso contra el reenvío duplicado
 *
 * Agregado: límite máximo para fecha_requerida (FECHA_MAX_DIAS en config.php).
 * Agregado: código de seguimiento para que el cliente consulte la respuesta.
 * Agregado: solo clientes con cuenta. Nombre y teléfono salen de la cuenta
 *           (tabla clientes) y la solicitud queda a su nombre (id_clientes).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';

iniciar_sesion();

// Solo se acepta POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

// Conexión a la base de datos (una sola inclusión).
require_once __DIR__ . '/conexion.php';

// Solo clientes con sesión iniciada. Los datos personales salen de su cuenta.
$cliente = cliente_logueado($pdo);
if ($cliente === null) {
    flash_cliente('info', 'Tu sesión terminó. Ingresá de nuevo para enviar la solicitud.');
    header('Location: login.php?volver=solicitud.php');
    exit;
}


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

    header('Location: solicitud.php?' . $query);
    exit;
}


// =====================================================
// RECUPERAR Y LIMPIAR DATOS
// =====================================================

$nombre          = $cliente['nombre'];
$telefono        = $cliente['telefono'];
$id_tipos_servicio = campo('id_tipos_servicio');
$tipo_prenda     = campo('tipo_prenda');
$descripcion     = campo('descripcion');
$fecha_requerida = campo('fecha_requerida');
$id_productos    = campo('id_productos');   // opcional: prenda del catálogo

$datos = [
    'id_tipos_servicio' => $id_tipos_servicio,
    'tipo_prenda'     => $tipo_prenda,
    'descripcion'     => $descripcion,
    'fecha_requerida' => $fecha_requerida,
    'id_productos'    => $id_productos,
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

// Nombre y teléfono ya se validaron al crear la cuenta (registro.php).


// ---------- TIPO DE SERVICIO ----------

// Se valida contra la tabla tipos_servicio (solo los activos).
if ($id_tipos_servicio === '') {

    $errores[] = 'Debe seleccionar un tipo de servicio.';

} elseif (!ctype_digit($id_tipos_servicio)
          || !array_key_exists((int) $id_tipos_servicio, obtener_tipos_servicio($pdo))) {

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

    } elseif ($fecha > fecha_maxima()) {

        $errores[] = 'La fecha requerida no puede superar los ' . FECHA_MAX_DIAS
            . ' días (máximo ' . fecha_maxima()->format('d/m/Y') . ').';
    }
}


// ---------- PRENDA DE REFERENCIA (opcional) ----------

// Si vino desde el catálogo, se verifica que el producto exista y esté visible.
$id_productos_ok = null;
if ($id_productos !== '') {
    $stmt = $pdo->prepare('SELECT p.id_productos FROM productos p
                             INNER JOIN categorias c ON c.id_categorias = p.id_categorias
                            WHERE p.id_productos = :id AND p.activo = 1 AND c.activo = 1');
    if (ctype_digit($id_productos) && $stmt->execute([':id' => (int) $id_productos]) && $stmt->fetchColumn()) {
        $id_productos_ok = (int) $id_productos;
    } else {
        $errores[] = 'La prenda del catálogo elegida ya no está disponible. Quitala y describí lo que necesitás.';
        $datos['id_productos'] = '';
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

    // Código con el que el cliente consulta su solicitud (seguimiento.php).
    $codigo = generar_codigo_seguimiento($pdo);

    $sql = "
        INSERT INTO solicitudes_presupuesto
        (
            id_clientes,
            id_productos,
            codigo_seguimiento,
            nombre,
            telefono,
            id_tipos_servicio,
            tipo_prenda,
            descripcion,
            fecha_requerida,
            id_estados_solicitud
        )
        VALUES
        (
            :id_clientes,
            :id_productos,
            :codigo_seguimiento,
            :nombre,
            :telefono,
            :id_tipos_servicio,
            :tipo_prenda,
            :descripcion,
            :fecha_requerida,
            :id_estados_solicitud
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':id_clientes'     => $cliente['id_clientes'],
        ':id_productos'    => $id_productos_ok,
        ':codigo_seguimiento' => $codigo,
        ':nombre'          => $nombre,
        ':telefono'        => $telefono,
        ':id_tipos_servicio' => (int) $id_tipos_servicio,
        ':tipo_prenda'     => $tipo_prenda,
        ':descripcion'     => $descripcion,
        ':fecha_requerida' => $fecha_requerida,
        ':id_estados_solicitud' => ESTADO_PENDIENTE,
    ]);

    // El token se consume: un segundo envío del mismo formulario no inserta de nuevo.
    unset($_SESSION['token_formulario']);

    flash_cliente('ok', '¡Solicitud enviada! Te avisamos acá cuando la modista responda.');
    header('Location: seguimiento.php?codigo=' . $codigo);
    exit;

} catch (PDOException $e) {

    // El detalle va al log; el usuario recibe un mensaje genérico.
    error_log('Error al insertar solicitud: ' . $e->getMessage());

    volver_con(
        ['Error al guardar la solicitud. Intente nuevamente más tarde.'],
        $datos
    );
}