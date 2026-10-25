<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_login();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $in = is_array($_POST['p'] ?? null) ? $_POST['p'] : [];
    $save = [];
    foreach (SERVICES as $key => [$label]) {
        $row = is_array($in[$key] ?? null) ? $in[$key] : [];
        $raw = trim(str_replace([' ', "\u{00a0}", ','], ['', '', '.'], (string)($row['prix'] ?? '')));
        if ($raw !== '' && (!is_numeric($raw) || (float)$raw < 0)) {
            $errors[] = "Prix invalide pour « $label ».";
            $raw = '';
        }
        $mention = (string)($row['mention'] ?? 'À partir de');
        $save[$key] = [
            'prix'    => $raw === '' ? '' : round((float)$raw, 2),
            'mention' => in_array($mention, ['À partir de', 'Prix fixe', 'Environ'], true) ? $mention : 'À partir de',
            'unite'   => mb_substr(trim((string)($row['unite'] ?? '')), 0, 40),
            'taxe'    => ($row['taxe'] ?? 'HT') === 'TTC' ? 'TTC' : 'HT',
            'note'    => mb_substr(trim((string)($row['note'] ?? '')), 0, 200),
            'visible' => !empty($row['visible']) && $raw !== '',
        ];
    }
    write_json('prix', $save);
    flash($errors ? 'error' : 'ok', $errors ? implode(' ', $errors) . ' Les autres prix ont été enregistrés.' : 'Prix enregistrés. Ils sont visibles sur le site immédiatement.');
    redirect_to('admin/prix');
}

$prices = all_prices();
admin_header('Prix des services', 'prix');
?>
<p>Définissez un prix indicatif pour chaque service. Il s’affiche dans l’encadré « Votre projet, votre budget » de la page du service et sur la page Services. Décochez « Afficher » pour le masquer sans l’effacer.</p>
<form method="post">
  <?= csrf_field() ?>
  <div class="price-grid">
  <?php foreach ($prices as $key => $p): $k = e($key); ?>
    <fieldset class="price-row" style="margin:0">
      <div>
        <h3><?= e($p['label']) ?></h3>
        <a class="preview" href="../<?= e(str_replace('.html', '', $p['page'])) ?>" target="_blank" rel="noopener">Voir la page ↗</a>
      </div>
      <div class="field">
        <label for="m-<?= $k ?>">Mention</label>
        <select id="m-<?= $k ?>" name="p[<?= $k ?>][mention]">
          <?php foreach (['À partir de', 'Prix fixe', 'Environ'] as $m): ?>
            <option<?= $p['mention'] === $m ? ' selected' : '' ?>><?= e($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="p-<?= $k ?>">Prix (MAD)</label>
        <input id="p-<?= $k ?>" name="p[<?= $k ?>][prix]" type="text" inputmode="decimal" placeholder="ex. 250" value="<?= $p['prix'] !== null ? e((string)$p['prix']) : '' ?>">
      </div>
      <div class="field">
        <label for="u-<?= $k ?>">Unité</label>
        <input id="u-<?= $k ?>" name="p[<?= $k ?>][unite]" type="text" maxlength="40" placeholder="ex. le m², les 100 cartes" value="<?= e($p['unite']) ?>">
      </div>
      <div class="field">
        <label for="t-<?= $k ?>">Taxe</label>
        <select id="t-<?= $k ?>" name="p[<?= $k ?>][taxe]">
          <option<?= $p['taxe'] === 'HT' ? ' selected' : '' ?>>HT</option>
          <option<?= $p['taxe'] === 'TTC' ? ' selected' : '' ?>>TTC</option>
        </select>
      </div>
      <label class="check"><input type="checkbox" name="p[<?= $k ?>][visible]" value="1"<?= $p['visible'] ? ' checked' : '' ?>> Afficher</label>
      <div class="field note">
        <label for="n-<?= $k ?>">Précision <span class="hint">Optionnel, ex. « Pose non comprise » ou « Délai 48 h ».</span></label>
        <input id="n-<?= $k ?>" name="p[<?= $k ?>][note]" type="text" maxlength="200" value="<?= e($p['note']) ?>">
      </div>
    </fieldset>
  <?php endforeach; ?>
  </div>
  <p style="margin-top:20px"><button class="btn btn--dark" type="submit">Enregistrer les prix</button></p>
</form>
<?php admin_footer();
