<?php
declare(strict_types=1);

/**
 * Presupuesto de la modista — Elegancia a Medida
 *
 * La modista elige la fecha de entrega (o el plazo en días) y el monto
 * base del trabajo. Según el plazo se aplica el recargo definido en
 * tarifas_plazo y se muestra el total en vivo.
 * Al guardar (guardar_presupuesto.php) la solicitud pasa a "Presupuestado".
 *
 * Tablas: solicitudes_presupuesto, tipos_servicio, presupuestos, tarifas_plazo.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';

session_start();
$token = token_modista();

require_once __DIR__ . '/conexion.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    flash('error', 'Solicitud no indicada.');
    header('Location: modista.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT s.*, t.nombre AS servicio
           FROM solicitudes_presupuesto s
           INNER JOIN tipos_servicio t ON t.id_tipos_servicio = s.id_tipos_servicio
          WHERE s.id_solicitudes_presupuesto = :id'
    );
    $stmt->execute([':id' => $id]);
    $sol = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT * FROM presupuestos WHERE id_solicitudes_presupuesto = :id');
    $stmt->execute([':id' => $id]);
    $presu = $stmt->fetch() ?: null;

    $tarifas = obtener_tarifas($pdo);
} catch (PDOException $e) {
    error_log('Error al cargar presupuesto: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo cargar la solicitud.');
}

if (!$sol) {
    flash('error', "La solicitud #$id no existe.");
    header('Location: modista.php');
    exit;
}

$estado = (int) $sol['id_estados_solicitud'];
if (!in_array($estado, ESTADOS_PRESUPUESTABLES, true)) {
    flash('error', "La solicitud #$id está «" . (ESTADOS[$estado] ?? '?') . "»: ya no se puede presupuestar. Reábrala primero si hace falta.");
    header('Location: modista.php');
    exit;
}

// Errores y datos devueltos por guardar_presupuesto.php
$errores = [];
$viejo   = null;
if (isset($_SESSION['form_presupuesto']) && (int) $_SESSION['form_presupuesto']['id'] === $id) {
    $errores = $_SESSION['form_presupuesto']['errores'];
    $viejo   = $_SESSION['form_presupuesto']['datos'];
}
unset($_SESSION['form_presupuesto']);

// Valores iniciales: lo que se envió con error, o el presupuesto existente.
$val_monto = $viejo['monto_base']    ?? ($presu ? $presu['monto_base'] : '');
$val_fecha = $viejo['fecha_entrega'] ?? ($presu ? $presu['fecha_entrega'] : '');
$val_obs   = $viejo['observaciones'] ?? ($presu ? $presu['observaciones'] : '');

$hoy        = new DateTime('today');
$fecha_max  = (clone $hoy)->modify('+' . PLAZO_MAX_DIAS . ' days');
$fecha_req  = DateTime::createFromFormat('!Y-m-d', $sol['fecha_requerida']);
$dias_req   = dias_desde_hoy($fecha_req);

// Rangos "desde" para mostrar las tarifas de forma legible.
$desde = 0;
foreach ($tarifas as &$t) {
    $t['desde'] = $desde;
    $desde = $t['dias_hasta'] === null ? $desde : (int) $t['dias_hasta'] + 1;
}
unset($t);
$hay_sin_limite = in_array(null, array_column($tarifas, 'dias_hasta'), true);

$pagina_actual = 'solicitudes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuesto #<?= $id ?> · Elegancia a Medida</title>
    <link rel="stylesheet" href="css/modista.css">
    <link rel="stylesheet" href="css/panel.css">
</head>
<body>
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container">
        <p style="margin-top:16px"><a href="modista.php" class="btn-link">← Volver a solicitudes</a></p>

        <?php if ($errores): ?>
            <div class="flash flash-error" role="alert">
                <strong>Revise los siguientes datos:</strong>
                <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <!-- Datos de la solicitud -->
        <section class="tarjeta">
            <h2>Solicitud #<?= $id ?> · <?= e($sol['nombre']) ?></h2>
            <dl class="datos-solicitud">
                <dt>Teléfono</dt>   <dd><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $sol['telefono'])) ?>"><?= e($sol['telefono']) ?></a></dd>
                <dt>Servicio</dt>   <dd><?= e($sol['servicio']) ?></dd>
                <dt>Prenda</dt>     <dd><?= e($sol['tipo_prenda']) ?></dd>
                <dt>Descripción</dt><dd><?= e($sol['descripcion']) ?></dd>
                <dt>Lo pide para</dt>
                <dd><?= $fecha_req->format('d/m/Y') ?> (<?= $dias_req < 0 ? 'hace ' . abs($dias_req) . ' días' : ($dias_req === 0 ? 'hoy' : "en $dias_req días") ?>)</dd>
                <dt>Estado</dt>     <dd><?= e(ESTADOS[$estado]) ?></dd>
            </dl>
        </section>

        <!-- Formulario de presupuesto -->
        <section class="tarjeta">
            <h2><?= $presu ? 'Editar presupuesto' : 'Armar presupuesto' ?></h2>

            <?php if (!$tarifas || !$hay_sin_limite): ?>
                <div class="aviso">
                    Hay plazos sin tarifa definida (falta una fila «sin límite»). Para esos plazos no se aplica recargo.
                    <a href="tarifas.php">Revisar tarifas</a>.
                </div>
            <?php endif; ?>

            <form method="post" action="guardar_presupuesto.php" class="form-panel" id="form-presupuesto" novalidate>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="id_solicitudes_presupuesto" value="<?= $id ?>">

                <div class="fila">
                    <div class="campo">
                        <label for="fecha_entrega">Fecha de entrega</label>
                        <input type="date" id="fecha_entrega" name="fecha_entrega" required
                               value="<?= e((string) $val_fecha) ?>"
                               min="<?= $hoy->format('Y-m-d') ?>" max="<?= $fecha_max->format('Y-m-d') ?>">
                        <?php if ($dias_req >= 0): ?>
                            <div class="ayuda">
                                <button type="button" class="btn-secundario" id="usar-fecha-cliente"
                                        data-fecha="<?= $fecha_req->format('Y-m-d') ?>"
                                        style="padding:4px 10px;font-size:0.8rem">
                                    Usar la fecha del cliente (<?= $fecha_req->format('d/m') ?>)
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="campo">
                        <label for="plazo_dias">…o plazo en días</label>
                        <input type="number" id="plazo_dias" min="0" max="<?= PLAZO_MAX_DIAS ?>" step="1"
                               inputmode="numeric" placeholder="Ej: 15">
                        <div class="ayuda">Se sincroniza con la fecha. Cuenta desde hoy.</div>
                    </div>
                </div>

                <div class="campo">
                    <label for="monto_base">Monto base del trabajo ($)</label>
                    <input type="number" id="monto_base" name="monto_base" required
                           min="<?= MONTO_MIN ?>" max="<?= MONTO_MAX ?>" step="0.01" inputmode="decimal"
                           value="<?= e((string) $val_monto) ?>" placeholder="Ej: 45000">
                    <div class="ayuda">Precio sin recargo. El recargo por plazo se calcula solo.</div>
                </div>

                <div class="calculo" id="calculo" aria-live="polite">
                    <div class="linea"><span>Plazo</span><span id="c-plazo">—</span></div>
                    <div class="linea"><span>Tarifa aplicada</span><span id="c-tarifa">—</span></div>
                    <div class="linea"><span>Monto base</span><span id="c-base">—</span></div>
                    <div class="linea"><span>Recargo</span><span id="c-recargo">—</span></div>
                    <div class="linea total"><span>Total para el cliente</span><span id="c-total">—</span></div>
                </div>
                <div class="aviso" id="aviso-tarde" hidden>
                    La fecha de entrega es posterior a la que pidió el cliente (<?= $fecha_req->format('d/m/Y') ?>).
                    Conviene avisarle al pasarle el presupuesto.
                </div>

                <div class="campo">
                    <label for="observaciones">Observaciones (opcional)</label>
                    <textarea id="observaciones" name="observaciones" rows="3" maxlength="<?= OBSERVACIONES_MAX ?>"
                              placeholder="Ej: incluye dos pruebas; la tela la trae la clienta; seña del 50 %."><?= e((string) $val_obs) ?></textarea>
                </div>

                <div class="botonera">
                    <button type="submit" class="btn-guardar">Guardar presupuesto</button>
                    <a href="modista.php" class="btn-secundario">Cancelar</a>
                </div>
            </form>
        </section>

        <!-- Referencia de tarifas -->
        <section class="tarjeta">
            <h2>Tarifas por plazo vigentes</h2>
            <table class="tabla-tarifas">
                <thead><tr><th>Tarifa</th><th>Plazo</th><th>Recargo</th></tr></thead>
                <tbody>
                <?php foreach ($tarifas as $t): ?>
                    <tr>
                        <td><?= e($t['nombre']) ?></td>
                        <td class="rango"><?= e(texto_rango_tarifa($t['desde'], $t['dias_hasta'] === null ? null : (int) $t['dias_hasta'])) ?></td>
                        <td><?= porcentaje((float) $t['recargo_porcentaje']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="ayuda" style="margin-top:10px"><a href="tarifas.php">Modificar tarifas</a></p>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <p>Elegancia a Medida · Panel de gestión (sin autenticación) · Ciclo 2026</p>
        </div>
    </footer>

    <script>
    (function () {
        // Mismas tarifas que usa el servidor; el cálculo final lo vuelve a hacer guardar_presupuesto.php.
        var TARIFAS = <?= json_encode(array_map(fn($t) => [
            'nombre'  => $t['nombre'],
            'hasta'   => $t['dias_hasta'] === null ? null : (int) $t['dias_hasta'],
            'recargo' => (float) $t['recargo_porcentaje'],
        ], $tarifas), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
        var FECHA_CLIENTE = '<?= $fecha_req->format('Y-m-d') ?>';

        var $fecha = document.getElementById('fecha_entrega');
        var $plazo = document.getElementById('plazo_dias');
        var $monto = document.getElementById('monto_base');
        var $btnCliente = document.getElementById('usar-fecha-cliente');

        function hoy() { var d = new Date(); d.setHours(0, 0, 0, 0); return d; }
        function aISO(d) {
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }
        function deISO(s) {
            var p = s.split('-'); if (p.length !== 3) return null;
            var d = new Date(+p[0], +p[1] - 1, +p[2]); return isNaN(d) ? null : d;
        }
        function diasHasta(d) { return Math.round((d - hoy()) / 86400000); }
        function pesos(n) {
            return '$ ' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function tarifaPara(dias) {
            for (var i = 0; i < TARIFAS.length; i++) {
                if (TARIFAS[i].hasta === null || TARIFAS[i].hasta >= dias) return TARIFAS[i];
            }
            return null;
        }
        function pct(n) { return (n > 0 ? '+' : n < 0 ? '−' : '') + Math.abs(n).toLocaleString('es-AR') + ' %'; }
        function set(id, txt) { document.getElementById(id).textContent = txt; }

        function recalcular() {
            var f = deISO($fecha.value);
            var dias = f ? diasHasta(f) : null;
            var base = parseFloat(($monto.value || '').replace(',', '.'));

            set('c-plazo', dias === null ? '—' : (dias === 0 ? 'Hoy' : dias + (dias === 1 ? ' día' : ' días')));

            var t = dias === null ? null : tarifaPara(dias);
            var rec = t ? t.recargo : 0;
            set('c-tarifa', dias === null ? '—' : (t ? t.nombre : 'Sin tarifa'));
            set('c-base', isNaN(base) ? '—' : pesos(base));

            if (isNaN(base) || dias === null) {
                set('c-recargo', t ? pct(rec) : '—');
                set('c-total', '—');
            } else {
                var recMonto = Math.round(base * rec) / 100;
                set('c-recargo', pct(rec) + ' (' + pesos(recMonto) + ')');
                set('c-total', pesos(Math.round(base * (100 + rec)) / 100));
            }

            document.getElementById('aviso-tarde').hidden = !(f && $fecha.value > FECHA_CLIENTE);
        }

        $fecha.addEventListener('input', function () {
            var f = deISO($fecha.value);
            $plazo.value = f ? diasHasta(f) : '';
            recalcular();
        });
        $plazo.addEventListener('input', function () {
            var n = parseInt($plazo.value, 10);
            if (!isNaN(n) && n >= 0) {
                var d = hoy(); d.setDate(d.getDate() + n);
                $fecha.value = aISO(d);
            }
            recalcular();
        });
        $monto.addEventListener('input', recalcular);
        if ($btnCliente) {
            $btnCliente.addEventListener('click', function () {
                $fecha.value = $btnCliente.dataset.fecha;
                $fecha.dispatchEvent(new Event('input'));
            });
        }

        // Estado inicial (al editar o volver con errores).
        if ($fecha.value) { $fecha.dispatchEvent(new Event('input')); } else { recalcular(); }
    })();
    </script>
</body>
</html>
