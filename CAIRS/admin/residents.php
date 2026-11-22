<?php
require __DIR__ . '/../config/database.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pdo->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id = ? AND role = 'resident'")
        ->execute([(int)$_POST['id']]);
    flash('Resident status changed.');
    redirect('admin/residents.php');
}
$rows = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM reports WHERE resident_id = u.id) AS total
                     FROM users u WHERE role='resident' ORDER BY u.created_at DESC")->fetchAll();
render_header($pdo, 'Residents');
?>
<h1>Residents</h1>
<div class="card tablewrap">
<?php if (!$rows): ?><p class="empty">No residents have registered yet.</p><?php else: ?>
<table>
  <tr><th>Name</th><th>Email</th><th>Phone</th><th>Address</th><th>Reports</th><th>Status</th><th></th></tr>
  <?php foreach ($rows as $u): ?>
  <tr><td><?= e($u['full_name']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['phone']) ?></td><td><?= e($u['address']) ?></td>
      <td><?= $u['total'] ?></td><td><?= e(ucfirst($u['status'])) ?></td>
      <td><form method="post" data-confirm="Change this resident's status?"><?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $u['id'] ?>">
        <button class="btn small <?= $u['status'] === 'active' ? 'danger' : '' ?>" type="submit"><?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button></form></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
</div>
<?php render_footer();
