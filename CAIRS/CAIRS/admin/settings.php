<?php
require __DIR__ . '/../config/database.php';
require_role('admin');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'site') {
        $up = $pdo->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach (['site_name', 'barangay_name', 'contact_phone', 'map_lat', 'map_lng'] as $k) {
            $v = trim($_POST[$k] ?? '');
            if (in_array($k, ['map_lat', 'map_lng']) && !is_numeric($v)) continue;   // keep old value if not a number
            $up->execute([$k, $v]);
        }
        flash('Settings saved.');
        redirect('admin/settings.php');
    }
    if (($_POST['action'] ?? '') === 'sms') {
        $up = $pdo->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $up->execute(['sms_enabled', isset($_POST['sms_enabled']) ? '1' : '0']);
        $up->execute(['sms_sender', trim($_POST['sms_sender'] ?? '')]);
        if (trim($_POST['sms_api_key'] ?? '') !== '') $up->execute(['sms_api_key', trim($_POST['sms_api_key'])]);   // blank = keep saved key
        flash('SMS settings saved.');
        redirect('admin/settings.php');
    }
    if (($_POST['action'] ?? '') === 'sms_test') {
        $st = $pdo->prepare('SELECT phone FROM users WHERE id = ?'); $st->execute([$_SESSION['user']['id']]);
        [$ok, $msg] = send_sms($pdo, $st->fetchColumn(), 'Test message from ' . setting($pdo, 'site_name', 'CAIRS') . '.');
        flash($ok ? 'Test SMS sent to your admin phone number.' : 'Test failed: ' . $msg, $ok ? 'success' : 'error');
        redirect('admin/settings.php');
    }
    if (($_POST['action'] ?? '') === 'hotlines') {
        $up = $pdo->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach (['emergency_hotline', 'hotline_fire', 'hotline_police', 'hotline_medical'] as $k) $up->execute([$k, substr(trim($_POST[$k] ?? ''), 0, 50)]);
        flash('Hotlines saved.');
        redirect('admin/settings.php');
    }
    if (($_POST['action'] ?? '') === 'password') {
        $st = $pdo->prepare('SELECT password FROM users WHERE id = ?'); $st->execute([$_SESSION['user']['id']]);
        if (!password_verify($_POST['current'] ?? '', $st->fetchColumn())) $errors[] = 'Current password is incorrect.';
        elseif (strlen($_POST['new'] ?? '') < 6) $errors[] = 'New password must be at least 6 characters.';
        else {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($_POST['new'], PASSWORD_DEFAULT), $_SESSION['user']['id']]);
            flash('Password changed.');
            redirect('admin/settings.php');
        }
    }
}
render_header($pdo, 'Settings');
?>
<h1>Settings</h1>
<div class="two">
  <div class="card">
    <h2>System</h2>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="site">
      <label for="site_name">System name</label><input id="site_name" name="site_name" required value="<?= e(setting($pdo, 'site_name')) ?>">
      <label for="barangay_name">Barangay name</label><input id="barangay_name" name="barangay_name" value="<?= e(setting($pdo, 'barangay_name')) ?>">
      <label for="contact_phone">Contact number</label><input id="contact_phone" name="contact_phone" value="<?= e(setting($pdo, 'contact_phone')) ?>">
      <label for="map_lat">Map center latitude</label><input id="map_lat" name="map_lat" value="<?= e(setting($pdo, 'map_lat', '8.4542')) ?>">
      <label for="map_lng">Map center longitude</label><input id="map_lng" name="map_lng" value="<?= e(setting($pdo, 'map_lng', '124.6319')) ?>">
      <p style="margin-top:16px"><button class="btn" type="submit">Save settings</button></p>
    </form>
  </div>
  <div class="card">
    <h2>Change my password</h2>
    <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <label for="current">Current password</label><input type="password" id="current" name="current" required>
      <label for="new">New password</label><input type="password" id="new" name="new" required minlength="6">
      <p style="margin-top:16px"><button class="btn" type="submit">Change password</button></p>
    </form>
  </div>
</div>
<div class="card" style="max-width:560px">
  <h2>Emergency hotlines</h2>
  <p class="muted">Shown to residents on their dashboard and on the SOS page. Leave a box empty to hide it.</p>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="hotlines">
    <label for="emergency_hotline">Emergency (e.g. 911)</label><input id="emergency_hotline" name="emergency_hotline" value="<?= e(setting($pdo, 'emergency_hotline', '911')) ?>">
    <label for="hotline_fire">Fire station</label><input id="hotline_fire" name="hotline_fire" value="<?= e(setting($pdo, 'hotline_fire')) ?>">
    <label for="hotline_police">Police</label><input id="hotline_police" name="hotline_police" value="<?= e(setting($pdo, 'hotline_police')) ?>">
    <label for="hotline_medical">Medical / ambulance</label><input id="hotline_medical" name="hotline_medical" value="<?= e(setting($pdo, 'hotline_medical')) ?>">
    <p style="margin-top:16px"><button class="btn" type="submit">Save hotlines</button></p>
  </form>
</div>
<div class="card" style="max-width:560px;margin-top:20px">
  <h2>SMS alerts</h2>
  <p class="muted">Residents and volunteers get a text when a report changes. Uses your own Semaphore account (semaphore.co); each message uses SMS credits.</p>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="sms">
    <label><input type="checkbox" name="sms_enabled" value="1" style="width:auto" <?= setting($pdo, 'sms_enabled', '0') === '1' ? 'checked' : '' ?>> Send SMS alerts</label>
    <label for="sms_api_key">Semaphore API key</label>
    <input id="sms_api_key" name="sms_api_key" type="password" autocomplete="off" placeholder="<?= setting($pdo, 'sms_api_key') !== '' ? 'Saved. Leave blank to keep it.' : 'Paste your API key' ?>">
    <label for="sms_sender">Sender name (optional)</label>
    <input id="sms_sender" name="sms_sender" maxlength="11" value="<?= e(setting($pdo, 'sms_sender')) ?>">
    <p style="margin-top:16px"><button class="btn" type="submit">Save SMS settings</button></p>
  </form>
  <form method="post" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="action" value="sms_test">
    <button class="btn ghost small" type="submit">Send a test SMS to my phone</button>
  </form>
</div>
<?php render_footer();
