<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_login();

$id = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
$article = $id !== '' ? find_article($id) : null;
if ($id !== '' && !$article) { flash('error', 'Article introuvable.'); header('Location: articles.php'); exit; }
$isNew = $article === null;
$a = $article ?? [
    'id' => '', 'titre' => '', 'slug' => '', 'extrait' => '', 'contenu' => '', 'image' => '', 'image_alt' => '',
    'statut' => 'brouillon', 'date' => date('Y-m-d'), 'service' => '',
    'meta_title' => '', 'meta_description' => '', 'noindex' => false,
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a['titre']   = post_str('titre', 160);
    $a['extrait'] = post_str('extrait', 400);
    $a['contenu'] = post_str('contenu', 60000);
    $a['image_alt'] = post_str('image_alt', 160);
    $a['statut']  = ($_POST['statut'] ?? '') === 'publie' ? 'publie' : 'brouillon';
    $date = post_str('date', 10);
    $a['date']    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
    $svc = post_str('service', 40);
    $a['service'] = array_key_exists($svc, SERVICES) ? $svc : '';
    $a['meta_title'] = post_str('meta_title', 120);
    $a['meta_description'] = post_str('meta_description', 320);
    $a['noindex'] = !empty($_POST['noindex']);
    $slugIn = post_str('slug', 90);
    $a['slug'] = unique_slug(slugify($slugIn !== '' ? $slugIn : $a['titre']), $a['id']);

    if ($a['titre'] === '') $errors[] = 'Le titre est obligatoire.';
    if ($a['contenu'] === '') $errors[] = 'Le contenu est obligatoire.';

    if (!empty($_POST['remove_image']) && $a['image']) {
        if (str_starts_with($a['image'], UPLOAD_URL . '/')) @unlink(SITE_ROOT . '/' . $a['image']);
        $a['image'] = '';
    }
    if (!$errors && !empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $new = save_uploaded_image($_FILES['image']);
            if ($a['image'] && str_starts_with($a['image'], UPLOAD_URL . '/')) @unlink(SITE_ROOT . '/' . $a['image']);
            $a['image'] = $new;
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $list = read_json('articles', []);
        $a['updated'] = date('c');
        if ($isNew) {
            $a['id'] = bin2hex(random_bytes(8));
            $a['created'] = date('c');
            $list[] = $a;
        } else {
            foreach ($list as &$row) if (($row['id'] ?? '') === $a['id']) $row = $a;
            unset($row);
        }
        write_json('articles', $list);
        flash('ok', $a['statut'] === 'publie' ? 'Article enregistré et publié.' : 'Brouillon enregistré.');
        header('Location: article.php?id=' . urlencode($a['id']));
        exit;
    }
}

admin_header($isNew ? 'Nouvel article' : 'Modifier l’article', 'articles');
foreach ($errors as $err): ?><p class="adm-flash adm-flash--error" role="alert"><?= e($err) ?></p><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="editor-grid">
  <?= csrf_field() ?>
  <div>
    <div class="card">
      <div class="field">
        <label for="titre">Titre de l’article</label>
        <input id="titre" name="titre" type="text" required maxlength="160" value="<?= e($a['titre']) ?>">
      </div>
      <div class="field">
        <label for="extrait">Résumé <span class="hint">Affiché dans la liste des articles (2 ou 3 phrases).</span></label>
        <textarea id="extrait" name="extrait" maxlength="400" rows="3"><?= e($a['extrait']) ?></textarea>
      </div>
      <div class="field">
        <label for="contenu">Contenu
          <span class="hint">Mise en forme : <code>## Titre</code>, <code>### Sous-titre</code>, <code>- élément de liste</code>, <code>**gras**</code>, <code>*italique*</code>, <code>[texte du lien](https://…)</code>. Laissez une ligne vide entre deux paragraphes.</span>
        </label>
        <textarea id="contenu" name="contenu" class="big" required><?= e($a['contenu']) ?></textarea>
      </div>
    </div>

    <div class="card">
      <h2>Métadonnées (SEO)</h2>
      <div class="field">
        <label for="meta_title">Titre SEO <span class="counter" data-for="meta_title" data-max="60"></span>
          <span class="hint">Titre affiché dans Google et l’onglet du navigateur. Vide = titre de l’article.</span></label>
        <input id="meta_title" name="meta_title" type="text" maxlength="120" value="<?= e($a['meta_title']) ?>">
      </div>
      <div class="field">
        <label for="meta_description">Méta-description <span class="counter" data-for="meta_description" data-max="160"></span>
          <span class="hint">Texte sous le titre dans Google (idéalement 120 à 160 caractères). Vide = résumé.</span></label>
        <textarea id="meta_description" name="meta_description" maxlength="320" rows="3"><?= e($a['meta_description']) ?></textarea>
      </div>
      <div class="field">
        <label for="slug">Adresse de la page (slug) <span class="hint">Vide = générée à partir du titre. Lettres, chiffres et tirets.</span></label>
        <input id="slug" name="slug" type="text" maxlength="90" value="<?= e($a['slug']) ?>">
      </div>
      <label class="check"><input type="checkbox" name="noindex" value="1"<?= !empty($a['noindex']) ? ' checked' : '' ?>> Ne pas indexer cet article dans Google</label>
      <p class="hint" style="margin-top:16px">Aperçu Google :</p>
      <div class="serp" aria-hidden="true">
        <div class="serp__url">alloprint.ma › article › <span id="serp-slug"><?= e($a['slug'] ?: '…') ?></span></div>
        <div class="serp__title" id="serp-title"></div>
        <div class="serp__desc" id="serp-desc"></div>
      </div>
    </div>
  </div>

  <aside>
    <div class="card">
      <h2>Publication</h2>
      <div class="field">
        <label for="statut">Statut</label>
        <select id="statut" name="statut">
          <option value="brouillon"<?= $a['statut'] !== 'publie' ? ' selected' : '' ?>>Brouillon (non visible)</option>
          <option value="publie"<?= $a['statut'] === 'publie' ? ' selected' : '' ?>>Publié</option>
        </select>
      </div>
      <div class="field">
        <label for="date">Date</label>
        <input id="date" name="date" type="date" value="<?= e($a['date']) ?>">
      </div>
      <div class="field">
        <label for="service">Service lié <span class="hint">Ajoute un lien vers ce service en fin d’article.</span></label>
        <select id="service" name="service">
          <option value="">Aucun</option>
          <?php foreach (SERVICES as $k => [$label]): ?>
            <option value="<?= e($k) ?>"<?= $a['service'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="actions">
        <button class="btn btn--dark" type="submit">Enregistrer</button>
        <?php if (!$isNew && $a['statut'] === 'publie'): ?><a class="btn btn--ghost" href="../article.php?slug=<?= e($a['slug']) ?>" target="_blank" rel="noopener">Voir ↗</a><?php endif; ?>
      </div>
    </div>
    <div class="card">
      <h2>Image de couverture</h2>
      <?php if ($a['image']): ?>
        <img class="cover-preview" src="../<?= e($a['image']) ?>" alt="">
        <label class="check" style="margin-bottom:12px"><input type="checkbox" name="remove_image" value="1"> Retirer l’image</label>
      <?php endif; ?>
      <div class="field">
        <label for="image"><?= $a['image'] ? 'Remplacer l’image' : 'Ajouter une image' ?> <span class="hint">JPG, PNG ou WebP, 4 Mo max. Format paysage conseillé (1200 × 630). Sert aussi d’image de partage.</span></label>
        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
      </div>
      <div class="field">
        <label for="image_alt">Texte alternatif <span class="hint">Décrit l’image (accessibilité et SEO).</span></label>
        <input id="image_alt" name="image_alt" type="text" maxlength="160" value="<?= e($a['image_alt']) ?>">
      </div>
    </div>
  </aside>
</form>
<script>
(() => {
  const $ = id => document.getElementById(id);
  const upd = () => {
    const t = $('meta_title').value.trim() || $('titre').value.trim() || 'Titre de l’article';
    const d = $('meta_description').value.trim() || $('extrait').value.trim() || 'Méta-description de l’article…';
    $('serp-title').textContent = t + ' — AlloPrint';
    $('serp-desc').textContent = d.length > 160 ? d.slice(0, 157) + '…' : d;
    document.querySelectorAll('.counter').forEach(c => {
      const n = $(c.dataset.for).value.length, max = +c.dataset.max;
      c.textContent = n + ' / ' + max; c.classList.toggle('is-over', n > max);
    });
  };
  ['titre','extrait','meta_title','meta_description'].forEach(id => $(id).addEventListener('input', upd));
  upd();
})();
</script>
<?php admin_footer();
