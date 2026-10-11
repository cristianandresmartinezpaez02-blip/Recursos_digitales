/* Máximos históricos: qué tan lejos está cada moneda de su precio récord. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;

    function fila(m) {
        // Barra: precio actual como % del máximo histórico.
        const pct = Math.max(0, Math.min(100, 100 + (m.ath_change_percentage || 0)));
        return `<tr>
            <td>${C.celdaMoneda(m, 24)}</td>
            <td class="num"><strong>${C.precio(m.current_price)}</strong><br><small class="texto-3">ATH ${C.precio(m.ath)}</small></td>
            <td><span class="distancia-ath" title="${C.numero(pct, 1)} %"><span style="width:${pct}%"></span></span><br>${C.porcentaje(m.ath_change_percentage, 1)}</td>
            <td class="texto-2" style="font-size:.8rem">${m.ath_date ? C.fecha(new Date(m.ath_date)) : '—'}</td>
        </tr>`;
    }

    C.iniciarRefresco(mercado => {
        const lista = mercado.monedas.filter(m => !m.analisis.es_stablecoin && m.ath_change_percentage !== null);
        const orden = [...lista].sort((a, b) => b.ath_change_percentage - a.ath_change_percentage);
        $('cuerpo-cerca').innerHTML = orden.slice(0, 15).map(fila).join('');
        $('cuerpo-lejos').innerHTML = orden.slice(-15).reverse().map(fila).join('');

        const hace30 = Date.now() - 30 * 86400000;
        $('k-nuevos').textContent = lista.filter(m => m.ath_date && new Date(m.ath_date).getTime() >= hace30).length;
        $('k-cerca').textContent = lista.filter(m => m.ath_change_percentage > -10).length;
        const d = lista.map(m => m.ath_change_percentage).sort((a, b) => a - b);
        const mediana = d.length ? d[Math.floor(d.length / 2)] : null;
        $('k-mediana').innerHTML = C.porcentaje(mediana, 1);
    });
})();
