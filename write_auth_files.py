import os

BASE = r'C:\xampp\htdocs\Library-Facilities-Booking-System'

# FILE 1: includes/functions.php
f1 = r"""<?php
// Require login; optionally restrict to a role
function requireLogin($role = null) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/');
        exit;
    }
    if ($role && $_SESSION['role'] !== $role) {
        header('Location: ' . BASE_URL . '/' . $_SESSION['role'] . '/dashboard.php');
        exit;
    }
}

// Sanitize output
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Store a flash message
function flash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

// Show and clear flash message
function showFlash() {
    if (!isset($_SESSION['flash'])) return '';
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="alert alert-' . $f['type'] . ' alert-dismissible fade show" role="alert">'
         . e($f['msg'])
         . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// Color badge for booking/user status
function statusBadge($status) {
    $colors = [
        'pending'   => 'warning text-dark',
        'approved'  => 'success',
        'rejected'  => 'danger',
        'cancelled' => 'secondary',
        'active'    => 'success',
        'inactive'  => 'secondary',
        'suspended' => 'danger',
    ];
    $c = $colors[$status] ?? 'secondary';
    return '<span class="badge bg-' . $c . '">' . ucfirst($status) . '</span>';
}

// Check if a time slot is free
function slotAvailable($pdo, $facilityId, $date, $start, $end, $excludeId = 0) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM bookings
         WHERE facility_id = ? AND booking_date = ?
           AND status IN ("pending","approved") AND id != ?
           AND NOT (end_time <= ? OR start_time >= ?)'
    );
    $stmt->execute([$facilityId, $date, $excludeId, $start, $end]);
    return $stmt->fetchColumn() == 0;
}

// Upload a request letter file
function uploadLetter($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = ['pdf','doc','docx','jpg','jpeg','png'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed) || $file['size'] > 5*1024*1024) return null;
    $name = 'letter_' . uniqid() . '.' . $ext;
    move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name);
    return $name;
}

// Facility type labels
function facilityLabel($type) {
    $map = [
        'study_room'      => 'Study Room',
        'conference_room' => 'Conference Room',
        'computer_lab'    => 'Computer Lab',
        'reading_hall'    => 'Reading Hall',
        'laboratory'      => 'Laboratory',
        'auditorium'      => 'Auditorium',
        'equipment'       => 'Equipment',
        'other'           => 'Other',
    ];
    return $map[$type] ?? ucfirst(str_replace('_', ' ', $type));
}
"""

# FILE 2: auth/logout.php
f2 = r"""<?php
require_once __DIR__ . '/../config/config.php';

// Proper session teardown
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header('Location: ' . BASE_URL . '/');
exit;
"""

# FILE 3: auth/login.php
f3 = r"""<?php
require_once __DIR__ . '/../config/config.php';

// Login form lives on index.php — redirect there
header('Location: ' . BASE_URL . '/');
exit;
"""

# FILE 4: auth/register.php
f4 = r"""<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

$errors = [];
$input  = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
"""

# FILE 5: auth/forgot_password.php
f5 = r"""<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

$step      = 1;
$error     = '';
$success   = '';
$token     = '';
$resetLink = '';

// Step 1: submit email → generate token
if (isset($_POST['step1'])) {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND status = "active"');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            $error = 'No active account found with that email.';
        } else {
            $token  = bin2hex(random_bytes(20));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $pdo->prepare('UPDATE users SET reset_token=?, reset_token_expiry=? WHERE id=?')
                ->execute([$token, $expiry, $user['id']]);
            $resetLink = BASE_URL . '/auth/forgot_password.php?token=' . $token;
            $step = 2;
        }
    }
}

// Step 2: open via token link
if (!empty($_GET['token']) && !isset($_POST['step1'])) {
    $token = preg_replace('/[^a-f0-9]/', '', $_GET['token']);
    $stmt  = $pdo->prepare('SELECT id FROM users WHERE reset_token=? AND reset_token_expiry>NOW()');
    $stmt->execute([$token]);
    if (!$stmt->fetch()) {
        $error = 'This reset link is invalid or has expired.';
        $step  = 1;
    } else {
        $step = 3;
    }
}

// Step 3: save new password
if (isset($_POST['step3'])) {
    $token    = preg_replace('/[^a-f0-9]/', '', $_POST['token'] ?? '');
    $newPass  = $_POST['password'] ?? '';
    $confPass = $_POST['confirm']  ?? '';
    $stmt = $pdo->prepare('SELECT id FROM users WHERE reset_token=? AND reset_token_expiry>NOW()');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user)                     { $error = 'Invalid or expired link.';              $step = 1; }
    elseif (strlen($newPass) < 8)   { $error = 'Password must be at least 8 characters.'; $step = 3; }
    elseif ($newPass !== $confPass) { $error = 'Passwords do not match.';               $step = 3; }
    else {
        $pdo->prepare('UPDATE users SET password=?, reset_token=NULL, reset_token_expiry=NULL WHERE id=?')
            ->execute([password_hash($newPass, PASSWORD_BCRYPT), $user['id']]);
        $success = 'Password reset successfully! You can now sign in.';
        $step = 4;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password – <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css">
</head>
<body>

<div class="auth-page">
  <div class="auth-bg"></div>
  <div class="auth-overlay"></div>

  <div class="auth-container fade-in-up" style="max-width:460px">
    <div class="auth-logo">
      <i class="fas fa-book-open"></i>
      <span><?= APP_SHORT ?></span>
    </div>

    <div class="auth-card">

      <?php if ($step === 1): ?>
      <div class="auth-card-header">
        <div class="auth-icon-circle"><i class="fas fa-key"></i></div>
        <h1>Forgot Password?</h1>
        <p>Enter your email to get a reset link</p>
      </div>

      <?php if ($error): ?>
      <div class="auth-alert auth-alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" class="auth-form">
        <div class="form-group full">
          <label>Email Address</label>
          <div class="input-wrap">
            <i class="fas fa-envelope"></i>
            <input type="email" name="email" placeholder="your@email.com" required autofocus>
          </div>
        </div>
        <button type="submit" name="step1" class="btn-auth">
          <span>Send Reset Link</span>
          <i class="fas fa-paper-plane"></i>
        </button>
      </form>

      <?php elseif ($step === 2): ?>
      <div class="auth-card-header">
        <div class="auth-icon-circle" style="background:rgba(16,185,129,0.15);color:#10b981"><i class="fas fa-envelope-open-text"></i></div>
        <h1>Reset Link Ready</h1>
        <p>In production this would be sent via email</p>
      </div>

      <div class="auth-alert auth-alert-info">
        <i class="fas fa-info-circle"></i>
        <strong>Dev mode:</strong> Click the button below to continue the reset flow.
      </div>

      <div class="reset-link-box">
        <span class="reset-link-label">Reset Link</span>
        <p class="reset-link-url"><?= e($resetLink) ?></p>
      </div>

      <a href="<?= e($resetLink) ?>" class="btn-auth" style="display:flex">
        <span>Continue to Reset</span>
        <i class="fas fa-arrow-right"></i>
      </a>

      <?php elseif ($step === 3): ?>
      <div class="auth-card-header">
        <div class="auth-icon-circle"><i class="fas fa-lock-open"></i></div>
        <h1>Set New Password</h1>
        <p>Choose a strong password</p>
      </div>

      <?php if ($error): ?>
      <div class="auth-alert auth-alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" class="auth-form">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="form-group full">
          <label>New Password</label>
          <div class="input-wrap">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" id="pw1" placeholder="Min. 8 characters" required autofocus>
            <button type="button" class="toggle-pw" onclick="toggleVis('pw1',this)"><i class="fas fa-eye"></i></button>
          </div>
        </div>
        <div class="form-group full">
          <label>Confirm New Password</label>
          <div class="input-wrap">
            <i class="fas fa-lock"></i>
            <input type="password" name="confirm" id="pw2" placeholder="Repeat password" required>
            <button type="button" class="toggle-pw" onclick="toggleVis('pw2',this)"><i class="fas fa-eye"></i></button>
          </div>
        </div>
        <button type="submit" name="step3" class="btn-auth">
          <span>Reset Password</span>
          <i class="fas fa-check"></i>
        </button>
      </form>

      <?php elseif ($step === 4): ?>
      <div class="auth-card-header">
        <div class="auth-icon-circle" style="background:rgba(16,185,129,0.15);color:#10b981">
          <i class="fas fa-check-circle"></i>
        </div>
        <h1>All Done!</h1>
        <p><?= e($success) ?></p>
      </div>
      <a href="<?= BASE_URL ?>/" class="btn-auth" style="display:flex;margin-top:1.5rem">
        <span>Back to Sign In</span>
        <i class="fas fa-arrow-right"></i>
      </a>
      <?php endif; ?>

      <?php if ($step !== 4): ?>
      <div class="auth-footer">
        <a href="<?= BASE_URL ?>/">← Back to Sign In</a>
      </div>
      <?php endif; ?>

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
</script>
</body>
</html>
"""

# FILE 6: assets/css/auth.css
f6 = """/* =============================================
   AUTH PAGES — auth.css
   Register & Forgot Password
   ============================================= */

:root {
  --primary:    #1a56db;
  --primary-dk: #1048c4;
  --accent:     #f59e0b;
  --glass-bg:   rgba(255,255,255,0.1);
  --glass-border: rgba(255,255,255,0.22);
  --radius: 16px;
  --transition: 0.3s ease;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body {
  font-family: 'Inter', sans-serif;
  min-height: 100vh;
  overflow-x: hidden;
}

/* ── Background ── */
.auth-page {
  position: relative;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1rem;
}

.auth-bg {
  position: fixed;
  inset: 0;
  background-image: url('https://images.unsplash.com/photo-1507842217343-583bb7270b66?w=1920&q=80');
  background-size: cover;
  background-position: center;
  z-index: 0;
}

.auth-overlay {
  position: fixed;
  inset: 0;
  background: linear-gradient(135deg, rgba(8,20,60,0.85) 0%, rgba(20,50,110,0.75) 100%);
  z-index: 1;
}

/* ── Container ── */
.auth-container {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 580px;
}

/* ── Logo ── */
.auth-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  color: #fff;
  font-weight: 800;
  font-size: 1.15rem;
  margin-bottom: 1.25rem;
  letter-spacing: -0.3px;
}

.auth-logo i { color: var(--accent); font-size: 1.4rem; }

/* ── Card ── */
.auth-card {
  background: rgba(255,255,255,0.1);
  backdrop-filter: blur(22px);
  -webkit-backdrop-filter: blur(22px);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: var(--radius);
  padding: 2.5rem 2.25rem;
  box-shadow: 0 24px 64px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.18);
  color: #fff;
}

/* ── Card Header ── */
.auth-card-header {
  text-align: center;
  margin-bottom: 2rem;
}

.auth-icon-circle {
  width: 64px; height: 64px;
  background: rgba(26,86,219,0.2);
  border: 1px solid rgba(26,86,219,0.35);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  color: #60a5fa;
  margin: 0 auto 1rem;
}

.auth-card-header h1 {
  font-size: 1.65rem;
  font-weight: 800;
  color: #fff;
  margin-bottom: 0.3rem;
  letter-spacing: -0.5px;
}

.auth-card-header p {
  font-size: 0.88rem;
  color: rgba(255,255,255,0.6);
}

/* ── Alerts ── */
.auth-alert {
  display: flex;
  align-items: flex-start;
  gap: 0.6rem;
  border-radius: 10px;
  padding: 0.8rem 1rem;
  font-size: 0.86rem;
  margin-bottom: 1.25rem;
  line-height: 1.5;
}

.auth-alert i { margin-top: 2px; flex-shrink: 0; }

.auth-alert ul { margin: 0.25rem 0 0 1rem; padding: 0; }
.auth-alert ul li { margin-bottom: 2px; }

.auth-alert-danger {
  background: rgba(239,68,68,0.18);
  border: 1px solid rgba(239,68,68,0.4);
  color: #fca5a5;
}

.auth-alert-info {
  background: rgba(96,165,250,0.15);
  border: 1px solid rgba(96,165,250,0.3);
  color: #bfdbfe;
}

/* ── Role Selector ── */
.role-selector {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}

.role-option {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.4rem;
  padding: 0.9rem 0.5rem;
  border: 1px solid rgba(255,255,255,0.18);
  border-radius: 12px;
  cursor: pointer;
  transition: border-color var(--transition), background var(--transition);
  background: rgba(255,255,255,0.06);
  color: rgba(255,255,255,0.7);
  font-size: 0.88rem;
  font-weight: 500;
  user-select: none;
}

.role-option input { display: none; }

.role-option i {
  font-size: 1.4rem;
  color: rgba(255,255,255,0.5);
  transition: color var(--transition);
}

.role-option.active, .role-option:hover {
  border-color: rgba(96,165,250,0.6);
  background: rgba(96,165,250,0.12);
  color: #fff;
}

.role-option.active i { color: #60a5fa; }

/* ── Form ── */
.auth-form { display: flex; flex-direction: column; gap: 1rem; }

.form-row { display: flex; flex-direction: column; gap: 1rem; }
.form-row.two-col { flex-direction: row; gap: 0.85rem; }
.form-row.two-col .form-group { flex: 1; min-width: 0; }

.form-group { display: flex; flex-direction: column; gap: 0.35rem; }

.form-group label {
  font-size: 0.8rem;
  font-weight: 600;
  color: rgba(255,255,255,0.75);
  letter-spacing: 0.3px;
}

.input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.input-wrap > i {
  position: absolute;
  left: 0.85rem;
  font-size: 0.85rem;
  color: rgba(255,255,255,0.4);
  pointer-events: none;
}

.input-wrap input {
  width: 100%;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.16);
  border-radius: 10px;
  padding: 0.7rem 2.5rem 0.7rem 2.4rem;
  font-size: 0.9rem;
  color: #fff;
  font-family: 'Inter', sans-serif;
  outline: none;
  transition: border-color var(--transition), background var(--transition), box-shadow var(--transition);
}

.input-wrap input::placeholder { color: rgba(255,255,255,0.3); }

.input-wrap input:focus {
  border-color: rgba(96,165,250,0.65);
  background: rgba(255,255,255,0.12);
  box-shadow: 0 0 0 3px rgba(96,165,250,0.15);
}

.toggle-pw {
  position: absolute;
  right: 0.7rem;
  background: none;
  border: none;
  color: rgba(255,255,255,0.4);
  cursor: pointer;
  font-size: 0.88rem;
  padding: 0.25rem;
  transition: color var(--transition);
}
.toggle-pw:hover { color: rgba(255,255,255,0.8); }

/* ── Submit button ── */
.btn-auth {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.6rem;
  width: 100%;
  padding: 0.85rem;
  background: linear-gradient(135deg, var(--primary), var(--primary-dk));
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 0.96rem;
  font-weight: 600;
  cursor: pointer;
  margin-top: 0.5rem;
  text-decoration: none;
  font-family: 'Inter', sans-serif;
  letter-spacing: 0.2px;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  box-shadow: 0 4px 20px rgba(26,86,219,0.45);
}

.btn-auth:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(26,86,219,0.55);
  color: #fff;
}
.btn-auth:active { transform: translateY(0); }

/* ── Footer ── */
.auth-footer {
  text-align: center;
  margin-top: 1.5rem;
  font-size: 0.85rem;
  color: rgba(255,255,255,0.55);
}

.auth-footer a {
  color: #93c5fd;
  text-decoration: none;
  font-weight: 500;
  transition: color var(--transition);
}
.auth-footer a:hover { color: #fff; }

/* ── Reset link box ── */
.reset-link-box {
  background: rgba(255,255,255,0.07);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 10px;
  padding: 0.85rem 1rem;
  margin-bottom: 1.25rem;
}
.reset-link-label { font-size: 0.75rem; color: rgba(255,255,255,0.5); display: block; margin-bottom: 0.3rem; }
.reset-link-url { font-size: 0.78rem; color: #93c5fd; word-break: break-all; line-height: 1.5; }

/* ── Animation ── */
.fade-in-up {
  animation: fadeInUp 0.65s ease both;
}

@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(24px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Responsive ── */
@media (max-width: 520px) {
  .auth-card { padding: 2rem 1.25rem; }
  .form-row.two-col { flex-direction: column; }
  .auth-card-header h1 { font-size: 1.4rem; }
}
"""

paths_contents = [
    (os.path.join(BASE, 'includes', 'functions.php'), f1),
    (os.path.join(BASE, 'auth', 'logout.php'),        f2),
    (os.path.join(BASE, 'auth', 'login.php'),         f3),
    (os.path.join(BASE, 'auth', 'register.php'),      f4),
    (os.path.join(BASE, 'auth', 'forgot_password.php'), f5),
    (os.path.join(BASE, 'assets', 'css', 'auth.css'), f6),
]

for path, content in paths_contents:
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8') as fh:
        fh.write(content)
    print(f'  OK  {path}  ({os.path.getsize(path)} bytes)')

print('Done.')
