/**
 * Avisos al cliente — Elegancia a Medida
 *
 * Se incluye en index.php y seguimiento.php. Mientras el cliente tenga
 * abierta una página del sitio:
 *   1. Consulta notificaciones.php al cargar y cada N segundos.
 *   2. Si la modista respondió, muestra un aviso en la esquina con un
 *      enlace a la página de seguimiento.
 *   3. Si el cliente dio permiso, además muestra una notificación del
 *      navegador (la del sistema operativo), una sola vez por aviso.
 *   4. Actualiza el contador #campana-avisos si existe en la página.
 */
(function () {
    'use strict';

    var URL_AVISOS   = 'notificaciones.php';
    var CLAVE_VISTOS = 'avisos_mostrados';   // ids ya mostrados en este navegador
    var intervalo    = 60;

    // ---------- Estilos del aviso (así no depende de otro CSS) ----------
    var estilo = document.createElement('style');
    estilo.textContent =
        '.aviso-modista{position:fixed;right:16px;bottom:16px;z-index:9999;max-width:360px;width:calc(100% - 32px);' +
        'background:#fff;border:1px solid #bf9d5c;border-left:5px solid #b7898f;border-radius:12px;' +
        'box-shadow:0 12px 32px rgba(35,31,32,.22);padding:14px 16px;font-family:inherit;color:#46413f;' +
        'animation:avisoEntra .3s ease-out}' +
        '.aviso-modista h4{margin:0 26px 4px 0;font-size:1rem;color:#231f20}' +
        '.aviso-modista p{margin:0 0 10px;font-size:.88rem;line-height:1.45}' +
        '.aviso-modista a{display:inline-block;background:#b7898f;color:#fff;text-decoration:none;font-weight:600;' +
        'font-size:.88rem;padding:7px 12px;border-radius:8px}' +
        '.aviso-modista a:hover{background:#695852}' +
        '.aviso-modista .cerrar{position:absolute;top:8px;right:10px;border:none;background:none;font-size:1.3rem;' +
        'line-height:1;cursor:pointer;color:#8a817d}' +
        '.aviso-modista .mas{font-size:.8rem;opacity:.75;margin-left:8px}' +
        '@keyframes avisoEntra{from{transform:translateY(20px);opacity:0}to{transform:none;opacity:1}}';
    document.head.appendChild(estilo);

    // ---------- Memoria de avisos ya mostrados (puede no estar disponible) ----------
    function leerVistos() {
        try { return JSON.parse(localStorage.getItem(CLAVE_VISTOS) || '[]'); } catch (e) { return []; }
    }
    function guardarVistos(ids) {
        try { localStorage.setItem(CLAVE_VISTOS, JSON.stringify(ids.slice(-100))); } catch (e) { /* sin memoria */ }
    }

    // ---------- Aviso en la página ----------
    function mostrarAviso(avisos) {
        var viejo = document.getElementById('aviso-modista');
        if (viejo) { viejo.remove(); }
        if (!avisos.length || document.body.dataset.ocultarAviso === '1') { return; }

        var a = avisos[0];
        var caja = document.createElement('div');
        caja.className = 'aviso-modista';
        caja.id = 'aviso-modista';
        caja.setAttribute('role', 'status');

        var cerrar = document.createElement('button');
        cerrar.className = 'cerrar';
        cerrar.type = 'button';
        cerrar.setAttribute('aria-label', 'Cerrar aviso');
        cerrar.textContent = '×';
        cerrar.onclick = function () { caja.remove(); };

        var h = document.createElement('h4');
        h.textContent = '🔔 ' + a.titulo;

        var p = document.createElement('p');
        p.textContent = a.mensaje;

        var link = document.createElement('a');
        link.href = a.url;
        link.textContent = 'Ver respuesta';

        caja.appendChild(cerrar);
        caja.appendChild(h);
        caja.appendChild(p);
        caja.appendChild(link);

        if (avisos.length > 1) {
            var mas = document.createElement('span');
            mas.className = 'mas';
            mas.textContent = '+' + (avisos.length - 1) + ' aviso' + (avisos.length > 2 ? 's' : '') + ' más';
            caja.appendChild(mas);
        }
        document.body.appendChild(caja);
    }

    // ---------- Notificación del navegador ----------
    function notificarNavegador(avisos) {
        if (!('Notification' in window) || Notification.permission !== 'granted') { return; }
        var vistos = leerVistos();
        avisos.forEach(function (a) {
            if (vistos.indexOf(a.id) !== -1) { return; }
            try {
                var n = new Notification(a.titulo, { body: a.mensaje, tag: 'aviso-' + a.id });
                n.onclick = function () { window.focus(); location.href = a.url; };
            } catch (e) { /* algunos navegadores móviles no permiten crearla así */ }
            vistos.push(a.id);
        });
        guardarVistos(vistos);
    }

    // ---------- Contador en la página (si existe) ----------
    function actualizarContador(n) {
        var c = document.getElementById('campana-avisos');
        if (!c) { return; }
        c.textContent = n;
        c.hidden = n === 0;
    }

    // ---------- Consulta periódica ----------
    function consultar() {
        fetch(URL_AVISOS, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (datos) {
                if (!datos) { return; }
                if (datos.intervalo) { intervalo = datos.intervalo; }
                actualizarContador(datos.no_leidas);
                mostrarAviso(datos.avisos);
                notificarNavegador(datos.avisos);
            })
            .catch(function () { /* sin conexión: se reintenta en el próximo ciclo */ })
            .then(function () { setTimeout(consultar, intervalo * 1000); });
    }

    // ---------- Botón para activar las notificaciones del navegador ----------
    // Cualquier elemento con data-activar-avisos lo usa (ver seguimiento.php).
    function prepararBotonPermiso() {
        var botones = document.querySelectorAll('[data-activar-avisos]');
        if (!botones.length) { return; }

        function refrescar() {
            var estado = !('Notification' in window) ? 'no-soportado' : Notification.permission;
            botones.forEach(function (b) {
                var txt = b.querySelector('[data-texto]') || b;
                if (estado === 'granted') {
                    txt.textContent = '✓ Avisos del navegador activados';
                    b.disabled = true;
                } else if (estado === 'denied') {
                    txt.textContent = 'Avisos bloqueados (se activan desde la configuración del navegador)';
                    b.disabled = true;
                } else if (estado === 'no-soportado') {
                    b.hidden = true;
                }
            });
        }

        botones.forEach(function (b) {
            b.addEventListener('click', function () {
                if (!('Notification' in window)) { return; }
                Notification.requestPermission().then(refrescar);
            });
        });
        refrescar();
    }

    var iniciado = false;
    function iniciar() {
        if (iniciado) { return; }
        iniciado = true;
        prepararBotonPermiso();
        consultar();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
