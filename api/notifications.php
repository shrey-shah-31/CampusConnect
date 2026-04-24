<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    require_api_role(['student','company','admin']);
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
    $countStmt->execute([$_SESSION['user_id']]);
    $count = (int)$countStmt->fetchColumn();

    $listStmt = $pdo->prepare('
        SELECT id, message, is_read, created_at
        FROM notifications
        WHERE user_id=?
        ORDER BY is_read ASC, created_at DESC
        LIMIT 20
    ');
    $listStmt->execute([$_SESSION['user_id']]);
    $notifications = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $payload = array_map(static function ($row): array {
        return [
            'id' => (int)$row['id'],
            'message' => (string)$row['message'],
            'is_read' => (int)$row['is_read'] === 1,
            'created_at' => (string)$row['created_at'],
        ];
    }, $notifications);

    json_response([
        'count' => $count,
        'notifications' => $payload,
    ]);
}
if ($method === 'POST') {
    require_api_role(['student','company','admin']);
    $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$_SESSION['user_id']]);
    json_response(['message' => 'Notifications marked as read']);
}
if ($method === 'PUT') {
    require_api_role(['student','company','admin']);
    $data = json_input();
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    if ($id <= 0) {
        json_response(['error' => 'id is required'], 422);
    }

    $markStmt = $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?');
    $markStmt->execute([$id, $_SESSION['user_id']]);
    if ($markStmt->rowCount() === 0) {
        json_response(['error' => 'Notification not found'], 404);
    }

    json_response(['message' => 'Notification marked as read']);
}
json_response(['error' => 'Method not allowed'], 405);
