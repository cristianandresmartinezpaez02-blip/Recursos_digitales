# QuizPy

Juego de preguntas en vivo al estilo Kahoot, hecho en **Python**.

- **Backend:** Python 3 + Flask (páginas y API) + Flask-SocketIO (tiempo real por WebSockets)
- **Base de datos:** SQLite (incluida en Python, no hay que instalar nada)
- **Plantillas:** Jinja2
- **Frontend:** HTML, CSS y JavaScript

## Cómo arrancarlo

1. Instala Python 3.10 o superior desde https://www.python.org/downloads/
   (marca la casilla **"Add python.exe to PATH"** durante la instalación).
2. Haz doble clic en `iniciar.bat`.
   La primera vez crea un entorno virtual e instala las dependencias.
3. Abre http://localhost:5000 en el ordenador del anfitrión.

Si prefieres la consola:

```bash
cd quizpy
python -m venv venv
venv\Scripts\activate
pip install -r requirements.txt
python app.py
```

Los jugadores entran desde el móvil con la dirección que aparece en la pantalla de la partida
(por ejemplo `http://192.168.1.20:5000`). Tienen que estar en **la misma red wifi**.
Si Windows pregunta por el firewall la primera vez, pulsa **Permitir acceso**.

## Cómo se juega

1. **Mis quizzes → ▶ Jugar.** Se proyecta un PIN y un código QR.
2. Los jugadores entran con el PIN, un apodo y un avatar.
3. Pulsa **Comenzar**. Usa **Espacio** o **Enter** para pasar a la siguiente pantalla.
4. Al final se muestra el podio, y el informe queda guardado en **Historial**.

## Mejoras respecto a Kahoot

| | Kahoot (gratis) | QuizPy |
|---|---|---|
| Tipos de pregunta | Quiz y V/F | Quiz, V/F, varias correctas, escribir la respuesta y encuesta |
| Pregunta en el móvil | Solo se ven las formas | Se ven la pregunta, la imagen y las respuestas |
| Reconexión | Se pierde la partida | El jugador vuelve automáticamente con sus puntos |
| Respuesta correcta en el móvil | No | Sí, después de cada pregunta |
| Reacciones con emojis | No | Sí, aparecen en la pantalla grande |
| Informes y Excel | De pago | Gratis: historial con % de acierto y CSV |
| Jugadores | Limitados | Hasta 300 (`MAX_PLAYERS` en `app.py`) |
| Importar y exportar quizzes | Limitado | JSON |
| Datos | En la nube de otra empresa | En tu propio ordenador |

## Puntuación

- Una respuesta correcta vale hasta **1000 puntos**: el 100 % si respondes al instante y el 50 % si respondes en el último segundo.
- Las preguntas con **puntos dobles** valen el doble.
- **Bonus por racha:** +100 puntos por cada acierto seguido, hasta un máximo de +500.
- Las encuestas no dan puntos ni rompen la racha.
- En "Escribir respuesta" no importan las mayúsculas, las tildes ni los espacios de más.

## Estructura

```
quizpy/
├── app.py            servidor: rutas, API y lógica del juego en tiempo real
├── db.py             base de datos SQLite y validación de quizzes
├── requirements.txt
├── iniciar.bat       arranque con doble clic (Windows)
├── templates/        páginas HTML (Jinja2)
└── static/
    ├── css/style.css
    └── js/           host.js (proyector), play.js (móvil), editor.js, fx.js (sonido y confeti)
```

Socket.IO, el generador de QR y la fuente se cargan desde internet (CDN), así que el ordenador del anfitrión necesita conexión.
