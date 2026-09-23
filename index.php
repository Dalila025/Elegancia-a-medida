<?php
require_once __DIR__ . '/config.php';

// La sesión debe iniciarse antes de cualquier salida HTML.
session_start();

// Token de un solo uso: evita el reenvío duplicado del formulario
// y valida que el POST provenga de esta misma página. (Corrige P07)
if (empty($_SESSION['token_formulario'])) {
    $_SESSION['token_formulario'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['token_formulario'];

// Atajo para escapar salida. (Corrige P02)
function e(?string $valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// Valor previo del formulario cuando se vuelve con errores.
function viejo(string $campo): string {
    return isset($_GET[$campo]) && is_string($_GET[$campo]) ? e($_GET[$campo]) : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Presupuesto - Elegancia a Medida</title>
    <style>
        :root {
            --color-fondo: #f8f4ef;
            --color-encabezado: #231f20;
            --color-boton: #b7898f;
            --color-boton-hover: #695852;
            --color-titulo: #231f20;
            --color-borde: #bf9d5c;
            --color-texto: #46413f;

        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--color-fondo) ;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--color-texto);
        }
        .container {
            background: #fff;
            border-radius: 16px;
            border: 1px solid rgba(191, 157, 92, 0.15);
            box-shadow: 0 20px 60px rgba(35,31,32,0.15);
            max-width: 560px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            text-align: center;
            color: var(--color-titulo);
            margin-bottom: 8px;
            font-size: 1.75rem;
        }
        .subtitle {
            text-align: center;
            color: var(--color-texto);
            opacity: 0.85;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--color-encabezado);
            font-size: 0.9rem;
        }
        label .required { color: #e74c3c; }
        input[type="text"],
        input[type="tel"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid rgba(191, 157, 92, 0.45);
            border-radius: 8px;
            font-size: 1rem;
            color: var(--color-texto);
            transition: border-color 0.2s, box-shadow 0.2s;
            font-family: inherit;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--color-boton);
            box-shadow: 0 0 0 3px rgba(183, 137, 143, 0.2);
        }
        textarea { min-height: 110px; resize: vertical; }
        .btn {
            width: 100%;
            padding: 14px;
            background: var(--color-boton);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s, box-shadow 0.15s;
            margin-top: 10px;
        }
        .btn:hover {
            color: var(--color-boton-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(105, 88, 82, 0.35);
        }
        .btn:active { transform: translateY(0); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 22px;
            font-size: 0.95rem;
        }
        .alert-error {
            background: #fdecea;
            color: #c0392b;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background: #e8f8f0;
            color: #1e7e34;
            border: 1px solid #c3e6cb;
        }
        .alert ul { margin: 8px 0 0 18px; }
        .contador { font-size: 0.8rem; color: var(--color-texto); opacity: 0.7; text-align: right; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Solicitud de Presupuesto</h1>
        <p class="subtitle">Elegancia a Medida — Complete el formulario y nos pondremos en contacto</p>

        <?php
        // Mensajes de error o éxito devueltos por procesar_presupuesto.php.
        // Todo texto que viene de la URL se escapa antes de imprimirse. (Corrige P02)
        if (isset($_GET['error']) && is_string($_GET['error']) && $_GET['error'] !== '') {

            $errores = explode('|', $_GET['error']);

            echo '<div class="alert alert-error"><strong>Corrija los siguientes errores:</strong><ul>';
            foreach ($errores as $err) {
                $err = trim($err);
                if ($err !== '') {
                    echo '<li>' . e($err) . '</li>';
                }
            }
            echo '</ul></div>';
        }

        if (isset($_GET['ok']) && $_GET['ok'] === '1') {
            echo '<div class="alert alert-success">¡Solicitud enviada correctamente! Nos contactaremos a la brevedad.</div>';
        }
        ?>

        <form action="procesar_presupuesto.php" method="POST" novalidate id="formPresupuesto">

            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="form-group">
                <label for="nombre">Nombre completo <span class="required">*</span></label>
                <input type="text" id="nombre" name="nombre" maxlength="<?= NOMBRE_MAX ?>"
                       value="<?= viejo('nombre') ?>"
                       required placeholder="Ej: María González">
            </div>

            <div class="form-group">
                <label for="telefono">Teléfono <span class="required">*</span></label>
                <input type="tel" id="telefono" name="telefono" maxlength="<?= TELEFONO_MAX ?>"
                       value="<?= viejo('telefono') ?>"
                       required placeholder="Ej: 11 1234-5678">
            </div>

            <div class="form-group">
                <label for="tipo_servicio">Tipo de servicio <span class="required">*</span></label>
                <select id="tipo_servicio" name="tipo_servicio" required>
                    <option value="">— Seleccione —</option>
                    <?php
                    // La lista ahora viene de config.php: una sola fuente de verdad.
                    $sel = isset($_GET['tipo_servicio']) && is_string($_GET['tipo_servicio'])
                        ? $_GET['tipo_servicio']
                        : '';
                    foreach (SERVICIOS_VALIDOS as $s) {
                        $selected = ($sel === $s) ? ' selected' : '';
                        echo '<option value="' . e($s) . '"' . $selected . '>' . e($s) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="tipo_prenda">Tipo de prenda <span class="required">*</span></label>
                <input type="text" id="tipo_prenda" name="tipo_prenda" maxlength="<?= PRENDA_MAX ?>"
                       value="<?= viejo('tipo_prenda') ?>"
                       required placeholder="Ej: Vestido de fiesta, traje, falda...">
            </div>

            <div class="form-group">
                <label for="descripcion">Descripción del trabajo <span class="required">*</span></label>
                <textarea id="descripcion" name="descripcion" required
                          minlength="<?= DESC_MIN ?>" maxlength="<?= DESC_MAX ?>"
                          placeholder="Detalle lo que necesita: medidas aproximadas, tela, estilo, etc."><?= viejo('descripcion') ?></textarea>
                <div class="contador">Máximo <?= DESC_MAX ?> caracteres</div>
            </div>

            <div class="form-group">
                <label for="fecha_requerida">Fecha requerida <span class="required">*</span></label>
                <input type="date" id="fecha_requerida" name="fecha_requerida"
                       value="<?= viejo('fecha_requerida') ?>"
                       required min="<?= date('Y-m-d') ?>">
            </div>

            <button type="submit" class="btn" id="btnEnviar">Enviar solicitud de presupuesto</button>
        </form>
    </div>

    <script>
        // Refuerzo visual contra el doble clic. La protección real es el token del servidor.
        document.getElementById('formPresupuesto').addEventListener('submit', function () {
            // setTimeout evita que deshabilitar el botón cancele el envío en curso.
            setTimeout(function () {
                var b = document.getElementById('btnEnviar');
                b.disabled = true;
                b.textContent = 'Enviando...';
            }, 0);
        });
    </script>
</body>
</html>