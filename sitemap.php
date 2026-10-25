<?php
/** Sitemap XML toujours à jour : pages publiques (adresses propres) + articles publiés. Servi aussi sous /sitemap.xml. */
declare(strict_types=1);
require __DIR__ . '/admin/inc/bootstrap.php';
require __DIR__ . '/inc/site.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$priority = ['index' => '1.0', 'services' => '0.9', 'palissade-chantier' => '0.9', 'impression-vinyle' => '0.9', 'impression-vitrine' => '0.9',
    'impression-bache' => '0.9', 'devis' => '0.7', 'contact' => '0.6', 'notre-imprimerie' => '0.6'];
$urls = [];
foreach (glob(__DIR__ . '/*.html') as $file) {
    $html = (string)file_get_contents($file);
    if (preg_match('~<meta\s+name="robots"\s+content="[^"]*noindex~i', $html) || str_contains($html, 'http-equiv="refresh"')) continue;
    $slug = basename($file, '.html');
    if (preg_match('/^google[0-9a-f]+$/', $slug)) continue; // fichier de validation Search Console
    $urls[] = [
        'loc' => site_url($slug === 'index' ? '' : $slug),
        'lastmod' => date('Y-m-d', (int)filemtime($file)),
        'priority' => $priority[$slug] ?? '0.8',
    ];
}
$articles = published_articles();
$latest = '';
foreach ($articles as $article) {
    if (!empty($article['noindex'])) continue;
    $modified = substr((string)($article['updated'] ?? $article['date']), 0, 10);
    $entry = ['loc' => site_url('article/' . rawurlencode($article['slug'])), 'priority' => '0.7'];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $modified) && $modified <= date('Y-m-d')) {
        $entry['lastmod'] = $modified;
        $latest = max($latest, $modified);
    }
    $urls[] = $entry;
}
$urls[] = array_filter(['loc' => site_url('articles'), 'lastmod' => $latest ?: null, 'priority' => '0.7']);

$out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $entry) {
    $out .= "  <url>\n";
    foreach ($entry as $key => $value) {
        $out .= '    <' . $key . '>' . htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</' . $key . ">\n";
    }
    $out .= "  </url>\n";
}
echo $out . "</urlset>\n";
