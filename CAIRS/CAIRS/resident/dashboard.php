<?php
require __DIR__ . '/../config/database.php';
require_role('resident');
$uid = $_SESSION['user']['id'];

$st = $pdo->prepare('SELECT status, COUNT(*) n FROM reports WHERE resident_id = ? GROUP BY status');
$st->execute([$uid]);
$c = $st->fetchAll(PDO::FETCH_KEY_PAIR);
$total = array_sum($c);
$open  = ($c['assigned'] ?? 0) + ($c['in_progress'] ?? 0);

$st = $pdo->prepare('SELECT r.*, c.name AS category, v.full_name AS volunteer_name
                     FROM reports r JOIN categories c ON c.id = r.category_id
                     LEFT JOIN users v ON v.id = r.volunteer_id
                     WHERE r.resident_id = ? ORDER BY r.id DESC LIMIT 6');
$st->execute([$uid]);
$recent = $st->fetchAll();

$st = $pdo->prepare('SELECT ru.*, u.full_name, r.title FROM report_updates ru
                     JOIN reports r ON r.id = ru.report_id JOIN users u ON u.id = ru.user_id
                     WHERE r.resident_id = ? ORDER BY ru.id DESC LIMIT 6');
$st->execute([$uid]);
$activity = $st->fetchAll();

render_header($pdo, 'Dashboard');
render_welcome(explode(' ', $_SESSION['user']['full_name'])[0], 'Track your reports and see how your community responds.',
    '<a class="btn" href="report.php">' . icon('plus', 17) . ' Report an incident</a>');
render_announcements($pdo);
?>
<div class="grid">
  <?php
  stat_card('file',   'Total reports', $total, 'Everything you have filed', 'primary');
  stat_card('clock',  'Pending', $c['pending'] ?? 0, 'Waiting for review', 'warning');
  stat_card('loader', 'In progress', $open, 'Currently being handled', 'progress');
  stat_card('check',  'Resolved', $c['resolved'] ?? 0, 'Successfully completed', 'success');
  ?>
</div>
<div class="two">
  <div class="card">
    <div class="card-head"><h2>Your recent reports</h2><a href="my_reports.php">View all</a></div>
    <?php if (!$recent): ?>
      <p class="empty">You haven't reported anything yet. <a href="report.php">Report your first incident</a>.</p>
    <?php else: ?>
      <div class="report-grid">
        <?php foreach ($recent as $r) render_report_card($r, 'report_details.php?id=' . $r['id']); ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="card"><div class="card-head"><h2>Latest updates</h2></div><?php render_activity($activity, 'report_details.php?id='); ?></div>
</div>
<div class="card"><div class="card-head"><h2>Emergency hotlines</h2></div><?php render_hotlines($pdo); ?></div>
<?php render_footer();
