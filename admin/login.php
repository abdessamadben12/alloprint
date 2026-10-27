<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
start_session();
send_security_headers();

if (is_logged_in()) { redirect_to('admin/'); }

$account = admin_account();
$setup = $account === null;           // premier accès : création du compte
$error = '';
$locked = $setup ? 0 : login_locked_for();

$csrfError = $_SERVER['REQUEST_METHOD'] === 'POST' ? csrf_error() : null;
if ($csrfError !== null) {
    http_response_code(403);
    $error = $csrfError;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $csrfError === null) {
    $user = post_str('username', 120);
    $pass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if ($setup) {
        $pass2 = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';
        if (!filter_var($user, FILTER_VALIDATE_EMAIL)) {
            $error = 'Indiquez une adresse e-mail valide.';
        } elseif (mb_strlen($pass) < 10) {
            $error = 'Le mot de passe doit contenir au moins 10 caractères.';
        } elseif ($pass !== $pass2) {
            $error = 'Les deux mots de passe ne correspondent pas.';
        } else {
            write_json('admin', ['username' => $user, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'created' => date('c')]);
            login($user);
            flash('ok', 'Compte administrateur créé. Bienvenue !');
            redirect_to('admin/');
        }
    } elseif ($locked > 0) {
        $error = 'Trop de tentatives. Réessayez dans ' . ceil($locked / 60) . ' minute(s).';
    } elseif (hash_equals(mb_strtolower($account['username']), mb_strtolower($user)) && password_verify($pass, $account['password_hash'])) {
        clear_login_failures();
        if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
            $account['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
            write_json('admin', $account);
        }
        login($user);
        redirect_to('admin/');
    } else {
        register_login_failure();
        usleep(400000);
        $error = 'E-mail ou mot de passe incorrect.';
        $locked = login_locked_for();
    }
}
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<base href="<?= e(site_base_path()) ?>admin/">
<title><?= $setup ? 'Créer le compte' : 'Connexion' ?> — Admin AlloPrint</title>
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
  <h1><?= $setup ? 'Créer le compte admin' : 'Espace administration' ?></h1>
  <p class="lead"><?= $setup ? 'Premier accès : choisissez l’adresse e-mail de connexion et le mot de passe.' : 'Connectez-vous pour gérer les articles et les prix.' ?></p>
  <?php if (isset($_GET['reset'])): ?><p class="adm-flash adm-flash--ok">Mot de passe modifié. Vous pouvez vous connecter.</p><?php endif; ?>
  <?php if (isset($_GET['expire'])): ?><p class="adm-flash adm-flash--error">Session expirée, reconnectez-vous.</p><?php endif; ?>
  <?php if ($error): ?><p class="adm-flash adm-flash--error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if ($why = session_storage_problem()): ?><p class="adm-flash adm-flash--error" role="alert"><?= e($why) ?></p><?php endif; ?>
  <form method="post" action="<?= e(site_base_path() . 'login') ?>" autocomplete="on">
    <?= csrf_field() ?>
    <div class="field">
      <label for="username">Adresse e-mail</label>
      <input id="username" name="username" type="email" required autocomplete="username" autofocus placeholder="contact@alloprint.ma" value="<?= e(post_str('username', 120)) ?>">
    </div>
    <div class="field">
      <label for="password">Mot de passe<?php if ($setup): ?><span class="hint">10 caractères minimum.</span><?php endif; ?></label>
      <input id="password" name="password" type="password" required autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>" <?= $setup ? 'minlength="10"' : '' ?>>
    </div>
    <?php if ($setup): ?>
    <div class="field">
      <label for="password2">Confirmer le mot de passe</label>
      <input id="password2" name="password2" type="password" required autocomplete="new-password" minlength="10">
    </div>
    <?php endif; ?>
    <button class="btn btn--dark" type="submit" style="width:100%"><?= $setup ? 'Créer mon compte' : 'Se connecter' ?></button>
  </form>
  <?php if (!$setup): ?><p style="text-align:center;margin:16px 0 0"><a href="oubli">Mot de passe oublié ?</a></p><?php endif; ?>
  <p style="text-align:center;margin:10px 0 0"><a href="../">← Retour au site</a></p>
</main>
</body>
</html>
