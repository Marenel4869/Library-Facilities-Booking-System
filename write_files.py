import os

base = r'C:\xampp\htdocs\Library-Facilities-Booking-System'

# FILE 1: index.php
index_content = r"""<?php
require_once __DIR__ . '/config/config.php';
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/' . $_SESSION['role'] . '/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand fw-bold" href="#"><i class="fas fa-book-open me-1"></i>LFBS</a>
    <div>
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline-light btn-sm me-2">Login</a>
      <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-light btn-sm">Register</a>
    </div>
  </div>
</nav>

<div class="py-5" style="background:linear-gradient(135deg,#0d6efd,#0056b3); color:#fff;">
  <div class="container text-center py-4">
    <h1 class="fw-bold display-5">Library Facilities Booking System</h1>
    <p class="lead mt-3 mb-4">Book study rooms, conference halls, laboratories and more — all in one place.</p>
    <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-light btn-lg me-3">Get Started</a>
    <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline-light btn-lg">Login</a>
  </div>
</div>

<div class="container py-5">
  <div class="row g-4 text-center">
    <div class="col-md-4">
      <div class="card p-4 h-100">
        <i class="fas fa-door-open fa-3x text-primary mb-3"></i>
        <h5>Study Rooms</h5>
        <p class="text-muted">Book private or group study rooms with instant availability checking.</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card p-4 h-100">
        <i class="fas fa-users fa-3x text-primary mb-3"></i>
        <h5>Conference Rooms</h5>
        <p class="text-muted">Reserve fully-equipped conference rooms for meetings and seminars.</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card p-4 h-100">
        <i class="fas fa-flask fa-3x text-primary mb-3"></i>
        <h5>Laboratories</h5>
        <p class="text-muted">Schedule computer labs and research facilities easily.</p>
      </div>
    </div>
  </div>
</div>

<footer class="bg-white border-top py-3 mt-4">
  <div class="container text-center text-muted small">
    &copy; <?= date('Y') ?> <?= APP_NAME ?>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
"""

# FILE 2: auth/login.php
login_content = r"""<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!$email || !$pass) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['password'])) {
            $error = 'Invalid email or password.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account is ' . $user['status'] . '. Contact admin.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];
            flash('success', 'Welcome back, ' . $user['name'] . '!');
            header('Location: ' . BASE_URL . '/' . $user['role'] . '/dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="auth-box" style="max-width:420px;margin:60px auto;padding:0 15px">
  <div class="card shadow-sm">
    <div class="card-header bg-primary text-white text-center py-3">
      <h5 class="mb-0"><i class="fas fa-book-open me-2"></i><?= APP_SHORT ?></h5>
      <small>Sign in to your account</small>
    </div>
    <div class="card-body p-4">
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label fw-semibold">Email</label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Password</label>
          <input type="password" name="password" class="form-control" required>
          <div class="text-end mt-1">
            <a href="<?= BASE_URL ?>/auth/forgot_password.php" class="small">Forgot password?</a>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-100">Login</button>
      </form>
    </div>
    <div class="card-footer text-center small">
      No account? <a href="<?= BASE_URL ?>/auth/register.php">Register here</a>
      &nbsp;·&nbsp;
      <a href="<?= BASE_URL ?>">Home</a>
    </div>
  </div>
</div>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
"""

# FILE 3: auth/register.php
register_content = r"""<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

$errors = [];
$input  = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($input['name']    ?? '');
    $email   = trim($input['email']   ?? '');
    $pass    = $input['password']     ?? '';
    $pass2   = $input['confirm_pass'] ?? '';
    $role    = $input['role']         ?? 'student';
    $id_num  = trim($input['id_number']   ?? '');
    $dept    = trim($input['department']  ?? '');
    $contact = trim($input['contact']     ?? '');

    if (!$name)   $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pass !== $pass2) $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['student','faculty'])) $errors[] = 'Invalid role.';
    if (!$id_num) $errors[] = 'ID number is required.';
    if (!$dept)   $errors[] = 'Department is required.';

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR id_number = ?');
        $stmt->execute([$email, $id_num]);
        if ($stmt->fetch()) {
            $errors[] = 'Email or ID number already registered.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $pdo->prepare('INSERT INTO users (name,email,password,role,id_number,department,contact_number,status) VALUES (?,?,?,?,?,?,?,\'active\')')
            ->execute([$name, $email, $hash, $role, $id_num, $dept, $contact]);
        flash('success', 'Registration successful! Please log in.');
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div style="max-width:520px;margin:40px auto;padding:0 15px">
  <div class="card shadow-sm">
    <div class="card-header bg-primary text-white text-center py-3">
      <h5 class="mb-0">Create Account</h5>
      <small><?= APP_NAME ?></small>
    </div>
    <div class="card-body p-4">
      <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label fw-semibold">Account Type</label>
          <div class="d-flex gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="role" value="student" id="r_student" <?= ($input['role']??'student')==='student'?'checked':'' ?>>
              <label class="form-check-label" for="r_student">Student</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="role" value="faculty" id="r_faculty" <?= ($input['role']??'')==='faculty'?'checked':'' ?>>
              <label class="form-check-label" for="r_faculty">Faculty</label>
            </div>
          </div>
        </div>
        <div class="row g-2">
          <div class="col-12">
            <label class="form-label fw-semibold">Full Name *</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($input['name']??'') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Email *</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($input['email']??'') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">ID Number *</label>
            <input type="text" name="id_number" class="form-control" value="<?= htmlspecialchars($input['id_number']??'') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Department *</label>
            <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($input['department']??'') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Contact Number</label>
            <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($input['contact']??'') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Password *</label>
            <input type="password" name="password" class="form-control" placeholder="Min. 8 chars" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Confirm Password *</label>
            <input type="password" name="confirm_pass" class="form-control" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 mt-3">Create Account</button>
      </form>
    </div>
    <div class="card-footer text-center small">
      Already registered? <a href="<?= BASE_URL ?>/auth/login.php">Login here</a>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
"""

# FILE 4: auth/logout.php
logout_content = r"""<?php
require_once __DIR__ . '/../config/config.php';
session_destroy();
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
"""

# FILE 5: auth/forgot_password.php
forgot_content = r"""<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

$step    = 1;
$error   = '';
$success = '';
$token   = '';
$resetLink = '';

// Step 1: submit email
if (isset($_POST['step1'])) {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email.';
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
    $stmt  = $pdo->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()');
    $stmt->execute([$token]);
    if (!$stmt->fetch()) {
        $error = 'This link is invalid or expired.';
    } else {
        $step = 3;
    }
}

// Step 3: save new password
if (isset($_POST['step3'])) {
    $token   = preg_replace('/[^a-f0-9]/', '', $_POST['token'] ?? '');
    $newPass = $_POST['password'] ?? '';
    $confPass = $_POST['confirm']  ?? '';
    $stmt = $pdo->prepare('SELECT id FROM users WHERE reset_token=? AND reset_token_expiry>NOW()');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user)                    { $error = 'Invalid or expired link.'; $step = 1; }
    elseif (strlen($newPass) < 8)  { $error = 'Password must be at least 8 characters.'; $step = 3; }
    elseif ($newPass !== $confPass) { $error = 'Passwords do not match.'; $step = 3; }
    else {
        $pdo->prepare('UPDATE users SET password=?, reset_token=NULL, reset_token_expiry=NULL WHERE id=?')
            ->execute([password_hash($newPass, PASSWORD_BCRYPT), $user['id']]);
        $success = 'Password changed! You can now log in.';
        $step = 4;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div style="max-width:420px;margin:60px auto;padding:0 15px">
  <div class="card shadow-sm">
    <div class="card-header bg-primary text-white text-center py-3">
      <h5 class="mb-0">Reset Password</h5>
    </div>
    <div class="card-body p-4">
      <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

      <?php if ($step === 1): ?>
        <form method="POST">
          <div class="mb-3">
            <label class="form-label fw-semibold">Your Email</label>
            <input type="email" name="email" class="form-control" required autofocus>
          </div>
          <button type="submit" name="step1" class="btn btn-primary w-100">Get Reset Link</button>
        </form>

      <?php elseif ($step === 2): ?>
        <div class="alert alert-info small">
          <strong>Dev mode:</strong> In production this would be emailed. For now, use the link below:
        </div>
        <p class="text-break small"><a href="<?= htmlspecialchars($resetLink) ?>"><?= htmlspecialchars($resetLink) ?></a></p>
        <a href="<?= htmlspecialchars($resetLink) ?>" class="btn btn-primary w-100">Continue</a>

      <?php elseif ($step === 3): ?>
        <form method="POST">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <div class="mb-3">
            <label class="form-label fw-semibold">New Password</label>
            <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Confirm Password</label>
            <input type="password" name="confirm" class="form-control" required>
          </div>
          <button type="submit" name="step3" class="btn btn-primary w-100">Set New Password</button>
        </form>

      <?php elseif ($step === 4): ?>
        <div class="text-center">
          <i class="fas fa-check-circle text-success" style="font-size:3rem"></i>
          <p class="mt-3">Password reset successful!</p>
          <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary">Go to Login</a>
        </div>
      <?php endif; ?>
    </div>
    <div class="card-footer text-center small">
      <a href="<?= BASE_URL ?>/auth/login.php">Back to Login</a>
    </div>
  </div>
</div>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
"""

file_map = {
    os.path.join(base, 'index.php'): index_content,
    os.path.join(base, 'auth', 'login.php'): login_content,
    os.path.join(base, 'auth', 'register.php'): register_content,
    os.path.join(base, 'auth', 'logout.php'): logout_content,
    os.path.join(base, 'auth', 'forgot_password.php'): forgot_content,
}

for path, content in file_map.items():
    # Strip leading newline from triple-quoted strings
    content = content.lstrip('\n')
    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)

# Verify
print("=== Verification ===")
for path in file_map:
    fname = os.path.basename(path)
    size = os.path.getsize(path)
    with open(path, 'r', encoding='utf-8') as f:
        lines = f.readlines()
    status = 'OK' if size > 0 else 'EMPTY/FAIL'
    print(f"\n[{status}] {fname} - {size} bytes")
    for i, line in enumerate(lines[:3], 1):
        print(f"  {i}: {line.rstrip()}")
