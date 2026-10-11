/* Indicadores de análisis técnico (sin dependencias). Los usan la ficha de cada
   moneda (pestaña Técnico), el Escáner técnico y el radar de la página de inicio.
   Todas las funciones reciben una serie de precios de cierre (números, del más antiguo al más reciente). */
(function () {
    'use strict';

    /** Media móvil simple. Devuelve un arreglo del mismo largo (null mientras no hay datos suficientes). */
    function sma(v, n) {
        const r = new Array(v.length).fill(null);
        let suma = 0;
        v.forEach((x, i) => {
            suma += x;
            if (i >= n) suma -= v[i - n];
            if (i >= n - 1) r[i] = suma / n;
        });
        return r;
    }

    /** Media móvil exponencial (arranca con la media simple de los primeros n). */
    function ema(v, n) {
        const r = new Array(v.length).fill(null);
        if (v.length < n) return r;
        const k = 2 / (n + 1);
        let previo = v.slice(0, n).reduce((a, b) => a + b, 0) / n;
        r[n - 1] = previo;
        for (let i = n; i < v.length; i++) r[i] = previo = v[i] * k + previo * (1 - k);
        return r;
    }

    /** RSI de Wilder (0-100). >70 sobrecompra, <30 sobreventa. */
    function rsi(v, n = 14) {
        const r = new Array(v.length).fill(null);
        if (v.length <= n) return r;
        let ganancia = 0, perdida = 0;
        for (let i = 1; i <= n; i++) {
            const d = v[i] - v[i - 1];
            if (d >= 0) ganancia += d; else perdida -= d;
        }
        ganancia /= n; perdida /= n;
        const valor = () => (perdida === 0 ? 100 : 100 - 100 / (1 + ganancia / perdida));
        r[n] = valor();
        for (let i = n + 1; i < v.length; i++) {
            const d = v[i] - v[i - 1];
            ganancia = (ganancia * (n - 1) + Math.max(d, 0)) / n;
            perdida = (perdida * (n - 1) + Math.max(-d, 0)) / n;
            r[i] = valor();
        }
        return r;
    }

    /** MACD (12, 26, 9): línea, señal e histograma. */
    function macd(v, rapida = 12, lenta = 26, senal = 9) {
        const er = ema(v, rapida), el = ema(v, lenta);
        const linea = v.map((_, i) => (er[i] === null || el[i] === null ? null : er[i] - el[i]));
        const inicio = linea.findIndex(x => x !== null);
        const sen = new Array(v.length).fill(null);
        if (inicio >= 0) ema(linea.slice(inicio), senal).forEach((x, j) => { sen[inicio + j] = x; });
        const hist = linea.map((x, i) => (x === null || sen[i] === null ? null : x - sen[i]));
        return { linea, senal: sen, hist };
    }

    /** Bandas de Bollinger (n, k desviaciones). */
    function bollinger(v, n = 20, k = 2) {
        const media = sma(v, n);
        const sup = new Array(v.length).fill(null), inf = new Array(v.length).fill(null);
        for (let i = n - 1; i < v.length; i++) {
            const ventana = v.slice(i - n + 1, i + 1);
            const de = Math.sqrt(ventana.reduce((s, x) => s + (x - media[i]) ** 2, 0) / n);
            sup[i] = media[i] + k * de;
            inf[i] = media[i] - k * de;
        }
        return { media, sup, inf };
    }

    /** Volatilidad: desviación estándar de los cambios porcentuales (en %). */
    function volatilidad(v) {
        if (v.length < 3) return null;
        const cambios = v.slice(1).map((x, i) => (x / v[i] - 1) * 100);
        const m = cambios.reduce((a, b) => a + b, 0) / cambios.length;
        return Math.sqrt(cambios.reduce((s, x) => s + (x - m) ** 2, 0) / cambios.length);
    }

    /** Soportes y resistencias: mínimos y máximos locales agrupados por cercanía. */
    function nivelesClave(v, ventana = 5, maximo = 3) {
        const actual = v[v.length - 1];
        const pivotes = [];
        for (let i = ventana; i < v.length - ventana; i++) {
            const tramo = v.slice(i - ventana, i + ventana + 1);
            if (v[i] === Math.max(...tramo)) pivotes.push(v[i]);
            if (v[i] === Math.min(...tramo)) pivotes.push(v[i]);
        }
        // Agrupa pivotes a menos de 1,5 % entre sí: un nivel tocado varias veces es más fuerte.
        const grupos = [];
        pivotes.sort((a, b) => a - b).forEach(p => {
            const g = grupos.find(x => Math.abs(p / x.precio - 1) < 0.015);
            if (g) { g.precio = (g.precio * g.toques + p) / (g.toques + 1); g.toques++; } else grupos.push({ precio: p, toques: 1 });
        });
        const ordenar = (a, b) => b.toques - a.toques || Math.abs(a.precio - actual) - Math.abs(b.precio - actual);
        return {
            soportes: grupos.filter(g => g.precio < actual).sort(ordenar).slice(0, maximo).sort((a, b) => b.precio - a.precio),
            resistencias: grupos.filter(g => g.precio > actual).sort(ordenar).slice(0, maximo).sort((a, b) => a.precio - b.precio),
        };
    }

    /** Correlación de Pearson entre dos series de rendimientos. */
    function correlacion(a, b) {
        const n = Math.min(a.length, b.length);
        if (n < 3) return null;
        const x = a.slice(-n), y = b.slice(-n);
        const mx = x.reduce((s, v) => s + v, 0) / n, my = y.reduce((s, v) => s + v, 0) / n;
        let num = 0, dx = 0, dy = 0;
        for (let i = 0; i < n; i++) { num += (x[i] - mx) * (y[i] - my); dx += (x[i] - mx) ** 2; dy += (y[i] - my) ** 2; }
        return dx && dy ? num / Math.sqrt(dx * dy) : 0;
    }

    const rendimientos = v => v.slice(1).map((x, i) => x / v[i] - 1);
    const ultimo = arr => { for (let i = arr.length - 1; i >= 0; i--) if (arr[i] !== null) return arr[i]; return null; };
    const penultimo = arr => { let visto = false; for (let i = arr.length - 1; i >= 0; i--) if (arr[i] !== null) { if (visto) return arr[i]; visto = true; } return null; };

    /**
     * Resumen técnico de una serie: valores actuales y un voto por indicador
     * (+1 compra, 0 neutral, -1 venta). `corta`/`larga` son los periodos de las medias.
     */
    function resumen(v, { corta = 20, larga = 50 } = {}) {
        if (!v || v.length < 20) return null;
        const precio = v[v.length - 1];
        const r = ultimo(rsi(v));
        const m = macd(v);
        const bb = bollinger(v);
        const mc = ultimo(sma(v, corta)), ml = v.length >= larga ? ultimo(sma(v, larga)) : null;
        const mcPrev = penultimo(sma(v, corta)), mlPrev = v.length >= larga + 1 ? penultimo(sma(v, larga)) : null;
        const histMacd = ultimo(m.hist), histPrev = penultimo(m.hist);
        const sup = ultimo(bb.sup), inf = ultimo(bb.inf);
        const pctB = sup !== null && sup !== inf ? (precio - inf) / (sup - inf) : null;

        const votos = [];
        if (r !== null) votos.push({ id: 'rsi', valor: r, voto: r < 30 ? 1 : r > 70 ? -1 : 0 });
        if (histMacd !== null) votos.push({ id: 'macd', valor: histMacd, voto: histMacd > 0 ? 1 : -1 });
        if (mc !== null) votos.push({ id: 'sma_corta', valor: mc, voto: precio > mc ? 1 : -1 });
        if (ml !== null) votos.push({ id: 'sma_larga', valor: ml, voto: precio > ml ? 1 : -1 });
        if (mc !== null && ml !== null) votos.push({ id: 'cruce', valor: mc - ml, voto: mc > ml ? 1 : -1 });
        if (pctB !== null) votos.push({ id: 'bollinger', valor: pctB, voto: pctB < 0.05 ? 1 : pctB > 0.95 ? -1 : 0 });

        const suma = votos.reduce((s, x) => s + x.voto, 0);
        const puntaje = votos.length ? suma / votos.length : 0; // -1 … +1
        const veredicto = puntaje >= 0.5 ? 'compra_fuerte' : puntaje >= 0.15 ? 'compra' : puntaje <= -0.5 ? 'venta_fuerte' : puntaje <= -0.15 ? 'venta' : 'neutral';

        // Eventos recientes que suelen mirar los traders.
        const eventos = [];
        if (mcPrev !== null && mlPrev !== null && mc !== null && ml !== null) {
            if (mcPrev <= mlPrev && mc > ml) eventos.push('cruce_dorado');
            if (mcPrev >= mlPrev && mc < ml) eventos.push('cruce_muerte');
        }
        if (histPrev !== null && histMacd !== null) {
            if (histPrev <= 0 && histMacd > 0) eventos.push('macd_alcista');
            if (histPrev >= 0 && histMacd < 0) eventos.push('macd_bajista');
        }
        if (r !== null && r < 30) eventos.push('sobreventa');
        if (r !== null && r > 70) eventos.push('sobrecompra');
        if (pctB !== null && pctB > 1) eventos.push('ruptura_alcista');
        if (pctB !== null && pctB < 0) eventos.push('ruptura_bajista');

        return { precio, rsi: r, macd: histMacd, smaCorta: mc, smaLarga: ml, pctB, volatilidad: volatilidad(v), votos, puntaje, veredicto, eventos };
    }

    window.Tecnico = { sma, ema, rsi, macd, bollinger, volatilidad, nivelesClave, correlacion, rendimientos, resumen };
})();
