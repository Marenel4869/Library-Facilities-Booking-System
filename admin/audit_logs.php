<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('admin');

// Filters
$search    = trim($_GET['q']      ?? '');
$action    = trim($_GET['action'] ?? '');
$dateFrom  = trim($_GET['from']   ?? '');
$dateTo    = trim($_GET['to']     ?? '');
$yearQuick = trim($_GET['year']   ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 25;
$offset    = ($page - 1) * $perPage;

// Year quick-filter
if ($yearQuick && ctype_digit($yearQuick)) {
    $dateFrom = "{$yearQuick}-01-01";
    $dateTo   = "{$yearQuick}-12-31";
}

// Available years in logs
try {
    $logYears = $pdo->query(
        'SELECT DISTINCT YEAR(created_at) AS yr FROM audit_logs ORDER BY yr DESC'
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { $logYears = []; }

// Build query
$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(u.name LIKE ? OR al.details LIKE ? OR al.ip_address LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($action) {
    $where[]  = 'al.action = ?';
    $params[] = $action;
}
if ($dateFrom) {
    $where[]  = 'DATE(al.created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[]  = 'DATE(al.created_at) <= ?';
    $params[] = $dateTo;
}

$whereSQL = implode(' AND ', $where);

$total = $pdo->prepare("SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON al.user_id=u.id WHERE $whereSQL");
$total->execute($params);
$totalRows = (int)$total->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$perPage = max(1, (int)$perPage);
$offset  = max(0, (int)$offset);

// With PDO::ATTR_EMULATE_PREPARES=false (native prepares), MySQL may reject placeholders in LIMIT/OFFSET.
$stmt = $pdo->prepare("SELECT al.*, u.name AS actor_name, u.role AS actor_role
    FROM audit_logs al LEFT JOIN users u ON al.user_id=u.id
    WHERE $whereSQL
    ORDER BY al.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Distinct actions for filter dropdown
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Action badge colors
function actionBadge($action) {
    if (str_contains($action, 'approved'))  return 'success';
    if (str_contains($action, 'rejected'))  return 'danger';
    if (str_contains($action, 'deleted'))   return 'danger';
    if (str_contains($action, 'cancelled')) return 'secondary';
    if (str_contains($action, 'created'))   return 'primary';
    if (str_contains($action, 'login'))     return 'info';
    if (str_contains($action, 'changed'))   return 'warning';
    return 'secondary';
}

$pageTitle = 'Audit Logs';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container-fluid py-3 px-3 px-lg-4 admin-page">

<?= showFlash() ?>

<div class="admin-hero mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex align-items-center gap-3">
      <div class="admin-hero-icon"><i class="fas fa-clipboard-list"></i></div>
      <div>
        <h4 class="mb-0 fw-bold text-white">Audit Logs</h4>
        <div class="admin-hero-sub"><?= number_format($totalRows) ?> total entries</div>
      </div>
    </div>
  </div>
</div>

<!-- Filters -->
<form class="card mb-4" method="GET">
  <div class="card-body">
    <div class="row g-2 mb-2">
      <div class="col-md-4">
        <input type="text" name="q" class="form-control" placeholder="Search user, details, IP..." value="<?= e($search) ?>">
      </div>
      <div class="col-md-2">
        <select name="action" class="form-select">
          <option value="">All Actions</option>
          <?php foreach ($actions as $a): ?>
            <option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e($a) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <input type="date" name="from" class="form-control" value="<?= e($dateFrom) ?>" placeholder="From date" title="From date">
      </div>
      <div class="col-md-2">
        <input type="date" name="to" class="form-control" value="<?= e($dateTo) ?>" placeholder="To date" title="To date">
      </div>
      <div class="col-md-1">
        <button class="btn btn-primary w-100">Filter</button>
      </div>
      <div class="col-md-1">
        <a href="<?= BASE_URL ?>/admin/audit_logs.php" class="btn btn-outline-secondary w-100">Reset</a>
      </div>
    </div>
    <?php if (!empty($logYears)): ?>
    <div class="d-flex align-items-center gap-2 flex-wrap pt-1 border-top">
      <span class="small text-muted fw-semibold">Year:</span>
      <?php foreach ($logYears as $yr): ?>
        <a href="?year=<?= $yr ?>&action=<?= urlencode($action) ?>&q=<?= urlencode($search) ?>"
           class="btn btn-sm <?= $yearQuick == $yr ? 'btn-info text-white' : 'btn-outline-info' ?>">
          <?= $yr ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</form>

<!-- Table -->
<div class="card">
  <div class="card-body p-0">
    <?php if (empty($logs)): ?>
      <p class="text-center text-muted py-5">
        <i class="fas fa-search fa-2x mb-3 d-block opacity-25"></i>
        No log entries found.
      </p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Action</th>
            <th>User</th>
            <th>Details</th>
            <th>IP Address</th>
            <th>Timestamp</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td class="text-muted small"><?= (int)$log['id'] ?></td>
            <td>
              <span class="badge bg-<?= actionBadge($log['action']) ?>-subtle text-<?= actionBadge($log['action']) ?> border border-<?= actionBadge($log['action']) ?>-subtle">
                <?= e($log['action']) ?>
              </span>
            </td>
            <td>
              <?php if ($log['actor_name']): ?>
                <span class="fw-semibold small"><?= e($log['actor_name']) ?></span><br>
                <span class="badge bg-secondary-subtle text-secondary border small"><?= ucfirst($log['actor_role'] ?? '') ?></span>
              <?php else: ?>
                <span class="text-muted small">System / Guest</span>
              <?php endif; ?>
            </td>
            <td class="small text-muted" style="max-width:320px">
              <?= e($log['details'] ?? '—') ?>
            </td>
            <td class="small text-muted"><?= e($log['ip_address'] ?? '—') ?></td>
            <td class="small text-muted text-nowrap">
              <?= date('M j, Y', strtotime($log['created_at'])) ?><br>
              <?= date('g:i:s A', strtotime($log['created_at'])) ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="card-footer bg-white border-top">
    <nav>
      <ul class="pagination pagination-sm mb-0 justify-content-center flex-wrap">
        <?php
          $qs = http_build_query(array_filter(['q'=>$search,'action'=>$action,'from'=>$dateFrom,'to'=>$dateTo,'year'=>$yearQuick]));
          $base = BASE_URL . '/admin/audit_logs.php?' . ($qs ? $qs . '&' : '');
        ?>
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= $base ?>page=<?= $page-1 ?>">‹ Prev</a>
        </li>
        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
          <li class="page-item <?= $p===$page ? 'active' : '' ?>">
            <a class="page-link" href="<?= $base ?>page=<?= $p ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= $base ?>page=<?= $page+1 ?>">Next ›</a>
        </li>
      </ul>
    </nav>
    <p class="text-center text-muted small mt-2 mb-0">
      Showing <?= number_format(($offset+1)) ?>–<?= number_format(min($offset+$perPage,$totalRows)) ?> of <?= number_format($totalRows) ?> entries
    </p>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
