<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('admin');

// Verify CSRF token passed in URL from reports page
verifyCsrfGet();

$dateFrom = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo   = $_GET['to']   ?? date('Y-m-d');

$type = $_GET['type'] ?? 'bookings';  // bookings | facilities | users | audit

switch ($type) {

    case 'facilities':
        $stmt = $pdo->prepare('
            SELECT f.name AS "Facility", f.type AS "Type", f.location AS "Location",
                   f.capacity AS "Capacity", f.status AS "Status",
                   COUNT(b.id) AS "Total Bookings",
                   SUM(b.status="approved") AS "Approved",
                   SUM(b.status="pending")  AS "Pending",
                   SUM(b.status="rejected") AS "Rejected"
            FROM facilities f
            LEFT JOIN bookings b ON b.facility_id = f.id
              AND b.booking_date BETWEEN ? AND ?
            GROUP BY f.id
            ORDER BY f.name
        ');
        $stmt->execute([$dateFrom, $dateTo]);
        $filename = "facilities_{$dateFrom}_{$dateTo}.csv";
        break;

    case 'users':
        $stmt = $pdo->prepare('
            SELECT u.name AS "Name", u.email AS "Email", u.id_number AS "ID Number",
                   u.role AS "Role", u.department AS "Department",
                   u.status AS "Status", u.created_at AS "Registered",
                   COUNT(b.id) AS "Total Bookings"
            FROM users u
            LEFT JOIN bookings b ON b.user_id = u.id
              AND b.booking_date BETWEEN ? AND ?
            WHERE u.role != "admin"
            GROUP BY u.id
            ORDER BY u.name
        ');
        $stmt->execute([$dateFrom, $dateTo]);
        $filename = "users_{$dateFrom}_{$dateTo}.csv";
        break;

    case 'audit':
        $stmt = $pdo->prepare('
            SELECT al.id AS "Log ID", u.name AS "User", u.role AS "Role",
                   al.action AS "Action", al.details AS "Details",
                   al.ip_address AS "IP Address", al.created_at AS "Timestamp"
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE DATE(al.created_at) BETWEEN ? AND ?
            ORDER BY al.created_at DESC
        ');
        $stmt->execute([$dateFrom, $dateTo]);
        $filename = "audit_logs_{$dateFrom}_{$dateTo}.csv";
        break;

    default: // bookings
        $stmt = $pdo->prepare('
            SELECT
                b.id AS "Booking ID",
                u.name AS "User", u.email AS "Email", u.id_number AS "ID Number",
                u.role AS "Role", u.department AS "Department",
                f.name AS "Facility", f.type AS "Type", f.location AS "Location",
                b.booking_date AS "Date",
                b.start_time AS "Start Time", b.end_time AS "End Time",
                b.attendees_count AS "Attendees",
                b.program AS "Program",
                b.level AS "Level",
                b.purpose AS "Purpose",
                b.status AS "Status",
                b.admin_remarks AS "Admin Remarks",
                b.esignature AS "E-Signature",
                b.created_at AS "Submitted At",
                b.reviewed_at AS "Reviewed At"
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN facilities f ON b.facility_id = f.id
            WHERE b.booking_date BETWEEN ? AND ?
            ORDER BY b.booking_date DESC, b.start_time
        ');
        $stmt->execute([$dateFrom, $dateTo]);
        $filename = "bookings_{$dateFrom}_{$dateTo}.csv";
        break;
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stream CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

if (!empty($rows)) {
    fputcsv($out, array_keys($rows[0])); // header row
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
} else {
    fputcsv($out, ['No data found for the selected date range.']);
}

fclose($out);
exit;
?>