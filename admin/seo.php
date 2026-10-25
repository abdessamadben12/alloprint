<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_login();

$pages = seo_pages();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $page = post_str('page', 80);
    $title = post_str('title', 120);
    $desc = post_str('description', 320);
    try {
        if ($title === '' || $desc === '') throw new RuntimeException('Le titre et la description sont obligatoires.');
        write_page_meta($page, $title, $desc);
        flash('ok', 'Métadonnées de « ' . ($pages[$page] ?? $page) . ' » enregistrées.');
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect_to('admin/seo#' . rawurlencode($page));
}

admin_header('Référencement (SEO)', 'seo');
?>
<p>Modifiez le titre et la description de chaque page, tels qu’ils apparaissent dans Google et lors d’un partage sur les réseaux sociaux. Visez environ 60 caractères pour le titre et 120 à 160 pour la description. Les métadonnées des articles se règlent dans chaque article.</p>
<?php foreach ($pages as $file => $label): $m = read_page_meta($file); $id = e($file); ?>
<form method="post" class="card" id="<?= $id ?>">
  <?= csrf_field() ?><input type="hidden" name="page" value="<?= $id ?>">
  <h2><?= e($label) ?> <small style="font-weight:400;color:#5b6b72">— <?= $id ?></small></h2>
  <div class="field">
    <label for="t-<?= $id ?>">Titre <span class="counter" data-for="t-<?= $id ?>" data-max="60"></span></label>
    <input id="t-<?= $id ?>" name="title" type="text" maxlength="120" required value="<?= e($m['title']) ?>">
  </div>
  <div class="field">
    <label for="d-<?= $id ?>">Méta-description <span class="counter" data-for="d-<?= $id ?>" data-max="160"></span></label>
    <textarea id="d-<?= $id ?>" name="description" maxlength="320" rows="2" required><?= e($m['description']) ?></textarea>
  </div>
  <button class="btn btn--small btn--dark" type="submit">Enregistrer</button>
</form>
<?php endforeach; ?>
<script>
document.querySelectorAll('.counter').forEach(c => {
  const f = document.getElementById(c.dataset.for), max = +c.dataset.max;
  const u = () => { c.textContent = f.value.length + ' / ' + max; c.classList.toggle('is-over', f.value.length > max); };
  f.addEventListener('input', u); u();
});
</script>
<?php admin_footer();
