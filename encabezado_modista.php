<?php
/**
 * Encabezado común del panel de la modista.
 * Antes de incluirlo definir:
 *   $usuario       → array de requerir_modista()
 *   $pagina_actual → 'solicitudes' | 'taller' | 'pedidos' | 'clientes' | 'senas' | 'gastos' | 'productos' | 'arreglos' | 'categorias' | 'portada' | 'tarifas' | 'sitio' | 'clave'
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
            <?php if (!$obligatorio_clave):
                $enlace = fn(string $url, string $clave, string $texto, string $extra = '') =>
                    '<a href="' . $url . '" class="btn-outline ' . ($pagina_actual === $clave ? 'activo' : '') . '"' . $extra . '>' . $texto . '</a>';
                $config_activa = in_array($pagina_actual, ['productos', 'categorias', 'arreglos', 'portada', 'tarifas', 'sitio', 'clave'], true); ?>
                <?= $enlace('modista.php', 'solicitudes', 'Solicitudes') ?>
                <?= $enlace('taller.php', 'taller', 'Taller') ?>
                <?= $enlace('pedidos.php', 'pedidos', 'Pedidos' . ($pendientes_panel ? ' <span class="globo-panel">' . $pendientes_panel . '</span>' : '')) ?>
                <?= $enlace('clientes.php', 'clientes', 'Clientas') ?>
                <?= $enlace('pagos.php', 'senas', 'Señas') ?>
                <?= $enlace('gastos.php', 'gastos', 'Gastos') ?>
                <details class="nav-mas">
                    <summary class="btn-outline <?= $config_activa ? 'activo' : '' ?>">Sitio y catálogo ▾</summary>
                    <div class="nav-mas-panel">
                        <?= $enlace('productos.php', 'productos', 'Catálogo') ?>
                        <?= $enlace('lista_arreglos.php', 'arreglos', 'Precios de arreglos') ?>
                        <?= $enlace('categorias.php', 'categorias', 'Categorías') ?>
                        <?= $enlace('banners.php', 'portada', 'Portada') ?>
                        <?= $enlace('tarifas.php', 'tarifas', 'Tarifas por plazo') ?>
                        <?= $enlace('sitio.php', 'sitio', 'Datos del sitio') ?>
                        <?= $enlace('panel_clave.php', 'clave', 'Cambiar contraseña') ?>
                    </div>
                </details>
                <a href="index.php" class="btn-outline" target="_blank" rel="noopener">Ver sitio ↗</a>
            <?php endif; ?>
            <form method="post" action="panel_logout.php" class="form-salir">
                <input type="hidden" name="token" value="<?= e(token_modista()) ?>">
                <button type="submit" class="btn-outline">Salir</button>
            </form>
        </nav>
    </div>
</header>
