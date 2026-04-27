<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

$errors = [];
$input  = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name    = trim($input['name']       ?? '');
    $email   = trim($input['email']      ?? '');
    $pass    = $input['password']        ?? '';
    $pass2   = $input['confirm_pass']    ?? '';
    $role    = $input['role']            ?? 'student';
    $id_num  = trim($input['id_number']  ?? '');
    $dept    = trim($input['department'] ?? '');
    $contact = trim($input['contact']    ?? '');

    if (!$name)                                        $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = 'Enter a valid email address.';
    if (strlen($pass) < 8)                             $errors[] = 'Password must be at least 8 characters.';
    if ($pass !== $pass2)                              $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['student','faculty']))       $errors[] = 'Invalid account type.';
    if (!$id_num)                                      $errors[] = 'ID number is required.';
    if (!$dept)                                        $errors[] = 'Department is required.';

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR id_number = ?');
        $stmt->execute([$email, $id_num]);
        if ($stmt->fetch()) $errors[] = 'Email or ID number is already registered.';
    }

    if (empty($errors)) {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $pdo->prepare('INSERT INTO users (name,email,password,role,id_number,department,contact_number,status)
                       VALUES (?,?,?,?,?,?,?,\'active\')')
            ->execute([$name, $email, $hash, $role, $id_num, $dept, $contact]);
        flash('success', 'Account created! Please sign in.');
        header('Location: ' . BASE_URL . '/');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register – <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css">
</head>
<body>

<div class="auth-page">
  <div class="auth-bg"></div>
  <div class="auth-overlay"></div>

  <div class="auth-container fade-in-up">
    <div class="auth-logo">
      <i class="fas fa-book-open"></i>
      <span><?= APP_SHORT ?></span>
    </div>

    <div class="auth-card">
      <div class="auth-card-header">
        <h1>Create Account</h1>
        <p>Join the Library Booking System</p>
      </div>

      <?php if ($errors): ?>
      <div class="auth-alert auth-alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <ul>
          <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <form method="POST" class="auth-form" novalidate>
        <?= csrfField() ?>

        <!-- Role selector -->
        <div class="role-selector">
          <label class="role-option <?= ($input['role'] ?? 'student') === 'student' ? 'active' : '' ?>">
            <input type="radio" name="role" value="student" <?= ($input['role'] ?? 'student') === 'student' ? 'checked' : '' ?>>
            <i class="fas fa-user-graduate"></i>
            <span>Student</span>
          </label>
          <label class="role-option <?= ($input['role'] ?? '') === 'faculty' ? 'active' : '' ?>">
            <input type="radio" name="role" value="faculty" <?= ($input['role'] ?? '') === 'faculty' ? 'checked' : '' ?>>
            <i class="fas fa-chalkboard-teacher"></i>
            <span>Faculty</span>
          </label>
        </div>

        <div class="form-row">
          <div class="form-group full">
            <label>Full Name *</label>
            <div class="input-wrap">
              <i class="fas fa-user"></i>
              <input type="text" name="name" placeholder="Your full name" value="<?= e($input['name'] ?? '') ?>" required>
            </div>
          </div>
        </div>

        <div class="form-row two-col">
          <div class="form-group">
            <label>Email *</label>
            <div class="input-wrap">
              <i class="fas fa-envelope"></i>
              <input type="email" name="email" placeholder="your@email.com" value="<?= e($input['email'] ?? '') ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>ID Number *</label>
            <div class="input-wrap">
              <i class="fas fa-id-card"></i>
              <input type="text" name="id_number" placeholder="e.g. 2024-00001" value="<?= e($input['id_number'] ?? '') ?>" required>
            </div>
          </div>
        </div>

        <div class="form-row two-col">
          <div class="form-group">
            <label>Department *</label>
            <div class="input-wrap">
              <i class="fas fa-building"></i>
              <input type="text" name="department" placeholder="e.g. BSIT" value="<?= e($input['department'] ?? '') ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Contact Number</label>
            <div class="input-wrap">
              <i class="fas fa-phone"></i>
              <input type="text" name="contact" placeholder="09XX-XXX-XXXX" value="<?= e($input['contact'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="form-row two-col">
          <div class="form-group">
            <label>Password *</label>
            <div class="input-wrap">
              <i class="fas fa-lock"></i>
              <input type="password" name="password" id="pw1" placeholder="Min. 8 characters" required>
              <button type="button" class="toggle-pw" onclick="toggleVis('pw1',this)"><i class="fas fa-eye"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label>Confirm Password *</label>
            <div class="input-wrap">
              <i class="fas fa-lock"></i>
              <input type="password" name="confirm_pass" id="pw2" placeholder="Repeat password" required>
              <button type="button" class="toggle-pw" onclick="toggleVis('pw2',this)"><i class="fas fa-eye"></i></button>
            </div>
          </div>
        </div>

        <button type="submit" class="btn-auth">
          <span>Create Account</span>
          <i class="fas fa-arrow-right"></i>
        </button>
      </form>

      <div class="auth-footer">
        Already have an account? <a href="<?= BASE_URL ?>/">Sign in here</a>
      </div>
    </div>
  </div>
</div>

<script>
function toggleVis(id, btn) {
  var inp = document.getElementById(id);
  var ic  = btn.querySelector('i');
  if (inp.type === 'password') { inp.type = 'text';     ic.className = 'fas fa-eye-slash'; }
  else                         { inp.type = 'password'; ic.className = 'fas fa-eye';       }
}

// Highlight role option on click
document.querySelectorAll('.role-option input').forEach(function(radio) {
  radio.addEventListener('change', function() {
    document.querySelectorAll('.role-option').forEach(function(el) { el.classList.remove('active'); });
    this.closest('.role-option').classList.add('active');
  });
});
</script>
</body>
</html>
