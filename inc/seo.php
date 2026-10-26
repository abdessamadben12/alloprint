<?php
/** Lecture des pages statiques partagée par le sitemap et l’outil de configuration SEO. */
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function seo_attributes(string $tag): array
{
    preg_match_all('~([\w:-]+)\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\s>]+))~', $tag, $matches, PREG_SET_ORDER);
    $attributes = [];
    foreach ($matches as $match) {
        $value = ($match[2] ?? '') !== '' ? $match[2] : (($match[3] ?? '') !== '' ? $match[3] : ($match[4] ?? ''));
        $attributes[strtolower($match[1])] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $attributes;
}

/** Pages éditoriales indexables uniquement : pas de validation Google ni de redirection HTML. */
function seo_static_pages(): array
{
    $pages = [];
    foreach (glob(__DIR__ . '/../*.html') as $file) {
        $filename = basename($file);
        if (preg_match('/^google[0-9a-f]+\.html$/i', $filename)) continue;
        $html = (string)file_get_contents($file);
        if (!preg_match('~<h1\b~i', $html)) continue;
        preg_match_all('~<meta\b[^>]*>~i', $html, $tags);
        $meta = [];
        $indexable = true;
        foreach ($tags[0] as $tag) {
            $attributes = seo_attributes($tag);
            $name = strtolower($attributes['name'] ?? $attributes['property'] ?? '');
            $meta[$name] = $attributes['content'] ?? '';
            if (in_array($name, ['robots', 'googlebot'], true) && preg_match('/\b(?:noindex|none)\b/i', $meta[$name])) $indexable = false;
            if (strtolower($attributes['http-equiv'] ?? '') === 'refresh') $indexable = false;
        }
        if (!$indexable) continue;
        $slug = $filename === 'index.html' ? '' : basename($filename, '.html');
        preg_match('~<title>(.*?)</title>~si', $html, $title);
        $pages[$filename] = [
            'file' => $file, 'html' => $html, 'slug' => $slug,
            'url' => public_base_url() . '/' . $slug,
            'title' => html_entity_decode($title[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'meta' => $meta,
        ];
    }
    return $pages;
}

/** Ne pas annoncer de date impossible ou future à un moteur de recherche. */
function seo_valid_date(string $value): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) return false;
    return checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]) && $value <= date('Y-m-d');
}

function seo_robots_text(): string
{
    $prefix = rtrim(parse_url(public_base_url(), PHP_URL_PATH) ?: '', '/');
    $text = "# Pages publiques et ressources CSS, JavaScript et images accessibles.\nUser-agent: *\n";
    foreach (['api/', 'data/', 'inc/', 'admin/inc/', 'router.php'] as $path) {
        $text .= 'Disallow: ' . $prefix . '/' . $path . "\n";
    }
    // Les écrans de connexion restent explorables pour que les moteurs lisent leur noindex.
    return $text . "\nSitemap: " . public_base_url() . "/sitemap.xml\n";
}
