<?php
declare(strict_types=1);

/**
 * Panel de la modista — Elegancia a Medida
 *
 * Lista las solicitudes de presupuesto, permite filtrarlas (por estado,
 * por tipo de servicio y por nombre), cargar el
 * presupuesto (fecha de entrega, plazo y monto) y cambiar su estado.
 * Sin autenticación (pendiente para etapas posteriores).
 *
 * Tablas: solicitudes_presupuesto, tipos_servicio, estados_solicitud,
 *         presupuestos.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';

session_start();

$token = token_modista();
$flash = tomar_flash();

require_once __DIR__ . '/conexion.php';

// Filtro opcional por estado (GET)
$filtro_estado = isset($_GET['estado']) ? (int) $_GET['estado'] : 0;
if (!array_key_exists($filtro_estado, ESTADOS)) {
    $filtro_estado = 0;
}

// Búsqueda opcional por nombre (GET)
$busqueda = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';

// Filtro opcional por tipo de servicio (GET).
// Se listan todos los servicios, también los inactivos, para poder
// encontrar solicitudes viejas de un servicio que ya no se ofrece.
try {
    $servicios = obtener_tipos_servicio($pdo, false);
} catch (PDOException $e) {
    error_log('Error al leer tipos_servicio: ' . $e->getMessage());
    $servicios = [];
}

$filtro_servicio = isset($_GET['servicio']) ? (int) $_GET['servicio'] : 0;
if (!array_key_exists($filtro_servicio, $servicios)) {
    $filtro_servicio = 0;
}


// -------------------------------------------------
// Consulta
// -------------------------------------------------
$sql = "
    SELECT
        s.id_solicitudes_presupuesto,
        s.nombre,
        s.telefono,
        t.nombre          AS servicio,
        s.tipo_prenda,
        s.descripcion,
        s.fecha_requerida,
        s.fecha_solicitud,
        s.id_estados_solicitud,
        p.fecha_entrega,
        p.plazo_dias,
        p.monto_total,
        p.recargo_porcentaje
    FROM solicitudes_presupuesto s
    INNER JOIN tipos_servicio t ON t.id_tipos_servicio = s.id_tipos_servicio
    LEFT  JOIN presupuestos   p ON p.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
";

$params = [];
$condiciones = [];

if ($filtro_estado > 0) {
    $condiciones[] = 's.id_estados_solicitud = :estado';
    $params[':estado'] = $filtro_estado;
}

if ($filtro_servicio > 0) {
    $condiciones[] = 's.id_tipos_servicio = :servicio';
    $params[':servicio'] = $filtro_servicio;
}

if ($busqueda !== '') {
    $condiciones[] = 's.nombre LIKE :busqueda';
    $params[':busqueda'] = '%' . $busqueda . '%';
}

if ($condiciones) {
    $sql .= ' WHERE ' . implode(' AND ', $condiciones);
}

$sql .= ' ORDER BY s.fecha_solicitud DESC, s.id_solicitudes_presupuesto DESC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $solicitudes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error al consultar solicitudes: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudieron cargar las solicitudes.');
}

// Contadores por estado (para los badges del filtro).
// Si hay un servicio elegido, cuentan solo las solicitudes de ese servicio.
$conteos = array_fill_keys(array_keys(ESTADOS), 0);
try {
    $sqlCont = 'SELECT id_estados_solicitud, COUNT(*) AS total FROM solicitudes_presupuesto';
    $paramsCont = [];
    if ($filtro_servicio > 0) {
        $sqlCont .= ' WHERE id_tipos_servicio = :servicio';
        $paramsCont[':servicio'] = $filtro_servicio;
    }
    $sqlCont .= ' GROUP BY id_estados_solicitud';

    $stmtCont = $pdo->prepare($sqlCont);
    $stmtCont->execute($paramsCont);
    foreach ($stmtCont->fetchAll() as $fila) {
        $est = (int) $fila['id_estados_solicitud'];
        if (isset($conteos[$est])) {
            $conteos[$est] = (int) $fila['total'];
        }
    }
} catch (PDOException $e) {
    // No crítico; se muestran en 0
}


$total_general = array_sum($conteos);
$pagina_actual = 'solicitudes';
$hay_filtros   = $busqueda !== '' || $filtro_estado > 0 || $filtro_servicio > 0;

// Solicitudes que todavía pueden avanzar (para marcar las urgentes).
const ESTADOS_ACTIVOS = [ESTADO_PENDIENTE, ESTADO_PRESUPUESTADO, ESTADO_EN_PROCESO];
const DIAS_URGENTE    = 7;


// -------------------------------------------------
// Ayudas de presentación (solo texto, no tocan datos)
// -------------------------------------------------

/** "hoy", "ayer", "hace 5 días" para una fecha pasada. */
function texto_hace(int $dias): string
{
    $dias = abs($dias);
    return match (true) {
        $dias === 0 => 'hoy',
        $dias === 1 => 'ayer',
        default     => "hace $dias días",
    };
}

/** "hoy", "mañana", "en 5 días" o "vencida hace 2 días". */
function texto_faltan(int $dias): string
{
    return match (true) {
        $dias < -1  => 'vencida hace ' . abs($dias) . ' días',
        $dias === -1 => 'vencida ayer',
        $dias === 0 => 'es hoy',
        $dias === 1 => 'mañana',
        default     => "en $dias días",
    };
}

/** Iniciales para el avatar: "María González" → "MG". */
function iniciales(string $nombre): string
{
    $partes = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $ini = mb_substr($partes[0], 0, 1);
    if (count($partes) > 1) {
        $ini .= mb_substr(end($partes), 0, 1);
    }
    return mb_strtoupper($ini);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Modista · Elegancia a Medida</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="css/panel_solicitudes.css">
</head>
<body class="pagina-solicitudes">
    <?php require __DIR__ . '/encabezado_modista.php'; ?>

    <main class="container contenido">

        <!-- Título de la página -->
        <section class="cabecera-pagina">
            <div>
                <p class="sobretitulo">Panel de gestión</p>
                <h2 class="titulo-pagina">Solicitudes de presupuesto</h2>
                <p class="bajada">
                    <?php $pend = $conteos[ESTADO_PENDIENTE] ?? 0; ?>
                    <?php if ($pend > 0): ?>
                        Tenés <strong><?= $pend ?></strong> solicitud<?= $pend === 1 ? '' : 'es' ?> pendiente<?= $pend === 1 ? '' : 's' ?> de presupuestar<?= $filtro_servicio > 0 ? ' en ' . e($servicios[$filtro_servicio]) : '' ?>.
                    <?php else: ?>
                        No hay solicitudes pendientes<?= $filtro_servicio > 0 ? ' en ' . e($servicios[$filtro_servicio]) : '' ?>. ¡Todo al día!
                    <?php endif; ?>
                </p>
            </div>
            <div class="total-tarjeta" aria-label="Total de solicitudes">
                <span class="total-numero"><?= $total_general ?></span>
                <span class="total-texto">solicitud<?= $total_general === 1 ? '' : 'es' ?><br>en total</span>
            </div>
        </section>

        <!-- Filtros y búsqueda -->
        <section class="toolbar">
            <form method="get" class="filtros" id="form-filtros">
                <nav class="filtros-estados" aria-label="Filtrar por estado">
                    <a href="<?= e(url_panel(['servicio' => $filtro_servicio, 'q' => $busqueda])) ?>"
                       class="chip <?= $filtro_estado === 0 ? 'activo' : '' ?>">
                        Todas <span class="badge"><?= $total_general ?></span>
                    </a>
                    <?php foreach (ESTADOS as $cod => $nombreEstado): ?>
                        <a href="<?= e(url_panel(['estado' => $cod, 'servicio' => $filtro_servicio, 'q' => $busqueda])) ?>"
                           class="chip chip-estado estado-<?= $cod ?> <?= $filtro_estado === $cod ? 'activo' : '' ?>">
                            <span class="punto" aria-hidden="true"></span>
                            <?= e($nombreEstado) ?>
                            <span class="badge"><?= $conteos[$cod] ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <div class="busqueda">
                    <select name="servicio" id="select-servicio" aria-label="Filtrar por servicio"
                            class="filtro-servicio <?= $filtro_servicio > 0 ? 'activo' : '' ?>">
                        <option value="0">Todos los servicios</option>
                        <?php foreach ($servicios as $id_serv => $nombre_serv): ?>
                            <option value="<?= $id_serv ?>" <?= $filtro_servicio === $id_serv ? 'selected' : '' ?>>
                                <?= e($nombre_serv) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="campo-busqueda">
                        <svg class="icono-lupa" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input type="search" name="q" id="input-busqueda"
                               placeholder="Buscar por nombre…"
                               value="<?= e($busqueda) ?>" autocomplete="off">
                        <kbd class="atajo" title="Atajo de teclado">/</kbd>
                    </div>

                    <?php if ($filtro_estado > 0): ?>
                        <input type="hidden" name="estado" value="<?= $filtro_estado ?>">
                    <?php endif; ?>

                    <button type="submit" class="btn-primary">Buscar</button>
                    <?php if ($hay_filtros): ?>
                        <a href="modista.php" class="btn-link">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- Resumen -->
        <p class="resumen">
            Mostrando <strong><?= count($solicitudes) ?></strong>
            solicitud<?= count($solicitudes) === 1 ? '' : 'es' ?>
            <?php if ($filtro_estado > 0): ?>
                <span class="resumen-tag">Estado: <?= e(ESTADOS[$filtro_estado]) ?></span>
            <?php endif; ?>
            <?php if ($filtro_servicio > 0): ?>
                <span class="resumen-tag">Servicio: <?= e($servicios[$filtro_servicio]) ?></span>
            <?php endif; ?>
            <?php if ($busqueda !== ''): ?>
                <span class="resumen-tag">Búsqueda: “<?= e($busqueda) ?>”</span>
            <?php endif; ?>
        </p>

        <!-- Tabla (en pantallas chicas se ve como tarjetas) -->
        <section class="tabla-wrapper">
            <?php if (empty($solicitudes)): ?>
                <div class="vacio">
                    <svg class="vacio-icono" viewBox="0 0 64 64" aria-hidden="true">
                        <path d="M14 50 50 14"/><ellipse cx="53" cy="11" rx="3" ry="5" transform="rotate(45 53 11)"/>
                        <path d="M10 54c6-2 10-8 16-8s8 6 14 6 10-6 14-8" class="hilo"/>
                    </svg>
                    <h3>No hay solicitudes para mostrar</h3>
                    <p>
                        <?= $hay_filtros
                            ? 'Ninguna solicitud coincide con los filtros elegidos.'
                            : 'Cuando un cliente envíe el formulario, su solicitud va a aparecer acá.' ?>
                    </p>
                    <?php if ($hay_filtros): ?>
                        <a href="modista.php" class="btn-primary">Ver todas</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <table class="tabla-solicitudes" id="tabla-solicitudes">
                    <thead>
                        <tr>
                            <th scope="col">Cliente</th>
                            <th scope="col">Trabajo</th>
                            <th scope="col">Fechas</th>
                            <th scope="col">Presupuesto</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $n => $s): ?>
                            <?php
                            $id        = (int) $s['id_solicitudes_presupuesto'];
                            $estadoCod = (int) $s['id_estados_solicitud'];
                            $estadoTxt = ESTADOS[$estadoCod] ?? 'Desconocido';
                            $fechaReq  = DateTime::createFromFormat('!Y-m-d', $s['fecha_requerida']);
                            $fechaSol  = new DateTime($s['fecha_solicitud']);
                            $fechaEnt  = $s['fecha_entrega'] ? DateTime::createFromFormat('!Y-m-d', $s['fecha_entrega']) : null;
                            $tieneProp = $fechaEnt !== null;

                            $diasFaltan = $fechaReq ? dias_desde_hoy($fechaReq) : null;
                            $diasHace   = dias_desde_hoy((clone $fechaSol)->setTime(0, 0));
                            $activa     = in_array($estadoCod, ESTADOS_ACTIVOS, true);
                            $urgente    = $activa && $diasFaltan !== null && $diasFaltan <= DIAS_URGENTE;
                            $entregaTarde = $tieneProp && $fechaReq && $fechaEnt > $fechaReq;
                            ?>
                            <tr class="fila <?= $urgente ? 'fila-urgente' : '' ?>" data-estado="<?= $estadoCod ?>" style="--i: <?= min($n, 12) ?>">

                                <td class="c-cliente" data-label="Cliente">
                                  <div class="cliente">
                                    <span class="avatar" aria-hidden="true"><?= e(iniciales($s['nombre'])) ?></span>
                                    <div class="cliente-datos">
                                        <span class="nombre"><?= e($s['nombre']) ?></span>
                                        <span class="cliente-meta">
                                            <span class="id">#<?= $id ?></span>
                                            <a class="telefono" href="tel:<?= e(preg_replace('/[^\d+]/', '', $s['telefono'])) ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>
                                                <?= e($s['telefono']) ?>
                                            </a>
                                        </span>
                                    </div>
                                  </div>
                                </td>

                                <td class="c-trabajo" data-label="Trabajo">
                                    <div class="trabajo-titulo">
                                        <span class="tag-servicio"><?= e($s['servicio']) ?></span>
                                        <span class="prenda"><?= e($s['tipo_prenda']) ?></span>
                                    </div>
                                    <p class="descripcion" title="<?= e($s['descripcion']) ?>"><?= e($s['descripcion']) ?></p>
                                    <button type="button" class="ver-mas" hidden aria-expanded="false">Ver más</button>
                                </td>

                                <td class="c-fechas" data-label="Fechas">
                                    <div class="fecha-linea">
                                        <span class="fecha-etiqueta">Pide para</span>
                                        <span class="fecha-valor"><?= $fechaReq ? $fechaReq->format('d/m/Y') : e($s['fecha_requerida']) ?></span>
                                        <?php if ($activa && $diasFaltan !== null): ?>
                                            <span class="plazo <?= $urgente ? 'plazo-urgente' : '' ?>"><?= e(texto_faltan($diasFaltan)) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fecha-linea secundaria">
                                        <span class="fecha-etiqueta">Recibida</span>
                                        <span class="fecha-valor" title="<?= $fechaSol->format('d/m/Y H:i') ?>">
                                            <?= $fechaSol->format('d/m/Y') ?> · <?= e(texto_hace($diasHace)) ?>
                                        </span>
                                    </div>
                                </td>

                                <td class="c-presupuesto" data-label="Presupuesto">
                                    <?php if ($tieneProp): ?>
                                        <span class="monto"><?= pesos((float) $s['monto_total']) ?></span>
                                        <span class="presu-detalle <?= $entregaTarde ? 'tarde' : '' ?>"
                                              <?= $entregaTarde ? 'title="Posterior a la fecha que pidió el cliente"' : '' ?>>
                                            Entrega <?= $fechaEnt->format('d/m/Y') ?>
                                        </span>
                                        <span class="presu-detalle">
                                            <?= (int) $s['plazo_dias'] ?> días
                                            <?php if ((float) $s['recargo_porcentaje'] != 0): ?>
                                                · <?= porcentaje((float) $s['recargo_porcentaje']) ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="sin-dato">Sin presupuesto</span>
                                    <?php endif; ?>
                                </td>

                                <td class="c-estado" data-label="Estado">
                                    <span class="estado-badge estado-<?= $estadoCod ?>">
                                        <span class="punto" aria-hidden="true"></span><?= e($estadoTxt) ?>
                                    </span>
                                </td>

                                <td class="c-acciones" data-label="Acciones">
                                    <?php
                                    $acciones          = TRANSICIONES[$estadoCod] ?? [];
                                    $puedePresupuestar = in_array($estadoCod, ESTADOS_PRESUPUESTABLES, true);
                                    ?>
                                    <?php if (empty($acciones) && !$puedePresupuestar): ?>
                                        <span class="sin-dato">Sin acciones</span>
                                    <?php else: ?>
                                        <div class="acciones">
                                            <?php if ($puedePresupuestar): ?>
                                                <a href="presupuesto.php?id=<?= $id ?>" class="btn-accion accion-presupuestar">
                                                    <?= $tieneProp ? 'Editar presupuesto' : 'Presupuestar' ?>
                                                </a>
                                            <?php endif; ?>
                                            <?php foreach ($acciones as $nuevo => $textoBoton): ?>
                                                <form method="post" action="actualizar_estado.php" class="form-estado"
                                                      data-confirmar="<?= in_array($nuevo, ESTADOS_CON_CONFIRMACION, true) ? '1' : '0' ?>"
                                                      data-titulo="<?= e($textoBoton) ?>"
                                                      data-texto="<?= e('Solicitud #' . $id . ' de ' . $s['nombre'] . '. La solicitud pasará a «' . (ESTADOS[$nuevo] ?? '') . '».') ?>">
                                                    <input type="hidden" name="token" value="<?= e($token) ?>">
                                                    <input type="hidden" name="id" value="<?= $id ?>">
                                                    <input type="hidden" name="estado_nuevo" value="<?= (int) $nuevo ?>">
                                                    <input type="hidden" name="volver_estado" value="<?= $filtro_estado ?>">
                                                    <input type="hidden" name="volver_servicio" value="<?= $filtro_servicio ?>">
                                                    <input type="hidden" name="volver_q" value="<?= e($busqueda) ?>">
                                                    <button type="submit" class="btn-accion accion-<?= (int) $nuevo ?>">
                                                        <?= e($textoBoton) ?>
                                                    </button>
                                                </form>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <p>Elegancia a Medida · Panel de gestión (sin autenticación) · Ciclo 2026</p>
        </div>
    </footer>

    <!-- Avisos (ok / error). Sin JavaScript se ven igual; con JS se cierran solos. -->
    <div class="toasts" id="toasts" aria-live="polite">
        <?php if ($flash): ?>
            <?php $ok = $flash['tipo'] === 'ok'; ?>
            <div class="toast <?= $ok ? 'toast-ok' : 'toast-error' ?>" role="<?= $ok ? 'status' : 'alert' ?>">
                <span class="toast-icono" aria-hidden="true">
                    <?php if ($ok): ?>
                        <svg viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24"><path d="M12 7v6M12 17h.01"/></svg>
                    <?php endif; ?>
                </span>
                <div class="toast-cuerpo">
                    <strong><?= $ok ? 'Listo' : 'Atención' ?></strong>
                    <p><?= e($flash['mensaje']) ?></p>
                </div>
                <button type="button" class="toast-cerrar" aria-label="Cerrar aviso">&times;</button>
                <span class="toast-progreso" aria-hidden="true"></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Ventana de confirmación (reemplaza al confirm() del navegador) -->
    <dialog class="modal" id="modal-confirmar" aria-labelledby="modal-titulo">
        <div class="modal-icono" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 8v5M12 16.5h.01"/><path d="M10.3 3.9 2.6 17.3A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.7L13.7 3.9a2 2 0 0 0-3.4 0"/></svg>
        </div>
        <h3 id="modal-titulo">¿Confirmar?</h3>
        <p id="modal-texto"></p>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" data-respuesta="no">Volver</button>
            <button type="button" class="btn-peligro" data-respuesta="si">Confirmar</button>
        </div>
    </dialog>

    <script src="js/modista.js"></script>
    <script src="js/panel_solicitudes.js"></script>
</body>
</html>