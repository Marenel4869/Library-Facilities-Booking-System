<?php
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized.']);
    exit;
}

$uid = $_SESSION['user_id'];

// ── Cancel ────────────────────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $bid = (int)($_POST['booking_id'] ?? 0);
    $s = $pdo->prepare('UPDATE bookings SET status="cancelled" WHERE id=? AND user_id=? AND status="pending"');
    $s->execute([$bid, $uid]);
    if ($s->rowCount() > 0) {
        createNotification($pdo, $uid, "🚫 Your booking #" . str_pad($bid,4,'0',STR_PAD_LEFT) . " has been cancelled.", 'warning', BASE_URL . '/faculty/my_bookings.php');
        logAudit($pdo, 'booking_cancelled', "Booking #{$bid} cancelled by faculty {$uid}");
        echo json_encode(['success'=>true, 'message'=>'Booking cancelled.']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Could not cancel.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Invalid request.']);
    exit;
}

// ── Inputs ────────────────────────────────────────────────────────────────────
$fid       = (int)trim($_POST['facility_id']    ?? 0);
$date      = trim($_POST['booking_date']         ?? '');
$startTime = trim($_POST['start_time']           ?? '');
$endTime   = trim($_POST['end_time']             ?? '');
$purpose   = trim($_POST['purpose']              ?? '');
$attendees = (int)trim($_POST['attendees_count'] ?? 1);
$program   = trim($_POST['program']              ?? '');

// Basic required checks
if (!$fid || !$date || !$startTime || !$endTime || !$purpose || $attendees < 1 || !$program) {
    echo json_encode(['success'=>false,'message'=>'All fields are required.']);
    exit;
}

if (!in_array($program, programOptions(), true)) {
    echo json_encode(['success'=>false,'message'=>'Invalid program selected.']);
    exit;
}

// Past date check
if (strtotime($date) < strtotime('today')) {
    echo json_encode(['success'=>false,'message'=>'Booking date cannot be in the past.']);
    exit;
}

// Booking rule: Thursday and Friday not open for booking (4-day program)
$dow = (int)date('N', strtotime($date));
if ($dow === 4 || $dow === 5) {
    echo json_encode(['success'=>false,'message'=>'Thursday and Friday are not open for bookings. Please choose another date.']);
    exit;
}

// Start must be before end
if ($startTime >= $endTime) {
    echo json_encode(['success'=>false,'message'=>'End time must be after start time.']);
    exit;
}

// ── Load facility ─────────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT * FROM facilities WHERE id=? AND status="active"');
$stmt->execute([$fid]);
$facility = $stmt->fetch();

if (!$facility) {
    echo json_encode(['success'=>false,'message'=>'Facility not found or inactive.']);
    exit;
}

$instantBooking = (int)($facility['instant_booking'] ?? 0);
$maxDay         = (int)($facility['max_bookings_day'] ?? 0);
$allowedSlots   = $facility['allowed_slots'] ? json_decode($facility['allowed_slots'], true) : null;

// ── Capacity check ────────────────────────────────────────────────────────────
if ($attendees > (int)$facility['capacity']) {
    echo json_encode(['success'=>false,
        'message'=>'Attendees ('.$attendees.') exceed capacity ('.$facility['capacity'].').']);
    exit;
}

// ── Time slot validation ──────────────────────────────────────────────────────
if ($allowedSlots) {
    // Must match one of the defined slots exactly
    $validSlot = false;
    foreach ($allowedSlots as $sl) {
        $slStart = $sl['start'] . ':00';
        $slEnd   = $sl['end']   . ':00';
        // normalize to HH:MM:SS
        if (strlen($startTime) === 5) $startTime .= ':00';
        if (strlen($endTime)   === 5) $endTime   .= ':00';
        if ($startTime === $slStart && $endTime === $slEnd) {
            $validSlot = true;
            break;
        }
    }
    if (!$validSlot) {
        $slotLabels = array_map(fn($sl) => $sl['label'], $allowedSlots);
        echo json_encode(['success'=>false,
            'message'=>'Invalid time slot. Allowed: ' . implode(' | ', $slotLabels)]);
        exit;
    }
} else {
    // CL: 08:00–18:00 range
    if (strlen($startTime) === 5) $startTime .= ':00';
    if (strlen($endTime)   === 5) $endTime   .= ':00';
    if ($startTime < '08:00:00' || $endTime > '18:00:00') {
        echo json_encode(['success'=>false,'message'=>'CL rooms are available 8:00 AM – 6:00 PM only.']);
        exit;
    }
    $diffMin = (strtotime($endTime) - strtotime($startTime)) / 60;
    if ($diffMin < 30) {
        echo json_encode(['success'=>false,'message'=>'Minimum booking duration is 30 minutes.']);
        exit;
    }
}

// ── Daily limit check ─────────────────────────────────────────────────────────
if ($maxDay > 0) {
    $dayCount = $pdo->prepare(
        'SELECT COUNT(*) FROM bookings
         WHERE facility_id=? AND booking_date=? AND status IN ("pending","approved")'
    );
    $dayCount->execute([$fid, $date]);
    $existing = (int)$dayCount->fetchColumn();
    if ($existing >= $maxDay) {
        echo json_encode(['success'=>false,
            'message'=>'This facility has reached its maximum bookings for ' . date('M j, Y', strtotime($date)) . ' ('.$maxDay.' per day). Please choose another date.']);
        exit;
    }
}

// ── Double-booking prevention ─────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    $conflict = $pdo->prepare(
        'SELECT id FROM bookings
         WHERE facility_id=? AND booking_date=?
           AND status IN ("pending","approved")
           AND NOT (end_time <= ? OR start_time >= ?)
         FOR UPDATE'
    );
    $conflict->execute([$fid, $date, $startTime, $endTime]);

    if ($conflict->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,
            'message'=>'That time slot is already taken. Please choose a different time.']);
        exit;
    }

    // ── File upload (optional for Morelos) ───────────────────────────────────
    $letterFile = null;
    if (!empty($_FILES['request_letter']['name'])) {
        $letterFile = uploadLetter($_FILES['request_letter']);
        if (!$letterFile) {
            $pdo->rollBack();
            echo json_encode(['success'=>false,
                'message'=>'Invalid file. Allowed: PDF, JPG, PNG — max 5 MB.']);
            exit;
        }
    }

    $status = $instantBooking ? 'approved' : 'pending';

    $pdo->prepare(
        'INSERT INTO bookings
         (user_id,facility_id,booking_date,start_time,end_time,purpose,attendees_count,program,letter_path,status)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    )->execute([$uid, $fid, $date, $startTime, $endTime, $purpose, $attendees, $program, $letterFile, $status]);

    $bid = $pdo->lastInsertId();
    $pdo->commit();

    // ── Notifications ─────────────────────────────────────────────────────────
    $facName = $facility['name'];
    $dtFmt   = date('M j, Y', strtotime($date)) . ' ' . date('g:i A', strtotime($startTime));
    $link    = BASE_URL . '/faculty/my_bookings.php';
    if ($instantBooking) {
        $notifMsg = "✅ Booking confirmed for \"{$facName}\" on {$dtFmt}.";
        createNotification($pdo, $uid, $notifMsg, 'success', $link);
        sendEmailSim($uid, $pdo, "Booking Confirmed — {$facName}", $notifMsg);
    } else {
        $notifMsg = "📋 Booking request submitted for \"{$facName}\" on {$dtFmt}. Awaiting admin approval.";
        createNotification($pdo, $uid, $notifMsg, 'info', $link);
        sendEmailSim($uid, $pdo, "Booking Request Submitted — {$facName}", $notifMsg);
    }
    logAudit($pdo, 'booking_created', "Booking #{$bid} for {$facName} on {$date} | Status: {$status}");

    $msg = $instantBooking
        ? '✅ Booking confirmed! Your reservation is approved.'
        : '📋 Request submitted! Awaiting admin approval.';

    echo json_encode([
        'success'    => true,
        'message'    => $msg,
        'booking_id' => $bid,
        'status'     => $status,
        'instant'    => (bool)$instantBooking,
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success'=>false,'message'=>'Database error. Please try again.']);
}
