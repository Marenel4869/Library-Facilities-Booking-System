<?php
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

// Must be logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$uid = $_SESSION['user_id'];

// ── Availability (for UI disabling of already-booked times) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'availability') {
    $fid  = (int)($_POST['facility_id'] ?? 0);
    $date = trim($_POST['booking_date'] ?? '');

    if (!$fid || !$date) {
        echo json_encode(['success' => false, 'message' => 'Missing facility/date.']);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT start_time, end_time
           FROM bookings
          WHERE facility_id = ?
            AND booking_date = ?
            AND status IN ("pending", "approved")
          ORDER BY start_time'
    );
    $stmt->execute([$fid, $date]);

    $booked = [];
    foreach ($stmt->fetchAll() as $row) {
        $booked[] = [
            'start' => substr($row['start_time'], 0, 5),
            'end'   => substr($row['end_time'],   0, 5),
        ];
    }

    echo json_encode(['success' => true, 'booked' => $booked]);
    exit;
}

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

// Normalize to HH:MM:SS (UI posts HH:MM)
if (strlen($startTime) === 5) $startTime .= ':00';
if (strlen($endTime)   === 5) $endTime   .= ':00';

if ($startTime >= $endTime) {
    echo json_encode(['success' => false, 'message' => 'End time must be after start time.']);
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

// Flexible facilities: Faculty / Reading Area (7:00–12:00, 1:00–5:00) and EIRC/Museum (8:00–18:00)
$name = strtolower((string)($facility['name'] ?? ''));
$isFlexible = (strpos($name, 'faculty') !== false) || (strpos($name, 'reading area') !== false) || (strpos($name, 'eirc') !== false) || (strpos($name, 'irc') !== false) || (strpos($name, 'museum') !== false);

if ($isFlexible) {
    $toMin = function ($t) {
        $p = explode(':', (string)$t);
        $h = (int)($p[0] ?? 0);
        $m = (int)($p[1] ?? 0);
        return $h * 60 + $m;
    };
    $sMin = $toMin($startTime);
    $eMin = $toMin($endTime);

    $isEircMuseum = (strpos($name, 'eirc') !== false) || (strpos($name, 'irc') !== false) || (strpos($name, 'museum') !== false);
    if ($isEircMuseum) {
        // EIRC/Museum: allow any start/end between 08:00 and 18:00 (start < end)
        if (!($sMin >= 8*60 && $eMin <= 18*60 && $sMin < $eMin)) {
            echo json_encode(['success' => false, 'message' => 'Invalid time. Allowed: 8:00 AM–6:00 PM for this facility.']);
            exit;
        }
    } else {
        // Faculty/Reading Area: must be within same window (7:00-12:00 OR 13:00-17:00)
        $morningOk = ($sMin >= 7*60)  && ($eMin <= 12*60);
        $aftOk     = ($sMin >= 13*60) && ($eMin <= 17*60);

        if (!(($morningOk || $aftOk) && ($sMin < $eMin))) {
            echo json_encode(['success' => false, 'message' => 'Invalid time. Allowed: 7:00 AM–12:00 PM or 1:00 PM–5:00 PM (must be within the same time zone).']);
            exit;
        }
    }
} else {
    // Fixed slots for all other facilities (7:30 AM – 6:00 PM)
    $allowedSlots = [
        ['start' => '07:30:00', 'end' => '09:00:00'],
        ['start' => '09:00:00', 'end' => '10:30:00'],
        ['start' => '10:30:00', 'end' => '12:00:00'],
        ['start' => '12:00:00', 'end' => '13:30:00'],
        ['start' => '13:30:00', 'end' => '15:00:00'],
        ['start' => '15:00:00', 'end' => '16:30:00'],
        ['start' => '16:30:00', 'end' => '18:00:00'],
    ];

    $validSlot = false;
    foreach ($allowedSlots as $sl) {
        if ($startTime === $sl['start'] && $endTime === $sl['end']) { $validSlot = true; break; }
    }

    if (!$validSlot) {
        echo json_encode(['success' => false, 'message' => 'Invalid time slot. Please select one of the available slots (7:30 AM – 6:00 PM).']);
        exit;
    }
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
