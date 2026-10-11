/* Alertas de precio: se guardan en el navegador y se revisan en cada actualización
   (en cualquier página del sitio, ver revisarAlertas en app.js). */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    let mercado = null;

    function pintarSelector() {
        const sel = $('alerta-moneda');
        const actual = sel.value || new URLSearchParams(location.search).get('moneda') || 'bitcoin';
        sel.innerHTML = mercado.monedas.map(m => `<option value="${m.id}">#${m.market_cap_rank} ${C.escapar(m.name)} (${m.symbol.toUpperCase()})</option>`).join('');
        sel.value = mercado.porId[actual] ? actual : mercado.monedas[0].id;
        pintarAyuda();
    }

    function pintarAyuda() {
        const m = mercado && mercado.porId[$('alerta-moneda').value];
        if (!m) return;
        $('alerta-ayuda').textContent = C.t('js.alerta_precio_actual', { simbolo: m.symbol.toUpperCase(), precio: C.precio(m.current_price) });
    }

    function pintarLista() {
        const lista = C.leerAlertas().sort((a, b) => (a.disparada ? 1 : 0) - (b.disparada ? 1 : 0) || b.creada - a.creada);
        if (!lista.length) {
            $('lista-alertas').innerHTML = `<div class="estado-vacio"><div class="grande">🔕</div><p>${C.t('js.alertas_vacio')}</p></div>`;
            return;
        }
        $('lista-alertas').innerHTML = lista.map(a => {
            const m = mercado && mercado.porId[a.moneda];
            const actual = m ? C.precio(m.current_price) : '—';
            const distancia = m ? (a.precio / m.current_price - 1) * 100 : null;
            const condicion = C.t(a.condicion === 'mayor' ? 'js.cond_mayor' : 'js.cond_menor', { precio: C.precio(a.precio) });
            const estado = a.disparada
                ? `<span class="insignia viable">✔ ${C.t('js.alerta_disparada', { fecha: C.fecha(a.disparada, { dateStyle: 'short', timeStyle: 'short' }) })}</span>`
                : `<span class="insignia neutral">⏳ ${C.t('js.alerta_esperando')}${distancia !== null ? ` · ${C.t('js.falta', { n: C.numero(Math.abs(distancia)) })}` : ''}</span>`;
            return `<div class="alerta-item ${a.disparada ? 'disparada' : ''}">
                <img src="${C.escapar(a.imagen)}" width="32" height="32" alt="">
                <div class="texto"><strong>${C.escapar(a.nombre)} (${C.escapar(a.simbolo)})</strong><br>
                    <span class="texto-2">${condicion} · ${C.t('js.actual')}: ${actual}</span></div>
                ${estado}
                <button class="boton secundario mini" data-borrar="${a.id}" type="button" aria-label="${C.t('js.borrar')}">🗑 ${C.t('js.borrar')}</button>
            </div>`;
        }).join('');
    }

    $('form-alerta').addEventListener('submit', ev => {
        ev.preventDefault();
        const m = mercado && mercado.porId[$('alerta-moneda').value];
        const valor = parseFloat($('alerta-precio').value);
        if (!m || !(valor > 0)) return;
        const lista = C.leerAlertas();
        lista.push({ id: Date.now(), moneda: m.id, simbolo: m.symbol.toUpperCase(), nombre: m.name, imagen: m.image, condicion: $('alerta-condicion').value, precio: valor, creada: Date.now(), disparada: null });
        C.guardarAlertas(lista);
        $('alerta-precio').value = '';
        C.toast(C.t('js.alerta_creada', { simbolo: m.symbol.toUpperCase(), precio: C.precio(valor) }), 'exito');
        pintarLista();
    });

    $('lista-alertas').addEventListener('click', ev => {
        const b = ev.target.closest('[data-borrar]');
        if (!b) return;
        C.guardarAlertas(C.leerAlertas().filter(a => String(a.id) !== b.dataset.borrar));
        pintarLista();
    });
    $('borrar-disparadas').addEventListener('click', () => {
        C.guardarAlertas(C.leerAlertas().filter(a => !a.disparada));
        pintarLista();
    });
    $('alerta-moneda').addEventListener('change', pintarAyuda);

    if ('Notification' in window && Notification.permission === 'default') {
        $('permiso-notif').hidden = false;
        $('permiso-notif').addEventListener('click', async () => {
            try { await Notification.requestPermission(); } catch (e) { /* */ }
            $('permiso-notif').hidden = true;
        });
    }

    document.addEventListener('alertas', pintarLista);
    C.iniciarRefresco(m => {
        const primera = !mercado;
        mercado = m;
        if (primera) pintarSelector(); else pintarAyuda();
        pintarLista();
    });
})();
