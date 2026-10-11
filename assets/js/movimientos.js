/* Mejores 25 y peores 25 del top 100, lado a lado, en el periodo elegido. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    const CAMPOS = {
        '1h': 'price_change_percentage_1h_in_currency',
        '24h': 'price_change_percentage_24h',
        '7d': 'price_change_percentage_7d_in_currency',
        '30d': 'price_change_percentage_30d_in_currency',
    };
    const preciosAnteriores = {};
    let mercado = null;
    let periodo = '24h';

    function filas(lista, campo, maximo, clase) {
        return lista.map((m, i) => {
            const v = m[campo];
            const ancho = maximo ? Math.min(100, Math.abs(v) / maximo * 100) : 0;
            return `<tr data-id="${m.id}">
                <td><span class="num-rank">${i + 1}</span></td>
                <td>${C.celdaMoneda(m, 24)}</td>
                <td class="num precio"><strong>${C.precio(m.current_price)}</strong></td>
                <td class="num">${C.porcentaje(v)}<span class="barra-mov" style="width:${ancho}%;margin-left:auto;background:var(--${clase})"></span></td>
                <td>${C.insignia(m.analisis)}</td>
            </tr>`;
        }).join('');
    }

    function pintar() {
        if (!mercado) return;
        const campo = CAMPOS[periodo];
        const conDato = mercado.monedas.filter(m => m[campo] !== null && m[campo] !== undefined);
        const mejores = [...conDato].sort((a, b) => b[campo] - a[campo]).slice(0, 25);
        const peores = [...conDato].sort((a, b) => a[campo] - b[campo]).slice(0, 25);
        const maximo = Math.max(...[...mejores, ...peores].map(m => Math.abs(m[campo])));
        const etiqueta = periodo.replace('h', ' h').replace('d', ' d');

        $('periodo').querySelectorAll('button').forEach(b => b.classList.toggle('activa', b.dataset.periodo === periodo));
        $('cuerpo-mejores').innerHTML = filas(mejores, campo, maximo, 'sube');
        $('cuerpo-peores').innerHTML = filas(peores, campo, maximo, 'baja');
        $('sub-mejores').textContent = C.t('js.mov_sub_ganadores', { periodo: etiqueta });
        $('sub-peores').textContent = C.t('js.mov_sub_perdedores', { periodo: etiqueta });
        const promedio = l => l.reduce((s, m) => s + m[campo], 0) / l.length;
        $('resumen-mejores').textContent = `${C.t('js.promedio')} ▲ ${C.numero(promedio(mejores))} %`;
        $('resumen-peores').textContent = `${C.t('js.promedio')} ▼ ${C.numero(Math.abs(promedio(peores)))} %`;

        document.querySelectorAll('tr[data-id] .precio').forEach(td => {
            const id = td.closest('tr').dataset.id;
            C.destellar(td, preciosAnteriores[id], mercado.porId[id].current_price);
        });
    }

    $('periodo').addEventListener('click', ev => {
        const b = ev.target.closest('button[data-periodo]');
        if (!b) return;
        periodo = b.dataset.periodo;
        pintar();
    });

    C.iniciarRefresco(m => {
        mercado = m;
        pintar();
        m.monedas.forEach(x => { preciosAnteriores[x.id] = x.current_price; });
    });
})();
