<?php
declare(strict_types=1);

/**
 * Portada: guardar, ordenar, ocultar/mostrar y eliminar banners — Elegancia a Medida
 * Recibe POST de banners.php y banner.php con accion = guardar | subir | bajar | alternar | eliminar.
 * Tabla: banners. Imágenes en img/banners/.
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

function volver_lista(string $tipo, string $mensaje): void
{
    flash($tipo, $mensaje);
    header('Location: banners.php');
    exit;
}

if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    volver_lista('error', 'La imagen es demasiado grande para el servidor (máximo ' . (IMAGEN_MAX_BYTES / 1024 / 1024) . ' MB).');
}
if (!token_modista_valido()) {
    volver_lista('error', 'La sesión expiró. Recargue la página e intente de nuevo.');
}

$campo  = fn(string $n): string => isset($_POST[$n]) && is_string($_POST[$n]) ? trim($_POST[$n]) : '';
$accion = $campo('accion');
$id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;

try {
    $actual = null;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM banners WHERE id_banners = :id');
        $stmt->execute([':id' => $id]);
        $actual = $stmt->fetch() ?: null;
        if (!$actual) {
            volver_lista('error', 'Ese banner ya no existe.');
        }
    }

    // ---------- Ordenar: intercambia el lugar con el vecino ----------
    if (($accion === 'subir' || $accion === 'bajar') && $actual) {
        $op = $accion === 'subir' ? '<' : '>';
        $dir = $accion === 'subir' ? 'DESC' : 'ASC';
        // Primero se numeran de corrido (por si hay órdenes repetidos)
        $filas = $pdo->prepare('SELECT id_banners FROM banners WHERE ubicacion = :u ORDER BY orden, id_banners');
        $filas->execute([':u' => $actual['ubicacion']]);
        $n = 1;
        $upd = $pdo->prepare('UPDATE banners SET orden = :o WHERE id_banners = :id');
        foreach ($filas->fetchAll(PDO::FETCH_COLUMN) as $idb) { $upd->execute([':o' => $n++, ':id' => $idb]); }

        $stmt = $pdo->prepare('SELECT orden FROM banners WHERE id_banners = :id');
        $stmt->execute([':id' => $id]);
        $mi_orden = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT id_banners, orden FROM banners WHERE ubicacion = :u AND orden $op :o ORDER BY orden $dir LIMIT 1");
        $stmt->execute([':u' => $actual['ubicacion'], ':o' => $mi_orden]);
        $vecino = $stmt->fetch();
        if ($vecino) {
            $upd->execute([':o' => $vecino['orden'], ':id' => $id]);
            $upd->execute([':o' => $mi_orden, ':id' => $vecino['id_banners']]);
        }
        header('Location: banners.php');
        exit;
    }

    if ($accion === 'alternar' && $actual) {
        $pdo->prepare('UPDATE banners SET activo = 1 - activo WHERE id_banners = :id')->execute([':id' => $id]);
        volver_lista('ok', 'Listo: se cambió la visibilidad de «' . $actual['titulo'] . '».');
    }

    if ($accion === 'eliminar' && $actual) {
        $pdo->prepare('DELETE FROM banners WHERE id_banners = :id')->execute([':id' => $id]);
        borrar_imagen(CARPETA_BANNERS, $actual['imagen']);
        volver_lista('ok', 'Se eliminó «' . $actual['titulo'] . '».');
    }

    if ($accion !== 'guardar') {
        volver_lista('error', 'Acción inválida.');
    }

    // ---------- Guardar (alta o edición) ----------
    $datos = [
        'ubicacion'   => in_array($campo('ubicacion'), ['carrusel', 'mosaico'], true) ? $campo('ubicacion') : 'carrusel',
        'titulo'      => $campo('titulo'),
        'subtitulo'   => $campo('subtitulo'),
        'texto_boton' => $campo('texto_boton'),
        'enlace'      => $campo('enlace'),
        'activo'      => isset($_POST['activo']) ? 1 : 0,
    ];
    $errores = [];
    if ($datos['titulo'] === '' || mb_strlen($datos['titulo']) > BANNER_TITULO_MAX) {
        $errores[] = 'El título es obligatorio y puede tener hasta ' . BANNER_TITULO_MAX . ' caracteres.';
    }
    if (mb_strlen($datos['subtitulo']) > BANNER_SUBTITULO_MAX) {
        $errores[] = 'El texto puede tener hasta ' . BANNER_SUBTITULO_MAX . ' caracteres.';
    }
    if (mb_strlen($datos['texto_boton']) > BANNER_BOTON_MAX) {
        $errores[] = 'El texto del botón puede tener hasta ' . BANNER_BOTON_MAX . ' caracteres.';
    }
    if ($datos['enlace'] !== '' && enlace_seguro($datos['enlace']) === '') {
        $errores[] = 'El enlace tiene que ser una página del sitio (ej: tienda.php?modalidad=alquiler) o una dirección que empiece con https://.';
    }
    if ($datos['texto_boton'] !== '' && $datos['enlace'] === '') {
        $errores[] = 'Si el banner tiene botón, indicá a dónde lleva.';
    }

    $img_nueva = null;
    if (!$errores) {
        $r = guardar_imagen_subida($_FILES['imagen'] ?? null, CARPETA_BANNERS, 'banner');
        if (isset($r['error'])) { $errores[] = $r['error']; } else { $img_nueva = $r['archivo']; }
    }

    if ($errores) {
        $_SESSION['form_banner'] = ['id' => $id, 'errores' => $errores, 'datos' => $datos];
        header('Location: banner.php' . ($id ? '?id=' . $id : '?ubicacion=' . $datos['ubicacion']));
        exit;
    }

    $img_anterior = $actual['imagen'] ?? null;
    $imagen = $img_nueva ?? (isset($_POST['quitar_imagen']) ? null : $img_anterior);

    $params = [
        ':u' => $datos['ubicacion'], ':t' => $datos['titulo'], ':s' => $datos['subtitulo'],
        ':b' => $datos['texto_boton'], ':e' => $datos['enlace'], ':i' => $imagen, ':a' => $datos['activo'],
    ];
    try {
        if ($actual) {
            $params[':id'] = $id;
            $pdo->prepare('UPDATE banners SET ubicacion = :u, titulo = :t, subtitulo = :s, texto_boton = :b,
                                              enlace = :e, imagen = :i, activo = :a WHERE id_banners = :id')->execute($params);
        } else {
            $params[':o'] = (int) $pdo->query('SELECT COALESCE(MAX(orden), 0) + 1 FROM banners WHERE ubicacion = ' . $pdo->quote($datos['ubicacion']))->fetchColumn();
            $pdo->prepare('INSERT INTO banners (ubicacion, titulo, subtitulo, texto_boton, enlace, imagen, activo, orden)
                           VALUES (:u, :t, :s, :b, :e, :i, :a, :o)')->execute($params);
        }
    } catch (PDOException $e) {
        borrar_imagen(CARPETA_BANNERS, $img_nueva);
        throw $e;
    }
    if ($img_anterior !== null && $img_anterior !== $imagen) {
        borrar_imagen(CARPETA_BANNERS, $img_anterior);
    }
    volver_lista('ok', ($actual ? 'Se guardaron los cambios de' : 'Se agregó') . ' «' . $datos['titulo'] . '».');

} catch (PDOException $e) {
    error_log('Error en banners: ' . $e->getMessage());
    volver_lista('error', 'No se pudo guardar. Intente nuevamente.');
}
