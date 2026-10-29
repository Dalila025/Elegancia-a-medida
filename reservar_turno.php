<?php
declare(strict_types=1);

/**
 * La clienta elige cuándo acercarse al taller — Elegancia a Medida
 *
 * Después de aceptar el presupuesto, la clienta elige uno de los horarios
 * libres que cargó la modista (horarios.php) para llevar la prenda, probársela
 * y que le tomen las medidas. Si ya había elegido uno, lo cambia.
 * Recibe POST de seguimiento.php: codigo y turno ('Y-m-d H:i').
 * Tablas: turnos_prueba, horarios_recepcion, dias_sin_atencion, notificaciones.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

require_once __DIR__ . '/conexion.php';
$cliente = requerir_cliente($pdo, 'Iniciá sesión para elegir tu turno.');

$codigo = normalizar_codigo(isset($_POST['codigo']) && is_string($_POST['codigo']) ? $_POST['codigo'] : '');
$turno  = isset($_POST['turno']) && is_string($_POST['turno']) ? trim($_POST['turno']) : '';
$volver = $codigo !== '' ? 'seguimiento.php?codigo=' . rawurlencode($codigo) . '#turno' : 'mis_solicitudes.php';

if (!token_cliente_valido()) {
    flash_cliente('error', 'La sesión expiró. Volvé a intentarlo.');
    header('Location: ' . $volver);
    exit;
}
if ($turno === '') {
    flash_cliente('error', 'Elegí un día y un horario.');
    header('Location: ' . $volver);
    exit;
}

try {
    [$ok, $msg] = reservar_turno_cliente($pdo, $codigo, (int) $cliente['id_clientes'], $turno);
    flash_cliente($ok ? 'ok' : 'error', $msg);
} catch (PDOException $e) {
    error_log('Error al reservar turno: ' . $e->getMessage());
    flash_cliente('error', 'No se pudo reservar el turno. Intentá nuevamente.');
}
header('Location: ' . $volver);
exit;
