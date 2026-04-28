<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

try {
    ensureContentTables($pdo);
} catch (PDOException $e) {
    // If DB is misconfigured, we still show the contact info.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($subject === '' || $message === '') {
        flash('warning', 'Please enter a subject and message.');
        header('Location: ' . BASE_URL . '/help.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO help_requests (user_id, role, subject, message, status) VALUES (?,?,?,?,"open")');
        $stmt->execute([
            (int)($_SESSION['user_id'] ?? 0),
            (string)($_SESSION['role'] ?? ''),
            $subject,
            $message,
        ]);
        flash('success', 'Your message was sent. The admin will review it soon.');
    } catch (PDOException $e) {
        flash('danger', 'Unable to submit your request right now.');
    }

    header('Location: ' . BASE_URL . '/help.php');
    exit;
}

$pageTitle = 'Need Help';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main><div class="container-fluid py-3 px-3 px-lg-4">

<?= showFlash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0">Need Help</h4>
  <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/announcements.php">
    <i class="fas fa-bullhorn me-1"></i>Announcements
  </a>
</div>

<div class="card">
  <div class="card-body">

    <div class="row g-3">
      <div class="col-lg-5">
        <div class="p-3 soft-panel">
          <div class="fw-bold mb-2">Library Contact</div>
          <div class="small text-muted">Email</div>
          <?php if (($_SESSION['role'] ?? '') === 'faculty'): ?>
            <div class="help-mini mb-1"><a href="mailto:morelos.library@urios.edu.ph">morelos.library@urios.edu.ph</a></div>
          <?php endif; ?>
          <div class="help-mini mb-2"><a href="mailto:library.helpdesk@urios.edu.ph">library.helpdesk@urios.edu.ph</a></div>
          <div class="small text-muted">Office Hours</div>
          <div class="help-mini">Mon–Sat · 7:00 AM – 5:00 PM</div>
          <hr class="my-3 hr-soft">
          <div class="text-muted small">Tip: Include the facility name, date/time, and a screenshot (if possible).</div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-bold">Report an Issue</div>
          <span class="text-muted small">We usually respond within office hours.</span>
        </div>

        <form method="post" class="d-grid gap-2">
          <?= csrfField() ?>
          <div>
            <label class="form-label small fw-semibold">Subject</label>
            <input class="form-control" name="subject" maxlength="255" placeholder="e.g., Booking error, page not loading" required>
          </div>
          <div>
            <label class="form-label small fw-semibold">Message</label>
            <textarea class="form-control" name="message" rows="5" placeholder="Describe what happened and what you were trying to do..." required></textarea>
          </div>
          <button class="btn btn-primary">
            <i class="fas fa-paper-plane me-1"></i>Send
          </button>
        </form>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
