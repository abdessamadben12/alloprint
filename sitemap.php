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

$xml = new XMLWriter();
$xml->openMemory();
$xml->setIndent(true);
$xml->startDocument('1.0', 'UTF-8');
$xml->startElement('urlset');
$xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
foreach ($urls as $entry) {
    $xml->startElement('url');
    foreach ($entry as $key => $value) $xml->writeElement($key, (string)$value);
    $xml->endElement();
}
$xml->endElement();
$xml->endDocument();
echo $xml->outputMemory();
