@echo off
REM Create directories
mkdir "C:\xampp\htdocs\Library-Facilities-Booking-System\api" 2>nul
mkdir "C:\xampp\htdocs\Library-Facilities-Booking-System\logs" 2>nul

REM Create File 1: api\notify.php
(
echo ^<?php
echo require_once __DIR__ . '/../config/database.php';
echo require_once __DIR__ . '/../includes/functions.php';
echo.
echo header('Content-Type: application/json');
echo.
echo if (!isset($_SESSION['user_id']^)^) {
echo     echo json_encode(['success' =^> false]^);
echo     exit;
echo }
echo.
echo $uid    = (int^)$_SESSION['user_id'];
echo $action = $_POST['action'] ?? $_GET['action'] ?? '';
echo.
echo switch ($action^) {
echo.
echo     case 'mark_all':
echo         $pdo-^>prepare('UPDATE notifications SET is_read=1 WHERE user_id=?'^)-^>execute([$uid]^);
echo         echo json_encode(['success' =^> true]^);
echo         break;
echo.
echo     case 'mark_one':
echo         $nid = (int^)($_POST['id'] ?? 0^);
echo         $pdo-^>prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?'^)-^>execute([$nid, $uid]^);
echo         echo json_encode(['success' =^> true]^);
echo         break;
echo.
echo     case 'count':
echo         $count = (int^)$pdo-^>query("SELECT COUNT(*^) FROM notifications WHERE user_id=$uid AND is_read=0"^)-^>fetchColumn(^);
echo         echo json_encode(['success' =^> true, 'count' =^> $count]^);
echo         break;
echo.
echo     case 'delete_one':
echo         $nid = (int^)($_POST['id'] ?? 0^);
echo         $pdo-^>prepare('DELETE FROM notifications WHERE id=? AND user_id=?'^)-^>execute([$nid, $uid]^);
echo         echo json_encode(['success' =^> true]^);
echo         break;
echo.
echo     case 'delete_all':
echo         $pdo-^>prepare('DELETE FROM notifications WHERE user_id=?'^)-^>execute([$uid]^);
echo         echo json_encode(['success' =^> true]^);
echo         break;
echo.
echo     default:
echo         echo json_encode(['success' =^> false, 'message' =^> 'Unknown action.']^);
echo }
echo exit;
echo ?^>
) > "C:\xampp\htdocs\Library-Facilities-Booking-System\api\notify.php"

REM Create empty .gitkeep file
type nul > "C:\xampp\htdocs\Library-Facilities-Booking-System\logs\.gitkeep"

echo Files created successfully!
