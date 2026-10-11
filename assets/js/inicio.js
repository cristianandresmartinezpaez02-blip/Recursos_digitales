/* Inicio: portada con monedas flotando, datos clave y mejores/peores 25. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    let mercado = null;

    function pintarPortada() {
        const ms = mercado.monedas;
        const g = mercado.global;
        const suben = ms.filter(m => m.price_change_percentage_24h > 0).length;
        const mejor = mercado.porId[mercado.mejores[0]];
        const peor = mercado.porId[mercado.peores[0]];
        const enlace = m => `<a href="${C.urlMoneda(m.id)}">${C.escapar(m.name)}</a> (${C.porcentaje(m.price_change_percentage_24h, 1)})`;
        let texto = '';
        if (g && g.cambio_24h !== null) {
            const tono = g.cambio_24h >= 0
                ? `<strong class="sube">${C.t('js.sube')} ${C.numero(g.cambio_24h)} %</strong>`
                : `<strong class="baja">${C.t('js.baja')} ${C.numero(Math.abs(g.cambio_24h))} %</strong>`;
            texto += C.t('js.resumen_cap', { tono }) + ' ';
        }
        texto += C.t('js.resumen_suben', { n: `<strong>${suben}</strong>`, total: ms.length }) + ' ';
        texto += C.t('js.resumen_mejor', { mejor: enlace(mejor), peor: enlace(peor) });
        $('resumen-mercado').innerHTML = texto;

        const btc = mercado.porId.bitcoin || ms[0];
        $('hero-cap').textContent = C.grande(g ? g.capitalizacion : ms.reduce((s, m) => s + (m.market_cap || 0), 0));
        $('hero-btc').innerHTML = `${C.precio(btc.current_price)} <small style="font-size:.9rem">${C.porcentaje(btc.price_change_percentage_24h, 1)}</small>`;
        $('hero-suben').innerHTML = `<span class="sube">${suben}</span><small class="texto-3" style="font-size:.9rem"> / ${ms.length}</small>`;

        // Las 8 monedas más grandes (sin stablecoins) orbitando alrededor del centro.
        const orbita = $('orbita');
        if (!orbita.querySelector('.moneda-flotante')) {
            const top = ms.filter(m => !m.analisis.es_stablecoin).slice(0, 8);
            orbita.insertAdjacentHTML('beforeend', top.map((m, i) => {
                const ang = (i / top.length) * Math.PI * 2 - Math.PI / 2;
                const x = 50 + Math.cos(ang) * 40, y = 50 + Math.sin(ang) * 38;
                return `<a class="moneda-flotante" href="${C.urlMoneda(m.id)}" style="left:calc(${x}% - 32px);top:calc(${y}% - 32px);animation-delay:${-i * 0.7}s" tabindex="-1">
                    <img src="${C.escapar(m.image)}" alt=""><span class="etq" data-etq="${m.id}"></span></a>`;
            }).join(''));
        }
        orbita.querySelectorAll('[data-etq]').forEach(el => {
            const m = mercado.porId[el.dataset.etq];
            if (m) el.innerHTML = `${C.escapar(m.symbol.toUpperCase())} ${C.porcentaje(m.price_change_percentage_24h, 1)}`;
        });
    }

    function pintarKpis() {
        const ms = mercado.monedas;
        const g = mercado.global;
        const suma = campo => ms.reduce((s, m) => s + (m[campo] || 0), 0);
        $('kpi-cap').textContent = C.grande(g ? g.capitalizacion : suma('market_cap'));
        $('kpi-cap-sub').innerHTML = g ? `${C.porcentaje(g.cambio_24h)} ${C.t('js.en_24h')}` : 'Top 100';
        const largo = Math.min(...ms.filter(m => m.sparkline.length).map(m => m.sparkline.length));
        const serie = Array.from({ length: largo }, (_, i) => ms.reduce((s, m) => s + (m.sparkline[i] || 0) * (m.circulating_supply || 0), 0));
        $('kpi-cap-spark').innerHTML = C.sparkline(serie, 160, 50).replace('width="160" height="50"', 'width="100%" height="50"');
        $('kpi-vol').textContent = C.grande(g ? g.volumen : suma('total_volume'));

        const cuenta = { viable: 0, neutral: 0, no_viable: 0 };
        ms.forEach(m => { cuenta[m.analisis.veredicto]++; });
        $('kpi-senal').innerHTML = `<span class="sube">${cuenta.viable}</span> <small class="texto-3" style="font-size:.9rem">${C.t('js.viables')}</small>`;
        const partes = [['viable', 'js.viables_cap'], ['neutral', 'js.precaucion'], ['no_viable', 'js.no_viables']];
        $('kpi-senal-barra').innerHTML = partes.map(([k, tk]) => `<span style="width:${cuenta[k]}%;background:${C.colorVeredicto(k)}" title="${C.t(tk)}: ${cuenta[k]}"></span>`).join('');
        $('kpi-senal-leyenda').innerHTML = partes.map(([k, tk]) => `<span><i style="background:${C.colorVeredicto(k)}"></i>${C.t(tk)} ${cuenta[k]}</span>`).join('');
    }

    function item(m, pos) {
        return `<a class="item" href="${C.urlMoneda(m.id)}">
            <span class="num-rank">${pos}</span>
            <img src="${C.escapar(m.image)}" alt="" width="28" height="28" loading="lazy">
            <span><strong>${C.escapar(m.name)}</strong><br><small class="texto-3">${C.escapar(m.symbol.toUpperCase())} · ${C.precio(m.current_price)}</small></span>
            <span class="derecha">${C.porcentaje(m.price_change_percentage_24h)}<br>${C.insignia(m.analisis)}</span></a>`;
    }

    function pintarListas() {
        $('lista-mejores').innerHTML = mercado.mejores.slice(0, 10).map((id, i) => item(mercado.porId[id], i + 1)).join('');
        $('lista-peores').innerHTML = mercado.peores.slice(0, 10).map((id, i) => item(mercado.porId[id], i + 1)).join('');
    }

    async function cargarMiedoCodicia() {
        try {
            const r = await fetch(`${APP.base}/api/extras.php`, { cache: 'no-store' });
            const f = (await r.json()).miedo_codicia;
            if (!f) throw new Error('fng');
            $('kpi-fng').textContent = f.clasificacion;
            $('kpi-fng-medidor').innerHTML = C.medidor(f.valor, { ancho: 110, etiqueta: C.t('js.fng') });
        } catch (e) {
            $('kpi-fng').textContent = C.t('js.no_disponible');
        }
    }

    // ---------- Gráficos ----------

    const G = window.Graficos;
    let idGrafico = 'bitcoin', diasGrafico = 7;

    async function pintarGraficoPrecio() {
        const m = mercado.porId[idGrafico];
        if (!m) return;
        $('ini-precio-titulo').textContent = m.name;
        try {
            const r = await fetch(`${APP.base}/api/historial.php?id=${encodeURIComponent(m.id)}&dias=${diasGrafico}`, { cache: 'no-store' });
            const d = await r.json();
            if (!r.ok) throw new Error(d.error);
            const puntos = d.puntos.map(p => ({ x: p.t * 1000, y: p.p }));
            puntos.push({ x: Date.now(), y: m.current_price }); // termina en el precio en vivo
            const cambio = (m.current_price / puntos[0].y - 1) * 100;
            $('ini-precio-sub').innerHTML = `${C.precio(m.current_price)} · ${C.t('js.bolsa_en_periodo', { cambio: C.porcentaje(cambio) })}`;
            G.reemplazar($('ini-precio'), () => G.linea($('ini-precio'), puntos, { etiqueta: m.symbol.toUpperCase(), dias: diasGrafico }));
        } catch (e) {
            $('ini-precio-sub').textContent = C.t('js.error_historial');
        }
    }

    function pintarDominancia() {
        const dom = mercado.global && mercado.global.dominancia;
        if (!dom) return;
        const partes = Object.entries(dom).slice(0, 4).map(([k, v]) => ({ etiqueta: k.toUpperCase(), valor: v }));
        const resto = 100 - partes.reduce((s, p) => s + p.valor, 0);
        if (resto > 0) partes.push({ etiqueta: C.t('js.otras'), valor: resto });
        const colores = ['--serie-1', '--serie-2', '--serie-3', '--serie-4'].slice(0, partes.length - (resto > 0 ? 1 : 0)).map(C.css);
        if (resto > 0) colores.push(C.css('--gris-medio'));
        G.reemplazar($('ini-dominancia'), () => new Chart($('ini-dominancia'), {
            type: 'doughnut',
            data: { labels: partes.map(p => p.etiqueta), datasets: [{ data: partes.map(p => p.valor), backgroundColor: colores, borderColor: C.css('--panel-solido'), borderWidth: 2 }] },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '64%', animation: false,
                plugins: {
                    legend: { position: 'right', labels: { color: C.css('--texto-2'), boxWidth: 12, boxHeight: 12,
                        generateLabels: ch => ch.data.labels.map((l, i) => ({ text: `${l} ${C.numero(partes[i].valor, 1)} %`, fillStyle: colores[i], strokeStyle: colores[i], fontColor: C.css('--texto-2'), index: i })) } },
                    tooltip: { ...C.tooltipChart(), callbacks: { label: i => ` ${i.label}: ${C.numero(i.parsed, 1)} %` } },
                },
            },
        }));
    }

    function pintarCambios() {
        const top = mercado.monedas.filter(m => !m.analisis.es_stablecoin && m.price_change_percentage_24h !== null).slice(0, 10);
        G.reemplazar($('ini-cambios'), () => G.barrasCambio($('ini-cambios'), top.map(m => ({ etiqueta: m.symbol.toUpperCase(), valor: m.price_change_percentage_24h }))));
    }

    /** Resumen de bolsa y divisas: S&P 500, Nasdaq, Dow Jones, oro y el dólar frente a 3 monedas. */
    async function pintarBolsa() {
        try {
            const [rb, rd] = await Promise.all([fetch(`${APP.base}/api/bolsa.php`), fetch(`${APP.base}/api/divisas.php`)]);
            const b = await rb.json(), d = await rd.json();
            const filas = [];
            if (rb.ok) {
                [...b.indices.slice(0, 3), ...b.materias.slice(0, 1)].forEach(a => filas.push(`<a class="item" href="${C.escapar(a.url)}">
                    <span><strong>${C.escapar(a.name)}</strong><br><small class="texto-3">${a.tipo === 'indice' ? C.numero(a.current_price) : C.precio(a.current_price)}</small></span>
                    <span class="derecha">${C.sparkline(a.sparkline, 90, 28)}</span><span class="derecha">${C.porcentaje(a.price_change_percentage_24h)}</span></a>`));
            }
            if (rd.ok) {
                const monedas = [...new Set([C.MONEDA.codigo, 'EUR', 'COP', 'MXN'])].filter(c => c !== 'USD' && d.vivo[c]).slice(0, 3);
                monedas.forEach(c => filas.push(`<a class="item" href="${APP.base}/paginas/bolsa.php?simbolo=${c}%3DX#divisas">
                    <span><strong>USD → ${c}</strong><br><small class="texto-3">1 USD = ${C.numero(d.tasas[c], d.tasas[c] < 10 ? 4 : 2)} ${c}</small></span>
                    <span class="derecha">${C.sparkline(d.vivo[c].spark, 90, 28)}</span><span class="derecha">${C.porcentaje(d.vivo[c].cambio)}</span></a>`));
            }
            $('ini-bolsa').innerHTML = filas.join('') || `<p class="texto-3">${C.t('js.bolsa_error')}</p>`;
        } catch (e) {
            $('ini-bolsa').innerHTML = `<p class="texto-3">${C.t('js.bolsa_error')}</p>`;
        }
    }

    function pintarGraficos() {
        pintarGraficoPrecio();
        pintarDominancia();
        pintarCambios();
    }

    const chips = (id, alElegir) => $(id).addEventListener('click', ev => {
        const b = ev.target.closest('.chip');
        if (!b || !mercado) return;
        $(id).querySelectorAll('.chip').forEach(x => x.classList.toggle('activo', x === b));
        alElegir(b);
        pintarGraficoPrecio();
    });
    chips('ini-moneda', b => { idGrafico = b.dataset.id; });
    chips('ini-rango', b => { diasGrafico = Number(b.dataset.dias); });
    document.addEventListener('tema', () => mercado && pintarGraficos());

    C.iniciarRefresco(m => {
        const primera = !mercado;
        mercado = m;
        pintarPortada();
        pintarKpis();
        pintarListas();
        pintarGraficos();
        if (primera) { cargarMiedoCodicia(); pintarBolsa(); setInterval(pintarBolsa, 120000); }
    });
})();
