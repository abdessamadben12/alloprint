# Configuration AlloPrint : HTML, PHP et référencement

L’accueil est **`index.html`**, les services restent des fichiers `.html`, mais **les adresses publiques n’ont plus d’extension** : `/services-impression`, `/contact-impression`, `/devis-impression`, `/impression-vinyle`, `/articles`, `/article/mon-article`, `/login`, `/admin/…`. Les anciennes adresses en `.html` / `.php` redirigent en 301. Ce sont le `.htaccess` (Apache) et `router.php` (développement) qui font la correspondance. PHP gère les articles, l’administration, les prix, les formulaires (SMTP) et le sitemap.

## Démarrer dans votre environnement Windows

PHP est disponible ici dans `C:/xampp/php/php.exe`. La configuration `.vscode/settings.json` à la racine du projet règle l’extension **PHP Server** sur le port 3000 avec le routeur `site/router.php`.

1. Ouvrir le dossier `alloprint` dans VS Code.
2. Arrêter puis relancer **PHP Server: Serve project** pour charger les réglages.
3. Ouvrir `http://localhost:3000/site/`.
4. Articles : `http://localhost:3000/site/articles`.
5. Connexion : `http://localhost:3000/site/login`, puis administration : `http://localhost:3000/site/admin/`.

Autre lancement depuis la racine du projet, dans PowerShell :

```powershell
.\outils\servir-site.ps1
```

Ou directement :

```powershell
php -S localhost:3000 -t . site/router.php
```

Pour servir uniquement le dossier `site` à la racine de l’URL :

```powershell
php -S localhost:3000 -t site site/router.php
```

Le routeur est nécessaire en développement : le serveur intégré PHP ne lit pas les `.htaccess`. Il protège les données et les fichiers internes, sert les articles PHP et renvoie les vrais codes HTTP 403/404. Ne pas lancer deux serveurs sur le même port. Un serveur uniquement statique ne peut pas exécuter les pages PHP.

## Hébergement Apache / cPanel

- Utiliser une version maintenue de **PHP 8.2 ou ultérieure**, avec `mbstring`, `json`, `session` et `fileinfo`.
- Copier **le contenu de `site/`**, fichiers cachés compris, dans la racine web du domaine. Ne pas y copier `public_html/` de ce projet ni les anciens gabarits.
- Le domaine préparé est **`https://alloprint.ma`**. Il reprend celui des coordonnées existantes du site.
- Activer PHP pour les fichiers `.php` via l’hébergeur. Les `.html` restent des fichiers statiques.
- Apache doit autoriser les `.htaccess` (`AllowOverride All`) et disposer de `mod_rewrite`. Les modules `headers`, `expires`, `filter` et `deflate` activent les en-têtes, le cache et la compression configurés.
- Installer le certificat HTTPS avant la mise en ligne. La redirection vers HTTPS et sans `www` ne concerne que `alloprint.ma`, pas localhost. Si un proxy termine HTTPS en amont, adapter cette règle à la configuration HTTPS de l’hébergeur pour éviter une boucle.
- Autoriser PHP à écrire dans `data/` et `uploads/articles/`. Pour modifier les titres depuis l’admin, PHP doit aussi pouvoir remplacer les pages `.html` dans leur répertoire. Utiliser des droits adaptés à l’utilisateur PHP, sans `777`.
- Le compte administrateur existe déjà dans `data/admin.json` (connexion avec **contact@alloprint.ma** sur `/login`). Changer le mot de passe dans Admin › Mon compte.
- Lors des mises à jour, conserver les données existantes dans `data/` et `uploads/articles/` : les écraser remplacerait vos articles, prix ou compte administrateur.

`.user.ini` fixe les limites d’envoi et les réglages PHP sous CGI/FPM. Sous Apache avec `mod_php`, reporter ces valeurs dans le `php.ini` utilisé par le serveur. Le fuseau `Africa/Casablanca`, l’UTF-8 et les paramètres de session sont également initialisés dans `inc/config.php`. Le réglage du cookie sécurisé suit la connexion HTTPS reçue par PHP.

## Mise en production (alloprint.ma)

Le site en ligne se met à jour depuis le dépôt GitHub. Après chaque `git push` :

1. cPanel › **Git Version Control** › dépôt alloprint › **Pull or Deploy** › **Update from Remote** (puis **Deploy HEAD Commit** si le dépôt n’est pas cloné directement dans `public_html`).
2. Vérifier : `https://alloprint.ma/sitemap.xml` (XML, 22 adresses), `/login`, une ancienne adresse (`/stand.html` → `/stands-exposition`), une adresse inconnue (page « Page introuvable », code 404).
3. Dossiers `data/` et `data/sessions/` accessibles en écriture par PHP (755 ou 775, jamais 777).

Déjà réglé dans le code pour la production :

- **Domaine canonique** `https://alloprint.ma` (HTTPS, sans `www`) : `inc/config.php` et `.htaccess`.
- **Anciennes adresses** de l’ancien site (`stand.html`, `palissades-de-chantier.html`, `impression-offset-*.html`, `contact.php`, `demande-devis.php`…) : redirection 301 vers la page équivalente, pour garder le référencement acquis.
- **Validation Google Search Console** : `googlea38880217857c20a.html` reste servi tel quel à la racine (ne pas le supprimer) et n’apparaît pas dans le sitemap.
- **Page 404** aux couleurs du site (`404.php`, non indexée).
- **Sitemap** sans dépendance à l’extension PHP XMLWriter.
- **PHP** : erreurs masquées aux visiteurs et journalisées (`.user.ini`), sessions de l’admin dans `data/sessions/`.

Données propres au serveur (non suivies par Git, voir `.gitignore`) : messages reçus, réglages SMTP, tentatives de connexion, sessions. Les réglages SMTP se saisissent dans Admin › Messagerie SMTP **sur le site en ligne**.

## URL de production et référencement

L’URL est centralisée dans `inc/config.php` (`ALLOPRINT_DEFAULT_URL`). La variable d’environnement `ALLOPRINT_SITE_URL` peut la remplacer ; elle doit être identique lors de la configuration des pages HTML et lors de l’exécution de PHP.

Après un changement de domaine ou de sous-dossier, depuis la racine du projet :

```powershell
php outils/configurer-seo.php
```

Ce script **ne modifie plus le HTML** : il régénère `robots.txt` (règles définies dans `inc/seo.php`) et contrôle chaque page indexable — un seul canonical égal à l’adresse propre, `og:url`, longueur du titre (25–65) et de la description (70–160), un seul H1, JSON-LD valide, images avec `alt`, aucun lien interne en `.html`, et présence des mots-clés « palissade de chantier », « impression sur vinyle », « impression de vitrine », « impression sur bâche » dans le titre, la description et le H1 de leur page. Il se termine en erreur s’il reste un point à revoir. Les balises SEO de chaque page sont maintenant dans le bloc `<!-- seo:start --> … <!-- seo:end -->` (canonical, Open Graph, données structurées LocalBusiness / Service / BreadcrumbList / FAQPage). Les titres et descriptions se modifient dans Admin › Référencement, qui met aussi à jour Open Graph et les données structurées. Adapter aussi le domaine de redirection dans `.htaccess`. Si le site est déployé dans un sous-dossier, reporter les règles de robots dans le **`robots.txt` à la racine du domaine**, car celui d’un sous-dossier n’est pas utilisé par les moteurs.

Le sitemap est **`https://alloprint.ma/sitemap.xml`** (servi par `sitemap.php` ; `/sitemap.php` et `/site.xml` redirigent en 301). Il liste les adresses sans extension, l’image principale de chaque page (extension image de Google) et, pour les articles, la date de dernière modification. Les pages HTML n’ont pas de `lastmod`, car la date du fichier change à chaque transfert FTP et ne reflète pas une vraie modification. Il inclut les pages publiques et les articles publiés à la date courante, en excluant les brouillons, les publications futures et les articles marqués `noindex`. Les modifications de publication sont prises en compte automatiquement.

Les six articles se trouvent dans `data/articles.json` et sont modifiables depuis l’administration. Les pages de services, l’accueil et les articles sont reliés entre eux par des liens HTML. Les noms visibles privilégient « impression » ; l’ancienne URL `notre-imprimerie.html` est conservée pour préserver les liens existants.

Le script `outils/generer-pages.py` est un **ancien générateur** : ses gabarits ne représentent plus toutes les pages actuelles. Ne pas le relancer sur le site final sans y reporter les enrichissements et les fonctions d’administration.

## Vérifier avant publication

Avec un serveur démarré :

```powershell
python outils/verifier-site.py http://localhost:3000/site/
```

`robots.txt` ne bloque pas `/admin/` ni `/login` : ces pages envoient `X-Robots-Tag: noindex`, et Google doit pouvoir les explorer pour lire cette consigne (une page bloquée peut quand même apparaître dans les résultats, sans description).

Vérifier également l’accès aux articles, au formulaire de connexion (`/login`) et au sitemap (`/sitemap.xml`) sur l’hébergement réel. `data/articles.json`, `inc/config.php` et `.user.ini` doivent renvoyer **403**, un article inconnu **404**.

Les formulaires de contact et de devis envoient les demandes par **SMTP** (`api/envoyer.php`, réglages dans Admin › Messagerie SMTP) et les enregistrent dans Admin › Messages reçus. Si PHP ne répond pas, le formulaire ouvre la messagerie du visiteur en secours.

## Après mise en ligne

1. Vérifier le domaine dans Google Search Console.
2. Soumettre `https://alloprint.ma/sitemap.xml` et inspecter les quatre pages de services prioritaires (`/palissade-chantier`, `/impression-vinyle`, `/impression-vitrine`, `/impression-bache`).
3. Suivre les impressions, clics et requêtes de ces pages ; enrichir les contenus avec des réalisations et informations vérifiables.

Les changements préparent le référencement mais ne garantissent ni l’indexation ni une position. Les URL localhost ne sont pas accessibles à Google. Références : [guide SEO Google](https://developers.google.com/search/docs/fundamentals/seo-starter-guide?hl=fr), [URL canoniques](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls?hl=fr), [sitemaps](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap?hl=fr).
