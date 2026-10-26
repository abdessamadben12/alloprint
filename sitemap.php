<?php
/** Sitemap XML toujours à jour : pages publiques (adresses propres) + articles publiés. Servi aussi sous /sitemap.xml. */
declare(strict_types=1);
require __DIR__ . '/admin/inc/bootstrap.php';
require __DIR__ . '/inc/site.php';
require __DIR__ . '/inc/seo.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache');

$urls = [];
foreach (seo_static_pages() as $page) {
    // filemtime change aussi après un transfert Git/FTP : ce n’est pas une date éditoriale fiable.
    $urls[$page['url']] = ['loc' => $page['url'], 'image' => $page['meta']['og:image'] ?? ''];
}
$articles = published_articles();
foreach ($articles as $article) {
    if (!empty($article['noindex'])) continue;
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $article['slug'] ?? '')) continue;
    $modified = substr((string)($article['updated'] ?? $article['date']), 0, 10);
    $entry = ['loc' => site_url('article/' . rawurlencode($article['slug']))];
    if (seo_valid_date($modified) && $modified >= (string)($article['date'] ?? '')) {
        $entry['lastmod'] = $modified;
    }
    $entry['image'] = !empty($article['image']) ? site_url($article['image']) : '';
    $urls[$entry['loc']] = $entry;
}
$urls[site_url('articles')] = ['loc' => site_url('articles')];

$xml = fn($value) => htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
    . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
foreach ($urls as $entry) {
    $out .= "  <url>\n    <loc>" . $xml($entry['loc']) . "</loc>\n";
    if (!empty($entry['lastmod'])) $out .= '    <lastmod>' . $xml($entry['lastmod']) . "</lastmod>\n";
    // Images hébergées sur le domaine uniquement (Google Images)
    if (!empty($entry['image']) && str_starts_with($entry['image'], public_base_url() . '/')) {
        $out .= '    <image:image><image:loc>' . $xml($entry['image']) . "</image:loc></image:image>\n";
    }
    $out .= "  </url>\n";
}
echo $out . "</urlset>\n";
