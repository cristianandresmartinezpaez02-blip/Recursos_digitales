/* Avisos en el celular (notificaciones push): activa o desactiva las notificaciones de este
   navegador y elige cuánto tiene que moverse una favorita para avisar. Panel: includes/panel_push.php.
   El servidor revisa los precios cada 10 minutos (api/avisos.php) aunque la página esté cerrada. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    if (!$('panel-push')) return;

    const soportado = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    const esIos = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const instalada = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    let clavePublica = '';

    function mensaje(texto, tipo = '') {
        $('push-mensaje').textContent = texto;
        $('push-mensaje').className = `nota ${tipo}`;
    }

    function pintar(activa) {
        $('push-estado').textContent = C.t(activa ? 'js.push_estado_activo' : 'js.push_estado_apagado');
        $('push-estado').classList.toggle('activa', activa);
        $('push-activar').hidden = activa;
        $('push-probar').hidden = !activa;
        $('push-desactivar').hidden = !activa;
    }

    /** La clave pública llega en base64url; PushManager la quiere en bytes. */
    function bytesClave(b64) {
        const relleno = '='.repeat((4 - b64.length % 4) % 4);
        const binario = atob((b64 + relleno).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from(binario, c => c.charCodeAt(0));
    }

    async function suscripcionActual() {
        const reg = await navigator.serviceWorker.ready;
        return reg.pushManager.getSubscription();
    }

    async function activar() {
        mensaje('');
        if (Notification.permission === 'denied') return mensaje(C.t('js.push_bloqueadas'), 'alerta');
        $('push-activar').disabled = true;
        try {
            if (await Notification.requestPermission() !== 'granted') return mensaje(C.t('js.push_bloqueadas'), 'alerta');
            const reg = await navigator.serviceWorker.ready;
            let sus = await reg.pushManager.getSubscription();
            // Una suscripción hecha con otra clave (p. ej. claves nuevas en el servidor) ya no sirve.
            if (sus && sus.options && sus.options.applicationServerKey) {
                const actual = btoa(String.fromCharCode(...new Uint8Array(sus.options.applicationServerKey))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
                if (actual !== clavePublica) { await sus.unsubscribe(); sus = null; }
            }
            sus ||= await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: bytesClave(clavePublica) });
            await C.api('push.php?accion=suscribir', { suscripcion: sus.toJSON(), umbral: Number($('push-umbral').value) });
            pintar(true);
            mensaje(C.t('js.push_activadas', { pct: $('push-umbral').value }), 'exito');
        } catch (e) {
            mensaje(e.message || C.t('js.push_error'), 'alerta');
        } finally {
            $('push-activar').disabled = false;
        }
    }

    async function desactivar() {
        try {
            const sus = await suscripcionActual();
            if (sus) {
                await C.api('push.php?accion=cancelar', { endpoint: sus.endpoint });
                await sus.unsubscribe();
            }
            pintar(false);
            mensaje(C.t('js.push_desactivadas'));
        } catch (e) {
            mensaje(e.message, 'alerta');
        }
    }

    async function probar() {
        $('push-probar').disabled = true;
        try {
            await C.api('push.php?accion=probar');
            mensaje(C.t('js.push_prueba_enviada'), 'exito');
        } catch (e) {
            mensaje(e.message, 'alerta');
        } finally {
            setTimeout(() => { $('push-probar').disabled = false; }, 3000);
        }
    }

    async function iniciar() {
        if (!APP.usuario) return mensaje(C.t('js.push_sin_cuenta'), 'alerta');
        if (!soportado) {
            $('push-activar').disabled = true;
            if (esIos && !instalada) $('push-ios').hidden = false;
            return mensaje(C.t(esIos && !instalada ? 'js.push_ios_instalar' : 'js.push_no_soportado'), 'alerta');
        }
        try {
            const r = await fetch(`${APP.base}/api/push.php`, { cache: 'no-store', credentials: 'same-origin' }).then(x => x.json());
            if (!r.ok) throw new Error(r.error);
            clavePublica = r.clave;
            if (r.umbral) $('push-umbral').value = String(r.umbral);
            // Activa en ESTE navegador = tiene suscripción y el servidor la conoce.
            const sus = await suscripcionActual();
            pintar(!!sus && r.activas > 0 && Notification.permission === 'granted');
        } catch (e) {
            mensaje(C.t('js.push_error'), 'alerta');
        }
    }

    $('push-activar').addEventListener('click', activar);
    $('push-desactivar').addEventListener('click', desactivar);
    $('push-probar').addEventListener('click', probar);
    $('push-umbral').addEventListener('change', async () => {
        if ($('push-activar').hidden === false) return; // aún no activas: se envía al activar
        try {
            await C.api('push.php?accion=umbral', { umbral: Number($('push-umbral').value) });
            mensaje(C.t('js.push_umbral_guardado', { pct: $('push-umbral').value }), 'exito');
        } catch (e) {
            mensaje(e.message, 'alerta');
        }
    });
    if (esIos && !instalada) $('push-ios').hidden = false;
    iniciar();
})();
