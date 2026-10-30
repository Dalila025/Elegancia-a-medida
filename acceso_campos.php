<?php
/**
 * Piezas de las tarjetas de acceso (login.php, registro.php, panel_login.php).
 * Estilo en css/acceso.css: tarjeta oscura con los colores del sitio, campos
 * con ícono y botones redondeados.
 */

/** Ícono de un campo (trazo fino, hereda el color). Independiente del encabezado. */
function icono_acceso(string $nombre): string
{
    $trazos = [
        'arroba'   => '<circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/>',
        'candado'  => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'persona'  => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
        'telefono' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        'ojo'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    ];
    return '<svg class="acceso-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($trazos[$nombre] ?? '') . '</svg>';
}

/**
 * Un campo con ícono. $attrs = atributos extra del input (ya escapados por quien llama
 * o constantes). La etiqueta queda para lectores de pantalla y el texto visible es el placeholder.
 */
function campo_acceso(string $icono, string $id, string $etiqueta, string $tipo, string $valor = '', string $attrs = ''): void
{
    ?>
    <div class="acceso-campo">
        <label for="<?= e($id) ?>" class="acceso-etiqueta"><?= e($etiqueta) ?></label>
        <div class="acceso-input">
            <?= icono_acceso($icono) ?>
            <input type="<?= e($tipo) ?>" id="<?= e($id) ?>" name="<?= e($id) ?>" placeholder="<?= e($etiqueta) ?>"
                   value="<?= e($valor) ?>" <?= $attrs ?>>
            <?php if ($tipo === 'password'): ?>
                <button type="button" class="acceso-ver" data-ver="<?= e($id) ?>" aria-label="Mostrar contraseña" aria-pressed="false"><?= icono_acceso('ojo') ?></button>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/** Script de «mostrar contraseña» (una sola vez por página). */
function script_acceso(): void
{
    ?>
    <script>
    document.querySelectorAll('.acceso-ver').forEach(function (b) {
        b.addEventListener('click', function () {
            var i = document.getElementById(b.dataset.ver);
            var ver = i.type === 'password';
            i.type = ver ? 'text' : 'password';
            b.setAttribute('aria-pressed', ver ? 'true' : 'false');
            b.setAttribute('aria-label', ver ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    });
    </script>
    <?php
}
