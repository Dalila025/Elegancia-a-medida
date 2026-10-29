<?php
declare(strict_types=1);

/**
 * Guardar la lista de precios de arreglos (panel) — Elegancia a Medida
 * Recibe POST de lista_arreglos.php: accion = guardar (id 0 = nuevo) | alternar | eliminar.
 * Tabla: arreglos.
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

function volver(string $tipo, string $mensaje): void
{
    flash($tipo, $mensaje);
    header('Location: lista_arreglos.php');
    exit;
}

if (!token_modista_valido()) {
    volver('error', 'La sesión expiró. Recargue la página e intente de nuevo.');
}

$campo  = fn(string $n): string => isset($_POST[$n]) && is_string($_POST[$n]) ? trim(preg_replace('/\s+/', ' ', $_POST[$n])) : '';
$accion = $campo('accion');
$id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

try {
    if ($accion === 'alternar' && $id) {
        $stmt = $pdo->prepare('UPDATE arreglos SET activo = 1 - activo WHERE id_arreglos = :id');
        $stmt->execute([':id' => $id]);
        volver($stmt->rowCount() ? 'ok' : 'error', $stmt->rowCount() ? 'Listo: se cambió la visibilidad del arreglo.' : 'Ese arreglo ya no existe.');
    }
    if ($accion === 'eliminar' && $id) {
        $stmt = $pdo->prepare('DELETE FROM arreglos WHERE id_arreglos = :id');
        $stmt->execute([':id' => $id]);
        volver($stmt->rowCount() ? 'ok' : 'error', $stmt->rowCount() ? 'Se eliminó el arreglo de la lista.' : 'Ese arreglo ya no existe.');
    }
    if ($accion !== 'guardar') {
        volver('error', 'Acción inválida.');
    }

    $datos = [
        'nombre'       => $campo('nombre'),
        'descripcion'  => $campo('descripcion'),
        'precio'       => $campo('precio'),
        'precio_desde' => isset($_POST['precio_desde']) ? 1 : 0,
        'demora_dias'  => $campo('demora_dias'),
    ];
    $errores = [];
    if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) > ARREGLO_NOMBRE_MAX) {
        $errores[] = 'El nombre del arreglo es obligatorio (hasta ' . ARREGLO_NOMBRE_MAX . ' caracteres).';
    }
    if (mb_strlen($datos['descripcion']) > ARREGLO_DESCRIPCION_MAX) {
        $errores[] = 'La descripción puede tener hasta ' . ARREGLO_DESCRIPCION_MAX . ' caracteres.';
    }
    [$precio, $err] = leer_precio_opcional($datos['precio'], 'precio');
    if ($err) { $errores[] = $err; }
    $demora = null;
    if ($datos['demora_dias'] !== '') {
        if (!ctype_digit($datos['demora_dias']) || (int) $datos['demora_dias'] < 1 || (int) $datos['demora_dias'] > ARREGLO_DEMORA_MAX) {
            $errores[] = 'La demora tiene que ser un número de días entre 1 y ' . ARREGLO_DEMORA_MAX . '.';
        } else {
            $demora = (int) $datos['demora_dias'];
        }
    }
    if (!$errores) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM arreglos WHERE nombre = :n AND id_arreglos <> :id');
        $stmt->execute([':n' => $datos['nombre'], ':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errores[] = "Ya hay un arreglo llamado «{$datos['nombre']}».";
        }
    }
    if ($errores) {
        $_SESSION['form_arreglo'] = ['errores' => $errores, 'datos' => $id ? null : $datos];
        header('Location: lista_arreglos.php');
        exit;
    }

    $params = [':n' => $datos['nombre'], ':d' => $datos['descripcion'], ':p' => $precio,
               ':desde' => $precio === null ? 0 : $datos['precio_desde'], ':dem' => $demora];
    if ($id) {
        $params[':id'] = $id;
        $stmt = $pdo->prepare('UPDATE arreglos SET nombre = :n, descripcion = :d, precio = :p, precio_desde = :desde, demora_dias = :dem WHERE id_arreglos = :id');
        $stmt->execute($params);
        volver('ok', "Se guardó «{$datos['nombre']}».");
    }
    $pdo->prepare('INSERT INTO arreglos (nombre, descripcion, precio, precio_desde, demora_dias) VALUES (:n, :d, :p, :desde, :dem)')->execute($params);
    volver('ok', "Se agregó «{$datos['nombre']}» a la lista.");

} catch (PDOException $e) {
    error_log('Error en arreglos: ' . $e->getMessage());
    volver('error', 'No se pudo guardar. Intente nuevamente.');
}
