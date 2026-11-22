<?php
require __DIR__ . '/../config/database.php';
require_role('resident');
$uid = $_SESSION['user']['id'];
$cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$errors = [];
$old = ['category_id' => '', 'title' => '', 'description' => '', 'location' => '', 'latitude' => '', 'longitude' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($old as $k => $_) $old[$k] = trim($_POST[$k] ?? '');
    $catName = null;                                   // the category must really exist
    foreach ($cats as $c) if ($c['id'] == $old['category_id']) $catName = $c['name'];
    if ($catName === null) $errors[] = 'Choose a category.';
    if ($old['title'] === '') $errors[] = 'Enter a short title.';
    if (strlen($old['description']) < 10) $errors[] = 'Describe the incident in at least 10 characters.';
    if ($old['location'] === '') $errors[] = 'Enter where it happened.';

    // Optional map pin: both numbers or neither
    $lat = is_numeric($old['latitude'])  ? (float)$old['latitude']  : null;
    $lng = is_numeric($old['longitude']) ? (float)$old['longitude'] : null;
    if (($lat === null) !== ($lng === null) || ($lat !== null && (abs($lat) > 90 || abs($lng) > 180)))
        $errors[] = 'The map pin is not valid. Click the map to place it again.';

    // Optional photo upload (jpg/png/webp, max 3 MB)
    $photo = null;
    if (!empty($_FILES['photo']['name'])) {
        $f = $_FILES['photo'];
        $mime = $f['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) $errors[] = 'Photo must be a JPG, PNG or WEBP image.';
        elseif ($f['size'] > 3 * 1024 * 1024) $errors[] = 'Photo must be 3 MB or smaller.';
        else $photo = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    }

    if (!$errors) {
        if ($photo) move_uploaded_file($_FILES['photo']['tmp_name'], UPLOAD_DIR . $photo);
        $pdo->prepare('INSERT INTO reports (resident_id,category_id,title,description,location,latitude,longitude,photo,priority) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$uid, $old['category_id'], $old['title'], $old['description'], $old['location'], $lat, $lng, $photo, default_priority($catName)]);
        $rid = $pdo->lastInsertId();
        log_update($pdo, $rid, $uid, 'pending', 'Report submitted.');
        foreach ($pdo->query("SELECT id FROM users WHERE role='admin' AND status='active'") as $a) {
            notify($pdo, $a['id'], $rid, 'New report ' . ref_no($rid) . ': ' . $old['title']);
        }
        flash('Report submitted as ' . ref_no($rid) . '. We will notify you of any update.');
        redirect('resident/report_details.php?id=' . $rid);
    }
}
render_header($pdo, 'Report an incident');
?>
<h1>Report an incident</h1>
<div class="card">
  <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label for="category_id">Category</label>
    <select id="category_id" name="category_id" required>
      <option value="">Choose one</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $old['category_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="title">Title</label>
    <input id="title" name="title" maxlength="150" required value="<?= e($old['title']) ?>">
    <label for="location">Location</label>
    <input id="location" name="location" placeholder="Street, purok, or landmark" required value="<?= e($old['location']) ?>">
    <label>Pin the exact spot (optional)</label>
    <?php render_map_picker($pdo, $old['latitude'], $old['longitude']); ?>
    <label for="description">What happened?</label>
    <textarea id="description" name="description" required><?= e($old['description']) ?></textarea>
    <label for="photo">Photo (optional, up to 3 MB)</label>
    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
    <div id="preview"></div>
    <p style="margin-top:18px"><button class="btn" type="submit">Submit report</button></p>
  </form>
</div>
<?php render_footer();
