<?php
declare(strict_types=1);

/**
 * Pago simulado — Elegancia a Medida
 *
 * Solo existe en MODO SIMULADO (sin Mercado Pago configurado): reemplaza a la
 * página de Mercado Pago para poder mostrar el recorrido completo en la demo,
 * sin internet y sin mover dinero. Con MP_MODO = 'mercadopago' responde 404.
 *
 * GET  ?ref=EAM-…            → muestra el pago
 * POST ref + resultado        → lo aprueba o lo rechaza (tabla pagos)
 */

require_once __DIR__ . '/funciones_pagos.php';
iniciar_sesion();

if (MP_MODO === 'mercadopago') {
    http_response_code(404);
    die('Página no disponible.');
}

require_once __DIR__ . '/conexion.php';
$cliente = requerir_cliente($pdo, 'Iniciá sesión para pagar la seña.');

$ref  = (string) ($_POST['ref'] ?? $_GET['ref'] ?? '');
$pago = preg_match('/^EAM-[0-9a-f]{20}$/', $ref) ? obtener_pago_por_referencia($pdo, $ref) : null;

// Solo el dueño, y solo pagos simulados
if (!$pago || $pago['metodo'] !== 'simulado' || cliente_del_pago($pdo, $pago) !== (int) $cliente['id_clientes']) {
    flash_cliente('error', 'No encontramos ese pago en tu cuenta.');
    header('Location: mis_pedidos.php');
    exit;
}
$destino = destino_pago($pdo, $pago);

if ($pago['estado'] !== 'pendiente') {
    flash_cliente('info', $pago['estado'] === 'aprobado' ? 'Esta seña ya está pagada.' : 'Ese intento de pago ya terminó. Podés volver a pagar desde tu pedido.');
    header('Location: ' . $destino);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!token_cliente_valido()) {
        flash_cliente('error', 'La sesión expiró. Volvé a intentarlo.');
        header('Location: ' . $destino);
        exit;
    }
    $aprobar = ($_POST['resultado'] ?? '') === 'aprobar';
    marcar_pago($pdo, (int) $pago['id_pagos'], $aprobar ? 'aprobado' : 'rechazado',
                'SIM-' . strtoupper(bin2hex(random_bytes(5))), $aprobar ? 'simulado_aprobado' : 'simulado_rechazado');
    [$t, $m] = mensaje_resultado_pago($aprobar ? 'aprobado' : 'rechazado');
    flash_cliente($t, $m);
    header('Location: ' . $destino);
    exit;
}

$token = token_cliente();
$concepto = $pago['id_pedidos'] !== null ? 'Seña de tu pedido' : 'Seña de tu presupuesto';
$pagina_actual = 'pedidos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Pago simulado - Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/cliente.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/sitio.css') ?>">
</head>
<body>
<?php require __DIR__ . '/encabezado_cliente.php'; ?>
<main class="pagina-sitio" style="max-width:560px">
    <div class="pago-sim">
        <p class="pago-sim-aviso">MODO DE PRUEBA · No se cobra dinero</p>
        <h1>Pago simulado</h1>
        <p class="pago-sim-concepto"><?= e($concepto) ?></p>
        <p class="pago-sim-monto"><?= e(pesos((float) $pago['monto'])) ?></p>
        <p class="pago-sim-ref">Referencia <?= e($pago['referencia']) ?></p>
        <p class="pago-sim-ayuda">Esta pantalla reemplaza a Mercado Pago mientras el sitio no tiene credenciales.
           Elegí qué resultado querés probar:</p>
        <form method="post" class="pago-sim-botones">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="ref" value="<?= e($pago['referencia']) ?>">
            <button type="submit" name="resultado" value="aprobar" class="btn-mp">Aprobar pago</button>
            <button type="submit" name="resultado" value="rechazar" class="btn-pill borde">Simular pago rechazado</button>
        </form>
        <p><a class="link" href="<?= e($destino) ?>">Volver sin pagar</a></p>
    </div>
</main>
<?php require __DIR__ . '/pie_cliente.php'; ?>
<script src="<?= asset('js/sitio.js') ?>"></script>
</body>
</html>
