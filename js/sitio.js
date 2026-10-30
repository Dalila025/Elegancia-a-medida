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

/* ---------- Cinta en arco (prendas en alquiler) ----------
 * Mueve la fila sin parar y le da a cada foto un giro y un tamaño según
 * qué tan lejos está del centro (efecto de pared curva).
 * Se frena con el mouse encima o con el foco del teclado; se puede arrastrar.
 * Si la compu o el celular tiene activado "reducir movimiento" (en Windows:
 * Configuración → Accesibilidad → Efectos visuales → Efectos de animación),
 * la cinta mantiene la forma de arco pero no avanza sola: se mueve arrastrando. */
(function () {
    var reducir = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('[data-arco]').forEach(function (arco) {
        var pista = arco.querySelector('.arco-pista');
        var originales = Array.prototype.slice.call(pista.children);
        if (!originales.length) { return; }

        // Se repiten las fotos hasta cubrir dos veces el ancho, para que la vuelta no se note.
        var anchoSet = pista.scrollWidth;
        var copias = Math.max(2, Math.ceil((arco.clientWidth * 2) / Math.max(anchoSet, 1)) + 1);
        for (var c = 1; c < copias; c++) {
            originales.forEach(function (it) {
                var copia = it.cloneNode(true);
                copia.setAttribute('aria-hidden', 'true');
                copia.setAttribute('tabindex', '-1');
                pista.appendChild(copia);
            });
        }
        var gap = parseFloat(getComputedStyle(pista).columnGap) || 0;
        var periodo = anchoSet - parseFloat(getComputedStyle(pista).paddingLeft) - parseFloat(getComputedStyle(pista).paddingRight) + gap;
        var items = Array.prototype.slice.call(pista.children);
        arco.classList.add('arco-activo');

        var x = 0, velocidad = reducir ? 0 : 38, pausa = false, arrastre = null, movio = false, antes = performance.now();

        function dibujar() {
            var mitad = arco.clientWidth / 2;
            var base = arco.getBoundingClientRect().left;
            pista.style.transform = 'translateX(' + (-x) + 'px)';
            items.forEach(function (it) {
                var r = it.getBoundingClientRect();
                var d = (r.left - base + r.width / 2 - mitad) / mitad;   // -1 a la izquierda, 0 al centro, 1 a la derecha
                d = Math.max(-1.4, Math.min(1.4, d));
                var escala = 1 + 0.22 * d * d;
                var giro = -d * 24;
                it.style.transform = 'rotateY(' + giro.toFixed(2) + 'deg) scale(' + escala.toFixed(3) + ')';
                it.style.zIndex = String(Math.round(10 + Math.abs(d) * 10));
            });
        }
        function paso(ahora) {
            var dt = Math.min(0.05, (ahora - antes) / 1000);
            antes = ahora;
            if (!pausa && !arrastre) { x += velocidad * dt; }
            if (x >= periodo) { x -= periodo; }
            if (x < 0) { x += periodo; }
            dibujar();
            requestAnimationFrame(paso);
        }

        arco.addEventListener('mouseenter', function () { pausa = true; });
        arco.addEventListener('mouseleave', function () { pausa = false; });
        arco.addEventListener('focusin', function () { pausa = true; });
        arco.addEventListener('focusout', function () { pausa = false; });
        arco.addEventListener('pointerdown', function (e) {
            arrastre = { x0: e.clientX, inicio: x }; movio = false;
            arco.classList.add('arrastrando');
        });
        window.addEventListener('pointermove', function (e) {
            if (!arrastre) { return; }
            var dx = e.clientX - arrastre.x0;
            if (Math.abs(dx) > 5) { movio = true; }
            x = arrastre.inicio - dx;
        });
        window.addEventListener('pointerup', function () {
            if (!arrastre) { return; }
            arrastre = null; arco.classList.remove('arrastrando');
        });
        // Si se arrastró, no abrir la prenda al soltar.
        arco.addEventListener('click', function (e) { if (movio) { e.preventDefault(); movio = false; } }, true);

        requestAnimationFrame(paso);
    });
})();
