/**
 * Elegancia a Medida — Panel de la modista
 * Interacciones ligeras del lado del cliente.
 */

(function () {
    'use strict';

    // Envío automático al cambiar el campo de búsqueda (con debounce)
    const inputBusqueda = document.getElementById('input-busqueda');
    const formFiltros = document.getElementById('form-filtros');

    if (inputBusqueda && formFiltros) {
        let temporizador = null;

        inputBusqueda.addEventListener('input', function () {
            clearTimeout(temporizador);
            // Solo auto-envía si el campo quedó vacío (para limpiar rápido)
            // o si el usuario deja de escribir 600 ms
            temporizador = setTimeout(function () {
                if (inputBusqueda.value.trim() === '' || inputBusqueda.value.trim().length >= 2) {
                    formFiltros.submit();
                }
            }, 600);
        });
    }

    // Resaltar filas al pasar el mouse ya está en CSS.
    // Aquí se puede ampliar más adelante (detalle modal, cambio de estado, etc.)
})();
