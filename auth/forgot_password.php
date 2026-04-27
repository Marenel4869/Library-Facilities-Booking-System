<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

$step      = 1;
$error     = '';
$success   = '';
$token     = '';
$resetLink = '';

// Step 1: submit email → generate token, store in password_resets table
if (isset($_POST['step1'])) {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND status = "active"');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            // Vague message prevents email enumeration
            $error = 'If that email is registered, a reset link will appear below.';
            $step  = 2; $resetLink = '';
        } else {
            $token  = bin2hex(random_bytes(20));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            // Invalidate any prior unused tokens for this email
            $pdo->prepare('UPDATE password_resets SET used=1 WHERE email=? AND used=0')
                ->execute([$email]);
            $pdo->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (?,?,?)')
                ->execute([$email, $token, $expiry]);
            $resetLink = BASE_URL . '/auth/forgot_password.php?token=' . $token;
            $step = 2;
        }
    }
}

// Step 2: open via token link
if (!empty($_GET['token']) && !isset($_POST['step1'])) {
    $token = preg_replace('/[^a-f0-9]/', '', $_GET['token']);
    $stmt  = $pdo->prepare(
        'SELECT pr.id FROM password_resets pr
         WHERE pr.token=? AND pr.expires_at>NOW() AND pr.used=0'
    );
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
    verifyCsrf();
    $token    = preg_replace('/[^a-f0-9]/', '', $_POST['token'] ?? '');
    $newPass  = $_POST['password'] ?? '';
    $confPass = $_POST['confirm']  ?? '';

    $stmt = $pdo->prepare(
        'SELECT pr.id, u.id AS user_id FROM password_resets pr
         JOIN users u ON u.email=pr.email
         WHERE pr.token=? AND pr.expires_at>NOW() AND pr.used=0'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row)                      { $error = 'Invalid or expired link.';               $step = 1; }
    elseif (strlen($newPass) < 8)   { $error = 'Password must be at least 8 characters.'; $step = 3; }
    elseif ($newPass !== $confPass) { $error = 'Passwords do not match.';                $step = 3; }
    else {
        $pdo->prepare('UPDATE users SET password=? WHERE id=?')
            ->execute([password_hash($newPass, PASSWORD_DEFAULT), $row['user_id']]);
        $pdo->prepare('UPDATE password_resets SET used=1 WHERE token=?')
            ->execute([$token]);
        logAudit($pdo, 'password_reset', "User ID {$row['user_id']} reset their password");
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
        <?= csrfField() ?>
        <div class="form-group full">
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
        <?= csrfField() ?>
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
