<?php
declare(strict_types=1);

/**
 * Tienda (Shop) — Elegancia a Medida
 *
 * Todas las prendas visibles, con filtros: categoría, tipo de prenda,
 * venta / alquiler, ofertas, temporada, búsqueda y orden.
 * Es la página a la que llevan el menú "Shop" / "Alquiler" y el buscador.
 *
 * Tablas: productos, categorias, tipos_producto.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/tarjeta_producto.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$cliente = cliente_logueado($pdo);
$flash   = tomar_flash_cliente();

// ---------- Filtros (GET) ----------
$f = [
    'categoria' => filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT) ?: 0,
    'tipo'      => filter_input(INPUT_GET, 'tipo', FILTER_VALIDATE_INT) ?: 0,
    'modalidad' => in_array($_GET['modalidad'] ?? '', ['venta', 'alquiler'], true) ? $_GET['modalidad'] : '',
    'ofertas'   => ($_GET['ofertas'] ?? '') === '1',
    'temporada' => ($_GET['temporada'] ?? '') === '1',
    'q'         => isset($_GET['q']) && is_string($_GET['q']) ? trim(mb_substr($_GET['q'], 0, 60)) : '',
    'orden'     => in_array($_GET['orden'] ?? '', ['precio_asc', 'precio_desc', 'nombre', 'nuevos'], true) ? $_GET['orden'] : '',
];

/** Enlace a la tienda con los filtros actuales y algunos cambiados (null = quitar). */
function url_tienda(array $cambios = []): string
{
    global $f;
    $p = array_merge([
        'categoria' => $f['categoria'] ?: null, 'tipo' => $f['tipo'] ?: null,
        'modalidad' => $f['modalidad'] ?: null, 'ofertas' => $f['ofertas'] ? '1' : null,
        'temporada' => $f['temporada'] ? '1' : null, 'q' => $f['q'] !== '' ? $f['q'] : null,
        'orden' => $f['orden'] ?: null,
    ], $cambios);
    $p = array_filter($p, fn($v) => $v !== null && $v !== '' && $v !== 0);
    return 'tienda.php' . ($p ? '?' . http_build_query($p) : '');
}

// Precio que se usa para ordenar (venta si se vende; si no, alquiler), con oferta.
$precio_orden = '(COALESCE(IF(p.en_venta = 1, p.precio_venta, NULL), p.precio_alquiler)
                 * (100 - IF(' . sql_oferta_vigente() . ', p.descuento_porcentaje, 0)) / 100)';

try {
    $sql = SQL_PRODUCTOS_VISIBLES;
    $params = [];
    if ($f['categoria']) { $sql .= ' AND p.id_categorias = :cat';      $params[':cat']  = $f['categoria']; }
    if ($f['tipo'])      { $sql .= ' AND p.id_tipos_producto = :tipo'; $params[':tipo'] = $f['tipo']; }
    if ($f['modalidad'] === 'venta')    { $sql .= ' AND p.en_venta = 1'; }
    if ($f['modalidad'] === 'alquiler') { $sql .= ' AND p.en_alquiler = 1'; }
    if ($f['ofertas'])   { $sql .= ' AND ' . sql_oferta_vigente(); }
    if ($f['temporada']) { $sql .= ' AND p.de_temporada = 1'; }
    if ($f['q'] !== '') {
        $sql .= ' AND (p.nombre LIKE :q1 OR p.descripcion LIKE :q2 OR c.nombre LIKE :q3 OR t.nombre LIKE :q4)';
        $params[':q1'] = $params[':q2'] = $params[':q3'] = $params[':q4'] = '%' . $f['q'] . '%';
    }
    $sql .= ' ORDER BY ' . [
        ''            => sql_oferta_vigente() . ' DESC, p.de_temporada DESC, p.nombre',
        'nuevos'      => 'p.fecha_alta DESC, p.id_productos DESC',
        'precio_asc'  => "$precio_orden IS NULL, $precio_orden ASC, p.nombre",
        'precio_desc' => "$precio_orden IS NULL, $precio_orden DESC, p.nombre",
        'nombre'      => 'p.nombre',
    ][$f['orden']];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll();

    // Opciones del costado, con cantidad de prendas
    $cats  = $pdo->query('SELECT c.id_categorias AS id, c.nombre, COUNT(*) AS n FROM productos p
                            INNER JOIN categorias c ON c.id_categorias = p.id_categorias AND c.activo = 1
                            INNER JOIN tipos_producto t ON t.id_tipos_producto = p.id_tipos_producto AND t.activo = 1
                           WHERE p.activo = 1 GROUP BY c.id_categorias, c.nombre ORDER BY c.nombre')->fetchAll();
    $tipos = $pdo->query('SELECT t.id_tipos_producto AS id, t.nombre, COUNT(*) AS n FROM productos p
                            INNER JOIN categorias c ON c.id_categorias = p.id_categorias AND c.activo = 1
                            INNER JOIN tipos_producto t ON t.id_tipos_producto = p.id_tipos_producto AND t.activo = 1
                           WHERE p.activo = 1 GROUP BY t.id_tipos_producto, t.nombre ORDER BY t.nombre')->fetchAll();
} catch (PDOException $e) {
    error_log('Error en la tienda: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo cargar la tienda. Intente nuevamente más tarde.');
}

$nombre_de = fn(array $lista, int $id) => array_column($lista, 'nombre', 'id')[$id] ?? '';

// Título según el filtro principal
$titulo = 'Tienda';
if ($f['modalidad'] === 'alquiler') { $titulo = 'Alquiler'; }
elseif ($f['modalidad'] === 'venta') { $titulo = 'Venta'; }
if ($f['ofertas'])   { $titulo = 'Ofertas'; }
if ($f['temporada']) { $titulo = 'Temporada'; }
if ($f['categoria'] && $nombre_de($cats, $f['categoria']) !== '') { $titulo .= ' · ' . $nombre_de($cats, $f['categoria']); }
if ($f['tipo'] && $nombre_de($tipos, $f['tipo']) !== '')          { $titulo .= ' · ' . $nombre_de($tipos, $f['tipo']); }

// Chips para quitar cada filtro activo
$activos = [];
if ($f['categoria']) { $activos[$nombre_de($cats, $f['categoria']) ?: 'Categoría'] = url_tienda(['categoria' => null]); }
if ($f['tipo'])      { $activos[$nombre_de($tipos, $f['tipo']) ?: 'Tipo'] = url_tienda(['tipo' => null]); }
if ($f['modalidad']) { $activos[$f['modalidad'] === 'venta' ? 'Venta' : 'Alquiler'] = url_tienda(['modalidad' => null]); }
if ($f['ofertas'])   { $activos['Ofertas'] = url_tienda(['ofertas' => null]); }
if ($f['temporada']) { $activos['Temporada'] = url_tienda(['temporada' => null]); }
if ($f['q'] !== '')  { $activos['«' . $f['q'] . '»'] = url_tienda(['q' => null]); }

$pagina_actual = 'tienda';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?> - Elegancia a Medida</title>
    <link rel="stylesheet" href="css/cliente.css">
    <link rel="stylesheet" href="css/sitio.css">
</head>
<body>
<?php require __DIR__ . '/encabezado_cliente.php'; ?>

<main class="pagina-sitio">
    <p class="migas"><a href="index.php">Inicio</a> / <?= e($titulo) ?></p>
    <h1><?= e($titulo) ?></h1>

    <?php if ($flash): ?><div class="alert alert-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div><?php endif; ?>

    <div class="tienda">
        <aside class="filtros-tienda" id="filtros-tienda" aria-label="Filtros">
            <h3>Modalidad</h3>
            <ul>
                <li><a href="<?= e(url_tienda(['modalidad' => null])) ?>" class="<?= $f['modalidad'] === '' ? 'activo' : '' ?>">Todo</a></li>
                <li><a href="<?= e(url_tienda(['modalidad' => 'venta'])) ?>" class="<?= $f['modalidad'] === 'venta' ? 'activo' : '' ?>">Venta</a></li>
                <li><a href="<?= e(url_tienda(['modalidad' => 'alquiler'])) ?>" class="<?= $f['modalidad'] === 'alquiler' ? 'activo' : '' ?>">Alquiler</a></li>
            </ul>
            <h3>Categoría</h3>
            <ul>
                <?php foreach ($cats as $c): $sel = $f['categoria'] === (int) $c['id']; ?>
                    <li><a href="<?= e(url_tienda(['categoria' => $sel ? null : (int) $c['id']])) ?>" class="<?= $sel ? 'activo' : '' ?>">
                        <?= e($c['nombre']) ?> <span class="contar"><?= (int) $c['n'] ?></span></a></li>
                <?php endforeach; ?>
            </ul>
            <h3>Tipo de prenda</h3>
            <ul>
                <?php foreach ($tipos as $t): $sel = $f['tipo'] === (int) $t['id']; ?>
                    <li><a href="<?= e(url_tienda(['tipo' => $sel ? null : (int) $t['id']])) ?>" class="<?= $sel ? 'activo' : '' ?>">
                        <?= e($t['nombre']) ?> <span class="contar"><?= (int) $t['n'] ?></span></a></li>
                <?php endforeach; ?>
            </ul>
            <h3>Destacados</h3>
            <ul>
                <li><a href="<?= e(url_tienda(['ofertas' => $f['ofertas'] ? null : '1'])) ?>" class="<?= $f['ofertas'] ? 'activo' : '' ?>">Ofertas</a></li>
                <li><a href="<?= e(url_tienda(['temporada' => $f['temporada'] ? null : '1'])) ?>" class="<?= $f['temporada'] ? 'activo' : '' ?>">Temporada</a></li>
            </ul>
        </aside>

        <section aria-label="Prendas">
            <div class="tienda-barra">
                <button type="button" class="btn-pill borde btn-filtros" onclick="document.getElementById('filtros-tienda').classList.toggle('abierto')">Filtros</button>
                <form method="get" action="tienda.php" role="search">
                    <?php foreach (['categoria', 'tipo', 'modalidad', 'orden'] as $k): if ($f[$k]): ?>
                        <input type="hidden" name="<?= $k ?>" value="<?= e((string) $f[$k]) ?>">
                    <?php endif; endforeach; ?>
                    <?php if ($f['ofertas']): ?><input type="hidden" name="ofertas" value="1"><?php endif; ?>
                    <?php if ($f['temporada']): ?><input type="hidden" name="temporada" value="1"><?php endif; ?>
                    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar en la tienda…" aria-label="Buscar en la tienda">
                    <button type="submit" class="btn-pill">Buscar</button>
                </form>
                <form method="get" action="tienda.php">
                    <?php foreach (['categoria', 'tipo', 'modalidad', 'q'] as $k): if ($f[$k]): ?>
                        <input type="hidden" name="<?= $k ?>" value="<?= e((string) $f[$k]) ?>">
                    <?php endif; endforeach; ?>
                    <?php if ($f['ofertas']): ?><input type="hidden" name="ofertas" value="1"><?php endif; ?>
                    <?php if ($f['temporada']): ?><input type="hidden" name="temporada" value="1"><?php endif; ?>
                    <select name="orden" aria-label="Ordenar" onchange="this.form.submit()">
                        <option value="">Ordenar: destacados</option>
                        <option value="nuevos" <?= $f['orden'] === 'nuevos' ? 'selected' : '' ?>>Más nuevos</option>
                        <option value="precio_asc" <?= $f['orden'] === 'precio_asc' ? 'selected' : '' ?>>Menor precio</option>
                        <option value="precio_desc" <?= $f['orden'] === 'precio_desc' ? 'selected' : '' ?>>Mayor precio</option>
                        <option value="nombre" <?= $f['orden'] === 'nombre' ? 'selected' : '' ?>>Nombre (A-Z)</option>
                    </select>
                    <noscript><button type="submit" class="btn-pill">Ordenar</button></noscript>
                </form>
            </div>

            <?php if ($activos): ?>
                <div class="filtros-activos">
                    <?php foreach ($activos as $etiqueta => $url): ?>
                        <a href="<?= e($url) ?>" title="Quitar filtro"><?= e($etiqueta) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p class="sec-sub" style="margin-bottom:14px"><?= count($productos) ?> prenda<?= count($productos) === 1 ? '' : 's' ?></p>

            <?php if ($productos): ?>
                <div class="productos"><?php foreach ($productos as $p) { tarjeta_producto($p); } ?></div>
            <?php else: ?>
                <div class="vacio-grande">
                    <p>No hay prendas con esos filtros.</p>
                    <a class="btn-pill" href="tienda.php">Ver toda la tienda</a>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php require __DIR__ . '/pie_cliente.php'; ?>
<script src="js/sitio.js"></script>
<?php if ($cliente): ?><script src="js/notificaciones.js"></script><?php endif; ?>
</body>
</html>
