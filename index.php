<?php
declare(strict_types=1);

/**
 * Inicio — Elegancia a Medida
 *
 *   1. Carrusel de pantallas grandes (Bienvenidos, Venta, Alquiler, Accesorios…)
 *   2. Mosaico (Venta / Alquiler / Accesorios)
 *   3. Ofertas y Temporada (filas que se deslizan)
 *   4. Novedades (últimas prendas) → "Ver toda la tienda"
 *   5. Nuestra historia y Contacto
 * Carrusel y mosaico salen de la tabla banners (panel → Portada).
 *
 * Tablas: banners, productos, categorias, tipos_producto, datos_sitio.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/tarjeta_producto.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$cliente = cliente_logueado($pdo);
$flash   = tomar_flash_cliente();
$sitio   = datos_sitio($pdo);

try {
    $carrusel = obtener_banners($pdo, 'carrusel');
    $mosaico  = obtener_banners($pdo, 'mosaico');
    $ofertas  = $pdo->query(SQL_PRODUCTOS_VISIBLES . ' AND ' . sql_oferta_vigente()
        . ' ORDER BY p.descuento_porcentaje DESC, p.nombre LIMIT ' . INICIO_MAX_OFERTAS)->fetchAll();
    $temporada = $pdo->query(SQL_PRODUCTOS_VISIBLES . ' AND p.de_temporada = 1 ORDER BY p.fecha_alta DESC, p.nombre LIMIT '
        . INICIO_MAX_TEMPORADA)->fetchAll();
    $novedades = $pdo->query(SQL_PRODUCTOS_VISIBLES . ' ORDER BY p.fecha_alta DESC, p.id_productos DESC LIMIT 8')->fetchAll();
} catch (PDOException $e) {
    error_log('Error en el inicio: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo cargar la página. Intente nuevamente más tarde.');
}

$pagina_actual = 'inicio';
$wa = enlace_whatsapp($sitio['whatsapp'] ?? '', 'Hola! Quería hacer una consulta.');
$ig = usuario_instagram($sitio['instagram'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elegancia a Medida - Venta, alquiler, confección y arreglos</title>
    <meta name="description" content="Prendas en venta y alquiler, confección a medida y arreglos. Ofertas y temporada.">
    <link rel="stylesheet" href="css/cliente.css">
    <link rel="stylesheet" href="css/sitio.css">
</head>
<body>
<?php require __DIR__ . '/encabezado_cliente.php'; ?>

<main>
    <?php if ($flash): ?>
        <div class="sec" style="padding-bottom:0"><div class="alert alert-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div></div>
    <?php endif; ?>

    <?php if ($carrusel): ?>
        <section class="carrusel" data-carrusel aria-roledescription="carrusel" aria-label="Destacados">
            <?php foreach ($carrusel as $i => $b):
                $img = url_imagen(CARPETA_BANNERS, $b['imagen']);
                $link = enlace_seguro($b['enlace']); ?>
                <div class="slide <?= $i === 0 ? 'activo' : '' ?> <?= $img ? '' : 'slide-sin-imagen' ?>"
                     role="group" aria-roledescription="pantalla" aria-label="<?= $i + 1 ?> de <?= count($carrusel) ?>" <?= $i ? 'aria-hidden="true"' : '' ?>>
                    <?php if ($img): ?><img src="<?= e($img) ?>" alt="" <?= $i ? 'loading="lazy"' : '' ?>><?php endif; ?>
                    <div class="slide-contenido">
                        <h2><?= e($b['titulo']) ?></h2>
                        <?php if ($b['subtitulo'] !== ''): ?><p><?= e($b['subtitulo']) ?></p><?php endif; ?>
                        <?php if ($link !== '' && $b['texto_boton'] !== ''): ?>
                            <a class="btn-pill <?= $i % 2 ? 'claro' : '' ?>" href="<?= e($link) ?>"><?= e($b['texto_boton']) ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (count($carrusel) > 1): ?>
                <button type="button" class="carrusel-prev" aria-label="Anterior">‹</button>
                <button type="button" class="carrusel-next" aria-label="Siguiente">›</button>
                <div class="carrusel-puntos">
                    <?php foreach ($carrusel as $i => $b): ?>
                        <button type="button" aria-label="Ir a <?= e($b['titulo']) ?>" <?= $i === 0 ? 'aria-current="true"' : '' ?>></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($mosaico): ?>
        <section class="sec" aria-label="Secciones de la tienda">
            <div class="mosaico">
                <?php foreach ($mosaico as $b):
                    $img = url_imagen(CARPETA_BANNERS, $b['imagen']);
                    $link = enlace_seguro($b['enlace']) ?: 'tienda.php'; ?>
                    <a class="mosaico-item" href="<?= e($link) ?>">
                        <?php if ($img): ?><img src="<?= e($img) ?>" alt="" loading="lazy"><?php endif; ?>
                        <div>
                            <h3><?= e($b['titulo']) ?></h3>
                            <?php if ($b['texto_boton'] !== ''): ?><span class="btn-pill"><?= e($b['texto_boton']) ?></span><?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($ofertas): ?>
        <section class="sec" id="ofertas">
            <div class="sec-titulo"><h2>Ofertas</h2><a href="tienda.php?ofertas=1">Ver todas →</a></div>
            <div class="fila-scroll"><?php foreach ($ofertas as $p) { tarjeta_producto($p); } ?></div>
        </section>
    <?php endif; ?>

    <?php if ($temporada): ?>
        <section class="sec" id="temporada">
            <div class="sec-titulo"><h2><?= e($sitio['temporada_titulo'] ?? 'Temporada') ?></h2><a href="tienda.php?temporada=1">Ver la temporada →</a></div>
            <div class="fila-scroll"><?php foreach ($temporada as $p) { tarjeta_producto($p); } ?></div>
        </section>
    <?php endif; ?>

    <section class="sec" id="productos">
        <div class="sec-titulo"><h2>Novedades</h2><a href="tienda.php">Ver toda la tienda →</a></div>
        <?php if ($novedades): ?>
            <div class="productos"><?php foreach ($novedades as $p) { tarjeta_producto($p); } ?></div>
        <?php else: ?>
            <p>Todavía no hay prendas cargadas.</p>
        <?php endif; ?>
    </section>

    <?php if (!empty($sitio['historia_texto'])): ?>
        <section class="sec seccion-historia" id="historia">
            <div class="historia">
                <div class="historia-adorno" aria-hidden="true">
                    <?php if (url_logo($pdo)): ?><img src="<?= e(url_logo($pdo)) ?>" alt=""><?php else: ?><span>E</span><?php endif; ?>
                </div>
                <div class="historia-texto">
                    <h2><?= e($sitio['historia_titulo'] ?: 'Nuestra historia') ?></h2>
                    <?php foreach (preg_split('/\R{2,}/', trim($sitio['historia_texto'])) as $parrafo): ?>
                        <p><?= nl2br(e($parrafo)) ?></p>
                    <?php endforeach; ?>
                    <?php if (!empty($sitio['nombre_modista'])): ?><p class="firma">— <?= e($sitio['nombre_modista']) ?></p><?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="sec seccion-contacto" id="contacto">
        <div class="sec-titulo"><h2>Contacto</h2><span class="sec-sub">Escribinos o pedí tu presupuesto online</span></div>
        <div class="contacto-grilla">
            <?php if ($wa): ?>
                <a class="contacto-item" href="<?= e($wa) ?>" target="_blank" rel="noopener"><strong>WhatsApp</strong><span><?= e($sitio['whatsapp']) ?></span></a>
            <?php endif; ?>
            <?php if (!empty($sitio['telefono'])): ?>
                <a class="contacto-item" href="tel:<?= e(preg_replace('/[^\d+]/', '', $sitio['telefono'])) ?>"><strong>Teléfono</strong><span><?= e($sitio['telefono']) ?></span></a>
            <?php endif; ?>
            <?php if (!empty($sitio['email'])): ?>
                <a class="contacto-item" href="mailto:<?= e($sitio['email']) ?>"><strong>Email</strong><span><?= e($sitio['email']) ?></span></a>
            <?php endif; ?>
            <?php if ($ig !== ''): ?>
                <a class="contacto-item" href="https://www.instagram.com/<?= e($ig) ?>/" target="_blank" rel="noopener"><strong>Instagram</strong><span>@<?= e($ig) ?></span></a>
            <?php endif; ?>
            <?php if (!empty($sitio['direccion']) || !empty($sitio['horarios'])): ?>
                <div class="contacto-item"><strong>Atención</strong>
                    <?php if (!empty($sitio['direccion'])): ?><span><?= e($sitio['direccion']) ?></span><?php endif; ?>
                    <?php if (!empty($sitio['horarios'])): ?><span><?= e($sitio['horarios']) ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="contacto-cta">
            <a class="btn-pill" href="solicitud.php?id_tipos_servicio=1">Pedir presupuesto de confección</a>
            <a class="btn-pill borde" href="solicitud.php?id_tipos_servicio=2">Pedir presupuesto de arreglo</a>
        </div>
    </section>
</main>

<?php require __DIR__ . '/pie_cliente.php'; ?>
<script src="js/sitio.js"></script>
<?php if ($cliente): ?><script src="js/notificaciones.js"></script><?php endif; ?>
</body>
</html>
