<?php
declare(strict_types=1);

/**
 * Funciones compartidas — Elegancia a Medida
 *
 * Requiere config.php incluido antes.
 */


/** Escapa texto para imprimirlo en HTML. */
function e(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}


/** Formato de dinero argentino: $ 85.000,00 */
function pesos(float $monto): string
{
    return '$ ' . number_format($monto, 2, ',', '.');
}


/** Porcentaje con signo y sin ceros de más: +40 %, -10 %, 12,5 % */
function porcentaje(float $valor): string
{
    $txt = rtrim(rtrim(number_format(abs($valor), 2, ',', ''), '0'), ',');
    $signo = $valor > 0 ? '+' : ($valor < 0 ? '−' : '');
    return $signo . $txt . ' %';
}


/** Fecha máxima aceptada para fecha_requerida (hoy + FECHA_MAX_DIAS). */
function fecha_maxima(): DateTime
{
    return (new DateTime('today'))->modify('+' . FECHA_MAX_DIAS . ' days');
}


/**
 * Dirección de un CSS o JS con la fecha del archivo al final (css/sitio.css?v=1730…).
 * Así, cuando se actualiza el archivo, el navegador baja el nuevo en vez de
 * usar el que tenía guardado (no hace falta Ctrl + F5).
 */
function asset(string $ruta): string
{
    $archivo = __DIR__ . '/' . $ruta;
    return e($ruta . (is_file($archivo) ? '?v=' . filemtime($archivo) : ''));
}


/** Días enteros desde hoy hasta $fecha (0 = hoy). */
function dias_desde_hoy(DateTime $fecha): int
{
    $hoy = new DateTime('today');
    $dias = (int) $hoy->diff($fecha)->format('%r%a');
    return $dias;
}


/**
 * Tipos de servicio desde la tabla tipos_servicio.
 * @return array<int,string> id_tipos_servicio => nombre
 */
function obtener_tipos_servicio(PDO $pdo, bool $solo_activos = true): array
{
    $sql = 'SELECT id_tipos_servicio, nombre FROM tipos_servicio'
         . ($solo_activos ? ' WHERE activo = 1' : '')
         . ' ORDER BY id_tipos_servicio';

    $tipos = [];
    foreach ($pdo->query($sql) as $fila) {
        $tipos[(int) $fila['id_tipos_servicio']] = $fila['nombre'];
    }
    return $tipos;
}


/**
 * Todas las tarifas por plazo, ordenadas de menor a mayor plazo
 * ("sin límite" al final).
 */
function obtener_tarifas(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id_tarifas_plazo, nombre, dias_hasta, recargo_porcentaje
           FROM tarifas_plazo
          ORDER BY dias_hasta IS NULL, dias_hasta'
    )->fetchAll();
}


/**
 * Tarifa que corresponde a un plazo en días:
 * la de menor dias_hasta que sea >= $dias, o la "sin límite".
 * Devuelve null si ninguna cubre ese plazo.
 */
function tarifa_para_plazo(PDO $pdo, int $dias): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id_tarifas_plazo, nombre, dias_hasta, recargo_porcentaje
           FROM tarifas_plazo
          WHERE dias_hasta IS NULL OR dias_hasta >= :dias
          ORDER BY dias_hasta IS NULL, dias_hasta
          LIMIT 1'
    );
    $stmt->execute([':dias' => $dias]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}


/**
 * Desde cuántos días de plazo ya no hay recargo (según tarifas_plazo), o null
 * si todas las tarifas tienen recargo. Ej.: Urgente hasta 7 (+40 %),
 * Rápido hasta 15 (+20 %), Normal sin límite (0 %) → 16.
 */
function dias_sin_recargo(array $tarifas): ?int
{
    $desde = 0;
    foreach ($tarifas as $t) {   // vienen ordenadas por dias_hasta (la «sin límite» al final)
        if ((float) $t['recargo_porcentaje'] <= 0) {
            return $desde;
        }
        if ($t['dias_hasta'] === null) {
            return null;
        }
        $desde = (int) $t['dias_hasta'] + 1;
    }
    return null;
}

/**
 * Fecha sugerida para la solicitud: la primera sin recargo y, si es un arreglo
 * de la lista que tarda más, la de su demora. null si no hay plazo sin recargo
 * o queda después de la fecha máxima.
 */
function fecha_sugerida_sin_recargo(array $tarifas, int $demora_dias = 0): ?DateTime
{
    $dias = dias_sin_recargo($tarifas);
    if ($dias === null) {
        return null;
    }
    $f = (new DateTime('today'))->modify('+' . max($dias, $demora_dias) . ' days');
    return $f <= fecha_maxima() ? $f : null;
}


/** monto_total = monto_base × (1 + recargo / 100), redondeado a centavos. */
function calcular_total(float $monto_base, float $recargo_porcentaje): float
{
    return round($monto_base * (100 + $recargo_porcentaje) / 100, 2);
}


/** Texto legible para el rango de una tarifa. */
function texto_rango_tarifa(?int $desde, ?int $hasta): string
{
    if ($hasta === null) {
        return $desde === null || $desde === 0 ? 'Cualquier plazo' : "Más de " . ($desde - 1) . " días";
    }
    return ($desde ?? 0) === 0 ? "Hasta $hasta días" : "De $desde a $hasta días";
}


/**
 * Token del panel de la modista (protección CSRF de los formularios POST).
 * No es de un solo uso: la modista hace varios cambios seguidos.
 */
function token_modista(): string
{
    if (empty($_SESSION['token_modista'])) {
        $_SESSION['token_modista'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token_modista'];
}

function token_modista_valido(): bool
{
    $token = isset($_POST['token']) && is_string($_POST['token']) ? $_POST['token'] : '';
    return $token !== ''
        && !empty($_SESSION['token_modista'])
        && hash_equals($_SESSION['token_modista'], $token);
}


/** Mensaje de una sola vez para el panel (ok / error). */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash_modista'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function tomar_flash(): ?array
{
    $f = $_SESSION['flash_modista'] ?? null;
    unset($_SESSION['flash_modista']);
    return $f;
}


// =====================================================
// SEGUIMIENTO Y NOTIFICACIONES AL CLIENTE
// =====================================================

/** Genera un código de seguimiento al azar que no exista en la base. */
function generar_codigo_seguimiento(PDO $pdo): string
{
    return generar_codigo_unico($pdo, 'SELECT 1 FROM solicitudes_presupuesto WHERE codigo_seguimiento = :c');
}

/** Número corto de pedido (pedidos.codigo), que no se repite. */
function generar_codigo_pedido(PDO $pdo): string
{
    return generar_codigo_unico($pdo, 'SELECT 1 FROM pedidos WHERE codigo = :c');
}

/** Código al azar de CODIGO_LARGO caracteres que no exista según $sql_existe. */
function generar_codigo_unico(PDO $pdo, string $sql_existe): string
{
    $stmt = $pdo->prepare($sql_existe);
    $max  = strlen(CODIGO_ALFABETO) - 1;

    do {
        $codigo = '';
        for ($i = 0; $i < CODIGO_LARGO; $i++) {
            $codigo .= CODIGO_ALFABETO[random_int(0, $max)];
        }
        $stmt->execute([':c' => $codigo]);
    } while ($stmt->fetchColumn());

    return $codigo;
}


/**
 * Limpia lo que escribe el cliente: mayúsculas, sin guiones ni espacios,
 * y corrige las confusiones O→0 e I→1 (los códigos nuevos no usan O ni I).
 * Devuelve '' si no tiene el formato de un código.
 */
function normalizar_codigo(string $texto): string
{
    $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $texto));
    $c = strtr($c, ['O' => '0', 'I' => '1']);
    return preg_match('/^[A-Z0-9]{' . CODIGO_LARGO . '}$/', $c) ? $c : '';
}


/** Código en dos bloques para leerlo fácil: 7K3Q-X9PA */
function formatear_codigo(string $codigo): string
{
    return substr($codigo, 0, 4) . '-' . substr($codigo, 4);
}


/**
 * Crea el aviso para el cliente cuando su solicitud cambia de estado.
 * Se llama desde guardar_presupuesto.php y actualizar_estado.php.
 * Si falla, lo deja en el log y NO interrumpe la acción de la modista.
 */
function notificar(PDO $pdo, int $id_solicitud, int $estado_nuevo, bool $presupuesto_actualizado = false): void
{
    if (!isset(NOTIFICACIONES[$estado_nuevo])) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT s.tipo_prenda, p.monto_total, p.fecha_entrega, p.observaciones
               FROM solicitudes_presupuesto s
               LEFT JOIN presupuestos p ON p.id_solicitudes_presupuesto = s.id_solicitudes_presupuesto
              WHERE s.id_solicitudes_presupuesto = :id'
        );
        $stmt->execute([':id' => $id_solicitud]);
        $d = $stmt->fetch();
        if (!$d) {
            return;
        }

        [$titulo, $mensaje] = NOTIFICACIONES[$estado_nuevo];
        if ($presupuesto_actualizado && $estado_nuevo === ESTADO_PRESUPUESTADO) {
            $titulo = NOTIF_TITULO_PRESUPUESTO_ACTUALIZADO;
        }

        $entrega = $d['fecha_entrega']
            ? DateTime::createFromFormat('!Y-m-d', $d['fecha_entrega'])->format('d/m/Y')
            : 'a confirmar';

        $mensaje = strtr($mensaje, [
            '{prenda}'        => mb_strtolower(mb_substr($d['tipo_prenda'], 0, 1)) . mb_substr($d['tipo_prenda'], 1),
            '{total}'         => $d['monto_total'] !== null ? pesos((float) $d['monto_total']) : 'a confirmar',
            '{entrega}'       => $entrega,
            '{observaciones}' => (string) $d['observaciones'],
        ]);
        $mensaje = mb_substr(trim($mensaje), 0, 500);

        $pdo->prepare(
            'INSERT INTO notificaciones (id_solicitudes_presupuesto, id_estados_solicitud, titulo, mensaje)
             VALUES (:id, :estado, :titulo, :mensaje)'
        )->execute([
            ':id'      => $id_solicitud,
            ':estado'  => $estado_nuevo,
            ':titulo'  => $titulo,
            ':mensaje' => $mensaje,
        ]);

    } catch (PDOException $e) {
        error_log('No se pudo crear la notificación de la solicitud ' . $id_solicitud . ': ' . $e->getMessage());
    }
}


// =====================================================
// CUENTAS DE CLIENTES (sesión, login, permisos)
// =====================================================

/**
 * Inicia la sesión si todavía no está iniciada (cookie solo HTTP).
 * Usar esto en lugar de session_start() en las páginas del cliente.
 */
function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }
}


/** Token CSRF de los formularios del cliente (registro, login, solicitud, respuesta). */
function token_cliente(): string
{
    if (empty($_SESSION['token_cliente'])) {
        $_SESSION['token_cliente'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token_cliente'];
}

function token_cliente_valido(): bool
{
    $token = isset($_POST['token']) && is_string($_POST['token']) ? $_POST['token'] : '';
    return $token !== ''
        && !empty($_SESSION['token_cliente'])
        && hash_equals($_SESSION['token_cliente'], $token);
}


/** Mensaje de una sola vez para las páginas del cliente. */
function flash_cliente(string $tipo, string $mensaje): void
{
    $_SESSION['flash_cliente'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function tomar_flash_cliente(): ?array
{
    $f = $_SESSION['flash_cliente'] ?? null;
    unset($_SESSION['flash_cliente']);
    return $f;
}


/**
 * Solo acepta destinos dentro del sitio (ej: "seguimiento.php?codigo=AB12CD34").
 * Evita que un enlace de login mande al cliente a otra página web.
 */
function destino_seguro(?string $url, string $por_defecto = 'mis_solicitudes.php'): string
{
    $url = (string) $url;
    return preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%.+\-]*)?$/', $url) ? $url : $por_defecto;
}


/** Página actual (para volver después del login). */
function pagina_actual(): string
{
    $pagina = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    $query  = $_SERVER['QUERY_STRING'] ?? '';
    return $pagina . ($query !== '' ? '?' . $query : '');
}


/**
 * Cliente con sesión iniciada, o null. Se vuelve a leer de la base en
 * cada página: si la cuenta se desactivó, la sesión deja de valer.
 */
function cliente_logueado(PDO $pdo): ?array
{
    $id = (int) ($_SESSION['cliente_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id_clientes, nombre, telefono, email FROM clientes WHERE id_clientes = :id AND activo = 1');
    $stmt->execute([':id' => $id]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        unset($_SESSION['cliente_id']);
        return null;
    }
    $cliente['id_clientes'] = (int) $cliente['id_clientes'];
    return $cliente;
}


/** Igual que cliente_logueado(), pero si no hay sesión manda al login. */
function requerir_cliente(PDO $pdo, string $mensaje = 'Iniciá sesión para continuar.'): array
{
    $cliente = cliente_logueado($pdo);
    if ($cliente === null) {
        flash_cliente('info', $mensaje);
        header('Location: login.php?volver=' . urlencode(pagina_actual()));
        exit;
    }
    return $cliente;
}


/** Deja al cliente con la sesión iniciada (nuevo id de sesión, contra robo de sesión). */
function abrir_sesion_cliente(int $id_clientes): void
{
    session_regenerate_id(true);
    $_SESSION['cliente_id'] = $id_clientes;
    unset($_SESSION['token_cliente']);   // token nuevo después de entrar
}


/**
 * Verifica email y clave contra la tabla de clientes o la de usuarios del panel.
 * Devuelve ['ok' => true, 'id' => …] o ['ok' => false, 'error' => '…'].
 * Tras LOGIN_MAX_INTENTOS claves incorrectas seguidas, bloquea la cuenta
 * LOGIN_BLOQUEO_MINUTOS minutos.
 */
function verificar_credenciales(PDO $pdo, string $tabla, string $email, string $clave): array
{
    // Solo estas dos tablas: el nombre no viene nunca del usuario.
    $pk = ['clientes' => 'id_clientes', 'usuarios' => 'id_usuarios'][$tabla] ?? null;
    if ($pk === null) {
        throw new InvalidArgumentException('Tabla de login no válida');
    }
    $generico = 'El email o la contraseña no son correctos.';

    $stmt = $pdo->prepare(
        "SELECT $pk AS id, clave_hash, activo, intentos_fallidos,
                (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) AS bloqueado,
                CEIL(TIMESTAMPDIFF(SECOND, NOW(), bloqueado_hasta) / 60) AS minutos
           FROM $tabla WHERE email = :email"
    );
    $stmt->execute([':email' => $email]);
    $c = $stmt->fetch();

    if (!$c) {
        // Mismo tiempo de respuesta que con un email existente.
        password_verify($clave, '$2y$10$nR9UtaMVNYQps87uKIogX.xXpTwQQTNw4sCHcQjmCNyszmb7x3Ady');
        return ['ok' => false, 'error' => $generico];
    }
    if ((int) $c['bloqueado'] === 1) {
        return ['ok' => false, 'error' => 'Hubo demasiados intentos fallidos. Probá de nuevo en '
            . max(1, (int) $c['minutos']) . ' minuto(s).'];
    }
    if ((int) $c['activo'] !== 1) {
        return ['ok' => false, 'error' => 'Esta cuenta está desactivada.'];
    }

    $id = (int) $c['id'];

    if (!password_verify($clave, $c['clave_hash'])) {
        $intentos = (int) $c['intentos_fallidos'] + 1;
        if ($intentos >= LOGIN_MAX_INTENTOS) {
            $pdo->prepare("UPDATE $tabla SET intentos_fallidos = 0,
                                  bloqueado_hasta = NOW() + INTERVAL " . LOGIN_BLOQUEO_MINUTOS . " MINUTE
                            WHERE $pk = :id")->execute([':id' => $id]);
            return ['ok' => false, 'error' => 'Hubo demasiados intentos fallidos. La cuenta quedó bloqueada '
                . LOGIN_BLOQUEO_MINUTOS . ' minutos.'];
        }
        $pdo->prepare("UPDATE $tabla SET intentos_fallidos = :n WHERE $pk = :id")
            ->execute([':n' => $intentos, ':id' => $id]);
        return ['ok' => false, 'error' => $generico];
    }

    // Clave correcta: se limpian los intentos y, si hace falta, se actualiza el hash.
    $nuevo_hash = password_needs_rehash($c['clave_hash'], PASSWORD_DEFAULT)
        ? password_hash($clave, PASSWORD_DEFAULT) : $c['clave_hash'];

    $pdo->prepare("UPDATE $tabla
                      SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW(), clave_hash = :h
                    WHERE $pk = :id")
        ->execute([':h' => $nuevo_hash, ':id' => $id]);

    return ['ok' => true, 'id' => $id];
}

/** Login de clientes (login.php). */
function verificar_login(PDO $pdo, string $email, string $clave): array
{
    return verificar_credenciales($pdo, 'clientes', $email, $clave);
}


/** Errores de una clave nueva (vacío si está bien). */
function validar_clave_nueva(string $clave, string $repetida): array
{
    if (mb_strlen($clave) < CLAVE_MIN)          return ['La contraseña debe tener al menos ' . CLAVE_MIN . ' caracteres.'];
    if (strlen($clave) > CLAVE_MAX)             return ['La contraseña no puede superar los ' . CLAVE_MAX . ' caracteres.'];
    if (!preg_match('/\p{L}/u', $clave) || !preg_match('/\d/', $clave))
                                                return ['La contraseña debe tener letras y números.'];
    if ($clave !== $repetida)                   return ['Las dos contraseñas no coinciden.'];
    return [];
}


/** Errores del nombre (vacío si está bien). Mismas reglas que tenía el formulario. */
function validar_nombre(string $nombre): array
{
    if ($nombre === '')                               return ['El nombre es obligatorio.'];
    if (mb_strlen($nombre) > NOMBRE_MAX)              return ['El nombre no puede superar los ' . NOMBRE_MAX . ' caracteres.'];
    if (!preg_match("/^[\p{L}\s'.-]+$/u", $nombre))   return ['El nombre solo puede contener letras, espacios, puntos, guiones y apóstrofes.'];
    if (!preg_match('/\p{L}/u', $nombre))             return ['El nombre debe contener al menos una letra.'];
    return [];
}


/** Errores del teléfono (vacío si está bien). */
function validar_telefono(string $telefono): array
{
    if ($telefono === '')                              return ['El teléfono es obligatorio.'];
    if (mb_strlen($telefono) > TELEFONO_MAX)           return ['El teléfono no puede superar los ' . TELEFONO_MAX . ' caracteres.'];
    if (!preg_match('/^[\d\s+\-()]+$/', $telefono))    return ['El teléfono solo puede contener números, espacios, +, - y paréntesis.'];
    $digitos = strlen(preg_replace('/\D/', '', $telefono));
    if ($digitos < TELEFONO_MIN_DIG)                   return ['El teléfono debe contener al menos ' . TELEFONO_MIN_DIG . ' dígitos.'];
    if ($digitos > 15)                                 return ['El teléfono no puede contener más de 15 dígitos.'];
    return [];
}


/** Cantidad de avisos sin leer del cliente (para el encabezado). */
function avisos_sin_leer(PDO $pdo, int $id_clientes): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM notificaciones n
           LEFT JOIN solicitudes_presupuesto s ON s.id_solicitudes_presupuesto = n.id_solicitudes_presupuesto
           LEFT JOIN pedidos p                 ON p.id_pedidos = n.id_pedidos
          WHERE n.leida = 0 AND (s.id_clientes = :id1 OR p.id_clientes = :id2)'
    );
    $stmt->execute([':id1' => $id_clientes, ':id2' => $id_clientes]);
    return (int) $stmt->fetchColumn();
}


/**
 * "Versión" del presupuesto que vio el cliente. Si la modista lo cambia
 * mientras el cliente lo está mirando, la versión no coincide y la
 * respuesta no se registra (ver responder_presupuesto.php).
 */
function version_presupuesto(array $p): string
{
    return hash('sha256', implode('|', [
        $p['monto_total'], $p['fecha_entrega'], $p['observaciones'],
        $p['fecha_actualizacion'] ?? $p['fecha_creacion'],
    ]));
}


/** Texto que ve el cliente para cada estado. */
function estado_para_cliente(int $estado): string
{
    return [
        ESTADO_PENDIENTE     => 'Esperando presupuesto',
        ESTADO_PRESUPUESTADO => 'Presupuesto para responder',
        ESTADO_NO_ACEPTADO   => 'No aceptaste',
        ESTADO_EN_PROCESO    => 'En confección',
        ESTADO_COMPLETADO    => 'Listo',
        ESTADO_CANCELADO     => 'Cancelada',
        ESTADO_RECHAZADO     => 'No disponible',
    ][$estado] ?? '';
}


/** Botones de estado que ve la modista para una solicitud (ver TRANSICIONES). */
function transiciones_permitidas(int $estado): array
{
    return TRANSICIONES[$estado] ?? [];
}


// =====================================================
// PANEL DE LA MODISTA (tabla usuarios)
// =====================================================

/**
 * Usuario del panel con sesión iniciada, o null. Se relee de la base en
 * cada página: si se desactiva la cuenta, la sesión deja de valer.
 */
function modista_logueada(PDO $pdo): ?array
{
    $id = (int) ($_SESSION['usuario_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id_usuarios, nombre, email, debe_cambiar_clave
                             FROM usuarios WHERE id_usuarios = :id AND activo = 1');
    $stmt->execute([':id' => $id]);
    $u = $stmt->fetch();
    if (!$u) {
        unset($_SESSION['usuario_id']);
        return null;
    }
    $u['id_usuarios'] = (int) $u['id_usuarios'];
    return $u;
}


/**
 * Protege una página del panel: sin sesión manda a panel_login.php.
 * Si la clave es provisoria (debe_cambiar_clave), solo deja entrar a
 * panel_clave.php hasta que la cambie.
 */
function requerir_modista(PDO $pdo): array
{
    $u = modista_logueada($pdo);
    if ($u === null) {
        $volver = $_SERVER['REQUEST_METHOD'] === 'GET' ? pagina_actual() : 'modista.php';
        flash('error', 'Ingresá con tu usuario para usar el panel.');
        header('Location: panel_login.php?volver=' . urlencode($volver));
        exit;
    }
    if ((int) $u['debe_cambiar_clave'] === 1 && basename($_SERVER['SCRIPT_NAME']) !== 'panel_clave.php') {
        flash('error', 'Antes de seguir, elegí una contraseña nueva.');
        header('Location: panel_clave.php');
        exit;
    }
    return $u;
}


/** Deja a la modista con la sesión iniciada (nuevo id de sesión). */
function abrir_sesion_modista(int $id_usuarios): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $id_usuarios;
    unset($_SESSION['token_modista']);
}


// =====================================================
// CATÁLOGO (categorias, productos y fotos)
// =====================================================

/**
 * Categorías del catálogo.
 * @return array<int,string> id_categorias => nombre
 */
function obtener_categorias(PDO $pdo, bool $solo_activas = true): array
{
    $sql = 'SELECT id_categorias, nombre FROM categorias'
         . ($solo_activas ? ' WHERE activo = 1' : '')
         . ' ORDER BY nombre';
    $lista = [];
    foreach ($pdo->query($sql) as $f) {
        $lista[(int) $f['id_categorias']] = $f['nombre'];
    }
    return $lista;
}


/** Ruta pública de una imagen guardada en $carpeta (ej: 'img/banners/'), o null. */
function url_imagen(string $carpeta, ?string $archivo): ?string
{
    if ($archivo === null || $archivo === '' || !preg_match('/^[a-z0-9_]+\.(jpg|png|webp)$/', $archivo)) {
        return null;
    }
    return is_file(__DIR__ . '/' . $carpeta . $archivo) ? $carpeta . $archivo : null;
}

/** Ruta pública de la foto de un producto, o null si no tiene. */
function url_imagen_producto(?string $archivo): ?string
{
    return url_imagen(CARPETA_PRODUCTOS, $archivo);
}


/** Precio de catálogo o "A consultar". */
function precio_o_consultar(?string $precio): string
{
    return $precio === null ? 'A consultar' : pesos((float) $precio);
}


/**
 * Valida y guarda una imagen subida ($_FILES['…']) en $carpeta.
 * Devuelve ['archivo' => nombre] si se guardó, ['archivo' => null] si no se
 * subió nada, o ['error' => '…'] si la imagen no sirve.
 * Revisa el contenido real del archivo (no la extensión) y le pone un nombre
 * nuevo al azar, así nunca se guarda un archivo con el nombre que eligió el usuario.
 */
function guardar_imagen_subida(?array $f, string $carpeta = CARPETA_PRODUCTOS, string $prefijo = 'prod'): array
{
    if ($f === null || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['archivo' => null];
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['error' => 'La imagen pesa demasiado (máximo ' . (IMAGEN_MAX_BYTES / 1024 / 1024) . ' MB).'];
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return ['error' => 'No se pudo subir la imagen. Probá de nuevo.'];
    }
    if ($f['size'] > IMAGEN_MAX_BYTES) {
        return ['error' => 'La imagen pesa demasiado (máximo ' . (IMAGEN_MAX_BYTES / 1024 / 1024) . ' MB).'];
    }

    $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset(IMAGEN_TIPOS[$tipo]) || @getimagesize($f['tmp_name']) === false) {
        return ['error' => 'La imagen tiene que ser JPG, PNG o WEBP.'];
    }

    $destino = __DIR__ . '/' . $carpeta;
    if (!is_dir($destino) && !mkdir($destino, 0755, true)) {
        return ['error' => 'No existe la carpeta ' . $carpeta . ' para guardar imágenes.'];
    }

    $archivo = $prefijo . '_' . bin2hex(random_bytes(8)) . '.' . IMAGEN_TIPOS[$tipo];
    if (!move_uploaded_file($f['tmp_name'], $destino . $archivo)) {
        return ['error' => 'No se pudo guardar la imagen en el servidor.'];
    }
    return ['archivo' => $archivo];
}


/**
 * Borra del disco una imagen subida. Las imágenes de ejemplo
 * (ejemplo_*.jpg) no se borran nunca, por si se vuelve a importar la base.
 */
function borrar_imagen(string $carpeta, ?string $archivo): void
{
    if ($archivo !== null && preg_match('/^[a-z0-9_]+\.(jpg|png|webp)$/', $archivo) && !str_starts_with($archivo, 'ejemplo_')) {
        $ruta = __DIR__ . '/' . $carpeta . $archivo;
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }
}

/** Borra del disco la foto de un producto (si existe). */
function borrar_imagen_producto(?string $archivo): void
{
    borrar_imagen(CARPETA_PRODUCTOS, $archivo);
}


/**
 * Lee un precio opcional del formulario. Vacío = NULL ("a consultar").
 * Devuelve [valor|null, error|null].
 */
function leer_precio_opcional(string $texto, string $etiqueta): array
{
    $t = str_replace(',', '.', trim($texto));
    if ($t === '') {
        return [null, null];
    }
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $t) || (float) $t <= 0 || (float) $t > PRECIO_MAX) {
        return [null, "El $etiqueta debe ser un número mayor a 0, con hasta 2 decimales (sin puntos de miles)."];
    }
    return [number_format((float) $t, 2, '.', ''), null];
}


// =====================================================
// OFERTAS Y TEMPORADA
// =====================================================

/** ¿El producto tiene una oferta vigente hoy? */
function oferta_vigente(array $p): bool
{
    if ((int) ($p['descuento_porcentaje'] ?? 0) <= 0) {
        return false;
    }
    return empty($p['oferta_hasta']) || $p['oferta_hasta'] >= date('Y-m-d');
}


/** Precio con el descuento aplicado (redondeado a centavos). */
function precio_con_descuento(float $precio, int $descuento): float
{
    return round($precio * (100 - $descuento) / 100, 2);
}


/**
 * Condición SQL de "oferta vigente" para usar en los WHERE.
 * Se arma acá para que el inicio y el panel usen exactamente la misma regla.
 */
function sql_oferta_vigente(string $alias = 'p'): string
{
    return "($alias.descuento_porcentaje > 0 AND ($alias.oferta_hasta IS NULL OR $alias.oferta_hasta >= CURDATE()))";
}


// =====================================================
// DATOS DEL SITIO (historia y contacto)
// =====================================================

/** La única fila de datos_sitio (se lee una sola vez por página). */
function datos_sitio(PDO $pdo): array
{
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = $pdo->query('SELECT * FROM datos_sitio WHERE id_datos_sitio = 1')->fetch() ?: [];
        } catch (PDOException $e) {
            error_log('Error al leer datos_sitio: ' . $e->getMessage());
            $cache = [];
        }
    }
    return $cache;
}


/** Enlace de WhatsApp (wa.me) a partir del número guardado, o null. */
function enlace_whatsapp(string $numero, string $mensaje = ''): ?string
{
    $digitos = preg_replace('/\D/', '', $numero);
    if (strlen($digitos) < 8) {
        return null;
    }
    return 'https://wa.me/' . $digitos . ($mensaje !== '' ? '?text=' . rawurlencode($mensaje) : '');
}


/**
 * WhatsApp de una clienta: su teléfono suele estar cargado sin el código del país
 * («11 4321-8765» o «0370 154-123456»), así que se le agrega el 54 9 de Argentina.
 */
function enlace_whatsapp_cliente(string $telefono, string $mensaje = ''): ?string
{
    $d = preg_replace('/\D/', '', $telefono);
    if (!str_starts_with($d, '54')) {
        $d = '549' . ltrim($d, '0');
    }
    return enlace_whatsapp($d, $mensaje);
}


/** Usuario de Instagram limpio (sin @ ni dirección), o ''. */
function usuario_instagram(string $texto): string
{
    $t = trim($texto);
    $t = preg_replace('~^https?://(www\.)?instagram\.com/~i', '', $t);
    $t = ltrim(trim($t, '/'), '@');
    return preg_match('/^[A-Za-z0-9._]{1,30}$/', $t) ? $t : '';
}


/**
 * Tipos de prenda (tabla tipos_producto).
 * @return array<int,string> id_tipos_producto => nombre
 */
function obtener_tipos_producto(PDO $pdo, bool $solo_activos = true): array
{
    $sql = 'SELECT id_tipos_producto, nombre FROM tipos_producto'
         . ($solo_activos ? ' WHERE activo = 1' : '')
         . ' ORDER BY nombre';
    $lista = [];
    foreach ($pdo->query($sql) as $f) {
        $lista[(int) $f['id_tipos_producto']] = $f['nombre'];
    }
    return $lista;
}



// =====================================================
// CARRITO Y PEDIDOS (items_carrito, pedidos, items_pedido)
// =====================================================

/** Cantidad total de prendas en el carrito (para el ícono). */
function cantidad_carrito(PDO $pdo, int $id_clientes): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(cantidad), 0) FROM items_carrito WHERE id_clientes = :id');
    $stmt->execute([':id' => $id_clientes]);
    return (int) $stmt->fetchColumn();
}


/**
 * Lo último que el cliente agregó al carrito en los últimos
 * CARRITO_DESHACER_MIN minutos (para el aviso "Deshacer"), o null.
 */
function ultimo_agregado(PDO $pdo, int $id_clientes): ?array
{
    $stmt = $pdo->prepare(
        'SELECT c.id_items_carrito, c.modalidad, c.talle, c.cantidad, p.nombre,
                TIMESTAMPDIFF(SECOND, NOW(), c.fecha_agregado + INTERVAL ' . CARRITO_DESHACER_MIN . ' MINUTE) AS segundos
           FROM items_carrito c INNER JOIN productos p ON p.id_productos = c.id_productos
          WHERE c.id_clientes = :id AND c.fecha_agregado > NOW() - INTERVAL ' . CARRITO_DESHACER_MIN . ' MINUTE
          ORDER BY c.fecha_agregado DESC, c.id_items_carrito DESC LIMIT 1'
    );
    $stmt->execute([':id' => $id_clientes]);
    $f = $stmt->fetch();
    return $f ?: null;
}


/**
 * Precio de una prenda en una modalidad, con la oferta vigente aplicada.
 * Devuelve null si es "a consultar".
 */
function precio_actual(array $p, string $modalidad): ?float
{
    $precio = $modalidad === 'alquiler' ? $p['precio_alquiler'] : $p['precio_venta'];
    if ($precio === null) {
        return null;
    }
    $desc = oferta_vigente($p) ? (int) $p['descuento_porcentaje'] : 0;
    return $desc ? precio_con_descuento((float) $precio, $desc) : round((float) $precio, 2);
}


/** Aviso para el cliente cuando su pedido cambia de estado. No interrumpe nada si falla. */
function notificar_pedido(PDO $pdo, int $id_pedido, int $estado_nuevo): void
{
    if (!isset(NOTIFICACIONES_PEDIDO[$estado_nuevo])) {
        return;
    }
    try {
        $stmt = $pdo->prepare('SELECT codigo, nota_modista, total_estimado, tiene_a_consultar FROM pedidos WHERE id_pedidos = :id');
        $stmt->execute([':id' => $id_pedido]);
        $p = $stmt->fetch();
        if (!$p) {
            return;
        }
        [$titulo, $mensaje] = NOTIFICACIONES_PEDIDO[$estado_nuevo];
        $sena = (int) $p['tiene_a_consultar'] === 1 ? 'a confirmar'
              : pesos(round((float) $p['total_estimado'] * SENA_PORCENTAJE / 100, 2));
        $mensaje = trim(strtr($mensaje, ['{codigo}' => formatear_codigo($p['codigo']), '{sena}' => $sena])
                 . ($p['nota_modista'] !== '' ? ' Nota: ' . $p['nota_modista'] : ''));
        $pdo->prepare('INSERT INTO notificaciones (id_pedidos, titulo, mensaje) VALUES (:id, :t, :m)')
            ->execute([':id' => $id_pedido, ':t' => $titulo, ':m' => mb_substr($mensaje, 0, 500)]);
    } catch (PDOException $e) {
        error_log('No se pudo crear la notificación del pedido ' . $id_pedido . ': ' . $e->getMessage());
    }
}


/** ¿El pedido tiene alguna prenda en alquiler? */
function pedido_tiene_alquiler(PDO $pdo, int $id_pedido): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM items_pedido WHERE id_pedidos = :id AND modalidad = 'alquiler' LIMIT 1");
    $stmt->execute([':id' => $id_pedido]);
    return (bool) $stmt->fetchColumn();
}


/** Botones de estado que ve la modista para un pedido. */
function transiciones_pedido(int $estado, bool $tiene_alquiler): array
{
    $t = TRANSICIONES_PEDIDO[$estado] ?? [];
    if (!$tiene_alquiler) {
        unset($t[PEDIDO_DEVUELTO]);   // "Devuelto" solo tiene sentido en alquileres
    }
    return $t;
}


// =====================================================
// LOGO Y PORTADA
// =====================================================

/** Ruta del logo subido por la modista, o null (se usa el logo de texto). */
function url_logo(PDO $pdo): ?string
{
    return url_imagen(CARPETA_SITIO, datos_sitio($pdo)['logo'] ?? null);
}


/** Banners activos de una ubicación ('carrusel' o 'mosaico'), en orden. */
function obtener_banners(PDO $pdo, string $ubicacion): array
{
    $stmt = $pdo->prepare('SELECT * FROM banners WHERE ubicacion = :u AND activo = 1 ORDER BY orden, id_banners');
    $stmt->execute([':u' => $ubicacion]);
    return $stmt->fetchAll();
}


/**
 * Enlace de un banner: solo páginas del sitio (ej: tienda.php?modalidad=alquiler)
 * o direcciones http(s). Cualquier otra cosa se descarta.
 */
function enlace_seguro(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('~^[a-z_]+\.php(\?[A-Za-z0-9_=&%.\-+]*)?(#[A-Za-z0-9_\-]*)?$~', $url)) {
        return $url;
    }
    if (preg_match('~^https?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL)) {
        return $url;
    }
    return '';
}


// Guía de talles y medidas de las clientas
require_once __DIR__ . '/funciones_medidas.php';

// Lista de precios de arreglos
require_once __DIR__ . '/funciones_arreglos.php';

// Taller: etapas de cada trabajo y turnos de prueba
require_once __DIR__ . '/funciones_taller.php';

// Tela sugerida y materiales del presupuesto
require_once __DIR__ . '/funciones_materiales.php';
