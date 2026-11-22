<?php
require __DIR__ . '/../config/database.php';
require_role('admin');

/* ---- numbers ---- */
$c = $pdo->query('SELECT status, COUNT(*) n FROM reports GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$total      = array_sum($c);
$residents  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='resident'")->fetchColumn();
$volunteers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='volunteer' AND status='active'")->fetchColumn();
$prio       = $pdo->query('SELECT priority, COUNT(*) n FROM reports GROUP BY priority')->fetchAll(PDO::FETCH_KEY_PAIR);
$prio       = array_merge(['high' => 0, 'medium' => 0, 'low' => 0], $prio);

// reports this month vs last month
$m = $pdo->query("SELECT COALESCE(SUM(created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')),0) AS cur,
                         COALESCE(SUM(created_at >= DATE_SUB(DATE_FORMAT(NOW(),'%Y-%m-01'), INTERVAL 1 MONTH)
                                  AND created_at < DATE_FORMAT(NOW(),'%Y-%m-01')),0) AS prev FROM reports")->fetch();
if ($m['prev'] > 0) {
    $diff  = round(($m['cur'] - $m['prev']) / $m['prev'] * 100);
    $trend = [$diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat'), ($diff > 0 ? '+' : '') . $diff . '% this month'];
} elseif ($m['cur'] > 0) {
    $trend = ['up', $m['cur'] . ' new this month'];
} else {
    $trend = ['flat', 'No new reports this month'];
}

/* ---- lists ---- */
$recent = $pdo->query("SELECT r.*, c.name AS category, u.full_name AS resident_name, v.full_name AS volunteer_name
                       FROM reports r JOIN categories c ON c.id=r.category_id JOIN users u ON u.id=r.resident_id
                       LEFT JOIN users v ON v.id=r.volunteer_id ORDER BY r.id DESC LIMIT 6")->fetchAll();
$pending = $pdo->query("SELECT r.*, c.name AS category, u.full_name AS resident_name FROM reports r
                        JOIN categories c ON c.id=r.category_id JOIN users u ON u.id=r.resident_id
                        WHERE r.status='pending' ORDER BY r.created_at ASC LIMIT 8")->fetchAll();
$activity = $pdo->query("SELECT ru.*, u.full_name, r.title FROM report_updates ru
                         JOIN users u ON u.id=ru.user_id JOIN reports r ON r.id=ru.report_id
                         ORDER BY ru.id DESC LIMIT 8")->fetchAll();

// Active SOS alerts (shown in a red board at the top)
$sos = $pdo->query("SELECT r.*, c.name AS category, u.full_name AS resident_name, v.full_name AS volunteer_name
                    FROM reports r JOIN categories c ON c.id=r.category_id JOIN users u ON u.id=r.resident_id
                    LEFT JOIN users v ON v.id=r.volunteer_id
                    WHERE r.is_sos = 1 AND r.status IN ('pending','assigned','in_progress') ORDER BY r.id DESC LIMIT 6")->fetchAll();

render_header($pdo, 'Dashboard');
render_welcome($_SESSION['user']['full_name'], 'Here is what is happening in your community today.',
    '<a class="btn" href="reports.php?status=pending">' . icon('clock', 17) . ' Review pending</a>');
render_alerts($pdo, $_SESSION['user']['id']);
?>
<?php if ($sos): ?>
<section class="sos-board" aria-label="Active SOS alerts">
  <div class="sos-board-head"><span class="live-dot"></span><h2>Active SOS alerts (<?= count($sos) ?>)</h2></div>
  <div class="report-grid">
    <?php foreach ($sos as $r) render_report_card($r, 'report_details.php?id=' . $r['id']); ?>
  </div>
</section>
<?php endif; ?>
<div class="grid">
  <?php
  stat_card('file',   'Total reports', $total, '', 'primary', $trend);
  stat_card('clock',  'Pending', $c['pending'] ?? 0, 'Needs attention', 'warning');
  stat_card('loader', 'In progress', ($c['assigned'] ?? 0) + ($c['in_progress'] ?? 0), 'Currently being handled', 'progress');
  stat_card('check',  'Resolved', $c['resolved'] ?? 0, 'Successfully completed', 'success');
  stat_card('users',  'Residents', $residents, 'Registered accounts', 'info');
  stat_card('shield', 'Volunteers', $volunteers, 'Active responders', 'primary');
  ?>
</div>

<div class="two even">
  <div class="card"><div class="card-head"><h2>Status overview</h2></div><?php render_donut($c); ?></div>
  <div class="card"><div class="card-head"><h2>Priority overview</h2></div><?php render_bars($prio, priority_tones()); ?></div>
</div>

<div class="two">
  <div class="card">
    <div class="card-head"><h2>Recent reports</h2><a href="reports.php">View all</a></div>
    <?php if (!$recent): ?><p class="empty">No reports yet.</p><?php else: ?>
    <div class="report-grid">
      <?php foreach ($recent as $r) render_report_card($r, 'report_details.php?id=' . $r['id']); ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="card"><div class="card-head"><h2>Activity</h2></div><?php render_activity($activity, 'report_details.php?id='); ?></div>
</div>

<div class="card">
  <div class="card-head"><h2>Waiting for review (oldest first)</h2></div>
  <?php if (!$pending): ?><p class="empty">All caught up. No pending reports.</p><?php else: ?>
  <div class="tablewrap"><table>
    <tr><th>Ref</th><th>Title</th><th>Category</th><th>Resident</th><th>Filed</th><th></th></tr>
    <?php foreach ($pending as $r): ?>
    <tr><td><?= ref_no($r['id']) ?></td><td><?= e($r['title']) ?><?= is_overdue($r) ? ' <span class="badge overdue">Overdue</span>' : '' ?></td><td><?= e($r['category']) ?></td>
        <td><?= e($r['resident_name']) ?></td><td><?= e(fmt_date($r['created_at'])) ?></td>
        <td><a class="btn small" href="report_details.php?id=<?= $r['id'] ?>">Review</a></td></tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>

<?php render_incident_map($pdo, 'Incident map', 380); ?>
<?php render_footer();
