<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('student');

$uid = $_SESSION['user_id'];

// ── Facility image map ────────────────────────────────────────────────────────
$facilityImages = [
    'CL Room 1'    => 'CL 1.jpg',
    'CL Room 2'    => 'CL 2.jpg',

    'EIRC'         => 'EIRC.jpg',
    'Museum'       => 'MUSEUM.jpg',

];

// DB schema upgrades + default facility seeding are handled centrally in config/database.php

// ── Stats ────────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT
    COUNT(*) AS total,
    SUM(status="pending")   AS pending,
    SUM(status="approved")  AS approved,
    SUM(status="rejected")  AS rejected
    FROM bookings WHERE user_id = ?');
$stmt->execute([$uid]);
$counts = $stmt->fetch();

// ── Facilities ───────────────────────────────────────────────────────────────
$studentExclude = ['CL Room 3', 'Faculty Area', 'Reading Area'];
$excludePlaceholders = implode(',', array_fill(0, count($studentExclude), '?'));

$stmtInst = $pdo->prepare(
    "SELECT * FROM facilities WHERE instant_booking=1 AND status='active'
     AND name NOT IN ($excludePlaceholders) ORDER BY name"
);
$stmtInst->execute($studentExclude);
$instantFacilities = $stmtInst->fetchAll();

$stmtReq = $pdo->prepare(
    "SELECT * FROM facilities WHERE instant_booking=0 AND status='active'
     AND name NOT IN ($excludePlaceholders) ORDER BY name"
);
$stmtReq->execute($studentExclude);
$requestFacilities = $stmtReq->fetchAll();

// ── Side panels content (Announcements) ──────────────────────────────────────
$dashAnns = [];
try {
    $dashAnns = fetchActiveAnnouncements($pdo, 3);
} catch (PDOException $e) {
    $dashAnns = [];
}

// ── My Requests ──────────────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$sql    = 'SELECT b.*, f.name AS facility_name, f.instant_booking FROM bookings b
           JOIN facilities f ON b.facility_id = f.id
           WHERE b.user_id = ?';
$params = [$uid];
if ($filterStatus) { $sql .= ' AND b.status = ?'; $params[] = $filterStatus; }
$sql .= ' ORDER BY b.created_at DESC LIMIT 20';
$stmt2 = $pdo->prepare($sql);
$stmt2->execute($params);
$myBookings = $stmt2->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container py-4">

<?= showFlash() ?>

<!-- ── Welcome bar ─────────────────────────────────────────── -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h4 class="mb-0 fw-bold">👋 Welcome, <?= e($_SESSION['name']) ?>!</h4>
    <small class="text-muted">Library Facilities Booking — <?= date('l, F j, Y') ?></small>
  </div>
  <a href="#my-requests" class="btn btn-outline-primary btn-sm">
    <i class="fas fa-list me-1"></i>My Requests
  </a>
</div>

<!-- ── Stats ───────────────────────────────────────────────── -->
<div class="row g-3 mb-5">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="label">Total Bookings</div>
      <div class="value text-primary"><?= (int)$counts['total'] ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="border-left-color:#ffc107">
      <div class="label">Pending</div>
      <div class="value text-warning"><?= (int)$counts['pending'] ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="border-left-color:#198754">
      <div class="label">Approved</div>
      <div class="value text-success"><?= (int)$counts['approved'] ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="border-left-color:#dc3545">
      <div class="label">Rejected</div>
      <div class="value text-danger"><?= (int)$counts['rejected'] ?></div>
    </div>
  </div>
</div>

<!-- ── Facilities + Schedule Grid ──────────────────────────── -->
<div class="row g-4 align-items-start mb-5">
  <div class="col-lg-8">

<!-- ── Section 1: Available Facilities (Instant Booking) ───── -->
<div class="section-label mb-3">
  <span class="badge bg-success fs-6 me-2"><i class="fas fa-bolt me-1"></i>Instant Booking</span>
  <span class="text-muted small">No approval needed — confirmed immediately</span>
</div>

<?php $instGridCols = count($instantFacilities) >= 3 ? 3 : (count($instantFacilities) === 2 ? 2 : 1); ?>
<div class="facility-grid facility-grid-<?= $instGridCols ?> mb-5">
  <?php if (empty($instantFacilities)): ?>
    <p class="text-muted mb-0">No instant facilities available.</p>
  <?php endif; ?>
  <?php foreach ($instantFacilities as $f): ?>
  <div class="facility-grid-item">
    <div class="card facility-card h-100 border-0 shadow-sm overflow-hidden">
      <?php if (isset($facilityImages[$f['name']])): ?>
      <img src="<?= BASE_URL ?>/images/<?= rawurlencode($facilityImages[$f['name']]) ?>"
           alt="<?= e($f['name']) ?>"
           class="card-img-top"
           style="height:140px;object-fit:cover;"
           onerror="this.style.display='none'">
      <?php endif; ?>
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="facility-icon instant">
            <i class="fas fa-desktop"></i>
          </div>
          <span class="badge bg-success-subtle text-success border border-success-subtle">Instant</span>
        </div>
        <h5 class="card-title fw-bold mb-1"><?= e($f['name']) ?></h5>
        <p class="text-muted small mb-2"><?= e($f['location']) ?></p>
        <div class="facility-meta">
          <span><i class="fas fa-users text-primary me-1"></i>Max <?= $f['capacity'] ?> people</span>
          <span><i class="fas fa-clock text-primary me-1"></i>7:30 AM – 6:00 PM</span>
        </div>
        <div class="mt-1 mb-3">
          <small class="text-success fw-semibold"><i class="fas fa-check-circle me-1"></i>No approval required</small>
        </div>
      </div>
      <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
        <button type="button" class="btn btn-success w-100 btn-book"
          data-facility-id="<?= $f['id'] ?>"
          data-facility-name="<?= e($f['name']) ?>"
          data-capacity="<?= $f['capacity'] ?>"
          data-instant="1"
          data-requires-letter="0"
          data-bs-toggle="modal"
          data-bs-target="#bookingModal">
          <i class="fas fa-bolt me-1"></i>Book Now
        </button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Section 2: Request-Based Facilities ─────────────────── -->
<div class="section-label mb-3">
  <span class="badge bg-warning text-dark fs-6 me-2"><i class="fas fa-file-alt me-1"></i>Request-Based</span>
  <span class="text-muted small">Admin approval required — upload request letter</span>
</div>

<?php $reqGridCols = count($requestFacilities) >= 3 ? 3 : (count($requestFacilities) === 2 ? 2 : 1); ?>
<div class="facility-grid facility-grid-<?= $reqGridCols ?> mb-5">
  <?php if (empty($requestFacilities)): ?>
    <p class="text-muted mb-0">No request-based facilities available.</p>
  <?php endif; ?>
  <?php foreach ($requestFacilities as $f): ?>
  <div class="facility-grid-item">
    <div class="card facility-card facility-request h-100 border-0 shadow-sm overflow-hidden">
      <?php if (isset($facilityImages[$f['name']])): ?>
      <img src="<?= BASE_URL ?>/images/<?= rawurlencode($facilityImages[$f['name']]) ?>"
           alt="<?= e($f['name']) ?>"
           class="card-img-top"
           style="height:140px;object-fit:cover;"
           onerror="this.style.display='none'">
      <?php endif; ?>
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="facility-icon request">
            <i class="fas fa-landmark"></i>
          </div>
          <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Approval</span>
        </div>
        <h5 class="card-title fw-bold mb-1"><?= e($f['name']) ?></h5>
        <p class="text-muted small mb-2"><?= e($f['location']) ?></p>
        <div class="facility-meta">
          <span><i class="fas fa-users text-warning me-1"></i>Max <?= $f['capacity'] ?> people</span>
          <span><i class="fas fa-clock text-warning me-1"></i>7:30 AM – 6:00 PM</span>
        </div>
        <div class="mt-1 mb-3">
          <small class="text-warning fw-semibold"><i class="fas fa-upload me-1"></i>Request letter required (PDF/Image)</small>
        </div>
      </div>
      <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
        <button type="button" class="btn btn-warning w-100 btn-book"
          data-facility-id="<?= $f['id'] ?>"
          data-facility-name="<?= e($f['name']) ?>"
          data-capacity="<?= $f['capacity'] ?>"
          data-instant="0"
          data-requires-letter="<?= $f['requires_letter'] ?>"
          data-is-eirc="<?= (stripos($f['name'], 'eirc') !== false || stripos($f['name'], 'irc') !== false || stripos($f['name'], 'museum') !== false) ? '1' : '0' ?>"
          data-bs-toggle="modal"
          data-bs-target="#bookingModal">
          <i class="fas fa-paper-plane me-1"></i>Request Booking
        </button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

  </div><!-- /col-lg-8 -->

  <div class="col-lg-4">

    <div class="side-stack">

    <!-- Schedule card styled like Facilities UI -->
    <div class="card facility-card border-0 shadow-sm overflow-hidden schedule-card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="facility-icon instant"><i class="fas fa-calendar-alt"></i></div>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Schedule</span>
        </div>
        <h5 class="card-title fw-bold mb-1">Library Schedule</h5>
        <p class="text-muted small mb-3">Quick reminder for student bookings</p>

        <div class="schedule-reminder">
          <i class="fas fa-circle-info me-1"></i>
          Due to the 4 days program implemented, <strong>Thursday and Friday are not open for booking</strong>.
        </div>

        <div class="schedule-mini mt-3">
          <div class="schedule-mini-row">
            <span>Monday · Tuesday</span>
            <strong>7:00 AM – 7:00 PM</strong>
          </div>
          <div class="schedule-mini-row">
            <span>Wednesday</span>
            <strong>7:00 AM – 5:00 PM</strong>
          </div>
          <div class="schedule-mini-row closed">
            <span>Thursday · Friday</span>
            <strong>Not open for booking</strong>
          </div>
          <div class="schedule-mini-row">
            <span>Saturday</span>
            <strong>7:00 AM – 5:00 PM</strong>
          </div>
        </div>
      </div>
    </div>

    <!-- Booking Rules / Tips -->
    <div class="card facility-card border-0 shadow-sm overflow-hidden mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="facility-icon request"><i class="fas fa-clipboard-check"></i></div>
          <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Tips</span>
        </div>
        <h6 class="fw-bold mb-2">Booking Rules / Tips</h6>
        <ul class="info-list">
          <li>Bring your school ID when using the facility.</li>
          <li>Arrive at the designated time to avoid losing your slot.</li>
          <li>Bookings will be removed if users do not arrive within the 5-minute grace period.</li>
          <li>Cancel at least 15 minutes in advance if you will not proceed.</li>
          <li>Follow facility-specific slots and capacity limits.</li>
        </ul>
      </div>
    </div>

    <!-- Announcements preview (uniform with other cards) -->
    <div class="card facility-card border-0 shadow-sm overflow-hidden mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="facility-icon info"><i class="fas fa-bullhorn"></i></div>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Announcements</span>
        </div>
        <?php if (!empty($dashAnns)): $a = $dashAnns[0]; ?>
          <h6 class="fw-bold mb-1"><?= e($a['title']) ?></h6>
          <div class="text-muted small mb-2"><?= e(truncateText($a['body'], 100)) ?></div>
          <a href="<?= BASE_URL ?>/announcements.php" class="small text-primary">View all announcements</a>
        <?php else: ?>
          <div class="text-muted small">No announcements right now.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Need Help -->
    <div class="card facility-card border-0 shadow-sm overflow-hidden">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="facility-icon instant"><i class="fas fa-life-ring"></i></div>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Support</span>
        </div>
        <h6 class="fw-bold mb-2">Need Help?</h6>
        <div class="help-mini">
          <div class="mt-1"><strong>Helpdesk:</strong> <a href="mailto:library.helpdesk@urios.edu.ph">library.helpdesk@urios.edu.ph</a></div>
          <div class="mt-1"><strong>Office hours:</strong> Mon–Sat · 7:00 AM – 5:00 PM</div>
        </div>
        <a class="btn btn-outline-primary btn-sm w-100 mt-3" href="<?= BASE_URL ?>/help.php">
          <i class="fas fa-flag me-1"></i>Report an issue
        </a>
      </div>
    </div>

    </div><!-- /side-stack -->

  </div><!-- /col-lg-4 -->
</div><!-- /row -->

<!-- ── Section 3: My Requests ──────────────────────────────── -->
<div id="my-requests" class="card border-0 shadow-sm" style="border-radius:16px!important;overflow:hidden;">
  <!-- Header -->
  <div class="card-header border-0 p-0">
    <div style="background:linear-gradient(135deg,#2563eb 0%,#7c3aed 100%);padding:1rem 1.25rem;">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
          <div style="width:36px;height:36px;background:rgba(255,255,255,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-list-alt text-white"></i>
          </div>
          <div>
            <h5 class="mb-0 fw-bold text-white">My Requests</h5>
            <small style="color:rgba(255,255,255,.65);font-size:.72rem;">Track all your facility bookings</small>
          </div>
        </div>
        <!-- Status counts -->
        <div class="d-flex flex-wrap gap-2">
          <?php
            $statusCounts = ['pending'=>0,'approved'=>0,'rejected'=>0,'cancelled'=>0];
            foreach ($myBookings as $b) if (isset($statusCounts[$b['status']])) $statusCounts[$b['status']]++;
            $total = count($myBookings);
          ?>
          <?php if (!$filterStatus): ?>
          <span style="background:rgba(255,255,255,.18);color:#fff;border-radius:100px;padding:.2rem .75rem;font-size:.75rem;font-weight:600;">
            <?= $total ?> total
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <!-- Filter pills row -->
    <div style="background:#f8faff;border-bottom:1px solid #e8eeff;padding:.65rem 1.25rem;display:flex;flex-wrap:wrap;gap:.4rem;">
      <?php
        $filters = ['' => ['All','fas fa-border-all','#2563eb'], 'pending' => ['Pending','fas fa-clock','#d97706'], 'approved' => ['Approved','fas fa-check-circle','#059669'], 'rejected' => ['Rejected','fas fa-times-circle','#dc2626'], 'cancelled' => ['Cancelled','fas fa-ban','#64748b']];
        foreach ($filters as $val => [$lbl, $icon, $color]):
          $active = ($filterStatus === $val);
          $cnt = $val === '' ? count($myBookings) : ($statusCounts[$val] ?? 0);
      ?>
      <a href="?status=<?= $val ?>#my-requests" style="
        display:inline-flex;align-items:center;gap:.35rem;
        padding:.3rem .85rem;border-radius:100px;font-size:.8rem;font-weight:600;
        text-decoration:none;transition:all .2s;
        background:<?= $active ? $color : 'white' ?>;
        color:<?= $active ? '#fff' : $color ?>;
        border:1.5px solid <?= $color ?>;
        box-shadow:<?= $active ? '0 2px 8px rgba(0,0,0,.15)' : 'none' ?>;">
        <i class="<?= $icon ?>" style="font-size:.7rem;"></i>
        <?= $lbl ?>
        <?php if ($cnt > 0 && !$active): ?>
          <span style="background:<?= $color ?>;color:#fff;border-radius:100px;padding:.05rem .4rem;font-size:.65rem;margin-left:.1rem;"><?= $cnt ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card-body p-0">
    <?php if (empty($myBookings)): ?>
      <!-- Enhanced empty state -->
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
          <a href="?status=#my-requests" class="btn btn-sm btn-outline-primary" style="border-radius:100px;">
            <i class="fas fa-border-all me-1"></i>View All
          </a>
        <?php endif; ?>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Facility</th>
            <th>Type</th>
            <th>Date</th>
            <th>Time</th>
            <th>Attendees</th>
            <th>Status</th>
            <th>Submitted</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($myBookings as $b): ?>
          <tr>
            <td class="text-muted small">#<?= str_pad($b['id'], 4, '0', STR_PAD_LEFT) ?></td>
            <td class="fw-semibold"><?= e($b['facility_name']) ?></td>
            <td>
              <?php if ($b['instant_booking']): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle small">Instant</span>
              <?php else: ?>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle small">Request</span>
              <?php endif; ?>
            </td>
            <td><?= date('M j, Y', strtotime($b['booking_date'])) ?></td>
            <td class="small"><?= date('g:i A', strtotime($b['start_time'])) ?> – <?= date('g:i A', strtotime($b['end_time'])) ?></td>
            <td><?= (int)$b['attendees_count'] ?></td>
            <td><?= statusBadge($b['status']) ?></td>
            <td class="text-muted small"><?= date('M j, g:i A', strtotime($b['created_at'])) ?></td>
            <td>
              <div class="d-flex gap-1">
                <a href="<?= BASE_URL ?>/student/view_booking.php?id=<?= $b['id'] ?>"
                   class="btn btn-sm btn-outline-primary">View</a>
                <?php if ($b['status'] === 'pending'): ?>
                <button class="btn btn-sm btn-outline-danger btn-cancel"
                        data-id="<?= $b['id'] ?>"
                        data-name="<?= e($b['facility_name']) ?>">Cancel</button>
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

<!-- ═══════════════ BOOKING MODAL ═══════════════════════════ -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header border-0 pb-0">
        <div>
          <h5 class="modal-title fw-bold" id="bookingModalLabel">Book Facility</h5>
          <p class="text-muted small mb-0" id="modalSubtitle"></p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <!-- Alert area -->
        <div id="modalAlert" class="d-none"></div>

        <form id="bookingForm" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="facility_id" id="modalFacilityId">

          <!-- Info row -->
          <div class="booking-info-row mb-3" id="modalInfoRow"></div>

          <!-- Date & Time -->
          <div class="row g-2 mb-3">
            <div class="col-12">
              <label class="form-label fw-semibold small">Date <span class="text-danger">*</span></label>
              <input type="date" name="booking_date" id="bookingDate" class="form-control"
                     required>
            </div>
          </div>
          <!-- Time Slot (default for most facilities) -->
          <div class="mb-3" id="slotOnlyGroup">
            <label class="form-label fw-semibold small">Time Slot <span class="text-danger">*</span></label>
            <select id="slotSelect" class="form-select" required>
              <option value="">-- Select a slot --</option>
              <option value="07:30|09:00">7:30 AM – 9:00 AM</option>
              <option value="09:00|10:30">9:00 AM – 10:30 AM</option>
              <option value="10:30|12:00">10:30 AM – 12:00 PM</option>
              <option value="12:00|13:30">12:00 PM – 1:30 PM</option>
              <option value="13:30|15:00">1:30 PM – 3:00 PM</option>
              <option value="15:00|16:30">3:00 PM – 4:30 PM</option>
              <option value="16:30|18:00">4:30 PM – 6:00 PM</option>
            </select>
            <div class="form-text text-muted small">Unavailable slots are grayed out and labeled “Booked”.</div>
          </div>

          <!-- Flexible time (Faculty / Reading Area) -->
          <div class="mb-3 d-none" id="flexTimeGroup">
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label fw-semibold small">Start Time <span class="text-danger">*</span></label>
                <select id="flexStart" class="form-select" required>
                  <?php
                    $startTimes = [];
                    for ($t = strtotime('08:00'); $t <= strtotime('17:00'); $t += 3600) {
                      $startTimes[] = date('H:i', $t);
                    }
                    foreach ($startTimes as $tm) {
                      echo '<option value="'.$tm.'">'.date('g:i A', strtotime($tm)).'</option>';
                    }
                  ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold small">End Time <span class="text-danger">*</span></label>
                <select id="flexEnd" class="form-select" required>
                  <?php
                    $endTimes = [];
                    for ($t = strtotime('08:30'); $t <= strtotime('17:30'); $t += 3600) {
                      $endTimes[] = date('H:i', $t);
                    }
                    $endTimes[] = '18:00';
                    foreach ($endTimes as $tm) {
                      echo '<option value="'.$tm.'">'.date('g:i A', strtotime($tm)).'</option>';
                    }
                  ?>
                </select>
              </div>
            </div>

          </div>

          <!-- Hidden values sent to the server -->
          <input type="hidden" name="start_time" id="startTime">
          <input type="hidden" name="end_time" id="endTime">

          <!-- Attendees -->
          <div class="mb-3">
            <label class="form-label fw-semibold small">
              Number of Attendees <span class="text-danger">*</span>
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

          <!-- Purpose -->
          <div class="mb-3">
            <label class="form-label fw-semibold small">Purpose / Activity <span class="text-danger">*</span></label>
            <textarea name="purpose" id="purpose" class="form-control" rows="2"
                      placeholder="Describe the purpose of your booking..." required></textarea>
          </div>

          <!-- Request Letter (conditional) -->
          <div class="mb-3" id="letterUploadGroup" style="display:none">
            <label class="form-label fw-semibold small">
              Request Letter <span class="text-danger">*</span>
            </label>
            <input type="file" name="request_letter" id="requestLetter"
                   class="form-control" accept=".pdf,.jpg,.jpeg,.png">
            <div class="form-text text-muted small">
              <i class="fas fa-info-circle me-1"></i>Accepted: PDF, JPG, PNG — Max 5 MB
            </div>
          </div>

          <!-- Instant booking notice -->
          <div id="instantNotice" class="alert alert-success d-none py-2 small" role="alert">
            <i class="fas fa-bolt me-1"></i>
            <strong>Instant Booking:</strong> Your reservation will be <strong>confirmed immediately</strong> upon submission.
          </div>

          <!-- Request notice -->
          <div id="requestNotice" class="alert alert-warning d-none py-2 small" role="alert">
            <i class="fas fa-clock me-1"></i>
            <strong>Request Booking:</strong> Your booking will be reviewed by the admin. Upload your request letter to proceed.
          </div>

        </form>
      </div><!-- .modal-body -->

      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="submitBooking">
          <span id="submitText"><i class="fas fa-paper-plane me-1"></i>Submit Booking</span>
          <span id="submitSpinner" class="d-none">
            <span class="spinner-border spinner-border-sm me-1"></span>Submitting…
          </span>
        </button>
      </div>

    </div><!-- .modal-content -->
  </div>
</div>

<!-- ═══════════════ CANCEL CONFIRM MODAL ════════════════════ -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h6 class="modal-title fw-bold">Cancel Booking</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-0">
        <p class="text-muted small mb-0">Cancel your booking for <strong id="cancelFacilityName"></strong>?</p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">No</button>
        <button type="button" class="btn btn-danger btn-sm" id="confirmCancel">Yes, Cancel</button>
      </div>
    </div>
  </div>
</div>

<?php
// Pass BASE_URL to JS
echo '<script>const BASE_URL = ' . json_encode(BASE_URL) . ';</script>';
?>
<script src="<?= BASE_URL ?>/assets/js/student_dashboard.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
