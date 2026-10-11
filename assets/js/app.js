/* Utilidades compartidas: traducciones, formato, mercado en vivo, cinta de precios,
   buscador, menús, preferencias (idioma, tema, visión de color), favoritas,
   alertas de precio, notificaciones, tooltip y medidores. */
(function () {
    'use strict';

    const $ = id => document.getElementById(id);
    const LOCALE = APP.locale || 'es-CO';

    // ---------- Traducciones ----------

    /** Traduce una clave "js.*" y reemplaza {parametros}. */
    function t(clave, params = {}) {
        let texto = (window.I18N && window.I18N[clave]) || clave;
        Object.entries(params).forEach(([k, v]) => { texto = texto.split(`{${k}}`).join(v); });
        return texto;
    }

    // ---------- Formato ----------

    // Moneda elegida en ⚙ Preferencias: los datos llegan en USD y aquí se convierten
    // (tasa = unidades de esa moneda por 1 USD, ver includes/header.php).
    const MONEDA = APP.moneda && APP.moneda.tasa > 0 ? APP.moneda : { codigo: 'USD', tasa: 1 };
    const aVista = v => v * MONEDA.tasa;
    const formatoMoneda = (opciones = {}) => new Intl.NumberFormat(LOCALE, { style: 'currency', currency: MONEDA.codigo, currencyDisplay: 'narrowSymbol', maximumFractionDigits: 2, ...opciones });
    const usd = formatoMoneda();
    const compacto = new Intl.NumberFormat(LOCALE, { notation: 'compact', maximumFractionDigits: 2 });
    const vacio = v => v === null || v === undefined || isNaN(v);

    function precio(v) {
        if (vacio(v)) return '—';
        v = aVista(v);
        if (Math.abs(v) >= 1) return usd.format(v);
        // Monedas baratas: mostrar decimales significativos.
        const dec = Math.min(10, Math.max(2, -Math.floor(Math.log10(Math.abs(v) || 1)) + 3));
        return formatoMoneda({ minimumFractionDigits: dec, maximumFractionDigits: dec }).format(v);
    }
    const dinero = v => vacio(v) ? '—' : usd.format(aVista(v));
    const grande = v => vacio(v) ? '—' : formatoMoneda({ notation: 'compact' }).format(aVista(v));
    /** Importe en dólares, sin convertir (para montos que el usuario escribe en USD). */
    const dolares = v => vacio(v) ? '—' : new Intl.NumberFormat(LOCALE, { style: 'currency', currency: 'USD', currencyDisplay: 'narrowSymbol', maximumFractionDigits: 2 }).format(v);
    const numeroCompacto = v => vacio(v) ? '—' : compacto.format(v);
    const cantidad = v => Number(v).toLocaleString(LOCALE, { maximumFractionDigits: v < 1 ? 8 : 4 });
    const numero = (v, dec = 2) => vacio(v) ? '—' : Number(v).toLocaleString(LOCALE, { minimumFractionDigits: dec, maximumFractionDigits: dec });

    // ---------- Zona horaria ----------
    // Automática: se detecta por la IP pública de la conexión (con una VPN, la del país de la VPN)
    // y se recuerda unas horas. Mientras no se conoce (o sin conexión) se usa la del dispositivo.

    const ZONA_DISPOSITIVO = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    const zonaValida = z => { try { new Intl.DateTimeFormat('en', { timeZone: z }); return true; } catch (e) { return false; } };
    const ZONA_RED = leer('zona_ip', null); // { zona, ciudad, pais, fecha }
    const ZONA = ZONA_RED && zonaValida(ZONA_RED.zona) ? ZONA_RED.zona : ZONA_DISPOSITIVO;
    try { localStorage.removeItem('zona_horaria'); } catch (e) { /* preferencia del antiguo selector */ }

    /** Consulta la zona de la IP pública; si cambió, recarga para redibujar horas y gráficos. */
    async function detectarZona() {
        if (ZONA_RED && zonaValida(ZONA_RED.zona) && Date.now() - ZONA_RED.fecha < 6 * 3600000) return;
        const fuentes = [
            ['https://ipwho.is/?fields=success,timezone,city,country', d => d.success && { zona: d.timezone && d.timezone.id, ciudad: d.city, pais: d.country }],
            ['https://get.geojs.io/v1/ip/geo.json', d => ({ zona: d.timezone, ciudad: d.city, pais: d.country })],
        ];
        for (const [url, extraer] of fuentes) {
            try {
                const r = await fetch(url, { cache: 'no-store', signal: AbortSignal.timeout(6000) });
                const z = r.ok && extraer(await r.json());
                if (!z || !zonaValida(z.zona)) continue;
                escribir('zona_ip', { ...z, fecha: Date.now() });
                // Solo se recarga sola al poco de abrir la página (no interrumpe a quien ya la está usando).
                if (z.zona !== ZONA && performance.now() < 8000) location.reload();
                return;
            } catch (e) { /* se prueba la siguiente fuente */ }
        }
    }

    const partesZona = new Intl.DateTimeFormat('en-US', {
        timeZone: ZONA, hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit',
    });
    /** Milisegundos que la zona elegida va por delante de UTC en el instante `ms`. */
    function desfaseZona(ms, formato = partesZona) {
        const p = Object.fromEntries(formato.formatToParts(new Date(ms)).map(x => [x.type, x.value]));
        return Date.UTC(p.year, p.month - 1, p.day, p.hour % 24, p.minute, p.second) - Math.floor(ms / 1000) * 1000;
    }
    /** "UTC−5", "UTC+5:30"… */
    function textoDesfase(ms) {
        const min = Math.round(ms / 60000), abs = Math.abs(min);
        return `UTC${min < 0 ? '−' : '+'}${Math.floor(abs / 60)}${abs % 60 ? ':' + String(abs % 60).padStart(2, '0') : ''}`;
    }

    const fecha = (ms, opciones = { dateStyle: 'medium' }) => new Date(ms).toLocaleString(LOCALE, { ...opciones, timeZone: ZONA });
    const hora = (ms, segundos = false) => new Date(ms).toLocaleTimeString(LOCALE, { hour: '2-digit', minute: '2-digit', ...(segundos ? { second: '2-digit' } : {}), timeZone: ZONA });
    /** Fecha AAAA-MM-DD en la zona elegida (toISOString daría la de UTC: el día siguiente desde las 7 p. m. en Colombia). */
    const fechaISO = (ms = Date.now()) => new Intl.DateTimeFormat('en-CA', { timeZone: ZONA, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(ms));

    // Los ejes de tiempo de Chart.js usan la hora del dispositivo: se adaptan a la zona elegida.
    (function zonaEnGraficos() {
        const adaptador = window.Chart && Chart._adapters && Chart._adapters._date;
        if (!adaptador || !adaptador.prototype.format) return;
        const orig = { format: adaptador.prototype.format, startOf: adaptador.prototype.startOf, endOf: adaptador.prototype.endOf };
        const ajuste = ms => desfaseZona(ms) + new Date(ms).getTimezoneOffset() * 60000;
        const aZona = ms => ms + ajuste(ms);
        const desdeZona = ms => ms - ajuste(ms);
        adaptador.override({
            format(t, f) { return orig.format.call(this, aZona(t), f); },
            startOf(t, u, w) { return desdeZona(orig.startOf.call(this, aZona(t), u, w)); },
            endOf(t, u) { return desdeZona(orig.endOf.call(this, aZona(t), u)); },
        });
    })();

    // Con un modo de visión de color activo, cada línea de un gráfico lleva su propio trazo
    // (continuo, rayas, puntos, raya-punto): se distinguen aunque no se vean los colores.
    (function patronesVision() {
        if (!window.Chart || !Chart.register) return;
        const PATRONES = [[], [8, 4], [2, 3], [12, 4, 2, 4]];
        Chart.register({
            id: 'patronesVision',
            beforeUpdate(chart) {
                if (!document.documentElement.dataset.vision) return;
                chart.data.datasets.forEach((d, i) => {
                    if ((d.type || chart.config.type) === 'line' && i > 0 && d.borderDash === undefined) d.borderDash = PATRONES[i % PATRONES.length];
                });
            },
        });
    })();

    function porcentaje(v, dec = 2) {
        if (vacio(v)) return '<span class="cambio texto-3">—</span>';
        const clase = v > 0 ? 'sube' : v < 0 ? 'baja' : '';
        const flecha = v > 0 ? '▲' : v < 0 ? '▼' : '';
        return `<span class="cambio ${clase}">${flecha} ${numero(Math.abs(v), dec)} %</span>`;
    }

    function pildora(v, etiqueta = '') {
        const pre = etiqueta ? `<small>${etiqueta}</small>` : '';
        if (vacio(v)) return `<span class="pildora-cambio">${pre}—</span>`;
        return `<span class="pildora-cambio ${v >= 0 ? 'sube' : 'baja'}">${pre}${v >= 0 ? '▲' : '▼'} ${numero(Math.abs(v))} %</span>`;
    }

    function escapar(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    const colorVeredicto = v => ({ viable: 'var(--sube)', neutral: 'var(--neutral)', no_viable: 'var(--baja)' }[v] || 'var(--texto-3)');

    function insignia(a, conPuntaje = false) {
        const iconos = { viable: '✔', neutral: '●', no_viable: '✖' };
        return `<span class="insignia ${a.veredicto}" title="${escapar(t('js.puntaje_de', { n: a.puntaje }))}">${iconos[a.veredicto] || ''} ${escapar(a.etiqueta)}${conPuntaje ? ` · ${a.puntaje}` : ''}</span>`;
    }

    function puntajeMini(a) {
        return `<span class="puntaje-mini" title="${escapar(t('js.puntaje'))}"><span class="pista"><span style="width:${a.puntaje}%;background:${colorVeredicto(a.veredicto)}"></span></span>${a.puntaje}</span>`;
    }

    const urlMoneda = id => `${APP.base}/paginas/moneda.php?id=${encodeURIComponent(id)}`;

    function celdaMoneda(m, tam = 24) {
        return `<a class="moneda" href="${escapar(m.url || urlMoneda(m.id))}">
            <img src="${escapar(m.image)}" alt="" width="${tam}" height="${tam}" loading="lazy">
            <span class="nombre" title="${escapar(m.name)}">${escapar(m.name)}</span> <small>${escapar(m.symbol.toUpperCase())}</small></a>`;
    }

    /** Miniatura SVG (línea + área suave). */
    function sparkline(valores, w = 120, h = 36) {
        if (!valores || valores.length < 2) return '';
        const min = Math.min(...valores), max = Math.max(...valores);
        const rango = max - min || 1;
        const xy = valores.map((v, i) => [i / (valores.length - 1) * w, h - 3 - (v - min) / rango * (h - 6)]);
        const pts = xy.map(p => p.map(n => n.toFixed(1)).join(',')).join(' ');
        const area = `M0,${h} L${pts.replace(/ /g, ' L')} L${w},${h} Z`;
        const clase = valores[valores.length - 1] >= valores[0] ? 'sube' : 'baja';
        return `<svg class="spark ${clase}" viewBox="0 0 ${w} ${h}" width="${w}" height="${h}" preserveAspectRatio="none" aria-hidden="true">
            <path d="${area}"/><polyline points="${pts}"/></svg>`;
    }

    /** Lee una variable CSS del tema actual (para Chart.js). */
    const css = nombre => getComputedStyle(document.documentElement).getPropertyValue(nombre).trim();

    /** Opciones de tooltip de Chart.js acordes al tema. */
    const tooltipChart = () => ({
        backgroundColor: css('--panel-solido'), titleColor: css('--texto-2'), bodyColor: css('--texto'),
        borderColor: css('--borde-fuerte'), borderWidth: 1, padding: 10, boxPadding: 4,
    });

    // Formatos de fecha numéricos (no dependen del idioma de Chart.js).
    const FORMATOS_FECHA = { minute: 'HH:mm', hour: 'HH:mm', day: 'dd/MM', week: 'dd/MM', month: 'MM/yy', year: 'yyyy' };

    // ---------- Medidor semicircular ----------

    function medidor(valor, { max = 100, ancho = 170, etiqueta = '' } = {}) {
        const r = ancho / 2 - 12, cx = ancho / 2, cy = ancho / 2, grosor = 12;
        const punto = f => [cx + r * Math.cos(Math.PI - f * Math.PI), cy - r * Math.sin(Math.PI - f * Math.PI)];
        const arco = (f0, f1) => {
            const [x0, y0] = punto(f0), [x1, y1] = punto(f1);
            return `M${x0.toFixed(2)},${y0.toFixed(2)} A${r},${r} 0 0 1 ${x1.toFixed(2)},${y1.toFixed(2)}`;
        };
        const colores = ['var(--baja)', 'color-mix(in srgb, var(--baja) 50%, var(--neutral))', 'var(--neutral)',
            'color-mix(in srgb, var(--neutral) 50%, var(--sube))', 'var(--sube)'];
        const hueco = 0.012;
        const segmentos = colores.map((c, i) =>
            `<path d="${arco(i / 5 + (i ? hueco : 0), (i + 1) / 5 - (i < 4 ? hueco : 0))}" style="stroke:${c}" stroke-width="${grosor}" fill="none"/>`).join('');
        const f = Math.max(0, Math.min(1, valor / max));
        const [mx, my] = punto(f);
        const alto = cy + 8;
        return `<svg width="${ancho}" height="${alto}" viewBox="0 0 ${ancho} ${alto}" role="img" aria-label="${escapar(etiqueta)}: ${valor} / ${max}">
            ${segmentos}
            <circle cx="${mx.toFixed(2)}" cy="${my.toFixed(2)}" r="9" style="fill:var(--panel-solido);stroke:var(--texto)" stroke-width="3"/>
            <text x="${cx}" y="${cy - 8}" text-anchor="middle" class="medidor-numero" font-size="${ancho / 4.6}">${valor}</text>
            <text x="${cx - r}" y="${cy + 8}" text-anchor="middle" class="medidor-escala">0</text>
            <text x="${cx + r}" y="${cy + 8}" text-anchor="middle" class="medidor-escala">${max}</text>
        </svg>`;
    }

    // ---------- Notificaciones ----------

    function toast(texto, tipo = '') {
        const caja = $('toasts');
        if (!caja) return;
        const el = document.createElement('div');
        el.className = `toast ${tipo}`;
        el.textContent = texto;
        caja.appendChild(el);
        setTimeout(() => { el.classList.add('saliendo'); setTimeout(() => el.remove(), 350); }, 4500);
    }

    // ---------- Tooltip ----------

    function mostrarTooltip(html, ev) {
        const tt = $('tooltip');
        tt.innerHTML = html;
        tt.hidden = false;
        const r = tt.getBoundingClientRect();
        let x = ev.clientX + 14, y = ev.clientY + 14;
        if (x + r.width > innerWidth - 8) x = ev.clientX - r.width - 14;
        if (y + r.height > innerHeight - 8) y = ev.clientY - r.height - 14;
        tt.style.left = Math.max(8, x) + 'px';
        tt.style.top = Math.max(8, y) + 'px';
    }
    const ocultarTooltip = () => { $('tooltip').hidden = true; };

    // ---------- Almacenamiento seguro ----------

    function leer(clave, porDefecto) {
        try { const v = JSON.parse(localStorage.getItem(clave)); return v ?? porDefecto; } catch (e) { return porDefecto; }
    }
    function escribir(clave, valor) {
        try { localStorage.setItem(clave, JSON.stringify(valor)); } catch (e) { /* sin almacenamiento */ }
        if (APP.usuario && APP.clavesProgreso.includes(clave)) programarSincronizacion(clave);
    }

    // ---------- Cuenta: peticiones y progreso en el servidor ----------

    /** POST JSON a api/<ruta> con el token CSRF. Lanza Error con el mensaje del servidor. */
    async function api(ruta, datos = {}) {
        const r = await fetch(`${APP.base}/api/${ruta}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF': APP.csrf },
            body: JSON.stringify(datos),
            credentials: 'same-origin',
        });
        let cuerpo = {};
        try { cuerpo = await r.json(); } catch (e) { /* respuesta vacía */ }
        if (!r.ok || cuerpo.ok === false) throw new Error(cuerpo.error || t('js.error_red'));
        return cuerpo;
    }

    // Cada cambio en el progreso se sube a la cuenta (agrupado cada 1,5 s).
    const pendientes = new Set(APP.progresoPendiente || []);
    let temporizadorSync = null;

    function paqueteProgreso() {
        const datos = {};
        pendientes.forEach(k => {
            try { const v = localStorage.getItem(k); if (v !== null) datos[k] = JSON.parse(v); } catch (e) { /* valor dañado */ }
        });
        pendientes.clear();
        return datos;
    }

    function programarSincronizacion(clave) {
        pendientes.add(clave);
        clearTimeout(temporizadorSync);
        temporizadorSync = setTimeout(sincronizarAhora, 1500);
    }

    async function sincronizarAhora() {
        clearTimeout(temporizadorSync);
        if (!APP.usuario || !pendientes.size) return;
        const datos = paqueteProgreso();
        try { await api('progreso.php', datos); } catch (e) { Object.keys(datos).forEach(k => pendientes.add(k)); }
    }

    if (APP.usuario) {
        if (pendientes.size) temporizadorSync = setTimeout(sincronizarAhora, 1500);
        // Al salir de la página se envía lo que falte aunque la pestaña se cierre.
        addEventListener('pagehide', () => {
            if (!pendientes.size) return;
            const blob = new Blob([JSON.stringify(paqueteProgreso())], { type: 'application/json' });
            navigator.sendBeacon(`${APP.base}/api/progreso.php?csrf=${encodeURIComponent(APP.csrf)}`, blob);
        });
    }

    async function cerrarSesion() {
        await sincronizarAhora();
        try { await api('auth.php?accion=logout'); } catch (e) { /* se limpia igual */ }
        try {
            APP.clavesProgreso.forEach(k => localStorage.removeItem(k));
            localStorage.removeItem('progreso_dueno');
        } catch (e) { /* */ }
        location.href = `${APP.base}/index.php`;
    }
    document.getElementById('boton-salir')?.addEventListener('click', cerrarSesion);

    // ---------- App instalable, atajo de búsqueda y botón "volver arriba" ----------

    if ('serviceWorker' in navigator && location.protocol !== 'file:') {
        addEventListener('load', () => navigator.serviceWorker.register(`${APP.base}/sw.js`, { scope: `${APP.base}/` }).catch(() => {}));
    }
    // Pulsa "/" en cualquier parte para buscar una moneda (como en GitHub o YouTube).
    document.addEventListener('keydown', ev => {
        if (ev.key !== '/' || ev.ctrlKey || ev.metaKey || ev.altKey || ev.target.closest('input, textarea, select, [contenteditable]')) return;
        const buscador = document.getElementById('buscador-global');
        if (buscador) { ev.preventDefault(); buscador.focus(); buscador.select(); }
    });
    (function volverArriba() {
        const b = document.getElementById('volver-arriba');
        if (!b) return;
        addEventListener('scroll', () => { b.hidden = scrollY < 700; }, { passive: true });
        b.addEventListener('click', () => { scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }); document.getElementById('contenido')?.focus({ preventScroll: true }); });
    })();

    // Menú de usuario: se cierra al hacer clic fuera.
    document.addEventListener('click', ev => {
        const m = document.querySelector('.menu-usuario');
        if (m && m.open && !ev.target.closest('.menu-usuario')) m.open = false;
    });

    // ---------- Favoritas ----------

    let favoritos = new Set(leer('favoritos', []));
    const esFavorito = id => favoritos.has(id);
    const listaFavoritos = () => [...favoritos];
    function alternarFavorito(id) {
        favoritos.has(id) ? favoritos.delete(id) : favoritos.add(id);
        escribir('favoritos', [...favoritos]);
        document.querySelectorAll(`.estrella[data-fav="${CSS.escape(id)}"]`).forEach(b => {
            b.classList.toggle('activa', favoritos.has(id));
            b.setAttribute('aria-pressed', favoritos.has(id));
        });
        document.dispatchEvent(new CustomEvent('favoritos'));
        return favoritos.has(id);
    }
    function botonFavorito(id) {
        const activo = favoritos.has(id);
        return `<button class="estrella ${activo ? 'activa' : ''}" data-fav="${escapar(id)}" aria-pressed="${activo}" title="${escapar(t('js.fav_agregar'))}" aria-label="${escapar(t('js.fav_agregar'))}">★</button>`;
    }
    document.addEventListener('click', ev => {
        const b = ev.target.closest('.estrella[data-fav]');
        if (!b) return;
        ev.preventDefault();
        if (!APP.usuario) { toast('🔒 ' + t('js.cuenta_favoritas'), 'alerta'); return; }
        toast(alternarFavorito(b.dataset.fav) ? t('js.fav_agregada') : t('js.fav_quitada'));
    });
    // Sincroniza estrellas renderizadas en PHP.
    document.querySelectorAll('.estrella[data-fav]').forEach(b => {
        b.classList.toggle('activa', favoritos.has(b.dataset.fav));
        b.setAttribute('aria-pressed', favoritos.has(b.dataset.fav));
    });

    // ---------- Alertas de precio ----------

    const leerAlertas = () => leer('alertas', []);
    const guardarAlertas = lista => escribir('alertas', lista);

    function revisarAlertas(mercado) {
        const lista = leerAlertas();
        let cambio = false;
        lista.forEach(a => {
            if (a.disparada) return;
            const m = mercado.porId[a.moneda];
            if (!m) return;
            const cumple = a.condicion === 'mayor' ? m.current_price >= a.precio : m.current_price <= a.precio;
            if (!cumple) return;
            a.disparada = Date.now();
            a.precioDisparo = m.current_price;
            cambio = true;
            const texto = t(a.condicion === 'mayor' ? 'js.alerta_subio' : 'js.alerta_bajo', {
                simbolo: m.symbol.toUpperCase(), precio: precio(a.precio), actual: precio(m.current_price),
            });
            toast('🔔 ' + texto, 'exito');
            try {
                if ('Notification' in window && Notification.permission === 'granted') new Notification('CriptoAnálisis', { body: texto, icon: m.image });
            } catch (e) { /* notificaciones no disponibles */ }
        });
        if (cambio) {
            guardarAlertas(lista);
            document.dispatchEvent(new CustomEvent('alertas'));
        }
    }

    // ---------- Preferencias: idioma, tema, visión de color, contraste y tamaño de letra ----------

    const raiz = document.documentElement;
    const temaActual = () => raiz.dataset.theme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

    // Valores antiguos del selector de visión (versiones anteriores de la página).
    const VISION_ANTIGUA = { rv: 'deuteranopia', tri: 'tritanopia', mono: 'acromatopsia', protanomalia: 'protanopia', deuteranomalia: 'deuteranopia', tritanomalia: 'tritanopia', conos_azules: 'acromatopsia' };
    if (VISION_ANTIGUA[raiz.dataset.vision]) {
        raiz.dataset.vision = VISION_ANTIGUA[raiz.dataset.vision];
        try { localStorage.setItem('vision', raiz.dataset.vision); } catch (e) { /* */ }
    }

    /** Guarda una preferencia visual como atributo data-* de <html> y en localStorage. */
    function preferencia(dato, clave, valor) {
        if (valor) raiz.dataset[dato] = valor; else delete raiz.dataset[dato];
        try { valor ? localStorage.setItem(clave, valor) : localStorage.removeItem(clave); } catch (e) { /* */ }
    }

    function marcarPrefs() {
        [['pref-tema', 'theme'], ['pref-contraste', 'contraste'], ['pref-texto', 'texto']].forEach(([id, dato]) => {
            document.querySelectorAll(`#${id} button`).forEach(b => b.classList.toggle('activa', b.dataset.valor === (raiz.dataset[dato] || '')));
        });
        const sel = $('pref-vision');
        if (sel) {
            sel.value = raiz.dataset.vision || '';
            const ayuda = $('vision-ayuda');
            if (ayuda) ayuda.textContent = sel.value ? t('js.vision_' + sel.value) : '';
        }
    }

    function avisarCambioVisual() {
        // Las gráficas leen los colores del tema: se vuelven a dibujar.
        document.dispatchEvent(new CustomEvent('tema'));
    }

    /** Traducción automática: Google Translate lee la cookie "googtrans" (/auto/<idioma>). */
    function traducirA(codigo) {
        const vencida = 'expires=Thu, 01 Jan 1970 00:00:00 GMT';
        // Google también la guarda con dominio: se borran todas las variantes.
        [`; path=/`, `; path=/; domain=${location.hostname}`, `; path=/; domain=.${location.hostname}`].forEach(sufijo => {
            document.cookie = `googtrans=; ${vencida}${sufijo}`;
        });
        if (codigo) document.cookie = `googtrans=/auto/${codigo}; path=/; max-age=31536000; SameSite=Lax`;
        location.reload();
    }

    (function preferencias() {
        const boton = $('boton-prefs'), panel = $('panel-prefs');
        if (!boton) return;
        const cerrar = () => { panel.hidden = true; boton.setAttribute('aria-expanded', 'false'); };
        boton.addEventListener('click', () => {
            panel.hidden = !panel.hidden;
            boton.setAttribute('aria-expanded', !panel.hidden);
        });
        $('cerrar-prefs')?.addEventListener('click', cerrar);
        document.addEventListener('click', ev => { if (!ev.target.closest('.preferencias')) cerrar(); });
        document.addEventListener('keydown', ev => { if (ev.key === 'Escape' && !panel.hidden) { cerrar(); boton.focus(); } });

        $('pref-idioma').addEventListener('click', ev => {
            const b = ev.target.closest('button[data-valor]');
            if (!b || (b.dataset.valor === APP.idioma && !APP.idiomaAuto)) return;
            document.cookie = `idioma=${b.dataset.valor}; path=/; max-age=31536000; SameSite=Lax`;
            if (APP.idiomaAuto) return traducirA(null);
            const url = new URL(location.href);
            url.searchParams.delete('lang');
            location.href = url.toString();
        });
        $('lista-idiomas')?.addEventListener('click', ev => {
            const b = ev.target.closest('button[data-auto]');
            if (b && b.dataset.auto !== APP.idiomaAuto) traducirA(b.dataset.auto);
        });
        $('quitar-traduccion')?.addEventListener('click', () => traducirA(null));
        $('buscar-idioma')?.addEventListener('input', ev => {
            const q = ev.target.value.trim().toLowerCase();
            document.querySelectorAll('#lista-idiomas button').forEach(b => { b.hidden = q && !b.dataset.buscar.includes(q); });
        });

        [['pref-tema', 'theme', 'tema'], ['pref-contraste', 'contraste', 'contraste'], ['pref-texto', 'texto', 'texto']].forEach(([id, dato, clave]) => {
            $(id)?.addEventListener('click', ev => {
                const b = ev.target.closest('button[data-valor]');
                if (!b) return;
                preferencia(dato, clave, b.dataset.valor);
                marcarPrefs();
                avisarCambioVisual();
            });
        });
        // Moneda para ver precios: se guarda en una cookie (el servidor trae su tasa) y se recarga.
        $('pref-moneda')?.addEventListener('change', ev => {
            document.cookie = `moneda=${ev.target.value}; path=/; max-age=31536000; samesite=lax`;
            location.reload();
        });
        $('pref-moneda')?.querySelectorAll('option').forEach(o => {
            try { o.textContent = `${o.value} · ${new Intl.DisplayNames([LOCALE], { type: 'currency' }).of(o.value)}`; } catch (e) { /* navegador antiguo */ }
        });

        $('pref-vision').addEventListener('change', ev => {
            preferencia('vision', 'vision', ev.target.value);
            marcarPrefs();
            avisarCambioVisual();
        });
        matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if (!raiz.dataset.theme) avisarCambioVisual(); });
        marcarPrefs();

        // Zona horaria (solo informativa): la detectada por la conexión, su desfase y la hora de allí.
        const zonaActual = $('zona-actual');
        if (zonaActual) {
            const lugar = ZONA_RED && ZONA_RED.zona === ZONA ? [ZONA_RED.ciudad, ZONA_RED.pais].filter(Boolean).join(', ') : '';
            zonaActual.textContent = `${ZONA.replace(/_/g, ' ')} · ${textoDesfase(desfaseZona(Date.now()))}`;
            const pintarAyuda = () => {
                $('zona-ayuda').textContent = `${lugar ? t('js.zona_red', { lugar }) : t('js.zona_dispositivo')} ${t('js.zona_ayuda', { hora: hora(Date.now(), true), zona: ZONA })}`;
            };
            pintarAyuda();
            setInterval(() => { if (!panel.hidden) pintarAyuda(); }, 1000);
        }
    })();

    // ---------- Menú (desplegables y versión móvil) ----------

    (function menu() {
        const menus = [...document.querySelectorAll('.menu')];
        menus.forEach(d => d.addEventListener('toggle', () => {
            if (d.open) menus.forEach(o => { if (o !== d) o.open = false; });
        }));
        document.addEventListener('click', ev => {
            if (!ev.target.closest('.menu')) menus.forEach(d => { d.open = false; });
        });
        document.addEventListener('keydown', ev => {
            if (ev.key === 'Escape') menus.forEach(d => { d.open = false; });
        });
        const boton = $('boton-menu'), nav = $('nav');
        boton?.addEventListener('click', () => {
            nav.classList.toggle('abierta');
            boton.setAttribute('aria-expanded', nav.classList.contains('abierta'));
        });
    })();

    // ---------- Mercado en vivo ----------

    let ultimoMercado = null;

    async function obtenerMercado() {
        const r = await fetch(`${APP.base}/api/mercado.php`, { cache: 'no-store' });
        const datos = await r.json();
        if (!r.ok) throw new Error(datos.error || 'API');
        datos.porId = Object.fromEntries(datos.monedas.map(m => [m.id, m]));
        ultimoMercado = datos;
        pintarCinta(datos);
        revisarAlertas(datos);
        document.dispatchEvent(new CustomEvent('mercado', { detail: datos })); // otros módulos (p. ej. el radar técnico) reutilizan estos datos
        return datos;
    }

    function pintarCinta(mercado) {
        const cinta = $('cinta');
        if (!cinta) return;
        const items = mercado.monedas.slice(0, 30).map(m => `<a class="cinta-item" href="${urlMoneda(m.id)}">
            <img src="${escapar(m.image)}" alt="" loading="lazy"><span>${escapar(m.symbol.toUpperCase())}</span>
            <strong>${precio(m.current_price)}</strong>${porcentaje(m.price_change_percentage_24h)}</a>`).join('');
        cinta.innerHTML = items + items; // duplicado para el desplazamiento infinito
    }

    /** Llama a `alActualizar(mercado)` ahora y luego cada minuto, con cuenta regresiva. */
    let refrescoIniciado = false;
    function iniciarRefresco(alActualizar) {
        refrescoIniciado = true;
        const texto = $('texto-estado');
        const caja = $('estado-actualizacion');
        let siguiente = 0, temporizador = null, ultima = null, ultimoCiclo = 0;

        async function ciclo() {
            ultimoCiclo = Date.now();
            try {
                const mercado = await obtenerMercado();
                ultima = mercado.actualizado * 1000;
                caja?.classList.remove('error');
                alActualizar(mercado);
            } catch (e) {
                caja?.classList.add('error');
                if (texto) texto.textContent = t('js.sin_conexion');
                console.error(e);
            }
        }

        // Se actualiza al empezar cada minuto del reloj (hh:mm:02), no "un minuto después de abrir la página".
        function programar() {
            clearTimeout(temporizador);
            const espera = APP.refrescoMs - (Date.now() % APP.refrescoMs) + 2000;
            siguiente = Date.now() + espera;
            temporizador = setTimeout(async () => { await ciclo(); programar(); }, espera);
        }

        setInterval(() => {
            if (!ultima || !texto || caja.classList.contains('error')) return;
            const seg = Math.max(0, Math.round((siguiente - Date.now()) / 1000));
            texto.innerHTML = `<span class="texto-largo">${escapar(t('js.en_vivo'))} · ${hora(Date.now(), true)} <span title="${escapar(ZONA)}">${textoDesfase(desfaseZona(Date.now()))}</span> · </span>${seg}s`;
        }, 1000);

        // Si la pestaña estuvo en segundo plano (el navegador frena los temporizadores), se pone al día al volver.
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && Date.now() - ultimoCiclo > APP.refrescoMs) { ciclo(); programar(); }
        });

        ciclo();
        programar();
    }

    /** Resalta brevemente un elemento si su valor cambió. */
    function destellar(el, anterior, actual) {
        if (anterior === undefined || anterior === actual) return;
        el.classList.remove('destello-sube', 'destello-baja');
        void el.offsetWidth;
        el.classList.add(actual > anterior ? 'destello-sube' : 'destello-baja');
    }

    // ---------- Buscador global ----------

    (function buscador() {
        const input = $('buscador-global');
        const caja = $('sugerencias');
        if (!input) return;
        let enfocada = -1;

        async function sugerir() {
            const q = input.value.trim().toLowerCase();
            if (!q) { caja.hidden = true; return; }
            if (!ultimoMercado) {
                try { await obtenerMercado(); } catch (e) { return; }
            }
            const lista = ultimoMercado.monedas
                .filter(m => m.name.toLowerCase().includes(q) || m.symbol.toLowerCase().includes(q))
                .slice(0, 8);
            enfocada = -1;
            caja.innerHTML = lista.length ? lista.map(m => `<a href="${urlMoneda(m.id)}">
                <img src="${escapar(m.image)}" alt=""><span><strong>${escapar(m.name)}</strong> <small class="texto-3">${escapar(m.symbol.toUpperCase())}</small></span>
                <span class="derecha">${precio(m.current_price)}<br>${porcentaje(m.price_change_percentage_24h)}</span></a>`).join('')
                : `<div class="vacio">${escapar(t('js.no_top100'))}</div>`;
            caja.hidden = false;
        }

        input.addEventListener('input', sugerir);
        input.addEventListener('focus', sugerir);
        input.addEventListener('keydown', ev => {
            const enlaces = [...caja.querySelectorAll('a')];
            if ((ev.key === 'ArrowDown' || ev.key === 'ArrowUp') && enlaces.length) {
                ev.preventDefault();
                enfocada = (enfocada + (ev.key === 'ArrowDown' ? 1 : -1) + enlaces.length) % enlaces.length;
                enlaces.forEach((a, i) => a.classList.toggle('enfocada', i === enfocada));
            } else if (ev.key === 'Enter') {
                const destino = enlaces[Math.max(0, enfocada)];
                if (destino) location.href = destino.href;
            } else if (ev.key === 'Escape') {
                caja.hidden = true;
            }
        });
        document.addEventListener('click', ev => {
            if (!ev.target.closest('.buscador')) caja.hidden = true;
        });
    })();

    // ---------- Pestañas de sección (con #ancla en la URL) ----------

    function iniciarPestanas(contenedor, alCambiar) {
        const nav = typeof contenedor === 'string' ? $(contenedor) : contenedor;
        if (!nav) return;
        const enlaces = [...nav.querySelectorAll('a[href^="#"]')];
        function mostrar(id, desdeUsuario) {
            if (!enlaces.some(a => a.getAttribute('href') === '#' + id)) id = enlaces[0].getAttribute('href').slice(1);
            enlaces.forEach(a => {
                const activo = a.getAttribute('href') === '#' + id;
                a.classList.toggle('activa', activo);
                a.setAttribute('aria-selected', activo);
                const panel = $(a.getAttribute('href').slice(1));
                if (panel) panel.hidden = !activo;
            });
            if (desdeUsuario) history.replaceState(null, '', '#' + id);
            alCambiar?.(id);
        }
        enlaces.forEach(a => a.addEventListener('click', ev => {
            ev.preventDefault();
            mostrar(a.getAttribute('href').slice(1), true);
        }));
        mostrar(location.hash.slice(1), false);
        return mostrar;
    }

    // Páginas sin datos propios (Aprende, Juegos…): la cinta, el indicador y las
    // alertas siguen actualizándose. Una página que arranca su refresco más tarde
    // (p. ej. la ficha de moneda) lo indica con window.REFRESCO_PROPIO = true.
    document.addEventListener('DOMContentLoaded', () => {
        if (!refrescoIniciado && !window.REFRESCO_PROPIO && $('estado-actualizacion')) iniciarRefresco(() => {});
    });

    /** Formatea con el idioma actual los números que el servidor deja como
        <span data-formato="grande|precio|dinero|porcentaje|numero" data-valor="123">. */
    function formatearDatos(raiz = document) {
        const formatos = { grande, precio, dinero, porcentaje, numero: v => numero(v, 0), compacto: numeroCompacto };
        raiz.querySelectorAll('[data-formato]').forEach(el => {
            const v = el.dataset.valor === '' ? null : parseFloat(el.dataset.valor);
            const f = formatos[el.dataset.formato];
            if (f) el.innerHTML = f(v);
        });
    }
    formatearDatos();
    detectarZona();

    window.Cripto = {
        $, t, precio, dinero, grande, dolares, MONEDA, numeroCompacto, cantidad, numero, fecha, hora, fechaISO, ZONA, porcentaje, pildora, escapar,
        insignia, puntajeMini, colorVeredicto, urlMoneda, celdaMoneda, sparkline, css, tooltipChart, FORMATOS_FECHA,
        medidor, toast, mostrarTooltip, ocultarTooltip, leer, escribir,
        esFavorito, listaFavoritos, alternarFavorito, botonFavorito, leerAlertas, guardarAlertas,
        obtenerMercado, iniciarRefresco, destellar, temaActual, iniciarPestanas, formatearDatos,
        api, sincronizarAhora, cerrarSesion,
    };
})();
