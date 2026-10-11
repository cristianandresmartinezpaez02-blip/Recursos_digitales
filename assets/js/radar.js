/* Radar técnico de la página de inicio: monedas en sobreventa / sobrecompra (RSI) y
   eventos recientes (cruces de medias, MACD, rupturas). Usa los datos que ya descarga
   la página (evento "mercado" de app.js), sin peticiones extra. */
(function () {
    'use strict';
    const C = window.Cripto, T = window.Tecnico;
    const $ = C.$;
    const EVENTOS_RADAR = ['cruce_dorado', 'cruce_muerte', 'macd_alcista', 'macd_bajista', 'ruptura_alcista', 'ruptura_bajista'];

    const item = (m, derecha) => `<a class="item" href="${C.urlMoneda(m.id)}#tecnico">
        <img src="${C.escapar(m.image)}" alt="" width="24" height="24" loading="lazy">
        <span><strong>${C.escapar(m.symbol.toUpperCase())}</strong> <small class="texto-3">${C.escapar(m.name)}</small></span>
        <span class="derecha">${derecha}</span></a>`;
    const vacio = clave => `<p class="texto-3" style="margin:.5rem 0">${C.t(clave)}</p>`;

    document.addEventListener('mercado', ev => {
        if (!$('radar-sobreventa')) return;
        const filas = ev.detail.monedas
            .filter(m => !m.analisis.es_stablecoin && m.sparkline && m.sparkline.length >= 35)
            .map(m => ({ m, r: T.resumen(m.sparkline, { corta: 10, larga: 30 }) }))
            .filter(f => f.r && f.r.rsi !== null);
        const rsi = f => `<strong class="${f.r.rsi < 30 ? 'sube' : f.r.rsi > 70 ? 'baja' : ''}">RSI ${C.numero(f.r.rsi, 0)}</strong><br>${C.porcentaje(f.m.price_change_percentage_24h, 1)}`;

        const bajas = filas.filter(f => f.r.rsi < 30).sort((a, b) => a.r.rsi - b.r.rsi).slice(0, 5);
        const altas = filas.filter(f => f.r.rsi > 70).sort((a, b) => b.r.rsi - a.r.rsi).slice(0, 5);
        $('radar-sobreventa').innerHTML = bajas.map(f => item(f.m, rsi(f))).join('') || vacio('js.radar_ninguna');
        $('radar-sobrecompra').innerHTML = altas.map(f => item(f.m, rsi(f))).join('') || vacio('js.radar_ninguna');

        const eventos = filas.flatMap(f => f.r.eventos.filter(e => EVENTOS_RADAR.includes(e)).map(e => ({ f, e }))).slice(0, 6);
        $('radar-eventos').innerHTML = eventos.map(({ f, e }) => item(f.m, `<span class="chip mini evento-${e}">${C.t('js.tec_ev_' + e)}</span>`)).join('') || vacio('js.radar_sin_eventos');
    });
})();
