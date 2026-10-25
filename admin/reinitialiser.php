<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
start_session();
send_security_headers();

$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : (is_string($_POST['token'] ?? null) ? $_POST['token'] : '');
$reset = read_json('reset', []);
$valid = $token !== '' && !empty($reset['hash']) && ($reset['expires'] ?? 0) > time() && hash_equals($reset['hash'], hash('sha256', $token));
$error = '';
if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $p1 = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $p2 = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';
    if (mb_strlen($p1) < 10) $error = 'Le mot de passe doit contenir au moins 10 caractères.';
    elseif ($p1 !== $p2) $error = 'Les deux mots de passe ne correspondent pas.';
    else {
        $account = admin_account();
        $account['password_hash'] = password_hash($p1, PASSWORD_DEFAULT);
        $account['updated'] = date('c');
        write_json('admin', $account);
        write_json('reset', []);
        clear_login_failures();
        redirect_to('login?reset=1');
    }
}
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<base href="<?= e(site_base_path()) ?>admin/">
<title>Nouveau mot de passe — Admin AlloPrint</title>
<link rel="icon" href="../favicon.ico?v=2" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32.png?v=2">
<link rel="apple-touch-icon" href="../images/favicon/apple-touch-icon.png?v=2">
<link rel="manifest" href="../site.webmanifest">
<meta name="theme-color" content="#00677a">
<link rel="stylesheet" href="admin.css">
</head>
<body class="login">
<main class="card">
  <img src="../images/logo.png" alt="AlloPrint" width="96" height="77">
  <h1>Nouveau mot de passe</h1>
  <?php if (!$valid): ?>
    <p class="adm-flash adm-flash--error">Ce lien n’est plus valable. Faites une nouvelle demande.</p>
    <p style="text-align:center"><a href="oubli">Mot de passe oublié</a></p>
  <?php else: ?>
    <?php if ($error): ?><p class="adm-flash adm-flash--error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="field"><label for="password">Nouveau mot de passe <span class="hint">10 caractères minimum.</span></label><input id="password" name="password" type="password" required minlength="10" autocomplete="new-password"></div>
      <div class="field"><label for="password2">Confirmer</label><input id="password2" name="password2" type="password" required minlength="10" autocomplete="new-password"></div>
      <button class="btn btn--dark" type="submit" style="width:100%">Enregistrer</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
