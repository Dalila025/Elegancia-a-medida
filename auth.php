<?php
declare(strict_types=1);

/**
 * Control de acceso del panel de la modista — Elegancia a Medida
 *
 * Uso, al principio de cada página o procesador del panel
 * (después de config.php, funciones.php y session_start()):
 *
 *     require_once __DIR__ . '/auth.php';
 *     requerir_login();
 *
 * Tabla: usuarios (id_usuarios, nombre, email, clave_hash, rol).
 */

// Tiempo máximo sin actividad antes de pedir el ingreso otra vez.
const SESION_INACTIVIDAD_SEG = 2 * 60 * 60;   // 2 horas

// Intentos fallidos permitidos antes de esperar.
const LOGIN_MAX_INTENTOS = 5;
const LOGIN_ESPERA_SEG   = 60;


/** ¿Hay una modista con sesión iniciada? */
function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}


/**
 * Corta la ejecución si no hay sesión iniciada.
 * - Si es una página (GET): manda al login y después vuelve a la misma página.
 * - Si es un envío (POST): manda al login con aviso de sesión vencida.
 */
function requerir_login(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $usuario = usuario_actual();

    // Sesión vencida por inactividad
    if ($usuario && (time() - ($_SESSION['ultimo_acceso'] ?? 0)) > SESION_INACTIVIDAD_SEG) {
        cerrar_sesion();
        session_start();
        $_SESSION['aviso_login'] = 'Tu sesión venció por inactividad. Ingresá de nuevo.';
        $usuario = null;
    }

    if ($usuario) {
        $_SESSION['ultimo_acceso'] = time();
        return;
    }

    $destino = 'login.php';
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $destino .= '?volver=' . urlencode(basename($_SERVER['PHP_SELF'])
            . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']));
    } elseif (empty($_SESSION['aviso_login'])) {
        $_SESSION['aviso_login'] = 'Tu sesión venció. Ingresá de nuevo; el cambio no se guardó.';
    }

    header('Location: ' . $destino);
    exit;
}


/**
 * Busca la usuaria por email y verifica la contraseña.
 * Devuelve sus datos públicos o null. Nunca dice cuál de los dos falló.
 */
function verificar_credenciales(PDO $pdo, string $email, string $clave): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id_usuarios, nombre, email, clave_hash, rol
           FROM usuarios
          WHERE email = :email
            AND activo = 1
          LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $fila = $stmt->fetch();

    if (!$fila || !password_verify($clave, $fila['clave_hash'])) {
        return null;
    }

    // Si PHP mejora el algoritmo en el futuro, se actualiza el hash guardado.
    if (password_needs_rehash($fila['clave_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE usuarios SET clave_hash = :h WHERE id_usuarios = :id')
            ->execute([':h' => password_hash($clave, PASSWORD_DEFAULT), ':id' => $fila['id_usuarios']]);
    }

    return [
        'id'     => (int) $fila['id_usuarios'],
        'nombre' => $fila['nombre'],
        'email'  => $fila['email'],
        'rol'    => $fila['rol'],
    ];
}


/** Inicia la sesión de la usuaria (con un ID de sesión nuevo). */
function iniciar_sesion_usuario(array $usuario): void
{
    session_regenerate_id(true);           // evita fijación de sesión
    unset($_SESSION['token_modista']);     // token del panel nuevo para esta sesión
    $_SESSION['usuario']       = $usuario;
    $_SESSION['ultimo_acceso'] = time();
    unset($_SESSION['login_intentos'], $_SESSION['login_bloqueo_hasta']);
}


/** Borra la sesión por completo (datos y cookie). */
function cerrar_sesion(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}


/**
 * Solo se permite volver a páginas propias del panel (evita redirecciones
 * a otros sitios con ?volver=https://...).
 */
function destino_seguro(string $volver): string
{
    $paginas = ['modista.php', 'presupuesto.php', 'tarifas.php'];
    $archivo = strtok($volver, '?');
    return in_array($archivo, $paginas, true) ? $volver : 'modista.php';
}
