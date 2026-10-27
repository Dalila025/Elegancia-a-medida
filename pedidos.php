<?php
declare(strict_types=1);

/**
 * Pedidos del carrito (panel) — Elegancia a Medida
 *
 * La modista ve los pedidos de venta y alquiler, con sus prendas, fechas y
 * aclaraciones del cliente, y los avanza: Confirmar, Listo para retirar,
 * Entregado, Devuelto (alquiler) o Rechazar. Puede dejar una nota que el
 * cliente ve. Los cambios se procesan en actualizar_pedido.php.
 *
 * Tablas: pedidos, items_pedido, clientes, estados_pedido.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario = requerir_modista($pdo);
$token   = token_modista();
$flash   = tomar_flash();

$filtro = filter_input(INPUT_GET, 'estado', FILTER_VALIDATE_INT) ?: 0;
if (!isset(ESTADOS_PEDIDO[$filtro])) { $filtro = 0; }

try {
    $sql = 'SELECT p.*, c.nombre AS cliente, c.telefono, c.email,
                   (SELECT COUNT(*) FROM items_pedido i WHERE i.id_pedidos = p.id_pedidos AND i.modalidad = \'alquiler\') AS n_alquiler
              FROM pedidos p INNER JOIN clientes c ON c.id_clientes = p.id_clientes';
    $params = [];
    if ($filtro) { $sql .= ' WHERE p.id_estados_pedido = :e'; $params[':e'] = $filtro; }
    $sql .= ' ORDER BY (p.id_estados_pedido = ' . PEDIDO_PENDIENTE . ') DESC, p.fecha_pedido DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();

    $items = [];
    if ($pedidos) {
        $ids = array_column($pedidos, 'id_pedidos');
        $stmt = $pdo->prepare('SELECT * FROM items_pedido WHERE id_pedidos IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id_items_pedido');
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $it) { $items[(int) $it['id_pedidos']][] = $it; }
    }

    $conteos = array_fill_keys(array_keys(ESTADOS_PEDIDO), 0);
    foreach ($pdo->query('SELECT id_estados_pedido AS e, COUNT(*) AS n FROM pedidos GROUP BY id_estados_pedido') as $f) {
        $conteos[(int) $f['e']] = (int) $f['n'];
    }
} catch (PDOException $e) {
    error_log('Error al listar pedidos: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudieron cargar los pedidos.');
}

$fecha = fn(?string $d) => $d ? DateTime::createFromFormat('!Y-m-d', $d)->format('d/m/Y') : '';
$pagina_actual = 'pedidos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Pedidos · Panel · Elegancia a Medida</title>
    <link rel="stylesheet" href="css/modista.css">
    <link rel="stylesheet" href="css/panel.css">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container">
        <section class="toolbar">
            <div class="filtros-estados">
                <a href="pedidos.php" class="chip <?= $filtro === 0 ? 'activo' : '' ?>">Todos <span class="badge"><?= array_sum($conteos) ?></span></a>
                <?php foreach (ESTADOS_PEDIDO as $cod => $nom): ?>
                    <a href="pedidos.php?estado=<?= $cod ?>" class="chip <?= $filtro === $cod ? 'activo' : '' ?>"><?= e($nom) ?> <span class="badge"><?= $conteos[$cod] ?></span></a>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($flash): ?>
            <div class="flash <?= $flash['tipo'] === 'ok' ? 'flash-ok' : 'flash-error' ?>" role="status"><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>

        <?php if (!$pedidos): ?>
            <div class="tarjeta vacio"><p>No hay pedidos<?= $filtro ? ' en este estado' : ' todavía' ?>.</p></div>
        <?php endif; ?>

        <?php foreach ($pedidos as $p):
            $idp = (int) $p['id_pedidos'];
            $est = (int) $p['id_estados_pedido'];
            $acciones = transiciones_pedido($est, (int) $p['n_alquiler'] > 0); ?>
            <section class="tarjeta pedido-panel">
                <div class="pedido-cabeza">
                    <div>
                        <h2>Pedido <span class="codigo-seg" style="display:inline"><?= e(formatear_codigo($p['codigo'])) ?></span></h2>
                        <p class="sin-acciones" style="opacity:1">
                            <strong><?= e($p['cliente']) ?></strong> ·
                            <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $p['telefono'])) ?>"><?= e($p['telefono']) ?></a> ·
                            <?= e($p['email']) ?> · <?= (new DateTime($p['fecha_pedido']))->format('d/m/Y H:i') ?>
                        </p>
                    </div>
                    <span class="estado-badge pedido-est-<?= $est ?>"><?= e(ESTADOS_PEDIDO[$est] ?? '') ?></span>
                </div>

                <table class="tabla-tarifas">
                    <thead><tr><th>Prenda</th><th>Modalidad</th><th>Cant.</th><th>Precio c/u</th><th>Subtotal</th></tr></thead>
                    <tbody>
                    <?php foreach ($items[$idp] ?? [] as $it): ?>
                        <tr>
                            <td><?= e($it['nombre_producto']) ?><?= (int) $it['descuento_porcentaje'] ? ' <small>(−' . (int) $it['descuento_porcentaje'] . ' %)</small>' : '' ?></td>
                            <td><?= $it['modalidad'] === 'alquiler' ? '<strong>Alquiler</strong>' : 'Venta' ?></td>
                            <td><?= (int) $it['cantidad'] ?></td>
                            <td><?= $it['precio_unitario'] === null ? 'A consultar' : e(pesos((float) $it['precio_unitario'])) ?></td>
                            <td><?= $it['precio_unitario'] === null ? '—' : e(pesos((float) $it['precio_unitario'] * (int) $it['cantidad'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="pedido-datos">
                    <span><strong>Total estimado:</strong> <?= e(pesos((float) $p['total_estimado'])) ?><?= (int) $p['tiene_a_consultar'] ? ' + a consultar' : '' ?></span>
                    <?php if ($p['fecha_retiro']): ?>
                        <span><strong>Alquiler:</strong> retira <?= $fecha($p['fecha_retiro']) ?> · devuelve <?= $fecha($p['fecha_devolucion']) ?></span>
                    <?php endif; ?>
                    <?php if ($p['comentario_cliente'] !== ''): ?><span><strong>Aclaraciones:</strong> <?= e($p['comentario_cliente']) ?></span><?php endif; ?>
                </div>

                <?php if ($acciones): ?>
                    <form method="post" action="actualizar_pedido.php" class="form-panel pedido-acciones">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <input type="hidden" name="id" value="<?= $idp ?>">
                        <input type="hidden" name="volver_estado" value="<?= $filtro ?>">
                        <div class="campo">
                            <label for="nota-<?= $idp ?>">Nota para el cliente (opcional)</label>
                            <input type="text" id="nota-<?= $idp ?>" name="nota" maxlength="500" value="<?= e($p['nota_modista']) ?>"
                                   placeholder="Ej: Podés retirarlo el jueves desde las 16 h. Seña del 50 %.">
                        </div>
                        <div class="acciones">
                            <?php foreach ($acciones as $nuevo => $texto): ?>
                                <button type="submit" name="estado_nuevo" value="<?= (int) $nuevo ?>"
                                        class="btn-accion <?= $nuevo === PEDIDO_RECHAZADO ? 'accion-5' : 'accion-2' ?>"
                                        <?= $nuevo === PEDIDO_RECHAZADO ? 'onclick="return confirm(\'¿Rechazar este pedido?\');"' : '' ?>><?= e($texto) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </form>
                <?php elseif ($p['nota_modista'] !== ''): ?>
                    <p class="ayuda">Nota enviada: <?= e($p['nota_modista']) ?></p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </main>
</body>
</html>
