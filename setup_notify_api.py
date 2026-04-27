import os

# Create the api directory
api_dir = r'C:\xampp\htdocs\Library-Facilities-Booking-System\api'
os.makedirs(api_dir, exist_ok=True)
print('dir created')

# Write the notify.php file
notify_file = os.path.join(api_dir, 'notify.php')
content = '''<?php
/**
 * api/notify.php — Notification AJAX endpoint
 * Actions: mark_one, mark_all, delete_one, delete_all, fetch_count
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
    exit;
}

$uid    = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    $id     = (int)($_GET['id'] ?? 0);
} else {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $body['action'] ?? '';
        $id     = (int)($body['id'] ?? 0);
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int)($_POST['id'] ?? 0);
    }
}

try {
    switch ($action) {
        case 'mark_one':
            $stmt = $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?');
            $stmt->execute([$id, $uid]);
            echo json_encode(['ok' => true, 'affected' => $stmt->rowCount()]);
            break;
        case 'mark_all':
            $stmt = $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0');
            $stmt->execute([$uid]);
            echo json_encode(['ok' => true, 'affected' => $stmt->rowCount()]);
            break;
        case 'delete_one':
            $stmt = $pdo->prepare('DELETE FROM notifications WHERE id=? AND user_id=?');
            $stmt->execute([$id, $uid]);
            echo json_encode(['ok' => true, 'affected' => $stmt->rowCount()]);
            break;
        case 'delete_all':
            $stmt = $pdo->prepare('DELETE FROM notifications WHERE user_id=?');
            $stmt->execute([$uid]);
            echo json_encode(['ok' => true, 'affected' => $stmt->rowCount()]);
            break;
        case 'fetch_count':
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
            $stmt->execute([$uid]);
            echo json_encode(['ok' => true, 'count' => (int)$stmt->fetchColumn()]);
            break;
        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
'''

with open(notify_file, 'w') as f:
    f.write(content)

# Check file size
file_size = os.path.getsize(notify_file)
print(f'File created: {notify_file}')
print(f'File size: {file_size} bytes')
