<?php
/**
 * Encabezado común del panel de la modista.
 * Antes de incluirlo, definir $pagina_actual: 'solicitudes' | 'tarifas'
 */
$pagina_actual = $pagina_actual ?? '';
?>
<header class="header">
    <div class="container header-inner">
        <div class="brand">
            <span class="brand-mark">E</span>
            <div>
                <h1>Elegancia a Medida</h1>
                <p class="subtitle">Panel de la modista</p>
            </div>
        </div>
        <nav class="nav-panel">
            <a href="modista.php" class="btn-outline <?= $pagina_actual === 'solicitudes' ? 'activo' : '' ?>">Solicitudes</a>
            <a href="tarifas.php" class="btn-outline <?= $pagina_actual === 'tarifas' ? 'activo' : '' ?>">Tarifas por plazo</a>
            <a href="index.php" class="btn-outline">Formulario de clientes</a>
        </nav>
    </div>
</header>
