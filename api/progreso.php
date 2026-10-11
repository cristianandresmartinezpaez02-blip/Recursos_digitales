<?php
/**
 * Progreso del usuario conectado (juegos, favoritas y alertas).
 *   GET  api/progreso.php                 -> {clave: valor, ...}
 *   POST api/progreso.php  {clave: valor} -> guarda esas claves (cabecera X-CSRF)
 * Los valores son el mismo JSON que la página guarda en localStorage.
 */
require_once __DIR__ . '/../includes/sesion.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$u = usuarioActual();
if (!$u) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(progresoUsuario((int) $u['id']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// sendBeacon no permite cabeceras: el token también puede ir en ?csrf=.
if (isset($_GET['csrf'])) {
    $_POST['csrf'] = $_GET['csrf'];
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCorrecto()) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

// Sin "true": los objetos JSON quedan como objetos. Con arrays asociativos, un objeto vacío {}
// se volvía a guardar como lista [] y el juego perdía las monedas compradas después.
$datos = json_decode(file_get_contents('php://input'));
if (!is_object($datos)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$st = db()->prepare('INSERT INTO progreso (usuario_id, clave, valor) VALUES (?, ?, ?)' . siExisteActualizar('usuario_id, clave', 'valor'));
$guardadas = 0;
foreach ($datos as $clave => $valor) {
    if (!in_array($clave, CLAVES_PROGRESO, true)) {
        continue;
    }
    $texto = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($texto === false || strlen($texto) > 2_000_000) {
        continue;
    }
    $st->execute([$u['id'], $clave, $texto]);
    $guardadas++;
}
echo json_encode(['ok' => true, 'guardadas' => $guardadas]);
