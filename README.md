# Elegancia a Medida

Sistema web para un emprendimiento de confección y modista, con estilo de tienda online. Cualquiera puede ver el inicio y la **tienda**; con una **cuenta de cliente** se pueden **comprar o alquilar** prendas (carrito → pedido) y **pedir presupuestos** de confección o arreglos. La **modista** maneja todo desde su panel.

Proyecto de Prácticas Profesionalizantes II – Grupo 2 · Ciclo 2026

## Qué hace

### Menú del sitio (en todas las páginas)
- A la izquierda, el **logo** de la modista (si no subió ninguno, se ve el nombre en texto).
- En el centro: **Shop**, **Alquiler**, **Confección** y **Arreglos**. Al hacer clic se despliega una barra con todas las opciones:
  - **Shop** y **Alquiler**: categorías, tipos de prenda, ofertas y temporada → llevan a la tienda filtrada;
  - **Confección** y **Arreglos**: llevan al **formulario de solicitud de presupuesto** (el aprobado en TP11), con el tipo de servicio y la prenda ya elegidos.
- A la derecha, tres íconos: **buscador**, **cuenta** (iniciar sesión, o Mis pedidos / Mis solicitudes / Salir) y **carrito** (con la cantidad).

### Visitante (sin cuenta)
- **Inicio** (`index.php`): pantallas grandes que van pasando (Bienvenidos, Venta, Alquiler, Accesorios), recuadros debajo, y filas de **Ofertas**, **Temporada** y **Novedades**; después **Nuestra historia**, **Contacto** y el pie.
- **Tienda** (`tienda.php`): todas las prendas, con filtros (venta/alquiler, categoría, tipo, ofertas, temporada), buscador y orden.
- **Prenda** (`prenda.php`): foto, descripción, precios de venta y alquiler, y relacionados.
- Para agregar al carrito o pedir un presupuesto le pide iniciar sesión y después lo devuelve a donde estaba.

### Cliente (con cuenta)
1. Se registra (`registro.php`) o ingresa (`login.php`).
2. **Carrito** (solo prendas en venta o alquiler): al agregar algo aparece arriba un aviso **«Deshacer»** que dura **5 minutos**, por si fue sin querer. Los productos se ven entrando al carrito (`carrito.php`), donde cambia cantidades y, si hay alquiler, elige las fechas de retiro y devolución.
3. Al confirmar se crea un **pedido** (sin pago en línea). En **Mis pedidos** ve el estado y la nota de la modista, y puede cancelarlo mientras está pendiente.
4. **Pide un presupuesto** (`solicitud.php`) para confección o arreglos. El formulario es el mismo que se validó en TP11.
5. En **Mis solicitudes** ve sus solicitudes; cuando la modista responde le llega un aviso y puede **aceptar** o **rechazar** el presupuesto.

### Modista (panel con login)
1. Ingresa por `panel_login.php`. En el primer ingreso el sistema le pide cambiar la contraseña provisoria.
2. **Solicitudes** (`modista.php`):
   - las filtra y busca;
   - arma el presupuesto (fecha de entrega + monto, con recargo automático según el plazo);
   - las rechaza, las cancela o las marca como completadas;
   - ve si el cliente aceptó o rechazó y el motivo.

   La modista **no** puede aceptar ni rechazar en nombre del cliente.
3. **Pedidos** (`pedidos.php`): ve los pedidos del carrito, les deja una nota y los pasa de estado (Confirmado → Listo para retirar → Entregado → Devuelto, este último solo en alquileres; o Rechazado). Cada cambio le avisa al cliente.
4. **Catálogo** (`productos.php`, `producto.php`): agrega, edita, oculta o elimina prendas con foto. En cada prenda define:
   - categoría y tipo de prenda;
   - si se vende y/o se alquila, con su precio (o "a consultar");
   - el **descuento** y hasta qué fecha vale;
   - si es **de temporada**.
5. **Categorías** (`categorias.php`): categorías y tipos de prenda (arman el menú y los filtros).
6. **Portada** (`banners.php`): las pantallas grandes del inicio y los recuadros de abajo (título, texto, botón, imagen, orden, ocultar).
7. **Tarifas** (`tarifas.php`): define el recargo por entrega rápida.
8. **Datos del sitio** (`sitio.php`): sube su **logo** y edita su historia, el título de la temporada y los datos de contacto (WhatsApp, teléfono, email, Instagram, Facebook, dirección y horarios).

### Recorrido de un pedido (carrito)

```
Pendiente ──► Confirmado ──► Listo para retirar ──► Entregado ──► Devuelto (solo alquiler)
    │             │
    │             └──► Rechazado (modista)
    ├──► Rechazado (modista)
    └──► Cancelado (cliente)
```

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
| `tienda.php` | Todas las prendas con filtros, buscador y orden |
| `prenda.php` | Detalle de una prenda |
| `tarjeta_producto.php` | Tarjeta de producto que usan inicio, tienda y prenda |
| `carrito_accion.php` | Agregar, sumar, restar, quitar y deshacer en el carrito |
| `carrito.php` → `confirmar_pedido.php` | Carrito y confirmación del pedido |
| `mis_pedidos.php` / `cancelar_pedido.php` | Pedidos del cliente |
| `registro.php` / `login.php` / `logout.php` | Cuenta del cliente |
| `solicitud.php` → `procesar_presupuesto.php` | Formulario de solicitud (solo con cuenta) y su validación |
| `mis_solicitudes.php` | Lista de solicitudes del cliente |
| `seguimiento.php` → `responder_presupuesto.php` | Detalle de una solicitud; aceptar o rechazar el presupuesto |
| `notificaciones.php` + `js/notificaciones.js` | Avisos al cliente (solicitudes y pedidos) |
| `encabezado_cliente.php`, `pie_cliente.php` | Menú (logo, Shop/Alquiler/Confección/Arreglos, íconos, aviso Deshacer) y pie |
| `css/sitio.css`, `css/cliente.css`, `js/sitio.js` | Estilos del sitio; menú desplegable, carrusel y cuenta regresiva de Deshacer |
| **Panel de la modista** | |
| `panel_login.php` / `panel_logout.php` / `panel_clave.php` | Ingreso, salida y cambio de contraseña |
| `modista.php` → `actualizar_estado.php` | Solicitudes y cambios de estado |
| `pedidos.php` → `actualizar_pedido.php` | Pedidos del carrito y cambios de estado |
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
| `conexion.example.php` | Plantilla de la conexión PDO (sin credenciales) |
| `img/productos/`, `img/banners/`, `img/sitio/` | Fotos del catálogo, de la portada y el logo (con `.htaccess` que impide ejecutar código ahí). Las `ejemplo_*.jpg` son fotos de muestra |
| `elegancia_a_medida.sql` | Base de datos completa con datos iniciales y de prueba |
| `BASE_DE_DATOS.md` | Diccionario de la base: tablas, claves, relaciones y qué archivo usa cada una |

## Requisitos

- XAMPP con PHP 8 (o Apache + PHP 8 + MySQL 5.7+/MariaDB 10.2+), con la extensión `fileinfo` activa (viene activa en XAMPP)
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
5. Abrir:
   - sitio: http://localhost/elegancia_a_medida/
   - panel: http://localhost/elegancia_a_medida/panel_login.php

### Usuarios de prueba

| Quién | Email | Contraseña |
|---|---|---|
| Modista (panel) | `modista@elegancia.com` | `Modista2026` (se cambia en el primer ingreso) |
| Clienta | `maria@demo.com` | `demo1234` |
| Clienta | `sofia@demo.com` | `demo1234` (tiene un presupuesto para responder) |

No usa librerías externas (ni CDN): el sitio funciona sin internet, solo con XAMPP.

## Prueba rápida

1. Sin iniciar sesión, abrir el inicio: pasan las pantallas Bienvenidos / Venta / Alquiler / Accesorios. Abrir **Shop** y **Arreglos** del menú. Probar el buscador (lupa).
2. Entrar a una prenda y tocar **Comprar**: pide iniciar sesión. Ingresar con `sofia@demo.com`.
3. Agregar una prenda: arriba aparece **Deshacer** con la cuenta regresiva. Tocarlo: el carrito vuelve a como estaba.
4. Agregar una prenda en **alquiler**, entrar al carrito, elegir fechas y **confirmar**: aparece en Mis pedidos como Pendiente.
5. En otra ventana (o incógnito), entrar al panel → **Pedidos** → Confirmar con una nota. En la ventana del cliente llega el aviso.
6. Menú **Confección** → «Pedir presupuesto»: abre el formulario de solicitud con el servicio elegido. Enviarlo, presupuestarlo desde el panel y aceptarlo desde la cuenta del cliente.
7. Panel → **Datos del sitio**: subir un logo y ver que aparece arriba a la izquierda. Panel → **Portada**: cambiar el orden del carrusel.

## Seguridad

- Contraseñas guardadas con `password_hash()`. Tras 5 intentos fallidos, la cuenta (de cliente o de la modista) queda bloqueada 15 minutos.
- Tokens contra envíos falsificados (CSRF) en todos los formularios, y token de un solo uso en la solicitud (sin envíos duplicados).
- Cada cliente ve y responde **solo** sus solicitudes.
- Carrito y pedidos: cada cliente solo ve y toca los suyos; al confirmar se vuelven a validar precios y disponibilidad dentro de una transacción.
- Enlaces de la portada: solo páginas del sitio o `https://`.
- Fotos: se verifica el contenido real del archivo (JPG/PNG/WEBP, máximo 3 MB) y se guardan con un nombre al azar.
- Toda salida se escapa con `e()` (contra XSS) y todas las consultas son preparadas (contra inyección SQL).

## Limitaciones y pendientes

- Los avisos le llegan al cliente mientras tiene el sitio abierto. Para avisarle con el sitio cerrado hace falta correo, WhatsApp o notificaciones push.
- No hay recuperación de contraseña por email.
- El panel tiene un solo tipo de usuario (la modista).
- No hay pago en línea: la modista confirma el pedido y el pago se arregla en persona.
- No se controla stock ni que dos alquileres de la misma prenda se superpongan en fechas: lo decide la modista al confirmar.
- Contacto y pagos tienen sus tablas creadas pero todavía no tienen código.
- Las fotos de ejemplo vienen de la plantilla TailStore (licencia MIT); hay que reemplazarlas por fotos propias.

## Equipo

- Spindola Dalila
- Galeano Kevin
- Caballero Facundo
