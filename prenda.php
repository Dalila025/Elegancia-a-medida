<?php
declare(strict_types=1);

/**
 * Ficha de una prenda — Elegancia a Medida
 *
 * prenda.php?id=N : foto, descripción, precios de venta y/o alquiler,
 * agregar al carrito (con cantidad) y "la quiero a mi medida" (lleva al
 * formulario de presupuesto con la prenda como referencia).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/tarjeta_producto.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$cliente = cliente_logueado($pdo);
$flash   = tomar_flash_cliente();
$id      = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

try {
    $stmt = $pdo->prepare(SQL_PRODUCTOS_VISIBLES . ' AND p.id_productos = :id');
    $stmt->execute([':id' => $id]);
    $p = $stmt->fetch() ?: null;

    $relacionados = [];
    if ($p) {
        $stmt = $pdo->prepare(SQL_PRODUCTOS_VISIBLES . ' AND p.id_categorias = :cat AND p.id_productos <> :id
                               ORDER BY ' . sql_oferta_vigente() . ' DESC, p.fecha_alta DESC LIMIT 4');
        $stmt->execute([':cat' => $p['id_categorias'], ':id' => $id]);
        $relacionados = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log('Error en la ficha de prenda: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo cargar la prenda.');
}

if (!$p) {
    http_response_code(404);
}

$pagina_actual = 'tienda';
$foto = $p ? url_imagen_producto($p['imagen']) : null;
$desc = $p && oferta_vigente($p) ? (int) $p['descuento_porcentaje'] : 0;

/** Recuadro de compra o alquiler con su precio y el botón. */
function opcion_compra(array $p, string $modalidad): void
{
    $lista  = $modalidad === 'alquiler' ? $p['precio_alquiler'] : $p['precio_venta'];
    $actual = precio_actual($p, $modalidad);
    $rebajado = $actual !== null && $lista !== null && $actual < (float) $lista;
    ?>
    <div class="opcion-compra">
        <div>
            <h2><?= $modalidad === 'alquiler' ? 'Alquiler' : 'Venta' ?></h2>
            <span class="precio-grande <?= $rebajado ? 'rebajado' : '' ?>"><?= $actual === null ? 'A consultar' : e(pesos($actual)) ?></span>
            <?php if ($rebajado): ?><s><?= e(pesos((float) $lista)) ?></s><?php endif; ?>
        </div>
        <form method="post" action="carrito_accion.php">
            <input type="hidden" name="token" value="<?= e(token_cliente()) ?>">
            <input type="hidden" name="accion" value="agregar">
            <input type="hidden" name="id_productos" value="<?= (int) $p['id_productos'] ?>">
            <input type="hidden" name="modalidad" value="<?= $modalidad ?>">
            <input type="hidden" name="volver" value="prenda.php?id=<?= (int) $p['id_productos'] ?>">
            <input type="number" name="cantidad" value="1" min="1" max="<?= CARRITO_CANT_MAX ?>" aria-label="Cantidad">
            <button type="submit" class="btn-pill"><?= $modalidad === 'alquiler' ? 'Alquilar' : 'Agregar al carrito' ?></button>
        </form>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $p ? e($p['nombre']) : 'Prenda no encontrada' ?> - Elegancia a Medida</title>
    <link rel="stylesheet" href="css/cliente.css">
    <link rel="stylesheet" href="css/sitio.css">
</head>
<body>
<?php require __DIR__ . '/encabezado_cliente.php'; ?>

<main class="pagina-sitio">
    <?php if (!$p): ?>
        <div class="vacio-grande">
            <p>Esta prenda no existe o ya no está disponible.</p>
            <a class="btn-pill" href="tienda.php">Ir a la tienda</a>
        </div>
    <?php else: ?>
        <p class="migas"><a href="index.php">Inicio</a> / <a href="tienda.php?categoria=<?= (int) $p['id_categorias'] ?>"><?= e($p['categoria']) ?></a> / <?= e($p['nombre']) ?></p>
        <?php if ($flash): ?><div class="alert alert-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div><?php endif; ?>

        <div class="ficha">
            <div class="ficha-foto">
                <?php if ($foto): ?>
                    <img src="<?= e($foto) ?>" alt="<?= e($p['nombre']) ?>">
                <?php else: ?>
                    <div class="sin-foto" aria-hidden="true" style="height:100%"><span><?= e(mb_substr($p['nombre'], 0, 1)) ?></span></div>
                <?php endif; ?>
            </div>
            <div class="ficha-info">
                <span class="ficha-cat"><?= e($p['categoria']) ?> · <?= e($p['tipo']) ?></span>
                <h1><?= e($p['nombre']) ?></h1>
                <div class="marcas" style="position:static;margin-bottom:14px">
                    <?php if ($desc): ?><span class="marca-et oferta">−<?= $desc ?> % de descuento<?= $p['oferta_hasta'] ? ' hasta el ' . DateTime::createFromFormat('!Y-m-d', $p['oferta_hasta'])->format('d/m') : '' ?></span><?php endif; ?>
                    <?php if ((int) $p['de_temporada'] === 1): ?><span class="marca-et" style="background:var(--fondo)">Temporada</span><?php endif; ?>
                </div>
                <p class="ficha-desc"><?= nl2br(e($p['descripcion'])) ?></p>

                <?php if ((int) $p['en_venta'] === 1) { opcion_compra($p, 'venta'); } ?>
                <?php if ((int) $p['en_alquiler'] === 1) { opcion_compra($p, 'alquiler'); } ?>
                <?php if (!$cliente): ?>
                    <p class="sec-sub">Para agregar al carrito necesitás <a href="login.php?volver=<?= e(urlencode('prenda.php?id=' . (int) $p['id_productos'])) ?>">iniciar sesión</a>.</p>
                <?php endif; ?>

                <div class="ficha-medida">
                    <p><strong>¿La querés con cambios o a tu medida?</strong> Pedí un presupuesto de confección usando esta prenda como referencia.</p>
                    <a class="btn-pill borde" href="solicitud.php?producto=<?= (int) $p['id_productos'] ?>&amp;id_tipos_servicio=1">Pedir presupuesto a medida</a>
                </div>
            </div>
        </div>

        <?php if ($relacionados): ?>
            <section class="sec" style="padding-left:0;padding-right:0">
                <div class="sec-titulo"><h2>También te puede gustar</h2><a href="tienda.php?categoria=<?= (int) $p['id_categorias'] ?>">Ver más →</a></div>
                <div class="productos"><?php foreach ($relacionados as $r) { tarjeta_producto($r); } ?></div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/pie_cliente.php'; ?>
<script src="js/sitio.js"></script>
<?php if ($cliente): ?><script src="js/notificaciones.js"></script><?php endif; ?>
</body>
</html>
