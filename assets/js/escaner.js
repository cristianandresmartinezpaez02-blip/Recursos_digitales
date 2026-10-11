/* Escáner técnico: indicadores del top 100 calculados con los precios de los últimos
   7 días (un punto cada ~3 h), filtros rápidos y mapa de correlación. Se actualiza cada minuto. */
(function () {
    'use strict';
    const C = window.Cripto, T = window.Tecnico;
    const $ = C.$;
    const MONEDAS_CORRELACION = 12;

    let filas = [];
    let filtro = '';
    let orden = { campo: 'rank', asc: true };
    let umbralVolatil = Infinity;

    /** Indicadores de cada moneda. Con ~56 puntos se usan medias de 10 y 30 periodos. */
    function analizar(mercado) {
        filas = mercado.monedas
            .filter(m => !m.analisis.es_stablecoin && m.sparkline && m.sparkline.length >= 35)
            .map(m => {
                const r = T.resumen(m.sparkline, { corta: 10, larga: 30 });
                return {
                    m, r,
                    rank: m.market_cap_rank, precio: m.current_price,
                    c1h: m.price_change_percentage_1h_in_currency, c24h: m.price_change_percentage_24h, c7d: m.price_change_percentage_7d_in_currency,
                    rsi: r.rsi, vol: r.volatilidad, puntaje: r.puntaje,
                };
            });
        // "Alta volatilidad" = el 20 % de monedas que más se mueven.
        const vols = filas.map(f => f.vol).sort((a, b) => b - a);
        umbralVolatil = vols[Math.floor(vols.length * 0.2)] ?? Infinity;
    }

    const cumple = (f, clave) => {
        switch (clave) {
            case '': return true;
            case 'compra': return f.r.puntaje >= 0.15;
            case 'venta': return f.r.puntaje <= -0.15;
            case 'volatil': return f.vol >= umbralVolatil;
            default: return f.r.eventos.includes(clave);
        }
    };

    function barraRsi(v) {
        if (v === null) return '—';
        const clase = v < 30 ? 'sube' : v > 70 ? 'baja' : '';
        return `<span class="rsi-celda ${clase}" title="RSI ${C.numero(v, 1)}"><span class="rsi-pista"><i style="left:${v}%"></i></span><strong>${C.numero(v, 0)}</strong></span>`;
    }

    function senal(r) {
        const clase = r.veredicto.startsWith('compra') ? 'viable' : r.veredicto.startsWith('venta') ? 'no_viable' : 'neutral';
        const icono = clase === 'viable' ? '▲' : clase === 'no_viable' ? '▼' : '●';
        return `<span class="insignia ${clase}">${icono} ${C.t('js.tec_v_' + r.veredicto)}</span>`;
    }

    function pintarTabla() {
        const q = $('esc-buscar').value.trim().toLowerCase();
        const lista = filas
            .filter(f => cumple(f, filtro) && (!q || f.m.name.toLowerCase().includes(q) || f.m.symbol.toLowerCase().includes(q)))
            .sort((a, b) => {
                const x = a[orden.campo] ?? -Infinity, y = b[orden.campo] ?? -Infinity;
                return orden.asc ? x - y : y - x;
            });
        $('esc-tabla').innerHTML = lista.map(f => `<tr>
            <td class="texto-3">${f.rank}</td>
            <td>${C.celdaMoneda(f.m, 22)}</td>
            <td class="num"><strong>${C.precio(f.precio)}</strong></td>
            <td class="num">${C.porcentaje(f.c1h, 1)}</td>
            <td class="num">${C.porcentaje(f.c24h, 1)}</td>
            <td class="num">${C.porcentaje(f.c7d, 1)}</td>
            <td>${barraRsi(f.rsi)}</td>
            <td>${C.sparkline(f.m.sparkline, 96, 30)}</td>
            <td class="num">${C.numero(f.vol, 2)} %</td>
            <td>${senal(f.r)}</td>
            <td class="eventos-celda">${f.r.eventos.map(e => `<span class="chip mini evento-${e}" title="${C.escapar(C.t('js.tec_ev_' + e))}">${C.t('js.tec_ev_' + e)}</span>`).join('') || '<span class="texto-3">—</span>'}</td>
        </tr>`).join('') || `<tr><td colspan="11" class="cargando">${C.t('js.sin_resultados')}</td></tr>`;
        document.querySelectorAll('.tabla-escaner th[data-orden]').forEach(th => {
            th.classList.toggle('ordenada', th.dataset.orden === orden.campo);
            th.classList.toggle('asc', th.dataset.orden === orden.campo && orden.asc);
        });
    }

    function pintarResumen() {
        const compra = filas.filter(f => f.r.puntaje >= 0.15).length;
        const venta = filas.filter(f => f.r.puntaje <= -0.15).length;
        const rsis = filas.map(f => f.rsi).filter(v => v !== null);
        const rsiMedio = rsis.reduce((a, b) => a + b, 0) / (rsis.length || 1);
        $('k-compra').textContent = compra;
        $('k-venta').textContent = venta;
        $('k-rsi').textContent = C.numero(rsiMedio, 0);
        $('k-rsi-d').textContent = C.t(rsiMedio < 40 ? 'js.esc_mercado_frio' : rsiMedio > 60 ? 'js.esc_mercado_caliente' : 'js.esc_mercado_normal');
        $('k-eventos').textContent = filas.reduce((s, f) => s + f.r.eventos.filter(e => !['sobreventa', 'sobrecompra'].includes(e)).length, 0);
        document.querySelectorAll('[data-cuenta]').forEach(el => {
            const n = filas.filter(f => cumple(f, el.dataset.cuenta)).length;
            el.textContent = el.dataset.cuenta === '' ? '' : `(${n})`;
        });
    }

    /** Matriz de correlación de los rendimientos de las monedas más grandes. */
    function pintarCorrelacion() {
        const top = filas.slice().sort((a, b) => a.rank - b.rank).slice(0, MONEDAS_CORRELACION);
        const rend = top.map(f => T.rendimientos(f.m.sparkline));
        const cabeza = `<tr><th></th>${top.map(f => `<th scope="col"><img src="${C.escapar(f.m.image)}" alt="" width="18" height="18"><span>${C.escapar(f.m.symbol.toUpperCase())}</span></th>`).join('')}</tr>`;
        const cuerpo = top.map((f, i) => `<tr><th scope="row"><img src="${C.escapar(f.m.image)}" alt="" width="18" height="18"> ${C.escapar(f.m.symbol.toUpperCase())}</th>${top.map((g, j) => {
            const c = i === j ? 1 : T.correlacion(rend[i], rend[j]);
            const color = c >= 0 ? 'var(--sube)' : 'var(--baja)';
            const fuerza = Math.round(Math.abs(c) * 70);
            return `<td style="background:color-mix(in srgb, ${color} ${fuerza}%, transparent)" title="${C.escapar(f.m.symbol.toUpperCase())} / ${C.escapar(g.m.symbol.toUpperCase())}: ${C.numero(c, 2)}">${C.numero(c, 2)}</td>`;
        }).join('')}</tr>`).join('');
        $('esc-corr').innerHTML = `<thead>${cabeza}</thead><tbody>${cuerpo}</tbody>`;
    }

    // ---------- Eventos ----------

    $('esc-filtros').addEventListener('click', ev => {
        const b = ev.target.closest('[data-filtro]');
        if (!b) return;
        filtro = b.dataset.filtro;
        $('esc-filtros').querySelectorAll('.chip').forEach(x => x.classList.toggle('activo', x === b));
        pintarTabla();
    });
    $('esc-buscar').addEventListener('input', pintarTabla);
    document.querySelectorAll('.tabla-escaner th[data-orden]').forEach(th => th.addEventListener('click', () => {
        const campo = th.dataset.orden;
        orden = { campo, asc: orden.campo === campo ? !orden.asc : campo === 'rank' };
        pintarTabla();
    }));
    C.iniciarPestanas('pestanas-escaner');

    C.iniciarRefresco(mercado => {
        analizar(mercado);
        pintarResumen();
        pintarTabla();
        pintarCorrelacion();
    });
})();
