#!/usr/bin/env python
import os

# Create directories
api_dir = r'C:\xampp\htdocs\Library-Facilities-Booking-System\api'
logs_dir = r'C:\xampp\htdocs\Library-Facilities-Booking-System\logs'

os.makedirs(api_dir, exist_ok=True)
print(f'Created/verified directory: {api_dir}')

os.makedirs(logs_dir, exist_ok=True)
print(f'Created/verified directory: {logs_dir}')

# Write PHP file
php_file = os.path.join(api_dir, 'notify.php')
php_content = '''<?php
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

with open(php_file, 'w', encoding='utf-8') as f:
    f.write(php_content)

# Check file size
file_size = os.path.getsize(php_file)
print(f'\nFile written successfully: {php_file}')
print(f'File size: {file_size} bytes')
