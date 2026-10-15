# Elegancia a Medida

Sistema web para que los clientes de un emprendimiento de confección y modista soliciten presupuestos mediante un formulario. Las solicitudes se validan en el servidor y quedan registradas en una base de datos MySQL. La modista las gestiona desde un panel donde arma el presupuesto (fecha de entrega, plazo y monto) y cambia el estado de cada solicitud.

Proyecto de Prácticas Profesionalizantes II – Grupo 2 · Ciclo 2026

## Qué hace

**Cliente** (`index.php`): completa un formulario con sus datos y el servicio que necesita. Al enviarlo:

1. El navegador envía los datos por POST a `procesar_presupuesto.php`.
2. El servidor valida los datos: campos obligatorios, teléfono, servicio permitido, descripción y una fecha entre hoy y hoy + 180 días.
3. Si son correctos, se guardan en `solicitudes_presupuesto` con `id_estados_solicitud = 1` (Pendiente).
4. El cliente vuelve a `index.php` y ve un mensaje de confirmación. Si hay errores, se informan y no se guarda nada.

Un token de un solo uso en sesión evita registrar dos veces la misma solicitud.

**Modista** (`modista.php`):

1. Ve las solicitudes y puede filtrarlas por estado o buscarlas por nombre.
2. **Presupuestar**: elige la fecha de entrega (o el plazo en días) y el monto base. Según el plazo se aplica el recargo de la tabla de tarifas y se ve el total en vivo. La solicitud pasa a *Presupuestado*.
3. Cambia el estado: *Cliente aceptó* → En proceso → *Completar*; o *Rechazar*, *Cliente no aceptó*, *Cancelar* y *Reabrir*.
4. **Tarifas por plazo** (`tarifas.php`): define cuánto se recarga según los días hasta la entrega (por ejemplo, Urgente ≤ 7 días +40 %).

## Tecnologías

- HTML, CSS y JavaScript
- PHP 8 con PDO (extensión `pdo_mysql`) y consultas preparadas
- MySQL / MariaDB (base `elegancia_a_medida`)
- XAMPP (Apache + MySQL) como entorno local

## Estructura

| Archivo | Función |
|---|---|
| `index.php` | Formulario del cliente, token y mensajes |
| `procesar_presupuesto.php` | Valida e inserta la solicitud |
| `modista.php` | Panel: listado, filtros, presupuesto y cambio de estado |
| `presupuesto.php` | Formulario de la modista: fecha de entrega, plazo y monto |
| `guardar_presupuesto.php` | Valida, calcula el recargo y guarda el presupuesto |
| `actualizar_estado.php` | Cambia el estado si la transición está permitida |
| `tarifas.php` / `guardar_tarifa.php` | Alta, modificación y baja de tarifas por plazo |
| `encabezado_modista.php` | Menú común del panel |
| `config.php` | Zona horaria, límites, estados y transiciones |
| `funciones.php` | Funciones compartidas (escape, cálculo, tokens) |
| `css/panel.css` | Estilos del panel agregados (complementa `css/modista.css`) |
| `conexion.example.php` | Plantilla de la conexión PDO (sin credenciales) |
| `elegancia_a_medida.sql` | Base de datos completa con datos iniciales |
| `BASE_DE_DATOS.md` | **Diccionario de la base**: tablas, ID, relaciones y qué archivo usa cada una |
| `.gitignore` | Excluye `conexion.php` para no publicar credenciales |

## Requisitos

- XAMPP con PHP 8 (o equivalente con Apache, PHP 8 y MySQL 5.7+/MariaDB 10.2+)
- Git

## Instalación y ejecución

1. Iniciar Apache y MySQL desde el Panel de Control de XAMPP.
2. Clonar el repositorio dentro de `htdocs`:
   ```
   cd C:\xampp\htdocs
   git clone https://github.com/Dalila025/Elegancia-a-medida.git elegancia_a_medida
   ```
3. Abrir http://localhost/phpmyadmin, pestaña **Importar**, y cargar `elegancia_a_medida.sql`. El script crea la base y todas las tablas. **Si ya existían, las borra.**
4. Copiar `conexion.example.php` como `conexion.php` y completar usuario y clave (en XAMPP: `root` y clave vacía). No subir `conexion.php` al repositorio.
5. Abrir http://localhost/elegancia_a_medida/index.php (clientes) y http://localhost/elegancia_a_medida/modista.php (modista).

## Prueba rápida

- **Caso válido:** completar el formulario y enviar. Debe aparecer `?ok=1` y una fila nueva con `id_estados_solicitud = 1`.
- **Caso inválido:** teléfono `abc123`. Aparece un error y no se agrega ninguna fila.
- **Presupuesto:** en el panel, *Presupuestar* una solicitud con entrega en 5 días y monto 10000. Con las tarifas iniciales, el total debe ser $ 14.000 (Urgente +40 %) y la solicitud debe pasar a *Presupuestado*.

## Estado actual

**Implementado**

- Formulario de solicitud de presupuesto con validación en el servidor
- Registro en MySQL con PDO y consultas preparadas
- Prevención de envíos duplicados
- Fecha requerida limitada a 180 días (`FECHA_MAX_DIAS`)
- Panel de la modista con filtros y cambio de estado
- Presupuesto con fecha de entrega, plazo y recargo según tarifas definidas por la modista

**Limitaciones y pendientes**

- El panel de la modista no tiene login: cualquiera con la URL puede verlo y modificarlo. La tabla `usuarios` ya está creada para esa etapa.
- El presupuesto se le comunica al cliente por fuera del sistema (teléfono / WhatsApp).
- Catálogo, contacto, alquileres y pagos tienen sus tablas creadas pero no tienen código (ver `BASE_DE_DATOS.md`).

## Equipo

- Spindola Dalila
- Galeano Kevin
- Caballero Facundo
