<?php
require_once __DIR__ . '/../includes/i18n.php';
$titulo = t('nav.proyecto');
$activa = 'proyecto';
require __DIR__ . '/../includes/header.php';

// Cifras en vivo de la base de datos (solo totales: nunca datos de una persona).
$cifras = ['usuarios' => null, 'favoritas' => null, 'partidas' => null, 'avisos' => null];
try {
    $pdo = db();
    $cifras['usuarios'] = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE activo = 1')->fetchColumn();
    $cifras['favoritas'] = (int) $pdo->query("SELECT COUNT(*) FROM progreso WHERE clave = 'favoritos' AND valor <> '[]'")->fetchColumn();
    $cifras['partidas'] = (int) $pdo->query("SELECT COUNT(*) FROM progreso WHERE clave IN ('minitrading_v1', 'reto_records', 'subebaja_stats')")->fetchColumn();
    $cifras['avisos'] = (int) $pdo->query('SELECT COUNT(DISTINCT usuario_id) FROM push_suscripciones')->fetchColumn();
} catch (Throwable $e) {
    error_log('proyecto: ' . $e->getMessage());
}
$secciones = array_sum(array_map(fn($g) => count($g['items']), $menu)) + 3; // + Aprende, ¿Es estafa? y este proyecto

$tecnologias = ['PHP 8.3', 'JavaScript', 'PostgreSQL (Supabase)', 'SQLite', 'Docker', 'Render', 'Binance API', 'CoinGecko API', 'CoinPaprika API',
    'Web Push (VAPID)', 'PWA', 'face-api.js', 'Google Sign-In', 'Claude (IA)', 'Chart.js'];
$destacadas = [
    ['paginas/mercado.php', '🏆', 'proyecto.f_mercado'],
    ['paginas/escaner.php', '🔎', 'proyecto.f_analisis'],
    ['paginas/trading.php', '💹', 'proyecto.f_juegos'],
    ['paginas/estafas.php', '🛡️', 'proyecto.f_estafas'],
    ['paginas/favoritas.php', '🔔', 'proyecto.f_avisos'],
    ['paginas/acceso.php', '🙂', 'proyecto.f_facial'],
    ['paginas/aprende.php', '📚', 'proyecto.f_aprende'],
    ['index.php', '♿', 'proyecto.f_accesible'],
];
?>

<section class="hero">
    <div>
        <div class="etiqueta-seccion">💡 <?= e(t('nav.proyecto')) ?></div>
        <h1><?= e(t('proyecto.titulo_a')) ?> <span class="titulo-gradiente"><?= e(t('proyecto.titulo_b')) ?></span></h1>
        <p><?= e(t('proyecto.intro')) ?></p>
    </div>
</section>

<section class="panel">
    <h2>📊 <?= e(t('proyecto.cifras')) ?></h2>
    <div class="cifras">
        <div class="cifra"><strong>100</strong><span><?= e(t('proyecto.c_monedas')) ?></span></div>
        <div class="cifra"><strong><?= $secciones ?></strong><span><?= e(t('proyecto.c_secciones')) ?></span></div>
        <div class="cifra"><strong>3</strong><span><?= e(t('proyecto.c_idiomas')) ?></span></div>
        <?php foreach (['usuarios', 'favoritas', 'partidas', 'avisos'] as $c): if (!$cifras[$c]) continue; // un 0 no aporta nada ?>
            <div class="cifra"><strong><?= number_format($cifras[$c], 0, ',', '.') ?></strong><span><?= e(t("proyecto.c_$c")) ?></span></div>
        <?php endforeach; ?>
    </div>
    <p class="nota"><?= e(t('proyecto.cifras_nota')) ?></p>
</section>

<div class="problema-solucion">
    <section class="panel">
        <h2>❓ <?= e(t('proyecto.problema')) ?></h2>
        <ul><?php for ($i = 1; $i <= 4; $i++): ?><li><?= e(t("proyecto.problema$i")) ?></li><?php endfor; ?></ul>
    </section>
    <section class="panel">
        <h2>✅ <?= e(t('proyecto.solucion')) ?></h2>
        <ul><?php for ($i = 1; $i <= 4; $i++): ?><li><?= e(t("proyecto.solucion$i")) ?></li><?php endfor; ?></ul>
    </section>
</div>

<section class="panel">
    <h2>⭐ <?= e(t('proyecto.funciones')) ?></h2>
    <div class="funciones">
        <?php foreach ($destacadas as [$ruta, $ico, $clave]): ?>
            <a class="tarjeta-guia" href="<?= url($ruta) ?>">
                <span class="ico-grande" aria-hidden="true"><?= $ico ?></span>
                <span><strong><?= e(t($clave)) ?></strong><small><?= e(t($clave . '_d')) ?></small></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <h2>🛠️ <?= e(t('proyecto.tecnologias')) ?></h2>
    <div class="chips"><?php foreach ($tecnologias as $tec): ?><span class="chip"><?= e($tec) ?></span><?php endforeach; ?></div>
    <div class="pasos" style="margin-top:1.2rem">
        <?php for ($i = 1; $i <= 4; $i++): ?>
            <div class="paso"><div><strong><?= e(t("proyecto.arq$i")) ?></strong><p><?= e(t("proyecto.arq{$i}_d")) ?></p></div></div>
        <?php endfor; ?>
    </div>
</section>

<section class="panel">
    <h2>👤 <?= e(t('proyecto.autor')) ?></h2>
    <p><strong>Cristian Andrés Martínez Páez</strong></p>
    <p class="texto-2"><?= e(t('proyecto.autor_d')) ?></p>
    <p class="nota"><?= e(t('pie.aviso')) ?></p>
</section>

<?php
$scripts = [url('assets/js/app.js')];
require __DIR__ . '/../includes/footer.php';
