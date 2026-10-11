/* Ficha de moneda · pestaña Técnico: precio con medias móviles y Bandas de Bollinger,
   RSI, MACD, resumen de señales (compra / neutral / venta), soportes, resistencias y eventos.
   Se dibuja la primera vez que se abre la pestaña (los gráficos ocultos no tienen tamaño). */
(function () {
    'use strict';
    const C = window.Cripto, T = window.Tecnico;
    const $ = C.$;
    const seccion = $('tecnico');
    if (!seccion || !T) return;
    const id = $('cabecera-moneda').dataset.id;

    // Marcos temporales: se piden los precios por hora (30 d) o por día (1 año) y se agrupan en velas.
    const MARCOS = {
        h4: { dias: 30, segundos: 4 * 3600, formato: { day: '2-digit', month: '2-digit', hour: '2-digit' } },
        d1: { dias: 365, segundos: 86400, formato: { day: '2-digit', month: 'short' } },
    };
    const VISIBLES = 120; // velas que se muestran (los indicadores se calculan con toda la serie)
    let marco = 'h4';
    const capas = { corta: true, larga: true, bollinger: true };
    const graficos = {};
    const cache = {};
    let datos = null;

    /** Agrupa los puntos {t (s), p} en periodos y se queda con el último precio de cada uno (cierre). */
    function agrupar(puntos, segundos) {
        const mapa = new Map();
        puntos.forEach(({ t, p }) => mapa.set(Math.floor(t / segundos) * segundos, p));
        return [...mapa.entries()].sort((a, b) => a[0] - b[0]).map(([t, p]) => ({ t: t * 1000, p }));
    }

    async function cargar() {
        const m = MARCOS[marco];
        if (!cache[marco]) {
            const r = await fetch(`${APP.base}/api/historial.php?id=${encodeURIComponent(id)}&dias=${m.dias}`, { cache: 'no-store' });
            const j = await r.json();
            if (!r.ok || !j.puntos) throw new Error(j.error || 'API');
            cache[marco] = agrupar(j.puntos, m.segundos);
        }
        const serie = cache[marco];
        const cierres = serie.map(x => x.p);
        datos = {
            fechas: serie.map(x => x.t),
            cierres,
            corta: T.sma(cierres, 20),
            larga: T.sma(cierres, 50),
            bb: T.bollinger(cierres, 20, 2),
            rsi: T.rsi(cierres, 14),
            macd: T.macd(cierres),
            resumen: T.resumen(cierres),
            niveles: T.nivelesClave(cierres.slice(-VISIBLES)),
        };
    }

    // ---------- Gráficos ----------

    const recorte = arr => arr.slice(-VISIBLES);
    const ejeX = () => ({
        grid: { display: false }, border: { display: false },
        ticks: { color: C.css('--texto-3'), maxTicksLimit: 6, maxRotation: 0, callback(v) { return C.fecha(this.getLabelForValue(v), MARCOS[marco].formato); } },
    });
    const ejeY = (callback, extra = {}) => ({ position: 'right', grid: { color: C.css('--borde') }, border: { display: false }, ticks: { color: C.css('--texto-3'), maxTicksLimit: 5, callback }, ...extra });
    const linea = (label, data, color, extra = {}) => ({ type: 'line', label, data, borderColor: color, backgroundColor: 'transparent', borderWidth: 1.6, pointRadius: 0, tension: 0.15, spanGaps: true, ...extra });
    const opcionesBase = () => ({
        responsive: true, maintainAspectRatio: false, animation: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: false }, tooltip: { ...C.tooltipChart(), callbacks: { title: i => C.fecha(datos.fechas.slice(-VISIBLES)[i[0].dataIndex], { dateStyle: 'medium', timeStyle: 'short' }) } } },
    });

    function graficoPrecio() {
        const acento = C.css('--acento');
        const series = [linea(C.t('js.precio'), recorte(datos.cierres), acento, { borderWidth: 2.2, fill: false })];
        if (capas.corta) series.push(linea('SMA 20', recorte(datos.corta), C.css('--serie-2')));
        if (capas.larga) series.push(linea('SMA 50', recorte(datos.larga), C.css('--serie-4')));
        if (capas.bollinger) {
            const gris = C.css('--texto-3');
            series.push(linea(C.t('js.tec_bb_sup'), recorte(datos.bb.sup), gris, { borderDash: [4, 4], borderWidth: 1 }));
            series.push(linea(C.t('js.tec_bb_inf'), recorte(datos.bb.inf), gris, { borderDash: [4, 4], borderWidth: 1, fill: '-1', backgroundColor: C.css('--acento-suave') }));
        }
        // Soportes (sube) y resistencias (baja) como líneas horizontales.
        const n = recorte(datos.cierres).length;
        datos.niveles.soportes.forEach(s => series.push(linea(C.t('js.tec_soporte'), new Array(n).fill(s.precio), C.css('--sube'), { borderDash: [2, 6], borderWidth: 1 })));
        datos.niveles.resistencias.forEach(s => series.push(linea(C.t('js.tec_resistencia'), new Array(n).fill(s.precio), C.css('--baja'), { borderDash: [2, 6], borderWidth: 1 })));
        const opciones = opcionesBase();
        opciones.plugins.tooltip.callbacks.label = i => ` ${i.dataset.label}: ${C.precio(i.parsed.y)}`;
        opciones.plugins.tooltip.filter = i => i.datasetIndex < 5;
        opciones.scales = { x: ejeX(), y: ejeY(v => C.precio(v)) };
        return { type: 'line', data: { labels: recorte(datos.fechas), datasets: series }, options: opciones };
    }

    function graficoRsi() {
        const n = recorte(datos.rsi).length;
        const opciones = opcionesBase();
        opciones.plugins.tooltip.callbacks.label = i => ` RSI: ${C.numero(i.parsed.y, 1)}`;
        opciones.plugins.tooltip.filter = i => i.datasetIndex === 0; // las líneas 30/70 no salen en el globo
        opciones.scales = { x: ejeX(), y: ejeY(v => v, { min: 0, max: 100, ticks: { color: C.css('--texto-3'), stepSize: 30, callback: v => v } }) };
        return {
            type: 'line',
            data: {
                labels: recorte(datos.fechas),
                datasets: [
                    linea('RSI', recorte(datos.rsi), C.css('--acento'), { borderWidth: 1.8 }),
                    linea('70', new Array(n).fill(70), C.css('--baja'), { borderDash: [4, 4], borderWidth: 1 }),
                    linea('30', new Array(n).fill(30), C.css('--sube'), { borderDash: [4, 4], borderWidth: 1 }),
                ],
            },
            options: opciones,
        };
    }

    function graficoMacd() {
        const hist = recorte(datos.macd.hist);
        const opciones = opcionesBase();
        opciones.plugins.tooltip.callbacks.label = i => ` ${i.dataset.label}: ${C.numero(i.parsed.y, 4)}`;
        opciones.scales = { x: ejeX(), y: ejeY(v => C.numeroCompacto(v)) };
        return {
            type: 'bar',
            data: {
                labels: recorte(datos.fechas),
                datasets: [
                    { type: 'bar', label: C.t('js.tec_histograma'), data: hist, backgroundColor: hist.map(v => C.css(v >= 0 ? '--sube' : '--baja')), borderWidth: 0, order: 2 },
                    linea('MACD', recorte(datos.macd.linea), C.css('--acento'), { order: 1 }),
                    linea(C.t('js.tec_senal'), recorte(datos.macd.senal), C.css('--serie-2'), { order: 1 }),
                ],
            },
            options: opciones,
        };
    }

    function dibujar(nombre, config) {
        graficos[nombre]?.destroy();
        graficos[nombre] = new Chart($('tec-' + nombre), config);
    }

    // ---------- Resumen ----------

    const NOMBRES = { rsi: 'RSI (14)', macd: 'MACD', sma_corta: 'SMA 20', sma_larga: 'SMA 50', cruce: () => C.t('js.tec_cruce'), bollinger: () => C.t('js.tec_bollinger') };

    function explicar(v) {
        const r = datos.resumen;
        switch (v.id) {
            case 'rsi': return C.t(v.valor < 30 ? 'js.tec_rsi_sobreventa' : v.valor > 70 ? 'js.tec_rsi_sobrecompra' : 'js.tec_rsi_neutral', { v: C.numero(v.valor, 1) });
            case 'macd': return C.t(v.valor > 0 ? 'js.tec_macd_alcista' : 'js.tec_macd_bajista');
            case 'sma_corta': return C.t(r.precio > v.valor ? 'js.tec_sma_encima' : 'js.tec_sma_debajo', { n: 20, v: C.precio(v.valor) });
            case 'sma_larga': return C.t(r.precio > v.valor ? 'js.tec_sma_encima' : 'js.tec_sma_debajo', { n: 50, v: C.precio(v.valor) });
            case 'cruce': return C.t(v.valor > 0 ? 'js.tec_cruce_alcista' : 'js.tec_cruce_bajista');
            case 'bollinger': return C.t(v.valor < 0.05 ? 'js.tec_bb_abajo' : v.valor > 0.95 ? 'js.tec_bb_arriba' : 'js.tec_bb_medio', { v: C.numero(v.valor * 100, 0) });
        }
        return '';
    }

    function pintarResumen() {
        const r = datos.resumen;
        if (!r) { $('tec-veredicto').textContent = C.t('js.no_disponible'); return; }
        const valor = Math.round((r.puntaje + 1) * 50);
        $('tec-medidor').innerHTML = C.medidor(valor, { max: 100, ancho: 200, etiqueta: C.t('js.tec_resumen') });
        const claseVeredicto = r.veredicto.startsWith('compra') ? 'viable' : r.veredicto.startsWith('venta') ? 'no_viable' : 'neutral';
        $('tec-veredicto').innerHTML = `<span class="insignia grande ${claseVeredicto}">${C.t('js.tec_v_' + r.veredicto)}</span>`;
        const etiquetaVoto = { 1: ['viable', '▲ ' + C.t('js.tec_compra')], 0: ['neutral', '● ' + C.t('js.tec_neutral')], '-1': ['no_viable', '▼ ' + C.t('js.tec_venta')] };
        $('tec-votos').innerHTML = r.votos.map(v => {
            const [clase, texto] = etiquetaVoto[v.voto];
            const nombre = typeof NOMBRES[v.id] === 'function' ? NOMBRES[v.id]() : NOMBRES[v.id];
            return `<div class="tec-voto"><div><strong>${nombre}</strong><small>${explicar(v)}</small></div><span class="insignia ${clase}">${texto}</span></div>`;
        }).join('');

        const nivel = (s, tipo) => `<div class="fila-info"><span class="${tipo === 'soporte' ? 'sube' : 'baja'}">${tipo === 'soporte' ? '▼' : '▲'} ${C.t('js.tec_' + tipo)}</span>
            <span><strong>${C.precio(s.precio)}</strong> <small class="texto-3">${C.porcentaje((s.precio / r.precio - 1) * 100, 1)} · ${C.t('js.tec_toques', { n: s.toques })}</small></span></div>`;
        const { soportes, resistencias } = datos.niveles;
        $('tec-niveles').innerHTML = [...resistencias.slice().reverse().map(s => nivel(s, 'resistencia')),
            `<div class="fila-info tec-actual"><span>● ${C.t('js.precio')}</span><strong>${C.precio(r.precio)}</strong></div>`,
            ...soportes.map(s => nivel(s, 'soporte'))].join('');

        $('tec-eventos').innerHTML = r.eventos.length
            ? r.eventos.map(e => `<span class="chip evento-${e}">${C.t('js.tec_ev_' + e)}</span>`).join('')
            : `<span class="texto-3" style="font-size:.85rem">${C.t('js.tec_sin_eventos')}</span>`;
        $('tec-subtitulo').textContent = `${C.t('js.tec_velas', { n: recorte(datos.cierres).length })} · ${C.t('js.tec_vol', { v: C.numero(r.volatilidad ?? 0, 2) })}`;
    }

    async function pintar() {
        try {
            await cargar();
            dibujar('precio', graficoPrecio());
            dibujar('rsi', graficoRsi());
            dibujar('macd', graficoMacd());
            pintarResumen();
        } catch (e) {
            console.error(e);
            $('tec-veredicto').textContent = C.t('js.sin_conexion');
        }
    }

    // ---------- Eventos ----------

    $('tec-marco').addEventListener('click', ev => {
        const b = ev.target.closest('button[data-marco]');
        if (!b || b.dataset.marco === marco) return;
        marco = b.dataset.marco;
        $('tec-marco').querySelectorAll('button').forEach(x => x.classList.toggle('activa', x === b));
        pintar();
    });
    $('tec-capas').addEventListener('click', ev => {
        const b = ev.target.closest('[data-capa]');
        if (!b || !datos) return;
        capas[b.dataset.capa] = !capas[b.dataset.capa];
        b.classList.toggle('activo', capas[b.dataset.capa]);
        dibujar('precio', graficoPrecio());
    });
    document.addEventListener('tema', () => { if (datos && !seccion.hidden) { dibujar('precio', graficoPrecio()); dibujar('rsi', graficoRsi()); dibujar('macd', graficoMacd()); } });

    // Se dibuja la primera vez que la pestaña se hace visible.
    let iniciado = false;
    const alMostrar = () => { if (!seccion.hidden && !iniciado) { iniciado = true; pintar(); } };
    new MutationObserver(alMostrar).observe(seccion, { attributes: true, attributeFilter: ['hidden'] });
    alMostrar();
})();
