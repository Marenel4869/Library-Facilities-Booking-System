<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('faculty');

$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    verifyCsrf();
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
<main><div class="container-fluid py-3 px-3 px-lg-4">

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
            <td class="small"><?= date('g:i A', strtotime($b['start_time'])) ?> – <?= date('g:i A', strtotime($b['end_time'])) ?></td>
            <td><?= $b['attendees_count'] ?></td>
            <td><?= statusBadge($b['status']) ?></td>
            <td class="d-flex gap-1">
              <a href="<?= BASE_URL ?>/faculty/view_booking.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
              <?php if ($b['status'] === 'pending'): ?>
              <form method="POST" onsubmit="return confirm('Cancel this booking?')">
                <?= csrfField() ?>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>