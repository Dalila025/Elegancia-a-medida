<?php
declare(strict_types=1);

/**
 * Tarifas por plazo — Elegancia a Medida
 *
 * La modista define cuánto recarga (o descuenta) según los días que
 * faltan hasta la entrega. Tabla: tarifas_plazo.
 * Los cambios se procesan en guardar_tarifa.php.
 * Los presupuestos ya guardados NO cambian (guardan su propio recargo).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';

iniciar_sesion();
$token = token_modista();
$flash = tomar_flash();

require_once __DIR__ . '/conexion.php';

// Solo la modista con sesión iniciada (panel_login.php).
$usuario = requerir_modista($pdo);

try {
    $tarifas = obtener_tarifas($pdo);
} catch (PDOException $e) {
    error_log('Error al leer tarifas: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudieron cargar las tarifas.');
}

$desde = 0;
foreach ($tarifas as &$t) {
    $t['desde'] = $desde;
    $desde = $t['dias_hasta'] === null ? $desde : (int) $t['dias_hasta'] + 1;
}
unset($t);
$hay_sin_limite = in_array(null, array_column($tarifas, 'dias_hasta'), true);

$ejemplo = 10000;   // monto de ejemplo para mostrar el efecto de cada tarifa
$pagina_actual = 'tarifas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarifas por plazo · Elegancia a Medida</title>
    <link rel="stylesheet" href="css/modista.css">
    <link rel="stylesheet" href="css/panel.css">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container">
        <?php if ($flash): ?>
            <div class="flash <?= $flash['tipo'] === 'ok' ? 'flash-ok' : 'flash-error' ?>" role="status">
                <?= e($flash['mensaje']) ?>
            </div>
        <?php endif; ?>

        <section class="tarjeta">
            <h2>Tarifas por plazo de entrega</h2>
            <p style="margin-bottom:14px">
                Cuanto menos tiempo hay hasta la entrega, más se cobra. Cada tarifa cubre los plazos
                <strong>hasta</strong> la cantidad de días indicada; dejá «Hasta días» vacío en la última
                para que cubra cualquier plazo mayor. Un recargo negativo es un descuento.
            </p>

            <?php if (!$hay_sin_limite): ?>
                <div class="aviso">
                    Ninguna tarifa tiene «Hasta días» vacío: los plazos más largos que la última tarifa quedan sin recargo.
                </div>
            <?php endif; ?>

            <?php foreach ($tarifas as $t): ?>
                <form method="post" action="guardar_tarifa.php" id="f-<?= (int) $t['id_tarifas_plazo'] ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <input type="hidden" name="id" value="<?= (int) $t['id_tarifas_plazo'] ?>">
                </form>
            <?php endforeach; ?>

            <table class="tabla-tarifas">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Hasta días</th>
                        <th>Recargo %</th>
                        <th>Cubre</th>
                        <th>Ej. sobre <?= pesos($ejemplo) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tarifas as $t): $f = 'f-' . (int) $t['id_tarifas_plazo']; ?>
                    <tr>
                        <td><input type="text" name="nombre" form="<?= $f ?>" required
                                   maxlength="<?= TARIFA_NOMBRE_MAX ?>" value="<?= e($t['nombre']) ?>" aria-label="Nombre"></td>
                        <td><input type="number" name="dias_hasta" form="<?= $f ?>" min="0" max="<?= TARIFA_DIAS_MAX ?>" step="1"
                                   value="<?= $t['dias_hasta'] === null ? '' : (int) $t['dias_hasta'] ?>"
                                   placeholder="sin límite" aria-label="Hasta días"></td>
                        <td><input type="number" name="recargo_porcentaje" form="<?= $f ?>" required
                                   min="<?= RECARGO_MIN ?>" max="<?= RECARGO_MAX ?>" step="0.01"
                                   value="<?= e((string) (float) $t['recargo_porcentaje']) ?>" aria-label="Recargo %"></td>
                        <td class="rango"><?= e(texto_rango_tarifa($t['desde'], $t['dias_hasta'] === null ? null : (int) $t['dias_hasta'])) ?></td>
                        <td class="rango"><?= pesos(calcular_total($ejemplo, (float) $t['recargo_porcentaje'])) ?></td>
                        <td>
                            <div class="acciones">
                                <button type="submit" form="<?= $f ?>" name="accion" value="actualizar" class="btn-secundario">Guardar</button>
                                <button type="submit" form="<?= $f ?>" name="accion" value="eliminar" class="btn-secundario btn-peligro"
                                        onclick="return confirm('¿Eliminar la tarifa «<?= e(addslashes($t['nombre'])) ?>»?');">Eliminar</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$tarifas): ?>
                    <tr><td colspan="6">No hay tarifas cargadas: no se aplica ningún recargo.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section class="tarjeta">
            <h2>Agregar tarifa</h2>
            <form method="post" action="guardar_tarifa.php" class="form-panel">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="accion" value="crear">
                <div class="fila">
                    <div class="campo">
                        <label for="n-nombre">Nombre</label>
                        <input type="text" id="n-nombre" name="nombre" required maxlength="<?= TARIFA_NOMBRE_MAX ?>" placeholder="Ej: Express">
                    </div>
                    <div class="campo">
                        <label for="n-dias">Hasta días</label>
                        <input type="number" id="n-dias" name="dias_hasta" min="0" max="<?= TARIFA_DIAS_MAX ?>" step="1" placeholder="Vacío = sin límite">
                    </div>
                </div>
                <div class="campo" style="max-width:240px">
                    <label for="n-recargo">Recargo %</label>
                    <input type="number" id="n-recargo" name="recargo_porcentaje" required
                           min="<?= RECARGO_MIN ?>" max="<?= RECARGO_MAX ?>" step="0.01" placeholder="Ej: 60">
                </div>
                <button type="submit" class="btn-guardar">Agregar</button>
            </form>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <p>Elegancia a Medida · Panel de gestión (sin autenticación) · Ciclo 2026</p>
        </div>
    </footer>
</body>
</html>
