<?php
require __DIR__ . '/../config/database.php';
require_role('volunteer');
$uid = $_SESSION['user']['id'];
$id = (int)($_GET['id'] ?? 0);
$r = fetch_report($pdo, $id);
// A volunteer may only open reports assigned to them
if (!$r || $r['volunteer_id'] != $uid) { flash('Report not found.', 'error'); redirect('volunteer/assigned_reports.php'); }
$open = in_array($r['status'], ['assigned', 'in_progress'], true);

$errors = [];
if ($open && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $status = $_POST['status'] ?? '';
    $remark = trim($_POST['remark'] ?? '');
    if (!in_array($status, ['in_progress', 'resolved'], true)) $errors[] = 'Choose In progress or Resolved.';
    if ($status === 'resolved' && $remark === '') $errors[] = 'Describe what was done before marking it resolved.';
    if (!$errors) {
        $pdo->prepare('UPDATE reports SET status = ? WHERE id = ?')->execute([$status, $id]);
        log_update($pdo, $id, $uid, $status, $remark);
        notify($pdo, $r['resident_id'], $id, ref_no($id) . ' is now ' . status_label($status) . '.');
        foreach ($pdo->query("SELECT id FROM users WHERE role='admin' AND status='active'") as $a) {
            notify($pdo, $a['id'], $id, $_SESSION['user']['full_name'] . ' set ' . ref_no($id) . ' to ' . status_label($status) . '.');
        }
        flash('Report updated.');
        redirect('volunteer/report_details.php?id=' . $id);
    }
}
render_header($pdo, ref_no($r['id']));
?>
<div class="page-head"><h1><?= e($r['title']) ?></h1><span><?= badge($r['status']) ?> <button type="button" class="btn ghost small noprint" data-print>Print / Save as PDF</button></span></div>
<div class="two">
  <div>
    <div class="card">
      <dl class="info">
        <dt>Reference</dt><dd><?= ref_no($r['id']) ?></dd>
        <dt>Category</dt><dd><?= e($r['category']) ?></dd>
        <dt>Location</dt><dd><?= e($r['location']) ?></dd>
        <dt>Priority</dt><dd><?= prio($r['priority']) ?></dd>
        <dt>Reported by</dt><dd><?= e($r['resident_name']) ?> <span class="muted"><?= e($r['resident_phone']) ?></span></dd>
      </dl>
      <h3 style="margin-top:20px">Description</h3>
      <p><?= nl2br(e($r['description'])) ?></p>
      <?php if ($r['photo']): ?><img class="photo" src="<?= BASE_URL ?>/assets/uploads/<?= e($r['photo']) ?>" alt="Photo attached to the report"><?php endif; ?>
      <?php render_map_view($r); ?>
    </div>
    <div class="card"><h2>History</h2><?php render_timeline($pdo, $id); ?></div>
  </div>
  <div class="card">
    <h2>Update progress</h2>
    <?php if (!$open): ?>
      <p class="muted">This report is <?= e(status_label($r['status'])) ?> and can no longer be changed.</p>
    <?php else: ?>
      <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
      <form method="post">
        <?= csrf_field() ?>
        <label for="status">New status</label>
        <select id="status" name="status">
          <option value="in_progress">In progress</option>
          <option value="resolved">Resolved</option>
        </select>
        <label for="remark">What did you do?</label>
        <textarea id="remark" name="remark" style="min-height:90px"></textarea>
        <p style="margin-top:16px"><button class="btn" type="submit">Post update</button></p>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php render_footer();
