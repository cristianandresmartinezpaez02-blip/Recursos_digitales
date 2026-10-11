/* Service worker: permite instalar la página como app y verla sin conexión.
   - Páginas: primero la red (datos al día); si no hay conexión, la última copia o offline.html.
   - CSS, JS, imágenes y librerías de CDN: primero la caché (llevan ?v=… y cambian de URL al actualizarse).
   - api/: siempre la red (precios y cuentas nunca se guardan aquí). */
const VERSION = 'cripto-v3';
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
