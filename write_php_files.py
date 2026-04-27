import os

base = r'C:\xampp\htdocs\Library-Facilities-Booking-System\faculty'

dashboard = r'''<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin('faculty');

$uid = $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT
    COUNT(*) AS total,
    SUM(status="pending")   AS pending,
    SUM(status="approved")  AS approved,
    SUM(status="rejected")  AS rejected
    FROM bookings WHERE user_id = ?');
$stmt->execute([$uid]);
$counts = $stmt->fetch();

$stmt2 = $pdo->prepare('SELECT b.*, f.name AS facility_name FROM bookings b
    JOIN facilities f ON b.facility_id = f.id
    WHERE b.user_id = ? ORDER BY b.created_at DESC LIMIT 5');
$stmt2->execute([$uid]);
$bookings = $stmt2->fetchAll();

$pageTitle = 'Faculty Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container py-3">

<?= showFlash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0">Welcome, <?= e($_SESSION['name']) ?>!</h4>
  <a href="<?= BASE_URL ?>/faculty/book_facility.php" class="btn btn-primary">
    <i class="fas fa-plus me-1"></i>Book a Facility
  </a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card"><div class="label">Total</div><div class="value text-primary"><?= (int)$counts['total'] ?></div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="border-left-color:#ffc107"><div class="label">Pending</div><div class="value text-warning"><?= (int)$counts['pending'] ?></div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="border-left-color:#198754"><div class="label">Approved</div><div class="value text-success"><?= (int)$counts['approved'] ?></div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="border-left-color:#dc3545"><div class="label">Rejected</div><div class="value text-danger"><?= (int)$counts['rejected'] ?></div></div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Recent Bookings</span>
    <a href="<?= BASE_URL ?>/faculty/my_bookings.php" class="btn btn-sm btn-outline-primary">View All</a>
  </div>
  <div class="card-body p-0">
    <?php if (empty($bookings)): ?>
      <p class="text-muted text-center py-4">No bookings yet. <a href="<?= BASE_URL ?>/faculty/book_facility.php">Book now</a></p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Facility</th><th>Date</th><th>Time</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td><?= e($b['facility_name']) ?></td>
            <td><?= date('M j, Y', strtotime($b['booking_date'])) ?></td>
            <td><?= date('g:i A', strtotime($b['start_time'])) ?> &#8211; <?= date('g:i A', strtotime($b['end_time'])) ?></td>
            <td><?= statusBadge($b['status']) ?></td>
            <td><a href="<?= BASE_URL ?>/faculty/view_booking.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>'''

book_facility = r'''<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin('faculty');

$uid    = $_SESSION['user_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fid       = (int)($_POST['facility_id']    ?? 0);
    $date      = trim($_POST['booking_date']     ?? '');
    $startTime = trim($_POST['start_time']       ?? '');
    $endTime   = trim($_POST['end_time']         ?? '');
    $purpose   = trim($_POST['purpose']          ?? '');
    $attendees = (int)($_POST['attendees_count'] ?? 1);

    if (!$fid)       $errors[] = 'Select a facility.';
    if (!$date)      $errors[] = 'Date is required.';
    if (!$startTime) $errors[] = 'Start time is required.';
    if (!$endTime)   $errors[] = 'End time is required.';
    if ($startTime >= $endTime) $errors[] = 'End time must be after start time.';
    if (!$purpose)   $errors[] = 'Purpose is required.';
    if (strtotime($date) < strtotime('today')) $errors[] = 'Date cannot be in the past.';

    $facility = null;
    if ($fid) {
        $s = $pdo->prepare('SELECT * FROM facilities WHERE id = ? AND status = "active"');
        $s->execute([$fid]);
        $facility = $s->fetch();
        if (!$facility) $errors[] = 'Facility not found.';
    }

    if ($facility && $attendees > $facility['capacity'])
        $errors[] = 'Attendees exceed facility capacity (' . $facility['capacity'] . ').';

    if (empty($errors) && !slotAvailable($pdo, $fid, $date, $startTime, $endTime))
        $errors[] = 'That time slot is already booked. Pick another time.';

    $letterFile = null;
    if (!empty($_FILES['request_letter']['name'])) {
        $letterFile = uploadLetter($_FILES['request_letter']);
        if (!$letterFile) $errors[] = 'Invalid file. Allowed: PDF, DOC, JPG (max 5MB).';
    }

    if (empty($errors)) {
        $pdo->prepare('INSERT INTO bookings (user_id,facility_id,booking_date,start_time,end_time,purpose,attendees_count,request_letter,status) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$uid, $fid, $date, $startTime, $endTime, $purpose, $attendees, $letterFile, 'pending']);
        $bid = $pdo->lastInsertId();
        flash('success', 'Booking submitted! Awaiting admin approval.');
        header('Location: ' . BASE_URL . '/faculty/view_booking.php?id=' . $bid);
        exit;
    }
}

$facilities = $pdo->query('SELECT * FROM facilities WHERE status = "active" ORDER BY name')->fetchAll();

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
  <div class="col-md-7">
    <?php if ($selected): ?>
    <div class="card">
      <div class="card-header">Booking: <?= e($selected['name']) ?></div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="facility_id" value="<?= $selected['id'] ?>">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">Date *</label>
              <input type="date" name="booking_date" class="form-control"
                     min="<?= date('Y-m-d') ?>"
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
            <div class="col-12">
              <label class="form-label fw-semibold">Purpose *</label>
              <textarea name="purpose" class="form-control" rows="3" required><?= e($_POST['purpose'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Request Letter <small class="text-muted">(optional)</small></label>
              <input type="file" name="request_letter" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
            </div>
          </div>
          <button type="submit" class="btn btn-primary mt-3">Submit Booking</button>
          <a href="<?= BASE_URL ?>/faculty/my_bookings.php" class="btn btn-outline-secondary mt-3 ms-2">Cancel</a>
        </form>
      </div>
    </div>
    <?php else: ?>
      <div class="alert alert-info"><i class="fas fa-arrow-left me-1"></i> Select a facility from the list to book it.</div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>'''

my_bookings = r'''<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin('faculty');

$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    $bid = (int)$_POST['booking_id'];
    $pdo->prepare('UPDATE bookings SET status = "cancelled" WHERE id = ? AND user_id = ? AND status = "pending"')
        ->execute([$bid, $uid]);
    flash('success', 'Booking cancelled.');
    header('Location: ' . BASE_URL . '/faculty/my_bookings.php');
    exit;
}

$status = $_GET['status'] ?? '';
$sql = 'SELECT b.*, f.name AS facility_name FROM bookings b JOIN facilities f ON b.facility_id = f.id WHERE b.user_id = ?';
$params = [$uid];
if ($status) { $sql .= ' AND b.status = ?'; $params[] = $status; }
$sql .= ' ORDER BY b.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$pageTitle = 'My Bookings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container py-3">

<?= showFlash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0">My Bookings</h4>
  <a href="<?= BASE_URL ?>/faculty/book_facility.php" class="btn btn-primary btn-sm">
    <i class="fas fa-plus me-1"></i>New Booking
  </a>
</div>

<div class="mb-3">
  <?php foreach (['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $val => $lbl): ?>
    <a href="?status=<?= $val ?>" class="btn btn-sm <?= $status === $val ? 'btn-primary' : 'btn-outline-secondary' ?> me-1"><?= $lbl ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (empty($bookings)): ?>
      <p class="text-center text-muted py-4">No bookings found.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>#</th><th>Facility</th><th>Date</th><th>Time</th><th>Attendees</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td class="text-muted small">#<?= str_pad($b['id'],4,'0',STR_PAD_LEFT) ?></td>
            <td><?= e($b['facility_name']) ?></td>
            <td><?= date('M j, Y', strtotime($b['booking_date'])) ?></td>
            <td class="small"><?= date('g:i A', strtotime($b['start_time'])) ?> &#8211; <?= date('g:i A', strtotime($b['end_time'])) ?></td>
            <td><?= $b['attendees_count'] ?></td>
            <td><?= statusBadge($b['status']) ?></td>
            <td class="d-flex gap-1">
              <a href="<?= BASE_URL ?>/faculty/view_booking.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
              <?php if ($b['status'] === 'pending'): ?>
              <form method="POST" onsubmit="return confirm('Cancel this booking?')">
                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                <button name="cancel" class="btn btn-sm btn-outline-danger">Cancel</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>'''

view_booking = r'''<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin('faculty');

$uid = $_SESSION['user_id'];
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT b.*, f.name AS facility_name, f.type AS facility_type, f.location, f.capacity FROM bookings b JOIN facilities f ON b.facility_id = f.id WHERE b.id = ? AND b.user_id = ?');
$stmt->execute([$id, $uid]);
$booking = $stmt->fetch();

if (!$booking) {
    flash('danger', 'Booking not found.');
    header('Location: ' . BASE_URL . '/faculty/my_bookings.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    if ($booking['status'] === 'pending') {
        $pdo->prepare('UPDATE bookings SET status = "cancelled" WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        flash('success', 'Booking cancelled.');
    }
    header('Location: ' . BASE_URL . '/faculty/view_booking.php?id=' . $id);
    exit;
}

$pageTitle = 'Booking #' . str_pad($id, 4, '0', STR_PAD_LEFT);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container py-3">

<?= showFlash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0">Booking #<?= str_pad($id,4,'0',STR_PAD_LEFT) ?></h4>
  <a href="<?= BASE_URL ?>/faculty/my_bookings.php" class="btn btn-outline-secondary btn-sm">&#8592; Back</a>
</div>

<div class="row g-4">
  <div class="col-md-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span>Booking Details</span>
        <?= statusBadge($booking['status']) ?>
      </div>
      <div class="card-body">
        <table class="table table-borderless mb-0">
          <tr><th style="width:160px">Facility</th><td><?= e($booking['facility_name']) ?></td></tr>
          <tr><th>Type</th><td><?= facilityLabel($booking['facility_type']) ?></td></tr>
          <tr><th>Location</th><td><?= e($booking['location']) ?></td></tr>
          <tr><th>Date</th><td><?= date('l, F j, Y', strtotime($booking['booking_date'])) ?></td></tr>
          <tr><th>Time</th><td><?= date('g:i A', strtotime($booking['start_time'])) ?> &#8211; <?= date('g:i A', strtotime($booking['end_time'])) ?></td></tr>
          <tr><th>Attendees</th><td><?= $booking['attendees_count'] ?> / <?= $booking['capacity'] ?></td></tr>
          <tr><th>Purpose</th><td><?= nl2br(e($booking['purpose'])) ?></td></tr>
          <?php if ($booking['request_letter']): ?>
          <tr><th>Letter</th><td><a href="<?= UPLOAD_URL . e($booking['request_letter']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Download</a></td></tr>
          <?php endif; ?>
          <?php if ($booking['admin_remarks']): ?>
          <tr><th>Admin Remarks</th><td><div class="alert alert-info mb-0 py-2 small"><?= nl2br(e($booking['admin_remarks'])) ?></div></td></tr>
          <?php endif; ?>
          <tr><th>Submitted</th><td class="text-muted small"><?= date('M j, Y g:i A', strtotime($booking['created_at'])) ?></td></tr>
        </table>
      </div>
      <?php if ($booking['status'] === 'pending'): ?>
      <div class="card-footer">
        <form method="POST" onsubmit="return confirm('Cancel this booking?')">
          <button name="cancel" class="btn btn-danger btn-sm">Cancel Booking</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>'''

profile = r'''<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin('faculty');

$uid    = $_SESSION['user_id'];
$errors = [];

$userStmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$userStmt->execute([$uid]);
$user = $userStmt->fetch();

if (isset($_POST['update_profile'])) {
    $name    = trim($_POST['name']           ?? '');
    $dept    = trim($_POST['department']     ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    if (!$name) $errors[] = 'Name is required.';
    if (empty($errors)) {
        $pdo->prepare('UPDATE users SET name=?, department=?, contact_number=? WHERE id=?')
            ->execute([$name, $dept, $contact, $uid]);
        $_SESSION['name'] = $name;
        flash('success', 'Profile updated.');
        header('Location: ' . BASE_URL . '/faculty/profile.php'); exit;
    }
}

if (isset($_POST['change_password'])) {
    $cur  = $_POST['current_password'] ?? '';
    $new  = $_POST['new_password']     ?? '';
    $conf = $_POST['confirm_password'] ?? '';
    if (!password_verify($cur, $user['password'])) $errors[] = 'Current password is wrong.';
    if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
    if ($new !== $conf)   $errors[] = 'Passwords do not match.';
    if (empty($errors)) {
        $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($new, PASSWORD_BCRYPT), $uid]);
        flash('success', 'Password changed.');
        header('Location: ' . BASE_URL . '/faculty/profile.php'); exit;
    }
}

$userStmt->execute([$uid]);
$user = $userStmt->fetch();

$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container py-3">

<?= showFlash() ?>
<h4 class="mb-4">My Profile</h4>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-md-4">
    <div class="card text-center p-4">
      <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width:70px;height:70px;font-size:1.8rem">
        <?= strtoupper(mb_substr($user['name'],0,1)) ?>
      </div>
      <h5><?= e($user['name']) ?></h5>
      <p class="text-muted small mb-1"><?= e($user['email']) ?></p>
      <span class="badge bg-success"><?= ucfirst($user['role']) ?></span>
      <div class="text-start mt-3 small text-muted">
        <p><i class="fas fa-id-card me-2"></i><?= e($user['id_number'] ?? '&#8211;') ?></p>
        <p><i class="fas fa-building me-2"></i><?= e($user['department'] ?? '&#8211;') ?></p>
        <p class="mb-0"><i class="fas fa-phone me-2"></i><?= e($user['contact_number'] ?? '&#8211;') ?></p>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card mb-4">
      <div class="card-header">Edit Profile</div>
      <div class="card-body">
        <form method="POST">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Full Name *</label>
              <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email</label>
              <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Department</label>
              <input type="text" name="department" class="form-control" value="<?= e($user['department'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Contact Number</label>
              <input type="text" name="contact_number" class="form-control" value="<?= e($user['contact_number'] ?? '') ?>">
            </div>
          </div>
          <button type="submit" name="update_profile" class="btn btn-primary mt-3">Save Changes</button>
        </form>
      </div>
    </div>
    <div class="card">
      <div class="card-header">Change Password</div>
      <div class="card-body">
        <form method="POST">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Current Password</label>
              <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">New Password</label>
              <input type="password" name="new_password" class="form-control" placeholder="Min. 8 chars" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Confirm New Password</label>
              <input type="password" name="confirm_password" class="form-control" required>
            </div>
          </div>
          <button type="submit" name="change_password" class="btn btn-warning mt-3">Change Password</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>'''

files = {
    'dashboard.php':     dashboard,
    'book_facility.php': book_facility,
    'my_bookings.php':   my_bookings,
    'view_booking.php':  view_booking,
    'profile.php':       profile,
}

for name, content in files.items():
    path = os.path.join(base, name)
    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)
    size = os.path.getsize(path)
    print(f"OK  {name:25s} {size:6d} bytes")
