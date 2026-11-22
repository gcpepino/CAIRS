<?php
require __DIR__ . '/../config/database.php';
require_role('resident');
$r = fetch_report($pdo, (int)($_GET['id'] ?? 0));
// A resident may only open their own reports
if (!$r || $r['resident_id'] != $_SESSION['user']['id']) { flash('Report not found.', 'error'); redirect('resident/my_reports.php'); }

render_header($pdo, ref_no($r['id']));
?>
<div class="page-head"><h1><?= e($r['title']) ?></h1><span><?= badge($r['status']) ?> <button type="button" class="btn ghost small noprint" data-print>Print / Save as PDF</button></span></div>
<div class="two">
  <div class="card">
    <dl class="info">
      <dt>Reference</dt><dd><?= ref_no($r['id']) ?></dd>
      <dt>Category</dt><dd><?= e($r['category']) ?></dd>
      <dt>Location</dt><dd><?= e($r['location']) ?></dd>
      <dt>Priority</dt><dd><?= prio($r['priority']) ?></dd>
      <dt>Assigned to</dt><dd><?= $r['volunteer_name'] ? e($r['volunteer_name']) : '<span class="muted">Not assigned yet</span>' ?></dd>
      <dt>Filed</dt><dd><?= e(fmt_date($r['created_at'])) ?></dd>
    </dl>
    <h3 style="margin-top:20px">Description</h3>
    <p><?= nl2br(e($r['description'])) ?></p>
    <?php if ($r['photo']): ?><img class="photo" src="<?= BASE_URL ?>/assets/uploads/<?= e($r['photo']) ?>" alt="Photo attached to the report"><?php endif; ?>
      <?php render_map_view($r); ?>
  </div>
  <div class="card"><h2>Progress</h2><?php render_timeline($pdo, $r['id']); ?></div>
</div>
<?php render_footer();
