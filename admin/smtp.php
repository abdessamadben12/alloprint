<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_login();

$s = smtp_settings();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'save') {
        $errors = [];
        $host = post_str('host', 120);
        $port = (int)post_str('port', 6);
        $secure = post_str('secure', 5);
        $user = post_str('username', 120);
        $pass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $from = post_str('from_email', 120);
        $fromName = post_str('from_name', 80);
        $toList = array_filter(array_map('trim', explode(',', post_str('to_email', 400))));
        if ($host !== '' && !preg_match('/^[A-Za-z0-9.-]+$/', $host)) $errors[] = 'Serveur SMTP invalide.';
        if ($port < 1 || $port > 65535) $errors[] = 'Port invalide.';
        if ($user !== '' && !filter_var($user, FILTER_VALIDATE_EMAIL) && !preg_match('/^[A-Za-z0-9._@-]+$/', $user)) $errors[] = 'Identifiant SMTP invalide.';
        if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse d’expédition invalide.';
        foreach ($toList as $t) if (!filter_var($t, FILTER_VALIDATE_EMAIL)) $errors[] = "Adresse de réception invalide : $t";
        if (!$toList) $errors[] = 'Indiquez au moins une adresse de réception.';
        if ($errors) {
            foreach ($errors as $er) flash('error', $er);
        } else {
            $new = [
                'host' => $host, 'port' => $port, 'secure' => in_array($secure, ['ssl', 'tls', 'none'], true) ? $secure : 'ssl',
                'username' => $user,
                'password' => !empty($_POST['clear_password']) ? '' : ($pass !== '' ? $pass : $s['password']),
                'from_email' => $from ?: $user, 'from_name' => $fromName ?: 'AlloPrint',
                'to_email' => implode(', ', $toList), 'confirm' => !empty($_POST['confirm']),
            ];
            write_json('smtp', $new);
            flash('ok', 'Réglages SMTP enregistrés.' . (smtp_ready() ? ' Envoyez un e-mail de test pour vérifier.' : ''));
        }
    } elseif ($action === 'test') {
        try {
            $to = post_str('test_to', 120) ?: $s['to_email'];
            if (!filter_var(trim(explode(',', $to)[0]), FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Adresse de test invalide.');
            send_mail($to, 'Test SMTP — site AlloPrint',
                mail_layout('E-mail de test', '<p>Bonne nouvelle : l’envoi d’e-mails depuis le site fonctionne.</p><p>Les demandes de devis et les messages du formulaire de contact arriveront à cette adresse.</p>'),
                "L’envoi d’e-mails depuis le site AlloPrint fonctionne.");
            flash('ok', 'E-mail de test envoyé à ' . $to . '. Vérifiez la boîte de réception (et les spams).');
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
        }
    }
    redirect_to('admin/smtp');
}

admin_header('Messagerie (SMTP)', 'smtp');
?>
<p>Ces réglages servent à envoyer les demandes de devis et les messages du formulaire de contact par e-mail. Les informations se trouvent dans l’espace e-mail de votre hébergeur (cPanel › Comptes e-mail › « Configurer le client de messagerie »).</p>
<p class="adm-flash <?= smtp_ready() ? 'adm-flash--ok' : 'adm-flash--error' ?>"><?= smtp_ready() ? 'SMTP configuré.' : 'SMTP pas encore configuré : les demandes sont tout de même enregistrées dans « Messages reçus ».' ?></p>
<form method="post" class="card">
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <h2>Compte d’envoi</h2>
  <div class="row">
    <div class="field">
      <label for="username">Adresse e-mail (identifiant SMTP)</label>
      <input id="username" name="username" type="text" autocomplete="off" value="<?= e($s['username']) ?>" placeholder="contact@alloprint.ma">
    </div>
    <div class="field">
      <label for="password">Mot de passe de l’e-mail <span class="hint"><?= $s['password'] !== '' ? 'Enregistré. Laissez vide pour le conserver.' : 'Pas encore enregistré.' ?></span></label>
      <input id="password" name="password" type="password" autocomplete="new-password" placeholder="<?= $s['password'] !== '' ? '••••••••••' : '' ?>">
      <?php if ($s['password'] !== ''): ?><label class="check" style="margin-top:8px;font-weight:400"><input type="checkbox" name="clear_password" value="1"> Effacer le mot de passe enregistré</label><?php endif; ?>
    </div>
  </div>
  <div class="row">
    <div class="field">
      <label for="host">Serveur SMTP <span class="hint">Souvent mail.alloprint.ma</span></label>
      <input id="host" name="host" type="text" value="<?= e($s['host']) ?>" placeholder="mail.alloprint.ma">
    </div>
    <div class="field">
      <label for="secure">Sécurité</label>
      <select id="secure" name="secure">
        <option value="ssl"<?= $s['secure'] === 'ssl' ? ' selected' : '' ?>>SSL (port 465)</option>
        <option value="tls"<?= $s['secure'] === 'tls' ? ' selected' : '' ?>>TLS / STARTTLS (port 587)</option>
        <option value="none"<?= $s['secure'] === 'none' ? ' selected' : '' ?>>Aucune (déconseillé)</option>
      </select>
    </div>
    <div class="field">
      <label for="port">Port</label>
      <input id="port" name="port" type="number" min="1" max="65535" value="<?= e((string)$s['port']) ?>">
    </div>
  </div>
  <h2 style="margin-top:8px">Expédition et réception</h2>
  <div class="row">
    <div class="field">
      <label for="from_email">Adresse d’expédition <span class="hint">En général la même que l’identifiant.</span></label>
      <input id="from_email" name="from_email" type="email" value="<?= e($s['from_email']) ?>">
    </div>
    <div class="field">
      <label for="from_name">Nom de l’expéditeur</label>
      <input id="from_name" name="from_name" type="text" value="<?= e($s['from_name']) ?>">
    </div>
  </div>
  <div class="field">
    <label for="to_email">Recevoir les demandes sur <span class="hint">Une ou plusieurs adresses séparées par des virgules.</span></label>
    <input id="to_email" name="to_email" type="text" value="<?= e($s['to_email']) ?>">
  </div>
  <label class="check" style="margin-bottom:18px"><input type="checkbox" name="confirm" value="1"<?= $s['confirm'] ? ' checked' : '' ?>> Envoyer un accusé de réception automatique au client</label>
  <button class="btn btn--dark" type="submit">Enregistrer</button>
</form>
<form method="post" class="card">
  <?= csrf_field() ?><input type="hidden" name="action" value="test">
  <h2>Tester l’envoi</h2>
  <div class="actions">
    <input name="test_to" type="email" value="<?= e(explode(',', $s['to_email'])[0]) ?>" style="max-width:340px" aria-label="Adresse de test">
    <button class="btn" type="submit"<?= smtp_ready() ? '' : ' disabled' ?>>Envoyer un e-mail de test</button>
  </div>
</form>
<script>
document.getElementById('secure').addEventListener('change', e => {
  const port = document.getElementById('port');
  port.value = { ssl: 465, tls: 587, none: 25 }[e.target.value];
});
</script>
<?php admin_footer();
