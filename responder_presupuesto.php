<?php
declare(strict_types=1);

/**
 * Respuesta del cliente al presupuesto — Elegancia a Medida
 *
 * El cliente (con sesión iniciada y dueño de la solicitud) acepta o
 * rechaza el presupuesto desde seguimiento.php:
 *   · aceptar  → la solicitud pasa a "En proceso"
 *   · rechazar → la solicitud pasa a "No aceptado" (con comentario opcional)
 * Se guarda cuándo respondió en presupuestos.fecha_respuesta_cliente.
 *
 * Controles: token CSRF, dueño de la solicitud, que siga en "Presupuestado"
 * y que la modista no haya cambiado el presupuesto mientras el cliente
 * lo miraba (versión).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

require_once __DIR__ . '/conexion.php';

$cliente = cliente_logueado($pdo);
if ($cliente === null) {
    flash_cliente('info', 'Tu sesión terminó. Ingresá de nuevo para responder el presupuesto.');
    header('Location: login.php?volver=mis_solicitudes.php');
    exit;
}

$campo  = fn(string $n): string => isset($_POST[$n]) && is_string($_POST[$n]) ? trim($_POST[$n]) : '';
$codigo = normalizar_codigo($campo('codigo'));
$accion = $campo('accion');
$version = $campo('version');
$comentario = $campo('comentario');

/** Vuelve al detalle de la solicitud con un mensaje. */
function volver_detalle(string $codigo, string $tipo, string $mensaje): void
{
    flash_cliente($tipo, $mensaje);
    header('Location: ' . ($codigo !== '' ? 'seguimiento.php?codigo=' . $codigo : 'mis_solicitudes.php'));
    exit;
}

if (!token_cliente_valido()) {
    volver_detalle($codigo, 'error', 'El formulario expiró. Volvé a intentarlo.');
}
if ($codigo === '' || !in_array($accion, ['aceptar', 'rechazar'], true)) {
    volver_detalle($codigo, 'error', 'Datos inválidos.');
}
if (mb_strlen($comentario) > COMENTARIO_CLIENTE_MAX) {
    volver_detalle($codigo, 'error', 'El comentario no puede superar los ' . COMENTARIO_CLIENTE_MAX . ' caracteres.');
}

try {
    $pdo->beginTransaction();

    // Bloquea la fila mientras se responde (por si la modista la está editando).
    $stmt = $pdo->prepare(
        'SELECT s.id_solicitudes_presupuesto, s.id_estados_solicitud,
                p.monto_total, p.fecha_entrega, p.observaciones, p.fecha_creacion, p.fecha_actualizacion
           FROM solicitudes_presupuesto s
           INNER JOIN presupuestos p ON p.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
          WHERE s.codigo_seguimiento = :codigo AND s.id_clientes = :cliente
          FOR UPDATE'
    );
    $stmt->execute([':codigo' => $codigo, ':cliente' => $cliente['id_clientes']]);
    $sol = $stmt->fetch();

    if (!$sol) {
        $pdo->rollBack();
        volver_detalle('', 'error', 'No encontramos ese presupuesto en tu cuenta.');
    }
    if ((int) $sol['id_estados_solicitud'] !== ESTADO_PRESUPUESTADO) {
        $pdo->rollBack();
        volver_detalle($codigo, 'error', 'Este presupuesto ya no está esperando respuesta.');
    }
    if (!hash_equals(version_presupuesto($sol), $version)) {
        $pdo->rollBack();
        volver_detalle($codigo, 'info', 'La modista actualizó el presupuesto mientras lo mirabas. Revisalo y volvé a responder.');
    }
    if ($accion === 'aceptar' && DateTime::createFromFormat('!Y-m-d', $sol['fecha_entrega']) < new DateTime('today')) {
        $pdo->rollBack();
        volver_detalle($codigo, 'error', 'La fecha de entrega ya pasó: pedile a la modista que actualice el presupuesto.');
    }

    $id     = (int) $sol['id_solicitudes_presupuesto'];
    $nuevo  = $accion === 'aceptar' ? ESTADO_EN_PROCESO : ESTADO_NO_ACEPTADO;

    $pdo->prepare('UPDATE solicitudes_presupuesto SET id_estados_solicitud = :nuevo
                    WHERE id_solicitudes_presupuesto = :id AND id_estados_solicitud = :actual')
        ->execute([':nuevo' => $nuevo, ':id' => $id, ':actual' => ESTADO_PRESUPUESTADO]);

    $pdo->prepare('UPDATE presupuestos SET fecha_respuesta_cliente = NOW(), comentario_cliente = :c
                    WHERE id_solicitudes_presupuesto = :id')
        ->execute([':c' => $accion === 'rechazar' ? $comentario : '', ':id' => $id]);

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error al responder presupuesto: ' . $e->getMessage());
    volver_detalle($codigo, 'error', 'No se pudo registrar tu respuesta. Intentá nuevamente.');
}

volver_detalle(
    $codigo,
    'ok',
    $accion === 'aceptar'
        ? '¡Listo! Aceptaste el presupuesto. Ahora elegí qué día acercarte al taller.'
        : 'Registramos que no aceptaste el presupuesto. ¡Gracias por avisarnos!'
);
