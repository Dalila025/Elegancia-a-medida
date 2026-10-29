<?php
declare(strict_types=1);

/**
 * Arreglos: lista de precios (sitio público) — Elegancia a Medida
 * Muestra los arreglos visibles con precio y demora aproximada. Cada uno
 * lleva al formulario de solicitud (el aprobado en TP11) con el arreglo escrito.
 * Tabla: arreglos.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$cliente = cliente_logueado($pdo);
try {
    $arreglos = obtener_arreglos($pdo);
} catch (PDOException $e) {
    error_log('Error en la lista de arreglos: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo cargar la lista de precios.');
}
$pagina_actual = 'arreglos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arreglos: lista de precios - Elegancia a Medida</title>
    <meta name="description" content="Precios de arreglos de ropa: dobladillos, ajustes, cierres, entallados y más.">
    <link rel="stylesheet" href="<?= asset('css/cliente.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/sitio.css') ?>">
</head>
<body>
<?php require __DIR__ . '/encabezado_cliente.php'; ?>

<main class="pagina-sitio" style="max-width:980px">
    <p class="migas"><a href="index.php">Inicio</a> / Arreglos</p>
    <header class="titulo-elegante" style="margin-bottom:26px">
        <span class="antetitulo">Lista de precios</span>
        <h2>Arreglos</h2>
    </header>
    <p class="arreglos-intro">Precios orientativos: la modista confirma el valor final al ver la prenda.
       Elegí el arreglo y contanos los detalles en el formulario.</p>

    <?php if (!$arreglos): ?>
        <div class="vacio-grande"><p>Pronto vas a ver acá la lista de precios.</p>
            <a class="btn-pill" href="solicitud.php?id_tipos_servicio=<?= SERVICIO_ARREGLOS ?>">Pedir presupuesto de arreglo</a></div>
    <?php else: ?>
        <ul class="lista-precios">
            <?php foreach ($arreglos as $a): ?>
                <li>
                    <div class="lp-texto">
                        <h3><?= e($a['nombre']) ?></h3>
                        <?php if ($a['descripcion'] !== ''): ?><p><?= e($a['descripcion']) ?></p><?php endif; ?>
                    </div>
                    <span class="lp-puntos" aria-hidden="true"></span>
                    <div class="lp-precio">
                        <strong><?= e(precio_arreglo($a)) ?></strong>
                        <?php if (demora_arreglo($a) !== ''): ?><small>listo en ~<?= e(demora_arreglo($a)) ?></small><?php endif; ?>
                    </div>
                    <a class="btn-pill borde lp-boton" href="<?= e(enlace_pedir_arreglo($a)) ?>">Pedir</a>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="lp-pie">
            <p>¿No está tu arreglo en la lista?</p>
            <a class="btn-pill" href="solicitud.php?id_tipos_servicio=<?= SERVICIO_ARREGLOS ?>">Pedir presupuesto de otro arreglo</a>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/pie_cliente.php'; ?>
<script src="<?= asset('js/sitio.js') ?>"></script>
<?php if ($cliente): ?><script src="<?= asset('js/notificaciones.js') ?>"></script><?php endif; ?>
</body>
</html>
