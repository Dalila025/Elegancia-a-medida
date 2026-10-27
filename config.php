<?php
declare(strict_types=1);

/**
 * Configuración general — Elegancia a Medida
 *
 * Centraliza la zona horaria y las reglas de validación para que el
 * formulario, el panel de la modista y los procesadores usen
 * exactamente los mismos límites.
 *
 * Corrige: P03 (longitudes inconsistentes) y P04 (zona horaria).
 */

// Una sola zona horaria para toda la aplicación.
date_default_timezone_set('America/Argentina/Buenos_Aires');


// =====================================================
// FORMULARIO DEL CLIENTE (tabla solicitudes_presupuesto)
// =====================================================

const NOMBRE_MAX       = 100;   // varchar(100)
const TELEFONO_MAX     = 30;    // varchar(30)
const TELEFONO_MIN_DIG = 10;    // mínimo de dígitos reales
const PRENDA_MAX       = 100;   // varchar(100)
const DESC_MIN         = 10;
const DESC_MAX         = 1000;  // límite propio: la columna es TEXT

// Rango permitido para fecha_requerida: desde hoy hasta hoy + FECHA_MAX_DIAS.
const FECHA_MAX_DIAS   = 180;   // ~6 meses

// Los tipos de servicio ya NO están acá: se leen de la tabla tipos_servicio
// (ver obtener_tipos_servicio() en funciones.php).


// =====================================================
// ESTADOS (tabla estados_solicitud)
// Los números DEBEN coincidir con los id de estados_solicitud.
// =====================================================

const ESTADO_PENDIENTE     = 1;
const ESTADO_EN_PROCESO    = 2;
const ESTADO_COMPLETADO    = 3;
const ESTADO_CANCELADO     = 4;
const ESTADO_RECHAZADO     = 5;
const ESTADO_PRESUPUESTADO = 6;
const ESTADO_NO_ACEPTADO   = 7;   // el cliente rechazó el presupuesto

// Ordenados según el recorrido de una solicitud (así se muestran en el panel).
const ESTADOS = [
    ESTADO_PENDIENTE     => 'Pendiente',
    ESTADO_PRESUPUESTADO => 'Presupuestado',
    ESTADO_NO_ACEPTADO   => 'No aceptado',
    ESTADO_EN_PROCESO    => 'En proceso',
    ESTADO_COMPLETADO    => 'Completado',
    ESTADO_CANCELADO     => 'Cancelado',
    ESTADO_RECHAZADO     => 'Rechazado',
];

// Cambios de estado con un botón del panel de la modista (actualizar_estado.php).
// estado actual => [estado nuevo => texto del botón]
// · Pasar a "Presupuestado" se hace cargando el presupuesto
//   (presupuesto.php → guardar_presupuesto.php).
// · Aceptar o rechazar el presupuesto lo hace EL CLIENTE desde su cuenta
//   (seguimiento.php → responder_presupuesto.php), no la modista.
const TRANSICIONES = [
    ESTADO_PENDIENTE     => [ESTADO_RECHAZADO  => 'Rechazar'],
    ESTADO_PRESUPUESTADO => [ESTADO_CANCELADO  => 'Cancelar'],
    ESTADO_NO_ACEPTADO   => [ESTADO_CANCELADO  => 'Cerrar'],
    ESTADO_EN_PROCESO    => [ESTADO_COMPLETADO => 'Completar',
                             ESTADO_CANCELADO  => 'Cancelar'],
    ESTADO_COMPLETADO    => [],
    ESTADO_CANCELADO     => [ESTADO_PENDIENTE  => 'Reabrir'],
    ESTADO_RECHAZADO     => [ESTADO_PENDIENTE  => 'Reabrir'],
];

// Estados en los que la modista puede cargar o corregir el presupuesto.
// Desde "No aceptado" puede mandarle al cliente una nueva propuesta.
const ESTADOS_PRESUPUESTABLES = [ESTADO_PENDIENTE, ESTADO_PRESUPUESTADO, ESTADO_NO_ACEPTADO];

// Pasar a estos estados pide confirmación en el panel.
const ESTADOS_CON_CONFIRMACION = [ESTADO_CANCELADO, ESTADO_RECHAZADO];


// =====================================================
// PRESUPUESTO DE LA MODISTA (tablas presupuestos y tarifas_plazo)
// =====================================================

const MONTO_MIN          = 1;
const MONTO_MAX          = 99999999.99;  // DECIMAL(10,2)
const PLAZO_MAX_DIAS     = 365;          // fecha de entrega: hasta 1 año
const OBSERVACIONES_MAX  = 500;          // varchar(500)

const TARIFA_NOMBRE_MAX  = 50;           // varchar(50)
const TARIFA_DIAS_MAX    = 365;
const RECARGO_MIN        = -50;          // coincide con el CHECK de la tabla
const RECARGO_MAX        = 300;


// =====================================================
// SEGUIMIENTO Y NOTIFICACIONES AL CLIENTE
// (columna codigo_seguimiento y tabla notificaciones)
// =====================================================

// Código que recibe el cliente al enviar la solicitud.
// Sin letras/números que se confunden (O/0, I/1).
const CODIGO_LARGO    = 8;
const CODIGO_ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

// Cada cuántos segundos el sitio vuelve a consultar si hay avisos nuevos.
const NOTIF_INTERVALO_SEG = 60;

// Aviso que recibe el cliente según el estado al que pasa su solicitud.
// Marcadores: {total} {entrega} {observaciones} {prenda}
const NOTIFICACIONES = [
    ESTADO_PRESUPUESTADO => ['Tu presupuesto está listo',
                             'Trabajo: {prenda}. Total: {total}. Entrega estimada: {entrega}. {observaciones}'],
    ESTADO_EN_PROCESO    => ['¡Empezamos tu trabajo!',
                             'Confirmamos tu pedido ({prenda}). Entrega estimada: {entrega}.'],
    ESTADO_COMPLETADO    => ['Tu pedido está listo',
                             'Tu pedido ({prenda}) ya está terminado. Podés coordinar el retiro con nosotros.'],
    ESTADO_CANCELADO     => ['Tu solicitud fue cancelada',
                             'La solicitud de {prenda} quedó cancelada. Si querés retomarla, comunicate con nosotros.'],
    ESTADO_NO_ACEPTADO   => ['Registramos que no aceptaste el presupuesto',
                             'Anotamos tu respuesta sobre {prenda}. Si querés, podemos prepararte otra propuesta.'],
    ESTADO_RECHAZADO     => ['No podemos tomar este trabajo',
                             'Lamentamos no poder tomar tu pedido ({prenda}) por el momento. ¡Gracias por consultarnos!'],
    ESTADO_PENDIENTE     => ['Tu solicitud volvió a revisión',
                             'La modista va a revisar nuevamente tu solicitud de {prenda}.'],
];

// Título cuando la modista corrige un presupuesto ya enviado.
const NOTIF_TITULO_PRESUPUESTO_ACTUALIZADO = 'Tu presupuesto fue actualizado';


// =====================================================
// CUENTAS DE CLIENTES (tabla clientes)
// =====================================================

const EMAIL_MAX             = 150;   // varchar(150)
const CLAVE_MIN             = 8;
const CLAVE_MAX             = 72;    // límite de password_hash() con bcrypt
const LOGIN_MAX_INTENTOS    = 5;     // claves incorrectas seguidas…
const LOGIN_BLOQUEO_MINUTOS = 15;    // …y la cuenta se bloquea este tiempo
const COMENTARIO_CLIENTE_MAX = 300;  // motivo al rechazar (varchar(300))


// =====================================================
// CATÁLOGO (tablas categorias y productos)
// =====================================================

const PRODUCTO_NOMBRE_MAX   = 100;    // varchar(100)
const PRODUCTO_DESC_MAX     = 1000;
const CATEGORIA_NOMBRE_MAX  = 60;     // varchar(60)
const CATEGORIA_DESC_MAX    = 255;    // varchar(255)
const PRECIO_MAX            = 99999999.99;

// Fotos de productos: carpeta, peso máximo y formatos aceptados.
const CARPETA_PRODUCTOS     = 'img/productos/';
const IMAGEN_MAX_BYTES      = 3 * 1024 * 1024;   // 3 MB
const IMAGEN_TIPOS          = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

// Ofertas y temporada (columnas de productos)
const DESCUENTO_MAX         = 90;     // % máximo (coincide con el CHECK de la tabla)
const TIPO_NOMBRE_MAX       = 60;     // tipos_producto.nombre varchar(60)

// Cuántos productos se muestran en cada sección del inicio
const INICIO_MAX_OFERTAS    = 8;
const INICIO_MAX_TEMPORADA  = 8;

// =====================================================
// DATOS DEL SITIO (tabla datos_sitio: historia y contacto)
// =====================================================
const SITIO_HISTORIA_MAX    = 3000;


// =====================================================
// CARRITO Y PEDIDOS (tablas items_carrito, pedidos, items_pedido)
// =====================================================

const CARRITO_DESHACER_MIN  = 5;     // minutos que se muestra el aviso "Deshacer"
const CARRITO_CANT_MAX      = 10;    // unidades máximas por prenda
const PEDIDO_COMENTARIO_MAX = 500;
const ALQUILER_MAX_DIAS     = 30;    // días máximos entre retiro y devolución
const PEDIDO_ANTICIPO_MAX   = 365;   // el retiro puede ser hasta 1 año adelante

// Estados (DEBEN coincidir con la tabla estados_pedido)
const PEDIDO_PENDIENTE  = 1;
const PEDIDO_CONFIRMADO = 2;
const PEDIDO_LISTO      = 3;
const PEDIDO_ENTREGADO  = 4;
const PEDIDO_DEVUELTO   = 5;
const PEDIDO_CANCELADO  = 6;   // lo canceló el cliente
const PEDIDO_RECHAZADO  = 7;   // la modista no lo toma

const ESTADOS_PEDIDO = [
    PEDIDO_PENDIENTE  => 'Pendiente',
    PEDIDO_CONFIRMADO => 'Confirmado',
    PEDIDO_LISTO      => 'Listo para retirar',
    PEDIDO_ENTREGADO  => 'Entregado',
    PEDIDO_DEVUELTO   => 'Devuelto',
    PEDIDO_CANCELADO  => 'Cancelado',
    PEDIDO_RECHAZADO  => 'Rechazado',
];

// Botones de la modista: estado actual => [estado nuevo => texto del botón]
// ("Devuelto" solo aparece si el pedido tiene prendas en alquiler).
const TRANSICIONES_PEDIDO = [
    PEDIDO_PENDIENTE  => [PEDIDO_CONFIRMADO => 'Confirmar', PEDIDO_RECHAZADO => 'Rechazar'],
    PEDIDO_CONFIRMADO => [PEDIDO_LISTO => 'Listo para retirar', PEDIDO_RECHAZADO => 'Rechazar'],
    PEDIDO_LISTO      => [PEDIDO_ENTREGADO => 'Entregado'],
    PEDIDO_ENTREGADO  => [PEDIDO_DEVUELTO => 'Devuelto'],
];

// Aviso para el cliente según el estado del pedido. Marcador: {codigo}
const NOTIFICACIONES_PEDIDO = [
    PEDIDO_CONFIRMADO => ['Confirmamos tu pedido', 'Tu pedido {codigo} fue confirmado. Te vamos a contactar para coordinar el pago y el retiro.'],
    PEDIDO_LISTO      => ['Tu pedido está listo', 'Ya podés retirar tu pedido {codigo}.'],
    PEDIDO_ENTREGADO  => ['Pedido entregado', 'Registramos la entrega de tu pedido {codigo}. ¡Gracias!'],
    PEDIDO_DEVUELTO   => ['Recibimos la devolución', 'Registramos la devolución de las prendas del pedido {codigo}. ¡Gracias!'],
    PEDIDO_RECHAZADO  => ['No podemos tomar tu pedido', 'Lamentamos no poder tomar el pedido {codigo}.'],
];


// =====================================================
// LOGO Y PORTADA (datos_sitio.logo y tabla banners)
// =====================================================

const CARPETA_SITIO        = 'img/sitio/';
const CARPETA_BANNERS      = 'img/banners/';
const BANNER_TITULO_MAX    = 60;
const BANNER_SUBTITULO_MAX = 160;
const BANNER_BOTON_MAX     = 30;
