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
                             'Confirmamos tu pedido ({prenda}). Entrega estimada: {entrega}. Entrá a tu solicitud y elegí qué día acercarte al taller; ahí también podés dejar la seña si querés.'],
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
const INICIO_MAX_NUEVOS     = 8;     // «Disfraces nuevos» del inicio (últimos cargados para alquilar)

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
    PEDIDO_CONFIRMADO => ['Confirmamos tu pedido', 'Tu pedido {codigo} fue confirmado. Para reservarlo, pagá la seña de {sena} desde «Mis pedidos».'],
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


// =====================================================
// GUÍA DE TALLES Y MEDIDAS (tablas talles_producto y medidas_clientes)
// =====================================================
//
// La modista carga, para cada prenda, sus talles con las medidas del cuerpo
// (en cm). La clienta carga sus medidas y el sitio le recomienda el talle.

// Medidas de la guía de talles: columna => [nombre, cómo se mide]
const MEDIDAS_TALLE = [
    'busto'    => ['Busto',    'Rodeá la parte más llena del busto, sin apretar la cinta.'],
    'cintura'  => ['Cintura',  'La parte más angosta de la cintura, arriba del ombligo.'],
    'cadera'   => ['Cadera',   'La parte más ancha de la cadera, con los pies juntos.'],
    'hombros'  => ['Hombros',  'De la punta de un hombro a la otra, por la espalda.'],
    'estatura' => ['Estatura', 'Descalza, de la cabeza a los pies.'],
    'largo'    => ['Largo de la prenda', 'Del hombro hasta el ruedo de la prenda.'],
];

// Medidas de la ficha de la clienta: columna => [nombre, cómo se mide]
const MEDIDAS_CLIENTE = [
    'busto'       => MEDIDAS_TALLE['busto'],
    'cintura'     => MEDIDAS_TALLE['cintura'],
    'cadera'      => MEDIDAS_TALLE['cadera'],
    'hombros'     => MEDIDAS_TALLE['hombros'],
    'estatura'    => MEDIDAS_TALLE['estatura'],
    'largo_brazo' => ['Largo de brazo', 'Desde la punta del hombro hasta la muñeca, con el brazo relajado.'],
];

// Medidas que se comparan para recomendar el talle (están en las dos tablas)
const MEDIDAS_COMPARABLES = ['busto', 'cintura', 'cadera', 'hombros', 'estatura'];

const MEDIDA_MIN_CM       = 20;    // valores razonables, en centímetros
const MEDIDA_MAX_CM       = 250;
const TALLES_MAX          = 8;     // talles por prenda
const TALLE_NOMBRE_MAX    = 10;    // talles_producto.talle varchar(10)
const TALLE_A_MEDIDA      = 'A medida';
const TALLE_HOLGURA_CM    = 3;     // si la clienta mide más que el talle por más de esto, le puede quedar justo
const TALLE_SOBRA_CM      = 6;     // si mide menos por más de esto, le puede quedar grande
const TALLE_ESTATURA_CM   = 5;     // diferencia de estatura que avisa "corto" o "largo"
const MEDIDAS_NOTAS_MAX   = 255;

// =====================================================
// SEÑA Y MERCADO PAGO (tabla pagos)
// =====================================================
//
// Cuándo se cobra la seña:
//   - Pedido del carrito: cuando la modista lo confirma (Confirmado o Listo para retirar).
//   - Presupuesto de confección/arreglo: cuando el cliente lo acepta (En proceso).
// La modista también puede registrar una seña cobrada en efectivo o transferencia.

const SENA_PORCENTAJE = 50;   // % del total que se paga como seña

// Estados en los que el cliente ve el botón «Pagar seña»
const PEDIDO_ESTADOS_CON_SENA    = [PEDIDO_CONFIRMADO, PEDIDO_LISTO];
const SOLICITUD_ESTADOS_CON_SENA = [ESTADO_EN_PROCESO];

const PAGO_METODOS = [
    'mercadopago'   => 'Mercado Pago',
    'simulado'      => 'Pago simulado',
    'efectivo'      => 'Efectivo',
    'transferencia' => 'Transferencia',
];
const PAGO_OBSERVACIONES_MAX = 255;

// Credenciales de Mercado Pago: van en mercadopago_config.php, que NO se sube
// a GitHub (está en .gitignore). Si el archivo no existe, se usa el modo simulado.
if (is_file(__DIR__ . '/mercadopago_config.php')) {
    require_once __DIR__ . '/mercadopago_config.php';
}
// Valores por defecto de lo que no se haya definido en mercadopago_config.php
defined('MP_MODO')         || define('MP_MODO', 'simulado');   // 'simulado' o 'mercadopago'
defined('MP_ACCESS_TOKEN') || define('MP_ACCESS_TOKEN', '');
defined('MP_USAR_SANDBOX') || define('MP_USAR_SANDBOX', false);  // true solo con tokens viejos TEST-
defined('MP_API_URL')      || define('MP_API_URL', 'https://api.mercadopago.com');
defined('SITIO_URL')       || define('SITIO_URL', '');          // vacío = se detecta solo
defined('MP_CA_BUNDLE')    || define('MP_CA_BUNDLE', '');       // vacío = se detecta solo


// =====================================================
// GASTOS DE LA MODISTA (tablas gastos y categorias_gasto)
// =====================================================

const GASTO_DESCRIPCION_MAX = 150;
const GASTO_CANTIDAD_MAX    = 30;
const GASTO_PROVEEDOR_MAX   = 100;
const GASTO_CATEGORIA_MAX   = 60;
const GASTO_MEDIOS = [
    'efectivo'      => 'Efectivo',
    'transferencia' => 'Transferencia',
    'tarjeta'       => 'Tarjeta',
    'mercadopago'   => 'Mercado Pago',
    'otro'          => 'Otro',
];


// =====================================================
// LISTA DE PRECIOS DE ARREGLOS (tabla arreglos)
// =====================================================

const ARREGLO_NOMBRE_MAX      = 80;
const ARREGLO_DESCRIPCION_MAX = 200;
const ARREGLO_DEMORA_MAX      = 60;     // días
const SERVICIO_ARREGLOS       = 2;      // id de «Arreglos» en tipos_servicio


// =====================================================
// TALLER: ETAPAS Y TURNOS DE PRUEBA (etapas_taller, avances_taller, turnos_prueba)
// =====================================================

// Etapas (DEBEN coincidir con la tabla etapas_taller)
const ETAPA_RECIBIDO     = 1;
const ETAPA_CORTE        = 2;
const ETAPA_PRIMERA      = 3;
const ETAPA_CONFECCION   = 4;
const ETAPA_PRUEBA_FINAL = 5;   // «Lista para retirar»: la clienta elige cuándo probársela y llevársela
const ETAPA_TERMINADO    = 6;   // al llegar acá la solicitud pasa a «Completado»

// Recorrido habitual (botón «Siguiente»). Un arreglo no tiene corte ni primera prueba.
const ETAPAS_RECORRIDO_ARREGLO = [ETAPA_RECIBIDO, ETAPA_CONFECCION, ETAPA_PRUEBA_FINAL];
const ETAPAS_RECORRIDO         = [ETAPA_RECIBIDO, ETAPA_CORTE, ETAPA_PRIMERA, ETAPA_CONFECCION, ETAPA_PRUEBA_FINAL];

const TALLER_NOTA_MAX       = 255;
const TALLER_AVISO_DIAS     = 3;     // entrega en menos de estos días → se marca en rojo
const TURNO_HORA_MIN        = '08:00';
const TURNO_HORA_MAX        = '21:00';
const TURNO_ANTICIPO_DIAS   = 120;   // un turno se puede dar hasta 4 meses adelante

// Para qué es cada turno (columna turnos_prueba.motivo).
// {cuando} = «jueves 05/11 a las 17:00», {prenda} = tipo de prenda.
const TURNO_MOTIVOS = [
    'traer'   => ['nombre'  => 'Traer la prenda',
                  'cliente' => 'Traé tu prenda',
                  'aviso'   => 'Acordamos cuándo traés tu prenda',
                  'texto'   => 'Te esperamos el {cuando} para que traigas tu {prenda}. Ese día te la probás para marcar el arreglo.'],
    'prueba'  => ['nombre'  => 'Prueba',
                  'cliente' => 'Tu turno de prueba',
                  'aviso'   => 'Tenés un turno de prueba',
                  'texto'   => 'Te esperamos el {cuando} para probarte tu {prenda}.'],
    'retirar' => ['nombre'  => 'Retirar el trabajo',
                  'cliente' => 'Retirá tu trabajo',
                  'aviso'   => 'Tu turno para retirar',
                  'texto'   => 'Te esperamos el {cuando} para retirar tu {prenda}: te la probás y, si te queda bien, te la llevás.'],
];
const TURNO_TEXTO_MEDIDAS = 'Aprovechamos para tomarte las medidas.';

// Horarios en que la modista recibe prendas (horarios_recepcion, dias_sin_atencion).
// Cuando la clienta acepta el presupuesto, elige uno para acercarse al taller.
const DIAS_SEMANA          = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'];
const HORARIO_PASO_MIN     = 30;   // la grilla de horarios va de media hora en media hora
const RESERVA_DIAS         = 14;   // la clienta ve los horarios libres de las próximas 2 semanas
const RESERVA_ANTICIPO_MIN = 120;  // no puede elegir un horario que empieza en menos de 2 horas
const DIA_SIN_ATENCION_MOTIVO_MAX = 100;
const RECORDATORIO_DIAS     = 2;    // arriba en el sitio: «No te olvides…» de los turnos de hoy a 2 días

