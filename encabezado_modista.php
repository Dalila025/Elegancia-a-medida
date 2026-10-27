<?php
/**
 * Encabezado común del panel de la modista.
 * Antes de incluirlo definir:
 *   $usuario       → array de requerir_modista()
 *   $pagina_actual → 'solicitudes' | 'pedidos' | 'productos' | 'categorias' | 'portada' | 'tarifas' | 'sitio' | 'clave'
 *   $pdo           → conexión
 */
$pagina_actual = $pagina_actual ?? '';
$obligatorio_clave = !empty($usuario) && (int) $usuario['debe_cambiar_clave'] === 1;
// Pedidos pendientes (número en el botón "Pedidos")
$pendientes_panel = 0;
try {
    $pendientes_panel = (int) $pdo->query('SELECT COUNT(*) FROM pedidos WHERE id_estados_pedido = ' . PEDIDO_PENDIENTE)->fetchColumn();
} catch (PDOException $e) { /* no crítico */ }
$logo_panel = url_logo($pdo);
?>
<header class="header">
    <div class="container header-inner">
        <div class="brand">
            <?php if ($logo_panel): ?><img class="brand-logo" src="<?= e($logo_panel) ?>" alt=""><?php else: ?><span class="brand-mark">E</span><?php endif; ?>
            <div>
                <h1>Elegancia a Medida</h1>
                <p class="subtitle">Panel de la modista<?= !empty($usuario) ? ' · ' . e($usuario['nombre']) : '' ?></p>
            </div>
        </div>
        <nav class="nav-panel" aria-label="Menú del panel">
            <?php if (!$obligatorio_clave): ?>
                <a href="modista.php"    class="btn-outline <?= $pagina_actual === 'solicitudes' ? 'activo' : '' ?>">Solicitudes</a>
                <a href="pedidos.php"    class="btn-outline <?= $pagina_actual === 'pedidos'     ? 'activo' : '' ?>">Pedidos<?= $pendientes_panel ? ' <span class="globo-panel">' . $pendientes_panel . '</span>' : '' ?></a>
                <a href="productos.php"  class="btn-outline <?= $pagina_actual === 'productos'   ? 'activo' : '' ?>">Catálogo</a>
                <a href="categorias.php" class="btn-outline <?= $pagina_actual === 'categorias'  ? 'activo' : '' ?>">Categorías</a>
                <a href="banners.php"    class="btn-outline <?= $pagina_actual === 'portada'     ? 'activo' : '' ?>">Portada</a>
                <a href="tarifas.php"    class="btn-outline <?= $pagina_actual === 'tarifas'     ? 'activo' : '' ?>">Tarifas</a>
                <a href="sitio.php"      class="btn-outline <?= $pagina_actual === 'sitio'       ? 'activo' : '' ?>">Datos del sitio</a>
                <a href="index.php"      class="btn-outline" target="_blank" rel="noopener">Ver sitio ↗</a>
                <a href="panel_clave.php" class="btn-outline <?= $pagina_actual === 'clave' ? 'activo' : '' ?>" title="Cambiar contraseña">Contraseña</a>
            <?php endif; ?>
            <form method="post" action="panel_logout.php" class="form-salir">
                <input type="hidden" name="token" value="<?= e(token_modista()) ?>">
                <button type="submit" class="btn-outline">Salir</button>
            </form>
        </nav>
    </div>
</header>
