<?php
declare(strict_types=1);
require __DIR__ . '/admin/inc/bootstrap.php';
require __DIR__ . '/inc/site.php';

$articles = published_articles();

header('Content-Type: text/html; charset=utf-8');
site_header([
    'title' => 'Conseils impression à Casablanca — AlloPrint',
    'description' => 'Guides pour votre impression de palissade de chantier, sur vinyle, de vitrine et sur bâche à Casablanca : supports, fichiers, finitions et devis.',
    'canonical' => site_url('articles'),
    'head_extra' => '<script type="application/ld+json">' . json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            ['@type' => 'CollectionPage', '@id' => site_url('articles') . '#webpage', 'url' => site_url('articles'), 'name' => 'Conseils impression à Casablanca', 'inLanguage' => 'fr-MA',
             'isPartOf' => ['@id' => site_url('') . '#website'], 'breadcrumb' => ['@id' => site_url('articles') . '#breadcrumb']],
            ['@type' => 'BreadcrumbList', '@id' => site_url('articles') . '#breadcrumb', 'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => site_url('')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Conseils impression', 'item' => site_url('articles')],
            ]],
            ['@type' => 'ItemList', 'itemListElement' => array_map(fn($i, $a) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => site_url('article/' . rawurlencode($a['slug'])), 'name' => $a['titre']], array_keys($articles), $articles)],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>' . "\n",
], 'articles');
?>
<section id="hero" class="hero" data-8b-section="hero" aria-labelledby="hero-title">
  <div class="wrap grid hero__grid">
    <p class="hero__crumbs m-text-4"><a href="./">Accueil</a> <span aria-hidden="true">/</span> <span aria-current="page">Articles</span></p>
    <h1 id="hero-title" class="hero__title m-text-2" data-8b-motion="register">Conseils impression<br><span>et guides pratiques.</span></h1>
    <p class="hero__lead m-text-5">Palissade de chantier, vinyle, vitrine ou bâche : choisissez votre support, préparez vos fichiers et précisez votre demande de devis.</p>
    <nav class="service-links" aria-label="Services d’impression">
      <a class="m-link" href="palissade-chantier">Impression palissade de chantier</a>
      <a class="m-link" href="impression-vinyle">Impression sur vinyle</a>
      <a class="m-link" href="impression-vitrine">Impression de vitrine</a>
      <a class="m-link" href="impression-bache">Impression sur bâche</a>
    </nav>
  </div>
</section>
<section class="band band--paper" aria-label="Liste des articles">
  <div class="wrap">
  <?php if (!$articles): ?>
    <p class="m-text-5">Les premiers articles arrivent bientôt. En attendant, découvrez <a class="m-link" href="services">nos services</a>.</p>
  <?php else: ?>
    <div class="svc-grid">
    <?php foreach ($articles as $a): $url = 'article/' . rawurlencode($a['slug']); ?>
      <article class="svc-card post-card">
        <a class="svc-card__photo m-photo" href="<?= e($url) ?>" aria-label="Lire : <?= e($a['titre']) ?>">
          <?php if (!empty($a['image'])): ?>
            <img src="<?= e($a['image']) ?>" alt="<?= e($a['image_alt'] ?? '') ?>" loading="lazy" decoding="async" width="1200" height="900">
          <?php else: ?>
            <span class="post-card__ph" aria-hidden="true"></span>
          <?php endif; ?>
          <?php if (!empty($a['service']) && isset(SERVICES[$a['service']])): ?><span class="svc-card__tag"><?= e(SERVICES[$a['service']][0]) ?></span><?php endif; ?>
        </a>
        <p class="svc-card__no m-text-4"><time datetime="<?= e($a['date']) ?>"><?= e(format_date_fr($a['date'])) ?></time></p>
        <h2 class="m-text-6"><a href="<?= e($url) ?>"><?= e($a['titre']) ?></a></h2>
        <?php if (!empty($a['extrait'])): ?><p><?= e($a['extrait']) ?></p><?php endif; ?>
        <a class="m-link" href="<?= e($url) ?>">Lire l’article ↗</a>
      </article>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
  </div>
</section>
<section id="cta" class="band band--green" data-8b-section="cta" aria-labelledby="cta-title">
  <div class="wrap grid cta">
    <h2 id="cta-title" class="cta__title m-text-2" data-8b-motion="register">Un projet<br>à imprimer ?</h2>
    <div class="cta__actions">
      <p class="m-text-5">Devis gratuit. Nous vous conseillons sur le support, le format et les finitions.</p>
      <a class="m-btn" href="devis"><span class="m-btn__label">Demander un devis</span></a>
      <a class="m-link" href="tel:+212522484425">Ou appelez le 05 22 48 44 25</a>
    </div>
  </div>
</section>
<?php site_footer();
