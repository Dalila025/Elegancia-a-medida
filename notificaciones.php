<?php
declare(strict_types=1);

/**
 * Avisos pendientes del cliente (JSON) — Elegancia a Medida
 *
 * Lo consulta js/notificaciones.js cada NOTIF_INTERVALO_SEG segundos.
 * Devuelve las notificaciones NO leídas (de solicitudes y de pedidos) del
 * cliente con sesión iniciada (en cualquier dispositivo donde haya ingresado).
 * No marca nada como leído: eso pasa al abrir seguimiento.php o mis_pedidos.php.
 *
 * Tablas: notificaciones, solicitudes_presupuesto, pedidos.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$respuesta = ['intervalo' => NOTIF_INTERVALO_SEG, 'no_leidas' => 0, 'avisos' => []];

require_once __DIR__ . '/conexion.php';

try {
    $cliente = cliente_logueado($pdo);

    // Se libera la sesión enseguida: así esta consulta periódica no frena
    // las otras páginas que el cliente abra al mismo tiempo.
    session_write_close();

    if ($cliente === null) {
        echo json_encode($respuesta);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT n.id_notificaciones, n.titulo, n.mensaje, n.fecha_creacion,
                s.codigo_seguimiento, p.codigo AS codigo_pedido
           FROM notificaciones n
           LEFT JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = n.id_solicitudes_presupuesto
           LEFT JOIN pedidos p                 ON p.id_pedidos = n.id_pedidos
          WHERE n.leida = 0 AND (s.id_clientes = :c1 OR p.id_clientes = :c2)
          ORDER BY n.fecha_creacion DESC, n.id_notificaciones DESC
          LIMIT 20'
    );
    $stmt->execute([':c1' => $cliente['id_clientes'], ':c2' => $cliente['id_clientes']]);

    foreach ($stmt->fetchAll() as $n) {
        $respuesta['avisos'][] = [
            'id'      => (int) $n['id_notificaciones'],
            'titulo'  => $n['titulo'],
            'mensaje' => $n['mensaje'],
            'fecha'   => (new DateTime($n['fecha_creacion']))->format('d/m/Y H:i'),
            'url'     => $n['codigo_pedido'] !== null
                ? 'mis_pedidos.php?codigo=' . $n['codigo_pedido']
                : 'seguimiento.php?codigo=' . $n['codigo_seguimiento'],
        ];
    }
    $respuesta['no_leidas'] = count($respuesta['avisos']);

} catch (PDOException $e) {
    error_log('Error al leer notificaciones: ' . $e->getMessage());
    http_response_code(500);
}

echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
