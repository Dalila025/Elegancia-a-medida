<?php
declare(strict_types=1);

/**
 * Lista de precios de arreglos (panel) — Elegancia a Medida
 *
 * La modista arma su lista: nombre del arreglo, descripción, precio
 * (o «a consultar», o «desde»), demora aproximada. Puede ocultar o eliminar.
 * Se publica en precios_arreglos.php y aparece como referencia al presupuestar
 * un arreglo. Guarda en guardar_arreglo.php. Tabla: arreglos.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario = requerir_modista($pdo);
$token   = token_modista();
$flash   = tomar_flash();
$errores = $_SESSION['form_arreglo']['errores'] ?? [];
$viejo   = $_SESSION['form_arreglo']['datos'] ?? null;
unset($_SESSION['form_arreglo']);

try {
    $arreglos = obtener_arreglos($pdo, false);
} catch (PDOException $e) {
    error_log('Error al listar arreglos: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo cargar la lista de arreglos.');
}
$v = fn(string $c) => (string) ($viejo[$c] ?? '');
$pagina_actual = 'arreglos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Lista de arreglos · Panel · Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/modista.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/panel.css') ?>">
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

        <section class="tarjeta">
            <div class="pedido-cabeza">
                <div>
                    <h2>Lista de precios de arreglos</h2>
                    <p class="ayuda" style="margin:0">Se publica en el sitio (<a href="precios_arreglos.php" target="_blank" rel="noopener">ver cómo se ve ↗</a>) y te aparece como referencia al presupuestar un arreglo.
                        Precio vacío = «a consultar». Tildá «desde» si el precio puede subir según la prenda.</p>
                </div>
            </div>

            <?php foreach ($arreglos as $a): $f = 'arr-' . (int) $a['id_arreglos']; ?>
                <form method="post" action="guardar_arreglo.php" id="<?= $f ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <input type="hidden" name="id" value="<?= (int) $a['id_arreglos'] ?>">
                </form>
            <?php endforeach; ?>

            <div class="tabla-scroll">
                <table class="tabla-talles tabla-arreglos">
                    <thead><tr><th>Arreglo</th><th>Descripción</th><th>Precio ($)</th><th>Desde</th><th>Demora (días)</th><th>En el sitio</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($arreglos as $a): $f = 'arr-' . (int) $a['id_arreglos']; ?>
                        <tr class="<?= (int) $a['activo'] === 1 ? '' : 'fila-oculta' ?>">
                            <td><input type="text" form="<?= $f ?>" name="nombre" maxlength="<?= ARREGLO_NOMBRE_MAX ?>" value="<?= e($a['nombre']) ?>" aria-label="Nombre" required></td>
                            <td><input type="text" form="<?= $f ?>" name="descripcion" maxlength="<?= ARREGLO_DESCRIPCION_MAX ?>" value="<?= e($a['descripcion']) ?>" aria-label="Descripción"></td>
                            <td><input type="text" form="<?= $f ?>" name="precio" inputmode="decimal" value="<?= $a['precio'] === null ? '' : e(rtrim(rtrim((string) $a['precio'], '0'), '.')) ?>" placeholder="a consultar" aria-label="Precio"></td>
                            <td style="text-align:center"><input type="checkbox" form="<?= $f ?>" name="precio_desde" value="1" <?= (int) $a['precio_desde'] === 1 ? 'checked' : '' ?> aria-label="Precio desde"></td>
                            <td><input type="text" form="<?= $f ?>" name="demora_dias" inputmode="numeric" value="<?= $a['demora_dias'] === null ? '' : (int) $a['demora_dias'] ?>" aria-label="Demora en días"></td>
                            <td><span class="estado-badge <?= (int) $a['activo'] === 1 ? 'estado-3' : 'estado-4' ?>"><?= (int) $a['activo'] === 1 ? 'Visible' : 'Oculto' ?></span></td>
                            <td>
                                <div class="acciones">
                                    <button type="submit" form="<?= $f ?>" name="accion" value="guardar" class="btn-secundario">Guardar</button>
                                    <button type="submit" form="<?= $f ?>" name="accion" value="alternar" class="btn-secundario"><?= (int) $a['activo'] === 1 ? 'Ocultar' : 'Mostrar' ?></button>
                                    <button type="submit" form="<?= $f ?>" name="accion" value="eliminar" class="btn-secundario btn-peligro" onclick="return confirm('¿Eliminar «<?= e(addslashes($a['nombre'])) ?>» de la lista?');">Eliminar</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$arreglos): ?><tr><td colspan="7">Todavía no hay arreglos. Agregá el primero abajo.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="tarjeta">
            <h2>Agregar un arreglo</h2>
            <form method="post" action="guardar_arreglo.php" class="form-panel arreglo-nuevo">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" value="0">
                <div class="campo"><label for="n-nombre">Arreglo</label><input type="text" id="n-nombre" name="nombre" maxlength="<?= ARREGLO_NOMBRE_MAX ?>" value="<?= e($v('nombre')) ?>" placeholder="Ej: Cambio de forro" required></div>
                <div class="campo"><label for="n-desc">Descripción (opcional)</label><input type="text" id="n-desc" name="descripcion" maxlength="<?= ARREGLO_DESCRIPCION_MAX ?>" value="<?= e($v('descripcion')) ?>" placeholder="Ej: de saco o tapado"></div>
                <div class="campo"><label for="n-precio">Precio ($)</label><input type="text" id="n-precio" name="precio" inputmode="decimal" value="<?= e($v('precio')) ?>" placeholder="vacío = a consultar"></div>
                <div class="campo"><label for="n-demora">Demora (días)</label><input type="text" id="n-demora" name="demora_dias" inputmode="numeric" value="<?= e($v('demora_dias')) ?>" placeholder="Ej: 3"></div>
                <label class="check"><input type="checkbox" name="precio_desde" value="1" <?= $v('precio_desde') ? 'checked' : '' ?>> Precio «desde»</label>
                <button type="submit" class="btn-guardar">+ Agregar</button>
            </form>
        </section>
    </main>
</body>
</html>
