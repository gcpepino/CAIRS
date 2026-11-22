<?php
require __DIR__ . '/../config/database.php';
require_role('resident');
$uid = $_SESSION['user']['id'];
$status = $_GET['status'] ?? '';

$sql = 'SELECT r.*, c.name AS category FROM reports r JOIN categories c ON c.id = r.category_id WHERE r.resident_id = ?';
$args = [$uid];
if ($status !== '') { $sql .= ' AND r.status = ?'; $args[] = $status; }
$st = $pdo->prepare($sql . ' ORDER BY r.id DESC');
$st->execute($args);
$rows = $st->fetchAll();

render_header($pdo, 'My reports');
?>
<div class="page-head"><h1>My reports</h1><a class="btn" href="report.php">Report an incident</a></div>
<form class="filters" method="get">
  <select name="status" onchange="this.form.submit()" aria-label="Filter by status">
    <option value="">All statuses</option>
    <?php foreach (['pending','assigned','in_progress','resolved','rejected'] as $s): ?>
      <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
    <?php endforeach; ?>
  </select>
</form>
<div class="card tablewrap">
<?php if (!$rows): ?><p class="empty">No reports found.</p><?php else: ?>
<table>
  <tr><th>Ref</th><th>Title</th><th>Category</th><th>Filed</th><th>Status</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
  <tr><td><?= ref_no($r['id']) ?></td><td><?= e($r['title']) ?></td><td><?= e($r['category']) ?></td>
      <td><?= e(fmt_date($r['created_at'])) ?></td><td><?= badge($r['status']) ?></td>
      <td><a href="report_details.php?id=<?= $r['id'] ?>">View</a></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
</div>
<?php render_footer();
