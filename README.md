# CriptoAnálisis

Análisis de criptomonedas y acciones en tiempo real, con juegos de inversión (dinero ficticio) y un **Coach IA** que explica cada decisión.

- **Juegos**: Reto 7 días, ¿Sube o baja? y Trading en vivo.
- **Coach IA**: lee la tendencia, el RSI, el impulso y la volatilidad del gráfico; recomienda comprar, esperar o vender, propone stop-loss / take-profit / monto, y al cerrar cada operación explica **por qué ganaste o perdiste**. Opcional: explicación redactada por Claude.
- **Cuentas**: correo y contraseña, reconocimiento facial, Gmail, Hotmail / Outlook y Apple.
- Español, inglés y francés.

Hecho en PHP + JavaScript (sin frameworks). Datos: un archivo SQLite en tu PC y PostgreSQL (Supabase) en internet.

---

## Cómo publicarla en internet con un link (GitHub + Render)

**Por qué no basta con GitHub Pages:** GitHub Pages solo sirve páginas estáticas (HTML). Esta página usa PHP y una base de datos, así que necesita un servidor que ejecute PHP. El camino es:

1. **GitHub** guarda el código.
2. **Render** (gratis) lo toma de GitHub, lo ejecuta y te da un link `https://….onrender.com` que cualquiera puede abrir desde cualquier país. Cada vez que subes cambios a GitHub, Render publica la nueva versión sola.

### Paso 1. Subir el proyecto a GitHub

1. Crea una cuenta en <https://github.com> (gratis).
2. Arriba a la derecha: **+** → **New repository**.
   - Nombre: `criptoanalisis`
   - Público o privado, como prefieras (Render puede leer los dos).
   - **No** marques "Add a README" (este proyecto ya tiene uno).
   - **Create repository**.
3. En tu PC abre una terminal **dentro de la carpeta del proyecto** (en VS Code: menú *Terminal → New Terminal*) y escribe, cambiando `TU-USUARIO`:

   ```bash
   git add .
   git commit -m "CriptoAnálisis: primera versión"
   git branch -M main
   git remote add origin https://github.com/TU-USUARIO/criptoanalisis.git
   git push -u origin main
   ```

   La primera vez se abre una ventana para iniciar sesión en GitHub: acéptala.

> **Lo que NO se sube** (está en `.gitignore`): la base de datos con los usuarios (`data/basedatos.sqlite`), tus claves (`config/config.local.php`) y la caché. Nunca pegues claves dentro de `config/config.php`: en internet se configuran como variables de entorno (paso 3).

### Paso 2. Publicarla en Render

1. Entra en <https://render.com> y pulsa **Get Started** → **GitHub** (inicia sesión con tu cuenta de GitHub).
2. **New +** → **Blueprint** → elige el repositorio `criptoanalisis` → **Connect**.
   Render lee el archivo `render.yaml` del proyecto y lo configura todo solo.
3. Pulsa **Apply** / **Deploy Blueprint**. La primera vez tarda unos 5 minutos.
4. Cuando diga **Live**, arriba aparece tu link, por ejemplo `https://criptoanalisis.onrender.com`. ¡Ese es el link para compartir!

**A tener en cuenta en el plan gratis:**

- Si nadie la visita en 15 minutos, la página "se duerme" y la siguiente visita tarda ~1 minuto en abrir.
- El disco de Render se borra en cada nueva publicación. Por eso las cuentas no se guardan en un archivo, sino en una **base de datos gratuita en Supabase** (paso 2b). Sin ese paso la página funciona, pero las cuentas se pierden cada vez que subes cambios.

### Paso 2a. Que la página no se duerma (gratis, 2 minutos)

Para que abra rápido siempre (también desde el celular), un servicio gratuito la visita cada 10 minutos y Render nunca la duerme:

1. Entra en <https://cron-job.org> → **Sign up** (gratis) y confirma tu correo.
2. **Create cronjob**:
   - **Title**: `CriptoAnalisis despierta`
   - **URL**: `https://criptoanalisis.onrender.com/offline.html` (con tu link)
   - **Execution schedule**: *Every 10 minutes*
3. **Create**.

Así no se gasta el cupo de CoinGecko (visita una página sin precios) y Render lo permite: un solo servicio encendido todo el mes cabe en las 750 horas gratis.

### Paso 2b. Base de datos gratuita para que las cuentas no se borren (Supabase)

En tu PC todo se sigue guardando en el archivo `data/basedatos.sqlite`. En internet se usa PostgreSQL de Supabase (gratis, 500 MB, más que suficiente):

1. Entra en <https://supabase.com> → **Start your project** → inicia sesión con GitHub.
2. **New project**: nombre `criptoanalisis`, una **contraseña solo con letras y números** (guárdala) y la región más cercana a tus usuarios (p. ej. *East US* o *South America*). **Create new project** y espera ~2 minutos.
3. Arriba pulsa **Connect**. En **Connection String** elige **Session pooler** (no "Direct connection": Render no la puede usar). Copia la dirección; se parece a:
   `postgresql://postgres.abcdefgh:[YOUR-PASSWORD]@aws-0-us-east-1.pooler.supabase.com:5432/postgres`
4. Cambia `[YOUR-PASSWORD]` por tu contraseña (sin los corchetes).
5. En Render → tu servicio → **Environment** → variable `DATABASE_URL` → pega esa dirección → **Save**.

Las tablas se crean solas la primera vez que alguien abre la página. Puedes verlas en Supabase → **Table Editor**.

**¿Quieres llevar a internet las cuentas que ya tienes en tu PC?** Ejecuta una vez en la terminal, dentro de la carpeta del proyecto (con tu dirección de Supabase):

```bash
C:\xampp\php\php.exe -d extension=pdo_pgsql sql/migrar_sqlite_a_postgres.php "postgresql://postgres.abcdefgh:TU-CLAVE@aws-0-us-east-1.pooler.supabase.com:5432/postgres"
```

Copia usuarios (con su contraseña cifrada), progreso de los juegos y ajustes; no borra ni duplica nada.

### Paso 3. Configurar claves (panel de Render → tu servicio → **Environment**)

| Variable | Para qué | Obligatoria |
|---|---|---|
| `ADMIN_PASSWORD` | Contraseña para convertir tu cuenta en administrador. Render la inventa: cópiala de ahí. | Ya viene creada |
| `DATABASE_URL` | Base de datos de Supabase donde se guardan las cuentas (paso 2b) | Muy recomendada |
| `GOOGLE_CLIENT_ID` | Iniciar sesión con Gmail | No |
| `MICROSOFT_CLIENT_ID` | Iniciar sesión con Hotmail / Outlook | No |
| `APPLE_CLIENT_ID` | Iniciar sesión con Apple | No |
| `COINGECKO_API_KEY` | Más peticiones a CoinGecko (sin clave usa la API pública) | No |
| `ANTHROPIC_API_KEY` | Botón "Explícamelo con IA (Claude)" en los juegos | No |

Después de guardar variables, Render vuelve a publicar la página sola.

**Hacerte administrador:** abre `https://TU-LINK/admin/index.php`, crea tu cuenta o entra, y escribe el valor de `ADMIN_PASSWORD`.

### Paso 4. Activar Gmail, Hotmail y Apple en el link público

El código de los tres ya está hecho y verifica la firma de cada inicio de sesión. Lo único que falta es que **tú registres la página en cada proveedor** (es gratis salvo Apple) y pegues el ID en Render:

**Gmail (Google)** — unos 5 minutos
1. <https://console.cloud.google.com> → proyecto nuevo "CriptoAnalisis".
2. *APIs y servicios → Pantalla de consentimiento de OAuth*: tipo **Externo**, nombre y correo. Pulsa **Publicar app** para que pueda entrar cualquier persona.
3. *Credenciales → Crear credenciales → ID de cliente de OAuth → Aplicación web*.
   En **Orígenes autorizados de JavaScript** añade tu link exacto, sin barra final: `https://criptoanalisis.onrender.com` (y `http://localhost:8000` para probar en tu PC).
4. Copia el ID (termina en `.apps.googleusercontent.com`) → variable `GOOGLE_CLIENT_ID` en Render.

**Hotmail / Outlook (Microsoft)** — unos 5 minutos
1. <https://portal.azure.com> → **Registros de aplicaciones** → **Nuevo registro**.
2. Tipo de cuenta: **Solo cuentas personales de Microsoft**. URI de redirección: plataforma **Web**, `https://criptoanalisis.onrender.com/paginas/oauth-retorno.html`.
3. *Autenticación* → marca **Tokens de id** → Guardar.
4. Copia el **Id. de aplicación (cliente)** → variable `MICROSOFT_CLIENT_ID` en Render.

**Apple** — requiere la membresía Apple Developer (99 USD/año)
1. <https://developer.apple.com/account> → *Certificates, Identifiers & Profiles* → **Identifiers** → crea un **App ID** con *Sign in with Apple*.
2. Crea un **Services ID** (p. ej. `com.criptoanalisis.web`), activa *Sign in with Apple* → **Configure**: dominio `criptoanalisis.onrender.com` y Return URL `https://criptoanalisis.onrender.com/paginas/acceso.php`.
3. Pega el Services ID en la variable `APPLE_CLIENT_ID` de Render.

> Si cambias el nombre del servicio en Render, tu link cambia: actualízalo también en Google, Microsoft y Apple.

### Paso 5 (opcional). Explicaciones con Claude

El Coach IA funciona siempre, gratis, en el navegador de cada jugador. Si además quieres el botón **"Explícamelo con IA (Claude)"**, que redacta una explicación personalizada:

1. Crea una clave en <https://console.anthropic.com> (pago por uso: cada explicación cuesta una fracción de centavo de dólar).
2. Pégala en la variable `ANTHROPIC_API_KEY` de Render.

Solo pueden usarlo personas con sesión iniciada, con un límite de 12 explicaciones cada 10 minutos.

### Actualizar la página después

Cada vez que cambies algo en tu PC:

```bash
git add .
git commit -m "Describe aquí el cambio"
git push
```

Render publica la nueva versión en unos minutos.

---

## Usarla en tu PC (sin internet público)

Doble clic en `iniciar.bat` y abre <http://localhost:8000>. Detalles en `LEEME.txt`.

## Aviso

Es un proyecto educativo con dinero ficticio. Ni el Coach IA ni ninguna herramienta acierta siempre: los indicadores mejoran las probabilidades, no las garantizan. No es asesoría financiera.
