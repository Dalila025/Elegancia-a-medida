<?php
/**
 * Encabezado común del panel de la modista.
 * Antes de incluirlo, definir $pagina_actual: 'solicitudes' | 'tarifas'
 * Requiere sesión iniciada (auth.php).
 */
$pagina_actual = $pagina_actual ?? '';
$usuario_enc   = usuario_actual();
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
        <?php if ($usuario_enc): ?>
            <div class="usuario-sesion">
                <span class="usuario-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($usuario_enc['nombre'], 0, 1))) ?></span>
                <span class="usuario-nombre" title="<?= e($usuario_enc['email']) ?>"><?= e($usuario_enc['nombre']) ?></span>
                <form method="post" action="logout.php" class="form-salir">
                    <input type="hidden" name="token" value="<?= e(token_modista()) ?>">
                    <button type="submit" class="btn-salir" title="Cerrar sesión">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H4"/></svg>
                        <span>Salir</span>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</header>