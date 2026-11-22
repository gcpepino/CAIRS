<?php
require __DIR__ . '/../config/database.php';
require_role('admin');

$total    = (int)$pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();
$resolved = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status='resolved'")->fetchColumn();
$avgHours = $pdo->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) FROM reports WHERE status='resolved'")->fetchColumn();
$byStatus = $pdo->query('SELECT status, COUNT(*) n FROM reports GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$byCat    = $pdo->query('SELECT c.name, COUNT(r.id) n FROM categories c LEFT JOIN reports r ON r.category_id=c.id GROUP BY c.id ORDER BY n DESC')->fetchAll(PDO::FETCH_KEY_PAIR);
$byMonth  = $pdo->query("SELECT DATE_FORMAT(created_at,'%b %Y') m, COUNT(*) n FROM reports
                         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                         GROUP BY YEAR(created_at), MONTH(created_at), m ORDER BY YEAR(created_at), MONTH(created_at)")->fetchAll(PDO::FETCH_KEY_PAIR);

render_header($pdo, 'Analytics');
?>
<div class="page-head"><h1>Analytics</h1><button type="button" class="btn ghost small noprint" data-print>Print / Save as PDF</button></div>
<div class="grid">
  <?php
  stat_card('file',  'Total reports', $total, 'All time', 'primary');
  stat_card('check', 'Resolution rate', ($total ? round($resolved / $total * 100) : 0) . '%', 'Reports resolved', 'success');
  stat_card('clock', 'Avg. hours to resolve', ($avgHours !== null && $avgHours !== false) ? round($avgHours, 1) : '-', 'From filing to resolved', 'warning');
  ?>
</div>
<div class="two even">
  <div class="card"><div class="card-head"><h2>Reports by category</h2></div><?php render_bars($byCat); ?></div>
  <div class="card"><div class="card-head"><h2>Reports by status</h2></div><?php render_bars($byStatus, status_tones()); ?></div>
</div>
<div class="card"><div class="card-head"><h2>Last 6 months</h2></div><?php render_columns($byMonth); ?></div>
<?php render_incident_map($pdo, 'Incident map', 420); ?>
<?php render_footer();
