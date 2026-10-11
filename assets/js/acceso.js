/* Página de acceso: iniciar sesión (correo + contraseña o rostro) y crear cuenta. */
(function () {
    'use strict';
    const C = window.Cripto;
    const $ = C.$;
    let metodo = 'clave';
    let descriptoresRegistro = null; // uno por expresión capturada
    let capturando = false;

    // ---------- Pestañas Entrar / Crear cuenta ----------

    function mostrarModo(modo) {
        detenerCamara();
        $('vista-entrar').hidden = modo !== 'entrar';
        $('vista-registro').hidden = modo !== 'registro';
        document.querySelectorAll('.acceso-pestanas button').forEach(b => b.classList.toggle('activa', b.dataset.modo === modo));
        const url = new URL(location.href);
        modo === 'registro' ? url.searchParams.set('modo', 'registro') : url.searchParams.delete('modo');
        history.replaceState(null, '', url);
        document.title = `${C.t(modo === 'registro' ? 'js.cuenta_crear' : 'js.cuenta_entrar')} · CriptoAnálisis`;
    }
    document.querySelectorAll('.acceso-pestanas button').forEach(b => b.addEventListener('click', () => mostrarModo(b.dataset.modo)));
    document.querySelectorAll('[data-ir]').forEach(a => a.addEventListener('click', ev => { ev.preventDefault(); mostrarModo(a.dataset.ir); }));

    // Mostrar / ocultar contraseña.
    document.querySelectorAll('[data-ojo]').forEach(b => b.addEventListener('click', () => {
        const input = $(b.dataset.ojo);
        input.type = input.type === 'password' ? 'text' : 'password';
        b.textContent = input.type === 'password' ? '👁' : '🙈';
    }));

    // El último correo usado se recuerda para que entrar con el rostro sea rápido.
    const emailGuardado = C.leer('ultimo_email', '');
    if (emailGuardado) $('entrar-email').value = emailGuardado;

    function mensaje(id, texto, tipo = 'alerta') {
        $(id).textContent = texto;
        $(id).className = `mensaje ${tipo}`;
    }

    function exito(usuario) {
        C.escribir('ultimo_email', usuario.email);
        C.toast(`👋 ${C.t('js.cuenta_hola', { nombre: usuario.nombre })}`, 'exito');
        // Pequeña pausa para que se vea el aviso.
        setTimeout(() => { location.href = window.VOLVER; }, 450);
    }

    // ---------- Cámara ----------

    /** Traduce los códigos de error de facial.js; los demás (servidor) ya vienen traducidos. */
    function textoError(e) {
        const claves = { permiso: 'js.facial_err_permiso', sin_camara: 'js.facial_err_camara', tiempo: 'js.facial_err_tiempo', script: 'js.facial_err_modelos', cancelado: 'js.facial_err_tiempo' };
        if (claves[e.message]) return C.t(claves[e.message]);
        return e.message || C.t('js.facial_err_modelos');
    }

    async function usarCamara(idCaja, plan) {
        capturando = true;
        try {
            return await Facial.capturar($(idCaja), plan);
        } finally {
            capturando = false;
        }
    }

    function detenerCamara() {
        if (window.Facial) Facial.detener();
    }
    addEventListener('pagehide', detenerCamara);
    // Los modelos se descargan en segundo plano: cuando el usuario elija el rostro ya estarán listos.
    if ($('camara-entrar') || $('camara-registro')) Facial.precargar();

    // ---------- Iniciar sesión ----------

    function elegirMetodo(nuevo) {
        metodo = nuevo;
        detenerCamara();
        document.querySelectorAll('.metodo').forEach(b => b.classList.toggle('activo', b.dataset.metodo === nuevo));
        $('campo-clave').hidden = nuevo !== 'clave';
        $('camara-entrar').hidden = nuevo !== 'facial';
        $('camara-entrar').querySelector('.camara-estado').textContent = C.t('js.facial_instrucciones');
        $('entrar-password').required = nuevo === 'clave';
        $('entrar-enviar').textContent = nuevo === 'facial' ? `🙂 ${C.t('js.facial_entrar')}` : C.t('js.cuenta_entrar');
        mensaje('entrar-mensaje', '');

    }
    document.querySelectorAll('.metodo').forEach(b => b.addEventListener('click', () => elegirMetodo(b.dataset.metodo)));

    $('form-entrar').addEventListener('submit', async ev => {
        ev.preventDefault();
        if (capturando) return;
        const email = $('entrar-email').value.trim();
        const boton = $('entrar-enviar');
        if (!/^\S+@\S+\.\S+$/.test(email)) return mensaje('entrar-mensaje', C.t('js.cuenta_err_email'));
        mensaje('entrar-mensaje', '');
        boton.disabled = true;
        try {
            let r;
            if (metodo === 'clave') {
                const password = $('entrar-password').value;
                if (!password) throw new Error(C.t('js.cuenta_err_password_vacia'));
                r = await C.api('auth.php?accion=login', { email, password });
            } else {
                const [descriptor] = await usarCamara('camara-entrar', 'entrar');
                mensaje('entrar-mensaje', C.t('js.facial_verificando'), 'texto-2');
                r = await C.api('auth.php?accion=login_facial', { email, descriptor });
            }
            mensaje('entrar-mensaje', '✔ ' + C.t('js.cuenta_ok'), 'exito');
            exito(r.usuario);
        } catch (e) {
            mensaje('entrar-mensaje', textoError(e));
            boton.disabled = false;
        }
    });

    // ---------- Crear cuenta ----------

    function fuerza(pw) {
        let p = 0;
        if (pw.length >= 8) p++;
        if (pw.length >= 12) p++;
        if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) p++;
        if (/\d/.test(pw)) p++;
        if (/[^A-Za-z0-9]/.test(pw)) p++;
        return Math.min(4, p);
    }
    $('registro-password')?.addEventListener('input', ev => {
        const pw = ev.target.value;
        const f = pw ? fuerza(pw) : 0;
        const barra = $('fuerza-barra');
        barra.style.width = `${f * 25}%`;
        barra.dataset.nivel = f;
        $('fuerza-texto').textContent = pw ? C.t('js.fuerza_' + f) : C.t('js.cuenta_password_regla');
    });

    /** Registro guiado: neutral 😐 → sonrisa 😊 → sorpresa 😮 (las que no salgan se saltan solas). */
    async function capturarRegistro() {
        if (capturando) return;
        const boton = $('registro-capturar');
        const estado = $('camara-registro').querySelector('.camara-estado');
        boton.disabled = true;
        descriptoresRegistro = null;
        try {
            descriptoresRegistro = await usarCamara('camara-registro', 'registro');
            estado.textContent = '✔ ' + C.t('js.facial_guardado', { n: descriptoresRegistro.length });
            boton.textContent = '🔄 ' + C.t('js.facial_repetir');
        } catch (e) {
            estado.textContent = textoError(e);
        } finally {
            boton.disabled = false;
        }
    }

    // Al marcar la casilla la cámara se abre sola: no hace falta otro clic.
    $('registro-con-rostro')?.addEventListener('change', ev => {
        $('registro-facial').hidden = !ev.target.checked;
        if (ev.target.checked) capturarRegistro();
        else { detenerCamara(); descriptoresRegistro = null; }
    });
    $('registro-capturar')?.addEventListener('click', capturarRegistro);

    $('form-registro')?.addEventListener('submit', async ev => {
        ev.preventDefault();
        const f = ev.target;
        const datos = {
            nombre: f.nombre.value.trim(),
            email: f.email.value.trim(),
            password: f.password.value,
        };
        if (datos.nombre.length < 2) return mensaje('registro-mensaje', C.t('js.cuenta_err_nombre'));
        if (!/^\S+@\S+\.\S+$/.test(datos.email)) return mensaje('registro-mensaje', C.t('js.cuenta_err_email'));
        if (datos.password.length < 8 || !/[A-Za-z]/.test(datos.password) || !/\d/.test(datos.password)) return mensaje('registro-mensaje', C.t('js.cuenta_password_regla'));
        if (datos.password !== f.password2.value) return mensaje('registro-mensaje', C.t('js.cuenta_err_coinciden'));
        if ($('registro-con-rostro')?.checked) {
            if (!descriptoresRegistro) return mensaje('registro-mensaje', C.t('js.facial_falta'));
            if (!$('registro-consentimiento').checked) return mensaje('registro-mensaje', C.t('js.facial_falta_consentimiento'));
            datos.descriptores = descriptoresRegistro;
        }
        const boton = $('registro-enviar');
        boton.disabled = true;
        mensaje('registro-mensaje', '');
        try {
            const r = await C.api('auth.php?accion=registro', datos);
            mensaje('registro-mensaje', '✔ ' + C.t('js.cuenta_creada'), 'exito');
            exito(r.usuario);
        } catch (e) {
            mensaje('registro-mensaje', e.message);
            boton.disabled = false;
        }
    });
})();
