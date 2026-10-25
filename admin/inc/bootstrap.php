<?php
/**
 * AlloPrint — socle de l'espace d'administration.
 * Stockage en fichiers JSON (dossier data/, protégé par .htaccess) : aucune base de données à configurer.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../inc/config.php';

const SITE_ROOT = __DIR__ . '/../..';
const DATA_DIR = SITE_ROOT . '/data';
const UPLOAD_DIR = SITE_ROOT . '/uploads/articles';
const UPLOAD_URL = 'uploads/articles';
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
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('alloprint_admin');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $secure,
        'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();
}

function send_security_headers(): void
{
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
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

function is_logged_in(): bool
{
    start_session();
    return !empty($_SESSION['admin_user']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    // expiration après 2 h d'inactivité
    if (!empty($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 7200) {
        logout();
        header('Location: login.php?expire=1');
        exit;
    }
    $_SESSION['last_seen'] = time();
}

function login(string $username): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['admin_user'] = $username;
    $_SESSION['last_seen'] = time();
}

function logout(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
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
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    start_session();
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(400);
        exit('Session expirée ou requête invalide. Rechargez la page et réessayez.');
    }
}

/* ---------- Messages flash ---------- */
function flash(string $type, string $msg): void
{
    start_session();
    $_SESSION['flash'][] = [$type, $msg];
}

function take_flashes(): array
{
    start_session();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
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
    usort($list, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    return $list;
}

/** Une seule règle de publication pour la liste, les articles et le sitemap. */
function published_articles(): array
{
    return array_values(array_filter(all_articles(), static function (array $article): bool {
        $date = $article['date'] ?? '';
        return ($article['statut'] ?? '') === 'publie'
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
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
        $t = preg_replace_callback('/\[([^\]]+)\]\(((?:https?:\/\/|\/|mailto:|tel:|#|[A-Za-z0-9_-]+\.(?:html|php)\b)[^\s)]*)\)/u', function ($m) {
            $ext = str_starts_with($m[2], 'http');
            return '<a class="m-link" href="' . $m[2] . '"' . ($ext ? ' target="_blank" rel="noopener"' : '') . '>' . $m[1] . '</a>';
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
function save_uploaded_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Envoi de l’image échoué.');
    if ($file['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('Image trop lourde (4 Mo maximum).');
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) throw new RuntimeException('Format accepté : JPG, PNG ou WebP.');
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) throw new RuntimeException('Dossier uploads/ introuvable.');
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) throw new RuntimeException('Impossible d’enregistrer l’image.');
    return UPLOAD_URL . '/' . $name;
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
    $pages = ['index.html' => 'Accueil', 'services.html' => 'Services d’impression', 'notre-imprimerie.html' => 'Notre atelier d’impression'];
    foreach (SERVICES as [$label, $page]) $pages[$page] = $label;
    $pages['contact.html'] = 'Contact';
    $pages['devis.html'] = 'Devis';
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
