/* Comparador: rendimiento de 7 días (desde el sparkline, sin peticiones extra),
   radar de factores y tabla de métricas. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    const MAX = 4;
    const SERIES = ['--serie-1', '--serie-2', '--serie-3', '--serie-4'];

    let mercado = null;
    let graficoLineas = null;
    let graficoRadar = null;
    let ids = (new URLSearchParams(location.search).get('ids') || '').split(',').filter(Boolean);
    if (!ids.length) ids = ['bitcoin', 'ethereum', 'solana'];
    if (ids.length === 1 && ids[0] !== 'bitcoin') ids.unshift('bitcoin');

    // Cada moneda conserva su color mientras siga en la comparación.
    const colorDe = {};
    function asignarColores() {
        Object.keys(colorDe).forEach(k => { if (!ids.includes(k)) delete colorDe[k]; });
        ids.forEach(k => { if (!colorDe[k]) colorDe[k] = SERIES.find(s => !Object.values(colorDe).includes(s)); });
    }
    const guardarUrl = () => history.replaceState(null, '', `?ids=${ids.join(',')}`);

    // Etiqueta directa al final de cada línea.
    const etiquetasFinales = {
        id: 'etiquetasFinales',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            ctx.save();
            ctx.font = '600 12px Inter, system-ui, sans-serif';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = C.css('--texto');
            chart.data.datasets.forEach((ds, i) => {
                const meta = chart.getDatasetMeta(i);
                const ultimo = meta.data[meta.data.length - 1];
                if (ultimo && !meta.hidden) ctx.fillText(ds.label, ultimo.x + 6, ultimo.y);
            });
            ctx.restore();
        },
    };

    function serieRendimiento(m) {
        const s = m.sparkline;
        const fin = new Date(m.last_updated).getTime() || Date.now();
        const paso = (7 * 24 * 3600 * 1000) / (s.length - 1);
        return s.map((v, i) => ({ x: fin - (s.length - 1 - i) * paso, y: (v / s[0] - 1) * 100 }));
    }

    function pintarGraficos() {
        const monedas = ids.map(i => mercado.porId[i]).filter(Boolean);
        const texto3 = C.css('--texto-3'), borde = C.css('--borde');

        if (graficoLineas) graficoLineas.destroy();
        graficoLineas = new Chart($('grafico-comparar'), {
            type: 'line',
            data: {
                datasets: monedas.map(m => ({
                    label: m.symbol.toUpperCase(), data: serieRendimiento(m),
                    borderColor: C.css(colorDe[m.id]), backgroundColor: C.css(colorDe[m.id]),
                    borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, tension: 0.25,
                })),
            },
            options: {
                responsive: true, maintainAspectRatio: false, animation: false,
                layout: { padding: { right: 48 } },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', align: 'start', labels: { color: C.css('--texto-2'), boxWidth: 12, boxHeight: 12 } },
                    tooltip: {
                        ...C.tooltipChart(),
                        callbacks: {
                            title: items => C.fecha(items[0].parsed.x, { dateStyle: 'medium', timeStyle: 'short' }),
                            label: i => ` ${i.dataset.label}: ${i.parsed.y >= 0 ? '+' : '−'}${C.numero(Math.abs(i.parsed.y))} %`,
                        },
                    },
                },
                scales: {
                    x: { type: 'time', time: { unit: 'day', displayFormats: C.FORMATOS_FECHA }, grid: { display: false }, border: { display: false }, ticks: { color: texto3, maxTicksLimit: 7, maxRotation: 0 } },
                    y: { grid: { color: borde }, border: { display: false }, ticks: { color: texto3, callback: v => `${v > 0 ? '+' : ''}${v} %` } },
                },
            },
            plugins: [etiquetasFinales],
        });

        const conFactores = monedas.filter(m => m.analisis.factores.length);
        if (graficoRadar) graficoRadar.destroy();
        graficoRadar = new Chart($('grafico-radar'), {
            type: 'radar',
            data: {
                labels: conFactores.length ? conFactores[0].analisis.factores.map(f => f.nombre) : [],
                datasets: conFactores.map(m => ({
                    label: m.symbol.toUpperCase(),
                    data: m.analisis.factores.map(f => Math.round(f.puntos / f.max * 100)),
                    borderColor: C.css(colorDe[m.id]), backgroundColor: C.css(colorDe[m.id]) + '22',
                    pointBackgroundColor: C.css(colorDe[m.id]), borderWidth: 2, pointRadius: 3,
                })),
            },
            options: {
                responsive: true, maintainAspectRatio: false, animation: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: C.css('--texto-2'), boxWidth: 12, boxHeight: 12 } },
                    tooltip: { ...C.tooltipChart(), callbacks: { label: i => ` ${i.dataset.label}: ${i.parsed.r} %` } },
                },
                scales: { r: { min: 0, max: 100, ticks: { display: false, stepSize: 25 }, grid: { color: borde }, angleLines: { color: borde }, pointLabels: { color: C.css('--texto-2'), font: { size: 11 } } } },
            },
        });
    }

    const FILAS = [
        ['js.precio', m => m.current_price, C.precio, null],
        ['js.puntaje', m => m.analisis.puntaje, v => `${v}/100`, 'max'],
        ['js.senal', m => m.analisis, a => C.insignia(a), null],
        ['js.riesgo', m => m.analisis.riesgo, v => C.escapar(v), null],
        ['js.puesto_col', m => m.market_cap_rank, v => `#${v}`, 'min'],
        ['js.capitalizacion', m => m.market_cap, C.grande, 'max'],
        ['js.volumen', m => m.total_volume, C.grande, 'max'],
        ['js.cambio_24h', m => m.price_change_percentage_24h, v => C.porcentaje(v), 'max'],
        ['js.cambio_7d', m => m.price_change_percentage_7d_in_currency, v => C.porcentaje(v), 'max'],
        ['js.cambio_30d', m => m.price_change_percentage_30d_in_currency, v => C.porcentaje(v), 'max'],
        ['js.cambio_1a', m => m.price_change_percentage_1y_in_currency, v => C.porcentaje(v), 'max'],
        ['js.dist_ath', m => m.ath_change_percentage, v => C.porcentaje(v, 1), null],
        ['js.ultimos_7d', m => m.sparkline, s => C.sparkline(s, 120, 34), null],
    ];

    function pintarTabla() {
        const monedas = ids.map(i => mercado.porId[i]).filter(Boolean);
        const cabecera = `<thead><tr><th>${C.t('js.metrica')}</th>${monedas.map(m => `<th>
            <span class="chip-serie" style="display:inline-flex;align-items:center;gap:.4rem"><i style="background:var(${colorDe[m.id]})"></i>${C.celdaMoneda(m, 22)}</span></th>`).join('')}</tr></thead>`;
        const cuerpo = FILAS.map(([clave, valor, formato, mejor]) => {
            const valores = monedas.map(valor);
            let idxMejor = -1;
            if (mejor && monedas.length > 1) {
                const nums = valores.map(v => (typeof v === 'number' ? v : null)).filter(v => v !== null);
                if (nums.length) idxMejor = valores.indexOf(mejor === 'max' ? Math.max(...nums) : Math.min(...nums));
            }
            return `<tr><td class="texto-2">${C.t(clave)}</td>${valores.map((v, i) =>
                `<td class="${i === idxMejor ? 'celda-mejor' : ''}">${formato(v)}</td>`).join('')}</tr>`;
        }).join('');
        $('tabla-comparar').innerHTML = cabecera + `<tbody>${cuerpo}</tbody>`;
    }

    function pintarSelector() {
        $('seleccionadas').innerHTML = ids.map(i => {
            const m = mercado.porId[i];
            return m ? `<button class="chip activo chip-serie" data-quitar="${m.id}" title="${C.t('js.quitar')}">
                <i style="background:var(${colorDe[m.id]})"></i>${C.escapar(m.name)} <span aria-hidden="true">✕</span></button>` : '';
        }).join('');
        const sel = $('agregar-moneda');
        sel.disabled = ids.length >= MAX;
        sel.innerHTML = `<option value="">${ids.length >= MAX ? C.t('js.max_4') : C.t('js.agregar_moneda')}</option>` +
            mercado.monedas.filter(m => !ids.includes(m.id))
                .map(m => `<option value="${m.id}">#${m.market_cap_rank} ${C.escapar(m.name)} (${m.symbol.toUpperCase()})</option>`).join('');
    }

    function pintarTodo() {
        ids = ids.filter(i => mercado.porId[i]).slice(0, MAX);
        asignarColores();
        pintarSelector();
        pintarGraficos();
        pintarTabla();
    }

    $('agregar-moneda').addEventListener('change', ev => {
        if (!ev.target.value || ids.length >= MAX) return;
        ids.push(ev.target.value);
        guardarUrl();
        pintarTodo();
    });
    $('seleccionadas').addEventListener('click', ev => {
        const b = ev.target.closest('[data-quitar]');
        if (!b) return;
        if (ids.length <= 1) return C.toast(C.t('js.minimo_una'));
        ids = ids.filter(i => i !== b.dataset.quitar);
        guardarUrl();
        pintarTodo();
    });
    document.addEventListener('tema', () => mercado && pintarGraficos());

    C.iniciarRefresco(m => { mercado = m; pintarTodo(); });
})();
