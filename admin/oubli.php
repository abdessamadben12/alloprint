<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
start_session();
send_security_headers();

$done = false; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = mb_strtolower(post_str('email', 120));
    $account = admin_account();
    if (login_locked_for() > 0) {
        $error = 'Trop de tentatives. Réessayez dans 15 minutes.';
    } elseif (!smtp_ready()) {
        $error = 'L’envoi d’e-mails n’est pas encore configuré. Pour réinitialiser l’accès, supprimez le fichier data/admin.json sur le serveur.';
    } else {
        register_login_failure(); // limite aussi le nombre de demandes
        if ($account && hash_equals(mb_strtolower($account['username']), $email)) {
            $token = bin2hex(random_bytes(32));
            write_json('reset', ['hash' => hash('sha256', $token), 'expires' => time() + 3600]);
            $link = admin_url('reinitialiser?token=' . $token);
            try {
                send_mail($account['username'], 'Réinitialisation du mot de passe — admin AlloPrint',
                    mail_layout('Mot de passe oublié', '<p>Une réinitialisation du mot de passe de l’espace d’administration a été demandée.</p>'
                        . '<p><a href="' . e($link) . '" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#b9e533;color:#06222a;font-weight:bold;text-decoration:none">Choisir un nouveau mot de passe</a></p>'
                        . '<p>Ce lien est valable 1 heure. Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail.</p>'),
                    "Choisissez un nouveau mot de passe (lien valable 1 heure) : $link");
            } catch (RuntimeException $ex) {
                $error = $ex->getMessage();
            }
        }
        if (!$error) $done = true;
    }
}
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<base href="<?= e(site_base_path()) ?>admin/">
<title>Mot de passe oublié — Admin AlloPrint</title>
<link rel="stylesheet" href="admin.css">
</head>
<body class="login">
<main class="card">
  <img src="../images/logo.png" alt="AlloPrint" width="96" height="77">
  <h1>Mot de passe oublié</h1>
  <?php if ($done): ?>
    <p class="adm-flash adm-flash--ok">Si cette adresse correspond au compte administrateur, un lien de réinitialisation vient d’être envoyé. Pensez à vérifier les spams.</p>
  <?php else: ?>
    <p class="lead">Indiquez l’adresse e-mail de connexion : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
    <?php if ($error): ?><p class="adm-flash adm-flash--error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="field"><label for="email">Adresse e-mail</label><input id="email" name="email" type="email" required autocomplete="username" placeholder="contact@alloprint.ma"></div>
      <button class="btn btn--dark" type="submit" style="width:100%">Envoyer le lien</button>
    </form>
  <?php endif; ?>
  <p style="text-align:center;margin:18px 0 0"><a href="../login">← Retour à la connexion</a></p>
</main>
</body>
</html>
