<?php
declare(strict_types=1);

/**
 * Detalle de una solicitud — panel del cliente — Elegancia a Medida
 *
 * Solo el dueño de la solicitud (cliente con sesión iniciada) puede verla.
 * Muestra el estado, la respuesta de la modista y los avisos, y cuando hay
 * un presupuesto para responder, los botones Aceptar / Rechazar
 * (se procesan en responder_presupuesto.php).
 * Al abrirla, los avisos de esta solicitud quedan leídos.
 *
 * Tablas: solicitudes_presupuesto, tipos_servicio, presupuestos, notificaciones.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$cliente = requerir_cliente($pdo, 'Iniciá sesión para ver tu solicitud.');
$flash   = tomar_flash_cliente();

$codigo = normalizar_codigo(isset($_GET['codigo']) && is_string($_GET['codigo']) ? $_GET['codigo'] : '');
$sol    = null;
$avisos = [];

try {
    if ($codigo !== '') {
        // La condición id_clientes impide ver solicitudes de otra persona.
        $stmt = $pdo->prepare(
            'SELECT s.id_solicitudes_presupuesto, s.codigo_seguimiento, s.tipo_prenda, s.descripcion,
                    s.fecha_requerida, s.fecha_solicitud, s.id_estados_solicitud, t.nombre AS servicio,
                    p.monto_total, p.fecha_entrega, p.observaciones, p.fecha_creacion, p.fecha_actualizacion,
                    p.fecha_respuesta_cliente, p.comentario_cliente
               FROM solicitudes_presupuesto s
               INNER JOIN tipos_servicio t ON t.id_tipos_servicio = s.id_tipos_servicio
               LEFT JOIN presupuestos p ON p.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
              WHERE s.codigo_seguimiento = :codigo AND s.id_clientes = :cliente'
        );
        $stmt->execute([':codigo' => $codigo, ':cliente' => $cliente['id_clientes']]);
        $sol = $stmt->fetch() ?: null;
    }

    if ($sol) {
        $id = (int) $sol['id_solicitudes_presupuesto'];

        $stmt = $pdo->prepare(
            'SELECT titulo, mensaje, leida, fecha_creacion FROM notificaciones
              WHERE id_solicitudes_presupuesto = :id
              ORDER BY fecha_creacion DESC, id_notificaciones DESC'
        );
        $stmt->execute([':id' => $id]);
        $avisos = $stmt->fetchAll();

        // Al verlos, quedan leídos (la modista lo ve en su panel).
        $pdo->prepare('UPDATE notificaciones SET leida = 1, fecha_lectura = NOW()
                        WHERE id_solicitudes_presupuesto = :id AND leida = 0')
            ->execute([':id' => $id]);
    }
} catch (PDOException $e) {
    error_log('Error en seguimiento: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo consultar la solicitud. Intentá nuevamente más tarde.');
}

if (!$sol) {
    flash_cliente('error', 'No encontramos esa solicitud en tu cuenta.');
    header('Location: mis_solicitudes.php');
    exit;
}

$estado     = (int) $sol['id_estados_solicitud'];
$tiene_presu = $sol['monto_total'] !== null;
$mostrar_presu = $tiene_presu && in_array($estado, [ESTADO_PRESUPUESTADO, ESTADO_EN_PROCESO, ESTADO_COMPLETADO, ESTADO_NO_ACEPTADO], true);
$entrega    = $tiene_presu ? DateTime::createFromFormat('!Y-m-d', $sol['fecha_entrega']) : null;
$vencido    = $entrega && $entrega < new DateTime('today');
$puede_responder = $estado === ESTADO_PRESUPUESTADO && $tiene_presu;

// Pasos que ve el cliente
$pasos = [
    ESTADO_PENDIENTE     => 'Enviada',
    ESTADO_PRESUPUESTADO => 'Presupuesto',
    ESTADO_EN_PROCESO    => 'En confección',
    ESTADO_COMPLETADO    => 'Lista',
];
$orden_pasos = array_keys($pasos);
$pos = array_search($estado, $orden_pasos, true);

$pagina_actual = 'mis';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($sol['tipo_prenda']) ?> - Mis solicitudes - Elegancia a Medida</title>
    <link rel="stylesheet" href="css/cliente.css">
    <link rel="stylesheet" href="css/sitio.css">
</head>
<body>
<?php require __DIR__ . '/encabezado_cliente.php'; ?>
<main class="pagina">
    <div class="container">
        <p style="margin-bottom:16px"><a href="mis_solicitudes.php" class="link">← Mis solicitudes</a></p>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['tipo']) ?>" role="status"><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>

        <div class="cabecera-sol">
            <div>
                <h2><?= e($sol['tipo_prenda']) ?></h2>
                <span class="cod">N° <?= e(formatear_codigo($codigo)) ?> · enviada el <?= (new DateTime($sol['fecha_solicitud']))->format('d/m/Y') ?></span>
            </div>
            <span class="estado estado-<?= $estado ?>"><?= e(estado_para_cliente($estado)) ?></span>
        </div>

        <?php if ($pos !== false): ?>
            <ol class="pasos" aria-label="Avance de la solicitud">
                <?php foreach ($orden_pasos as $i => $cod): ?>
                    <li class="<?= $i <= $pos ? 'hecho' : '' ?> <?= $i === $pos ? 'actual' : '' ?>"><?= e($pasos[$cod]) ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if ($estado === ESTADO_PENDIENTE): ?>
            <div class="alert alert-info" style="margin-top:16px">
                La modista todavía está revisando tu pedido. Cuando responda, vas a ver acá el precio y la fecha
                de entrega para aceptarlo o rechazarlo.
            </div>
        <?php elseif ($estado === ESTADO_RECHAZADO): ?>
            <div class="alert alert-mal" style="margin-top:16px">
                Lamentablemente la modista no puede tomar este trabajo por el momento. ¡Gracias por consultarnos!
            </div>
        <?php elseif ($estado === ESTADO_CANCELADO): ?>
            <div class="alert alert-mal" style="margin-top:16px">
                Esta solicitud quedó cancelada. Si querés retomarla, comunicate con nosotros o hacé una nueva solicitud.
            </div>
        <?php elseif ($estado === ESTADO_NO_ACEPTADO): ?>
            <div class="alert alert-mal" style="margin-top:16px">
                No aceptaste este presupuesto<?= $sol['fecha_respuesta_cliente'] ? ' el ' . (new DateTime($sol['fecha_respuesta_cliente']))->format('d/m/Y') : '' ?>.
                Si la modista te prepara otra propuesta, te avisamos acá.
            </div>
        <?php endif; ?>

        <?php if ($mostrar_presu): ?>
            <div class="respuesta">
                <div>Total del trabajo</div>
                <div class="total"><?= pesos((float) $sol['monto_total']) ?></div>
                <dl>
                    <dt>Entrega estimada</dt>
                    <dd><?= $entrega->format('d/m/Y') ?></dd>
                    <?php if (trim((string) $sol['observaciones']) !== ''): ?>
                        <dt>Observaciones</dt>
                        <dd><?= e($sol['observaciones']) ?></dd>
                    <?php endif; ?>
                    <?php if ($estado === ESTADO_NO_ACEPTADO && trim((string) $sol['comentario_cliente']) !== ''): ?>
                        <dt>Tu comentario</dt>
                        <dd><?= e($sol['comentario_cliente']) ?></dd>
                    <?php endif; ?>
                </dl>

                <?php if ($puede_responder): ?>
                    <div class="responder">
                        <?php if ($vencido): ?>
                            <p>La fecha de entrega de este presupuesto ya pasó, así que no se puede aceptar.
                               Podés rechazarlo o esperar a que la modista lo actualice.</p>
                        <?php else: ?>
                            <p><strong>¿Aceptás este presupuesto?</strong> Si lo aceptás, la modista empieza a trabajar en tu pedido.</p>
                        <?php endif; ?>

                        <div class="acciones">
                            <?php if (!$vencido): ?>
                                <form method="post" action="responder_presupuesto.php" class="form-responder"
                                      data-confirmar="¿Confirmás que aceptás el presupuesto de <?= e(pesos((float) $sol['monto_total'])) ?> con entrega el <?= $entrega->format('d/m/Y') ?>?">
                                    <input type="hidden" name="token" value="<?= e(token_cliente()) ?>">
                                    <input type="hidden" name="codigo" value="<?= e($codigo) ?>">
                                    <input type="hidden" name="version" value="<?= e(version_presupuesto($sol)) ?>">
                                    <input type="hidden" name="accion" value="aceptar">
                                    <button type="submit" class="btn btn-aceptar">Aceptar presupuesto</button>
                                </form>
                            <?php endif; ?>

                            <details>
                                <summary class="btn btn-rechazar">Rechazar</summary>
                                <form method="post" action="responder_presupuesto.php" class="form-responder"
                                      data-confirmar="¿Confirmás que rechazás este presupuesto?">
                                    <input type="hidden" name="token" value="<?= e(token_cliente()) ?>">
                                    <input type="hidden" name="codigo" value="<?= e($codigo) ?>">
                                    <input type="hidden" name="version" value="<?= e(version_presupuesto($sol)) ?>">
                                    <input type="hidden" name="accion" value="rechazar">
                                    <label for="comentario">¿Nos contás por qué? (opcional)</label>
                                    <textarea id="comentario" name="comentario" maxlength="<?= COMENTARIO_CLIENTE_MAX ?>"
                                              placeholder="Ej: necesito la entrega antes, el precio supera mi presupuesto…"></textarea>
                                    <button type="submit" class="btn btn-rechazar">Confirmar rechazo</button>
                                </form>
                            </details>
                        </div>
                    </div>
                <?php elseif ($estado === ESTADO_EN_PROCESO && $sol['fecha_respuesta_cliente']): ?>
                    <p style="margin-top:10px;font-size:.88rem">
                        ✓ Aceptaste este presupuesto el <?= (new DateTime($sol['fecha_respuesta_cliente']))->format('d/m/Y') ?>.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($avisos): ?>
            <h2>Avisos</h2>
            <ul class="avisos">
                <?php foreach ($avisos as $a): ?>
                    <li class="<?= (int) $a['leida'] === 0 ? 'nuevo' : '' ?>">
                        <span class="t"><?= e($a['titulo']) ?></span>
                        <?php if ((int) $a['leida'] === 0): ?><span class="nuevo-tag">Nuevo</span><?php endif; ?>
                        <div><?= e($a['mensaje']) ?></div>
                        <div class="f"><?= (new DateTime($a['fecha_creacion']))->format('d/m/Y H:i') ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h2>Tu solicitud</h2>
        <dl class="datos">
            <dt>Servicio</dt>        <dd><?= e($sol['servicio']) ?></dd>
            <dt>Lo pediste para</dt> <dd><?= DateTime::createFromFormat('!Y-m-d', $sol['fecha_requerida'])->format('d/m/Y') ?></dd>
            <dt>Descripción</dt>     <dd><?= e($sol['descripcion']) ?></dd>
        </dl>

        <div class="activar">
            <p>Mientras tengas el sitio abierto, te avisamos apenas la modista responda.
               También puede aparecer una notificación del navegador.</p>
            <button type="button" class="btn-sec" data-activar-avisos><span data-texto>🔔 Activar avisos en este navegador</span></button>
        </div>
    </div>
</main>
<?php require __DIR__ . '/pie_cliente.php'; ?>

<script>
    // Confirmación antes de responder el presupuesto.
    document.querySelectorAll('.form-responder').forEach(function (f) {
        f.addEventListener('submit', function (ev) {
            if (!confirm(f.dataset.confirmar)) { ev.preventDefault(); }
        });
    });
</script>
<script src="js/notificaciones.js"></script>
<script src="js/sitio.js"></script>
</body>
</html>
