<?php
declare(strict_types=1);

/**
 * Acciones del taller (panel) — Elegancia a Medida
 * Recibe POST de taller.php con accion =
 *   etapa             id (solicitud), etapa, nota → cambiar_etapa()
 *   turno             id (solicitud), motivo, fecha, hora, medidas, nota → agendar_turno()
 *   turno_realizado   id_turno → cerrar_turno()  (vino; si había que tomarle medidas, va a su ficha)
 *   turno_cancelado   id_turno → cerrar_turno()
 *   turno_retiro      id_turno → cerrar_turno()  (se lo probó, quedó bien y se lo llevó)
 *   turno_retoque     id_turno, nota → cerrar_turno()  (hay que ajustar algo antes de retirar)
 * Tablas: avances_taller, turnos_prueba (funciones_taller.php).
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

function volver(string $tipo, string $mensaje, string $destino = 'taller.php'): void
{
    flash($tipo, $mensaje);
    header('Location: ' . $destino);
    exit;
}

if (!token_modista_valido()) {
    volver('error', 'La sesión expiró. Recargue la página e intente de nuevo.');
}

$campo  = fn(string $n): string => isset($_POST[$n]) && is_string($_POST[$n]) ? trim(preg_replace('/\s+/', ' ', $_POST[$n])) : '';
$accion = $campo('accion');
$id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$nota   = $campo('nota');
if (mb_strlen($nota) > TALLER_NOTA_MAX) {
    volver('error', 'La nota puede tener hasta ' . TALLER_NOTA_MAX . ' caracteres.');
}

try {
    if ($accion === 'etapa' && $id) {
        $etapa = filter_input(INPUT_POST, 'etapa', FILTER_VALIDATE_INT) ?: 0;
        [$ok, $msg] = cambiar_etapa($pdo, $id, $etapa, $nota);
        volver($ok ? 'ok' : 'error', $msg);
    }
    if ($accion === 'turno' && $id) {
        [$ok, $msg] = agendar_turno($pdo, $id, $campo('fecha'), $campo('hora'), $nota, $campo('motivo') ?: 'prueba', $campo('medidas') === '1');
        volver($ok ? 'ok' : 'error', $msg);
    }
    if (in_array($accion, ['turno_realizado', 'turno_cancelado', 'turno_retiro', 'turno_retoque'], true)) {
        $id_turno = filter_input(INPUT_POST, 'id_turno', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
        [$ok, $msg, $ir] = cerrar_turno($pdo, $id_turno, substr($accion, 6), $nota);
        // Vuelve a la página desde donde se tocó el botón (Solicitudes o Taller)
        $desde = in_array($campo('volver'), ['modista.php', 'taller.php'], true) ? $campo('volver') : 'taller.php';
        volver($ok ? 'ok' : 'error', $msg, $ok && $ir !== '' ? $ir : $desde);
    }
    volver('error', 'Acción inválida.');
} catch (PDOException $e) {
    error_log('Error en el taller: ' . $e->getMessage());
    volver('error', 'No se pudo guardar. Intente nuevamente.');
}
