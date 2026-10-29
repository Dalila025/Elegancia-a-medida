<?php
declare(strict_types=1);

/**
 * Portada: alta y edición de una pantalla del carrusel o recuadro del mosaico (panel)
 * banner.php?ubicacion=carrusel|mosaico → nuevo
 * banner.php?id=N                       → editar
 * Envía (con la imagen) a guardar_banner.php. Tabla: banners.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario = requerir_modista($pdo);
$token   = token_modista();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$banner = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM banners WHERE id_banners = :id');
    $stmt->execute([':id' => $id]);
    $banner = $stmt->fetch() ?: null;
    if (!$banner) {
        flash('error', 'Ese banner no existe.');
        header('Location: banners.php');
        exit;
    }
}

$errores = [];
$viejo   = null;
if (isset($_SESSION['form_banner']) && (int) $_SESSION['form_banner']['id'] === $id) {
    $errores = $_SESSION['form_banner']['errores'];
    $viejo   = $_SESSION['form_banner']['datos'];
}
unset($_SESSION['form_banner']);

$v = fn(string $c, string $d = '') => (string) ($viejo[$c] ?? $banner[$c] ?? $d);
$ubicacion = $v('ubicacion', in_array($_GET['ubicacion'] ?? '', ['carrusel', 'mosaico'], true) ? $_GET['ubicacion'] : 'carrusel');
$activo = $viejo !== null ? !empty($viejo['activo']) : ($banner ? (int) $banner['activo'] === 1 : true);
$img = $banner ? url_imagen(CARPETA_BANNERS, $banner['imagen']) : null;

// Destinos sugeridos para el botón
$sugeridos = [
    'tienda.php' => 'Toda la tienda',
    'tienda.php?modalidad=venta' => 'Prendas en venta',
    'tienda.php?modalidad=alquiler' => 'Prendas en alquiler',
    'tienda.php?ofertas=1' => 'Ofertas',
    'tienda.php?temporada=1' => 'Temporada',
    'solicitud.php?id_tipos_servicio=1' => 'Presupuesto de confección',
    'solicitud.php?id_tipos_servicio=2' => 'Presupuesto de arreglo',
    'index.php#historia' => 'Nuestra historia',
];
foreach (obtener_categorias($pdo) as $idc => $nom) { $sugeridos['tienda.php?categoria=' . $idc] = 'Categoría: ' . $nom; }
foreach (obtener_tipos_producto($pdo) as $idt => $nom) { $sugeridos['tienda.php?tipo=' . $idt] = 'Tipo: ' . $nom; }

$pagina_actual = 'portada';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= $banner ? 'Editar' : 'Nuevo' ?> banner · Panel · Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/modista.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/panel.css') ?>">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container">
        <p style="margin-top:16px"><a href="banners.php" class="btn-link">← Volver a la portada</a></p>
        <?php if ($errores): ?>
            <div class="flash flash-error" role="alert"><strong>Revisá estos datos:</strong>
                <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <section class="tarjeta">
            <h2><?= $banner ? 'Editar «' . e($banner['titulo']) . '»' : 'Nuevo banner' ?></h2>
            <form method="post" action="guardar_banner.php" enctype="multipart/form-data" class="form-panel" novalidate>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= IMAGEN_MAX_BYTES ?>">

                <div class="producto-grid">
                    <div>
                        <div class="campo">
                            <label for="ubicacion">Dónde va</label>
                            <select id="ubicacion" name="ubicacion">
                                <option value="carrusel" <?= $ubicacion === 'carrusel' ? 'selected' : '' ?>>Carrusel (pantalla grande de arriba)</option>
                                <option value="mosaico"  <?= $ubicacion === 'mosaico'  ? 'selected' : '' ?>>Mosaico (recuadro de abajo)</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="titulo">Título grande</label>
                            <input type="text" id="titulo" name="titulo" required maxlength="<?= BANNER_TITULO_MAX ?>" value="<?= e($v('titulo')) ?>" placeholder="Ej: Bienvenidos, Venta, Alquiler, Accesorios">
                        </div>
                        <div class="campo">
                            <label for="subtitulo">Texto debajo (opcional)</label>
                            <input type="text" id="subtitulo" name="subtitulo" maxlength="<?= BANNER_SUBTITULO_MAX ?>" value="<?= e($v('subtitulo')) ?>" placeholder="Ej: Vestidos y trajes para tu evento">
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="texto_boton">Texto del botón (opcional)</label>
                                <input type="text" id="texto_boton" name="texto_boton" maxlength="<?= BANNER_BOTON_MAX ?>" value="<?= e($v('texto_boton')) ?>" placeholder="Ej: Ver alquileres">
                            </div>
                            <div class="campo">
                                <label for="enlace">A dónde lleva</label>
                                <input type="text" id="enlace" name="enlace" list="enlaces-sugeridos" maxlength="255" value="<?= e($v('enlace')) ?>" placeholder="Ej: tienda.php?modalidad=alquiler">
                                <datalist id="enlaces-sugeridos">
                                    <?php foreach ($sugeridos as $url => $txt): ?><option value="<?= e($url) ?>"><?= e($txt) ?></option><?php endforeach; ?>
                                </datalist>
                                <div class="ayuda">Elegí de la lista o escribí una página del sitio.</div>
                            </div>
                        </div>
                        <label class="check"><input type="checkbox" name="activo" value="1" <?= $activo ? 'checked' : '' ?>> Mostrar en el inicio</label>
                    </div>

                    <div class="foto-campo">
                        <label for="imagen">Imagen</label>
                        <div class="foto-preview foto-ancha" id="foto-preview">
                            <?php if ($img): ?><img src="<?= e($img) ?>" alt="Imagen actual"><?php else: ?><span>Sin imagen (se ve un degradado)</span><?php endif; ?>
                        </div>
                        <input type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp">
                        <div class="ayuda">JPG, PNG o WEBP, hasta <?= IMAGEN_MAX_BYTES / 1024 / 1024 ?> MB. Horizontal.</div>
                        <?php if ($img): ?><label class="check"><input type="checkbox" name="quitar_imagen" value="1"> Quitar la imagen</label><?php endif; ?>
                    </div>
                </div>

                <div class="botonera">
                    <button type="submit" class="btn-guardar"><?= $banner ? 'Guardar cambios' : 'Agregar' ?></button>
                    <a href="banners.php" class="btn-secundario">Cancelar</a>
                </div>
            </form>
        </section>
    </main>
    <script>
        document.getElementById('imagen').addEventListener('change', function () {
            var f = this.files && this.files[0]; if (!f) { return; }
            var caja = document.getElementById('foto-preview'), img = document.createElement('img');
            img.alt = 'Imagen nueva'; img.src = URL.createObjectURL(f); caja.innerHTML = ''; caja.appendChild(img);
        });
    </script>
</body>
</html>
