<?php
/** Routeur réservé au serveur de développement PHP : voir CONFIGURATION.md. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }

$documentRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$siteRoot = str_replace('\\', '/', __DIR__);
$prefix = $documentRoot === $siteRoot ? '' : '/' . trim(substr($siteRoot, strlen($documentRoot)), '/');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if ($path === $prefix && $prefix !== '') {
    header('Location: ' . $prefix . '/', true, 301); exit;
}
if ($prefix !== '' && !str_starts_with($path, $prefix . '/')) {
    http_response_code(404); exit('Page introuvable.');
}
$relative = substr($path, strlen($prefix) + 1);
if (str_contains($relative, "\0") || str_contains($relative, '\\')
    || preg_match('~(?:^|/)\.|^(?:data|inc|admin/inc)(?:/|$)|^router\.php$|\.(?:json|ini|log|md|bak|tmp|sql|ps1|py)$~i', $relative)
    || (str_starts_with(strtolower($relative), 'uploads/') && !preg_match('~\.(?:jpe?g|png|webp)$~i', $relative))) {
    http_response_code(403); exit('Accès interdit.');
}
// Mêmes adresses propres que le .htaccess (Apache) : /services-impression, /article/mon-article, /login…
if (in_array($relative, ['index.php', 'index.html'], true)) {
    header('Location: ' . $prefix . '/', true, 301); exit;
}
if (preg_match('~^(services|contact|devis)(?:\.html)?$~', $relative, $m)) {
    $qs = ($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: ' . $prefix . '/' . $m[1] . '-impression' . $qs, true, 301); exit;
}
if (preg_match('~^impression-lettrage(?:\.html)?$~', $relative)) {
    header('Location: ' . $prefix . '/impression-vitrine', true, 301); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('~^([a-z0-9-]+)\.html$~', $relative, $m)
    && !preg_match('~^google[0-9a-f]+$~i', $m[1])) {
    header('Location: ' . $prefix . '/' . $m[1], true, 301); exit;
}
$redirect = null;
if ($relative === 'site.xml' || ($relative === 'sitemap.php' && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true))) $redirect = 'sitemap.xml';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($relative === 'articles.php') $redirect = 'articles';
    if ($relative === 'article.php' && is_string($_GET['slug'] ?? null) && preg_match('~^[a-z0-9-]+$~', $_GET['slug'])) $redirect = 'article/' . $_GET['slug'];
    if ($relative === 'admin/login.php') $redirect = 'login' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
    if (preg_match('~^(.+)/$~', $relative, $m) && !is_dir(__DIR__ . '/' . $m[1])) $redirect = $m[1];
}
if ($redirect !== null) {
    header('Location: ' . $prefix . '/' . $redirect, true, 301); exit;
}
if ($relative === 'sitemap.xml') $relative = 'sitemap.php';
if ($relative === 'login') $relative = 'admin/login.php';
if (preg_match('~^article/([a-z0-9-]+)/?$~', $relative, $m)) { $_GET['slug'] = $m[1]; $relative = 'article.php'; }
if ($relative !== '' && !str_contains(basename($relative), '.') && !is_dir(__DIR__ . '/' . $relative)) {
    if (is_file(__DIR__ . '/' . $relative . '.html')) $relative .= '.html';
    elseif (is_file(__DIR__ . '/' . $relative . '.php')) $relative .= '.php';
}
if ($relative === '') $relative = 'index.html';
if ($relative === 'admin') {
    header('Location: ' . $prefix . '/admin/', true, 301); exit;
}
if ($relative === 'admin/') $relative = 'admin/index.php';
$target = realpath(__DIR__ . '/' . $relative);
if (!$target || !str_starts_with(str_replace('\\', '/', $target), $siteRoot . '/') || !is_file($target)) {
    http_response_code(404);
    header('X-Robots-Tag: noindex');
    exit('Page introuvable.');
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (str_ends_with($relative, '.php')) {
    $_SERVER['SCRIPT_FILENAME'] = $target;
    $_SERVER['SCRIPT_NAME'] = $prefix . '/' . $relative;
    require $target;
    return true;
}
if (str_ends_with($relative, '.html') && !str_ends_with($path, '.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($target);
    return true;
}
return false;
