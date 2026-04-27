#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os

# FILE 1: layout.css
layout_css_content = """/* =============================================
   LAYOUT SYSTEM — Sidebar + Topbar
   Mobile-first. Breakpoints:
     Mobile  : ≤ 991px  (sidebar hidden)
     Desktop : ≥ 992px  (sidebar always visible)
   ============================================= */

:root {
  --sidebar-w:      260px;
  --topbar-h:       64px;
  --sidebar-bg:     #1e293b;
  --sidebar-hover:  rgba(255,255,255,.08);
  --sidebar-active: rgba(59,130,246,.3);
  --sidebar-text:   rgba(255,255,255,.78);
  --topbar-bg:      #ffffff;
  --transition:     .26s cubic-bezier(.4,0,.2,1);
}

/* ── Body / Shell ─────────────────────────────── */
body { overflow-x: hidden; }

.app-wrapper {
  display: flex;
  min-height: 100vh;
}

/* ── SIDEBAR ──────────────────────────────────── */
.sidebar {
  width: var(--sidebar-w);
  min-width: var(--sidebar-w);
  background: var(--sidebar-bg);
  color: #fff;
  display: flex;
  flex-direction: column;
  position: fixed;
  inset: 0 auto 0 0;
  height: 100vh;
  z-index: 1050;
  transition: transform var(--transition), box-shadow var(--transition);
  overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,.12) transparent;
}
.sidebar::-webkit-scrollbar { width: 4px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 4px; }

/* ── Sidebar Brand ─────────────────────────────── */
.sidebar-brand {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.1rem 1.25rem;
  border-bottom: 1px solid rgba(255,255,255,.1);
  flex-shrink: 0;
}

.sidebar-logo {
  display: flex;
  align-items: center;
  gap: .75rem;
  text-decoration: none;
  color: #fff;
}
.sidebar-logo:hover { color: #fff; text-decoration: none; }

.sidebar-logo-icon {
  width: 38px; height: 38px;
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.05rem;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(59,130,246,.4);
}

.sidebar-logo-name {
  display: block;
  font-weight: 700;
  font-size: 1rem;
  line-height: 1.2;
}
.sidebar-logo-sub {
  display: block;
  font-size: .68rem;
  color: rgba(255,255,255,.45);
  letter-spacing: .4px;
}

.sidebar-close-btn {
  background: none;
  border: none;
  color: rgba(255,255,255,.55);
  cursor: pointer;
  padding: .4rem;
  border-radius: 6px;
  line-height: 1;
  display: none;
  align-items: center; justify-content: center;
  width: 32px; height: 32px;
  transition: background var(--transition), color var(--transition);
}
.sidebar-close-btn:hover { background: rgba(255,255,255,.1); color: #fff; }

/* ── Sidebar User Card ─────────────────────────── */
.sidebar-user {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .9rem 1.25rem;
  border-bottom: 1px solid rgba(255,255,255,.08);
  flex-shrink: 0;
}

.sidebar-user-avatar {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: .9rem;
  flex-shrink: 0;
}
.sidebar-user-name {
  font-size: .875rem;
  font-weight: 600;
  color: #fff;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  max-width: 160px;
}
.sidebar-user-role {
  font-size: .68rem;
  color: rgba(255,255,255,.45);
  text-transform: uppercase;
  letter-spacing: .5px;
}

/* ── Sidebar Nav ───────────────────────────────── */
.sidebar-nav {
  flex: 1;
  padding: .65rem .65rem;
  overflow-y: auto;
  overflow-x: hidden;
}
.sidebar-nav ul {
  list-style: none;
  margin: 0; padding: 0;
  display: flex; flex-direction: column; gap: .1rem;
}

.sidebar-section-label {
  font-size: .65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .8px;
  color: rgba(255,255,255,.35);
  padding: .75rem 1rem .25rem;
}

.sidebar-nav-link {
  display: flex;
  align-items: center;
  gap: .7rem;
  padding: .6rem .9rem;
  border-radius: 8px;
  color: var(--sidebar-text);
  text-decoration: none;
  font-size: .875rem;
  font-weight: 500;
  transition: background var(--transition), color var(--transition);
  white-space: nowrap;
  position: relative;
}
.sidebar-nav-link i {
  width: 18px;
  font-size: .95rem;
  flex-shrink: 0;
  text-align: center;
  opacity: .8;
}
.sidebar-nav-link:hover {
  background: var(--sidebar-hover);
  color: #fff;
  text-decoration: none;
}
.sidebar-nav-link:hover i { opacity: 1; }

.sidebar-nav-link.active {
  background: var(--sidebar-active);
  color: #fff;
  font-weight: 600;
}
.sidebar-nav-link.active i { opacity: 1; }
.sidebar-nav-link.active::before {
  content: '';
  position: absolute;
  left: 0; top: 20%; bottom: 20%;
  width: 3px;
  background: #3b82f6;
  border-radius: 0 3px 3px 0;
}

.sidebar-badge {
  margin-left: auto;
  background: #ef4444;
  color: #fff;
  font-size: .62rem;
  font-weight: 700;
  padding: .1rem .4rem;
  border-radius: 100px;
  min-width: 1.3em;
  text-align: center;
  line-height: 1.4;
}

/* ── Sidebar Footer Links ──────────────────────── */
.sidebar-footer-links {
  padding: .65rem .65rem .9rem;
  border-top: 1px solid rgba(255,255,255,.08);
  display: flex;
  flex-direction: column;
  gap: .1rem;
  flex-shrink: 0;
}
.sidebar-logout { color: #fca5a5 !important; }
.sidebar-logout:hover { background: rgba(239,68,68,.15) !important; color: #fca5a5 !important; }

/* ── Overlay ───────────────────────────────────── */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.52);
  z-index: 1040;
  backdrop-filter: blur(2px);
  -webkit-backdrop-filter: blur(2px);
}
.sidebar-overlay.active { display: block; }

/* ── Main Wrapper ──────────────────────────────── */
.main-wrapper {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  margin-left: var(--sidebar-w);
  transition: margin-left var(--transition);
  min-height: 100vh;
}

/* ── Topbar ────────────────────────────────────── */
.topbar {
  height: var(--topbar-h);
  background: var(--topbar-bg);
  border-bottom: 1px solid #e9ecef;
  display: flex;
  align-items: center;
  padding: 0 1.25rem;
  gap: .75rem;
  position: sticky;
  top: 0;
  z-index: 1020;
  box-shadow: 0 1px 3px rgba(0,0,0,.05);
  flex-shrink: 0;
}

.topbar-hamburger {
  display: none;
  background: none;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  width: 40px; height: 40px;
  padding: 0;
  cursor: pointer;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 5px;
  flex-shrink: 0;
  transition: background var(--transition);
}
.topbar-hamburger:hover { background: #f1f5f9; }
.topbar-hamburger span {
  display: block;
  width: 18px; height: 2px;
  background: #475569;
  border-radius: 2px;
  transition: transform .2s, opacity .2s, width .2s;
}

/* Hamburger → X animation */
body.sidebar-body-open .topbar-hamburger span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
body.sidebar-body-open .topbar-hamburger span:nth-child(2) { opacity: 0; width: 0; }
body.sidebar-body-open .topbar-hamburger span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

.topbar-title {
  font-weight: 700;
  font-size: clamp(.9rem, 2.5vw, 1.1rem);
  color: #1e293b;
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.topbar-actions {
  display: flex;
  align-items: center;
  gap: .4rem;
  flex-shrink: 0;
}

.topbar-icon-btn {
  width: 40px; height: 40px;
  background: none;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem;
  color: #64748b;
  cursor: pointer;
  position: relative;
  transition: background var(--transition), border-color var(--transition);
  padding: 0;
}
.topbar-icon-btn:hover { background: #f8fafc; border-color: #cbd5e1; }

.topbar-notif-badge {
  position: absolute;
  top: -4px; right: -4px;
  background: #ef4444;
  color: #fff;
  font-size: .58rem;
  font-weight: 700;
  padding: .1rem .32rem;
  border-radius: 100px;
  min-width: 1.2em;
  text-align: center;
  border: 2px solid #fff;
  line-height: 1.4;
}

.topbar-user-btn {
  display: flex;
  align-items: center;
  gap: .5rem;
  background: none;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: .28rem .65rem .28rem .28rem;
  cursor: pointer;
  transition: background var(--transition);
}
.topbar-user-btn:hover { background: #f8fafc; }

.topbar-avatar {
  width: 30px; height: 30px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: .8rem; color: #fff;
  flex-shrink: 0;
}
.topbar-username {
  font-size: .85rem;
  font-weight: 500;
  color: #374151;
  max-width: 130px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Page Content ──────────────────────────────── */
.page-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: #f4f6f9;
}
.page-content main { flex: 1; }

/* ── App Footer ────────────────────────────────── */
.app-footer {
  background: #fff;
  border-top: 1px solid #e9ecef;
  padding: .7rem 1.5rem;
  font-size: .78rem;
  color: #94a3b8;
  display: flex;
  align-items: center;
  gap: .4rem;
  flex-shrink: 0;
}
.footer-sep { opacity: .4; }

/* ── RESPONSIVE ────────────────────────────────── */

/* Tablet + Mobile (≤ 991px): sidebar off-screen */
@media (max-width: 991px) {
  .sidebar {
    transform: translateX(-100%);
    box-shadow: none;
  }
  .sidebar.sidebar-open {
    transform: translateX(0);
    box-shadow: 6px 0 30px rgba(0,0,0,.22);
  }
  .sidebar-close-btn { display: flex; }

  .main-wrapper { margin-left: 0; }

  .topbar-hamburger { display: flex; }

  /* Prevent body scroll when sidebar open */
  body.sidebar-body-open { overflow: hidden; }
}

@media (max-width: 768px) {
  .topbar { padding: 0 .75rem; gap: .4rem; }
  .topbar-title { font-size: .9rem; }
  .app-footer { padding: .6rem .75rem; font-size: .74rem; }
  .footer-sep, .app-footer .hide-mobile { display: none; }
}

@media (max-width: 480px) {
  .topbar-username { display: none; }
  .topbar-user-btn { padding: .28rem .28rem; }
}
"""

# FILE 2: navbar.php
navbar_php_content = """<?php
// ── Layout data ───────────────────────────────────────────
$role   = $_SESSION['role']   ?? '';
$name   = $_SESSION['name']   ?? 'User';
$uid    = (int)($_SESSION['user_id'] ?? 0);
$script = basename($_SERVER['SCRIPT_NAME']);

// Role-based sidebar navigation
$navItems = [];
if ($role === 'admin') {
    $navItems = [
        ['href' => BASE_URL.'/admin/dashboard.php',         'icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard',   'match' => ['dashboard.php']],
        ['href' => BASE_URL.'/admin/manage_bookings.php',   'icon' => 'fas fa-calendar-check', 'label' => 'Bookings',    'match' => ['manage_bookings.php','view_booking.php']],
        ['href' => BASE_URL.'/admin/manage_facilities.php', 'icon' => 'fas fa-building',       'label' => 'Facilities',  'match' => ['manage_facilities.php','add_facility.php','edit_facility.php']],
        ['href' => BASE_URL.'/admin/manage_users.php',      'icon' => 'fas fa-users',          'label' => 'Users',       'match' => ['manage_users.php']],
        ['href' => BASE_URL.'/admin/audit_logs.php',        'icon' => 'fas fa-clipboard-list', 'label' => 'Audit Logs',  'match' => ['audit_logs.php']],
        ['href' => BASE_URL.'/admin/reports.php',           'icon' => 'fas fa-chart-bar',      'label' => 'Reports',     'match' => ['reports.php']],
    ];
} elseif ($role === 'faculty') {
    $navItems = [
        ['href' => BASE_URL.'/faculty/dashboard.php',   'icon' => 'fas fa-home',     'label' => 'Dashboard',    'match' => ['dashboard.php']],
        ['href' => BASE_URL.'/faculty/my_bookings.php', 'icon' => 'fas fa-calendar', 'label' => 'My Bookings',  'match' => ['my_bookings.php','view_booking.php']],
        ['href' => BASE_URL.'/notifications.php',       'icon' => 'fas fa-bell',     'label' => 'Notifications','match' => ['notifications.php'], 'notif' => true],
    ];
} else {
    $navItems = [
        ['href' => BASE_URL.'/student/dashboard.php',   'icon' => 'fas fa-home',     'label' => 'Dashboard',    'match' => ['dashboard.php']],
        ['href' => BASE_URL.'/student/my_bookings.php', 'icon' => 'fas fa-calendar', 'label' => 'My Bookings',  'match' => ['my_bookings.php','view_booking.php']],
        ['href' => BASE_URL.'/notifications.php',       'icon' => 'fas fa-bell',     'label' => 'Notifications','match' => ['notifications.php'], 'notif' => true],
    ];
}

// Load notifications
$_notifCount = 0;
$_notifItems = [];
if ($uid && isset($pdo)) {
    try {
        $_notifCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid AND is_read=0")->fetchColumn();
        $ns = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 8');
        $ns->execute([$uid]);
        $_notifItems = $ns->fetchAll();
    } catch (PDOException $e) {}
}

// Derive topbar title from $pageTitle global or script name
$_topTitle = isset($pageTitle) ? $pageTitle
           : ucwords(str_replace(['_','.php'],[' ',''], $script));

$_typeIcon = ['success'=>'check-circle text-success','danger'=>'times-circle text-danger',
              'warning'=>'exclamation-circle text-warning','info'=>'info-circle text-info'];
?>
<div class="app-wrapper">

  <!-- ════════════════════════ SIDEBAR ════════════════════════ -->
  <aside class="sidebar" id="sidebar" role="navigation" aria-label="Main navigation">

    <!-- Brand -->
    <div class="sidebar-brand">
      <a href="<?= BASE_URL ?>/<?= $role ?>/dashboard.php" class="sidebar-logo" aria-label="<?= APP_NAME ?>">
        <div class="sidebar-logo-icon"><i class="fas fa-book-open" aria-hidden="true"></i></div>
        <div>
          <span class="sidebar-logo-name"><?= APP_SHORT ?></span>
          <span class="sidebar-logo-sub">Booking System</span>
        </div>
      </a>
      <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close sidebar">
        <i class="fas fa-times" aria-hidden="true"></i>
      </button>
    </div>

    <!-- User info -->
    <div class="sidebar-user">
      <div class="sidebar-user-avatar" aria-hidden="true">
        <?= strtoupper(mb_substr($name, 0, 1)) ?>
      </div>
      <div style="min-width:0">
        <div class="sidebar-user-name"><?= e($name) ?></div>
        <div class="sidebar-user-role"><?= ucfirst($role) ?></div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav" aria-label="<?= ucfirst($role) ?> navigation">
      <ul>
        <?php foreach ($navItems as $item):
          $isActive = in_array($script, $item['match'] ?? [$item['label']]);
        ?>
        <li>
          <a href="<?= $item['href'] ?>"
             class="sidebar-nav-link<?= $isActive ? ' active' : '' ?>"
             <?= $isActive ? 'aria-current="page"' : '' ?>>
            <i class="<?= $item['icon'] ?>" aria-hidden="true"></i>
            <span><?= $item['label'] ?></span>
            <?php if (!empty($item['notif']) && $_notifCount > 0): ?>
              <span class="sidebar-badge" aria-label="<?= $_notifCount ?> unread"><?= min($_notifCount,99) ?></span>
            <?php endif; ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <!-- Bottom links -->
    <div class="sidebar-footer-links">
      <a href="<?= BASE_URL ?>/<?= $role ?>/profile.php"
         class="sidebar-nav-link<?= $script === 'profile.php' ? ' active' : '' ?>">
        <i class="fas fa-user-circle" aria-hidden="true"></i>
        <span>Profile</span>
      </a>
      <a href="<?= BASE_URL ?>/auth/logout.php" class="sidebar-nav-link sidebar-logout">
        <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
        <span>Logout</span>
      </a>
    </div>

  </aside><!-- /sidebar -->

  <!-- Mobile overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

  <!-- ════════════════════════ MAIN WRAPPER ════════════════════ -->
  <div class="main-wrapper">

    <!-- TOPBAR -->
    <header class="topbar" role="banner">

      <!-- Hamburger (mobile only) -->
      <button class="topbar-hamburger" id="hamburger"
              aria-label="Toggle navigation menu" aria-expanded="false"
              aria-controls="sidebar">
        <span></span><span></span><span></span>
      </button>

      <!-- Page title -->
      <div class="topbar-title"><?= e($_topTitle) ?></div>

      <!-- Actions: notifications + user -->
      <div class="topbar-actions">

        <?php if ($uid): ?>
        <!-- Notification Bell -->
        <div class="dropdown">
          <button class="topbar-icon-btn" id="notifBell"
                  data-bs-toggle="dropdown" aria-expanded="false"
                  onclick="markNotifsRead()"
                  aria-label="Notifications<?= $_notifCount ? " ($_notifCount unread)" : '' ?>">
            <i class="fas fa-bell" aria-hidden="true"></i>
            <?php if ($_notifCount > 0): ?>
              <span class="topbar-notif-badge" id="notifBadge" aria-hidden="true">
                <?= $_notifCount > 99 ? '99+' : $_notifCount ?>
              </span>
            <?php endif; ?>
          </button>
          <div class="dropdown-menu dropdown-menu-end p-0 shadow"
               style="width:min(340px,calc(100vw - 1.5rem));max-height:420px;overflow-y:auto">
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light">
              <span class="fw-semibold small">Notifications</span>
              <?php if (!empty($_notifItems)): ?>
              <button class="btn btn-link btn-sm p-0 text-muted text-decoration-none small"
                      onclick="markAllRead();return false;">Mark all read</button>
              <?php endif; ?>
            </div>
            <?php if (empty($_notifItems)): ?>
              <div class="text-center text-muted py-4 small">
                <i class="fas fa-bell-slash fa-2x mb-2 d-block opacity-25" aria-hidden="true"></i>
                No notifications
              </div>
            <?php else: ?>
              <?php foreach ($_notifItems as $n):
                $ic = $_typeIcon[$n['type']] ?? 'circle text-secondary';
              ?>
                <a href="<?= $n['link'] ? e($n['link']) : BASE_URL.'/notifications.php' ?>"
                   class="dropdown-item px-3 py-2 border-bottom small <?= $n['is_read'] ? 'text-muted' : 'fw-semibold' ?>"
                   style="white-space:normal">
                  <div class="d-flex gap-2 align-items-start">
                    <i class="fas fa-<?= $ic ?> mt-1 flex-shrink-0" aria-hidden="true"></i>
                    <div>
                      <div><?= e($n['message']) ?></div>
                      <div class="text-muted fw-normal" style="font-size:.73rem">
                        <?= date('M j, g:i A', strtotime($n['created_at'])) ?>
                      </div>
                    </div>
                  </div>
                </a>
              <?php endforeach; ?>
              <div class="text-center py-2 border-top">
                <a href="<?= BASE_URL ?>/notifications.php"
                   class="small text-primary text-decoration-none">View all notifications</a>
              </div>
            <?php endif; ?>
          </div>
        </div><!-- /notif dropdown -->
        <?php endif; ?>

        <!-- User menu -->
        <div class="dropdown">
          <button class="topbar-user-btn" data-bs-toggle="dropdown" aria-expanded="false"
                  aria-label="User menu">
            <div class="topbar-avatar" aria-hidden="true">
              <?= strtoupper(mb_substr($name, 0, 1)) ?>
            </div>
            <span class="topbar-username"><?= e($name) ?></span>
            <i class="fas fa-chevron-down ms-1" style="font-size:.65rem;color:#94a3b8" aria-hidden="true"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li><span class="dropdown-item-text text-muted small py-1"><?= ucfirst($role) ?></span></li>
            <li><hr class="dropdown-divider my-1"></li>
            <li>
              <a class="dropdown-item" href="<?= BASE_URL ?>/<?= $role ?>/profile.php">
                <i class="fas fa-user me-2 text-muted" aria-hidden="true"></i>Profile
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="<?= BASE_URL ?>/notifications.php">
                <i class="fas fa-bell me-2 text-muted" aria-hidden="true"></i>Notifications
                <?php if ($_notifCount > 0): ?>
                  <span class="badge bg-danger ms-1"><?= $_notifCount ?></span>
                <?php endif; ?>
              </a>
            </li>
            <li><hr class="dropdown-divider my-1"></li>
            <li>
              <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php">
                <i class="fas fa-sign-out-alt me-2" aria-hidden="true"></i>Logout
              </a>
            </li>
          </ul>
        </div><!-- /user dropdown -->

      </div><!-- /topbar-actions -->
    </header><!-- /topbar -->

    <!-- PAGE CONTENT AREA (opened here; closed in footer.php) -->
    <div class="page-content">

<script>
document.addEventListener('DOMContentLoaded', function () {
  var sidebar  = document.getElementById('sidebar');
  var overlay  = document.getElementById('sidebarOverlay');
  var hamburger = document.getElementById('hamburger');
  var closeBtn = document.getElementById('sidebarClose');

  function openSidebar() {
    sidebar.classList.add('sidebar-open');
    overlay.classList.add('active');
    hamburger.setAttribute('aria-expanded', 'true');
    document.body.classList.add('sidebar-body-open');
  }

  function closeSidebar() {
    sidebar.classList.remove('sidebar-open');
    overlay.classList.remove('active');
    hamburger.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('sidebar-body-open');
  }

  hamburger.addEventListener('click', function () {
    sidebar.classList.contains('sidebar-open') ? closeSidebar() : openSidebar();
  });

  if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
  overlay.addEventListener('click', closeSidebar);

  window.addEventListener('resize', function () {
    if (window.innerWidth >= 992) closeSidebar();
  });
});

function markNotifsRead() {
  var badge = document.getElementById('notifBadge');
  if (badge) badge.style.display = 'none';
  fetch('<?= BASE_URL ?>/api/notify.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=mark_all'
  });
}

function markAllRead() {
  fetch('<?= BASE_URL ?>/api/notify.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=mark_all'
  }).then(function () { location.reload(); });
}
</script>
"""

# Write FILE 1: layout.css
file1_path = r'assets\css\layout.css'
try:
    with open(file1_path, 'w', encoding='utf-8') as f:
        f.write(layout_css_content)
    print(f"✓ Written {file1_path} ({os.path.getsize(file1_path)} bytes)")
except Exception as e:
    print(f"✗ Failed to write {file1_path}: {e}")
    exit(1)

# Write FILE 2: navbar.php
file2_path = r'includes\navbar.php'
try:
    with open(file2_path, 'w', encoding='utf-8') as f:
        f.write(navbar_php_content)
    print(f"✓ Written {file2_path} ({os.path.getsize(file2_path)} bytes)")
except Exception as e:
    print(f"✗ Failed to write {file2_path}: {e}")
    exit(1)

print("\n✓ All files written successfully!")
