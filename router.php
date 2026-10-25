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
if ($relative === 'index.php') {
    header('Location: ' . $prefix . '/index.html', true, 301); exit;
}
if ($relative === 'impression-lettrage.html') {
    header('Location: ' . $prefix . '/impression-vitrine.html', true, 301); exit;
}
if ($relative === 'sitemap.xml') $relative = 'sitemap.php';
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
if ($relative === 'index.html' && str_ends_with($path, '/')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($target);
    return true;
}
return false;
