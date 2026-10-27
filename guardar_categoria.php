<?php
declare(strict_types=1);

/**
 * Alta, edición, ocultar/mostrar y baja de categorías y tipos de prenda — Elegancia a Medida
 * Recibe POST de categorias.php con:
 *   lista  = categorias | tipos
 *   accion = crear | actualizar | alternar | eliminar
 * Tablas: categorias, tipos_producto.
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


function volver(string $tipo, string $mensaje): void
{
    flash($tipo, $mensaje);
    header('Location: categorias.php');
    exit;
}

function campo(string $nombre): string
{
    return isset($_POST[$nombre]) && is_string($_POST[$nombre]) ? trim($_POST[$nombre]) : '';
}


if (!token_modista_valido()) {
    volver('error', 'La sesión expiró. Recargue la página e intente de nuevo.');
}

// Cada lista con su tabla y sus columnas. Los nombres de tabla salen de acá,
// nunca de lo que envía el formulario.
$LISTAS = [
    'categorias' => ['tabla' => 'categorias',     'pk' => 'id_categorias',     'max' => CATEGORIA_NOMBRE_MAX, 'desc' => true,  'nombre' => 'categoría'],
    'tipos'      => ['tabla' => 'tipos_producto', 'pk' => 'id_tipos_producto', 'max' => TIPO_NOMBRE_MAX,      'desc' => false, 'nombre' => 'tipo de prenda'],
];
$lista = $LISTAS[campo('lista')] ?? null;
$accion = campo('accion');
$id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

if ($lista === null
    || !in_array($accion, ['crear', 'actualizar', 'alternar', 'eliminar'], true)
    || ($accion !== 'crear' && !$id)) {
    volver('error', 'Acción inválida.');
}

['tabla' => $tabla, 'pk' => $pk, 'nombre' => $que] = $lista;

try {

    if ($accion === 'alternar') {
        $stmt = $pdo->prepare("UPDATE $tabla SET activo = 1 - activo WHERE $pk = :id");
        $stmt->execute([':id' => $id]);
        volver($stmt->rowCount() ? 'ok' : 'error', $stmt->rowCount() ? 'Listo: se cambió la visibilidad.' : 'Ya no existe.');
    }

    if ($accion === 'eliminar') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE $pk = :id");
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            volver('error', 'No se puede eliminar porque tiene productos. Podés ocultarlo o cambiar esos productos.');
        }
        $stmt = $pdo->prepare("DELETE FROM $tabla WHERE $pk = :id");
        $stmt->execute([':id' => $id]);
        volver($stmt->rowCount() ? 'ok' : 'error', $stmt->rowCount() ? 'Se eliminó.' : 'Ya no existe.');
    }

    // crear / actualizar
    $nombre = campo('nombre');
    $desc   = campo('descripcion');

    if ($nombre === '' || mb_strlen($nombre) > $lista['max']) {
        volver('error', 'El nombre es obligatorio y puede tener hasta ' . $lista['max'] . ' caracteres.');
    }
    if ($lista['desc'] && mb_strlen($desc) > CATEGORIA_DESC_MAX) {
        volver('error', 'La descripción puede tener hasta ' . CATEGORIA_DESC_MAX . ' caracteres.');
    }

    if ($accion === 'crear') {
        if ($lista['desc']) {
            $pdo->prepare("INSERT INTO $tabla (nombre, descripcion) VALUES (:n, :d)")->execute([':n' => $nombre, ':d' => $desc]);
        } else {
            $pdo->prepare("INSERT INTO $tabla (nombre) VALUES (:n)")->execute([':n' => $nombre]);
        }
        volver('ok', "Se agregó «{$nombre}».");
    }

    if ($lista['desc']) {
        $pdo->prepare("UPDATE $tabla SET nombre = :n, descripcion = :d WHERE $pk = :id")
            ->execute([':n' => $nombre, ':d' => $desc, ':id' => $id]);
    } else {
        $pdo->prepare("UPDATE $tabla SET nombre = :n WHERE $pk = :id")->execute([':n' => $nombre, ':id' => $id]);
    }
    volver('ok', "Se guardó «{$nombre}».");

} catch (PDOException $e) {
    // 1062 = nombre repetido
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        volver('error', 'Ya existe uno con ese nombre. Elegí otro.');
    }
    error_log('Error en categorías/tipos: ' . $e->getMessage());
    volver('error', 'No se pudo guardar. Intente nuevamente.');
}
