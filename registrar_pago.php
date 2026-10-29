<?php
declare(strict_types=1);

/**
 * Registrar una seña cobrada en efectivo o transferencia (panel) — Elegancia a Medida
 * Recibe POST de pagos.php: tipo (pedido|presupuesto), id, monto, metodo, observaciones.
 * Inserta el pago como aprobado y le avisa al cliente. Tabla: pagos.
 */

require_once __DIR__ . '/funciones_pagos.php';
iniciar_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

require_once __DIR__ . '/conexion.php';
$usuario = requerir_modista($pdo);

function volver_senas(string $tipo, string $mensaje): void
{
    flash($tipo, $mensaje);
    header('Location: pagos.php');
    exit;
}

if (!token_modista_valido()) {
    volver_senas('error', 'La sesión expiró. Recargue la página e intente de nuevo.');
}

$tipo   = ($_POST['tipo'] ?? '') === 'presupuesto' ? 'presupuesto' : 'pedido';
$id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$metodo = in_array($_POST['metodo'] ?? '', ['efectivo', 'transferencia'], true) ? $_POST['metodo'] : '';
$obs    = isset($_POST['observaciones']) && is_string($_POST['observaciones']) ? trim(preg_replace('/\s+/', ' ', $_POST['observaciones'])) : '';
[$monto, $error] = leer_precio_opcional(isset($_POST['monto']) && is_string($_POST['monto']) ? $_POST['monto'] : '', 'monto');

if (!$id || $metodo === '') {
    volver_senas('error', 'Datos inválidos.');
}
if ($error || $monto === null) {
    volver_senas('error', $error ?? 'Indicá el monto que cobraste.');
}
if (mb_strlen($obs) > PAGO_OBSERVACIONES_MAX) {
    volver_senas('error', 'La nota puede tener hasta ' . PAGO_OBSERVACIONES_MAX . ' caracteres.');
}

try {
    // ¿Existe y está en un estado que lleva seña? ¿Cuánto falta?
    if ($tipo === 'pedido') {
        $stmt = $pdo->prepare('SELECT id_estados_pedido AS estado, total_estimado, tiene_a_consultar FROM pedidos WHERE id_pedidos = :id');
        $stmt->execute([':id' => $id]);
        $o = $stmt->fetch();
        $valido = $o && in_array((int) $o['estado'], PEDIDO_ESTADOS_CON_SENA, true);
        $total  = $o ? total_pedido_para_sena($o) : null;
    } else {
        $stmt = $pdo->prepare('SELECT s.id_estados_solicitud AS estado, p.monto_total FROM presupuestos p
                                 INNER JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = p.id_solicitudes_presupuesto
                                WHERE p.id_presupuestos = :id');
        $stmt->execute([':id' => $id]);
        $o = $stmt->fetch();
        $valido = $o && in_array((int) $o['estado'], SOLICITUD_ESTADOS_CON_SENA, true);
        $total  = $o ? (float) $o['monto_total'] : null;
    }
    if (!$valido || $total === null) {
        volver_senas('error', 'A ese pedido no le corresponde registrar seña ahora.');
    }
    $sena = estado_sena($pdo, $tipo, $id, $total);
    if ((float) $monto > (float) $sena['falta'] + 0.001) {
        volver_senas('error', 'El monto supera lo que falta de la seña (' . pesos((float) $sena['falta']) . ').');
    }

    $col = $tipo === 'pedido' ? 'id_pedidos' : 'id_presupuestos';
    $ref = generar_referencia_pago();
    $pdo->prepare("INSERT INTO pagos ($col, concepto, monto, metodo, estado, referencia, observaciones, fecha_pago)
                   VALUES (:id, 'sena', :m, :met, 'aprobado', :ref, :obs, NOW())")
        ->execute([':id' => $id, ':m' => $monto, ':met' => $metodo, ':ref' => $ref, ':obs' => $obs]);

    notificar_pago_aprobado($pdo, obtener_pago_por_referencia($pdo, $ref));
    volver_senas('ok', 'Se registró la seña de ' . pesos((float) $monto) . ' (' . PAGO_METODOS[$metodo] . '). Le avisamos al cliente.');

} catch (PDOException $e) {
    error_log('Error al registrar pago manual: ' . $e->getMessage());
    volver_senas('error', 'No se pudo registrar el pago. Intente nuevamente.');
}
