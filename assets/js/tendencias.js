/* Tendencias y sentimiento: miedo y codicia, dominancia y monedas en tendencia. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    let extras = null;
    let mercado = null;
    let graficoFng = null;
    let graficoDom = null;

    function pintarFng() {
        const f = extras && extras.miedo_codicia;
        if (!f) { $('fng-clasificacion').textContent = C.t('js.no_disponible'); return; }
        $('fng-medidor').innerHTML = C.medidor(f.valor, { ancho: 200, etiqueta: C.t('js.fng') });
        $('fng-clasificacion').textContent = f.clasificacion;
        $('fng-comparacion').innerHTML = C.t('js.fng_comparacion', { ayer: f.ayer ?? '—', semana: f.semana ?? '—' });

        if (graficoFng) graficoFng.destroy();
        const color = C.css('--acento');
        graficoFng = new Chart($('grafico-fng'), {
            type: 'line',
            data: { datasets: [{ label: C.t('js.fng'), data: f.serie.map(p => ({ x: p.t * 1000, y: p.v })), borderColor: color, backgroundColor: color + '22', fill: true, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, tension: 0.3 }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, tooltip: { ...C.tooltipChart(), displayColors: false, callbacks: { title: i => C.fecha(i[0].parsed.x), label: i => ` ${i.parsed.y} / 100` } } },
                scales: {
                    x: { type: 'time', time: { unit: 'day', displayFormats: C.FORMATOS_FECHA }, grid: { display: false }, border: { display: false }, ticks: { color: C.css('--texto-3'), maxTicksLimit: 6, maxRotation: 0 } },
                    y: { min: 0, max: 100, grid: { color: C.css('--borde') }, border: { display: false }, ticks: { color: C.css('--texto-3'), stepSize: 25 } },
                },
            },
        });
    }

    function pintarDominancia() {
        const g = mercado && mercado.global;
        if (!g || !g.dominancia) return;
        const entradas = Object.entries(g.dominancia).slice(0, 4);
        const otras = Math.max(0, 100 - entradas.reduce((s, [, v]) => s + v, 0));
        const etiquetas = [...entradas.map(([k]) => k.toUpperCase()), C.t('js.otras')];
        const valores = [...entradas.map(([, v]) => v), otras];
        const colores = [...['--serie-1', '--serie-2', '--serie-3', '--serie-4'].map(C.css), C.css('--gris-medio')];
        if (graficoDom) graficoDom.destroy();
        graficoDom = new Chart($('grafico-dominancia'), {
            type: 'doughnut',
            data: { labels: etiquetas, datasets: [{ data: valores, backgroundColor: colores, borderColor: C.css('--panel-solido'), borderWidth: 2 }] },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '64%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: C.css('--texto-2'), boxWidth: 12, boxHeight: 12, generateLabels: ch => ch.data.labels.map((l, i) => ({ text: `${l} ${C.numero(valores[i], 1)} %`, fillStyle: colores[i], strokeStyle: colores[i], fontColor: C.css('--texto-2'), index: i })) } },
                    tooltip: { ...C.tooltipChart(), callbacks: { label: i => ` ${i.label}: ${C.numero(i.parsed, 1)} %` } },
                },
            },
        });
    }

    function pintarTendencias() {
        const lista = (extras && extras.tendencias) || [];
        if (!lista.length) { $('tendencias').innerHTML = `<p class="texto-3">${C.t('js.no_disponible')}</p>`; return; }
        $('tendencias').innerHTML = lista.map((x, i) => {
            const enTop = mercado && mercado.porId[x.id];
            const href = enTop ? C.urlMoneda(x.id) : `https://www.coingecko.com/${APP.idioma}/coins/${encodeURIComponent(x.id)}`;
            return `<a class="tarjeta-tendencia" href="${href}" ${enTop ? '' : 'target="_blank" rel="noopener"'}>
                <div class="cabeza"><img class="logo-moneda" src="${C.escapar(x.imagen)}" alt="">
                    <span class="nombre">${C.escapar(x.nombre)}</span><span class="puesto-tendencia">#${i + 1}</span></div>
                <span class="texto-3" style="font-size:.78rem">${C.escapar(x.simbolo.toUpperCase())} · ${x.rank ? C.t('js.puesto', { n: x.rank }) : C.t('js.sin_ranking')}</span>
                <span class="precio">${C.precio(x.precio)}</span>
                ${C.porcentaje(x.cambio_24h)}
                ${x.sparkline ? `<img class="mini-grafico" src="${C.escapar(x.sparkline)}" alt="" loading="lazy">` : ''}
            </a>`;
        }).join('');
    }

    async function cargarExtras() {
        try {
            const r = await fetch(`${APP.base}/api/extras.php`, { cache: 'no-store' });
            extras = await r.json();
        } catch (e) {
            extras = null;
        }
        pintarFng();
        pintarTendencias();
    }

    document.addEventListener('tema', () => { pintarFng(); pintarDominancia(); });

    C.iniciarRefresco(m => {
        const primera = !mercado;
        mercado = m;
        pintarDominancia();
        if (primera) cargarExtras();
    });
})();
