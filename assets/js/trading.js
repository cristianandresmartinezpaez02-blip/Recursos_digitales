/* Trading en vivo: portafolio virtual (localStorage) con precios reales que se
   actualizan cada minuto, niveles, logros, stop-loss / take-profit y sugerencias. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    const CLAVE = 'minitrading_v1';
    // Comprar y vender cobra comisión dos veces: el precio debe subir al menos esto para no perder.
    const EQUILIBRIO = (1 / Math.pow(1 - APP.comision, 2) - 1) * 100;

    const G = window.Graficos;
    let mercado = null;
    let bolsa = null;       // acciones de la bolsa (api/bolsa.php), con la misma forma que las criptos
    let activos = {};       // cripto + acciones por id: todo lo que se puede operar
    let graficoPatrimonio = null;
    let graficoDona = null;
    let filtro = '';
    const params = new URLSearchParams(location.search);
    let clase = params.get('clase') === 'bolsa' || /^bolsa-/.test(params.get('moneda') || '') ? 'bolsa' : 'cripto';
    let diasGrafico = 1;
    const esAccion = m => m && (m.tipo === 'accion' || /^bolsa-/.test(m.id));

    function unirActivos() {
        activos = Object.assign({}, bolsa ? bolsa.porId : {}, mercado ? mercado.porId : {});
    }
    // Los logos de las acciones son imágenes embebidas: no se guardan en la partida (ocupan espacio).
    const imagenGuardable = src => /^data:/.test(src || '') ? '' : src;

    /** Cómo se movió hoy el mercado de referencia (Bitcoin para cripto, S&P 500 para acciones). */
    function referenciaMercado(m) {
        const ref = esAccion(m) ? bolsa && bolsa.indices.find(i => i.simbolo_yahoo === '^GSPC') : mercado && mercado.porId.bitcoin;
        return ref && ref.id !== m.id ? { nombre: ref.name, cambio: ref.price_change_percentage_24h } : null;
    }

    /** Precios de la bolsa (cada 2 minutos; Yahoo Finanzas vía api/bolsa.php). */
    async function cargarBolsa() {
        try {
            const r = await fetch(`${APP.base}/api/bolsa.php`, { cache: 'no-store' });
            const d = await r.json();
            if (!r.ok) throw new Error(d.error);
            d.porId = Object.fromEntries(d.acciones.map(a => [a.id, a]));
            bolsa = d;
        } catch (e) {
            console.error(e);
            if (!bolsa) C.toast(C.t('js.bolsa_error'), 'alerta');
        }
        unirActivos();
        if (mercado) {
            pintarSelector();
            recordarPrecios();
            revisarProtecciones();
            revisarOrdenes();
            guardar();
            pintarTodo();
        }
    }

    const NIVELES = [
        { min: -Infinity, clave: 'novato', icono: '🐣' },
        { min: 0, clave: 'aprendiz', icono: '📘' },
        { min: 3, clave: 'trader', icono: '🦊' },
        { min: 10, clave: 'tiburon', icono: '🦈' },
        { min: 25, clave: 'ballena', icono: '🐋' },
        { min: 50, clave: 'leyenda', icono: '👑' },
    ];
    const LOGROS = [
        ['primera_compra', '🚀'], ['buen_ojo', '🔍'], ['ganancia', '💰'], ['diversificado', '🧺'], ['protegido', '🛡️'],
        ['activo', '⚡'], ['rentable', '📈'], ['ballena', '🐋'], ['temerario', '🎲'], ['refugio', '🏝️'],
    ];

    // ---------- Estado ----------

    const nuevoEstado = () => ({ efectivo: APP.saldoInicial, posiciones: {}, operaciones: [], ordenes: [], patrimonio: [], logros: {}, inicio: Date.now() });
    function cargar() {
        const s = C.leer(CLAVE, null);
        const e = s && typeof s.efectivo === 'number' ? Object.assign(nuevoEstado(), s) : nuevoEstado();
        if (!Array.isArray(e.ordenes)) e.ordenes = [];
        // Partidas guardadas por una versión anterior del servidor traen [] en vez de {}:
        // en una lista, las monedas compradas no se guardaban.
        ['posiciones', 'logros'].forEach(k => { if (Array.isArray(e[k]) || typeof e[k] !== 'object' || !e[k]) e[k] = {}; });
        return e;
    }
    const guardar = () => C.escribir(CLAVE, estado);
    let estado = cargar();

    /** Último precio conocido de una posición: el del mercado o, si salió del top 100, el guardado. */
    function precioPosicion(id, p) {
        const m = activos[id];
        return m ? m.current_price : (p.ultimo ? p.ultimo.precio : p.costo / p.cantidad);
    }
    function valorInvertido() {
        return Object.entries(estado.posiciones).reduce((s, [id, p]) => s + p.cantidad * precioPosicion(id, p), 0);
    }

    /** Moneda del mercado, o una equivalente con el último precio guardado si ya no está en el top 100. */
    function monedaDe(id) {
        if (activos[id]) return activos[id];
        const u = estado.posiciones[id] && estado.posiciones[id].ultimo;
        return u ? { id, symbol: u.simbolo, name: u.nombre, image: u.imagen, current_price: u.precio, fuera: true,
            analisis: { etiqueta: '—', veredicto: 'neutral', es_stablecoin: false, resumen: '', pros: [] } } : null;
    }

    /** Recuerda nombre, imagen y precio de cada posición (por si la moneda sale del top 100). */
    function recordarPrecios() {
        Object.entries(estado.posiciones).forEach(([id, p]) => {
            const m = activos[id];
            if (m) p.ultimo = { precio: m.current_price, simbolo: m.symbol, nombre: m.name, imagen: imagenGuardable(m.image) };
        });
    }

    // Si la API falla, el servidor entrega la última copia guardada: con un precio viejo no se opera.
    const MAX_ANTIGUEDAD_MS = 15 * 60000;
    function precioViejo(m) {
        if (m.fuera) return false; // fuera del top 100 solo se puede cerrar, al último precio conocido
        const fecha = Date.parse(m.last_updated) || mercado.actualizado * 1000;
        return Date.now() - fecha > MAX_ANTIGUEDAD_MS;
    }
    /** Dinero apartado en órdenes límite pendientes (sigue siendo tuyo). */
    const reservado = () => estado.ordenes.reduce((s, o) => s + o.monto, 0);
    const patrimonio = () => estado.efectivo + reservado() + valorInvertido();
    const gananciaPct = () => (patrimonio() / APP.saldoInicial - 1) * 100;

    // ---------- Operaciones ----------

    function comprar(m, monto, prot = {}, tipo = 'compra') {
        if (!(monto > 0)) throw new Error(C.t('js.monto_invalido'));
        if (monto < APP.montoMinimo - 1e-9) throw new Error(C.t('js.monto_minimo', { monto: C.dolares(APP.montoMinimo) }));
        if (precioViejo(m)) throw new Error(C.t('js.precio_viejo'));
        if (monto > estado.efectivo + 1e-9) throw new Error(C.t('js.sin_efectivo'));
        const precio = m.current_price;
        const cantidad = monto * (1 - APP.comision) / precio;
        // "desde", "senalCompra" e "iaCompra" sirven después para explicar por qué se ganó o se perdió.
        const ia = iaDe(m);
        const p = estado.posiciones[m.id] || { cantidad: 0, costo: 0, stopLoss: null, takeProfit: null, desde: Date.now(), senalCompra: m.analisis.veredicto, iaCompra: ia.listo ? ia.accion : null };
        p.cantidad += cantidad;
        p.costo += monto;
        if (prot.sl) p.stopLoss = precio * (1 - prot.sl / 100);
        if (prot.tp) p.takeProfit = precio * (1 + prot.tp / 100);
        estado.posiciones[m.id] = p;
        estado.efectivo -= monto;
        registrar(tipo, m, cantidad, precio, monto, null);
        ganarLogro('primera_compra');
        if (m.analisis.veredicto === 'viable') ganarLogro('buen_ojo');
        if (m.analisis.veredicto === 'no_viable') ganarLogro('temerario');
        if (m.analisis.es_stablecoin) ganarLogro('refugio');
        if (prot.sl || prot.tp) ganarLogro('protegido');
        if (Object.keys(estado.posiciones).length >= 5) ganarLogro('diversificado');
        return C.t('js.compraste', { cantidad: C.cantidad(cantidad), simbolo: m.symbol.toUpperCase(), monto: C.dinero(monto) });
    }

    function vender(m, monto, motivo = 'venta') {
        const p = estado.posiciones[m.id];
        if (!p) throw new Error(C.t('js.no_tienes', { simbolo: m.symbol.toUpperCase() }));
        if (!(monto > 0)) throw new Error(C.t('js.monto_invalido'));
        if (precioViejo(m)) throw new Error(C.t('js.precio_viejo'));
        const precio = m.current_price;
        let cantidad = monto / precio;
        if (cantidad > p.cantidad * 0.9999) cantidad = p.cantidad; // vender todo
        // Mínimo por operación, salvo para cerrar del todo una posición pequeña.
        if (cantidad < p.cantidad && monto < APP.montoMinimo - 1e-9) throw new Error(C.t('js.monto_minimo', { monto: C.dolares(APP.montoMinimo) }));
        const recibido = cantidad * precio * (1 - APP.comision);
        const costoVendido = p.costo * (cantidad / p.cantidad);
        const resultado = recibido - costoVendido;
        // Desglose del resultado: lo que movió el precio y lo que se llevaron las comisiones.
        const precioCompra = costoVendido * (1 - APP.comision) / cantidad;
        const detalle = {
            precioCompra, desde: p.desde || null, senalCompra: p.senalCompra || null, costo: costoVendido,
            efectoPrecio: cantidad * (precio - precioCompra),
            comisiones: costoVendido * APP.comision + cantidad * precio * APP.comision,
            cambio24h: m.price_change_percentage_24h, clase: esAccion(m) ? 'bolsa' : 'cripto',
            mercado24h: referenciaMercado(m),
            iaCompra: p.iaCompra || null,
        };
        const ia = iaDe(m);
        if (ia.listo) detalle.iaVenta = { accion: ia.accion, confianza: ia.confianza };
        p.cantidad -= cantidad;
        p.costo -= costoVendido;
        if (p.cantidad <= 1e-12) delete estado.posiciones[m.id];
        estado.efectivo += recibido;
        registrar(motivo, m, cantidad, precio, recibido, resultado, detalle);
        if (resultado > 0) ganarLogro('ganancia');
        const op = estado.operaciones[0];
        setTimeout(() => explicar(op, true), 0);
        return C.t(resultado >= 0 ? 'js.vendiste_gan' : 'js.vendiste_per', {
            cantidad: C.cantidad(cantidad), simbolo: m.symbol.toUpperCase(), recibido: C.dinero(recibido), resultado: C.dinero(Math.abs(resultado)),
        });
    }

    function registrar(tipo, m, cantidad, precio, total, resultado, detalle = null) {
        estado.operaciones.unshift({
            fecha: Date.now(), tipo, id: m.id, simbolo: m.symbol.toUpperCase(), nombre: m.name, imagen: imagenGuardable(m.image),
            cantidad, precio, total, resultado, senal: m.analisis.etiqueta, veredicto: m.analisis.veredicto, ...(detalle || {}),
        });
        estado.operaciones = estado.operaciones.slice(0, 300);
        if (estado.operaciones.length >= 10) ganarLogro('activo');
        registrarPatrimonio();
        guardar();
    }

    function registrarPatrimonio() {
        estado.patrimonio.push({ t: Date.now(), v: patrimonio() });
        if (estado.patrimonio.length > 2000) estado.patrimonio.shift();
    }

    function ganarLogro(id) {
        if (estado.logros[id]) return;
        estado.logros[id] = Date.now();
        const icono = LOGROS.find(l => l[0] === id)[1];
        C.toast(`🏆 ${C.t('js.logro_desbloqueado')} ${icono} ${C.t('js.logro_' + id)}`, 'exito');
    }

    function revisarProtecciones() {
        Object.entries(estado.posiciones).forEach(([id, p]) => {
            const m = activos[id];
            if (!m || precioViejo(m)) return;
            if (p.stopLoss && m.current_price <= p.stopLoss) C.toast(`🛡️ Stop-loss: ${vender(m, p.cantidad * m.current_price, 'stop_loss')}`, 'alerta');
            else if (p.takeProfit && m.current_price >= p.takeProfit) C.toast(`🎯 Take-profit: ${vender(m, p.cantidad * m.current_price, 'take_profit')}`, 'exito');
        });
    }

    // ---------- Órdenes límite: "compra si baja a X" ----------

    function crearLimite(m, monto, precioLimite, prot = {}) {
        if (!(monto > 0)) throw new Error(C.t('js.monto_invalido'));
        if (monto < APP.montoMinimo - 1e-9) throw new Error(C.t('js.monto_minimo', { monto: C.dolares(APP.montoMinimo) }));
        if (monto > estado.efectivo + 1e-9) throw new Error(C.t('js.sin_efectivo'));
        if (precioViejo(m)) throw new Error(C.t('js.precio_viejo'));
        if (!(precioLimite > 0 && precioLimite < m.current_price)) throw new Error(C.t('js.limite_invalido', { precio: C.precio(m.current_price) }));
        estado.efectivo -= monto; // el dinero queda apartado hasta que se ejecute o se cancele
        estado.ordenes.push({
            id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`, moneda: m.id, simbolo: m.symbol.toUpperCase(), nombre: m.name, imagen: imagenGuardable(m.image),
            monto, precio: precioLimite, sl: prot.sl || 0, tp: prot.tp || 0, fecha: Date.now(),
        });
        guardar();
        return C.t('js.limite_creada', { monto: C.dinero(monto), simbolo: m.symbol.toUpperCase(), precio: C.precio(precioLimite) });
    }

    function cancelarOrden(id) {
        const o = estado.ordenes.find(x => x.id === id);
        if (!o) return;
        estado.ordenes = estado.ordenes.filter(x => x !== o);
        estado.efectivo += o.monto;
        guardar();
        C.toast(C.t('js.limite_cancelada', { monto: C.dinero(o.monto) }), 'exito');
        pintarTodo();
    }

    /** En cada actualización de precios: compra lo que llegó a su precio límite. */
    function revisarOrdenes() {
        estado.ordenes.slice().forEach(o => {
            const m = activos[o.moneda];
            if (!m || precioViejo(m) || m.current_price > o.precio) return;
            estado.ordenes = estado.ordenes.filter(x => x !== o);
            estado.efectivo += o.monto; // se libera la reserva y se compra con ella
            try {
                C.toast(`⏳ ${C.t('js.limite_ejecutada', { texto: comprar(m, o.monto, { sl: o.sl, tp: o.tp }, 'limite') })}`, 'exito');
            } catch (e) {
                console.error(e);
            }
        });
    }

    function revisarLogrosGlobales() {
        if (gananciaPct() >= 5) ganarLogro('rentable');
        if (patrimonio() >= 15000) ganarLogro('ballena');
    }

    function consejo(a, gpPct) {
        if (a.es_stablecoin) return C.t('js.consejo_stable');
        if (a.veredicto === 'no_viable') return C.t(gpPct >= 0 ? 'js.consejo_asegura' : 'js.consejo_limita');
        if (a.veredicto === 'viable') return C.t(gpPct >= 10 ? 'js.consejo_toma' : 'js.consejo_mantener');
        return C.t(gpPct <= -10 ? 'js.consejo_stop' : 'js.consejo_vigilar');
    }

    // ---------- Coach IA ----------

    /** Análisis del Coach IA (cripto: sparkline de 7 días, un punto cada 3 h; acciones: cierres diarios del mes). */
    const cacheIa = new Map();
    function iaDe(m) {
        if (!m || m.fuera || !window.Coach) return { listo: false, faltan: 16 };
        const clave = `${m.id}|${m.current_price}`;
        if (cacheIa.has(clave)) return cacheIa.get(clave);
        const serie = (m.sparkline || []).slice();
        if (serie.length) serie.push(m.current_price); // la serie termina en el precio en vivo
        const a = window.Coach.analizar(serie, {
            veredicto: m.analisis.veredicto, cambio24h: m.price_change_percentage_24h, esStable: m.analisis.es_stablecoin, puntosDia: esAccion(m) ? 1 : 8,
        });
        if (cacheIa.size > 500) cacheIa.clear();
        cacheIa.set(clave, a);
        return a;
    }

    let claveCoach = '';
    function pintarCoach() {
        const m = monedaOrden();
        if (!m) return;
        const a = iaDe(m);
        const clave = `${m.id}|${lado()}|${a.accion}|${a.confianza}|${Math.round(estado.efectivo)}`;
        if (clave === claveCoach) return; // así no se borra una explicación de Claude que se está leyendo
        claveCoach = clave;
        let extra = '';
        if (a.listo && lado() === 'compra' && a.accion !== 'vender') {
            const monto = window.Coach.tamano(patrimonio(), a.sl, estado.efectivo);
            extra = window.Coach.plan(a, m.current_price) +
                (monto >= APP.montoMinimo ? `<p class="coach-plan">💰 ${C.t('js.ia_tamano', { monto: C.dinero(monto) })}</p>` : '') +
                `<div class="coach-botones"><button class="boton mini" type="button" data-coach-aplicar>✔ ${C.t('js.ia_aplicar')}</button></div>`;
        }
        extra += `<p class="coach-stats">${C.t('js.ia_aviso')}</p>`;
        $('o-coach').innerHTML = window.Coach.tarjeta(a, { extra });
        const caja = $('o-coach').querySelector('.coach');
        if (a.listo && caja) {
            window.Coach.botonClaude(caja, () => {
                const p = estado.posiciones[m.id];
                return {
                    juego: 'trading', activo: `${m.name} (${m.symbol.toUpperCase()})`, precio: m.current_price,
                    cambio24h: m.price_change_percentage_24h, lado: lado(), analisis: window.Coach.resumen(iaDe(m)),
                    cartera: {
                        patrimonio: Math.round(patrimonio()), efectivo: Math.round(estado.efectivo), ganancia_pct: +gananciaPct().toFixed(2),
                        posicion_pct: p ? +((p.cantidad * m.current_price / p.costo - 1) * 100).toFixed(2) : null,
                    },
                };
            });
        }
    }

    // ---------- Render ----------

    function pintarNivel() {
        const g = gananciaPct();
        let i = NIVELES.length - 1;
        while (i > 0 && g < NIVELES[i].min) i--;
        const actual = NIVELES[i], siguiente = NIVELES[i + 1];
        $('nivel-icono').textContent = actual.icono;
        $('nivel-nombre').textContent = C.t('js.nivel_' + actual.clave);
        if (siguiente) {
            const desde = Number.isFinite(actual.min) ? actual.min : Math.min(g, -10);
            $('nivel-progreso').style.width = `${Math.max(0, Math.min(100, (g - desde) / (siguiente.min - desde) * 100))}%`;
            $('nivel-texto').textContent = C.t('js.siguiente_nivel', { n: (siguiente.min >= 0 ? '+' : '') + siguiente.min, nivel: `${siguiente.icono} ${C.t('js.nivel_' + siguiente.clave)}` });
        } else {
            $('nivel-progreso').style.width = '100%';
            $('nivel-texto').textContent = C.t('js.nivel_maximo');
        }
    }

    function pintarResumen() {
        const total = patrimonio();
        const gp = total - APP.saldoInicial;
        $('j-efectivo').textContent = C.dinero(estado.efectivo);
        $('j-reservado').textContent = estado.ordenes.length ? C.t('js.reservado', { monto: C.dinero(reservado()) }) : '\u00a0';
        $('j-invertido').textContent = C.dinero(valorInvertido());
        $('j-total').textContent = C.dinero(total);
        $('j-ganancia').innerHTML = `<span class="${gp >= 0 ? 'sube' : 'baja'}">${gp >= 0 ? '+' : '−'}${C.dinero(Math.abs(gp))}</span> ${C.porcentaje(gp / APP.saldoInicial * 100)}`;
        const ventas = estado.operaciones.filter(o => o.resultado !== null && o.resultado !== undefined);
        const ganadoras = ventas.filter(o => o.resultado > 0).length;
        $('j-aciertos').textContent = ventas.length ? `${Math.round(ganadoras / ventas.length * 100)} %` : '—';
        $('j-aciertos-sub').textContent = ventas.length ? C.t('js.ventas_ganancia', { n: ganadoras, total: ventas.length }) : C.t('js.sin_ventas');
    }

    function pintarPortafolio() {
        const filas = Object.entries(estado.posiciones).map(([id, p]) => {
            const m = activos[id];
            if (!m) {
                const u = p.ultimo;
                return `<tr><td>${C.escapar(u ? `${u.nombre} (${u.simbolo.toUpperCase()})` : id)}</td><td class="num">${C.cantidad(p.cantidad)}</td>
                    <td class="num">${C.precio(p.costo / p.cantidad)}</td><td class="num">${u ? C.precio(u.precio) : '—'}</td>
                    <td class="num"><strong>${C.dinero(p.cantidad * precioPosicion(id, p))}</strong></td><td colspan="3" class="texto-3">${C.t('js.fuera_top')}</td>
                    <td>${u ? `<button class="boton mini peligro" data-cerrar="${C.escapar(id)}" type="button">${C.t('js.vender')}</button>` : ''}</td></tr>`;
            }
            const valor = p.cantidad * m.current_price;
            const gp = valor - p.costo;
            const gpPct = gp / p.costo * 100;
            const prot = [p.stopLoss ? `<span class="baja">SL ${C.precio(p.stopLoss)}</span>` : '', p.takeProfit ? `<span class="sube">TP ${C.precio(p.takeProfit)}</span>` : '']
                .filter(Boolean).join('<br>') || '<span class="texto-3">—</span>';
            return `<tr>
                <td>${C.celdaMoneda(m)}<br>${C.insignia(m.analisis)}</td>
                <td class="num">${C.cantidad(p.cantidad)}</td>
                <td class="num">${C.precio(p.costo / p.cantidad)}</td>
                <td class="num">${C.precio(m.current_price)}</td>
                <td class="num"><strong>${C.dinero(valor)}</strong></td>
                <td class="num"><span class="${gp >= 0 ? 'sube' : 'baja'}">${gp >= 0 ? '+' : '−'}${C.dinero(Math.abs(gp))}</span><br>${C.porcentaje(gpPct)}</td>
                <td style="font-size:.8rem">${prot}</td>
                <td class="consejo">${consejo(m.analisis, gpPct)}${iaDe(m).listo ? `<br><small>${C.t('js.ia_portafolio', { accion: C.t('js.ia_' + iaDe(m).accion) })}</small>` : ''}</td>
                <td><button class="boton mini peligro" data-vender="${id}" type="button">${C.t('js.vender')}</button></td>
            </tr>`;
        });
        $('tabla-portafolio').innerHTML = filas.join('') || `<tr><td colspan="9" class="cargando">${C.t('js.portafolio_vacio')}</td></tr>`;
    }

    function pintarMercado() {
        const q = $('j-buscar').value.trim().toLowerCase();
        const base = clase === 'bolsa' ? (bolsa ? bolsa.acciones : []) : mercado.monedas;
        const lista = base.filter(m =>
            (!q || m.name.toLowerCase().includes(q) || m.symbol.toLowerCase().includes(q)) && (!filtro || m.analisis.veredicto === filtro));
        const vacio = clase === 'bolsa' && !bolsa ? C.t('js.cargando') : C.t('js.sin_resultados');
        $('tabla-juego').innerHTML = lista.map(m => `<tr>
            <td>${C.celdaMoneda(m, 22)}</td>
            <td class="num"><strong>${C.precio(m.current_price)}</strong></td>
            <td class="num">${C.porcentaje(m.price_change_percentage_24h)}</td>
            <td>${C.insignia(m.analisis, true)}</td>
            <td><button class="boton mini" data-comprar="${m.id}" type="button">${C.t('js.operar')}</button></td>
        </tr>`).join('') || `<tr><td colspan="5" class="cargando">${vacio}</td></tr>`;
        $('j-clase').querySelectorAll('button').forEach(b => b.classList.toggle('activa', b.dataset.clase === clase));
    }

    function pintarHistorial() {
        const tipos = { compra: 'js.op_compra', venta: 'js.op_venta', stop_loss: 'js.op_sl', take_profit: 'js.op_tp', Compra: 'js.op_compra', Venta: 'js.op_venta', 'Stop-loss': 'js.op_sl', 'Take-profit': 'js.op_tp', limite: 'js.op_limite' };
        $('tabla-historial').innerHTML = estado.operaciones.map((o, i) => {
            const compra = o.tipo === 'compra' || o.tipo === 'Compra' || o.tipo === 'limite';
            const res = o.resultado === null || o.resultado === undefined ? '<span class="texto-3">—</span>'
                : `<span class="${o.resultado >= 0 ? 'sube' : 'baja'}">${o.resultado >= 0 ? '+' : '−'}${C.dinero(Math.abs(o.resultado))}</span>`;
            return `<tr>
                <td class="texto-2">${C.fecha(o.fecha, { dateStyle: 'short', timeStyle: 'short' })}</td>
                <td><span class="insignia ${compra ? 'viable' : 'no_viable'}">${C.t(tipos[o.tipo] || 'js.op_venta')}</span></td>
                <td>${o.imagen ? `<img src="${C.escapar(o.imagen)}" width="18" height="18" alt="" style="vertical-align:middle;border-radius:50%"> ` : ''}${C.escapar(o.nombre)}</td>
                <td class="num">${C.cantidad(o.cantidad)}</td>
                <td class="num">${C.precio(o.precio)}</td>
                <td class="num">${C.dinero(o.total)}</td>
                <td class="num">${res}</td>
                <td><span class="insignia ${o.veredicto}">${C.escapar(o.senal)}</span></td>
                <td>${compra ? '' : `<button class="boton mini secundario" type="button" data-explicar="${i}">📊 ${C.t('js.por_que')}</button>`}</td>
            </tr>`;
        }).join('') || `<tr><td colspan="9" class="cargando">${C.t('js.sin_operaciones')}</td></tr>`;
    }

    function pintarLogros() {
        const ganados = LOGROS.filter(([id]) => estado.logros[id]).length;
        $('logros-cuenta').textContent = C.t('js.logros_cuenta', { n: ganados, total: LOGROS.length });
        $('lista-logros').innerHTML = LOGROS.map(([id, icono]) => {
            const f = estado.logros[id];
            return `<div class="logro ${f ? 'ganado' : ''}" title="${f ? C.fecha(f) : C.t('js.bloqueado')}">
                <div class="icono">${icono}</div><strong>${C.t('js.logro_' + id)}</strong><small>${C.t('js.logro_' + id + '_d')}</small></div>`;
        }).join('');
    }

    function pintarSugerencias() {
        // Avisos: posiciones con mala señal fundamental o que el Coach IA recomienda vender.
        const avisos = Object.keys(estado.posiciones).map(id => activos[id])
            .filter(m => m && (m.analisis.veredicto === 'no_viable' || iaDe(m).accion === 'vender'))
            .map(m => {
                const ia = iaDe(m);
                const motivo = ia.accion === 'vender' && ia.razones[0] ? `🤖 ${ia.razones[0][1]}` : C.t('js.revisa_d');
                return `<div class="sugerencia"><img src="${C.escapar(m.image)}" width="28" height="28" alt="">
                <div class="texto"><strong class="baja">${C.t('js.revisa', { simbolo: m.symbol.toUpperCase() })}</strong><small>${motivo}</small></div>
                <button class="boton mini peligro" data-vender="${m.id}" type="button">${C.t('js.vender')}</button></div>`;
            });
        // Ideas: monedas que el Coach IA ve para comprar, ordenadas por su puntaje técnico + el fundamental.
        const ideas = mercado.monedas
            .filter(m => !m.analisis.es_stablecoin && !estado.posiciones[m.id] && m.analisis.veredicto !== 'no_viable')
            .map(m => [m, iaDe(m)]).filter(([, ia]) => ia.accion === 'comprar')
            .sort((a, b) => (b[1].puntos + b[0].analisis.puntaje / 2) - (a[1].puntos + a[0].analisis.puntaje / 2)).slice(0, 3)
            .map(([m, ia]) => `<div class="sugerencia"><img src="${C.escapar(m.image)}" width="28" height="28" alt="">
                <div class="texto"><strong>${C.escapar(m.name)}</strong> <span class="sube" style="font-size:.8rem">🤖 ${ia.confianza} %</span>
                <small>${ia.razones[0] ? ia.razones[0][1] : C.escapar(m.analisis.resumen)}</small></div>
                <button class="boton mini" data-comprar="${m.id}" type="button">${C.t('js.operar')}</button></div>`);
        $('sugerencias-bot').innerHTML = [...avisos, ...ideas].join('') || `<p class="texto-3">${C.t('js.sin_sugerencias')}</p>`;
    }

    // La moneda pedida en la URL (?moneda=bolsa-aapl) puede llegar antes que los precios de la bolsa.
    let pedida = params.get('moneda');
    function pintarSelector() {
        const sel = $('o-moneda');
        const actual = (pedida && activos[pedida] ? pedida : null) || sel.value || pedida || (clase === 'bolsa' ? 'bolsa-aapl' : 'bitcoin');
        if (pedida && activos[pedida]) pedida = null;
        const opcion = m => `<option value="${m.id}">${C.escapar(m.name)} (${m.symbol.toUpperCase()})</option>`;
        sel.innerHTML = `<optgroup label="🪙 ${C.t('js.clase_cripto')}">${mercado.monedas.map(opcion).join('')}</optgroup>` +
            (bolsa ? `<optgroup label="📈 ${C.t('js.clase_bolsa')}">${bolsa.acciones.map(opcion).join('')}</optgroup>` : '');
        sel.value = activos[actual] ? actual : mercado.monedas[0].id;
    }

    const lado = () => document.querySelector('input[name="lado"]:checked').value;
    const monedaOrden = () => activos[$('o-moneda').value];

    function pintarOrden() {
        const m = monedaOrden();
        if (!m) return;
        const a = m.analisis;
        const p = estado.posiciones[m.id];
        const aviso = a.veredicto === 'no_viable' ? `<p class="alerta">✖ ${C.t('js.aviso_no')}</p>`
            : a.veredicto === 'viable' ? `<p class="exito">✔ ${C.t('js.aviso_si')}</p>` : `<p class="precaucion">● ${C.t('js.aviso_precaucion')}</p>`;
        let posicion = '0';
        if (p) {
            const gp = (p.cantidad * m.current_price / p.costo - 1) * 100;
            posicion = `${C.cantidad(p.cantidad)} ${m.symbol.toUpperCase()} (${C.dinero(p.cantidad * m.current_price)}) ${C.porcentaje(gp)}`;
        }
        $('o-info').innerHTML = `
            <div class="fila-info"><span class="moneda" style="pointer-events:none"><img src="${C.escapar(m.image)}" width="20" height="20" alt="">${C.escapar(m.name)}</span><strong>${C.precio(m.current_price)}</strong></div>
            <div class="fila-info"><span class="texto-2">24 h</span>${C.porcentaje(m.price_change_percentage_24h)}</div>
            <div class="fila-info"><span class="texto-2">${C.t('js.tienes')}</span><strong>${posicion}</strong></div>
            ${aviso}<p class="texto-2" style="font-size:.8rem">${C.escapar(a.resumen)} <a href="${C.escapar(m.url || C.urlMoneda(m.id) + '#analisis')}">${C.t('js.ver_analisis')} →</a></p>`;
        // La bolsa cierra de noche y los fines de semana: se opera al último precio de cierre.
        if (esAccion(m) && !m.mercado_abierto) $('o-info').insertAdjacentHTML('beforeend', `<p class="precaucion">🌙 ${C.t('js.bolsa_cerrada_operar')}</p>`);
        if (precioViejo(m)) $('o-info').insertAdjacentHTML('beforeend', `<p class="alerta">⏳ ${C.t('js.precio_viejo')}</p>`);
        $('o-enviar').disabled = precioViejo(m);
        $('o-avanzado').hidden = lado() !== 'compra';
        $('o-tipo-caja').hidden = lado() !== 'compra';
        $('o-limite-caja').hidden = !esLimite();
        $('o-enviar').textContent = esLimite() ? C.t('js.crear_orden') : `${C.t(lado() === 'compra' ? 'js.comprar' : 'js.vender')} ${m.symbol.toUpperCase()}`;
        pintarPrevia();
        pintarCoach();
    }

    const esLimite = () => lado() === 'compra' && $('o-tipo').value === 'limite';

    function pintarPrevia() {
        const m = monedaOrden();
        const monto = parseFloat($('o-monto').value);
        if (!m || !(monto > 0)) { $('o-previa').textContent = ''; return; }
        if (lado() === 'compra' && monto < APP.montoMinimo) {
            $('o-previa').innerHTML = `<span class="alerta">⚠ ${C.t('js.monto_minimo', { monto: C.dolares(APP.montoMinimo) })}</span>`;
            return;
        }
        const limite = parseFloat($('o-limite').value);
        if (esLimite() && limite > 0) {
            $('o-previa').innerHTML = C.t('js.previa_limite', { monto: C.dinero(monto), simbolo: m.symbol.toUpperCase(), precio: C.precio(limite), cantidad: C.cantidad(monto * (1 - APP.comision) / limite) });
        } else if (lado() === 'compra') {
            const cantidad = monto * (1 - APP.comision) / m.current_price;
            $('o-previa').innerHTML = C.t('js.previa_compra', { cantidad: C.cantidad(cantidad), simbolo: m.symbol.toUpperCase(), comision: C.dinero(monto * APP.comision) }) +
                `<br><span class="texto-3">${C.t('js.equilibrio', { pct: C.numero(EQUILIBRIO), precio: C.precio(m.current_price * (1 + EQUILIBRIO / 100)) })}</span>` +
                avisosRiesgo(m, monto);
        } else {
            $('o-previa').textContent = C.t('js.previa_venta', { cantidad: C.cantidad(monto / m.current_price), simbolo: m.symbol.toUpperCase(), recibido: C.dinero(monto * (1 - APP.comision)) });
        }
    }

    /** Gestión del riesgo: avisos antes de comprar (tamaño de la posición, stop-loss y señal). */
    function avisosRiesgo(m, monto) {
        const p = estado.posiciones[m.id];
        const enMoneda = monto + (p ? p.cantidad * m.current_price : 0);
        const peso = enMoneda / patrimonio() * 100;
        const avisos = [];
        if (peso > 25 && !m.analisis.es_stablecoin) avisos.push(['alerta', C.t('js.riesgo_peso', { pct: C.numero(peso, 0) })]);
        if (!(parseFloat($('o-sl').value) > 0) && !m.analisis.es_stablecoin) avisos.push(['precaucion', C.t('js.riesgo_sin_sl')]);
        if (m.analisis.veredicto === 'no_viable') avisos.push(['alerta', C.t('js.riesgo_no_viable')]);
        if (m.price_change_percentage_24h > 15) avisos.push(['precaucion', C.t('js.riesgo_perseguir', { pct: C.numero(m.price_change_percentage_24h, 1) })]);
        if (!avisos.length) avisos.push(['exito', C.t('js.riesgo_ok', { pct: C.numero(peso, 0) })]);
        return `<span class="avisos-riesgo">${avisos.map(([tipo, texto]) => `<span class="${tipo}">${tipo === 'exito' ? '✔' : '⚠'} ${texto}</span>`).join('')}</span>`;
    }

    function pintarGraficoPatrimonio() {
        const datos = estado.patrimonio.map(p => ({ x: p.t, y: p.v }));
        const color = C.css(gananciaPct() >= 0 ? '--sube' : '--baja');
        if (graficoPatrimonio) graficoPatrimonio.destroy();
        graficoPatrimonio = new Chart($('grafico-patrimonio'), {
            type: 'line',
            data: {
                datasets: [
                    { label: C.t('js.patrimonio'), data: datos, borderColor: color, backgroundColor: color + '22', fill: true, borderWidth: 2, pointRadius: datos.length < 3 ? 4 : 0, pointBackgroundColor: color, pointHoverRadius: 5, tension: 0.2 },
                    { label: C.t('js.saldo_inicial'), borderColor: C.css('--texto-3'), borderDash: [5, 5], borderWidth: 1, pointRadius: 0,
                        data: datos.length ? [{ x: datos[0].x, y: APP.saldoInicial }, { x: datos[datos.length - 1].x, y: APP.saldoInicial }] : [] },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false, animation: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { color: C.css('--texto-2'), boxWidth: 12, boxHeight: 12 } },
                    tooltip: { ...C.tooltipChart(), callbacks: { title: i => C.fecha(i[0].parsed.x, { dateStyle: 'short', timeStyle: 'short' }), label: i => ` ${i.dataset.label}: ${C.dinero(i.parsed.y)}` } },
                },
                scales: {
                    x: { type: 'time', time: { minUnit: 'minute', displayFormats: C.FORMATOS_FECHA }, grid: { display: false }, border: { display: false }, ticks: { color: C.css('--texto-3'), maxTicksLimit: 6, maxRotation: 0 } },
                    y: { position: 'right', grid: { color: C.css('--borde') }, border: { display: false }, ticks: { color: C.css('--texto-3'), callback: v => C.dinero(v), maxTicksLimit: 6 } },
                },
            },
        });
        $('nota-patrimonio').textContent = C.t('js.nota_patrimonio', { fecha: C.fecha(estado.inicio, { dateStyle: 'long' }), n: datos.length });
    }

    function pintarDona() {
        const pos = Object.entries(estado.posiciones).map(([id, p]) => {
            const m = activos[id];
            return { etiqueta: m ? m.symbol.toUpperCase() : (p.ultimo ? p.ultimo.simbolo.toUpperCase() : id), valor: p.cantidad * precioPosicion(id, p) };
        }).sort((a, b) => b.valor - a.valor);
        const partes = pos.slice(0, 4);
        const colores = ['--serie-1', '--serie-2', '--serie-3', '--serie-4'].slice(0, partes.length).map(C.css);
        const otras = pos.slice(4).reduce((s, x) => s + x.valor, 0);
        if (otras > 0) { partes.push({ etiqueta: C.t('js.otras'), valor: otras }); colores.push(C.css('--gris-medio')); }
        partes.push({ etiqueta: C.t('js.efectivo'), valor: estado.efectivo + reservado() });
        colores.push(C.css('--texto-3'));
        const total = partes.reduce((s, x) => s + x.valor, 0);
        if (graficoDona) graficoDona.destroy();
        graficoDona = new Chart($('grafico-dona'), {
            type: 'doughnut',
            data: { labels: partes.map(p => p.etiqueta), datasets: [{ data: partes.map(p => p.valor), backgroundColor: colores, borderColor: C.css('--panel-solido'), borderWidth: 2 }] },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '66%', animation: false,
                plugins: {
                    legend: { position: 'right', labels: { color: C.css('--texto-2'), boxWidth: 12, boxHeight: 12, generateLabels: ch => ch.data.labels.map((l, i) => ({ text: `${l} ${C.numero(ch.data.datasets[0].data[i] / total * 100, 0)} %`, fillStyle: colores[i], strokeStyle: colores[i], fontColor: C.css('--texto-2'), index: i })) } },
                    tooltip: { ...C.tooltipChart(), callbacks: { label: i => ` ${i.label}: ${C.dinero(i.parsed)}` } },
                },
            },
        });
    }

    function pintarOrdenes() {
        $('lista-ordenes').innerHTML = estado.ordenes.map(o => {
            const m = activos[o.moneda];
            const falta = m ? (1 - o.precio / m.current_price) * 100 : null;
            return `<div class="sugerencia">${o.imagen ? `<img src="${C.escapar(o.imagen)}" width="28" height="28" alt="">` : ''}
                <div class="texto"><strong>${C.escapar(o.nombre)} · ${C.dinero(o.monto)}</strong>
                <small>${C.t('js.op_limite')} ≤ ${C.precio(o.precio)} · ${m ? `${C.precio(m.current_price)} · ${C.t('js.distancia', { pct: C.numero(falta, 2) + ' %' })}` : C.t('js.fuera_top')}</small></div>
                <button class="boton mini secundario" data-cancelar="${C.escapar(o.id)}" type="button">${C.t('js.cancelar')}</button></div>`;
        }).join('') || `<p class="texto-3">${C.t('js.sin_ordenes')}</p>`;
    }

    /** Historial de operaciones a CSV (se abre en Excel o Google Sheets). */
    function exportarCsv() {
        const texto = v => `"${String(v ?? '').replace(/"/g, '""').replace(/^([=+\-@])/, "'$1")}"`;
        const num = v => (v === null || v === undefined) ? '' : String(v); // números propios: sin comillas
        const cab = ['fecha', 'tipo', 'moneda', 'simbolo', 'cantidad', 'precio_usd', 'total_usd', 'resultado_usd', 'senal'];
        const filas = estado.operaciones.map(o => [
            texto(new Date(o.fecha).toISOString()), texto(o.tipo), texto(o.nombre), texto(o.simbolo),
            num(o.cantidad), num(o.precio), num(o.total && o.total.toFixed(2)), num(o.resultado === null || o.resultado === undefined ? null : o.resultado.toFixed(2)), texto(o.senal),
        ].join(';'));
        const blob = new Blob(['﻿' + [cab.join(';'), ...filas].join('\n')], { type: 'text/csv;charset=utf-8' });
        const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: `trading_${C.fechaISO()}.csv` });
        a.click();
        URL.revokeObjectURL(a.href);
    }

    let pestana = 'operar';
    function pintarTodo() {
        if (!mercado) return;
        pintarNivel();
        pintarResumen();
        pintarMercado();
        pintarOrden();
        pintarOrdenes();
        // Las gráficas solo se dibujan cuando su pestaña está visible.
        if (pestana === 'portafolio') { pintarPortafolio(); pintarGraficoPatrimonio(); pintarDona(); pintarSugerencias(); }
        if (pestana === 'logros') pintarLogros();
        if (pestana === 'historial') pintarHistorial();
    }

    // ---------- Gráfico del precio del activo elegido ----------

    const cacheHistorial = {};
    /** Serie de precios ({x, y}) de una cripto (api/historial.php) o de una acción (api/bolsa.php). */
    async function historialDe(m, dias) {
        const clave = `${m.id}|${dias}`;
        const c = cacheHistorial[clave];
        if (c && Date.now() - c.t < 60000) return c.puntos;
        const url = esAccion(m)
            ? `${APP.base}/api/bolsa.php?historial=${encodeURIComponent(m.simbolo_yahoo || m.symbol.toUpperCase())}&dias=${dias}`
            : `${APP.base}/api/historial.php?id=${encodeURIComponent(m.id)}&dias=${dias}`;
        const r = await fetch(url, { cache: 'no-store' });
        const d = await r.json();
        if (!r.ok) throw new Error(d.error);
        const puntos = d.puntos.map(p => ({ x: p.t * 1000, y: p.p }));
        cacheHistorial[clave] = { t: Date.now(), puntos };
        return puntos;
    }
    /** La serie termina con el precio en vivo (el histórico puede ir unos minutos atrasado). */
    function conPrecioActual(puntos, m) {
        const ultimo = puntos[puntos.length - 1];
        return m && !m.fuera && (!ultimo || ultimo.x < Date.now() - 30000) ? [...puntos, { x: Date.now(), y: m.current_price }] : puntos;
    }

    async function pintarGraficoOrden() {
        const m = monedaOrden();
        if (!m) return;
        let puntos;
        try {
            puntos = conPrecioActual(await historialDe(m, diasGrafico), m);
        } catch (e) {
            $('o-graf-sub').textContent = C.t('js.error_historial');
            return;
        }
        if (monedaOrden() !== m || puntos.length < 2) return; // se eligió otra mientras cargaba
        const p = estado.posiciones[m.id];
        const lineas = [];
        if (p) {
            const compra = p.costo * (1 - APP.comision) / p.cantidad;
            lineas.push({ y: compra, etiqueta: C.t('js.tu_compra'), color: C.css('--serie-2'), punteada: false });
            lineas.push({ y: compra * (1 + EQUILIBRIO / 100), etiqueta: C.t('js.equilibrio_corto'), color: C.css('--texto-3') });
            if (p.stopLoss) lineas.push({ y: p.stopLoss, etiqueta: 'Stop-loss', color: C.css('--baja') });
            if (p.takeProfit) lineas.push({ y: p.takeProfit, etiqueta: 'Take-profit', color: C.css('--sube') });
        }
        const cambio = (puntos[puntos.length - 1].y / puntos[0].y - 1) * 100;
        $('o-graf-sub').innerHTML = `${C.escapar(m.symbol.toUpperCase())} · ${C.t('js.bolsa_en_periodo', { cambio: C.porcentaje(cambio) })}`;
        G.reemplazar($('o-grafico'), () => G.linea($('o-grafico'), puntos, { etiqueta: m.symbol.toUpperCase(), lineas, dias: diasGrafico }));
        explicarGrafico(m);
    }

    // ---------- ¿Por qué subió o bajó la gráfica? ----------

    const indiceCercano = (puntos, t) => puntos.reduce((mejor, p, i) => (Math.abs(p.x - t) < Math.abs(puntos[mejor].x - t) ? i : mejor), 0);

    /** Cuánto se movió Bitcoin entre dos momentos (para saber si el mercado arrastró a la moneda). */
    async function referenciaEntre(m, desde, hasta, dias) {
        const btc = activos.bitcoin;
        if (esAccion(m) || m.id === 'bitcoin' || !btc) return null;
        try {
            const p = conPrecioActual(await historialDe(btc, dias), btc);
            const a = p[indiceCercano(p, desde)].y, b = p[indiceCercano(p, hasta)].y;
            return a > 0 ? { nombre: 'Bitcoin', cambio: (b / a - 1) * 100 } : null;
        } catch (e) {
            return null;
        }
    }

    /** ¿Por qué se movió la moneda en el periodo que muestra el gráfico? Usa una serie más larga
        (el periodo siguiente: 1D → 7D…) para saber cómo venía antes de ese periodo. */
    async function explicarGrafico(m) {
        const opciones = [1, 7, 30, 90, 365];
        const diasContexto = opciones[Math.min(opciones.indexOf(diasGrafico) + 1, opciones.length - 1)];
        const dias = diasGrafico;
        try {
            const serie = conPrecioActual(await historialDe(m, diasContexto), m);
            const fin = serie.length - 1;
            const inicio = indiceCercano(serie, Date.now() - dias * 86400000);
            const referencia = await referenciaEntre(m, serie[inicio].x, serie[fin].x, diasContexto);
            if (monedaOrden() !== m || diasGrafico !== dias) return; // cambió mientras cargaba
            const res = window.Coach.porQueSeMovio({ serie: serie.map(p => p.y), inicio, fin, referencia });
            $('o-porque').innerHTML = window.Coach.htmlPorQue(res, m.symbol.toUpperCase(), 4);
            $('o-porque-caja').hidden = !res;
        } catch (e) {
            $('o-porque-caja').hidden = true;
        }
    }

    /** ¿Por qué se movió entre tu compra y tu venta? (panel "¿Por qué gané o perdí?") */
    async function explicarTramo(op, m, dias) {
        $('exp-porque').innerHTML = '';
        if (!op.desde || !op.fecha) return;
        try {
            const serie = conPrecioActual(await historialDe(m, dias), m);
            const inicio = indiceCercano(serie, op.desde), fin = indiceCercano(serie, op.fecha);
            const referencia = await referenciaEntre(m, op.desde, op.fecha, dias);
            const res = window.Coach.porQueSeMovio({ serie: serie.map(p => p.y), inicio, fin, referencia });
            $('exp-porque').innerHTML = window.Coach.htmlPorQue(res, op.simbolo, 5);
        } catch (e) { /* sin historial: queda el resto de la explicación */ }
    }

    // ---------- ¿Por qué gané o perdí? ----------

    function duracionTexto(ms) {
        const min = Math.round(ms / 60000);
        if (min < 60) return C.t('js.dur_min', { n: Math.max(1, min) });
        const h = Math.round(min / 60);
        return h < 48 ? C.t('js.dur_h', { n: h }) : C.t('js.dur_d', { n: Math.round(h / 24) });
    }

    /** Explica una venta con texto y dos gráficos: el camino del precio y de dónde sale el resultado. */
    async function explicar(op, auto = false) {
        if (!op || op.resultado === null || op.resultado === undefined) return;
        const panel = $('panel-explicacion');
        panel.hidden = false;
        $('exp-porque').innerHTML = ''; // se rellena al cargar el camino del precio (explicarTramo)
        const gano = op.resultado >= 0;
        const costo = op.costo || op.total - op.resultado;
        const pct = costo ? op.resultado / costo * 100 : 0;
        const conDetalle = op.precioCompra > 0;
        const cambio = conDetalle ? (op.precio / op.precioCompra - 1) * 100 : null;
        const dur = op.desde ? ' ' + C.t('js.exp_durante', { tiempo: duracionTexto(op.fecha - op.desde) }) : '';
        const datos = {
            simbolo: C.escapar(op.simbolo), compra: C.precio(op.precioCompra), venta: C.precio(op.precio),
            cambio: conDetalle ? C.porcentaje(cambio) : '', duracion: dur, eq: C.numero(EQUILIBRIO),
            comisiones: C.dinero(op.comisiones || 0),
        };
        let principal, leccion;
        if (!conDetalle) { principal = 'js.exp_sin_detalle'; leccion = gano ? 'js.exp_leccion_gano' : 'js.exp_leccion_perdio'; }
        else if (gano) { principal = 'js.exp_principal_gano'; leccion = 'js.exp_leccion_gano'; }
        else if (cambio > 0) { principal = 'js.exp_principal_comision'; leccion = 'js.exp_leccion_comision'; }
        else { principal = 'js.exp_principal_perdio'; leccion = 'js.exp_leccion_perdio'; }

        $('exp-titulo').textContent = `📊 ${C.t(gano ? 'js.exp_titulo_gano' : 'js.exp_titulo_perdio', { simbolo: op.simbolo })}`;
        $('exp-resumen').innerHTML = `<div class="exp-cifra ${gano ? 'sube' : 'baja'}">${gano ? '+' : '−'}${C.dinero(Math.abs(op.resultado))}
            <small>${gano ? '+' : '−'}${C.numero(Math.abs(pct))} %</small></div><p>${C.t(principal, datos)}</p>`;

        // Razones, de la más a la menos importante.
        const razones = [];
        if (op.comisiones) razones.push(['💸', C.t('js.exp_comisiones', datos)]);
        if (op.tipo === 'stop_loss') razones.push(['🛡️', C.t('js.exp_salida_sl')]);
        else if (op.tipo === 'take_profit') razones.push(['🎯', C.t('js.exp_salida_tp')]);
        else razones.push(['✋', C.t('js.exp_salida_manual')]);
        if (op.senalCompra) razones.push([{ viable: '✔', neutral: '●', no_viable: '✖' }[op.senalCompra] || '●', C.t('js.exp_senal_' + op.senalCompra)]);
        if (op.mercado24h && op.mercado24h.cambio !== null && op.mercado24h.cambio !== undefined) {
            razones.push(['🌐', C.t(op.mercado24h.cambio >= 0 ? 'js.exp_mercado_sube' : 'js.exp_mercado_baja', {
                nombre: C.escapar(op.mercado24h.nombre), cambio: C.porcentaje(op.mercado24h.cambio),
                clase: C.t(op.clase === 'bolsa' ? 'js.exp_clase_bolsa' : 'js.exp_clase_cripto'),
            })]);
        }
        if (op.desde && op.fecha - op.desde < 3600000) razones.push(['⏱️', C.t('js.exp_corto', { tiempo: duracionTexto(op.fecha - op.desde) })]);
        // Lo que decía el Coach IA al comprar y al vender.
        if (op.iaCompra) razones.push(['🤖', C.t('js.exp_ia_compra', { accion: C.t('js.ia_' + op.iaCompra) }).replace(/^🤖\s*/, '')]);
        if (op.iaVenta) {
            const clave = ({ comprar: 'js.exp_venta_ia_pronto', esperar: 'js.exp_venta_ia_neutral', vender: 'js.exp_venta_ia_ok' })[op.iaVenta.accion];
            razones.push(['🤖', C.t(clave, { accion: C.t('js.ia_' + op.iaVenta.accion) }).replace(/^🤖\s*/, '')]);
        }
        $('exp-razones').innerHTML = razones.map(([ico, texto]) => `<li><span aria-hidden="true">${ico}</span><span>${texto}</span></li>`).join('');
        $('exp-leccion').innerHTML = `<span>💡</span><span>${C.t(leccion, datos)}</span>`;

        // Cuentas: invertido → efecto del precio → comisiones → recibido.
        const pasos = conDetalle && op.comisiones !== undefined
            ? [{ etiqueta: C.t('js.exp_invertido'), valor: costo, total: true }, { etiqueta: C.t('js.exp_efecto_precio'), valor: op.efectoPrecio },
                { etiqueta: C.t('js.exp_comisiones_corto'), valor: -op.comisiones }, { etiqueta: C.t('js.exp_recibido'), valor: op.total, total: true }]
            : [{ etiqueta: C.t('js.exp_invertido'), valor: costo, total: true }, { etiqueta: C.t('js.exp_resultado'), valor: op.resultado },
                { etiqueta: C.t('js.exp_recibido'), valor: op.total, total: true }];
        G.reemplazar($('exp-cascada'), () => G.cascada($('exp-cascada'), pasos));

        if (auto && pestana === 'operar') panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        // Camino del precio desde la compra hasta la venta (y lo que pasó después).
        const caja = $('exp-precio').parentElement;
        const tiempo = op.desde ? Date.now() - op.desde : null;
        const dias = tiempo === null ? null : [1, 7, 30, 90, 365].find(d => tiempo < d * 86400000 * 0.95);
        caja.hidden = !dias;
        if (!dias) { $('exp-precio-nota').textContent = C.t('js.exp_sin_camino'); return; }
        const m = activos[op.id] || { id: op.id, symbol: op.simbolo, tipo: op.clase === 'bolsa' ? 'accion' : 'cripto', fuera: true };
        try {
            const margen = Math.max(1800000, (op.fecha - op.desde) * 0.3);
            const puntos = conPrecioActual(await historialDe(m, dias), m).filter(p => p.x >= op.desde - margen);
            if (puntos.length < 2) throw new Error('pocos datos');
            explicarTramo(op, m, dias);
            G.reemplazar($('exp-precio'), () => G.linea($('exp-precio'), puntos, {
                etiqueta: op.simbolo, dias, color: C.css('--texto-2'),
                lineas: [{ y: op.precioCompra * (1 + EQUILIBRIO / 100), etiqueta: C.t('js.equilibrio_corto'), color: C.css('--texto-3') }],
                marcadores: [
                    { x: op.desde, y: op.precioCompra, etiqueta: C.t('js.exp_compraste'), color: C.css('--serie-2') },
                    { x: op.fecha, y: op.precio, etiqueta: C.t('js.exp_vendiste'), color: C.css(gano ? '--sube' : '--baja') },
                ],
            }));
            $('exp-precio-nota').textContent = C.t('js.exp_nota_camino');
        } catch (e) {
            caja.hidden = true;
            $('exp-precio-nota').textContent = C.t('js.exp_sin_camino');
        }
    }

    // ---------- Eventos ----------

    function mensaje(texto, tipo) {
        $('o-mensaje').textContent = texto;
        $('o-mensaje').className = 'mensaje ' + tipo;
    }

    $('form-orden').addEventListener('submit', ev => {
        ev.preventDefault();
        const m = monedaOrden();
        if (!m) return;
        const monto = parseFloat($('o-monto').value);
        try {
            const prot = { sl: parseFloat($('o-sl').value) || 0, tp: parseFloat($('o-tp').value) || 0 };
            const compraDirecta = !esLimite() && lado() === 'compra';
            const texto = esLimite() ? crearLimite(m, monto, parseFloat($('o-limite').value), prot)
                : lado() === 'compra' ? comprar(m, monto, prot) : vender(m, monto);
            // Cada compra explica qué tiene que pasar para ganar y qué la haría perder (las ventas usan el panel de abajo).
            const p = estado.posiciones[m.id];
            $('o-explica').innerHTML = compraDirecta && p ? window.Coach.explicarCompra({
                simbolo: m.symbol.toUpperCase(), precio: m.current_price, monto, a: iaDe(m),
                stopLoss: p.stopLoss || null, peso: p.cantidad * m.current_price / patrimonio() * 100,
            }) : '';
            mensaje(texto, 'exito');
            C.toast(texto, 'exito');
            ['o-monto', 'o-sl', 'o-tp', 'o-limite'].forEach(i => { $(i).value = ''; });
            revisarLogrosGlobales();
            guardar();
            pintarTodo();
        } catch (e) {
            mensaje(e.message, 'alerta');
        }
    });

    document.querySelectorAll('.atajos button').forEach(b => b.addEventListener('click', () => {
        const m = monedaOrden();
        if (!m) return;
        const p = estado.posiciones[m.id];
        const max = lado() === 'compra' ? estado.efectivo : (p ? p.cantidad * m.current_price : 0);
        $('o-monto').value = (Math.floor(max * b.dataset.porc) / 100).toFixed(2);
        pintarPrevia();
    }));

    function seleccionar(id, nuevoLado) {
        mostrarPestana('operar', true);
        $('o-moneda').value = id;
        document.querySelector(`input[name="lado"][value="${nuevoLado}"]`).checked = true;
        if (nuevoLado === 'venta') {
            const m = activos[id], p = estado.posiciones[id];
            if (m && p) $('o-monto').value = (Math.floor(p.cantidad * m.current_price * 100) / 100).toFixed(2);
        }
        pintarOrden();
        pintarGraficoOrden();
        $('panel-orden').scrollIntoView({ behavior: 'smooth', block: 'start' });
        $('o-monto').focus({ preventScroll: true });
    }

    document.addEventListener('click', ev => {
        const c = ev.target.closest('[data-comprar]'), v = ev.target.closest('[data-vender]');
        if (c) seleccionar(c.dataset.comprar, 'compra');
        if (v) seleccionar(v.dataset.vender, 'venta');
        const cancelar = ev.target.closest('[data-cancelar]');
        if (cancelar) cancelarOrden(cancelar.dataset.cancelar);
        const cerrar = ev.target.closest('[data-cerrar]');
        if (cerrar) {
            // Moneda que salió del top 100: se vende todo al último precio conocido.
            const m = monedaDe(cerrar.dataset.cerrar), p = estado.posiciones[cerrar.dataset.cerrar];
            if (!m || !p) return;
            C.toast(vender(m, p.cantidad * m.current_price), 'exito');
            guardar();
            pintarTodo();
        }
    });
    // "Aplicar el plan de la IA": monto según el riesgo, stop-loss y take-profit sugeridos.
    $('o-coach').addEventListener('click', ev => {
        if (!ev.target.closest('[data-coach-aplicar]')) return;
        const m = monedaOrden(), a = iaDe(m);
        if (!a.listo) return;
        const monto = window.Coach.tamano(patrimonio(), a.sl, estado.efectivo);
        if (monto >= APP.montoMinimo) $('o-monto').value = monto.toFixed(2);
        $('o-sl').value = a.sl.toFixed(1);
        $('o-tp').value = a.tp.toFixed(1);
        $('o-avanzado').open = true;
        pintarPrevia();
        mensaje(C.t('js.ia_aplicado'), 'exito');
    });
    $('o-moneda').addEventListener('change', () => { $('o-explica').innerHTML = ''; pintarOrden(); pintarGraficoOrden(); });
    $('o-tipo').addEventListener('change', pintarOrden);
    ['o-monto', 'o-sl', 'o-limite'].forEach(id => $(id).addEventListener('input', pintarPrevia));
    document.querySelectorAll('input[name="lado"]').forEach(r => r.addEventListener('change', pintarOrden));
    $('j-buscar').addEventListener('input', () => mercado && pintarMercado());
    $('j-filtro').addEventListener('click', ev => {
        const b = ev.target.closest('[data-filtro]');
        if (!b) return;
        filtro = b.dataset.filtro;
        $('j-filtro').querySelectorAll('.chip').forEach(x => x.classList.toggle('activo', x === b));
        if (mercado) pintarMercado();
    });
    // Cripto o bolsa en la lista de la izquierda.
    $('j-clase').addEventListener('click', ev => {
        const b = ev.target.closest('[data-clase]');
        if (!b || !mercado) return;
        clase = b.dataset.clase;
        pintarMercado();
    });
    $('o-graf-rango').addEventListener('click', ev => {
        const b = ev.target.closest('[data-dias]');
        if (!b) return;
        diasGrafico = Number(b.dataset.dias);
        $('o-graf-rango').querySelectorAll('.chip').forEach(x => x.classList.toggle('activo', x === b));
        pintarGraficoOrden();
    });
    // "¿Por qué?" en el historial: abre la explicación de esa venta.
    $('tabla-historial').addEventListener('click', ev => {
        const b = ev.target.closest('[data-explicar]');
        if (!b) return;
        mostrarPestana('operar', true);
        explicar(estado.operaciones[Number(b.dataset.explicar)]);
        $('panel-explicacion').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    $('exp-cerrar').addEventListener('click', () => { $('panel-explicacion').hidden = true; });

    $('j-reiniciar').addEventListener('click', () => { $('j-confirmar').hidden = false; });
    $('j-confirmar-no').addEventListener('click', () => { $('j-confirmar').hidden = true; });
    $('j-confirmar-si').addEventListener('click', () => {
        estado = nuevoEstado();
        registrarPatrimonio();
        guardar();
        $('j-confirmar').hidden = true;
        C.toast(C.t('js.reiniciado'), 'exito');
        pintarTodo();
    });
    document.addEventListener('tema', () => { pintarTodo(); pintarGraficoOrden(); });
    $('j-exportar').addEventListener('click', exportarCsv);

    // Con el juego abierto en dos pestañas, cada una toma lo que guarda la otra (así no se pisan).
    const huella = e => `${e.operaciones.length}|${e.operaciones[0] ? e.operaciones[0].fecha : 0}|${e.ordenes.length}|${e.inicio}`;
    addEventListener('storage', ev => {
        if (ev.key !== CLAVE || !ev.newValue) return;
        const antes = huella(estado);
        estado = cargar();
        if (huella(estado) !== antes) C.toast(C.t('js.otra_pestana'), 'exito');
        pintarTodo();
    });

    const mostrarPestana = C.iniciarPestanas('pestanas-trading', id => { pestana = id; pintarTodo(); });

    C.iniciarRefresco(m => {
        const primera = !mercado;
        mercado = m;
        unirActivos();
        pintarSelector();
        recordarPrecios();
        revisarProtecciones();
        revisarOrdenes();
        registrarPatrimonio();
        revisarLogrosGlobales();
        guardar();
        pintarTodo();
        pintarGraficoOrden();
        if (primera) {
            cargarBolsa();
            setInterval(cargarBolsa, 120000); // la bolsa se renueva cada 2 minutos en el servidor
            // Al abrir el juego se muestra la explicación de la última venta.
            const ultimaVenta = estado.operaciones.find(o => o.resultado !== null && o.resultado !== undefined);
            if (ultimaVenta && ultimaVenta.precioCompra) explicar(ultimaVenta);
        }
    });
})();
