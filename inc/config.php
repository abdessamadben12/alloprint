<?php
/** URL publique de production, également utilisée par le sitemap et les balises canonical. */
declare(strict_types=1);

const ALLOPRINT_DEFAULT_URL = 'https://alloprint.ma';

function public_base_url(): string
{
    $url = rtrim(getenv('ALLOPRINT_SITE_URL') ?: ALLOPRINT_DEFAULT_URL, '/');
    $parts = parse_url($url);
    if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
        || empty($parts['host']) || isset($parts['query']) || isset($parts['fragment'])
        || isset($parts['user']) || isset($parts['pass'])) {
        throw new RuntimeException('ALLOPRINT_SITE_URL doit être une URL absolue sans paramètres.');
    }
    return $url;
}

date_default_timezone_set('Africa/Casablanca');
ini_set('default_charset', 'UTF-8');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
