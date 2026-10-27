<?php
declare(strict_types=1);

/**
 * Guardar presupuesto — Elegancia a Medida
 *
 * Recibe POST de presupuesto.php, valida, calcula plazo, recargo y total
 * (el cálculo del navegador es solo una vista previa: acá se recalcula),
 * guarda en presupuestos, pasa la solicitud a "Presupuestado" y crea el
 * aviso para el cliente (tabla notificaciones).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

require_once __DIR__ . '/conexion.php';

// Solo la modista con sesión iniciada (panel_login.php).
$usuario = requerir_modista($pdo);


function campo(string $nombre): string
{
    return isset($_POST[$nombre]) && is_string($_POST[$nombre]) ? trim($_POST[$nombre]) : '';
}

/** Vuelve al formulario con los errores y lo que se había cargado. */
function volver_form(int $id, array $errores, array $datos): void
{
    $_SESSION['form_presupuesto'] = ['id' => $id, 'errores' => $errores, 'datos' => $datos];
    header('Location: presupuesto.php?id=' . $id);
    exit;
}


// ---------- TOKEN Y SOLICITUD ----------

$id = filter_input(INPUT_POST, 'id_solicitudes_presupuesto', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!token_modista_valido()) {
    flash('error', 'La sesión expiró. Vuelva a abrir el presupuesto e intente de nuevo.');
    header('Location: ' . ($id ? 'presupuesto.php?id=' . $id : 'modista.php'));
    exit;
}

if (!$id) {
    flash('error', 'Solicitud no indicada.');
    header('Location: modista.php');
    exit;
}

$monto_txt     = str_replace(',', '.', campo('monto_base'));
$fecha_entrega = campo('fecha_entrega');
$observaciones = campo('observaciones');

$datos = [
    'monto_base'    => campo('monto_base'),
    'fecha_entrega' => $fecha_entrega,
    'observaciones' => $observaciones,
];

$errores = [];


// ---------- VALIDACIONES ----------

// Monto base
if ($monto_txt === '') {
    $errores[] = 'El monto base es obligatorio.';
} elseif (!is_numeric($monto_txt) || !preg_match('/^\d+(\.\d{1,2})?$/', $monto_txt)) {
    $errores[] = 'El monto base debe ser un número con hasta 2 decimales (sin puntos de miles).';
} elseif ((float) $monto_txt < MONTO_MIN || (float) $monto_txt > MONTO_MAX) {
    $errores[] = 'El monto base debe estar entre ' . pesos(MONTO_MIN) . ' y ' . pesos(MONTO_MAX) . '.';
}

// Fecha de entrega
$plazo = null;
if ($fecha_entrega === '') {
    $errores[] = 'La fecha de entrega es obligatoria.';
} else {
    $f = DateTime::createFromFormat('!Y-m-d', $fecha_entrega);
    if (!$f || $f->format('Y-m-d') !== $fecha_entrega) {
        $errores[] = 'La fecha de entrega no tiene un formato válido.';
    } else {
        $plazo = dias_desde_hoy($f);
        if ($plazo < 0) {
            $errores[] = 'La fecha de entrega no puede ser anterior a hoy.';
        } elseif ($plazo > PLAZO_MAX_DIAS) {
            $errores[] = 'La fecha de entrega no puede superar los ' . PLAZO_MAX_DIAS . ' días.';
        }
    }
}

// Observaciones
if (mb_strlen($observaciones) > OBSERVACIONES_MAX) {
    $errores[] = 'Las observaciones no pueden superar los ' . OBSERVACIONES_MAX . ' caracteres.';
}

if ($errores) {
    volver_form($id, $errores, $datos);
}


// ---------- CÁLCULO Y GUARDADO ----------

try {

    $pdo->beginTransaction();

    // Bloquea la fila para que nadie cambie el estado mientras se guarda.
    $stmt = $pdo->prepare('SELECT id_estados_solicitud FROM solicitudes_presupuesto WHERE id_solicitudes_presupuesto = :id FOR UPDATE');
    $stmt->execute([':id' => $id]);
    $sol = $stmt->fetch();

    if (!$sol) {
        $pdo->rollBack();
        flash('error', "La solicitud #$id no existe.");
        header('Location: modista.php');
        exit;
    }

    $estado_actual = (int) $sol['id_estados_solicitud'];
    if (!in_array($estado_actual, ESTADOS_PRESUPUESTABLES, true)) {
        $pdo->rollBack();
        flash('error', "La solicitud #$id está «" . (ESTADOS[$estado_actual] ?? '?') . "» y ya no se puede presupuestar.");
        header('Location: modista.php');
        exit;
    }

    // ¿Ya tenía presupuesto? (para avisarle al cliente "actualizado" en vez de "listo")
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM presupuestos WHERE id_solicitudes_presupuesto = :id');
    $stmt->execute([':id' => $id]);
    $ya_existia = (int) $stmt->fetchColumn() > 0;

    $monto_base = round((float) $monto_txt, 2);
    $tarifa     = tarifa_para_plazo($pdo, $plazo);
    $recargo    = $tarifa ? (float) $tarifa['recargo_porcentaje'] : 0.0;
    $total      = calcular_total($monto_base, $recargo);

    // Una sola fila por solicitud (UNIQUE id_solicitudes_presupuesto): si existe, se actualiza.
    $sql = '
        INSERT INTO presupuestos
            (id_solicitudes_presupuesto, id_tarifas_plazo, monto_base, fecha_entrega, plazo_dias,
             recargo_porcentaje, monto_total, observaciones)
        VALUES
            (:id_solicitudes_presupuesto, :id_tarifas_plazo, :monto_base, :fecha_entrega, :plazo,
             :recargo, :total, :obs)
        ON DUPLICATE KEY UPDATE
            id_tarifas_plazo    = VALUES(id_tarifas_plazo),
            monto_base          = VALUES(monto_base),
            fecha_entrega       = VALUES(fecha_entrega),
            plazo_dias          = VALUES(plazo_dias),
            recargo_porcentaje  = VALUES(recargo_porcentaje),
            monto_total         = VALUES(monto_total),
            observaciones       = VALUES(observaciones),
            fecha_actualizacion = NOW(),
            -- presupuesto nuevo: la respuesta anterior del cliente ya no vale
            fecha_respuesta_cliente = NULL,
            comentario_cliente  = DEFAULT(comentario_cliente)
    ';
    $pdo->prepare($sql)->execute([
        ':id_solicitudes_presupuesto' => $id,
        ':id_tarifas_plazo' => $tarifa ? (int) $tarifa['id_tarifas_plazo'] : null,
        ':monto_base'    => number_format($monto_base, 2, '.', ''),
        ':fecha_entrega' => $fecha_entrega,
        ':plazo'         => $plazo,
        ':recargo'       => number_format($recargo, 2, '.', ''),
        ':total'         => number_format($total, 2, '.', ''),
        ':obs'           => $observaciones,
    ]);

    $pdo->prepare('UPDATE solicitudes_presupuesto SET id_estados_solicitud = :nuevo WHERE id_solicitudes_presupuesto = :id')
        ->execute([':nuevo' => ESTADO_PRESUPUESTADO, ':id' => $id]);

    $pdo->commit();

    // Aviso para el cliente (seguimiento.php). Si falla, no afecta lo guardado.
    notificar($pdo, $id, ESTADO_PRESUPUESTADO, $ya_existia);

    flash('ok', "Presupuesto de la solicitud #$id guardado: " . pesos($total)
        . ', entrega el ' . DateTime::createFromFormat('!Y-m-d', $fecha_entrega)->format('d/m/Y')
        . ($tarifa ? ' (tarifa ' . $tarifa['nombre'] . ', ' . porcentaje($recargo) . ')' : '') . '.');
    header('Location: modista.php');
    exit;

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error al guardar presupuesto: ' . $e->getMessage());
    volver_form($id, ['No se pudo guardar el presupuesto. Intente nuevamente.'], $datos);
}
