<?php
declare(strict_types=1);

/**
 * Costos de un trabajo (panel) — Elegancia a Medida
 *
 * trabajo.php?id=N: lo que gastó la modista para hacer esa prenda (tela,
 * cierres, hilos…), comparado con el precio del presupuesto:
 *   Precio − Gastado = Ganancia.
 * Tiene un formulario para cargar un gasto de este trabajo y la lista de los
 * cargados (editar en Gastos o eliminar).
 * Guarda en guardar_gasto.php (origen=trabajo). Tablas: gastos, solicitudes_presupuesto, presupuestos, pagos.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario = requerir_modista($pdo);
$token   = token_modista();
$flash   = tomar_flash();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
try {
    $t = $id ? costos_trabajo($pdo, $id) : null;
    if (!$t) {
        flash('error', 'Ese trabajo no existe.');
        header('Location: taller.php');
        exit;
    }
    $rubros = $pdo->query('SELECT id_categorias_gasto, nombre FROM categorias_gasto WHERE activo = 1 ORDER BY nombre')->fetchAll();
    $proveedores = $pdo->query("SELECT DISTINCT proveedor FROM gastos WHERE proveedor <> '' ORDER BY proveedor LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('Error en costos del trabajo: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo cargar el trabajo.');
}
$admite = in_array((int) $t['id_estados_solicitud'], TRABAJO_ESTADOS_CON_GASTOS, true) && $t['id_presupuestos'] !== null;

// Errores devueltos por guardar_gasto.php
$errores = [];
$viejo = [];
if (isset($_SESSION['form_gasto']) && (int) ($_SESSION['form_gasto']['datos']['id_solicitudes_presupuesto'] ?? 0) === $id) {
    $errores = $_SESSION['form_gasto']['errores'];
    $viejo   = $_SESSION['form_gasto']['datos'];
}
unset($_SESSION['form_gasto']);
$v = fn(string $c, string $def = '') => (string) ($viejo[$c] ?? $def);

// Rubro sugerido: «Telas» si existe
$rubro_def = 0;
foreach ($rubros as $r) { if (stripos($r['nombre'], 'tela') !== false) { $rubro_def = (int) $r['id_categorias_gasto']; break; } }
$margen = $t['precio'] > 0 ? $t['ganancia'] / $t['precio'] * 100 : null;
$pagina_actual = 'gastos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Costos del trabajo · Panel · Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/modista.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/panel.css') ?>">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container">
        <?php if ($flash): ?>
            <div class="flash <?= $flash['tipo'] === 'ok' ? 'flash-ok' : 'flash-error' ?>" role="status"><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>

        <p class="volver-links">
            <a href="taller.php" class="btn-secundario">← Taller</a>
            <a href="gastos.php" class="btn-secundario">Gastos</a>
            <a href="cliente.php?id=<?= (int) $t['id_clientes'] ?>" class="btn-secundario">Ficha de <?= e(explode(' ', trim($t['cliente']))[0]) ?></a>
        </p>

        <section class="tarjeta">
            <div class="pedido-cabeza">
                <div>
                    <h2>✂ <?= e($t['tipo_prenda']) ?></h2>
                    <p class="ayuda" style="margin:0"><?= e($t['cliente']) ?> · <?= e($t['servicio']) ?> · <?= e(formatear_codigo($t['codigo_seguimiento'])) ?>
                        <?php if ($t['fecha_entrega']): ?> · entrega <?= e(DateTime::createFromFormat('!Y-m-d', $t['fecha_entrega'])->format('d/m/Y')) ?><?php endif; ?></p>
                </div>
                <span class="estado-badge estado-<?= (int) $t['id_estados_solicitud'] ?>"><?= e(ESTADOS[(int) $t['id_estados_solicitud']] ?? '') ?></span>
            </div>
            <div class="costos-cifras">
                <div class="cifra"><span>Precio del trabajo</span><strong><?= e(pesos($t['precio'])) ?></strong>
                    <small>Cobrado hasta hoy: <?= e(pesos($t['cobrado'])) ?></small></div>
                <div class="cifra"><span>Gastado en materiales</span><strong><?= e(pesos($t['total_gastos'])) ?></strong>
                    <small><?= count($t['gastos']) ?> gasto<?= count($t['gastos']) === 1 ? '' : 's' ?> cargado<?= count($t['gastos']) === 1 ? '' : 's' ?></small></div>
                <div class="cifra"><span>Ganancia</span>
                    <strong class="<?= $t['ganancia'] < 0 ? 'negativo' : 'positivo' ?>"><?= ($t['ganancia'] < 0 ? '− ' : '') . e(pesos(abs($t['ganancia']))) ?></strong>
                    <small><?= $margen !== null ? 'Te queda el ' . e(number_format($margen, 0, ',', '.')) . ' % del precio' : 'Todavía no tiene presupuesto' ?></small></div>
            </div>
            <?php if ($t['precio'] > 0 && $t['total_gastos'] > $t['precio']): ?>
                <div class="flash flash-error" role="status">Gastaste más de lo que cobrás por este trabajo. Revisá el precio para la próxima.</div>
            <?php endif; ?>
        </section>

        <div class="gastos-grid">
            <section class="tarjeta" id="form-gasto">
                <h2>Cargar un gasto de este trabajo</h2>
                <?php if (!$admite): ?>
                    <p class="sin-acciones">Solo se cargan gastos de trabajos aceptados (en el taller o terminados).</p>
                <?php else: ?>
                    <?php if ($errores): ?>
                        <div class="flash flash-error" role="alert"><strong>Revisá estos datos:</strong>
                            <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                    <form method="post" action="guardar_gasto.php" class="form-panel" novalidate>
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <input type="hidden" name="accion" value="guardar">
                        <input type="hidden" name="id" value="0">
                        <input type="hidden" name="origen" value="trabajo">
                        <input type="hidden" name="trabajo" value="<?= $id ?>">
                        <input type="hidden" name="id_solicitudes_presupuesto" value="<?= $id ?>">
                        <div class="campo">
                            <label for="g-desc">¿Qué compraste para esta prenda?</label>
                            <input type="text" id="g-desc" name="descripcion" maxlength="<?= GASTO_DESCRIPCION_MAX ?>" value="<?= e($v('descripcion')) ?>" placeholder="Ej: Satén azul, cierre invisible, hilo, botones" required>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="g-monto">Monto ($)</label>
                                <input type="text" id="g-monto" name="monto" inputmode="decimal" value="<?= e($v('monto')) ?>" placeholder="Ej: 27000" required>
                            </div>
                            <div class="campo">
                                <label for="g-cant">Cantidad (opcional)</label>
                                <input type="text" id="g-cant" name="cantidad" maxlength="<?= GASTO_CANTIDAD_MAX ?>" value="<?= e($v('cantidad')) ?>" placeholder="Ej: 3 m">
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="g-rubro">Rubro</label>
                                <select id="g-rubro" name="id_categorias_gasto" required>
                                    <?php $sel = (int) $v('id_categorias_gasto', (string) $rubro_def); foreach ($rubros as $r): ?>
                                        <option value="<?= (int) $r['id_categorias_gasto'] ?>" <?= $sel === (int) $r['id_categorias_gasto'] ? 'selected' : '' ?>><?= e($r['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="campo">
                                <label for="g-fecha">Fecha</label>
                                <input type="date" id="g-fecha" name="fecha" max="<?= date('Y-m-d') ?>" value="<?= e($v('fecha', date('Y-m-d'))) ?>" required>
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="g-medio">Cómo lo pagaste</label>
                                <select id="g-medio" name="medio_pago">
                                    <?php $m = $v('medio_pago', 'efectivo'); foreach (GASTO_MEDIOS as $clave => $txt): ?>
                                        <option value="<?= $clave ?>" <?= $m === $clave ? 'selected' : '' ?>><?= e($txt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="campo">
                                <label for="g-prov">Dónde (opcional)</label>
                                <input type="text" id="g-prov" name="proveedor" maxlength="<?= GASTO_PROVEEDOR_MAX ?>" value="<?= e($v('proveedor')) ?>" placeholder="Ej: Mercería Rosa" list="proveedores">
                                <datalist id="proveedores"><?php foreach ($proveedores as $pr): ?><option value="<?= e($pr) ?>"></option><?php endforeach; ?></datalist>
                            </div>
                        </div>
                        <div class="botonera">
                            <button type="submit" class="btn-guardar">Agregar gasto</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>

            <section class="tarjeta">
                <h2>Lo que gastaste</h2>
                <?php if (!$t['gastos']): ?>
                    <p class="sin-acciones">Todavía no cargaste gastos para este trabajo.</p>
                <?php else: ?>
                    <ul class="lista-gastos-trabajo">
                        <?php foreach ($t['gastos'] as $g): $idg = (int) $g['id_gastos']; ?>
                            <li>
                                <div>
                                    <strong><?= e($g['descripcion']) ?></strong><?= $g['cantidad'] !== '' ? ' · ' . e($g['cantidad']) : '' ?>
                                    <span class="ref-catalogo"><?= e(DateTime::createFromFormat('!Y-m-d', $g['fecha'])->format('d/m/Y')) ?> · <?= e($g['rubro']) ?><?= $g['proveedor'] !== '' ? ' · ' . e($g['proveedor']) : '' ?></span>
                                </div>
                                <div class="lgt-derecha">
                                    <strong><?= e(pesos((float) $g['monto'])) ?></strong>
                                    <a class="btn-accion accion-presupuestar" href="gastos.php?mes=<?= e(substr($g['fecha'], 0, 7)) ?>&amp;editar=<?= $idg ?>#form-gasto">Editar</a>
                                    <form method="post" action="guardar_gasto.php" onsubmit="return confirm('¿Eliminar este gasto?');">
                                        <input type="hidden" name="token" value="<?= e($token) ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?= $idg ?>">
                                        <input type="hidden" name="origen" value="trabajo">
                                        <input type="hidden" name="trabajo" value="<?= $id ?>">
                                        <button type="submit" class="btn-accion accion-5">Eliminar</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        <li class="lgt-total"><span>Total</span><strong><?= e(pesos($t['total_gastos'])) ?></strong></li>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
