<?php
require __DIR__ . '/../config/database.php';
require_role('admin');
$id = (int)($_GET['id'] ?? 0);
$r = fetch_report($pdo, $id);
if (!$r) { flash('Report not found.', 'error'); redirect('admin/reports.php'); }

$vols = $pdo->query("SELECT id, full_name FROM users WHERE role='volunteer' AND status='active' ORDER BY full_name")->fetchAll();
$statuses = ['pending','assigned','in_progress','resolved','rejected'];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $status   = $_POST['status'] ?? '';
    $priority = $_POST['priority'] ?? '';
    $vol      = (int)($_POST['volunteer_id'] ?? 0) ?: null;
    $remark   = trim($_POST['remark'] ?? '');

    if (!in_array($status, $statuses, true)) $errors[] = 'Choose a valid status.';
    if (!in_array($priority, ['low','medium','high'], true)) $errors[] = 'Choose a valid priority.';
    if ($vol && !in_array($vol, array_column($vols, 'id'))) $errors[] = 'Choose an active volunteer.';
    if ($vol && $status === 'pending') $status = 'assigned';            // assigning moves it forward
    if (in_array($status, ['assigned','in_progress'], true) && !$vol) $errors[] = 'Assign a volunteer before marking it ' . status_label($status) . '.';
    if ($status === 'rejected' && $remark === '') $errors[] = 'Give a reason when rejecting a report.';

    if (!$errors) {
        $pdo->prepare('UPDATE reports SET status=?, priority=?, volunteer_id=? WHERE id=?')->execute([$status, $priority, $vol, $id]);

        $note = $remark;
        $newVol = $vol && $vol != $r['volunteer_id'];
        if ($newVol) {
            $name = $vols[array_search($vol, array_column($vols, 'id'))]['full_name'];
            $note = trim("Assigned to $name. $remark");
        }
        if ($status !== $r['status'] || $note !== '') log_update($pdo, $id, $_SESSION['user']['id'], $status, $note);

        if ($status !== $r['status'] || $newVol) {
            notify($pdo, $r['resident_id'], $id, ref_no($id) . ' is now ' . status_label($status) . '.');
        }
        if ($newVol) notify($pdo, $vol, $id, 'You were assigned to ' . ref_no($id) . ': ' . $r['title']);

        flash('Report updated.');
        redirect('admin/report_details.php?id=' . $id);
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
        <dt>Resident</dt><dd><?= e($r['resident_name']) ?> <span class="muted"><?= e($r['resident_phone']) ?></span></dd>
        <dt>Filed</dt><dd><?= e(fmt_date($r['created_at'])) ?></dd>
      </dl>
      <h3 style="margin-top:20px">Description</h3>
      <p><?= nl2br(e($r['description'])) ?></p>
      <?php if ($r['photo']): ?><img class="photo" src="<?= BASE_URL ?>/assets/uploads/<?= e($r['photo']) ?>" alt="Photo attached to the report"><?php endif; ?>
      <?php render_map_view($r); ?>
    </div>
    <div class="card"><h2>History</h2><?php render_timeline($pdo, $id); ?></div>
  </div>
  <div class="card">
    <h2>Manage</h2>
    <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label for="volunteer_id">Volunteer</label>
      <select id="volunteer_id" name="volunteer_id">
        <option value="0">Not assigned</option>
        <?php foreach ($vols as $v): ?><option value="<?= $v['id'] ?>" <?= $r['volunteer_id'] == $v['id'] ? 'selected' : '' ?>><?= e($v['full_name']) ?></option><?php endforeach; ?>
      </select>
      <label for="priority">Priority</label>
      <select id="priority" name="priority">
        <?php foreach (['low','medium','high'] as $p): ?><option value="<?= $p ?>" <?= $r['priority'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option><?php endforeach; ?>
      </select>
      <label for="status">Status</label>
      <select id="status" name="status">
        <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option><?php endforeach; ?>
      </select>
      <label for="remark">Remark (the resident can see this)</label>
      <textarea id="remark" name="remark" style="min-height:80px"></textarea>
      <p style="margin-top:16px"><button class="btn" type="submit">Save changes</button></p>
    </form>
  </div>
</div>
<?php render_footer();
