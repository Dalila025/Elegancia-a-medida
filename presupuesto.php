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

iniciar_sesion();
$token = token_modista();

require_once __DIR__ . '/conexion.php';

// Solo la modista con sesión iniciada (panel_login.php).
$usuario = requerir_modista($pdo);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    flash('error', 'Solicitud no indicada.');
    header('Location: modista.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT s.*, t.nombre AS servicio, pr.nombre AS producto_ref
           FROM solicitudes_presupuesto s
           INNER JOIN tipos_servicio t ON t.id_tipos_servicio = s.id_tipos_servicio
           LEFT JOIN productos pr ON pr.id_productos = s.id_productos
          WHERE s.id_solicitudes_presupuesto = :id'
    );
    $stmt->execute([':id' => $id]);
    $sol = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT * FROM presupuestos WHERE id_solicitudes_presupuesto = :id');
    $stmt->execute([':id' => $id]);
    $presu = $stmt->fetch() ?: null;

    $tarifas = obtener_tarifas($pdo);
    // Si es un arreglo, la lista de precios sirve de referencia
    $lista_arreglos = (int) $sol['id_tipos_servicio'] === SERVICIO_ARREGLOS ? obtener_arreglos($pdo) : [];
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
// Tela sugerida y materiales (funciones_materiales.php)
$val_nota_mat = $viejo['nota_materiales'] ?? ($presu ? $presu['nota_materiales'] : '');
$val_mat = $viejo['materiales'] ?? materiales_de($pdo, $presu ? (int) $presu['id_presupuestos'] : null);
$val_mat = array_values($val_mat);
// ¿Hacen falta materiales? Al editar: sí si ya tenía; si volvió con errores, lo que eligió.
$val_necesita = isset($viejo['necesita_materiales']) ? $viejo['necesita_materiales'] === '1' : (bool) array_filter($val_mat, fn($m) => trim((string) $m['descripcion']) !== '');
while (count($val_mat) < MATERIALES_FILAS) {
    $val_mat[] = ['tipo' => count($val_mat) === 0 ? 'tela' : 'material', 'descripcion' => '', 'cantidad' => '', 'precio' => null];
}

// Si la clienta pidió un arreglo de la lista de precios, ese precio va como monto base.
$arreglo_elegido = $lista_arreglos ? arreglo_de_solicitud($lista_arreglos, (string) $sol['descripcion']) : null;
if ($val_monto === '' && $arreglo_elegido && $arreglo_elegido['precio'] !== null) {
    $val_monto = $arreglo_elegido['precio'];
}

$hoy        = new DateTime('today');
$fecha_max  = (clone $hoy)->modify('+' . PLAZO_MAX_DIAS . ' days');
$fecha_req  = DateTime::createFromFormat('!Y-m-d', $sol['fecha_requerida']);
$dias_req   = dias_desde_hoy($fecha_req);

// Si todavía no hay fecha, se sugiere la que pidió la clienta (si se puede cumplir).
$fecha_sugerida = false;
if ($val_fecha === '' && $dias_req >= 0 && $fecha_req <= $fecha_max) {
    $val_fecha = $fecha_req->format('Y-m-d');
    $fecha_sugerida = true;
}

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
    <link rel="stylesheet" href="<?= asset('css/modista.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/panel.css') ?>">
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
                <dt>Prenda</dt>     <dd><?= e($sol['tipo_prenda']) ?><?php if ($sol['producto_ref'] !== null): ?> <span class="ref-catalogo">Referencia del catálogo: <a href="producto.php?id=<?= (int) $sol['id_productos'] ?>"><?= e($sol['producto_ref']) ?></a></span><?php endif; ?></dd>
                <dt>Descripción</dt><dd><?= e($sol['descripcion']) ?></dd>
                <dt>Lo pide para</dt>
                <dd><?= $fecha_req->format('d/m/Y') ?> (<?= $dias_req < 0 ? 'hace ' . abs($dias_req) . ' días' : ($dias_req === 0 ? 'hoy' : "en $dias_req días") ?>)</dd>
                <dt>Estado</dt>     <dd><?= e(ESTADOS[$estado]) ?></dd>
                <dt>Código</dt>     <dd><?= e(formatear_codigo($sol['codigo_seguimiento'])) ?> <small>(el cliente lo usa en seguimiento.php)</small></dd>
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
                        <?php if ($fecha_sugerida): ?>
                            <div class="ayuda aviso-fecha">Es la fecha que pidió la clienta. Si no llegás, cambiala por la que puedas.</div>
                        <?php elseif ($dias_req >= 0): ?>
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
                    <?php if ($arreglo_elegido): ?>
                        <div class="aviso aviso-arreglo">
                            La clienta eligió <strong><?= e($arreglo_elegido['nombre']) ?></strong> de tu lista
                            (<?= e(precio_arreglo($arreglo_elegido)) ?>).
                            <?= $arreglo_elegido['precio'] !== null
                                ? ((int) $arreglo_elegido['precio_desde'] === 1
                                    ? 'Puse ese precio como monto base: como es «desde», revisalo según el trabajo.'
                                    : 'Ya está puesto como monto base; si hace falta, cambialo.')
                                : 'Ese arreglo es «a consultar»: poné el monto según el trabajo.' ?>
                        </div>
                    <?php endif; ?>
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

                <fieldset class="materiales-pregunta">
                    <legend>🧵 ¿Hacen falta materiales para este trabajo?</legend>
                    <label><input type="radio" name="necesita_materiales" value="0" <?= !$val_necesita ? 'checked' : '' ?>>
                        <span><strong>No hace falta</strong><small>La clienta no tiene que traer ni pagar materiales aparte.</small></span></label>
                    <label><input type="radio" name="necesita_materiales" value="1" <?= $val_necesita ? 'checked' : '' ?>>
                        <span><strong>Sí, hacen falta</strong><small>Le decís qué materiales y qué tela sugerís; ella elige si los trae o si los comprás vos.</small></span></label>
                </fieldset>

                <fieldset class="materiales-form" id="materiales-form" <?= $val_necesita ? '' : 'hidden' ?>>
                    <legend>Tela sugerida y materiales</legend>
                    <p class="ayuda">Sugerile la tela según el trabajo y anotá lo que hace falta (cierre, botones, entretela…) con un precio aproximado.
                        Al aceptar, la clienta elige si los trae ella o si los comprás vos (en ese caso se suma al total).</p>
                    <div class="campo">
                        <label for="nota_materiales">Por qué esa tela (la ve la clienta)</label>
                        <input type="text" id="nota_materiales" name="nota_materiales" maxlength="<?= NOTA_MATERIALES_MAX ?>"
                               value="<?= e((string) $val_nota_mat) ?>" placeholder="Ej: el satén cae bien en la falda larga y no se arruga">
                    </div>
                    <div class="tabla-scroll">
                        <table class="tabla-materiales">
                            <thead><tr><th>Tipo</th><th>Qué</th><th>Cantidad</th><th>Precio aprox. ($)</th></tr></thead>
                            <tbody>
                            <?php foreach ($val_mat as $i => $m): ?>
                                <tr>
                                    <td><select name="materiales[<?= $i ?>][tipo]" aria-label="Tipo del material <?= $i + 1 ?>">
                                        <?php foreach (MATERIALES_TIPOS as $k => $txt): ?><option value="<?= $k ?>" <?= ($m['tipo'] ?? 'material') === $k ? 'selected' : '' ?>><?= e($txt) ?></option><?php endforeach; ?>
                                    </select></td>
                                    <td><input type="text" name="materiales[<?= $i ?>][descripcion]" maxlength="<?= MATERIAL_DESC_MAX ?>" value="<?= e((string) $m['descripcion']) ?>"
                                               placeholder="<?= $i === 0 ? 'Ej: Satén azul' : ($i === 1 ? 'Ej: Cierre invisible 40 cm' : '') ?>" aria-label="Material <?= $i + 1 ?>"></td>
                                    <td><input type="text" name="materiales[<?= $i ?>][cantidad]" maxlength="<?= MATERIAL_CANT_MAX ?>" value="<?= e((string) $m['cantidad']) ?>"
                                               placeholder="<?= $i === 0 ? '3 m' : '' ?>" aria-label="Cantidad del material <?= $i + 1 ?>"></td>
                                    <td><input type="text" inputmode="decimal" class="mat-precio-in" name="materiales[<?= $i ?>][precio]" value="<?= e((string) ($m['precio'] ?? '')) ?>"
                                               placeholder="<?= $i === 0 ? '27000' : '' ?>" aria-label="Precio del material <?= $i + 1 ?>"></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="ayuda" id="mat-total" aria-live="polite"></p>
                </fieldset>

                <div class="botonera">
                    <button type="submit" class="btn-guardar">Guardar presupuesto</button>
                    <a href="modista.php" class="btn-secundario">Cancelar</a>
                </div>
            </form>
        </section>

        <?php if ($lista_arreglos): ?>
        <!-- Referencia: lista de precios de arreglos -->
        <section class="tarjeta">
            <h2>Tu lista de precios de arreglos</h2>
            <p class="ayuda" style="margin:-6px 0 10px">Tocá «Usar» para poner ese precio como monto base (después lo podés cambiar).</p>
            <table class="tabla-tarifas">
                <thead><tr><th>Arreglo</th><th>Precio</th><th>Demora</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($lista_arreglos as $a): ?>
                    <?php $es_elegido = $arreglo_elegido && (int) $arreglo_elegido['id_arreglos'] === (int) $a['id_arreglos']; ?>
                    <tr<?= $es_elegido ? ' class="arreglo-elegido"' : '' ?>>
                        <td><?= e($a['nombre']) ?><?= $es_elegido ? ' <strong>← lo pidió la clienta</strong>' : '' ?></td>
                        <td><?= e(precio_arreglo($a)) ?></td>
                        <td><?= e(demora_arreglo($a)) ?></td>
                        <td><?php if ($a['precio'] !== null): ?><button type="button" class="btn-accion accion-presupuestar usar-precio" data-precio="<?= e((string) $a['precio']) ?>">Usar</button><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php endif; ?>

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
        // ¿Hacen falta materiales? Muestra u oculta la tabla (oculta, sus campos no se envían)
        var $matForm = document.getElementById('materiales-form');
        function verMateriales() {
            var si = document.querySelector('input[name="necesita_materiales"][value="1"]').checked;
            $matForm.hidden = !si;
            $matForm.disabled = !si;
        }
        document.querySelectorAll('input[name="necesita_materiales"]').forEach(function (r) { r.addEventListener('change', verMateriales); });
        verMateriales();

        // Materiales: suma lo que costaría si los compra la modista
        var $matTotal = document.getElementById('mat-total');
        function sumarMateriales() {
            var t = 0, n = 0;
            document.querySelectorAll('.mat-precio-in').forEach(function (i) {
                var v = parseFloat(i.value.replace(',', '.'));
                if (!isNaN(v) && v > 0) { t += v; n++; }
            });
            $matTotal.textContent = n ? 'Si los comprás vos, se suman $ ' + t.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' al total.' : '';
        }
        document.querySelectorAll('.mat-precio-in').forEach(function (i) { i.addEventListener('input', sumarMateriales); });
        sumarMateriales();
        // Lista de precios de arreglos: «Usar» pone ese precio como monto base
        document.querySelectorAll('.usar-precio').forEach(function (b) {
            b.addEventListener('click', function () {
                $monto.value = b.dataset.precio;
                $monto.dispatchEvent(new Event('input', { bubbles: true }));
                $monto.focus();
            });
        });

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
