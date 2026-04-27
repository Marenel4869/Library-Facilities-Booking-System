<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

$errors  = [];
$timeout = isset($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';   // never trim passwords

    // Rate-limit check
    $lockMsg = checkLoginRateLimit($pdo);
    if ($lockMsg) {
        $errors[] = $lockMsg;
    } elseif (!$email || !$password) {
        $errors[] = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['status'] === 'suspended') {
            $errors[] = 'Your account has been suspended. Contact the administrator.';
        } elseif ($user && $user['status'] !== 'active') {
            $errors[] = 'Your account is inactive.';
        } elseif ($user && password_verify($password, $user['password'])) {
            clearLoginFailures($pdo);
            // Regenerate session ID on login to prevent session fixation
            session_regenerate_id(true);
            $_SESSION['user_id']      = $user['id'];
            $_SESSION['name']         = $user['name'];
            $_SESSION['role']         = $user['role'];
            $_SESSION['_last_active'] = time();
            logAudit($pdo, 'login', "User {$user['name']} (ID {$user['id']}) logged in");
            header('Location: ' . BASE_URL . '/' . $user['role'] . '/dashboard.php');
            exit;
        } else {
            recordLoginFailure($pdo);
            $errors[] = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Library Facilities Booking System</title>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- Landing CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/landing.css">
</head>
<body>

  <!-- ===== NAVBAR ===== -->
  <nav class="landing-nav" id="navbar">
    <div class="nav-container">
      <a href="#" class="nav-logo">
        <i class="fas fa-book-open"></i>
        <span>LibBook</span>
      </a>
      <ul class="nav-menu" id="navMenu">
        <li><a href="#hero" class="nav-link">Home</a></li>
        <li><a href="#about" class="nav-link">About</a></li>
        <li><a href="#contact" class="nav-link">Contact</a></li>
      </ul>
      <button class="hamburger" id="hamburger" aria-label="Toggle menu">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </div>
  </nav>

  <!-- ===== HERO ===== -->
  <section class="hero" id="hero">
    <div class="hero-overlay"></div>

    <div class="hero-content">
      <!-- Title block -->
      <div class="hero-text fade-in-up">
        <div class="hero-badge"><i class="fas fa-building-columns me-2"></i>University Library</div>
        <h1>Library Facilities<br><span>Booking System</span></h1>
        <p>Reserve spaces easily and efficiently.<br>Study rooms, labs, and more — all in one place.</p>
        <div class="hero-features">
          <span><i class="fas fa-check-circle"></i> Instant Booking</span>
          <span><i class="fas fa-check-circle"></i> Real-time Availability</span>
          <span><i class="fas fa-check-circle"></i> Easy Management</span>
        </div>
      </div>

      <!-- Login card -->
      <div class="login-card fade-in-up delay-1">
        <div class="login-card-header">
          <i class="fas fa-user-circle"></i>
          <h2>Sign In</h2>
          <p>Access your account</p>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert-error">
          <i class="fas fa-exclamation-circle"></i>
          <?= htmlspecialchars($errors[0]) ?>
        </div>
        <?php endif; ?>

        <?php if ($timeout): ?>
        <div class="alert-error" style="background:rgba(255,193,7,.15);border-color:#ffc107;color:#856404;">
          <i class="fas fa-clock"></i> Your session expired. Please log in again.
        </div>
        <?php endif; ?>

        <form method="POST" class="login-form" novalidate>
          <?= csrfField() ?>
          <div class="form-group">
            <label for="email">Email Address</label>
            <div class="input-wrap">
              <i class="fas fa-envelope"></i>
              <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your email"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                autocomplete="email"
                required>
            </div>
          </div>

          <div class="form-group">
            <div class="label-row">
              <label for="password">Password</label>
              <a href="<?= BASE_URL ?>/auth/forgot_password.php" class="forgot-link">Forgot password?</a>
            </div>
            <div class="input-wrap">
              <i class="fas fa-lock"></i>
              <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                required>
              <button type="button" class="toggle-pw" id="togglePw" aria-label="Show password">
                <i class="fas fa-eye" id="eyeIcon"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-login">
            <span>Sign In</span>
            <i class="fas fa-arrow-right"></i>
          </button>
        </form>

        <div class="login-footer">
          <p>Don't have an account? <a href="<?= BASE_URL ?>/auth/register.php">Register here</a></p>
        </div>
      </div>
    </div>

    <!-- Scroll indicator -->
    <a href="#about" class="scroll-down" aria-label="Scroll down">
      <i class="fas fa-chevron-down"></i>
    </a>
  </section>

  <!-- ===== ABOUT ===== -->
  <section class="section" id="about">
    <div class="section-container">
      <div class="section-header">
        <span class="section-tag">About</span>
        <h2>Everything You Need</h2>
        <p>A streamlined platform for booking library facilities on campus.</p>
      </div>
      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon" style="background:#e8f4fd;color:#0d6efd">
            <i class="fas fa-calendar-check"></i>
          </div>
          <h3>Easy Booking</h3>
          <p>Select your facility, pick a date and time, and submit — all in under a minute.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon" style="background:#e8fdf2;color:#198754">
            <i class="fas fa-clock"></i>
          </div>
          <h3>Real-time Status</h3>
          <p>Track your booking status from pending to approved instantly.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon" style="background:#fff8e1;color:#ffc107">
            <i class="fas fa-shield-halved"></i>
          </div>
          <h3>Secure &amp; Reliable</h3>
          <p>Role-based access ensures students, faculty, and admins each get the right tools.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon" style="background:#fce8fd;color:#6f42c1">
            <i class="fas fa-mobile-screen"></i>
          </div>
          <h3>Mobile Friendly</h3>
          <p>Access the system on any device — phone, tablet, or desktop.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== CONTACT ===== -->
  <section class="section section-alt" id="contact">
    <div class="section-container">
      <div class="section-header">
        <span class="section-tag">Contact</span>
        <h2>Get In Touch</h2>
        <p>Have questions about booking facilities? We're here to help.</p>
      </div>
      <div class="contact-grid">
        <div class="contact-item">
          <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
          <h4>Location</h4>
          <p>San Francisco Street, Butuan City, Philippines, 8600</p>
        </div>
        <div class="contact-item">
          <div class="contact-icon"><i class="fas fa-envelope"></i></div>
          <h4>Email</h4>
          <p><a href="mailto:library.helpdesk@urios.edu.ph">library.helpdesk@urios.edu.ph</a></p>
        </div>
        <div class="contact-item">
          <div class="contact-icon"><i class="fab fa-facebook-messenger"></i></div>
          <h4>Messenger</h4>
          <p>FSUU Basic Education Library<br>FSUU Learning Resource Center</p>
        </div>
        <div class="contact-item">
          <div class="contact-icon"><i class="fas fa-clock"></i></div>
          <h4>Hours</h4>
          <p>Mon–Fri: 7:00 AM – 7:00 PM</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== FOOTER ===== -->
  <footer class="landing-footer">
    <div class="footer-inner">
      <div class="footer-logo">
        <i class="fas fa-book-open"></i>
        <span>LibBook</span>
      </div>
      <p>&copy; <?= date('Y') ?> Library Facilities Booking System. All rights reserved.</p>
    </div>
  </footer>

  <script src="<?= BASE_URL ?>/assets/js/landing.js"></script>
</body>
</html>
