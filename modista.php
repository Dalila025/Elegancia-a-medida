<?php
declare(strict_types=1);

/**
 * Vista mínima para la modista — Elegancia a Medida
 *
 * Consulta las solicitudes de presupuesto registradas.
 * Sin autenticación (pendiente para etapas posteriores).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexion.php';

// Filtro opcional por estado (GET)
$filtro_estado = isset($_GET['estado']) ? (int) $_GET['estado'] : 0;

// Búsqueda opcional por nombre (GET)
$busqueda = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

// -------------------------------------------------
// Consulta
// -------------------------------------------------
$sql = "
    SELECT
        id,
        nombre,
        telefono,
        tipo_servicio,
        tipo_prenda,
        descripcion,
        fecha_requerida,
        fecha_solicitud,
        estado
    FROM solicitudes_presupuesto
";

$params = [];
$condiciones = [];

if ($filtro_estado > 0 && array_key_exists($filtro_estado, ESTADOS)) {
    $condiciones[] = 'estado = :estado';
    $params[':estado'] = $filtro_estado;
}

if ($busqueda !== '') {
    $condiciones[] = 'nombre LIKE :busqueda';
    $params[':busqueda'] = '%' . $busqueda . '%';
}

if ($condiciones) {
    $sql .= ' WHERE ' . implode(' AND ', $condiciones);
}

$sql .= ' ORDER BY fecha_solicitud DESC';

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
    $stmtCont = $pdo->query('SELECT estado, COUNT(*) AS total FROM solicitudes_presupuesto GROUP BY estado');
    foreach ($stmtCont->fetchAll() as $fila) {
        $est = (int) $fila['estado'];
        if (isset($conteos[$est])) {
            $conteos[$est] = (int) $fila['total'];
        }
    }
} catch (PDOException $e) {
    // No crítico; se muestran en 0
}

$total_general = array_sum($conteos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Modista · Elegancia a Medida</title>
    <link rel="stylesheet" href="css/modista.css">
</head>
<body>
    <header class="header">
        <div class="container header-inner">
            <div class="brand">
                <span class="brand-mark">E</span>
                <div>
                    <h1>Elegancia a Medida</h1>
                    <p class="subtitle">Panel de la modista · Solicitudes de presupuesto</p>
                </div>
            </div>
            <a href="index.php" class="btn-outline">← Formulario de clientes</a>
        </div>
    </header>

    <main class="container">
        <!-- Filtros y búsqueda -->
        <section class="toolbar">
            <form method="get" class="filtros" id="form-filtros">
                <div class="filtros-estados">
                    <a href="modista.php"
                       class="chip <?= $filtro_estado === 0 ? 'activo' : '' ?>">
                        Todas <span class="badge"><?= $total_general ?></span>
                    </a>
                    <?php foreach (ESTADOS as $cod => $nombreEstado): ?>
                        <a href="modista.php?estado=<?= $cod ?><?= $busqueda !== '' ? '&q=' . urlencode($busqueda) : '' ?>"
                           class="chip estado-<?= $cod ?> <?= $filtro_estado === $cod ? 'activo' : '' ?>">
                            <?= htmlspecialchars($nombreEstado) ?>
                            <span class="badge"><?= $conteos[$cod] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="busqueda">
                    <input type="search"
                           name="q"
                           id="input-busqueda"
                           placeholder="Buscar por nombre…"
                           value="<?= htmlspecialchars($busqueda) ?>"
                           autocomplete="off">
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

        <!-- Resumen -->
        <p class="resumen">
            Mostrando <strong><?= count($solicitudes) ?></strong>
            solicitud<?= count($solicitudes) === 1 ? '' : 'es' ?>
            <?php if ($filtro_estado > 0): ?>
                · filtro: <em><?= htmlspecialchars(ESTADOS[$filtro_estado]) ?></em>
            <?php endif; ?>
            <?php if ($busqueda !== ''): ?>
                · búsqueda: <em>“<?= htmlspecialchars($busqueda) ?>”</em>
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
                            <th>Fecha requerida</th>
                            <th>Solicitado</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $s): ?>
                            <?php
                            $estadoCod = (int) $s['estado'];
                            $estadoTxt = ESTADOS[$estadoCod] ?? 'Desconocido';
                            $fechaReq  = DateTime::createFromFormat('Y-m-d', $s['fecha_requerida']);
                            $fechaSol  = new DateTime($s['fecha_solicitud']);
                            ?>
                            <tr data-estado="<?= $estadoCod ?>">
                                <td class="id"><?= (int) $s['id'] ?></td>
                                <td class="nombre"><?= htmlspecialchars($s['nombre']) ?></td>
                                <td class="telefono">
                                    <a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $s['telefono'])) ?>">
                                        <?= htmlspecialchars($s['telefono']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($s['tipo_servicio']) ?></td>
                                <td><?= htmlspecialchars($s['tipo_prenda']) ?></td>
                                <td class="descripcion" title="<?= htmlspecialchars($s['descripcion']) ?>">
                                    <?= htmlspecialchars(mb_strimwidth($s['descripcion'], 0, 80, '…')) ?>
                                </td>
                                <td class="fecha">
                                    <?= $fechaReq ? $fechaReq->format('d/m/Y') : htmlspecialchars($s['fecha_requerida']) ?>
                                </td>
                                <td class="fecha">
                                    <?= $fechaSol->format('d/m/Y H:i') ?>
                                </td>
                                <td>
                                    <span class="estado-badge estado-<?= $estadoCod ?>">
                                        <?= htmlspecialchars($estadoTxt) ?>
                                    </span>
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
            <p>Elegancia a Medida · Vista de consulta (sin autenticación) · Ciclo 2026</p>
        </div>
    </footer>

    <script src="js/modista.js"></script>
</body>
</html>
