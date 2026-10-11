/* Gráficos compartidos (Chart.js) con el estilo de la página:
   - linea: serie de precios con líneas de referencia (compra, equilibrio…) y marcadores.
   - cascada: de dónde sale el resultado (invertido → efecto del precio → comisiones → final).
   - barras: variaciones positivas / negativas (verde / rojo). */
(function () {
    'use strict';
    const C = window.Cripto;

    const ejes = (formato, tiempo = true, dias = 30) => ({
        x: tiempo
            ? { type: 'time', time: { minUnit: dias <= 1 ? 'minute' : 'hour', displayFormats: C.FORMATOS_FECHA }, grid: { display: false }, border: { display: false }, ticks: { color: C.css('--texto-3'), maxTicksLimit: 6, maxRotation: 0 } }
            : { grid: { display: false }, border: { display: false }, ticks: { color: C.css('--texto-2'), maxRotation: 0, autoSkip: false } },
        y: { position: 'right', grid: { color: C.css('--borde') }, border: { display: false }, ticks: { color: C.css('--texto-3'), maxTicksLimit: 6, callback: v => formato(v) } },
    });

    /**
     * @param canvas  elemento <canvas>
     * @param puntos  [{x: ms, y: valor}]
     * @param o       { etiqueta, formato, color, dias, lineas: [{y, etiqueta, color, punteada}],
     *                  marcadores: [{x, y, etiqueta, color} o {datos: [{x, y}…], radio, etiqueta, color}] }
     */
    function linea(canvas, puntos, o = {}) {
        const formato = o.formato || C.precio;
        const color = o.color || C.css(puntos.length > 1 && puntos[puntos.length - 1].y < puntos[0].y ? '--baja' : '--sube');
        const x0 = puntos.length ? puntos[0].x : Date.now(), x1 = puntos.length ? puntos[puntos.length - 1].x : Date.now();
        const datasets = [{
            label: o.etiqueta || C.t('js.precio'), data: puntos, borderColor: color, backgroundColor: color + '1f', fill: true,
            borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, tension: 0.15, order: 3,
        }];
        (o.lineas || []).forEach(l => datasets.push({
            label: l.etiqueta, data: [{ x: x0, y: l.y }, { x: x1, y: l.y }], borderColor: l.color || C.css('--texto-3'),
            borderDash: l.punteada === false ? [] : [6, 4], borderWidth: 1.5, pointRadius: 0, pointHoverRadius: 0, fill: false, order: 2,
        }));
        (o.marcadores || []).forEach(m => datasets.push({
            label: m.etiqueta, data: m.datos || [{ x: m.x, y: m.y }], type: 'scatter', pointRadius: m.radio || 7, pointHoverRadius: (m.radio || 7) + 2,
            pointBackgroundColor: m.color, pointBorderColor: C.css('--panel-solido'), pointBorderWidth: 2, order: 1,
        }));
        return new Chart(canvas, {
            type: 'line',
            data: { datasets },
            options: {
                responsive: true, maintainAspectRatio: false, animation: false,
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                plugins: {
                    legend: { display: datasets.length > 1, labels: { color: C.css('--texto-2'), boxWidth: 12, boxHeight: 12, filter: i => i.datasetIndex > 0 || datasets.length > 1 } },
                    tooltip: { ...C.tooltipChart(), callbacks: {
                        title: i => C.fecha(i[0].parsed.x, { dateStyle: 'medium', timeStyle: (o.dias || 30) <= 7 ? 'short' : undefined }),
                        label: i => ` ${i.dataset.label}: ${formato(i.parsed.y)}`,
                    } },
                },
                scales: ejes(formato, true, o.dias || 30),
            },
        });
    }

    /**
     * Cascada: pasos [{etiqueta, valor, total: bool}]. Un paso "total" es una barra desde 0;
     * los demás suben (verde) o bajan (rojo) desde donde quedó el anterior.
     */
    function cascada(canvas, pasos, formato = C.dinero) {
        let acumulado = 0;
        const barras = pasos.map(p => {
            if (p.total) { acumulado = p.valor; return [0, p.valor]; }
            const desde = acumulado;
            acumulado += p.valor;
            return [desde, acumulado];
        });
        const colores = pasos.map(p => C.css(p.total ? '--serie-1' : p.valor >= 0 ? '--sube' : '--baja'));
        return new Chart(canvas, {
            type: 'bar',
            data: { labels: pasos.map(p => p.etiqueta), datasets: [{ data: barras, backgroundColor: colores, borderRadius: 4, borderSkipped: false, maxBarThickness: 56 }] },
            options: {
                responsive: true, maintainAspectRatio: false, animation: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { ...C.tooltipChart(), callbacks: { label: i => {
                        const p = pasos[i.dataIndex];
                        return ` ${p.total ? '' : p.valor >= 0 ? '+' : '−'}${formato(Math.abs(p.valor))}`;
                    } } },
                },
                scales: (() => {
                    const e = ejes(formato, false);
                    // Empieza cerca del menor valor: así se ven las diferencias pequeñas.
                    const valores = barras.flat();
                    const min = Math.min(...valores.filter(v => v > 0)), max = Math.max(...valores);
                    e.y.min = Math.max(0, min - (max - min) * 1.5);
                    return e;
                })(),
            },
        });
    }

    /** Barras horizontales de variación (%) con color según el signo. */
    function barrasCambio(canvas, items) {
        return new Chart(canvas, {
            type: 'bar',
            data: { labels: items.map(i => i.etiqueta), datasets: [{
                data: items.map(i => i.valor), backgroundColor: items.map(i => C.css(i.valor >= 0 ? '--sube' : '--baja')),
                borderRadius: 4, borderSkipped: false, maxBarThickness: 18,
            }] },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false, animation: false,
                plugins: { legend: { display: false }, tooltip: { ...C.tooltipChart(), callbacks: { label: i => ` ${i.parsed.x >= 0 ? '+' : ''}${C.numero(i.parsed.x)} %` } } },
                scales: {
                    x: { grid: { color: C.css('--borde') }, border: { display: false }, ticks: { color: C.css('--texto-3'), callback: v => `${v} %`, maxTicksLimit: 5 } },
                    y: { grid: { display: false }, border: { display: false }, ticks: { color: C.css('--texto-2') } },
                },
            },
        });
    }

    /** Destruye el gráfico anterior de un canvas antes de dibujar otro. */
    function reemplazar(canvas, crear) {
        const viejo = Chart.getChart(canvas);
        if (viejo) viejo.destroy();
        return crear();
    }

    window.Graficos = { linea, cascada, barrasCambio, reemplazar };
})();
