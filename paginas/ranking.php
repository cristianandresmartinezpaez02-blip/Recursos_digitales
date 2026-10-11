<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.ranking');
$activa = 'ranking';
require __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/api.php';

// Ve los nombres de otros jugadores: solo con sesión, aunque el panel de administración la haya dejado abierta.
if (!$usuario) {
    require __DIR__ . '/../includes/bloqueo.php';
    $scripts = [url('assets/js/app.js')];
    require __DIR__ . '/../includes/footer.php';
    exit;
}

/** "cristian andres" -> "Cristian A." (no se muestra el nombre completo de nadie). */
function nombreCorto(string $nombre): string
{
    $partes = preg_split('/\s+/', trim(mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8')));
    return $partes[0] . (isset($partes[1]) ? ' ' . mb_substr($partes[1], 0, 1) . '.' : '');
}

// Precio actual de cada moneda del top 100 (si la API falla, se usa el último precio guardado de cada posición).
$precios = [];
try {
    foreach (obtenerTop100()['datos'] as $m) {
        $precios[$m['id']] = (float) $m['current_price'];
    }
} catch (Throwable $e) {
    error_log('ranking: ' . $e->getMessage());
}

$trading = $reto = $subebaja = [];
$filas = db()->query("SELECT p.usuario_id, p.clave, p.valor, u.nombre FROM progreso p
    JOIN usuarios u ON u.id = p.usuario_id
    WHERE u.activo = 1 AND p.clave IN ('minitrading_v1', 'reto_records', 'subebaja_record')")->fetchAll();

foreach ($filas as $f) {
    $dato = json_decode($f['valor'], true);
    $jugador = ['id' => (int) $f['usuario_id'], 'nombre' => nombreCorto($f['nombre'])];

    if ($f['clave'] === 'minitrading_v1' && is_array($dato) && is_numeric($dato['efectivo'] ?? null) && !empty($dato['operaciones'])) {
        $total = (float) $dato['efectivo'];
        foreach ((array) ($dato['ordenes'] ?? []) as $o) {
            $total += (float) ($o['monto'] ?? 0); // dinero reservado en órdenes límite
        }
        foreach ((array) ($dato['posiciones'] ?? []) as $id => $p) {
            $cantidad = (float) ($p['cantidad'] ?? 0);
            $precio = $precios[$id] ?? $p['ultimo']['precio'] ?? ($cantidad > 0 ? (float) ($p['costo'] ?? 0) / $cantidad : 0);
            $total += $cantidad * (float) $precio;
        }
        $trading[] = $jugador + ['valor' => $total, 'pct' => ($total / JUEGO_SALDO_INICIAL - 1) * 100, 'ops' => count($dato['operaciones'])];
    } elseif ($f['clave'] === 'reto_records' && is_array($dato) && $dato) {
        $mejor = max(array_map(fn($r) => (float) ($r['ganancia'] ?? -INF), $dato));
        if (is_finite($mejor)) {
            $reto[] = $jugador + ['valor' => $mejor, 'partidas' => count($dato)];
        }
    } elseif ($f['clave'] === 'subebaja_record' && is_numeric($dato) && $dato > 0) {
        $subebaja[] = $jugador + ['valor' => (int) $dato];
    }
}
foreach ([&$trading, &$reto, &$subebaja] as &$tabla) {
    usort($tabla, fn($a, $b) => $b['valor'] <=> $a['valor']);
}
unset($tabla);

$yo = (int) $usuario['id'];
$medalla = fn(int $i) => ['🥇', '🥈', '🥉'][$i] ?? '#' . ($i + 1);

/** Pinta una tabla de posiciones (top 20 + el puesto del usuario si quedó fuera). */
function tablaRanking(array $lista, int $yo, callable $medalla, array $columnas, string $urlJuego): void
{
    $puesto = array_search($yo, array_column($lista, 'id'), true);
    if (!$lista) {
        echo '<p class="texto-3">' . e(t('rank.vacio')) . ' <a href="' . e($urlJuego) . '">' . e(t('rank.jugar')) . ' →</a></p>';
        return;
    }
    echo '<p class="nota" style="margin-top:0">' . e($puesto === false ? t('rank.sin_jugar') : t('rank.tu_puesto', ['n' => $puesto + 1, 'total' => count($lista)])) . '</p>';
    echo '<div class="tabla-scroll"><table class="tabla compacta ranking"><thead><tr><th></th><th>' . e(t('rank.jugador')) . '</th>';
    foreach ($columnas as [$titulo]) {
        echo '<th class="num">' . e($titulo) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($lista as $i => $j) {
        if ($i >= 20 && $j['id'] !== $yo) {
            continue;
        }
        $propia = $j['id'] === $yo;
        echo '<tr' . ($propia ? ' class="fila-propia"' : '') . '><td class="puesto">' . $medalla($i) . '</td><td><strong>' . e($j['nombre']) . '</strong>'
            . ($propia ? ' <small class="texto-3">(' . e(t('rank.tu')) . ')</small>' : '') . '</td>';
        foreach ($columnas as [, $celda]) {
            echo '<td class="num">' . $celda($j) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">🏅 <?= e(t('nav.juegos')) ?></div>
        <h1><?= e(t('nav.ranking')) ?></h1>
        <p><?= e(t('rank.intro')) ?></p>
    </div>
</section>

<section class="rejilla col-3 ranking-rejilla">
    <div class="panel">
        <div class="panel-cabecera"><h2>💹 <?= e(t('rank.trading')) ?></h2><a href="<?= url('paginas/trading.php') ?>"><?= e(t('rank.jugar')) ?> →</a></div>
        <?php tablaRanking($trading, $yo, $medalla, [
            [t('rank.patrimonio'), fn($j) => '<strong>' . fmt($j['valor'], 'dinero') . '</strong>'],
            [t('rank.ganancia'), fn($j) => fmt($j['pct'], 'porcentaje')],
            [t('rank.ops'), fn($j) => (int) $j['ops']],
        ], url('paginas/trading.php')); ?>
    </div>
    <div class="panel">
        <div class="panel-cabecera"><h2>⏩ <?= e(t('rank.reto')) ?></h2><a href="<?= url('paginas/reto.php') ?>"><?= e(t('rank.jugar')) ?> →</a></div>
        <?php tablaRanking($reto, $yo, $medalla, [
            [t('rank.resultado'), fn($j) => fmt($j['valor'], 'porcentaje')],
        ], url('paginas/reto.php')); ?>
    </div>
    <div class="panel">
        <div class="panel-cabecera"><h2>🎯 <?= e(t('rank.subebaja')) ?></h2><a href="<?= url('paginas/sube-baja.php') ?>"><?= e(t('rank.jugar')) ?> →</a></div>
        <?php tablaRanking($subebaja, $yo, $medalla, [
            [t('rank.puntos'), fn($j) => '<strong>' . (int) $j['valor'] . '</strong>'],
        ], url('paginas/sube-baja.php')); ?>
    </div>
</section>

<div class="explicacion"><span class="ico">ℹ️</span><p><?= e(t('rank.nota')) ?></p></div>

<?php
$scripts = [url('assets/js/app.js')];
require __DIR__ . '/../includes/footer.php';
