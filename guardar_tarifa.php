<?php
declare(strict_types=1);

/**
 * Alta, modificación y baja de tarifas por plazo — Elegancia a Medida
 *
 * Recibe POST de tarifas.php con accion = crear | actualizar | eliminar.
 * Tabla: tarifas_plazo.
 */

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

require_once __DIR__ . '/conexion.php';


function volver(string $tipo, string $mensaje): void
{
    flash($tipo, $mensaje);
    header('Location: tarifas.php');
    exit;
}

function campo(string $nombre): string
{
    return isset($_POST[$nombre]) && is_string($_POST[$nombre]) ? trim($_POST[$nombre]) : '';
}


if (!token_modista_valido()) {
    volver('error', 'La sesión expiró. Recargue la página e intente de nuevo.');
}

$accion = campo('accion');
$id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!in_array($accion, ['crear', 'actualizar', 'eliminar'], true)
    || ($accion !== 'crear' && !$id)) {
    volver('error', 'Acción inválida.');
}

try {

    // ---------- ELIMINAR ----------
    if ($accion === 'eliminar') {
        // Los presupuestos que la usaban conservan su recargo (id_tarifas_plazo queda NULL).
        $stmt = $pdo->prepare('DELETE FROM tarifas_plazo WHERE id_tarifas_plazo = :id');
        $stmt->execute([':id' => $id]);
        volver($stmt->rowCount() ? 'ok' : 'error', $stmt->rowCount() ? 'Tarifa eliminada.' : 'La tarifa ya no existe.');
    }

    // ---------- VALIDAR (crear / actualizar) ----------
    $nombre  = campo('nombre');
    $dias    = campo('dias_hasta');
    $recargo = str_replace(',', '.', campo('recargo_porcentaje'));
    $errores = [];

    if ($nombre === '') {
        $errores[] = 'el nombre es obligatorio';
    } elseif (mb_strlen($nombre) > TARIFA_NOMBRE_MAX) {
        $errores[] = 'el nombre no puede superar ' . TARIFA_NOMBRE_MAX . ' caracteres';
    }

    $dias_hasta = null;   // vacío = sin límite
    if ($dias !== '') {
        if (!ctype_digit($dias) || (int) $dias > TARIFA_DIAS_MAX) {
            $errores[] = '«Hasta días» debe ser un número entero entre 0 y ' . TARIFA_DIAS_MAX . ' (o vacío)';
        } else {
            $dias_hasta = (int) $dias;
        }
    }

    if ($recargo === '' || !is_numeric($recargo)) {
        $errores[] = 'el recargo debe ser un número';
    } elseif ((float) $recargo < RECARGO_MIN || (float) $recargo > RECARGO_MAX) {
        $errores[] = 'el recargo debe estar entre ' . RECARGO_MIN . ' % y ' . RECARGO_MAX . ' %';
    }

    // Solo puede haber una tarifa "sin límite".
    if ($dias_hasta === null && !$errores) {
        $stmt = $pdo->prepare('SELECT id_tarifas_plazo FROM tarifas_plazo WHERE dias_hasta IS NULL AND id_tarifas_plazo <> :id');
        $stmt->execute([':id' => $id ?: 0]);
        if ($stmt->fetch()) {
            $errores[] = 'ya existe una tarifa sin límite de días; complete «Hasta días»';
        }
    }

    if ($errores) {
        volver('error', 'No se guardó: ' . implode('; ', $errores) . '.');
    }

    $params = [
        ':nombre'  => $nombre,
        ':dias'    => $dias_hasta,
        ':recargo' => number_format((float) $recargo, 2, '.', ''),
    ];

    if ($accion === 'crear') {
        $pdo->prepare('INSERT INTO tarifas_plazo (nombre, dias_hasta, recargo_porcentaje) VALUES (:nombre, :dias, :recargo)')
            ->execute($params);
        volver('ok', "Tarifa «{$nombre}» agregada.");
    }

    $params[':id'] = $id;
    $stmt = $pdo->prepare('UPDATE tarifas_plazo SET nombre = :nombre, dias_hasta = :dias, recargo_porcentaje = :recargo WHERE id_tarifas_plazo = :id');
    $stmt->execute($params);
    volver('ok', "Tarifa «{$nombre}» actualizada. Los presupuestos ya guardados no cambian.");

} catch (PDOException $e) {

    // 1062 = valor duplicado (dos tarifas con el mismo «Hasta días»)
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        volver('error', 'Ya hay otra tarifa con ese mismo «Hasta días».');
    }
    error_log('Error en tarifas_plazo: ' . $e->getMessage());
    volver('error', 'No se pudo guardar la tarifa. Intente nuevamente.');
}
