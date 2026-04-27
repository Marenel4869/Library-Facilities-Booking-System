<?php
// ── Auth & session ────────────────────────────────────────────────────────────

// Enforce login; optionally restrict to a role; checks session timeout
function requireLogin($role = null) {
    checkSessionTimeout();
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/');
        exit;
    }
    if ($role && $_SESSION['role'] !== $role) {
        header('Location: ' . BASE_URL . '/' . $_SESSION['role'] . '/dashboard.php');
        exit;
    }
}

// Idle-timeout: redirect after SESSION_TIMEOUT seconds of inactivity
function checkSessionTimeout() {
    if (!isset($_SESSION['user_id'])) return;
    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800;
    if (isset($_SESSION['_last_active']) && (time() - $_SESSION['_last_active']) > $timeout) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/?timeout=1');
        exit;
    }
    $_SESSION['_last_active'] = time();
}

// ── CSRF ─────────────────────────────────────────────────────────────────────

// Return (and store) a CSRF token for the current session
function csrfToken() {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

// Hidden CSRF input field (drop into any <form>)
function csrfField() {
    return '<input type="hidden" name="_csrf" value="' . csrfToken() . '">';
}

// Verify CSRF token passed as GET parameter (for download/export links)
function verifyCsrfGet() {
    $token = $_GET['_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(403);
        die('<p style="font-family:sans-serif;padding:20px;color:#c00">Invalid or expired request token. Please go back and try again.</p>');
    }
}

// Verify POST CSRF token; die with 403 on failure
function verifyCsrf() {
    $token = $_POST['_csrf'] ?? '';
    if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(403);
        die('<p style="font-family:sans-serif;padding:20px;color:#c00">Invalid request (CSRF check failed). Please go back and try again.</p>');
    }
}

// ── Output / flash ────────────────────────────────────────────────────────────

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function flash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function showFlash() {
    if (!isset($_SESSION['flash'])) return '';
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $allowed = ['success','danger','warning','info','primary','secondary'];
    $type    = in_array($f['type'], $allowed) ? $f['type'] : 'info';
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">'
         . e($f['msg'])
         . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// ── Badges ────────────────────────────────────────────────────────────────────

function statusBadge($status) {
    $colors = [
        'pending'   => 'warning text-dark',
        'approved'  => 'success',
        'rejected'  => 'danger',
        'cancelled' => 'secondary',
        'active'    => 'success',
        'inactive'  => 'secondary',
        'suspended' => 'danger',
    ];
    $c = $colors[$status] ?? 'secondary';
    return '<span class="badge bg-' . $c . '">' . ucfirst(e($status)) . '</span>';
}

// ── Slot / booking helpers ────────────────────────────────────────────────────

function slotAvailable($pdo, $facilityId, $date, $start, $end, $excludeId = 0) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM bookings
         WHERE facility_id = ? AND booking_date = ?
           AND status IN ("pending","approved") AND id != ?
           AND NOT (end_time <= ? OR start_time >= ?)'
    );
    $stmt->execute([$facilityId, $date, $excludeId, $start, $end]);
    return $stmt->fetchColumn() == 0;
}

// ── File upload ───────────────────────────────────────────────────────────────

function uploadLetter($file) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) return null;

    $maxSize     = 5 * 1024 * 1024;  // 5 MB
    $allowedExts  = ['pdf','jpg','jpeg','png'];
    $allowedMimes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    if ($file['size'] > $maxSize) return null;

    // Extension check
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) return null;

    // MIME type check via finfo (not spoofable via filename)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowedMimes, true)) return null;

    // Ensure upload directory exists and is not web-executable via .htaccess
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
        file_put_contents(UPLOAD_DIR . '.htaccess', "Options -ExecCGI\nAddHandler cgi-script .php .pl .py\n");
    }

    $name = 'letter_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) return null;
    return $name;
}

// ── Facility labels ───────────────────────────────────────────────────────────

function facilityLabel($type) {
    $map = [
        'collaboration_room'   => 'Collaboration/Study Room',
        'electronic_resources' => 'Electronic Resources Room',
        'meeting_room'         => 'Meeting/Discussion Room',
        'artifacts_room'       => 'Artifacts Room',
        'reading_hall'         => 'Reading Hall',
        'study_room'           => 'Study Room',
        'conference_room'      => 'Conference Room',
        'computer_lab'         => 'Computer Lab',
        'auditorium'           => 'Auditorium',
        'other'                => 'Other',
    ];
    return $map[$type] ?? ucfirst(str_replace('_', ' ', (string)$type));
}

function programOptions() {
    return ['AP','ASP','BAP','CJEP','CSP','ETP','GRAD','LAW','NP','TEP','THMP'];
}

// ── Announcements & Help Requests ─────────────────────────────────────────────

function truncateText($text, $max = 140) {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (function_exists('mb_strlen') && mb_strlen($text) > $max) {
        return mb_substr($text, 0, $max - 1) . '…';
    }
    if (strlen($text) > $max) {
        return substr($text, 0, $max - 1) . '…';
    }
    return $text;
}

function ensureContentTables($pdo) {
    // Safe to call multiple times; uses IF NOT EXISTS.
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        title      VARCHAR(255) NOT NULL,
        body       TEXT NOT NULL,
        type       ENUM('info','warning','success','danger') NOT NULL DEFAULT 'info',
        starts_at  DATETIME NULL,
        ends_at    DATETIME NULL,
        is_active  TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (is_active),
        INDEX (starts_at),
        INDEX (ends_at)
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS help_requests (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        role       VARCHAR(20) NOT NULL,
        subject    VARCHAR(255) NOT NULL,
        message    TEXT NOT NULL,
        status     ENUM('open','closed') NOT NULL DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (status)
    ) ENGINE=InnoDB");
}

function fetchActiveAnnouncements($pdo, $limit = 3) {
    $limit = max(1, min(20, (int)$limit));
    ensureContentTables($pdo);
    $stmt = $pdo->prepare(
        'SELECT id,title,body,type,starts_at,ends_at,created_at
         FROM announcements
         WHERE is_active=1
           AND (starts_at IS NULL OR starts_at <= NOW())
           AND (ends_at   IS NULL OR ends_at   >= NOW())
         ORDER BY created_at DESC
         LIMIT ' . $limit
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

// ── Audit ─────────────────────────────────────────────────────────────────────

function logAudit($pdo, $action, $details = '') {
    $uid = $_SESSION['user_id'] ?? null;
    try {
        $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?,?,?,?)')
            ->execute([$uid, $action, substr($details, 0, 500), $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (PDOException $e) {
        // Silently log to file so audit failure never breaks the user flow
        $logDir = defined('UPLOAD_DIR') ? dirname(UPLOAD_DIR) . '/logs/' : __DIR__ . '/../logs/';
        @file_put_contents($logDir . 'audit_errors.log',
            date('[Y-m-d H:i:s]') . " {$e->getMessage()}\n", FILE_APPEND);
    }
}

// ── Login rate limiting (DB-backed per IP) ───────────────────────────────────

function checkLoginRateLimit($pdo = null) {
    $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $max     = defined('LOGIN_MAX_ATTEMPTS') ? LOGIN_MAX_ATTEMPTS : 5;
    $lockSec = defined('LOGIN_LOCKOUT_SEC')  ? LOGIN_LOCKOUT_SEC  : 900;

    if ($pdo) {
        try {
            $row = $pdo->prepare('SELECT attempts, locked_until FROM login_attempts WHERE ip_address = ?');
            $row->execute([$ip]);
            $rec = $row->fetch();
            if ($rec && $rec['locked_until'] && strtotime($rec['locked_until']) > time()) {
                $remaining = strtotime($rec['locked_until']) - time();
                return 'Too many failed attempts. Please wait ' . ceil($remaining / 60) . ' minute(s).';
            }
            // Expired lock — reset it
            if ($rec && $rec['locked_until'] && strtotime($rec['locked_until']) <= time()) {
                $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = ?')->execute([$ip]);
            }
        } catch (PDOException $e) { /* fall through */ }
        return null;
    }

    // Session fallback
    $lockKey = 'login_locked_until_' . md5($ip);
    if (!empty($_SESSION[$lockKey]) && time() < $_SESSION[$lockKey]) {
        $remaining = $_SESSION[$lockKey] - time();
        return 'Too many failed attempts. Please wait ' . ceil($remaining / 60) . ' minute(s).';
    }
    return null;
}

function recordLoginFailure($pdo = null) {
    $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $max     = defined('LOGIN_MAX_ATTEMPTS') ? LOGIN_MAX_ATTEMPTS : 5;
    $lockSec = defined('LOGIN_LOCKOUT_SEC')  ? LOGIN_LOCKOUT_SEC  : 900;

    if ($pdo) {
        try {
            $pdo->prepare(
                'INSERT INTO login_attempts (ip_address, attempts) VALUES (?, 1)
                 ON DUPLICATE KEY UPDATE attempts = attempts + 1, updated_at = NOW()'
            )->execute([$ip]);
            $row = $pdo->prepare('SELECT attempts FROM login_attempts WHERE ip_address = ?');
            $row->execute([$ip]);
            $attempts = (int)($row->fetchColumn() ?: 0);
            if ($attempts >= $max) {
                $lockedUntil = date('Y-m-d H:i:s', time() + $lockSec);
                $pdo->prepare('UPDATE login_attempts SET locked_until = ? WHERE ip_address = ?')
                    ->execute([$lockedUntil, $ip]);
            }
        } catch (PDOException $e) { /* fall through */ }
        return;
    }

    // Session fallback
    $key     = 'login_attempts_' . md5($ip);
    $lockKey = 'login_locked_until_' . md5($ip);
    $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
    if ($_SESSION[$key] >= $max) {
        $_SESSION[$lockKey] = time() + $lockSec;
        $_SESSION[$key]     = 0;
    }
}

function clearLoginFailures($pdo = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if ($pdo) {
        try {
            $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = ?')->execute([$ip]);
        } catch (PDOException $e) { /* ignore */ }
        return;
    }
    // Session fallback
    $key     = 'login_attempts_' . md5($ip);
    $lockKey = 'login_locked_until_' . md5($ip);
    unset($_SESSION[$key], $_SESSION[$lockKey]);
}

// ── Notifications ─────────────────────────────────────────────────────────────

function createNotification($pdo, $userId, $message, $type = 'info', $link = '') {
    try {
        $pdo->prepare('INSERT INTO notifications (user_id, message, type, link) VALUES (?,?,?,?)')
            ->execute([(int)$userId, $message, $type, $link ?: null]);
    } catch (PDOException $e) {}
}

function unreadNotifCount($pdo) {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if (!$uid) return 0;
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
        $stmt->execute([$uid]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) { return 0; }
}

function recentNotifications($pdo, $limit = 8) {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if (!$uid) return [];

    $limit = (int)$limit;
    if ($limit < 1) $limit = 1;
    if ($limit > 50) $limit = 50;

    try {
        // With PDO::ATTR_EMULATE_PREPARES=false (native prepares), MySQL may reject placeholders in LIMIT.
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT {$limit}");
        $stmt->execute([$uid]);
        return $stmt->fetchAll();
    } catch (PDOException $e) { return []; }
}

// ── Reminders ─────────────────────────────────────────────────────────────────

function checkReminders($pdo) {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if (!$uid) return;
    try {
        $stmt = $pdo->prepare(
            'SELECT b.id, b.booking_date, b.start_time, f.name AS fname
             FROM bookings b JOIN facilities f ON b.facility_id=f.id
             WHERE b.user_id=? AND b.status="approved" AND b.reminder_sent=0
               AND TIMESTAMP(b.booking_date, b.start_time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->execute([$uid]);
        foreach ($stmt->fetchAll() as $bk) {
            $dt = date('M j, Y', strtotime($bk['booking_date']));
            $tm = date('g:i A',  strtotime($bk['start_time']));
            createNotification($pdo, $uid,
                "⏰ Reminder: Your booking for \"{$bk['fname']}\" is tomorrow on {$dt} at {$tm}.",
                'warning', BASE_URL . '/notifications.php'
            );
            $pdo->prepare('UPDATE bookings SET reminder_sent=1 WHERE id=?')->execute([$bk['id']]);
            sendEmailSim($uid, $pdo,
                "Booking Reminder — {$bk['fname']}",
                "Your booking for \"{$bk['fname']}\" is scheduled for {$dt} at {$tm}."
            );
        }
    } catch (PDOException $e) {}
}

// ── Email simulation ──────────────────────────────────────────────────────────

function sendEmailSim($userId, $pdo, $subject, $body) {
    try {
        $stmt = $pdo->prepare('SELECT name, email FROM users WHERE id=?');
        $stmt->execute([(int)$userId]);
        $user = $stmt->fetch();
        if (!$user) return;

        $headers = "From: noreply@library.local\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        $fullMsg = "Dear {$user['name']},\r\n\r\n{$body}\r\n\r\n— Library Facilities Booking System";

        if (!@mail($user['email'], $subject, $fullMsg, $headers)) {
            $logDir = defined('UPLOAD_DIR') ? dirname(UPLOAD_DIR) . '/logs/' : __DIR__ . '/../logs/';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0750, true);
                @file_put_contents($logDir . '.htaccess', "Require all denied\n");
            }
            $entry = '[' . date('Y-m-d H:i:s') . "] TO:{$user['email']} | SUBJECT:{$subject}\n{$body}\n---\n";
            @file_put_contents($logDir . 'email_sim.log', $entry, FILE_APPEND);
        }
    } catch (PDOException $e) {}
}