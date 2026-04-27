<?php
require_once __DIR__ . '/config.php';

$host = DB_HOST;
$db   = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;

try {
    // Connect without DB first so we can CREATE it if missing
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,   false);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db`
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");

    // ── 1. USERS ─────────────────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id`             INT          NOT NULL AUTO_INCREMENT,
        `name`           VARCHAR(120) NOT NULL,
        `email`          VARCHAR(180) NOT NULL,
        `id_number`      VARCHAR(40)  DEFAULT NULL,
        `password`       VARCHAR(255) NOT NULL,
        `role`           ENUM('student','faculty','admin') NOT NULL DEFAULT 'student',
        `department`     VARCHAR(120) DEFAULT NULL,
        `contact_number` VARCHAR(30)  DEFAULT NULL,
        `status`         ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
        `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_users_email`     (`email`),
        INDEX `idx_users_role_status`   (`role`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── 2. FACILITIES ─────────────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS `facilities` (
        `id`               INT       NOT NULL AUTO_INCREMENT,
        `name`             VARCHAR(120) NOT NULL,
        `type`             VARCHAR(60)  DEFAULT NULL,
        `location`         VARCHAR(120) DEFAULT NULL,
        `capacity`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        `open_time`        TIME      NOT NULL DEFAULT '08:00:00',
        `close_time`       TIME      NOT NULL DEFAULT '18:00:00',
        `description`      TEXT      DEFAULT NULL,
        `equipment`        TEXT      DEFAULT NULL,
        `status`           ENUM('active','inactive') NOT NULL DEFAULT 'active',
        `instant_booking`  TINYINT(1) NOT NULL DEFAULT 0,
        `requires_letter`  TINYINT(1) NOT NULL DEFAULT 0,
        `max_bookings_day` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        `allowed_slots`    TEXT      DEFAULT NULL,
        `purpose_options`  TEXT      DEFAULT NULL,
        `facility_group`   VARCHAR(50) DEFAULT NULL,
        `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_facilities_status` (`status`),
        INDEX `idx_facilities_group`  (`facility_group`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── 3. BOOKINGS ───────────────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS `bookings` (
        `id`              INT       NOT NULL AUTO_INCREMENT,
        `user_id`         INT       NOT NULL,
        `facility_id`     INT       NOT NULL,
        `booking_date`    DATE      NOT NULL,
        `start_time`      TIME      NOT NULL,
        `end_time`        TIME      NOT NULL,
        `attendees_count` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        `program`         VARCHAR(20) DEFAULT NULL,
        `purpose`         TEXT      DEFAULT NULL,
        `status`          ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
        `letter_path`     VARCHAR(255) DEFAULT NULL,
        `admin_remarks`   TEXT      DEFAULT NULL,
        `reviewed_at`     DATETIME  DEFAULT NULL,
        `approved_by`     INT       DEFAULT NULL,
        `esignature`      TEXT      DEFAULT NULL,
        `reminder_sent`   TINYINT(1) NOT NULL DEFAULT 0,
        `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        CONSTRAINT `fk_bookings_user`
            FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)      ON DELETE CASCADE  ON UPDATE CASCADE,
        CONSTRAINT `fk_bookings_facility`
            FOREIGN KEY (`facility_id`) REFERENCES `facilities`(`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
        CONSTRAINT `fk_bookings_approver`
            FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)      ON DELETE SET NULL ON UPDATE CASCADE,
        INDEX `idx_bookings_user`     (`user_id`),
        INDEX `idx_bookings_facility` (`facility_id`),
        INDEX `idx_bookings_date`     (`booking_date`),
        INDEX `idx_bookings_status`   (`status`),
        INDEX `idx_bookings_date_fac` (`booking_date`, `facility_id`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── 4. REQUESTS ───────────────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS `requests` (
        `id`             INT       NOT NULL AUTO_INCREMENT,
        `booking_id`     INT       DEFAULT NULL,
        `user_id`        INT       NOT NULL,
        `facility_id`    INT       NOT NULL,
        `requested_date` DATE      NOT NULL,
        `start_time`     TIME      NOT NULL,
        `end_time`       TIME      NOT NULL,
        `attendees`      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        `purpose`        TEXT      DEFAULT NULL,
        `letter_path`    VARCHAR(255) DEFAULT NULL,
        `status`         ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
        `admin_remarks`  TEXT      DEFAULT NULL,
        `reviewed_by`    INT       DEFAULT NULL,
        `reviewed_at`    DATETIME  DEFAULT NULL,
        `esignature`     TEXT      DEFAULT NULL,
        `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        CONSTRAINT `fk_requests_user`
            FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)      ON DELETE CASCADE  ON UPDATE CASCADE,
        CONSTRAINT `fk_requests_facility`
            FOREIGN KEY (`facility_id`) REFERENCES `facilities`(`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
        CONSTRAINT `fk_requests_booking`
            FOREIGN KEY (`booking_id`)  REFERENCES `bookings`(`id`)   ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT `fk_requests_reviewer`
            FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`)      ON DELETE SET NULL ON UPDATE CASCADE,
        INDEX `idx_requests_user`     (`user_id`),
        INDEX `idx_requests_facility` (`facility_id`),
        INDEX `idx_requests_status`   (`status`),
        INDEX `idx_requests_date`     (`requested_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── 5. NOTIFICATIONS ──────────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
        `id`         INT          NOT NULL AUTO_INCREMENT,
        `user_id`    INT          NOT NULL,
        `message`    TEXT         NOT NULL,
        `type`       VARCHAR(20)  NOT NULL DEFAULT 'info',
        `link`       VARCHAR(255) DEFAULT NULL,
        `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        CONSTRAINT `fk_notifications_user`
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        INDEX `idx_notif_user_read` (`user_id`, `is_read`),
        INDEX `idx_notif_created`   (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── 6. AUDIT LOGS ─────────────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_logs` (
        `id`         INT          NOT NULL AUTO_INCREMENT,
        `user_id`    INT          DEFAULT NULL,
        `action`     VARCHAR(100) NOT NULL,
        `details`    TEXT         DEFAULT NULL,
        `ip_address` VARCHAR(45)  DEFAULT NULL,
        `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        CONSTRAINT `fk_audit_user`
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
        INDEX `idx_audit_user`    (`user_id`),
        INDEX `idx_audit_action`  (`action`),
        INDEX `idx_audit_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── LOGIN ATTEMPTS (DB-based rate limiting) ───────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
        `ip_address`   VARCHAR(45)      NOT NULL,
        `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `locked_until` DATETIME         DEFAULT NULL,
        `updated_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`ip_address`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── PASSWORD RESETS ───────────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
        `id`         INT          NOT NULL AUTO_INCREMENT,
        `email`      VARCHAR(180) NOT NULL,
        `token`      VARCHAR(64)  NOT NULL,
        `expires_at` DATETIME     NOT NULL,
        `used`       TINYINT(1)   NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_pr_token` (`token`),
        INDEX `idx_pr_email`   (`email`),
        INDEX `idx_pr_expires` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── COLUMN MIGRATIONS (silent — safe on re-run) ───────────────────────────
    foreach ([
        "ALTER TABLE users ADD COLUMN id_number      VARCHAR(40)  DEFAULT NULL AFTER email",
        "ALTER TABLE users ADD COLUMN department     VARCHAR(120) DEFAULT NULL AFTER role",
        "ALTER TABLE users ADD COLUMN contact_number VARCHAR(30)  DEFAULT NULL AFTER department",
        "ALTER TABLE users ADD COLUMN updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        "ALTER TABLE users MODIFY COLUMN status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active'",
        "ALTER TABLE facilities ADD COLUMN description TEXT DEFAULT NULL AFTER close_time",
        "ALTER TABLE facilities ADD COLUMN equipment   TEXT DEFAULT NULL AFTER description",
        "ALTER TABLE facilities ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        "ALTER TABLE bookings ADD COLUMN program       VARCHAR(20)  DEFAULT NULL AFTER attendees_count",
        "ALTER TABLE bookings ADD COLUMN reviewed_at   DATETIME     DEFAULT NULL AFTER admin_remarks",
        "ALTER TABLE bookings ADD COLUMN approved_by   INT          DEFAULT NULL AFTER reviewed_at",
        "ALTER TABLE bookings ADD COLUMN esignature    TEXT         DEFAULT NULL AFTER approved_by",
        "ALTER TABLE bookings ADD COLUMN reminder_sent TINYINT(1)   NOT NULL DEFAULT 0 AFTER esignature",
        "ALTER TABLE bookings ADD COLUMN updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        "ALTER TABLE notifications ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT 'info' AFTER message",
        "ALTER TABLE notifications ADD COLUMN link VARCHAR(255) DEFAULT NULL AFTER type",

        // Facilities feature columns
        "ALTER TABLE facilities ADD COLUMN instant_booking  TINYINT(1)        NOT NULL DEFAULT 0",
        "ALTER TABLE facilities ADD COLUMN requires_letter  TINYINT(1)        NOT NULL DEFAULT 0",
        "ALTER TABLE facilities ADD COLUMN max_bookings_day SMALLINT UNSIGNED NOT NULL DEFAULT 0",
        "ALTER TABLE facilities ADD COLUMN allowed_slots    TEXT             DEFAULT NULL",
        "ALTER TABLE facilities ADD COLUMN purpose_options  TEXT             DEFAULT NULL",
        "ALTER TABLE facilities ADD COLUMN facility_group   VARCHAR(50)      DEFAULT NULL",
    ] as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $ignored) {}
    }

    // ── SEED: default admin ───────────────────────────────────────────────────
    $adminExists = $pdo->query('SELECT id FROM users WHERE role="admin" LIMIT 1')->fetch();
    if (!$adminExists) {
        $pdo->prepare('INSERT INTO users (name,email,password,role,status) VALUES (?,?,?,?,?)')
            ->execute(['Administrator', 'admin@library.com',
                       password_hash('Admin@123', PASSWORD_DEFAULT), 'admin', 'active']);
    }

    // ── SEED: facilities (by name — safe on re-run) ───────────────────────────
    $facilitySeeds = [
        // name, type, location, capacity, open_time, close_time, instant, requires_letter, max_day, allowed_slots, purpose_options, group
        ['CL Room 1',    'collaboration_room',   'CL Building',      7,  '08:00:00', '18:00:00', 1, 0, 0, null, null, 'cl'],
        ['CL Room 2',    'collaboration_room',   'CL Building',      8,  '08:00:00', '18:00:00', 1, 0, 0, null, null, 'cl'],
        ['CL Room 3',    'collaboration_room',   'CL Building',      2,  '08:00:00', '18:00:00', 1, 0, 0, null, null, 'cl'],
        ['Museum',       'artifacts_room',       'Main Library',     50, '08:00:00', '17:00:00', 0, 1, 0, null, null, 'library'],
        ['EIRC',         'electronic_resources', 'Main Library',     30, '08:00:00', '17:00:00', 0, 1, 0, null, null, 'library'],
        [
            'Reading Area', 'reading_hall', 'Morelos Building', 200,
            '07:00:00', '17:00:00',
            0, 0, 4,
            json_encode([
                ['label'=>'7:00 AM – 10:00 AM','start'=>'07:00','end'=>'10:00'],
                ['label'=>'1:00 PM – 5:00 PM', 'start'=>'13:00','end'=>'17:00'],
            ]),
            json_encode(['Class Activity','Research Work','Group Study','Seminar','Others']),
            'morelos'
        ],
        [
            'Faculty Area', 'meeting_room', 'Morelos Building', 30,
            '07:00:00', '17:00:00',
            0, 0, 0,
            json_encode([
                ['label'=>'7:00 AM – 12:00 PM','start'=>'07:00','end'=>'12:00'],
                ['label'=>'1:00 PM – 5:00 PM', 'start'=>'13:00','end'=>'17:00'],
            ]),
            json_encode(['Faculty Meeting','Lecture','Consultation','Department Activity','Others']),
            'morelos'
        ],
    ];

    $facChk = $pdo->prepare('SELECT id, facility_group, type FROM facilities WHERE name=? LIMIT 1');
    $facIns = $pdo->prepare(
        'INSERT INTO facilities
         (name,type,location,capacity,open_time,close_time,status,
          instant_booking,requires_letter,max_bookings_day,allowed_slots,purpose_options,facility_group)
         VALUES (?,?,?,?,?,?,"active",?,?,?,?,?,?)'
    );
    $facUpd = $pdo->prepare('UPDATE facilities SET facility_group=?, type=? WHERE name=?');

    foreach ($facilitySeeds as $f) {
        $facChk->execute([$f[0]]);
        $existing = $facChk->fetch();
        if (!$existing) {
            $facIns->execute($f);
            continue;
        }
        $needUpdate = empty($existing['facility_group']) || empty($existing['type']);
        if ($needUpdate) {
            $facUpd->execute([$f[11], $f[1], $f[0]]);
        }
    }

} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:24px;color:#c00">'
      . '<strong>Database error:</strong> ' . htmlspecialchars($e->getMessage())
      . '<br><small>Make sure XAMPP MySQL is running, then visit <a href="/Library-Facilities-Booking-System/setup.php">setup.php</a>.</small>'
      . '</div>');
}