/* Reconocimiento facial en el navegador con face-api.js (@vladmandic/face-api).
   - La imagen de la cámara NUNCA se envía: por cada expresión se calcula un "descriptor",
     una lista de 128 números que resume la forma del rostro, y eso es lo que va al servidor.
   - Registro guiado por expresiones: cara neutral 😐, sonrisa 😊 y sorpresa 😮. Guardar el
     rostro con varias expresiones hace que después te reconozca aunque sonrías o hables.
   - Para que sea rápido: los modelos se descargan en segundo plano al abrir la página
     (precargar), la GPU se "calienta" antes de usarla y se analiza cada fotograma sin pausas.
   Uso: const descriptores = await Facial.capturar(cajaCamara, 'registro' | 'entrar'); */
(function () {
    'use strict';
    const C = window.Cripto;
    const VERSION = '1.7.15';
    const CDN = `https://cdn.jsdelivr.net/npm/@vladmandic/face-api@${VERSION}`;
    const MODELOS = `${CDN}/model`;
    const TIEMPO_MAX_MS = 25000;

    const EMOJI = { neutral: '😐', happy: '😊', surprised: '😮', sad: '😢', angry: '😠', fearful: '😨', disgusted: '🤢' };

    /* Pasos de cada captura. "acepta" decide si el fotograma sirve según la expresión;
       "opcional" = milisegundos tras los que el paso se salta si no se logra la expresión. */
    const PLANES = {
        entrar: [
            { id: 'frente', muestras: 2 },
        ],
        registro: [
            { id: 'frente', muestras: 3, acepta: e => e.happy < 0.5 && e.surprised < 0.5 },
            { id: 'sonrisa', muestras: 2, acepta: e => e.happy > 0.6, opcional: 7000 },
            { id: 'sorpresa', muestras: 2, acepta: e => e.surprised > 0.5, opcional: 6000 },
        ],
    };

    let cargando = null;
    let flujo = null;
    let activo = false;
    let opciones = null;

    // ---------- Carga de la librería y los modelos ----------

    function cargarScript(src) {
        return new Promise((ok, error) => {
            const s = document.createElement('script');
            s.src = src;
            s.onload = ok;
            s.onerror = () => error(new Error('script'));
            document.head.appendChild(s);
        });
    }

    /** Ejecuta cada red una vez con una imagen vacía: la primera inferencia en la GPU es la más lenta. */
    async function calentar() {
        const lienzo = n => Object.assign(document.createElement('canvas'), { width: n, height: n });
        try {
            await faceapi.detectAllFaces(lienzo(160), opciones);
            await faceapi.nets.faceLandmark68Net.detectLandmarks(lienzo(112));
            await faceapi.nets.faceExpressionNet.predictExpressions(lienzo(112));
            await faceapi.nets.faceRecognitionNet.computeFaceDescriptor(lienzo(150));
        } catch (e) { /* solo es una optimización */ }
    }

    /** Descarga la librería y los 4 modelos (~7 MB la primera vez; después quedan en caché). */
    function cargarModelos(alProgreso = () => {}) {
        if (!cargando) {
            cargando = (async () => {
                if (!window.faceapi) await cargarScript(`${CDN}/dist/face-api.js`);
                try { await faceapi.tf.setBackend('webgl'); } catch (e) { /* usa el que haya */ }
                await faceapi.tf.ready();
                const redes = [faceapi.nets.tinyFaceDetector, faceapi.nets.faceLandmark68Net, faceapi.nets.faceExpressionNet, faceapi.nets.faceRecognitionNet];
                let listas = 0;
                await Promise.all(redes.map(r => r.loadFromUri(MODELOS).then(() => alProgreso(++listas, redes.length))));
                opciones = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 });
                await calentar();
            })().catch(e => { cargando = null; throw e; });
        }
        return cargando;
    }

    /** Empieza a descargar los modelos cuando el navegador esté libre (para que luego sea instantáneo). */
    function precargar() {
        const empezar = () => cargarModelos().catch(() => {});
        'requestIdleCallback' in window ? requestIdleCallback(empezar, { timeout: 2500 }) : setTimeout(empezar, 1200);
    }

    // ---------- Interfaz dentro de la caja de la cámara ----------

    function prepararCaja(caja, plan) {
        const marco = caja.querySelector('.camara-marco');
        let insignia = marco.querySelector('.camara-expresion');
        if (!insignia) {
            insignia = document.createElement('span');
            insignia.className = 'camara-expresion';
            insignia.setAttribute('aria-live', 'polite');
            marco.appendChild(insignia);
        }
        insignia.hidden = true;
        let pasos = caja.querySelector('.camara-pasos');
        if (!pasos) {
            pasos = document.createElement('ol');
            pasos.className = 'camara-pasos';
            marco.after(pasos);
        }
        pasos.hidden = plan.length < 2;
        pasos.innerHTML = plan.map(p => `<li data-paso="${p.id}">${C.t('js.facial_chip_' + p.id)}</li>`).join('');
        return {
            video: marco.querySelector('video'),
            lienzo: marco.querySelector('canvas'),
            estado: caja.querySelector('.camara-estado'),
            insignia,
            marcar: (id, clase) => pasos.querySelector(`[data-paso="${id}"]`)?.classList.add(clase),
        };
    }

    function dibujar(ui, rect, color) {
        const { lienzo, video } = ui;
        lienzo.width = video.videoWidth;
        lienzo.height = video.videoHeight;
        const ctx = lienzo.getContext('2d');
        ctx.clearRect(0, 0, lienzo.width, lienzo.height);
        if (!rect) return;
        ctx.strokeStyle = color;
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.roundRect ? ctx.roundRect(rect.x, rect.y, rect.width, rect.height, 16) : ctx.rect(rect.x, rect.y, rect.width, rect.height);
        ctx.stroke();
    }

    function mostrarExpresion(ui, expresiones) {
        const [nombre, prob] = Object.entries(expresiones).sort((a, b) => b[1] - a[1])[0];
        ui.insignia.hidden = false;
        ui.insignia.textContent = `${EMOJI[nombre] || '🙂'} ${C.t('js.expr_' + nombre)} ${Math.round(prob * 100)} %`;
    }

    // ---------- Cámara ----------

    async function abrirCamara(video) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) throw new Error('sin_camara');
        try {
            flujo = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }, audio: false });
        } catch (e) {
            throw new Error(e.name === 'NotAllowedError' ? 'permiso' : 'sin_camara');
        }
        video.srcObject = flujo;
        video.muted = true;
        video.playsInline = true;
        await video.play();
    }

    function detener() {
        activo = false;
        if (flujo) flujo.getTracks().forEach(t => t.stop());
        flujo = null;
    }

    function promedio(descriptores) {
        const r = new Array(128).fill(0);
        descriptores.forEach(d => d.forEach((v, i) => { r[i] += v / descriptores.length; }));
        return r.map(v => Math.round(v * 1e6) / 1e6);
    }

    const siguienteCuadro = () => new Promise(r => requestAnimationFrame(() => r()));

    /**
     * Abre la cámara de `caja` (.camara) y sigue el plan ("registro" o "entrar").
     * Devuelve un descriptor (128 números) por cada paso logrado.
     */
    async function capturar(caja, nombrePlan = 'entrar') {
        const plan = PLANES[nombrePlan];
        const ui = prepararCaja(caja, plan);
        const estado = (clave, datos) => { ui.estado.textContent = C.t('js.facial_' + clave, datos); };
        caja.hidden = false;
        caja.classList.add('activa');
        caja.classList.remove('lista');
        activo = true;
        estado('cargando');
        try {
            // Cámara y modelos a la vez; si ya se precargaron, esto es casi instantáneo.
            await Promise.all([
                abrirCamara(ui.video),
                cargarModelos((n, total) => estado('modelos', { n, total })),
            ]);
            const descriptores = [];
            let i = 0, muestras = [], inicioPaso = Date.now();
            const inicio = Date.now();
            estado('paso_' + plan[0].id);

            while (activo && i < plan.length) {
                if (Date.now() - inicio > TIEMPO_MAX_MS) throw new Error('tiempo');
                const paso = plan[i];
                const caras = await faceapi.detectAllFaces(ui.video, opciones).withFaceLandmarks().withFaceExpressions().withFaceDescriptors();
                if (!activo) break;

                if (caras.length !== 1) {
                    dibujar(ui, caras[0]?.detection.box, '#ff5d73');
                    ui.insignia.hidden = true;
                    estado(caras.length ? 'varios' : 'buscando');
                } else {
                    const cara = caras[0];
                    const rect = cara.detection.box;
                    mostrarExpresion(ui, cara.expressions);
                    if (rect.width < ui.video.videoWidth * 0.15 || cara.detection.score < 0.6) {
                        dibujar(ui, rect, '#f5b94a');
                        estado('acercate');
                    } else if (!paso.acepta || paso.acepta(cara.expressions)) {
                        dibujar(ui, rect, '#2ee6a0');
                        muestras.push(Array.from(cara.descriptor));
                        estado('leyendo', { n: muestras.length, total: paso.muestras });
                    } else {
                        dibujar(ui, rect, '#8b8dff');
                        estado('paso_' + paso.id);
                    }
                }

                // ¿Paso completo u omitido por tiempo?
                if (muestras.length >= paso.muestras) {
                    descriptores.push(promedio(muestras));
                    ui.marcar(paso.id, 'hecho');
                } else if (paso.opcional && Date.now() - inicioPaso > paso.opcional) {
                    ui.marcar(paso.id, 'omitido');
                } else {
                    await siguienteCuadro();
                    continue;
                }
                i++;
                muestras = [];
                inicioPaso = Date.now();
                if (i < plan.length) estado('paso_' + plan[i].id);
            }
            if (!descriptores.length) throw new Error('cancelado');
            caja.classList.add('lista');
            estado('listo', { n: descriptores.length });
            return descriptores;
        } finally {
            detener();
            caja.classList.remove('activa');
        }
    }

    window.Facial = { precargar, cargarModelos, capturar, detener, VERSION };
})();
