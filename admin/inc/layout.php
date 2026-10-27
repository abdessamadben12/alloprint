<?php
declare(strict_types=1);

function admin_header(string $title, string $active = ''): void
{
    send_security_headers();
    $nav = [
        './'       => ['dashboard', 'Tableau de bord'],
        'messages' => ['messages', 'Messages reçus'],
        'articles' => ['articles', 'Articles'],
        'realisations' => ['realisations', 'Réalisations'],
        'prix'     => ['prix', 'Prix des services'],
        'seo'      => ['seo', 'Référencement (SEO)'],
        'smtp'     => ['smtp', 'Messagerie (SMTP)'],
        'compte'   => ['compte', 'Mon compte'],
    ];
    $unread = count(array_filter(read_json('messages', []), fn($m) => empty($m['lu'])));
    ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> — Admin AlloPrint</title>
<link rel="icon" href="../favicon.ico?v=2" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32.png?v=2">
<link rel="apple-touch-icon" href="../images/favicon/apple-touch-icon.png?v=2">
<link rel="manifest" href="../site.webmanifest">
<meta name="theme-color" content="#00677a">
<link rel="stylesheet" href="admin.css">
</head>
<body class="adm">
<header class="adm-side">
  <a class="adm-brand" href="./"><img src="../images/logo.png" alt="AlloPrint" width="80" height="64"><span>Administration</span></a>
  <button class="adm-burger" type="button" aria-expanded="false" aria-controls="adm-nav" onclick="const n=document.getElementById('adm-nav');const o=n.classList.toggle('is-open');this.setAttribute('aria-expanded',o)">Menu</button>
  <nav id="adm-nav" class="adm-nav" aria-label="Administration">
    <?php foreach ($nav as $href => [$key, $label]): ?>
      <a href="<?= $href ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($key === 'messages' && $unread): ?><span class="nav-count"><?= $unread ?></span><?php endif; ?></a>
    <?php endforeach; ?>
    <hr>
    <a href="../" target="_blank" rel="noopener">Voir le site ↗</a>
    <form method="post" action="logout"><?= csrf_field() ?><button type="submit" class="adm-linkbtn">Se déconnecter</button></form>
  </nav>
</header>
<main class="adm-main">
  <h1><?= e($title) ?></h1>
  <?php foreach (take_flashes() as [$type, $msg]): ?>
    <p class="adm-flash adm-flash--<?= e($type) ?>" role="status"><?= e($msg) ?></p>
  <?php endforeach;
}

function admin_footer(): void
{
    ?>
</main>
</body>
</html><?php
}
