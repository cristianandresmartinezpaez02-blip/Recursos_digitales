/* Bolsa y divisas: índices, acciones, materias primas y monedas del mundo en vivo,
   con gráfico del activo elegido. Datos: api/bolsa.php y api/divisas.php. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    const G = window.Graficos;
    let bolsa = null, divisas = null;
    let elegido = new URLSearchParams(location.search).get('simbolo') || 'AAPL';
    let dias = 30;
    const nombreDivisa = (() => {
        try { const dn = new Intl.DisplayNames([APP.locale], { type: 'currency' }); return c => dn.of(c); } catch (e) { return c => c; }
    })();
    const bandera = c => {
        const pais = c === 'EUR' ? 'EU' : c.slice(0, 2);
        return /^[A-Z]{2}$/.test(pais) ? String.fromCodePoint(...[...pais].map(l => 0x1F1A5 + l.charCodeAt(0))) : '💱';
    };
    /** Los índices son puntos, no dinero; lo demás se muestra en la moneda elegida. */
    const valor = a => a.tipo === 'indice' ? C.numero(a.current_price) : C.precio(a.current_price);
    const todos = () => bolsa ? [...bolsa.acciones, ...bolsa.indices, ...bolsa.materias] : [];
    const buscar = simbolo => todos().find(a => a.simbolo_yahoo === simbolo);

    function pintarIndices() {
        $('lista-indices').innerHTML = bolsa.indices.map(a => `
            <button type="button" class="tarjeta-indice ${a.simbolo_yahoo === elegido ? 'activa' : ''}" data-simbolo="${C.escapar(a.simbolo_yahoo)}">
                <span class="nombre">${C.escapar(a.name)}</span>
                <strong>${valor(a)}</strong>${C.porcentaje(a.price_change_percentage_24h)}
                ${C.sparkline(a.sparkline, 120, 30)}
            </button>`).join('');
        $('bolsa-hora').textContent = C.t('js.bolsa_actualizado', { hora: C.hora(bolsa.actualizado * 1000) });
    }

    function filaActivo(a, conSenal = true) {
        return `<tr class="${a.simbolo_yahoo === elegido ? 'fila-activa' : ''}">
            <td>${C.celdaMoneda(a)}<br><small class="texto-3">${C.escapar(a.bolsa)}${a.mercado_abierto ? ' · <span class="sube">● ' + C.t('js.bolsa_abierta') + '</span>' : ''}</small></td>
            <td class="num"><strong>${valor(a)}</strong></td>
            <td class="num">${C.porcentaje(a.price_change_percentage_24h)}</td>
            <td class="ocultar-movil">${C.sparkline(a.sparkline)}</td>
            ${conSenal ? `<td>${C.insignia(a.analisis, true)}</td>` : ''}
            <td class="acciones-fila"><button class="boton mini secundario" type="button" data-simbolo="${C.escapar(a.simbolo_yahoo)}">📈</button>
            ${a.tipo === 'accion' ? `<a class="boton mini" href="${APP.base}/paginas/trading.php?moneda=${encodeURIComponent(a.id)}">${C.t('js.invertir')}</a>` : ''}</td>
        </tr>`;
    }

    function pintarAcciones() {
        const q = $('buscar-accion').value.trim().toLowerCase();
        const lista = bolsa.acciones.filter(a => !q || a.name.toLowerCase().includes(q) || a.symbol.toLowerCase().includes(q));
        $('tabla-acciones').innerHTML = lista.map(a => filaActivo(a)).join('') || `<tr><td colspan="6" class="cargando">${C.t('js.sin_resultados')}</td></tr>`;
        $('tabla-materias').innerHTML = bolsa.materias.map(a => filaActivo(a, false)).join('');
    }

    function pintarDetalle() {
        const a = buscar(elegido);
        if (!a) return;
        const an = a.analisis;
        const rango = a.max_52s > a.min_52s ? (a.current_price - a.min_52s) / (a.max_52s - a.min_52s) * 100 : null;
        $('detalle-activo').innerHTML = `
            <div class="fila-info"><span class="moneda" style="pointer-events:none"><img src="${a.image}" width="28" height="28" alt=""><strong>${C.escapar(a.name)}</strong></span></div>
            <div class="fila-info"><span class="texto-2">${C.t('js.precio')}</span><strong>${valor(a)}</strong></div>
            <div class="fila-info"><span class="texto-2">${C.t('js.bolsa_hoy')}</span>${C.porcentaje(a.price_change_percentage_24h)}</div>
            <div class="fila-info"><span class="texto-2">${C.t('js.bolsa_mercado')}</span><span>${a.mercado_abierto ? `<span class="sube">● ${C.t('js.bolsa_abierta')}</span>` : `<span class="texto-3">● ${C.t('js.bolsa_cerrada')}</span>`}</span></div>
            ${rango !== null ? `<div class="campo-rango"><span class="texto-2">${C.t('js.bolsa_rango52')}</span>
                <div class="barra-rango"><span style="left:${Math.max(0, Math.min(100, rango))}%"></span></div>
                <small class="texto-3">${a.tipo === 'indice' ? C.numero(a.min_52s) : C.precio(a.min_52s)} — ${a.tipo === 'indice' ? C.numero(a.max_52s) : C.precio(a.max_52s)}</small></div>` : ''}
            ${a.tipo === 'accion' ? `<p>${C.insignia(an, true)}</p><p class="texto-2" style="font-size:.85rem">${C.escapar(an.resumen)}</p>
                <ul class="lista-razones">${an.pros.map(p => `<li class="sube">✔ ${C.escapar(p)}</li>`).join('')}${an.contras.map(p => `<li class="baja">✖ ${C.escapar(p)}</li>`).join('')}</ul>
                <a class="boton bloque" href="${APP.base}/paginas/trading.php?moneda=${encodeURIComponent(a.id)}">💹 ${C.t('js.bolsa_invertir', { simbolo: a.symbol })}</a>` : ''}`;
    }

    async function pintarGrafico() {
        const a = buscar(elegido);
        const esDivisa = /=X$/.test(elegido) && !a;
        const titulo = a ? a.name : `USD → ${elegido.replace('=X', '')}`;
        $('graf-titulo').textContent = titulo;
        $('graf-sub').textContent = C.t('js.cargando');
        try {
            const r = await fetch(`${APP.base}/api/bolsa.php?historial=${encodeURIComponent(elegido)}&dias=${dias}`, { cache: 'no-store' });
            const d = await r.json();
            if (!r.ok) throw new Error(d.error);
            const puntos = d.puntos.map(p => ({ x: p.t * 1000, y: p.p }));
            if (!puntos.length) throw new Error('vacío');
            const cambio = (puntos[puntos.length - 1].y / puntos[0].y - 1) * 100;
            const formato = esDivisa ? v => C.numero(v, v < 10 ? 4 : 2) : a && a.tipo === 'indice' ? v => C.numero(v, 0) : C.precio;
            $('graf-sub').innerHTML = C.t('js.bolsa_en_periodo', { cambio: C.porcentaje(cambio) });
            G.reemplazar($('graf-bolsa'), () => G.linea($('graf-bolsa'), puntos, { etiqueta: titulo, formato, dias }));
        } catch (e) {
            $('graf-sub').textContent = C.t('js.error_historial');
        }
    }

    function pintarDivisas() {
        if (!divisas) return;
        const vivas = Object.keys(divisas.vivo);
        $('tabla-divisas-vivo').innerHTML = vivas.map(c => {
            const v = divisas.vivo[c], t = divisas.tasas[c];
            return `<tr>
                <td><span class="bandera" aria-hidden="true">${bandera(c)}</span> <strong>${c}</strong> <small class="texto-3">${C.escapar(nombreDivisa(c))}</small></td>
                <td class="num"><strong>${C.numero(t, t < 10 ? 4 : 2)}</strong></td>
                <td class="num texto-2">${C.numero(1 / t, 1 / t < 0.01 ? 6 : 4)} USD</td>
                <td class="num">${C.porcentaje(v.cambio)}</td>
                <td class="ocultar-movil">${C.sparkline(v.spark)}</td>
                <td><button class="boton mini secundario" type="button" data-simbolo="${c}=X">📈</button></td></tr>`;
        }).join('');
        const q = ($('buscar-divisa').value || '').trim().toLowerCase();
        const codigos = Object.keys(divisas.tasas).filter(c => c !== 'USD' && (!q || c.toLowerCase().includes(q) || nombreDivisa(c).toLowerCase().includes(q)));
        $('divisas-cuenta').textContent = `(${Object.keys(divisas.tasas).length})`;
        $('lista-divisas').innerHTML = codigos.map(c => `<div class="item-divisa"><span class="bandera" aria-hidden="true">${bandera(c)}</span>
            <span><strong>${c}</strong><small>${C.escapar(nombreDivisa(c))}</small></span>
            <span class="derecha">${C.numero(divisas.tasas[c], divisas.tasas[c] < 10 ? 4 : 2)}</span></div>`).join('') || `<p class="texto-3">${C.t('js.sin_resultados')}</p>`;
        $('divisas-fecha').textContent = C.t('js.divisas_fecha', { fecha: C.fecha(divisas.fecha_tasas * 1000, { dateStyle: 'medium', timeStyle: 'short' }) });
    }

    function elegir(simbolo) {
        elegido = simbolo;
        const url = new URL(location.href);
        url.searchParams.set('simbolo', simbolo);
        history.replaceState(null, '', url);
        if (bolsa) { pintarIndices(); pintarAcciones(); pintarDetalle(); }
        pintarGrafico();
        $('graf-titulo').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    async function cargar() {
        try {
            const [rb, rd] = await Promise.all([
                fetch(`${APP.base}/api/bolsa.php`, { cache: 'no-store' }),
                fetch(`${APP.base}/api/divisas.php`, { cache: 'no-store' }),
            ]);
            const b = await rb.json(), d = await rd.json();
            if (rb.ok) bolsa = b;
            if (rd.ok) divisas = d;
        } catch (e) {
            console.error(e);
        }
        if (bolsa) {
            if (!buscar(elegido) && !/=X$/.test(elegido)) elegido = 'AAPL';
            pintarIndices();
            pintarAcciones();
            pintarDetalle();
        } else {
            $('lista-indices').innerHTML = `<p class="alerta">${C.t('js.bolsa_error')}</p>`;
        }
        pintarDivisas();
    }

    document.addEventListener('click', ev => {
        const b = ev.target.closest('[data-simbolo]');
        if (b) elegir(b.dataset.simbolo);
    });
    $('graf-rango').addEventListener('click', ev => {
        const b = ev.target.closest('[data-dias]');
        if (!b) return;
        dias = Number(b.dataset.dias);
        $('graf-rango').querySelectorAll('.chip').forEach(x => x.classList.toggle('activo', x === b));
        pintarGrafico();
    });
    $('buscar-accion').addEventListener('input', () => bolsa && pintarAcciones());
    $('buscar-divisa').addEventListener('input', pintarDivisas);
    document.addEventListener('tema', pintarGrafico);
    C.iniciarPestanas('pestanas-bolsa');

    cargar().then(pintarGrafico);
    // La bolsa se actualiza cada 2 minutos en el servidor: se vuelve a pedir cada 2 minutos.
    setInterval(cargar, 120000);
})();
