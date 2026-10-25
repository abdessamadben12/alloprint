<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    check_csrf();
    $id = post_str('id', 40);
    $list = read_json('articles', []);
    $kept = [];
    foreach ($list as $a) {
        if (($a['id'] ?? '') === $id) {
            $img = $a['image'] ?? '';
            if ($img && str_starts_with($img, UPLOAD_URL . '/')) @unlink(SITE_ROOT . '/' . $img);
            continue;
        }
        $kept[] = $a;
    }
    write_json('articles', $kept);
    flash('ok', 'Article supprimé.');
    redirect_to('admin/articles');
}

$articles = all_articles();
admin_header('Articles', 'articles');
?>
<div class="toolbar">
  <p style="margin:0"><?= count($articles) ?> article(s). Les articles publiés apparaissent sur la page <a href="../articles" target="_blank" rel="noopener">Articles ↗</a>.</p>
  <a class="btn" href="article">+ Nouvel article</a>
</div>
<div class="card table-wrap">
<?php if (!$articles): ?>
  <p>Aucun article pour l’instant. Cliquez sur « Nouvel article » pour commencer.</p>
<?php else: ?>
  <table>
    <thead><tr><th>Image</th><th>Titre</th><th>Statut</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($articles as $a): $pub = ($a['statut'] ?? '') === 'publie'; ?>
      <tr>
        <td><?php if (!empty($a['image'])): ?><img class="thumb" src="../<?= e($a['image']) ?>" alt=""><?php else: ?><span class="thumb"></span><?php endif; ?></td>
        <td><a href="article?id=<?= e($a['id']) ?>"><b><?= e($a['titre']) ?></b></a><br><small>/article/<?= e($a['slug']) ?></small></td>
        <td><span class="badge <?= $pub ? 'badge--pub' : 'badge--draft' ?>"><?= $pub ? 'Publié' : 'Brouillon' ?></span></td>
        <td><?= e(format_date_fr($a['date'] ?? '')) ?></td>
        <td>
          <div class="actions">
            <a class="btn btn--small btn--ghost" href="article?id=<?= e($a['id']) ?>">Modifier</a>
            <?php if ($pub): ?><a class="btn btn--small btn--ghost" href="../article/<?= e($a['slug']) ?>" target="_blank" rel="noopener">Voir ↗</a><?php endif; ?>
            <form method="post" onsubmit="return confirm('Supprimer définitivement cet article ?')">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($a['id']) ?>">
              <button class="btn btn--small btn--danger" type="submit">Supprimer</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
<?php admin_footer();
