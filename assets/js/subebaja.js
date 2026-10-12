/* ¿Sube o baja?: se muestra parte de la semana real de una moneda misteriosa y
   hay que adivinar si su precio subió o bajó en las ~12 h siguientes. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    const FUTURO = 4;        // puntos a revelar (~3 h cada uno = ~12 h)
    const HISTORIA_MIN = 20; // puntos visibles como mínimo
    const PUNTOS_ACIERTO = 10;
    const COSTO_PISTA = 3;

    let monedas = [];
    let grafico = null;
    let partida = null;
    let ronda = null;

    function nuevaPartida() {
        partida = { puntos: 0, racha: 0, vidas: 3, rondas: 0, usadas: new Set() };
        $('sb-fin').hidden = true;
        pintarHud();
        nuevaRonda();
    }

    function nuevaRonda() {
        const disponibles = monedas.filter(m => !partida.usadas.has(m.id));
        if (!disponibles.length) partida.usadas.clear();
        const lista = disponibles.length ? disponibles : monedas;
        const m = lista[Math.floor(Math.random() * lista.length)];
        partida.usadas.add(m.id);
        const corte = HISTORIA_MIN + Math.floor(Math.random() * (m.sparkline.length - FUTURO - HISTORIA_MIN));
        partida.rondas++;
        // La IA solo ve la misma historia que el jugador (hasta el corte), nunca el futuro.
        const analisis = window.Coach.analizar(m.sparkline.slice(0, corte + 1));
        ronda = { m, corte, respondida: false, pista: false, ia: false, analisis, prediccion: analisis.listo ? window.Coach.prediccion(analisis) : null };
        $('sb-resultado').innerHTML = '';
        $('sb-ia-caja').innerHTML = '';
        $('sb-ia').disabled = !ronda.prediccion;
        $('sb-siguiente').hidden = true;
        $('sb-sube').disabled = false;
        $('sb-baja').disabled = false;
        $('sb-pista').disabled = false;
        $('sb-moneda').textContent = C.t('js.sb_misteriosa');
        $('sb-ronda').textContent = C.t('js.sb_ronda', { n: partida.rondas });
        dibujar(false);
    }

    function dibujar(revelar) {
        const { m, corte } = ronda;
        const historia = m.sparkline.slice(0, corte + 1);
        const futuro = m.sparkline.slice(corte, corte + FUTURO + 1);
        const subio = futuro[futuro.length - 1] >= futuro[0];
        const etiquetas = m.sparkline.slice(0, corte + FUTURO + 1).map((_, i) => i <= corte ? '' : '?');
        const acento = C.css('--acento');
        const colorFuturo = C.css(subio ? '--sube' : '--baja');
        if (grafico) grafico.destroy();
        grafico = new Chart($('sb-grafico'), {
            type: 'line',
            data: {
                labels: etiquetas,
                datasets: [
                    { label: C.t('js.sb_antes'), data: historia, borderColor: acento, backgroundColor: acento + '1a', fill: true, borderWidth: 2, pointRadius: 0, tension: 0.25 },
                    { label: C.t('js.sb_despues'), data: revelar ? [...Array(corte).fill(null), ...futuro] : [], borderColor: colorFuturo, borderDash: [6, 4], borderWidth: 3, pointRadius: 0, tension: 0.25 },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false, animation: { duration: revelar ? 800 : 300 },
                plugins: { legend: { display: revelar, labels: { color: C.css('--texto-2'), boxWidth: 12, boxHeight: 12 } }, tooltip: { enabled: false } },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { display: false } },
                    y: { position: 'right', grid: { color: C.css('--borde') }, border: { display: false }, ticks: { color: C.css('--texto-3'), callback: v => C.precio(v), maxTicksLimit: 5 } },
                },
            },
        });
    }

    function responder(prediccion) {
        if (!ronda || ronda.respondida) return;
        ronda.respondida = true;
        const { m, corte } = ronda;
        const antes = m.sparkline[corte], despues = m.sparkline[corte + FUTURO];
        const cambio = (despues / antes - 1) * 100;
        const acierto = (cambio >= 0) === (prediccion === 'sube');
        if (acierto) {
            partida.racha++;
            const ganados = PUNTOS_ACIERTO + (partida.racha - 1) * 5;
            partida.puntos += ronda.ia ? Math.ceil(ganados / 2) : ganados;
        } else {
            partida.racha = 0;
            partida.vidas--;
        }
        $('sb-sube').disabled = true;
        $('sb-baja').disabled = true;
        $('sb-pista').disabled = true;
        $('sb-ia').disabled = true;
        $('sb-moneda').innerHTML = `<span class="moneda" style="pointer-events:none"><img src="${C.escapar(m.image)}" width="24" height="24" alt="">${C.escapar(m.name)}</span>`;
        // La IA se evalúa en todas las rondas (aunque no se le pregunte): así su % de aciertos es honesto.
        let textoIa = '';
        if (ronda.prediccion) {
            const iaOk = ronda.prediccion.direccion === (cambio >= 0 ? 'sube' : 'baja');
            window.Coach.registrar('subebaja', iaOk);
            textoIa = C.t(iaOk ? 'js.sb_ia_acerto' : 'js.sb_ia_fallo', { dir: C.t('js.sb_ia_' + ronda.prediccion.direccion) });
            const razon = ronda.analisis.razones.find(r => r[2] !== 0);
            if (razon) textoIa += `<br>${C.t('js.sb_por_que', { razon: `${razon[0]} ${razon[1]}` })}`;
        }
        // Explicación: ¿la moneda siguió la tendencia de las ~24 h anteriores o cambió de dirección?
        const previa = (m.sparkline[corte] / m.sparkline[Math.max(0, corte - 8)] - 1) * 100;
        const siguio = (previa >= 0) === (cambio >= 0);
        const explicacion = C.t(siguio ? 'js.sb_siguio' : 'js.sb_revirtio', {
            previa: C.porcentaje(previa),
            direccion: C.t(previa >= 0 ? 'js.sb_subiendo' : 'js.sb_bajando'),
        });
        // ¿Por qué subió o bajó? Con Bitcoin y el resto de monedas en esas mismas ~12 h.
        const porQue = window.Coach.porQueSeMovio({
            serie: m.sparkline, inicio: corte, fin: corte + FUTURO, ...window.Coach.contextoSemana(m, monedas, corte, corte + FUTURO),
        });
        const stats = C.leer('subebaja_stats', { rondas: 0, aciertos: 0, tendencia: 0 });
        stats.rondas++;
        if (acierto) stats.aciertos++;
        if (siguio) stats.tendencia++;
        C.escribir('subebaja_stats', stats);

        $('sb-resultado').innerHTML = `<div class="resultado-ronda ${acierto ? 'acierto' : 'fallo'}">
            ${acierto ? '✔ ' + C.t('js.sb_acierto') : '✖ ' + C.t('js.sb_fallo')}<br>
            <span style="font-weight:500">${C.t('js.sb_cambio', { simbolo: m.symbol.toUpperCase() })} ${C.porcentaje(cambio)}</span></div>
            ${porQue ? window.Coach.htmlPorQue(porQue, m.symbol.toUpperCase()) : `<p class="explicacion-ronda">📖 ${explicacion}</p>`}
            ${textoIa ? `<p class="explicacion-ronda">${textoIa}</p>` : ''}`;
        dibujar(true);
        pintarHud();
        if (partida.vidas <= 0) setTimeout(finPartida, 1600);
        else $('sb-siguiente').hidden = false;
    }

    function finPartida() {
        const record = Math.max(C.leer('subebaja_record', 0), partida.puntos);
        const nuevo = partida.puntos > 0 && partida.puntos >= record && partida.puntos > C.leer('subebaja_record', 0);
        C.escribir('subebaja_record', record);
        $('sb-fin').hidden = false;
        $('sb-final').textContent = partida.puntos;
        $('sb-final-texto').textContent = nuevo ? C.t('js.sb_nuevo_record') : C.t('js.sb_record_actual', { n: record });
        $('sb-fin').scrollIntoView({ behavior: 'smooth' });
        pintarHud();
    }

    function pintarHud() {
        $('sb-puntos').textContent = partida.puntos;
        $('sb-racha').textContent = partida.racha;
        $('sb-vidas').textContent = '❤️'.repeat(Math.max(0, partida.vidas)) + '🤍'.repeat(3 - Math.max(0, partida.vidas));
        $('sb-record').textContent = Math.max(C.leer('subebaja_record', 0), partida.puntos);
        const stats = C.leer('subebaja_stats', null);
        if (stats && stats.rondas) {
            $('sb-precision').textContent = `${Math.round(stats.aciertos / stats.rondas * 100)} %`;
            $('sb-precision-sub').textContent = C.t('js.sb_precision_d', { n: stats.rondas, tendencia: Math.round(stats.tendencia / stats.rondas * 100) });
        }
    }

    $('sb-sube').addEventListener('click', () => responder('sube'));
    $('sb-baja').addEventListener('click', () => responder('baja'));
    $('sb-siguiente').addEventListener('click', nuevaRonda);
    $('sb-reiniciar').addEventListener('click', nuevaPartida);
    $('sb-pista').addEventListener('click', () => {
        if (!ronda || ronda.pista || ronda.respondida) return;
        ronda.pista = true;
        partida.puntos = Math.max(0, partida.puntos - COSTO_PISTA);
        $('sb-moneda').innerHTML = `<span class="moneda" style="pointer-events:none"><img src="${C.escapar(ronda.m.image)}" width="24" height="24" alt="">${C.escapar(ronda.m.name)}</span>`;
        $('sb-pista').disabled = true;
        pintarHud();
    });
    $('sb-ia').addEventListener('click', () => {
        if (!ronda || ronda.ia || ronda.respondida || !ronda.prediccion) return;
        ronda.ia = true;
        $('sb-ia').disabled = true;
        const { direccion, confianza } = ronda.prediccion;
        const razones = ronda.analisis.razones.slice(0, 3)
            .map(([i, texto, p]) => `<li class="${p > 0 ? 'pro' : p < 0 ? 'contra' : ''}"><span aria-hidden="true">${i}</span><span>${texto}</span></li>`).join('');
        $('sb-ia-caja').innerHTML = `<div class="coach coach-${direccion === 'sube' ? 'sube' : 'baja'} ia-prediccion">
            <p style="margin:0">${C.t('js.sb_ia_dice', { dir: C.t('js.sb_ia_' + direccion), conf: confianza })}</p>
            <ul class="coach-razones">${razones}</ul>
            <p class="coach-stats">⚖️ ${C.t('js.sb_ia_costo')}</p>${window.Coach.textoStats('subebaja')}</div>`;
    });
    document.addEventListener('keydown', ev => {
        if (ev.target.closest('input, select, textarea')) return;
        if (ev.key === 'ArrowUp') responder('sube');
        if (ev.key === 'ArrowDown') responder('baja');
        if (ev.key === 'Enter' && !$('sb-siguiente').hidden) nuevaRonda();
    });
    document.addEventListener('tema', () => ronda && dibujar(ronda.respondida));

    C.obtenerMercado().then(mercado => {
        monedas = mercado.monedas.filter(m => !m.analisis.es_stablecoin && m.sparkline && m.sparkline.length >= HISTORIA_MIN + FUTURO + 5).slice(0, 60);
        nuevaPartida();
    }).catch(() => C.toast(C.t('js.sin_conexion'), 'alerta'));
})();
