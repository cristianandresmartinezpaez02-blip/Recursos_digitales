/* Simulador de inversión con precios históricos reales (hasta 1 año):
   compra única o compras periódicas (DCA), comparación entre ambas y explicación
   (texto + gráficos) de por qué la simulación gana o pierde. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    const G = window.Graficos;
    const DIA = 86400000;
    let mercado = null;
    let grafico = null;
    const historiales = {};

    async function historial(id) {
        if (historiales[id]) return historiales[id];
        const r = await fetch(`${APP.base}/api/historial.php?id=${encodeURIComponent(id)}&dias=365`, { cache: 'no-store' });
        const d = await r.json();
        if (!r.ok) throw new Error(d.error);
        return (historiales[id] = d.puntos.map(p => ({ t: p.t * 1000, p: p.p })));
    }

    /** Simula compras de `monto` cada `cadaDias` días (o una sola compra) desde `desde`. */
    function simular(puntos, monto, cadaDias, desde) {
        const serie = puntos.filter(x => x.t >= desde);
        let cantidad = 0, invertido = 0;
        const compras = [];
        let proxima = serie.length ? serie[0].t : 0;
        const evolucion = serie.map(x => {
            if (cadaDias === 'unica' ? compras.length === 0 : x.t >= proxima) {
                cantidad += monto * (1 - APP.comision) / x.p;
                invertido += monto;
                compras.push({ x: x.t, y: x.p });
                if (cadaDias !== 'unica') proxima += cadaDias * DIA;
            }
            return { t: x.t, p: x.p, valor: cantidad * x.p, invertido };
        });
        const final = serie.length ? cantidad * serie[serie.length - 1].p : 0;
        return {
            evolucion, cantidad, invertido, compras: compras.length, puntosCompra: compras, final,
            promedio: cantidad ? invertido * (1 - APP.comision) / cantidad : 0,
        };
    }

    async function calcular(ev) {
        ev?.preventDefault();
        const m = mercado.porId[$('sim-moneda').value];
        const monto = parseFloat($('sim-monto').value);
        const estrategia = $('sim-estrategia').value;
        const dias = Number($('sim-periodo').value);
        if (!m || !(monto > 0)) return;
        if (monto < APP.montoMinimo) {
            C.toast(C.t('js.monto_minimo', { monto: C.dolares(APP.montoMinimo) }), 'alerta');
            $('sim-monto').value = APP.montoMinimo;
            return;
        }
        let puntos;
        try {
            puntos = await historial(m.id);
        } catch (e) {
            return C.toast(C.t('js.error_historial'), 'alerta');
        }
        const desde = Date.now() - dias * DIA;
        const cada = estrategia === 'unica' ? 'unica' : Number(estrategia);
        const r = simular(puntos, monto, cada, desde);
        const ganancia = r.final - r.invertido;
        const pct = r.invertido ? ganancia / r.invertido * 100 : 0;
        const sim = m.symbol.toUpperCase();

        $('r-invertido').textContent = C.dinero(r.invertido);
        $('r-compras').textContent = C.t('js.sim_compras', { n: r.compras });
        $('r-valor').textContent = C.dinero(r.final);
        $('r-cantidad').textContent = `${C.cantidad(r.cantidad)} ${sim}`;
        $('r-ganancia').innerHTML = `<span class="${ganancia >= 0 ? 'sube' : 'baja'}">${ganancia >= 0 ? '+' : '−'}${C.dinero(Math.abs(ganancia))}</span>`;
        $('r-ganancia-pct').innerHTML = C.porcentaje(pct);
        $('r-promedio').textContent = C.precio(r.promedio);
        $('r-actual').textContent = `${C.t('js.precio_hoy')}: ${C.precio(m.current_price)}`;

        // Comparación: la misma cantidad total invertida de una sola vez al inicio.
        const unica = simular(puntos, r.invertido, 'unica', desde);
        const pctUnica = unica.invertido ? (unica.final / unica.invertido - 1) * 100 : 0;
        const btc = m.id === 'bitcoin' ? null : await historial('bitcoin').then(p => simular(p, monto, cada, desde)).catch(() => null);
        const pctBtc = btc && btc.invertido ? (btc.final / btc.invertido - 1) * 100 : null;
        const tarjeta = (titulo, valor, p) => `<div class="dato"><span>${titulo}</span><strong>${C.dinero(valor)}</strong> ${C.porcentaje(p)}</div>`;
        $('sim-comparacion').innerHTML =
            tarjeta(C.t('js.sim_tu_estrategia'), r.final, pct) +
            (estrategia !== 'unica' ? tarjeta(C.t('js.sim_todo_inicio'), unica.final, pctUnica) : '') +
            (btc ? tarjeta(C.t('js.sim_misma_btc'), btc.final, pctBtc) : '');

        pintarGrafico(r.evolucion);
        explicar(m, r, { pct, estrategia, pctUnica, pctBtc });
    }

    /** Por qué la simulación gana o pierde: texto, cuentas en cascada y precio con las compras. */
    function explicar(m, r, extra) {
        if (!r.evolucion.length || !r.invertido) return;
        $('sim-explicacion').hidden = false;
        const sim = m.symbol.toUpperCase();
        const gano = r.final >= r.invertido;
        const inicio = r.evolucion[0].p, hoy = r.evolucion[r.evolucion.length - 1].p;
        const cambioPeriodo = (hoy / inicio - 1) * 100;
        const sobrePromedio = (hoy / r.promedio - 1) * 100;
        const comisiones = r.invertido * APP.comision;
        // Peor y mejor momento: cuándo la inversión estuvo más abajo y más arriba de lo invertido.
        let peor = null, mejor = null;
        r.evolucion.forEach(x => {
            const rel = x.invertido ? (x.valor / x.invertido - 1) * 100 : 0;
            if (!peor || rel < peor.rel) peor = { ...x, rel };
            if (!mejor || rel > mejor.rel) mejor = { ...x, rel };
        });
        const datos = {
            simbolo: C.escapar(sim), promedio: C.precio(r.promedio), hoy: C.precio(hoy), inicio: C.precio(inicio),
            cambio: C.porcentaje(sobrePromedio), cambio_periodo: C.porcentaje(cambioPeriodo), comisiones: C.dinero(comisiones),
        };

        $('sim-exp-titulo').textContent = `📊 ${C.t(gano ? 'js.sim_exp_titulo_gana' : 'js.sim_exp_titulo_pierde')}`;
        $('sim-exp-resumen').innerHTML = `<div class="exp-cifra ${gano ? 'sube' : 'baja'}">${gano ? '+' : '−'}${C.dinero(Math.abs(r.final - r.invertido))}
            <small>${C.porcentaje(extra.pct)}</small></div><p>${C.t(gano ? 'js.sim_exp_gana' : 'js.sim_exp_pierde', datos)}</p>`;

        const razones = [['📈', C.t('js.sim_exp_periodo', datos)]];
        if (extra.estrategia !== 'unica') {
            const promedioMejor = r.promedio < inicio;
            razones.push(['🔁', C.t(promedioMejor ? 'js.sim_exp_dca_bien' : 'js.sim_exp_dca_mal', datos)]);
            razones.push(['⚖️', C.t(extra.pct >= extra.pctUnica ? 'js.sim_exp_vs_unica_mejor' : 'js.sim_exp_vs_unica_peor', { unica: C.porcentaje(extra.pctUnica) })]);
        }
        if (peor && peor.rel < -1) razones.push(['📉', C.t('js.sim_exp_peor', { fecha: C.fecha(peor.t), valor: C.dinero(peor.valor), pct: C.porcentaje(peor.rel) })]);
        if (mejor && mejor.rel > 1) razones.push(['🏔️', C.t('js.sim_exp_mejor', { fecha: C.fecha(mejor.t), valor: C.dinero(mejor.valor), pct: C.porcentaje(mejor.rel) })]);
        razones.push(['💸', C.t('js.sim_exp_comisiones', datos)]);
        if (extra.pctBtc !== null && extra.pctBtc !== undefined) razones.push(['₿', C.t(extra.pct >= extra.pctBtc ? 'js.sim_exp_vs_btc_mejor' : 'js.sim_exp_vs_btc_peor', { btc: C.porcentaje(extra.pctBtc) })]);
        $('sim-exp-razones').innerHTML = razones.map(([ico, texto]) => `<li><span aria-hidden="true">${ico}</span><span>${texto}</span></li>`).join('');
        $('sim-exp-leccion').innerHTML = `<span>💡</span><span>${C.t(gano ? 'js.sim_exp_leccion_gana' : 'js.sim_exp_leccion_pierde')}</span>`;

        // Invertido → comisiones → efecto del precio → valor hoy.
        G.reemplazar($('sim-exp-cascada'), () => G.cascada($('sim-exp-cascada'), [
            { etiqueta: C.t('js.exp_invertido'), valor: r.invertido, total: true },
            { etiqueta: C.t('js.exp_comisiones_corto'), valor: -comisiones },
            { etiqueta: C.t('js.exp_efecto_precio'), valor: r.final - (r.invertido - comisiones) },
            { etiqueta: C.t('js.sim_valor_hoy'), valor: r.final, total: true },
        ]));
        // Precio en el período con cada compra marcada y el precio promedio pagado.
        G.reemplazar($('sim-exp-precio'), () => G.linea($('sim-exp-precio'), r.evolucion.map(x => ({ x: x.t, y: x.p })), {
            etiqueta: sim, dias: 365, color: C.css('--texto-2'),
            lineas: [{ y: r.promedio, etiqueta: C.t('js.sim_tu_promedio'), color: C.css('--serie-2'), punteada: false }],
            marcadores: [{ datos: r.puntosCompra, radio: r.puntosCompra.length > 20 ? 3 : 5, etiqueta: C.t('js.sim_compras_marcas'), color: C.css('--serie-2') }],
        }));
    }

    function pintarGrafico(evolucion) {
        if (grafico) grafico.destroy();
        const acento = C.css('--acento');
        grafico = new Chart($('sim-grafico'), {
            type: 'line',
            data: {
                datasets: [
                    { label: C.t('js.sim_valor'), data: evolucion.map(x => ({ x: x.t, y: x.valor })), borderColor: acento, backgroundColor: acento + '26', fill: true, borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 5, tension: 0.2 },
                    { label: C.t('js.sim_invertido'), data: evolucion.map(x => ({ x: x.t, y: x.invertido })), borderColor: C.css('--texto-3'), borderDash: [6, 4], borderWidth: 2, pointRadius: 0, stepped: true },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false, animation: { duration: 600 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { color: C.css('--texto-2'), boxWidth: 12, boxHeight: 12 } },
                    tooltip: { ...C.tooltipChart(), callbacks: { title: i => C.fecha(i[0].parsed.x), label: i => ` ${i.dataset.label}: ${C.dinero(i.parsed.y)}` } },
                },
                scales: {
                    x: { type: 'time', time: { unit: 'month', displayFormats: C.FORMATOS_FECHA }, grid: { display: false }, border: { display: false }, ticks: { color: C.css('--texto-3'), maxTicksLimit: 8, maxRotation: 0 } },
                    y: { position: 'right', grid: { color: C.css('--borde') }, border: { display: false }, ticks: { color: C.css('--texto-3'), callback: v => C.dinero(v), maxTicksLimit: 6 } },
                },
            },
        });
    }

    // ---------- 🔮 ¿Cuánto podría ganar si compro HOY? ----------
    // Nadie conoce el precio futuro. Con los movimientos diarios del último año se calcula
    // la volatilidad (cuánto suele moverse) y con ella un rango probable: el 80 % de los
    // resultados históricamente parecidos cayó entre el escenario pesimista y el optimista.

    const Z80 = 1.2816; // percentiles 10 y 90 de una distribución normal
    /** Probabilidad acumulada de la normal estándar (aproximación de Abramowitz-Stegun). */
    function normalAcum(x) {
        const t = 1 / (1 + 0.2316419 * Math.abs(x));
        const d = 0.3989423 * Math.exp(-x * x / 2);
        const p = d * t * (0.3193815 + t * (-0.3565638 + t * (1.781478 + t * (-1.821256 + t * 1.330274))));
        return x > 0 ? 1 - p : p;
    }

    async function proyectar(ev) {
        ev?.preventDefault();
        const m = mercado.porId[$('fut-moneda').value];
        const monto = parseFloat($('fut-monto').value);
        const dias = Number($('fut-plazo').value);
        if (!m || !(monto > 0)) return;
        if (monto < APP.montoMinimo) {
            C.toast(C.t('js.monto_minimo', { monto: C.dolares(APP.montoMinimo) }), 'alerta');
            $('fut-monto').value = APP.montoMinimo;
            return;
        }
        let puntos;
        try {
            puntos = await historial(m.id);
        } catch (e) {
            return C.toast(C.t('js.error_historial'), 'alerta');
        }
        // Un precio por día (el histórico de 1 año ya viene diario) y rendimientos logarítmicos.
        const diarios = puntos.filter((p, i) => i === 0 || p.t - puntos[i - 1].t > 12 * 3600000);
        const rend = diarios.slice(1).map((p, i) => Math.log(p.p / diarios[i].p)).filter(Number.isFinite);
        if (rend.length < 20) return C.toast(C.t('js.error_historial'), 'alerta');
        const media = rend.reduce((s, r) => s + r, 0) / rend.length;
        const sigma = Math.sqrt(rend.reduce((s, r) => s + (r - media) ** 2, 0) / (rend.length - 1));

        const p0 = m.current_price; // precio de ESTE momento
        const neto = 1 - APP.comision;
        const cantidad = monto * neto / p0;
        const valorSi = precio => cantidad * precio * neto; // al vender también se paga comisión
        const raiz = Math.sqrt(dias);
        const escenario = z => p0 * Math.exp(z * sigma * raiz);
        const p10 = escenario(-Z80), p50 = p0, p90 = escenario(Z80);
        const tendencia = p0 * Math.exp(media * dias);
        // Probabilidad de terminar ganando: el precio debe subir más que las dos comisiones.
        const umbral = -2 * Math.log(neto);
        const prob = (1 - normalAcum(umbral / (sigma * raiz))) * 100;
        const sim = m.symbol.toUpperCase();

        $('fut-compra').innerHTML = C.t('js.fut_compra', {
            monto: C.dinero(monto), cantidad: C.cantidad(cantidad), simbolo: C.escapar(sim), precio: C.precio(p0),
            hora: C.hora(Date.now()),
        });
        const pintarEsc = (id, precio) => {
            const v = valorSi(precio), g = v - monto;
            $(id).textContent = C.dinero(v);
            $(id + '-sub').innerHTML = `<span class="${g >= 0 ? 'sube' : 'baja'}">${g >= 0 ? '+' : '−'}${C.dinero(Math.abs(g))}</span> ${C.porcentaje(g / monto * 100)}
                <br><small class="texto-3">${C.t('js.fut_si_precio', { simbolo: C.escapar(sim), precio: C.precio(precio) })}</small>`;
        };
        pintarEsc('fut-p10', p10);
        pintarEsc('fut-p50', p50);
        pintarEsc('fut-p90', p90);
        $('fut-prob').textContent = `${C.numero(prob, 0)} %`;
        $('fut-prob-sub').textContent = C.t('js.fut_prob_d', { n: Math.round(prob / 10) });

        const vol = sigma * Math.sqrt(365) * 100;
        const plazoTxt = $('fut-plazo').selectedOptions[0].textContent.trim().toLowerCase();
        $('fut-razones').innerHTML = [
            ['📏', C.t('js.fut_volatilidad', { simbolo: C.escapar(sim), dia: C.numero(sigma * 100, 1), anual: C.numero(vol, 0) })],
            ['🎯', C.t('js.fut_rango', { plazo: plazoTxt, bajo: C.precio(p10), alto: C.precio(p90), vbajo: C.dinero(valorSi(p10)), valto: C.dinero(valorSi(p90)) })],
            ['💸', C.t('js.fut_comisiones', { pct: C.numero(umbral * 100, 2) })],
            ['📈', C.t(media >= 0 ? 'js.fut_tendencia_sube' : 'js.fut_tendencia_baja', { valor: C.dinero(valorSi(tendencia)), cambio: C.porcentaje((tendencia / p0 - 1) * 100) })],
            ['🧭', C.t(m.analisis.veredicto === 'viable' ? 'js.fut_senal_viable' : m.analisis.veredicto === 'no_viable' ? 'js.fut_senal_no' : 'js.fut_senal_neutral', { senal: C.escapar(m.analisis.etiqueta) })],
        ].map(([i, t]) => `<li><span aria-hidden="true">${i}</span><span>${t}</span></li>`).join('');

        pintarAbanico(puntos, p0, sigma, dias, sim);
    }

    /** Precio reciente + abanico de escenarios futuros (pesimista, probable, optimista). */
    function pintarAbanico(puntos, p0, sigma, dias, sim) {
        const ahora = Date.now();
        const atras = Math.max(30, dias * 2) * DIA;
        const pasado = puntos.filter(p => p.t >= ahora - atras).map(p => ({ x: p.t, y: p.p }));
        pasado.push({ x: ahora, y: p0 });
        const paso = Math.max(1, Math.round(dias / 60));
        const curva = z => {
            const c = [];
            for (let d = 0; d <= dias; d += paso) c.push({ x: ahora + d * DIA, y: p0 * Math.exp(z * sigma * Math.sqrt(d)) });
            return c;
        };
        const sube = C.css('--sube'), baja = C.css('--baja'), acento = C.css('--acento');
        G.reemplazar($('fut-grafico'), () => new Chart($('fut-grafico'), {
            type: 'line',
            data: { datasets: [
                { label: C.t('js.fut_historia', { simbolo: sim }), data: pasado, borderColor: C.css('--texto-2'), borderWidth: 2, pointRadius: 0, tension: 0.15 },
                { label: C.t('js.fut_pesimista'), data: curva(-Z80), borderColor: baja, borderWidth: 1.5, borderDash: [5, 4], pointRadius: 0, fill: false },
                { label: C.t('js.fut_optimista'), data: curva(Z80), borderColor: sube, borderWidth: 1.5, borderDash: [5, 4], pointRadius: 0, fill: '-1', backgroundColor: acento + '1f' },
                { label: C.t('js.fut_probable'), data: curva(0), borderColor: acento, borderWidth: 2, pointRadius: 0 },
            ] },
            options: {
                responsive: true, maintainAspectRatio: false, animation: false,
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                plugins: {
                    legend: { labels: { color: C.css('--texto-2'), boxWidth: 12, boxHeight: 12 } },
                    tooltip: { ...C.tooltipChart(), callbacks: { title: i => C.fecha(i[0].parsed.x), label: i => ` ${i.dataset.label}: ${C.precio(i.parsed.y)}` } },
                },
                scales: {
                    x: { type: 'time', time: { minUnit: 'day', displayFormats: C.FORMATOS_FECHA }, grid: { display: false }, border: { display: false }, ticks: { color: C.css('--texto-3'), maxTicksLimit: 7, maxRotation: 0 } },
                    y: { position: 'right', grid: { color: C.css('--borde') }, border: { display: false }, ticks: { color: C.css('--texto-3'), callback: v => C.precio(v), maxTicksLimit: 6 } },
                },
            },
        }));
    }

    $('form-futuro').addEventListener('submit', proyectar);
    ['fut-moneda', 'fut-plazo'].forEach(id => $(id).addEventListener('change', proyectar));

    $('form-sim').addEventListener('submit', calcular);
    ['sim-moneda', 'sim-estrategia', 'sim-periodo'].forEach(id => $(id).addEventListener('change', calcular));
    document.addEventListener('tema', () => { if (mercado) { calcular(); proyectar(); } });

    C.iniciarRefresco(m => {
        const primera = !mercado;
        mercado = m;
        if (primera) {
            const pedida = new URLSearchParams(location.search).get('moneda');
            $('sim-moneda').innerHTML = m.monedas.filter(x => !x.analisis.es_stablecoin)
                .map(x => `<option value="${x.id}">#${x.market_cap_rank} ${C.escapar(x.name)} (${x.symbol.toUpperCase()})</option>`).join('');
            $('sim-moneda').value = m.porId[pedida] ? pedida : 'bitcoin';
            $('fut-moneda').innerHTML = $('sim-moneda').innerHTML;
            $('fut-moneda').value = $('sim-moneda').value;
            calcular();
        }
        // La proyección usa el precio de este minuto: se rehace en cada actualización.
        proyectar();
    });
})();
