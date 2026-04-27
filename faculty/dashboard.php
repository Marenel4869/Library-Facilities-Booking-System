<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('faculty');

$uid = $_SESSION['user_id'];

// ── Facility image map ────────────────────────────────────────────────────────
$facilityImages = [
    'CL Room 1'    => 'CL 1.jpg',
    'CL Room 2'    => 'CL 2.jpg',
    'CL Room 3'    => 'CL 3.jpg',
    'EIRC'         => 'EIRC.jpg',
    'Museum'       => 'MUSEUM.jpg',
    'Reading Area' => 'Reading Area.jpg',
    'Faculty Area' => 'Faculty Area.jpg',
];

// DB schema upgrades + default facility seeding are handled centrally in config/database.php

// ── Stats ─────────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT
    COUNT(*) AS total,
    SUM(status="pending")   AS pending,
    SUM(status="approved")  AS approved,
    SUM(status="rejected")  AS rejected
    FROM bookings WHERE user_id = ?');
$stmt->execute([$uid]);
$counts = $stmt->fetch();

// ── Load all faculty-visible facilities ───────────────────────────────────────
$allFacilities = $pdo->query(
    'SELECT * FROM facilities WHERE status="active" ORDER BY facility_group, name'
)->fetchAll();

// Split into groups
$clFacilities      = array_filter($allFacilities, fn($f) => $f['facility_group'] === 'cl');
$morelosFacilities = array_filter($allFacilities, fn($f) => $f['facility_group'] === 'morelos');
$libraryFacilities = array_filter($allFacilities, fn($f) => $f['facility_group'] === 'library');

// ── My Bookings ───────────────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$bsql   = 'SELECT b.*, f.name AS facility_name, f.instant_booking, f.facility_group
           FROM bookings b JOIN facilities f ON b.facility_id = f.id
           WHERE b.user_id = ?';
$bparam = [$uid];
if ($filterStatus) { $bsql .= ' AND b.status = ?'; $bparam[] = $filterStatus; }
$bsql .= ' ORDER BY b.created_at DESC LIMIT 30';
$bstmt = $pdo->prepare($bsql);
$bstmt->execute($bparam);
$myBookings = $bstmt->fetchAll();

// ── Side panels content (Announcements) ──────────────────────────────────────
$dashAnns = [];
try {
    $dashAnns = fetchActiveAnnouncements($pdo, 3);
} catch (PDOException $e) {
    $dashAnns = [];
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container py-4">

<?= showFlash() ?>

<!-- ── Welcome bar ──────────────────────────────────────────────────────── -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h4 class="mb-0 fw-bold">👋 Welcome, <?= e($_SESSION['name']) ?>!</h4>
    <small class="text-muted">Dashboard — <?= date('l, F j, Y') ?></small>
  </div>
  <a href="#my-bookings" class="btn btn-outline-primary btn-sm">
    <i class="fas fa-list me-1"></i>My Bookings
  </a>
</div>

<!-- ── Library Schedule ─────────────────────────────────────────────────── -->
<div class="library-schedule-card mb-5">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <div class="d-flex align-items-center gap-2">
      <div class="schedule-icon"><i class="fas fa-clock"></i></div>
      <div>
        <div class="schedule-title">Library Schedule</div>
        <div class="schedule-subtitle">Quick reminder for bookings</div>
      </div>
    </div>
  </div>

  <div class="schedule-reminder mt-3">
    <i class="fas fa-circle-info me-1"></i>
    Due to the 4 days program implemented, <strong>Thursday and Friday are not open for booking</strong>.
  </div>

  <div class="schedule-rows">
    <div class="schedule-row">
      <span class="day">Monday · Tuesday</span>
      <span class="time">7:00 AM – 7:00 PM</span>
    </div>
    <div class="schedule-row">
      <span class="day">Wednesday</span>
      <span class="time">7:00 AM – 5:00 PM</span>
    </div>
    <div class="schedule-row closed">
      <span class="day">Thursday · Friday</span>
      <span class="time">Not open for booking</span>
    </div>
    <div class="schedule-row">
      <span class="day">Saturday</span>
      <span class="time">7:00 AM – 5:00 PM</span>
    </div>
  </div>
</div>

<!-- ── Quick Info ───────────────────────────────────────────────────────── -->
<div class="row g-3 mb-5">
  <div class="col-md-4">
    <div class="card facility-card border-0 shadow-sm overflow-hidden h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="facility-icon request"><i class="fas fa-clipboard-check"></i></div>
          <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Tips</span>
        </div>
        <h6 class="fw-bold mb-2">Booking Rules / Tips</h6>
        <ul class="info-list">
          <li>Bring your school ID when using the facility.</li>
          <li>Arrive on the designated time selected to avoid losing your slot.</li>
          <li>Bookings will be removed if users didn't arrived within grace period (5 minutes).</li>
          <li>Cancel 15 minutes early if you won’t proceed.</li>
          <li>Follow facility-specific slots and capacity limits.</li>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card facility-card border-0 shadow-sm overflow-hidden h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="facility-icon instant"><i class="fas fa-bullhorn"></i></div>
          <a class="small text-primary text-decoration-none" href="<?= BASE_URL ?>/announcements.php">View all</a>
        </div>
        <h6 class="fw-bold mb-2">Announcements</h6>
        <?php if (empty($dashAnns)): ?>
          <div class="text-muted small">No announcements right now.</div>
        <?php else: ?>
          <div class="d-flex flex-column gap-2">
            <?php foreach ($dashAnns as $a): ?>
              <div class="announcement-item">
                <div class="d-flex justify-content-between gap-2">
                  <div class="title"><?= e($a['title']) ?></div>
                  <div class="meta"><?= date('M j', strtotime($a['created_at'])) ?></div>
                </div>
                <div class="body"><?= e(truncateText($a['body'], 95)) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card facility-card border-0 shadow-sm overflow-hidden h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="facility-icon instant"><i class="fas fa-life-ring"></i></div>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Support</span>
        </div>
        <h6 class="fw-bold mb-2">Need Help?</h6>
        <div class="help-mini">
          <div><strong>Email:</strong> <a href="mailto:morelos.library@urios.edu.ph">morelos.library@urios.edu.ph</a></div>
          <div class="mt-1"><strong>Helpdesk:</strong> <a href="mailto:library.helpdesk@urios.edu.ph">library.helpdesk@urios.edu.ph</a></div>
          <div class="mt-1"><strong>Office hours:</strong> Mon–Sat · 7:00 AM – 5:00 PM</div>
        </div>
        <a class="btn btn-outline-primary btn-sm w-100 mt-3" href="<?= BASE_URL ?>/help.php">
          <i class="fas fa-flag me-1"></i>Report an issue
        </a>
      </div>
    </div>
  </div>
</div>

<!-- ── Stats ────────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-5">
  <div class="col-6 col-md-3">
    <div class="stat-card"><div class="label">Total</div><div class="value text-primary"><?= (int)$counts['total'] ?></div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="border-left-color:#ffc107"><div class="label">Pending</div><div class="value text-warning"><?= (int)$counts['pending'] ?></div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="border-left-color:#198754"><div class="label">Approved</div><div class="value text-success"><?= (int)$counts['approved'] ?></div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="border-left-color:#dc3545"><div class="label">Rejected</div><div class="value text-danger"><?= (int)$counts['rejected'] ?></div></div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════
     SECTION 1 — CL Facilities (Instant Booking)
     ══════════════════════════════════════════════════════════════════════ -->
<div class="section-label mb-3">
  <span class="badge bg-success fs-6 me-2"><i class="fas fa-bolt me-1"></i>CL Facilities — Instant Booking</span>
  <span class="text-muted small">Confirmed immediately · 8:00 AM – 6:00 PM</span>
</div>

<div class="row g-3 mb-5">
  <?php if (empty($clFacilities)): ?>
    <div class="col-12"><p class="text-muted">No CL facilities found.</p></div>
  <?php endif; ?>
  <?php foreach ($clFacilities as $f): ?>
  <div class="col-sm-6 col-md-4">
    <div class="card facility-card h-100 border-0 shadow-sm overflow-hidden">
      <?php if (isset($facilityImages[$f['name']])): ?>
      <img src="<?= BASE_URL ?>/images/<?= rawurlencode($facilityImages[$f['name']]) ?>"
           alt="<?= e($f['name']) ?>"
           class="card-img-top"
           style="height:160px;object-fit:cover;"
           onerror="this.style.display='none'">
      <?php endif; ?>
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="facility-icon facility-icon-cl"><i class="fas fa-desktop"></i></div>
          <span class="badge bg-success-subtle text-success border border-success-subtle">Instant</span>
        </div>
        <h5 class="fw-bold mb-1"><?= e($f['name']) ?></h5>
        <p class="text-muted small mb-2"><?= e($f['location']) ?></p>
        <div class="facility-meta">
          <span><i class="fas fa-users text-success me-1"></i>Max <?= $f['capacity'] ?> people</span>
          <span><i class="fas fa-clock text-success me-1"></i>8:00 AM – 6:00 PM</span>
        </div>
        <p class="mt-2 mb-0 small text-success fw-semibold"><i class="fas fa-check-circle me-1"></i>No approval needed</p>
      </div>
      <div class="card-footer bg-transparent border-0 pb-3 px-3">
        <button class="btn btn-success w-100 btn-open-modal"
          data-fac='<?= json_encode([
            "id"              => $f['id'],
            "name"            => $f['name'],
            "capacity"        => $f['capacity'],
            "instant"         => 1,
            "requires_letter" => 0,
            "max_day"         => 0,
            "slots"           => null,
            "purposes"        => null,
            "group"           => "cl",
          ]) ?>'>
          <i class="fas fa-bolt me-1"></i>Book Now
        </button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════════════
     SECTION 2 — Morelos Building Facilities (Request-Based)
     ══════════════════════════════════════════════════════════════════════ -->
<div class="section-label mb-3">
  <span class="badge bg-primary fs-6 me-2"><i class="fas fa-building me-1"></i>Morelos Building</span>
  <span class="text-muted small">Admin approval required · fixed time slots</span>
</div>

<div class="row g-3 mb-5">
  <?php if (empty($morelosFacilities)): ?>
    <div class="col-12"><p class="text-muted">No Morelos facilities found.</p></div>
  <?php endif; ?>
  <?php foreach ($morelosFacilities as $f):
    $slots    = $f['allowed_slots']   ? json_decode($f['allowed_slots'],   true) : [];
    $purposes = $f['purpose_options'] ? json_decode($f['purpose_options'], true) : [];
    $maxDay   = (int)$f['max_bookings_day'];
    $openHrs  = substr($f['open_time'],0,5) . ' – ' . substr($f['close_time'],0,5);
  ?>
  <div class="col-sm-6 col-md-5">
    <div class="card facility-card facility-morelos h-100 border-0 shadow-sm overflow-hidden">
      <?php if (isset($facilityImages[$f['name']])): ?>
      <img src="<?= BASE_URL ?>/images/<?= rawurlencode($facilityImages[$f['name']]) ?>"
           alt="<?= e($f['name']) ?>"
           class="card-img-top"
           style="height:160px;object-fit:cover;"
           onerror="this.style.display='none'">
      <?php endif; ?>
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="facility-icon facility-icon-morelos"><i class="fas fa-building-columns"></i></div>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Approval</span>
        </div>
        <h5 class="fw-bold mb-1"><?= e($f['name']) ?></h5>
        <p class="text-muted small mb-2"><?= e($f['location']) ?></p>
        <div class="facility-meta mb-2">
          <span><i class="fas fa-users text-primary me-1"></i>Capacity: <?= $f['capacity'] ?></span>
          <?php if ($maxDay > 0): ?>
          <span><i class="fas fa-calendar-day text-warning me-1"></i>Max <?= $maxDay ?> bookings/day</span>
          <?php endif; ?>
        </div>
        <?php if (!empty($slots)): ?>
        <div class="mb-2">
          <small class="text-muted fw-semibold d-block mb-1">Available Time Slots:</small>
          <?php foreach ($slots as $sl): ?>
            <span class="badge bg-light text-dark border me-1 mb-1 fw-normal"><?= e($sl['label']) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($purposes)): ?>
        <div>
          <small class="text-muted fw-semibold d-block mb-1">Purpose options:</small>
          <small class="text-muted"><?= implode(', ', array_map('htmlspecialchars', $purposes)) ?></small>
        </div>
        <?php endif; ?>
      </div>
      <div class="card-footer bg-transparent border-0 pb-3 px-3">
        <button class="btn btn-primary w-100 btn-open-modal"
          data-fac='<?= json_encode([
            "id"              => (int)$f['id'],
            "name"            => $f['name'],
            "capacity"        => (int)$f['capacity'],
            "instant"         => 0,
            "requires_letter" => (int)$f['requires_letter'],
            "max_day"         => $maxDay,
            "slots"           => $slots,
            "purposes"        => $purposes,
            "group"           => "morelos",
          ]) ?>'>
          <i class="fas fa-paper-plane me-1"></i>Request Booking
        </button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════════════
     SECTION 3 — Library Facilities (Request-Based)
     ══════════════════════════════════════════════════════════════════════ -->
<div class="section-label mb-3">
  <span class="badge bg-warning text-dark fs-6 me-2"><i class="fas fa-landmark me-1"></i>Library Facilities</span>
  <span class="text-muted small">Admin approval required · upload request letter</span>
</div>

<div class="row g-3 mb-5">
  <?php if (empty($libraryFacilities)): ?>
    <div class="col-12"><p class="text-muted">No library facilities found.</p></div>
  <?php endif; ?>
  <?php foreach ($libraryFacilities as $f): ?>
  <div class="col-sm-6 col-md-4">
    <div class="card facility-card h-100 border-0 shadow-sm overflow-hidden">
      <?php if (isset($facilityImages[$f['name']])): ?>
      <img src="<?= BASE_URL ?>/images/<?= rawurlencode($facilityImages[$f['name']]) ?>"
           alt="<?= e($f['name']) ?>"
           class="card-img-top"
           style="height:160px;object-fit:cover;"
           onerror="this.style.display='none'">
      <?php endif; ?>
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="facility-icon" style="background:#fef9c3;color:#ca8a04;width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;">
            <i class="fas fa-landmark"></i>
          </div>
          <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Approval</span>
        </div>
        <h5 class="fw-bold mb-1"><?= e($f['name']) ?></h5>
        <p class="text-muted small mb-2"><?= e($f['location']) ?></p>
        <div class="facility-meta">
          <span><i class="fas fa-users text-warning me-1"></i>Max <?= $f['capacity'] ?> people</span>
          <span><i class="fas fa-clock text-warning me-1"></i>8:00 AM – 6:00 PM</span>
        </div>
        <p class="mt-2 mb-0 small text-warning fw-semibold">
          <i class="fas fa-upload me-1"></i>Request letter required (PDF/Image)
        </p>
      </div>
      <div class="card-footer bg-transparent border-0 pb-3 px-3">
        <button class="btn btn-warning w-100 btn-open-modal"
          data-fac='<?= json_encode([
            "id"              => (int)$f['id'],
            "name"            => $f['name'],
            "capacity"        => (int)$f['capacity'],
            "instant"         => 0,
            "requires_letter" => (int)($f['requires_letter'] ?? 1),
            "max_day"         => 0,
            "slots"           => null,
            "purposes"        => null,
            "group"           => "library",
          ]) ?>'>
          <i class="fas fa-paper-plane me-1"></i>Request Booking
        </button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════════════
<!-- ══════════════════════════════════════════════════════════════════════
     SECTION 4 — My Bookings
     ══════════════════════════════════════════════════════════════════════ -->
<div id="my-bookings" class="card border-0 shadow-sm" style="border-radius:16px!important;overflow:hidden;">
  <!-- Header -->
  <div class="card-header border-0 p-0">
    <div style="background:linear-gradient(135deg,#2563eb 0%,#7c3aed 100%);padding:1rem 1.25rem;">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
          <div style="width:36px;height:36px;background:rgba(255,255,255,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-list-alt text-white"></i>
          </div>
          <div>
            <h5 class="mb-0 fw-bold text-white">My Bookings</h5>
            <small style="color:rgba(255,255,255,.65);font-size:.72rem;">Track all your facility bookings</small>
          </div>
        </div>
        <?php
          $bStatusCounts = ['pending'=>0,'approved'=>0,'rejected'=>0,'cancelled'=>0];
          foreach ($myBookings as $bk) if (isset($bStatusCounts[$bk['status']])) $bStatusCounts[$bk['status']]++;
          $bTotal = count($myBookings);
        ?>
        <?php if (!$filterStatus): ?>
        <span style="background:rgba(255,255,255,.18);color:#fff;border-radius:100px;padding:.2rem .75rem;font-size:.75rem;font-weight:600;">
          <?= $bTotal ?> total
        </span>
        <?php endif; ?>
      </div>
    </div>
    <!-- Filter pills -->
    <div style="background:#f8faff;border-bottom:1px solid #e8eeff;padding:.65rem 1.25rem;display:flex;flex-wrap:wrap;gap:.4rem;">
      <?php
        $bFilters = ['' => ['All','fas fa-border-all','#2563eb'], 'pending' => ['Pending','fas fa-clock','#d97706'], 'approved' => ['Approved','fas fa-check-circle','#059669'], 'rejected' => ['Rejected','fas fa-times-circle','#dc2626'], 'cancelled' => ['Cancelled','fas fa-ban','#64748b']];
        foreach ($bFilters as $v => [$l, $bIcon, $bColor]):
          $bActive = ($filterStatus === $v);
          $bCnt = $v === '' ? $bTotal : ($bStatusCounts[$v] ?? 0);
      ?>
      <a href="?status=<?= $v ?>#my-bookings" style="
        display:inline-flex;align-items:center;gap:.35rem;
        padding:.3rem .85rem;border-radius:100px;font-size:.8rem;font-weight:600;
        text-decoration:none;transition:all .2s;
        background:<?= $bActive ? $bColor : 'white' ?>;
        color:<?= $bActive ? '#fff' : $bColor ?>;
        border:1.5px solid <?= $bColor ?>;
        box-shadow:<?= $bActive ? '0 2px 8px rgba(0,0,0,.15)' : 'none' ?>;">
        <i class="<?= $bIcon ?>" style="font-size:.7rem;"></i>
        <?= $l ?>
        <?php if ($bCnt > 0 && !$bActive): ?>
          <span style="background:<?= $bColor ?>;color:#fff;border-radius:100px;padding:.05rem .4rem;font-size:.65rem;margin-left:.1rem;"><?= $bCnt ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card-body p-0">
    <?php if (empty($myBookings)): ?>
      <div class="text-center py-5 px-3" style="background:linear-gradient(180deg,#f8faff 0%,#fff 100%);">
        <div style="width:80px;height:80px;background:linear-gradient(135deg,#eff6ff,#f5f3ff);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;border:2px solid #e8eeff;">
          <i class="fas fa-calendar-plus" style="font-size:1.8rem;background:linear-gradient(135deg,#2563eb,#7c3aed);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i>
        </div>
        <h6 class="fw-bold mb-1" style="color:#0f172a;">
          <?= $filterStatus ? 'No ' . ucfirst($filterStatus) . ' bookings' : 'No bookings yet' ?>
        </h6>
        <p class="text-muted small mb-3">
          <?= $filterStatus ? 'Try a different filter to see other bookings.' : 'Browse the facilities above and make your first booking.' ?>
        </p>
        <?php if ($filterStatus): ?>
          <a href="?status=#my-bookings" class="btn btn-sm btn-outline-primary" style="border-radius:100px;">
            <i class="fas fa-border-all me-1"></i>View All
          </a>
        <?php endif; ?>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr>
          <th>#</th><th>Facility</th><th>Group</th><th>Date</th>
          <th>Time</th><th>Attendees</th><th>Purpose</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($myBookings as $b): ?>
          <tr>
            <td class="text-muted small">#<?= str_pad($b['id'],4,'0',STR_PAD_LEFT) ?></td>
            <td class="fw-semibold"><?= e($b['facility_name']) ?></td>
            <td>
              <?php if ($b['facility_group']==='cl'): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle small">CL</span>
              <?php elseif ($b['facility_group']==='morelos'): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">Morelos</span>
              <?php else: ?>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle small">Library</span>
              <?php endif; ?>
            </td>
            <td><?= date('M j, Y', strtotime($b['booking_date'])) ?></td>
            <td class="small"><?= date('g:i A', strtotime($b['start_time'])) ?> – <?= date('g:i A', strtotime($b['end_time'])) ?></td>
            <td><?= (int)$b['attendees_count'] ?></td>
            <td class="small text-muted" style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                title="<?= e($b['purpose']) ?>"><?= e($b['purpose']) ?></td>
            <td><?= statusBadge($b['status']) ?></td>
            <td>
              <div class="d-flex gap-1">
                <a href="<?= BASE_URL ?>/faculty/view_booking.php?id=<?= $b['id'] ?>"
                   class="btn btn-sm btn-outline-primary">View</a>
                <?php if ($b['status']==='pending'): ?>
                <button class="btn btn-sm btn-outline-danger btn-cancel"
                        data-id="<?= $b['id'] ?>" data-name="<?= e($b['facility_name']) ?>">Cancel</button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════
     BOOKING MODAL
     ══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header border-0 pb-0">
        <div>
          <h5 class="modal-title fw-bold" id="modalTitle">Book Facility</h5>
          <p class="text-muted small mb-0" id="modalSubtitle"></p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div id="modalAlert" class="d-none mb-3"></div>

        <form id="bookingForm" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="facility_id" id="hiddenFacilityId">

          <!-- Chips row -->
          <div id="chipRow" class="d-flex flex-wrap gap-2 mb-3"></div>

          <!-- Date -->
          <div class="mb-3">
            <label class="form-label fw-semibold small">Date <span class="text-danger">*</span></label>
            <input type="date" name="booking_date" id="bookingDate" class="form-control"
                   required>
          </div>

          <!-- Time Slots (for Morelos) — now uses select dropdowns like CL -->
          <div class="mb-3" id="slotGroup" style="display:none">
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label fw-semibold small">Start Time <span class="text-danger">*</span></label>
                <select name="start_time" id="slotStart" class="form-select">
                  <?php
                    $s = strtotime('07:00'); $e = strtotime('17:00');
                    for ($t = $s; $t <= $e; $t += 1800)
                      echo '<option value="'.date('H:i',$t).'"'.($t===$s?' selected':'').'>'.date('g:i A',$t).'</option>';
                  ?>
                </select>
                <div class="form-text small">From 7:00 AM</div>
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold small">End Time <span class="text-danger">*</span></label>
                <select name="end_time" id="slotEnd" class="form-select">
                  <?php
                    $s2 = strtotime('07:30'); $e2 = strtotime('17:00');
                    for ($t = $s2; $t <= $e2; $t += 1800)
                      echo '<option value="'.date('H:i',$t).'"'.($t===strtotime('08:00')?' selected':'').'>'.date('g:i A',$t).'</option>';
                  ?>
                </select>
                <div class="form-text small">Until 5:00 PM</div>
              </div>
            </div>
            <input type="hidden" id="hiddenStart">
            <input type="hidden" id="hiddenEnd">
          </div>

          <!-- Custom time (for CL) -->
          <div id="customTimeGroup">
            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label fw-semibold small">Start Time <span class="text-danger">*</span></label>
                <select name="start_time_custom" id="startTimeCustom" class="form-select">
                  <?php
                    $s = strtotime('08:00'); $e = strtotime('18:00');
                    for ($t = $s; $t <= $e; $t += 1800)
                      echo '<option value="'.date('H:i',$t).'"'.($t===$s?' selected':'').'>'.date('g:i A',$t).'</option>';
                  ?>
                </select>
                <div class="form-text small">From 8:00 AM</div>
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold small">End Time <span class="text-danger">*</span></label>
                <select name="end_time_custom" id="endTimeCustom" class="form-select">
                  <?php
                    $s2 = strtotime('08:30'); $e2 = strtotime('18:00');
                    for ($t = $s2; $t <= $e2; $t += 1800)
                      echo '<option value="'.date('H:i',$t).'"'.($t===strtotime('09:00')?' selected':'').'>'.date('g:i A',$t).'</option>';
                  ?>
                </select>
                <div class="form-text small">Until 6:00 PM</div>
              </div>
            </div>
          </div>

          <!-- Attendees -->
          <div class="mb-3">
            <label class="form-label fw-semibold small">
              Attendees <span class="text-danger">*</span>
              <span class="text-muted" id="capacityNote"></span>
            </label>
            <input type="number" name="attendees_count" id="attendeesCount"
                   class="form-control" min="1" value="1" required>
          </div>

          <!-- Program -->
          <div class="mb-3">
            <label class="form-label fw-semibold small">Program <span class="text-danger">*</span></label>
            <select name="program" id="programSelect" class="form-select" required>
              <option value="">-- Select Program --</option>
              <?php foreach (programOptions() as $p): ?>
                <option value="<?= e($p) ?>"><?= e($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Purpose dropdown (Morelos) -->
          <div class="mb-3" id="purposeSelectGroup" style="display:none">
            <label class="form-label fw-semibold small">Purpose <span class="text-danger">*</span></label>
            <select name="purpose_choice" id="purposeChoice" class="form-select">
              <option value="">-- Select Purpose --</option>
            </select>
          </div>

          <!-- "Others" text (Morelos — Faculty Area) -->
          <div class="mb-3" id="othersGroup" style="display:none">
            <label class="form-label fw-semibold small">Specify Purpose <span class="text-danger">*</span></label>
            <input type="text" name="purpose_other" id="purposeOther"
                   class="form-control" placeholder="Describe the activity...">
          </div>

          <!-- Purpose free text (CL) -->
          <div class="mb-3" id="purposeTextGroup">
            <label class="form-label fw-semibold small">Purpose <span class="text-danger">*</span></label>
            <textarea name="purpose" id="purposeText" class="form-control" rows="2"
                      placeholder="Briefly describe the purpose..." required></textarea>
          </div>

          <!-- Request Letter (optional) -->
          <div class="mb-2" id="letterGroup" style="display:none">
            <label class="form-label fw-semibold small">Request Letter <span class="text-danger">*</span></label>
            <input type="file" name="request_letter" id="requestLetter"
                   class="form-control" accept=".pdf,.jpg,.jpeg,.png">
            <div class="form-text small text-muted">PDF, JPG, PNG — max 5 MB</div>
          </div>

          <!-- Notices -->
          <div id="instantNotice" class="alert alert-success d-none py-2 small">
            <i class="fas fa-bolt me-1"></i><strong>Instant:</strong> Confirmed immediately upon submission.
          </div>
          <div id="requestNotice" class="alert alert-primary d-none py-2 small">
            <i class="fas fa-clock me-1"></i><strong>Request:</strong> Awaiting admin approval after submission.
          </div>
          <div id="maxDayNotice" class="alert alert-warning d-none py-2 small">
            <i class="fas fa-exclamation-triangle me-1"></i>
            <span id="maxDayText"></span>
          </div>
        </form>
      </div>

      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="submitBtn">
          <span id="submitText"><i class="fas fa-paper-plane me-1"></i>Submit</span>
          <span id="submitSpinner" class="d-none">
            <span class="spinner-border spinner-border-sm me-1"></span>Submitting…
          </span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h6 class="modal-title fw-bold">Cancel Booking</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-0">
        <p class="text-muted small mb-0">Cancel booking for <strong id="cancelName"></strong>?</p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button class="btn btn-light btn-sm" data-bs-dismiss="modal">No</button>
        <button class="btn btn-danger btn-sm" id="confirmCancel">Yes, Cancel</button>
      </div>
    </div>
  </div>
</div>

<?php echo '<script>const BASE_URL=' . json_encode(BASE_URL) . ';</script>'; ?>

<style>
  .facility-icon-cl      { background:#d1fae5; color:#059669; width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.25rem; }
  .facility-icon-morelos { background:#dbeafe; color:#1d4ed8; width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.25rem; }
  .slot-btn { border:2px solid #dee2e6; background:#f8fafc; border-radius:8px; padding:0.45rem 1rem; font-size:0.88rem; cursor:pointer; transition:all .2s; }
  .slot-btn.selected { border-color:#0d6efd; background:#eff6ff; color:#1d4ed8; font-weight:600; }
</style>
<script src="<?= BASE_URL ?>/assets/js/faculty_dashboard.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>