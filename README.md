legancia a Medida

Sistema web para que los clientes de un emprendimiento de confección y modista soliciten presupuestos mediante un formulario. Las solicitudes se validan en el servidor y quedan registradas en una base de datos MySQL.

Proyecto de Prácticas Profesionalizantes II – Grupo 2 · Ciclo 2026

Qué hace

El cliente completa un formulario con sus datos y las características del servicio que necesita (confección, arreglos o diseño personalizado). Al enviarlo:

El navegador envía los datos por POST a procesar_presupuesto.php.
El servidor valida los datos (campos obligatorios, teléfono, servicio permitido, descripción y fecha).
Si son correctos, se guardan en la tabla solicitudes_presupuesto con estado 1 (Pendiente).
El cliente vuelve a index.php y ve un mensaje de confirmación. Si hay errores, se informan y no se guarda nada.

Un token de un solo uso en sesión evita registrar dos veces la misma solicitud.

Tecnologías
HTML, CSS y JavaScript
PHP 8 con PDO (extensión pdo_mysql) y consultas preparadas
MySQL (base elegancia_a_medida)
XAMPP (Apache + MySQL) como entorno local
Estructura
Archivo	Función
index.php	Formulario, generación del token y mensajes de confirmación/error
procesar_presupuesto.php	Recibe el POST, valida, inserta en MySQL y redirige
config.php	Zona horaria y constantes de validación compartidas
conexion.example.php	Plantilla de la conexión PDO (sin credenciales)
elegancia_a_medida.sql	Estructura de la base de datos (ya sin ON UPDATE en fecha_solicitud)
.gitignore	Excluye conexion.php para no publicar credenciales
Requisitos
XAMPP con PHP 8 (o equivalente con Apache, PHP 8 y MySQL)
Git (para clonar el repositorio)
Instalación y ejecución
Iniciar Apache y MySQL desde el Panel de Control de XAMPP.
Clonar el repositorio dentro de htdocs:
   cd C:\xampp\htdocs
   git clone https://github.com/Dalila025/Elegancia-a-medida.git elegancia_a_medida
Abrir http://localhost/phpmyadmin, crear la base elegancia_a_medida (cotejamiento utf8mb4_general_ci) e importar elegancia_a_medida.sql.
Copiar conexion.example.php como conexion.php y completar usuario y clave de su MySQL local (en XAMPP: usuario root, clave vacía). No subir conexion.php al repositorio.
Abrir http://localhost/elegancia_a_medida/index.php.
Prueba rápida
Caso válido: completar todos los campos con datos correctos y enviar. Debe aparecer el mensaje de confirmación (?ok=1) y una fila nueva con estado = 1 en solicitudes_presupuesto.
Caso inválido: enviar con teléfono abc123. Debe aparecer un mensaje de error y no se agrega ninguna fila.
Estado actual

Versión funcional validada: rama main, commit 535183a52de728d915ec3396bb5c25f1cd9cab43 (probada con 5 casos funcionales, todos "Cumple").

Implementado

Formulario de solicitud de presupuesto
Validaciones en el servidor
Registro en MySQL con PDO y consultas preparadas
Mensajes de confirmación y error
Prevención de envíos duplicados
Limitaciones y pendientes
No existe una vista para consultar las solicitudes: por ahora solo se ven desde phpMyAdmin.
fecha_requerida no tiene un límite máximo.
No incluye login, panel administrativo, pagos, catálogo ni reportes (previstos para etapas posteriores).
Equipo
Spindola Dalila
Galeano Kevin
Caballero Facundo