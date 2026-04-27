import os

# Create directories
for d in [r'C:\xampp\htdocs\Library-Facilities-Booking-System\api',
          r'C:\xampp\htdocs\Library-Facilities-Booking-System\logs']:
    os.makedirs(d, exist_ok=True)
    print(f'Created: {d}')

# Write notify.php
content = '''<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$uid    = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'mark_all':
        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$uid]);
        echo json_encode(['success' => true]);
        break;
    case 'mark_one':
        $nid = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([$nid, $uid]);
        echo json_encode(['success' => true]);
        break;
    case 'count':
        $count = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid AND is_read=0")->fetchColumn();
        echo json_encode(['success' => true, 'count' => $count]);
        break;
    case 'delete_one':
        $nid = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM notifications WHERE id=? AND user_id=?')->execute([$nid, $uid]);
        echo json_encode(['success' => true]);
        break;
    case 'delete_all':
        $pdo->prepare('DELETE FROM notifications WHERE user_id=?')->execute([$uid]);
        echo json_encode(['success' => true]);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
exit;
'''

path = r'C:\xampp\htdocs\Library-Facilities-Booking-System\api\notify.php'
with open(path, 'w', encoding='utf-8') as f:
    f.write(content)
print(f'Written {path}: {os.path.getsize(path)} bytes')
