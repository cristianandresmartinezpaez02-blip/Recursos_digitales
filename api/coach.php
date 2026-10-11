<?php
/**
 * Explicación del Coach IA redactada por Claude.
 *   POST api/coach.php  {juego, activo, precio, cambio24h, lado, analisis, cartera}  (cabecera X-CSRF)
 *   -> {ok: true, texto}
 * El navegador ya calculó el análisis técnico (assets/js/coach.js); aquí Claude lo explica
 * en palabras sencillas. Solo para usuarios con sesión y con un límite por sesión (cada
 * pregunta cuesta dinero en la API). Cómo activarlo: includes/ia.php.
 */
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/ia.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCorrecto()) {
    responder(403, ['ok' => false]);
}
if (!usuarioActual()) {
    responder(401, ['ok' => false, 'error' => t('js.ia_claude_cuenta')]);
}
if (!iaClaudeActiva()) {
    responder(503, ['ok' => false, 'error' => t('coach.off')]);
}

// Como mucho 12 explicaciones cada 10 minutos por sesión.
$ahora = time();
$_SESSION['coach_ia'] = array_values(array_filter($_SESSION['coach_ia'] ?? [], fn($t) => $t > $ahora - 600));
if (count($_SESSION['coach_ia']) >= 12) {
    responder(429, ['ok' => false, 'error' => t('coach.limite')]);
}

// Solo se aceptan los campos esperados, con tamaños acotados (nada de texto libre largo).
$e = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($e)) {
    responder(400, ['ok' => false]);
}
$texto = fn($v, int $max = 120) => mb_substr(trim(strip_tags((string) $v)), 0, $max);
$numero = fn($v) => is_numeric($v) ? round((float) $v, 4) : null;
$a = is_array($e['analisis'] ?? null) ? $e['analisis'] : [];
$c = is_array($e['cartera'] ?? null) ? $e['cartera'] : [];
$contexto = [
    'juego' => $texto($e['juego'] ?? '', 20),
    'activo' => $texto($e['activo'] ?? ''),
    'precio_usd' => $numero($e['precio'] ?? null),
    'cambio_24h_pct' => $numero($e['cambio24h'] ?? null),
    'operacion_que_prepara' => ($e['lado'] ?? '') === 'venta' ? 'venta' : 'compra',
    'analisis_tecnico' => [
        'recomendacion' => in_array($a['accion'] ?? '', ['comprar', 'esperar', 'vender'], true) ? $a['accion'] : 'esperar',
        'confianza_pct' => $numero($a['confianza'] ?? null),
        'rsi' => $numero($a['rsi'] ?? null),
        'tendencia_media_corta_vs_larga_pct' => $numero($a['tendencia'] ?? null),
        'impulso_24h_pct' => $numero($a['impulso'] ?? null),
        'volatilidad_diaria_pct' => $numero($a['volatilidad'] ?? null),
        'posicion_en_rango_0_a_1' => $numero($a['rango'] ?? null),
        'stop_loss_sugerido_pct' => $numero($a['sl'] ?? null),
        'take_profit_sugerido_pct' => $numero($a['tp'] ?? null),
        'razones' => array_map(fn($r) => $texto($r, 300), array_slice(is_array($a['razones'] ?? null) ? $a['razones'] : [], 0, 6)),
    ],
    'cartera_del_jugador' => [
        'patrimonio_usd' => $numero($c['patrimonio'] ?? null),
        'efectivo_usd' => $numero($c['efectivo'] ?? null),
        'ganancia_total_pct' => $numero($c['ganancia_pct'] ?? null),
        'resultado_de_su_posicion_en_este_activo_pct' => $numero($c['posicion_pct'] ?? null),
    ],
];

$idiomas = ['es' => 'español', 'en' => 'English', 'fr' => 'français'];
$sistema = 'Eres el Coach IA de CriptoAnálisis, un simulador educativo de inversión con dinero ficticio. '
    . 'Recibes en JSON el análisis técnico que ya calculó el juego (tendencia por medias móviles, impulso, RSI, '
    . 'volatilidad, posición en el rango de precios y un plan de stop-loss / take-profit) y la situación del jugador. '
    . 'Explícale al jugador, en ' . ($idiomas[idioma()] ?? 'español') . ', en 4 a 6 frases cortas y sencillas para alguien que está aprendiendo: '
    . 'qué significa la recomendación, cuál es el principal riesgo ahora mismo y una acción concreta para este momento '
    . '(comprar y con qué protección, esperar y a qué señal, o vender). Usa solo los datos del contexto; no inventes cifras. '
    . 'No prometas ganancias: deja claro que el mercado puede contradecir a cualquier indicador. '
    . 'Responde en texto plano, sin markdown ni listas.';

require_once __DIR__ . '/../vendor/autoload.php';

try {
    $cliente = new \Anthropic\Client(apiKey: claveAnthropic());
    $mensaje = $cliente->messages->create(
        model: 'claude-opus-5-5',
        maxTokens: 4000,
        outputConfig: ['effort' => 'low'],
        system: $sistema,
        messages: [
            ['role' => 'user', 'content' => json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)],
        ],
    );
} catch (\Anthropic\Core\Exceptions\RateLimitException $ex) {
    error_log('coach IA (límite de la API): ' . $ex->getMessage());
    responder(503, ['ok' => false, 'error' => t('coach.error')]);
} catch (\Anthropic\Core\Exceptions\APIStatusException $ex) {
    error_log('coach IA (' . $ex->getCode() . '): ' . $ex->getMessage());
    responder(502, ['ok' => false, 'error' => t('coach.error')]);
} catch (\Anthropic\Core\Exceptions\APIConnectionException $ex) {
    error_log('coach IA (sin conexión): ' . $ex->getMessage());
    responder(502, ['ok' => false, 'error' => t('coach.error')]);
}

$_SESSION['coach_ia'][] = $ahora;

if ($mensaje->stopReason === 'refusal') {
    responder(502, ['ok' => false, 'error' => t('coach.error')]);
}
$respuesta = '';
foreach ($mensaje->content as $bloque) {
    if ($bloque->type === 'text') {
        $respuesta .= $bloque->text;
    }
}
if (trim($respuesta) === '') {
    responder(502, ['ok' => false, 'error' => t('coach.error')]);
}
echo json_encode(['ok' => true, 'texto' => trim($respuesta)], JSON_UNESCAPED_UNICODE);
