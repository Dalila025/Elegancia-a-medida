<?php
declare(strict_types=1);

/**
 * Carrito — Elegancia a Medida
 *
 * Hay que iniciar sesión para verlo. Muestra las prendas agregadas (venta
 * y/o alquiler) con el precio de hoy (con ofertas), permite cambiar
 * cantidades o sacarlas, y confirmar el pedido (confirmar_pedido.php).
 * Si hay prendas en alquiler, pide fecha de retiro y de devolución.
 *
 * Tablas: items_carrito, productos.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$cliente = requerir_cliente($pdo, 'Iniciá sesión para ver tu carrito.');
$flash   = tomar_flash_cliente();
$token   = token_cliente();

// Datos devueltos por confirmar_pedido.php cuando hubo errores
$errores = $_SESSION['form_pedido']['errores'] ?? [];
$viejo   = $_SESSION['form_pedido']['datos'] ?? [];
unset($_SESSION['form_pedido']);

try {
    $stmt = $pdo->prepare(
        'SELECT ic.id_items_carrito, ic.modalidad, ic.talle, ic.cantidad,
                p.id_productos, p.nombre, p.imagen, p.en_venta, p.precio_venta, p.en_alquiler, p.precio_alquiler,
                p.descuento_porcentaje, p.oferta_hasta,
                (p.activo = 1 AND c.activo = 1 AND t.activo = 1
                 AND IF(ic.modalidad = \'alquiler\', p.en_alquiler, p.en_venta) = 1) AS disponible
           FROM items_carrito ic
           INNER JOIN productos p      ON p.id_productos = ic.id_productos
           INNER JOIN categorias c     ON c.id_categorias = p.id_categorias
           INNER JOIN tipos_producto t ON t.id_tipos_producto = p.id_tipos_producto
          WHERE ic.id_clientes = :c
          ORDER BY ic.fecha_agregado DESC, ic.id_items_carrito DESC'
    );
    $stmt->execute([':c' => $cliente['id_clientes']]);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error al leer el carrito: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo cargar el carrito.');
}

// Totales
$total = 0.0; $a_consultar = false; $hay_alquiler = false; $no_disponibles = 0; $unidades = 0;
foreach ($items as &$it) {
    $it['precio'] = (int) $it['disponible'] === 1 ? precio_actual($it, $it['modalidad']) : null;
    if ((int) $it['disponible'] !== 1) { $no_disponibles++; continue; }
    $unidades += (int) $it['cantidad'];
    if ($it['modalidad'] === 'alquiler') { $hay_alquiler = true; }
    if ($it['precio'] === null) { $a_consultar = true; } else { $total += $it['precio'] * (int) $it['cantidad']; }
}
unset($it);

$hoy = date('Y-m-d');
$max = (new DateTime('today'))->modify('+' . PEDIDO_ANTICIPO_MAX . ' days')->format('Y-m-d');
$pagina_actual = 'carrito';

/** Botón de una acción del carrito sobre una fila. */
function boton_item(int $id, string $accion, string $texto, string $clase, string $token, bool $deshabilitado = false, string $etiqueta = ''): void
{
    ?>
    <form method="post" action="carrito_accion.php">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="accion" value="<?= $accion ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="volver" value="carrito.php">
        <button type="submit" class="<?= $clase ?>" <?= $deshabilitado ? 'disabled' : '' ?> <?= $etiqueta ? 'aria-label="' . e($etiqueta) . '"' : '' ?>><?= $texto ?></button>
    </form>
    <?php
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito - Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/cliente.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/sitio.css') ?>">
</head>
<body>
<?php require __DIR__ . '/encabezado_cliente.php'; ?>

<main class="pagina-sitio">
    <p class="migas"><a href="index.php">Inicio</a> / Carrito</p>
    <h1>Tu carrito</h1>

    <?php if ($flash): ?><div class="alert alert-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div><?php endif; ?>
    <?php if ($errores): ?>
        <div class="alert alert-error" role="alert"><strong>Revisá estos datos:</strong>
            <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if (!$items): ?>
        <div class="vacio-grande">
            <?= icono('carrito') ?>
            <p>Tu carrito está vacío.</p>
            <a class="btn-pill" href="tienda.php">Ir a la tienda</a>
        </div>
    <?php else: ?>
        <div class="carrito">
            <section class="carrito-lista" aria-label="Prendas del carrito">
                <?php foreach ($items as $it):
                    $idi = (int) $it['id_items_carrito'];
                    $foto = url_imagen_producto($it['imagen']);
                    $ok = (int) $it['disponible'] === 1; ?>
                    <div class="carrito-item">
                        <a class="mini" href="prenda.php?id=<?= (int) $it['id_productos'] ?>">
                            <?php if ($foto): ?><img src="<?= e($foto) ?>" alt=""><?php else: ?><div class="sin-foto"><span><?= e(mb_substr($it['nombre'], 0, 1)) ?></span></div><?php endif; ?>
                        </a>
                        <div>
                            <h3><a href="prenda.php?id=<?= (int) $it['id_productos'] ?>"><?= e($it['nombre']) ?></a></h3>
                            <span class="modo-et <?= $it['modalidad'] ?>"><?= $it['modalidad'] === 'alquiler' ? 'Alquiler' : 'Venta' ?></span>
                            <?php if ($it['talle'] !== ''): ?><span class="item-talle"><?= e(texto_talle($it['talle'])) ?></span><?php endif; ?>
                            <?php if ($it['talle'] === TALLE_A_MEDIDA): ?><span class="item-medidas">Se confecciona con <a href="mis_medidas.php">tus medidas</a>.</span><?php endif; ?>
                            <?php if ($ok): ?>
                                <div class="precio-u"><?= $it['precio'] === null ? 'Precio a consultar' : e(pesos($it['precio'])) . ' c/u' ?></div>
                            <?php else: ?>
                                <div class="precio-u" style="color:#c0392b">Ya no está disponible: sacala para confirmar el pedido.</div>
                            <?php endif; ?>
                        </div>
                        <div class="derecha">
                            <?php if ($ok): ?>
                                <div class="cantidad" aria-label="Cantidad">
                                    <?php boton_item($idi, 'restar', '−', '', $token, (int) $it['cantidad'] <= 1, 'Restar uno'); ?>
                                    <span><?= (int) $it['cantidad'] ?></span>
                                    <?php boton_item($idi, 'sumar', '+', '', $token, (int) $it['cantidad'] >= CARRITO_CANT_MAX, 'Sumar uno'); ?>
                                </div>
                                <span class="subtotal"><?= $it['precio'] === null ? 'A consultar' : e(pesos($it['precio'] * (int) $it['cantidad'])) ?></span>
                            <?php endif; ?>
                            <?php boton_item($idi, 'quitar', 'Quitar', 'quitar', $token); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <aside class="resumen-pedido" aria-label="Resumen del pedido">
                <h2>Resumen</h2>
                <div class="linea"><span>Prendas</span><span><?= $unidades ?></span></div>
                <div class="linea total"><span>Total estimado</span><span><?= e(pesos($total)) ?><?= $a_consultar ? ' + a consultar' : '' ?></span></div>

                <form method="post" action="confirmar_pedido.php" novalidate>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <?php if ($hay_alquiler): ?>
                        <label for="fecha_retiro">Alquiler: ¿cuándo lo retirás?</label>
                        <input type="date" id="fecha_retiro" name="fecha_retiro" required min="<?= $hoy ?>" max="<?= $max ?>" value="<?= e($viejo['fecha_retiro'] ?? '') ?>">
                        <label for="fecha_devolucion">¿Cuándo lo devolvés?</label>
                        <input type="date" id="fecha_devolucion" name="fecha_devolucion" required min="<?= $hoy ?>" max="<?= $max ?>" value="<?= e($viejo['fecha_devolucion'] ?? '') ?>">
                        <p class="nota">Hasta <?= ALQUILER_MAX_DIAS ?> días de alquiler.</p>
                    <?php endif; ?>
                    <label for="comentario">Aclaraciones (opcional)</label>
                    <textarea id="comentario" name="comentario" rows="3" maxlength="<?= PEDIDO_COMENTARIO_MAX ?>" placeholder="Talle, color, horario para retirar…"><?= e($viejo['comentario'] ?? '') ?></textarea>
                    <button type="submit" class="btn-pill" <?= $no_disponibles || !$unidades ? 'disabled' : '' ?>>Confirmar pedido</button>
                    <p class="nota">Ahora no se cobra nada: cuando la modista confirme el pedido, vas a poder pagar una seña del <?= SENA_PORCENTAJE ?> % desde «Mis pedidos». El resto se paga al retirar.</p>
                </form>
            </aside>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/pie_cliente.php'; ?>
<script src="<?= asset('js/sitio.js') ?>"></script>
<script src="<?= asset('js/notificaciones.js') ?>"></script>
</body>
</html>
