<?php
/**
 * setup.php — Run this once in the browser to create the database, tables, and seed data.
 * Visit: http://localhost/Library-Facilities-Booking-System/setup.php
 */

$host = 'localhost';
$db   = 'library_booking';
$user = 'root';
$pass = '';

$steps = [];

try {
    // 1. Connect without DB (so we can create it)
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 2. Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");
    $steps[] = ['ok', "Database <strong>$db</strong> ready."];

    // 3. Create tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(120) NOT NULL,
        email      VARCHAR(180) NOT NULL UNIQUE,
        student_id VARCHAR(40)  DEFAULT NULL,
        password   VARCHAR(255) NOT NULL,
        role       ENUM('student','faculty','admin') NOT NULL DEFAULT 'student',
        status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $steps[] = ['ok', 'Table <strong>users</strong> ready.'];

    $pdo->exec("CREATE TABLE IF NOT EXISTS facilities (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        name             VARCHAR(120) NOT NULL,
        type             VARCHAR(60)  DEFAULT NULL,
        location         VARCHAR(120) DEFAULT NULL,
        capacity         INT          DEFAULT 0,
        open_time        TIME         DEFAULT '08:00:00',
        close_time       TIME         DEFAULT '18:00:00',
        description      TEXT         DEFAULT NULL,
        equipment        TEXT         DEFAULT NULL,
        status           ENUM('active','inactive') NOT NULL DEFAULT 'active',
        instant_booking  TINYINT(1)   NOT NULL DEFAULT 0,
        requires_letter  TINYINT(1)   NOT NULL DEFAULT 0,
        max_bookings_day INT          NOT NULL DEFAULT 0,
        allowed_slots    TEXT         DEFAULT NULL,
        purpose_options  TEXT         DEFAULT NULL,
        facility_group   VARCHAR(50)  DEFAULT NULL,
        created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $steps[] = ['ok', 'Table <strong>facilities</strong> ready.'];

    // Silent column migrations (safe on re-run) for older installs
    foreach ([
        "ALTER TABLE facilities ADD COLUMN description TEXT DEFAULT NULL AFTER close_time",
        "ALTER TABLE facilities ADD COLUMN equipment   TEXT DEFAULT NULL AFTER description",
        "ALTER TABLE facilities ADD COLUMN updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",

        // Bookings enhancements
        "ALTER TABLE bookings ADD COLUMN program VARCHAR(20) DEFAULT NULL AFTER attendees_count",
        "ALTER TABLE bookings ADD COLUMN level   VARCHAR(10) DEFAULT NULL AFTER program",
    ] as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $ignored) {}
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        user_id          INT          NOT NULL,
        facility_id      INT          NOT NULL,
        booking_date     DATE         NOT NULL,
        start_time       TIME         NOT NULL,
        end_time         TIME         NOT NULL,
        attendees_count  INT          DEFAULT 1,
        program          VARCHAR(20)  DEFAULT NULL,
        level            VARCHAR(10)  DEFAULT NULL,
        purpose          TEXT         DEFAULT NULL,
        status           ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
        letter_path      VARCHAR(255) DEFAULT NULL,
        admin_remarks    TEXT         DEFAULT NULL,
        created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
        FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    $steps[] = ['ok', 'Table <strong>bookings</strong> ready.'];

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT  NOT NULL,
        message    TEXT NOT NULL,
        is_read    TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    $steps[] = ['ok', 'Table <strong>notifications</strong> ready.'];

    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        email      VARCHAR(180) NOT NULL,
        token      VARCHAR(64)  NOT NULL,
        expires_at DATETIME     NOT NULL,
        used       TINYINT(1)   DEFAULT 0,
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $steps[] = ['ok', 'Table <strong>password_resets</strong> ready.'];

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
    $steps[] = ['ok', 'Table <strong>announcements</strong> ready.'];

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
    $steps[] = ['ok', 'Table <strong>help_requests</strong> ready.'];

    // Seed a default announcement (only if none exist)
    $aCount = (int)$pdo->query('SELECT COUNT(*) FROM announcements')->fetchColumn();
    if ($aCount === 0) {
        $pdo->prepare('INSERT INTO announcements (title, body, type, is_active, created_by) VALUES (?,?,?,?,?)')
            ->execute(['Welcome!', 'You can now book library facilities online. Please follow booking rules and schedules.', 'info', 1, null]);
        $steps[] = ['ok', 'Seeded a default announcement.'];
    }

    // 4. Seed admin user (always reset password so you can always log in)
    $admin = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $admin->execute(['admin@library.com']);
    if (!$admin->fetch()) {
        $pdo->prepare('INSERT INTO users (name,email,password,role,status) VALUES (?,?,?,?,?)')
            ->execute(['Administrator', 'admin@library.com', password_hash('Admin@123', PASSWORD_DEFAULT), 'admin', 'active']);
        $steps[] = ['ok', 'Admin account created: <strong>admin@library.com</strong> / <strong>Admin@123</strong>'];
    } else {
        // Reset password and ensure account is active
        $pdo->prepare('UPDATE users SET password=?, status=?, name=? WHERE email=?')
            ->execute([password_hash('Admin@123', PASSWORD_DEFAULT), 'active', 'Administrator', 'admin@library.com']);
        $steps[] = ['ok', 'Admin account reset: <strong>admin@library.com</strong> / <strong>Admin@123</strong>'];
    }

    // 5. Seed demo student
    $stu = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stu->execute(['student@library.com']);
    if (!$stu->fetch()) {
        $pdo->prepare('INSERT INTO users (name,email,student_id,password,role,status) VALUES (?,?,?,?,?,?)')
            ->execute(['Juan dela Cruz', 'student@library.com', 'STU-001', password_hash('Student@123', PASSWORD_DEFAULT), 'student', 'active']);
        $steps[] = ['ok', 'Demo student created: <strong>student@library.com</strong> / <strong>Student@123</strong>'];
    } else {
        $steps[] = ['info', 'Demo student already exists.'];
    }

    // 6. Seed demo faculty
    $fac = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $fac->execute(['faculty@library.com']);
    if (!$fac->fetch()) {
        $pdo->prepare('INSERT INTO users (name,email,password,role,status) VALUES (?,?,?,?,?)')
            ->execute(['Prof. Maria Santos', 'faculty@library.com', password_hash('Faculty@123', PASSWORD_DEFAULT), 'faculty', 'active']);
        $steps[] = ['ok', 'Demo faculty created: <strong>faculty@library.com</strong> / <strong>Faculty@123</strong>'];
    } else {
        $steps[] = ['info', 'Demo faculty already exists.'];
    }

    // 7. Seed facilities
    $facilities = [
        // name, type, location, capacity, open, close, instant, requires_letter, max_day, slots_json, purposes_json, group
        ['CL Room 1',     'collaboration_room',   'Main Library, CL Building', 7,   '08:00', '18:00', 1, 0, 0, null, null, 'cl'],
        ['CL Room 2',     'collaboration_room',   'Main Library, CL Building', 8,   '08:00', '18:00', 1, 0, 0, null, null, 'cl'],
        ['CL Room 3',     'collaboration_room',   'Main Library, CL Building', 2,   '08:00', '18:00', 1, 0, 0, null, null, 'cl'],
        ['Museum',        'artifacts_room',       'Main Library',              50,  '08:00', '18:00', 0, 1, 0, null, null, 'library'],
        ['EIRC',          'electronic_resources', 'Main Library',              30,  '08:00', '18:00', 0, 1, 0, null, null, 'library'],
        ['Reading Area',  'reading_hall',  'Morelos Building',          200, '07:00', '17:00', 0, 0, 4,
            '[{"label":"7:00 AM – 10:00 AM","start":"07:00","end":"10:00"},{"label":"1:00 PM – 5:00 PM","start":"13:00","end":"17:00"}]',
            '["Class Reading Session","Research Work","Lecture","Study Group","Others"]',
            'morelos'],
        ['Faculty Area',  'meeting_room',  'Morelos Building',          30,  '07:00', '17:00', 0, 0, 0,
            '[{"label":"7:00 AM – 12:00 PM","start":"07:00","end":"12:00"},{"label":"1:00 PM – 5:00 PM","start":"13:00","end":"17:00"}]',
            '["Faculty Meeting","Department Review","Student Consultation","Research Discussion","Others"]',
            'morelos'],
    ];

    $ins = $pdo->prepare('INSERT INTO facilities
        (name,type,location,capacity,open_time,close_time,status,instant_booking,requires_letter,max_bookings_day,allowed_slots,purpose_options,facility_group)
        VALUES (?,?,?,?,?,?,"active",?,?,?,?,?,?)');

    foreach ($facilities as $f) {
        $chk = $pdo->prepare('SELECT id FROM facilities WHERE name = ?');
        $chk->execute([$f[0]]);
        if (!$chk->fetch()) {
            $ins->execute([$f[0],$f[1],$f[2],$f[3],$f[4],$f[5],$f[6],$f[7],$f[8],$f[9],$f[10],$f[11]]);
            $steps[] = ['ok', "Facility <strong>{$f[0]}</strong> seeded."];
        } else {
            // Update facility_group and type in case they were seeded with old values
            $pdo->prepare('UPDATE facilities SET facility_group=?, type=? WHERE name=?')->execute([$f[11], $f[1], $f[0]]);
            $steps[] = ['info', "Facility <strong>{$f[0]}</strong> already exists (group/type updated)."];
        }
    }

    $steps[] = ['done', '<strong>Setup complete!</strong> You can now <a href="/">go to the homepage</a>.'];

} catch (PDOException $e) {
    $steps[] = ['error', 'Database error: ' . htmlspecialchars($e->getMessage())];
}

// ── Create required directories ──────────────────────────────────────────────
$dirs = [
    __DIR__ . '/uploads',
    __DIR__ . '/api',
    __DIR__ . '/logs',
];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        $steps[] = ['ok', "Directory created: <code>" . basename($dir) . "/</code>"];
    }
}

// ── Write api/notify.php if missing ──────────────────────────────────────────
$notifyFile = __DIR__ . '/api/notify.php';
if (!file_exists($notifyFile) || filesize($notifyFile) === 0) {
    $notifyContent = <<<'PHP'
<?php
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
        $sc = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
        $sc->execute([$uid]);
        $count = (int)$sc->fetchColumn();
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
    case 'fetch_count':
        $sc = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
        $sc->execute([$uid]);
        echo json_encode(['ok' => true, 'count' => (int)$sc->fetchColumn()]);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
exit;
PHP;
    file_put_contents($notifyFile, $notifyContent);
    $steps[] = ['ok', 'Created <code>api/notify.php</code>'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Setup — Library Facilities Booking System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:680px">
  <h2 class="mb-1">Library Facilities Booking System</h2>
  <p class="text-muted mb-4">Setup &amp; Database Initialisation</p>
  <?php foreach ($steps as [$type, $msg]): ?>
    <?php
      $cls = match($type) {
          'ok'    => 'success',
          'info'  => 'info',
          'done'  => 'primary',
          default => 'danger',
      };
      $icon = match($type) {
          'ok'    => '✔',
          'info'  => 'ℹ',
          'done'  => '🎉',
          default => '✘',
      };
    ?>
    <div class="alert alert-<?= $cls ?> py-2"><?= $icon ?> <?= $msg ?></div>
  <?php endforeach; ?>
</div>
</body>
</html>