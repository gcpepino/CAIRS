<?php
require __DIR__ . '/../config/database.php';
require_role('admin');

$levels = ['info', 'warning', 'critical'];
$errors = [];
$old = ['title' => '', 'body' => '', 'level' => 'info'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $pdo->prepare('UPDATE announcements SET is_active = 1 - is_active WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('Announcement updated.');
        redirect('admin/announcements.php');
    }
    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM announcements WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('Announcement deleted.');
        redirect('admin/announcements.php');
    }

    // otherwise: post a new one
    foreach ($old as $k => $_) $old[$k] = trim($_POST[$k] ?? '');
    if ($old['title'] === '') $errors[] = 'Enter a title.';
    if (strlen($old['title']) > 150) $errors[] = 'The title is too long (150 characters at most).';
    if ($old['body'] === '') $errors[] = 'Write the message.';
    if (strlen($old['body']) > 2000) $errors[] = 'The message is too long (2000 characters at most).';
    if (!in_array($old['level'], $levels, true)) $errors[] = 'Choose a level.';

    if (!$errors) {
        $pdo->prepare('INSERT INTO announcements (user_id, title, body, level) VALUES (?,?,?,?)')
            ->execute([$_SESSION['user']['id'], $old['title'], $old['body'], $old['level']]);
        flash('Announcement posted.');
        redirect('admin/announcements.php');
    }
}

$rows = $pdo->query('SELECT a.*, u.full_name FROM announcements a JOIN users u ON u.id = a.user_id ORDER BY a.id DESC')->fetchAll();
render_header($pdo, 'Announcements');
?>
<h1>Announcements</h1>
<div class="two">
  <div>
    <?php if (!$rows): ?><div class="card"><p class="empty">No announcements yet. Post the first one.</p></div><?php endif; ?>
    <?php foreach ($rows as $a): ?>
    <article class="ann ann-<?= e($a['level']) ?><?= $a['is_active'] ? '' : ' is-off' ?>">
      <span class="ann-ic"><?= icon($a['level'] === 'critical' ? 'siren' : 'megaphone', 20) ?></span>
      <div>
        <strong><?= e($a['title']) ?></strong> <?= $a['is_active'] ? '' : '<span class="badge">Hidden</span>' ?>
        <p><?= nl2br(e($a['body'])) ?></p>
        <span class="muted"><?= e(fmt_date($a['created_at'])) ?> &middot; <?= e($a['full_name']) ?> &middot; <?= e(ucfirst($a['level'])) ?></span>
        <div class="ann-actions">
          <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <button class="btn ghost small" type="submit"><?= $a['is_active'] ? 'Hide' : 'Show again' ?></button></form>
          <form method="post" data-confirm="Delete this announcement for good?"><?= csrf_field() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <button class="btn danger small" type="submit">Delete</button></form>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2>Post an announcement</h2>
    <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <label for="title">Title</label>
      <input id="title" name="title" maxlength="150" required value="<?= e($old['title']) ?>">
      <label for="level">Level</label>
      <select id="level" name="level">
        <?php foreach ($levels as $l): ?><option value="<?= $l ?>" <?= $old['level'] === $l ? 'selected' : '' ?>><?= e(ucfirst($l)) ?></option><?php endforeach; ?>
      </select>
      <label for="body">Message</label>
      <textarea id="body" name="body" required style="min-height:110px"><?= e($old['body']) ?></textarea>
      <p style="margin-top:16px"><button class="btn" type="submit">Post announcement</button></p>
    </form>
    <p class="muted" style="margin-top:14px">Active announcements show on the home page and on the resident and volunteer dashboards. Use <b>Critical</b> only for real emergencies.</p>
  </div>
</div>
<?php render_footer();
