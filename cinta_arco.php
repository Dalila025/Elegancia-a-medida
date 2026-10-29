<?php
/**
 * Cinta en arco de prendas en alquiler — Elegancia a Medida
 *
 * Las fotos de las prendas que se alquilan pasan solas, sin parar, sobre una
 * "pared curva" (las de los costados se ven más grandes). Se usa arriba de la
 * página de Alquiler y en el inicio. La modista no tiene que hacer nada:
 * aparece toda prenda visible, marcada «Se alquila» y con foto.
 * El movimiento lo hace js/sitio.js ([data-arco]); sin JavaScript, o si la
 * persona pidió "reducir movimiento", es una fila que se desliza con el dedo.
 *
 * Se incluye con require_once (necesita tarjeta_producto.php por SQL_PRODUCTOS_VISIBLES).
 */

/**
 * Prendas para la cinta: visibles, en alquiler y con foto.
 * $categoria: nombre de una categoría (ej: 'Disfraces') para mostrar solo esas; vacío = todas.
 */
function prendas_para_arco(PDO $pdo, int $max = 16, string $categoria = ''): array
{
    $sql = SQL_PRODUCTOS_VISIBLES . ' AND p.en_alquiler = 1 AND p.imagen IS NOT NULL';
    $params = [];
    if ($categoria !== '') {
        $sql .= ' AND c.nombre = :cat';
        $params[':cat'] = $categoria;
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY p.de_temporada DESC, p.fecha_alta DESC LIMIT ' . $max);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Dibuja la cinta. $titulo vacío = sin encabezado (lo pone la página).
 * $boton: texto del botón de abajo (vacío = sin botón); $enlace: a dónde lleva.
 * Si no hay prendas, no se muestra nada.
 */
function cinta_arco(array $prendas, string $titulo = '', string $subtitulo = '', string $boton = '', string $enlace = 'tienda.php?modalidad=alquiler'): void
{
    $prendas = array_values(array_filter($prendas, fn($p) => url_imagen_producto($p['imagen']) !== null));
    if (!$prendas) {
        return;
    }
    ?>
    <section class="arco-seccion" aria-label="<?= e($titulo ?: 'Prendas en alquiler') ?>">
        <?php if ($titulo !== ''): ?>
            <div class="arco-encabezado">
                <h2><?= e($titulo) ?></h2>
                <?php if ($subtitulo !== ''): ?><p><?= e($subtitulo) ?></p><?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="arco" data-arco>
            <div class="arco-pista">
                <?php foreach ($prendas as $p): $precio = precio_actual($p, 'alquiler'); ?>
                    <a class="arco-item" href="prenda.php?id=<?= (int) $p['id_productos'] ?>">
                        <img src="<?= e(url_imagen_producto($p['imagen'])) ?>" alt="<?= e($p['nombre']) ?>" loading="lazy" draggable="false">
                        <span class="arco-texto">
                            <strong><?= e($p['nombre']) ?></strong>
                            <span><?= $precio === null ? 'Alquiler a consultar' : 'Alquiler ' . e(pesos($precio)) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($boton !== ''): ?>
            <div class="arco-boton"><a class="btn-pill" href="<?= e($enlace) ?>"><?= e($boton) ?></a></div>
        <?php endif; ?>
    </section>
    <?php
}
