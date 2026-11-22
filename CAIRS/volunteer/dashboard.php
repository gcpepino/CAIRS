<?php
require __DIR__ . '/../config/database.php';
require_role('volunteer');
$uid = $_SESSION['user']['id'];

$st = $pdo->prepare('SELECT status, COUNT(*) n FROM reports WHERE volunteer_id = ? GROUP BY status');
$st->execute([$uid]);
$c = $st->fetchAll(PDO::FETCH_KEY_PAIR);

$st = $pdo->prepare("SELECT r.*, c.name AS category, u.full_name AS resident_name, v.full_name AS volunteer_name
                     FROM reports r JOIN categories c ON c.id=r.category_id
                     JOIN users u ON u.id=r.resident_id LEFT JOIN users v ON v.id=r.volunteer_id
                     WHERE r.volunteer_id = ? AND r.status IN ('assigned','in_progress')
                     ORDER BY FIELD(r.priority,'high','medium','low'), r.created_at LIMIT 6");
$st->execute([$uid]);
$todo = $st->fetchAll();

$st = $pdo->prepare('SELECT ru.*, u.full_name, r.title FROM report_updates ru
                     JOIN reports r ON r.id = ru.report_id JOIN users u ON u.id = ru.user_id
                     WHERE r.volunteer_id = ? ORDER BY ru.id DESC LIMIT 6');
$st->execute([$uid]);
$activity = $st->fetchAll();

render_header($pdo, 'Dashboard');
render_welcome(explode(' ', $_SESSION['user']['full_name'])[0], 'Your assigned incidents, highest priority first.',
    '<a class="btn ghost" href="assigned_reports.php">' . icon('clipboard', 17) . ' All assignments</a>');
render_announcements($pdo);
render_alerts($pdo, $uid);
?>
<div class="grid">
  <?php
  stat_card('clipboard', 'New assignments', $c['assigned'] ?? 0, 'Waiting for you to start', 'warning');
  stat_card('loader',    'In progress', $c['in_progress'] ?? 0, 'Currently being handled', 'progress');
  stat_card('check',     'Resolved by you', $c['resolved'] ?? 0, 'Successfully completed', 'success');
  ?>
</div>
<div class="two">
  <div class="card">
    <div class="card-head"><h2>Your next tasks</h2></div>
    <?php if (!$todo): ?><p class="empty">No open assignments. The admin will assign new reports to you.</p><?php else: ?>
      <div class="report-grid">
        <?php foreach ($todo as $r) render_report_card($r, 'report_details.php?id=' . $r['id']); ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="card"><div class="card-head"><h2>Recent activity</h2></div><?php render_activity($activity, 'report_details.php?id='); ?></div>
</div>
<?php render_footer();
