/* Calculadoras de trader: tamaño de posición por riesgo, apalancamiento y precio de
   liquidación, ganancia / pérdida con comisiones, precio promedio de compras y interés compuesto. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    const n = id => { const v = parseFloat($(id).value); return Number.isFinite(v) ? v : 0; };
    const dato = (titulo, valor, clase = '', nota = '') => `<div class="dato"><span>${titulo}</span><strong class="${clase}">${valor}</strong>${nota ? `<small class="texto-3">${nota}</small>` : ''}</div>`;
    const firmado = v => `${v >= 0 ? '+' : '−'}${C.dinero(Math.abs(v))}`;
    let grafico = null;

    // ---------- Tamaño de posición ----------
    function posicion() {
        const capital = n('p-capital'), riesgoPct = n('p-riesgo'), entrada = n('p-entrada'), stop = n('p-stop'), rr = n('p-rr');
        if (!(capital > 0 && entrada > 0 && stop > 0) || stop === entrada) { $('r-posicion').innerHTML = `<p class="alerta">${C.t('js.calc_err_stop')}</p>`; return; }
        const largo = stop < entrada;
        const riesgo = capital * riesgoPct / 100;
        const distancia = Math.abs(entrada - stop) / entrada;
        const valor = riesgo / distancia;
        const unidades = valor / entrada;
        const objetivo = largo ? entrada * (1 + distancia * rr) : entrada * (1 - distancia * rr);
        const palanca = valor / capital;
        $('r-posicion').innerHTML =
            dato(C.t('js.calc_tipo'), largo ? '▲ ' + C.t('js.dia_largo') : '▼ ' + C.t('js.dia_corto')) +
            dato(C.t('js.calc_arriesgas_max'), C.dinero(riesgo), 'baja') +
            dato(C.t('js.calc_distancia_stop'), `${C.numero(distancia * 100, 2)} %`) +
            dato(C.t('js.calc_tamano'), C.dinero(valor), '', `${C.numero(palanca * 100, 0)} % ${C.t('js.calc_del_capital')}`) +
            dato(C.t('js.calc_unidades'), C.cantidad(unidades)) +
            dato(C.t('js.calc_objetivo', { rr }), C.precio(objetivo), 'sube', firmado(riesgo * rr)) +
            (palanca > 1 ? dato(C.t('js.calc_necesita_palanca'), `x${C.numero(palanca, 1)}`, 'baja', C.t('js.calc_palanca_aviso')) : '');
    }

    // ---------- Apalancamiento ----------
    function apalancamiento() {
        const largo = $('a-lado').value === 'largo', entrada = n('a-entrada'), margen = n('a-margen'), palanca = Math.max(1, n('a-palanca')), mm = n('a-mantenimiento') / 100;
        if (!(entrada > 0 && margen > 0)) return;
        const tamano = margen * palanca;
        const liquidacion = largo ? entrada * (1 - 1 / palanca + mm) : entrada * (1 + 1 / palanca - mm);
        const movimiento = Math.abs(liquidacion / entrada - 1) * 100;
        $('r-apalancamiento').innerHTML =
            dato(C.t('js.calc_tamano'), C.dinero(tamano)) +
            dato(C.t('js.calc_liquidacion'), liquidacion > 0 ? C.precio(liquidacion) : '—', 'baja') +
            dato(C.t('js.calc_mov_liquidacion'), `${largo ? '−' : '+'}${C.numero(movimiento, 2)} %`, movimiento < 5 ? 'baja' : '', C.t(movimiento < 5 ? 'js.calc_muy_cerca' : 'js.calc_distancia_ok'));
        const escenarios = [-20, -10, -5, -2, 2, 5, 10, 20];
        $('r-escenarios').innerHTML = `<thead><tr><th>${C.t('js.calc_si_precio')}</th><th class="num">${C.t('js.calc_precio')}</th><th class="num">${C.t('js.calc_resultado')}</th><th class="num">ROE</th></tr></thead><tbody>` +
            escenarios.map(p => {
                const precio = entrada * (1 + p / 100);
                const liquidado = largo ? precio <= liquidacion : precio >= liquidacion;
                const pnl = liquidado ? -margen : tamano * (p / 100) * (largo ? 1 : -1);
                return `<tr><td>${C.porcentaje(p, 0)}</td><td class="num">${C.precio(precio)}</td>
                    <td class="num">${liquidado ? `<span class="baja">☠️ ${C.t('js.calc_liquidado')}</span>` : `<span class="${pnl >= 0 ? 'sube' : 'baja'}">${firmado(pnl)}</span>`}</td>
                    <td class="num">${C.porcentaje(pnl / margen * 100, 0)}</td></tr>`;
            }).join('') + '</tbody>';
    }

    // ---------- Ganancia / pérdida ----------
    function ganancia() {
        const largo = $('g-lado').value === 'largo', entrada = n('g-entrada'), salida = n('g-salida'), monto = n('g-monto'), com = n('g-comision') / 100;
        if (!(entrada > 0 && salida > 0 && monto > 0)) return;
        const bruto = monto * (salida / entrada - 1) * (largo ? 1 : -1);
        const comisiones = monto * com * (1 + salida / entrada);
        const neto = bruto - comisiones;
        const equilibrio = largo ? entrada * (1 + com) / (1 - com) : entrada * (1 - com) / (1 + com);
        $('r-ganancia').innerHTML =
            dato(C.t('js.calc_bruto'), firmado(bruto), bruto >= 0 ? 'sube' : 'baja') +
            dato(C.t('js.calc_comisiones'), C.dinero(comisiones), 'baja') +
            dato(C.t('js.calc_neto'), firmado(neto), neto >= 0 ? 'sube' : 'baja', C.porcentaje(neto / monto * 100, 2).replace(/<[^>]+>/g, '')) +
            dato(C.t('js.calc_equilibrio_precio'), C.precio(equilibrio), '', C.t('js.calc_equilibrio_d'));
    }

    // ---------- Precio promedio ----------
    function filaCompra(precio = '', monto = '') {
        const div = document.createElement('div');
        div.className = 'fila-compra';
        div.innerHTML = `<input type="number" min="0" step="any" placeholder="${C.escapar(C.t('js.calc_precio'))}" value="${precio}" aria-label="${C.escapar(C.t('js.calc_precio'))}" data-pr="precio">
            <input type="number" min="0" step="any" placeholder="${C.escapar(C.t('js.calc_monto'))}" value="${monto}" aria-label="${C.escapar(C.t('js.calc_monto'))}" data-pr="monto">
            <button type="button" class="boton-icono mini-cerrar" aria-label="${C.escapar(C.t('js.dia_borrar'))}">✕</button>`;
        div.querySelector('button').addEventListener('click', () => { div.remove(); promedio(); });
        $('pr-filas').appendChild(div);
    }
    function promedio() {
        let invertido = 0, unidades = 0;
        $('pr-filas').querySelectorAll('.fila-compra').forEach(f => {
            const p = parseFloat(f.querySelector('[data-pr=precio]').value), m = parseFloat(f.querySelector('[data-pr=monto]').value);
            if (p > 0 && m > 0) { invertido += m; unidades += m / p; }
        });
        if (!unidades) { $('r-promedio').innerHTML = ''; return; }
        const medio = invertido / unidades;
        const actual = precioSeleccionado();
        $('r-promedio').innerHTML = dato(C.t('js.calc_precio_medio'), C.precio(medio), 'sube') + dato(C.t('js.calc_invertido'), C.dinero(invertido)) + dato(C.t('js.calc_unidades'), C.cantidad(unidades)) +
            (actual ? dato(C.t('js.calc_valor_hoy'), C.dinero(unidades * actual), unidades * actual >= invertido ? 'sube' : 'baja', C.porcentaje((actual / medio - 1) * 100, 2).replace(/<[^>]+>/g, '')) : '');
    }

    // ---------- Interés compuesto ----------
    function compuesto() {
        const capital = n('i-capital'), tasa = n('i-tasa') / 100, periodos = Math.min(600, Math.max(1, Math.round(n('i-periodos')))), aporte = n('i-aporte');
        const serie = [capital], aportado = [capital];
        for (let i = 1; i <= periodos; i++) { serie.push(serie[i - 1] * (1 + tasa) + aporte); aportado.push(aportado[i - 1] + aporte); }
        const final = serie[periodos], puesto = aportado[periodos];
        $('r-compuesto').innerHTML = dato(C.t('js.calc_final'), C.dinero(final), 'sube') + dato(C.t('js.calc_aportado'), C.dinero(puesto)) +
            dato(C.t('js.calc_intereses'), firmado(final - puesto), final >= puesto ? 'sube' : 'baja') +
            (tasa > 0 ? dato(C.t('js.calc_duplicar'), C.t('js.calc_periodos_n', { n: C.numero(Math.log(2) / Math.log(1 + tasa), 1) }), '', C.t('js.calc_regla72')) : '');
        grafico?.destroy();
        grafico = new Chart($('i-grafico'), {
            type: 'line',
            data: {
                labels: serie.map((_, i) => i),
                datasets: [
                    { label: C.t('js.calc_final'), data: serie, borderColor: C.css('--sube'), backgroundColor: C.css('--sube') + '22', fill: true, pointRadius: 0, borderWidth: 2 },
                    { label: C.t('js.calc_aportado'), data: aportado, borderColor: C.css('--texto-3'), borderDash: [5, 5], pointRadius: 0, borderWidth: 1.5 },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false, animation: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { labels: { color: C.css('--texto-2'), boxWidth: 12, boxHeight: 12 } }, tooltip: { ...C.tooltipChart(), callbacks: { title: i => C.t('js.calc_periodo', { n: i[0].label }), label: i => ` ${i.dataset.label}: ${C.dinero(i.parsed.y)}` } } },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: C.css('--texto-3'), maxTicksLimit: 8 } },
                    y: { position: 'right', grid: { color: C.css('--borde') }, border: { display: false }, ticks: { color: C.css('--texto-3'), callback: v => C.dinero(v), maxTicksLimit: 6 } },
                },
            },
        });
    }

    const CALCULOS = { posicion, apalancamiento, ganancia, compuesto };
    document.querySelectorAll('form.calc').forEach(f => {
        f.addEventListener('input', () => CALCULOS[f.dataset.calc]());
        f.addEventListener('submit', ev => ev.preventDefault());
    });
    $('pr-filas').addEventListener('input', promedio);
    $('pr-agregar').addEventListener('click', () => filaCompra());
    C.iniciarPestanas('pestanas-calc', id => { if (id === 'compuesto') compuesto(); });
    document.addEventListener('tema', () => { if (!$('compuesto').hidden) compuesto(); });

    // ---------- Precio en vivo de una moneda ----------
    let mercado = null;
    const precioSeleccionado = () => (mercado && $('c-moneda').value ? mercado.porId[$('c-moneda').value]?.current_price : null);
    $('c-moneda').addEventListener('change', () => {
        const p = precioSeleccionado();
        if (!p) return;
        const redondear = v => Number(v.toPrecision(6));
        $('p-entrada').value = redondear(p); $('p-stop').value = redondear(p * 0.95);
        $('a-entrada').value = redondear(p);
        $('g-entrada').value = redondear(p); $('g-salida').value = redondear(p * 1.1);
        posicion(); apalancamiento(); ganancia(); promedio();
        C.toast(C.t('js.calc_precio_usado', { simbolo: mercado.porId[$('c-moneda').value].symbol.toUpperCase(), precio: C.precio(p) }));
    });
    C.obtenerMercado().then(m => {
        mercado = m;
        $('c-moneda').insertAdjacentHTML('beforeend', m.monedas.map(x => `<option value="${x.id}">#${x.market_cap_rank} ${C.escapar(x.name)} (${x.symbol.toUpperCase()})</option>`).join(''));
    }).catch(() => {});

    filaCompra(100, 500); filaCompra(80, 500);
    posicion(); apalancamiento(); ganancia(); promedio();
})();
