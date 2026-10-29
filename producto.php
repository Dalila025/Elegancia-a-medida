<?php
declare(strict_types=1);

/**
 * Catálogo: alta y edición de un producto (panel) — Elegancia a Medida
 *
 * producto.php        → producto nuevo
 * producto.php?id=N   → editar el producto N
 * Envía (con la foto) a guardar_producto.php.
 * Incluye: tipo de prenda, venta y/o alquiler, oferta, temporada y la
 * guía de talles (talles_producto: medidas del cuerpo en cm por talle).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario = requerir_modista($pdo);
$token   = token_modista();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$producto = null;

try {
    $categorias = obtener_categorias($pdo, false);
    $tipos      = obtener_tipos_producto($pdo, false);
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM productos WHERE id_productos = :id');
        $stmt->execute([':id' => $id]);
        $producto = $stmt->fetch() ?: null;
        if (!$producto) {
            flash('error', "El producto #$id no existe.");
            header('Location: productos.php');
            exit;
        }
    }
} catch (PDOException $e) {
    error_log('Error al cargar producto: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo cargar el producto.');
}

if (!$categorias || !$tipos) {
    flash('error', 'Antes de cargar productos, creá al menos una categoría y un tipo de prenda.');
    header('Location: categorias.php');
    exit;
}

// Errores y datos devueltos por guardar_producto.php
$errores = [];
$viejo   = null;
if (isset($_SESSION['form_producto']) && (int) $_SESSION['form_producto']['id'] === $id) {
    $errores = $_SESSION['form_producto']['errores'];
    $viejo   = $_SESSION['form_producto']['datos'];
}
unset($_SESSION['form_producto']);

/** Valor del campo: lo enviado con error, lo guardado, o el valor por defecto. */
$v = fn(string $campo, $defecto = '') => $viejo[$campo] ?? ($producto[$campo] ?? $defecto);
/** Casillas: si se volvió con error, vale lo enviado; si no, lo guardado. */
$check = fn(string $campo, bool $defecto) => $viejo !== null
    ? !empty($viejo[$campo])
    : ($producto ? (int) $producto[$campo] === 1 : $defecto);

$foto = $producto ? url_imagen_producto($producto['imagen']) : null;

// Guía de talles: lo enviado con error, lo guardado, o vacío
$talles = $viejo['talles'] ?? ($producto ? array_map(
    fn($t) => array_merge(['talle' => $t['talle']], array_map(
        fn($c) => $t[$c] === null ? '' : cm((float) $t[$c]),
        array_combine(array_keys(MEDIDAS_TALLE), array_keys(MEDIDAS_TALLE))
    )),
    talles_de_producto($pdo, $id)
) : []);
$filas_visibles = max(3, count($talles) + 1);
$descuento_val = (int) $v('descuento_porcentaje', 0);
$pagina_actual = 'productos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= $producto ? 'Editar producto' : 'Nuevo producto' ?> · Panel · Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/modista.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/panel.css') ?>">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container">
        <p style="margin-top:16px"><a href="productos.php" class="btn-link">← Volver al catálogo</a></p>

        <?php if ($errores): ?>
            <div class="flash flash-error" role="alert">
                <strong>Revisá estos datos:</strong>
                <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <section class="tarjeta">
            <h2><?= $producto ? 'Editar «' . e($producto['nombre']) . '»' : 'Nuevo producto' ?></h2>

            <form method="post" action="guardar_producto.php" enctype="multipart/form-data" class="form-panel form-producto" novalidate>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= IMAGEN_MAX_BYTES ?>">

                <div class="producto-grid">
                    <div>
                        <div class="campo">
                            <label for="nombre">Nombre</label>
                            <input type="text" id="nombre" name="nombre" required maxlength="<?= PRODUCTO_NOMBRE_MAX ?>"
                                   value="<?= e((string) $v('nombre')) ?>" placeholder="Ej: Vestido largo bordó">
                        </div>

                        <div class="fila">
                            <div class="campo">
                                <label for="id_categorias">Categoría</label>
                                <select id="id_categorias" name="id_categorias" required>
                                    <option value="">— Elegí una —</option>
                                    <?php $sel = (int) $v('id_categorias', 0); foreach ($categorias as $idc => $nom): ?>
                                        <option value="<?= $idc ?>" <?= $sel === $idc ? 'selected' : '' ?>><?= e($nom) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="campo">
                                <label for="id_tipos_producto">Tipo de prenda</label>
                                <select id="id_tipos_producto" name="id_tipos_producto" required>
                                    <option value="">— Elegí uno —</option>
                                    <?php $sel = (int) $v('id_tipos_producto', 0); foreach ($tipos as $idt => $nom): ?>
                                        <option value="<?= $idt ?>" <?= $sel === $idt ? 'selected' : '' ?>><?= e($nom) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="campo">
                            <label for="descripcion">Descripción</label>
                            <textarea id="descripcion" name="descripcion" rows="4" required maxlength="<?= PRODUCTO_DESC_MAX ?>"
                                      placeholder="Telas, cortes, detalles, qué incluye…"><?= e((string) $v('descripcion')) ?></textarea>
                        </div>

                        <fieldset class="grupo">
                            <legend>Venta y alquiler</legend>
                            <div class="fila">
                                <div class="campo">
                                    <label class="check"><input type="checkbox" name="en_venta" value="1" data-precio="precio_venta" <?= $check('en_venta', true) ? 'checked' : '' ?>> Se confecciona / vende</label>
                                    <input type="text" inputmode="decimal" id="precio_venta" name="precio_venta" aria-label="Precio de venta"
                                           value="<?= e((string) $v('precio_venta')) ?>" placeholder="Precio desde ($) · vacío = a consultar">
                                </div>
                                <div class="campo">
                                    <label class="check"><input type="checkbox" name="en_alquiler" value="1" data-precio="precio_alquiler" <?= $check('en_alquiler', false) ? 'checked' : '' ?>> Se alquila</label>
                                    <input type="text" inputmode="decimal" id="precio_alquiler" name="precio_alquiler" aria-label="Precio de alquiler"
                                           value="<?= e((string) $v('precio_alquiler')) ?>" placeholder="Precio de alquiler ($) · vacío = a consultar">
                                </div>
                            </div>
                            <div class="ayuda">Tiene que estar marcada al menos una de las dos.</div>
                        </fieldset>

                        <fieldset class="grupo">
                            <legend>Oferta y temporada</legend>
                            <div class="fila">
                                <div class="campo">
                                    <label for="descuento_porcentaje">Descuento (%)</label>
                                    <input type="number" id="descuento_porcentaje" name="descuento_porcentaje" min="0" max="<?= DESCUENTO_MAX ?>" step="1"
                                           value="<?= $descuento_val ?>">
                                    <div class="ayuda">0 = sin oferta. Con descuento aparece en «Ofertas» del inicio.</div>
                                </div>
                                <div class="campo">
                                    <label for="oferta_hasta">Oferta válida hasta</label>
                                    <input type="date" id="oferta_hasta" name="oferta_hasta" min="<?= date('Y-m-d') ?>"
                                           value="<?= e((string) $v('oferta_hasta')) ?>">
                                    <div class="ayuda">Opcional. Después de esa fecha, la oferta deja de mostrarse sola.</div>
                                </div>
                            </div>
                            <label class="check">
                                <input type="checkbox" name="de_temporada" value="1" <?= $check('de_temporada', false) ? 'checked' : '' ?>>
                                Producto de temporada (aparece en el filtro «Temporada» de la tienda y del menú)
                            </label>
                        </fieldset>

                        <label class="check">
                            <input type="checkbox" name="activo" value="1" <?= $check('activo', true) ? 'checked' : '' ?>>
                            Mostrar en el sitio
                        </label>
                    </div>

                    <div class="foto-campo">
                        <label for="imagen">Foto</label>
                        <div class="foto-preview" id="foto-preview">
                            <?php if ($foto): ?>
                                <img src="<?= e($foto) ?>" alt="Foto actual">
                            <?php else: ?>
                                <span>Sin foto</span>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp">
                        <div class="ayuda">JPG, PNG o WEBP, hasta <?= IMAGEN_MAX_BYTES / 1024 / 1024 ?> MB. Mejor vertical (4:5).</div>
                        <?php if ($foto): ?>
                            <label class="check"><input type="checkbox" name="quitar_foto" value="1"> Quitar la foto actual</label>
                        <?php endif; ?>
                    </div>
                </div>

                <fieldset class="grupo guia-talles">
                    <legend>Guía de talles (medidas del cuerpo, en cm)</legend>
                    <p class="ayuda" style="margin-top:0">
                        Cargá cada talle (S, M, L o 38, 40, 42…) con las medidas del cuerpo que le quedan bien.
                        Así la clienta elige por sus medidas y no por la edad, y el sitio le recomienda su talle.
                        Dejá vacías las medidas que no correspondan. Si la prenda no tiene talles (ej: un tocado), dejá todo vacío.
                    </p>
                    <div class="tabla-scroll">
                        <table class="tabla-talles">
                            <thead>
                                <tr>
                                    <th>Talle</th>
                                    <?php foreach (MEDIDAS_TALLE as $c => [$nom, $ayuda]): ?><th title="<?= e($ayuda) ?>"><?= e($nom) ?></th><?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php for ($i = 0; $i < TALLES_MAX; $i++): $t = $talles[$i] ?? []; ?>
                                <tr class="<?= $i >= $filas_visibles ? 'fila-talle-oculta' : '' ?>">
                                    <td><input type="text" name="talles[<?= $i ?>][talle]" maxlength="<?= TALLE_NOMBRE_MAX ?>"
                                               value="<?= e((string) ($t['talle'] ?? '')) ?>" placeholder="Ej: M" aria-label="Talle <?= $i + 1 ?>"></td>
                                    <?php foreach (MEDIDAS_TALLE as $c => [$nom]): ?>
                                        <td><input type="text" inputmode="decimal" name="talles[<?= $i ?>][<?= $c ?>]" maxlength="5"
                                                   value="<?= e((string) ($t[$c] ?? '')) ?>" aria-label="<?= e($nom) ?> del talle <?= $i + 1 ?>"></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn-secundario" id="mas-talles" <?= $filas_visibles >= TALLES_MAX ? 'hidden' : '' ?>>+ Agregar otro talle</button>
                </fieldset>

                <div class="botonera">
                    <button type="submit" class="btn-guardar"><?= $producto ? 'Guardar cambios' : 'Agregar al catálogo' ?></button>
                    <a href="productos.php" class="btn-secundario">Cancelar</a>
                </div>
            </form>
        </section>
    </main>

    <script>
        // Vista previa de la foto elegida (antes de subirla).
        document.getElementById('imagen').addEventListener('change', function () {
            var caja = document.getElementById('foto-preview');
            var f = this.files && this.files[0];
            if (!f) { return; }
            var img = document.createElement('img');
            img.alt = 'Foto nueva';
            img.src = URL.createObjectURL(f);
            caja.innerHTML = '';
            caja.appendChild(img);
        });

        // Guía de talles: mostrar una fila más
        document.getElementById('mas-talles').addEventListener('click', function () {
            var oculta = document.querySelector('.fila-talle-oculta');
            if (oculta) { oculta.classList.remove('fila-talle-oculta'); oculta.querySelector('input').focus(); }
            if (!document.querySelector('.fila-talle-oculta')) { this.hidden = true; }
        });

        // El precio solo se puede escribir si la modalidad está marcada.
        document.querySelectorAll('input[data-precio]').forEach(function (chk) {
            var precio = document.getElementById(chk.dataset.precio);
            function actualizar() { precio.disabled = !chk.checked; }
            chk.addEventListener('change', actualizar);
            actualizar();
        });
    </script>
</body>
</html>
