<?php
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized.']);
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
$level     = trim($_POST['level']                ?? '');

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

// ── Time validation ───────────────────────────────────────────────────────────
// Normalize to HH:MM:SS
if (strlen($startTime) === 5) $startTime .= ':00';
if (strlen($endTime)   === 5) $endTime   .= ':00';

$name = strtolower((string)($facility['name'] ?? ''));

// Require Level for Faculty Area / Reading Area
$needsLevel = (strpos($name, 'faculty area') !== false) || (strpos($name, 'reading area') !== false);
if ($needsLevel) {
    $allowedLevels = ['GS', 'JHS', 'SHS'];
    if (!$level) {
        echo json_encode(['success'=>false,'message'=>'Level is required for this facility.']);
        exit;
    }
    if (!in_array($level, $allowedLevels, true)) {
        echo json_encode(['success'=>false,'message'=>'Invalid level selected.']);
        exit;
    }
} else {
    $level = null;
}

// Flexible for faculty/reading-area and EIRC/Museum
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
        // EIRC/Museum: allow 08:00 - 18:00
        if (!($sMin >= 8*60 && $eMin <= 18*60 && $sMin < $eMin)) {
            echo json_encode(['success'=>false,'message'=>'Invalid time. Allowed: 8:00 AM–6:00 PM for this facility.']);
            exit;
        }
    } else {
        $morningOk = ($sMin >= 7*60)  && ($eMin <= 12*60);
        $aftOk     = ($sMin >= 13*60) && ($eMin <= 17*60);

        if (!(($morningOk || $aftOk) && ($sMin < $eMin))) {
            echo json_encode(['success'=>false,'message'=>'Invalid time. Allowed: 7:00 AM–12:00 PM or 1:00 PM–5:00 PM (must be within the same time zone).']);
            exit;
        }
    }
} else {
    // Fixed slots for all other facilities (7:30 AM – 6:00 PM)
    $allowedSlots = [
        ['start' => '07:30:00', 'end' => '09:00:00',  'label' => '7:30am-9:00am'],
        ['start' => '09:00:00', 'end' => '10:30:00', 'label' => '9:00am-10:30am'],
        ['start' => '10:30:00', 'end' => '12:00:00', 'label' => '10:30am-12:00pm'],
        ['start' => '12:00:00', 'end' => '13:30:00', 'label' => '12:00pm-1:30pm'],
        ['start' => '13:30:00', 'end' => '15:00:00', 'label' => '1:30pm-3:00pm'],
        ['start' => '15:00:00', 'end' => '16:30:00', 'label' => '3:00pm-4:30pm'],
        ['start' => '16:30:00', 'end' => '18:00:00', 'label' => '4:30pm-6:00pm'],
    ];

    $validSlot = false;
    foreach ($allowedSlots as $sl) {
        if ($startTime === $sl['start'] && $endTime === $sl['end']) { $validSlot = true; break; }
    }

    if (!$validSlot) {
        $slotLabels = array_map(fn($sl) => $sl['label'], $allowedSlots);
        echo json_encode(['success'=>false, 'message'=>'Invalid time slot. Allowed: ' . implode(' | ', $slotLabels)]);
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
         (user_id,facility_id,booking_date,start_time,end_time,purpose,attendees_count,program,level,letter_path,status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([$uid, $fid, $date, $startTime, $endTime, $purpose, $attendees, $program, $level, $letterFile, $status]);

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
