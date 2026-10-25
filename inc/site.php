<?php
/** Gabarit public partagé par les pages PHP (articles). Même en-tête et pied de page que les pages HTML. */
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function site_header(array $meta, string $current = ''): void
{
    $title = $meta['title'] ?? 'AlloPrint';
    $desc = $meta['description'] ?? '';
    $canonical = $meta['canonical'] ?? '';
    $image = $meta['image'] ?? '';
    $type = $meta['type'] ?? 'website';
    $noindex = !empty($meta['noindex']);
    $nav = <<<'HTML'
<!-- navigation: top bar: wordmark, page links, Devis button; phone menu overlay -->
<header id="nav" class="nav" data-8b-section="navigation">
  <a class="nav__skip" href="#main">Aller au contenu</a>
  <div class="nav__shell">
    <div class="nav__bar wrap">
      <a class="nav__brand" href="./" title="Accueil"><img class="nav__logo" src="images/logo.png" alt="AlloPrint" width="160" height="128"></a>
      <nav class="nav__links" aria-label="Principal">
        <a class="m-link" href="services">Services</a>
        <a class="m-link" href="./#editions">Réalisations</a>
        <a class="m-link" href="notre-imprimerie">L’imprimerie</a>
        <a class="m-link" href="contact">Contact</a>
      </nav>
      <a class="m-btn nav__cta" href="devis"><span class="m-btn__label">Demander un devis</span></a>
      <button class="m-icon-btn nav__toggle" type="button" aria-expanded="false" aria-controls="menu"><span class="burger" aria-hidden="true"><i></i><i></i><i></i></span><span class="m-icon-btn__name">Ouvrir le menu</span></button>
    </div>
  </div>
  <div id="menu" class="nav__menu" data-lenis-prevent hidden>
    <div class="nav__menu-top">
      <img class="nav__logo" src="images/logo.png" alt="AlloPrint" width="160" height="128">
      <button class="m-icon-btn nav__close" type="button"><span class="xmark" aria-hidden="true"></span><span class="m-icon-btn__name">Fermer le menu</span></button>
    </div>
    <nav class="nav__menu-links" aria-label="Menu">
      <a href="./"><span>Accueil</span><span class="nav__arrow" aria-hidden="true">→</span></a>
      <a href="services"><span>Services</span><span class="nav__arrow" aria-hidden="true">→</span></a>
      <a href="./#editions"><span>Réalisations</span><span class="nav__arrow" aria-hidden="true">→</span></a>
      <a href="notre-imprimerie"><span>L’imprimerie</span><span class="nav__arrow" aria-hidden="true">→</span></a>
      <a href="contact"><span>Contact</span><span class="nav__arrow" aria-hidden="true">→</span></a>
    </nav>
    <div class="nav__menu-extra">
      <a class="m-btn nav__menu-cta" href="devis"><span class="m-btn__label">Demander un devis gratuit</span></a>
      <div class="nav__quick">
        <a href="tel:+212522484425"><img src="images/icons/telephone.svg" alt="" aria-hidden="true" width="28" height="28">Appeler</a>
        <a href="https://wa.me/212668746386" target="_blank" rel="noopener"><img src="images/icons/whatsapp.svg" alt="" aria-hidden="true" width="28" height="28">WhatsApp</a>
      </div>
      <p class="nav__menu-addr">3, avenue 2 Mars, Résidence Marwa, 5e étage, Casablanca<br><a href="mailto:contact@alloprint.ma">contact@alloprint.ma</a></p>
    </div>
  </div>
</header>
HTML;
    if ($current) $nav = str_replace('href="' . $current . '"', 'href="' . $current . '" aria-current="page"', $nav);
    // avec <base>, les ancres doivent garder l'adresse de la page en cours
    $nav = str_replace('href="#main"', 'href="' . e(current_path()) . '#main"', $nav);
    ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<base href="<?= e(site_base_path()) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="robots" content="<?= $noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large' ?>">
<?php if ($canonical): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif; ?>
<meta property="og:type" content="<?= e($type) ?>">
<meta property="og:site_name" content="AlloPrint">
<meta property="og:locale" content="fr_MA">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<?php if ($canonical): ?><meta property="og:url" content="<?= e($canonical) ?>"><?php endif; ?>
<?php if ($image): ?><meta property="og:image" content="<?= e($image) ?>"><meta name="twitter:card" content="summary_large_image"><?php else: ?><meta name="twitter:card" content="summary"><?php endif; ?>
<?= $meta['head_extra'] ?? '' ?>
<link rel="icon" href="favicon.ico?v=2" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32.png?v=2">
<link rel="apple-touch-icon" href="images/favicon/apple-touch-icon.png?v=2">
<link rel="manifest" href="site.webmanifest">
<meta name="theme-color" content="#00677a">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" href="https://fonts.gstatic.com/s/gabarito/v9/QGYtz_0dZAGKJJ4t3HtoW4U.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="https://fonts.gstatic.com/s/rethinksans/v7/AMOWz4SDuXOMCPfdoglY9JQEVFi3.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="css/alloprint.css">
</head>
<body class="m-text">
<?= $nav ?>
<main id="main">
<?php
}

function site_footer(): void
{
    ?></main>
<!-- footer: links, address, phone and copyright -->
<footer id="footer" class="footer" data-8b-section="footer">
  <div class="wrap">
    <div class="footer__cols">
      <nav class="footer__nav" aria-label="Pied de page">
        <ul>
          <li><a class="m-link" href="./">Accueil</a></li>
          <li><a class="m-link" href="services">Services</a></li>
          <li><a class="m-link" href="notre-imprimerie">Notre imprimerie</a></li>
          <li><a class="m-link" href="articles">Articles</a></li>
          <li><a class="m-link" href="./#editions">Réalisations</a></li>
          <li><a class="m-link" href="devis">Devis</a></li>
          <li><a class="m-link" href="contact">Contact</a></li>
        </ul>
      </nav>
      <p class="footer__address">3, avenue 2 Mars, Résidence Marwa, 5e étage<br>Casablanca, Maroc</p>
      <ul class="footer__social">
        <li><a class="m-link" href="tel:+212522484425">05 22 48 44 25</a></li>
        <li><a class="m-link" href="https://wa.me/212668746386" target="_blank" rel="noopener">WhatsApp : 06 68 74 63 86</a></li>
        <li><a class="m-link" href="mailto:contact@alloprint.ma">contact@alloprint.ma</a></li>
      </ul>
    </div>
    <div class="footer__base">
      <p class="m-text-4">© 2026 AlloPrint · Imprimerie à Casablanca</p>
      <a class="m-link" href="<?= e(current_path()) ?>#nav">Retour en haut</a>
    </div>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/lenis@1.3.27/dist/lenis.min.js"></script>
<script src="js/alloprint.js"></script>
</body>
</html><?php
}

/** Adresse absolue d'un chemin du site (pour canonical / Open Graph). */
function site_url(string $path = ''): string
{
    // URL de production fixe (inc/config.php) : canonical et Open Graph identiques en local et en ligne
    return public_base_url() . '/' . ltrim($path, '/');
}

/** Chemin de la page en cours (sans domaine), pour les ancres internes. */
function current_path(): string
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    return strtok($uri, '#') ?: '/';
}
