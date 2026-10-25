<?php
/** Sitemap toujours synchronisé avec les publications et leurs dates. */
declare(strict_types=1);
require __DIR__ . '/admin/inc/bootstrap.php';
require __DIR__ . '/inc/site.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$urls = [];
foreach (glob(__DIR__ . '/*.html') as $file) {
    $html = (string)file_get_contents($file);
    if (preg_match('~<meta\s+name="robots"\s+content="[^"]*noindex~i', $html)) continue;
    $urls[] = ['loc' => site_url(basename($file))];
}
$urls[] = ['loc' => site_url('articles.php')];
foreach (published_articles() as $article) {
    if (!empty($article['noindex'])) continue;
    $entry = ['loc' => site_url('article.php?slug=' . rawurlencode($article['slug']))];
    $modified = substr($article['updated'] ?? $article['date'], 0, 10);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $modified) && $modified <= date('Y-m-d')) {
        $entry['lastmod'] = $modified;
    }
    $urls[] = $entry;
}

$xml = new XMLWriter();
$xml->openMemory();
$xml->setIndent(true);
$xml->startDocument('1.0', 'UTF-8');
$xml->startElement('urlset');
$xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
foreach ($urls as $entry) {
    $xml->startElement('url');
    foreach ($entry as $key => $value) $xml->writeElement($key, $value);
    $xml->endElement();
}
$xml->endElement();
$xml->endDocument();
echo $xml->outputMemory();
