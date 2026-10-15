/**
 * Panel de solicitudes — Elegancia a Medida
 *
 * Mejoras visuales del panel de la modista. Nada de esto reemplaza las
 * validaciones del servidor: si el JavaScript no carga, el panel funciona
 * igual (los avisos se ven fijos y se usa el confirm() del navegador).
 *
 *  1. Sombra del encabezado al hacer scroll.
 *  2. Avisos (toasts) que se cierran solos, con barra de tiempo.
 *  3. Ventana de confirmación propia para Rechazar / Cancelar.
 *  4. Indicador "cargando" al cambiar un estado (evita doble clic).
 *  5. "Ver más" en descripciones largas.
 *  6. Filtro por servicio sin tocar "Buscar".
 *  7. Atajo "/" para ir al buscador.
 */
(function () {
    'use strict';

    var DURACION_AVISO = 6000; // ms

    // ---------- 1. Encabezado con sombra al bajar ----------
    var header = document.querySelector('.header');
    if (header) {
        var actualizarHeader = function () {
            header.classList.toggle('con-sombra', window.scrollY > 8);
        };
        window.addEventListener('scroll', actualizarHeader, { passive: true });
        actualizarHeader();
    }

    // ---------- 2. Avisos flotantes ----------
    function cerrarAviso(toast) {
        if (!toast || toast.classList.contains('saliendo')) return;
        toast.classList.add('saliendo');
        toast.addEventListener('animationend', function () { toast.remove(); }, { once: true });
    }

    document.querySelectorAll('.toast').forEach(function (toast) {
        var restante = DURACION_AVISO;
        var inicio = Date.now();
        var temporizador = null;

        var arrancar = function () {
            inicio = Date.now();
            temporizador = setTimeout(function () { cerrarAviso(toast); }, restante);
        };
        var pausar = function () {
            clearTimeout(temporizador);
            restante -= Date.now() - inicio;
        };

        toast.style.setProperty('--duracion', DURACION_AVISO + 'ms');
        toast.classList.add('con-tiempo');
        toast.addEventListener('mouseenter', pausar);
        toast.addEventListener('mouseleave', arrancar);
        toast.querySelector('.toast-cerrar').addEventListener('click', function () { cerrarAviso(toast); });
        arrancar();
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') document.querySelectorAll('.toast').forEach(cerrarAviso);
    });

    // ---------- 3 y 4. Confirmación y "cargando" en los cambios de estado ----------
    var modal = document.getElementById('modal-confirmar');
    var soportaDialog = modal && typeof modal.showModal === 'function';
    var formPendiente = null;

    function enviarConCarga(form) {
        var boton = form.querySelector('button[type="submit"]');
        var fila = form.closest('.fila');
        if (boton) boton.classList.add('cargando');
        if (fila) fila.classList.add('procesando');
        form.submit(); // submit() no vuelve a disparar el evento: no hay doble envío
    }

    document.querySelectorAll('.form-estado').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();

            if (form.dataset.confirmar !== '1') {
                enviarConCarga(form);
                return;
            }

            var titulo = (form.dataset.titulo || 'Confirmar') + '?';
            var texto = form.dataset.texto || '';

            if (!soportaDialog) {
                // Navegadores viejos: confirmación clásica
                if (window.confirm('¿' + titulo + '\n' + texto)) enviarConCarga(form);
                return;
            }

            formPendiente = form;
            document.getElementById('modal-titulo').textContent = '¿' + titulo;
            document.getElementById('modal-texto').textContent = texto;
            modal.querySelector('[data-respuesta="si"]').textContent = form.dataset.titulo || 'Confirmar';
            modal.showModal();
            modal.querySelector('[data-respuesta="no"]').focus();
        });
    });

    if (soportaDialog) {
        modal.addEventListener('click', function (ev) {
            var respuesta = ev.target.closest('[data-respuesta]');
            if (respuesta) {
                var confirmar = respuesta.dataset.respuesta === 'si';
                modal.close();
                if (confirmar && formPendiente) enviarConCarga(formPendiente);
                formPendiente = null;
                return;
            }
            // Clic afuera de la ventana: se cierra sin hacer nada
            if (ev.target === modal) {
                var r = modal.getBoundingClientRect();
                var adentro = ev.clientX >= r.left && ev.clientX <= r.right && ev.clientY >= r.top && ev.clientY <= r.bottom;
                if (!adentro) { modal.close(); formPendiente = null; }
            }
        });
        modal.addEventListener('cancel', function () { formPendiente = null; });
    }

    // El link "Presupuestar" también muestra que está cargando
    document.querySelectorAll('.accion-presupuestar').forEach(function (link) {
        link.addEventListener('click', function (ev) {
            if (ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button !== 0) return;
            link.classList.add('cargando');
        });
    });
    // Si se vuelve con el botón Atrás, se limpia el estado de carga
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('.cargando').forEach(function (el) { el.classList.remove('cargando'); });
        document.querySelectorAll('.procesando').forEach(function (el) { el.classList.remove('procesando'); });
    });

    // ---------- 5. "Ver más" en descripciones largas ----------
    function revisarDescripciones() {
        document.querySelectorAll('.c-trabajo').forEach(function (celda) {
            var desc = celda.querySelector('.descripcion');
            var boton = celda.querySelector('.ver-mas');
            if (!desc || !boton || desc.classList.contains('abierta')) return;
            boton.hidden = desc.scrollHeight <= desc.clientHeight + 2;
        });
    }

    document.addEventListener('click', function (ev) {
        var boton = ev.target.closest('.ver-mas');
        if (!boton) return;
        var desc = boton.parentElement.querySelector('.descripcion');
        var abierta = desc.classList.toggle('abierta');
        boton.textContent = abierta ? 'Ver menos' : 'Ver más';
        boton.setAttribute('aria-expanded', abierta ? 'true' : 'false');
    });

    revisarDescripciones();
    var esperaResize;
    window.addEventListener('resize', function () {
        clearTimeout(esperaResize);
        esperaResize = setTimeout(revisarDescripciones, 150);
    });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(revisarDescripciones);

    // ---------- 6. Filtro por servicio inmediato ----------
    var selectServicio = document.getElementById('select-servicio');
    if (selectServicio) {
        selectServicio.addEventListener('change', function () {
            document.getElementById('form-filtros').submit();
        });
    }

    // ---------- 7. Atajo "/" para buscar ----------
    var buscador = document.getElementById('input-busqueda');
    document.addEventListener('keydown', function (ev) {
        if (ev.key !== '/' || !buscador) return;
        var tag = (document.activeElement && document.activeElement.tagName) || '';
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
        ev.preventDefault();
        buscador.focus();
        buscador.select();
    });
})();