/* Panel de administración: pestañas, confirmaciones y menús de acciones. */
(function () {
    'use strict';
    const C = window.Cripto;

    C.iniciarPestanas('pestanas-admin');

    // Acciones peligrosas: <form data-confirmar="¿Seguro…?">.
    document.querySelectorAll('form[data-confirmar]').forEach(f => f.addEventListener('submit', ev => {
        if (!confirm(f.dataset.confirmar)) ev.preventDefault();
    }));

    // Solo un menú de acciones abierto a la vez; se cierra al hacer clic fuera.
    const menus = [...document.querySelectorAll('.acciones-fila')];
    menus.forEach(d => d.addEventListener('toggle', () => { if (d.open) menus.forEach(o => { if (o !== d) o.open = false; }); }));
    document.addEventListener('click', ev => { if (!ev.target.closest('.acciones-fila')) menus.forEach(d => { d.open = false; }); });
})();
