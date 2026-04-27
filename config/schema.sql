-- ============================================================
--  Library Facilities Booking System — Database Schema
--  Engine : InnoDB (FK support + transactions)
--  Charset: utf8mb4 / utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS `library_booking`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `library_booking`;

-- ─────────────────────────────────────────────────────────────
--  1. USERS
--     Stores all system accounts (student / faculty / admin).
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`             INT            NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(120)   NOT NULL,
    `email`          VARCHAR(180)   NOT NULL,
    `id_number`      VARCHAR(40)    DEFAULT NULL   COMMENT 'Student or faculty ID number',
    `password`       VARCHAR(255)   NOT NULL,
    `role`           ENUM('student','faculty','admin') NOT NULL DEFAULT 'student',
    `department`     VARCHAR(120)   DEFAULT NULL,
    `contact_number` VARCHAR(30)    DEFAULT NULL,
    `status`         ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE  KEY `uq_users_email`     (`email`),
    UNIQUE  KEY `uq_users_id_number` (`id_number`),
    INDEX         `idx_users_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
--  2. FACILITIES
--     Bookable spaces / resources managed by the admin.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `facilities` (
    `id`              INT            NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(120)   NOT NULL,
    `type`            VARCHAR(60)    DEFAULT NULL  COMMENT 'study_room, museum, reading_hall, etc.',
    `location`        VARCHAR(120)   DEFAULT NULL,
    `capacity`        SMALLINT       UNSIGNED NOT NULL DEFAULT 0,
    `open_time`       TIME           NOT NULL DEFAULT '08:00:00',
    `close_time`      TIME           NOT NULL DEFAULT '18:00:00',
    `status`          ENUM('active','inactive') NOT NULL DEFAULT 'active',

    -- Booking behaviour flags
    `instant_booking` TINYINT(1)     NOT NULL DEFAULT 0  COMMENT '1 = confirmed immediately without admin approval',
    `requires_letter` TINYINT(1)     NOT NULL DEFAULT 0  COMMENT '1 = letter upload mandatory',
    `max_bookings_day` SMALLINT      UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = no daily cap',

    -- JSON configuration columns (stored as TEXT for MySQL 5.x compatibility)
    `allowed_slots`   TEXT           DEFAULT NULL COMMENT 'JSON: [{label,start,end}]',
    `purpose_options` TEXT           DEFAULT NULL COMMENT 'JSON: ["Lecture","Meeting",...]',

    `facility_group`  VARCHAR(50)    DEFAULT NULL COMMENT 'cl | morelos | library',
    `created_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_facilities_status`  (`status`),
    INDEX `idx_facilities_group`   (`facility_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
--  3. BOOKINGS
--     Confirmed / instant reservations for a facility.
--     Request-based facilities start as 'pending' here too;
--     the companion `requests` table tracks the approval doc.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `bookings` (
    `id`              INT            NOT NULL AUTO_INCREMENT,
    `user_id`         INT            NOT NULL,
    `facility_id`     INT            NOT NULL,
    `booking_date`    DATE           NOT NULL,
    `start_time`      TIME           NOT NULL,
    `end_time`        TIME           NOT NULL,
    `attendees_count` SMALLINT       UNSIGNED NOT NULL DEFAULT 1,
    `purpose`         TEXT           DEFAULT NULL,
    `status`          ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',

    -- Letter upload (for Museum / EIRC bookings)
    `letter_path`     VARCHAR(255)   DEFAULT NULL,

    -- Admin review fields
    `admin_remarks`   TEXT           DEFAULT NULL,
    `reviewed_at`     DATETIME       DEFAULT NULL,
    `approved_by`     INT            DEFAULT NULL COMMENT 'FK → users.id of the approving admin',
    `esignature`      TEXT           DEFAULT NULL COMMENT 'Admin e-signature text or base64 image',

    -- Reminder flag
    `reminder_sent`   TINYINT(1)     NOT NULL DEFAULT 0,

    `created_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_bookings_user`
        FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)      ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_bookings_facility`
        FOREIGN KEY (`facility_id`) REFERENCES `facilities`(`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_bookings_approver`
        FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)      ON DELETE SET NULL ON UPDATE CASCADE,

    -- Prevent completely duplicate rows
    UNIQUE KEY `uq_booking_slot` (`facility_id`, `booking_date`, `start_time`, `end_time`, `status`),

    INDEX `idx_bookings_user`      (`user_id`),
    INDEX `idx_bookings_facility`  (`facility_id`),
    INDEX `idx_bookings_date`      (`booking_date`),
    INDEX `idx_bookings_status`    (`status`),
    INDEX `idx_bookings_date_fac`  (`booking_date`, `facility_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
--  4. REQUESTS
--     Formal letter-based requests (Museum, EIRC, etc.).
--     Each approved request generates a booking row.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `requests` (
    `id`              INT            NOT NULL AUTO_INCREMENT,
    `booking_id`      INT            DEFAULT NULL  COMMENT 'Set once the request is approved → booking created',
    `user_id`         INT            NOT NULL,
    `facility_id`     INT            NOT NULL,
    `requested_date`  DATE           NOT NULL,
    `start_time`      TIME           NOT NULL,
    `end_time`        TIME           NOT NULL,
    `attendees`       SMALLINT       UNSIGNED NOT NULL DEFAULT 1,
    `purpose`         TEXT           DEFAULT NULL,
    `letter_path`     VARCHAR(255)   DEFAULT NULL COMMENT 'Uploaded PDF / image',
    `status`          ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    `admin_remarks`   TEXT           DEFAULT NULL,
    `reviewed_by`     INT            DEFAULT NULL,
    `reviewed_at`     DATETIME       DEFAULT NULL,
    `esignature`      TEXT           DEFAULT NULL,
    `created_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
--  5. NOTIFICATIONS
--     In-app notifications for every user action / event.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `user_id`    INT          NOT NULL,
    `message`    TEXT         NOT NULL,
    `type`       VARCHAR(20)  NOT NULL DEFAULT 'info'
                     COMMENT 'info | success | warning | danger',
    `link`       VARCHAR(255) DEFAULT NULL COMMENT 'Optional deep-link URL',
    `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_notifications_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,

    INDEX `idx_notif_user_read` (`user_id`, `is_read`),
    INDEX `idx_notif_created`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
--  6. AUDIT LOGS
--     Immutable trail of every significant action.
--     user_id is nullable so system-level events can be logged.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`         INT           NOT NULL AUTO_INCREMENT,
    `user_id`    INT           DEFAULT NULL COMMENT 'NULL for system-generated events',
    `action`     VARCHAR(100)  NOT NULL,
    `details`    TEXT          DEFAULT NULL,
    `ip_address` VARCHAR(45)   DEFAULT NULL COMMENT 'IPv4 or IPv6',
    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_audit_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,

    INDEX `idx_audit_user`    (`user_id`),
    INDEX `idx_audit_action`  (`action`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
--  PASSWORD RESETS  (supporting table, not in the main 6)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `email`      VARCHAR(180) NOT NULL,
    `token`      VARCHAR(64)  NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `used`       TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pr_token` (`token`),
    INDEX `idx_pr_email`     (`email`),
    INDEX `idx_pr_expires`   (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
--  SEED DATA — Default admin account & facilities
--  (safe to re-run; INSERT IGNORE skips duplicates)
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `users` (name, email, password, role, status) VALUES
    ('Administrator', 'admin@library.com',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- password: Admin@123
     'admin', 'active');

INSERT IGNORE INTO `facilities`
    (name, type, location, capacity, open_time, close_time, instant_booking, requires_letter, max_bookings_day, facility_group)
VALUES
    ('CL Room 1',     'computer_lab',  'CL Building',     7,   '08:00:00', '18:00:00', 1, 0, 0, 'cl'),
    ('CL Room 2',     'computer_lab',  'CL Building',     8,   '08:00:00', '18:00:00', 1, 0, 0, 'cl'),
    ('CL Room 3',     'computer_lab',  'CL Building',     2,   '08:00:00', '18:00:00', 1, 0, 0, 'cl'),
    ('Museum',        'museum',        'Main Library',    50,  '08:00:00', '17:00:00', 0, 1, 0, 'library'),
    ('EIRC',          'reference',     'Main Library',    30,  '08:00:00', '17:00:00', 0, 1, 0, 'library'),
    ('Reading Area',  'reading_hall',  'Morelos Building',200, '07:00:00', '17:00:00', 0, 0, 4, 'morelos'),
    ('Faculty Area',  'faculty_room',  'Morelos Building', 30, '07:00:00', '17:00:00', 0, 0, 0, 'morelos');
