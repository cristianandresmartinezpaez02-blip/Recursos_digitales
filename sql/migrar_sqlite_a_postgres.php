<?php
/**
 * Copia las cuentas de tu PC (data/basedatos.sqlite) a la base PostgreSQL de internet (Supabase):
 * usuarios (con su contraseña cifrada y rostro), progreso de los juegos y ajustes del panel.
 * Uso, una sola vez, desde la carpeta del proyecto (cambia la dirección por la tuya):
 *
 *   C:\xampp\php\php.exe -d extension=pdo_pgsql sql/migrar_sqlite_a_postgres.php "postgresql://usuario:clave@servidor:5432/postgres"
 *
 * No borra ni sobrescribe nada: los correos que ya existen en PostgreSQL se saltan.
 */
if (PHP_SAPI !== 'cli') {
    exit('Solo desde la consola.');
}
if (empty($argv[1]) || !preg_match('#^postgres(ql)?://#', $argv[1])) {
    exit("Falta la dirección: php sql/migrar_sqlite_a_postgres.php \"postgresql://usuario:clave@servidor:5432/postgres\"\n");
}
define('DATABASE_URL', $argv[1]);
require __DIR__ . '/../includes/db.php';

$origen = new PDO('sqlite:' . DB_ARCHIVO, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$destino = db(); // PostgreSQL (crea las tablas si aún no existen)

$columnas = ['nombre', 'email', 'password_hash', 'rol', 'activo', 'metodo_registro', 'google_sub', 'apple_sub',
    'microsoft_sub', 'descriptor_facial', 'facial_fecha', 'creado', 'ultimo_acceso'];
$existe = $destino->prepare('SELECT id FROM usuarios WHERE email = ?');
$insertar = $destino->prepare('INSERT INTO usuarios (' . implode(', ', $columnas) . ') VALUES (' . rtrim(str_repeat('?, ', count($columnas)), ', ') . ') RETURNING id');
$progreso = $destino->prepare('INSERT INTO progreso (usuario_id, clave, valor) VALUES (?, ?, ?) ON CONFLICT (usuario_id, clave) DO NOTHING');
$leerProgreso = $origen->prepare('SELECT clave, valor FROM progreso WHERE usuario_id = ?');

$copiados = $saltados = 0;
$destino->beginTransaction();
foreach ($origen->query('SELECT * FROM usuarios ORDER BY id') as $u) {
    $existe->execute([$u['email']]);
    if ($existe->fetchColumn()) {
        $saltados++;
        continue;
    }
    $insertar->execute(array_map(fn($c) => ($u[$c] ?? null) === '' && str_ends_with($c, '_sub') ? null : ($u[$c] ?? null), $columnas));
    $nuevoId = (int) $insertar->fetchColumn();
    $leerProgreso->execute([$u['id']]);
    foreach ($leerProgreso->fetchAll() as $p) {
        $progreso->execute([$nuevoId, $p['clave'], $p['valor']]);
    }
    $copiados++;
}
$ajuste = $destino->prepare('INSERT INTO ajustes (clave, valor) VALUES (?, ?) ON CONFLICT (clave) DO NOTHING');
foreach ($origen->query('SELECT clave, valor FROM ajustes') as $a) {
    $ajuste->execute([$a['clave'], $a['valor']]);
}
$destino->commit();

echo "Listo: $copiados cuentas copiadas, $saltados ya existían.\n";
