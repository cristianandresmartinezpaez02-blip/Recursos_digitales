<?php
/**
 * Configuración general de CriptoAnálisis.
 * Este archivo está protegido por config/.htaccess: la clave nunca llega al navegador.
 */

define('APP_NOMBRE', 'CriptoAnálisis');

// En un hosting, copia config/config.local.ejemplo.php como config/config.local.php y pon
// ahí los datos de la base de datos y las claves: lo que se defina allí manda sobre lo de abajo.
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// En un servidor en la nube (Render, Railway, Docker…) las claves se ponen como variables de
// entorno en su panel, nunca en el código que se sube a GitHub. Mandan sobre los valores de abajo.
foreach (['COINGECKO_API_KEY', 'ADMIN_PASSWORD', 'GOOGLE_CLIENT_ID', 'APPLE_CLIENT_ID', 'MICROSOFT_CLIENT_ID', 'ANTHROPIC_API_KEY', 'DATABASE_URL', 'GOOGLE_SITE_VERIFICATION'] as $variableEntorno) {
    if (!defined($variableEntorno) && (string) getenv($variableEntorno) !== '') {
        define($variableEntorno, (string) getenv($variableEntorno));
    }
}
unset($variableEntorno);

// Ruta base del proyecto (para construir enlaces). Se detecta sola comparando el archivo
// que se ejecuta con la URL pedida: "" si la página está en la raíz del dominio
// (https://misitio.com/) y "/carpeta" si está en una carpeta (p. ej. "/Recursos_digitales" en XAMPP).
// No depende de DOCUMENT_ROOT, que en muchos hostings apunta a otra ruta (enlaces simbólicos).
if (!defined('BASE_URL')) {
    $raizProyecto = rtrim(str_replace('\\', '/', (string) realpath(__DIR__ . '/..')), '/');
    $archivoScript = str_replace('\\', '/', (string) realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $urlScript = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $baseDetectada = '';
    if ($archivoScript !== '' && stripos($archivoScript, $raizProyecto . '/') === 0) {
        $relativa = substr($archivoScript, strlen($raizProyecto)); // p. ej. "/paginas/trading.php"
        if (strcasecmp(substr($urlScript, -strlen($relativa)), $relativa) === 0) {
            $baseDetectada = substr($urlScript, 0, -strlen($relativa));
        }
    }
    define('BASE_URL', rtrim($baseDetectada, '/'));
}

// API de CoinGecko (plan Demo).
define('COINGECKO_URL', 'https://api.coingecko.com/api/v3');
// La clave va en config/config.local.php o en la variable de entorno COINGECKO_API_KEY (sin clave se usa la API pública).
defined('COINGECKO_API_KEY') || define('COINGECKO_API_KEY', '');

// El plan Demo permite 30 peticiones/minuto y 10.000 al mes.
define('LIMITE_MENSUAL_API', 10000);
// Si la clave se queda sin cupo, reintentar con la API pública sin clave.
define('RESPALDO_SIN_CLAVE', true);

// Binance (datos públicos de mercado, sin clave). Da el precio al segundo y velas con
// la hora de APERTURA real de cada una. Si una moneda no cotiza en Binance (o Binance
// falla) se usa CoinGecko como respaldo.
define('BINANCE_URL', 'https://data-api.binance.vision/api/v3');
define('USAR_BINANCE', true);
define('CACHE_BINANCE_PRECIOS_SEG', 15); // precio actual de todas las monedas

// Índice de miedo y codicia (API pública de alternative.me, sin clave).
define('FNG_URL', 'https://api.alternative.me/fng/');

define('MONEDA_BASE', 'usd');

// Segundos que se guarda en caché cada respuesta (así se ahorran peticiones).
define('CACHE_MERCADO_SEG', 60);       // top 100 -> se actualiza cada minuto
define('CACHE_GLOBAL_SEG', 900);       // capitalización global y dominancia
define('CACHE_TENDENCIAS_SEG', 1800);  // monedas en tendencia
define('CACHE_DETALLE_SEG', 86400);    // descripción de cada moneda
define('CACHE_FNG_SEG', 3600);         // miedo y codicia
define('CACHE_CATEGORIAS_SEG', 3600);  // lista de categorías
define('CACHE_CATEGORIA_SEG', 600);    // monedas de una categoría
define('CACHE_EXCHANGES_SEG', 3600);   // exchanges
// Un dato caducado pero con menos de 2 días se muestra al instante y se renueva en segundo plano.
define('CACHE_VIEJA_MAX_SEG', 172800);
// Precios y velas de Binance: como mucho 2 minutos (los gráficos siguen "en vivo").
define('CACHE_VIEJA_VIVO_SEG', 120);
// Histórico por rango (días => segundos de caché).
define('CACHE_HISTORIAL', [1 => 600, 7 => 1800, 30 => 3600, 90 => 7200, 365 => 21600]);

// Histórico desde Binance (días => [intervalo de vela, segundos de caché]).
define('BINANCE_HISTORIAL', [1 => ['5m', 30], 7 => ['15m', 120], 30 => ['1h', 600], 90 => ['4h', 1800], 365 => ['1d', 3600]]);
// Velas japonesas desde Binance (días => [intervalo de vela, segundos de caché]).
define('BINANCE_VELAS', [1 => ['30m', 30], 7 => ['4h', 300], 30 => ['4h', 600], 90 => ['1d', 1800], 365 => ['3d', 3600]]);

// Zona horaria del servidor (Cúcuta / Bogotá, UTC−5). En el navegador cada visitante ve
// la hora de su zona, detectada automáticamente por su conexión (ver app.js).
define('ZONA_HORARIA', 'America/Bogota');

// Intervalo de refresco en el navegador (milisegundos).
define('REFRESCO_MS', 60000);

// Carpeta de caché en archivos JSON (sin base de datos).
define('DIR_DATA', __DIR__ . '/../data');
define('DIR_CACHE', DIR_DATA . '/cache');

// Contraseña de instalación del panel de administración (admin/index.php). Solo sirve
// mientras no exista ningún administrador: convierte tu cuenta en la del primer admin.
// Después, los administradores dan o quitan permisos desde el panel. ¡Cámbiala!
defined('ADMIN_PASSWORD') || define('ADMIN_PASSWORD', 'admin123');

// Juego de minitrading.
define('JUEGO_SALDO_INICIAL', 10000);
define('JUEGO_COMISION', 0.001); // 0.1 % por operación
// Monto mínimo de cada compra (USD) en el juego de trading y en el simulador.
defined('JUEGO_MONTO_MINIMO') || define('JUEGO_MONTO_MINIMO', 10);

// Bolsa de valores (Yahoo Finanzas, sin clave) y divisas del mundo (open.er-api.com, sin clave).
define('YAHOO_URL', 'https://query1.finance.yahoo.com');
define('DIVISAS_URL', 'https://open.er-api.com/v6/latest/USD');
define('CACHE_BOLSA_SEG', 120);        // cotizaciones de acciones e índices
define('CACHE_DIVISAS_SEG', 3600);     // tasas de todas las divisas (se publican una vez al día)
define('CACHE_DIVISAS_VIVO_SEG', 300); // divisas principales en vivo (Yahoo)

// Inicio de sesión con Gmail, Apple y Hotmail. También se pueden pegar en Panel de administración → Ajustes.
// Cómo obtener cada dato: LEEME.txt.
defined('GOOGLE_CLIENT_ID') || define('GOOGLE_CLIENT_ID', ''); // xxxx.apps.googleusercontent.com
defined('APPLE_CLIENT_ID') || define('APPLE_CLIENT_ID', '');   // Services ID, p. ej. com.misitio.web
defined('MICROSOFT_CLIENT_ID') || define('MICROSOFT_CLIENT_ID', ''); // Hotmail: ID de aplicación de Azure

// Google Search Console (para que la página salga en Google): método «Etiqueta HTML»,
// solo el valor de content="…". Ver README.md → «Aparecer en Google».
defined('GOOGLE_SITE_VERIFICATION') || define('GOOGLE_SITE_VERIFICATION', '');

date_default_timezone_set(ZONA_HORARIA);

// Compresión gzip y tareas después de responder (ver includes/respuesta.php).
require_once __DIR__ . '/../includes/respuesta.php';

// Base de datos: usuarios (correo, rostro, Google, Apple), progreso de los juegos y ajustes.
// Por defecto es SQLite: un solo archivo (data/basedatos.sqlite), sin XAMPP ni MySQL.
// Para usar MySQL (p. ej. la base de un hosting) pon en config/config.local.php:
//   define('DB_DRIVER', 'mysql'); y los datos DB_HOST, DB_NOMBRE, DB_USUARIO, DB_CLAVE.
// En internet (Render) se usa PostgreSQL si existe la variable de entorno DATABASE_URL
// (p. ej. la base gratuita de Supabase): así las cuentas no se borran al publicar cambios.
defined('DATABASE_URL') || define('DATABASE_URL', '');
defined('DB_DRIVER') || define('DB_DRIVER', DATABASE_URL !== '' ? 'pgsql' : 'sqlite');
defined('DB_ARCHIVO') || define('DB_ARCHIVO', DIR_DATA . '/basedatos.sqlite');
// Datos de MySQL (solo si DB_DRIVER es 'mysql'; los de abajo son los de XAMPP).
defined('DB_HOST') || define('DB_HOST', '127.0.0.1');
defined('DB_NOMBRE') || define('DB_NOMBRE', 'recursos_digitales');
defined('DB_USUARIO') || define('DB_USUARIO', 'root');
defined('DB_CLAVE') || define('DB_CLAVE', '');

// Reconocimiento facial (face-api.js). Distancia euclidiana máxima entre dos
// descriptores de 128 números para considerar que son la misma persona.
// 0.6 es el valor del modelo original; 0.5 es más estricto (menos falsos positivos).
define('FACIAL_UMBRAL', 0.5);

// Bloqueo por intentos fallidos de inicio de sesión.
define('LOGIN_MAX_INTENTOS', 5);      // por correo
define('LOGIN_VENTANA_MIN', 15);      // minutos
