<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('admin');

$status   = $_GET['status']  ?? '';
$search   = trim($_GET['q']  ?? '');

$sql = 'SELECT b.*, u.name AS user_name, u.role AS user_role, f.name AS facility_name
        FROM bookings b JOIN users u ON b.user_id=u.id JOIN facilities f ON b.facility_id=f.id WHERE 1=1';
$params = [];

if ($status) { $sql .= ' AND b.status = ?'; $params[] = $status; }
if ($search)  { $sql .= ' AND (u.name LIKE ? OR f.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= ' ORDER BY b.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$pageTitle = 'Manage Bookings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container-fluid py-3 px-3 px-lg-4 admin-page">

<?= showFlash() ?>

<!-- Page Header -->
<div style="background:linear-gradient(135deg,#2563eb 0%,#7c3aed 100%);border-radius:16px;padding:1.25rem 1.5rem;margin-bottom:1.5rem;">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex align-items-center gap-3">
      <div style="width:44px;height:44px;background:rgba(255,255,255,.15);border-radius:12px;display:flex;align-items:center;justify-content:center;">
        <i class="fas fa-calendar-check text-white fa-lg"></i>
      </div>
      <div>
        <h4 class="mb-0 fw-bold text-white">Manage Bookings</h4>
        <small style="color:rgba(255,255,255,.65);">Review, approve and manage all facility bookings</small>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <?php
        $allCounts = $pdo->query('SELECT status, COUNT(*) as c FROM bookings GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
        $statusMeta = ['pending'=>['#d97706','fas fa-clock'],'approved'=>['#059669','fas fa-check-circle'],'rejected'=>['#dc2626','fas fa-times-circle'],'cancelled'=>['#64748b','fas fa-ban']];
        foreach ($statusMeta as $s => [$c, $ico]):
      ?>
      <div style="background:rgba(255,255,255,.12);border-radius:10px;padding:.4rem .85rem;text-align:center;min-width:60px;">
        <div style="font-size:1.1rem;font-weight:800;color:#fff;"><?= (int)($allCounts[$s] ?? 0) ?></div>
        <div style="font-size:.65rem;color:rgba(255,255,255,.65);text-transform:uppercase;letter-spacing:.5px;"><?= ucfirst($s) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3" style="border-radius:12px!important;">
  <div class="card-body py-3">
    <form class="row g-2 align-items-end" method="GET">
      <div class="col-md-5">
        <label class="form-label small fw-semibold text-muted mb-1"><i class="fas fa-search me-1"></i>Search</label>
        <input type="text" name="q" class="form-control" placeholder="User name or facility..." value="<?= e($search) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold text-muted mb-1"><i class="fas fa-filter me-1"></i>Status</label>
        <select name="status" class="form-select">
          <option value="">All Statuses</option>
          <?php foreach (['pending','approved','rejected','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
      </div>
      <div class="col-md-2">
        <a href="<?= BASE_URL ?>/admin/manage_bookings.php" class="btn btn-outline-secondary w-100">Reset</a>
      </div>
    </form>
    <!-- Quick filter pills -->
    <div class="mt-2 d-flex flex-wrap gap-2">
      <?php
        $qFilters = ['' => ['All','fas fa-border-all','#2563eb'], 'pending' => ['Pending','fas fa-clock','#d97706'], 'approved' => ['Approved','fas fa-check-circle','#059669'], 'rejected' => ['Rejected','fas fa-times-circle','#dc2626'], 'cancelled' => ['Cancelled','fas fa-ban','#64748b']];
        foreach ($qFilters as $qs => [$ql, $qi, $qc]):
          $qa = ($status === $qs);
      ?>
      <a href="?status=<?= $qs ?>" style="
        display:inline-flex;align-items:center;gap:.35rem;
        padding:.25rem .75rem;border-radius:100px;font-size:.78rem;font-weight:600;
        text-decoration:none;transition:all .2s;
        background:<?= $qa ? $qc : 'white' ?>;
        color:<?= $qa ? '#fff' : $qc ?>;
        border:1.5px solid <?= $qc ?>;
        box-shadow:<?= $qa ? '0 2px 8px rgba(0,0,0,.12)' : 'none' ?>;">
        <i class="<?= $qi ?>" style="font-size:.68rem;"></i><?= $ql ?>
        <?php $qcnt = $qs === '' ? array_sum($allCounts) : (int)($allCounts[$qs] ?? 0); if ($qcnt > 0 && !$qa): ?>
          <span style="background:<?= $qc ?>;color:#fff;border-radius:100px;padding:.05rem .38rem;font-size:.62rem;"><?= $qcnt ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius:12px!important;overflow:hidden;">
  <div class="card-body p-0">
    <?php if (empty($bookings)): ?>
      <div class="text-center py-5 px-3" style="background:linear-gradient(180deg,#f8faff 0%,#fff 100%);">
        <div style="width:80px;height:80px;background:linear-gradient(135deg,#eff6ff,#f5f3ff);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;border:2px solid #e8eeff;">
          <i class="fas fa-calendar-plus" style="font-size:1.8rem;background:linear-gradient(135deg,#2563eb,#7c3aed);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i>
        </div>
        <h6 class="fw-bold mb-1" style="color:#0f172a;"><?= $status ? 'No ' . ucfirst($status) . ' bookings' : 'No bookings found' ?></h6>
        <p class="text-muted small mb-3"><?= $status ? 'Try a different filter.' : 'No bookings have been made yet.' ?></p>
        <?php if ($status): ?>
          <a href="<?= BASE_URL ?>/admin/manage_bookings.php" class="btn btn-sm btn-outline-primary" style="border-radius:100px;"><i class="fas fa-border-all me-1"></i>View All</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>#</th><th>User</th><th>Role</th><th>Facility</th><th>Date</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td class="text-muted small">#<?= str_pad($b['id'],4,'0',STR_PAD_LEFT) ?></td>
            <td><?= e($b['user_name']) ?></td>
            <td><span class="badge bg-secondary"><?= ucfirst($b['user_role']) ?></span></td>
            <td><?= e($b['facility_name']) ?></td>
            <td><?= date('M j, Y', strtotime($b['booking_date'])) ?></td>
            <td><?= statusBadge($b['status']) ?></td>
            <td class="text-muted small"><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
            <td><a href="<?= BASE_URL ?>/admin/view_booking.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
