/*
 * Solicitud de presupuesto más guiada — Elegancia a Medida
 *
 *  - Arriba, los 4 pasos (Servicio, Prenda, Detalle, Fecha) se van tildando.
 *  - El paso que toca completar se resalta.
 *  - Cuando completás uno, aparece una flecha que rebota (↓) con lo que sigue;
 *    al tocarla te lleva al campo siguiente (y en la fecha abre el calendario).
 *    La fecha viene sugerida (sin recargo): el paso se tilda cuando la clienta la mira.
 *  - El servicio se elige con tarjetas y la prenda con botones rápidos.
 *  - El contador de la descripción dice cuántas letras faltan.
 * Sin JavaScript el formulario funciona igual (es solo una ayuda).
 */
(function () {
    'use strict';
    var form = document.getElementById('formPresupuesto');
    if (!form) { return; }
    var grupos = Array.prototype.slice.call(form.querySelectorAll('.form-group[data-paso]'));
    var pasos  = document.getElementById('pasos-solicitud');
    var minDesc = parseInt(form.dataset.descMin, 10) || 10;
    var campo = {
        1: form.querySelector('#id_tipos_servicio'),
        2: form.querySelector('#tipo_prenda'),
        3: form.querySelector('#descripcion'),
        4: form.querySelector('#fecha_requerida')
    };
    var boton = document.getElementById('btnEnviar');
    // La fecha viene sugerida: el paso cuenta como hecho cuando la clienta la mira o la cambia
    // (o si volvió con errores y ya la había elegido).
    var fechaVista = /[?&]fecha_requerida=/.test(location.search);

    function completo(n) {
        var c = campo[n];
        if (!c) { return true; }
        var v = (c.value || '').trim();
        if (n === 3) { return v.length >= minDesc; }
        if (n === 4) { return v !== '' && fechaVista; }
        return v !== '';
    }

    // ---- Flecha «siguiente» debajo de cada paso ----
    grupos.forEach(function (g) {
        var f = document.createElement('button');
        f.type = 'button';
        f.className = 'flecha-siguiente';
        f.hidden = true;
        f.innerHTML = '<span class="fs-flecha" aria-hidden="true">↓</span> ' + g.dataset.siguiente;
        f.addEventListener('click', function () { irAlSiguiente(parseInt(g.dataset.paso, 10)); });
        g.appendChild(f);
    });

    function irA(el) {
        if (!el) { return; }
        el.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
        setTimeout(function () {
            el.focus({ preventScroll: true });
            if (el.type === 'date' && typeof el.showPicker === 'function') {
                try { el.showPicker(); } catch (e) { /* algunos navegadores no lo permiten */ }
            }
        }, 350);
    }

    function irAlSiguiente(n) {
        for (var i = n + 1; i <= 4; i++) {
            if (!completo(i)) { return irA(campo[i]); }
        }
        irA(boton);
    }

    // ---- Estado de cada paso ----
    function actualizar() {
        var actual = 0;
        for (var i = 1; i <= 4; i++) { if (!completo(i)) { actual = i; break; } }
        grupos.forEach(function (g) {
            var n = parseInt(g.dataset.paso, 10);
            var ok = completo(n);
            g.classList.toggle('paso-hecho', ok);
            g.classList.toggle('paso-actual', n === actual);
            // La flecha aparece en el último paso completado antes del que falta
            var f = g.querySelector('.flecha-siguiente');
            f.hidden = !(ok && (actual === 0 ? n === 4 : n === actual - 1));
        });
        if (pasos) {
            Array.prototype.forEach.call(pasos.children, function (li) {
                var n = parseInt(li.dataset.paso, 10);
                li.classList.toggle('hecho', completo(n));
                li.classList.toggle('actual', n === actual);
            });
        }
        if (boton) { boton.classList.toggle('listo', actual === 0); }
        contador();
    }

    // ---- Contador de la descripción ----
    var cont = document.getElementById('contador-desc');
    var textoMax = cont ? cont.textContent : '';
    function contador() {
        if (!cont || !campo[3]) { return; }
        var n = campo[3].value.trim().length;
        cont.textContent = n < minDesc
            ? (minDesc - n === 1 ? 'Te falta 1 letra' : 'Te faltan ' + (minDesc - n) + ' letras') + ' para que la modista entienda bien el trabajo'
            : '✓ ' + n + ' caracteres · ' + textoMax.toLowerCase();
        cont.classList.toggle('ok', n >= minDesc);
    }

    // ---- Servicio con tarjetas (el select queda oculto pero sigue enviándose) ----
    var sel = campo[1];
    if (sel) {
        var iconos = { 'confección': '✂️', 'arreglos': '🧵', 'diseño': '✨', 'otro': '💬' };
        var tarjetas = document.createElement('div');
        tarjetas.className = 'servicio-tarjetas';
        tarjetas.setAttribute('role', 'radiogroup');
        tarjetas.setAttribute('aria-label', 'Tipo de servicio');
        Array.prototype.forEach.call(sel.options, function (o) {
            if (!o.value) { return; }
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'servicio-tarjeta';
            b.setAttribute('role', 'radio');
            var clave = Object.keys(iconos).filter(function (k) { return o.text.toLowerCase().indexOf(k) === 0; })[0];
            b.innerHTML = '<span class="st-icono" aria-hidden="true">' + (iconos[clave] || '🪡') + '</span><span>' + o.text + '</span>';
            b.addEventListener('click', function () {
                sel.value = o.value;
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            });
            b.dataset.valor = o.value;
            tarjetas.appendChild(b);
        });
        sel.classList.add('oculto-js');
        sel.parentNode.insertBefore(tarjetas, sel.nextSibling);
        function marcar() {
            Array.prototype.forEach.call(tarjetas.children, function (b) {
                var on = b.dataset.valor === sel.value;
                b.classList.toggle('elegida', on);
                b.setAttribute('aria-checked', on ? 'true' : 'false');
            });
        }
        sel.addEventListener('change', function () { marcar(); actualizar(); if (completo(1) && !completo(2)) { setTimeout(function () { irAlSiguiente(1); }, 250); } });
        marcar();
    }

    // ---- Prendas rápidas ----
    var rapidas = form.querySelector('.prendas-rapidas');
    if (rapidas && campo[2]) {
        rapidas.hidden = false;
        rapidas.addEventListener('click', function (e) {
            var b = e.target.closest('button[data-prenda]');
            if (!b) { return; }
            campo[2].value = b.dataset.prenda;
            campo[2].dispatchEvent(new Event('input', { bubbles: true }));
            irAlSiguiente(2);
        });
    }

    if (campo[4]) {
        ['focus', 'click', 'change', 'input'].forEach(function (ev) {
            campo[4].addEventListener(ev, function () { fechaVista = true; actualizar(); });
        });
        var usar = document.querySelector('.fs-usar');
        if (usar) { usar.addEventListener('click', function () { fechaVista = true; actualizar(); }); }
    }
    form.addEventListener('input', actualizar);
    form.addEventListener('change', actualizar);
    if (pasos) { pasos.hidden = false; }
    actualizar();
})();
