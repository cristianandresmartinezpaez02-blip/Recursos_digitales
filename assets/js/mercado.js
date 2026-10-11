/* Top 100: tabla ordenable, filtrable y paginada (25 por página). */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    const POR_PAGINA = 25;
    const preciosAnteriores = {};
    let mercado = null;
    let orden = { campo: 'market_cap_rank', asc: true };
    let filtro = new URLSearchParams(location.search).get('filtro') || '';
    let pagina = 1;

    function valorOrden(m, campo) {
        if (campo === 'puntaje') return m.analisis.puntaje;
        if (campo === 'name') return m.name.toLowerCase();
        return m[campo] ?? -Infinity;
    }

    function listaFiltrada() {
        const q = $('buscar').value.trim().toLowerCase();
        const lista = mercado.monedas.filter(m =>
            (!q || m.name.toLowerCase().includes(q) || m.symbol.toLowerCase().includes(q)) &&
            (!filtro || (filtro === 'fav' ? C.esFavorito(m.id) : m.analisis.veredicto === filtro)));
        return lista.sort((a, b) => {
            const va = valorOrden(a, orden.campo), vb = valorOrden(b, orden.campo);
            const r = va < vb ? -1 : va > vb ? 1 : 0;
            return orden.asc ? r : -r;
        });
    }

    function pintar() {
        if (!mercado) return;
        const lista = listaFiltrada();
        const paginas = Math.max(1, Math.ceil(lista.length / POR_PAGINA));
        pagina = Math.min(pagina, paginas);
        const visibles = lista.slice((pagina - 1) * POR_PAGINA, pagina * POR_PAGINA);

        const vacio = filtro === 'fav' ? C.t('js.fav_vacio') : C.t('js.sin_resultados');
        $('cuerpo-top100').innerHTML = visibles.length ? visibles.map(m => `<tr data-id="${m.id}">
            <td>${C.botonFavorito(m.id)}</td>
            <td class="texto-3">${m.market_cap_rank}</td>
            <td>${C.celdaMoneda(m, 26)}</td>
            <td class="num precio"><strong>${C.precio(m.current_price)}</strong></td>
            <td class="num">${C.porcentaje(m.price_change_percentage_24h)}</td>
            <td class="num">${C.porcentaje(m.price_change_percentage_7d_in_currency)}</td>
            <td class="num">${C.grande(m.market_cap)}</td>
            <td>${C.sparkline(m.sparkline, 110, 34)}</td>
            <td>${C.insignia(m.analisis, true)}</td>
        </tr>`).join('') : `<tr><td colspan="9" class="cargando">${vacio}</td></tr>`;

        document.querySelectorAll('#cuerpo-top100 .precio').forEach(td => {
            const id = td.closest('tr').dataset.id;
            C.destellar(td, preciosAnteriores[id], mercado.porId[id].current_price);
        });

        const desde = lista.length ? (pagina - 1) * POR_PAGINA + 1 : 0;
        const hasta = Math.min(pagina * POR_PAGINA, lista.length);
        let botones = `<button data-pag="${pagina - 1}" ${pagina === 1 ? 'disabled' : ''} aria-label="${C.t('js.anterior')}">‹</button>`;
        for (let i = 1; i <= paginas; i++) botones += `<button data-pag="${i}" class="${i === pagina ? 'activa' : ''}">${i}</button>`;
        botones += `<button data-pag="${pagina + 1}" ${pagina === paginas ? 'disabled' : ''} aria-label="${C.t('js.siguiente')}">›</button>`;
        $('paginacion').innerHTML = `<span>${C.t('js.mostrando', { desde, hasta, total: lista.length })}</span><div class="botones">${botones}</div>`;
    }

    $('paginacion').addEventListener('click', ev => {
        const b = ev.target.closest('button[data-pag]');
        if (!b || b.disabled) return;
        pagina = Number(b.dataset.pag);
        pintar();
        $('tabla-top100').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    document.querySelectorAll('#tabla-top100 th[data-orden]').forEach(th => th.addEventListener('click', () => {
        const campo = th.dataset.orden;
        orden = { campo, asc: orden.campo === campo ? !orden.asc : ['market_cap_rank', 'name'].includes(campo) };
        document.querySelectorAll('#tabla-top100 th[data-orden]').forEach(x => x.classList.remove('ordenada', 'asc'));
        th.classList.add('ordenada');
        th.classList.toggle('asc', orden.asc);
        pagina = 1;
        pintar();
    }));

    $('filtro-senal').querySelectorAll('.chip').forEach(x => x.classList.toggle('activo', x.dataset.filtro === filtro));
    $('filtro-senal').addEventListener('click', ev => {
        const b = ev.target.closest('[data-filtro]');
        if (!b) return;
        filtro = b.dataset.filtro;
        pagina = 1;
        $('filtro-senal').querySelectorAll('.chip').forEach(x => x.classList.toggle('activo', x === b));
        pintar();
    });
    $('buscar').addEventListener('input', () => { pagina = 1; pintar(); });
    document.addEventListener('favoritos', () => { if (filtro === 'fav') pintar(); });

    C.iniciarRefresco(m => {
        mercado = m;
        pintar();
        m.monedas.forEach(x => { preciosAnteriores[x.id] = x.current_price; });
    });
})();
