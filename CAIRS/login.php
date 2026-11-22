<?php
require __DIR__ . '/config/database.php';
if (!empty($_SESSION['user'])) redirect(home_for($_SESSION['user']['role']));

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $st = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $user = $st->fetch();

    if (login_locked($pdo, $email)) {
        $error = 'Too many failed attempts. Please wait 10 minutes and try again.';
    } elseif (!$user || !password_verify($_POST['password'] ?? '', $user['password'])) {
        $error = 'Email or password is incorrect.';
        login_failed($pdo, $email);
    } elseif ($user['status'] !== 'active') {
        $error = 'This account is inactive. Contact the barangay office.';
    } else {
        login_clear($pdo, $email);
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $user['id'], 'full_name' => $user['full_name'], 'email' => $user['email'], 'role' => $user['role']];
        redirect(home_for($user['role']));
    }
}
render_header($pdo, 'Log in');
?>
<div class="auth-shell">
  <aside class="auth-brand">
    <a class="brand" href="<?= BASE_URL ?>/index.php"><span class="brand-mark"><?= icon('shield', 20) ?></span><span><?= e(setting($pdo, 'site_name', 'CAIRS')) ?></span></a>
    <div class="auth-copy">
      <h2>Report it. Track it. Get it resolved.</h2>
      <p>Community Assistance and Incident Reporting System for <?= e(setting($pdo, 'barangay_name', 'your barangay')) ?>.</p>
      <ul class="auth-points">
        <li><?= icon('pin', 18) ?> Pin the exact location on a map</li>
        <li><?= icon('bell', 18) ?> Get notified at every step</li>
        <li><?= icon('check', 18) ?> See when your report is resolved</li>
      </ul>
    </div>
    <p class="auth-foot">&copy; <?= date('Y') ?> <?= e(setting($pdo, 'site_name', 'CAIRS')) ?></p>
  </aside>
  <section class="auth-panel">
    <div class="auth-card">
      <h1>Welcome back</h1>
      <p class="muted">Log in to your account to continue.</p>
      <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" id="login-form">
        <?= csrf_field() ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>">
        <label for="password">Password</label>
        <div class="pw-wrap">
          <input type="password" id="password" name="password" required autocomplete="current-password">
          <button type="button" class="pw-toggle" id="pw-toggle" aria-label="Show password"><?= icon('eye', 19) ?></button>
        </div>
        <small class="caps-warn" id="caps-warn" hidden>Caps Lock is on.</small>
        <p style="margin-top:18px"><button class="btn" type="submit" id="login-btn">Log in</button></p>
      </form>
      <p class="muted">No account yet? <a href="<?= BASE_URL ?>/register.php">Register as a resident</a>.</p>
      <p class="secure-note"><?= icon('shield', 15) ?> Secure, encrypted-password login</p>
    </div>
  </section>
</div>
<script>
(function () {
  var pw = document.getElementById('password'), tg = document.getElementById('pw-toggle');
  var caps = document.getElementById('caps-warn'), form = document.getElementById('login-form'), btn = document.getElementById('login-btn');
  tg.addEventListener('click', function () {                       // show / hide password
    var show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    tg.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    tg.style.opacity = show ? '1' : '.8';
  });
  pw.addEventListener('keyup', function (e) {                      // Caps Lock hint
    caps.hidden = !(e.getModifierState && e.getModifierState('CapsLock'));
  });
  pw.addEventListener('blur', function () { caps.hidden = true; });
  form.addEventListener('submit', function () {                    // loading state, blocks double-click
    btn.classList.add('is-loading'); btn.textContent = 'Logging in...';
  });
})();
</script>
<?php render_footer();