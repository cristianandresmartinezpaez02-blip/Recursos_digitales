<?php
/* Panel «Avisos en tu celular» (notificaciones push). Lo usan paginas/favoritas.php y
   paginas/alertas.php; la lógica está en assets/js/push.js y el envío en api/avisos.php. */
?>
<section class="panel panel-push" id="panel-push">
    <div class="panel-cabecera">
        <h2>🔔 <?= e(t('push.titulo')) ?></h2>
        <span class="chip" id="push-estado"><?= e(t('push.estado_apagado')) ?></span>
    </div>
    <p class="texto-2"><?= e(t('push.explicacion')) ?></p>
    <div class="form-linea">
        <label class="campo-form"><?= e(t('push.umbral')) ?>
            <select id="push-umbral">
                <?php foreach ([2, 3, 5, 10, 15] as $pct): ?>
                    <option value="<?= $pct ?>" <?= $pct === 5 ? 'selected' : '' ?>><?= e(t('push.umbral_op', ['pct' => $pct])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="boton" type="button" id="push-activar">🔔 <?= e(t('push.activar')) ?></button>
        <button class="boton secundario" type="button" id="push-probar" hidden>📨 <?= e(t('push.probar')) ?></button>
        <button class="boton secundario" type="button" id="push-desactivar" hidden>🔕 <?= e(t('push.desactivar')) ?></button>
    </div>
    <p class="nota" id="push-mensaje" role="status"></p>
    <p class="nota" id="push-ios" hidden>📱 <?= e(t('push.ios')) ?></p>
</section>
