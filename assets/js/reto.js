/* Reto 7 días: se repiten los precios reales de la última semana (sparkline de
   CoinGecko, un punto cada ~3 h) en unos 2 minutos. Compra, vende y compara tu
   resultado con haber mantenido Bitcoin o con la mejor moneda de la semana. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    const MS_POR_PASO = 2000; // a velocidad x1
    const MAX_MONEDAS = 30;

    let mercado = null;
    let monedas = [];   // monedas jugables
    let pasos = 0;      // puntos disponibles en la semana
    let juego = null;
    let temporizador = null;
    let velocidad = 2;
    let grafico = null;

    // ---------- Preparación ----------

    function prepararMonedas() {
        monedas = mercado.monedas
            .filter(m => !m.analisis.es_stablecoin && m.sparkline && m.sparkline.length >= 40)
            .slice(0, MAX_MONEDAS);
        pasos = Math.min(...monedas.map(m => m.sparkline.length));
        $('empezar').disabled = !monedas.length;
    }

    const precioEn = (m, paso) => m.sparkline[Math.min(paso, m.sparkline.length - 1)];
    const precioActual = m => precioEn(m, juego.paso);

    function fechaPaso(paso) {
        const fin = Date.now();
        const inicio = fin - 7 * 24 * 3600 * 1000;
        return inicio + (fin - inicio) * (paso / (pasos - 1));
    }

    function valorPortafolio(paso = juego.paso) {
        return Object.entries(juego.posiciones).reduce((s, [id, cant]) => s + cant * precioEn(monedas.find(m => m.id === id), paso), 0);
    }

    // ---------- Flujo del juego ----------

    function empezar() {
        juego = {
            paso: 0, efectivo: APP.saldoInicial, posiciones: {}, seleccion: monedas[0].id, operaciones: 0, comisiones: 0, pausado: false,
            costos: {},        // dólares pagados por cada posición abierta (para saber si se gana o se pierde)
            iaCompra: {},      // qué decía la IA cuando se abrió cada posición
            ventas: [],        // cada venta, para el resumen final
            ia: { favor: { n: 0, res: 0 }, contra: { n: 0, res: 0 } },
            cacheIa: null,
        };
        $('r-explica').innerHTML = '';
        $('pantalla-inicio').hidden = true;
        $('pantalla-fin').hidden = true;
        $('pantalla-juego').hidden = false;
        marcarVelocidad();
        crearGrafico();
        pintar();
        programar();
    }

    function programar() {
        clearInterval(temporizador);
        temporizador = setInterval(avanzar, MS_POR_PASO / velocidad);
    }

    function avanzar() {
        if (juego.pausado) return;
        juego.paso++;
        if (juego.paso >= pasos - 1) {
            juego.paso = pasos - 1;
            pintar();
            return terminar();
        }
        pintar();
    }

    function terminar() {
        clearInterval(temporizador);
        const final = juego.efectivo + valorPortafolio(pasos - 1);
        const ganancia = (final / APP.saldoInicial - 1) * 100;
        const btc = monedas.find(m => m.id === 'bitcoin') || monedas[0];
        const cambio = m => (precioEn(m, pasos - 1) / precioEn(m, 0) - 1) * 100;
        const holdBtc = cambio(btc);
        const mejor = monedas.reduce((a, b) => (cambio(b) > cambio(a) ? b : a));
        const peor = monedas.reduce((a, b) => (cambio(b) < cambio(a) ? b : a));

        // ⭐ ganar dinero · ⭐ superar a Bitcoin · ⭐ lograr al menos la mitad que la mejor moneda.
        const leGanoABtc = ganancia > holdBtc;
        let estrellas = 0;
        if (ganancia > 0) estrellas++;
        if (leGanoABtc) estrellas++;
        if (ganancia > 0 && ganancia >= cambio(mejor) * 0.5) estrellas++;
        let titulo;
        if (ganancia <= 0) titulo = leGanoABtc ? 'js.reto_fin_btc' : 'js.reto_fin_0';
        else titulo = estrellas >= 3 ? 'js.reto_fin_3' : leGanoABtc ? 'js.reto_fin_2' : 'js.reto_fin_1';

        $('pantalla-juego').hidden = true;
        $('pantalla-fin').hidden = false;
        $('f-estrellas').innerHTML = [0, 1, 2].map(i => `<span class="${i < estrellas ? '' : 'apagada'}">⭐</span>`).join('');
        $('f-titulo').textContent = C.t(titulo);
        $('f-resultado').innerHTML = `<span class="${ganancia >= 0 ? 'sube' : 'baja'}">${ganancia >= 0 ? '+' : '−'}${C.numero(Math.abs(ganancia))} %</span>`;
        $('f-detalle').textContent = C.t('js.reto_detalle', { final: C.dinero(final), ops: juego.operaciones });
        const tarjeta = (titulo, m, v) => `<div class="dato"><span>${titulo}</span>
            <strong class="moneda" style="pointer-events:none"><img src="${C.escapar(m.image)}" width="18" height="18" alt="">${C.escapar(m.symbol.toUpperCase())}</strong> ${C.porcentaje(v)}</div>`;
        $('f-comparacion').innerHTML = tarjeta(C.t('js.reto_hold_btc'), btc, holdBtc) + tarjeta(C.t('js.reto_mejor'), mejor, cambio(mejor)) + tarjeta(C.t('js.reto_peor'), peor, cambio(peor));
        $('f-leccion').innerHTML = leccion(ganancia, holdBtc, mejor, cambio(mejor));
        $('f-ops').innerHTML = resumenOperaciones();

        const records = C.leer('reto_records', []);
        records.push({ ganancia, fecha: Date.now(), estrellas, ops: juego.operaciones });
        records.sort((a, b) => b.ganancia - a.ganancia);
        C.escribir('reto_records', records.slice(0, 5));
        pintarRecords();
    }

    /** Consejo personalizado según cómo jugó (se muestra al terminar). */
    function leccion(ganancia, holdBtc, mejor, cambioMejor) {
        const consejos = [];
        if (juego.operaciones === 0) consejos.push(C.t('js.leccion_sin_ops'));
        if (juego.operaciones > 25) consejos.push(C.t('js.leccion_muchas_ops', { n: juego.operaciones, monto: C.dinero(juego.comisiones) }));
        if (juego.efectivo > APP.saldoInicial * 0.6 && ganancia < holdBtc) consejos.push(C.t('js.leccion_efectivo'));
        if (ganancia < holdBtc && holdBtc > 0) consejos.push(C.t('js.leccion_btc'));
        if (cambioMejor > 5) consejos.push(C.t('js.leccion_mejor', { simbolo: mejor.symbol.toUpperCase(), pct: C.numero(cambioMejor, 1) }));
        if (!consejos.length) consejos.push(C.t('js.leccion_bien'));
        return `<h3>🧠 ${C.t('js.leccion_titulo')}</h3><ul>${consejos.slice(0, 3).map(c => `<li>${c}</li>`).join('')}</ul>
            <a href="${APP.base}/paginas/estrategias.php#reto">${C.t('js.ver_guia')} →</a>`;
    }

    /** Operaciones una por una y cómo te fue siguiendo (o no) a la IA. */
    function resumenOperaciones() {
        const filas = juego.ventas.map(v => `<li>${v.resultado >= 0 ? '✔' : '✖'} ${C.t('js.reto_op_venta', {
            simbolo: C.escapar(v.simbolo), compra: C.precio(v.precioCompra), venta: C.precio(v.precioVenta),
            cambio: window.Coach.pct((v.precioVenta / v.precioCompra - 1) * 100, 2), resultado: firmado(v.resultado),
        })}</li>`);
        Object.entries(juego.posiciones).forEach(([id, cant]) => {
            const m = monedas.find(x => x.id === id);
            const valor = cant * precioEn(m, pasos - 1) * (1 - APP.comision);
            const costo = juego.costos[id] || 0;
            filas.push(`<li>⏳ ${C.t('js.reto_op_abierta', {
                simbolo: C.escapar(m.symbol.toUpperCase()), cambio: window.Coach.pct((valor / costo - 1) * 100, 2), resultado: firmado(valor - costo),
            })}</li>`);
        });
        if (!filas.length) return '';
        const { favor, contra } = juego.ia;
        const ia = favor.n + contra.n ? `<p>🤖 ${C.t('js.reto_ia_seguiste', { a: favor.n, b: contra.n })} ${favor.res || contra.res
            ? C.t('js.reto_ia_resultado', { a: firmado(favor.res), b: firmado(contra.res) }) : ''}</p>` : '';
        return `<h3>${C.t('js.reto_ops_titulo')}</h3><ul class="lista-ops-reto">${filas.join('')}</ul>${ia}`;
    }
    const firmado = v => `<strong class="${v >= 0 ? 'sube' : 'baja'}">${v >= 0 ? '+' : '−'}${C.dinero(Math.abs(v))}</strong>`;

    // ---------- Coach IA ----------

    /** Análisis de la IA en el paso actual (solo con los precios ya mostrados). Se calcula una vez por paso. */
    function iaDe(m) {
        if (!juego.cacheIa || juego.cacheIa.paso !== juego.paso) juego.cacheIa = { paso: juego.paso, porId: {} };
        const c = juego.cacheIa.porId;
        if (!c[m.id]) c[m.id] = window.Coach.analizar(m.sparkline.slice(0, juego.paso + 1), { veredicto: m.analisis.veredicto });
        return c[m.id];
    }

    function pintarCoach(m) {
        const a = iaDe(m);
        let extra = '';
        if (a.listo) {
            const mejor = monedas.map(x => [x, iaDe(x)]).filter(([, b]) => b.accion === 'comprar').sort((p, q) => q[1].puntos - p[1].puntos)[0];
            extra = mejor
                ? `<p class="coach-plan">⭐ ${C.t('js.ia_mejor_ahora', { simbolo: C.escapar(mejor[0].symbol.toUpperCase()), conf: mejor[1].confianza })}
                    ${mejor[0].id !== m.id ? ` <button class="boton mini secundario" type="button" data-sel="${mejor[0].id}">${C.t('js.ia_ver')}</button>` : ''}</p>`
                : `<p class="coach-plan">⏸ ${C.t('js.ia_ninguna')}</p>`;
        }
        $('r-coach').innerHTML = window.Coach.tarjeta(a, { maxRazones: 3, extra });
    }

    // ---------- Operaciones ----------

    function mensaje(texto, tipo) { $('r-mensaje').textContent = texto; $('r-mensaje').className = 'mensaje ' + tipo; }

    function comprar(monto) {
        const m = monedas.find(x => x.id === juego.seleccion);
        if (monto === 'todo') monto = Math.floor(juego.efectivo * 100) / 100;
        if (!(monto > 0) || monto > juego.efectivo + 1e-9) return mensaje(C.t('js.sin_efectivo'), 'alerta');
        const precio = precioActual(m);
        const cantidad = monto * (1 - APP.comision) / precio;
        const a = iaDe(m);
        if (!juego.posiciones[m.id] && a.listo) juego.iaCompra[m.id] = a.accion;
        if (a.listo) juego.ia[a.accion === 'comprar' ? 'favor' : 'contra'].n++;
        juego.posiciones[m.id] = (juego.posiciones[m.id] || 0) + cantidad;
        juego.costos[m.id] = (juego.costos[m.id] || 0) + monto;
        juego.efectivo -= monto;
        juego.operaciones++;
        juego.comisiones += monto * APP.comision;
        mensaje(C.t('js.compraste', { cantidad: C.cantidad(cantidad), simbolo: m.symbol.toUpperCase(), monto: C.dinero(monto) }), 'exito');
        const total = juego.efectivo + valorPortafolio();
        $('r-explica').innerHTML = window.Coach.explicarCompra({
            simbolo: m.symbol.toUpperCase(), precio, monto, a,
            stopLoss: undefined, peso: juego.posiciones[m.id] * precio / total * 100,
        });
        pintar();
    }

    function vender(porc) {
        const m = monedas.find(x => x.id === juego.seleccion);
        const tiene = juego.posiciones[m.id] || 0;
        if (!tiene) return mensaje(C.t('js.no_tienes', { simbolo: m.symbol.toUpperCase() }), 'alerta');
        const cantidad = porc >= 100 ? tiene : tiene * porc / 100;
        const precio = precioActual(m);
        const recibido = cantidad * precio * (1 - APP.comision);
        // Parte proporcional de lo que costó: de ahí sale si esta venta ganó o perdió.
        const costoVendido = (juego.costos[m.id] || 0) * (cantidad / tiene);
        const resultado = recibido - costoVendido;
        const precioCompra = costoVendido * (1 - APP.comision) / cantidad;
        const accionCompra = juego.iaCompra[m.id];
        juego.costos[m.id] -= costoVendido;
        juego.posiciones[m.id] = tiene - cantidad;
        if (juego.posiciones[m.id] <= 1e-12) { delete juego.posiciones[m.id]; delete juego.costos[m.id]; delete juego.iaCompra[m.id]; }
        juego.efectivo += recibido;
        juego.operaciones++;
        juego.comisiones += cantidad * precio * APP.comision;
        juego.ventas.push({ simbolo: m.symbol.toUpperCase(), precioCompra, precioVenta: precio, resultado });
        if (accionCompra) juego.ia[accionCompra === 'comprar' ? 'favor' : 'contra'].res += resultado;
        mensaje(C.t('js.reto_vendiste', { cantidad: C.cantidad(cantidad), simbolo: m.symbol.toUpperCase(), recibido: C.dinero(recibido) }), 'exito');
        $('r-explica').innerHTML = window.Coach.explicarVenta({
            simbolo: m.symbol.toUpperCase(), precioCompra, precioVenta: precio, resultado, accionCompra, a: iaDe(m),
            comisiones: costoVendido * APP.comision + cantidad * precio * APP.comision,
        });
        pintar();
    }

    // ---------- Render ----------

    function crearGrafico() {
        if (grafico) grafico.destroy();
        grafico = new Chart($('r-grafico'), {
            type: 'line',
            data: { labels: [], datasets: [{ data: [], borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, tension: 0.25, fill: true }] },
            options: {
                responsive: true, maintainAspectRatio: false, animation: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, tooltip: { ...C.tooltipChart(), displayColors: false, callbacks: { label: i => C.precio(i.parsed.y) } } },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: C.css('--texto-3'), maxTicksLimit: 7, maxRotation: 0 } },
                    y: { position: 'right', grid: { color: C.css('--borde') }, border: { display: false }, ticks: { color: C.css('--texto-3'), callback: v => C.precio(v), maxTicksLimit: 5 } },
                },
            },
        });
    }

    function pintar() {
        const m = monedas.find(x => x.id === juego.seleccion);
        const total = juego.efectivo + valorPortafolio();
        const gan = (total / APP.saldoInicial - 1) * 100;

        $('reloj').textContent = `${C.t('js.dia', { n: Math.min(7, Math.floor(juego.paso / (pasos - 1) * 7) + 1) })} · ${C.fecha(fechaPaso(juego.paso), { dateStyle: 'medium', timeStyle: 'short' })}`;
        $('barra-tiempo').style.width = `${juego.paso / (pasos - 1) * 100}%`;
        $('r-efectivo').textContent = C.dinero(juego.efectivo);
        $('r-invertido').textContent = C.dinero(valorPortafolio());
        $('r-total').textContent = C.dinero(total);
        $('r-ganancia').innerHTML = C.porcentaje(gan);
        $('r-comisiones').textContent = C.dinero(juego.comisiones);
        $('r-ops').textContent = C.t('js.reto_ops', { n: juego.operaciones });
        pintarObjetivos(gan);
        pintarCoach(m);

        // Gráfico de la moneda seleccionada hasta el paso actual.
        const serie = m.sparkline.slice(0, juego.paso + 1);
        const color = C.css(serie[serie.length - 1] >= serie[0] ? '--sube' : '--baja');
        grafico.data.labels = serie.map((_, i) => C.fecha(fechaPaso(i), { weekday: 'short', hour: '2-digit' }));
        grafico.data.datasets[0].data = serie;
        grafico.data.datasets[0].borderColor = color;
        grafico.data.datasets[0].backgroundColor = color + '22';
        grafico.update();
        $('r-titulo-grafico').innerHTML = `<span class="moneda" style="pointer-events:none"><img src="${C.escapar(m.image)}" width="22" height="22" alt="">${C.escapar(m.name)}</span>`;
        $('r-cambio-grafico').innerHTML = `${C.t('js.desde_inicio')}: ${C.porcentaje((precioActual(m) / precioEn(m, 0) - 1) * 100)}`;

        const tiene = juego.posiciones[m.id] || 0;
        $('r-info').innerHTML = `<div class="fila-info"><span class="texto-2">${C.t('js.precio')}</span><strong>${C.precio(precioActual(m))}</strong></div>
            <div class="fila-info"><span class="texto-2">${C.t('js.tienes')}</span><strong>${tiene ? `${C.cantidad(tiene)} ${m.symbol.toUpperCase()} (${C.dinero(tiene * precioActual(m))})` : '0'}</strong></div>`;

        $('r-monedas').innerHTML = monedas.map(x => {
            const cambio = (precioActual(x) / precioEn(x, 0) - 1) * 100;
            const mia = juego.posiciones[x.id];
            return `<button class="moneda-reto ${x.id === juego.seleccion ? 'activa' : ''}" data-sel="${x.id}" type="button">
                <span class="fila"><span class="moneda" style="pointer-events:none"><img src="${C.escapar(x.image)}" width="20" height="20" alt="">${C.escapar(x.symbol.toUpperCase())}</span>${mia ? '💼' : ''}</span>
                <span class="fila"><strong>${C.precio(precioActual(x))}</strong>${C.porcentaje(cambio, 1)}</span></button>`;
        }).join('');
    }

    /** Las 3 estrellas en vivo: así se ve en todo momento qué falta para ganar. */
    function pintarObjetivos(gan) {
        const cambio = m => (precioActual(m) / precioEn(m, 0) - 1) * 100;
        const btc = monedas.find(m => m.id === 'bitcoin') || monedas[0];
        const mejor = monedas.reduce((a, b) => (cambio(b) > cambio(a) ? b : a));
        const metaMejor = cambio(mejor) * 0.5;
        const objetivos = [
            [gan > 0, C.t('js.obj_ganar'), C.t('js.obj_tu', { v: C.porcentaje(gan) })],
            [gan > cambio(btc), C.t('js.obj_btc'), C.t('js.obj_btc_d', { v: C.porcentaje(cambio(btc)) })],
            [gan > 0 && gan >= metaMejor, C.t('js.obj_mejor'), C.t('js.obj_mejor_d', { simbolo: mejor.symbol.toUpperCase(), v: C.porcentaje(cambio(mejor)), meta: C.numero(Math.max(0, metaMejor), 1) })],
        ];
        $('r-objetivos').innerHTML = objetivos.map(([ok, titulo, detalle]) => `<div class="objetivo ${ok ? 'cumplido' : ''}">
            <span class="estrella-obj">${ok ? '⭐' : '☆'}</span><span><strong>${titulo}</strong><small>${detalle}</small></span></div>`).join('');
    }

    function pintarRecords() {
        const records = C.leer('reto_records', []);
        $('records').innerHTML = records.length ? records.map((r, i) => `<div class="fila">
            <span>${['🥇', '🥈', '🥉', '4.', '5.'][i]} ${C.fecha(r.fecha, { dateStyle: 'short' })} ${'⭐'.repeat(r.estrellas)}</span>
            <strong class="${r.ganancia >= 0 ? 'sube' : 'baja'}">${r.ganancia >= 0 ? '+' : '−'}${C.numero(Math.abs(r.ganancia))} %</strong></div>`).join('')
            : `<p class="texto-3">${C.t('js.sin_records')}</p>`;
    }

    function marcarVelocidad() {
        ['velocidad', 'velocidad-inicio'].forEach(idc => $(idc).querySelectorAll('button').forEach(b => b.classList.toggle('activa', Number(b.dataset.vel) === velocidad)));
    }

    // ---------- Eventos ----------

    $('empezar').addEventListener('click', empezar);
    $('otra-vez').addEventListener('click', () => { $('pantalla-fin').hidden = true; $('pantalla-inicio').hidden = false; });
    $('terminar').addEventListener('click', terminar);
    $('pausa').addEventListener('click', () => {
        juego.pausado = !juego.pausado;
        $('pausa').textContent = juego.pausado ? `▶ ${C.t('js.reanudar')}` : `⏸ ${C.t('js.pausa')}`;
    });
    ['velocidad', 'velocidad-inicio'].forEach(idc => $(idc).addEventListener('click', ev => {
        const b = ev.target.closest('button[data-vel]');
        if (!b) return;
        velocidad = Number(b.dataset.vel);
        marcarVelocidad();
        if (juego && !$('pantalla-juego').hidden) programar();
    }));
    $('r-coach').addEventListener('click', ev => {
        const b = ev.target.closest('[data-sel]');
        if (!b) return;
        juego.seleccion = b.dataset.sel;
        pintar();
    });
    $('r-monedas').addEventListener('click', ev => {
        const b = ev.target.closest('[data-sel]');
        if (!b) return;
        juego.seleccion = b.dataset.sel;
        pintar();
    });
    document.querySelectorAll('[data-comprar-monto]').forEach(b => b.addEventListener('click', () => comprar(b.dataset.comprarMonto === 'todo' ? 'todo' : Number(b.dataset.comprarMonto))));
    document.querySelectorAll('[data-vender-porc]').forEach(b => b.addEventListener('click', () => vender(Number(b.dataset.venderPorc))));
    document.addEventListener('tema', () => { if (juego && !$('pantalla-juego').hidden) { crearGrafico(); pintar(); } });

    // Atajos: 1-4 comprar · Q vender 50 % · W vender todo · espacio pausa · ←/→ cambiar moneda.
    document.addEventListener('keydown', ev => {
        if (!juego || $('pantalla-juego').hidden || ev.target.closest('input, select, textarea') || ev.ctrlKey || ev.metaKey || ev.altKey) return;
        const compras = { 1: 500, 2: 1000, 3: 2500, 4: 'todo' };
        const tecla = ev.key.toLowerCase();
        if (compras[tecla]) comprar(compras[tecla]);
        else if (tecla === 'q') vender(50);
        else if (tecla === 'w') vender(100);
        else if (tecla === ' ') { ev.preventDefault(); $('pausa').click(); }
        else if (tecla === 'arrowright' || tecla === 'arrowleft') {
            const i = monedas.findIndex(m => m.id === juego.seleccion);
            juego.seleccion = monedas[(i + (tecla === 'arrowright' ? 1 : -1) + monedas.length) % monedas.length].id;
            pintar();
        }
    });

    pintarRecords();
    // Los precios de la semana se cargan una sola vez: el reto usa una foto fija.
    C.obtenerMercado().then(m => { mercado = m; prepararMonedas(); })
        .catch(() => C.toast(C.t('js.sin_conexion'), 'alerta'));
})();
