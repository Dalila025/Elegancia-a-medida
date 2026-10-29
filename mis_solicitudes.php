<?php
declare(strict_types=1);

/**
 * Mis solicitudes — panel del cliente — Elegancia a Medida
 *
 * Lista las solicitudes del cliente que inició sesión, con su estado,
 * el total presupuestado y los avisos sin leer. Las que esperan
 * respuesta del cliente aparecen destacadas.
 *
 * Tablas: solicitudes_presupuesto, presupuestos, notificaciones.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$cliente = requerir_cliente($pdo);
$flash   = tomar_flash_cliente();

try {
    $stmt = $pdo->prepare(
        'SELECT s.codigo_seguimiento, s.tipo_prenda, s.fecha_solicitud, s.id_estados_solicitud,
                p.monto_total, p.fecha_entrega,
                (SELECT COUNT(*) FROM notificaciones n
                  WHERE n.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto AND n.leida = 0) AS no_leidas
           FROM solicitudes_presupuesto s
           LEFT JOIN presupuestos p ON p.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
          WHERE s.id_clientes = :id
          ORDER BY (s.id_estados_solicitud = :presupuestado) DESC, s.fecha_solicitud DESC'
    );
    $stmt->execute([':id' => $cliente['id_clientes'], ':presupuestado' => ESTADO_PRESUPUESTADO]);
    $solicitudes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error en mis_solicitudes: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudieron cargar tus solicitudes. Intentá nuevamente más tarde.');
}

$esperando = count(array_filter($solicitudes, fn($s) => (int) $s['id_estados_solicitud'] === ESTADO_PRESUPUESTADO));

$pagina_actual = 'mis';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis solicitudes - Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/cliente.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/sitio.css') ?>">
</head>
<body>
<?php require __DIR__ . '/encabezado_cliente.php'; ?>
<main class="pagina">
    <div class="container ancha">
        <div class="encabezado-lista">
            <h1>Mis solicitudes</h1>
            <a href="solicitud.php" class="btn btn-inline">+ Nueva solicitud</a>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>

        <?php if ($esperando): ?>
            <div class="alert alert-info">
                Tenés <?= $esperando ?> presupuesto<?= $esperando > 1 ? 's' : '' ?> esperando tu respuesta.
                Entrá para aceptarlo o rechazarlo.
            </div>
        <?php endif; ?>

        <?php if (!$solicitudes): ?>
            <div class="vacio">
                <p>Todavía no enviaste ninguna solicitud.</p>
                <a href="solicitud.php" class="btn btn-inline">Pedir mi primer presupuesto</a>
            </div>
        <?php else: ?>
            <ul class="lista-sol">
                <?php foreach ($solicitudes as $s):
                    $est = (int) $s['id_estados_solicitud'];
                    $con_monto = $s['monto_total'] !== null && in_array($est, [ESTADO_PRESUPUESTADO, ESTADO_EN_PROCESO, ESTADO_COMPLETADO, ESTADO_NO_ACEPTADO], true);
                ?>
                    <li>
                        <a href="seguimiento.php?codigo=<?= e($s['codigo_seguimiento']) ?>">
                            <span>
                                <span class="titulo"><?= e($s['tipo_prenda']) ?></span>
                                <?php if ((int) $s['no_leidas'] > 0): ?>
                                    <span class="nuevo-tag"><?= (int) $s['no_leidas'] ?> nuevo<?= (int) $s['no_leidas'] > 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                                <span class="detalle">
                                    N° <?= e(formatear_codigo($s['codigo_seguimiento'])) ?> ·
                                    enviada el <?= (new DateTime($s['fecha_solicitud']))->format('d/m/Y') ?>
                                    <?php if ($con_monto && $s['fecha_entrega']): ?>
                                        · entrega <?= DateTime::createFromFormat('!Y-m-d', $s['fecha_entrega'])->format('d/m/Y') ?>
                                    <?php endif; ?>
                                </span>
                            </span>
                            <span class="derecha">
                                <span class="estado estado-<?= $est ?>"><?= e(estado_para_cliente($est)) ?></span>
                                <?php if ($con_monto): ?>
                                    <span class="monto"><?= pesos((float) $s['monto_total']) ?></span>
                                <?php endif; ?>
                                <?php if ($est === ESTADO_PRESUPUESTADO): ?>
                                    <span class="accion">Responder →</span>
                                <?php endif; ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/pie_cliente.php'; ?>
<script src="<?= asset('js/notificaciones.js') ?>"></script>
<script src="<?= asset('js/sitio.js') ?>"></script>
</body>
</html>
