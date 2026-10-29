<?php
/*
 * CONFIGURACIÓN DE MERCADO PAGO — PLANTILLA (sin credenciales reales)
 *
 *   1. Copiar este archivo como "mercadopago_config.php" (misma carpeta).
 *   2. Completar los datos de abajo.
 *   3. NO subir mercadopago_config.php a GitHub (está en .gitignore).
 *
 * Si mercadopago_config.php no existe, el sitio usa el MODO SIMULADO:
 * el botón «Pagar seña» abre una pantalla de prueba del propio sitio
 * (sirve para la demo sin internet y sin cuenta de Mercado Pago).
 */

// 'simulado'    → pantalla de pago de prueba del propio sitio (no usa internet).
// 'mercadopago' → Checkout Pro de Mercado Pago (necesita internet y el Access Token).
const MP_MODO = 'mercadopago';

// Access Token. Se saca de: Mercado Pago Developers → Tus integraciones →
// tu aplicación → Cuentas de prueba → cuenta VENDEDORA → Credenciales.
//   - Para PROBAR: el Access Token de la cuenta de prueba vendedora (empieza con APP_USR-).
//   - Para cobrar DE VERDAD: el Access Token de producción de la cuenta real.
const MP_ACCESS_TOKEN = 'APP_USR-0000000000000000-000000-00000000000000000000000000000000-000000000';

// Dejar en false. Solo poner true si tenés un token viejo que empieza con TEST-
// y Mercado Pago muestra un error al abrir el pago.
const MP_USAR_SANDBOX = false;

// Dirección pública del sitio, sin barra al final. Vacío = se detecta sola.
// Ej. en InfinityFree: 'https://eleganciaamedida.infinityfreeapp.com'
const SITIO_URL = '';

// Solo si al pagar aparece "SSL certificate problem" en XAMPP:
// ruta al archivo de certificados. Ej: 'C:/xampp/apache/bin/curl-ca-bundle.crt'
const MP_CA_BUNDLE = '';
