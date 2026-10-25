# Espace d’administration AlloPrint

## Adresses
- Connexion : **ton-domaine/login** (ex. https://alloprint.ma/login)
- Administration : **ton-domaine/admin/**
- Les pages du site n’ont plus d’extension : /services, /contact, /devis, /notre-imprimerie, /articles, /article/titre-de-l-article…
  Les anciennes adresses (.html / .php) redirigent automatiquement (301) vers les nouvelles.
  Tout cela passe par le fichier `.htaccess` à la racine du site (Apache : cPanel, XAMPP — module « rewrite » activé, ce qui est le cas par défaut).

## Connexion
- E-mail : **contact@alloprint.ma**
- Mot de passe : celui communiqué à la livraison. Changez-le dans **Admin › Mon compte**.
- « Mot de passe oublié ? » sur la page de connexion envoie un lien valable 1 heure (nécessite le SMTP configuré).
- Sans SMTP et sans accès : supprimez `data/admin.json` sur le serveur, puis ouvrez /login pour recréer le compte.

## Messagerie (SMTP) — formulaires Devis et Contact
Admin › **Messagerie (SMTP)** :
- Adresse e-mail (identifiant SMTP) et **mot de passe de la boîte mail** (jamais réaffiché ; laisser vide pour le conserver).
- Serveur (souvent `mail.alloprint.ma`), sécurité SSL/465 ou TLS/587 : voir cPanel › Comptes e-mail › « Configurer le client de messagerie ».
- Adresse(s) de réception des demandes, accusé de réception automatique au client.
- Bouton « Envoyer un e-mail de test ».

Chaque demande est aussi enregistrée dans **Admin › Messages reçus** (même si l’e-mail ne part pas), avec un bouton « Répondre ».
Protection anti-spam : champ piège invisible, délai minimum, 5 envois max. par 15 min et par adresse IP.

## Le reste
- **Articles** : création, image, publication, métadonnées SEO.
- **Prix des services** : prix affichés sur les pages de services.
- **Référencement (SEO)** : titre et description de chaque page.

## Mise en ligne
1. PHP 8.0 ou plus, Apache avec `.htaccess` autorisé.
2. Dossiers `data/` et `uploads/articles/` modifiables par PHP (755/775). Pages `.html` modifiables pour l’onglet SEO.
3. Utilisez le site en HTTPS.
- `data/`, `admin/inc/` et les fichiers `.json` / `.md` sont interdits au web. Aucun script ne s’exécute dans `uploads/`.
- Serveur Nginx : reproduire les règles du `.htaccess` dans la configuration.

## Tester en local
XAMPP : copiez `site/` dans `htdocs/alloprint/site/`, puis ouvrez http://localhost/alloprint/site/login.
