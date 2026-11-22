<?php
require __DIR__ . '/config/database.php';
if (!empty($_SESSION['user'])) redirect(home_for($_SESSION['user']['role']));

$resolved = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status='resolved'")->fetchColumn();
$total    = (int)$pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();
$barangay = setting($pdo, 'barangay_name', 'your barangay');
$site     = setting($pdo, 'site_name', 'CAIRS');
render_header($pdo, 'Community incident reporting');
?>
<div class="landing">
  <div class="hero-shapes" aria-hidden="true"><span></span><span></span></div>

  <header class="landing-nav">
    <a class="brand" href="<?= BASE_URL ?>/index.php"><span class="brand-mark"><?= icon('shield', 20) ?></span><span><?= e($site) ?></span></a>
    <nav>
      <a class="btn ghost small" href="<?= BASE_URL ?>/login.php">Log in</a>
      <a class="btn small" href="<?= BASE_URL ?>/register.php">Register</a>
    </nav>
  </header>

  <section class="hero">
    <div class="hero-copy">
      <span class="eyebrow-pill">Serving <?= e($barangay) ?></span>
      <h1>Community Assistance <span>and Incident Reporting</span> System</h1>
      <p class="lead">Report community concerns, track incidents, and help build a safer and better community.</p>
      <div class="actions">
        <a class="btn btn-lg" href="<?= BASE_URL ?>/resident/report.php">Report an incident</a>
        <a class="btn ghost btn-lg" href="<?= BASE_URL ?>/login.php">Login</a>
      </div>
      <ul class="hero-stats">
        <li><b><?= $resolved ?></b>incidents resolved</li>
        <li><b><?= $total ?></b>reports received</li>
      </ul>
    </div>
    <div class="hero-visual" aria-hidden="true">
      <div class="mock-stack">
        <div class="mock mock-1"><span class="rc-ref">CAIRS-00128</span><strong>Broken streetlight on Rizal St.</strong><span class="badge s-pending">Pending</span></div>
        <div class="mock mock-2"><span class="rc-ref">CAIRS-00127</span><strong>Flooding near the covered court</strong><span class="badge s-in_progress">In Progress</span></div>
        <div class="mock mock-3"><span class="rc-ref">CAIRS-00126</span><strong>Garbage pickup missed</strong><span class="badge s-resolved">Resolved</span></div>
      </div>
    </div>
  </section>

  <?php render_announcements($pdo, 2); ?>

  <section class="steps">
    <div class="card"><div class="step-no">1</div><h3>Report</h3><p class="muted">Pick a category, describe what happened, and pin the exact location on the map.</p></div>
    <div class="card"><div class="step-no">2</div><h3>Assigned</h3><p class="muted">The barangay admin reviews it and assigns a volunteer to respond.</p></div>
    <div class="card"><div class="step-no">3</div><h3>Resolved</h3><p class="muted">Follow every update until the incident is closed, with notifications along the way.</p></div>
  </section>

  <footer class="landing-foot muted">&copy; <?= date('Y') ?> <?= e($site) ?> &middot; <?= e($barangay) ?></footer>
</div>
<?php render_footer();
