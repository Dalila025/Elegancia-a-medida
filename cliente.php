<?php
declare(strict_types=1);

/**
 * Ficha de una clienta (panel) — Elegancia a Medida
 *
 * cliente.php?id=N: datos de contacto, medidas vigentes con su historial,
 * y formulario para cargar medidas nuevas (las que la modista toma en el
 * taller: quedan marcadas como «modista»). También lista sus pedidos y solicitudes,
 * y su CUENTA CORRIENTE: trabajos confirmados (debe), pagos (haber) y saldo, con un
 * formulario para registrar lo que paga en efectivo o transferencia (registrar_cobro.php).
 * Tablas: clientes, medidas_clientes, pedidos, solicitudes_presupuesto, presupuestos, pagos.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/funciones_cuenta.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario = requerir_modista($pdo);
$token   = token_modista();
$id      = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$errores = [];
$enviado = null;

try {
    $stmt = $pdo->prepare('SELECT * FROM clientes WHERE id_clientes = :id');
    $stmt->execute([':id' => $id]);
    $cli = $stmt->fetch();
    if (!$cli) {
        flash('error', 'Esa clienta no existe.');
        header('Location: clientes.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!token_modista_valido()) {
            $errores[] = 'La sesión expiró. Recargue la página e intente de nuevo.';
        } else {
            $errores = guardar_medidas($pdo, $id, $_POST, 'modista');
            if (!$errores) {
                flash('ok', 'Se guardaron las medidas de ' . $cli['nombre'] . '.');
                header('Location: cliente.php?id=' . $id);
                exit;
            }
        }
        $enviado = $_POST;
    }

    $medidas = medidas_vigentes($pdo, $id);
    $stmt = $pdo->prepare('SELECT * FROM medidas_clientes WHERE id_clientes = :id ORDER BY fecha_carga DESC, id_medidas_clientes DESC LIMIT 10');
    $stmt->execute([':id' => $id]);
    $historial = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT codigo, id_estados_pedido, total_estimado, tiene_a_consultar, fecha_pedido FROM pedidos WHERE id_clientes = :id ORDER BY fecha_pedido DESC LIMIT 10');
    $stmt->execute([':id' => $id]);
    $pedidos = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT s.id_solicitudes_presupuesto, s.codigo_seguimiento, s.tipo_prenda, s.id_estados_solicitud, s.fecha_solicitud, p.monto_total
                             FROM solicitudes_presupuesto s LEFT JOIN presupuestos p USING (id_solicitudes_presupuesto)
                            WHERE s.id_clientes = :id ORDER BY s.fecha_solicitud DESC LIMIT 10');
    $stmt->execute([':id' => $id]);
    $solicitudes = $stmt->fetchAll();

    $trabajos    = trabajos_cliente($pdo, $id);
    $cuenta      = resumen_cuenta($trabajos);
    $movimientos = movimientos_cliente($pdo, $trabajos);
    $con_saldo   = array_values(array_filter($trabajos, fn($t) => $t['activo'] && !$t['a_consultar'] && $t['saldo'] > 0));
} catch (PDOException $e) {
    error_log('Error en la ficha de clienta: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo cargar la ficha.');
}

$flash = tomar_flash();
$valor = function (string $c) use ($enviado, $medidas): string {
    if ($enviado !== null) {
        return isset($enviado[$c]) && is_string($enviado[$c]) ? $enviado[$c] : '';
    }
    if ($c === 'notas') {
        return (string) ($medidas['notas'] ?? '');
    }
    return $medidas && $medidas[$c] !== null ? cm((float) $medidas[$c]) : '';
};
$pagina_actual = 'clientes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= e($cli['nombre']) ?> · Clientas · Panel · Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/modista.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/panel.css') ?>">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>
    <main class="container">
        <p style="margin-top:16px"><a href="clientes.php" class="btn-link">← Volver a clientas</a></p>
        <?php if ($flash): ?>
            <div class="flash <?= $flash['tipo'] === 'ok' ? 'flash-ok' : 'flash-error' ?>" role="status"><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>
        <?php if ($errores): ?>
            <div class="flash flash-error" role="alert"><strong>Revisá estos datos:</strong>
                <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <section class="tarjeta">
            <h2><?= e($cli['nombre']) ?></h2>
            <p class="ayuda" style="margin:0">
                <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $cli['telefono'])) ?>"><?= e($cli['telefono']) ?></a> ·
                <?= e($cli['email']) ?> · clienta desde <?= (new DateTime($cli['fecha_alta']))->format('d/m/Y') ?>
            </p>
        </section>

        <section class="tarjeta" id="cuenta">
            <div class="pedido-cabeza">
                <h2>Cuenta corriente</h2>
                <span class="saldo-badge <?= $cuenta['saldo'] > 0.009 ? 'debe' : ($cuenta['saldo'] < -0.009 ? 'favor' : 'al-dia') ?>"><?= e(texto_saldo($cuenta['saldo'])) ?></span>
            </div>
            <div class="resumen-gastos" style="margin-top:10px">
                <div class="cifra"><span>Trabajos confirmados</span><strong><?= e(pesos($cuenta['debe'])) ?></strong><small>Lo que tiene que pagar en total</small></div>
                <div class="cifra"><span>Pagó</span><strong><?= e(pesos($cuenta['haber'])) ?></strong><small>Señas y pagos registrados</small></div>
                <div class="cifra"><span>Saldo</span><strong class="<?= $cuenta['saldo'] > 0.009 ? 'negativo' : 'positivo' ?>"><?= e(pesos(abs($cuenta['saldo']))) ?></strong><small><?= $cuenta['saldo'] > 0.009 ? 'Le falta pagar' : ($cuenta['saldo'] < -0.009 ? 'A favor de la clienta (hay que devolver o descontar)' : 'No debe nada') ?></small></div>
            </div>
            <?php if ($cuenta['a_consultar']): ?>
                <p class="ayuda">Hay un pedido con precios «a consultar»: no suma hasta que le pongas el total al confirmarlo.</p>
            <?php endif; ?>

            <?php if (!$trabajos): ?>
                <p class="sin-acciones">Todavía no tiene trabajos confirmados ni pagos.</p>
            <?php else: ?>
                <h3>Por trabajo</h3>
                <div class="tabla-scroll">
                    <table class="tabla-tarifas tabla-gastos">
                        <thead><tr><th>Trabajo</th><th>Estado</th><th class="num">Total</th><th class="num">Pagado</th><th class="num">Saldo</th></tr></thead>
                        <tbody>
                        <?php foreach ($trabajos as $t): ?>
                            <tr>
                                <td><?= e($t['titulo']) ?></td>
                                <td><?= e($t['estado']) ?></td>
                                <td class="num"><?= $t['a_consultar'] ? 'A consultar' : e(pesos($t['total'])) ?></td>
                                <td class="num"><?= e(pesos($t['pagado'])) ?></td>
                                <td class="num"><strong class="<?= $t['saldo'] > 0.009 ? 'txt-debe' : ($t['saldo'] < -0.009 ? 'txt-favor' : '') ?>"><?= !$t['activo'] && $t['pagado'] <= 0 ? 'Devuelto' : e(texto_saldo($t['saldo'])) ?></strong>
                                    <?php if (!$t['activo'] && $t['pagado'] > 0): ?>
                                        <form method="post" action="registrar_cobro.php" class="form-devolver" onsubmit="return confirm('¿Ya le devolviste <?= e(pesos($t['pagado'])) ?> a la clienta?');">
                                            <input type="hidden" name="token" value="<?= e($token) ?>">
                                            <input type="hidden" name="accion" value="devolver">
                                            <input type="hidden" name="id_clientes" value="<?= $id ?>">
                                            <input type="hidden" name="trabajo" value="<?= $t['tipo'] . '-' . $t['id'] ?>">
                                            <button type="submit" class="btn-accion accion-1">Registrar devolución</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h3>Movimientos</h3>
                <div class="tabla-scroll">
                    <table class="tabla-tarifas tabla-gastos">
                        <thead><tr><th>Fecha</th><th>Concepto</th><th class="num">Debe</th><th class="num">Haber</th><th class="num">Saldo</th></tr></thead>
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
                </div>
            <?php endif; ?>

            <?php if ($con_saldo): ?>
                <h3>Registrar un pago</h3>
                <p class="ayuda" style="margin-top:-4px">Lo que te pague en efectivo o por transferencia. Si todavía falta la seña de ese trabajo, se anota primero como seña.</p>
                <form method="post" action="registrar_cobro.php" class="form-panel cobro-form">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <input type="hidden" name="id_clientes" value="<?= $id ?>">
                    <div class="campo">
                        <label for="c-trabajo">Trabajo</label>
                        <select id="c-trabajo" name="trabajo" required>
                            <?php foreach ($con_saldo as $t): ?>
                                <option value="<?= $t['tipo'] . '-' . $t['id'] ?>" data-saldo="<?= e(number_format($t['saldo'], 2, '.', '')) ?>"><?= e($t['titulo']) ?> — saldo <?= e(pesos($t['saldo'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="c-monto">Monto ($)</label>
                        <input type="text" id="c-monto" name="monto" inputmode="decimal" value="<?= e(number_format($con_saldo[0]['saldo'], 2, '.', '')) ?>" required>
                    </div>
                    <div class="campo">
                        <label for="c-metodo">Cómo pagó</label>
                        <select id="c-metodo" name="metodo"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option></select>
                    </div>
                    <div class="campo">
                        <label for="c-obs">Nota (opcional)</label>
                        <input type="text" id="c-obs" name="observaciones" maxlength="<?= PAGO_OBSERVACIONES_MAX ?>" placeholder="Ej: pagó al retirar">
                    </div>
                    <button type="submit" class="btn-guardar" onclick="return confirm('¿Registrar este pago?');">Registrar pago</button>
                </form>
                <script>
                    // Al cambiar de trabajo, se propone el saldo de ese trabajo.
                    document.getElementById('c-trabajo').addEventListener('change', function () {
                        document.getElementById('c-monto').value = this.options[this.selectedIndex].dataset.saldo;
                    });
                </script>
            <?php endif; ?>
        </section>

        <section class="tarjeta" id="medidas">
            <h2>Medidas</h2>
            <?php if ($medidas): ?>
                <p class="ayuda" style="margin:-6px 0 12px">Vigentes desde el <?= (new DateTime($medidas['fecha_carga']))->format('d/m/Y') ?>
                    (<?= $medidas['cargada_por'] === 'modista' ? 'tomadas por vos' : 'cargadas por la clienta' ?>). Al guardar se agrega una carga nueva y queda el historial.</p>
            <?php else: ?>
                <p class="ayuda" style="margin:-6px 0 12px">Todavía no tiene medidas. Cargá las que le tomes en el taller.</p>
            <?php endif; ?>
            <form method="post" class="form-panel">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="grilla-medidas">
                    <?php foreach (MEDIDAS_CLIENTE as $c => [$nombre, $como]): ?>
                        <div class="campo">
                            <label for="m-<?= $c ?>" title="<?= e($como) ?>"><?= e($nombre) ?> (cm)</label>
                            <input type="text" inputmode="decimal" id="m-<?= $c ?>" name="<?= $c ?>" maxlength="5" value="<?= e($valor($c)) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="campo">
                    <label for="m-notas">Notas</label>
                    <input type="text" id="m-notas" name="notas" maxlength="<?= MEDIDAS_NOTAS_MAX ?>" value="<?= e($valor('notas')) ?>" placeholder="Ej: hombro derecho más bajo, usa taco 8 cm">
                </div>
                <button type="submit" class="btn-guardar">Guardar medidas</button>
            </form>

            <?php if (count($historial) > 1): ?>
                <h3 style="margin-top:20px">Historial</h3>
                <div class="tabla-scroll">
                    <table class="tabla-tarifas">
                        <thead><tr><th>Fecha</th><th>Por</th><?php foreach (MEDIDAS_CLIENTE as [$nombre]): ?><th><?= e($nombre) ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                        <?php foreach ($historial as $h): ?>
                            <tr>
                                <td><?= (new DateTime($h['fecha_carga']))->format('d/m/Y') ?></td>
                                <td><?= $h['cargada_por'] === 'modista' ? 'Modista' : 'Clienta' ?></td>
                                <?php foreach (array_keys(MEDIDAS_CLIENTE) as $c): ?><td><?= $h[$c] === null ? '—' : e(cm((float) $h[$c])) ?></td><?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="tarjeta">
            <h2>Pedidos y solicitudes</h2>
            <?php if (!$pedidos && !$solicitudes): ?>
                <p class="sin-acciones">Todavía no hizo pedidos ni solicitudes.</p>
            <?php endif; ?>
            <ul class="lista-ficha">
                <?php foreach ($pedidos as $p): ?>
                    <li>Pedido <strong><?= e(formatear_codigo($p['codigo'])) ?></strong> · <?= e(ESTADOS_PEDIDO[(int) $p['id_estados_pedido']] ?? '') ?>
                        · <?= e(pesos((float) $p['total_estimado'])) ?><?= (int) $p['tiene_a_consultar'] ? ' + a consultar' : '' ?>
                        · <?= (new DateTime($p['fecha_pedido']))->format('d/m/Y') ?></li>
                <?php endforeach; ?>
                <?php foreach ($solicitudes as $s): ?>
                    <li>Solicitud <strong><?= e($s['tipo_prenda']) ?></strong> (<?= e(formatear_codigo($s['codigo_seguimiento'])) ?>) · <?= e(ESTADOS[(int) $s['id_estados_solicitud']] ?? '') ?>
                        <?= $s['monto_total'] !== null ? ' · ' . e(pesos((float) $s['monto_total'])) : '' ?>
                        · <?= (new DateTime($s['fecha_solicitud']))->format('d/m/Y') ?>
                        <?php if ($s['monto_total'] !== null && in_array((int) $s['id_estados_solicitud'], TRABAJO_ESTADOS_CON_GASTOS, true)): ?>
                            · <a href="trabajo.php?id=<?= (int) $s['id_solicitudes_presupuesto'] ?>">💰 Gastos y ganancia</a>
                        <?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </main>
</body>
</html>
