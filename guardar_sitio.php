<?php
declare(strict_types=1);

/**
 * Guardar datos del sitio — Elegancia a Medida
 * Recibe POST de sitio.php y actualiza la única fila de datos_sitio.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

require_once __DIR__ . '/conexion.php';
$usuario = requerir_modista($pdo);

if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'El logo es demasiado grande para el servidor (máximo ' . (IMAGEN_MAX_BYTES / 1024 / 1024) . ' MB).');
    header('Location: sitio.php');
    exit;
}

if (!token_modista_valido()) {
    flash('error', 'La sesión expiró. Recargue la página e intente de nuevo.');
    header('Location: sitio.php');
    exit;
}

// campo => largo máximo (coincide con datos_sitio)
$LARGOS = [
    'nombre_modista' => 100, 'historia_titulo' => 100, 'historia_texto' => SITIO_HISTORIA_MAX,
    'temporada_titulo' => 60, 'telefono' => 30, 'whatsapp' => 30, 'email' => 150,
    'instagram' => 60, 'facebook' => 100, 'direccion' => 150, 'horarios' => 150,
];

$datos = [];
$errores = [];
foreach ($LARGOS as $campo => $max) {
    $valor = isset($_POST[$campo]) && is_string($_POST[$campo]) ? trim($_POST[$campo]) : '';
    // En la historia se respetan los saltos de línea; en el resto, una sola línea.
    $valor = $campo === 'historia_texto'
        ? str_replace("\r\n", "\n", $valor)
        : preg_replace('/\s+/', ' ', $valor);
    if (mb_strlen($valor) > $max) {
        $errores[] = "«{$campo}» puede tener hasta {$max} caracteres.";
    }
    $datos[$campo] = $valor;
}

if ($datos['historia_titulo'] === '') { $datos['historia_titulo'] = 'Nuestra historia'; }
if ($datos['temporada_titulo'] === '') { $datos['temporada_titulo'] = 'Temporada'; }

if ($datos['email'] !== '' && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'El email no tiene un formato válido.';
}
if ($datos['whatsapp'] !== '' && enlace_whatsapp($datos['whatsapp']) === null) {
    $errores[] = 'El WhatsApp tiene que tener al menos 8 números (con código de país, ej: 54 9 11 2345-6789).';
}
foreach (['telefono', 'whatsapp'] as $tel) {
    if ($datos[$tel] !== '' && !preg_match('/^[\d\s+\-()]+$/', $datos[$tel])) {
        $errores[] = 'El ' . ($tel === 'telefono' ? 'teléfono' : 'WhatsApp') . ' solo puede tener números, espacios, +, - y paréntesis.';
    }
}
if ($datos['instagram'] !== '') {
    $ig = usuario_instagram($datos['instagram']);
    if ($ig === '') {
        $errores[] = 'El usuario de Instagram solo puede tener letras, números, puntos y guiones bajos.';
    } else {
        $datos['instagram'] = $ig;   // se guarda limpio, sin @ ni dirección
    }
}

// Logo (opcional): se procesa solo si el resto está bien.
$logo_nuevo = null;
if (!$errores) {
    $r = guardar_imagen_subida($_FILES['logo'] ?? null, CARPETA_SITIO, 'logo');
    if (isset($r['error'])) { $errores[] = 'Logo: ' . $r['error']; } else { $logo_nuevo = $r['archivo']; }
}

if ($errores) {
    $_SESSION['form_sitio'] = ['errores' => $errores, 'datos' => $datos];
    header('Location: sitio.php');
    exit;
}

try {
    $logo_anterior = datos_sitio($pdo)['logo'] ?? null;
    $datos['logo'] = $logo_nuevo ?? (isset($_POST['quitar_logo']) ? null : $logo_anterior);

    $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($datos)));
    $params = [];
    foreach ($datos as $c => $val) { $params[":$c"] = $val; }

    // La fila 1 siempre existe (la crea el .sql); por las dudas, se crea si falta.
    $pdo->exec("INSERT IGNORE INTO datos_sitio (id_datos_sitio, historia_texto) VALUES (1, '')");
    $pdo->prepare("UPDATE datos_sitio SET $set, fecha_actualizacion = NOW() WHERE id_datos_sitio = 1")->execute($params);

    if ($logo_anterior !== null && $logo_anterior !== $datos['logo']) {
        borrar_imagen(CARPETA_SITIO, $logo_anterior);
    }
    flash('ok', 'Se guardaron los datos del sitio.');
} catch (PDOException $e) {
    borrar_imagen(CARPETA_SITIO, $logo_nuevo);
    error_log('Error al guardar datos del sitio: ' . $e->getMessage());
    flash('error', 'No se pudieron guardar los datos. Intente nuevamente.');
}
header('Location: sitio.php');
exit;
