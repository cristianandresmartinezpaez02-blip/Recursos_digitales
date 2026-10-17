/* Service worker: permite instalar la página como app y verla sin conexión.
   - Páginas: primero la red (datos al día); si no hay conexión, la última copia o offline.html.
   - CSS, JS, imágenes y librerías de CDN: primero la caché (llevan ?v=… y cambian de URL al actualizarse).
   - api/: siempre la red. Excepción: los datos PÚBLICOS de mercado (API_SIN_CONEXION) se guardan
     para mostrar los últimos precios sin conexión. Nada de cuentas, progreso ni avisos. */
const VERSION = 'cripto-v5';
const API_SIN_CONEXION = ['api/mercado.php', 'api/extras.php', 'api/divisas.php'];
const BASE = new URL('./', self.location).pathname; // carpeta donde está sw.js (sirve en XAMPP y en un hosting)

self.addEventListener('install', ev => {
    ev.waitUntil(caches.open(VERSION).then(c => c.addAll([BASE + 'offline.html', BASE + 'assets/img/icono.svg'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', ev => {
    ev.waitUntil(caches.keys().then(claves => Promise.all(claves.filter(k => k !== VERSION).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', ev => {
    const pedido = ev.request;
    if (pedido.method !== 'GET') return;
    const url = new URL(pedido.url);
    // Precios públicos: primero la red; sin conexión, la última respuesta guardada.
    if (url.origin === location.origin && !url.search && API_SIN_CONEXION.some(r => url.pathname === BASE + r)) {
        ev.respondWith(fetch(pedido).then(r => {
            if (r.ok) { const copia = r.clone(); caches.open(VERSION).then(c => c.put(pedido, copia)); }
            return r;
        }).catch(() => caches.match(pedido).then(r => r || Response.error())));
        return;
    }
    if (url.origin === location.origin && (url.pathname.includes('/api/') || url.pathname.includes('/admin/'))) return;

    if (pedido.mode === 'navigate') {
        ev.respondWith(fetch(pedido).then(r => {
            const copia = r.clone();
            caches.open(VERSION).then(c => c.put(pedido, copia));
            return r;
        }).catch(() => caches.match(pedido).then(r => r || caches.match(BASE + 'offline.html'))));
        return;
    }

    const estatico = url.origin === location.origin ? url.pathname.includes('/assets/') : /cdn\.jsdelivr\.net|fonts\.(googleapis|gstatic)\.com/.test(url.host);
    if (estatico) {
        ev.respondWith(caches.match(pedido).then(guardado => guardado || fetch(pedido).then(r => {
            if (r.ok || r.type === 'opaque') { const copia = r.clone(); caches.open(VERSION).then(c => c.put(pedido, copia)); }
            return r;
        })));
    }
});

// ---------- Notificaciones push (api/avisos.php) ----------
// Llegan aunque la página esté cerrada. Al tocarla se abre la moneda (o se reutiliza una pestaña abierta).
self.addEventListener('push', ev => {
    let aviso = {};
    try { aviso = ev.data ? ev.data.json() : {}; } catch (e) { aviso = { cuerpo: ev.data && ev.data.text() }; }
    ev.waitUntil(self.registration.showNotification(aviso.titulo || 'CriptoAnálisis', {
        body: aviso.cuerpo || '',
        icon: aviso.icono || BASE + 'assets/img/icono.svg',
        badge: BASE + 'assets/img/icono.svg',
        tag: aviso.etiqueta || undefined,   // un aviso nuevo de la misma moneda reemplaza al anterior
        renotify: !!aviso.etiqueta,
        data: { url: aviso.url || BASE },
    }));
});

self.addEventListener('notificationclick', ev => {
    ev.notification.close();
    const destino = new URL(ev.notification.data && ev.notification.data.url || BASE, self.location.origin).href;
    ev.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(ventanas => {
        const abierta = ventanas.find(v => v.url.startsWith(self.location.origin));
        if (abierta) return abierta.navigate(destino).then(v => (v || abierta).focus()).catch(() => self.clients.openWindow(destino));
        return self.clients.openWindow(destino);
    }));
});
