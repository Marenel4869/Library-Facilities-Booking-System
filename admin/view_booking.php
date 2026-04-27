<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('admin');

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT b.*, u.name AS user_name, u.email AS user_email, u.role AS user_role,
    u.department, u.contact_number, u.id_number,
    f.name AS facility_name, f.type AS facility_type, f.location, f.capacity,
    a.name AS reviewer_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN facilities f ON b.facility_id = f.id
    LEFT JOIN users a ON b.approved_by = a.id
    WHERE b.id = ?');
$stmt->execute([$id]);
$booking = $stmt->fetch();

if (!$booking) {
    flash('danger', 'Booking not found.');
    header('Location: ' . BASE_URL . '/admin/manage_bookings.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action    = $_POST['action']    ?? '';
    $remarks   = trim($_POST['remarks']   ?? '');
    $esig      = trim($_POST['esignature'] ?? '');

    if (!in_array($action, ['approved', 'rejected'])) {
        $errors[] = 'Invalid action.';
    } elseif ($action === 'rejected' && !$remarks) {
        $errors[] = 'Please provide a reason for rejection.';
    } else {
        try {
            $pdo->beginTransaction();

            $pdo->prepare('UPDATE bookings SET status=?, admin_remarks=?, reviewed_at=NOW(),
                            approved_by=?, esignature=? WHERE id=?')
                ->execute([$action, $remarks, $_SESSION['user_id'], $esig ?: null, $id]);

            // Notify the booking owner
            $notifLink = BASE_URL . '/' . $booking['user_role'] . '/my_bookings.php';
            $facName   = $booking['facility_name'];
            $dtFmt     = date('M j, Y', strtotime($booking['booking_date'])) . ' at ' . date('g:i A', strtotime($booking['start_time']));
            if ($action === 'approved') {
                $notifMsg = "✅ Your booking for \"{$facName}\" on {$dtFmt} has been approved!" . ($esig ? " — Signed by: {$esig}" : '');
                createNotification($pdo, $booking['user_id'], $notifMsg, 'success', $notifLink);
                sendEmailSim($booking['user_id'], $pdo, "Booking Approved — {$facName}", $notifMsg);
            } else {
                $notifMsg = "❌ Your booking for \"{$facName}\" on {$dtFmt} was rejected." . ($remarks ? " Reason: {$remarks}" : '');
                createNotification($pdo, $booking['user_id'], $notifMsg, 'danger', $notifLink);
                sendEmailSim($booking['user_id'], $pdo, "Booking Rejected — {$facName}", $notifMsg);
            }

            $detail = "Booking #" . str_pad($id,4,'0',STR_PAD_LEFT)
                    . " for '{$booking['facility_name']}' by {$booking['user_name']}"
                    . ($remarks ? " — Remarks: $remarks" : '');
            logAudit($pdo, "booking_{$action}", $detail);

            $pdo->commit();

            flash('success', 'Booking ' . $action . '.');
            header('Location: ' . BASE_URL . '/admin/view_booking.php?id=' . $id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'An error occurred while processing the booking. Please try again.';
        }
    }
}

// Reload after update
$stmt->execute([$id]);
$booking = $stmt->fetch();

$pageTitle = 'Booking #' . str_pad($id, 4, '0', STR_PAD_LEFT);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container-fluid py-3 px-3 px-lg-4 admin-page">

<?= showFlash() ?>

<div class="admin-hero mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex align-items-center gap-3">
      <div class="admin-hero-icon"><i class="fas fa-calendar-check"></i></div>
      <div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <h4 class="mb-0 fw-bold text-white">Booking #<?= str_pad($id,4,'0',STR_PAD_LEFT) ?></h4>
          <?= statusBadge($booking['status']) ?>
        </div>
        <div class="admin-hero-sub">Review details, letter, and approval decision</div>
      </div>
    </div>
    <a href="<?= BASE_URL ?>/admin/manage_bookings.php" class="btn btn-light btn-sm">← Back</a>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><?= e($errors[0]) ?></div>
<?php endif; ?>

<div class="row g-4">
  <!-- Left: Booking details + approval form -->
  <div class="col-md-8">

    <div class="card mb-4">
      <div class="card-header fw-semibold">Booking Details</div>
      <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-borderless mb-0">
          <tr><th style="width:170px">Facility</th><td><?= e($booking['facility_name']) ?></td></tr>
          <tr><th>Type</th><td><?= facilityLabel($booking['facility_type']) ?></td></tr>
          <tr><th>Location</th><td><?= e($booking['location']) ?></td></tr>
          <tr><th>Date</th><td><?= date('l, F j, Y', strtotime($booking['booking_date'])) ?></td></tr>
          <tr><th>Time</th><td><?= date('g:i A', strtotime($booking['start_time'])) ?> – <?= date('g:i A', strtotime($booking['end_time'])) ?></td></tr>
          <tr><th>Attendees</th><td><?= $booking['attendees_count'] ?> / <?= $booking['capacity'] ?></td></tr>
          <tr><th>Program</th><td><?= e($booking['program'] ?? '—') ?></td></tr>
          <tr><th>Purpose</th><td><?= nl2br(e($booking['purpose'] ?? '—')) ?></td></tr>
          <tr><th>Submitted</th><td class="text-muted small"><?= date('M j, Y g:i A', strtotime($booking['created_at'])) ?></td></tr>
          <?php if ($booking['reviewed_at']): ?>
          <tr>
            <th>Reviewed</th>
            <td class="small">
              <?= date('M j, Y g:i A', strtotime($booking['reviewed_at'])) ?>
              <?php if ($booking['reviewer_name']): ?>
                by <strong><?= e($booking['reviewer_name']) ?></strong>
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>
          <?php if ($booking['admin_remarks']): ?>
          <tr><th>Remarks</th><td><div class="alert alert-info mb-0 py-2 small"><?= nl2br(e($booking['admin_remarks'])) ?></div></td></tr>
          <?php endif; ?>
          <?php if ($booking['esignature']): ?>
          <tr><th>E-Signature</th><td><span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2"><?= e($booking['esignature']) ?></span></td></tr>
          <?php endif; ?>
        </table>
        </div><!-- /.table-responsive -->
      </div>
    </div>
    <?php if (!empty($booking['letter_path'])): ?>
    <div class="card mb-4">
      <div class="card-header fw-semibold"><i class="fas fa-file-alt me-2 text-primary"></i>Request Letter</div>
      <div class="card-body">
        <?php
          $ext = strtolower(pathinfo($booking['letter_path'], PATHINFO_EXTENSION));
          $url = BASE_URL . '/uploads/' . rawurlencode($booking['letter_path']);
        ?>
        <?php if (in_array($ext, ['jpg','jpeg','png'])): ?>
          <img src="<?= e($url) ?>" class="img-fluid rounded" style="max-height:400px" alt="Request Letter">
        <?php elseif ($ext === 'pdf'): ?>
          <embed src="<?= e($url) ?>" type="application/pdf" width="100%" height="400px" class="rounded border">
        <?php else: ?>
          <p class="mb-2 text-muted small">Preview not available for this file type.</p>
        <?php endif; ?>
        <a href="<?= e($url) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
          <i class="fas fa-download me-1"></i>Open / Download
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Approve / Reject form -->
    <?php if ($booking['status'] === 'pending'): ?>
    <div class="card">
      <div class="card-header fw-semibold">Review Booking</div>
      <div class="card-body">
        <form method="POST">
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label fw-semibold">Admin Remarks <span class="text-danger small">(required when rejecting)</span></label>
            <textarea name="remarks" class="form-control" rows="3"
              placeholder="Add a note for the user (required for rejection)..."><?= e($_POST['remarks'] ?? '') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">E-Signature <span class="text-muted small">(optional — applied on approval)</span></label>
            <input type="text" name="esignature" class="form-control"
              placeholder="e.g. John Doe, Library Administrator"
              value="<?= e($_POST['esignature'] ?? $_SESSION['name'] ?? '') ?>">
            <div class="form-text">Your name/title will be embedded as the official e-signature on the approved booking.</div>
          </div>
          <div class="d-flex gap-2">
            <button name="action" value="approved" class="btn btn-success"
              onclick="return confirm('Approve this booking?')">
              <i class="fas fa-check me-1"></i>Approve
            </button>
            <button name="action" value="rejected" class="btn btn-danger"
              onclick="return confirm('Reject this booking?')">
              <i class="fas fa-times me-1"></i>Reject
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right: User info -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header fw-semibold">Submitted By</div>
      <div class="card-body small">
        <p class="mb-1"><strong><?= e($booking['user_name']) ?></strong>
          <span class="badge bg-secondary ms-1"><?= ucfirst($booking['user_role']) ?></span></p>
        <p class="mb-1 text-muted"><?= e($booking['user_email']) ?></p>
        <?php if ($booking['id_number']): ?>
          <p class="mb-1"><i class="fas fa-id-card me-1 text-muted"></i><?= e($booking['id_number']) ?></p>
        <?php endif; ?>
        <?php if ($booking['department']): ?>
          <p class="mb-1"><i class="fas fa-building me-1 text-muted"></i><?= e($booking['department']) ?></p>
        <?php endif; ?>
        <?php if ($booking['contact_number']): ?>
          <p class="mb-0"><i class="fas fa-phone me-1 text-muted"></i><?= e($booking['contact_number']) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
