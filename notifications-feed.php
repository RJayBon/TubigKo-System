<?php
/**
 * TubigKo — lightweight notification feed for near-real-time page updates.
 * This endpoint is read-only and returns only notifications visible to the
 * authenticated user. The frontend polls it while a page is open.
 */

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/data.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$me = current_user();
if (!$me) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = (int)$me['id'];
$notifications = current_role() === 'admin'
    ? load_notifications()
    : load_notifications($userId);

$unreadRow = db_one(
    'SELECT COUNT(*) AS c FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0',
    [$userId]
);

$latestId = 0;
foreach ($notifications as $notification) {
    $latestId = max($latestId, (int)$notification['db_id']);
}

echo json_encode([
    'ok' => true,
    'latest_id' => $latestId,
    'unread_count' => (int)($unreadRow['c'] ?? 0),
    'notifications' => $notifications,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
