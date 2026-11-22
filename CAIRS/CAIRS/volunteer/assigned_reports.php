<?php
require __DIR__ . '/../config/database.php';
require_role('volunteer');
$uid = $_SESSION['user']['id'];
$status = $_GET['status'] ?? '';

$sql = 'SELECT r.*, c.name AS category FROM reports r JOIN categories c ON c.id=r.category_id WHERE r.volunteer_id = ?';
$args = [$uid];
if ($status !== '') { $sql .= ' AND r.status = ?'; $args[] = $status; }
$st = $pdo->prepare($sql . ' ORDER BY r.id DESC');
$st->execute($args);
$rows = $st->fetchAll();

render_header($pdo, 'Assigned reports');
?>
<h1>Assigned reports</h1>
<form class="filters" method="get">
  <select name="status" onchange="this.form.submit()" aria-label="Filter by status">
    <option value="">All statuses</option>
    <?php foreach (['assigned','in_progress','resolved'] as $s): ?>
      <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
    <?php endforeach; ?>
  </select>
</form>
<div class="card tablewrap">
<?php if (!$rows): ?><p class="empty">Nothing here.</p><?php else: ?>
<table>
  <tr><th>Ref</th><th>Title</th><th>Category</th><th>Location</th><th>Priority</th><th>Status</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
  <tr><td><?= ref_no($r['id']) ?></td><td><?= e($r['title']) ?></td><td><?= e($r['category']) ?></td><td><?= e($r['location']) ?></td>
      <td><?= prio($r['priority']) ?></td><td><?= badge($r['status']) ?></td><td><a href="report_details.php?id=<?= $r['id'] ?>">Open</a></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
</div>
<?php render_footer();
