<?php
// ── Layout data ───────────────────────────────────────────
$role   = $_SESSION['role']   ?? '';
$name   = $_SESSION['name']   ?? 'User';
$uid    = (int)($_SESSION['user_id'] ?? 0);
$script = basename($_SERVER['SCRIPT_NAME']);

// Role-based sidebar navigation
$navItems = [];
if ($role === 'admin') {
    $navItems = [
        ['href' => BASE_URL.'/admin/dashboard.php',         'icon' => 'fas fa-home',           'label' => 'Dashboard',   'match' => ['dashboard.php']],
        ['href' => BASE_URL.'/admin/manage_bookings.php',   'icon' => 'fas fa-calendar-check', 'label' => 'Bookings',    'match' => ['manage_bookings.php','view_booking.php']],
        ['href' => BASE_URL.'/admin/manage_facilities.php', 'icon' => 'fas fa-building',       'label' => 'Facilities',  'match' => ['manage_facilities.php','add_facility.php','edit_facility.php']],
        ['href' => BASE_URL.'/admin/manage_users.php',      'icon' => 'fas fa-users',          'label' => 'Users',       'match' => ['manage_users.php']],
        ['href' => BASE_URL.'/admin/audit_logs.php',           'icon' => 'fas fa-clipboard-list', 'label' => 'Audit Logs',    'match' => ['audit_logs.php']],
        ['href' => BASE_URL.'/admin/manage_announcements.php', 'icon' => 'fas fa-bullhorn',       'label' => 'Announcements','match' => ['manage_announcements.php']],
        ['href' => BASE_URL.'/admin/help_requests.php',        'icon' => 'fas fa-life-ring',      'label' => 'Help Requests','match' => ['help_requests.php']],
        ['href' => BASE_URL.'/admin/reports.php',              'icon' => 'fas fa-chart-bar',      'label' => 'Reports',       'match' => ['reports.php']],
    ];
} elseif ($role === 'faculty') {
    $navItems = [
        ['href' => BASE_URL.'/faculty/dashboard.php',   'icon' => 'fas fa-home',          'label' => 'Dashboard',    'match' => ['dashboard.php']],
        ['href' => BASE_URL.'/faculty/my_bookings.php', 'icon' => 'fas fa-calendar-alt',  'label' => 'My Bookings',  'match' => ['my_bookings.php','view_booking.php']],
        ['href' => BASE_URL.'/announcements.php',       'icon' => 'fas fa-bullhorn',      'label' => 'Announcements','match' => ['announcements.php']],
        ['href' => BASE_URL.'/help.php',                'icon' => 'fas fa-life-ring',     'label' => 'Need Help',    'match' => ['help.php']],
        ['href' => BASE_URL.'/notifications.php',       'icon' => 'fas fa-bell',          'label' => 'Notifications','match' => ['notifications.php'], 'notif' => true],
    ];
} else {
    $navItems = [
        ['href' => BASE_URL.'/student/dashboard.php',   'icon' => 'fas fa-home',          'label' => 'Dashboard',    'match' => ['dashboard.php']],
        ['href' => BASE_URL.'/student/my_bookings.php', 'icon' => 'fas fa-calendar-alt',  'label' => 'My Bookings',  'match' => ['my_bookings.php','view_booking.php']],
        ['href' => BASE_URL.'/announcements.php',       'icon' => 'fas fa-bullhorn',      'label' => 'Announcements','match' => ['announcements.php']],
        ['href' => BASE_URL.'/help.php',                'icon' => 'fas fa-life-ring',     'label' => 'Need Help',    'match' => ['help.php']],
        ['href' => BASE_URL.'/notifications.php',       'icon' => 'fas fa-bell',          'label' => 'Notifications','match' => ['notifications.php'], 'notif' => true],
    ];
}

// Load notifications
$_notifCount = 0;
$_notifItems = [];
if ($uid && isset($pdo)) {
    try {
        $nsCount = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
        $nsCount->execute([$uid]);
        $_notifCount = (int)$nsCount->fetchColumn();
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

// Quick links (topbar) derived from sidebar items
$_quickNav = array_values(array_filter($navItems, function ($it) {
    return empty($it['notif']) && ($it['label'] ?? '') !== 'Dashboard';
}));
$_quickNav = array_slice($_quickNav, 0, 5);
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
        </div>
      </a>
      <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close sidebar">
        <i class="fas fa-times" aria-hidden="true"></i>
      </button>
    </div>

    <!-- User info -->
    <div class="sidebar-user">
      <div class="sidebar-user-avatar-wrap">
        <div class="sidebar-user-avatar" aria-hidden="true">
          <?= strtoupper(mb_substr($name, 0, 1)) ?>
        </div>
        <span class="sidebar-user-online" title="Online" aria-hidden="true"></span>
      </div>
      <div class="sidebar-user-info" style="min-width:0">
        <div class="sidebar-user-name"><?= e($name) ?></div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav" aria-label="Main navigation">
      <div class="sidebar-section-label">Menu</div>
      <ul>
        <?php foreach ($navItems as $item):
          $isActive = in_array($script, $item['match'] ?? [$item['label']]);
        ?>
        <li>
          <a href="<?= $item['href'] ?>"
             class="sidebar-nav-link<?= $isActive ? ' active' : '' ?>"
             <?= $isActive ? 'aria-current="page"' : '' ?>>
            <span class="nav-icon" aria-hidden="true"><i class="<?= $item['icon'] ?>"></i></span>
            <span><?= $item['label'] ?></span>
            <?php if (!empty($item['notif']) && $_notifCount > 0): ?>
              <span class="sidebar-badge ms-auto" aria-label="<?= $_notifCount ?> unread"><?= min($_notifCount,99) ?></span>
            <?php endif; ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <!-- Sidebar footer removed per user request -->

  </aside><!-- /sidebar -->

  <!-- Mobile overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

  <!-- ════════════════════════ MAIN WRAPPER ════════════════════ -->
  <div class="main-wrapper">

    <!-- TOPBAR -->
    <header class="topbar" role="banner">

      <div class="topbar-main">
        <!-- Hamburger (mobile only) -->
        <button class="topbar-hamburger" id="hamburger"
                aria-label="Toggle navigation menu" aria-expanded="false"
                aria-controls="sidebar">
          <span></span><span></span><span></span>
        </button>

        <button id="sidebarCollapseToggle" class="topbar-icon-btn sidebar-toggle-btn d-none d-lg-inline-flex me-2" aria-label="Toggle sidebar" title="Toggle sidebar">
          <i class="fas fa-columns" aria-hidden="true"></i>
        </button>
        <a class="topbar-brand d-flex align-items-center" href="<?= BASE_URL ?>/<?= $role ?>/dashboard.php" aria-label="<?= APP_NAME ?> home">
          <span class="brand-mark" aria-hidden="true"><i class="fas fa-book-open"></i></span>
          <span class="brand-text ms-2">
            <span class="brand-name"><?= e($_topTitle) ?></span>
          </span>
        </a>





        <?php if (!empty($_quickNav) && $role !== 'admin'): ?>
        <nav class="topbar-quicknav" aria-label="Quick links">
          <?php foreach ($_quickNav as $qi): ?>
            <a href="<?= $qi['href'] ?>">
              <i class="<?= $qi['icon'] ?>" aria-hidden="true"></i>
              <span><?= e($qi['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <!-- Actions: notifications + user (right side) -->
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
                  <a href="<?= BASE_URL . '/notifications.php?open=' . (int)$n['id'] ?>"
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
            <ul class="dropdown-menu dropdown-menu-end shadow-sm py-2" aria-label="User menu">
              <li>
                <a class="dropdown-item d-flex align-items-center gap-3 py-2" href="<?= BASE_URL ?>/<?= $role ?>/profile.php">
                  <span class="menu-icon"><i class="fas fa-user text-muted"></i></span>
                  <div>
                    <div class="fw-semibold">Profile</div>
                    <div class="text-muted small">View and edit your profile</div>
                  </div>
                </a>
              </li>
              <li>
                <a class="dropdown-item d-flex align-items-center gap-3 py-2" href="<?= BASE_URL ?>/notifications.php">
                  <span class="menu-icon"><i class="fas fa-bell text-muted"></i></span>
                  <div>
                    <div class="fw-semibold">Notifications <?php if ($_notifCount > 0): ?><span class="badge bg-danger ms-2"><?= $_notifCount ?></span><?php endif; ?></div>
                    <div class="text-muted small">Recent updates</div>
                  </div>
                </a>
              </li>
              <li><hr class="dropdown-divider my-1"></li>
              <li>
                <a class="dropdown-item d-flex align-items-center gap-3 py-2 text-danger" href="<?= BASE_URL ?>/auth/logout.php">
                  <span class="menu-icon menu-icon-danger"><i class="fas fa-sign-out-alt"></i></span>
                  <div class="fw-semibold">Logout</div>
                </a>
              </li>
            </ul>
          </div><!-- /user dropdown -->
          <?php endif; ?>
        </div>
      </div><!-- /topbar-main -->

      <div class="topbar-search">
        <form action="<?= BASE_URL ?>/search.php" method="get" role="search" aria-label="Site search">
          <input class="search-input" type="search" name="q"
                 value="<?= e(is_string($_GET['q'] ?? null) ? (string)$_GET['q'] : '') ?>"
                 placeholder="Search facilities, announcements..." autocomplete="off">
          <button class="btn btn-primary search-btn" type="submit">
            <i class="fas fa-search me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Search</span>
          </button>
        </form>
      </div>

    </header><!-- /topbar -->

    <!-- PAGE CONTENT AREA (opened here; closed in footer.php) -->
    <div class="page-content">

      <?php
      // Dashboard hero section (structure inspired by reference image)
      if ($script === 'dashboard.php'):
          $_spotTitle = 'Welcome back';
          $_spotBody  = 'Check announcements for closures, maintenance, and updates.';
          if (isset($pdo)) {
              try {
                  $a = fetchActiveAnnouncements($pdo, 1);
                  if (!empty($a)) {
                      $_spotTitle = $a[0]['title'] ?? $_spotTitle;
                      $_spotBody  = $a[0]['body'] ?? $_spotBody;
                  }
              } catch (Throwable $e) {}
          }
      ?>
        <section class="page-hero <?= $role === 'admin' ? 'page-hero-admin' : '' ?>" aria-label="Dashboard hero">
          <div class="hero-inner">
            <div class="hero-bg" style="background-image:linear-gradient(135deg, rgba(37,99,235,.25), rgba(124,58,237,.18)), url('<?= BASE_URL ?>/images/MUSEUM.jpg');"></div>
            <div class="hero-overlay" aria-hidden="true"></div>
            <div class="hero-content">
              <h2 class="hero-title"><?= e(APP_NAME) ?></h2>
              <p class="hero-sub mb-0">Book facilities, manage requests, and stay updated.</p>

              <?php if ($role === 'admin'): ?>
                <?php
                  $facilitySlides = [
                    ['name' => 'CL Room 1',    'file' => 'CL 1.jpg'],
                    ['name' => 'CL Room 2',    'file' => 'CL 2.jpg'],
                    ['name' => 'CL Room 3',    'file' => 'CL 3.jpg'],
                    ['name' => 'EIRC',         'file' => 'EIRC.jpg'],
                    ['name' => 'Museum',       'file' => 'MUSEUM.jpg'],
                    ['name' => 'Reading Area', 'file' => 'Reading Area.jpg'],
                    ['name' => 'Faculty Area', 'file' => 'Faculty Area.jpg'],
                  ];
                ?>
                <div class="spotlight spotlight-carousel">
                  <div id="facilityHeroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="3500" style="overflow:hidden;">
                    <div class="carousel-inner" style="height:100%;">
                      <?php foreach ($facilitySlides as $i => $s): ?>
                        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                          <img src="<?= BASE_URL ?>/images/<?= rawurlencode($s['file']) ?>" class="d-block w-100 facility-slide" alt="<?= e($s['name']) ?>" loading="eager">
                          <div class="carousel-caption d-none d-md-block">
                            <h6 class="mb-0 fw-bold"><?= e($s['name']) ?></h6>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#facilityHeroCarousel" data-bs-slide="prev">
                      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                      <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#facilityHeroCarousel" data-bs-slide="next">
                      <span class="carousel-control-next-icon" aria-hidden="true"></span>
                      <span class="visually-hidden">Next</span>
                    </button>
                  </div>
                </div>
              <?php else: ?>
                <div class="spotlight">
                  <div class="label">In the spotlight</div>
                  <div class="stitle"><?= e($_spotTitle) ?></div>
                  <p class="sbody"><?= e(mb_strimwidth($_spotBody, 0, 160, '...')) ?></p>
                  <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/announcements.php">View announcements</a>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </section>
      <?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var sidebar   = document.getElementById('sidebar');
  var overlay   = document.getElementById('sidebarOverlay');
  var hamburger = document.getElementById('hamburger');
  var closeBtn  = document.getElementById('sidebarClose');

  if (!sidebar || !overlay || !hamburger) return;

  var collapseBtn = document.getElementById('sidebarCollapseToggle');

  function syncAria() {
    var desktop = window.innerWidth >= 992;
    var hidden = document.body.classList.contains('sidebar-hidden');
    if (collapseBtn) collapseBtn.setAttribute('aria-pressed', hidden ? 'true' : 'false');
    if (sidebar) sidebar.setAttribute('aria-hidden', (desktop && hidden) ? 'true' : 'false');
    try { sidebar.inert = (desktop && hidden); } catch (e) {}
  }

  function setSidebarHidden(isHidden) {
    document.body.classList.toggle('sidebar-hidden', !!isHidden);
    // Prevent conflicts with the legacy collapsed mode
    document.body.classList.remove('sidebar-collapsed');

    try {
      localStorage.setItem('sidebarHidden', isHidden ? '1' : '0');
      localStorage.removeItem('sidebarCollapsed');
    } catch (e) {}

    if (isHidden) closeSidebar();
    syncAria();
  }

  // Respect stored preference (and migrate old 'collapsed' key)
  try {
    var hiddenPref = localStorage.getItem('sidebarHidden');
    var shouldHide = hiddenPref === '1';
    if (hiddenPref === null) {
      // Back-compat: treat old collapsed preference as hidden (new behavior)
      shouldHide = localStorage.getItem('sidebarCollapsed') === '1';
    }
    if (shouldHide) document.body.classList.add('sidebar-hidden');
    localStorage.removeItem('sidebarCollapsed');
  } catch (e) { /* ignore */ }

  if (collapseBtn) {
    collapseBtn.addEventListener('click', function () {
      setSidebarHidden(!document.body.classList.contains('sidebar-hidden'));
    });
  }

  syncAria();


  function openSidebar() {
    sidebar.classList.add('sidebar-open');
    overlay.classList.add('active');
    overlay.setAttribute('aria-hidden', 'false');
    hamburger.setAttribute('aria-expanded', 'true');
    document.body.classList.add('sidebar-body-open');
  }

  function closeSidebar() {
    sidebar.classList.remove('sidebar-open');
    overlay.classList.remove('active');
    overlay.setAttribute('aria-hidden', 'true');
    hamburger.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('sidebar-body-open');
  }

  // Safety: if anything left the overlay "active" (or the body locked), clear it on load.
  closeSidebar();

  hamburger.addEventListener('click', function () {
    sidebar.classList.contains('sidebar-open') ? closeSidebar() : openSidebar();
  });

  if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
  overlay.addEventListener('click', closeSidebar);

  window.addEventListener('resize', function () {
    if (window.innerWidth >= 992) closeSidebar();
  });

  // Auto-start admin carousel when available (init on window.load so bootstrap is loaded)
  if (document.getElementById('facilityHeroCarousel')) {
    window.addEventListener('load', function(){
      var c = document.getElementById('facilityHeroCarousel');
      if (c && window.bootstrap && bootstrap.Carousel) {
        new bootstrap.Carousel(c, { interval: 3500, ride: 'carousel', pause: false });
      }
    });
  }
});

function markNotifsRead() {
  var badge = document.getElementById('notifBadge');
  if (badge) badge.style.display = 'none';
  fetch('<?= BASE_URL ?>/admin/notify.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=mark_all'
  });
}

function markAllRead() {
  fetch('<?= BASE_URL ?>/admin/notify.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=mark_all'
  }).then(function () { location.reload(); });
}
</script>