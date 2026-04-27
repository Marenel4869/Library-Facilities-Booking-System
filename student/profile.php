<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('student');

$uid    = $_SESSION['user_id'];
$errors = [];

$user = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$user->execute([$uid]);
$user = $user->fetch();

// Update profile
if (isset($_POST['update_profile'])) {
    $name    = trim($_POST['name']    ?? '');
    $dept    = trim($_POST['department']     ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    if (!$name) $errors[] = 'Name is required.';
    if (empty($errors)) {
        $pdo->prepare('UPDATE users SET name=?, department=?, contact_number=? WHERE id=?')
            ->execute([$name, $dept, $contact, $uid]);
        $_SESSION['name'] = $name;
        flash('success', 'Profile updated.');
        header('Location: ' . BASE_URL . '/student/profile.php'); exit;
    }
}

// Change password
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
        header('Location: ' . BASE_URL . '/student/profile.php'); exit;
    }
}

// Reload user after changes
$userRow = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$userRow->execute([$uid]);
$user = $userRow->fetch();

$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container py-3">

<?= showFlash() ?>
<h4 class="mb-4">My Profile</h4>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-md-4">
    <div class="card text-center p-4">
      <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width:70px;height:70px;font-size:1.8rem">
        <?= strtoupper(mb_substr($user['name'],0,1)) ?>
      </div>
      <h5><?= e($user['name']) ?></h5>
      <p class="text-muted small mb-1"><?= e($user['email']) ?></p>
      <span class="badge bg-primary"><?= ucfirst($user['role']) ?></span>
      <div class="text-start mt-3 small text-muted">
        <p><i class="fas fa-id-card me-2"></i><?= e($user['id_number'] ?? '–') ?></p>
        <p><i class="fas fa-building me-2"></i><?= e($user['department'] ?? '–') ?></p>
        <p class="mb-0"><i class="fas fa-phone me-2"></i><?= e($user['contact_number'] ?? '–') ?></p>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <!-- Edit profile -->
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
    <!-- Change password -->
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
