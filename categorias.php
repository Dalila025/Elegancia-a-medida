<?php
declare(strict_types=1);

/**
 * Categorías y tipos de prenda (panel) — Elegancia a Medida
 *
 * Son los dos filtros del inicio:
 *   · Categorías (tabla categorias): ocasión o línea — Fiesta, Novias…
 *   · Tipos de prenda (tabla tipos_producto): Vestido, Falda, Traje…
 * Alta, cambio de nombre, ocultar/mostrar y baja.
 * Si tiene productos no se puede borrar (se puede ocultar).
 * Los cambios se procesan en guardar_categoria.php.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario = requerir_modista($pdo);
$token   = token_modista();
$flash   = tomar_flash();

try {
    $categorias = $pdo->query(
        'SELECT c.id_categorias AS id, c.nombre, c.descripcion, c.activo, COUNT(p.id_productos) AS productos
           FROM categorias c LEFT JOIN productos p ON p.id_categorias = c.id_categorias
          GROUP BY c.id_categorias, c.nombre, c.descripcion, c.activo
          ORDER BY c.nombre'
    )->fetchAll();
    $tipos = $pdo->query(
        'SELECT t.id_tipos_producto AS id, t.nombre, t.activo, COUNT(p.id_productos) AS productos
           FROM tipos_producto t LEFT JOIN productos p ON p.id_tipos_producto = t.id_tipos_producto
          GROUP BY t.id_tipos_producto, t.nombre, t.activo
          ORDER BY t.nombre'
    )->fetchAll();
} catch (PDOException $e) {
    error_log('Error al listar categorías/tipos: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudieron cargar las categorías.');
}

/** Tabla editable de una lista (categorías o tipos). */
function tabla_lista(string $lista, array $filas, string $token, bool $con_descripcion): void
{
    $max_nombre = $lista === 'categorias' ? CATEGORIA_NOMBRE_MAX : TIPO_NOMBRE_MAX;
    $filtro     = $lista === 'categorias' ? 'categoria' : 'tipo';
    foreach ($filas as $c): ?>
        <form method="post" action="guardar_categoria.php" id="<?= $lista ?>-<?= (int) $c['id'] ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="lista" value="<?= $lista ?>">
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
        </form>
    <?php endforeach; ?>
    <table class="tabla-tarifas">
        <thead>
            <tr><th>Nombre</th><?php if ($con_descripcion): ?><th>Descripción</th><?php endif; ?><th>Productos</th><th>En el sitio</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($filas as $c): $f = $lista . '-' . (int) $c['id']; $n = (int) $c['productos']; ?>
            <tr>
                <td><input type="text" name="nombre" form="<?= $f ?>" required maxlength="<?= $max_nombre ?>"
                           value="<?= e($c['nombre']) ?>" aria-label="Nombre"></td>
                <?php if ($con_descripcion): ?>
                    <td><input type="text" name="descripcion" form="<?= $f ?>" maxlength="<?= CATEGORIA_DESC_MAX ?>"
                               value="<?= e($c['descripcion']) ?>" aria-label="Descripción"></td>
                <?php endif; ?>
                <td><a href="productos.php?<?= $filtro === 'categoria' ? 'categoria=' . (int) $c['id'] : '' ?>"><?= $n ?></a></td>
                <td><span class="estado-badge <?= (int) $c['activo'] === 1 ? 'estado-3' : 'estado-4' ?>"><?= (int) $c['activo'] === 1 ? 'Visible' : 'Oculto' ?></span></td>
                <td>
                    <div class="acciones">
                        <button type="submit" form="<?= $f ?>" name="accion" value="actualizar" class="btn-secundario">Guardar</button>
                        <button type="submit" form="<?= $f ?>" name="accion" value="alternar" class="btn-secundario"><?= (int) $c['activo'] === 1 ? 'Ocultar' : 'Mostrar' ?></button>
                        <?php if ($n === 0): ?>
                            <button type="submit" form="<?= $f ?>" name="accion" value="eliminar" class="btn-secundario btn-peligro"
                                    onclick="return confirm('¿Eliminar «<?= e(addslashes($c['nombre'])) ?>»?');">Eliminar</button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$filas): ?>
            <tr><td colspan="5">Todavía no hay ninguno. Agregá el primero abajo.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <form method="post" action="guardar_categoria.php" class="form-panel form-agregar">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="lista" value="<?= $lista ?>">
        <input type="hidden" name="accion" value="crear">
        <input type="text" name="nombre" required maxlength="<?= $max_nombre ?>" aria-label="Nombre nuevo"
               placeholder="<?= $lista === 'categorias' ? 'Nueva categoría, ej: Fiesta' : 'Nuevo tipo, ej: Blusa' ?>">
        <?php if ($con_descripcion): ?>
            <input type="text" name="descripcion" maxlength="<?= CATEGORIA_DESC_MAX ?>" aria-label="Descripción" placeholder="Descripción (opcional)">
        <?php endif; ?>
        <button type="submit" class="btn-guardar">Agregar</button>
    </form>
    <?php
}

$pagina_actual = 'categorias';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Categorías y tipos · Panel · Elegancia a Medida</title>
    <link rel="stylesheet" href="css/modista.css">
    <link rel="stylesheet" href="css/panel.css">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container">
        <?php if ($flash): ?>
            <div class="flash <?= $flash['tipo'] === 'ok' ? 'flash-ok' : 'flash-error' ?>" role="status"><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>

        <section class="tarjeta">
            <h2>Categorías</h2>
            <p style="margin-bottom:14px">La ocasión o línea de cada prenda (Fiesta, Novias, Egresados…). Son los botones de filtro del inicio. Ocultar una categoría esconde todos sus productos.</p>
            <?php tabla_lista('categorias', $categorias, $token, true); ?>
        </section>

        <section class="tarjeta">
            <h2>Tipos de prenda</h2>
            <p style="margin-bottom:14px">Qué es cada producto (Vestido, Falda, Traje…). Es el filtro «Tipo de prenda» del inicio.</p>
            <?php tabla_lista('tipos', $tipos, $token, false); ?>
        </section>
    </main>
</body>
</html>
