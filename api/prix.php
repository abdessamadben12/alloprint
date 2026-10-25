<?php
/** Prix publics des services (JSON), lus par js/prix.js sur les pages du site. */
declare(strict_types=1);
require __DIR__ . '/../admin/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');

$out = [];
foreach (all_prices() as $key => $p) {
    if (!$p['visible'] || $p['prix'] === null) continue;
    $out[$key] = [
        'mention' => $p['mention'],
        'prix' => format_price($p['prix']),
        'unite' => $p['unite'],
        'taxe' => $p['taxe'],
        'note' => $p['note'],
    ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
