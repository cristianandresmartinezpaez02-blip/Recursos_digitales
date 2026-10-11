/* Coach IA: lee la serie de precios que el jugador YA ve (nunca el futuro) y calcula
   indicadores de análisis técnico (tendencia por medias móviles, impulso, RSI,
   volatilidad y posición en el rango). Con ellos da una recomendación explicada
   —comprar, esperar o vender— con stop-loss, take-profit y tamaño de posición.
   Ninguna herramienta acierta siempre: por eso lleva la cuenta de sus propios aciertos.
   Si el servidor tiene configurado Claude (api/coach.php), también puede dar una
   explicación redactada por IA generativa a partir de este mismo análisis. */
(function () {
    'use strict';
    const C = window.Cripto;
    const MIN_PUNTOS = 16;

    const media = a => a.reduce((s, x) => s + x, 0) / a.length;
    const limitar = (v, a, b) => Math.max(a, Math.min(b, v));
    const pct = (v, dec = 1) => `${v >= 0 ? '+' : '−'}${C.numero(Math.abs(v), dec)} %`;

    /** RSI de Cutler (14 periodos): 0-100; >70 sobrecompra, <30 sobreventa. */
    function rsi(serie, n = 14) {
        if (serie.length < n + 1) return null;
        let gan = 0, per = 0;
        for (let i = serie.length - n; i < serie.length; i++) {
            const d = serie[i] - serie[i - 1];
            if (d >= 0) gan += d; else per -= d;
        }
        if (per === 0) return gan === 0 ? 50 : 100;
        return 100 - 100 / (1 + gan / per);
    }

    /**
     * @param {number[]} serie  precios en orden cronológico (solo el pasado visible)
     * @param {{veredicto?:string, cambio24h?:number, esStable?:boolean, puntosDia?:number}} extra
     *        puntosDia: puntos de la serie que hay en 24 h (sparkline de cripto: uno cada 3 h = 8; acciones: 1).
     */
    function analizar(serie, extra = {}) {
        serie = (serie || []).filter(v => v > 0 && Number.isFinite(v));
        if (serie.length < MIN_PUNTOS) return { listo: false, faltan: MIN_PUNTOS - serie.length };
        const porDia = extra.puntosDia || 8;
        const ultimo = serie[serie.length - 1];
        const corta = media(serie.slice(-12));
        const larga = media(serie.slice(-48));
        const tendencia = (corta / larga - 1) * 100;
        const nImp = Math.min(porDia, serie.length - 1);
        const impulso = (ultimo / serie[serie.length - 1 - nImp] - 1) * 100;
        const rets = [];
        for (let i = Math.max(1, serie.length - 48); i < serie.length; i++) rets.push(serie[i] / serie[i - 1] - 1);
        const m = media(rets);
        const volatilidad = Math.sqrt(media(rets.map(r => (r - m) ** 2))) * Math.sqrt(porDia) * 100; // movimiento típico en un día
        const max = Math.max(...serie), min = Math.min(...serie);
        const rango = max > min ? (ultimo - min) / (max - min) : 0.5;
        const r = rsi(serie);

        const razones = []; // [icono, texto, efecto en el puntaje]
        let puntos = 0;
        const sumar = (p, ico, clave, datos = {}) => { puntos += p; razones.push([ico, C.t(clave, datos), p]); };

        if (extra.esStable) {
            sumar(0, '🏝️', 'js.ia_r_stable');
            return resultado({ puntos: 0, razones, serie, ultimo, tendencia, impulso, volatilidad, rango, r, stable: true });
        }
        if (tendencia > 0.4) sumar(25, '📈', 'js.ia_r_tend_sube', { pct: pct(tendencia) });
        else if (tendencia < -0.4) sumar(-25, '📉', 'js.ia_r_tend_baja', { pct: pct(tendencia) });
        else sumar(0, '➡️', 'js.ia_r_tend_lateral');

        if (impulso > 2) sumar(15, '🚀', 'js.ia_r_imp_sube', { pct: pct(impulso) });
        else if (impulso < -2) sumar(-15, '🧊', 'js.ia_r_imp_baja', { pct: pct(impulso) });

        if (r !== null) {
            const v = C.numero(r, 0);
            // En tendencia, un RSI extremo avisa de que el movimiento está estirado, no de que se acabó.
            if (r >= 70) sumar(r >= 80 ? -20 : -10, '🌡️', 'js.ia_r_rsi_alto', { v });
            else if (r <= 30) sumar(r <= 20 ? 15 : 10, '🧲', 'js.ia_r_rsi_bajo', { v });
            else sumar(0, '⚖️', 'js.ia_r_rsi_medio', { v });
        }

        if (rango > 0.9 && tendencia > 0) sumar(-10, '🧗', 'js.ia_r_techo');
        else if (rango < 0.12 && tendencia < 0) sumar(-5, '🔪', 'js.ia_r_cuchillo');
        else if (rango < 0.2 && tendencia >= 0) sumar(10, '🪜', 'js.ia_r_suelo');

        if (extra.veredicto === 'viable') sumar(10, '✔', 'js.ia_r_senal_si');
        else if (extra.veredicto === 'no_viable') sumar(-10, '✖', 'js.ia_r_senal_no');
        if (extra.cambio24h > 15) sumar(-10, '🏃', 'js.ia_r_perseguir', { pct: C.numero(extra.cambio24h, 1) });
        if (volatilidad > 6) sumar(0, '🌪️', 'js.ia_r_volatil', { pct: C.numero(volatilidad, 1) });

        return resultado({ puntos, razones, serie, ultimo, tendencia, impulso, volatilidad, rango, r });
    }

    function resultado(d) {
        const puntos = limitar(d.puntos, -100, 100);
        const accion = d.stable ? 'esperar' : puntos >= 20 ? 'comprar' : puntos <= -20 ? 'vender' : 'esperar';
        // Nunca más de 85 %: el mercado siempre puede sorprender.
        const confianza = Math.round(limitar(40 + Math.abs(puntos) * 0.55 - (d.volatilidad > 6 ? 10 : 0), 30, 85));
        // Stop-loss a 1,5 veces el movimiento típico (no te saca el ruido normal) y objetivo al doble (1:2).
        const sl = limitar(d.volatilidad * 1.5, 1.5, 15);
        const razones = d.razones.slice().sort((a, b) => Math.abs(b[2]) - Math.abs(a[2]));
        return {
            listo: true, puntos, accion, confianza, razones, sl, tp: sl * 2, precio: d.ultimo,
            rsi: d.r, tendencia: d.tendencia, impulso: d.impulso, volatilidad: d.volatilidad, rango: d.rango,
        };
    }

    /** Para "¿Sube o baja?": dirección más probable en el corto plazo. */
    function prediccion(a) {
        const direccion = a.puntos > 0 ? 'sube' : a.puntos < 0 ? 'baja' : (a.impulso >= 0 ? 'sube' : 'baja');
        return { direccion, confianza: Math.round(limitar(50 + Math.abs(a.puntos) * 0.35, 51, 80)) };
    }

    /** Monto para arriesgar como mucho `riesgo` % del patrimonio si salta el stop-loss (máx. 25 %). */
    function tamano(patrimonio, slPct, efectivo, riesgo = 2) {
        const monto = Math.min(patrimonio * riesgo / slPct, patrimonio * 0.25, efectivo);
        return Math.max(0, Math.floor(monto));
    }

    const ETIQUETAS = { comprar: ['🟢', 'js.ia_comprar', 'sube'], esperar: ['🟡', 'js.ia_esperar', 'neutral'], vender: ['🔴', 'js.ia_vender', 'baja'] };

    /** Tarjeta HTML con la recomendación. opciones: { titulo, extra (html), maxRazones, stats (clave) } */
    function tarjeta(a, opciones = {}) {
        if (!a || !a.listo) {
            return `<div class="coach"><div class="coach-cabeza"><span class="coach-avatar" aria-hidden="true">🤖</span>
                <div><strong>${C.t('js.ia_titulo')}</strong><small>${C.t('js.ia_faltan', { n: a ? a.faltan : MIN_PUNTOS })}</small></div></div></div>`;
        }
        const [ico, clave, clase] = ETIQUETAS[a.accion];
        const razones = a.razones.slice(0, opciones.maxRazones || 4)
            .map(([i, texto, p]) => `<li class="${p > 0 ? 'pro' : p < 0 ? 'contra' : ''}"><span aria-hidden="true">${i}</span><span>${texto}</span></li>`).join('');
        return `<div class="coach coach-${clase}">
            <div class="coach-cabeza">
                <span class="coach-avatar" aria-hidden="true">🤖</span>
                <div><strong>${opciones.titulo || C.t('js.ia_titulo')}</strong><small>${C.t('js.ia_subtitulo')}</small></div>
                <span class="coach-accion ${clase}">${ico} ${C.t(clave)}</span>
            </div>
            <div class="coach-confianza" title="${C.t('js.ia_confianza_d')}">
                <span>${C.t('js.ia_confianza')}</span><span class="pista"><span style="width:${a.confianza}%"></span></span><strong>${a.confianza} %</strong>
            </div>
            <ul class="coach-razones">${razones}</ul>
            ${opciones.extra || ''}
            ${opciones.stats ? textoStats(opciones.stats) : ''}
        </div>`;
    }

    /** Texto con el plan de la operación: dónde salir si sale mal y dónde tomar ganancias. */
    function plan(a, precio = a.precio) {
        if (!a.listo || a.accion === 'vender') return '';
        return `<p class="coach-plan">🎯 ${C.t('js.ia_plan', {
            sl: C.numero(a.sl, 1), tp: C.numero(a.tp, 1), psl: C.precio(precio * (1 - a.sl / 100)), ptp: C.precio(precio * (1 + a.tp / 100)),
        })}</p>`;
    }

    // ---------- ¿Por qué esta compra / venta gana o pierde? ----------

    // Comprar y vender cobra comisión dos veces: el precio debe subir al menos esto para no perder.
    const equilibrio = () => (1 / Math.pow(1 - APP.comision, 2) - 1) * 100;
    const textoAccion = a => C.t(ETIQUETAS[a.accion][1]);

    /** d: { simbolo, precio, monto, stopLoss (precio o null), peso (% del patrimonio), a (análisis al comprar) } */
    function explicarCompra(d) {
        const eq = equilibrio();
        const items = [
            C.t('js.exp_compra_ganar', { simbolo: C.escapar(d.simbolo), eq: C.numero(eq), precio: C.precio(d.precio * (1 + eq / 100)) }),
            C.t('js.exp_compra_perder', { uno: C.dinero(d.monto / 100), monto: C.dinero(d.monto) }),
        ];
        if (d.stopLoss) items.push(C.t('js.exp_compra_sl', { precio: C.precio(d.stopLoss), perdida: C.dinero(d.monto * (1 - d.stopLoss / d.precio) + d.monto * APP.comision) }));
        else if (d.stopLoss === null) items.push(C.t('js.exp_compra_sin_sl'));
        if (d.a && d.a.listo) {
            items.push(C.t(({ comprar: 'js.exp_compra_ia_a_favor', esperar: 'js.exp_compra_ia_neutral', vender: 'js.exp_compra_ia_contra' })[d.a.accion], { accion: textoAccion(d.a), conf: d.a.confianza }));
            if (d.a.razones[0]) items.push(`${d.a.razones[0][0]} ${d.a.razones[0][1]}`);
        }
        if (d.peso > 0) items.push(C.t(d.peso > 25 ? 'js.exp_compra_peso_alto' : 'js.exp_compra_peso', { pct: C.numero(d.peso, 0) }));
        return `<div class="explica-op"><h4>🛒 ${C.t('js.exp_compra_titulo', { simbolo: C.escapar(d.simbolo) })}</h4>
            <ul>${items.map(i => `<li>${i}</li>`).join('')}</ul></div>`;
    }

    /** d: { simbolo, precioCompra, precioVenta, comisiones, resultado, a (análisis al vender), accionCompra } */
    function explicarVenta(d) {
        const gano = d.resultado >= 0;
        const cambio = (d.precioVenta / d.precioCompra - 1) * 100;
        const items = [
            C.t('js.exp_venta_precio', { compra: C.precio(d.precioCompra), venta: C.precio(d.precioVenta), cambio: pct(cambio, 2) }),
            C.t('js.exp_venta_comision', { comisiones: C.dinero(d.comisiones) }),
            C.t(gano ? 'js.exp_venta_por_sube' : cambio > 0 ? 'js.exp_venta_por_comision' : 'js.exp_venta_por_baja', { eq: C.numero(equilibrio()) }),
        ];
        if (d.accionCompra) items.push(C.t('js.exp_ia_compra', { accion: C.t(ETIQUETAS[d.accionCompra][1]) }));
        if (d.a && d.a.listo) items.push(C.t(({ comprar: 'js.exp_venta_ia_pronto', esperar: 'js.exp_venta_ia_neutral', vender: 'js.exp_venta_ia_ok' })[d.a.accion], { accion: textoAccion(d.a) }));
        items.push(`💡 ${C.t(gano ? 'js.exp_venta_leccion_gano' : 'js.exp_venta_leccion_perdio')}`);
        return `<div class="explica-op ${gano ? 'gano' : 'perdio'}"><h4>${C.t(gano ? 'js.exp_venta_gano' : 'js.exp_venta_perdio', { monto: C.dinero(Math.abs(d.resultado)), simbolo: C.escapar(d.simbolo) })}</h4>
            <ul>${items.map(i => `<li>${i}</li>`).join('')}</ul></div>`;
    }

    // ---------- Aciertos de la IA (para ser honestos sobre lo que sabe) ----------

    function registrar(clave, acierto) {
        const s = C.leer('coach_stats', {});
        s[clave] = s[clave] || { n: 0, ok: 0 };
        s[clave].n++;
        if (acierto) s[clave].ok++;
        C.escribir('coach_stats', s);
    }
    function textoStats(clave) {
        const s = (C.leer('coach_stats', {}) || {})[clave];
        const base = s && s.n ? C.t('js.ia_stats', { ok: s.ok, n: s.n, pct: Math.round(s.ok / s.n * 100) }) : C.t('js.ia_stats_vacio');
        return `<p class="coach-stats">📊 ${base} ${C.t('js.ia_aviso')}</p>`;
    }

    // ---------- Explicación con IA generativa (Claude), si el servidor la tiene ----------

    /** Devuelve el texto de Claude o lanza un Error (el juego sigue con el análisis local). */
    async function preguntarClaude(contexto) {
        if (!APP.iaClaude) throw new Error(C.t('js.ia_claude_off'));
        if (!APP.usuario) throw new Error(C.t('js.ia_claude_cuenta'));
        const r = await C.api('coach.php', contexto);
        return r.texto;
    }

    /** Resumen compacto del análisis para enviar al servidor. */
    function resumen(a) {
        return a && a.listo ? {
            accion: a.accion, confianza: a.confianza, rsi: a.rsi === null ? null : Math.round(a.rsi),
            tendencia: +a.tendencia.toFixed(2), impulso: +a.impulso.toFixed(2), volatilidad: +a.volatilidad.toFixed(2),
            rango: +a.rango.toFixed(2), sl: +a.sl.toFixed(1), tp: +a.tp.toFixed(1),
            razones: a.razones.map(x => x[1].replace(/<[^>]+>/g, '')).slice(0, 6),
        } : null;
    }

    /** Botón + caja para pedir la explicación de Claude. contexto() se evalúa al pulsar. */
    function botonClaude(caja, contexto) {
        if (!APP.iaClaude) return;
        caja.insertAdjacentHTML('beforeend', `<button class="boton mini secundario coach-claude-boton" type="button">✨ ${C.t('js.ia_claude_boton')}</button><div class="coach-claude" hidden></div>`);
        const boton = caja.querySelector('.coach-claude-boton'), salida = caja.querySelector('.coach-claude');
        boton.addEventListener('click', async () => {
            boton.disabled = true;
            salida.hidden = false;
            salida.textContent = C.t('js.ia_claude_pensando');
            try {
                salida.textContent = await preguntarClaude(contexto());
            } catch (e) {
                salida.textContent = e.message;
            } finally {
                boton.disabled = false;
            }
        });
    }

    window.Coach = {
        analizar, prediccion, tamano, tarjeta, plan, registrar, textoStats, preguntarClaude, resumen, botonClaude, pct,
        explicarCompra, explicarVenta, textoAccion,
    };
})();
