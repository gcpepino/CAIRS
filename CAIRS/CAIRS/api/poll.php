<?php
// Small JSON endpoint the pages poll every few seconds: unread count for everyone,
// plus the newest active SOS for the admin. Read-only, no CSRF needed for GET.
require __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$u = $_SESSION['user'] ?? null;
session_write_close();                       // do not hold the session lock while we query
if (!$u) { http_response_code(401); echo '{"error":"login"}'; exit; }

$st = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$st->execute([$u['id']]);
$out = ['unread' => (int)$st->fetchColumn()];

if ($u['role'] === 'admin') {
    $active = "is_sos = 1 AND status IN ('pending','assigned','in_progress')";
    $out['sos'] = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE $active")->fetchColumn();
    $last = $pdo->query("SELECT id, title FROM reports WHERE $active ORDER BY id DESC LIMIT 1")->fetch();
    $out['sos_last']  = $last ? (int)$last['id'] : 0;
    $out['sos_title'] = $last ? ref_no($last['id']) . ' ' . $last['title'] : '';
    $out['sos_url']   = $last ? BASE_URL . '/admin/report_details.php?id=' . (int)$last['id'] : '';
}
echo json_encode($out);
