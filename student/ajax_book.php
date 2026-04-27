<?php
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

// Must be logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$uid = $_SESSION['user_id'];

// ── Cancel action ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $bid = (int)($_POST['booking_id'] ?? 0);
    $stmt = $pdo->prepare('UPDATE bookings SET status="cancelled" WHERE id=? AND user_id=? AND status="pending"');
    $stmt->execute([$bid, $uid]);
    if ($stmt->rowCount() > 0) {
        createNotification($pdo, $uid, "🚫 Your booking #" . str_pad($bid,4,'0',STR_PAD_LEFT) . " has been cancelled.", 'warning', BASE_URL . '/student/my_bookings.php');
        logAudit($pdo, 'booking_cancelled', "Booking #{$bid} cancelled by user {$uid}");
        echo json_encode(['success' => true, 'message' => 'Booking cancelled.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not cancel booking.']);
    }
    exit;
}

// ── Book action ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$fid       = (int)trim($_POST['facility_id']    ?? 0);
$date      = trim($_POST['booking_date']         ?? '');
$startTime = trim($_POST['start_time']           ?? '');
$endTime   = trim($_POST['end_time']             ?? '');
$purpose   = trim($_POST['purpose']              ?? '');
$attendees = (int)trim($_POST['attendees_count'] ?? 1);
$program   = trim($_POST['program']              ?? '');

$errors = [];

// ── Basic validation ──────────────────────────────────────────────────────────
if (!$fid)       $errors[] = 'No facility selected.';
if (!$date)      $errors[] = 'Date is required.';
if (!$startTime) $errors[] = 'Start time is required.';
if (!$endTime)   $errors[] = 'End time is required.';
if (!$purpose)   $errors[] = 'Purpose is required.';
if ($attendees < 1) $errors[] = 'Attendees must be at least 1.';
if (!$program)   $errors[] = 'Program is required.';
if ($program && !in_array($program, programOptions(), true)) $errors[] = 'Invalid program selected.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ── Date/time rules ───────────────────────────────────────────────────────────
if (strtotime($date) < strtotime('today')) {
    echo json_encode(['success' => false, 'message' => 'Booking date cannot be in the past.']);
    exit;
}

// Student rule: Thursday and Friday not open for booking (4-day program)
$dow = (int)date('N', strtotime($date));
if ($dow === 4 || $dow === 5) {
    echo json_encode(['success' => false, 'message' => 'Thursday and Friday are not open for student bookings. Please choose another date.']);
    exit;
}

// Enforce 8:00 AM – 6:00 PM
$openLimit  = '08:00:00';
$closeLimit = '18:00:00';
if ($startTime < $openLimit || $endTime > $closeLimit) {
    echo json_encode(['success' => false, 'message' => 'Booking must be between 8:00 AM and 6:00 PM.']);
    exit;
}
if ($startTime >= $endTime) {
    echo json_encode(['success' => false, 'message' => 'End time must be after start time.']);
    exit;
}

// Minimum 30-minute booking
$diff = (strtotime($endTime) - strtotime($startTime)) / 60;
if ($diff < 30) {
    echo json_encode(['success' => false, 'message' => 'Booking must be at least 30 minutes.']);
    exit;
}

// ── Facility check ────────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT * FROM facilities WHERE id=? AND status="active"');
$stmt->execute([$fid]);
$facility = $stmt->fetch();

if (!$facility) {
    echo json_encode(['success' => false, 'message' => 'Facility not found or inactive.']);
    exit;
}

if ($attendees > (int)$facility['capacity']) {
    echo json_encode(['success' => false,
        'message' => 'Attendees (' . $attendees . ') exceed the facility capacity (' . $facility['capacity'] . ').']);
    exit;
}

// ── Double booking prevention (row-level lock with transaction) ───────────────
try {
    $pdo->beginTransaction();

    // Lock the relevant rows to prevent concurrent duplicates
    $conflict = $pdo->prepare(
        'SELECT id FROM bookings
         WHERE facility_id = ? AND booking_date = ?
           AND status IN ("pending","approved")
           AND NOT (end_time <= ? OR start_time >= ?)
         FOR UPDATE'
    );
    $conflict->execute([$fid, $date, $startTime, $endTime]);

    if ($conflict->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false,
            'message' => 'That time slot is already booked. Please choose a different time.']);
        exit;
    }

    // ── Handle file upload for request-based facilities ───────────────────────
    $letterFile = null;
    $requiresLetter = (int)($facility['requires_letter'] ?? 0);

    if ($requiresLetter) {
        if (empty($_FILES['request_letter']['name'])) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'A request letter is required for this facility.']);
            exit;
        }
        $letterFile = uploadLetter($_FILES['request_letter']);
        if (!$letterFile) {
            $pdo->rollBack();
            echo json_encode(['success' => false,
                'message' => 'Invalid file. Allowed: PDF, JPG, PNG — max 5 MB.']);
            exit;
        }
    } elseif (!empty($_FILES['request_letter']['name'])) {
        // Optional upload for non-required letter
        $letterFile = uploadLetter($_FILES['request_letter']);
    }

    // ── Determine status ──────────────────────────────────────────────────────
    $instantBooking = (int)($facility['instant_booking'] ?? 0);
    $status = $instantBooking ? 'approved' : 'pending';

    // ── Insert booking ────────────────────────────────────────────────────────
    $pdo->prepare(
        'INSERT INTO bookings
         (user_id, facility_id, booking_date, start_time, end_time, purpose, attendees_count, program, letter_path, status)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    )->execute([$uid, $fid, $date, $startTime, $endTime, $purpose, $attendees, $program, $letterFile, $status]);

    $bookingId = $pdo->lastInsertId();
    $pdo->commit();

    // ── Notifications ─────────────────────────────────────────────────────────
    $facName = $facility['name'];
    $dtFmt   = date('M j, Y', strtotime($date)) . ' ' . date('g:i A', strtotime($startTime));
    $link    = BASE_URL . '/student/my_bookings.php';

    if ($instantBooking) {
        $notifMsg = "✅ Booking confirmed for \"{$facName}\" on {$dtFmt}.";
        createNotification($pdo, $uid, $notifMsg, 'success', $link);
        sendEmailSim($uid, $pdo, "Booking Confirmed — {$facName}", $notifMsg);
    } else {
        $notifMsg = "📋 Booking request submitted for \"{$facName}\" on {$dtFmt}. Awaiting admin approval.";
        createNotification($pdo, $uid, $notifMsg, 'info', $link);
        sendEmailSim($uid, $pdo, "Booking Request Submitted — {$facName}", $notifMsg);
    }
    logAudit($pdo, 'booking_created', "Booking #{$bookingId} for {$facName} on {$date} | Status: {$status}");

    $msg = $instantBooking
        ? '✅ Booking confirmed! Your reservation is approved.'
        : '📋 Request submitted! Awaiting admin approval.';

    echo json_encode([
        'success'    => true,
        'message'    => $msg,
        'booking_id' => $bookingId,
        'status'     => $status,
        'instant'    => (bool)$instantBooking,
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
