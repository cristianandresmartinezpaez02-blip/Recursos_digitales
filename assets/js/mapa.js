/* Mapa de calor (treemap "squarified") del top 100. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    const LIMITE = { '24h': 10, '7d': 20, '30d': 40 };
    const CAMPO = {
        '24h': 'price_change_percentage_24h',
        '7d': 'price_change_percentage_7d_in_currency',
        '30d': 'price_change_percentage_30d_in_currency',
    };
    let mercado = null;
    let periodo = '24h';
    let cantidad = 100;

    function squarify(items, x, y, w, h) {
        const total = items.reduce((s, i) => s + i.valor, 0);
        const escala = (w * h) / total;
        const cola = items.map(i => ({ ...i, area: i.valor * escala }));
        const salida = [];
        let rect = { x, y, w, h };
        let fila = [];
        const peor = (f, lado) => {
            const s = f.reduce((a, i) => a + i.area, 0);
            const max = Math.max(...f.map(i => i.area)), min = Math.min(...f.map(i => i.area));
            return Math.max((lado * lado * max) / (s * s), (s * s) / (lado * lado * min));
        };
        const colocarFila = f => {
            const s = f.reduce((a, i) => a + i.area, 0);
            if (rect.w >= rect.h) {
                const ancho = s / rect.h;
                let yy = rect.y;
                f.forEach(i => { const alto = i.area / ancho; salida.push({ ...i, x: rect.x, y: yy, w: ancho, h: alto }); yy += alto; });
                rect = { x: rect.x + ancho, y: rect.y, w: rect.w - ancho, h: rect.h };
            } else {
                const alto = s / rect.w;
                let xx = rect.x;
                f.forEach(i => { const ancho = i.area / alto; salida.push({ ...i, x: xx, y: rect.y, w: ancho, h: alto }); xx += ancho; });
                rect = { x: rect.x, y: rect.y + alto, w: rect.w, h: rect.h - alto };
            }
        };
        while (cola.length) {
            const lado = Math.min(rect.w, rect.h);
            if (!fila.length || peor([...fila, cola[0]], lado) <= peor(fila, lado)) fila.push(cola.shift());
            else { colocarFila(fila); fila = []; }
        }
        if (fila.length) colocarFila(fila);
        return salida;
    }

    const rgb = hex => { const n = parseInt(hex.replace('#', ''), 16); return [n >> 16 & 255, n >> 8 & 255, n & 255]; };

    function colorCambio(v, limite) {
        const paleta = { sube: rgb(C.css('--mapa-sube')), baja: rgb(C.css('--mapa-baja')), neutro: rgb(C.css('--mapa-neutro')) };
        const t = Math.max(-1, Math.min(1, (v || 0) / limite));
        const destino = t >= 0 ? paleta.sube : paleta.baja;
        const k = Math.pow(Math.abs(t), 0.7);
        return `rgb(${paleta.neutro.map((n, i) => Math.round(n + (destino[i] - n) * k)).join(',')})`;
    }

    function pintar() {
        const caja = $('mapa-calor');
        const W = caja.clientWidth, H = caja.clientHeight;
        if (!W || !mercado) return;
        const campo = CAMPO[periodo], limite = LIMITE[periodo];
        const items = mercado.monedas.slice(0, cantidad)
            .filter(m => m.market_cap > 0)
            .map(m => ({ m, valor: Math.sqrt(m.market_cap) }))
            .sort((a, b) => b.valor - a.valor);

        caja.innerHTML = squarify(items, 0, 0, W, H).map(({ m, x, y, w, h }) => {
            const v = m[campo];
            const lado = Math.min(w, h);
            const fuente = Math.max(9, Math.min(26, lado / 4.2));
            let contenido = '';
            if (w > 38 && h > 24) contenido = `<strong style="font-size:${fuente}px">${C.escapar(m.symbol.toUpperCase())}</strong>`;
            if (w > 52 && h > 40) contenido += `<span style="font-size:${Math.max(9, fuente * 0.62)}px">${v >= 0 ? '▲' : '▼'} ${C.numero(Math.abs(v ?? 0), 1)} %</span>`;
            if (w > 90 && h > 90) contenido = `<img src="${C.escapar(m.image)}" alt="" width="${Math.min(40, lado / 4)}" height="${Math.min(40, lado / 4)}" loading="lazy">` + contenido;
            return `<a class="celda-mapa" data-id="${m.id}" href="${C.urlMoneda(m.id)}" aria-label="${C.escapar(m.name)}"
                style="left:${x}px;top:${y}px;width:${w}px;height:${h}px;background:${colorCambio(v, limite)}">${contenido}</a>`;
        }).join('');

        $('escala-min').textContent = `▼ ${limite} %`;
        $('escala-max').textContent = `▲ ${limite} %`;
        $('escala-degradado').style.background =
            `linear-gradient(90deg, ${colorCambio(-limite, limite)}, ${colorCambio(0, limite)}, ${colorCambio(limite, limite)})`;
    }

    $('mapa-calor').addEventListener('mousemove', ev => {
        const celda = ev.target.closest('.celda-mapa');
        if (!celda || !mercado) return C.ocultarTooltip();
        const m = mercado.porId[celda.dataset.id];
        C.mostrarTooltip(`<div class="titulo-tt"><img src="${C.escapar(m.image)}" width="18" height="18" alt=""> ${C.escapar(m.name)}</div>
            <div class="fila"><span>${C.t('js.precio')}</span><strong>${C.precio(m.current_price)}</strong></div>
            <div class="fila"><span>24 h</span>${C.porcentaje(m.price_change_percentage_24h)}</div>
            <div class="fila"><span>7 d</span>${C.porcentaje(m.price_change_percentage_7d_in_currency)}</div>
            <div class="fila"><span>30 d</span>${C.porcentaje(m.price_change_percentage_30d_in_currency)}</div>
            <div class="fila"><span>${C.t('js.capitalizacion')}</span><strong>${C.grande(m.market_cap)}</strong></div>
            <div class="fila"><span>${C.t('js.senal')}</span>${C.insignia(m.analisis)}</div>`, ev);
    });
    $('mapa-calor').addEventListener('mouseleave', C.ocultarTooltip);

    $('mapa-periodo').addEventListener('click', ev => {
        const b = ev.target.closest('button[data-periodo]');
        if (!b) return;
        periodo = b.dataset.periodo;
        $('mapa-periodo').querySelectorAll('button').forEach(x => x.classList.toggle('activa', x === b));
        pintar();
    });
    $('mapa-cantidad').addEventListener('click', ev => {
        const b = ev.target.closest('button[data-cantidad]');
        if (!b) return;
        cantidad = Number(b.dataset.cantidad);
        $('mapa-cantidad').querySelectorAll('button').forEach(x => x.classList.toggle('activa', x === b));
        pintar();
    });

    let espera;
    window.addEventListener('resize', () => { clearTimeout(espera); espera = setTimeout(pintar, 150); });
    document.addEventListener('tema', pintar);

    C.iniciarRefresco(m => { mercado = m; pintar(); });
})();
