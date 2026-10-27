<?php
declare(strict_types=1);

/**
 * Catálogo: listado de productos (panel) — Elegancia a Medida
 *
 * La modista ve todos los productos (visibles y ocultos), los filtra,
 * y desde acá los agrega, edita, oculta/muestra o elimina.
 * Los cambios se procesan en guardar_producto.php.
 *
 * Tablas: productos, categorias.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario = requerir_modista($pdo);
$token   = token_modista();
$flash   = tomar_flash();

$filtro_cat = filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT) ?: 0;
$filtro_vis = isset($_GET['ver']) && in_array($_GET['ver'], ['visibles', 'ocultos', 'ofertas', 'temporada', 'alquiler'], true) ? $_GET['ver'] : '';

try {
    $categorias = obtener_categorias($pdo, false);

    $sql = 'SELECT p.id_productos, p.nombre, p.en_venta, p.precio_venta, p.en_alquiler, p.precio_alquiler,
                   p.descuento_porcentaje, p.oferta_hasta, p.de_temporada, p.imagen, p.activo,
                   c.nombre AS categoria, c.activo AS categoria_activa, t.nombre AS tipo, t.activo AS tipo_activo,
                   (SELECT COUNT(*) FROM solicitudes_presupuesto s WHERE s.id_productos = p.id_productos) AS pedidos
              FROM productos p
              INNER JOIN categorias c ON c.id_categorias = p.id_categorias
              INNER JOIN tipos_producto t ON t.id_tipos_producto = p.id_tipos_producto
             WHERE 1 = 1';
    $params = [];
    if ($filtro_cat > 0) {
        $sql .= ' AND p.id_categorias = :cat';
        $params[':cat'] = $filtro_cat;
    }
    $sql .= [
        ''          => '',
        'visibles'  => ' AND p.activo = 1',
        'ocultos'   => ' AND p.activo = 0',
        'ofertas'   => ' AND ' . sql_oferta_vigente(),
        'temporada' => ' AND p.de_temporada = 1',
        'alquiler'  => ' AND p.en_alquiler = 1',
    ][$filtro_vis];
    $sql .= ' ORDER BY c.nombre, p.nombre';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error al listar productos: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo cargar el catálogo.');
}

$pagina_actual = 'productos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Catálogo · Panel · Elegancia a Medida</title>
    <link rel="stylesheet" href="css/modista.css">
    <link rel="stylesheet" href="css/panel.css">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container">
        <?php if ($flash): ?>
            <div class="flash <?= $flash['tipo'] === 'ok' ? 'flash-ok' : 'flash-error' ?>" role="status"><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>

        <section class="toolbar">
            <form method="get" class="barra-catalogo">
                <div class="campo-filtro">
                    <label for="categoria">Categoría</label>
                    <select id="categoria" name="categoria" onchange="this.form.submit()">
                        <option value="0">Todas</option>
                        <?php foreach ($categorias as $idc => $nom): ?>
                            <option value="<?= $idc ?>" <?= $filtro_cat === $idc ? 'selected' : '' ?>><?= e($nom) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo-filtro">
                    <label for="ver">Mostrar</label>
                    <select id="ver" name="ver" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        <option value="visibles" <?= $filtro_vis === 'visibles' ? 'selected' : '' ?>>Visibles en el sitio</option>
                        <option value="ocultos"  <?= $filtro_vis === 'ocultos'  ? 'selected' : '' ?>>Ocultos</option>
                        <option value="ofertas"  <?= $filtro_vis === 'ofertas'  ? 'selected' : '' ?>>Con oferta vigente</option>
                        <option value="temporada" <?= $filtro_vis === 'temporada' ? 'selected' : '' ?>>De temporada</option>
                        <option value="alquiler" <?= $filtro_vis === 'alquiler' ? 'selected' : '' ?>>Se alquilan</option>
                    </select>
                </div>
                <noscript><button type="submit" class="btn-primary">Filtrar</button></noscript>
                <a href="producto.php" class="btn-primary btn-agregar">+ Agregar producto</a>
            </form>
        </section>

        <p class="resumen">
            <strong><?= count($productos) ?></strong> producto<?= count($productos) === 1 ? '' : 's' ?>
            <?php if (!$categorias): ?> · <a href="categorias.php">Primero creá una categoría</a><?php endif; ?>
        </p>

        <section class="tabla-wrapper">
            <?php if (!$productos): ?>
                <div class="vacio"><p>No hay productos con esos filtros.</p></div>
            <?php else: ?>
                <table class="tabla-solicitudes tabla-productos">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Producto</th>
                            <th>Categoría · tipo</th>
                            <th>Venta</th>
                            <th>Alquiler</th>
                            <th>Oferta / temporada</th>
                            <th>Pedidos</th>
                            <th>En el sitio</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($productos as $p):
                        $idp  = (int) $p['id_productos'];
                        $foto = url_imagen_producto($p['imagen']);
                        $visible = (int) $p['activo'] === 1 && (int) $p['categoria_activa'] === 1 && (int) $p['tipo_activo'] === 1;
                        $oferta  = oferta_vigente($p);
                    ?>
                        <tr class="<?= (int) $p['activo'] === 1 ? '' : 'fila-oculta' ?>">
                            <td>
                                <div class="mini-foto">
                                    <?php if ($foto): ?><img src="<?= e($foto) ?>" alt=""><?php else: ?><span><?= e(mb_substr($p['nombre'], 0, 1)) ?></span><?php endif; ?>
                                </div>
                            </td>
                            <td class="nombre"><?= e($p['nombre']) ?></td>
                            <td>
                                <?= e($p['categoria']) ?><?= (int) $p['categoria_activa'] === 1 ? '' : ' <small class="sin-acciones">(oculta)</small>' ?>
                                <span class="ref-catalogo"><?= e($p['tipo']) ?><?= (int) $p['tipo_activo'] === 1 ? '' : ' (oculto)' ?></span>
                            </td>
                            <td><?= (int) $p['en_venta'] === 1 ? e(precio_o_consultar($p['precio_venta'])) : '<span class="sin-acciones">No</span>' ?></td>
                            <td><?= (int) $p['en_alquiler'] === 1 ? e(precio_o_consultar($p['precio_alquiler'])) : '<span class="sin-acciones">No</span>' ?></td>
                            <td>
                                <?php if ($oferta): ?>
                                    <span class="estado-badge estado-5">−<?= (int) $p['descuento_porcentaje'] ?> %</span>
                                    <?php if ($p['oferta_hasta']): ?><span class="ref-catalogo">hasta <?= DateTime::createFromFormat('!Y-m-d', $p['oferta_hasta'])->format('d/m/Y') ?></span><?php endif; ?>
                                <?php elseif ((int) $p['descuento_porcentaje'] > 0): ?>
                                    <span class="sin-acciones">Oferta vencida</span>
                                <?php endif; ?>
                                <?php if ((int) $p['de_temporada'] === 1): ?><span class="estado-badge estado-6">Temporada</span><?php endif; ?>
                                <?php if (!$oferta && (int) $p['descuento_porcentaje'] === 0 && (int) $p['de_temporada'] === 0): ?><span class="sin-acciones">—</span><?php endif; ?>
                            </td>
                            <td><?= (int) $p['pedidos'] ?></td>
                            <td>
                                <span class="estado-badge <?= $visible ? 'estado-3' : 'estado-4' ?>"><?= $visible ? 'Visible' : 'Oculto' ?></span>
                            </td>
                            <td>
                                <div class="acciones">
                                    <a href="producto.php?id=<?= $idp ?>" class="btn-accion accion-presupuestar">Editar</a>
                                    <form method="post" action="guardar_producto.php">
                                        <input type="hidden" name="token" value="<?= e($token) ?>">
                                        <input type="hidden" name="id" value="<?= $idp ?>">
                                        <input type="hidden" name="accion" value="alternar">
                                        <button type="submit" class="btn-accion accion-1"><?= (int) $p['activo'] === 1 ? 'Ocultar' : 'Mostrar' ?></button>
                                    </form>
                                    <form method="post" action="guardar_producto.php"
                                          onsubmit="return confirm('¿Eliminar «<?= e(addslashes($p['nombre'])) ?>»? Se borra también su foto.');">
                                        <input type="hidden" name="token" value="<?= e($token) ?>">
                                        <input type="hidden" name="id" value="<?= $idp ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <button type="submit" class="btn-accion accion-5">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>

    <footer class="footer">
        <div class="container"><p>Elegancia a Medida · Panel de gestión · Ciclo 2026</p></div>
    </footer>
</body>
</html>
