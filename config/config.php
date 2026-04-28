<?php
define('APP_NAME',  'Library Facilities Booking System');
define('APP_SHORT', 'LFBS');
define('BASE_URL',  'http://localhost/Library-Facilities-Booking-System');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// ── Database connection constants ─────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'library_booking');
define('DB_USER', 'root');
define('DB_PASS', '');

// ── Security constants ────────────────────────────────────────────────────────
define('SESSION_TIMEOUT',    1800);  // 30 minutes idle
define('LOGIN_MAX_ATTEMPTS',    5);  // lock after 5 failures
define('LOGIN_LOCKOUT_SEC',   900);  // 15-minute lockout

if (session_status() === PHP_SESSION_NONE) {
    // Automatically mark cookies secure when served over HTTPS.
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => 0, // expires on browser close
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true, // block JS access to cookie
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Security headers ──────────────────────────────────────────────────────────
if (!headers_sent() && php_sapi_name() !== 'cli') {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

// Global PDO error logger (writes to logs/php_errors.log)
$_logsDir = __DIR__ . '/../logs/';
if (!is_dir($_logsDir)) {
    @mkdir($_logsDir, 0750, true);
    @file_put_contents($_logsDir . '.htaccess', "Require all denied\n");
}
unset($_logsDir);

set_exception_handler(function (Throwable $e) {
    $logDir = __DIR__ . '/../logs/';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

    $logFile = $logDir . 'php_errors.log';
    $maxBytes = 1024 * 1024; // 1 MB
    if (is_file($logFile)) {
        $size = @filesize($logFile);
        if ($size !== false && $size > $maxBytes) {
            $archived = $logDir . 'php_errors_' . date('Ymd_His') . '.log';
            @rename($logFile, $archived);
        }
    }

    $msg = date('[Y-m-d H:i:s]') . ' ' . get_class($e) . ': ' . $e->getMessage()
         . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n";
    @file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);

    http_response_code(500);
    // Show friendly error in production; for dev you can swap this for a full dump
    die('<p style="font-family:sans-serif;padding:20px;color:#c00">An unexpected error occurred. Please try again later.</p>');
});