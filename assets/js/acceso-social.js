/* Iniciar sesión o crear cuenta en un clic con Gmail, Hotmail (cuenta Microsoft) y Apple.
   Cada proveedor abre su propia ventana para elegir la cuenta; el servidor
   (api/auth.php?accion=oauth) comprueba con el proveedor que el acceso es válido. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    const O = window.OAUTH || {};
    if (!$('acceso-social')) return;

    function mensaje(texto, tipo = 'alerta') {
        $('social-mensaje').textContent = texto;
        $('social-mensaje').className = `mensaje ${tipo}`;
    }

    async function enviar(proveedor, token, nombre = '') {
        mensaje(C.t('js.social_verificando'), 'texto-2');
        try {
            const r = await C.api('auth.php?accion=oauth', { proveedor, token, nombre });
            mensaje('✔ ' + C.t('js.cuenta_ok'), 'exito');
            C.escribir('ultimo_email', r.usuario.email);
            C.toast(`👋 ${C.t('js.cuenta_hola', { nombre: r.usuario.nombre })}`, 'exito');
            setTimeout(() => { location.href = window.VOLVER; }, 450);
        } catch (e) {
            mensaje(e.message);
        }
    }

    const sinConfigurar = proveedor => mensaje(C.t('js.social_sin_configurar', { proveedor }));

    // ---------- Gmail: ventana de Google para elegir una de tus cuentas ----------
    let clienteGoogle = null;
    if (O.google && window.google && google.accounts && google.accounts.oauth2) {
        clienteGoogle = google.accounts.oauth2.initTokenClient({
            client_id: O.google,
            scope: 'openid email profile',
            prompt: 'select_account', // siempre muestra la lista de cuentas
            callback: r => r.error ? mensaje(C.t('js.social_error', { proveedor: 'Gmail' })) : enviar('google', r.access_token),
            error_callback: e => { if (e && e.type !== 'popup_closed') mensaje(C.t('js.social_error', { proveedor: 'Gmail' })); },
        });
    }
    $('boton-google').addEventListener('click', () => {
        if (!O.google) return sinConfigurar('Gmail');
        if (!clienteGoogle) return mensaje(C.t('js.social_bloqueado', { proveedor: 'Google' }));
        mensaje('');
        clienteGoogle.requestAccessToken();
    });

    // ---------- Hotmail / Outlook (cuenta personal de Microsoft) ----------
    // Ventana de Microsoft → vuelve a paginas/oauth-retorno.html → nos pasa el id_token.
    let estadoMicrosoft = '';
    $('boton-microsoft').addEventListener('click', () => {
        if (!O.microsoft) return sinConfigurar('Hotmail');
        estadoMicrosoft = Math.random().toString(36).slice(2) + Date.now().toString(36);
        const url = 'https://login.microsoftonline.com/consumers/oauth2/v2.0/authorize?' + new URLSearchParams({
            client_id: O.microsoft, response_type: 'id_token', response_mode: 'fragment', redirect_uri: O.retorno,
            scope: 'openid email profile', nonce: O.nonce, state: estadoMicrosoft, prompt: 'select_account',
        });
        const ancho = 480, alto = 640;
        const ventana = window.open(url, 'microsoft', `width=${ancho},height=${alto},left=${(screen.width - ancho) / 2},top=${(screen.height - alto) / 2}`);
        if (!ventana) return mensaje(C.t('js.social_popup'));
        mensaje('');
    });
    addEventListener('message', ev => {
        if (ev.origin !== location.origin || !ev.data || ev.data.tipo !== 'oauth-retorno') return;
        const p = new URLSearchParams(ev.data.datos);
        if (!estadoMicrosoft || p.get('state') !== estadoMicrosoft) return; // respuesta que no pedimos
        estadoMicrosoft = '';
        if (p.get('error')) {
            if (p.get('error') !== 'access_denied') mensaje(C.t('js.social_error', { proveedor: 'Hotmail' }));
            return;
        }
        enviar('microsoft', p.get('id_token') || '');
    });

    // ---------- Apple (Sign in with Apple JS) ----------
    if (O.apple && window.AppleID) {
        AppleID.auth.init({ clientId: O.apple, scope: 'name email', redirectURI: O.redirect, nonce: O.nonce, usePopup: true });
    }
    $('boton-apple').addEventListener('click', async () => {
        if (!O.apple) return sinConfigurar('Apple');
        if (!window.AppleID) return mensaje(C.t('js.social_bloqueado', { proveedor: 'Apple' }));
        try {
            const r = await AppleID.auth.signIn();
            // Apple solo envía el nombre la primera vez que el usuario autoriza la página.
            const n = r.user && r.user.name;
            await enviar('apple', r.authorization.id_token, n ? `${n.firstName || ''} ${n.lastName || ''}`.trim() : '');
        } catch (e) {
            if (e && e.error !== 'popup_closed_by_user') mensaje(C.t('js.social_error', { proveedor: 'Apple' }));
        }
    });
})();
