<?php
declare(strict_types=1);

/**
 * Guarda los horarios para recibir prendas (panel) — Elegancia a Medida
 * Recibe POST de horarios.php con accion =
 *   semana   h[] = ['1|10:00', …] → guardar_horarios()
 *   cerrar   fecha, motivo → agrega un día sin atención
 *   abrir    fecha → lo quita
 * Tablas: horarios_recepcion, dias_sin_atencion (funciones_taller.php).
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
    header('Location: horarios.php');
    exit;
}

if (!token_modista_valido()) {
    volver('error', 'La sesión expiró. Recargue la página e intente de nuevo.');
}

$campo  = fn(string $n): string => isset($_POST[$n]) && is_string($_POST[$n]) ? trim(preg_replace('/\s+/', ' ', $_POST[$n])) : '';
$accion = $campo('accion');

try {
    if ($accion === 'semana') {
        $marcados = isset($_POST['h']) && is_array($_POST['h']) ? $_POST['h'] : [];
        $n = guardar_horarios($pdo, $marcados);
        volver('ok', $n ? "Se guardaron los horarios ($n por semana)." : 'Guardado: no marcaste ningún horario, así que las clientas no van a poder elegir turno.');
    }
    if ($accion === 'cerrar' || $accion === 'abrir') {
        $fecha = $campo('fecha');
        $f = DateTime::createFromFormat('!Y-m-d', $fecha);
        if (!$f || $f->format('Y-m-d') !== $fecha) {
            volver('error', 'Indicá el día.');
        }
        if ($accion === 'abrir') {
            $pdo->prepare('DELETE FROM dias_sin_atencion WHERE fecha = :f')->execute([':f' => $fecha]);
            volver('ok', 'El ' . texto_dia($fecha) . ' vuelve a tener horarios.');
        }
        if ($fecha < date('Y-m-d')) {
            volver('error', 'Ese día ya pasó.');
        }
        $motivo = $campo('motivo');
        if (mb_strlen($motivo) > DIA_SIN_ATENCION_MOTIVO_MAX) {
            volver('error', 'El motivo puede tener hasta ' . DIA_SIN_ATENCION_MOTIVO_MAX . ' caracteres.');
        }
        $pdo->prepare('INSERT INTO dias_sin_atencion (fecha, motivo) VALUES (:f, :m) ON DUPLICATE KEY UPDATE motivo = VALUES(motivo)')
            ->execute([':f' => $fecha, ':m' => $motivo]);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM turnos_prueba WHERE estado = 'programado' AND DATE(fecha_hora) = :f");
        $stmt->execute([':f' => $fecha]);
        $hay = (int) $stmt->fetchColumn();
        volver('ok', 'El ' . texto_dia($fecha) . ' no se ofrecen horarios.'
                     . ($hay ? " Ojo: ese día ya tenés $hay turno(s) agendado(s); si no vas a estar, cancelalos desde el Taller." : ''));
    }
    volver('error', 'Acción inválida.');
} catch (PDOException $e) {
    error_log('Error al guardar horarios: ' . $e->getMessage());
    volver('error', 'No se pudo guardar. Intente nuevamente.');
}
