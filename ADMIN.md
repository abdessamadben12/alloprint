# Espace d’administration AlloPrint

Adresse : **/admin/** (par ex. https://alloprint.ma/admin/)

## Mise en ligne
Voir d’abord [CONFIGURATION.md](CONFIGURATION.md) pour le serveur, PHP Server dans VS Code, le sitemap et les réglages SEO.

1. Hébergement **PHP 8.2 ou plus récent, dans une version maintenue** (cPanel / Apache classique). Aucune base de données n’est nécessaire : tout est enregistré dans `data/` en fichiers JSON.
2. Envoyez tout le dossier `site/` sur le serveur.
3. Les dossiers `data/` et `uploads/articles/` doivent être **modifiables par PHP** (droits 755 ou 775 selon l’hébergeur). Les pages `.html` doivent aussi l’être si vous voulez modifier leurs titres et descriptions depuis l’onglet « Référencement ».
4. Ouvrez **/admin/** en local ou derrière un accès protégé avant publication : au premier accès, la page demande de **créer le compte administrateur** (identifiant + mot de passe de 10 caractères minimum).

## Ce que l’on peut faire
- **Articles** : créer, modifier, publier ou mettre en brouillon, supprimer. Image de couverture, résumé, service lié, et **métadonnées SEO** (titre SEO, méta-description, adresse de la page, option « ne pas indexer »). Les articles publiés apparaissent sur `articles.php`, chacun sur `article.php?slug=…` (avec balises Open Graph et données structurées Google).
- **Prix des services** : un prix indicatif par service (mention « À partir de », « Prix fixe » ou « Environ », unité, HT/TTC, précision). Il s’affiche dans l’encadré « Votre projet, votre budget » de la page du service et sur la page Services. Les pages lisent les prix via `api/prix.php`.
- **Référencement (SEO)** : titre et méta-description de chaque page du site (accueil, services, contact…). Les balises Open Graph sont mises à jour en même temps.

## Sécurité
- Mot de passe chiffré (`password_hash`), session sécurisée, protection CSRF sur tous les formulaires, blocage 15 minutes après 5 échecs de connexion, déconnexion après 2 h d’inactivité.
- `data/` et `admin/inc/` sont interdits au web par `.htaccess` ; aucun script ne peut s’exécuter dans `uploads/`.
- **Serveur Nginx** (sans `.htaccess`) : ajoutez l’équivalent dans la configuration : `location ~ ^/(data|admin/inc|inc)/ { deny all; }` et interdisez PHP dans `/uploads/`.
- Utilisez le site en **HTTPS**.

## Mot de passe oublié
Supprimez le fichier `data/admin.json` (par FTP ou le gestionnaire de fichiers) puis ouvrez /admin/ : la création du compte est proposée à nouveau. Les articles et les prix sont conservés.

## À savoir
Les pages `.php` (articles, admin) ne fonctionnent qu’à travers un serveur PHP, pas en ouvrant le fichier directement sur l’ordinateur. Pour tester en local : `php -S localhost:8000 router.php` dans le dossier `site/`, puis ouvrez http://localhost:8000/admin/. Le routeur applique les protections que le serveur intégré ne lit pas dans les `.htaccess`.
