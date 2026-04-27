<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('student');

$uid    = $_SESSION['user_id'];
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $fid       = (int)($_POST['facility_id']    ?? 0);
    $date      = trim($_POST['booking_date']     ?? '');
    $startTime = trim($_POST['start_time']       ?? '');
    $endTime   = trim($_POST['end_time']         ?? '');
    $purpose   = trim($_POST['purpose']          ?? '');
    $attendees = (int)($_POST['attendees_count'] ?? 1);
    $program   = trim($_POST['program']          ?? '');

    if (!$fid)       $errors[] = 'Select a facility.';
    if (!$date)      $errors[] = 'Date is required.';
    if (!$startTime) $errors[] = 'Start time is required.';
    if (!$endTime)   $errors[] = 'End time is required.';
    if ($startTime >= $endTime) $errors[] = 'End time must be after start time.';
    if (!$purpose)   $errors[] = 'Purpose is required.';
    if (!$program)   $errors[] = 'Program is required.';
    if ($program && !in_array($program, programOptions(), true)) $errors[] = 'Invalid program selected.';
    if (strtotime($date) < strtotime('today')) $errors[] = 'Date cannot be in the past.';
    if ($date) {
        $dow = (int)date('N', strtotime($date));
        if ($dow === 4 || $dow === 5) $errors[] = 'Thursday and Friday are not open for student bookings.';
    }

    $facility = null;
    if ($fid) {
        $stmt = $pdo->prepare('SELECT * FROM facilities WHERE id = ? AND status = "active"');
        $stmt->execute([$fid]);
        $facility = $stmt->fetch();
        if (!$facility) $errors[] = 'Facility not found.';
    }

    if ($facility && $attendees > $facility['capacity'])
        $errors[] = 'Attendees exceed facility capacity (' . $facility['capacity'] . ').';

    if (empty($errors) && !slotAvailable($pdo, $fid, $date, $startTime, $endTime))
        $errors[] = 'That time slot is already booked. Pick another time.';

    $letterFile = null;
    if (!empty($_FILES['request_letter']['name'])) {
        $letterFile = uploadLetter($_FILES['request_letter']);
        if (!$letterFile) $errors[] = 'Invalid file. Allowed: PDF, JPG, PNG — max 5 MB.';
    }

    if (empty($errors)) {
        $pdo->prepare('INSERT INTO bookings (user_id,facility_id,booking_date,start_time,end_time,purpose,attendees_count,program,letter_path,status) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$uid, $fid, $date, $startTime, $endTime, $purpose, $attendees, $program, $letterFile, 'pending']);
        $bid = $pdo->lastInsertId();
        flash('success', 'Booking submitted! Awaiting admin approval.');
        header('Location: ' . BASE_URL . '/student/view_booking.php?id=' . $bid);
        exit;
    }
}

// Get facilities
$facilities = $pdo->query('SELECT * FROM facilities WHERE status = "active" ORDER BY name')->fetchAll();

// Pre-select if ?id= given
$selected = null;
if (!empty($_GET['id'])) {
    $s = $pdo->prepare('SELECT * FROM facilities WHERE id = ? AND status = "active"');
    $s->execute([(int)$_GET['id']]);
    $selected = $s->fetch();
}

$pageTitle = 'Book a Facility';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container py-3">

<?= showFlash() ?>
<h4 class="mb-4">Book a Facility</h4>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
  <!-- Facility list -->
  <div class="col-md-5">
    <div class="card">
      <div class="card-header">Available Facilities</div>
      <div class="list-group list-group-flush">
        <?php foreach ($facilities as $f): ?>
        <a href="?id=<?= $f['id'] ?>" class="list-group-item list-group-item-action <?= ($selected && $selected['id']==$f['id']) ? 'active' : '' ?>">
          <div class="d-flex justify-content-between">
            <strong><?= e($f['name']) ?></strong>
            <small><?= facilityLabel($f['type']) ?></small>
          </div>
          <small class="<?= ($selected && $selected['id']==$f['id']) ? 'text-white-50' : 'text-muted' ?>">
            <i class="fas fa-users me-1"></i><?= $f['capacity'] ?> &nbsp;
            <i class="fas fa-map-marker-alt me-1"></i><?= e($f['location']) ?>
          </small>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Booking form -->
  <div class="col-md-7">
    <?php if ($selected): ?>
    <div class="card">
      <div class="card-header">Booking: <?= e($selected['name']) ?></div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <?= csrfField() ?>
          <input type="hidden" name="facility_id" value="<?= $selected['id'] ?>">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">Date *</label>
              <input type="date" name="booking_date" class="form-control"
                     value="<?= htmlspecialchars($_POST['booking_date'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Start Time *</label>
              <input type="time" name="start_time" class="form-control"
                     min="<?= substr($selected['open_time'],0,5) ?>"
                     max="<?= substr($selected['close_time'],0,5) ?>"
                     value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">End Time *</label>
              <input type="time" name="end_time" class="form-control"
                     max="<?= substr($selected['close_time'],0,5) ?>"
                     value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Attendees *</label>
              <input type="number" name="attendees_count" class="form-control"
                     min="1" max="<?= $selected['capacity'] ?>"
                     value="<?= htmlspecialchars($_POST['attendees_count'] ?? '1') ?>" required>
              <div class="form-text">Max: <?= $selected['capacity'] ?></div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Program *</label>
              <select name="program" class="form-select" required>
                <option value="">-- Select Program --</option>
                <?php foreach (programOptions() as $p): ?>
                  <option value="<?= e($p) ?>" <?= (($_POST['program'] ?? '') === $p) ? 'selected' : '' ?>><?= e($p) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Purpose *</label>
              <textarea name="purpose" class="form-control" rows="3" required><?= e($_POST['purpose'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Request Letter <small class="text-muted">(optional, PDF/DOC/JPG, max 5MB)</small></label>
              <input type="file" name="request_letter" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
            </div>
          </div>
          <button type="submit" class="btn btn-primary mt-3">Submit Booking</button>
          <a href="<?= BASE_URL ?>/student/my_bookings.php" class="btn btn-outline-secondary mt-3 ms-2">Cancel</a>
        </form>
      </div>
    </div>
    <?php else: ?>
      <div class="alert alert-info">
        <i class="fas fa-arrow-left me-1"></i> Select a facility from the list to book it.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
