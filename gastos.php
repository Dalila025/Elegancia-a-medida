<?php
declare(strict_types=1);

/**
 * Gastos (panel) — Elegancia a Medida
 *
 * La modista anota lo que gasta en su trabajo: telas, elástico, hilos,
 * cierres, luz, alquiler del taller, etc.
 *   - Arriba: el mes que se mira (con flechas) y el filtro por rubro.
 *   - Resumen: total gastado, lo cobrado en el mes (tabla pagos) y la ganancia, y por rubro.
 *   - Formulario para cargar o editar un gasto (general o de un trabajo: la tela de un vestido, etc.).
 *   - Trabajos con gastos en el mes: precio, gastado y ganancia de cada uno (trabajo.php).
 *   - Lista de gastos del mes (editar / eliminar) y exportar a Excel (CSV).
 *   - Rubros: agregar, ocultar o eliminar (si no tienen gastos).
 *
 * gastos.php?mes=2026-10&rubro=2&editar=15
 * Guarda en guardar_gasto.php. Tablas: gastos, categorias_gasto (y pagos para el resumen).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario = requerir_modista($pdo);
$token   = token_modista();
$flash   = tomar_flash();

const MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

// Mes que se mira (por defecto, el actual)
$mes_txt = isset($_GET['mes']) && is_string($_GET['mes']) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_GET['mes']) ? $_GET['mes'] : date('Y-m');
$inicio  = DateTime::createFromFormat('!Y-m-d', $mes_txt . '-01');
$fin     = (clone $inicio)->modify('first day of next month');
$anterior = (clone $inicio)->modify('-1 month')->format('Y-m');
$siguiente = (clone $inicio)->modify('+1 month')->format('Y-m');
$nombre_mes = fn(DateTime $d) => MESES[(int) $d->format('n')] . ' ' . $d->format('Y');

$rubro  = filter_input(INPUT_GET, 'rubro', FILTER_VALIDATE_INT) ?: 0;
$editar = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT) ?: 0;

try {
    $rubros_todos = $pdo->query('SELECT r.*, (SELECT COUNT(*) FROM gastos g WHERE g.id_categorias_gasto = r.id_categorias_gasto) AS usados
                                   FROM categorias_gasto r ORDER BY r.nombre')->fetchAll();
    $rubros_activos = array_filter($rubros_todos, fn($r) => (int) $r['activo'] === 1);
    if (!array_filter($rubros_todos, fn($r) => (int) $r['id_categorias_gasto'] === $rubro)) {
        $rubro = 0;
    }

    $params = [':ini' => $inicio->format('Y-m-d'), ':fin' => $fin->format('Y-m-d')];
    $sql = 'SELECT g.*, r.nombre AS rubro, s.tipo_prenda AS trabajo_prenda, c.nombre AS trabajo_cliente FROM gastos g
              INNER JOIN categorias_gasto r ON r.id_categorias_gasto = g.id_categorias_gasto
              LEFT JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = g.id_solicitudes_presupuesto
              LEFT JOIN clientes c ON c.id_clientes = s.id_clientes
             WHERE g.fecha >= :ini AND g.fecha < :fin';
    if ($rubro) {
        $sql .= ' AND g.id_categorias_gasto = :r';
        $params[':r'] = $rubro;
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY g.fecha DESC, g.id_gastos DESC');
    $stmt->execute($params);
    $gastos = $stmt->fetchAll();

    // Resumen del mes (sin filtro de rubro)
    $stmt = $pdo->prepare('SELECT r.id_categorias_gasto AS id, r.nombre, SUM(g.monto) AS total, COUNT(*) AS n
                             FROM gastos g INNER JOIN categorias_gasto r ON r.id_categorias_gasto = g.id_categorias_gasto
                            WHERE g.fecha >= :ini AND g.fecha < :fin
                            GROUP BY r.id_categorias_gasto, r.nombre ORDER BY total DESC');
    $stmt->execute([':ini' => $params[':ini'], ':fin' => $params[':fin']]);
    $por_rubro = $stmt->fetchAll();
    $total_mes = array_sum(array_map(fn($f) => (float) $f['total'], $por_rubro));

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE estado = 'aprobado' AND fecha_pago >= :ini AND fecha_pago < :fin");
    $stmt->execute([':ini' => $params[':ini'], ':fin' => $params[':fin']]);
    $cobrado_mes = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(monto), 0) FROM gastos WHERE fecha >= :ini AND fecha < :fin');
    $ant_ini = (clone $inicio)->modify('-1 month');
    $stmt->execute([':ini' => $ant_ini->format('Y-m-d'), ':fin' => $inicio->format('Y-m-d')]);
    $total_anterior = (float) $stmt->fetchColumn();

    // Gasto a editar
    $en_edicion = null;
    if ($editar) {
        $stmt = $pdo->prepare('SELECT * FROM gastos WHERE id_gastos = :id');
        $stmt->execute([':id' => $editar]);
        $en_edicion = $stmt->fetch() ?: null;
    }

    // Trabajos para elegir en el formulario, y los que tuvieron gastos este mes (con su ganancia)
    $trabajos_sel = trabajos_para_gastos($pdo, $en_edicion ? (int) $en_edicion['id_solicitudes_presupuesto'] : 0);
    $stmt = $pdo->prepare('SELECT DISTINCT id_solicitudes_presupuesto FROM gastos
                            WHERE id_solicitudes_presupuesto IS NOT NULL AND fecha >= :ini AND fecha < :fin');
    $stmt->execute([':ini' => $params[':ini'], ':fin' => $params[':fin']]);
    $trabajos_mes = array_values(array_filter(array_map(fn($id) => costos_trabajo($pdo, (int) $id), $stmt->fetchAll(PDO::FETCH_COLUMN))));
} catch (PDOException $e) {
    error_log('Error en gastos: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudieron cargar los gastos.');
}

// Datos del formulario: lo enviado con error, el gasto en edición o vacío
$errores = $_SESSION['form_gasto']['errores'] ?? [];
$viejo   = $_SESSION['form_gasto']['datos'] ?? null;
if ($viejo !== null) { $editar = (int) ($_SESSION['form_gasto']['id'] ?? 0); }
unset($_SESSION['form_gasto']);
$v = function (string $c, string $def = '') use ($viejo, $en_edicion): string {
    if ($viejo !== null) { return (string) ($viejo[$c] ?? $def); }
    if ($en_edicion) { return (string) $en_edicion[$c]; }
    return $def;
};
$fecha_def = date('Y-m') === $mes_txt ? date('Y-m-d') : $fin->modify('-1 day')->format('Y-m-d');
$url = fn(array $cambios) => 'gastos.php?' . http_build_query(array_filter(array_merge(['mes' => $mes_txt, 'rubro' => $rubro ?: null], $cambios)));

$pagina_actual = 'gastos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Gastos · Panel · Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/modista.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/panel.css') ?>">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container">
        <?php if ($flash): ?>
            <div class="flash <?= $flash['tipo'] === 'ok' ? 'flash-ok' : 'flash-error' ?>" role="status"><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>

        <section class="toolbar gastos-barra">
            <div class="mes-nav">
                <a class="btn-secundario" href="<?= e($url(['mes' => $anterior, 'editar' => null])) ?>" aria-label="Mes anterior">‹</a>
                <h2><?= e($nombre_mes($inicio)) ?></h2>
                <a class="btn-secundario" href="<?= e($url(['mes' => $siguiente, 'editar' => null])) ?>" aria-label="Mes siguiente">›</a>
                <?php if ($mes_txt !== date('Y-m')): ?><a class="btn-link" href="gastos.php">Ir al mes actual</a><?php endif; ?>
            </div>
            <form method="get" class="form-panel gastos-filtro">
                <input type="hidden" name="mes" value="<?= e($mes_txt) ?>">
                <label for="f-rubro" class="sr-only">Rubro</label>
                <select id="f-rubro" name="rubro" onchange="this.form.submit()">
                    <option value="">Todos los rubros</option>
                    <?php foreach ($rubros_todos as $r): ?>
                        <option value="<?= (int) $r['id_categorias_gasto'] ?>" <?= $rubro === (int) $r['id_categorias_gasto'] ? 'selected' : '' ?>><?= e($r['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript><button type="submit" class="btn-secundario">Filtrar</button></noscript>
                <a class="btn-secundario" href="gastos_csv.php?mes=<?= e($mes_txt) ?>">Descargar para Excel</a>
            </form>
        </section>

        <section class="resumen-gastos">
            <div class="cifra">
                <span>Gastado en <?= e(mb_strtolower(MESES[(int) $inicio->format('n')])) ?></span>
                <strong><?= e(pesos($total_mes)) ?></strong>
                <?php if ($total_anterior > 0): $var = ($total_mes - $total_anterior) / $total_anterior * 100; ?>
                    <small class="<?= $var > 0 ? 'sube' : 'baja' ?>"><?= $var > 0 ? '▲' : '▼' ?> <?= e(number_format(abs($var), 0, ',', '.')) ?> % que <?= e(mb_strtolower(MESES[(int) $ant_ini->format('n')])) ?> (<?= e(pesos($total_anterior)) ?>)</small>
                <?php endif; ?>
            </div>
            <div class="cifra">
                <span>Cobrado en el mes</span>
                <strong><?= e(pesos($cobrado_mes)) ?></strong>
                <small>Señas y pagos registrados (Mercado Pago, efectivo, transferencia)</small>
            </div>
            <div class="cifra">
                <span>Ganancia del mes (cobrado − gastado)</span>
                <strong class="<?= $cobrado_mes - $total_mes < 0 ? 'negativo' : 'positivo' ?>"><?php $dif = $cobrado_mes - $total_mes; ?><?= ($dif < 0 ? '− ' : '') . e(pesos(abs($dif))) ?></strong>
                <small>Para que sea exacta, registrá cada pago en la cuenta corriente de la clienta.</small>
            </div>
        </section>

        <div class="gastos-grid">
            <section class="tarjeta" id="form-gasto">
                <h2><?= $editar ? 'Editar gasto' : 'Cargar un gasto' ?></h2>
                <?php if ($errores): ?>
                    <div class="flash flash-error" role="alert"><strong>Revisá estos datos:</strong>
                        <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="post" action="guardar_gasto.php" class="form-panel" novalidate>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" value="<?= (int) $editar ?>">
                    <div class="fila">
                        <div class="campo">
                            <label for="g-fecha">Fecha</label>
                            <input type="date" id="g-fecha" name="fecha" max="<?= date('Y-m-d') ?>" value="<?= e($v('fecha', $fecha_def)) ?>" required>
                        </div>
                        <div class="campo">
                            <label for="g-rubro">Rubro</label>
                            <select id="g-rubro" name="id_categorias_gasto" required>
                                <option value="">— Elegí uno —</option>
                                <?php $sel = (int) $v('id_categorias_gasto', (string) ($rubro ?: 0));
                                foreach ($rubros_todos as $r): if ((int) $r['activo'] !== 1 && (int) $r['id_categorias_gasto'] !== $sel) { continue; } ?>
                                    <option value="<?= (int) $r['id_categorias_gasto'] ?>" <?= $sel === (int) $r['id_categorias_gasto'] ? 'selected' : '' ?>><?= e($r['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="campo">
                        <label for="g-trabajo">¿Para qué trabajo? (opcional)</label>
                        <select id="g-trabajo" name="id_solicitudes_presupuesto">
                            <option value="">— Gasto general (luz, alquiler, máquina…) —</option>
                            <?php $trab_sel = (int) $v('id_solicitudes_presupuesto', (string) (filter_input(INPUT_GET, 'trabajo', FILTER_VALIDATE_INT) ?: 0));
                            foreach ($trabajos_sel as $tr): ?>
                                <option value="<?= (int) $tr['id'] ?>" <?= $trab_sel === (int) $tr['id'] ? 'selected' : '' ?>><?= e(texto_trabajo($tr)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="ayuda">Si lo compraste para una prenda en particular, elegila: así ves cuánto te costó hacerla.</div>
                    </div>
                    <div class="campo">
                        <label for="g-desc">¿Qué compraste o pagaste?</label>
                        <input type="text" id="g-desc" name="descripcion" maxlength="<?= GASTO_DESCRIPCION_MAX ?>" value="<?= e($v('descripcion')) ?>" placeholder="Ej: Satén azul, elástico de 3 cm, factura de luz" required>
                    </div>
                    <div class="fila">
                        <div class="campo">
                            <label for="g-monto">Monto ($)</label>
                            <input type="text" id="g-monto" name="monto" inputmode="decimal" value="<?= e($v('monto')) ?>" placeholder="Ej: 27000" required>
                        </div>
                        <div class="campo">
                            <label for="g-cant">Cantidad (opcional)</label>
                            <input type="text" id="g-cant" name="cantidad" maxlength="<?= GASTO_CANTIDAD_MAX ?>" value="<?= e($v('cantidad')) ?>" placeholder="Ej: 3 m, 2 rollos">
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
                            <datalist id="proveedores">
                                <?php foreach ($pdo->query("SELECT DISTINCT proveedor FROM gastos WHERE proveedor <> '' ORDER BY proveedor LIMIT 50") as $pr): ?>
                                    <option value="<?= e($pr['proveedor']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    <div class="botonera">
                        <button type="submit" class="btn-guardar"><?= $editar ? 'Guardar cambios' : 'Agregar gasto' ?></button>
                        <?php if ($editar): ?><a class="btn-secundario" href="<?= e($url(['editar' => null])) ?>">Cancelar</a><?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="tarjeta">
                <h2>Por rubro</h2>
                <?php if (!$por_rubro): ?>
                    <p class="sin-acciones">No hay gastos en este mes.</p>
                <?php else: ?>
                    <ul class="barras-rubro">
                        <?php foreach ($por_rubro as $f): $pct = $total_mes > 0 ? (float) $f['total'] / $total_mes * 100 : 0; ?>
                            <li>
                                <a href="<?= e($url(['rubro' => (int) $f['id'], 'editar' => null])) ?>" class="<?= $rubro === (int) $f['id'] ? 'activo' : '' ?>">
                                    <span class="rubro-nombre"><?= e($f['nombre']) ?> <small>(<?= (int) $f['n'] ?>)</small></span>
                                    <span class="rubro-monto"><?= e(pesos((float) $f['total'])) ?> · <?= e(number_format($pct, 0, ',', '.')) ?> %</span>
                                    <span class="barra"><span style="width:<?= max(2, round($pct, 1)) ?>%"></span></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>

        <?php if ($trabajos_mes): ?>
        <section class="tarjeta">
            <h2>Trabajos con gastos este mes</h2>
            <p class="ayuda" style="margin:-6px 0 12px">Cuánto te costó cada prenda y cuánto te queda. El gastado es el total del trabajo (de todos los meses).</p>
            <div class="tabla-scroll">
                <table class="tabla-tarifas">
                    <thead><tr><th>Trabajo</th><th class="num">Precio</th><th class="num">Gastado</th><th class="num">Ganancia</th></tr></thead>
                    <tbody>
                    <?php foreach ($trabajos_mes as $tm): ?>
                        <tr>
                            <td><a href="trabajo.php?id=<?= (int) $tm['id'] ?>"><?= e($tm['tipo_prenda']) ?></a> <span class="ref-catalogo" style="display:inline">· <?= e($tm['cliente']) ?></span></td>
                            <td class="num"><?= e(pesos($tm['precio'])) ?></td>
                            <td class="num"><?= e(pesos($tm['total_gastos'])) ?></td>
                            <td class="num"><strong class="<?= $tm['ganancia'] < 0 ? 'negativo' : 'positivo' ?>"><?= ($tm['ganancia'] < 0 ? '− ' : '') . e(pesos(abs($tm['ganancia']))) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <section class="tarjeta">
            <div class="pedido-cabeza">
                <h2>Gastos de <?= e(mb_strtolower($nombre_mes($inicio))) ?><?= $rubro ? ' · ' . e((string) array_column($rubros_todos, 'nombre', 'id_categorias_gasto')[$rubro]) : '' ?></h2>
                <?php if ($rubro): ?><a class="btn-link" href="<?= e($url(['rubro' => null, 'editar' => null])) ?>">Ver todos los rubros</a><?php endif; ?>
            </div>
            <?php if (!$gastos): ?>
                <p class="sin-acciones">No hay gastos cargados<?= $rubro ? ' de este rubro' : '' ?> en este mes.</p>
            <?php else: ?>
                <div class="tabla-scroll">
                    <table class="tabla-tarifas tabla-gastos">
                        <thead><tr><th>Fecha</th><th>Rubro</th><th>Detalle</th><th>Dónde</th><th>Pago</th><th class="num">Monto</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($gastos as $g): $idg = (int) $g['id_gastos']; ?>
                            <tr class="<?= $editar === $idg ? 'fila-editando' : '' ?>">
                                <td><?= DateTime::createFromFormat('!Y-m-d', $g['fecha'])->format('d/m') ?></td>
                                <td><?= e($g['rubro']) ?></td>
                                <td><?= e($g['descripcion']) ?><?= $g['cantidad'] !== '' ? ' <span class="ref-catalogo" style="display:inline">· ' . e($g['cantidad']) . '</span>' : '' ?>
                                    <?php if ($g['id_solicitudes_presupuesto'] !== null): ?><br><a class="gasto-trabajo" href="trabajo.php?id=<?= (int) $g['id_solicitudes_presupuesto'] ?>">✂ <?= e($g['trabajo_prenda'] . ' — ' . $g['trabajo_cliente']) ?></a><?php endif; ?></td>
                                <td><?= e($g['proveedor']) ?></td>
                                <td><?= e(GASTO_MEDIOS[$g['medio_pago']] ?? '') ?></td>
                                <td class="num"><strong><?= e(pesos((float) $g['monto'])) ?></strong></td>
                                <td class="acciones-gasto">
                                    <a class="btn-accion accion-presupuestar" href="<?= e($url(['editar' => $idg])) ?>#form-gasto">Editar</a>
                                    <form method="post" action="guardar_gasto.php" onsubmit="return confirm('¿Eliminar este gasto?');">
                                        <input type="hidden" name="token" value="<?= e($token) ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?= $idg ?>">
                                        <button type="submit" class="btn-accion accion-5">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr><td colspan="5">Total<?= $rubro ? ' del rubro' : '' ?></td><td class="num"><strong><?= e(pesos(array_sum(array_map(fn($g) => (float) $g['monto'], $gastos)))) ?></strong></td><td></td></tr></tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="tarjeta">
            <h2>Rubros</h2>
            <p class="ayuda" style="margin:-6px 0 12px">Sirven para ordenar los gastos. Un rubro con gastos no se puede borrar, pero sí ocultar (deja de aparecer al cargar).</p>
            <div class="rubros-lista">
                <?php foreach ($rubros_todos as $r): $idr = (int) $r['id_categorias_gasto']; ?>
                    <div class="rubro-chip <?= (int) $r['activo'] === 1 ? '' : 'oculto' ?>">
                        <span><?= e($r['nombre']) ?></span>
                        <form method="post" action="guardar_gasto.php">
                            <input type="hidden" name="token" value="<?= e($token) ?>">
                            <input type="hidden" name="id" value="<?= $idr ?>">
                            <input type="hidden" name="mes" value="<?= e($mes_txt) ?>">
                            <button type="submit" name="accion" value="rubro_alternar" title="<?= (int) $r['activo'] === 1 ? 'Ocultar' : 'Mostrar' ?>"><?= (int) $r['activo'] === 1 ? 'Ocultar' : 'Mostrar' ?></button>
                            <?php if ((int) $r['usados'] === 0): ?>
                                <button type="submit" name="accion" value="rubro_eliminar" onclick="return confirm('¿Eliminar el rubro «<?= e(addslashes($r['nombre'])) ?>»?');">Eliminar</button>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="post" action="guardar_gasto.php" class="form-panel rubro-nuevo">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="accion" value="rubro_crear">
                <input type="hidden" name="mes" value="<?= e($mes_txt) ?>">
                <label for="r-nombre" class="sr-only">Nombre del rubro nuevo</label>
                <input type="text" id="r-nombre" name="nombre" maxlength="<?= GASTO_CATEGORIA_MAX ?>" placeholder="Rubro nuevo (ej: Publicidad)" required>
                <button type="submit" class="btn-secundario">+ Agregar rubro</button>
            </form>
        </section>
    </main>
</body>
</html>
