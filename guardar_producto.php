<?php
declare(strict_types=1);

/**
 * Catálogo: alta, edición, ocultar/mostrar y baja de productos — Elegancia a Medida
 *
 * Recibe POST de productos.php y producto.php con accion =
 *   guardar  (alta si id = 0, edición si id > 0; puede traer foto)
 *   alternar (ocultar / mostrar en el sitio)
 *   eliminar (borra el producto y su foto; las solicitudes que lo usaban
 *             quedan sin referencia: id_productos = NULL)
 * Tablas: productos (con tipo de prenda, venta/alquiler, oferta y temporada)
 *         y talles_producto (guía de talles: se reemplaza entera al guardar).
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
    header('Location: productos.php');
    exit;
}

function campo(string $nombre): string
{
    return isset($_POST[$nombre]) && is_string($_POST[$nombre]) ? trim($_POST[$nombre]) : '';
}


// Una foto más grande que post_max_size vacía todo el POST (también el token).
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    volver_lista('error', 'La foto es demasiado grande para el servidor (máximo ' . (IMAGEN_MAX_BYTES / 1024 / 1024) . ' MB).');
}

if (!token_modista_valido()) {
    volver_lista('error', 'La sesión expiró. Recargue la página e intente de nuevo.');
}

$accion = campo('accion');
$id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;

try {

    // ---------- OCULTAR / MOSTRAR ----------
    if ($accion === 'alternar' && $id > 0) {
        $stmt = $pdo->prepare('UPDATE productos SET activo = 1 - activo, fecha_actualizacion = NOW() WHERE id_productos = :id');
        $stmt->execute([':id' => $id]);
        volver_lista($stmt->rowCount() ? 'ok' : 'error', $stmt->rowCount() ? 'Listo: se cambió la visibilidad del producto.' : 'El producto ya no existe.');
    }

    // ---------- ELIMINAR ----------
    if ($accion === 'eliminar' && $id > 0) {
        $stmt = $pdo->prepare('SELECT nombre, imagen FROM productos WHERE id_productos = :id');
        $stmt->execute([':id' => $id]);
        $p = $stmt->fetch();
        if (!$p) {
            volver_lista('error', 'El producto ya no existe.');
        }
        $pdo->prepare('DELETE FROM productos WHERE id_productos = :id')->execute([':id' => $id]);
        borrar_imagen_producto($p['imagen']);
        volver_lista('ok', "Se eliminó «{$p['nombre']}» del catálogo.");
    }

    if ($accion !== 'guardar') {
        volver_lista('error', 'Acción inválida.');
    }

    // ---------- GUARDAR (alta o edición) ----------
    $actual = null;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM productos WHERE id_productos = :id');
        $stmt->execute([':id' => $id]);
        $actual = $stmt->fetch() ?: null;
        if (!$actual) {
            volver_lista('error', "El producto #$id no existe.");
        }
    }

    $nombre      = campo('nombre');
    $descripcion = campo('descripcion');
    $id_cat      = campo('id_categorias');
    $id_tipo     = campo('id_tipos_producto');
    $activo      = isset($_POST['activo']) ? 1 : 0;
    $en_venta    = isset($_POST['en_venta']) ? 1 : 0;
    $en_alquiler = isset($_POST['en_alquiler']) ? 1 : 0;
    $temporada   = isset($_POST['de_temporada']) ? 1 : 0;
    $descuento_t = campo('descuento_porcentaje');
    $oferta_t    = campo('oferta_hasta');
    $quitar_foto = isset($_POST['quitar_foto']);
    $errores     = [];

    if ($nombre === '') {
        $errores[] = 'El nombre es obligatorio.';
    } elseif (mb_strlen($nombre) > PRODUCTO_NOMBRE_MAX) {
        $errores[] = 'El nombre no puede superar los ' . PRODUCTO_NOMBRE_MAX . ' caracteres.';
    }

    if (!ctype_digit($id_cat) || !array_key_exists((int) $id_cat, obtener_categorias($pdo, false))) {
        $errores[] = 'Elegí una categoría.';
    }

    if (!ctype_digit($id_tipo) || !array_key_exists((int) $id_tipo, obtener_tipos_producto($pdo, false))) {
        $errores[] = 'Elegí un tipo de prenda.';
    }

    if ($descripcion === '') {
        $errores[] = 'La descripción es obligatoria.';
    } elseif (mb_strlen($descripcion) > PRODUCTO_DESC_MAX) {
        $errores[] = 'La descripción no puede superar los ' . PRODUCTO_DESC_MAX . ' caracteres.';
    }

    // Venta y alquiler: al menos una. El precio solo cuenta si la modalidad está marcada.
    if (!$en_venta && !$en_alquiler) {
        $errores[] = 'Marcá si el producto se vende, se alquila o las dos cosas.';
    }
    $precio_venta = $precio_alquiler = null;
    if ($en_venta) {
        [$precio_venta, $err] = leer_precio_opcional(campo('precio_venta'), 'precio de confección');
        if ($err) { $errores[] = $err; }
    }
    if ($en_alquiler) {
        [$precio_alquiler, $err] = leer_precio_opcional(campo('precio_alquiler'), 'precio de alquiler');
        if ($err) { $errores[] = $err; }
    }

    // Oferta
    $descuento = 0;
    if ($descuento_t !== '') {
        if (!ctype_digit($descuento_t) || (int) $descuento_t > DESCUENTO_MAX) {
            $errores[] = 'El descuento tiene que ser un número entero entre 0 y ' . DESCUENTO_MAX . '.';
        } else {
            $descuento = (int) $descuento_t;
        }
    }
    $oferta_hasta = null;
    if ($oferta_t !== '') {
        $f = DateTime::createFromFormat('!Y-m-d', $oferta_t);
        if (!$f || $f->format('Y-m-d') !== $oferta_t) {
            $errores[] = 'La fecha de fin de la oferta no es válida.';
        } elseif ($descuento === 0) {
            $errores[] = 'Pusiste una fecha de fin de oferta pero el descuento es 0.';
        } elseif ($oferta_t < date('Y-m-d') && $oferta_t !== ($actual['oferta_hasta'] ?? null)) {
            $errores[] = 'La fecha de fin de la oferta no puede ser anterior a hoy.';
        } else {
            $oferta_hasta = $oferta_t;
        }
    }

    // Guía de talles: filas con nombre de talle; las filas totalmente vacías se ignoran.
    $talles_form = [];   // lo enviado (para devolverlo si hay errores)
    $talles      = [];   // lo que se guarda
    $vistos      = [];
    $filas = isset($_POST['talles']) && is_array($_POST['talles']) ? array_slice($_POST['talles'], 0, TALLES_MAX) : [];
    foreach ($filas as $n => $fila) {
        if (!is_array($fila)) { continue; }
        $f = ['talle' => isset($fila['talle']) && is_string($fila['talle']) ? trim($fila['talle']) : ''];
        foreach (array_keys(MEDIDAS_TALLE) as $c) {
            $f[$c] = isset($fila[$c]) && is_string($fila[$c]) ? trim($fila[$c]) : '';
        }
        if (implode('', $f) === '') { continue; }
        $talles_form[] = $f;

        $nro = count($talles_form);
        if ($f['talle'] === '') {
            $errores[] = "Guía de talles, fila $nro: falta el nombre del talle (ej: M o 40).";
            continue;
        }
        if (mb_strlen($f['talle']) > TALLE_NOMBRE_MAX || mb_strtolower($f['talle']) === mb_strtolower(TALLE_A_MEDIDA)) {
            $errores[] = "Guía de talles: el talle «{$f['talle']}» no es válido (hasta " . TALLE_NOMBRE_MAX . ' caracteres; «A medida» está reservado).';
            continue;
        }
        if (isset($vistos[mb_strtolower($f['talle'])])) {
            $errores[] = "Guía de talles: el talle «{$f['talle']}» está repetido.";
            continue;
        }
        $vistos[mb_strtolower($f['talle'])] = true;
        $fila_ok = ['talle' => $f['talle']];
        foreach (MEDIDAS_TALLE as $c => [$nombre_medida]) {   // ¡no usar $nombre: es el de la prenda!
            [$fila_ok[$c], $err] = leer_medida($f[$c], "Talle {$f['talle']}, $nombre_medida");
            if ($err) { $errores[] = $err; }
        }
        $talles[] = $fila_ok;
    }

    // La foto se procesa al final: solo si todo lo demás está bien.
    $foto_nueva = null;
    if (!$errores) {
        $r = guardar_imagen_subida($_FILES['imagen'] ?? null);
        if (isset($r['error'])) {
            $errores[] = $r['error'];
        } else {
            $foto_nueva = $r['archivo'];
        }
    }

    if ($errores) {
        $_SESSION['form_producto'] = [
            'id'      => $id,
            'errores' => $errores,
            'datos'   => [
                'nombre' => $nombre, 'descripcion' => $descripcion, 'id_categorias' => $id_cat,
                'id_tipos_producto' => $id_tipo,
                'en_venta' => $en_venta, 'precio_venta' => campo('precio_venta'),
                'en_alquiler' => $en_alquiler, 'precio_alquiler' => campo('precio_alquiler'),
                'descuento_porcentaje' => $descuento_t, 'oferta_hasta' => $oferta_t,
                'de_temporada' => $temporada, 'activo' => $activo,
                'talles' => $talles_form,
            ],
        ];
        header('Location: producto.php' . ($id > 0 ? '?id=' . $id : ''));
        exit;
    }

    // Foto que queda guardada en la base
    $imagen_anterior = $actual['imagen'] ?? null;
    $imagen = $foto_nueva ?? ($quitar_foto ? null : $imagen_anterior);

    $params = [
        ':cat'      => (int) $id_cat,
        ':tipo'     => (int) $id_tipo,
        ':en_venta' => $en_venta,
        ':en_alq'   => $en_alquiler,
        ':desc_pct' => $descuento,
        ':hasta'    => $oferta_hasta,
        ':temp'     => $temporada,
        ':nombre'   => $nombre,
        ':desc'     => $descripcion,
        ':venta'    => $precio_venta,
        ':alquiler' => $precio_alquiler,
        ':imagen'   => $imagen,
        ':activo'   => $activo,
    ];

    try {
        $pdo->beginTransaction();   // producto y guía de talles se guardan juntos
        if ($actual) {
            $params[':id'] = $id;
            $pdo->prepare('UPDATE productos
                              SET id_categorias = :cat, id_tipos_producto = :tipo, nombre = :nombre, descripcion = :desc,
                                  en_venta = :en_venta, precio_venta = :venta,
                                  en_alquiler = :en_alq, precio_alquiler = :alquiler,
                                  descuento_porcentaje = :desc_pct, oferta_hasta = :hasta, de_temporada = :temp,
                                  imagen = :imagen, activo = :activo, fecha_actualizacion = NOW()
                            WHERE id_productos = :id')->execute($params);
            $mensaje = "Se guardaron los cambios de «{$nombre}».";
        } else {
            $pdo->prepare('INSERT INTO productos
                              (id_categorias, id_tipos_producto, nombre, descripcion, en_venta, precio_venta,
                               en_alquiler, precio_alquiler, descuento_porcentaje, oferta_hasta, de_temporada, imagen, activo)
                           VALUES (:cat, :tipo, :nombre, :desc, :en_venta, :venta,
                                   :en_alq, :alquiler, :desc_pct, :hasta, :temp, :imagen, :activo)')->execute($params);
            $mensaje = "Se agregó «{$nombre}» al catálogo" . ($activo ? '.' : ' (oculto).');
            $id = (int) $pdo->lastInsertId();
        }

        // Guía de talles: se reemplaza entera
        $pdo->prepare('DELETE FROM talles_producto WHERE id_productos = :id')->execute([':id' => $id]);
        $cols = array_keys(MEDIDAS_TALLE);
        $ins = $pdo->prepare('INSERT INTO talles_producto (id_productos, talle, orden, ' . implode(', ', $cols) . ')
                              VALUES (:id, :talle, :orden, :' . implode(', :', $cols) . ')');
        foreach ($talles as $orden => $t) {
            $p = [':id' => $id, ':talle' => $t['talle'], ':orden' => $orden + 1];
            foreach ($cols as $c) { $p[":$c"] = $t[$c]; }
            $ins->execute($p);
        }
        $pdo->commit();
        if ($talles) {
            $mensaje .= ' Guía de talles: ' . implode(', ', array_column($talles, 'talle')) . '.';
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        borrar_imagen_producto($foto_nueva);   // no dejar fotos sueltas si falló la base
        throw $e;
    }

    // Recién ahora, con la base actualizada, se borra la foto reemplazada o quitada.
    if ($imagen_anterior !== null && $imagen_anterior !== $imagen) {
        borrar_imagen_producto($imagen_anterior);
    }

    volver_lista('ok', $mensaje);

} catch (PDOException $e) {
    error_log('Error en productos: ' . $e->getMessage());
    volver_lista('error', 'No se pudo guardar el producto. Intente nuevamente.');
}
