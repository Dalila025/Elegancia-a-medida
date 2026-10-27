<?php
declare(strict_types=1);

/**
 * Datos del sitio (panel) — Elegancia a Medida
 *
 * La modista edita lo que se ve en el encabezado, el inicio y el pie:
 * su logo, su historia, el título de la temporada y los datos de contacto.
 * Tabla: datos_sitio (una sola fila). Guarda en guardar_sitio.php.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario = requerir_modista($pdo);
$token   = token_modista();
$flash   = tomar_flash();

$errores = $_SESSION['form_sitio']['errores'] ?? [];
$viejo   = $_SESSION['form_sitio']['datos'] ?? null;
unset($_SESSION['form_sitio']);

$sitio = datos_sitio($pdo);
$v = fn(string $c) => (string) ($viejo[$c] ?? $sitio[$c] ?? '');

// Campos de contacto: nombre => [etiqueta, largo máximo, ayuda]
$campos_contacto = [
    'whatsapp'  => ['WhatsApp', 30,  'Con código de país, ej: 54 9 11 2345-6789. Se arma el enlace solo.'],
    'telefono'  => ['Teléfono', 30,  ''],
    'email'     => ['Email', 150, ''],
    'instagram' => ['Instagram', 60, 'Solo el usuario, sin @.'],
    'facebook'  => ['Facebook', 100, 'Nombre de la página.'],
    'direccion' => ['Dirección / zona', 150, 'Ej: Barrio Centro · Atención con turno previo.'],
    'horarios'  => ['Horarios', 150, ''],
];

$pagina_actual = 'sitio';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Datos del sitio · Panel · Elegancia a Medida</title>
    <link rel="stylesheet" href="css/modista.css">
    <link rel="stylesheet" href="css/panel.css">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container">
        <?php if ($flash): ?>
            <div class="flash <?= $flash['tipo'] === 'ok' ? 'flash-ok' : 'flash-error' ?>" role="status"><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>
        <?php if ($errores): ?>
            <div class="flash flash-error" role="alert"><strong>Revisá estos datos:</strong>
                <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post" action="guardar_sitio.php" class="form-panel" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= IMAGEN_MAX_BYTES ?>">

            <section class="tarjeta">
                <h2>Logo</h2>
                <p class="ayuda" style="margin:-6px 0 14px">Se muestra arriba a la izquierda en todas las páginas y en el pie. Si no subís ninguno, se ve el nombre «Elegancia a Medida».</p>
                <div class="logo-campo">
                    <div class="logo-preview" id="logo-preview">
                        <?php if ($logo = url_logo($pdo)): ?><img src="<?= e($logo) ?>" alt="Logo actual"><?php else: ?><span>Sin logo</span><?php endif; ?>
                    </div>
                    <div>
                        <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp" aria-label="Subir logo">
                        <button type="button" id="logo-cancelar" class="btn-secundario btn-quitar-archivo" hidden>Quitar la imagen elegida</button>
                        <div class="ayuda">PNG con fondo transparente queda mejor (se ve sobre fondo oscuro). Hasta <?= IMAGEN_MAX_BYTES / 1024 / 1024 ?> MB.</div>
                        <?php if (url_logo($pdo)): ?><label class="check"><input type="checkbox" name="quitar_logo" value="1"> Quitar el logo y usar el nombre</label><?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="tarjeta">
                <h2>Nuestra historia</h2>
                <p class="ayuda" style="margin:-6px 0 14px">Se muestra en el inicio, en la sección «Nuestra historia». Dejá una línea en blanco para separar párrafos.</p>
                <div class="fila">
                    <div class="campo">
                        <label for="historia_titulo">Título</label>
                        <input type="text" id="historia_titulo" name="historia_titulo" maxlength="100" value="<?= e($v('historia_titulo')) ?>">
                    </div>
                    <div class="campo">
                        <label for="nombre_modista">Firma (opcional)</label>
                        <input type="text" id="nombre_modista" name="nombre_modista" maxlength="100" value="<?= e($v('nombre_modista')) ?>" placeholder="Tu nombre">
                    </div>
                </div>
                <div class="campo">
                    <label for="historia_texto">Texto</label>
                    <textarea id="historia_texto" name="historia_texto" rows="9" maxlength="<?= SITIO_HISTORIA_MAX ?>"><?= e($v('historia_texto')) ?></textarea>
                    <div class="ayuda">Máximo <?= SITIO_HISTORIA_MAX ?> caracteres. Si lo dejás vacío, la sección no se muestra.</div>
                </div>
            </section>

            <section class="tarjeta">
                <h2>Temporada</h2>
                <div class="campo" style="max-width:420px">
                    <label for="temporada_titulo">Título de la sección de temporada</label>
                    <input type="text" id="temporada_titulo" name="temporada_titulo" maxlength="60" value="<?= e($v('temporada_titulo')) ?>" placeholder="Ej: Primavera-Verano 2026">
                    <div class="ayuda">Los productos de temporada se marcan en cada producto del catálogo.</div>
                </div>
            </section>

            <section class="tarjeta">
                <h2>Contacto</h2>
                <p class="ayuda" style="margin:-6px 0 14px">Se muestra en la sección «Contacto» del inicio y en el pie de todas las páginas. Lo que quede vacío no se muestra.</p>
                <div class="grilla-campos">
                    <?php foreach ($campos_contacto as $campo => [$etiqueta, $max, $ayuda]): ?>
                        <div class="campo">
                            <label for="<?= $campo ?>"><?= e($etiqueta) ?></label>
                            <input type="text" id="<?= $campo ?>" name="<?= $campo ?>" maxlength="<?= $max ?>" value="<?= e($v($campo)) ?>"
                                   <?= $campo === 'email' ? 'inputmode="email"' : ($campo === 'whatsapp' || $campo === 'telefono' ? 'inputmode="tel"' : '') ?>>
                            <?php if ($ayuda): ?><div class="ayuda"><?= e($ayuda) ?></div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="botonera" style="margin-bottom:32px">
                <button type="submit" class="btn-guardar">Guardar datos del sitio</button>
                <a href="index.php#historia" class="btn-secundario" target="_blank" rel="noopener">Ver en el sitio ↗</a>
            </div>
        </form>
    </main>
    <script>
       var campoLogo = document.getElementById('logo'),
    cajaLogo  = document.getElementById('logo-preview'),
    btnLogo   = document.getElementById('logo-cancelar'),
    original  = cajaLogo.innerHTML;   // lo que se veía antes de elegir un archivo
campoLogo.addEventListener('change', function () {
    var f = this.files && this.files[0];
    if (!f) { cajaLogo.innerHTML = original; btnLogo.hidden = true; return; }
    var img = document.createElement('img');
    img.alt = 'Logo nuevo'; img.src = URL.createObjectURL(f);
    cajaLogo.innerHTML = ''; cajaLogo.appendChild(img); btnLogo.hidden = false;
});
btnLogo.addEventListener('click', function () {
    campoLogo.value = '';            // no se sube nada al guardar
    cajaLogo.innerHTML = original; btnLogo.hidden = true;
});
    </script>
</body>
</html>
