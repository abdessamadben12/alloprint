<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_login();

$articles = all_articles();
$pub = count(array_filter($articles, fn($a) => ($a['statut'] ?? '') === 'publie'));
$prices = all_prices();
$withPrice = count(array_filter($prices, fn($p) => $p['prix'] !== null && $p['visible']));

admin_header('Tableau de bord', 'dashboard');
?>
<div class="stats">
  <div class="stat"><b><?= $pub ?></b><span>article(s) publié(s)</span></div>
  <div class="stat"><b><?= count($articles) - $pub ?></b><span>brouillon(s)</span></div>
  <div class="stat"><b><?= $withPrice ?> / <?= count($prices) ?></b><span>services avec un prix affiché</span></div>
</div>
<div class="card">
  <h2>Actions rapides</h2>
  <div class="actions">
    <a class="btn" href="article">+ Nouvel article</a>
    <a class="btn btn--ghost" href="realisations">Modifier les réalisations</a>
    <a class="btn btn--ghost" href="prix">Modifier les prix</a>
    <a class="btn btn--ghost" href="seo">Titres et descriptions des pages</a>
    <a class="btn btn--ghost" href="../articles" target="_blank" rel="noopener">Voir la page Articles ↗</a>
  </div>
</div>
<?php if ($articles): ?>
<div class="card">
  <h2>Derniers articles</h2>
  <ul>
    <?php foreach (array_slice($articles, 0, 5) as $a): ?>
      <li><a href="article?id=<?= e($a['id']) ?>"><?= e($a['titre']) ?></a> — <?= ($a['statut'] ?? '') === 'publie' ? 'publié' : 'brouillon' ?>, <?= e(format_date_fr($a['date'] ?? '')) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif;
admin_footer();
