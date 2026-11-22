<?php
require __DIR__ . '/../config/database.php';
require_role('resident');
$uid = $_SESSION['user']['id'];

// Mark one as read, then jump to its report
if (isset($_GET['open'])) {
    $st = $pdo->prepare('SELECT * FROM notifications WHERE id = ? AND user_id = ?');
    $st->execute([(int)$_GET['open'], $uid]);
    if ($n = $st->fetch()) {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?')->execute([$n['id']]);
        if ($n['report_id']) redirect('resident/report_details.php?id=' . $n['report_id']);
    }
    redirect('resident/notifications.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
    flash('All notifications marked as read.');
    redirect('resident/notifications.php');
}
$st = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50');
$st->execute([$uid]);
$rows = $st->fetchAll();

render_header($pdo, 'Notifications');
?>
<div class="page-head">
  <h1>Notifications</h1>
  <form method="post"><?= csrf_field() ?><button class="btn ghost small" type="submit">Mark all as read</button></form>
</div>
<div class="card" style="padding:0">
<?php if (!$rows): ?><p class="empty">Nothing yet. Updates on your reports will show here.</p><?php endif; ?>
<?php foreach ($rows as $n): ?>
  <a class="notif <?= $n['is_read'] ? '' : 'unread' ?>" href="?open=<?= $n['id'] ?>">
    <?= e($n['message']) ?><br><span class="muted"><?= e(fmt_date($n['created_at'])) ?></span>
  </a>
<?php endforeach; ?>
</div>
<?php render_footer();
