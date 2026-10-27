<?php
declare(strict_types=1);

/**
 * Cambio de estado de un pedido (panel) — Elegancia a Medida
 * Recibe POST de pedidos.php: id, estado_nuevo y una nota opcional para el cliente.
 * Solo permite los cambios de TRANSICIONES_PEDIDO (config.php) y avisa al cliente.
 * Tabla: pedidos.
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

$filtro = filter_input(INPUT_POST, 'volver_estado', FILTER_VALIDATE_INT) ?: 0;
$volver = 'pedidos.php' . (isset(ESTADOS_PEDIDO[$filtro]) ? '?estado=' . $filtro : '');

function volver(string $url, string $tipo, string $mensaje): void
{
    flash($tipo, $mensaje);
    header('Location: ' . $url);
    exit;
}

if (!token_modista_valido()) {
    volver($volver, 'error', 'La sesión expiró. Recargue la página e intente de nuevo.');
}

$id    = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$nuevo = filter_input(INPUT_POST, 'estado_nuevo', FILTER_VALIDATE_INT) ?: 0;
$nota  = isset($_POST['nota']) && is_string($_POST['nota']) ? trim(preg_replace('/\s+/', ' ', $_POST['nota'])) : '';

if (mb_strlen($nota) > 500) {
    volver($volver, 'error', 'La nota puede tener hasta 500 caracteres.');
}

try {
    $stmt = $pdo->prepare('SELECT codigo, id_estados_pedido FROM pedidos WHERE id_pedidos = :id');
    $stmt->execute([':id' => $id]);
    $p = $stmt->fetch();
    if (!$p) {
        volver($volver, 'error', 'El pedido no existe.');
    }
    $actual = (int) $p['id_estados_pedido'];

    if (!isset(transiciones_pedido($actual, pedido_tiene_alquiler($pdo, $id))[$nuevo])) {
        volver($volver, 'error', 'El pedido está «' . (ESTADOS_PEDIDO[$actual] ?? '?') . '» y no puede pasar a «' . (ESTADOS_PEDIDO[$nuevo] ?? '?') . '».');
    }

    // El WHERE con el estado actual evita pisar un cambio hecho desde otra pestaña
    // (o una cancelación del cliente justo en ese momento).
    $upd = $pdo->prepare('UPDATE pedidos SET id_estados_pedido = :n, nota_modista = :nota, fecha_actualizacion = NOW()
                           WHERE id_pedidos = :id AND id_estados_pedido = :a');
    $upd->execute([':n' => $nuevo, ':nota' => $nota, ':id' => $id, ':a' => $actual]);
    if ($upd->rowCount() === 0) {
        volver($volver, 'error', 'El pedido cambió mientras tanto (quizás el cliente lo canceló). Revisalo de nuevo.');
    }

    notificar_pedido($pdo, $id, $nuevo);
    volver($volver, 'ok', 'Pedido ' . formatear_codigo($p['codigo']) . ': ' . ESTADOS_PEDIDO[$actual] . ' → ' . ESTADOS_PEDIDO[$nuevo] . '. Le avisamos al cliente.');

} catch (PDOException $e) {
    error_log('Error al actualizar pedido: ' . $e->getMessage());
    volver($volver, 'error', 'No se pudo actualizar el pedido. Intente nuevamente.');
}
