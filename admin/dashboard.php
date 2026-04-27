<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('admin');

$counts = $pdo->query('SELECT
    COUNT(*) AS total,
    SUM(status="pending")   AS pending,
    SUM(status="approved")  AS approved,
    SUM(status="rejected")  AS rejected,
    SUM(status="cancelled") AS cancelled
    FROM bookings')->fetch();

$totalUsers      = $pdo->query('SELECT COUNT(*) FROM users WHERE role != "admin"')->fetchColumn();
$totalFacilities = $pdo->query('SELECT COUNT(*) FROM facilities WHERE status="active"')->fetchColumn();
$todayBookings   = $pdo->query('SELECT COUNT(*) FROM bookings WHERE DATE(created_at)=CURDATE()')->fetchColumn();

$pending = $pdo->query('SELECT b.*, u.name AS user_name, f.name AS facility_name
    FROM bookings b JOIN users u ON b.user_id=u.id JOIN facilities f ON b.facility_id=f.id
    WHERE b.status="pending" ORDER BY b.created_at ASC LIMIT 8')->fetchAll();

$recentLogs = $pdo->query('SELECT al.*, u.name AS actor_name, u.role AS actor_role
    FROM audit_logs al LEFT JOIN users u ON al.user_id=u.id
    ORDER BY al.created_at DESC LIMIT 10')->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container-fluid py-3 px-3 px-lg-4 admin-dashboard">

<?= showFlash() ?>

<!-- Summary (eye-friendly, like reference layout) -->
<div class="card border-0 shadow-sm admin-summary mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-2">
      <div>
        <div class="admin-summary-title">System Overview</div>
        <div class="admin-summary-sub text-muted">Quick stats & shortcuts</div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/admin/manage_bookings.php">View All</a>
        <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/admin/reports.php">Reports</a>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-12 col-md-4">
        <div class="admin-metric">
          <div class="admin-metric-icon bg-primary-subtle text-primary"><i class="fas fa-calendar-check"></i></div>
          <div class="admin-metric-body">
            <div class="admin-metric-label">Total Bookings</div>
            <div class="admin-metric-value"><?= (int)$counts['total'] ?></div>
            <div class="admin-metric-sub text-muted">Pending: <?= (int)$counts['pending'] ?> · Today: <?= (int)$todayBookings ?></div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="admin-metric">
          <div class="admin-metric-icon" style="background:var(--purple-light);color:var(--purple)"><i class="fas fa-users"></i></div>
          <div class="admin-metric-body">
            <div class="admin-metric-label">Users</div>
            <div class="admin-metric-value"><?= (int)$totalUsers ?></div>
            <div class="admin-metric-sub text-muted">Active accounts (excluding admins)</div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="admin-metric">
          <div class="admin-metric-icon" style="background:var(--green-light);color:var(--green)"><i class="fas fa-building"></i></div>
          <div class="admin-metric-body">
            <div class="admin-metric-label">Active Facilities</div>
            <div class="admin-metric-value"><?= (int)$totalFacilities ?></div>
            <div class="admin-metric-sub text-muted">Available for booking</div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mt-3">
      <div class="col-6 col-md-3">
        <div class="stat-card stat-yellow" style="margin-top:0">
          <div class="stat-icon"><i class="fas fa-clock"></i></div>
          <div class="label">Pending</div>
          <div class="value"><?= (int)$counts['pending'] ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card stat-green" style="margin-top:0">
          <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
          <div class="label">Approved</div>
          <div class="value"><?= (int)$counts['approved'] ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card stat-red" style="margin-top:0">
          <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
          <div class="label">Rejected</div>
          <div class="value"><?= (int)$counts['rejected'] ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card stat-slate" style="margin-top:0">
          <div class="stat-icon"><i class="fas fa-ban"></i></div>
          <div class="label">Cancelled</div>
          <div class="value"><?= (int)$counts['cancelled'] ?></div>
        </div>
      </div>
    </div>

    <div class="text-center mt-3">
      <a class="btn btn-primary btn-sm admin-summary-cta" href="<?= BASE_URL ?>/admin/manage_bookings.php?status=pending">
        <i class="fas fa-clipboard-check me-1"></i>View Pending Approvals
      </a>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Pending Approvals -->
  <div class="col-lg-7">
    <div class="card h-100 border-0 shadow-sm admin-panel">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
          <span class="admin-panel-icon" style="background:var(--yellow-light);color:var(--yellow)"><i class="fas fa-clock"></i></span>
          <span class="fw-semibold">Pending Approvals</span>
          <?php if ($counts['pending'] > 0): ?>
            <span class="badge bg-warning text-dark ms-1"><?= (int)$counts['pending'] ?></span>
          <?php endif; ?>
        </div>
        <a href="<?= BASE_URL ?>/admin/manage_bookings.php?status=pending" class="btn btn-sm btn-outline-secondary">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($pending)): ?>
          <p class="text-center text-muted py-4"><i class="fas fa-check-circle fa-2x mb-2 d-block opacity-25"></i>No pending bookings.</p>
        <?php else: ?>
        <div class="admin-scroll">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>User</th><th>Facility</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pending as $b): ?>
              <tr>
                <td class="text-muted small">#<?= str_pad($b['id'],4,'0',STR_PAD_LEFT) ?></td>
                <td><?= e($b['user_name']) ?></td>
                <td><?= e($b['facility_name']) ?></td>
                <td class="small"><?= date('M j, Y', strtotime($b['booking_date'])) ?></td>
                <td><a href="<?= BASE_URL ?>/admin/view_booking.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-warning">Review</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Audit Logs -->
  <div class="col-lg-5">
    <div class="card h-100 border-0 shadow-sm admin-panel">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
          <span class="admin-panel-icon" style="background:var(--blue-light);color:var(--blue)"><i class="fas fa-clipboard-list"></i></span>
          <span class="fw-semibold">Recent Activity</span>
        </div>
        <a href="<?= BASE_URL ?>/admin/audit_logs.php" class="btn btn-sm btn-outline-secondary">Full Log</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentLogs)): ?>
          <p class="text-center text-muted py-4">No activity yet.</p>
        <?php else: ?>
        <div class="admin-scroll">
        <ul class="list-group list-group-flush admin-timeline">
          <?php foreach ($recentLogs as $log): ?>
          <li class="list-group-item px-3 py-3">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div class="flex-grow-1" style="min-width:0">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <span class="badge bg-secondary-subtle text-secondary border small"><?= e($log['action']) ?></span>
                  <?php if ($log['actor_name']): ?>
                    <small class="text-muted"><?= e($log['actor_name']) ?></small>
                  <?php endif; ?>
                </div>
                <?php if ($log['details']): ?>
                  <div class="text-muted small mt-2 text-truncate"><?= e($log['details']) ?></div>
                <?php endif; ?>
              </div>
              <small class="text-muted text-nowrap ms-2"><?= date('M j, g:i A', strtotime($log['created_at'])) ?></small>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
