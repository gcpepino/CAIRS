<?php
require __DIR__ . '/../config/database.php';
require_role('admin');

$errors = []; $old = ['full_name' => '', 'email' => '', 'phone' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'toggle') {
        $pdo->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id = ? AND role = 'volunteer'")
            ->execute([(int)$_POST['id']]);
        flash('Volunteer status changed.');
        redirect('admin/volunteers.php');
    }
    foreach ($old as $k => $_) $old[$k] = trim($_POST[$k] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($old['full_name'] === '') $errors[] = 'Enter the volunteer\'s name.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';
    if (!$errors) {
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ?'); $st->execute([$old['email']]);
        if ($st->fetch()) $errors[] = 'That email is already registered.';
    }
    if (!$errors) {
        $pdo->prepare("INSERT INTO users (full_name,email,password,role,phone) VALUES (?,?,?,'volunteer',?)")
            ->execute([$old['full_name'], $old['email'], password_hash($pass, PASSWORD_DEFAULT), $old['phone']]);
        flash('Volunteer account created.');
        redirect('admin/volunteers.php');
    }
}
$rows = $pdo->query("SELECT u.*,
        (SELECT COUNT(*) FROM reports WHERE volunteer_id=u.id AND status IN ('assigned','in_progress')) AS active_jobs,
        (SELECT COUNT(*) FROM reports WHERE volunteer_id=u.id AND status='resolved') AS done
        FROM users u WHERE role='volunteer' ORDER BY u.full_name")->fetchAll();
render_header($pdo, 'Volunteers');
?>
<h1>Volunteers</h1>
<div class="two">
  <div class="card tablewrap">
  <?php if (!$rows): ?><p class="empty">No volunteers yet. Add the first one.</p><?php else: ?>
  <table>
    <tr><th>Name</th><th>Contact</th><th>Active</th><th>Resolved</th><th>Status</th><th></th></tr>
    <?php foreach ($rows as $u): ?>
    <tr><td><?= e($u['full_name']) ?></td><td><?= e($u['email']) ?><br><span class="muted"><?= e($u['phone']) ?></span></td>
        <td><?= $u['active_jobs'] ?></td><td><?= $u['done'] ?></td><td><?= e(ucfirst($u['status'])) ?></td>
        <td><form method="post" data-confirm="Change this volunteer's status?"><?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $u['id'] ?>">
          <button class="btn small <?= $u['status'] === 'active' ? 'danger' : '' ?>" type="submit"><?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button></form></td></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  </div>
  <div class="card">
    <h2>Add a volunteer</h2>
    <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <label for="full_name">Full name</label><input id="full_name" name="full_name" required value="<?= e($old['full_name']) ?>">
      <label for="email">Email</label><input type="email" id="email" name="email" required value="<?= e($old['email']) ?>">
      <label for="phone">Mobile number</label><input id="phone" name="phone" value="<?= e($old['phone']) ?>">
      <label for="password">Temporary password</label><input type="password" id="password" name="password" required minlength="6">
      <p style="margin-top:16px"><button class="btn" type="submit">Create volunteer</button></p>
    </form>
  </div>
</div>
<?php render_footer();
