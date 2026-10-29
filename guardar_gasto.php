<?php
declare(strict_types=1);

/**
 * Guardar gastos y rubros (panel) — Elegancia a Medida
 *
 * Recibe POST de gastos.php con accion =
 *   guardar         alta (id = 0) o edición de un gasto; id_solicitudes_presupuesto
 *                   (opcional) = el trabajo para el que se compró
 *   eliminar        borra un gasto
 * Con origen=trabajo&trabajo=N vuelve a trabajo.php?id=N (costos de ese trabajo).
 *   rubro_crear     agrega un rubro (nombre)
 *   rubro_alternar  oculta / muestra un rubro
 *   rubro_eliminar  borra un rubro sin gastos
 * Tablas: gastos, categorias_gasto.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
iniciar_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

require_once __DIR__ . '/conexion.php';
$usuario = requerir_modista($pdo);

$campo  = fn(string $n): string => isset($_POST[$n]) && is_string($_POST[$n]) ? trim(preg_replace('/\s+/', ' ', $_POST[$n])) : '';
$accion = $campo('accion');
$id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$mes    = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $campo('mes')) ? $campo('mes') : date('Y-m');

// Si se cargó desde los costos de un trabajo, se vuelve ahí
$trabajo_origen = $campo('origen') === 'trabajo'
    ? (filter_input(INPUT_POST, 'trabajo', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0) : 0;

function volver(string $tipo, string $mensaje, string $mes): void
{
    global $trabajo_origen;
    flash($tipo, $mensaje);
    header('Location: ' . ($trabajo_origen ? 'trabajo.php?id=' . $trabajo_origen : 'gastos.php?mes=' . $mes));
    exit;
}

if (!token_modista_valido()) {
    volver('error', 'La sesión expiró. Recargue la página e intente de nuevo.', $mes);
}

try {
    // ---------- RUBROS ----------
    if ($accion === 'rubro_crear') {
        $nombre = $campo('nombre');
        if ($nombre === '' || mb_strlen($nombre) > GASTO_CATEGORIA_MAX) {
            volver('error', 'El nombre del rubro es obligatorio (hasta ' . GASTO_CATEGORIA_MAX . ' caracteres).', $mes);
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM categorias_gasto WHERE nombre = :n');
        $stmt->execute([':n' => $nombre]);
        if ((int) $stmt->fetchColumn() > 0) {
            volver('error', "Ya existe el rubro «{$nombre}».", $mes);
        }
        $pdo->prepare('INSERT INTO categorias_gasto (nombre) VALUES (:n)')->execute([':n' => $nombre]);
        volver('ok', "Se agregó el rubro «{$nombre}».", $mes);
    }

    if ($accion === 'rubro_alternar' && $id) {
        $stmt = $pdo->prepare('UPDATE categorias_gasto SET activo = 1 - activo WHERE id_categorias_gasto = :id');
        $stmt->execute([':id' => $id]);
        volver($stmt->rowCount() ? 'ok' : 'error', $stmt->rowCount() ? 'Listo: se cambió la visibilidad del rubro.' : 'Ese rubro ya no existe.', $mes);
    }

    if ($accion === 'rubro_eliminar' && $id) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM gastos WHERE id_categorias_gasto = :id');
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            volver('error', 'Ese rubro tiene gastos: no se puede eliminar, pero podés ocultarlo.', $mes);
        }
        $stmt = $pdo->prepare('DELETE FROM categorias_gasto WHERE id_categorias_gasto = :id');
        $stmt->execute([':id' => $id]);
        volver($stmt->rowCount() ? 'ok' : 'error', $stmt->rowCount() ? 'Se eliminó el rubro.' : 'Ese rubro ya no existe.', $mes);
    }

    // ---------- ELIMINAR GASTO ----------
    if ($accion === 'eliminar' && $id) {
        $stmt = $pdo->prepare('SELECT descripcion, fecha FROM gastos WHERE id_gastos = :id');
        $stmt->execute([':id' => $id]);
        $g = $stmt->fetch();
        if (!$g) {
            volver('error', 'Ese gasto ya no existe.', $mes);
        }
        $pdo->prepare('DELETE FROM gastos WHERE id_gastos = :id')->execute([':id' => $id]);
        volver('ok', "Se eliminó el gasto «{$g['descripcion']}».", substr($g['fecha'], 0, 7));
    }

    if ($accion !== 'guardar') {
        volver('error', 'Acción inválida.', $mes);
    }

    // ---------- GUARDAR GASTO (alta o edición) ----------
    $trabajo_actual = 0;   // trabajo que ya tenía el gasto (al editar)
    if ($id) {
        $stmt = $pdo->prepare('SELECT id_gastos, id_solicitudes_presupuesto FROM gastos WHERE id_gastos = :id');
        $stmt->execute([':id' => $id]);
        $actual = $stmt->fetch();
        if (!$actual) {
            volver('error', 'Ese gasto ya no existe.', $mes);
        }
        $trabajo_actual = (int) $actual['id_solicitudes_presupuesto'];
    }

    $datos = [
        'fecha'               => $campo('fecha'),
        'id_categorias_gasto' => $campo('id_categorias_gasto'),
        'descripcion'         => $campo('descripcion'),
        'monto'               => $campo('monto'),
        'cantidad'            => $campo('cantidad'),
        'medio_pago'          => $campo('medio_pago'),
        'proveedor'           => $campo('proveedor'),
        'id_solicitudes_presupuesto' => $campo('id_solicitudes_presupuesto'),
    ];
    $errores = [];

    // Trabajo (opcional): tiene que estar aceptado (en el taller o terminado)
    $trabajo = ctype_digit($datos['id_solicitudes_presupuesto']) ? (int) $datos['id_solicitudes_presupuesto'] : 0;
    if ($datos['id_solicitudes_presupuesto'] !== '' && $datos['id_solicitudes_presupuesto'] !== '0'
        && (!$trabajo || ($trabajo !== $trabajo_actual && !trabajo_admite_gastos($pdo, $trabajo)))) {
        $errores[] = 'Ese trabajo no existe o todavía no fue aceptado.';
        $trabajo = 0;
    }

    $f = DateTime::createFromFormat('!Y-m-d', $datos['fecha']);
    if (!$f || $f->format('Y-m-d') !== $datos['fecha']) {
        $errores[] = 'Indicá la fecha del gasto.';
    } elseif ($datos['fecha'] > date('Y-m-d')) {
        $errores[] = 'La fecha no puede ser posterior a hoy.';
    } elseif ($datos['fecha'] < '2000-01-01') {
        $errores[] = 'La fecha no es válida.';
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM categorias_gasto WHERE id_categorias_gasto = :id');
    $stmt->execute([':id' => ctype_digit($datos['id_categorias_gasto']) ? (int) $datos['id_categorias_gasto'] : 0]);
    if ((int) $stmt->fetchColumn() === 0) {
        $errores[] = 'Elegí un rubro.';
    }

    if ($datos['descripcion'] === '' || mb_strlen($datos['descripcion']) > GASTO_DESCRIPCION_MAX) {
        $errores[] = 'Escribí qué compraste o pagaste (hasta ' . GASTO_DESCRIPCION_MAX . ' caracteres).';
    }
    [$monto, $err] = leer_precio_opcional($datos['monto'], 'monto');
    if ($err || $monto === null) {
        $errores[] = $err ?? 'Indicá el monto.';
    }
    if (mb_strlen($datos['cantidad']) > GASTO_CANTIDAD_MAX) {
        $errores[] = 'La cantidad puede tener hasta ' . GASTO_CANTIDAD_MAX . ' caracteres.';
    }
    if (!isset(GASTO_MEDIOS[$datos['medio_pago']])) {
        $errores[] = 'Elegí cómo lo pagaste.';
    }
    if (mb_strlen($datos['proveedor']) > GASTO_PROVEEDOR_MAX) {
        $errores[] = 'El lugar puede tener hasta ' . GASTO_PROVEEDOR_MAX . ' caracteres.';
    }

    if ($errores) {
        $_SESSION['form_gasto'] = ['id' => $id, 'errores' => $errores, 'datos' => $datos];
        header('Location: ' . ($trabajo_origen
            ? 'trabajo.php?id=' . $trabajo_origen . '#form-gasto'
            : 'gastos.php?mes=' . $mes . ($id ? '&editar=' . $id : '') . '#form-gasto'));
        exit;
    }

    $params = [
        ':cat' => (int) $datos['id_categorias_gasto'], ':fecha' => $datos['fecha'], ':desc' => $datos['descripcion'],
        ':cant' => $datos['cantidad'], ':monto' => $monto, ':medio' => $datos['medio_pago'], ':prov' => $datos['proveedor'],
        ':trab' => $trabajo ?: null,
    ];
    if ($id) {
        $params[':id'] = $id;
        $pdo->prepare('UPDATE gastos SET id_categorias_gasto = :cat, fecha = :fecha, descripcion = :desc, cantidad = :cant,
                                         monto = :monto, medio_pago = :medio, proveedor = :prov, id_solicitudes_presupuesto = :trab
                        WHERE id_gastos = :id')->execute($params);
        $mensaje = 'Se guardaron los cambios del gasto.';
    } else {
        $pdo->prepare('INSERT INTO gastos (id_categorias_gasto, fecha, descripcion, cantidad, monto, medio_pago, proveedor, id_solicitudes_presupuesto)
                       VALUES (:cat, :fecha, :desc, :cant, :monto, :medio, :prov, :trab)')->execute($params);
        $mensaje = 'Se agregó el gasto «' . $datos['descripcion'] . '» por ' . pesos((float) $monto) . '.';
    }
    volver('ok', $mensaje, substr($datos['fecha'], 0, 7));

} catch (PDOException $e) {
    error_log('Error en gastos: ' . $e->getMessage());
    volver('error', 'No se pudo guardar. Intente nuevamente.', $mes);
}
