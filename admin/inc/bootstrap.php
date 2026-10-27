<?php
/**
 * AlloPrint — socle de l'espace d'administration.
 * Stockage en fichiers JSON (dossier data/, protégé par .htaccess) : aucune base de données à configurer.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../inc/config.php';

date_default_timezone_set('Africa/Casablanca');

const SITE_ROOT = __DIR__ . '/../..';
const DATA_DIR = SITE_ROOT . '/data';
const UPLOAD_DIR = SITE_ROOT . '/uploads/articles';
const UPLOAD_URL = 'uploads/articles';
const REAL_UPLOAD_DIR = SITE_ROOT . '/uploads/realisations';
const REAL_UPLOAD_URL = 'uploads/realisations';
const MAX_UPLOAD_BYTES = 4 * 1024 * 1024; // 4 Mo
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_SECONDS = 900; // 15 minutes

/** Services du site : clé => [libellé, page]. Les clés correspondent au formulaire de devis. */
const SERVICES = [
    'numerique'             => ['Impression numérique', 'impression-numerique.html'],
    'grand-format'          => ['Impression grand format', 'impression-grand-format.html'],
    'industrie-publicitaire'=> ['Industrie publicitaire', 'industrie-publicitaire.html'],
    'habillage-vehicule'    => ['Habillage de véhicule', 'habillage-vehicule.html'],
    'palissade-chantier'    => ['Impression palissade de chantier', 'palissade-chantier.html'],
    'vinyle'                => ['Impression sur vinyle', 'impression-vinyle.html'],
    'bache'                 => ['Impression sur bâche', 'impression-bache.html'],
    'creation'              => ['Design graphique', 'design-graphique.html'],
    'stands'                => ['Aménagement de stands d’exposition', 'stands-exposition.html'],
    'vitrine'               => ['Impression de vitrine', 'impression-vitrine.html'],
];

/* ---------- Session ---------- */
/*
 * Session sans stockage sur le serveur : l'état (utilisateur connecté, jeton CSRF, messages)
 * est gardé dans un cookie signé HMAC-SHA256. Aucun dossier de sessions n'est nécessaire ;
 * seule la clé secrète data/cle-session.php est créée automatiquement au premier accès.
 */
const SESSION_COOKIE = 'alloprint_admin';
const SESSION_IDLE = 7200; // déconnexion après 2 h d'inactivité

function session_key(): string
{
    static $key = null;
    if ($key !== null) return $key;
    $file = DATA_DIR . '/cle-session.php';
    for ($try = 0; $try < 2; $try++) {
        if (is_file($file)) {
            $k = include $file;
            if (is_string($k) && strlen($k) >= 64) return $key = $k;
        }
        $fh = @fopen($file, 'x'); // création exclusive : une seule clé, même en cas d'accès simultanés
        if ($fh) {
            fwrite($fh, "<?php return '" . bin2hex(random_bytes(32)) . "';\n");
            fclose($fh);
            @chmod($file, 0600);
        } elseif (!is_file($file)) {
            break;
        }
    }
    return $key = '';
}

function b64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function b64url_decode(string $s): string { return (string)base64_decode(strtr($s, '-_', '+/'), true); }

/** Lit et vérifie le cookie une fois par requête ; crée le jeton CSRF si besoin. */
function start_session(): void
{
    if (array_key_exists('ap_session', $GLOBALS)) return;
    $state = [];
    $raw = $_COOKIE[SESSION_COOKIE] ?? '';
    $GLOBALS['ap_cookie_received'] = is_string($raw) && $raw !== '';
    $key = session_key();
    if ($key !== '' && is_string($raw) && preg_match('/^([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]{43})$/', $raw, $m)) {
        if (hash_equals(b64url(hash_hmac('sha256', $m[1], $key, true)), $m[2])) {
            $data = json_decode(b64url_decode($m[1]), true);
            if (is_array($data)) $state = $data;
        }
    }
    $GLOBALS['ap_session'] = $state;
    // messages flash : affichés une seule fois
    $GLOBALS['ap_flash'] = is_array($state['f'] ?? null) ? $state['f'] : [];
    $changed = !empty($state['f']);
    unset($GLOBALS['ap_session']['f']);
    if (empty($GLOBALS['ap_session']['c']) || !is_string($GLOBALS['ap_session']['c'])) {
        $GLOBALS['ap_session']['c'] = bin2hex(random_bytes(32));
        $changed = true;
    }
    if ($changed) save_session();
}

/** Envoie le cookie signé (avant toute sortie HTML). */
function save_session(): void
{
    if (headers_sent() || ($key = session_key()) === '') return;
    $payload = b64url(json_encode($GLOBALS['ap_session'] ?? [], JSON_UNESCAPED_UNICODE));
    $value = $payload . '.' . b64url(hash_hmac('sha256', $payload, $key, true));
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    setcookie(SESSION_COOKIE, $value, ['expires' => 0, 'path' => site_base_path(), 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    $_COOKIE[SESSION_COOKIE] = $value;
}

/** Indique pourquoi la connexion ne peut pas tenir (diagnostic affiché à la connexion). */
function session_storage_problem(): ?string
{
    if (session_key() !== '') return null;
    return 'Le serveur ne peut pas créer la clé de connexion : rendez le dossier data/ modifiable par PHP (droits 755 dans le gestionnaire de fichiers cPanel), puis rechargez la page.';
}

function send_security_headers(): void
{
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    header('X-LiteSpeed-Cache-Control: no-cache');
}

/* ---------- Stockage JSON ---------- */
function data_path(string $name): string
{
    return DATA_DIR . '/' . $name . '.json';
}

function read_json(string $name, $default = [])
{
    $file = data_path($name);
    if (!is_file($file)) return $default;
    $fh = fopen($file, 'rb');
    if (!$fh) return $default;
    flock($fh, LOCK_SH);
    $raw = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : $default;
}

function write_json(string $name, $data): void
{
    if (!is_dir(DATA_DIR) && !mkdir(DATA_DIR, 0750, true)) {
        throw new RuntimeException('Impossible de créer le dossier data/.');
    }
    $file = data_path($name);
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException('Écriture impossible dans data/. Vérifiez les droits du dossier.');
    }
}

/* ---------- Authentification ---------- */
function admin_account(): ?array
{
    $a = read_json('admin', []);
    return !empty($a['username']) && !empty($a['password_hash']) ? $a : null;
}

/** Empreinte du compte : changer l'e-mail ou le mot de passe déconnecte les autres navigateurs. */
function account_fingerprint(): string
{
    $a = admin_account();
    return $a ? substr(hash_hmac('sha256', $a['username'] . '|' . $a['password_hash'], session_key()), 0, 32) : '';
}

function is_logged_in(): bool
{
    start_session();
    $s = $GLOBALS['ap_session'];
    return !empty($s['u']) && is_string($s['v'] ?? null) && account_fingerprint() !== ''
        && hash_equals(account_fingerprint(), $s['v']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        // La redirection elle-même ne doit pas être indexée (admin/ reste explorable dans robots.txt)
        send_security_headers();
        redirect_to('login');
    }
    // expiration après 2 h d'inactivité
    if (time() - (int)($GLOBALS['ap_session']['t'] ?? 0) > SESSION_IDLE) {
        logout();
        redirect_to('login?expire=1');
    }
    $GLOBALS['ap_session']['t'] = time();
    save_session();
}

function login(string $username): void
{
    start_session();
    $GLOBALS['ap_session'] = ['u' => $username, 'v' => account_fingerprint(), 't' => time(), 'c' => bin2hex(random_bytes(32))];
    save_session();
}

function logout(): void
{
    start_session();
    $GLOBALS['ap_session'] = ['c' => bin2hex(random_bytes(32))];
    save_session();
}

/** Limitation des tentatives de connexion par adresse IP. */
function client_key(): string
{
    return hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'inconnu');
}

function login_locked_for(): int
{
    $all = read_json('login-attempts', []);
    $e = $all[client_key()] ?? null;
    if (!$e || ($e['count'] ?? 0) < LOGIN_MAX_ATTEMPTS) return 0;
    $left = ($e['last'] ?? 0) + LOGIN_LOCK_SECONDS - time();
    return max(0, $left);
}

function register_login_failure(): void
{
    $all = read_json('login-attempts', []);
    $k = client_key();
    $e = $all[$k] ?? ['count' => 0, 'last' => 0];
    if (time() - $e['last'] > LOGIN_LOCK_SECONDS) $e['count'] = 0;
    $e['count']++;
    $e['last'] = time();
    $all[$k] = $e;
    // nettoyage des vieilles entrées
    foreach ($all as $key => $v) {
        if (time() - ($v['last'] ?? 0) > 86400) unset($all[$key]);
    }
    write_json('login-attempts', $all);
}

function clear_login_failures(): void
{
    $all = read_json('login-attempts', []);
    unset($all[client_key()]);
    write_json('login-attempts', $all);
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    start_session();
    return (string)$GLOBALS['ap_session']['c'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Vérifie le formulaire sans interrompre l'affichage de la page de connexion. */
function csrf_error(): ?string
{
    start_session();
    $sent = $_POST['csrf'] ?? '';
    if (is_string($sent) && $sent !== '' && !empty($GLOBALS['ap_cookie_received'])
        && hash_equals((string)$GLOBALS['ap_session']['c'], $sent)) {
        return null;
    }
    if ($why = session_storage_problem()) return $why;
    if (empty($GLOBALS['ap_cookie_received'])) {
        return 'Le cookie de connexion n’a pas été reçu. Autorisez les cookies pour ce site, puis renvoyez le formulaire.';
    }
    return 'Le formulaire a expiré ou la session a été renouvelée. Saisissez à nouveau votre mot de passe pour réessayer.';
}

function check_csrf(): void
{
    if (($error = csrf_error()) === null) return;
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    send_security_headers();
    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Formulaire expiré — AlloPrint</title><h1>Formulaire non enregistré</h1><p>' . e($error) . '</p>'
        . '<p>Aucune modification n’a été appliquée. Revenez au formulaire et rechargez la page avant de réessayer.</p>'
        . '<p><a href="' . e(site_base_path() . 'login') . '">Revenir à la connexion</a></p></html>';
    exit;
}

/* ---------- Messages flash ---------- */
function flash(string $type, string $msg): void
{
    start_session();
    $GLOBALS['ap_flash'][] = [$type, $msg];
    // conservé dans le cookie pour la page suivante (redirection)
    $GLOBALS['ap_session']['f'] = $GLOBALS['ap_flash'];
    save_session();
}

function take_flashes(): array
{
    start_session();
    $f = $GLOBALS['ap_flash'];
    $GLOBALS['ap_flash'] = [];
    if (isset($GLOBALS['ap_session']['f'])) { unset($GLOBALS['ap_session']['f']); save_session(); }
    return $f;
}

/* ---------- Utilitaires ---------- */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post_str(string $key, int $max = 10000): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) return '';
    $v = trim(str_replace("\r\n", "\n", $v));
    return mb_substr($v, 0, $max);
}

function slugify(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $map = ['à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ç'=>'c','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','í'=>'i','ô'=>'o','ö'=>'o','ó'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u','ÿ'=>'y','œ'=>'oe','æ'=>'ae','’'=>'-',"'"=>'-'];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? mb_substr($text, 0, 80) : 'article';
}

/* ---------- Articles ---------- */
function all_articles(): array
{
    $list = read_json('articles', []);
    foreach ($list as &$item) {
        // « noindex » peut avoir été enregistré comme texte ("False"/"True") : on le normalise en booléen
        $item['noindex'] = filter_var($item['noindex'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
    unset($item);
    usort($list, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    return $list;
}

/** Une seule règle de publication pour la liste, les articles et le sitemap. */
function published_articles(): array
{
    return array_values(array_filter(all_articles(), static function (array $article): bool {
        $date = $article['date'] ?? '';
        return ($article['statut'] ?? '') === 'publie'
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
            && checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])
            && $date <= date('Y-m-d');
    }));
}

function find_article(string $id): ?array
{
    foreach (read_json('articles', []) as $a) if (($a['id'] ?? '') === $id) return $a;
    return null;
}

function unique_slug(string $slug, string $exceptId = ''): string
{
    $taken = [];
    foreach (read_json('articles', []) as $a) if (($a['id'] ?? '') !== $exceptId) $taken[$a['slug']] = true;
    $base = $slug; $i = 2;
    while (isset($taken[$slug])) $slug = $base . '-' . $i++;
    return $slug;
}

/**
 * Mise en forme simple et sûre du contenu d'un article :
 * ## Titre, ### Sous-titre, - liste, **gras**, *italique*, [texte](https://lien), paragraphes.
 */
function render_content(string $src): string
{
    $inline = function (string $t): string {
        $t = e($t);
        $t = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $t);
        $t = preg_replace('/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/u', '<em>$1</em>', $t);
        $t = preg_replace_callback('/\[([^\]]+)\]\(((?:https?:\/\/|\/|mailto:|tel:|#|[A-Za-z0-9][A-Za-z0-9\/_.-]*(?=[?#)]))[^\s)]*)\)/u', function ($m) {
            $ext = str_starts_with($m[2], 'http');
            // Liens internes « page.html » → adresse propre (évite une redirection 301)
            $href = $ext ? $m[2] : preg_replace('~^(?:index\.html(?=$|[?#])|([A-Za-z0-9_-]+)\.html(?=$|[?#]))~', '$1', $m[2]);
            if ($href === '') $href = './';
            return '<a class="m-link" href="' . $href . '"' . ($ext ? ' target="_blank" rel="noopener"' : '') . '>' . $m[1] . '</a>';
        }, $t);
        return $t;
    };
    $out = []; $para = []; $list = [];
    $flushP = function () use (&$para, &$out, $inline) { if ($para) { $out[] = '<p>' . $inline(implode(' ', $para)) . '</p>'; $para = []; } };
    $flushL = function () use (&$list, &$out, $inline) { if ($list) { $out[] = '<ul>' . implode('', array_map(fn($i) => '<li>' . $inline($i) . '</li>', $list)) . '</ul>'; $list = []; } };
    foreach (explode("\n", $src) as $line) {
        $l = trim($line);
        if ($l === '') { $flushP(); $flushL(); continue; }
        if (preg_match('/^(#{2,3})\s+(.+)$/u', $l, $m)) { $flushP(); $flushL(); $tag = strlen($m[1]) === 2 ? 'h2' : 'h3'; $out[] = "<$tag>" . $inline($m[2]) . "</$tag>"; continue; }
        if (preg_match('/^[-•]\s+(.+)$/u', $l, $m)) { $flushP(); $list[] = $m[1]; continue; }
        $flushL(); $para[] = $l;
    }
    $flushP(); $flushL();
    return implode("\n", $out);
}

function format_date_fr(string $ymd): string
{
    $mois = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    $t = strtotime($ymd);
    if (!$t) return '';
    return (int)date('j', $t) . ' ' . $mois[(int)date('n', $t) - 1] . ' ' . date('Y', $t);
}

/** Enregistre une image envoyée ; renvoie le chemin public ou lève une exception. */
function save_uploaded_image(array $file, string $dir = UPLOAD_DIR, string $url = UPLOAD_URL): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Envoi de l’image échoué.');
    if ($file['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('Image trop lourde (4 Mo maximum).');
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) throw new RuntimeException('Format accepté : JPG, PNG ou WebP.');
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('Dossier uploads/ introuvable.');
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Impossible d’enregistrer l’image.');
    return $url . '/' . $name;
}

/* ---------- Réalisations (section « Nos réalisations » de l'accueil) ---------- */
/** Liste des réalisations, dans l'ordre d'affichage. Premier accès : reprise de celles déjà dans index.html. */
function all_realisations(): array
{
    $list = read_json('realisations', null);
    return is_array($list) ? array_values($list) : realisations_from_html();
}

function realisations_from_html(): array
{
    $html = (string)@file_get_contents(SITE_ROOT . '/index.html');
    preg_match_all('~<article class="edition">.*?<img[^>]*src="([^"]+)"[^>]*alt="([^"]*)".*?<h3[^>]*>(.*?)</h3>\s*<p class="edition__meta[^"]*">(.*?)</p>(?:.*?service=([a-z-]+))?.*?</article>~s', $html, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $row) {
        $titre = html_entity_decode(strip_tags($row[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $type = html_entity_decode(strip_tags($row[4]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $out[] = [
            'id' => substr(hash('sha256', $row[1]), 0, 16), 'titre' => $titre, 'type' => $type,
            'image' => html_entity_decode($row[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'alt' => html_entity_decode($row[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'service' => array_key_exists($row[5] ?? '', SERVICES) ? $row[5] : realisation_guess_service($type . ' ' . $titre),
            'visible' => true,
        ];
    }
    return $out;
}

function realisation_guess_service(string $text): string
{
    $t = mb_strtolower($text);
    foreach (['stand' => 'stands', 'véhicule' => 'habillage-vehicule', 'enseigne' => 'industrie-publicitaire', 'affiche' => 'grand-format',
        'vitrine' => 'vitrine', 'bâche' => 'bache', 'vinyle' => 'vinyle', 'palissade' => 'palissade-chantier', 'design' => 'creation'] as $word => $key) {
        if (str_contains($t, $word)) return $key;
    }
    return '';
}

/** HTML des réalisations visibles, réparties sur les 4 colonnes décalées de l'accueil. */
function render_realisations_html(array $items): string
{
    $items = array_values(array_filter($items, fn($r) => !empty($r['visible']) && !empty($r['image'])));
    $lanes = [[], [], [], []];
    $per = max(1, (int)ceil(count($items) / 4));
    foreach ($items as $i => $r) $lanes[min(3, intdiv($i, $per))][] = $r;
    $trail = '      <div class="editions__trail m-trail" aria-hidden="true"></div>' . "\n";
    $out = '';
    foreach ($lanes as $n => $lane) {
        $high = $n % 2 === 1;
        $out .= '    <div class="editions__lane editions__lane--' . ($high ? 'high' : 'low') . '">' . "\n" . ($high ? '' : $trail);
        foreach ($lane as $r) {
            [$w, $h] = [720, 805];
            $info = @getimagesize(SITE_ROOT . '/' . $r['image']);
            if ($info) [$w, $h] = [$info[0], $info[1]];
            $href = 'devis-impression' . (!empty($r['service']) && array_key_exists($r['service'], SERVICES) ? '?service=' . rawurlencode($r['service']) : '');
            $out .= '      <article class="edition">' . "\n"
                . '        <figure class="m-photo edition__photo"><img style="background:#e6e9ee" src="' . e($r['image']) . '" alt="' . e($r['alt'] ?: $r['titre']) . '" width="' . $w . '" height="' . $h . '" loading="lazy"></figure>' . "\n"
                . '        <h3 class="m-text-6">' . e($r['titre']) . '</h3>' . "\n"
                . ($r['type'] !== '' ? '        <p class="edition__meta m-text-4">' . e($r['type']) . '</p>' . "\n" : '')
                . '        <p class="edition__buy"><a class="m-link" href="' . e($href) . '">Un projet similaire ?</a></p>' . "\n"
                . '      </article>' . "\n";
        }
        $out .= ($high ? $trail : '') . '    </div>' . "\n";
    }
    return $out;
}

/** Enregistre la liste et met à jour la section de l'accueil (index.html). */
function save_realisations(array $items): void
{
    $file = SITE_ROOT . '/index.html';
    $html = file_get_contents($file);
    if ($html === false) throw new RuntimeException('Lecture impossible de index.html.');
    $re = '~(<!-- realisations:start[^>]*-->\n).*?(<!-- realisations:end -->)~s';
    if (!preg_match($re, $html)) throw new RuntimeException('Repères de la section Réalisations introuvables dans index.html.');
    $block = render_realisations_html($items);
    $html = preg_replace_callback($re, fn($m) => $m[1] . $block . $m[2], $html, 1);
    write_json('realisations', array_values($items));
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($tmp, $html, LOCK_EX) === false || !rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException('Écriture impossible dans index.html. Vérifiez les droits du fichier.');
    }
}

/* ---------- Prix ---------- */
function all_prices(): array
{
    $saved = read_json('prix', []);
    $out = [];
    foreach (SERVICES as $key => [$label, $page]) {
        $p = $saved[$key] ?? [];
        $out[$key] = [
            'label'   => $label,
            'page'    => $page,
            'prix'    => isset($p['prix']) && $p['prix'] !== '' ? (float)$p['prix'] : null,
            'mention' => $p['mention'] ?? 'À partir de',
            'unite'   => $p['unite'] ?? '',
            'taxe'    => $p['taxe'] ?? 'HT',
            'note'    => $p['note'] ?? '',
            'visible' => (bool)($p['visible'] ?? false),
        ];
    }
    return $out;
}

/** Pages statiques dont le titre et la description sont modifiables depuis l'admin. */
function seo_pages(): array
{
    $pages = ['index.html' => 'Accueil', 'services-impression.html' => 'Services d’impression', 'notre-imprimerie.html' => 'Notre atelier d’impression'];
    foreach (SERVICES as [$label, $page]) $pages[$page] = $label;
    $pages['contact-impression.html'] = 'Contact';
    $pages['devis-impression.html'] = 'Devis';
    return $pages;
}

/** Lit le titre et la description d'une page HTML statique. */
function read_page_meta(string $page): array
{
    $html = (string)@file_get_contents(SITE_ROOT . '/' . $page);
    preg_match('~<title>(.*?)</title>~s', $html, $t);
    preg_match('~<meta name="description" content="(.*?)">~s', $html, $d);
    return [
        'title' => html_entity_decode($t[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'description' => html_entity_decode($d[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    ];
}

/** Réécrit le titre, la description et les balises Open Graph d'une page HTML statique. */
function write_page_meta(string $page, string $title, string $desc): void
{
    if (!array_key_exists($page, seo_pages())) throw new RuntimeException('Page inconnue.');
    $file = SITE_ROOT . '/' . $page;
    $html = file_get_contents($file);
    if ($html === false) throw new RuntimeException('Lecture impossible de ' . $page . '.');
    $t = e($title); $d = e($desc);
    $html = preg_replace('~<title>.*?</title>~s', '<title>' . $t . '</title>', $html, 1);
    $html = preg_replace('~<meta name="description" content=".*?">~s', '<meta name="description" content="' . $d . '">', $html, 1);
    $html = preg_replace('~<meta property="og:title" content=".*?">~s', '<meta property="og:title" content="' . $t . '">', $html, 1);
    $html = preg_replace('~<meta property="og:description" content=".*?">~s', '<meta property="og:description" content="' . $d . '">', $html, 1);
    $html = preg_replace('~<meta property="og:image:alt" content=".*?">~s', '<meta property="og:image:alt" content="' . $t . '">', $html, 1);
    // données structurées : nom et description de la page (nœud WebPage)
    $html = preg_replace_callback('~(<script type="application/ld\+json">)(.*?)(</script>)~s', function ($m) use ($title, $desc) {
        $data = json_decode(str_replace('<\\/', '</', $m[2]), true);
        if (!is_array($data) || empty($data['@graph'])) return $m[0];
        foreach ($data['@graph'] as &$node) {
            if (isset($node['@id']) && str_ends_with((string)$node['@id'], '#webpage')) { $node['name'] = $title; $node['description'] = $desc; }
        }
        unset($node);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $m[1] . str_replace('</', '<\\/', (string)$json) . $m[3];
    }, $html, 1);
    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, $html, LOCK_EX) === false || !rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException('Écriture impossible dans ' . $page . '. Vérifiez les droits du fichier.');
    }
}

function format_price(float $v): string
{
    $dec = floor($v) == $v ? 0 : 2;
    return number_format($v, $dec, ',', ' ');
}

/* ---------- E-mail (SMTP via PHPMailer) ---------- */
function smtp_settings(): array
{
    $s = read_json('smtp', []);
    return [
        'host'       => (string)($s['host'] ?? ''),
        'port'       => (int)($s['port'] ?? 465),
        'secure'     => in_array($s['secure'] ?? 'ssl', ['ssl', 'tls', 'none'], true) ? ($s['secure'] ?? 'ssl') : 'ssl',
        'username'   => (string)($s['username'] ?? 'contact@alloprint.ma'),
        'password'   => (string)($s['password'] ?? ''),
        'from_email' => (string)($s['from_email'] ?? 'contact@alloprint.ma'),
        'from_name'  => (string)($s['from_name'] ?? 'AlloPrint'),
        'to_email'   => (string)($s['to_email'] ?? 'contact@alloprint.ma'),
        'confirm'    => (bool)($s['confirm'] ?? true),
    ];
}

function smtp_ready(): bool
{
    $s = smtp_settings();
    return $s['host'] !== '' && $s['username'] !== '' && $s['password'] !== '';
}

/**
 * Envoie un e-mail via SMTP. Lève RuntimeException avec un message lisible en cas d'échec.
 * $to : adresse ou liste d'adresses séparées par des virgules.
 */
function send_mail(string $to, string $subject, string $html, string $text, ?array $replyTo = null): void
{
    require_once __DIR__ . '/vendor/phpmailer/Exception.php';
    require_once __DIR__ . '/vendor/phpmailer/PHPMailer.php';
    require_once __DIR__ . '/vendor/phpmailer/SMTP.php';
    $s = smtp_settings();
    if (!smtp_ready()) throw new RuntimeException('Le SMTP n’est pas encore configuré (Admin › Messagerie SMTP).');
    $m = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $m->isSMTP();
        $m->Host = $s['host'];
        $m->Port = $s['port'];
        $m->SMTPAuth = true;
        $m->Username = $s['username'];
        $m->Password = $s['password'];
        if ($s['secure'] === 'ssl') $m->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        elseif ($s['secure'] === 'tls') $m->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        else { $m->SMTPSecure = ''; $m->SMTPAutoTLS = false; }
        $m->Timeout = 15;
        $m->CharSet = 'UTF-8';
        $m->setFrom($s['from_email'] ?: $s['username'], $s['from_name']);
        foreach (array_filter(array_map('trim', explode(',', $to))) as $addr) $m->addAddress($addr);
        if ($replyTo && filter_var($replyTo[0], FILTER_VALIDATE_EMAIL)) $m->addReplyTo($replyTo[0], $replyTo[1] ?? '');
        $m->isHTML(true);
        $m->Subject = $subject;
        $m->Body = $html;
        $m->AltBody = $text;
        $m->send();
    } catch (\PHPMailer\PHPMailer\Exception $ex) {
        throw new RuntimeException('Envoi impossible : ' . $m->ErrorInfo);
    }
}

/** Gabarit HTML simple aux couleurs du site pour les e-mails. */
function mail_layout(string $title, string $bodyHtml): string
{
    return '<!doctype html><html lang="fr"><body style="margin:0;background:#f3f3fa;font-family:Arial,Helvetica,sans-serif;color:#06222a">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f3fa;padding:24px 0"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden">'
        . '<tr><td style="background:#00677a;padding:22px 28px;color:#ffffff;font-size:20px;font-weight:bold">AlloPrint <span style="color:#b9e533">·</span> ' . e($title) . '</td></tr>'
        . '<tr><td style="padding:26px 28px;font-size:15px;line-height:1.6">' . $bodyHtml . '</td></tr>'
        . '<tr><td style="padding:16px 28px;background:#f3f3fa;font-size:12px;color:#5b6b72">AlloPrint · 3, avenue 2 Mars, Résidence Marwa, 5e étage, Casablanca · 05 22 48 44 25</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Adresse absolue d'une page de l'admin (pour les liens envoyés par e-mail). */
function admin_url(string $page): string
{
    return site_abs_url('admin/' . $page);
}

/**
 * Chemin web de la racine du site (« / » en ligne, « /alloprint/site/ » en local par exemple),
 * calculé à partir du script en cours : fonctionne aussi derrière les adresses propres (/login, /article/…).
 */
function site_base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
    $file = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '';
    $root = realpath(SITE_ROOT) ?: '';
    $rel = ($file && $root && str_starts_with($file, $root)) ? str_replace('\\', '/', substr($file, strlen($root))) : '';
    $base = ($rel !== '' && str_ends_with($script, $rel)) ? substr($script, 0, -strlen($rel)) : rtrim(dirname($script), '/');
    $base = rtrim($base, '/') . '/';
    return $base;
}

/** Adresse absolue (avec domaine) d'un chemin du site, ex. site_abs_url('login'). */
function site_abs_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', $_SERVER['HTTP_HOST'] ?? 'alloprint.ma');
    return ($https ? 'https' : 'http') . '://' . $host . site_base_path() . ltrim($path, '/');
}

function redirect_to(string $path): void
{
    header('Location: ' . site_base_path() . ltrim($path, '/'));
    exit;
}
