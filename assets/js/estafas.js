/* ¿Es estafa?: detector de señales de alerta, calculadora de rentabilidad imposible y quiz. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    const numero = (v, dec = 0) => Number(v).toLocaleString(APP.locale, { maximumFractionDigits: dec });

    // ---------- Detector: cada señal marcada suma su peso ----------
    const PESO_MAXIMO = [...document.querySelectorAll('#form-detector input')].reduce((s, i) => s + Number(i.value), 0);
    function evaluarDetector() {
        const marcadas = [...document.querySelectorAll('#form-detector input:checked')];
        const puntos = marcadas.reduce((s, i) => s + Number(i.value), 0);
        const grave = marcadas.some(i => Number(i.value) >= 3);
        const nivel = puntos === 0 ? 'bajo' : (puntos >= 5 || grave) ? 'alto' : 'medio';
        const ins = $('detector-nivel');
        ins.className = 'insignia ' + { bajo: 'viable', medio: 'neutral', alto: 'no_viable' }[nivel];
        ins.textContent = { bajo: '✔ ', medio: '⚠ ', alto: '🚨 ' }[nivel] + C.t('js.estafa_riesgo_' + nivel);
        $('detector-texto').textContent = C.t('js.estafa_riesgo_' + nivel + '_d', { n: marcadas.length });
        const barra = $('detector-barra');
        barra.style.width = Math.min(100, Math.round(puntos / PESO_MAXIMO * 100 * 2.2)) + '%';
        barra.dataset.nivel = nivel;
    }
    $('form-detector').addEventListener('change', evaluarDetector);

    // ---------- Calculadora: lo que "prometen" llevado a un año ----------
    function calcular() {
        const monto = Math.max(0, Number($('calc-monto').value) || 0);
        const pct = Math.max(0, Number($('calc-pct').value) || 0) / 100;
        const veces = Number($('calc-periodo').value);
        const factor = Math.pow(1 + pct, veces);
        const anual = (factor - 1) * 100;
        $('calc-anual').textContent = anual > 1e6 ? '> 1.000.000 %' : numero(anual, 1) + ' %';
        $('calc-final').textContent = factor > 1e9 ? '∞' : numero(monto * factor);
        const nivel = anual <= 15 ? 'normal' : anual <= 40 ? 'raro' : 'imposible';
        $('calc-veredicto').textContent = C.t('js.estafa_calc_' + nivel);
        $('calc-veredicto').className = { normal: 'sube', raro: 'texto-neutral', imposible: 'baja' }[nivel];
        $('calc-explicacion').textContent = C.t('js.estafa_calc_' + nivel + '_d', { anual: numero(anual, 0) });
    }
    ['calc-monto', 'calc-pct', 'calc-periodo'].forEach(id => $(id).addEventListener('input', calcular));
    calcular();

    // ---------- Quiz: situaciones reales, ¿estafa o no? ----------
    const casos = window.CASOS_ESTAFA || [];
    let indice = 0, aciertos = 0, respondido = false;

    function mostrarCaso() {
        respondido = false;
        $('quiz-caso').textContent = casos[indice].texto;
        $('quiz-progreso').textContent = `${indice + 1} / ${casos.length}`;
        $('quiz-feedback').textContent = '';
        $('quiz-feedback').className = 'mensaje';
        $('quiz-siguiente').hidden = true;
        document.querySelectorAll('[data-resp]').forEach(b => { b.disabled = false; });
    }

    document.querySelectorAll('[data-resp]').forEach(b => b.addEventListener('click', () => {
        if (respondido) return;
        respondido = true;
        const caso = casos[indice];
        const bien = b.dataset.resp === caso.resp;
        if (bien) aciertos++;
        $('quiz-feedback').textContent = (bien ? '✔ ' + C.t('js.estafa_bien') : '✖ ' + C.t('js.estafa_mal')) + ' ' + caso.explica;
        $('quiz-feedback').className = 'mensaje ' + (bien ? 'exito' : 'alerta');
        document.querySelectorAll('[data-resp]').forEach(x => { x.disabled = true; });
        $('quiz-siguiente').hidden = false;
        $('quiz-siguiente').focus();
    }));

    $('quiz-siguiente').addEventListener('click', () => {
        indice++;
        if (indice < casos.length) return mostrarCaso();
        $('quiz-caja').hidden = true;
        $('quiz-final').hidden = false;
        $('quiz-puntaje').textContent = C.t('js.estafa_puntaje', { aciertos, total: casos.length });
        const nota = aciertos === casos.length ? 'perfecto' : aciertos >= casos.length * 0.75 ? 'bien' : 'repasar';
        $('quiz-comentario').textContent = C.t('js.estafa_final_' + nota);
        try { localStorage.setItem('estafas_record', String(Math.max(aciertos, Number(localStorage.getItem('estafas_record')) || 0))); } catch (e) { /* */ }
    });

    $('quiz-reiniciar').addEventListener('click', () => {
        indice = 0;
        aciertos = 0;
        $('quiz-final').hidden = true;
        $('quiz-caja').hidden = false;
        mostrarCaso();
    });

    if (casos.length) mostrarCaso();
})();
