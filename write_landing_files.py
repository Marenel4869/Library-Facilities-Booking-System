import os

# FILE 1: index.php
index_php = r"""<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $errors[] = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND status = "active" LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            header('Location: ' . BASE_URL . '/' . $user['role'] . '/dashboard.php');
            exit;
        } else {
            $errors[] = 'Invalid credentials or account suspended.';
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

        <form method="POST" class="login-form" novalidate>
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
          <p>University Library, Main Campus</p>
        </div>
        <div class="contact-item">
          <div class="contact-icon"><i class="fas fa-envelope"></i></div>
          <h4>Email</h4>
          <p>library@university.edu.ph</p>
        </div>
        <div class="contact-item">
          <div class="contact-icon"><i class="fas fa-phone"></i></div>
          <h4>Phone</h4>
          <p>(02) 8123-4567</p>
        </div>
        <div class="contact-item">
          <div class="contact-icon"><i class="fas fa-clock"></i></div>
          <h4>Hours</h4>
          <p>Mon–Fri: 7:00 AM – 8:00 PM</p>
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
"""

# FILE 2: landing.css
landing_css = """/* =============================================
   LANDING PAGE — landing.css
   ============================================= */

/* --- Variables --- */
:root {
  --primary:    #1a56db;
  --primary-dk: #1048c4;
  --accent:     #f59e0b;
  --glass-bg:   rgba(255,255,255,0.12);
  --glass-border: rgba(255,255,255,0.25);
  --text-light: rgba(255,255,255,0.88);
  --radius:     16px;
  --shadow:     0 8px 32px rgba(0,0,0,0.25);
  --transition: 0.3s ease;
}

/* --- Reset & Base --- */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html { scroll-behavior: smooth; }

body {
  font-family: 'Inter', sans-serif;
  color: #1e293b;
  background: #f8fafc;
  overflow-x: hidden;
}

/* =============================================
   NAVBAR
   ============================================= */
.landing-nav {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 1000;
  padding: 0;
  transition: background var(--transition), box-shadow var(--transition);
}

.landing-nav.scrolled {
  background: rgba(10,20,50,0.92);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  box-shadow: 0 2px 20px rgba(0,0,0,0.3);
}

.nav-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.nav-logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
  color: #fff;
  font-weight: 700;
  font-size: 1.2rem;
  letter-spacing: -0.3px;
}

.nav-logo i { color: var(--accent); font-size: 1.3rem; }

.nav-menu {
  display: flex;
  list-style: none;
  gap: 0.25rem;
}

.nav-link {
  display: block;
  padding: 0.45rem 0.9rem;
  color: rgba(255,255,255,0.85);
  text-decoration: none;
  font-size: 0.92rem;
  font-weight: 500;
  border-radius: 8px;
  transition: background var(--transition), color var(--transition);
}

.nav-link:hover {
  background: rgba(255,255,255,0.12);
  color: #fff;
}

/* Hamburger */
.hamburger {
  display: none;
  flex-direction: column;
  gap: 5px;
  cursor: pointer;
  background: none;
  border: none;
  padding: 4px;
}

.hamburger span {
  display: block;
  width: 24px;
  height: 2px;
  background: #fff;
  border-radius: 2px;
  transition: transform 0.35s ease, opacity 0.35s ease;
}

/* Hamburger open state */
.hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
.hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* Mobile menu */
@media (max-width: 768px) {
  .hamburger { display: flex; }

  .nav-menu {
    position: absolute;
    top: 100%;
    left: 0; right: 0;
    flex-direction: column;
    gap: 0;
    background: rgba(10,20,50,0.97);
    backdrop-filter: blur(12px);
    padding: 0.5rem 1rem 1rem;
    transform: translateY(-10px);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease, transform 0.3s ease;
  }

  .nav-menu.open {
    opacity: 1;
    transform: translateY(0);
    pointer-events: all;
  }

  .nav-link { padding: 0.75rem 1rem; border-radius: 10px; }
}

/* =============================================
   HERO
   ============================================= */
.hero {
  position: relative;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 6rem 1.5rem 3rem;
  background-image: url('https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=1920&q=80');
  background-size: cover;
  background-position: center;
  background-attachment: fixed;
  overflow: hidden;
}

/* Gradient overlay */
.hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    135deg,
    rgba(8,20,60,0.82) 0%,
    rgba(20,60,120,0.70) 50%,
    rgba(10,30,80,0.80) 100%
  );
}

/* Animated background orbs */
.hero::before, .hero::after {
  content: '';
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.25;
  animation: float 8s ease-in-out infinite alternate;
}

.hero::before {
  width: 500px; height: 500px;
  background: radial-gradient(circle, #1a56db, transparent);
  top: -100px; right: -100px;
}

.hero::after {
  width: 400px; height: 400px;
  background: radial-gradient(circle, #f59e0b, transparent);
  bottom: -80px; left: -80px;
  animation-delay: -4s;
}

.hero-content {
  position: relative;
  z-index: 1;
  max-width: 1100px;
  width: 100%;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr 420px;
  gap: 3.5rem;
  align-items: center;
}

/* Hero text */
.hero-text { color: #fff; }

.hero-badge {
  display: inline-flex;
  align-items: center;
  background: rgba(245,158,11,0.2);
  border: 1px solid rgba(245,158,11,0.4);
  color: var(--accent);
  font-size: 0.8rem;
  font-weight: 600;
  padding: 0.35rem 0.9rem;
  border-radius: 100px;
  margin-bottom: 1.25rem;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.hero-text h1 {
  font-size: clamp(2rem, 4vw, 3.2rem);
  font-weight: 800;
  line-height: 1.15;
  letter-spacing: -1px;
  margin-bottom: 1rem;
}

.hero-text h1 span {
  background: linear-gradient(135deg, #60a5fa, var(--accent));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-text p {
  font-size: 1.1rem;
  color: var(--text-light);
  line-height: 1.7;
  margin-bottom: 2rem;
}

.hero-features {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.hero-features span {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.85rem;
  color: rgba(255,255,255,0.85);
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.15);
  padding: 0.35rem 0.85rem;
  border-radius: 100px;
}

.hero-features span i { color: #4ade80; }

/* Scroll down */
.scroll-down {
  position: absolute;
  bottom: 2rem;
  left: 50%;
  transform: translateX(-50%);
  color: rgba(255,255,255,0.6);
  font-size: 1.2rem;
  z-index: 2;
  animation: bounce 2s ease-in-out infinite;
  text-decoration: none;
  transition: color var(--transition);
}
.scroll-down:hover { color: #fff; }

/* =============================================
   LOGIN CARD (Glassmorphism)
   ============================================= */
.login-card {
  background: rgba(255,255,255,0.1);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: var(--radius);
  padding: 2.5rem 2rem;
  box-shadow: 0 20px 60px rgba(0,0,0,0.35), inset 0 1px 0 rgba(255,255,255,0.2);
  color: #fff;
}

.login-card-header {
  text-align: center;
  margin-bottom: 1.8rem;
}

.login-card-header i {
  font-size: 2.6rem;
  color: var(--accent);
  display: block;
  margin-bottom: 0.6rem;
}

.login-card-header h2 {
  font-size: 1.6rem;
  font-weight: 700;
  color: #fff;
  margin-bottom: 0.2rem;
}

.login-card-header p {
  font-size: 0.88rem;
  color: rgba(255,255,255,0.65);
}

/* Alert */
.alert-error {
  background: rgba(239,68,68,0.18);
  border: 1px solid rgba(239,68,68,0.4);
  color: #fca5a5;
  border-radius: 10px;
  padding: 0.75rem 1rem;
  font-size: 0.88rem;
  margin-bottom: 1.25rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* Form */
.login-form { display: flex; flex-direction: column; gap: 1.1rem; }

.form-group { display: flex; flex-direction: column; gap: 0.4rem; }

.form-group label {
  font-size: 0.82rem;
  font-weight: 600;
  color: rgba(255,255,255,0.8);
  letter-spacing: 0.3px;
}

.label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.forgot-link {
  font-size: 0.78rem;
  color: rgba(255,255,255,0.6);
  text-decoration: none;
  transition: color var(--transition);
}
.forgot-link:hover { color: var(--accent); }

.input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.input-wrap > i {
  position: absolute;
  left: 0.9rem;
  font-size: 0.9rem;
  color: rgba(255,255,255,0.45);
  pointer-events: none;
}

.input-wrap input {
  width: 100%;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.18);
  border-radius: 10px;
  padding: 0.75rem 2.8rem 0.75rem 2.5rem;
  font-size: 0.92rem;
  color: #fff;
  outline: none;
  transition: border-color var(--transition), background var(--transition), box-shadow var(--transition);
  font-family: 'Inter', sans-serif;
}

.input-wrap input::placeholder { color: rgba(255,255,255,0.35); }

.input-wrap input:focus {
  border-color: rgba(96,165,250,0.7);
  background: rgba(255,255,255,0.12);
  box-shadow: 0 0 0 3px rgba(96,165,250,0.15);
}

/* Toggle password */
.toggle-pw {
  position: absolute;
  right: 0.75rem;
  background: none;
  border: none;
  color: rgba(255,255,255,0.45);
  cursor: pointer;
  font-size: 0.9rem;
  padding: 0.25rem;
  transition: color var(--transition);
}
.toggle-pw:hover { color: rgba(255,255,255,0.85); }

/* Login button */
.btn-login {
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
  margin-top: 0.3rem;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  box-shadow: 0 4px 20px rgba(26,86,219,0.5);
  font-family: 'Inter', sans-serif;
  letter-spacing: 0.2px;
}

.btn-login:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(26,86,219,0.55);
}

.btn-login:active { transform: translateY(0); }

.login-footer {
  text-align: center;
  margin-top: 1.4rem;
  font-size: 0.85rem;
  color: rgba(255,255,255,0.6);
}

.login-footer a {
  color: #93c5fd;
  text-decoration: none;
  font-weight: 500;
  transition: color var(--transition);
}
.login-footer a:hover { color: #fff; }

/* =============================================
   SECTIONS
   ============================================= */
.section { padding: 5rem 1.5rem; }
.section-alt { background: #f1f5f9; }

.section-container {
  max-width: 1100px;
  margin: 0 auto;
}

.section-header {
  text-align: center;
  margin-bottom: 3rem;
}

.section-tag {
  display: inline-block;
  background: #e8f0fe;
  color: var(--primary);
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.3rem 0.8rem;
  border-radius: 100px;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 0.75rem;
}

.section-header h2 {
  font-size: clamp(1.6rem, 3vw, 2.4rem);
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 0.6rem;
}

.section-header p {
  color: #64748b;
  font-size: 1.05rem;
}

/* Features grid */
.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.5rem;
}

.feature-card {
  background: #fff;
  border-radius: var(--radius);
  padding: 2rem 1.5rem;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06);
  transition: transform var(--transition), box-shadow var(--transition);
  border: 1px solid #f1f5f9;
}

.feature-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 30px rgba(0,0,0,0.1);
}

.feature-icon {
  width: 52px; height: 52px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
  margin-bottom: 1rem;
}

.feature-card h3 {
  font-size: 1rem;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 0.5rem;
}

.feature-card p {
  font-size: 0.9rem;
  color: #64748b;
  line-height: 1.6;
}

/* Contact grid */
.contact-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
}

.contact-item {
  text-align: center;
  padding: 2rem 1.5rem;
  background: #fff;
  border-radius: var(--radius);
  box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.contact-icon {
  width: 56px; height: 56px;
  background: linear-gradient(135deg, var(--primary), var(--primary-dk));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  color: #fff;
  margin: 0 auto 1rem;
}

.contact-item h4 { font-weight: 700; font-size: 0.95rem; color: #0f172a; margin-bottom: 0.35rem; }
.contact-item p  { font-size: 0.88rem; color: #64748b; }

/* =============================================
   FOOTER
   ============================================= */
.landing-footer {
  background: #0b1120;
  color: rgba(255,255,255,0.5);
  padding: 1.5rem;
  text-align: center;
}

.footer-inner { max-width: 1100px; margin: 0 auto; }

.footer-logo {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: #fff;
  font-weight: 700;
  font-size: 1rem;
  margin-bottom: 0.5rem;
}

.footer-logo i { color: var(--accent); }

.landing-footer p { font-size: 0.82rem; }

/* =============================================
   ANIMATIONS
   ============================================= */
.fade-in-up {
  opacity: 0;
  transform: translateY(28px);
  animation: fadeInUp 0.75s ease forwards;
}
.delay-1 { animation-delay: 0.2s; }

@keyframes fadeInUp {
  to { opacity: 1; transform: translateY(0); }
}

@keyframes float {
  0%   { transform: translate(0, 0) scale(1); }
  100% { transform: translate(30px, 20px) scale(1.05); }
}

@keyframes bounce {
  0%, 100% { transform: translateX(-50%) translateY(0); }
  50%       { transform: translateX(-50%) translateY(8px); }
}

/* =============================================
   RESPONSIVE
   ============================================= */
@media (max-width: 900px) {
  .hero-content {
    grid-template-columns: 1fr;
    text-align: center;
    gap: 2.5rem;
  }

  .hero-features { justify-content: center; }

  .login-card { max-width: 440px; margin: 0 auto; }
}

@media (max-width: 480px) {
  .hero { padding: 5rem 1rem 3rem; background-attachment: scroll; }
  .login-card { padding: 2rem 1.25rem; }
  .hero-text h1 { font-size: 1.9rem; }
  .section { padding: 3.5rem 1rem; }
}
"""

# FILE 3: landing.js
landing_js = """/* landing.js — Hamburger menu + navbar scroll + password toggle */

(function () {
  'use strict';

  // --- Hamburger Menu ---
  const hamburger = document.getElementById('hamburger');
  const navMenu   = document.getElementById('navMenu');

  if (hamburger && navMenu) {
    hamburger.addEventListener('click', function () {
      const isOpen = navMenu.classList.toggle('open');
      hamburger.classList.toggle('open', isOpen);
      hamburger.setAttribute('aria-expanded', isOpen);
    });

    // Close menu when a nav link is clicked
    navMenu.querySelectorAll('.nav-link').forEach(function (link) {
      link.addEventListener('click', function () {
        navMenu.classList.remove('open');
        hamburger.classList.remove('open');
        hamburger.setAttribute('aria-expanded', false);
      });
    });

    // Close menu on outside click
    document.addEventListener('click', function (e) {
      if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) {
        navMenu.classList.remove('open');
        hamburger.classList.remove('open');
      }
    });
  }

  // --- Navbar scroll effect ---
  const navbar = document.getElementById('navbar');
  if (navbar) {
    window.addEventListener('scroll', function () {
      navbar.classList.toggle('scrolled', window.scrollY > 40);
    }, { passive: true });
  }

  // --- Password visibility toggle ---
  const togglePw = document.getElementById('togglePw');
  const pwInput  = document.getElementById('password');
  const eyeIcon  = document.getElementById('eyeIcon');

  if (togglePw && pwInput && eyeIcon) {
    togglePw.addEventListener('click', function () {
      const visible = pwInput.type === 'password';
      pwInput.type  = visible ? 'text' : 'password';
      eyeIcon.className = visible ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
  }

  // --- Smooth scroll for nav links ---
  document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
    anchor.addEventListener('click', function (e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

})();
"""

base = r'C:\xampp\htdocs\Library-Facilities-Booking-System'

files = {
    os.path.join(base, 'index.php'): index_php,
    os.path.join(base, 'assets', 'css', 'landing.css'): landing_css,
    os.path.join(base, 'assets', 'js', 'landing.js'): landing_js,
}

for path, content in files.items():
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)
    size = os.path.getsize(path)
    print(f'Written: {path}  ({size} bytes)')
