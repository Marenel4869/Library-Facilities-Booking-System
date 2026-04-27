# -*- coding: utf-8 -*-
import os

BASE = r'C:\xampp\htdocs\Library-Facilities-Booking-System\admin'

files = {}

# ── dashboard.php ──────────────────────────────────────────────────────────────
files['dashboard.php'] = """\
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin('admin');

$counts = $pdo->query('SELECT
    COUNT(*) AS total,
    SUM(status="pending")   AS pending,
    SUM(status="approved")  AS approved,
    SUM(status="rejected")  AS rejected
    FROM bookings')->fetch();

$totalUsers = $pdo->query('SELECT COUNT(*) FROM users WHERE role != "admin"')->fetchColumn();
$totalFacilities = $pdo->query('SELECT COUNT(*) FROM facilities')->fetchColumn();

$pending = $pdo->query('SELECT b.*, u.name AS user_name, f.name AS facility_name
    FROM bookings b JOIN users u ON b.user_id=u.id JOIN facilities f ON b.facility_id=f.id
    WHERE b.status="pending" ORDER BY b.created_at ASC LIMIT 10')->fetchAll();

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container py-3">

<?= showFlash() ?>
<h4 class="mb-4">Dashboard</h4>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card"><div class="label">Total Bookings</div><div class="value text-primary"><?= (int)$counts['total'] ?></div></div>
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
  <div class="col-6 col-md-3">
    <div class="stat-card" style="border-left-color:#6f42c1"><div class="label">Users</div><div class="value text-purple" style="color:#6f42c1"><?= (int)$totalUsers ?></div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="border-left-color:#0dcaf0"><div class="label">Facilities</div><div class="value text-info"><?= (int)$totalFacilities ?></div></div>
  </div>
</div>

<!-- Pending Bookings -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-clock me-1 text-warning"></i>Pending Approvals</span>
    <a href="<?= BASE_URL ?>/admin/manage_bookings.php?status=pending" class="btn btn-sm btn-outline-warning">View All</a>
  </div>
  <div class="card-body p-0">
    <?php if (empty($pending)): ?>
      <p class="text-center text-muted py-4">No pending bookings.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>#</th><th>User</th><th>Facility</th><th>Date</th><th>Submitted</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pending as $b): ?>
          <tr>
            <td class="text-muted small">#<?= str_pad($b['id'],4,'0',STR_PAD_LEFT) ?></td>
            <td><?= e($b['user_name']) ?></td>
            <td><?= e($b['facility_name']) ?></td>
            <td><?= date('M j, Y', strtotime($b['booking_date'])) ?></td>
            <td class="text-muted small"><?= date('M j g:i A', strtotime($b['created_at'])) ?></td>
            <td><a href="<?= BASE_URL ?>/admin/view_booking.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-warning">Review</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
"""

# ── manage_bookings.php ────────────────────────────────────────────────────────
files['manage_bookings.php'] = """\
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin('admin');

$status   = $_GET['status']  ?? '';
$search   = trim($_GET['q']  ?? '');

$sql = 'SELECT b.*, u.name AS user_name, u.role AS user_role, f.name AS facility_name
        FROM bookings b JOIN users u ON b.user_id=u.id JOIN facilities f ON b.facility_id=f.id WHERE 1=1';
$params = [];

if ($status) { $sql .= ' AND b.status = ?'; $params[] = $status; }
if ($search)  { $sql .= ' AND (u.name LIKE ? OR f.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= ' ORDER BY b.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$pageTitle = 'Manage Bookings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container py-3">

<?= showFlash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0">Manage Bookings</h4>
</div>

<!-- Filters -->
<form class="row g-2 mb-3" method="GET">
  <div class="col-md-5">
    <input type="text" name="q" class="form-control" placeholder="Search by user or facility..." value="<?= e($search) ?>">
  </div>
  <div class="col-md-3">
    <select name="status" class="form-select">
      <option value="">All Statuses</option>
      <?php foreach (['pending','approved','rejected','cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <button class="btn btn-primary w-100">Filter</button>
  </div>
  <div class="col-md-2">
    <a href="<?= BASE_URL ?>/admin/manage_bookings.php" class="btn btn-outline-secondary w-100">Reset</a>
  </div>
</form>

<div class="card">
  <div class="card-body p-0">
    <?php if (empty($bookings)): ?>
      <p class="text-center text-muted py-4">No bookings found.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>#</th><th>User</th><th>Role</th><th>Facility</th><th>Date</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td class="text-muted small">#<?= str_pad($b['id'],4,'0',STR_PAD_LEFT) ?></td>
            <td><?= e($b['user_name']) ?></td>
            <td><span class="badge bg-secondary"><?= ucfirst($b['user_role']) ?></span></td>
            <td><?= e($b['facility_name']) ?></td>
            <td><?= date('M j, Y', strtotime($b['booking_date'])) ?></td>
            <td><?= statusBadge($b['status']) ?></td>
            <td class="text-muted small"><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
            <td><a href="<?= BASE_URL ?>/admin/view_booking.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
"""

# ── view_booking.php ───────────────────────────────────────────────────────────
files['view_booking.php'] = """\
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin('admin');

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT b.*, u.name AS user_name, u.email AS user_email, u.role AS user_role,
    u.department, u.contact_number, u.id_number,
    f.name AS facility_name, f.type AS facility_type, f.location, f.capacity
    FROM bookings b JOIN users u ON b.user_id=u.id JOIN facilities f ON b.facility_id=f.id WHERE b.id = ?');
$stmt->execute([$id]);
$booking = $stmt->fetch();

if (!$booking) {
    flash('danger', 'Booking not found.');
    header('Location: ' . BASE_URL . '/admin/manage_bookings.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']       ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    if (!in_array($action, ['approved', 'rejected'])) {
        $errors[] = 'Invalid action.';
    } else {
        $pdo->prepare('UPDATE bookings SET status=?, admin_remarks=?, reviewed_at=NOW() WHERE id=?')
            ->execute([$action, $remarks, $id]);
        flash('success', 'Booking ' . $action . '.');
        header('Location: ' . BASE_URL . '/admin/view_booking.php?id=' . $id);
        exit;
    }
}

// Reload after update
$stmt->execute([$id]);
$booking = $stmt->fetch();

$pageTitle = 'Booking #' . str_pad($id, 4, '0', STR_PAD_LEFT);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container py-3">

<?= showFlash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0">Booking #<?= str_pad($id,4,'0',STR_PAD_LEFT) ?></h4>
  <a href="<?= BASE_URL ?>/admin/manage_bookings.php" class="btn btn-outline-secondary btn-sm">&#8592; Back</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><?= e($errors[0]) ?></div>
<?php endif; ?>

<div class="row g-4">
  <!-- Booking info -->
  <div class="col-md-8">
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between">
        <span>Booking Details</span>
        <?= statusBadge($booking['status']) ?>
      </div>
      <div class="card-body">
        <table class="table table-borderless mb-0">
          <tr><th style="width:170px">Facility</th><td><?= e($booking['facility_name']) ?></td></tr>
          <tr><th>Type</th><td><?= facilityLabel($booking['facility_type']) ?></td></tr>
          <tr><th>Location</th><td><?= e($booking['location']) ?></td></tr>
          <tr><th>Date</th><td><?= date('l, F j, Y', strtotime($booking['booking_date'])) ?></td></tr>
          <tr><th>Time</th><td><?= date('g:i A', strtotime($booking['start_time'])) ?> &ndash; <?= date('g:i A', strtotime($booking['end_time'])) ?></td></tr>
          <tr><th>Attendees</th><td><?= $booking['attendees_count'] ?> / <?= $booking['capacity'] ?></td></tr>
          <tr><th>Purpose</th><td><?= nl2br(e($booking['purpose'])) ?></td></tr>
          <?php if ($booking['request_letter']): ?>
          <tr><th>Letter</th><td><a href="<?= UPLOAD_URL . e($booking['request_letter']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Download</a></td></tr>
          <?php endif; ?>
          <?php if ($booking['admin_remarks']): ?>
          <tr><th>Remarks</th><td><div class="alert alert-info mb-0 py-2 small"><?= nl2br(e($booking['admin_remarks'])) ?></div></td></tr>
          <?php endif; ?>
          <tr><th>Submitted</th><td class="text-muted small"><?= date('M j, Y g:i A', strtotime($booking['created_at'])) ?></td></tr>
        </table>
      </div>
    </div>

    <!-- Approve/Reject form -->
    <?php if ($booking['status'] === 'pending'): ?>
    <div class="card">
      <div class="card-header">Review Booking</div>
      <div class="card-body">
        <form method="POST">
          <div class="mb-3">
            <label class="form-label fw-semibold">Remarks (optional)</label>
            <textarea name="remarks" class="form-control" rows="3" placeholder="Optional remarks for the user..."></textarea>
          </div>
          <button name="action" value="approved" class="btn btn-success me-2" onclick="return confirm('Approve this booking?')">
            <i class="fas fa-check me-1"></i>Approve
          </button>
          <button name="action" value="rejected" class="btn btn-danger" onclick="return confirm('Reject this booking?')">
            <i class="fas fa-times me-1"></i>Reject
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- User info -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header">Submitted By</div>
      <div class="card-body small">
        <p class="mb-1"><strong><?= e($booking['user_name']) ?></strong> <span class="badge bg-secondary"><?= ucfirst($booking['user_role']) ?></span></p>
        <p class="mb-1 text-muted"><?= e($booking['user_email']) ?></p>
        <?php if ($booking['id_number']): ?><p class="mb-1"><i class="fas fa-id-card me-1 text-muted"></i><?= e($booking['id_number']) ?></p><?php endif; ?>
        <?php if ($booking['department']): ?><p class="mb-1"><i class="fas fa-building me-1 text-muted"></i><?= e($booking['department']) ?></p><?php endif; ?>
        <?php if ($booking['contact_number']): ?><p class="mb-0"><i class="fas fa-phone me-1 text-muted"></i><?= e($booking['contact_number']) ?></p><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
"""

# ── manage_facilities.php ──────────────────────────────────────────────────────
files['manage_facilities.php'] = """\
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin('admin');

// Toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle'])) {
    $fid = (int)$_POST['facility_id'];
    $stmt = $pdo->prepare('SELECT status FROM facilities WHERE id = ?');
    $stmt->execute([$fid]);
    $cur = $stmt->fetchColumn();
    $newStatus = ($cur === 'active') ? 'inactive' : 'active';
    $pdo->prepare('UPDATE facilities SET status=? WHERE id=?')->execute([$newStatus, $fid]);
    flash('success', 'Facility status updated.');
    header('Location: ' . BASE_URL . '/admin/manage_facilities.php'); exit;
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $fid = (int)$_POST['facility_id'];
    $pdo->prepare('DELETE FROM facilities WHERE id=?')->execute([$fid]);
    flash('success', 'Facility deleted.');
    header('Location: ' . BASE_URL . '/admin/manage_facilities.php'); exit;
}

$facilities = $pdo->query('SELECT f.*, (SELECT COUNT(*) FROM bookings WHERE facility_id=f.id) AS booking_count FROM facilities f ORDER BY f.name')->fetchAll();

$pageTitle = 'Manage Facilities';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container py-3">

<?= showFlash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0">Manage Facilities</h4>
  <a href="<?= BASE_URL ?>/admin/add_facility.php" class="btn btn-primary btn-sm">
    <i class="fas fa-plus me-1"></i>Add Facility
  </a>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (empty($facilities)): ?>
      <p class="text-center text-muted py-4">No facilities yet. <a href="<?= BASE_URL ?>/admin/add_facility.php">Add one</a></p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Name</th><th>Type</th><th>Location</th><th>Capacity</th><th>Hours</th><th>Bookings</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($facilities as $f): ?>
          <tr>
            <td><strong><?= e($f['name']) ?></strong></td>
            <td><?= facilityLabel($f['type']) ?></td>
            <td><?= e($f['location']) ?></td>
            <td><?= $f['capacity'] ?></td>
            <td class="small"><?= substr($f['open_time'],0,5) ?> &ndash; <?= substr($f['close_time'],0,5) ?></td>
            <td><?= $f['booking_count'] ?></td>
            <td><span class="badge <?= $f['status']==='active' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($f['status']) ?></span></td>
            <td>
              <a href="<?= BASE_URL ?>/admin/edit_facility.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary me-1">Edit</a>
              <form class="d-inline" method="POST">
                <input type="hidden" name="facility_id" value="<?= $f['id'] ?>">
                <button name="toggle" class="btn btn-sm btn-outline-secondary me-1"><?= $f['status']==='active' ? 'Deactivate' : 'Activate' ?></button>
              </form>
              <form class="d-inline" method="POST" onsubmit="return confirm('Delete this facility?')">
                <input type="hidden" name="facility_id" value="<?= $f['id'] ?>">
                <button name="delete" class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
"""

# ── add_facility.php ───────────────────────────────────────────────────────────
files['add_facility.php'] = """\
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin('admin');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name']        ?? '');
    $type       = trim($_POST['type']        ?? '');
    $location   = trim($_POST['location']    ?? '');
    $capacity   = (int)($_POST['capacity']   ?? 0);
    $openTime   = trim($_POST['open_time']   ?? '');
    $closeTime  = trim($_POST['close_time']  ?? '');
    $description= trim($_POST['description'] ?? '');
    $equipment  = trim($_POST['equipment']   ?? '');

    if (!$name)     $errors[] = 'Name is required.';
    if (!$type)     $errors[] = 'Type is required.';
    if (!$location) $errors[] = 'Location is required.';
    if ($capacity < 1) $errors[] = 'Capacity must be at least 1.';
    if (!$openTime || !$closeTime) $errors[] = 'Operating hours are required.';
    if ($openTime >= $closeTime) $errors[] = 'Close time must be after open time.';

    if (empty($errors)) {
        $pdo->prepare('INSERT INTO facilities (name,type,location,capacity,open_time,close_time,description,equipment,status) VALUES (?,?,?,?,?,?,?,?,"active")')
            ->execute([$name, $type, $location, $capacity, $openTime, $closeTime, $description, $equipment]);
        flash('success', 'Facility added successfully.');
        header('Location: ' . BASE_URL . '/admin/manage_facilities.php'); exit;
    }
}

$pageTitle = 'Add Facility';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container py-3">

<?= showFlash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0">Add Facility</h4>
  <a href="<?= BASE_URL ?>/admin/manage_facilities.php" class="btn btn-outline-secondary btn-sm">&#8592; Back</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <form method="POST">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Facility Name *</label>
          <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Type *</label>
          <select name="type" class="form-select" required>
            <option value="">-- Select --</option>
            <?php foreach (['study_room'=>'Study Room','conference_room'=>'Conference Room','computer_lab'=>'Computer Lab','reading_hall'=>'Reading Hall','auditorium'=>'Auditorium'] as $val=>$lbl): ?>
              <option value="<?= $val ?>" <?= ($_POST['type'] ?? '')===$val ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label fw-semibold">Location *</label>
          <input type="text" name="location" class="form-control" value="<?= e($_POST['location'] ?? '') ?>" placeholder="e.g. 2nd Floor, Room 201" required>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Capacity *</label>
          <input type="number" name="capacity" class="form-control" min="1" value="<?= e($_POST['capacity'] ?? '') ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Open Time *</label>
          <input type="time" name="open_time" class="form-control" value="<?= e($_POST['open_time'] ?? '08:00') ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Close Time *</label>
          <input type="time" name="close_time" class="form-control" value="<?= e($_POST['close_time'] ?? '18:00') ?>" required>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Description</label>
          <textarea name="description" class="form-control" rows="3"><?= e($_POST['description'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Equipment / Amenities</label>
          <textarea name="equipment" class="form-control" rows="2" placeholder="e.g. Projector, Whiteboard, AC"><?= e($_POST['equipment'] ?? '') ?></textarea>
        </div>
      </div>
      <button type="submit" class="btn btn-primary mt-3">Add Facility</button>
      <a href="<?= BASE_URL ?>/admin/manage_facilities.php" class="btn btn-outline-secondary mt-3 ms-2">Cancel</a>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
"""

# ── edit_facility.php ──────────────────────────────────────────────────────────
files['edit_facility.php'] = """\
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin('admin');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM facilities WHERE id = ?');
$stmt->execute([$id]);
$facility = $stmt->fetch();

if (!$facility) {
    flash('danger', 'Facility not found.');
    header('Location: ' . BASE_URL . '/admin/manage_facilities.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']        ?? '');
    $type        = trim($_POST['type']        ?? '');
    $location    = trim($_POST['location']    ?? '');
    $capacity    = (int)($_POST['capacity']   ?? 0);
    $openTime    = trim($_POST['open_time']   ?? '');
    $closeTime   = trim($_POST['close_time']  ?? '');
    $description = trim($_POST['description'] ?? '');
    $equipment   = trim($_POST['equipment']   ?? '');
    $status      = trim($_POST['status']      ?? 'active');

    if (!$name)     $errors[] = 'Name is required.';
    if (!$type)     $errors[] = 'Type is required.';
    if (!$location) $errors[] = 'Location is required.';
    if ($capacity < 1) $errors[] = 'Capacity must be at least 1.';
    if (!$openTime || !$closeTime) $errors[] = 'Operating hours are required.';
    if ($openTime >= $closeTime) $errors[] = 'Close time must be after open time.';

    if (empty($errors)) {
        $pdo->prepare('UPDATE facilities SET name=?,type=?,location=?,capacity=?,open_time=?,close_time=?,description=?,equipment=?,status=? WHERE id=?')
            ->execute([$name, $type, $location, $capacity, $openTime, $closeTime, $description, $equipment, $status, $id]);
        flash('success', 'Facility updated.');
        header('Location: ' . BASE_URL . '/admin/manage_facilities.php'); exit;
    }
    // Restore values on error
    $facility = array_merge($facility, $_POST);
}

$pageTitle = 'Edit Facility';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container py-3">

<?= showFlash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0">Edit Facility</h4>
  <a href="<?= BASE_URL ?>/admin/manage_facilities.php" class="btn btn-outline-secondary btn-sm">&#8592; Back</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <form method="POST">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Facility Name *</label>
          <input type="text" name="name" class="form-control" value="<?= e($facility['name']) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Type *</label>
          <select name="type" class="form-select" required>
            <?php foreach (['study_room'=>'Study Room','conference_room'=>'Conference Room','computer_lab'=>'Computer Lab','reading_hall'=>'Reading Hall','auditorium'=>'Auditorium'] as $val=>$lbl): ?>
              <option value="<?= $val ?>" <?= $facility['type']===$val ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label fw-semibold">Location *</label>
          <input type="text" name="location" class="form-control" value="<?= e($facility['location']) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Capacity *</label>
          <input type="number" name="capacity" class="form-control" min="1" value="<?= e($facility['capacity']) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Open Time *</label>
          <input type="time" name="open_time" class="form-control" value="<?= e(substr($facility['open_time'],0,5)) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Close Time *</label>
          <input type="time" name="close_time" class="form-control" value="<?= e(substr($facility['close_time'],0,5)) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Status</label>
          <select name="status" class="form-select">
            <option value="active"   <?= $facility['status']==='active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $facility['status']==='inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Description</label>
          <textarea name="description" class="form-control" rows="3"><?= e($facility['description'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Equipment / Amenities</label>
          <textarea name="equipment" class="form-control" rows="2"><?= e($facility['equipment'] ?? '') ?></textarea>
        </div>
      </div>
      <button type="submit" class="btn btn-primary mt-3">Save Changes</button>
      <a href="<?= BASE_URL ?>/admin/manage_facilities.php" class="btn btn-outline-secondary mt-3 ms-2">Cancel</a>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
"""

# ── manage_users.php ───────────────────────────────────────────────────────────
files['manage_users.php'] = """\
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin('admin');

// Suspend/unsuspend
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $uid = (int)$_POST['user_id'];
    $stmt = $pdo->prepare('SELECT status FROM users WHERE id = ? AND role != "admin"');
    $stmt->execute([$uid]);
    $cur = $stmt->fetchColumn();
    $newStatus = ($cur === 'active') ? 'suspended' : 'active';
    $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$newStatus, $uid]);
    flash('success', 'User status updated.');
    header('Location: ' . BASE_URL . '/admin/manage_users.php'); exit;
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $uid = (int)$_POST['user_id'];
    $pdo->prepare('DELETE FROM users WHERE id=? AND role != "admin"')->execute([$uid]);
    flash('success', 'User deleted.');
    header('Location: ' . BASE_URL . '/admin/manage_users.php'); exit;
}

$role   = $_GET['role']  ?? '';
$search = trim($_GET['q'] ?? '');

$sql = 'SELECT * FROM users WHERE role != "admin"';
$params = [];
if ($role)   { $sql .= ' AND role = ?';   $params[] = $role; }
if ($search) { $sql .= ' AND (name LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container py-3">

<?= showFlash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0">Manage Users</h4>
</div>

<!-- Filter -->
<form class="row g-2 mb-3" method="GET">
  <div class="col-md-5">
    <input type="text" name="q" class="form-control" placeholder="Search name or email..." value="<?= e($search) ?>">
  </div>
  <div class="col-md-3">
    <select name="role" class="form-select">
      <option value="">All Roles</option>
      <option value="student"  <?= $role==='student'  ? 'selected' : '' ?>>Student</option>
      <option value="faculty"  <?= $role==='faculty'  ? 'selected' : '' ?>>Faculty</option>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
  <div class="col-md-2"><a href="<?= BASE_URL ?>/admin/manage_users.php" class="btn btn-outline-secondary w-100">Reset</a></div>
</form>

<div class="card">
  <div class="card-body p-0">
    <?php if (empty($users)): ?>
      <p class="text-center text-muted py-4">No users found.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>ID Number</th><th>Department</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= e($u['name']) ?></td>
            <td class="small"><?= e($u['email']) ?></td>
            <td><span class="badge bg-secondary"><?= ucfirst($u['role']) ?></span></td>
            <td class="small"><?= e($u['id_number'] ?? '&ndash;') ?></td>
            <td class="small"><?= e($u['department'] ?? '&ndash;') ?></td>
            <td>
              <?php if ($u['status'] === 'active'): ?>
                <span class="badge bg-success">Active</span>
              <?php else: ?>
                <span class="badge bg-warning text-dark">Suspended</span>
              <?php endif; ?>
            </td>
            <td class="small text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
            <td>
              <form class="d-inline" method="POST">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button name="toggle_status" class="btn btn-sm btn-outline-secondary me-1"><?= $u['status']==='active' ? 'Suspend' : 'Unsuspend' ?></button>
              </form>
              <form class="d-inline" method="POST" onsubmit="return confirm('Delete this user?')">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button name="delete" class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
"""

# ── profile.php ────────────────────────────────────────────────────────────────
files['profile.php'] = """\
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin('admin');

$uid    = $_SESSION['user_id'];
$errors = [];

$userStmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$userStmt->execute([$uid]);
$user = $userStmt->fetch();

if (isset($_POST['update_profile'])) {
    $name    = trim($_POST['name']           ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    if (!$name) $errors[] = 'Name is required.';
    if (empty($errors)) {
        $pdo->prepare('UPDATE users SET name=?, contact_number=? WHERE id=?')
            ->execute([$name, $contact, $uid]);
        $_SESSION['name'] = $name;
        flash('success', 'Profile updated.');
        header('Location: ' . BASE_URL . '/admin/profile.php'); exit;
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
        header('Location: ' . BASE_URL . '/admin/profile.php'); exit;
    }
}

$userStmt->execute([$uid]);
$user = $userStmt->fetch();

$pageTitle = 'Admin Profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container py-3">

<?= showFlash() ?>
<h4 class="mb-4">Admin Profile</h4>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-md-4">
    <div class="card text-center p-4">
      <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width:70px;height:70px;font-size:1.8rem">
        <?= strtoupper(mb_substr($user['name'],0,1)) ?>
      </div>
      <h5><?= e($user['name']) ?></h5>
      <p class="text-muted small mb-1"><?= e($user['email']) ?></p>
      <span class="badge bg-danger">Administrator</span>
      <div class="text-start mt-3 small text-muted">
        <p class="mb-0"><i class="fas fa-phone me-2"></i><?= e($user['contact_number'] ?? '&ndash;') ?></p>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
"""

# Write all files
for name, content in files.items():
    path = os.path.join(BASE, name)
    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)
    size = os.path.getsize(path)
    print(f'OK  {name}  ({size} bytes)')

print()
print('All 8 files written successfully.')
