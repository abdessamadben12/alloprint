<?php
/** Page 404 du site (ErrorDocument dans .htaccess) : même gabarit que le site, non indexée. */
declare(strict_types=1);
require __DIR__ . '/admin/inc/bootstrap.php';
require __DIR__ . '/inc/site.php';

http_response_code(404);
site_header(['title' => 'Page introuvable — AlloPrint', 'description' => 'Cette page n’existe pas ou a été déplacée.', 'noindex' => true]);
?>
<section class="hero"><div class="wrap">
  <h1 class="hero__title m-text-2">Page introuvable.</h1>
  <p class="hero__lead m-text-5" style="margin-top:24px">Cette adresse n’existe pas ou a changé avec le nouveau site. Nos services sont tous ici :</p>
  <p class="m-text-5" style="margin-top:24px;display:flex;flex-wrap:wrap;gap:12px 28px">
    <a class="m-link" href="services-impression">Tous les services</a>
    <a class="m-link" href="devis-impression">Demander un devis</a>
    <a class="m-link" href="contact-impression">Contact</a>
    <a class="m-link" href="./">Accueil</a>
  </p>
</div></section>
<?php site_footer();
