/**
 * Comportamiento del sitio — Elegancia a Medida
 * (sin librerías: funciona sin internet, por ejemplo en el coloquio)
 *
 *  · Menú: Shop / Alquiler / Confección / Arreglos abren su barra al hacer clic.
 *  · Íconos: buscador y menú de la cuenta.
 *  · Menú del celular (botón ☰).
 *  · Aviso "Deshacer" del carrito: cuenta regresiva y se oculta solo.
 *  · Carrusel del inicio.
 */
(function () {
    'use strict';

    // ---------- Abrir / cerrar paneles ----------
    var abiertos = [];   // [{boton, panel}]

    function cerrar(boton, panel) {
        boton.setAttribute('aria-expanded', 'false');
        panel.hidden = true;
        abiertos = abiertos.filter(function (a) { return a.panel !== panel; });
    }
    function cerrarTodos(excepto) {
        abiertos.slice().forEach(function (a) { if (a.panel !== excepto) { cerrar(a.boton, a.panel); } });
    }
    function alternar(boton, panel) {
        var abrir = panel.hidden;
        cerrarTodos(abrir ? null : panel);
        if (abrir) {
            boton.setAttribute('aria-expanded', 'true');
            panel.hidden = false;
            abiertos.push({ boton: boton, panel: panel });
            var campo = panel.querySelector('input[type="search"]');
            if (campo) { campo.focus(); }
        } else {
            cerrar(boton, panel);
        }
    }

    // Botones con aria-controls (menú, buscador, cuenta)
    document.querySelectorAll('.menu-boton, #btn-buscar, #btn-cuenta').forEach(function (boton) {
        var panel = document.getElementById(boton.getAttribute('aria-controls'));
        if (!panel) { return; }
        boton.addEventListener('click', function (ev) {
            ev.stopPropagation();
            alternar(boton, panel);
        });
    });

    // Clic afuera o Escape: se cierra todo
    document.addEventListener('click', function (ev) {
        if (!ev.target.closest('.menu-panel, .barra-busqueda, .menu-cuenta')) { cerrarTodos(null); }
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') {
            var ultimo = abiertos[abiertos.length - 1];
            cerrarTodos(null);
            if (ultimo) { ultimo.boton.focus(); }
        }
    });

    // ---------- Menú del celular ----------
    var btnMenu = document.getElementById('btn-menu');
    var menu = document.getElementById('menu-principal');
    if (btnMenu && menu) {
        btnMenu.addEventListener('click', function (ev) {
            ev.stopPropagation();
            var abrir = !menu.classList.contains('abierto');
            menu.classList.toggle('abierto', abrir);
            document.body.classList.toggle('menu-abierto', abrir);
            btnMenu.setAttribute('aria-expanded', abrir ? 'true' : 'false');
            btnMenu.setAttribute('aria-label', abrir ? 'Cerrar menú' : 'Abrir menú');
        });
        menu.addEventListener('click', function (ev) { ev.stopPropagation(); });
    }

    // ---------- Aviso "Deshacer" del carrito ----------
    var aviso = document.getElementById('aviso-deshacer');
    if (aviso) {
        var restante = parseInt(aviso.dataset.segundos, 10) || 0;
        var reloj = aviso.querySelector('.aviso-deshacer-tiempo');
        var mostrar = function () {
            if (restante <= 0) { aviso.remove(); return; }
            var m = Math.floor(restante / 60), s = restante % 60;
            if (reloj) { reloj.textContent = 'Podés deshacerlo durante ' + m + ':' + (s < 10 ? '0' : '') + s; }
            restante--;
        };
        mostrar();
        setInterval(mostrar, 1000);
    }

    // ---------- Carrusel ----------
    document.querySelectorAll('[data-carrusel]').forEach(function (car) {
        var slides = car.querySelectorAll('.slide');
        if (slides.length < 2) { return; }
        var puntos = car.querySelectorAll('.carrusel-puntos button');
        var actual = 0, timer = null;
        var ir = function (n) {
            slides[actual].classList.remove('activo');
            slides[actual].setAttribute('aria-hidden', 'true');
            if (puntos[actual]) { puntos[actual].removeAttribute('aria-current'); }
            actual = (n + slides.length) % slides.length;
            slides[actual].classList.add('activo');
            slides[actual].removeAttribute('aria-hidden');
            if (puntos[actual]) { puntos[actual].setAttribute('aria-current', 'true'); }
        };
        var auto = function () {
            clearInterval(timer);
            if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                timer = setInterval(function () { ir(actual + 1); }, 6000);
            }
        };
        var prev = car.querySelector('.carrusel-prev'), next = car.querySelector('.carrusel-next');
        if (prev) { prev.addEventListener('click', function () { ir(actual - 1); auto(); }); }
        if (next) { next.addEventListener('click', function () { ir(actual + 1); auto(); }); }
        puntos.forEach(function (p, i) { p.addEventListener('click', function () { ir(i); auto(); }); });
        car.addEventListener('mouseenter', function () { clearInterval(timer); });
        car.addEventListener('mouseleave', auto);
        // Deslizar con el dedo
        var x0 = null;
        car.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
        car.addEventListener('touchend', function (e) {
            if (x0 === null) { return; }
            var dx = e.changedTouches[0].clientX - x0;
            if (Math.abs(dx) > 40) { ir(actual + (dx < 0 ? 1 : -1)); auto(); }
            x0 = null;
        });
        auto();
    });
})();
