<?php
/**
 * Tarjeta de producto (inicio, tienda y "también te puede gustar").
 * Define tarjeta_producto(); se incluye con require_once.
 *
 * El botón agrega la prenda al carrito (carrito_accion.php). Si la prenda
 * se vende y se alquila, hay un botón para cada modalidad.
 * Si la prenda tiene guía de talles, el botón lleva a la ficha para elegir el talle.
 * Sin sesión iniciada, carrito_accion.php manda a iniciar sesión.
 */

/** Consulta base de productos visibles (con categoría y tipo). */
const SQL_PRODUCTOS_VISIBLES = 'SELECT p.id_productos, p.nombre, p.descripcion, p.en_venta, p.precio_venta,
                  p.en_alquiler, p.precio_alquiler, p.descuento_porcentaje, p.oferta_hasta,
                  p.de_temporada, p.imagen, p.fecha_alta, p.id_categorias, p.id_tipos_producto,
                  c.nombre AS categoria, t.nombre AS tipo,
                  (SELECT COUNT(*) FROM talles_producto tp WHERE tp.id_productos = p.id_productos) AS n_talles
             FROM productos p
             INNER JOIN categorias c     ON c.id_categorias     = p.id_categorias     AND c.activo = 1
             INNER JOIN tipos_producto t ON t.id_tipos_producto = p.id_tipos_producto AND t.activo = 1
            WHERE p.activo = 1';

/** Línea de precio: tachado + rebajado si hay oferta, o "A consultar". */
function linea_precio(array $p, string $modalidad): string
{
    $lista  = $modalidad === 'alquiler' ? $p['precio_alquiler'] : $p['precio_venta'];
    $actual = precio_actual($p, $modalidad);
    $etiq   = $modalidad === 'alquiler' ? 'Alquiler' : 'Venta';
    if ($actual === null) {
        return '<div class="linea"><span class="modo">' . $etiq . '</span><strong>A consultar</strong></div>';
    }
    $rebajado = $lista !== null && $actual < (float) $lista;
    return '<div class="linea"><span class="modo">' . $etiq . '</span>'
         . '<strong class="' . ($rebajado ? 'rebajado' : '') . '">' . e(pesos($actual)) . '</strong>'
         . ($rebajado ? '<s>' . e(pesos((float) $lista)) . '</s>' : '') . '</div>';
}

/** Formulario "agregar al carrito" para una modalidad. */
function boton_carrito(array $p, string $modalidad, string $texto, string $clase = ''): void
{
    ?>
    <form method="post" action="carrito_accion.php">
        <input type="hidden" name="token" value="<?= e(token_cliente()) ?>">
        <input type="hidden" name="accion" value="agregar">
        <input type="hidden" name="id_productos" value="<?= (int) $p['id_productos'] ?>">
        <input type="hidden" name="modalidad" value="<?= $modalidad ?>">
        <input type="hidden" name="volver" value="<?= e(pagina_actual()) ?>">
        <button type="submit" class="btn-pill <?= $clase ?>"><?= e($texto) ?></button>
    </form>
    <?php
}

/**
 * Tarjeta de un producto. $solo = 'alquiler' (o 'venta') muestra solo esa
 * modalidad: precio y botón (se usa en las filas de alquiler del inicio).
 */
function tarjeta_producto(array $p, string $solo = ''): void
{
    $id    = (int) $p['id_productos'];
    $foto  = url_imagen_producto($p['imagen']);
    $desc  = oferta_vigente($p) ? (int) $p['descuento_porcentaje'] : 0;
    $venta = (int) $p['en_venta'] === 1 && $solo !== 'alquiler';
    $alq   = (int) $p['en_alquiler'] === 1 && $solo !== 'venta';
    ?>
    <article class="tarjeta-prod">
        <a class="tarjeta-foto" href="prenda.php?id=<?= $id ?>">
            <?php if ($foto): ?>
                <img src="<?= e($foto) ?>" alt="<?= e($p['nombre']) ?>" loading="lazy">
            <?php else: ?>
                <div class="sin-foto" aria-hidden="true"><span><?= e(mb_substr($p['nombre'], 0, 1)) ?></span></div>
            <?php endif; ?>
            <span class="marcas">
                <?php if ($desc): ?><span class="marca-et oferta">−<?= $desc ?> %</span><?php endif; ?>
                <?php if ((int) $p['de_temporada'] === 1): ?><span class="marca-et">Temporada</span><?php endif; ?>
                <?php if ($alq): ?><span class="marca-et alquiler"><?= $venta ? 'También alquiler' : 'Alquiler' ?></span><?php endif; ?>
            </span>
        </a>
        <a class="tarjeta-nombre" href="prenda.php?id=<?= $id ?>"><?= e($p['nombre']) ?></a>
        <p class="tarjeta-cat"><?= e($p['categoria']) ?> · <?= e($p['tipo']) ?></p>
        <div class="tarjeta-precios">
            <?php if ($venta) { echo linea_precio($p, 'venta'); } ?>
            <?php if ($alq)   { echo linea_precio($p, 'alquiler'); } ?>
        </div>
        <div class="tarjeta-acciones">
            <?php if ((int) ($p['n_talles'] ?? 0) > 0): ?>
                <a class="btn-pill" href="prenda.php?id=<?= $id ?>#elegir-talle">Elegir talle</a>
            <?php elseif ($venta && $alq): ?>
                <?php boton_carrito($p, 'venta', 'Comprar'); ?>
                <?php boton_carrito($p, 'alquiler', 'Alquilar', 'borde'); ?>
            <?php elseif ($alq): ?>
                <?php boton_carrito($p, 'alquiler', 'Agregar al carrito'); ?>
            <?php else: ?>
                <?php boton_carrito($p, 'venta', 'Agregar al carrito'); ?>
            <?php endif; ?>
        </div>
    </article>
    <?php
}
