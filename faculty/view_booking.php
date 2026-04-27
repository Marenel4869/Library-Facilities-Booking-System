<?php
require_once __DIR__ . '/../includes/bootstrap.php';
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
  <a href="<?= BASE_URL ?>/faculty/my_bookings.php" class="btn btn-outline-secondary btn-sm">← Back</a>
</div>

<div class="row g-4">
  <div class="col-md-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span>Booking Details</span>
        <?= statusBadge($booking['status']) ?>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-borderless mb-0">
          <tr><th style="width:160px" class="ps-3">Facility</th><td><?= e($booking['facility_name']) ?></td></tr>
          <tr><th class="ps-3">Type</th><td><?= facilityLabel($booking['facility_type']) ?></td></tr>
          <tr><th class="ps-3">Location</th><td><?= e($booking['location']) ?></td></tr>
          <tr><th class="ps-3">Date</th><td><?= date('l, F j, Y', strtotime($booking['booking_date'])) ?></td></tr>
          <tr><th class="ps-3">Time</th><td><?= date('g:i A', strtotime($booking['start_time'])) ?> – <?= date('g:i A', strtotime($booking['end_time'])) ?></td></tr>
          <tr><th class="ps-3">Attendees</th><td><?= $booking['attendees_count'] ?> / <?= $booking['capacity'] ?></td></tr>
          <tr><th class="ps-3">Purpose</th><td><?= nl2br(e($booking['purpose'])) ?></td></tr>
          <?php if (!empty($booking['letter_path'])): ?>
          <tr><th class="ps-3">Letter</th><td><a href="<?= UPLOAD_URL . e($booking['letter_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Download</a></td></tr>
          <?php endif; ?>
          <?php if ($booking['admin_remarks']): ?>
          <tr><th class="ps-3">Admin Remarks</th><td><div class="alert alert-info mb-0 py-2 small"><?= nl2br(e($booking['admin_remarks'])) ?></div></td></tr>
          <?php endif; ?>
          <tr><th class="ps-3">Submitted</th><td class="text-muted small"><?= date('M j, Y g:i A', strtotime($booking['created_at'])) ?></td></tr>
        </table>
        </div><!-- /.table-responsive -->
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>