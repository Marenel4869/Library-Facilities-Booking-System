<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('admin');

// ── Date range filter ────────────────────────────────────────────────────────
$rangePreset = $_GET['range'] ?? '30';   // 7 | 30 | 90 | 365 | year_YYYY | all | custom
$dateFrom    = $_GET['from']  ?? '';
$dateTo      = $_GET['to']    ?? '';

if ($rangePreset === 'all') {
    $dateFrom = '2000-01-01';
    $dateTo   = date('Y-m-d');
} elseif (str_starts_with($rangePreset, 'year_')) {
    $yr       = (int)substr($rangePreset, 5);
    $dateFrom = "$yr-01-01";
    $dateTo   = "$yr-12-31";
} elseif ($rangePreset !== 'custom') {
    $days     = (int)$rangePreset ?: 30;
    $dateFrom = date('Y-m-d', strtotime("-{$days} days"));
    $dateTo   = date('Y-m-d');
}
$dateFrom = $dateFrom ?: date('Y-m-d', strtotime('-30 days'));
$dateTo   = $dateTo   ?: date('Y-m-d');

// Build list of years that have bookings (for year shortcut buttons)
try {
    $yearsWithData = $pdo->query(
        'SELECT DISTINCT YEAR(booking_date) AS yr FROM bookings ORDER BY yr DESC'
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { $yearsWithData = []; }

// ── 1. Most Booked Facilities ────────────────────────────────────────────────
$facBookings = $pdo->prepare('
    SELECT f.name, COUNT(b.id) AS total
    FROM bookings b JOIN facilities f ON b.facility_id = f.id
    WHERE b.status IN ("approved","pending")
      AND b.booking_date BETWEEN ? AND ?
    GROUP BY f.id, f.name
    ORDER BY total DESC LIMIT 10
');
$facBookings->execute([$dateFrom, $dateTo]);
$facBookings = $facBookings->fetchAll();

// ── 2. Bookings per Day (trend line) ────────────────────────────────────────
$dailyTrend = $pdo->prepare('
    SELECT booking_date, COUNT(*) AS total
    FROM bookings
    WHERE booking_date BETWEEN ? AND ?
      AND status IN ("approved","pending","rejected","cancelled")
    GROUP BY booking_date ORDER BY booking_date
');
$dailyTrend->execute([$dateFrom, $dateTo]);
$dailyTrend = $dailyTrend->fetchAll();

// ── 3. Peak Booking Hours ────────────────────────────────────────────────────
$peakHours = $pdo->prepare('
    SELECT HOUR(start_time) AS hr, COUNT(*) AS total
    FROM bookings
    WHERE booking_date BETWEEN ? AND ?
      AND status IN ("approved","pending")
    GROUP BY hr ORDER BY hr
');
$peakHours->execute([$dateFrom, $dateTo]);
$peakHours = $peakHours->fetchAll();

// ── 4. User Type Usage (role breakdown) ─────────────────────────────────────
$userUsage = $pdo->prepare('
    SELECT u.role, COUNT(b.id) AS total
    FROM bookings b JOIN users u ON b.user_id = u.id
    WHERE b.booking_date BETWEEN ? AND ?
      AND b.status IN ("approved","pending")
    GROUP BY u.role
');
$userUsage->execute([$dateFrom, $dateTo]);
$userUsage = $userUsage->fetchAll();

// ── 5. Status Breakdown ──────────────────────────────────────────────────────
$statusBreakdown = $pdo->prepare('
    SELECT status, COUNT(*) AS total
    FROM bookings
    WHERE booking_date BETWEEN ? AND ?
    GROUP BY status
');
$statusBreakdown->execute([$dateFrom, $dateTo]);
$statusBreakdown = $statusBreakdown->fetchAll();

// ── 6. Summary numbers ───────────────────────────────────────────────────────
$summary = $pdo->prepare('
    SELECT
        COUNT(*) AS total,
        SUM(status="approved")  AS approved,
        SUM(status="pending")   AS pending,
        SUM(status="rejected")  AS rejected,
        SUM(status="cancelled") AS cancelled,
        COUNT(DISTINCT user_id) AS unique_users,
        COUNT(DISTINCT facility_id) AS unique_facilities
    FROM bookings
    WHERE booking_date BETWEEN ? AND ?
');
$summary->execute([$dateFrom, $dateTo]);
$sum = $summary->fetch();

// ── JSON for charts ──────────────────────────────────────────────────────────
$j_facLabels  = json_encode(array_column($facBookings, 'name'));
$j_facData    = json_encode(array_map('intval', array_column($facBookings, 'total')));

// Build full day-by-day array (fill gaps with 0)
$dayMap = [];
foreach ($dailyTrend as $d) $dayMap[$d['booking_date']] = (int)$d['total'];
$allDays = []; $allCounts = [];
for ($ts = strtotime($dateFrom); $ts <= strtotime($dateTo); $ts = strtotime('+1 day', $ts)) {
    $k = date('Y-m-d', $ts);
    $allDays[]   = date('M j', $ts);
    $allCounts[] = $dayMap[$k] ?? 0;
}
$j_trendLabels = json_encode($allDays);
$j_trendData   = json_encode($allCounts);

// Peak hours: fill 0-23
$hrMap = [];
foreach ($peakHours as $h) $hrMap[(int)$h['hr']] = (int)$h['total'];
$hrLabels = $hrData = [];
for ($h = 0; $h < 24; $h++) {
    $hrLabels[] = date('g A', mktime($h, 0, 0));
    $hrData[]   = $hrMap[$h] ?? 0;
}
$j_hrLabels = json_encode($hrLabels);
$j_hrData   = json_encode($hrData);

$j_userLabels = json_encode(array_map('ucfirst', array_column($userUsage, 'role')));
$j_userData   = json_encode(array_map('intval',  array_column($userUsage, 'total')));

$j_stLabels = json_encode(array_map('ucfirst', array_column($statusBreakdown, 'status')));
$j_stData   = json_encode(array_map('intval',  array_column($statusBreakdown, 'total')));

$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container-fluid px-4 py-3">

<?= showFlash() ?>

<!-- Header row -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <h4 class="mb-0"><i class="fas fa-chart-bar me-2 text-primary"></i>Reports &amp; Analytics</h4>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/admin/export_csv.php?from=<?= urlencode($dateFrom) ?>&to=<?= urlencode($dateTo) ?>&_token=<?= csrfToken() ?>"
       class="btn btn-success btn-sm">
      <i class="fas fa-file-csv me-1"></i>Export CSV
    </a>
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
      <i class="fas fa-print me-1"></i>Print / PDF
    </button>
  </div>
</div>

<!-- Date range filter -->
<div class="card mb-4">
  <div class="card-body py-2">
    <form class="row g-2 align-items-end" method="GET">
      <div class="col-12 col-lg-auto">
        <label class="form-label mb-1 small fw-semibold">Quick Range</label>
        <div class="btn-group btn-group-sm flex-wrap" role="group">
          <?php foreach (['7'=>'7 Days','30'=>'30 Days','90'=>'90 Days','365'=>'1 Year'] as $v=>$l): ?>
            <a href="?range=<?= $v ?>" class="btn <?= $rangePreset===$v ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $l ?></a>
          <?php endforeach; ?>
          <a href="?range=all" class="btn <?= $rangePreset==='all' ? 'btn-primary' : 'btn-outline-secondary' ?>">All Time</a>
          <a href="?range=custom" class="btn <?= $rangePreset==='custom' ? 'btn-primary' : 'btn-outline-secondary' ?>">Custom</a>
        </div>
      </div>
      <?php if (!empty($yearsWithData)): ?>
      <div class="col-12 col-lg-auto">
        <label class="form-label mb-1 small fw-semibold">By Year</label>
        <div class="btn-group btn-group-sm flex-wrap" role="group">
          <?php foreach ($yearsWithData as $yr): ?>
            <a href="?range=year_<?= $yr ?>" class="btn <?= $rangePreset==="year_$yr" ? 'btn-info text-white' : 'btn-outline-info' ?>"><?= $yr ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($rangePreset === 'custom'): ?>
      <input type="hidden" name="range" value="custom">
      <div class="col-auto">
        <label class="form-label mb-1 small fw-semibold">From</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
      </div>
      <div class="col-auto">
        <label class="form-label mb-1 small fw-semibold">To</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Apply</button>
      </div>
      <?php endif; ?>
      <div class="col-auto ms-auto text-muted small align-self-center">
        <?= date('M j, Y', strtotime($dateFrom)) ?> – <?= date('M j, Y', strtotime($dateTo)) ?>
      </div>
    </form>
  </div>
</div>

<!-- Summary KPI cards -->
<div class="row g-3 mb-4">
  <?php
    $kpis = [
      ['Total Bookings',    $sum['total'],              'primary',   'fa-calendar-check'],
      ['Approved',          $sum['approved'],            'success',   'fa-check-circle'],
      ['Pending',           $sum['pending'],             'warning',   'fa-clock'],
      ['Rejected',          $sum['rejected'],            'danger',    'fa-times-circle'],
      ['Cancelled',         $sum['cancelled'],           'secondary', 'fa-ban'],
      ['Unique Users',      $sum['unique_users'],        'info',      'fa-users'],
      ['Facilities Used',   $sum['unique_facilities'],   'purple',    'fa-building'],
    ];
  ?>
  <?php foreach ($kpis as [$label, $val, $color, $icon]): ?>
  <div class="col-6 col-md-3 col-xl-auto flex-xl-fill">
    <div class="stat-card" style="<?= $color==='purple'?'border-left-color:#6f42c1':'' ?>">
      <div class="d-flex align-items-center gap-2">
        <i class="fas <?= $icon ?> fa-lg text-<?= $color === 'purple' ? 'secondary' : $color ?>"></i>
        <div>
          <div class="label"><?= $label ?></div>
          <div class="value text-<?= $color === 'purple' ? 'secondary' : $color ?>"><?= (int)$val ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts row 1: Trend + Facility bar -->
<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header fw-semibold"><i class="fas fa-chart-line me-2 text-primary"></i>Daily Booking Trend</div>
      <div class="card-body"><canvas id="trendChart" height="100"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header fw-semibold"><i class="fas fa-chart-pie me-2 text-success"></i>User Type Usage</div>
      <div class="card-body d-flex align-items-center justify-content-center">
        <canvas id="userChart" height="220"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Charts row 2: Most booked + Peak hours + Status -->
<div class="row g-4 mb-4">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header fw-semibold"><i class="fas fa-trophy me-2 text-warning"></i>Most Booked Facilities</div>
      <div class="card-body"><canvas id="facChart" height="180"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header fw-semibold"><i class="fas fa-clock me-2 text-info"></i>Peak Booking Hours</div>
      <div class="card-body"><canvas id="peakChart" height="180"></canvas></div>
    </div>
  </div>
  <div class="col-lg-3">
    <div class="card h-100">
      <div class="card-header fw-semibold"><i class="fas fa-chart-donut me-2 text-secondary"></i>Status Breakdown</div>
      <div class="card-body d-flex align-items-center justify-content-center">
        <canvas id="statusChart" height="220"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Data table: top facilities -->
<?php if (!empty($facBookings)): ?>
<div class="card mb-4">
  <div class="card-header fw-semibold"><i class="fas fa-table me-2"></i>Facility Booking Summary (<?= date('M j', strtotime($dateFrom)) ?> – <?= date('M j, Y', strtotime($dateTo)) ?>)</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr><th>Rank</th><th>Facility</th><th>Bookings</th><th>Share</th><th>Bar</th></tr>
        </thead>
        <tbody>
        <?php
          $maxBookings = max(array_column($facBookings, 'total') ?: [1]);
          foreach ($facBookings as $i => $row):
            $pct = round($row['total'] / max($sum['total'],1) * 100, 1);
            $barW = round($row['total'] / $maxBookings * 100);
        ?>
          <tr>
            <td class="text-muted small"><?= $i+1 ?></td>
            <td class="fw-semibold"><?= e($row['name']) ?></td>
            <td><?= (int)$row['total'] ?></td>
            <td class="small text-muted"><?= $pct ?>%</td>
            <td style="width:200px">
              <div class="progress" style="height:8px">
                <div class="progress-bar bg-primary" style="width:<?= $barW ?>%"></div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
Chart.defaults.plugins.legend.position = 'bottom';

const PALETTE = ['#0d6efd','#198754','#ffc107','#dc3545','#0dcaf0','#6f42c1','#fd7e14','#20c997','#6c757d','#d63384'];

// 1. Daily trend
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: <?= $j_trendLabels ?>,
    datasets: [{
      label: 'Bookings',
      data: <?= $j_trendData ?>,
      fill: true,
      tension: 0.4,
      borderColor: '#0d6efd',
      backgroundColor: 'rgba(13,110,253,0.1)',
      pointRadius: 3,
      pointHoverRadius: 5,
    }]
  },
  options: { scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { display: false } } }
});

// 2. User type doughnut
new Chart(document.getElementById('userChart'), {
  type: 'doughnut',
  data: {
    labels: <?= $j_userLabels ?>,
    datasets: [{ data: <?= $j_userData ?>, backgroundColor: PALETTE, hoverOffset: 8 }]
  },
  options: { cutout: '60%', plugins: { legend: { position: 'bottom' } } }
});

// 3. Most booked facilities (horizontal bar)
new Chart(document.getElementById('facChart'), {
  type: 'bar',
  data: {
    labels: <?= $j_facLabels ?>,
    datasets: [{
      label: 'Bookings',
      data: <?= $j_facData ?>,
      backgroundColor: PALETTE,
      borderRadius: 4,
    }]
  },
  options: {
    indexAxis: 'y',
    scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
    plugins: { legend: { display: false } }
  }
});

// 4. Peak hours bar
new Chart(document.getElementById('peakChart'), {
  type: 'bar',
  data: {
    labels: <?= $j_hrLabels ?>,
    datasets: [{
      label: 'Bookings',
      data: <?= $j_hrData ?>,
      backgroundColor: 'rgba(13,202,240,0.7)',
      borderColor: '#0dcaf0',
      borderWidth: 1,
      borderRadius: 3,
    }]
  },
  options: {
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
    plugins: { legend: { display: false } }
  }
});

// 5. Status doughnut
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: <?= $j_stLabels ?>,
    datasets: [{
      data: <?= $j_stData ?>,
      backgroundColor: ['#ffc107','#198754','#dc3545','#6c757d'],
      hoverOffset: 8,
    }]
  },
  options: { cutout: '55%', plugins: { legend: { position: 'bottom' } } }
});
</script>

<style>
@media print {
  nav, .btn, form.card { display: none !important; }
  .card { break-inside: avoid; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>