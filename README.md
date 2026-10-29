# Elegancia a Medida

Sistema web para una **modista**: confección a medida, arreglos, alquiler y venta de ropa de fiesta. La idea central: en la ropa de fiesta **el talle no depende de la edad sino de las medidas del cuerpo**. Cada prenda tiene su guía de talles en centímetros, la clienta carga sus medidas y el sitio le recomienda el talle (o se la hacen a medida). Cualquiera puede ver el inicio y la **tienda**; con una **cuenta de cliente** se pueden **comprar o alquilar** prendas (carrito → pedido) y **pedir presupuestos** de confección o arreglos. La **modista** maneja todo desde su panel.

Proyecto de Prácticas Profesionalizantes II – Grupo 2 · Ciclo 2026

## Qué hace

### Menú del sitio (en todas las páginas)
- A la izquierda, el **logo** de la modista (si no subió ninguno, se ve el nombre en texto).
- En el centro: **Shop**, **Alquiler**, **Confección** y **Arreglos**. Al hacer clic se despliega una barra con todas las opciones:
  - **Shop** y **Alquiler**: categorías, tipos de prenda, ofertas y temporada → llevan a la tienda filtrada;
  - **Confección** y **Arreglos**: llevan al **formulario de solicitud de presupuesto** (el aprobado en TP11), con el tipo de servicio y la prenda ya elegidos.
- A la derecha, tres íconos: **buscador**, **cuenta** (iniciar sesión, o Mis pedidos / Mis solicitudes / Salir) y **carrito** (con la cantidad).

### Guía de talles y medidas (la idea principal)
- La modista carga, para cada prenda, una **guía de talles**: cada talle (S, M, L o 38, 40…) con busto, cintura, cadera, hombros, estatura y largo en cm.
- La clienta carga **sus medidas** en «Mis medidas» (o se las toma la modista en el taller).
- En cada prenda el sitio le dice **«Según tus medidas, tu talle es M»** y le avisa si algo le puede quedar justo, holgado, corto o largo.
- Para agregar al carrito elige el talle (viene marcado el recomendado) o **«A medida»** (solo en venta): sus medidas se copian en el pedido para que la modista confeccione.
- La modista ve las medidas de cada clienta y su historial en **Panel → Clientas**.

### Visitante (sin cuenta)
- **Inicio** (`index.php`): pantallas grandes que van pasando; **los servicios de la modista** (confección a medida, arreglos, alquiler, venta) y **cómo trabaja**; una **cinta en arco** con los disfraces para chicos en alquiler que pasan solos (todas las prendas «Se alquila» con foto; textos en `index.php`); recuadros; **Ofertas en alquiler** (solo lo que se alquila con descuento) y **Disfraces nuevos** (lo último que la modista cargó para alquilar); el botón «Ver toda la tienda»; **Nuestra historia**, **Contacto** y el pie.
- **Arreglos** (`precios_arreglos.php`): lista de precios; cada arreglo lleva al formulario de solicitud con el arreglo ya escrito.
- **Alquiler** (`tienda.php?modalidad=alquiler`): arriba, la cinta en arco con todas las prendas en alquiler (aparece sola cada prenda marcada «Se alquila» con foto).
- **Tienda** (`tienda.php`): todas las prendas, con filtros (venta/alquiler, categoría, tipo, ofertas, temporada), buscador y orden.
- **Prenda** (`prenda.php`): foto, descripción, precios de venta y alquiler, y relacionados.
- Para agregar al carrito o pedir un presupuesto le pide iniciar sesión y después lo devuelve a donde estaba.

### Cliente (con cuenta)
1. Se registra (`registro.php`) o ingresa (`login.php`).
2. **Carrito** (solo prendas en venta o alquiler): al agregar algo aparece arriba un aviso **«Deshacer»** que dura **5 minutos**, por si fue sin querer. Los productos se ven entrando al carrito (`carrito.php`), donde cambia cantidades y, si hay alquiler, elige las fechas de retiro y devolución.
3. Al confirmar se crea un **pedido** (todavía no paga nada). En **Mis pedidos** ve el estado y la nota de la modista, y puede cancelarlo mientras está pendiente.
4. Cuando la modista **confirma** el pedido, aparece el botón para pagar la **seña del 50 %** con **Mercado Pago**. El resto se paga al retirar.
5. **Pide un presupuesto** (`solicitud.php`) para confección o arreglos. El formulario es el mismo que se validó en TP11.
6. En su solicitud aceptada ve **el avance en el taller** (etapa por etapa) y su **próximo turno** (para probarse, para traer la prenda de un arreglo o para retirar el trabajo). Apenas acepta el presupuesto, **elige uno de los horarios libres de la modista** para acercarse: lleva la prenda, se la prueba, le toman las medidas y, si quiere, deja la seña (también la puede pagar antes con Mercado Pago). Puede cambiar el horario mientras no haya llevado la prenda. Si no hay horarios libres, tiene un botón para escribirle por WhatsApp. Cuando la modista pasa el trabajo a **«Lista para retirar»**, la clienta elige igual un horario para ir a probársela y llevársela (con el saldo a pagar). Arriba de todo el sitio le aparece **«No te olvides de retirar tu prenda mañana a las…»** (turnos de los próximos 2 días y prendas listas sin turno). En cada paso tiene un enlace de **WhatsApp con el mensaje ya armado** (su nombre, la prenda, el código y el turno).
7. En **Mis solicitudes** ve sus solicitudes; cuando la modista responde le llega un aviso y puede **aceptar** o **rechazar** el presupuesto. Si lo acepta, también paga la **seña del 50 %**.
8. En **Estado de cuenta** (`mi_cuenta.php`) ve cuánto le falta pagar de cada trabajo y todos sus pagos.

### Modista (panel con login)
1. Ingresa por `panel_login.php`. En el primer ingreso el sistema le pide cambiar la contraseña provisoria.
2. **Solicitudes** (`modista.php`):
   - las filtra y busca;
   - arma el presupuesto (fecha de entrega + monto, con recargo automático según el plazo);
   - las rechaza, las cancela o las marca como completadas;
   - ve si el cliente aceptó o rechazó y el motivo.

   La modista **no** puede aceptar ni rechazar en nombre del cliente.
3. **Taller** (`taller.php`): tablero con los trabajos aceptados por columnas (Por empezar, Recibido, Corte, Primera prueba, Confección, Lista para retirar). Los pasa de etapa con una nota para la clienta, les da **turnos** (prueba, traer la prenda o retirar el trabajo; los de traer la prenda normalmente los elige la clienta de los **Horarios para recibir prendas**, que la modista marca en una grilla semanal, más los días que no atiende) y ve la agenda de próximos turnos, cada uno con **«Recordarle por WhatsApp»** (mensaje armado con día, hora y saldo). Cuando un trabajo está «Lista para retirar» y la clienta no eligió turno, la tarjeta lo marca y ofrece avisarle por WhatsApp. En los arreglos avisa «Falta acordar cuándo trae la prenda» y tiene un enlace de WhatsApp a la clienta; al marcar «Trajo la prenda», el trabajo pasa solo a «Recibido». Cualquier turno puede aprovecharse para **tomarle las medidas** (al cerrarlo, lleva a su ficha para cargarlas). En el turno para **retirar**, la clienta se lo prueba: «Quedó bien: se lo llevó» deja el trabajo Terminado y la solicitud Completada; «Hay que retocar» lo vuelve a Confección con una nota para la clienta. Al pasar a «Terminado», la solicitud queda Completada.
4. **Clientas** (`clientes.php`, `cliente.php`): ficha de cada clienta con sus medidas, historial, pedidos, solicitudes y **cuenta corriente** (trabajos confirmados, pagos, saldo y movimientos). Registra lo que la clienta paga en efectivo o transferencia, y las devoluciones de trabajos rechazados. En la lista ve el saldo de cada una, el total que le deben y el filtro «Con saldo pendiente».
5. **Pedidos** (`pedidos.php`): ve los pedidos del carrito, les deja una nota y los pasa de estado (Confirmado → Listo para retirar → Entregado → Devuelto, este último solo en alquileres; o Rechazado). Cada cambio le avisa al cliente. Si el pedido tiene precios «a consultar», al confirmarlo pone el **total final** (con ese total se calcula la seña).
6. **Señas** (`pagos.php`): ve las señas por cobrar y los pagos recibidos, y registra las que le pagaron en **efectivo o transferencia**.
7. **Gastos** (`gastos.php`): anota lo que gasta (tela, elástico, luz…) con rubro, monto, medio de pago y dónde lo compró. Ve el total del mes comparado con el anterior, cuánto va a cada rubro, y descarga el mes para Excel. Cada gasto se puede asignar a un **trabajo** (la tela del vestido de Sofía…): en **Costos del trabajo** (`trabajo.php`, desde el Taller, la ficha de la clienta o la lista de gastos) ve lo que le costó hacer esa prenda, el precio y la **ganancia**, y en Gastos un resumen de los trabajos del mes.
8. **Precios de arreglos** (`lista_arreglos.php`): su lista de arreglos con precio («desde» o «a consultar») y demora. Se publica en el sitio y le aparece al presupuestar un arreglo.
9. **Catálogo** (`productos.php`, `producto.php`): agrega, edita, oculta o elimina prendas con foto. En cada prenda define:
   - categoría y tipo de prenda;
   - si se vende y/o se alquila, con su precio (o "a consultar");
   - la **guía de talles** (medidas del cuerpo en cm por talle);
   - el **descuento** y hasta qué fecha vale;
   - si es **de temporada**.
10. **Categorías** (`categorias.php`): categorías y tipos de prenda (arman el menú y los filtros).
11. **Portada** (`banners.php`): las pantallas grandes del inicio y los recuadros de abajo (título, texto, botón, imagen, orden, ocultar).
12. **Tarifas** (`tarifas.php`): define el recargo por entrega rápida.
13. **Datos del sitio** (`sitio.php`): sube su **logo** y edita su historia, el título de la temporada y los datos de contacto (WhatsApp, teléfono, email, Instagram, Facebook, dirección y horarios).

### Recorrido de un pedido (carrito)

```
Pendiente ──► Confirmado ──► Listo para retirar ──► Entregado ──► Devuelto (solo alquiler)
    │             │
    │             └──► Rechazado (modista)
    ├──► Rechazado (modista)
    └──► Cancelado (cliente)
```

### Pago de la seña con Mercado Pago

```
Modista confirma ──► Cliente toca «Pagar seña» ──► Mercado Pago ──► vuelve a pago_resultado.php
 (o el cliente                                        (paga)           │
  acepta el presupuesto)                                               ▼
                                     el sitio le pregunta a Mercado Pago por ese pago
                                     y solo lo aprueba si coinciden referencia, monto y moneda
```

- **Modo simulado** (sin `mercadopago_config.php`): el botón abre una pantalla de pago de prueba del propio sitio. Sirve para la demo sin internet y no cobra nada.
- **Modo Mercado Pago**: copiar `mercadopago_config.example.php` como `mercadopago_config.php`, poner `MP_MODO = 'mercadopago'` y el **Access Token** (el de la cuenta de prueba vendedora para probar, o el de producción para cobrar de verdad). Ese archivo no se sube a GitHub.
- Si el cliente cierra la ventana antes de volver, puede tocar **«Ya pagué, verificar»**. Con el sitio publicado, además, Mercado Pago avisa solo (`pago_webhook.php`).

### Recorrido de una solicitud

```
Pendiente ──(modista presupuesta)──► Presupuestado ──(cliente acepta)──► En proceso ──► Completado
    │                                     │
    │                                     └──(cliente rechaza)──► No aceptado ──(la modista manda otra propuesta)──► Presupuestado
    └──(modista rechaza)──► Rechazado
```

## Tecnologías

- HTML, CSS y JavaScript
- PHP 8 con PDO (`pdo_mysql`), consultas preparadas y `password_hash()`
- MySQL / MariaDB (base `elegancia_a_medida`)
- XAMPP (Apache + MySQL) como entorno local

## Estructura

| Archivo | Función |
|---|---|
| **Sitio público y clientes** | |
| `index.php` | Inicio: carrusel, mosaico, ofertas, temporada, novedades, historia y contacto |
| `tienda.php` | Todas las prendas con filtros, buscador y orden (en Alquiler, con la cinta en arco arriba) |
| `cinta_arco.php` | Cinta de fotos en arco con las prendas en alquiler (se mueve con `js/sitio.js`) |
| `mis_medidas.php` | La clienta carga sus medidas (queda el historial) |
| `precios_arreglos.php` | Lista de precios de arreglos (sitio) |
| `mi_cuenta.php` | Estado de cuenta de la clienta: trabajos, pagos, saldo y movimientos |
| `prenda.php` | Detalle de una prenda, guía de talles, talle recomendado y elección de talle |
| `tarjeta_producto.php` | Tarjeta de producto que usan inicio, tienda y prenda |
| `carrito_accion.php` | Agregar, sumar, restar, quitar y deshacer en el carrito |
| `carrito.php` → `confirmar_pedido.php` | Carrito y confirmación del pedido |
| `mis_pedidos.php` / `cancelar_pedido.php` | Pedidos del cliente |
| `registro.php` / `login.php` / `logout.php` | Cuenta del cliente |
| `solicitud.php` → `procesar_presupuesto.php` | Formulario de solicitud (solo con cuenta) y su validación |
| `mis_solicitudes.php` | Lista de solicitudes del cliente |
| `seguimiento.php` → `responder_presupuesto.php` | Detalle de una solicitud; aceptar o rechazar el presupuesto |
| `pagar_sena.php` → Mercado Pago → `pago_resultado.php` | Pago de la seña; la vuelta se verifica con la API |
| `verificar_pago.php`, `pago_webhook.php` | «Ya pagué, verificar» y aviso automático de Mercado Pago |
| `pago_simulado.php` | Pantalla de pago de prueba (solo en modo simulado) |
| `notificaciones.php` + `js/notificaciones.js` | Avisos al cliente (solicitudes y pedidos) |
| `encabezado_cliente.php`, `pie_cliente.php` | Menú (logo, Shop/Alquiler/Confección/Arreglos, íconos, aviso Deshacer) y pie |
| `css/sitio.css`, `css/cliente.css`, `js/sitio.js` | Estilos del sitio; menú desplegable, carrusel y cuenta regresiva de Deshacer |
| **Panel de la modista** | |
| `panel_login.php` / `panel_logout.php` / `panel_clave.php` | Ingreso, salida y cambio de contraseña |
| `modista.php` → `actualizar_estado.php` | Solicitudes y cambios de estado |
| `taller.php` → `guardar_taller.php` | Taller: etapas de cada trabajo y turnos |
| `trabajo.php` → `guardar_gasto.php` | Costos de un trabajo: gastos de esa prenda, precio y ganancia |
| `horarios.php` → `guardar_horarios.php` | Horarios de la semana para recibir prendas y días sin atención |
| `reservar_turno.php` | La clienta elige su horario para acercarse (desde `seguimiento.php`) |
| `lista_arreglos.php` → `guardar_arreglo.php` | Lista de precios de arreglos (panel) |
| `clientes.php`, `cliente.php` → `registrar_cobro.php` | Clientas: ficha con medidas, pedidos, solicitudes y cuenta corriente (registrar pagos y devoluciones) |
| `pedidos.php` → `actualizar_pedido.php` | Pedidos del carrito y cambios de estado |
| `gastos.php` → `guardar_gasto.php`, `gastos_csv.php` | Gastos de la modista: carga, resumen del mes y por rubro, rubros, descarga para Excel |
| `pagos.php` → `registrar_pago.php` | Señas por cobrar, pagos recibidos y cobros en efectivo/transferencia |
| `banners.php`, `banner.php` → `guardar_banner.php` | Portada: carrusel y mosaico del inicio |
| `presupuesto.php` → `guardar_presupuesto.php` | Armar o corregir un presupuesto |
| `productos.php`, `producto.php` → `guardar_producto.php` | Catálogo: listado, alta/edición con foto, ocultar, eliminar |
| `categorias.php` → `guardar_categoria.php` | Categorías y tipos de prenda |
| `sitio.php` → `guardar_sitio.php` | Logo, historia de la modista, título de temporada y datos de contacto |
| `tarifas.php` → `guardar_tarifa.php` | Recargo según el plazo de entrega |
| `encabezado_modista.php`, `css/modista.css`, `css/panel.css` | Menú y estilos del panel |
| **Comunes** | |
| `config.php` | Zona horaria, límites, estados, transiciones, textos de avisos |
| `funciones.php` | Funciones compartidas (sesiones, login, validaciones, fotos, cálculos) |
| `funciones_medidas.php` | Guía de talles, medidas de las clientas y recomendación de talle |
| `funciones_taller.php` | Etapas del taller, turnos de prueba y avisos |
| `funciones_arreglos.php` | Lista de precios de arreglos |
| `funciones_cuenta.php` | Cuenta corriente: trabajos, saldos, movimientos, cobros y devoluciones |
| `funciones_pagos.php` | Cálculo de la seña y conexión con la API de Mercado Pago (sin librerías externas) |
| `mercadopago_config.example.php` | Plantilla de las credenciales de Mercado Pago (sin credenciales reales) |
| `conexion.example.php` | Plantilla de la conexión PDO (sin credenciales) |
| `img/productos/`, `img/banners/`, `img/sitio/` | Fotos del catálogo, de la portada y el logo (con `.htaccess` que impide ejecutar código ahí). Las `ejemplo_*.jpg` son fotos de muestra |
| `elegancia_a_medida.sql` | Base de datos completa con datos iniciales y de prueba |
| `BASE_DE_DATOS.md` | Diccionario de la base: tablas, claves, relaciones y qué archivo usa cada una |

## Requisitos

- XAMPP con PHP 8 (o Apache + PHP 8 + MySQL 5.7+/MariaDB 10.2+), con las extensiones `fileinfo` y `curl` activas (vienen activas en XAMPP)
- Git

## Instalación y ejecución

1. Iniciar Apache y MySQL desde el Panel de Control de XAMPP.
2. Clonar el repositorio dentro de `htdocs`:
   ```
   cd C:\xampp\htdocs
   git clone https://github.com/Dalila025/Elegancia-a-medida.git elegancia_a_medida
   ```
3. Abrir http://localhost/phpmyadmin, ir a **Importar** y cargar `elegancia_a_medida.sql`. El script crea la base y todas las tablas. **Si ya existían, las borra.**
4. Copiar `conexion.example.php` como `conexion.php` y completar usuario y clave (en XAMPP: `root` y clave vacía).
5. (Opcional) Para cobrar con Mercado Pago: copiar `mercadopago_config.example.php` como `mercadopago_config.php` y completar el Access Token. Sin ese archivo, la seña se paga en **modo simulado**.
6. Abrir:
   - sitio: http://localhost/elegancia_a_medida/
   - panel: http://localhost/elegancia_a_medida/panel_login.php

### Usuarios de prueba

| Quién | Email | Contraseña |
|---|---|---|
| Modista (panel) | `modista@elegancia.com` | `Modista2026` (se cambia en el primer ingreso) |
| Clienta | `maria@demo.com` | `demo1234` (tiene medidas cargadas: le corresponde talle M) |
| Clienta | `sofia@demo.com` | `demo1234` (tiene un presupuesto para responder) |

No usa librerías externas (ni CDN): el sitio funciona sin internet, solo con XAMPP.

## Prueba rápida

1. Sin iniciar sesión, abrir el inicio: pasan las pantallas Bienvenidos / Venta / Alquiler / Accesorios. Abrir **Shop** y **Arreglos** del menú. Probar el buscador (lupa).
2. Entrar a una prenda y tocar **Comprar**: pide iniciar sesión. Ingresar con `sofia@demo.com`.
3. Abrir **Vestido largo bordó**: como Sofía no tiene medidas, invita a cargarlas. Cargarlas en **Mis medidas** (por ejemplo busto 88, cintura 70, cadera 94) y volver: dice «tu talle es **M**».
4. Agregar una prenda: arriba aparece **Deshacer** con la cuenta regresiva. Tocarlo: el carrito vuelve a como estaba.
5. Agregar una prenda en **alquiler**, entrar al carrito, elegir fechas y **confirmar**: aparece en Mis pedidos como Pendiente.
6. En otra ventana (o incógnito), entrar al panel → **Pedidos** → Confirmar con una nota. En la ventana del cliente llega el aviso.
7. Volver a **Mis pedidos**: aparece la **seña del 50 %**. Tocar **Pagar seña** → **Aprobar pago** (modo simulado). En el panel → **Señas** aparece en pagos recibidos.
8. Menú **Confección** → «Pedir presupuesto»: abre el formulario de solicitud con el servicio elegido. Enviarlo, presupuestarlo desde el panel y aceptarlo desde la cuenta del cliente.
9. Panel → **Datos del sitio**: subir un logo y ver que aparece arriba a la izquierda. Panel → **Portada**: cambiar el orden del carrusel.

## Seguridad

- Contraseñas guardadas con `password_hash()`. Tras 5 intentos fallidos, la cuenta (de cliente o de la modista) queda bloqueada 15 minutos.
- Tokens contra envíos falsificados (CSRF) en todos los formularios, y token de un solo uso en la solicitud (sin envíos duplicados).
- Cada cliente ve y responde **solo** sus solicitudes.
- Carrito y pedidos: cada cliente solo ve y toca los suyos; al confirmar se vuelven a validar precios y disponibilidad dentro de una transacción.
- Pagos: el sitio nunca cree lo que dice la dirección al volver de Mercado Pago; consulta el pago con el Access Token y verifica referencia, monto y moneda. Un mismo pago no sirve para dos señas, y el Access Token no se sube a GitHub.
- Enlaces de la portada: solo páginas del sitio o `https://`.
- Fotos: se verifica el contenido real del archivo (JPG/PNG/WEBP, máximo 3 MB) y se guardan con un nombre al azar.
- Toda salida se escapa con `e()` (contra XSS) y todas las consultas son preparadas (contra inyección SQL).

## Limitaciones y pendientes

- Los avisos le llegan al cliente mientras tiene el sitio abierto. Para avisarle con el sitio cerrado hace falta correo, WhatsApp o notificaciones push.
- No hay recuperación de contraseña por email.
- El panel tiene un solo tipo de usuario (la modista).
- En línea solo se cobra la seña; el saldo se paga al retirar y la modista lo registra en la cuenta corriente. Las devoluciones de dinero se hacen desde la cuenta de Mercado Pago.
- En `localhost`, Mercado Pago no puede volver solo al sitio ni avisar por webhook: el cliente toca «Volver al sitio» o «Ya pagué, verificar». Con el sitio publicado funciona automático.
- No se controla stock ni que dos alquileres de la misma prenda se superpongan en fechas: lo decide la modista al confirmar.
- Contacto tiene su tabla creada pero todavía no tiene código.
- Las fotos de ejemplo vienen de la plantilla TailStore (licencia MIT); hay que reemplazarlas por fotos propias.

## Equipo

- Spindola Dalila
- Galeano Kevin
- Caballero Facundo
