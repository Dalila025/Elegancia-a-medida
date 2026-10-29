<?php
declare(strict_types=1);

/**
 * Vuelta desde Mercado Pago — Elegancia a Medida
 *
 * Mercado Pago manda al cliente acá después de pagar, con datos en la dirección
 * (payment_id, status, external_reference…). Esos datos NO se creen: con el
 * payment_id se le pregunta a Mercado Pago (con el Access Token) cómo quedó el
 * pago, y recién ahí se actualiza la tabla pagos (ver aplicar_pago_mp()).
 */

require_once __DIR__ . '/funciones_pagos.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$ref        = isset($_GET['external_reference']) && is_string($_GET['external_reference']) ? $_GET['external_reference'] : '';
$payment_id = (string) ($_GET['payment_id'] ?? $_GET['collection_id'] ?? '');

try {
    $pago = preg_match('/^EAM-[0-9a-f]{20}$/', $ref) ? obtener_pago_por_referencia($pdo, $ref) : null;
    if (!$pago) {
        flash_cliente('error', 'No encontramos ese pago.');
        header('Location: index.php');
        exit;
    }

    if ($payment_id !== '' && $payment_id !== 'null' && mp_configurado()) {
        $mp = mp_obtener_pago($payment_id);
        if ($mp && ($mp['external_reference'] ?? '') === $ref) {
            $pago = aplicar_pago_mp($pdo, $mp) ?? $pago;
        }
        [$tipo, $mensaje] = mensaje_resultado_pago($pago['estado']);
    } else {
        // Volvió sin pagar (tocó "Volver al sitio" o cerró la operación).
        [$tipo, $mensaje] = ['info', 'No se completó el pago. Podés intentarlo de nuevo cuando quieras.'];
    }

    flash_cliente($tipo, $mensaje);
    header('Location: ' . destino_pago($pdo, $pago));
    exit;

} catch (PDOException $e) {
    error_log('Error al procesar la vuelta de Mercado Pago: ' . $e->getMessage());
    flash_cliente('error', 'No pudimos registrar el resultado del pago. Si pagaste, tocá «Ya pagué, verificar».');
    header('Location: mis_pedidos.php');
    exit;
}
