<?php
/**
 * Configuración propia de tu servidor (tu PC con iniciar.bat o un hosting).
 * 1. Copia este archivo como config/config.local.php (ese archivo NO se sube a git).
 * 2. Cambia solo lo que necesites: todo lo que no definas aquí usa el valor de config/config.php.
 */

// Base de datos. Por defecto se usa SQLite (archivo data/basedatos.sqlite): no hay que
// configurar nada. Solo si quieres usar MySQL (p. ej. la base de un hosting), quita las "//":
// define('DB_DRIVER', 'mysql');
// define('DB_HOST', 'sqlXXX.tuhosting.com');
// define('DB_NOMBRE', 'nombre_de_la_base');
// define('DB_USUARIO', 'usuario_de_la_base');
// define('DB_CLAVE', 'contraseña_de_la_base');

// Contraseña para crear el primer administrador (¡no dejes admin123 en internet!).
define('ADMIN_PASSWORD', 'pon-aqui-una-clave-larga-y-unica');

// Clave Demo de CoinGecko (gratis en https://www.coingecko.com/en/api).
// define('COINGECKO_API_KEY', 'CG-...');

// Solo si la ruta detectada automáticamente fuera incorrecta:
// define('BASE_URL', '');            // página en la raíz del dominio
// define('BASE_URL', '/carpeta');    // página dentro de una carpeta

// Iniciar sesión con Gmail (ver LEEME.txt). También se puede pegar en Panel de administración → Ajustes.
define('GOOGLE_CLIENT_ID', '');   // p. ej. '1234567890-abc123.apps.googleusercontent.com'

// Iniciar sesión con Apple (paso 8; requiere cuenta de Apple Developer). Vacío = sin botón de Apple.
define('APPLE_CLIENT_ID', '');    // el "Services ID", p. ej. 'com.tusitio.web'

// Monto mínimo de cada compra en el juego de trading y en el simulador (USD).
// define('JUEGO_MONTO_MINIMO', 10);

// Iniciar sesión con Hotmail / Outlook (ID de aplicación de Azure, ver LEEME.txt).
// define('MICROSOFT_CLIENT_ID', '');

// Botón "Explícamelo con IA (Claude)" en los juegos (opcional, de pago por uso):
// clave de https://console.anthropic.com. Además hay que instalar el SDK con "composer install"
// (PHP 8.1 o superior). En Render se pone como variable de entorno, no aquí.
// define('ANTHROPIC_API_KEY', 'sk-ant-...');
