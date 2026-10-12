<?php
/**
 * Revisión de precios para los avisos push (la usa api/avisos.php):
 *   - Favoritas: cuando suben o bajan el % elegido (umbral) desde el último aviso (máx. 24 h).
 *   - Alertas de precio: cuando se alcanza el precio de la alerta (una sola vez).
 */
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/push.php';

const AVISOS_REF_MAX_SEG = 86400;   // la referencia de cada favorita se renueva a las 24 h
const AVISOS_MAX_POR_USUARIO = 3;   // más movimientos a la vez se juntan en un solo aviso

/** Textos de los avisos (se envían sin visita: no hay cookie de idioma). */
function textoAviso(string $idioma, string $clave, array $p = []): string
{
    $textos = [
        'es' => ['subio' => '📈 {nombre} subió {pct}', 'bajo' => '📉 {nombre} bajó {pct}', 'cuerpo' => 'Ahora {precio} (antes {antes}, hace {horas})',
                 'alerta_mayor' => '🔔 {nombre} llegó a {precio}', 'alerta_menor' => '🔔 {nombre} bajó a {precio}', 'alerta_cuerpo' => 'Tu alerta: {condicion} {objetivo}',
                 'mayor' => 'por encima de', 'menor' => 'por debajo de', 'varias' => '🔔 {n} de tus favoritas se movieron', 'horas' => '{n} h', 'minutos' => '{n} min'],
        'en' => ['subio' => '📈 {nombre} is up {pct}', 'bajo' => '📉 {nombre} is down {pct}', 'cuerpo' => 'Now {precio} (was {antes}, {horas} ago)',
                 'alerta_mayor' => '🔔 {nombre} reached {precio}', 'alerta_menor' => '🔔 {nombre} fell to {precio}', 'alerta_cuerpo' => 'Your alert: {condicion} {objetivo}',
                 'mayor' => 'above', 'menor' => 'below', 'varias' => '🔔 {n} of your favorites moved', 'horas' => '{n} h', 'minutos' => '{n} min'],
        'fr' => ['subio' => '📈 {nombre} a monté de {pct}', 'bajo' => '📉 {nombre} a baissé de {pct}', 'cuerpo' => 'Maintenant {precio} (avant {antes}, il y a {horas})',
                 'alerta_mayor' => '🔔 {nombre} a atteint {precio}', 'alerta_menor' => '🔔 {nombre} est tombé à {precio}', 'alerta_cuerpo' => 'Ton alerte : {condicion} {objetivo}',
                 'mayor' => 'au-dessus de', 'menor' => 'en dessous de', 'varias' => '🔔 {n} de tes favorites ont bougé', 'horas' => '{n} h', 'minutos' => '{n} min'],
    ];
    $texto = ($textos[$idioma] ?? $textos['es'])[$clave];
    foreach ($p as $k => $v) {
        $texto = str_replace('{' . $k . '}', (string) $v, $texto);
    }
    return $texto;
}

function precioAviso(float $v): string
{
    $decimales = $v >= 1000 ? 0 : ($v >= 1 ? 2 : ($v >= 0.01 ? 4 : 8));
    return '$' . number_format($v, $decimales, '.', ',');
}

function revisarAvisos(): array
{
    $pdo = db();
    $suscripciones = $pdo->query('SELECT id, usuario_id, endpoint, p256dh, auth, idioma, umbral FROM push_suscripciones')->fetchAll();
    if (!$suscripciones) {
        return ['usuarios' => 0, 'enviados' => 0]; // nadie las activó: ni siquiera se piden precios
    }
    $porUsuario = [];
    foreach ($suscripciones as $s) {
        $porUsuario[(int) $s['usuario_id']][] = $s;
    }

    $monedas = array_column(obtenerTop100()['datos'], null, 'id');
    $vapid = clavesVapid();
    $guardarRef = $pdo->prepare('INSERT INTO push_avisos (usuario_id, clave, precio_ref, fecha) VALUES (?, ?, ?, ?)' . siExisteActualizar('usuario_id, clave', 'precio_ref'));
    $actualizarFecha = $pdo->prepare('UPDATE push_avisos SET fecha = ? WHERE usuario_id = ? AND clave = ?');
    $leerRefs = $pdo->prepare('SELECT clave, precio_ref, fecha FROM push_avisos WHERE usuario_id = ?');
    $enviados = 0;

    foreach ($porUsuario as $usuarioId => $subs) {
        $idioma = $subs[0]['idioma'];
        $umbral = min(array_map(fn($s) => (int) $s['umbral'], $subs)) / 100;
        $progreso = progresoUsuario($usuarioId);
        $favoritas = json_decode($progreso['favoritos'] ?? '[]', true) ?: [];
        $alertas = json_decode($progreso['alertas'] ?? '[]', true) ?: [];
        $leerRefs->execute([$usuarioId]);
        $refs = [];
        foreach ($leerRefs->fetchAll() as $r) {
            $refs[$r['clave']] = $r;
        }
        $ahora = date('Y-m-d H:i:s');
        $avisos = [];

        // Favoritas que se movieron más que el umbral.
        foreach (array_unique(array_filter($favoritas, 'is_string')) as $idMoneda) {
            $m = $monedas[$idMoneda] ?? null;
            $precio = (float) ($m['current_price'] ?? 0);
            if ($precio <= 0) {
                continue; // fuera del top 100 o sin precio
            }
            $ref = $refs[$idMoneda] ?? null;
            $edad = $ref ? time() - strtotime($ref['fecha']) : PHP_INT_MAX;
            if (!$ref || $edad >= AVISOS_REF_MAX_SEG || (float) $ref['precio_ref'] <= 0) {
                $guardarRef->execute([$usuarioId, $idMoneda, $precio, $ahora]); // punto de partida, sin aviso
                $actualizarFecha->execute([$ahora, $usuarioId, $idMoneda]);
                continue;
            }
            $cambio = $precio / (float) $ref['precio_ref'] - 1;
            if (abs($cambio) < $umbral) {
                continue;
            }
            $nombre = $m['name'] . ' (' . strtoupper($m['symbol']) . ')';
            $pct = ($cambio > 0 ? '+' : '') . number_format($cambio * 100, 1, ',', '') . ' %';
            $hace = $edad >= 3600 ? textoAviso($idioma, 'horas', ['n' => intdiv($edad, 3600)]) : textoAviso($idioma, 'minutos', ['n' => max(1, intdiv($edad, 60))]);
            $avisos[] = [
                'titulo' => textoAviso($idioma, $cambio > 0 ? 'subio' : 'bajo', ['nombre' => $nombre, 'pct' => $pct]),
                'cuerpo' => textoAviso($idioma, 'cuerpo', ['precio' => precioAviso($precio), 'antes' => precioAviso((float) $ref['precio_ref']), 'horas' => $hace]),
                'url' => BASE_URL . '/paginas/moneda.php?id=' . rawurlencode($idMoneda),
                'icono' => $m['image'] ?? null,
                'etiqueta' => 'moneda-' . $idMoneda,
            ];
            $guardarRef->execute([$usuarioId, $idMoneda, $precio, $ahora]);
            $actualizarFecha->execute([$ahora, $usuarioId, $idMoneda]);
        }

        // Alertas de precio cumplidas (cada una se avisa una sola vez).
        foreach ($alertas as $a) {
            if (!is_array($a) || !empty($a['disparada']) || !isset($a['id'], $a['moneda'], $a['precio'], $a['condicion'])) {
                continue;
            }
            $clave = 'alerta:' . $a['id'];
            $m = $monedas[$a['moneda']] ?? null;
            $precio = (float) ($m['current_price'] ?? 0);
            if ($precio <= 0 || isset($refs[$clave])) {
                continue;
            }
            $mayor = $a['condicion'] === 'mayor';
            if ($mayor ? $precio < (float) $a['precio'] : $precio > (float) $a['precio']) {
                continue;
            }
            $avisos[] = [
                'titulo' => textoAviso($idioma, $mayor ? 'alerta_mayor' : 'alerta_menor', ['nombre' => $m['name'], 'precio' => precioAviso($precio)]),
                'cuerpo' => textoAviso($idioma, 'alerta_cuerpo', ['condicion' => textoAviso($idioma, $mayor ? 'mayor' : 'menor'), 'objetivo' => precioAviso((float) $a['precio'])]),
                'url' => BASE_URL . '/paginas/alertas.php',
                'icono' => $m['image'] ?? null,
                'etiqueta' => $clave,
            ];
            $guardarRef->execute([$usuarioId, $clave, $precio, $ahora]);
        }

        if (!$avisos) {
            continue;
        }
        if (count($avisos) > AVISOS_MAX_POR_USUARIO) {
            $avisos = [[
                'titulo' => textoAviso($idioma, 'varias', ['n' => count($avisos)]),
                'cuerpo' => implode(' · ', array_map(fn($a) => $a['titulo'], array_slice($avisos, 0, 4))),
                'url' => BASE_URL . '/paginas/favoritas.php',
                'etiqueta' => 'varias',
            ]];
        }
        foreach ($subs as $s) {
            foreach ($avisos as $aviso) {
                $codigo = enviarPush($s, $aviso, $vapid);
                if ($codigo === 404 || $codigo === 410) {
                    $pdo->prepare('DELETE FROM push_suscripciones WHERE id = ?')->execute([$s['id']]); // ese celular ya no las quiere
                    break;
                }
                if ($codigo >= 200 && $codigo < 300) {
                    $enviados++;
                } else {
                    error_log("avisos push: el servicio respondió $codigo");
                }
            }
        }
    }
    return ['usuarios' => count($porUsuario), 'enviados' => $enviados];
}
