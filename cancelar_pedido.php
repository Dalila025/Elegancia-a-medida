<?php
declare(strict_types=1);

/**
 * Cancelar un pedido (cliente) — Elegancia a Medida
 * Solo el dueño, y solo mientras está Pendiente (la modista todavía no lo confirmó).
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

$cliente = cliente_logueado($pdo);
if ($cliente === null) {
    header('Location: login.php?volver=mis_pedidos.php');
    exit;
}

$codigo = normalizar_codigo(isset($_POST['codigo']) && is_string($_POST['codigo']) ? $_POST['codigo'] : '');

if (!token_cliente_valido() || $codigo === '') {
    flash_cliente('error', 'No se pudo cancelar el pedido. Probá de nuevo.');
    header('Location: mis_pedidos.php');
    exit;
}

try {
    // El WHERE asegura que sea del cliente y que siga pendiente.
    $stmt = $pdo->prepare('UPDATE pedidos SET id_estados_pedido = :cancelado, fecha_actualizacion = NOW()
                            WHERE codigo = :cod AND id_clientes = :c AND id_estados_pedido = :pendiente');
    $stmt->execute([':cancelado' => PEDIDO_CANCELADO, ':cod' => $codigo,
                    ':c' => $cliente['id_clientes'], ':pendiente' => PEDIDO_PENDIENTE]);

    flash_cliente(
        $stmt->rowCount() ? 'ok' : 'error',
        $stmt->rowCount() ? 'Cancelaste el pedido ' . formatear_codigo($codigo) . '.'
                          : 'Ese pedido ya no se puede cancelar desde acá: comunicate con nosotros.'
    );
} catch (PDOException $e) {
    error_log('Error al cancelar pedido: ' . $e->getMessage());
    flash_cliente('error', 'No se pudo cancelar el pedido. Probá de nuevo.');
}
header('Location: mis_pedidos.php');
exit;
