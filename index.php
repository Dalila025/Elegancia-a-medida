<?php
declare(strict_types=1);

/**
 * Inicio — Elegancia a Medida
 *
 *   1. Carrusel de pantallas grandes (Bienvenidos, Venta, Alquiler, Accesorios…)
 *   2. Servicios de la modista y «Cómo trabajo» (+ «¿No sabés tu talle?»)
 *   3. Cinta en arco con los disfraces en alquiler (cinta_arco.php)
 *   4. Mosaico (Venta / Alquiler / Accesorios)
 *   5. Ofertas en alquiler y Disfraces nuevos (filas que se deslizan) y «Ver toda la tienda»
 *   6. Nuestra historia y Contacto
 * Carrusel y mosaico salen de la tabla banners (panel → Portada).
 * (Temporada y Novedades se sacaron del inicio para que no quede cargado:
 *  siguen en la tienda y en el menú.)
 *
 * Tablas: banners, productos, categorias, tipos_producto, datos_sitio.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/tarjeta_producto.php';
require_once __DIR__ . '/cinta_arco.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$cliente = cliente_logueado($pdo);
$flash   = tomar_flash_cliente();
$sitio   = datos_sitio($pdo);

try {
    $carrusel = obtener_banners($pdo, 'carrusel');
    $mosaico  = obtener_banners($pdo, 'mosaico');
    // Ofertas: solo lo que se alquila y tiene descuento vigente
    $ofertas  = $pdo->query(SQL_PRODUCTOS_VISIBLES . ' AND p.en_alquiler = 1 AND ' . sql_oferta_vigente()
        . ' ORDER BY p.descuento_porcentaje DESC, p.nombre LIMIT ' . INICIO_MAX_OFERTAS)->fetchAll();
    // Disfraces nuevos: lo último que la modista cargó para alquilar
    $nuevos_alquiler = $pdo->query(SQL_PRODUCTOS_VISIBLES . ' AND p.en_alquiler = 1
        ORDER BY p.fecha_alta DESC, p.id_productos DESC LIMIT ' . INICIO_MAX_NUEVOS)->fetchAll();
    // Cinta en arco del inicio: prendas en alquiler con foto.
    // Para mostrar solo una categoría, poné su nombre (ej: 'Disfraces'); vacío = todas las que se alquilan.
    $arco_categoria = '';
    $en_alquiler = prendas_para_arco($pdo, 16, $arco_categoria);
    $arco_id_cat = $arco_categoria !== '' ? array_search($arco_categoria, obtener_categorias($pdo), true) : false;
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
    <title>Elegancia a Medida - Modista: confección a medida, arreglos y alquiler</title>
    <meta name="description" content="Modista: confección a medida con tus medidas, arreglos, venta de ropa de fiesta y alquiler de disfraces para chicos.">
    <link rel="stylesheet" href="<?= asset('css/cliente.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/sitio.css') ?>">
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

    <section class="servicios-banda" id="servicios">
        <div class="servicios-inner">
            <header class="titulo-elegante claro">
                <span class="antetitulo">Servicios</span>
                <h2>Lo que hago por vos</h2>
            </header>
            <div class="servicios">
                <a class="servicio" href="solicitud.php?id_tipos_servicio=1">
                    <span class="servicio-num" aria-hidden="true">01</span>
                    <span class="servicio-icono" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.5v2.5M9.5 5h5l1.8 3.6-1.8 1.9 2 10H7.5l2-10-1.8-1.9z"/><path d="M12 21.5v-2"/></svg></span>
                    <h3>Confección a medida</h3>
                    <p>Tu vestido o traje de fiesta, hecho con tus medidas. Elegís un modelo o me contás tu idea.</p>
                    <span class="servicio-ir">Pedir presupuesto <span aria-hidden="true">→</span></span>
                </a>
                <a class="servicio" href="precios_arreglos.php">
                    <span class="servicio-num" aria-hidden="true">02</span>
                    <span class="servicio-icono" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="2.8"/><circle cx="6" cy="18" r="2.8"/><path d="M8.4 7.6 20 18M8.4 16.4 20 6"/></svg></span>
                    <h3>Arreglos</h3>
                    <p>Dobladillos, ajustes, cierres y entallados para que tu prenda te quede perfecta.</p>
                    <span class="servicio-ir">Ver precios <span aria-hidden="true">→</span></span>
                </a>
                <a class="servicio" href="tienda.php?modalidad=alquiler">
                    <span class="servicio-num" aria-hidden="true">03</span>
                    <span class="servicio-icono" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4.2a1.9 1.9 0 1 1 1.9 1.9c-1 0-1.9.6-1.9 1.5V9"/><path d="M12 9 3.3 15.2a1 1 0 0 0 .6 1.8h16.2a1 1 0 0 0 .6-1.8z"/></svg></span>
                    <h3>Alquiler de disfraces</h3>
                    <p>Disfraces para chicos: actos patrios como el 25 de Mayo y cumpleaños con sus personajes favoritos.</p>
                    <span class="servicio-ir">Ver disfraces <span aria-hidden="true">→</span></span>
                </a>
                <a class="servicio" href="tienda.php?modalidad=venta">
                    <span class="servicio-num" aria-hidden="true">04</span>
                    <span class="servicio-icono" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5.5 7.5h13l-1 13h-11z"/><path d="M9 7.5a3 3 0 0 1 6 0"/></svg></span>
                    <h3>Prendas en venta</h3>
                    <p>Modelos propios en tu talle o a medida, reservándolos con una seña.</p>
                    <span class="servicio-ir">Ver la tienda <span aria-hidden="true">→</span></span>
                </a>
            </div>
        </div>
    </section>

    <section class="sec" id="como-trabajo">
        <header class="titulo-elegante">
            <span class="antetitulo">El proceso</span>
            <h2>Cómo trabajo</h2>
        </header>
        <ol class="linea-tiempo">
            <li>
                <span class="paso-marca" aria-hidden="true">1</span>
                <h3>Elegís</h3>
                <p>Un modelo de la tienda o la idea que tenés en mente.</p>
            </li>
            <li>
                <span class="paso-marca" aria-hidden="true">2</span>
                <h3>Tus medidas</h3>
                <p>Pasás por el taller y te las tomo: quedan guardadas en tu cuenta.</p>
            </li>
            <li>
                <span class="paso-marca" aria-hidden="true">3</span>
                <h3>Pruebas</h3>
                <p>Ajustamos todo lo necesario hasta que quede perfecto.</p>
            </li>
            <li>
                <span class="paso-marca" aria-hidden="true">4</span>
                <h3>Retirás</h3>
                <p>Tu prenda lista. La seña reserva el turno y el resto se paga al retirar.</p>
            </li>
        </ol>

        <div class="talle-promo">
            <div class="talle-promo-texto">
                <span class="antetitulo">Guía de talles</span>
                <h2>¿No sabés tu talle?</h2>
                <p>En la ropa de fiesta el talle no depende de la edad. Pasá una vez por el taller, te tomo las medidas y en cada prenda te decimos cuál te queda.</p>
                <a class="btn-pill" href="mis_medidas.php#turno-medidas">Pedir turno para medirme</a>
            </div>
            <div class="talle-promo-demo" aria-hidden="true">
                <div class="demo-tarjeta">
                    <div class="demo-fila"><span>Busto</span><strong>88 cm</strong></div>
                    <div class="demo-fila"><span>Cintura</span><strong>70 cm</strong></div>
                    <div class="demo-fila"><span>Cadera</span><strong>94 cm</strong></div>
                    <div class="demo-resultado"><span>Tu talle</span><b>M</b></div>
                </div>
                <div class="cinta-metrica"></div>
            </div>
        </div>
    </section>

    <?php
    // ===== TEXTOS DE LA CINTA DE DISFRACES (se pueden cambiar acá) =====
    cinta_arco(
        $en_alquiler,
        'Disfraces para chicos',                                                       // título grande
        'Para actos patrios como el 25 de Mayo y para cumpleaños con sus personajes favoritos. Tocá uno para verlo.', // texto de abajo
        'Ver todos los disfraces',                                                     // texto del botón
        'tienda.php?modalidad=alquiler' . ($arco_id_cat ? '&categoria=' . $arco_id_cat : '') // a dónde lleva el botón
    );
    ?>

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
            <div class="sec-titulo"><h2>Ofertas en alquiler</h2><a href="tienda.php?modalidad=alquiler&amp;ofertas=1">Ver todas →</a></div>
            <div class="fila-scroll"><?php foreach ($ofertas as $p) { tarjeta_producto($p, 'alquiler'); } ?></div>
        </section>
    <?php endif; ?>

    <?php if ($nuevos_alquiler): ?>
        <section class="sec" id="nuevos">
            <div class="sec-titulo"><h2>Disfraces nuevos</h2><a href="tienda.php?modalidad=alquiler&amp;orden=nuevos">Ver todos →</a></div>
            <div class="fila-scroll"><?php foreach ($nuevos_alquiler as $p) { tarjeta_producto($p, 'alquiler'); } ?></div>
        </section>
    <?php endif; ?>

    <div class="sec ver-tienda">
        <a class="btn-pill" href="tienda.php">Ver toda la tienda</a>
    </div>

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
<script src="<?= asset('js/sitio.js') ?>"></script>
<?php if ($cliente): ?><script src="<?= asset('js/notificaciones.js') ?>"></script><?php endif; ?>
</body>
</html>
