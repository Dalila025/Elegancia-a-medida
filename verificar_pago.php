<?php
declare(strict_types=1);

/**
 * «Ya pagué, verificar» — Elegancia a Medida
 *
 * Por si el cliente pagó pero cerró la ventana antes de volver al sitio:
 * se busca en Mercado Pago el pago por su referencia y se actualiza.
 * Recibe POST: tipo (pedido|solicitud) y codigo.
 */

require_once __DIR__ . '/funciones_pagos.php';
iniciar_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

require_once __DIR__ . '/conexion.php';
$cliente = requerir_cliente($pdo, 'Iniciá sesión para verificar tu pago.');

$tipo   = isset($_POST['tipo']) && is_string($_POST['tipo']) ? $_POST['tipo'] : '';
$codigo = normalizar_codigo(isset($_POST['codigo']) && is_string($_POST['codigo']) ? $_POST['codigo'] : '');
$lista  = $tipo === 'solicitud' ? 'mis_solicitudes.php' : 'mis_pedidos.php';

if (!token_cliente_valido()) {
    flash_cliente('error', 'La sesión expiró. Volvé a intentarlo.');
    header('Location: ' . $lista);
    exit;
}

try {
    $obj = objetivo_sena($pdo, $tipo, $codigo, (int) $cliente['id_clientes']);
    if (!$obj) {
        flash_cliente('error', 'Ese pedido no está en tu cuenta.');
        header('Location: ' . $lista);
        exit;
    }
    if (!mp_configurado()) {
        flash_cliente('error', 'El pago con Mercado Pago no está configurado.');
        header('Location: ' . $obj['volver']);
        exit;
    }

    $col = $obj['tipo'] === 'pedido' ? 'id_pedidos' : 'id_presupuestos';
    $stmt = $pdo->prepare("SELECT referencia FROM pagos WHERE $col = :id AND metodo = 'mercadopago' AND estado = 'pendiente'
                            ORDER BY id_pagos DESC LIMIT 5");
    $stmt->execute([':id' => $obj['id']]);

    $resultado = 'pendiente';
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $ref) {
        $mp = mp_buscar_por_referencia($ref);
        if ($mp && ($pago = aplicar_pago_mp($pdo, $mp))) {
            $resultado = $pago['estado'];
            if ($resultado === 'aprobado') {
                break;
            }
        }
    }

    [$t, $m] = $resultado === 'pendiente'
        ? ['info', 'Mercado Pago todavía no nos informa el pago. Si recién pagaste, esperá unos minutos y volvé a verificar.']
        : mensaje_resultado_pago($resultado);
    flash_cliente($t, $m);
    header('Location: ' . $obj['volver']);
    exit;

} catch (PDOException $e) {
    error_log('Error al verificar pago: ' . $e->getMessage());
    flash_cliente('error', 'No se pudo verificar el pago. Intentá nuevamente.');
    header('Location: ' . $lista);
    exit;
}
