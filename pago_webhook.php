<?php
declare(strict_types=1);

/**
 * Aviso automático de Mercado Pago (webhook) — Elegancia a Medida
 *
 * Mercado Pago llama a esta página cuando cambia un pago, aunque el cliente
 * no vuelva al sitio. Solo funciona con el sitio publicado en internet
 * (no en localhost). Igual que en pago_resultado.php, el aviso no se cree:
 * se consulta el pago a Mercado Pago con el Access Token.
 */

require_once __DIR__ . '/funciones_pagos.php';

// Mercado Pago manda el número de pago en el cuerpo JSON o en la dirección.
$cuerpo = json_decode((string) file_get_contents('php://input'), true) ?: [];
$tipo   = (string) ($cuerpo['type'] ?? $_GET['type'] ?? $_GET['topic'] ?? '');
$id     = (string) ($cuerpo['data']['id'] ?? $_GET['data_id'] ?? $_GET['id'] ?? '');

http_response_code(200);   // siempre 200: si no, Mercado Pago reintenta muchas veces

if ($tipo !== 'payment' || !preg_match('/^\d{1,20}$/', $id) || !mp_configurado()) {
    exit;
}

try {
    require_once __DIR__ . '/conexion.php';
    if ($mp = mp_obtener_pago($id)) {
        aplicar_pago_mp($pdo, $mp);
    }
} catch (Throwable $e) {
    error_log('Error en el webhook de Mercado Pago: ' . $e->getMessage());
}
