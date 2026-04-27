<?php
require_once __DIR__ . '/../includes/bootstrap.php';
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
<main><div class="container-fluid py-3 px-3 px-lg-4 admin-page">

<?= showFlash() ?>

<div class="admin-hero mb-3">
  <div class="d-flex align-items-center gap-3">
    <div class="admin-hero-icon"><i class="fas fa-user-cog"></i></div>
    <div>
      <h4 class="mb-0 fw-bold text-white">Profile</h4>
      <div class="admin-hero-sub">Update your details and change password</div>
    </div>
  </div>
</div>

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
        <p class="mb-0"><i class="fas fa-phone me-2"></i><?= e($user['contact_number'] ?? '–') ?></p>
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
