<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_login();

$account = admin_account();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $current = is_string($_POST['current'] ?? null) ? $_POST['current'] : '';
    $user = mb_strtolower(post_str('username', 120));
    $new = is_string($_POST['new'] ?? null) ? $_POST['new'] : '';
    $new2 = is_string($_POST['new2'] ?? null) ? $_POST['new2'] : '';
    if (!$account || !password_verify($current, $account['password_hash'])) {
        $error = 'Le mot de passe actuel est incorrect.';
    } elseif (!filter_var($user, FILTER_VALIDATE_EMAIL)) {
        $error = 'Indiquez une adresse e-mail valide.';
    } elseif ($new !== '' && mb_strlen($new) < 10) {
        $error = 'Le nouveau mot de passe doit contenir au moins 10 caractères.';
    } elseif ($new !== $new2) {
        $error = 'Les deux nouveaux mots de passe ne correspondent pas.';
    } else {
        $account['username'] = $user;
        if ($new !== '') $account['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
        $account['updated'] = date('c');
        write_json('admin', $account);
        login($user); // nouvelle empreinte du compte : les autres navigateurs sont déconnectés
        flash('ok', $new !== '' ? 'E-mail et mot de passe mis à jour.' : 'E-mail de connexion mis à jour.');
        redirect_to('admin/compte');
    }
}

admin_header('Mon compte', 'compte');
if ($error): ?><p class="adm-flash adm-flash--error" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="card" style="max-width:560px">
  <?= csrf_field() ?>
  <div class="field">
    <label for="username">E-mail de connexion <span class="hint">Sert aussi à recevoir le lien « Mot de passe oublié ».</span></label>
    <input id="username" name="username" type="email" required autocomplete="username" value="<?= e($account['username'] ?? '') ?>">
  </div>
  <div class="field">
    <label for="new">Nouveau mot de passe <span class="hint">10 caractères minimum. Laissez vide pour le garder.</span></label>
    <input id="new" name="new" type="password" autocomplete="new-password" minlength="10">
  </div>
  <div class="field">
    <label for="new2">Confirmer le nouveau mot de passe</label>
    <input id="new2" name="new2" type="password" autocomplete="new-password">
  </div>
  <div class="field">
    <label for="current">Mot de passe actuel <span class="hint">Obligatoire pour valider le changement.</span></label>
    <input id="current" name="current" type="password" required autocomplete="current-password">
  </div>
  <button class="btn btn--dark" type="submit">Enregistrer</button>
</form>
<?php admin_footer();
