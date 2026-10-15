<?php
declare(strict_types=1);

/**
 * Cambio de estado de una solicitud — Elegancia a Medida
 *
 * Recibe POST desde modista.php (Rechazar, Cliente aceptó, Completar,
 * Cancelar, Reabrir), valida el token del panel y que el cambio esté
 * permitido en TRANSICIONES (config.php), actualiza
 * solicitudes_presupuesto.id_estados_solicitud y vuelve al panel.
 */

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

require_once __DIR__ . '/conexion.php';


/** Guarda el mensaje y vuelve al panel conservando filtro y búsqueda. */
function volver_panel(string $tipo, string $mensaje): void
{
    flash($tipo, $mensaje);

    $estado   = isset($_POST['volver_estado'])   ? (int) $_POST['volver_estado']   : 0;
    $servicio = isset($_POST['volver_servicio']) ? (int) $_POST['volver_servicio'] : 0;
    $q        = isset($_POST['volver_q']) && is_string($_POST['volver_q']) ? trim($_POST['volver_q']) : '';

    if (!array_key_exists($estado, ESTADOS)) {
        $estado = 0;
    }
    // El servicio se vuelve a validar contra la tabla al cargar modista.php.
    if ($servicio < 0) {
        $servicio = 0;
    }

    header('Location: ' . url_panel(['estado' => $estado, 'servicio' => $servicio, 'q' => $q]));
    exit;
}


// ---------- TOKEN DEL PANEL (CSRF) ----------

if (!token_modista_valido()) {
    volver_panel('error', 'La sesión expiró. Recargue el panel e intente de nuevo.');
}


// ---------- DATOS ----------

$id           = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$estado_nuevo = filter_input(INPUT_POST, 'estado_nuevo', FILTER_VALIDATE_INT);

if (!$id || !$estado_nuevo || !array_key_exists($estado_nuevo, ESTADOS)) {
    volver_panel('error', 'Datos inválidos para el cambio de estado.');
}


// ---------- ACTUALIZACIÓN ----------

try {

    $stmt = $pdo->prepare('SELECT id_estados_solicitud FROM solicitudes_presupuesto WHERE id_solicitudes_presupuesto = :id');
    $stmt->execute([':id' => $id]);
    $fila = $stmt->fetch();

    if (!$fila) {
        volver_panel('error', "La solicitud #$id no existe.");
    }

    $estado_actual = (int) $fila['id_estados_solicitud'];

    // Solo se permiten los cambios definidos en config.php.
    if (!isset(TRANSICIONES[$estado_actual][$estado_nuevo])) {
        volver_panel(
            'error',
            "La solicitud #$id está «" . (ESTADOS[$estado_actual] ?? 'Desconocido')
            . "» y no puede pasar a «" . ESTADOS[$estado_nuevo] . "»."
        );
    }

    // El WHERE incluye el estado actual: si otra pestaña ya lo cambió,
    // no se pisa ese cambio.
    $upd = $pdo->prepare(
        'UPDATE solicitudes_presupuesto
            SET id_estados_solicitud = :nuevo
          WHERE id_solicitudes_presupuesto = :id AND id_estados_solicitud = :actual'
    );
    $upd->execute([
        ':nuevo'  => $estado_nuevo,
        ':id'     => $id,
        ':actual' => $estado_actual,
    ]);

    if ($upd->rowCount() === 0) {
        volver_panel('error', "La solicitud #$id cambió mientras tanto. Revise su estado actual.");
    }

    volver_panel('ok', "Solicitud #$id: " . ESTADOS[$estado_actual] . ' → ' . ESTADOS[$estado_nuevo] . '.');

} catch (PDOException $e) {

    error_log('Error al actualizar estado: ' . $e->getMessage());
    volver_panel('error', 'No se pudo actualizar el estado. Intente nuevamente.');
}