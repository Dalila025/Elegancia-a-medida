<?php
declare(strict_types=1);

/**
 * Portada (panel) — Elegancia a Medida
 *
 * Lista las pantallas del carrusel y los recuadros del mosaico del inicio.
 * Desde acá se agregan, editan (banner.php), ordenan, ocultan o eliminan.
 * Los cambios se procesan en guardar_banner.php. Tabla: banners.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario = requerir_modista($pdo);
$token   = token_modista();
$flash   = tomar_flash();

try {
    $todos = $pdo->query('SELECT * FROM banners ORDER BY ubicacion, orden, id_banners')->fetchAll();
} catch (PDOException $e) {
    error_log('Error al listar banners: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo cargar la portada.');
}
$grupos = ['carrusel' => [], 'mosaico' => []];
foreach ($todos as $b) { $grupos[$b['ubicacion']][] = $b; }

$pagina_actual = 'portada';

/** Botón de acción sobre un banner. */
function accion_banner(int $id, string $accion, string $texto, string $clase, string $token, string $confirmar = ''): void
{
    ?>
    <form method="post" action="guardar_banner.php" <?= $confirmar ? 'onsubmit="return confirm(\'' . e($confirmar) . '\');"' : '' ?>>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="accion" value="<?= $accion ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="btn-accion <?= $clase ?>"><?= $texto ?></button>
    </form>
    <?php
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Portada · Panel · Elegancia a Medida</title>
    <link rel="stylesheet" href="css/modista.css">
    <link rel="stylesheet" href="css/panel.css">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container">
        <?php if ($flash): ?>
            <div class="flash <?= $flash['tipo'] === 'ok' ? 'flash-ok' : 'flash-error' ?>" role="status"><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>

        <?php foreach (['carrusel' => ['Carrusel', 'Pantallas grandes que pasan arriba de todo en el inicio (ej: Bienvenidos, Venta, Alquiler, Accesorios). Ideal: fotos horizontales de 1600 × 700.'],
                        'mosaico'  => ['Mosaico', 'Recuadros que van debajo del carrusel (ej: Venta, Alquiler, Accesorios). Ideal: 3 recuadros.']] as $ubic => [$titulo, $ayuda]): ?>
            <section class="tarjeta">
                <div class="pedido-cabeza">
                    <div><h2><?= $titulo ?></h2><p class="ayuda" style="margin:0"><?= e($ayuda) ?></p></div>
                    <a class="btn-primary btn-agregar" href="banner.php?ubicacion=<?= $ubic ?>">+ Agregar</a>
                </div>
                <?php if (!$grupos[$ubic]): ?>
                    <p class="sin-acciones" style="margin-top:12px">No hay ninguno: en el inicio no se muestra esta sección.</p>
                <?php else: ?>
                    <div class="lista-banners">
                        <?php foreach ($grupos[$ubic] as $i => $b):
                            $idb = (int) $b['id_banners'];
                            $img = url_imagen(CARPETA_BANNERS, $b['imagen']); ?>
                            <div class="banner-fila <?= (int) $b['activo'] ? '' : 'fila-oculta' ?>">
                                <div class="banner-mini" <?= $img ? 'style="background-image:url(\'' . e($img) . '\')"' : '' ?>><span><?= e($b['titulo']) ?></span></div>
                                <div class="banner-datos">
                                    <strong><?= e($b['titulo']) ?></strong>
                                    <?php if ($b['subtitulo'] !== ''): ?><span><?= e($b['subtitulo']) ?></span><?php endif; ?>
                                    <span class="ref-catalogo"><?= $b['texto_boton'] !== '' ? 'Botón «' . e($b['texto_boton']) . '» → ' . e($b['enlace']) : 'Sin botón' ?></span>
                                    <span class="estado-badge <?= (int) $b['activo'] ? 'estado-3' : 'estado-4' ?>" style="width:max-content"><?= (int) $b['activo'] ? 'Visible' : 'Oculto' ?></span>
                                </div>
                                <div class="acciones">
                                    <?php if ($i > 0) { accion_banner($idb, 'subir', '↑', 'accion-1', $token); } ?>
                                    <?php if ($i < count($grupos[$ubic]) - 1) { accion_banner($idb, 'bajar', '↓', 'accion-1', $token); } ?>
                                    <a class="btn-accion accion-presupuestar" href="banner.php?id=<?= $idb ?>">Editar</a>
                                    <?php accion_banner($idb, 'alternar', (int) $b['activo'] ? 'Ocultar' : 'Mostrar', 'accion-1', $token); ?>
                                    <?php accion_banner($idb, 'eliminar', 'Eliminar', 'accion-5', $token, '¿Eliminar «' . addslashes($b['titulo']) . '»?'); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </main>
</body>
</html>
