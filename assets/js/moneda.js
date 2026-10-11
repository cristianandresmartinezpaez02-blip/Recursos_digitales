/* Ficha de una criptomoneda: pestañas, gráfico con rangos que crece cada minuto,
   análisis, datos, herramientas (conversor, simulador, alerta) y monedas cercanas. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    window.REFRESCO_PROPIO = true; // el refresco arranca cuando carga el historial
    const cabecera = $('cabecera-moneda');
    const id = cabecera.dataset.id;
    const simbolo = cabecera.dataset.simbolo;

    let grafico = null;
    let dias = 1;
    let puntos = [];
    let moneda = null;
    let precioAnterior;
    const NOMBRE_RANGO = { 1: '24 h', 7: '7 d', 30: '30 d', 90: '90 d', 365: C.t('js.1ano') };

    const mostrarPestana = C.iniciarPestanas('pestanas-moneda');
    $('atajo-analisis').addEventListener('click', ev => { ev.preventDefault(); mostrarPestana('analisis', true); scrollTo({ top: 0, behavior: 'smooth' }); });

    // ---------- Gráfico ----------

    function colorTendencia() {
        const sube = puntos.length < 2 || puntos[puntos.length - 1].y >= puntos[0].y;
        return C.css(sube ? '--sube' : '--baja');
    }

    // Línea vertical que sigue al cursor.
    const lineaCruz = {
        id: 'lineaCruz',
        afterDatasetsDraw(chart) {
            const activo = chart.tooltip && chart.tooltip.getActiveElements()[0];
            if (!activo) return;
            const { ctx, chartArea } = chart;
            ctx.save();
            ctx.strokeStyle = C.css('--texto-3');
            ctx.setLineDash([4, 4]);
            ctx.beginPath();
            ctx.moveTo(activo.element.x, chartArea.top);
            ctx.lineTo(activo.element.x, chartArea.bottom);
            ctx.stroke();
            ctx.restore();
        },
    };

    function crearGrafico() {
        if (grafico) grafico.destroy();
        const color = colorTendencia();
        grafico = new Chart($('grafico-precio'), {
            type: 'line',
            data: {
                datasets: [{
                    label: C.t('js.precio'),
                    data: puntos,
                    borderColor: color,
                    backgroundColor: c => {
                        const a = c.chart.chartArea;
                        if (!a) return 'transparent';
                        const g = c.chart.ctx.createLinearGradient(0, a.top, 0, a.bottom);
                        g.addColorStop(0, colorTendencia() + '55');
                        g.addColorStop(1, colorTendencia() + '00');
                        return g;
                    },
                    borderWidth: 2, fill: true, pointRadius: 0, pointHoverRadius: 5,
                    pointHoverBackgroundColor: color, pointHoverBorderColor: C.css('--panel-solido'), pointHoverBorderWidth: 2,
                    tension: 0.25,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false, animation: { duration: 500 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...C.tooltipChart(), displayColors: false,
                        callbacks: {
                            title: items => C.fecha(items[0].parsed.x, dias <= 7 ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' }),
                            label: item => C.precio(item.parsed.y),
                        },
                    },
                },
                scales: {
                    x: { type: 'time', time: { displayFormats: C.FORMATOS_FECHA }, grid: { display: false }, border: { display: false }, ticks: { color: C.css('--texto-3'), maxTicksLimit: 7, maxRotation: 0 } },
                    y: { position: 'right', grid: { color: C.css('--borde') }, border: { display: false }, ticks: { color: C.css('--texto-3'), callback: v => C.precio(v), maxTicksLimit: 6 } },
                },
            },
            plugins: [lineaCruz],
        });
        pintarResumenRango();
    }

    function pintarResumenRango() {
        if (!puntos.length) {
            $('resumen-rango').innerHTML = `<span class="texto-3">${C.t('js.sin_datos_rango')}</span>`;
            return;
        }
        const valores = puntos.map(p => p.y);
        const cambio = (valores[valores.length - 1] / valores[0] - 1) * 100;
        $('resumen-rango').innerHTML = `
            <div><span>${C.t('js.cambio_en', { rango: NOMBRE_RANGO[dias] })}</span>${C.porcentaje(cambio)}</div>
            <div><span>${C.t('js.maximo')}</span><strong>${C.precio(Math.max(...valores))}</strong></div>
            <div><span>${C.t('js.minimo')}</span><strong>${C.precio(Math.min(...valores))}</strong></div>
            <div><span>${C.t('js.puntos')}</span><strong>${puntos.length}</strong></div>`;
    }

    async function cargarHistorial(d) {
        dias = d;
        $('subtitulo-grafico').textContent = d === 1 ? C.t('js.se_actualiza') : NOMBRE_RANGO[d];
        try {
            const r = await fetch(`${APP.base}/api/historial.php?id=${encodeURIComponent(id)}&dias=${d}`, { cache: 'no-store' });
            const datos = await r.json();
            if (!r.ok) throw new Error(datos.error);
            puntos = datos.puntos.map(p => ({ x: p.t * 1000, y: p.p }));
        } catch (e) {
            console.error(e);
            puntos = [];
            C.toast(C.t('js.error_historial'), 'alerta');
        }
        if (tipo === 'velas') await dibujarVelas();
        else crearGrafico();
    }

    // ---------- Velas japonesas ----------

    let tipo = 'linea';
    const hayVelas = () => { try { return !!Chart.registry.getController('candlestick'); } catch (e) { return false; } };

    async function dibujarVelas() {
        let velas = [];
        try {
            const r = await fetch(`${APP.base}/api/ohlc.php?id=${encodeURIComponent(id)}&dias=${dias}`, { cache: 'no-store' });
            const datos = await r.json();
            if (!r.ok) throw new Error(datos.error);
            velas = datos.velas.map(v => ({ x: v.t, o: v.o, h: v.h, l: v.l, c: v.c }));
        } catch (e) {
            C.toast(C.t('js.error_historial'), 'alerta');
        }
        if (grafico) grafico.destroy();
        const sube = C.css('--sube'), baja = C.css('--baja'), neutro = C.css('--texto-3');
        grafico = new Chart($('grafico-precio'), {
            type: 'candlestick',
            data: {
                datasets: [{
                    label: simbolo, data: velas,
                    color: { up: sube, down: baja, unchanged: neutro },
                    borderColors: { up: sube, down: baja, unchanged: neutro },
                    backgroundColors: { up: sube, down: baja, unchanged: neutro },
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false, animation: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...C.tooltipChart(), displayColors: false,
                        callbacks: {
                            title: i => C.fecha(i[0].raw.x, { dateStyle: 'medium', timeStyle: 'short' }),
                            label: i => [`${C.t('js.apertura')}: ${C.precio(i.raw.o)}`, `${C.t('js.maximo')}: ${C.precio(i.raw.h)}`, `${C.t('js.minimo')}: ${C.precio(i.raw.l)}`, `${C.t('js.cierre')}: ${C.precio(i.raw.c)}`],
                        },
                    },
                },
                scales: {
                    x: { type: 'time', time: { displayFormats: C.FORMATOS_FECHA }, grid: { display: false }, border: { display: false }, ticks: { color: C.css('--texto-3'), maxTicksLimit: 7, maxRotation: 0, source: 'auto' } },
                    y: { position: 'right', grid: { color: C.css('--borde') }, border: { display: false }, ticks: { color: C.css('--texto-3'), callback: v => C.precio(v), maxTicksLimit: 6 } },
                },
            },
        });
    }

    $('tipo-grafico').addEventListener('click', ev => {
        const b = ev.target.closest('button[data-tipo]');
        if (!b || b.dataset.tipo === tipo) return;
        if (b.dataset.tipo === 'velas' && !hayVelas()) return C.toast(C.t('js.no_disponible'), 'alerta');
        tipo = b.dataset.tipo;
        $('tipo-grafico').querySelectorAll('button').forEach(x => x.classList.toggle('activa', x === b));
        $('nota-velas').hidden = tipo !== 'velas';
        if (tipo === 'velas') dibujarVelas(); else crearGrafico();
    });

    // ---------- Rendimiento por periodo (barras) ----------

    let graficoRend = null;
    function pintarRendimiento() {
        const lienzo = $('grafico-rendimiento');
        const datos = window.RENDIMIENTO || {};
        if (!lienzo || !Object.keys(datos).length) return;
        const etiquetas = { '24h': '24 h', '7d': '7 d', '14d': '14 d', '30d': '30 d', '60d': '60 d', '200d': '200 d', '1y': C.t('js.1ano') };
        const claves = Object.keys(datos);
        const valores = claves.map(k => datos[k]);
        const colores = valores.map(v => C.css(v >= 0 ? '--sube' : '--baja'));
        // Etiqueta con el valor encima (o debajo) de cada barra.
        const valoresEncima = {
            id: 'valoresEncima',
            afterDatasetsDraw(chart) {
                const { ctx } = chart;
                ctx.save();
                ctx.font = '600 11px Inter, system-ui, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillStyle = C.css('--texto-2');
                chart.getDatasetMeta(0).data.forEach((barra, i) => {
                    const v = valores[i];
                    ctx.textBaseline = v >= 0 ? 'bottom' : 'top';
                    ctx.fillText(`${v >= 0 ? '+' : '−'}${C.numero(Math.abs(v), 1)} %`, barra.x, barra.y + (v >= 0 ? -4 : 4));
                });
                ctx.restore();
            },
        };
        if (graficoRend) graficoRend.destroy();
        graficoRend = new Chart(lienzo, {
            type: 'bar',
            data: { labels: claves.map(k => etiquetas[k] || k), datasets: [{ data: valores, backgroundColor: colores, borderRadius: 4, borderSkipped: false, maxBarThickness: 46 }] },
            options: {
                responsive: true, maintainAspectRatio: false, animation: { duration: 600 },
                layout: { padding: { top: 20, bottom: 4 } },
                plugins: { legend: { display: false }, tooltip: { ...C.tooltipChart(), displayColors: false, callbacks: { label: i => ` ${i.parsed.y >= 0 ? '+' : '−'}${C.numero(Math.abs(i.parsed.y))} %` } } },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: C.css('--texto-2') } },
                    y: { grid: { color: C.css('--borde') }, border: { display: false }, ticks: { color: C.css('--texto-3'), callback: v => `${v} %` }, grace: '12%' },
                },
            },
            plugins: [valoresEncima],
        });
    }
    pintarRendimiento();

    /** Cada minuto vuelve a pedir la serie del rango actual (la última vela termina en la hora
        actual) y la cambia sin destruir el gráfico. */
    async function refrescarGrafico() {
        if (!grafico) return;
        const d = dias, t = tipo;
        try {
            if (t === 'velas') {
                const r = await fetch(`${APP.base}/api/ohlc.php?id=${encodeURIComponent(id)}&dias=${d}`, { cache: 'no-store' });
                const datos = await r.json();
                if (!r.ok || d !== dias || tipo !== 'velas') return;
                grafico.data.datasets[0].data = datos.velas.map(v => ({ x: v.t, o: v.o, h: v.h, l: v.l, c: v.c }));
                grafico.update('none');
                return;
            }
            const r = await fetch(`${APP.base}/api/historial.php?id=${encodeURIComponent(id)}&dias=${d}`, { cache: 'no-store' });
            const datos = await r.json();
            if (!r.ok || d !== dias || tipo !== 'linea') return;
            puntos = datos.puntos.map(p => ({ x: p.t * 1000, y: p.p }));
            grafico.data.datasets[0].data = puntos;
            grafico.data.datasets[0].borderColor = colorTendencia();
            grafico.data.datasets[0].pointHoverBackgroundColor = colorTendencia();
            grafico.update();
            pintarResumenRango();
        } catch (e) {
            console.error(e);
        }
    }

    $('rango-grafico').addEventListener('click', ev => {
        const b = ev.target.closest('button[data-dias]');
        if (!b) return;
        $('rango-grafico').querySelectorAll('button').forEach(x => x.classList.toggle('activa', x === b));
        cargarHistorial(Number(b.dataset.dias));
    });

    document.addEventListener('tema', () => {
        if (tipo === 'velas') dibujarVelas(); else crearGrafico();
        pintarRendimiento();
        if (moneda) pintarAnalisis(moneda.analisis);
    });
    document.querySelectorAll('[data-ir-pestana]').forEach(a => a.addEventListener('click', ev => {
        ev.preventDefault();
        mostrarPestana(a.dataset.irPestana, true);
    }));

    // ---------- Análisis ----------

    function pintarAnalisis(a) {
        $('panel-veredicto').className = `panel veredicto veredicto-${a.veredicto}`;
        $('atajo-analisis').className = `panel veredicto veredicto-${a.veredicto}`;
        $('medidor-puntaje').innerHTML = C.medidor(a.puntaje, { ancho: 210, etiqueta: C.t('js.puntaje') });
        $('etiqueta').className = `insignia grande ${a.veredicto}`;
        $('etiqueta').textContent = a.etiqueta;
        $('atajo-etiqueta').className = `insignia grande ${a.veredicto}`;
        $('atajo-etiqueta').textContent = `${a.etiqueta} · ${a.puntaje}/100`;
        $('insignia-cabecera').className = `insignia ${a.veredicto}`;
        $('insignia-cabecera').textContent = `${a.etiqueta} · ${a.puntaje}`;
        $('resumen-analisis').textContent = a.resumen;
        $('chip-riesgo').textContent = `${C.t('js.riesgo')}: ${a.riesgo}`;
        $('factores').innerHTML = a.factores.length ? a.factores.map(f => `<div class="factor">
            <span>${C.escapar(f.nombre)}</span><span class="valor">${f.puntos}/${f.max}</span>
            <div class="pista"><span style="width:${f.puntos / f.max * 100}%"></span></div></div>`).join('')
            : `<p class="texto-3">${C.t('js.stable_sin_factores')}</p>`;
        const lista = (items, vacio) => (items.length ? items : [vacio]).map(x => `<li>${C.escapar(x)}</li>`).join('');
        $('lista-pros').innerHTML = lista(a.pros, C.t('js.sin_pros'));
        $('lista-contras').innerHTML = lista(a.contras, C.t('js.sin_contras'));
    }

    // ---------- Datos ----------

    function pintarDatos(m) {
        const dato = (k, v) => `<div class="dato"><span>${k}</span><strong>${v}</strong></div>`;
        $('datos-mercado').innerHTML = [
            dato(C.t('js.capitalizacion'), C.grande(m.market_cap)),
            dato(C.t('js.volumen'), C.grande(m.total_volume)),
            dato(C.t('js.vol_cap'), m.market_cap ? `${C.numero(m.total_volume / m.market_cap * 100)} %` : '—'),
            dato(C.t('js.fdv'), C.grande(m.fully_diluted_valuation)),
            dato(C.t('js.max_24h'), C.precio(m.high_24h)),
            dato(C.t('js.min_24h'), C.precio(m.low_24h)),
        ].join('');

        const circ = m.circulating_supply, max = m.max_supply || m.total_supply;
        $('oferta').innerHTML = `<div style="display:flex;justify-content:space-between;gap:1rem;font-size:.9rem;flex-wrap:wrap">
                <span class="texto-2">${C.t('js.circulante')}</span>
                <strong class="tabular">${circ ? C.numeroCompacto(circ) : '—'} / ${m.max_supply ? C.numeroCompacto(m.max_supply) : '∞'} ${C.escapar(simbolo)}</strong></div>
            ${max ? `<div class="progreso"><span style="width:${Math.min(100, circ / max * 100)}%"></span></div>
            <span class="nota">${C.t(m.max_supply ? 'js.oferta_max_pct' : 'js.oferta_total_pct', { n: C.numero(circ / max * 100, 1) })}</span>`
            : `<p class="nota">${C.t('js.sin_oferta_max')}</p>`}`;

        $('records').innerHTML = `
            <div class="dato"><span>${C.t('js.ath')} · ${m.ath_date ? C.fecha(new Date(m.ath_date)) : ''}</span>
                <strong>${C.precio(m.ath)}</strong> ${C.porcentaje(m.ath_change_percentage, 1)}</div>
            <div class="dato"><span>${C.t('js.atl')} · ${m.atl_date ? C.fecha(new Date(m.atl_date)) : ''}</span>
                <strong>${C.precio(m.atl)}</strong> ${C.porcentaje(m.atl_change_percentage, 0)}</div>`;

        const f = m.high_24h > m.low_24h ? (m.current_price - m.low_24h) / (m.high_24h - m.low_24h) : 0.5;
        $('marcador-dia').style.left = `${Math.max(0, Math.min(1, f)) * 100}%`;
        $('minimo-dia').textContent = `${C.t('js.min')} ${C.precio(m.low_24h)}`;
        $('maximo-dia').textContent = `${C.t('js.max')} ${C.precio(m.high_24h)}`;

        $('cambios-periodo').innerHTML = [
            ['1h', m.price_change_percentage_1h_in_currency], ['24h', m.price_change_percentage_24h],
            ['7d', m.price_change_percentage_7d_in_currency], ['30d', m.price_change_percentage_30d_in_currency],
            [C.t('js.1a'), m.price_change_percentage_1y_in_currency],
        ].map(([k, v]) => C.pildora(v, k)).join('');

        $('simulador-pasado').innerHTML = [
            ['24 h', m.price_change_percentage_24h], ['7 d', m.price_change_percentage_7d_in_currency],
            ['30 d', m.price_change_percentage_30d_in_currency], [C.t('js.1ano'), m.price_change_percentage_1y_in_currency],
        ].map(([k, v]) => v === null || v === undefined ? dato(k, '—')
            : `<div class="dato"><span>${k}</span><strong class="${v >= 0 ? 'sube' : 'baja'}">${C.dinero(1000 * (1 + v / 100))}</strong></div>`).join('');
    }

    function pintarSimilares(mercado, m) {
        const i = mercado.monedas.findIndex(x => x.id === m.id);
        const vecinos = mercado.monedas.slice(Math.max(0, i - 3), i + 4).filter(x => x.id !== m.id).slice(0, 6);
        $('similares').innerHTML = vecinos.map(x => `<a href="${C.urlMoneda(x.id)}">
            <span class="moneda" style="pointer-events:none"><img src="${C.escapar(x.image)}" width="24" height="24" alt=""><span class="nombre">${C.escapar(x.name)}</span></span>
            <strong class="tabular">${C.precio(x.current_price)}</strong>
            <span style="display:flex;justify-content:space-between;align-items:center;gap:.4rem">${C.porcentaje(x.price_change_percentage_24h)} ${C.insignia(x.analisis)}</span>
            ${C.sparkline(x.sparkline, 150, 32)}</a>`).join('');
    }

    // ---------- Herramientas ----------

    let ultimoCampo = 'cripto';
    function convertir() {
        if (!moneda) return;
        const p = moneda.current_price;
        if (ultimoCampo === 'cripto') {
            const v = parseFloat($('conv-cripto').value);
            $('conv-usd').value = isNaN(v) ? '' : +(v * p).toFixed(2);
        } else {
            const v = parseFloat($('conv-usd').value);
            $('conv-cripto').value = isNaN(v) ? '' : +(v / p).toPrecision(8);
        }
    }
    $('conv-cripto').addEventListener('input', () => { ultimoCampo = 'cripto'; convertir(); });
    $('conv-usd').addEventListener('input', () => { ultimoCampo = 'usd'; convertir(); });

    $('form-alerta').addEventListener('submit', ev => {
        ev.preventDefault();
        const valor = parseFloat($('alerta-precio').value);
        if (!(valor > 0) || !moneda) return;
        const lista = C.leerAlertas();
        lista.push({ id: Date.now(), moneda: id, simbolo, nombre: moneda.name, imagen: moneda.image, condicion: $('alerta-condicion').value, precio: valor, creada: Date.now(), disparada: null });
        C.guardarAlertas(lista);
        C.toast(C.t('js.alerta_creada', { simbolo, precio: C.precio(valor) }), 'exito');
        $('alerta-precio').value = '';
        try { if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission(); } catch (e) { /* */ }
    });

    $('leer-mas')?.addEventListener('click', ev => {
        const d = $('descripcion');
        d.classList.toggle('recortada');
        ev.target.textContent = d.classList.contains('recortada') ? ev.target.dataset.mas : ev.target.dataset.menos;
    });

    // ---------- Actualización cada minuto ----------

    function actualizar(mercado) {
        const m = mercado.porId[id];
        if (!m) return;
        const primera = !moneda;
        moneda = m;
        const el = $('precio-actual');
        el.textContent = C.precio(m.current_price);
        const btc = mercado.porId.bitcoin;
        $('precio-btc').textContent = btc ? `${C.cantidad(m.current_price / btc.current_price)} BTC` : '—';
        C.destellar(el, precioAnterior, m.current_price);
        if (!primera && precioAnterior !== m.current_price) {
            const dif = (m.current_price / precioAnterior - 1) * 100;
            C.toast(C.t(dif >= 0 ? 'js.precio_subio' : 'js.precio_bajo', { simbolo, precio: C.precio(m.current_price), dif: C.numero(Math.abs(dif), 3) }), dif >= 0 ? 'exito' : 'alerta');
        }
        precioAnterior = m.current_price;
        document.title = `${C.precio(m.current_price)} · ${m.name}`;
        if (primera && !$('alerta-precio').value) $('alerta-precio').placeholder = C.numero(m.current_price, m.current_price < 1 ? 6 : 2);

        pintarDatos(m);
        pintarAnalisis(m.analisis);
        pintarSimilares(mercado, m);
        convertir();
        if (!primera) refrescarGrafico();
    }

    if (window.ANALISIS_INICIAL) pintarAnalisis(window.ANALISIS_INICIAL);
    cargarHistorial(1).then(() => C.iniciarRefresco(actualizar));
})();
