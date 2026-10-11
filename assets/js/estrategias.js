/* Guía de estrategias: pestañas por juego y calculadora de riesgo / beneficio. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;

    C.iniciarPestanas('pestanas-estrategias');

    function calcular() {
        const capital = parseFloat($('c-capital').value) || 0;
        const posicion = capital * (parseFloat($('c-posicion').value) || 0) / 100;
        const sl = (parseFloat($('c-sl').value) || 0) / 100;
        const tp = (parseFloat($('c-tp').value) || 0) / 100;
        const acierto = (parseFloat($('c-acierto').value) || 0) / 100;
        if (!(posicion > 0 && sl > 0 && tp > 0)) return;

        // Comisión de compra y de venta en cada operación.
        const comision = posicion * APP.comision * 2;
        const ganancia = posicion * tp - comision;
        const perdida = posicion * sl + comision;
        const esperado = acierto * ganancia - (1 - acierto) * perdida;
        const equilibrio = perdida / (ganancia + perdida) * 100;
        const diez = esperado * 10;

        const dato = (titulo, valor, clase = '') => `<div class="dato"><span>${titulo}</span><strong class="${clase}">${valor}</strong></div>`;
        $('c-resultado').innerHTML =
            dato(C.t('js.calc_arriesgas'), C.dinero(perdida), 'baja') +
            dato(C.t('js.calc_ganas'), C.dinero(ganancia), 'sube') +
            dato(C.t('js.calc_relacion'), `1 : ${C.numero(ganancia / perdida, 1)}`) +
            dato(C.t('js.calc_equilibrio'), `${C.numero(equilibrio, 0)} %`) +
            dato(C.t('js.calc_esperado'), `${esperado >= 0 ? '+' : '−'}${C.dinero(Math.abs(esperado))}`, esperado >= 0 ? 'sube' : 'baja') +
            dato(C.t('js.calc_diez'), `${diez >= 0 ? '+' : '−'}${C.dinero(Math.abs(diez))}`, diez >= 0 ? 'sube' : 'baja');
        $('c-veredicto').innerHTML = esperado >= 0
            ? `<span class="sube">✔ ${C.t('js.calc_bien', { pct: C.numero(equilibrio, 0) })}</span>`
            : `<span class="baja">✖ ${C.t('js.calc_mal', { pct: C.numero(equilibrio, 0) })}</span>`;
    }

    $('form-calc').addEventListener('input', calcular);
    $('form-calc').addEventListener('submit', ev => ev.preventDefault());
    calcular();
})();
