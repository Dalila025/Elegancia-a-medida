<?php
declare(strict_types=1);

/**
 * Panel de la modista — Elegancia a Medida
 *
 * Lista las solicitudes de presupuesto, permite filtrarlas, cargar el
 * presupuesto (fecha de entrega, plazo y monto) y cambiar su estado.
 * Sin autenticación (pendiente para etapas posteriores).
 *
 * Tablas: solicitudes_presupuesto, tipos_servicio, estados_solicitud,
 *         presupuestos.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';

iniciar_sesion();

$token = token_modista();
$flash = tomar_flash();

require_once __DIR__ . '/conexion.php';

// Solo la modista con sesión iniciada (panel_login.php).
$usuario = requerir_modista($pdo);

// Filtro opcional por estado (GET)
$filtro_estado = isset($_GET['estado']) ? (int) $_GET['estado'] : 0;
if (!array_key_exists($filtro_estado, ESTADOS)) {
    $filtro_estado = 0;
}

// Búsqueda opcional por nombre (GET)
$busqueda = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';


// -------------------------------------------------
// Consulta
// -------------------------------------------------
$sql = "
    SELECT
        s.id_solicitudes_presupuesto,
        s.codigo_seguimiento,
        s.nombre,
        s.telefono,
        t.nombre          AS servicio,
        s.tipo_prenda,
        pr.nombre AS producto_ref,
        s.descripcion,
        s.fecha_requerida,
        s.fecha_solicitud,
        s.id_estados_solicitud,
        s.id_clientes,
        p.fecha_respuesta_cliente,
        p.comentario_cliente,
        p.fecha_entrega,
        p.plazo_dias,
        p.monto_total,
        p.recargo_porcentaje,
        (SELECT n.leida FROM notificaciones n
          WHERE n.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
          ORDER BY n.id_notificaciones DESC LIMIT 1) AS ultimo_aviso_leido
    FROM solicitudes_presupuesto s
    INNER JOIN tipos_servicio t ON t.id_tipos_servicio = s.id_tipos_servicio
    LEFT  JOIN presupuestos   p ON p.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
    LEFT  JOIN productos      pr ON pr.id_productos = s.id_productos
";

$params = [];
$condiciones = [];

if ($filtro_estado > 0) {
    $condiciones[] = 's.id_estados_solicitud = :estado';
    $params[':estado'] = $filtro_estado;
}

if ($busqueda !== '') {
    $condiciones[] = 's.nombre LIKE :busqueda';
    $params[':busqueda'] = '%' . $busqueda . '%';
}

if ($condiciones) {
    $sql .= ' WHERE ' . implode(' AND ', $condiciones);
}

$sql .= ' ORDER BY s.fecha_solicitud DESC, s.id_solicitudes_presupuesto DESC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $solicitudes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error al consultar solicitudes: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudieron cargar las solicitudes.');
}

// Contadores por estado (para los badges del filtro)
$conteos = array_fill_keys(array_keys(ESTADOS), 0);
try {
    $stmtCont = $pdo->query('SELECT id_estados_solicitud, COUNT(*) AS total FROM solicitudes_presupuesto GROUP BY id_estados_solicitud');
    foreach ($stmtCont->fetchAll() as $fila) {
        $est = (int) $fila['id_estados_solicitud'];
        if (isset($conteos[$est])) {
            $conteos[$est] = (int) $fila['total'];
        }
    }
} catch (PDOException $e) {
    // No crítico; se muestran en 0
}

$total_general = array_sum($conteos);

// Respuestas de clientes de los últimos 2 días (aviso arriba del panel).
$respuestas_recientes = ['aceptaron' => 0, 'rechazaron' => 0];
try {
    $fila = $pdo->query(
        'SELECT SUM(s.id_estados_solicitud <> ' . ESTADO_NO_ACEPTADO . ') AS aceptaron,
                SUM(s.id_estados_solicitud =  ' . ESTADO_NO_ACEPTADO . ') AS rechazaron
           FROM presupuestos p
           INNER JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = p.id_solicitudes_presupuesto
          WHERE p.fecha_respuesta_cliente >= NOW() - INTERVAL 2 DAY'
    )->fetch();
    $respuestas_recientes = ['aceptaron' => (int) $fila['aceptaron'], 'rechazaron' => (int) $fila['rechazaron']];
} catch (PDOException $e) {
    // No crítico
}
$pagina_actual = 'solicitudes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Modista · Elegancia a Medida</title>
    <link rel="stylesheet" href="css/modista.css">
    <link rel="stylesheet" href="css/panel.css">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container">
        <!-- Filtros y búsqueda -->
        <section class="toolbar">
            <form method="get" class="filtros" id="form-filtros">
                <div class="filtros-estados">
                    <a href="modista.php<?= $busqueda !== '' ? '?q=' . urlencode($busqueda) : '' ?>"
                       class="chip <?= $filtro_estado === 0 ? 'activo' : '' ?>">
                        Todas <span class="badge"><?= $total_general ?></span>
                    </a>
                    <?php foreach (ESTADOS as $cod => $nombreEstado): ?>
                        <a href="modista.php?estado=<?= $cod ?><?= $busqueda !== '' ? '&q=' . urlencode($busqueda) : '' ?>"
                           class="chip estado-<?= $cod ?> <?= $filtro_estado === $cod ? 'activo' : '' ?>">
                            <?= e($nombreEstado) ?>
                            <span class="badge"><?= $conteos[$cod] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="busqueda">
                    <input type="search" name="q" id="input-busqueda"
                           placeholder="Buscar por nombre…"
                           value="<?= e($busqueda) ?>" autocomplete="off">
                    <?php if ($filtro_estado > 0): ?>
                        <input type="hidden" name="estado" value="<?= $filtro_estado ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn-primary">Buscar</button>
                    <?php if ($busqueda !== '' || $filtro_estado > 0): ?>
                        <a href="modista.php" class="btn-link">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <?php if ($respuestas_recientes['aceptaron'] + $respuestas_recientes['rechazaron'] > 0): ?>
            <div class="flash flash-info">
                <strong>Respuestas de clientes (últimas 48 h):</strong>
                <?php if ($respuestas_recientes['aceptaron']): ?>
                    <a href="modista.php?estado=<?= ESTADO_EN_PROCESO ?>"><?= $respuestas_recientes['aceptaron'] ?> aceptaron</a>
                <?php endif; ?>
                <?php if ($respuestas_recientes['aceptaron'] && $respuestas_recientes['rechazaron']): ?> · <?php endif; ?>
                <?php if ($respuestas_recientes['rechazaron']): ?>
                    <a href="modista.php?estado=<?= ESTADO_NO_ACEPTADO ?>"><?= $respuestas_recientes['rechazaron'] ?> no aceptaron</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($flash): ?>
            <div class="flash <?= $flash['tipo'] === 'ok' ? 'flash-ok' : 'flash-error' ?>" role="status">
                <?= e($flash['mensaje']) ?>
            </div>
        <?php endif; ?>

        <!-- Resumen -->
        <p class="resumen">
            Mostrando <strong><?= count($solicitudes) ?></strong>
            solicitud<?= count($solicitudes) === 1 ? '' : 'es' ?>
            <?php if ($filtro_estado > 0): ?>
                · filtro: <em><?= e(ESTADOS[$filtro_estado]) ?></em>
            <?php endif; ?>
            <?php if ($busqueda !== ''): ?>
                · búsqueda: <em>“<?= e($busqueda) ?>”</em>
            <?php endif; ?>
        </p>

        <!-- Tabla -->
        <section class="tabla-wrapper">
            <?php if (empty($solicitudes)): ?>
                <div class="vacio">
                    <p>No hay solicitudes que coincidan con los filtros.</p>
                </div>
            <?php else: ?>
                <table class="tabla-solicitudes" id="tabla-solicitudes">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Servicio</th>
                            <th>Prenda</th>
                            <th>Descripción</th>
                            <th>Pide para</th>
                            <th>Solicitado</th>
                            <th>Presupuesto</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $s): ?>
                            <?php
                            $id        = (int) $s['id_solicitudes_presupuesto'];
                            $estadoCod = (int) $s['id_estados_solicitud'];
                            $estadoTxt = ESTADOS[$estadoCod] ?? 'Desconocido';
                            $fechaReq  = DateTime::createFromFormat('!Y-m-d', $s['fecha_requerida']);
                            $fechaSol  = new DateTime($s['fecha_solicitud']);
                            $fechaEnt  = $s['fecha_entrega'] ? DateTime::createFromFormat('!Y-m-d', $s['fecha_entrega']) : null;
                            $tieneProp = $fechaEnt !== null;
                            ?>
                            <tr data-estado="<?= $estadoCod ?>">
                                <td class="id"><?= $id ?></td>
                                <td class="nombre">
                                    <?= e($s['nombre']) ?>
                                    <span class="codigo-seg" title="Código de seguimiento del cliente"><?= e(formatear_codigo($s['codigo_seguimiento'])) ?></span>
                                </td>
                                <td class="telefono">
                                    <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $s['telefono'])) ?>">
                                        <?= e($s['telefono']) ?>
                                    </a>
                                </td>
                                <td><?= e($s['servicio']) ?></td>
                                <td>
                                    <?= e($s['tipo_prenda']) ?>
                                    <?php if ($s['producto_ref'] !== null): ?>
                                        <span class="ref-catalogo" title="Prenda del catálogo que eligió como referencia">Ref. catálogo: <?= e($s['producto_ref']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="descripcion" title="<?= e($s['descripcion']) ?>">
                                    <?= e(mb_strimwidth($s['descripcion'], 0, 80, '…')) ?>
                                </td>
                                <td class="fecha">
                                    <?= $fechaReq ? $fechaReq->format('d/m/Y') : e($s['fecha_requerida']) ?>
                                </td>
                                <td class="fecha">
                                    <?= $fechaSol->format('d/m/Y H:i') ?>
                                </td>
                                <td class="presu-celda">
                                    <?php if ($tieneProp): ?>
                                        <strong><?= pesos((float) $s['monto_total']) ?></strong><br>
                                        <span class="<?= $fechaReq && $fechaEnt > $fechaReq ? 'tarde' : '' ?>"
                                              title="<?= $fechaReq && $fechaEnt > $fechaReq ? 'Posterior a la fecha que pidió el cliente' : '' ?>">
                                            Entrega <?= $fechaEnt->format('d/m/Y') ?>
                                        </span><br>
                                        <?= (int) $s['plazo_dias'] ?> días
                                        <?php if ((float) $s['recargo_porcentaje'] != 0): ?>
                                            · <?= porcentaje((float) $s['recargo_porcentaje']) ?>
                                        <?php endif; ?>
                                        <?php if ($s['fecha_respuesta_cliente']): ?>
                                            <?php $acepto = $estadoCod !== ESTADO_NO_ACEPTADO; ?>
                                            <span class="resp-cliente <?= $acepto ? 'si' : 'no' ?>">
                                                <?= $acepto ? '✓ Aceptó' : '✗ No aceptó' ?>
                                                el <?= (new DateTime($s['fecha_respuesta_cliente']))->format('d/m') ?>
                                            </span>
                                            <?php if (!$acepto && trim((string) $s['comentario_cliente']) !== ''): ?>
                                                <span class="resp-comentario" title="<?= e($s['comentario_cliente']) ?>">“<?= e(mb_strimwidth($s['comentario_cliente'], 0, 60, '…')) ?>”</span>
                                            <?php endif; ?>
                                        <?php elseif ($estadoCod === ESTADO_PRESUPUESTADO): ?>
                                            <span class="resp-cliente espera">Esperando respuesta del cliente</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="sin-acciones">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="estado-badge estado-<?= $estadoCod ?>">
                                        <?= e($estadoTxt) ?>
                                    </span>
                                    <?php if ($s['ultimo_aviso_leido'] !== null): ?>
                                        <span class="aviso-cliente <?= (int) $s['ultimo_aviso_leido'] === 1 ? 'visto' : '' ?>">
                                            <?= (int) $s['ultimo_aviso_leido'] === 1 ? '✓ El cliente vio el aviso' : 'Aviso sin ver' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $acciones       = transiciones_permitidas($estadoCod);
                                    $puedePresupuestar = in_array($estadoCod, ESTADOS_PRESUPUESTABLES, true);
                                    ?>
                                    <?php if (empty($acciones) && !$puedePresupuestar): ?>
                                        <span class="sin-acciones">—</span>
                                    <?php else: ?>
                                        <div class="acciones">
                                            <?php if ($puedePresupuestar): ?>
                                                <a href="presupuesto.php?id=<?= $id ?>" class="btn-accion accion-presupuestar">
                                                    <?= $tieneProp ? 'Editar presupuesto' : 'Presupuestar' ?>
                                                </a>
                                            <?php endif; ?>
                                            <?php foreach ($acciones as $nuevo => $textoBoton): ?>
                                                <form method="post" action="actualizar_estado.php" class="form-estado"
                                                      data-confirmar="<?= in_array($nuevo, ESTADOS_CON_CONFIRMACION, true) ? '1' : '0' ?>"
                                                      data-texto="<?= e($textoBoton . ': solicitud #' . $id . ' de ' . $s['nombre'] . '. ¿Confirmar?') ?>">
                                                    <input type="hidden" name="token" value="<?= e($token) ?>">
                                                    <input type="hidden" name="id" value="<?= $id ?>">
                                                    <input type="hidden" name="estado_nuevo" value="<?= (int) $nuevo ?>">
                                                    <input type="hidden" name="volver_estado" value="<?= $filtro_estado ?>">
                                                    <input type="hidden" name="volver_q" value="<?= e($busqueda) ?>">
                                                    <button type="submit" class="btn-accion accion-<?= (int) $nuevo ?>">
                                                        <?= e($textoBoton) ?>
                                                    </button>
                                                </form>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <p>Elegancia a Medida · Panel de gestión (sin autenticación) · Ciclo 2026</p>
        </div>
    </footer>

    <script>
        // Confirmación antes de rechazar o cancelar.
        document.querySelectorAll('.form-estado').forEach(function (f) {
            f.addEventListener('submit', function (ev) {
                if (f.dataset.confirmar === '1' && !confirm(f.dataset.texto)) {
                    ev.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
