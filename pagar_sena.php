<?php
declare(strict_types=1);

/**
 * Pagar la seña — Elegancia a Medida
 *
 * Recibe POST de mis_pedidos.php o seguimiento.php: tipo (pedido|solicitud) y codigo.
 * Comprueba que sea del cliente y que corresponda pagar seña, crea el pago
 * pendiente (tabla pagos) y manda al cliente a:
 *   - Mercado Pago (MP_MODO = 'mercadopago'), o
 *   - pago_simulado.php (modo simulado, para la demo).
 */

require_once __DIR__ . '/funciones_pagos.php';
iniciar_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

require_once __DIR__ . '/conexion.php';
$cliente = requerir_cliente($pdo, 'Iniciá sesión para pagar la seña.');

$tipo   = isset($_POST['tipo']) && is_string($_POST['tipo']) ? $_POST['tipo'] : '';
$codigo = normalizar_codigo(isset($_POST['codigo']) && is_string($_POST['codigo']) ? $_POST['codigo'] : '');
$lista  = $tipo === 'solicitud' ? 'mis_solicitudes.php' : 'mis_pedidos.php';

function ir(string $url, string $tipo, string $mensaje): void
{
    flash_cliente($tipo, $mensaje);
    header('Location: ' . $url);
    exit;
}

if (!token_cliente_valido()) {
    ir($lista, 'error', 'La sesión expiró. Volvé a intentarlo.');
}

try {
    $obj = objetivo_sena($pdo, $tipo, $codigo, (int) $cliente['id_clientes']);
    if (!$obj) {
        ir($lista, 'error', 'Ese pedido no está en tu cuenta o todavía no corresponde pagar la seña.');
    }
    if ($obj['total'] === null) {
        ir($obj['volver'], 'info', 'La modista todavía tiene que indicar el total para calcular la seña.');
    }
    $sena = estado_sena($pdo, $obj['tipo'], $obj['id'], $obj['total']);
    if ($sena['cubierta']) {
        ir($obj['volver'], 'info', 'La seña ya está pagada. ¡Gracias!');
    }

    if (MP_MODO === 'mercadopago') {
        if (!mp_configurado()) {
            ir($obj['volver'], 'error', 'El pago con Mercado Pago todavía no está configurado. Avisale a la modista.');
        }
        $pago = crear_pago_pendiente($pdo, $obj, (float) $sena['falta'], 'mercadopago');
        $pref = mp_crear_preferencia($obj, $pago, $cliente);
        if (!$pref['ok']) {
            ir($obj['volver'], 'error', 'No pudimos abrir Mercado Pago (' . $pref['error'] . '). Probá de nuevo en unos minutos.');
        }
        $pdo->prepare('UPDATE pagos SET mp_preference_id = :p WHERE id_pagos = :id')
            ->execute([':p' => $pref['preference_id'], ':id' => $pago['id_pagos']]);
        header('Location: ' . $pref['url']);   // página de pago de Mercado Pago
        exit;
    }

    // Modo simulado
    $pago = crear_pago_pendiente($pdo, $obj, (float) $sena['falta'], 'simulado');
    header('Location: pago_simulado.php?ref=' . rawurlencode($pago['referencia']));
    exit;

} catch (PDOException $e) {
    error_log('Error al iniciar el pago de la seña: ' . $e->getMessage());
    ir($lista, 'error', 'No se pudo iniciar el pago. Intentá nuevamente.');
}
