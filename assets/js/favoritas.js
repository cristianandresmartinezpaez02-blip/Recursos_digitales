/* Favoritas: tarjetas con las monedas marcadas con ★ (guardadas en el navegador). */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    let mercado = null;

    function pintar() {
        if (!mercado) return;
        const favoritas = C.listaFavoritos().map(id => mercado.porId[id]).filter(Boolean);
        if (!favoritas.length) {
            const ideas = mercado.monedas.slice(0, 4).map(m => `<button class="chip" data-fav="${m.id}" type="button">★ ${C.escapar(m.name)}</button>`).join('');
            $('favoritas').innerHTML = `<div class="panel estado-vacio" style="grid-column:1/-1">
                <div class="grande">☆</div><h2>${C.t('js.fav_vacio_titulo')}</h2><p>${C.t('js.fav_vacio')}</p>
                <div class="chips" style="justify-content:center">${ideas}</div></div>`;
            $('favoritas').querySelectorAll('[data-fav]').forEach(b => b.addEventListener('click', () => { C.alternarFavorito(b.dataset.fav); }));
            return;
        }
        $('favoritas').innerHTML = favoritas.map(m => `<article class="tarjeta-favorita">
            <div class="cabeza">${C.celdaMoneda(m, 32)}${C.botonFavorito(m.id)}</div>
            <div class="precio">${C.precio(m.current_price)}</div>
            <div style="display:flex;gap:.4rem;flex-wrap:wrap">${C.pildora(m.price_change_percentage_24h, '24h')}${C.pildora(m.price_change_percentage_7d_in_currency, '7d')}</div>
            ${C.sparkline(m.sparkline, 220, 48).replace('width="220" height="48"', 'width="100%" height="48"')}
            <div style="display:flex;justify-content:space-between;align-items:center">${C.insignia(m.analisis, true)}
                <a href="${APP.base}/paginas/trading.php?moneda=${m.id}" class="boton mini">🎮 ${C.t('js.operar')}</a></div>
        </article>`).join('');
    }

    document.addEventListener('favoritos', pintar);
    C.iniciarRefresco(m => { mercado = m; pintar(); });
})();
