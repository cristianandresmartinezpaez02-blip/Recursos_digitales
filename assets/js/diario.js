/* Diario de trading: registro de operaciones (reales o de práctica) con estadísticas que
   usan los traders: % de aciertos, factor de beneficio, esperanza matemática, rachas,
   curva de resultados y análisis por estrategia y por emoción. Se guarda en la cuenta. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    const CLAVE = 'diario_trading';
    const EMOJI = { tranquilo: '😌', confiado: '😎', nervioso: '😬', codicia: '🤑', miedo: '😨', revancha: '😤' };

    let ops = C.leer(CLAVE, []);
    let mercado = null;
    let filtro = '';
    let grafico = null;

    const guardar = () => C.escribir(CLAVE, ops);
    const hoy = () => C.fechaISO();
    const nuevoId = () => Date.now().toString(36) + Math.random().toString(36).slice(2, 6);

    /** Resultado en USD de una operación (con comisión de entrada y salida). Usa el precio actual si sigue abierta. */
    function resultado(o) {
        const salida = o.salida ?? precioActual(o);
        if (!salida || !o.entrada) return null;
        const signo = o.lado === 'venta' ? -1 : 1;
        const bruto = o.monto * (salida / o.entrada - 1) * signo;
        const comisiones = o.monto * (o.comision || 0) / 100 * (1 + salida / o.entrada);
        return bruto - comisiones;
    }

    function precioActual(o) {
        if (!mercado) return null;
        const s = o.moneda.toLowerCase();
        const m = mercado.monedas.find(x => x.symbol.toLowerCase() === s || x.id === s || x.name.toLowerCase() === s);
        return m ? m.current_price : null;
    }

    const cerradas = () => ops.filter(o => o.salida != null);

    // ---------- Estadísticas ----------

    function estadisticas(lista) {
        const res = lista.map(resultado).filter(v => v !== null);
        const ganan = res.filter(v => v > 0), pierden = res.filter(v => v <= 0);
        const sumaG = ganan.reduce((a, b) => a + b, 0), sumaP = -pierden.reduce((a, b) => a + b, 0);
        let racha = 0, mejorRacha = 0, rachaMala = 0, peorRacha = 0;
        res.forEach(v => {
            if (v > 0) { racha++; rachaMala = 0; } else { rachaMala++; racha = 0; }
            mejorRacha = Math.max(mejorRacha, racha); peorRacha = Math.max(peorRacha, rachaMala);
        });
        return {
            n: res.length, total: sumaG - sumaP, acierto: res.length ? ganan.length / res.length * 100 : null,
            factor: sumaP ? sumaG / sumaP : (sumaG ? Infinity : null),
            esperanza: res.length ? (sumaG - sumaP) / res.length : null,
            mediaG: ganan.length ? sumaG / ganan.length : 0, mediaP: pierden.length ? sumaP / pierden.length : 0,
            mejor: res.length ? Math.max(...res) : null, peor: res.length ? Math.min(...res) : null,
            mejorRacha, peorRacha,
        };
    }

    const dinero = v => v === null || v === undefined ? '—' : `<span class="${v >= 0 ? 'sube' : 'baja'}">${v >= 0 ? '+' : '−'}${C.dinero(Math.abs(v))}</span>`;

    function pintarKpis() {
        const s = estadisticas(cerradas().sort((a, b) => a.fecha.localeCompare(b.fecha)));
        $('k-pnl').innerHTML = s.n ? dinero(s.total) : '—';
        $('k-pnl-d').textContent = s.n ? C.t('js.dia_cerradas', { n: s.n, abiertas: ops.length - s.n }) : C.t('js.dia_vacio_corto');
        $('k-acierto').textContent = s.acierto === null ? '—' : `${C.numero(s.acierto, 0)} %`;
        $('k-acierto-d').textContent = s.n ? C.t('js.dia_medias', { g: C.dinero(s.mediaG), p: C.dinero(s.mediaP) }) : '';
        $('k-factor').textContent = s.factor === null ? '—' : s.factor === Infinity ? '∞' : C.numero(s.factor, 2);
        $('k-factor').className = 'kpi-valor ' + (s.factor === null ? '' : s.factor >= 1 ? 'sube' : 'baja');
        $('k-esperanza').innerHTML = s.esperanza === null ? '—' : dinero(s.esperanza);
        return s;
    }

    function pintarTabla() {
        const lista = ops.slice().sort((a, b) => b.fecha.localeCompare(a.fecha) || b.creado - a.creado).filter(o => {
            if (filtro === 'abiertas') return o.salida == null;
            const r = resultado(o);
            if (filtro === 'ganadoras') return o.salida != null && r > 0;
            if (filtro === 'perdedoras') return o.salida != null && r <= 0;
            return true;
        });
        $('d-cuenta').textContent = `(${ops.length})`;
        $('d-tabla').innerHTML = lista.map(o => {
            const r = resultado(o);
            const pct = r === null ? null : r / o.monto * 100;
            const abierta = o.salida == null;
            return `<tr class="${abierta ? 'abierta' : ''}">
                <td class="texto-2">${C.fecha(o.fecha + 'T12:00:00', { dateStyle: 'short' })}</td>
                <td><strong>${C.escapar(o.moneda.toUpperCase())}</strong> ${o.notas ? `<span title="${C.escapar(o.notas)}">📝</span>` : ''} ${o.origen === 'juego' ? '<span title="' + C.escapar(C.t('js.dia_del_juego')) + '">🎮</span>' : ''}</td>
                <td><span class="insignia ${o.lado === 'venta' ? 'no_viable' : 'viable'}">${o.lado === 'venta' ? '▼ ' + C.t('js.dia_corto') : '▲ ' + C.t('js.dia_largo')}</span></td>
                <td class="num">${C.precio(o.entrada)}</td>
                <td class="num">${abierta ? `<span class="texto-3">${C.t('js.dia_abierta')}</span>` : C.precio(o.salida)}</td>
                <td class="num">${C.dinero(o.monto)}</td>
                <td class="num">${dinero(r)}${pct !== null ? `<br>${C.porcentaje(pct, 1)}` : ''}${abierta && r !== null ? `<br><small class="texto-3">${C.t('js.dia_flotante')}</small>` : ''}</td>
                <td><small>${C.t('js.dia_e_' + o.estrategia)} ${EMOJI[o.emocion] || ''}${o.plan ? '' : ' ⚠️'}</small></td>
                <td class="acciones-diario">
                    ${abierta ? `<button class="boton mini" data-cerrar="${o.id}" type="button" title="${C.escapar(C.t('js.dia_cerrar_d'))}">${C.t('js.dia_cerrar')}</button>` : ''}
                    <button class="boton-icono mini-cerrar" data-editar="${o.id}" type="button" aria-label="${C.escapar(C.t('js.dia_editar'))}">✏️</button>
                    <button class="boton-icono mini-cerrar" data-borrar="${o.id}" type="button" aria-label="${C.escapar(C.t('js.dia_borrar'))}">🗑</button>
                </td>
            </tr>`;
        }).join('') || `<tr><td colspan="9" class="cargando">${C.t('js.dia_vacio')}</td></tr>`;
    }

    function pintarCurva() {
        const lista = cerradas().sort((a, b) => a.fecha.localeCompare(b.fecha) || a.creado - b.creado);
        let acumulado = 0;
        const puntos = lista.map(o => (acumulado += resultado(o) || 0));
        const color = C.css(acumulado >= 0 ? '--sube' : '--baja');
        grafico?.destroy();
        grafico = new Chart($('d-curva'), {
            type: 'line',
            data: {
                labels: [C.t('js.dia_inicio'), ...lista.map((o, i) => `#${i + 1} ${o.moneda.toUpperCase()}`)],
                datasets: [{ data: [0, ...puntos], borderColor: color, backgroundColor: color + '22', fill: true, borderWidth: 2, pointRadius: puntos.length < 30 ? 3 : 0, tension: 0.2 }],
            },
            options: {
                responsive: true, maintainAspectRatio: false, animation: false,
                plugins: { legend: { display: false }, tooltip: { ...C.tooltipChart(), displayColors: false, callbacks: { label: i => ` ${C.dinero(i.parsed.y)}` } } },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { display: false } },
                    y: { position: 'right', grid: { color: C.css('--borde') }, border: { display: false }, ticks: { color: C.css('--texto-3'), callback: v => C.dinero(v), maxTicksLimit: 6 } },
                },
            },
        });
    }

    /** Qué estrategia y qué estado de ánimo te funcionan mejor. */
    function pintarAnalisis(s) {
        if (!s.n) { $('d-analisis').innerHTML = `<p class="texto-3">${C.t('js.dia_analisis_vacio')}</p>`; return; }
        const agrupar = (campo, nombre) => {
            const grupos = {};
            cerradas().forEach(o => { (grupos[o[campo]] = grupos[o[campo]] || []).push(o); });
            return Object.entries(grupos).map(([k, lista]) => ({ k, nombre: nombre(k), ...estadisticas(lista) })).sort((a, b) => b.total - a.total);
        };
        const tabla = (titulo, filas) => `<h3 class="titulo-sub">${titulo}</h3><div class="records">${filas.map(f => `<div class="fila">
            <span>${f.nombre} <small class="texto-3">${C.t('js.dia_n_ops', { n: f.n })} · ${C.numero(f.acierto, 0)} %</small></span><strong>${dinero(f.total)}</strong></div>`).join('')}</div>`;
        const porEstrategia = agrupar('estrategia', k => C.t('js.dia_e_' + k));
        const porEmocion = agrupar('emocion', k => `${EMOJI[k] || ''} ${C.t('js.dia_m_' + k)}`);
        const sinPlan = estadisticas(cerradas().filter(o => !o.plan)), conPlan = estadisticas(cerradas().filter(o => o.plan));

        const consejos = [];
        if (s.factor !== null && s.factor < 1) consejos.push(C.t('js.dia_c_factor'));
        if (s.mediaP > s.mediaG && s.n >= 3) consejos.push(C.t('js.dia_c_perdidas', { g: C.dinero(s.mediaG), p: C.dinero(s.mediaP) }));
        if (sinPlan.n && conPlan.n && sinPlan.total < conPlan.total) consejos.push(C.t('js.dia_c_plan'));
        const peorEmocion = porEmocion[porEmocion.length - 1];
        if (peorEmocion && peorEmocion.total < 0 && porEmocion.length > 1) consejos.push(C.t('js.dia_c_emocion', { e: peorEmocion.nombre }));
        if (s.peorRacha >= 3) consejos.push(C.t('js.dia_c_racha', { n: s.peorRacha }));
        if (!consejos.length) consejos.push(C.t('js.dia_c_bien'));

        $('d-analisis').innerHTML = `<div class="rejilla-datos" style="grid-template-columns:repeat(2,minmax(0,1fr))">
                <div class="dato"><span>${C.t('js.dia_mejor')}</span><strong>${dinero(s.mejor)}</strong></div>
                <div class="dato"><span>${C.t('js.dia_peor')}</span><strong>${dinero(s.peor)}</strong></div>
                <div class="dato"><span>${C.t('js.dia_racha_g')}</span><strong>${s.mejorRacha}</strong></div>
                <div class="dato"><span>${C.t('js.dia_racha_p')}</span><strong>${s.peorRacha}</strong></div>
            </div>
            ${tabla('🧭 ' + C.t('js.dia_por_estrategia'), porEstrategia)}
            ${tabla('💭 ' + C.t('js.dia_por_emocion'), porEmocion)}
            <div class="leccion" style="margin-top:1rem"><h3>💡 ${C.t('js.dia_consejos')}</h3><ul>${consejos.map(c => `<li>${c}</li>`).join('')}</ul></div>`;
    }

    function pintarTodo() {
        const s = pintarKpis();
        pintarTabla();
        pintarCurva();
        pintarAnalisis(s);
    }

    // ---------- Formulario ----------

    const lado = () => document.querySelector('input[name="d-lado"]:checked').value;

    function leerFormulario() {
        const num = id => { const v = parseFloat($(id).value); return Number.isFinite(v) ? v : null; };
        return {
            id: $('d-id').value || nuevoId(),
            fecha: $('d-fecha').value || hoy(),
            moneda: $('d-moneda').value.trim(),
            lado: lado(),
            entrada: num('d-entrada'),
            salida: num('d-salida'),
            monto: num('d-monto'),
            comision: num('d-comision') ?? 0,
            estrategia: $('d-estrategia').value,
            emocion: $('d-emocion').value,
            plan: $('d-plan').checked,
            notas: $('d-notas').value.trim(),
        };
    }

    function pintarPrevia() {
        const o = leerFormulario();
        if (!o.entrada || !o.monto || !o.moneda) { $('d-previa').textContent = ''; return; }
        const r = resultado(o);
        $('d-previa').innerHTML = r === null ? C.t('js.dia_previa_abierta')
            : `${C.t(o.salida == null ? 'js.dia_previa_flotante' : 'js.dia_previa')}: ${dinero(r)} (${C.porcentaje(r / o.monto * 100, 2)})`;
    }

    function limpiarFormulario() {
        $('d-form').reset();
        $('d-id').value = '';
        $('d-fecha').value = hoy();
        $('d-comision').value = '0.1';
        $('d-form-titulo').textContent = '➕ ' + C.t('js.dia_nueva');
        $('d-cancelar').hidden = true;
        $('d-previa').textContent = '';
    }

    function editar(id) {
        const o = ops.find(x => x.id === id);
        if (!o) return;
        $('d-id').value = o.id;
        $('d-fecha').value = o.fecha;
        $('d-moneda').value = o.moneda;
        document.querySelector(`input[name="d-lado"][value="${o.lado}"]`).checked = true;
        $('d-entrada').value = o.entrada;
        $('d-salida').value = o.salida ?? '';
        $('d-monto').value = o.monto;
        $('d-comision').value = o.comision;
        $('d-estrategia').value = o.estrategia;
        $('d-emocion').value = o.emocion;
        $('d-plan').checked = o.plan;
        $('d-notas').value = o.notas || '';
        $('d-form-titulo').textContent = '✏️ ' + C.t('js.dia_editar');
        $('d-cancelar').hidden = false;
        pintarPrevia();
        $('d-panel-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    $('d-form').addEventListener('submit', ev => {
        ev.preventDefault();
        const o = leerFormulario();
        if (!o.moneda || !(o.entrada > 0) || !(o.monto > 0)) {
            $('d-mensaje').textContent = C.t('js.dia_err');
            $('d-mensaje').className = 'mensaje alerta';
            return;
        }
        const i = ops.findIndex(x => x.id === o.id);
        if (i >= 0) ops[i] = { ...ops[i], ...o }; else ops.push({ ...o, creado: Date.now() });
        guardar();
        C.toast('📓 ' + C.t(i >= 0 ? 'js.dia_actualizada' : 'js.dia_guardada'), 'exito');
        $('d-mensaje').textContent = '';
        limpiarFormulario();
        pintarTodo();
    });
    $('d-form').addEventListener('input', pintarPrevia);
    $('d-cancelar').addEventListener('click', limpiarFormulario);

    document.addEventListener('click', ev => {
        const editarB = ev.target.closest('[data-editar]'), borrarB = ev.target.closest('[data-borrar]'), cerrarB = ev.target.closest('[data-cerrar]');
        if (editarB) editar(editarB.dataset.editar);
        if (borrarB && confirm(C.t('js.dia_confirmar_borrar'))) {
            ops = ops.filter(o => o.id !== borrarB.dataset.borrar);
            guardar();
            pintarTodo();
        }
        if (cerrarB) {
            const o = ops.find(x => x.id === cerrarB.dataset.cerrar);
            const p = o && precioActual(o);
            if (!p) { editar(o.id); $('d-salida').focus(); return; }
            o.salida = p;
            guardar();
            C.toast(C.t('js.dia_cerrada', { precio: C.precio(p) }), 'exito');
            pintarTodo();
        }
    });

    $('d-filtro').addEventListener('click', ev => {
        const b = ev.target.closest('[data-filtro]');
        if (!b) return;
        filtro = b.dataset.filtro;
        $('d-filtro').querySelectorAll('button').forEach(x => x.classList.toggle('activa', x === b));
        pintarTabla();
    });

    // ---------- Importar del juego y exportar ----------

    /** Trae las ventas del juego "Trading en vivo" (cada venta es una operación cerrada). */
    $('d-importar').addEventListener('click', () => {
        const juego = C.leer('minitrading_v1', null);
        const ventas = (juego?.operaciones || []).filter(o => o.resultado !== null && o.resultado !== undefined);
        let nuevas = 0;
        ventas.forEach(v => {
            const id = 'juego-' + v.fecha;
            if (ops.some(o => o.id === id)) return;
            const costo = v.total - v.resultado; // lo que había costado lo vendido
            ops.push({
                id, creado: v.fecha, origen: 'juego', fecha: C.fechaISO(v.fecha), moneda: v.simbolo, lado: 'compra',
                entrada: costo / v.cantidad, salida: v.precio, monto: costo, comision: 0,
                estrategia: v.tipo === 'stop_loss' || v.tipo === 'take_profit' ? 'tendencia' : 'otra', emocion: 'tranquilo', plan: true,
                notas: C.t('js.dia_nota_juego', { tipo: v.tipo }),
            });
            nuevas++;
        });
        guardar();
        pintarTodo();
        C.toast(nuevas ? C.t('js.dia_importadas', { n: nuevas }) : C.t('js.dia_nada_importar'), nuevas ? 'exito' : 'alerta');
    });

    $('d-exportar').addEventListener('click', () => {
        const cab = ['fecha', 'moneda', 'lado', 'entrada', 'salida', 'monto_usd', 'comision_pct', 'resultado_usd', 'estrategia', 'emocion', 'siguio_plan', 'notas'];
        const celda = v => { const s = String(v ?? ''); return /^[=+\-@]/.test(s) ? `"'${s.replace(/"/g, '""')}"` : `"${s.replace(/"/g, '""')}"`; };
        const filas = ops.map(o => [o.fecha, o.moneda, o.lado, o.entrada, o.salida ?? '', o.monto, o.comision, (resultado(o) ?? 0).toFixed(2), o.estrategia, o.emocion, o.plan ? 'si' : 'no', o.notas].map(celda).join(';'));
        const blob = new Blob(['﻿' + [cab.join(';'), ...filas].join('\n')], { type: 'text/csv;charset=utf-8' });
        const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: `diario_trading_${hoy()}.csv` });
        a.click();
        URL.revokeObjectURL(a.href);
    });

    // ---------- Inicio ----------

    limpiarFormulario();
    pintarTodo();
    document.addEventListener('tema', pintarCurva);
    // Precios en vivo: sugerencias de monedas y resultado flotante de las operaciones abiertas.
    C.iniciarRefresco(m => {
        mercado = m;
        $('d-lista-monedas').innerHTML = m.monedas.map(x => `<option value="${C.escapar(x.symbol.toUpperCase())}">${C.escapar(x.name)}</option>`).join('');
        pintarTodo();
    });
    $('d-moneda').addEventListener('change', () => {
        const p = precioActual({ moneda: $('d-moneda').value.trim() });
        if (p && !$('d-entrada').value) { $('d-entrada').value = p; pintarPrevia(); }
    });
})();
