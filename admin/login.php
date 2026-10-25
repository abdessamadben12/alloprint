<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
start_session();
send_security_headers();

if (is_logged_in()) { header('Location: index.php'); exit; }

$account = admin_account();
$setup = $account === null;           // premier accès : création du compte
$error = '';
$locked = $setup ? 0 : login_locked_for();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $user = post_str('username', 60);
    $pass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if ($setup) {
        $pass2 = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';
        if (!preg_match('/^[A-Za-z0-9._@-]{3,60}$/', $user)) {
            $error = 'Identifiant : 3 caractères minimum (lettres, chiffres, . _ - @).';
        } elseif (mb_strlen($pass) < 10) {
            $error = 'Le mot de passe doit contenir au moins 10 caractères.';
        } elseif ($pass !== $pass2) {
            $error = 'Les deux mots de passe ne correspondent pas.';
        } else {
            write_json('admin', ['username' => $user, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'created' => date('c')]);
            login($user);
            flash('ok', 'Compte administrateur créé. Bienvenue !');
            header('Location: index.php');
            exit;
        }
    } elseif ($locked > 0) {
        $error = 'Trop de tentatives. Réessayez dans ' . ceil($locked / 60) . ' minute(s).';
    } elseif (hash_equals($account['username'], $user) && password_verify($pass, $account['password_hash'])) {
        clear_login_failures();
        if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
            $account['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
            write_json('admin', $account);
        }
        login($user);
        header('Location: index.php');
        exit;
    } else {
        register_login_failure();
        usleep(400000);
        $error = 'Identifiant ou mot de passe incorrect.';
        $locked = login_locked_for();
    }
}
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $setup ? 'Créer le compte' : 'Connexion' ?> — Admin AlloPrint</title>
<link rel="icon" type="image/png" href="../images/logo.png">
<link rel="stylesheet" href="admin.css">
</head>
<body class="login">
<main class="card">
  <img src="../images/logo.png" alt="AlloPrint" width="96" height="77">
  <h1><?= $setup ? 'Créer le compte admin' : 'Espace administration' ?></h1>
  <p class="lead"><?= $setup ? 'Premier accès : choisissez votre identifiant et votre mot de passe.' : 'Connectez-vous pour gérer les articles et les prix.' ?></p>
  <?php if (isset($_GET['expire'])): ?><p class="adm-flash adm-flash--error">Session expirée, reconnectez-vous.</p><?php endif; ?>
  <?php if ($error): ?><p class="adm-flash adm-flash--error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <div class="field">
      <label for="username">Identifiant</label>
      <input id="username" name="username" type="text" required autocomplete="username" autofocus value="<?= e(post_str('username', 60)) ?>">
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
  <p style="text-align:center;margin:18px 0 0"><a href="../index.html">← Retour au site</a></p>
</main>
</body>
</html>
