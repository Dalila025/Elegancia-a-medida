<?php
declare(strict_types=1);

/**
 * Señas (panel) — Elegancia a Medida
 *
 * - Señas por cobrar: pedidos confirmados/listos y presupuestos aceptados
 *   que todavía no tienen la seña completa. Desde acá la modista puede
 *   registrar una seña cobrada en efectivo o por transferencia (registrar_pago.php).
 * - Pagos recibidos: todas las señas aprobadas (Mercado Pago, simuladas o manuales).
 *
 * Tablas: pagos, pedidos, presupuestos, solicitudes_presupuesto, clientes.
 */

require_once __DIR__ . '/funciones_pagos.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario = requerir_modista($pdo);
$token   = token_modista();
$flash   = tomar_flash();

try {
    $por_cobrar = [];

    $marcas = implode(',', array_map('intval', PEDIDO_ESTADOS_CON_SENA));
    foreach ($pdo->query("SELECT p.id_pedidos, p.codigo, p.id_estados_pedido, p.total_estimado, p.tiene_a_consultar,
                                 c.nombre, c.telefono
                            FROM pedidos p INNER JOIN clientes c ON c.id_clientes = p.id_clientes
                           WHERE p.id_estados_pedido IN ($marcas) ORDER BY p.fecha_pedido") as $p) {
        $total = total_pedido_para_sena($p);
        $sena  = estado_sena($pdo, 'pedido', (int) $p['id_pedidos'], $total);
        if (!$sena['cubierta']) {
            $por_cobrar[] = ['tipo' => 'pedido', 'id' => (int) $p['id_pedidos'], 'que' => 'Pedido ' . formatear_codigo($p['codigo']),
                             'estado' => ESTADOS_PEDIDO[(int) $p['id_estados_pedido']], 'cliente' => $p['nombre'],
                             'telefono' => $p['telefono'], 'total' => $total, 'sena' => $sena];
        }
    }

    $marcas = implode(',', array_map('intval', SOLICITUD_ESTADOS_CON_SENA));
    foreach ($pdo->query("SELECT pr.id_presupuestos, pr.monto_total, s.codigo_seguimiento, s.tipo_prenda,
                                 s.id_estados_solicitud, s.nombre, s.telefono
                            FROM presupuestos pr
                            INNER JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = pr.id_solicitudes_presupuesto
                           WHERE s.id_estados_solicitud IN ($marcas) ORDER BY pr.fecha_respuesta_cliente") as $s) {
        $total = (float) $s['monto_total'];
        $sena  = estado_sena($pdo, 'presupuesto', (int) $s['id_presupuestos'], $total);
        if (!$sena['cubierta']) {
            $por_cobrar[] = ['tipo' => 'presupuesto', 'id' => (int) $s['id_presupuestos'],
                             'que' => $s['tipo_prenda'] . ' · ' . formatear_codigo($s['codigo_seguimiento']),
                             'estado' => ESTADOS[(int) $s['id_estados_solicitud']], 'cliente' => $s['nombre'],
                             'telefono' => $s['telefono'], 'total' => $total, 'sena' => $sena];
        }
    }

    $recibidos = $pdo->query(
        "SELECT pg.*, ped.codigo AS cod_pedido, s.codigo_seguimiento AS cod_solicitud, s.tipo_prenda,
                COALESCE(c.nombre, s.nombre) AS cliente
           FROM pagos pg
           LEFT JOIN pedidos ped ON ped.id_pedidos = pg.id_pedidos
           LEFT JOIN clientes c ON c.id_clientes = ped.id_clientes
           LEFT JOIN presupuestos pr ON pr.id_presupuestos = pg.id_presupuestos
           LEFT JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = pr.id_solicitudes_presupuesto
          WHERE pg.estado = 'aprobado'
          ORDER BY pg.fecha_pago DESC, pg.id_pagos DESC LIMIT 100"
    )->fetchAll();
    $total_recibido = array_sum(array_map(fn($r) => (float) $r['monto'], $recibidos));
} catch (PDOException $e) {
    error_log('Error al listar señas: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudieron cargar las señas.');
}

$pagina_actual = 'senas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Señas · Panel · Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/modista.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/panel.css') ?>">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container">
        <?php if ($flash): ?>
            <div class="flash <?= $flash['tipo'] === 'ok' ? 'flash-ok' : 'flash-error' ?>" role="status"><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>

        <p class="modo-cobro">Cobro de señas: <strong><?= e(modo_cobro_texto()) ?></strong> · Seña: <?= SENA_PORCENTAJE ?> % del total</p>

        <section class="tarjeta">
            <h2>Señas por cobrar</h2>
            <p class="ayuda" style="margin:-6px 0 14px">Pedidos confirmados y presupuestos aceptados que todavía no tienen la seña.
               Si el cliente te pagó en efectivo o por transferencia, registralo acá.</p>
            <?php if (!$por_cobrar): ?>
                <p class="sin-acciones">No hay señas pendientes.</p>
            <?php else: ?>
                <div class="lista-senas">
                    <?php foreach ($por_cobrar as $f): $ida = $f['tipo'] . '-' . $f['id']; ?>
                        <div class="sena-fila">
                            <div class="sena-info">
                                <strong><?= e($f['que']) ?></strong>
                                <span><?= e($f['cliente']) ?> · <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $f['telefono'])) ?>"><?= e($f['telefono']) ?></a> · <?= e($f['estado']) ?></span>
                                <?php if ($f['total'] === null): ?>
                                    <span class="sena-aviso">Tiene precios «a consultar»: confirmalo con el total final para calcular la seña.</span>
                                <?php else: ?>
                                    <span>Total <?= e(pesos($f['total'])) ?> · Seña <?= e(pesos($f['sena']['requerida'])) ?>
                                        <?= $f['sena']['pagado'] > 0 ? ' · Pagado ' . e(pesos($f['sena']['pagado'])) : '' ?>
                                        · <strong>Falta <?= e(pesos($f['sena']['falta'])) ?></strong></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($f['total'] !== null): ?>
                                <form method="post" action="registrar_pago.php" class="form-panel sena-form">
                                    <input type="hidden" name="token" value="<?= e($token) ?>">
                                    <input type="hidden" name="tipo" value="<?= e($f['tipo']) ?>">
                                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                    <label for="monto-<?= $ida ?>" class="sr-only">Monto</label>
                                    <input type="text" id="monto-<?= $ida ?>" name="monto" inputmode="decimal" value="<?= e(number_format((float) $f['sena']['falta'], 2, '.', '')) ?>" aria-label="Monto cobrado">
                                    <select name="metodo" aria-label="Cómo te pagó">
                                        <option value="efectivo">Efectivo</option>
                                        <option value="transferencia">Transferencia</option>
                                    </select>
                                    <input type="text" name="observaciones" maxlength="<?= PAGO_OBSERVACIONES_MAX ?>" placeholder="Nota (opcional)" aria-label="Nota">
                                    <button type="submit" class="btn-accion accion-2" onclick="return confirm('¿Registrar esta seña como cobrada?');">Registrar seña</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="tarjeta">
            <h2>Pagos recibidos</h2>
            <p class="ayuda" style="margin:-6px 0 8px">Los saldos (lo que pagan al retirar) se registran en la ficha de cada clienta → Cuenta corriente.</p>
            <?php if (!$recibidos): ?>
                <p class="sin-acciones">Todavía no hay pagos.</p>
            <?php else: ?>
                <p class="ayuda" style="margin:-6px 0 12px">Total de los últimos <?= count($recibidos) ?>: <strong><?= e(pesos($total_recibido)) ?></strong></p>
                <table class="tabla-tarifas">
                    <thead><tr><th>Fecha</th><th>De</th><th>Cliente</th><th>Concepto</th><th>Monto</th><th>Medio</th><th>N° de operación / nota</th></tr></thead>
                    <tbody>
                    <?php foreach ($recibidos as $r): ?>
                        <tr>
                            <td><?= $r['fecha_pago'] ? (new DateTime($r['fecha_pago']))->format('d/m/Y H:i') : '' ?></td>
                            <td><?= $r['cod_pedido'] !== null ? 'Pedido ' . e(formatear_codigo($r['cod_pedido'])) : e((string) $r['tipo_prenda']) . ' · ' . e(formatear_codigo((string) $r['cod_solicitud'])) ?></td>
                            <td><?= e((string) $r['cliente']) ?></td>
                            <td><?= $r['concepto'] === 'sena' ? 'Seña' : 'Saldo' ?></td>
                            <td><strong><?= e(pesos((float) $r['monto'])) ?></strong></td>
                            <td><?= e(PAGO_METODOS[$r['metodo']] ?? $r['metodo']) ?></td>
                            <td class="ref-catalogo" style="display:table-cell"><?= e(in_array($r['metodo'], ['mercadopago', 'simulado'], true) ? (string) $r['mp_payment_id'] : $r['observaciones']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
