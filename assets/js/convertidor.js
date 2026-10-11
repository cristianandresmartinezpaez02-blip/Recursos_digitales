/* Convertidor entre cualquier moneda del top 100 y las divisas del mundo (USD, EUR, COP…),
   con precios en vivo. Las divisas usan api/divisas.php (unidades por 1 USD). */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    let mercado = null;
    let divisas = { USD: 1 };
    const nombreDivisa = (() => {
        try { const dn = new Intl.DisplayNames([APP.locale], { type: 'currency' }); return c => dn.of(c); } catch (e) { return c => c; }
    })();
    const PRINCIPALES = ['USD', 'EUR', 'COP', 'MXN', 'BRL', 'ARS', 'CLP', 'PEN', 'GBP', 'JPY', 'CNY', 'CAD', 'CHF'];

    // Las divisas llevan el prefijo "fx:" para no confundirlas con una cripto del mismo nombre.
    const esDivisa = id => id.startsWith('fx:');
    const codigo = id => id.slice(3);
    /** Valor en USD de 1 unidad. */
    const precioDe = id => esDivisa(id) ? 1 / divisas[codigo(id)] : mercado.porId[id].current_price;
    const simboloDe = id => esDivisa(id) ? codigo(id) : mercado.porId[id].symbol.toUpperCase();
    function formatoCantidad(v, id) {
        if (!esDivisa(id)) return `${C.cantidad(v)} ${simboloDe(id)}`;
        try {
            return new Intl.NumberFormat(APP.locale, { style: 'currency', currency: codigo(id), currencyDisplay: 'narrowSymbol', maximumFractionDigits: v < 1 ? 6 : 2 }).format(v);
        } catch (e) {
            return `${C.numero(v)} ${codigo(id)}`;
        }
    }

    function llenarSelects() {
        const fiat = Object.keys(divisas);
        const resto = fiat.filter(c => !PRINCIPALES.includes(c)).sort();
        const opcionFx = c => `<option value="fx:${c}">${C.escapar(c)} · ${C.escapar(nombreDivisa(c))}</option>`;
        const opciones =
            `<optgroup label="💵 ${C.t('js.divisas_principales')}">${PRINCIPALES.filter(c => divisas[c]).map(opcionFx).join('')}</optgroup>` +
            `<optgroup label="🪙 Cripto">${mercado.monedas.map(m => `<option value="${m.id}">#${m.market_cap_rank} ${C.escapar(m.name)} (${m.symbol.toUpperCase()})</option>`).join('')}</optgroup>` +
            (resto.length ? `<optgroup label="🌍 ${C.t('js.divisas_mundo')}">${resto.map(opcionFx).join('')}</optgroup>` : '');
        ['cv-de', 'cv-a'].forEach(id => {
            const sel = $(id);
            const actual = sel.value;
            sel.innerHTML = opciones;
            const valido = actual && (esDivisa(actual) ? divisas[codigo(actual)] : mercado.porId[actual]);
            sel.value = valido ? actual : (id === 'cv-de' ? 'bitcoin' : `fx:${C.MONEDA.codigo}`);
        });
    }

    function convertir() {
        if (!mercado) return;
        const cant = parseFloat($('cv-cantidad').value) || 0;
        const de = $('cv-de').value, a = $('cv-a').value;
        const resultado = cant * precioDe(de) / precioDe(a);
        $('cv-resultado').innerHTML = `${C.escapar(formatoCantidad(cant, de))} = <span class="titulo-gradiente">${C.escapar(formatoCantidad(resultado, a))}</span>`;
        $('cv-tasa').textContent = `1 ${simboloDe(de)} = ${formatoCantidad(precioDe(de) / precioDe(a), a)} · 1 ${simboloDe(a)} = ${formatoCantidad(precioDe(a) / precioDe(de), de)}`;

        const valorUsd = cant * precioDe(de);
        $('cv-equiv-titulo').textContent = `(${formatoCantidad(cant, de)} = ${C.dinero(valorUsd)})`;
        $('cv-lista').innerHTML = mercado.monedas.filter(m => !m.analisis.es_stablecoin && m.id !== de).slice(0, 12).map(m => `
            <div class="item-conv"><img src="${C.escapar(m.image)}" width="24" height="24" alt=""><span>${C.escapar(m.symbol.toUpperCase())}</span>
            <strong>${C.cantidad(valorUsd / m.current_price)}</strong></div>`).join('') +
            PRINCIPALES.filter(c => divisas[c] && `fx:${c}` !== de).slice(0, 8).map(c => `
            <div class="item-conv"><span class="bandera" aria-hidden="true">${bandera(c)}</span><span>${c}</span>
            <strong>${C.escapar(formatoCantidad(valorUsd * divisas[c], 'fx:' + c))}</strong></div>`).join('');
    }

    /** Bandera a partir del código ISO (las 2 primeras letras son el país; EUR → UE). */
    function bandera(c) {
        const pais = c === 'EUR' ? 'EU' : c.slice(0, 2);
        return /^[A-Z]{2}$/.test(pais) ? String.fromCodePoint(...[...pais].map(l => 0x1F1A5 + l.charCodeAt(0))) : '💱';
    }

    function pintarCompras() {
        if (!mercado) return;
        const usd = parseFloat($('cv-usd').value) || 0;
        $('cv-compras').innerHTML = mercado.monedas.filter(m => !m.analisis.es_stablecoin).slice(0, 6).map(m => `
            <div class="item"><img src="${C.escapar(m.image)}" width="26" height="26" alt="">
            <span><strong>${C.escapar(m.name)}</strong><br><small class="texto-3">${C.precio(m.current_price)}</small></span>
            <span class="derecha"><strong>${C.cantidad(usd / m.current_price)}</strong> ${C.escapar(m.symbol.toUpperCase())}</span></div>`).join('');
    }

    async function cargarDivisas() {
        try {
            const r = await fetch(`${APP.base}/api/divisas.php`, { cache: 'no-store' });
            const d = await r.json();
            if (r.ok && d.tasas) divisas = d.tasas;
        } catch (e) { /* sin divisas: queda solo el dólar */ }
    }

    ['cv-cantidad', 'cv-de', 'cv-a'].forEach(id => $(id).addEventListener('input', convertir));
    $('cv-usd').addEventListener('input', pintarCompras);
    $('cv-invertir').addEventListener('click', () => {
        const de = $('cv-de').value;
        $('cv-de').value = $('cv-a').value;
        $('cv-a').value = de;
        convertir();
    });

    const listas = cargarDivisas();
    C.iniciarRefresco(async m => {
        mercado = m;
        await listas;
        llenarSelects();
        convertir();
        pintarCompras();
    });
})();
