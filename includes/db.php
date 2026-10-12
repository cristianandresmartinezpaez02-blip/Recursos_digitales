<?php
/**
 * Base de datos de usuarios, rostros, progreso de los juegos y ajustes.
 *
 *  - SQLite (por defecto): todo vive en un solo archivo, data/basedatos.sqlite. No hace falta
 *    XAMPP ni un servidor MySQL: basta con PHP (ver iniciar.bat) o cualquier hosting con PHP.
 *  - MySQL/MariaDB: define DB_DRIVER = 'mysql' y los datos de conexión en config/config.local.php.
 *  - PostgreSQL: se usa solo cuando existe DATABASE_URL (p. ej. Supabase gratis, para la página
 *    publicada en Render: allí el disco se borra en cada publicación y un archivo no sirve).
 *
 * Las tablas se crean solas la primera vez (sql/esquema_sqlite.sql, sql/esquema.sql o sql/esquema_pgsql.sql).
 */
require_once __DIR__ . '/../config/config.php';

function esSqlite(): bool
{
    return DB_DRIVER === 'sqlite';
}

function esPostgres(): bool
{
    return DB_DRIVER === 'pgsql';
}

/**
 * PostgreSQL a partir de DATABASE_URL (postgresql://usuario:clave@servidor:5432/base), la
 * cadena que da Supabase. Siempre con conexión cifrada (sslmode=require).
 */
function conexionPostgres(array $opciones): PDO
{
    // trim: al pegarla en Render a veces se cuela un salto de línea al final, y parse_url lo
    // convierte en «_» (la base pasaba a llamarse «postgres_» y no existía).
    $u = parse_url(trim(DATABASE_URL));
    if (!$u || empty($u['host'])) {
        throw new RuntimeException('DATABASE_URL no es una dirección postgresql:// válida');
    }
    parse_str($u['query'] ?? '', $extra);
    // connect_timeout: si la base no responde, la página no se queda esperando.
    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s;connect_timeout=5', $u['host'], $u['port'] ?? 5432,
        ltrim(rawurldecode($u['path'] ?? ''), '/') ?: 'postgres', $extra['sslmode'] ?? 'require');
    // Conexión persistente: cada proceso de Apache reutiliza la suya en vez de abrir una nueva
    // (cifrada, hasta otro país) en cada visita. El Dockerfile limita los procesos a 8.
    $opciones[PDO::ATTR_PERSISTENT] = true;
    $pdo = new PDO($dsn, rawurldecode($u['user'] ?? ''), rawurldecode($u['pass'] ?? ''), $opciones);
    // NOW() y las fechas guardadas usan la misma hora que PHP (config.php).
    $pdo->exec('SET TIME ZONE ' . $pdo->quote(ZONA_HORARIA));
    return $pdo;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $opciones = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (esSqlite()) {
        if (!is_file(DB_ARCHIVO)) {
            @unlink(DIR_DATA . '/.esquema_sqlite_v2'); // archivo nuevo (o borrado): hay que crear las tablas
        }
        $pdo = new PDO('sqlite:' . DB_ARCHIVO, null, null, $opciones);
        // Funciones de MySQL que usa el proyecto, con la hora de la zona de config.php.
        $pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'), 0);
        $version = 'SQLite ' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $pdo->sqliteCreateFunction('VERSION', fn() => $version, 0);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');   // lecturas y escrituras a la vez sin bloquearse
        $pdo->exec('PRAGMA busy_timeout = 5000');
        crearTablas($pdo, 'esquema_sqlite.sql', DIR_DATA . '/.esquema_sqlite_v2'); // cambia el número al modificar el esquema
        return $pdo;
    }
    if (esPostgres()) {
        // Si la base no responde, se recuerda el error: el resto de la visita no vuelve a esperar.
        static $fallo = null;
        if ($fallo) {
            throw $fallo;
        }
        try {
            $conexion = conexionPostgres($opciones);
            crearTablas($conexion, 'esquema_pgsql.sql', DIR_DATA . '/.esquema_pgsql_v3'); // todo es "IF NOT EXISTS": repetirlo no hace daño
        } catch (Throwable $e) {
            throw $fallo = $e;
        }
        return $pdo = $conexion;
    }
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NOMBRE . ';charset=utf8mb4', DB_USUARIO, DB_CLAVE, $opciones);
    } catch (PDOException $e) {
        // 1049 = la base no existe todavía: se crea.
        if ((int) ($e->errorInfo[1] ?? 0) !== 1049) {
            throw $e;
        }
        $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USUARIO, DB_CLAVE, $opciones);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NOMBRE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . DB_NOMBRE . '`');
    }
    crearTablas($pdo, 'esquema.sql', DIR_DATA . '/.esquema_v4'); // cambia el número al modificar sql/esquema.sql
    return $pdo;
}

function crearTablas(PDO $pdo, string $archivo, string $marca): void
{
    // Se marca en un archivo para no repetir el CREATE en cada petición.
    if (is_file($marca)) {
        return;
    }
    // Sin comentarios: pueden contener ";" y romper la separación en sentencias.
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents(__DIR__ . '/../sql/' . $archivo));
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $sentencia) {
        try {
            $pdo->exec($sentencia);
        } catch (PDOException $e) {
            // Columna o índice que ya existe (MySQL 1060/1061; SQLite "duplicate column"): ya estaba al día.
            if (!in_array((int) ($e->errorInfo[1] ?? 0), [1060, 1061], true) && stripos($e->getMessage(), 'duplicate column') === false) {
                throw $e;
            }
        }
    }
    @file_put_contents($marca, date('c'));
}

/** Fecha de hace $segundos, para comparar con columnas de fecha (igual en SQLite y MySQL). */
function fechaHace(int $segundos): string
{
    return date('Y-m-d H:i:s', time() - $segundos);
}

/** "INSERT … VALUES (…)" + esto = insertar o, si la clave ya existe, actualizar $columna. */
function siExisteActualizar(string $clave, string $columna): string
{
    return DB_DRIVER !== 'mysql'
        ? " ON CONFLICT($clave) DO UPDATE SET $columna = excluded.$columna"
        : " ON DUPLICATE KEY UPDATE $columna = VALUES($columna)";
}

/** Valores distintos de $columna unidos con comas (GROUP_CONCAT en SQLite/MySQL, string_agg en PostgreSQL). */
function unirDistintos(string $columna): string
{
    return esPostgres() ? "string_agg(DISTINCT $columna, ',')" : "GROUP_CONCAT(DISTINCT $columna)";
}

/** LIKE sin distinguir mayúsculas (PostgreSQL necesita ILIKE). */
function comoTexto(): string
{
    return esPostgres() ? 'ILIKE' : 'LIKE';
}
