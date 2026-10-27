# Base de datos — Elegancia a Medida

Base: `elegancia_a_medida` · Motor: InnoDB · Cotejamiento: `utf8mb4_general_ci`
Script de creación: `elegancia_a_medida.sql`. Al reimportarlo se borran las tablas y se vuelven a crear.

## Convenciones de nombres

| Qué | Regla | Ejemplo |
|---|---|---|
| Tabla | plural, snake_case | `solicitudes_presupuesto` |
| Clave primaria | `id_` + nombre de la tabla | `usuarios.id_usuarios` |
| Clave foránea | **el mismo nombre** que la clave primaria a la que apunta | `presupuestos.id_solicitudes_presupuesto` → `solicitudes_presupuesto.id_solicitudes_presupuesto` |
| Índice | `idx_<tabla>_<columnas>` | `idx_solicitudes_estado_fecha` |
| Único | `uq_<tabla>_<columna>` | `uq_clientes_email` |
| Restricción de clave foránea | `fk_<tabla>_<referencia>` | `fk_productos_categoria` |
| Dinero | `DECIMAL(10,2)` | `monto_total` |
| Sí/No | `TINYINT(1)` 0/1 | `activo`, `leida` |
| Contraseñas | `clave_hash` con `password_hash()` | nunca la clave en texto |

Así, cuando en una tabla ves `id_clientes`, sabés que apunta a la tabla `clientes`.

## Todas las claves

| # | Tabla | Clave primaria | Claves foráneas (→ tabla) |
|---|---|---|---|
| 1 | `estados_solicitud` | `id_estados_solicitud` | — |
| 2 | `tipos_servicio` | `id_tipos_servicio` | — |
| 3 | `clientes` | `id_clientes` | — |
| 4 | `usuarios` | `id_usuarios` | — |
| 5 | `categorias` | `id_categorias` | — |
| 6 | `tipos_producto` | `id_tipos_producto` | — |
| 7 | `productos` | `id_productos` | `id_categorias` → categorias · `id_tipos_producto` → tipos_producto |
| 8 | `solicitudes_presupuesto` | `id_solicitudes_presupuesto` | `id_clientes` → clientes · `id_productos` → productos (opcional) · `id_tipos_servicio` → tipos_servicio · `id_estados_solicitud` → estados_solicitud |
| 9 | `tarifas_plazo` | `id_tarifas_plazo` | — |
| 10 | `presupuestos` | `id_presupuestos` | `id_solicitudes_presupuesto` → solicitudes_presupuesto · `id_tarifas_plazo` → tarifas_plazo |
| 11 | `estados_pedido` | `id_estados_pedido` | — |
| 12 | `pedidos` | `id_pedidos` | `id_clientes` → clientes · `id_estados_pedido` → estados_pedido |
| 13 | `items_pedido` | `id_items_pedido` | `id_pedidos` → pedidos · `id_productos` → productos (queda NULL si se borra) |
| 14 | `items_carrito` | `id_items_carrito` | `id_clientes` → clientes · `id_productos` → productos |
| 15 | `notificaciones` | `id_notificaciones` | `id_solicitudes_presupuesto` → solicitudes_presupuesto · `id_pedidos` → pedidos · `id_estados_solicitud` → estados_solicitud |
| 16 | `datos_sitio` | `id_datos_sitio` (siempre 1) | — |
| 17 | `banners` | `id_banners` | — |
| 18 | `mensajes_contacto` | `id_mensajes_contacto` | — |
| 19 | `pagos` | `id_pagos` | `id_presupuestos` → presupuestos |

## Diagrama

```mermaid
erDiagram
    categorias        ||--o{ productos : "id_categorias"
    tipos_producto    ||--o{ productos : "id_tipos_producto"
    clientes          ||--o{ solicitudes_presupuesto : "id_clientes"
    productos         |o--o{ solicitudes_presupuesto : "id_productos (opcional)"
    tipos_servicio    ||--o{ solicitudes_presupuesto : "id_tipos_servicio"
    estados_solicitud ||--o{ solicitudes_presupuesto : "id_estados_solicitud"
    solicitudes_presupuesto ||--o| presupuestos : "id_solicitudes_presupuesto"
    tarifas_plazo     |o--o{ presupuestos : "id_tarifas_plazo"
    clientes          ||--o{ items_carrito : "id_clientes"
    productos         ||--o{ items_carrito : "id_productos"
    clientes          ||--o{ pedidos : "id_clientes"
    estados_pedido    ||--o{ pedidos : "id_estados_pedido"
    pedidos           ||--|{ items_pedido : "id_pedidos"
    productos         |o--o{ items_pedido : "id_productos"
    solicitudes_presupuesto |o--o{ notificaciones : "id_solicitudes_presupuesto"
    pedidos           |o--o{ notificaciones : "id_pedidos"
    presupuestos      ||--o{ pagos : "id_presupuestos (futuro)"
```

`usuarios`, `datos_sitio`, `banners` y `mensajes_contacto` no se relacionan con otras tablas.

## Tablas en uso

### 1. `estados_solicitud`
**Los valores de `id_estados_solicitud` tienen que coincidir con las constantes `ESTADO_*` de `config.php`.**

| id | nombre | Quién la pone en ese estado |
|---|---|---|
| 1 | Pendiente | El cliente al enviar (o la modista al reabrir) |
| 6 | Presupuestado | La modista al cargar el presupuesto |
| 2 | En proceso | **El cliente** al aceptar el presupuesto |
| 7 | No aceptado | **El cliente** al rechazar el presupuesto |
| 3 | Completado | La modista |
| 4 | Cancelado | La modista |
| 5 | Rechazado | La modista (no toma el trabajo) |

Los botones que tiene la modista en cada estado están en `TRANSICIONES` (`config.php`). Aceptar y rechazar el presupuesto **no** están ahí: solo los puede hacer el cliente (`responder_presupuesto.php`).

### 2. `tipos_servicio`
| Columna | Tipo | Nota |
|---|---|---|
| id_tipos_servicio | TINYINT UNSIGNED PK | 1 Confección · 2 Arreglos · 3 Diseño personalizado · 4 Otro |
| nombre | VARCHAR(50) único | |
| activo | TINYINT(1) | 0 = no aparece en el formulario |

### 3. `clientes`
| Columna | Tipo | Nota |
|---|---|---|
| id_clientes | INT UNSIGNED PK | |
| nombre, telefono | VARCHAR(100), VARCHAR(30) | se copian a cada solicitud |
| email | VARCHAR(150) único | se usa para ingresar |
| clave_hash | VARCHAR(255) | `password_hash()` |
| activo | TINYINT(1) | 0 = no puede ingresar |
| intentos_fallidos, bloqueado_hasta | TINYINT, DATETIME NULL | 5 fallos → bloqueo de 15 min |
| ultimo_acceso, fecha_alta | DATETIME NULL, TIMESTAMP | |

**Usada en:** `registro.php`, `login.php`, todas las páginas del cliente (sesión).

### 4. `usuarios`
Cuentas del panel (la modista). Mismas columnas de acceso que `clientes`, más:

| Columna | Tipo | Nota |
|---|---|---|
| id_usuarios | INT UNSIGNED PK | |
| debe_cambiar_clave | TINYINT(1) | 1 = en el próximo ingreso tiene que elegir una clave nueva |

Usuario inicial: `modista@elegancia.com` / `Modista2026` (provisoria).
**Usada en:** `panel_login.php`, `panel_clave.php` y todas las páginas del panel (`requerir_modista()`).

### 5. `categorias`
La ocasión o línea de la prenda (Fiesta, Novias, Sastrería, Egresados, Día a día). Arman el menú Shop / Alquiler y los filtros de la tienda.
| Columna | Tipo | Nota |
|---|---|---|
| id_categorias | SMALLINT UNSIGNED PK | |
| nombre | VARCHAR(60) único | |
| descripcion | VARCHAR(255) | |
| activo | TINYINT(1) | 0 = oculta del catálogo (con todos sus productos) |

No se puede borrar una categoría con productos (`ON DELETE RESTRICT`).
**Usada en:** `categorias.php`, `guardar_categoria.php`, `encabezado_cliente.php`, `tienda.php`, `productos.php`, `producto.php`.

### 6. `tipos_producto`
Qué es cada prenda (Vestido, Traje / saco, Conjunto, Falda, Pantalón, Accesorio). Arman el menú y el filtro «Tipo de prenda» de la tienda; en Arreglos se usan como prenda a arreglar.

| Columna | Tipo | Nota |
|---|---|---|
| id_tipos_producto | SMALLINT UNSIGNED PK | |
| nombre | VARCHAR(60) único | |
| activo | TINYINT(1) | 0 = oculta todos sus productos del sitio |

No se puede borrar un tipo con productos. **Usada en:** `categorias.php`, `guardar_categoria.php`, `encabezado_cliente.php`, `tienda.php`, `producto.php`.

### 7. `productos`
| Columna | Tipo | Nota |
|---|---|---|
| id_productos | INT UNSIGNED PK | |
| id_categorias | SMALLINT UNSIGNED → `categorias` | |
| id_tipos_producto | SMALLINT UNSIGNED → `tipos_producto` | |
| nombre | VARCHAR(100) | |
| descripcion | TEXT | máximo 1000 caracteres (lo controla PHP) |
| en_venta / precio_venta | TINYINT(1) / DECIMAL(10,2) NULL | se vende; precio NULL = "A consultar" |
| en_alquiler / precio_alquiler | TINYINT(1) / DECIMAL(10,2) NULL | se alquila; precio NULL = "A consultar". Al menos una de las dos modalidades (CHECK) |
| descuento_porcentaje | TINYINT UNSIGNED | 0 = sin oferta; hasta 90 % |
| oferta_hasta | DATE NULL | último día de la oferta; NULL = sin fecha de fin |
| de_temporada | TINYINT(1) | 1 = aparece en la sección de temporada |
| imagen | VARCHAR(255) NULL | nombre del archivo en `img/productos/` (las `ejemplo_*.jpg` son de muestra) |
| activo | TINYINT(1) | 0 = oculto del catálogo |
| fecha_alta, fecha_actualizacion | TIMESTAMP, DATETIME NULL | |

Una oferta está **vigente** si `descuento_porcentaje > 0` y (`oferta_hasta` es NULL o es hoy o después). La regla está en `sql_oferta_vigente()` y `oferta_vigente()` (`funciones.php`).
**Usada en:** `index.php`, `tienda.php`, `prenda.php`, `carrito.php`, `productos.php`, `producto.php`, `guardar_producto.php`, `solicitud.php` (prenda de referencia).

### 8. `solicitudes_presupuesto`
| Columna | Tipo | Nota |
|---|---|---|
| id_solicitudes_presupuesto | INT UNSIGNED PK | |
| id_clientes | INT UNSIGNED → `clientes` | **obligatorio**: solo con cuenta se pide presupuesto |
| id_productos | INT UNSIGNED NULL → `productos` | prenda del catálogo usada como referencia; queda NULL si se borra el producto |
| codigo_seguimiento | CHAR(8) único | número corto de la solicitud (ej. `7K3Q-X9PA`) |
| nombre, telefono | VARCHAR | copia de los datos de la cuenta al momento de pedir |
| id_tipos_servicio | TINYINT UNSIGNED → `tipos_servicio` | |
| tipo_prenda | VARCHAR(100) | |
| descripcion | TEXT | 10 a 1000 caracteres |
| fecha_requerida | DATE | entre hoy y hoy + 180 días |
| fecha_solicitud | TIMESTAMP | automática |
| id_estados_solicitud | TINYINT UNSIGNED → `estados_solicitud` | por defecto 1 (Pendiente) |

**Usada en:** `solicitud.php`, `procesar_presupuesto.php` (INSERT), `mis_solicitudes.php`, `seguimiento.php`, `responder_presupuesto.php`, `modista.php`, `presupuesto.php`, `actualizar_estado.php`.

### 9. `tarifas_plazo`
Para un plazo de N días se toma la fila con el **menor `dias_hasta` que sea ≥ N**. `dias_hasta` NULL = sin límite.

| Columna | Tipo | Nota |
|---|---|---|
| id_tarifas_plazo | SMALLINT UNSIGNED PK | |
| nombre | VARCHAR(50) | Urgente, Rápido, Normal… |
| dias_hasta | SMALLINT UNSIGNED NULL, único | |
| recargo_porcentaje | DECIMAL(5,2) | entre −50 y 300; negativo = descuento |

Valores iniciales: Urgente ≤ 7 días +40 % · Rápido ≤ 15 días +20 % · Normal 0 %.

### 10. `presupuestos`
Uno por solicitud (si la modista lo corrige, se actualiza la misma fila).

| Columna | Tipo | Nota |
|---|---|---|
| id_presupuestos | INT UNSIGNED PK | |
| id_solicitudes_presupuesto | INT UNSIGNED único → `solicitudes_presupuesto` | |
| id_tarifas_plazo | SMALLINT UNSIGNED NULL → `tarifas_plazo` | |
| monto_base | DECIMAL(10,2) | precio sin recargo |
| fecha_entrega, plazo_dias | DATE, SMALLINT | los elige la modista |
| recargo_porcentaje, monto_total | DECIMAL | se guardan fijos; `monto_total = monto_base × (100 + recargo) / 100` |
| observaciones | VARCHAR(500) | |
| fecha_respuesta_cliente | DATETIME NULL | cuándo el cliente aceptó o rechazó |
| comentario_cliente | VARCHAR(300) | motivo opcional del rechazo (lo ve la modista) |
| fecha_creacion, fecha_actualizacion | TIMESTAMP, DATETIME NULL | |

### 11. `estados_pedido`
**Los valores tienen que coincidir con las constantes `PEDIDO_*` de `config.php`.** Los botones de la modista en cada estado están en `TRANSICIONES_PEDIDO`.

| id | nombre | Quién lo pone |
|---|---|---|
| 1 | Pendiente | El cliente al confirmar el carrito |
| 2 | Confirmado | La modista |
| 3 | Listo para retirar | La modista |
| 4 | Entregado | La modista |
| 5 | Devuelto | La modista (solo si el pedido tiene prendas en alquiler) |
| 6 | Cancelado | **El cliente**, solo mientras está Pendiente |
| 7 | Rechazado | La modista |

### 12. `pedidos`
Se crea cuando el cliente confirma el carrito (`confirmar_pedido.php`). No hay pago en línea: la modista confirma y arregla el pago en persona.

| Columna | Tipo | Nota |
|---|---|---|
| id_pedidos | INT UNSIGNED PK | |
| id_clientes | INT UNSIGNED → `clientes` | |
| codigo | CHAR(8) único | número corto del pedido (ej. `A552-7S4X`) |
| id_estados_pedido | TINYINT UNSIGNED → `estados_pedido` | por defecto 1 (Pendiente) |
| fecha_retiro, fecha_devolucion | DATE NULL | solo si hay prendas en alquiler (máx. 30 días) |
| comentario_cliente | VARCHAR(500) | aclaraciones (talle, etc.) |
| nota_modista | VARCHAR(500) | la ve el cliente en «Mis pedidos» |
| total_estimado | DECIMAL(10,2) | suma de los ítems con precio |
| tiene_a_consultar | TINYINT(1) | 1 = algún ítem no tiene precio |
| fecha_pedido, fecha_actualizacion | TIMESTAMP, DATETIME NULL | |

**Usada en:** `confirmar_pedido.php`, `mis_pedidos.php`, `cancelar_pedido.php`, `pedidos.php`, `actualizar_pedido.php`.

### 13. `items_pedido`
Las prendas de cada pedido. Nombre y precio se **copian** al confirmar: si después cambia el catálogo, el pedido no cambia.

| Columna | Tipo | Nota |
|---|---|---|
| id_items_pedido | INT UNSIGNED PK | |
| id_pedidos | INT UNSIGNED → `pedidos` | se borra con el pedido |
| id_productos | INT UNSIGNED NULL → `productos` | NULL si se borró el producto |
| nombre_producto | VARCHAR(100) | copia |
| modalidad | ENUM('venta','alquiler') | |
| cantidad | TINYINT UNSIGNED | 1 a 10 |
| precio_unitario | DECIMAL(10,2) NULL | con el descuento aplicado; NULL = a consultar |
| descuento_porcentaje | TINYINT UNSIGNED | el que tenía al confirmar |

### 14. `items_carrito`
El carrito de cada cliente (se guarda en la base, así no se pierde al cerrar el navegador). Solo para prendas **en venta o en alquiler**; confección y arreglos van por el formulario de presupuesto.

| Columna | Tipo | Nota |
|---|---|---|
| id_items_carrito | INT UNSIGNED PK | |
| id_clientes | INT UNSIGNED → `clientes` | |
| id_productos | INT UNSIGNED → `productos` | |
| modalidad | ENUM('venta','alquiler') | |
| cantidad | TINYINT UNSIGNED | 1 a 10 (`CARRITO_CANT_MAX`) |
| fecha_agregado | DATETIME | para el aviso «Deshacer» de 5 minutos (`CARRITO_DESHACER_MIN`) |

Único por cliente + producto + modalidad: agregar dos veces lo mismo suma la cantidad.
**Usada en:** `carrito_accion.php`, `carrito.php`, `confirmar_pedido.php`, `encabezado_cliente.php` (número del ícono).

### 15. `notificaciones`
Avisos al cliente. Cada aviso es de una **solicitud** (`id_solicitudes_presupuesto`) **o** de un **pedido** (`id_pedidos`): una de las dos columnas queda NULL. Se crean con `notificar()` / `notificar_pedido()` (`funciones.php`), con los textos de `NOTIFICACIONES` / `NOTIFICACIONES_PEDIDO` (`config.php`).

| Columna | Tipo | Nota |
|---|---|---|
| id_notificaciones | INT UNSIGNED PK | |
| id_solicitudes_presupuesto | INT UNSIGNED NULL → `solicitudes_presupuesto` | |
| id_pedidos | INT UNSIGNED NULL → `pedidos` | |
| id_estados_solicitud | TINYINT UNSIGNED NULL → `estados_solicitud` | estado al que pasó la solicitud |
| titulo, mensaje | VARCHAR(100), VARCHAR(500) | |
| leida, fecha_lectura | TINYINT(1), DATETIME NULL | se marca al abrir la solicitud o «Mis pedidos» |
| fecha_creacion | TIMESTAMP | |

> MariaDB no permite un `CHECK` sobre columnas con clave foránea en cascada, por eso la regla «una de las dos» la controla PHP.

### 16. `datos_sitio`
Una sola fila (`id_datos_sitio = 1`). La modista la edita en el panel: **Datos del sitio** (`sitio.php`).

| Columna | Nota |
|---|---|
| logo | archivo en `img/sitio/`; NULL = se muestra el nombre «Elegancia a Medida» en texto |
| historia_titulo, historia_texto, nombre_modista | sección «Nuestra historia» del inicio |
| temporada_titulo | título de la sección de temporada |
| whatsapp, telefono, email, instagram, facebook, direccion, horarios | sección «Contacto» y pie de página; lo vacío no se muestra |

**Usada en:** `encabezado_cliente.php`, `encabezado_modista.php`, `index.php`, `pie_cliente.php`, `sitio.php`, `guardar_sitio.php`.

### 17. `banners`
Las pantallas grandes del inicio (carrusel: Bienvenidos, Venta, Alquiler, Accesorios) y los recuadros de abajo (mosaico). Panel → **Portada**.

| Columna | Tipo | Nota |
|---|---|---|
| id_banners | SMALLINT UNSIGNED PK | |
| ubicacion | ENUM('carrusel','mosaico') | |
| titulo, subtitulo | VARCHAR(60), VARCHAR(160) | |
| texto_boton, enlace | VARCHAR(30), VARCHAR(255) | enlace = página del sitio o `https://` (lo controla `enlace_seguro()`) |
| imagen | VARCHAR(255) NULL | archivo en `img/banners/`; sin imagen se ve un degradado |
| orden | SMALLINT | flechas ↑ ↓ del panel |
| activo | TINYINT(1) | 0 = oculto |

**Usada en:** `index.php`, `banners.php`, `banner.php`, `guardar_banner.php`.

## Tablas de evolución posterior (vacías)

| # | Tabla | Para |
|---|---|---|
| 18 | `mensajes_contacto` | formulario de contacto |
| 19 | `pagos` | señas y saldos de un presupuesto (`id_presupuestos`) |

## Dónde tocar cuando se modifica algo

| Si cambia… | Tocar también |
|---|---|
| Un estado de solicitud | `estados_solicitud` **y** `ESTADOS` / `ESTADO_*` / `TRANSICIONES` en `config.php` |
| Un estado de pedido | `estados_pedido` **y** `ESTADOS_PEDIDO` / `PEDIDO_*` / `TRANSICIONES_PEDIDO` en `config.php` |
| Un tipo de servicio | Solo la tabla `tipos_servicio` |
| Lo que se cobra por plazo | Panel → **Tarifas** |
| Productos, ofertas o temporada | Panel → **Catálogo** |
| Categorías o tipos de prenda | Panel → **Categorías** |
| Logo, historia o datos de contacto | Panel → **Datos del sitio** |
| Pantallas del carrusel o recuadros del mosaico | Panel → **Portada** |
| Minutos para deshacer en el carrito | `CARRITO_DESHACER_MIN` en `config.php` |
| Cuántos productos muestra Ofertas / Temporada | `INICIO_MAX_OFERTAS` / `INICIO_MAX_TEMPORADA` en `config.php` |
| Peso o formatos de las fotos | `IMAGEN_MAX_BYTES` / `IMAGEN_TIPOS` en `config.php` |
| Longitud de una columna | El `CREATE TABLE` y la constante `*_MAX` de `config.php` |
| Intentos de login / tiempo de bloqueo | `LOGIN_MAX_INTENTOS` / `LOGIN_BLOQUEO_MINUTOS` en `config.php` |
| El texto de un aviso al cliente | `NOTIFICACIONES` / `NOTIFICACIONES_PEDIDO` en `config.php` |
| Una tabla nueva | Clave primaria `id_<tabla>`; quien la referencie usa ese mismo nombre |
