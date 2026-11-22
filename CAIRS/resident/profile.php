<?php
require __DIR__ . '/../config/database.php';
require_role('resident');
$uid = $_SESSION['user']['id'];
$st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$st->execute([$uid]);
$me = $st->fetch();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['full_name'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $addr = trim($_POST['address'] ?? '');
    if ($name === '') $errors[] = 'Enter your full name.';
    if ($addr === '') $errors[] = 'Enter your address.';

    $newpass = $_POST['new_password'] ?? '';
    if ($newpass !== '') {
        if (!password_verify($_POST['current_password'] ?? '', $me['password'])) $errors[] = 'Current password is incorrect.';
        elseif (strlen($newpass) < 6) $errors[] = 'New password must be at least 6 characters.';
    }
    if (!$errors) {
        $pdo->prepare('UPDATE users SET full_name=?, phone=?, address=? WHERE id=?')->execute([$name, $phone, $addr, $uid]);
        if ($newpass !== '') $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($newpass, PASSWORD_DEFAULT), $uid]);
        $_SESSION['user']['full_name'] = $name;
        flash('Profile saved.');
        redirect('resident/profile.php');
    }
    $me['full_name'] = $name; $me['phone'] = $phone; $me['address'] = $addr;
}
render_header($pdo, 'Profile');
?>
<h1>Profile</h1>
<div class="card" style="max-width:560px">
  <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Email</label><input value="<?= e($me['email']) ?>" disabled>
    <label for="full_name">Full name</label><input id="full_name" name="full_name" required value="<?= e($me['full_name']) ?>">
    <label for="phone">Mobile number</label><input id="phone" name="phone" value="<?= e($me['phone']) ?>">
    <label for="address">Address / Purok</label><input id="address" name="address" required value="<?= e($me['address']) ?>">
    <h3 style="margin-top:24px">Change password (optional)</h3>
    <label for="current_password">Current password</label><input type="password" id="current_password" name="current_password">
    <label for="new_password">New password</label><input type="password" id="new_password" name="new_password">
    <p style="margin-top:18px"><button class="btn" type="submit">Save changes</button></p>
  </form>
</div>
<?php render_footer();
