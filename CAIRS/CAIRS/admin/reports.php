<?php
require __DIR__ . '/../config/database.php';
require_role('admin');

$status = $_GET['status'] ?? ''; $cat = (int)($_GET['category'] ?? 0); $q = trim($_GET['q'] ?? '');
$sql = 'SELECT r.*, c.name AS category, u.full_name AS resident_name, v.full_name AS volunteer_name
        FROM reports r JOIN categories c ON c.id=r.category_id JOIN users u ON u.id=r.resident_id
        LEFT JOIN users v ON v.id=r.volunteer_id WHERE 1=1';
$args = [];
if ($status !== '') { $sql .= ' AND r.status = ?'; $args[] = $status; }
if ($cat)           { $sql .= ' AND r.category_id = ?'; $args[] = $cat; }
if ($q !== '')      { $sql .= ' AND (r.title LIKE ? OR r.location LIKE ? OR u.full_name LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
$exporting = isset($_GET['export']);
$st = $pdo->prepare($sql . ' ORDER BY r.id DESC' . ($exporting ? '' : ' LIMIT 200'));
$st->execute($args);
$rows = $st->fetchAll();

// Excel export: same filters as the screen, saved as CSV (opens in Excel)
if ($exporting) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cairs-reports-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                       // UTF-8 BOM so Excel shows accents correctly
    fputcsv($out, ['Reference', 'Title', 'Category', 'Resident', 'Volunteer', 'Location', 'Latitude', 'Longitude', 'Priority', 'Status', 'Filed', 'Last updated']);
    foreach ($rows as $r) {
        fputcsv($out, array_map('csv_safe', [ref_no($r['id']), $r['title'], $r['category'], $r['resident_name'], $r['volunteer_name'] ?? '',
            $r['location'], $r['latitude'] ?? '', $r['longitude'] ?? '', ucfirst($r['priority']), status_label($r['status']), $r['created_at'], $r['updated_at']]));
    }
    exit;
}
$cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();

render_header($pdo, 'Reports');
?>
<div class="page-head">
  <h1>Reports</h1>
  <span class="noprint">
    <a class="btn ghost small" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 1]))) ?>">Download Excel (CSV)</a>
    <button type="button" class="btn ghost small" data-print>Print / Save as PDF</button>
  </span>
</div>
<form class="filters" method="get">
  <input name="q" placeholder="Search title, location or resident" value="<?= e($q) ?>">
  <select name="status" aria-label="Status">
    <option value="">All statuses</option>
    <?php foreach (['pending','assigned','in_progress','resolved','rejected'] as $s): ?>
      <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="category" aria-label="Category">
    <option value="0">All categories</option>
    <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $cat == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Filter</button>
</form>
<div class="card tablewrap">
<?php if (!$rows): ?><p class="empty">No reports match these filters.</p><?php else: ?>
<table>
  <tr><th>Ref</th><th>Title</th><th>Category</th><th>Resident</th><th>Volunteer</th><th>Priority</th><th>Status</th><th class="noprint"></th></tr>
  <?php foreach ($rows as $r): ?>
  <tr><td><?= ref_no($r['id']) ?></td><td><?= e($r['title']) ?><?= !empty($r['is_sos']) ? ' <span class="badge sos">SOS</span>' : '' ?></td><td><?= e($r['category']) ?></td>
      <td><?= e($r['resident_name']) ?></td><td><?= e($r['volunteer_name'] ?? '-') ?></td>
      <td><?= prio($r['priority']) ?></td><td><?= badge($r['status']) ?></td>
      <td class="noprint"><a href="report_details.php?id=<?= $r['id'] ?>">Open</a></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
</div>
<?php render_footer();
