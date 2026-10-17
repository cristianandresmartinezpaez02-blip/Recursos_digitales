/* Mi cuenta: resumen de los juegos y gestión del acceso facial. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;

    // ---------- Resumen (el progreso ya viene de la cuenta, ver header.php) ----------

    const trading = C.leer('minitrading_v1', null);
    if (trading && typeof trading.efectivo === 'number') {
        const ops = (trading.operaciones || []).length;
        $('st-trading').textContent = C.t('js.cuenta_ops', { n: ops });
        $('st-trading-sub').textContent = C.t('js.cuenta_posiciones', { n: Object.keys(trading.posiciones || {}).length });
    }
    const reto = C.leer('reto_records', [])[0];
    if (reto) $('st-reto').innerHTML = `<span class="${reto.ganancia >= 0 ? 'sube' : 'baja'}">${reto.ganancia >= 0 ? '+' : '−'}${C.numero(Math.abs(reto.ganancia))} %</span> ${'⭐'.repeat(reto.estrellas)}`;
    $('st-subebaja').textContent = C.leer('subebaja_record', 0);
    const sb = C.leer('subebaja_stats', null);
    if (sb && sb.rondas) $('st-subebaja-sub').textContent = C.t('js.sb_precision', { pct: Math.round(sb.aciertos / sb.rondas * 100), n: sb.rondas });
    $('st-favs').textContent = C.listaFavoritos().length;
    $('st-alertas').textContent = C.t('js.cuenta_alertas', { n: C.leerAlertas().filter(a => !a.disparada).length });

    $('cuenta-salir').addEventListener('click', C.cerrarSesion);

    // ---------- Rostro ----------

    function mensaje(texto, tipo = 'alerta') {
        $('rostro-mensaje').textContent = texto;
        $('rostro-mensaje').className = `mensaje ${tipo}`;
    }

    const errores = { permiso: 'js.facial_err_permiso', sin_camara: 'js.facial_err_camara', tiempo: 'js.facial_err_tiempo', script: 'js.facial_err_modelos', cancelado: 'js.facial_err_tiempo' };

    $('form-rostro').addEventListener('submit', async ev => {
        ev.preventDefault();
        const password = $('rostro-password') ? $('rostro-password').value : '';
        if ($('rostro-password') && !password) return mensaje(C.t('js.cuenta_err_password_vacia'));
        if (!$('rostro-consentimiento').checked) return mensaje(C.t('js.facial_falta_consentimiento'));
        const boton = $('rostro-guardar');
        boton.disabled = true;
        mensaje('');
        try {
            // Registro guiado por expresiones: neutral, sonrisa y sorpresa.
            const descriptores = await Facial.capturar($('camara-cuenta'), 'registro');
            await C.api('auth.php?accion=guardar_rostro', { password, descriptores });
            mensaje('✔ ' + C.t('js.facial_guardado_cuenta'), 'exito');
            C.toast('🙂 ' + C.t('js.facial_guardado_cuenta'), 'exito');
            setTimeout(() => location.reload(), 1200);
        } catch (e) {
            mensaje(errores[e.message] ? C.t(errores[e.message]) : e.message);
            boton.disabled = false;
        }
    });

    $('rostro-quitar')?.addEventListener('click', async () => {
        const password = $('rostro-password') ? $('rostro-password').value : '';
        if ($('rostro-password') && !password) return mensaje(C.t('js.cuenta_err_password_vacia'));
        if (!confirm(C.t('js.facial_confirmar_quitar'))) return;
        try {
            await C.api('auth.php?accion=quitar_rostro', { password });
            C.toast(C.t('js.facial_quitado'), 'exito');
            setTimeout(() => location.reload(), 900);
        } catch (e) {
            mensaje(e.message);
        }
    });

    // ---------- Seguridad: otras sesiones y eliminar la cuenta ----------

    $('cerrar-otras').addEventListener('click', async ev => {
        const boton = ev.currentTarget;
        boton.disabled = true;
        try {
            await C.api('auth.php?accion=cerrar_otras');
            C.toast('📱 ' + C.t('js.cuenta_otras_cerradas'), 'exito');
        } catch (e) {
            C.toast(e.message, 'alerta');
        }
        boton.disabled = false;
    });

    $('form-eliminar').addEventListener('submit', async ev => {
        ev.preventDefault();
        const caja = $('eliminar-mensaje');
        const datos = $('eliminar-password') ? { password: $('eliminar-password').value } : { confirmacion: $('eliminar-email').value.trim() };
        if (!(datos.password || datos.confirmacion) || !confirm(C.t('js.cuenta_confirmar_eliminar'))) return;
        try {
            await C.api('auth.php?accion=eliminar_cuenta', datos);
            // El progreso guardado en este navegador también se borra.
            try { localStorage.clear(); } catch (e) { /* almacenamiento bloqueado */ }
            C.toast(C.t('js.cuenta_eliminada'), 'exito');
            setTimeout(() => { location.href = `${APP.base}/index.php`; }, 1200);
        } catch (e) {
            caja.textContent = e.message;
            caja.className = 'mensaje alerta';
        }
    });

    addEventListener('pagehide', () => Facial.detener());
    if (!$('rostro-guardar').disabled) Facial.precargar(); // modelos listos antes de pulsar el botón
})();
