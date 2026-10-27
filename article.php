<?php
declare(strict_types=1);
require __DIR__ . '/admin/inc/bootstrap.php';
require __DIR__ . '/inc/site.php';

$slug = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
$article = null;
foreach (published_articles() as $a) {
    if (($a['slug'] ?? '') === $slug) { $article = $a; break; }
}
if (!$article) {
    http_response_code(404);
    site_header(['title' => 'Article introuvable — AlloPrint', 'description' => 'Cet article n’existe pas ou n’est plus en ligne.', 'noindex' => true], 'articles');
    echo '<section class="hero"><div class="wrap"><h1 class="hero__title m-text-2">Article introuvable.</h1><p class="hero__lead m-text-5" style="margin-top:24px"><a class="m-link" href="articles">Voir tous les articles</a></p></div></section>';
    site_footer();
    exit;
}

$a = $article;
$canonical = site_url('article/' . rawurlencode($a['slug']));
$title = ($a['meta_title'] ?: $a['titre']) . ' — AlloPrint';
$desc = $a['meta_description'] ?: ($a['extrait'] ?: mb_substr(strip_tags(render_content($a['contenu'])), 0, 160));
$image = !empty($a['image']) ? site_url($a['image']) : site_url('images/logo.png');
$ld = [
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $a['titre'],
    'description' => $desc,
    'image' => [$image],
    'datePublished' => $a['date'],
    'dateModified' => substr($a['updated'] ?? $a['date'], 0, 10),
    'mainEntityOfPage' => $canonical,
    'author' => ['@type' => 'Organization', 'name' => 'AlloPrint', 'url' => site_url('notre-imprimerie')],
    'publisher' => ['@type' => 'Organization', 'name' => 'AlloPrint', 'logo' => ['@type' => 'ImageObject', 'url' => site_url('images/logo.png')]],
];
$svc = (!empty($a['service']) && isset(SERVICES[$a['service']])) ? SERVICES[$a['service']] : null;
$breadcrumbs = [
    '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => site_url('')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Conseils impression', 'item' => site_url('articles')],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $a['titre'], 'item' => $canonical],
    ],
];
$related = array_values(array_filter(published_articles(), fn($other) => $other['id'] !== $a['id'] && empty($other['noindex'])));
usort($related, fn($left, $right) => (int)($right['service'] === $a['service']) <=> (int)($left['service'] === $a['service']));

site_header([
    'title' => $title,
    'description' => $desc,
    'canonical' => $canonical,
    'image' => $image,
    'type' => 'article',
    'noindex' => !empty($a['noindex']),
    'head_extra' => '<meta property="article:published_time" content="' . e($a['date']) . '">' . "\n"
        . '<script type="application/ld+json">' . json_encode([$ld, $breadcrumbs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>' . "\n",
], 'articles');
?>
<section id="hero" class="hero" data-8b-section="hero" aria-labelledby="hero-title">
  <div class="wrap grid hero__grid">
    <p class="hero__crumbs m-text-4"><a href="./">Accueil</a> <span aria-hidden="true">/</span> <a href="articles">Articles</a> <span aria-hidden="true">/</span> <span aria-current="page"><?= e($a['titre']) ?></span></p>
    <h1 id="hero-title" class="hero__title hero__title--article m-text-2"><?= e($a['titre']) ?></h1>
    <p class="hero__lead m-text-5">Par AlloPrint · <time datetime="<?= e($a['date']) ?>"><?= e(format_date_fr($a['date'])) ?></time><?php if ($svc): ?> · <?= e($svc[0]) ?><?php endif; ?></p>
  </div>
</section>
<article class="band band--paper" aria-labelledby="hero-title">
  <div class="wrap prose-wrap">
    <?php if (!empty($a['image'])): ?>
      <figure class="m-photo post-cover"><img src="<?= e($a['image']) ?>" alt="<?= e($a['image_alt'] ?? '') ?>" decoding="async" fetchpriority="high"></figure>
    <?php endif; ?>
    <?php if (!empty($a['extrait'])): ?><p class="prose-lead m-text-5"><?= e($a['extrait']) ?></p><?php endif; ?>
    <div class="prose">
      <?= render_content($a['contenu']) ?>
    </div>
    <?php if ($svc): ?>
      <div class="detail__box m-surface post-svc">
        <p class="m-text-4">Notre service</p>
        <p><?= e($svc[0]) ?> à Casablanca : découvrez nos solutions et demandez votre devis gratuit.</p>
        <a class="m-btn-2" href="<?= e(str_replace('.html', '', $svc[1])) ?>"><span class="m-btn-2__label"><?= e($svc[0]) ?> ↗</span></a>
        <a class="m-link" href="devis-impression?service=<?= e($a['service']) ?>">Demander un devis pour ce service</a>
      </div>
    <?php endif; ?>
    <?php if ($related): ?>
    <aside class="prose post-related" aria-labelledby="related-posts">
      <h2 id="related-posts">Pour préparer votre impression</h2>
      <ul><?php foreach (array_slice($related, 0, 3) as $other): ?>
        <li><a class="m-link" href="article/<?= e(rawurlencode($other['slug'])) ?>"><?= e($other['titre']) ?></a></li>
      <?php endforeach; ?></ul>
    </aside>
    <?php endif; ?>
    <p style="margin-top:40px"><a class="m-link" href="articles">← Tous les articles</a></p>
  </div>
</article>
<?php site_footer();
