<?php
declare(strict_types=1);

/**
 * Clientas (panel) — Elegancia a Medida
 *
 * Lista de clientas con sus datos de contacto, si tienen medidas cargadas
 * cuántos pedidos/solicitudes tienen y el SALDO de su cuenta corriente.
 * Filtro «Con saldo pendiente». Cada una abre su ficha (cliente.php).
 * Tablas: clientes, medidas_clientes, pedidos, solicitudes_presupuesto.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/funciones_cuenta.php';
iniciar_sesion();
require_once __DIR__ . '/conexion.php';

$usuario = requerir_modista($pdo);
$flash   = tomar_flash();
$solo_deuda = isset($_GET['deuda']);
$buscar  = isset($_GET['q']) && is_string($_GET['q']) ? trim(mb_substr($_GET['q'], 0, 60)) : '';

try {
    $sql = 'SELECT c.id_clientes, c.nombre, c.telefono, c.email, c.fecha_alta,
                   (SELECT MAX(m.fecha_carga) FROM medidas_clientes m WHERE m.id_clientes = c.id_clientes) AS medidas_fecha,
                   (SELECT COUNT(*) FROM pedidos p WHERE p.id_clientes = c.id_clientes) AS n_pedidos,
                   (SELECT COUNT(*) FROM solicitudes_presupuesto s WHERE s.id_clientes = c.id_clientes) AS n_solicitudes
              FROM clientes c';
    $params = [];
    if ($buscar !== '') {
        $sql .= ' WHERE c.nombre LIKE :q1 OR c.email LIKE :q2 OR c.telefono LIKE :q3';
        $like = '%' . addcslashes($buscar, '%_\\') . '%';
        $params = [':q1' => $like, ':q2' => $like, ':q3' => $like];
    }
    $sql .= ' ORDER BY c.nombre';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clientas = $stmt->fetchAll();
    $saldos   = saldos_clientes($pdo);
    $total_adeudado = array_sum(array_map(fn($s) => max(0, $s['saldo']), $saldos));
    if ($solo_deuda) {
        $clientas = array_values(array_filter($clientas, fn($c) => ($saldos[(int) $c['id_clientes']]['saldo'] ?? 0) > 0.009));
    }
} catch (PDOException $e) {
    error_log('Error al listar clientas: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudieron cargar las clientas.');
}

$pagina_actual = 'clientes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Clientas · Panel · Elegancia a Medida</title>
    <link rel="stylesheet" href="<?= asset('css/modista.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/panel.css') ?>">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>
    <main class="container">
        <?php if ($flash): ?>
            <div class="flash <?= $flash['tipo'] === 'ok' ? 'flash-ok' : 'flash-error' ?>" role="status"><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>
        <section class="tarjeta">
            <div class="pedido-cabeza">
                <div><h2>Clientas</h2><p class="ayuda" style="margin:0">Abrí la ficha de cada clienta para ver y cargar sus medidas.</p></div>
                <form method="get" class="form-panel buscar-clientas">
                    <label class="check"><input type="checkbox" name="deuda" value="1" <?= $solo_deuda ? 'checked' : '' ?> onchange="this.form.submit()"> Con saldo pendiente</label>
                    <input type="search" name="q" value="<?= e($buscar) ?>" placeholder="Buscar por nombre, email o teléfono" aria-label="Buscar clienta">
                    <button type="submit" class="btn-secundario">Buscar</button>
                </form>
            </div>
            <p class="total-adeudado">Total que te deben: <strong><?= e(pesos($total_adeudado)) ?></strong></p>
            <?php if (!$clientas): ?>
                <p class="sin-acciones">No hay clientas<?= $buscar !== '' ? ' con esa búsqueda' : ' registradas todavía' ?>.</p>
            <?php else: ?>
                <table class="tabla-tarifas">
                    <thead><tr><th>Clienta</th><th>Contacto</th><th>Medidas</th><th>Pedidos</th><th>Solicitudes</th><th>Cuenta</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($clientas as $c): ?>
                        <tr>
                            <td><strong><?= e($c['nombre']) ?></strong></td>
                            <td><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $c['telefono'])) ?>"><?= e($c['telefono']) ?></a><br><span class="ref-catalogo"><?= e($c['email']) ?></span></td>
                            <td><?= $c['medidas_fecha'] ? '<span class="sena-estado ok">✓ ' . (new DateTime($c['medidas_fecha']))->format('d/m/Y') . '</span>' : '<span class="sena-estado falta">Sin cargar</span>' ?></td>
                            <td><?= (int) $c['n_pedidos'] ?></td>
                            <td><?= (int) $c['n_solicitudes'] ?></td>
                            <?php $sal = $saldos[(int) $c['id_clientes']]['saldo'] ?? 0.0; ?>
                            <td><span class="saldo-badge <?= $sal > 0.009 ? 'debe' : ($sal < -0.009 ? 'favor' : 'al-dia') ?>"><?= e(texto_saldo($sal)) ?></span></td>
                            <td><a class="btn-accion accion-presupuestar" href="cliente.php?id=<?= (int) $c['id_clientes'] ?>">Ver ficha</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
