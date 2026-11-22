<?php
require __DIR__ . '/../config/database.php';
require_role('admin');

// Validates a category name; returns an error text or '' when it is fine. $except = id being renamed.
function category_name_error($pdo, $name, $except = 0) {
    if ($name === '') return 'Enter a category name.';
    if (strlen($name) > 60) return 'The name is too long (60 characters at most).';
    $st = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(?) AND id <> ?');
    $st->execute([$name, $except]);
    return $st->fetch() ? 'That category already exists.' : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');

    if ($action === 'add') {
        $err = category_name_error($pdo, $name);
        if ($err) flash($err, 'error');
        else { $pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]); flash('Category added.'); }
    } elseif ($action === 'rename') {
        $err = category_name_error($pdo, $name, $id);
        if ($err) flash($err, 'error');
        else { $pdo->prepare('UPDATE categories SET name = ? WHERE id = ?')->execute([$name, $id]); flash('Category renamed.'); }
    } elseif ($action === 'delete') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM reports WHERE category_id = ?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) flash('This category is used by reports, so it cannot be deleted.', 'error');
        else { $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]); flash('Category deleted.'); }
    }
    redirect('admin/categories.php');
}

$rows = $pdo->query('SELECT c.*, COUNT(r.id) AS total FROM categories c LEFT JOIN reports r ON r.category_id = c.id GROUP BY c.id ORDER BY c.name')->fetchAll();
render_header($pdo, 'Categories');
?>
<h1>Categories</h1>
<div class="two">
  <div class="card tablewrap">
  <?php if (!$rows): ?><p class="empty">No categories yet. Add the first one.</p><?php else: ?>
  <table>
    <tr><th>Name</th><th>Priority</th><th>Reports</th><th></th></tr>
    <?php foreach ($rows as $c): ?>
    <tr>
      <td>
        <form method="post" style="display:flex;gap:8px;align-items:center"><?= csrf_field() ?>
          <input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <input name="name" maxlength="60" required value="<?= e($c['name']) ?>" aria-label="Category name" style="flex:1;min-width:0">
          <button class="btn ghost small" type="submit">Save</button>
        </form>
      </td>
      <td><?= prio(default_priority($c['name'])) ?></td>
      <td><?= (int)$c['total'] ?></td>
      <td>
        <?php if ((int)$c['total'] > 0): ?><span class="muted">In use</span>
        <?php else: ?>
        <form method="post" data-confirm="Delete the category &quot;<?= e($c['name']) ?>&quot;?"><?= csrf_field() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="btn danger small" type="submit">Delete</button></form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  </div>

  <div class="card">
    <h2>Add a category</h2>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <label for="name">Name</label>
      <input id="name" name="name" maxlength="60" required>
      <p style="margin-top:16px"><button class="btn" type="submit">Add category</button></p>
    </form>
    <p class="muted" style="margin-top:14px">New reports get their priority from the category name: Fire, Flood, Medical emergency, Road accident and Theft / Crime are High; Noise, Garbage and Streetlight are Low; the rest are Medium. The SOS buttons file under the default category names, so keep those if you can.</p>
  </div>
</div>
<?php render_footer();
