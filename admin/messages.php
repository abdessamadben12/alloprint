<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = post_str('id', 40);
    $action = $_POST['action'] ?? '';
    $all = read_json('messages', []);
    if ($action === 'delete') {
        $all = array_values(array_filter($all, fn($m) => $m['id'] !== $id));
        flash('ok', 'Message supprimé.');
    } elseif ($action === 'read') {
        foreach ($all as &$m) if ($m['id'] === $id) $m['lu'] = !($m['lu'] ?? false);
        unset($m);
    }
    write_json('messages', $all);
    redirect_to('admin/messages');
}

$all = read_json('messages', []);
admin_header('Messages reçus', 'messages');
?>
<p>Toutes les demandes envoyées depuis les formulaires Devis et Contact sont enregistrées ici, même si l’e-mail n’a pas pu partir.</p>
<?php if (!$all): ?>
  <div class="card"><p>Aucun message pour l’instant.</p></div>
<?php endif; ?>
<?php foreach ($all as $m): $f = $m['fields'] ?? []; ?>
  <article class="card msg<?= !empty($m['lu']) ? ' msg--read' : '' ?>">
    <div class="toolbar" style="margin-bottom:10px">
      <div>
        <span class="badge <?= $m['type'] === 'devis' ? 'badge--pub' : 'badge--draft' ?>"><?= $m['type'] === 'devis' ? 'Devis' : 'Contact' ?></span>
        <b style="margin-left:6px"><?= e($f['Nom'] ?? '') ?></b>
        <small style="color:#5b6b72"> — <?= e(date('d/m/Y H:i', strtotime($m['date']))) ?></small>
        <?php if (!empty($m['sent'])): ?><small style="color:var(--ok)"> · e-mail envoyé</small><?php else: ?><small style="color:var(--danger)" title="<?= e($m['error'] ?? '') ?>"> · e-mail non envoyé</small><?php endif; ?>
      </div>
      <div class="actions">
        <?php if (!empty($f['E-mail'])): ?><a class="btn btn--small" href="mailto:<?= e($f['E-mail']) ?>?subject=<?= rawurlencode('Re : votre demande AlloPrint') ?>">Répondre</a><?php endif; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($m['id']) ?>"><input type="hidden" name="action" value="read"><button class="btn btn--small btn--ghost" type="submit"><?= !empty($m['lu']) ? 'Marquer non lu' : 'Marquer lu' ?></button></form>
        <form method="post" onsubmit="return confirm('Supprimer ce message ?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($m['id']) ?>"><input type="hidden" name="action" value="delete"><button class="btn btn--small btn--danger" type="submit">Supprimer</button></form>
      </div>
    </div>
    <dl class="msg__fields">
      <?php foreach ($f as $label => $v): ?>
        <dt><?= e($label) ?></dt><dd><?= nl2br(e($v)) ?></dd>
      <?php endforeach; ?>
    </dl>
  </article>
<?php endforeach;
admin_footer();
