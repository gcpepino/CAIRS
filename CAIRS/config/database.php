<?php
// ============================================================
// config/database.php  -  connection + helper functions
// Every page includes this file first.
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);   // JS cannot read the session cookie
    session_start();
}

define('BASE_URL', '/CAIRS');                       // folder name inside htdocs
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
date_default_timezone_set('Asia/Manila');           // so greetings and the clock use Philippine time

/* ---------- small helpers ---------- */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect($path) { header('Location: ' . BASE_URL . '/' . ltrim($path, '/')); exit; }
function ref_no($id) { return 'CAIRS-' . str_pad($id, 5, '0', STR_PAD_LEFT); }
function fmt_date($d) { return date('M j, Y g:i A', strtotime($d)); }
function status_label($s) { return ucwords(str_replace('_', ' ', $s)); }
function badge($s) { return '<span class="badge s-' . e($s) . '">' . e(status_label($s)) . '</span>'; }
function prio($p) { return '<span class="badge p-' . e($p) . '">' . e(ucfirst($p)) . '</span>'; }

try {
    $pdo = new PDO('mysql:host=localhost;dbname=cairs;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $ex) {
    die('Database connection failed. Did you import database/cairs.sql? ' . e($ex->getMessage()));
}

/* ---------- auto-upgrade: creates the v3 tables/columns the first time a session loads (no manual SQL needed) ---------- */
function ensure_schema($pdo) {
    if (!empty($_SESSION['schema_v3'])) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
            id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, title VARCHAR(150) NOT NULL, body TEXT NOT NULL,
            level ENUM('info','warning','critical') NOT NULL DEFAULT 'info', is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB");
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(120) NOT NULL, ip VARCHAR(45) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX (email, created_at), INDEX (ip, created_at)) ENGINE=InnoDB");
        $has = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'is_sos'")->fetchColumn();
        if (!$has) $pdo->exec("ALTER TABLE reports ADD COLUMN is_sos TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('emergency_hotline','911'),('hotline_fire',''),('hotline_police',''),('hotline_medical','')");
        $_SESSION['schema_v3'] = 1;
    } catch (PDOException $ex) { /* never block the page; the SQL file update_v3.sql does the same thing manually */ }
}
ensure_schema($pdo);

function flash($msg = null, $type = 'success') {
    if ($msg !== null) { $_SESSION['flash'] = ['m' => $msg, 't' => $type]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function setting($pdo, $key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $cache[$key] ?? $default;
}

/* ---------- login / roles ---------- */
function home_for($role) {
    return ['resident' => 'resident/dashboard.php', 'admin' => 'admin/dashboard.php', 'volunteer' => 'volunteer/dashboard.php'][$role] ?? 'login.php';
}
function require_role($role) {
    if (empty($_SESSION['user'])) redirect('login.php');
    if ($_SESSION['user']['role'] !== $role) redirect(home_for($_SESSION['user']['role']));
}

/* ---------- CSRF protection for every POST form ---------- */
function csrf_field() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
            http_response_code(419);
            die('Session expired. Go back, refresh the page and try again.');
        }
    }
}

/* ---------- report helpers ---------- */
function fetch_report($pdo, $id) {
    $st = $pdo->prepare('SELECT r.*, c.name AS category, u.full_name AS resident_name, u.phone AS resident_phone,
                                v.full_name AS volunteer_name
                         FROM reports r
                         JOIN categories c ON c.id = r.category_id
                         JOIN users u ON u.id = r.resident_id
                         LEFT JOIN users v ON v.id = r.volunteer_id
                         WHERE r.id = ?');
    $st->execute([$id]);
    return $st->fetch();
}
function notify($pdo, $user_id, $report_id, $message) {
    $pdo->prepare('INSERT INTO notifications (user_id, report_id, message) VALUES (?,?,?)')
        ->execute([$user_id, $report_id, $message]);

    // Also text residents and volunteers (not admins) when SMS is switched on in Settings
    if (setting($pdo, 'sms_enabled', '0') === '1') {
        $st = $pdo->prepare("SELECT phone FROM users WHERE id = ? AND role IN ('resident','volunteer')");
        $st->execute([$user_id]);
        if ($phone = $st->fetchColumn()) {
            send_sms($pdo, $phone, '[' . setting($pdo, 'site_name', 'CAIRS') . '] ' . $message);
        }
    }
}
function log_update($pdo, $report_id, $user_id, $status, $remark) {
    $pdo->prepare('INSERT INTO report_updates (report_id, user_id, status, remark) VALUES (?,?,?,?)')
        ->execute([$report_id, $user_id, $status, $remark]);
}
function render_timeline($pdo, $report_id) {
    $st = $pdo->prepare('SELECT ru.*, u.full_name FROM report_updates ru JOIN users u ON u.id = ru.user_id
                         WHERE ru.report_id = ? ORDER BY ru.id DESC');
    $st->execute([$report_id]);
    echo '<ol class="timeline">';
    foreach ($st->fetchAll() as $r) {
        echo '<li>' . badge($r['status']) . ' <span class="muted">' . e(fmt_date($r['created_at'])) . ' by ' . e($r['full_name']) . '</span>';
        if ($r['remark']) echo '<p>' . e($r['remark']) . '</p>';
        echo '</li>';
    }
    echo '</ol>';
}

/* ---------- page layout (sidebar + top bar + footer) ---------- */
function initials($name) {
    $out = '';
    foreach (array_slice(preg_split('/\s+/', trim((string)$name)), 0, 2) as $w) $out .= strtoupper(substr($w, 0, 1));
    return $out ?: '?';
}

function render_header($pdo, $title) {
    $site = setting($pdo, 'site_name', 'CAIRS');
    $u = $_SESSION['user'] ?? null;
    $flash = flash();
    $unread = 0;
    if ($u) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $st->execute([$u['id']]);
        $unread = (int)$st->fetchColumn();
    }
    // path => [label, icon]
    $menus = [
        'resident'  => ['resident/dashboard.php' => ['Dashboard', 'dashboard'], 'resident/report.php' => ['Report an incident', 'plus'],
                        'resident/sos.php' => ['SOS emergency', 'siren'], 'resident/my_reports.php' => ['My reports', 'list'], 'resident/notifications.php' => ['Notifications', 'bell'],
                        'resident/profile.php' => ['Profile', 'user']],
        'admin'     => ['admin/dashboard.php' => ['Dashboard', 'dashboard'], 'admin/reports.php' => ['Reports', 'file'],
                        'admin/residents.php' => ['Residents', 'users'], 'admin/volunteers.php' => ['Volunteers', 'shield'],
                        'admin/announcements.php' => ['Announcements', 'megaphone'], 'admin/categories.php' => ['Categories', 'tag'],
                        'admin/analytics.php' => ['Analytics', 'chart'], 'admin/analytics.php#incident-map' => ['Incident map', 'pin'],
                        'admin/settings.php' => ['Settings', 'sliders']],
        'volunteer' => ['volunteer/dashboard.php' => ['Dashboard', 'dashboard'], 'volunteer/assigned_reports.php' => ['Assigned reports', 'clipboard']],
    ];
    $current = ltrim(substr($_SERVER['SCRIPT_NAME'], strlen(BASE_URL)), '/');
    $bell = $u ? ($u['role'] === 'resident' ? 'resident/notifications.php' : home_for($u['role'])) : '';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#030a14">
<title><?= e($title) ?> | <?= e($site) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="<?= $u ? 'app role-' . e($u['role']) : 'public' ?>"<?= $u ? ' data-poll="' . BASE_URL . '/api/poll.php"' : '' ?>>
<div class="bg-fx" aria-hidden="true"><span class="orb o1"></span><span class="orb o2"></span></div>
<?php if ($u): ?>
<a class="skip-link" href="#main">Skip to content</a>
<div class="sidebar-overlay" aria-hidden="true"></div>
<aside class="sidebar" id="sidebar" aria-label="Main navigation">
  <a class="brand" href="<?= BASE_URL ?>/<?= home_for($u['role']) ?>">
    <span class="brand-mark"><?= icon('shield', 20) ?></span><span><?= e($site) ?></span>
  </a>
  <p class="nav-label">Menu</p>
  <nav>
    <?php foreach ($menus[$u['role']] as $path => [$label, $ic]): ?>
      <a href="<?= BASE_URL ?>/<?= $path ?>" class="<?= $current === $path ? 'active' : '' ?>">
        <?= icon($ic, 19) ?><span><?= e($label) ?></span>
        <?php if ($path === 'resident/notifications.php' && $unread): ?><span class="count"><?= $unread ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="who">
    <span class="avatar"><?= e(initials($u['full_name'])) ?></span>
    <div><strong><?= e($u['full_name']) ?></strong><span class="muted"><?= e(ucfirst($u['role'])) ?></span></div>
  </div>
  <a class="logout-btn" href="<?= BASE_URL ?>/logout.php"><?= icon('logout', 18) ?> Log out</a>
</aside>
<div class="main">
  <header class="topbar">
    <button class="nav-toggle" type="button" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false"><?= icon('menu', 20) ?></button>
    <nav class="crumbs" aria-label="Breadcrumb">
      <a class="crumb-root" href="<?= BASE_URL ?>/<?= home_for($u['role']) ?>">Home</a><span class="sep">/</span>
      <span><?= e(ucfirst($u['role'])) ?></span><span class="sep">/</span><strong><?= e($title) ?></strong>
    </nav>
    <?php if ($u['role'] === 'admin'): ?>
    <form class="search" action="<?= BASE_URL ?>/admin/reports.php" method="get" role="search">
      <?= icon('search', 17) ?><label class="sr-only" for="top-search">Search reports</label>
      <input id="top-search" type="search" name="q" placeholder="Search reports...">
    </form>
    <?php endif; ?>
    <div class="top-actions">
      <span class="clock" data-clock><?= e(date('D, M j, g:i A')) ?></span>
      <a class="icon-btn" href="<?= BASE_URL ?>/<?= $bell ?>" aria-label="Notifications<?= $unread ? ' (' . $unread . ' unread)' : '' ?>">
        <?= icon('bell', 19) ?><?php if ($unread): ?><span class="count"><?= $unread ?></span><?php endif; ?>
      </a>
      <div class="dropdown">
        <button class="avatar-btn" type="button" data-dropdown aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
          <span class="avatar"><?= e(initials($u['full_name'])) ?></span>
        </button>
        <div class="dropdown-menu" role="menu">
          <div class="dropdown-head"><strong><?= e($u['full_name']) ?></strong><span class="muted"><?= e($u['email']) ?></span></div>
          <?php if ($u['role'] === 'resident'): ?><a role="menuitem" href="<?= BASE_URL ?>/resident/profile.php"><?= icon('user', 17) ?> My profile</a><?php endif; ?>
          <?php if ($u['role'] === 'admin'): ?><a role="menuitem" href="<?= BASE_URL ?>/admin/settings.php"><?= icon('sliders', 17) ?> Settings</a><?php endif; ?>
          <a role="menuitem" href="<?= BASE_URL ?>/logout.php"><?= icon('logout', 17) ?> Log out</a>
        </div>
      </div>
    </div>
  </header>
<?php if ($u['role'] === 'resident' && $current !== 'resident/sos.php'): ?>
  <a class="sos-fab" href="<?= BASE_URL ?>/resident/sos.php" aria-label="Emergency SOS"><span class="sos-ring"></span><?= icon('siren', 22) ?><b>SOS</b></a>
<?php endif; ?>
<?php endif; ?>
<main class="content" id="main">
  <?php if ($flash): ?>
    <div class="flash <?= e($flash['t']) ?> toast" role="<?= $flash['t'] === 'error' ? 'alert' : 'status' ?>">
      <span><?= e($flash['m']) ?></span><button type="button" class="toast-close" aria-label="Dismiss">&times;</button>
    </div>
  <?php endif; ?>
<?php
}
function render_footer() {
    ?>
</main>
<?php if (!empty($_SESSION['user'])): ?></div><?php endif; ?>
<script src="<?= BASE_URL ?>/assets/js/script.js"></script>
</body>
</html>
<?php
}

/* Show unread notifications as a small card (used on admin + volunteer dashboards), then mark them read */
function render_alerts($pdo, $user_id) {
    $st = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY id DESC LIMIT 8');
    $st->execute([$user_id]);
    $rows = $st->fetchAll();
    if (!$rows) return;
    echo '<div class="card"><h2>New alerts</h2>';
    foreach ($rows as $n) {
        $link = $n['report_id'] ? 'report_details.php?id=' . (int)$n['report_id'] : '#';
        echo '<a class="notif unread" href="' . $link . '">' . e($n['message']) . '<br><span class="muted">' . e(fmt_date($n['created_at'])) . '</span></a>';
    }
    echo '</div>';
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user_id]);
}


/* ---------- SMS (Semaphore, https://semaphore.co) ----------
   Returns [true|false, message]. Never throws, so a failed text never breaks a report update. */
function send_sms($pdo, $phone, $message) {
    $key = setting($pdo, 'sms_api_key', '');
    if ($key === '') return [false, 'No API key saved in Settings.'];
    $number = preg_replace('/[^0-9+]/', '', (string)$phone);
    if (strlen($number) < 10) return [false, 'Invalid phone number.'];
    if (!function_exists('curl_init')) return [false, 'The PHP cURL extension is not enabled.'];

    $data = ['apikey' => $key, 'number' => $number, 'message' => $message];
    if (($sender = setting($pdo, 'sms_sender', '')) !== '') $data['sendername'] = $sender;

    $ch = curl_init('https://api.semaphore.co/api/v4/messages');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) return [false, 'Could not reach the SMS service: ' . $err];
    return ($code >= 200 && $code < 300) ? [true, 'Sent.'] : [false, "SMS service replied $code: " . substr($resp, 0, 150)];
}

/* ---------- Maps (Leaflet + OpenStreetMap, no API key needed) ---------- */
function map_assets() {
    static $done = false;
    if ($done) return;
    $done = true;
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">'
       . '<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>';
}
function map_center($pdo) {
    return [(float)setting($pdo, 'map_lat', '8.4542'), (float)setting($pdo, 'map_lng', '124.6319')];
}
function render_map_picker($pdo, $lat = '', $lng = '') {
    map_assets();
    $has = is_numeric($lat) && is_numeric($lng);
    [$cl, $cn] = $has ? [(float)$lat, (float)$lng] : map_center($pdo);
    echo '<div class="map-card">'
       . '<div class="map-head"><strong>Pin the location</strong><span class="legend"><span class="legend-item"><i class="dot tone-primary"></i>Click the map to drop a pin</span></span></div>'
       . '<div id="map-picker" class="map" data-lat="' . $cl . '" data-lng="' . $cn . '" data-pin="' . ($has ? 1 : 0) . '"></div>'
       . '<input type="hidden" id="latitude" name="latitude" value="' . e($has ? $lat : '') . '">'
       . '<input type="hidden" id="longitude" name="longitude" value="' . e($has ? $lng : '') . '">'
       . '<div class="map-foot"><button type="button" id="locate" class="btn ghost small">' . icon('pin', 15) . ' Use my current location</button>'
       . '<span class="muted">The pin helps volunteers find the exact spot.</span></div></div>';
}
function render_map_view($r) {
    if ($r['latitude'] === null || $r['longitude'] === null) return;
    map_assets();
    $lat = (float)$r['latitude']; $lng = (float)$r['longitude'];
    echo '<div class="map-card"><div class="map-head"><strong>Pinned location</strong>'
       . '<a target="_blank" rel="noopener" href="https://www.google.com/maps?q=' . $lat . ',' . $lng . '">Open in Google Maps</a></div>'
       . '<div id="map-view" class="map" data-lat="' . $lat . '" data-lng="' . $lng . '"></div>'
       . '<div class="map-foot"><span class="legend-item"><i class="dot tone-primary"></i>Reported location</span>'
       . '<span class="muted">' . e(number_format($lat, 5)) . ', ' . e(number_format($lng, 5)) . '</span></div></div>';
}
/* All pinned reports (used by the admin dashboard and the analytics page) */
function incident_points($pdo) {
    $pts = [];
    foreach ($pdo->query('SELECT id, title, status, latitude, longitude FROM reports WHERE latitude IS NOT NULL') as $p) {
        $pts[] = ['lat' => (float)$p['latitude'], 'lng' => (float)$p['longitude'], 'ref' => ref_no($p['id']), 'title' => $p['title'],
                  'key' => $p['status'], 'status' => status_label($p['status']), 'url' => BASE_URL . '/admin/report_details.php?id=' . $p['id']];
    }
    return $pts;
}
function render_incident_map($pdo, $title = 'Incident map', $height = 420) {
    $pts = incident_points($pdo);
    [$mlat, $mlng] = map_center($pdo);
    echo '<section class="card map-card" id="incident-map"><div class="map-head"><div><h2>' . e($title) . '</h2>'
       . '<p class="muted">' . count($pts) . ' report' . (count($pts) === 1 ? '' : 's') . ' with a map pin</p></div><div class="legend">';
    foreach (['pending', 'assigned', 'in_progress', 'resolved', 'rejected'] as $s) {
        echo '<span class="legend-item"><i class="dot dot-' . $s . '"></i>' . e(status_label($s)) . '</span>';
    }
    echo '</div></div>';
    if (!$pts) {
        echo '<p class="empty">No reports have a map pin yet.</p>';
    } else {
        map_assets();
        echo '<div id="map-all" class="map" style="height:' . (int)$height . 'px" data-lat="' . $mlat . '" data-lng="' . $mlng . '" data-points="' . e(json_encode($pts)) . '"></div>';
    }
    echo '</section>';
}
function csv_safe($v) {   // stops Excel from running "=formulas" typed by users
    $v = (string)$v;
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}


/* ---------- v3 helpers: priority rules, SLA, announcements, hotlines, login throttle ---------- */
function default_priority($category_name) {
    $high = ['fire', 'medical emergency', 'flood', 'road accident', 'theft / crime'];
    $low  = ['noise disturbance', 'garbage / sanitation', 'broken streetlight / road'];
    $n = strtolower(trim((string)$category_name));
    return in_array($n, $high, true) ? 'high' : (in_array($n, $low, true) ? 'low' : 'medium');
}
function is_overdue($r) {   // still pending after 24 hours
    return ($r['status'] ?? '') === 'pending' && strtotime($r['created_at']) < time() - 86400;
}
function level_tone($l) { return ['info' => 'info', 'warning' => 'warning', 'critical' => 'danger'][$l] ?? 'info'; }

function render_announcements($pdo, $limit = 3) {
    $st = $pdo->prepare('SELECT a.*, u.full_name FROM announcements a JOIN users u ON u.id = a.user_id WHERE a.is_active = 1 ORDER BY a.id DESC LIMIT ' . (int)$limit);
    $st->execute();
    $rows = $st->fetchAll();
    if (!$rows) return;
    echo '<section class="alerts-strip" aria-label="Community announcements">';
    foreach ($rows as $a) {
        echo '<article class="ann ann-' . e($a['level']) . '"><span class="ann-ic">' . icon($a['level'] === 'critical' ? 'siren' : 'megaphone', 20) . '</span>'
           . '<div><strong>' . e($a['title']) . '</strong><p>' . nl2br(e($a['body'])) . '</p>'
           . '<span class="muted">' . e(fmt_date($a['created_at'])) . ' &middot; ' . e($a['full_name']) . '</span></div></article>';
    }
    echo '</section>';
}
function render_hotlines($pdo) {
    $items = [['Emergency', setting($pdo, 'emergency_hotline', '911'), 'siren'], ['Barangay', setting($pdo, 'contact_phone', ''), 'shield'],
              ['Fire', setting($pdo, 'hotline_fire', ''), 'flame'], ['Police', setting($pdo, 'hotline_police', ''), 'shield'],
              ['Medical', setting($pdo, 'hotline_medical', ''), 'plus']];
    echo '<div class="hotlines">';
    foreach ($items as [$label, $num, $ic]) {
        if ($num === '') continue;
        echo '<a class="hotline" href="tel:' . e(preg_replace('/[^0-9+]/', '', $num)) . '">' . icon($ic, 20) . '<span><small>' . e($label) . '</small><b>' . e($num) . '</b></span></a>';
    }
    echo '</div>';
}
/* Too many wrong passwords from one email or one IP = locked for 10 minutes */
function login_locked($pdo, $email) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE (email = ? OR ip = ?) AND created_at > (NOW() - INTERVAL 10 MINUTE)');
    $st->execute([strtolower($email), $_SERVER['REMOTE_ADDR'] ?? '']);
    return (int)$st->fetchColumn() >= 5;
}
function login_failed($pdo, $email) {
    $pdo->prepare('INSERT INTO login_attempts (email, ip) VALUES (?,?)')->execute([strtolower($email), $_SERVER['REMOTE_ADDR'] ?? '']);
    $pdo->exec('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)');
}
function login_clear($pdo, $email) {
    $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([strtolower($email)]);
}
/* Vertical animated columns, e.g. reports per day. $data = [label => number] */
function render_columns($data, $tone = 'primary') {
    if (!$data) { echo '<p class="empty">No data yet.</p>'; return; }
    $max = max(1, max($data));
    echo '<div class="cols">';
    foreach ($data as $label => $n) {
        echo '<div class="col tone-' . e($tone) . '" title="' . e($label) . ': ' . (int)$n . '"><b>' . (int)$n . '</b>'
           . '<span class="col-track"><i style="height:' . max(4, round($n / $max * 100)) . '%"></i></span><em>' . e($label) . '</em></div>';
    }
    echo '</div>';
}

/* ---------- UI helpers: icons, stat cards, report cards, bars, donut ---------- */
function icon($name, $size = 20) {
    static $p = [
        'dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'file'      => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'plus'      => '<circle cx="12" cy="12" r="10"/><path d="M12 8v8"/><path d="M8 12h8"/>',
        'list'      => '<path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/>',
        'bell'      => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'user'      => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'shield'    => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        'chart'     => '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
        'sliders'   => '<path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'clipboard' => '<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
        'pin'       => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'search'    => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'menu'      => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
        'clock'     => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'loader'    => '<path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/>',
        'check'     => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
        'tag'       => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
        'eye'       => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'siren'     => '<path d="M7 18v-6a5 5 0 1 1 10 0v6"/><path d="M5 21a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-1a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2z"/><path d="M21 12h1"/><path d="M18.5 4.5 18 5"/><path d="M2 12h1"/><path d="M12 2v1"/><path d="m4.929 4.929.707.707"/><path d="M12 12v6"/>',
        'megaphone' => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
        'flame'     => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
        'radar'     => '<path d="M19.07 4.93A10 10 0 0 0 6.99 3.34"/><path d="M4 6h.01"/><path d="M2.29 9.62A10 10 0 1 0 21.31 8.35"/><path d="M16.24 7.76A6 6 0 1 0 8.23 16.67"/><path d="M12 18h.01"/><path d="M17.99 11.66A6 6 0 0 1 15.77 16.67"/><circle cx="12" cy="12" r="2"/><path d="m13.41 10.59 5.66-5.66"/>',
        'calendar'  => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
    ];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($p[$name] ?? $p['file']) . '</svg>';
}

/* Colors per status / priority (names match the .tone-* classes in the CSS) */
function status_tones()   { return ['pending' => 'warning', 'assigned' => 'info', 'in_progress' => 'progress', 'resolved' => 'success', 'rejected' => 'danger']; }
function priority_tones() { return ['high' => 'danger', 'medium' => 'warning', 'low' => 'muted']; }

function greeting() {
    $h = (int)date('G');
    return $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');
}
function render_welcome($name, $subtitle, $actions = '') {
    echo '<section class="welcome"><div><p class="eyebrow" data-clock-date>' . e(date('l, F j, Y')) . '</p>'
       . '<h1>' . e(greeting()) . ', ' . e($name) . '</h1><p class="muted">' . e($subtitle) . '</p></div>'
       . '<div class="welcome-side"><span class="clock" data-clock>' . e(date('D, M j, g:i A')) . '</span>' . $actions . '</div></section>';
}

/* $trend = ['up'|'down'|'flat', 'text'] (optional) */
function stat_card($ic, $label, $value, $note = '', $tone = 'primary', $trend = null) {
    echo '<div class="stat tone-' . e($tone) . '"><span class="stat-label">' . e($label) . '</span>'
       . '<span class="stat-icon">' . icon($ic, 22) . '</span><b>' . e($value) . '</b><span class="stat-note">'
       . ($trend ? '<span class="trend ' . e($trend[0]) . '">' . e($trend[1]) . '</span>' : '') . e($note) . '</span></div>';
}

/* A report as a card. $r needs: id,title,status,priority,created_at (+ optional category,location,resident_name,volunteer_name) */
function render_report_card($r, $link) {
    $pr = $r['priority'] ?? 'medium';
    echo '<article class="report-card rc-' . e($pr) . (!empty($r['is_sos']) ? ' rc-sos' : '') . '"><div class="rc-top"><span class="rc-ref">' . ref_no($r['id']) . (!empty($r['is_sos']) ? ' <span class="badge sos">SOS</span>' : '') . (is_overdue($r) ? ' <span class="badge overdue">Overdue</span>' : '') . '</span>' . badge($r['status']) . '</div>'
       . '<h3 class="rc-title"><a href="' . e($link) . '">' . e($r['title']) . '</a></h3><ul class="rc-meta">';
    if (!empty($r['category']))      echo '<li>' . icon('tag', 15) . e($r['category']) . '</li>';
    if (!empty($r['location']))      echo '<li>' . icon('pin', 15) . e($r['location']) . '</li>';
    echo '<li>' . icon('calendar', 15) . e(fmt_date($r['created_at'])) . '</li>';
    if (!empty($r['resident_name'])) echo '<li>' . icon('user', 15) . 'Reported by ' . e($r['resident_name']) . '</li>';
    echo '<li>' . icon('shield', 15) . (!empty($r['volunteer_name']) ? 'Assigned to ' . e($r['volunteer_name']) : '<span>Not assigned yet</span>') . '</li></ul>'
       . '<div class="rc-foot">' . prio($pr) . '<a class="btn ghost small" href="' . e($link) . '">View details</a></div></article>';
}

/* Horizontal bars with count + percent. $data = [label => number], $tones = [label => tone] */
function render_bars($data, $tones = []) {
    if (!$data) { echo '<p class="empty">No data yet.</p>'; return; }
    $total = max(1, array_sum($data));
    $max = max(1, max($data));
    foreach ($data as $label => $n) {
        $pct = round($n / $total * 100);
        echo '<div class="bar" title="' . e(status_label($label)) . ': ' . (int)$n . ' (' . $pct . '%)">'
           . '<span class="l">' . e(status_label($label)) . '</span>'
           . '<span class="track"><i class="tone-' . e($tones[$label] ?? 'primary') . '" style="width:' . round($n / $max * 100) . '%"></i></span>'
           . '<b>' . (int)$n . '</b><em>' . $pct . '%</em></div>';
    }
}

/* Pure-CSS donut for the status overview */
function render_donut($counts) {
    $tones = status_tones();
    $total = array_sum($counts);
    $parts = []; $acc = 0;
    if ($total) {
        foreach ($tones as $s => $t) {
            $n = $counts[$s] ?? 0;
            if (!$n) continue;
            $from = round($acc, 2); $acc += $n / $total * 100;
            $parts[] = 'var(--' . $t . ') ' . $from . '% ' . round($acc, 2) . '%';
        }
    }
    $bg = $parts ? 'conic-gradient(' . implode(',', $parts) . ')' : 'var(--border)';
    echo '<div class="donut-wrap"><div class="donut" style="background:' . $bg . '"><div class="donut-center"><b>' . (int)$total . '</b><span>reports</span></div></div><ul class="legend-list">';
    foreach ($tones as $s => $t) echo '<li><i class="dot dot-' . $s . '"></i>' . e(status_label($s)) . '<b>' . (int)($counts[$s] ?? 0) . '</b></li>';
    echo '</ul></div>';
}

/* Latest updates as a timeline. rows need: report_id,title,status,remark,full_name,created_at */
function render_activity($rows, $link_base) {
    if (!$rows) { echo '<p class="empty">No activity yet.</p>'; return; }
    echo '<ol class="timeline">';
    foreach ($rows as $a) {
        echo '<li><a href="' . e($link_base . $a['report_id']) . '"><strong>' . ref_no($a['report_id']) . '</strong> ' . e($a['title']) . '</a> ' . badge($a['status'])
           . '<br><span class="muted">' . e(fmt_date($a['created_at'])) . ' by ' . e($a['full_name']) . '</span>';
        if (!empty($a['remark'])) echo '<p>' . e($a['remark']) . '</p>';
        echo '</li>';
    }
    echo '</ol>';
}