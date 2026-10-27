<?php
/** Admin › Réalisations : projets affichés dans la section « Nos réalisations » de l'accueil. */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_login();

$items = all_realisations();
$errors = [];

function find_index(array $items, string $id): ?int
{
    foreach ($items as $i => $r) if (($r['id'] ?? '') === $id) return $i;
    return null;
}

function remove_uploaded(string $image): void
{
    if ($image !== '' && str_starts_with($image, REAL_UPLOAD_URL . '/')) @unlink(SITE_ROOT . '/' . $image);
}

$editId = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
$isNew = isset($_GET['nouveau']);
$current = null;
if ($editId !== '') {
    $idx = find_index($items, $editId);
    if ($idx === null) { flash('error', 'Réalisation introuvable.'); redirect_to('admin/realisations'); }
    $current = $items[$idx];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = post_str('action', 20);
    $id = post_str('id', 40);
    $idx = $id !== '' ? find_index($items, $id) : null;

    try {
        if (in_array($action, ['up', 'down', 'delete', 'toggle'], true)) {
            if ($idx === null) throw new RuntimeException('Réalisation introuvable.');
            if ($action === 'delete') {
                remove_uploaded($items[$idx]['image'] ?? '');
                array_splice($items, $idx, 1);
                $msg = 'Réalisation supprimée.';
            } elseif ($action === 'toggle') {
                $items[$idx]['visible'] = empty($items[$idx]['visible']);
                $msg = $items[$idx]['visible'] ? 'Réalisation affichée sur le site.' : 'Réalisation masquée.';
            } else {
                $to = $action === 'up' ? $idx - 1 : $idx + 1;
                if ($to >= 0 && $to < count($items)) [$items[$idx], $items[$to]] = [$items[$to], $items[$idx]];
                $msg = 'Ordre mis à jour.';
            }
            save_realisations($items);
            flash('ok', $msg);
            redirect_to('admin/realisations');
        }

        if ($action === 'save') {
            $r = $idx !== null ? $items[$idx] : ['id' => bin2hex(random_bytes(8)), 'image' => '', 'visible' => true];
            $r['titre'] = post_str('titre', 80);
            $r['type'] = post_str('type', 120);
            $r['alt'] = post_str('alt', 160);
            $svc = post_str('service', 40);
            $r['service'] = array_key_exists($svc, SERVICES) ? $svc : '';
            $r['visible'] = !empty($_POST['visible']);
            if ($r['titre'] === '') $errors[] = 'Le nom du client ou du projet est obligatoire.';
            $hasFile = !empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            if (!$hasFile && $r['image'] === '') $errors[] = 'Ajoutez une photo de la réalisation.';
            if (!$errors && $hasFile) {
                $new = save_uploaded_image($_FILES['image'], REAL_UPLOAD_DIR, REAL_UPLOAD_URL);
                remove_uploaded($r['image']);
                $r['image'] = $new;
            }
            if (!$errors) {
                if ($idx !== null) $items[$idx] = $r;
                elseif (post_str('position', 10) === 'debut') array_unshift($items, $r);
                else $items[] = $r;
                save_realisations($items);
                flash('ok', $idx !== null ? 'Réalisation mise à jour sur l’accueil.' : 'Réalisation ajoutée sur l’accueil.');
                redirect_to('admin/realisations');
            }
            $current = $r;
            $isNew = $idx === null;
        }
    } catch (RuntimeException $ex) {
        $errors[] = $ex->getMessage();
    }
}

$visible = count(array_filter($items, fn($r) => !empty($r['visible'])));
admin_header('Réalisations', 'realisations');
foreach ($errors as $err): ?><p class="adm-flash adm-flash--error" role="alert"><?= e($err) ?></p><?php endforeach;

if ($current !== null || $isNew):
    $r = $current ?? ['id' => '', 'titre' => '', 'type' => '', 'alt' => '', 'image' => '', 'service' => '', 'visible' => true];
?>
<p><a href="realisations">← Retour à la liste</a></p>
<form method="post" enctype="multipart/form-data" class="editor-grid">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= e($r['id']) ?>">
  <div class="card">
    <h2><?= $r['id'] === '' ? 'Nouvelle réalisation' : 'Modifier la réalisation' ?></h2>
    <div class="field">
      <label for="titre">Client ou projet <span class="hint">Ex. : Laticrete, Ixina, Adoha.</span></label>
      <input id="titre" name="titre" type="text" required maxlength="80" value="<?= e($r['titre']) ?>">
    </div>
    <div class="field">
      <label for="type">Type de réalisation <span class="hint">Ligne sous le nom. Ex. : Stand · Salon Big Five, Enseigne publicitaire.</span></label>
      <input id="type" name="type" type="text" maxlength="120" value="<?= e($r['type']) ?>">
    </div>
    <div class="field">
      <label for="alt">Description de la photo <span class="hint">Texte alternatif pour Google Images et l’accessibilité. Vide = nom du client.</span></label>
      <input id="alt" name="alt" type="text" maxlength="160" value="<?= e($r['alt']) ?>">
    </div>
    <div class="field">
      <label for="service">Service lié <span class="hint">Le lien « Un projet similaire ? » ouvre le devis avec ce service présélectionné.</span></label>
      <select id="service" name="service">
        <option value="">Aucun (devis général)</option>
        <?php foreach (SERVICES as $k => [$label]): ?>
          <option value="<?= e($k) ?>"<?= $r['service'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <label class="check"><input type="checkbox" name="visible" value="1"<?= !empty($r['visible']) ? ' checked' : '' ?>> Afficher sur le site</label>
  </div>
  <aside>
    <div class="card">
      <h2>Photo</h2>
      <?php if ($r['image']): ?><img class="cover-preview" style="aspect-ratio:3/4" src="../<?= e($r['image']) ?>" alt=""><?php endif; ?>
      <div class="field">
        <label for="image"><?= $r['image'] ? 'Remplacer la photo' : 'Photo' ?> <span class="hint">JPG, PNG ou WebP, 4 Mo maximum. Format portrait conseillé (3:4), environ 720 × 960 px.</span></label>
        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"<?= $r['image'] ? '' : ' required' ?>>
      </div>
      <?php if ($r['id'] === ''): ?>
      <div class="field">
        <label for="position">Position</label>
        <select id="position" name="position"><option value="fin">À la fin</option><option value="debut">En premier</option></select>
      </div>
      <?php endif; ?>
      <div class="actions"><button class="btn btn--dark" type="submit">Enregistrer</button></div>
    </div>
  </aside>
</form>
<?php else: ?>
<div class="toolbar">
  <p style="margin:0"><?= count($items) ?> réalisation(s), <?= $visible ?> affichée(s) dans la section <a href="../#edition-impression" target="_blank" rel="noopener">Nos réalisations ↗</a> de l’accueil.</p>
  <a class="btn" href="realisations?nouveau">+ Nouvelle réalisation</a>
</div>
<div class="card table-wrap">
<?php if (!$items): ?>
  <p>Aucune réalisation. Cliquez sur « Nouvelle réalisation » pour en ajouter une.</p>
<?php else: ?>
  <table>
    <thead><tr><th>Photo</th><th>Client / type</th><th>Statut</th><th>Ordre</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($items as $i => $r): ?>
      <tr>
        <td><img class="thumb" src="../<?= e($r['image']) ?>" alt="" style="width:54px;height:72px"></td>
        <td><a href="realisations?id=<?= e($r['id']) ?>"><b><?= e($r['titre']) ?></b></a><br><small><?= e($r['type']) ?><?= !empty($r['service']) ? ' · ' . e(SERVICES[$r['service']][0] ?? '') : '' ?></small></td>
        <td><span class="badge <?= !empty($r['visible']) ? 'badge--pub' : 'badge--draft' ?>"><?= !empty($r['visible']) ? 'Affichée' : 'Masquée' ?></span></td>
        <td>
          <div class="actions">
            <?php foreach (['up' => ['↑', 'Monter', $i > 0], 'down' => ['↓', 'Descendre', $i < count($items) - 1]] as $act => [$sym, $lbl, $ok]): if (!$ok) continue; ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $act ?>"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="btn btn--small btn--ghost" type="submit" title="<?= $lbl ?>" aria-label="<?= $lbl ?>"><?= $sym ?></button></form>
            <?php endforeach; ?>
          </div>
        </td>
        <td>
          <div class="actions">
            <a class="btn btn--small btn--ghost" href="realisations?id=<?= e($r['id']) ?>">Modifier</a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="btn btn--small btn--ghost" type="submit"><?= !empty($r['visible']) ? 'Masquer' : 'Afficher' ?></button></form>
            <form method="post" onsubmit="return confirm('Supprimer définitivement cette réalisation ?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="btn btn--small btn--danger" type="submit">Supprimer</button></form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="hint" style="margin-top:14px">Les réalisations s’affichent dans cet ordre, réparties sur 4 colonnes décalées. Un nombre pair (8, 12…) donne le meilleur rendu.</p>
<?php endif; ?>
</div>
<?php endif;
admin_footer();
