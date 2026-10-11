<?php
/**
 * Copia usuarios, rostros, progreso, ajustes y actividad de MySQL (XAMPP) al archivo SQLite
 * data/basedatos.sqlite. Se ejecuta una sola vez, desde la consola y con MySQL encendido:
 *
 *     php sql/migrar_mysql_a_sqlite.php
 *
 * Usa los datos de MySQL de config.php / config.local.php (DB_HOST, DB_NOMBRE…).
 * Si el archivo SQLite ya tiene usuarios, no hace nada (para no duplicar ni pisar datos).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/db.php';

if (!esSqlite()) {
    exit("DB_DRIVER no es 'sqlite': no hay nada que migrar.\n");
}

$mysql = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NOMBRE . ';charset=utf8mb4', DB_USUARIO, DB_CLAVE, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$sqlite = db();

if ((int) $sqlite->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0) {
    exit("El archivo SQLite ya tiene usuarios: no se migra nada.\n");
}

// Solo las columnas que existen en ambos lados (la base MySQL puede ser de una versión anterior).
$columnas = fn(string $tabla) => array_column($mysql->query("SHOW COLUMNS FROM `$tabla`")->fetchAll(), 'Field');
$sqlite->beginTransaction();
foreach (['usuarios', 'progreso', 'ajustes', 'actividad'] as $tabla) {
    $destino = array_column($sqlite->query("PRAGMA table_info($tabla)")->fetchAll(), 'name');
    $comunes = array_values(array_intersect($columnas($tabla), $destino));
    $lista = implode(', ', $comunes);
    $insertar = $sqlite->prepare("INSERT INTO $tabla ($lista) VALUES (" . implode(', ', array_fill(0, count($comunes), '?')) . ')');
    $n = 0;
    foreach ($mysql->query("SELECT $lista FROM `$tabla`") as $fila) {
        $insertar->execute(array_values($fila));
        $n++;
    }
    echo str_pad($tabla, 12) . " $n filas\n";
}
$sqlite->commit();
echo "Listo: los datos están en " . DB_ARCHIVO . "\n";
