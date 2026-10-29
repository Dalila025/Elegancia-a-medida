<?php
declare(strict_types=1);

/**
 * Estado de cuenta de la clienta — Elegancia a Medida
 *
 * La clienta ve lo mismo que la modista en su cuenta corriente:
 * sus trabajos confirmados (lo que tiene que pagar), lo que ya pagó
 * (señas y pagos) y cuánto le queda, con el detalle de movimientos.
 * Se arma con funciones_cuenta.php. Tablas: pedidos, presupuestos, pagos.
 */

require_once __DIR__ . '/funciones_cuenta.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$cliente = requerir_cliente($pdo, 'Iniciá sesión para ver tu estado de cuenta.');

try {
    $trabajos    = trabajos_cliente($pdo, (int) $cliente['id_clientes']);
    $cuenta      = resumen_cuenta($trabajos);
    $movimientos = movimientos_cliente($pdo, $trabajos);
} catch (PDOException $e) {
    error_log('Error en mi cuenta: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo cargar tu estado de cuenta.');
}

$enlace = fn(array $t) => $t['tipo'] === 'pedido'
    ? 'mis_pedidos.php?codigo=' . $t['codigo'] . '#pedido-' . $t['codigo']
    : 'seguimiento.php?codigo=' . $t['codigo'];
$pagina_actual = 'cuenta';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de cuenta - Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/cliente.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/sitio.css') ?>">
</head>
<body>
<?php require __DIR__ . '/encabezado_cliente.php'; ?>

<main class="pagina-sitio" style="max-width:900px">
    <p class="migas"><a href="index.php">Inicio</a> / Estado de cuenta</p>
    <h1>Estado de cuenta</h1>

    <div class="cuenta-cifras">
        <div><span>Tus trabajos confirmados</span><strong><?= e(pesos($cuenta['debe'])) ?></strong></div>
        <div><span>Ya pagaste</span><strong><?= e(pesos($cuenta['haber'])) ?></strong></div>
        <div class="<?= $cuenta['saldo'] > 0.009 ? 'debe' : 'al-dia' ?>">
            <span><?= $cuenta['saldo'] > 0.009 ? 'Te queda por pagar' : ($cuenta['saldo'] < -0.009 ? 'Saldo a tu favor' : 'Estás al día') ?></span>
            <strong><?= e(pesos(abs($cuenta['saldo']))) ?></strong>
        </div>
    </div>

    <?php if (!$trabajos): ?>
        <div class="vacio-grande">
            <p>Todavía no tenés trabajos confirmados. Cuando la modista confirme un pedido o aceptes un presupuesto, lo vas a ver acá.</p>
            <a class="btn-pill" href="tienda.php">Ir a la tienda</a>
        </div>
    <?php else: ?>
        <h2 class="cuenta-sub">Tus trabajos</h2>
        <div class="cuenta-trabajos">
            <?php foreach ($trabajos as $t): $pct = $t['total'] > 0 ? min(100, $t['pagado'] / $t['total'] * 100) : 100; ?>
                <a class="cuenta-trabajo" href="<?= e($enlace($t)) ?>">
                    <div class="ct-cabeza">
                        <strong><?= e($t['titulo']) ?></strong>
                        <span class="ct-estado"><?= e($t['estado']) ?></span>
                    </div>
                    <div class="ct-barra" aria-hidden="true"><span style="width:<?= round($pct, 1) ?>%"></span></div>
                    <div class="ct-montos">
                        <span>Total <?= $t['a_consultar'] ? 'a consultar' : e(pesos($t['total'])) ?></span>
                        <span>Pagaste <?= e(pesos($t['pagado'])) ?></span>
                        <strong><?= !$t['activo'] && $t['pagado'] <= 0 ? 'Te devolvimos el pago' : (e(texto_saldo($t['saldo'])) === 'Al día' ? '✓ Pagado' : ($t['saldo'] > 0 ? 'Falta ' . e(pesos($t['saldo'])) : 'A tu favor ' . e(pesos(-$t['saldo'])))) ?></strong>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <h2 class="cuenta-sub">Movimientos</h2>
        <table class="tabla-mov">
            <thead><tr><th>Fecha</th><th>Detalle</th><th class="num">Cargo</th><th class="num">Pago</th><th class="num">Saldo</th></tr></thead>
            <tbody>
            <?php foreach ($movimientos as $mv): ?>
                <tr>
                    <td><?= (new DateTime($mv['fecha']))->format('d/m/Y') ?></td>
                    <td><?= e($mv['concepto']) ?></td>
                    <td class="num"><?= $mv['debe'] > 0 ? e(pesos($mv['debe'])) : '' ?></td>
                    <td class="num"><?= $mv['haber'] > 0 ? e(pesos($mv['haber'])) : '' ?></td>
                    <td class="num"><?= e(pesos_signo($mv['saldo'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="sec-sub" style="margin-top:12px">La seña se paga online desde cada pedido o solicitud. El resto lo podés pagar al retirar, en efectivo o por transferencia.</p>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/pie_cliente.php'; ?>
<script src="<?= asset('js/sitio.js') ?>"></script>
<script src="<?= asset('js/notificaciones.js') ?>"></script>
</body>
</html>
