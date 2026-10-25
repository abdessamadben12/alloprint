<?php
/**
 * Réception des formulaires « Devis » et « Contact ».
 * Enregistre la demande (Admin › Messages reçus) puis l'envoie par e-mail via le SMTP configuré.
 */
declare(strict_types=1);
require __DIR__ . '/../admin/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function reply(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(405, ['ok' => false, 'error' => 'Méthode non autorisée.']);

// même origine uniquement
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    reply(403, ['ok' => false, 'error' => 'Origine non autorisée.']);
}

// anti-spam : champ piège + délai minimum de remplissage
if (post_str('website', 200) !== '') reply(200, ['ok' => true]);
$started = (int)post_str('t', 20);
if ($started > 0 && (int)(microtime(true) * 1000) - $started < 2500) reply(429, ['ok' => false, 'error' => 'Envoi trop rapide, réessayez dans quelques secondes.']);

// limite : 5 envois par 15 minutes et par adresse IP
$rate = read_json('form-rate', []);
$key = client_key(); $now = time();
$hits = array_values(array_filter($rate[$key] ?? [], fn($t) => $now - $t < 900));
if (count($hits) >= 5) reply(429, ['ok' => false, 'error' => 'Trop de demandes envoyées. Réessayez dans quelques minutes ou appelez le 05 22 48 44 25.']);

$type = post_str('type', 10) === 'contact' ? 'contact' : 'devis';
$f = [
    'name' => post_str('name', 120), 'email' => post_str('email', 160), 'phone' => post_str('phone', 40),
    'company' => post_str('company', 120), 'subject' => post_str('subject', 160), 'service' => post_str('service', 40),
    'quantity' => post_str('quantity', 80), 'format' => post_str('format', 80), 'deadline' => post_str('deadline', 10),
    'details' => post_str('details', 5000),
];
$errors = [];
if (mb_strlen($f['name']) < 2) $errors[] = 'Indiquez votre nom.';
if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse e-mail invalide.';
if (mb_strlen($f['details']) < 10) $errors[] = 'Décrivez votre demande en quelques mots.';
if ($type === 'contact' && mb_strlen($f['subject']) < 3) $errors[] = 'Indiquez le sujet de votre message.';
if (empty($_POST['consent'])) $errors[] = 'Merci d’accepter l’utilisation de vos coordonnées.';
if ($errors) reply(422, ['ok' => false, 'error' => implode(' ', $errors)]);

$serviceLabel = $f['service'] === 'autre' ? 'Autre / je ne sais pas encore' : (SERVICES[$f['service']][0] ?? '');
$labels = ['name' => 'Nom', 'email' => 'E-mail', 'phone' => 'Téléphone', 'company' => 'Entreprise', 'subject' => 'Sujet',
    'service' => 'Service', 'quantity' => 'Quantité', 'format' => 'Format', 'deadline' => 'Date souhaitée', 'details' => 'Message'];
$rows = [];
foreach ($labels as $k => $label) {
    $v = $k === 'service' ? $serviceLabel : ($k === 'deadline' && $f[$k] ? format_date_fr($f[$k]) : $f[$k]);
    if ($v !== '') $rows[$label] = $v;
}

// enregistrement (sauvegarde même si l'e-mail échoue)
$msg = ['id' => bin2hex(random_bytes(8)), 'type' => $type, 'date' => date('c'), 'fields' => $rows, 'sent' => false, 'error' => '', 'lu' => false];
$all = read_json('messages', []);
array_unshift($all, $msg);
$all = array_slice($all, 0, 1000);
write_json('messages', $all);
$hits[] = $now; $rate[$key] = $hits;
foreach ($rate as $k => $v) if (!array_filter($v, fn($t) => $now - $t < 900)) unset($rate[$k]);
write_json('form-rate', $rate);

// e-mail à AlloPrint
$title = $type === 'devis' ? 'Nouvelle demande de devis' : 'Nouveau message de contact';
$table = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse">';
foreach ($rows as $label => $v) {
    $table .= '<tr><td style="padding:8px 10px;border-bottom:1px solid #e3e8eb;font-weight:bold;width:150px;vertical-align:top;color:#00677a">' . e($label) . '</td>'
        . '<td style="padding:8px 10px;border-bottom:1px solid #e3e8eb">' . nl2br(e($v)) . '</td></tr>';
}
$table .= '</table>';
$text = $title . "\n\n";
foreach ($rows as $label => $v) $text .= "$label : $v\n";
$subject = $title . ' — ' . $f['name'] . ($serviceLabel && $type === 'devis' ? ' (' . $serviceLabel . ')' : ($f['subject'] ? ' — ' . $f['subject'] : ''));

$sent = false; $error = '';
try {
    $s = smtp_settings();
    send_mail($s['to_email'], $subject, mail_layout($title, $table . '<p style="margin-top:18px">Répondez directement à cet e-mail pour écrire au client.</p>'), $text, [$f['email'], $f['name']]);
    $sent = true;
    if ($s['confirm']) {
        $ack = '<p>Bonjour ' . e($f['name']) . ',</p><p>Merci pour votre ' . ($type === 'devis' ? 'demande de devis' : 'message') . '. Nous l’avons bien reçu et nous vous répondons au plus vite, en général sous 24 h ouvrées.</p>'
            . '<p>Pour toute urgence : 05 22 48 44 25 ou WhatsApp 06 68 74 63 86.</p><p style="margin-top:18px"><b>Récapitulatif</b></p>' . $table . '<p>L’équipe AlloPrint</p>';
        try { send_mail($f['email'], 'AlloPrint — nous avons bien reçu votre ' . ($type === 'devis' ? 'demande de devis' : 'message'), mail_layout('Demande bien reçue', $ack), "Merci, nous avons bien reçu votre demande. L'équipe AlloPrint."); } catch (RuntimeException $ex) { /* accusé facultatif */ }
    }
} catch (RuntimeException $ex) {
    $error = $ex->getMessage();
}
if (!$sent) {
    $all = read_json('messages', []);
    foreach ($all as &$m) if ($m['id'] === $msg['id']) { $m['error'] = $error; }
    unset($m);
    write_json('messages', $all);
} else {
    $all = read_json('messages', []);
    foreach ($all as &$m) if ($m['id'] === $msg['id']) { $m['sent'] = true; }
    unset($m);
    write_json('messages', $all);
}

reply(200, ['ok' => true, 'sent' => $sent]);
