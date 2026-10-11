<?php
/**
 * Pantalla que ve un visitante sin sesión al abrir una sección de CLAVES_PROTEGIDAS.
 * La incluye header.php; usa $menu, $activa y $urlAcceso.
 */
$seccion = null;
foreach ($menu as $grupo) {
    foreach ($grupo['items'] as $item) {
        if ($item[0] === $activa) {
            $seccion = $item;
        }
    }
}
[, , $icono, $tituloSeccion, $descSeccion] = $seccion ?? [null, null, '🔒', 'cuenta.solo_miembros', 'cuenta.bloqueo_d'];
$urlRegistro = url('paginas/acceso.php') . '?modo=registro&volver=' . rawurlencode($urlActual);
$beneficios = [
    ['🎮', 'cuenta.ben_juegos'],
    ['🧰', 'cuenta.ben_herramientas'],
    ['☁️', 'cuenta.ben_progreso'],
    ['🙂', 'cuenta.ben_facial'],
];
?>
<section class="bloqueo">
    <div class="bloqueo-vista" aria-hidden="true">
        <span class="bloqueo-icono"><?= $icono ?></span>
        <span class="bloqueo-candado">🔒</span>
    </div>
    <div class="etiqueta-seccion">🔒 <?= e(t('cuenta.solo_miembros')) ?></div>
    <h1><?= e(t($tituloSeccion)) ?></h1>
    <p class="texto-2"><?= e(t($descSeccion)) ?></p>
    <p><?= e(t('cuenta.bloqueo_d')) ?></p>
    <ul class="beneficios">
        <?php foreach ($beneficios as [$ico, $clave]): ?>
            <li><span aria-hidden="true"><?= $ico ?></span> <?= e(t($clave)) ?></li>
        <?php endforeach; ?>
    </ul>
    <div class="hero-acciones" style="justify-content:center">
        <a class="boton" href="<?= e($urlRegistro) ?>">✨ <?= e(t('cuenta.crear_gratis')) ?></a>
        <a class="boton secundario" href="<?= e($urlAcceso) ?>">👤 <?= e(t('cuenta.ya_tengo')) ?></a>
    </div>
    <p class="nota"><a href="<?= url('paginas/estrategias.php') ?>">🧠 <?= e(t('cuenta.bloqueo_guia')) ?> →</a></p>
</section>
