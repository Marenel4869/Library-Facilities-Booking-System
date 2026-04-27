<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('admin');

ensureContentTables($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'close' && $id > 0) {
        $pdo->prepare('UPDATE help_requests SET status="closed" WHERE id=?')->execute([$id]);
        flash('success', 'Request closed.');
        header('Location: ' . BASE_URL . '/admin/help_requests.php');
        exit;
    }
}

$filter = $_GET['filter'] ?? 'open'; // open|closed|all
$where = '';
if ($filter === 'open') $where = 'WHERE hr.status="open"';
elseif ($filter === 'closed') $where = 'WHERE hr.status="closed"';

$rows = $pdo->query(
    'SELECT hr.*, u.name, u.email
     FROM help_requests hr
     JOIN users u ON u.id = hr.user_id
     ' . $where . '
     ORDER BY hr.created_at DESC
     LIMIT 80'
)->fetchAll();

$pageTitle = 'Help Requests';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container-fluid py-3 px-3 px-lg-4 admin-page">

<?= showFlash() ?>

<div class="admin-hero mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex align-items-center gap-3">
      <div class="admin-hero-icon"><i class="fas fa-life-ring"></i></div>
      <div>
        <h4 class="mb-0 fw-bold text-white">Help Requests</h4>
        <div class="admin-hero-sub">User-reported issues and support messages</div>
      </div>
    </div>
    <div class="btn-group btn-group-sm">
      <a class="btn <?= $filter==='open'?'btn-light':'btn-outline-light' ?>" href="?filter=open">Open</a>
      <a class="btn <?= $filter==='closed'?'btn-light':'btn-outline-light' ?>" href="?filter=closed">Closed</a>
      <a class="btn <?= $filter==='all'?'btn-light':'btn-outline-light' ?>" href="?filter=all">All</a>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>User</th>
          <th>Subject</th>
          <th>Status</th>
          <th>Created</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="5" class="text-muted">No help requests.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($r['name']) ?></div>
              <div class="text-muted small"><?= e($r['email']) ?> · <?= e($r['role']) ?></div>
            </td>
            <td>
              <div class="fw-semibold"><?= e($r['subject']) ?></div>
              <div class="text-muted small" style="white-space:pre-wrap"><?= e(truncateText($r['message'], 120)) ?></div>
            </td>
            <td><?= $r['status'] === 'open' ? '<span class="badge bg-warning text-dark">OPEN</span>' : '<span class="badge bg-secondary">CLOSED</span>' ?></td>
            <td class="text-muted small"><?= date('M j, Y g:i A', strtotime($r['created_at'])) ?></td>
            <td class="text-end">
              <?php if ($r['status'] === 'open'): ?>
                <form method="post" class="d-inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="close">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-outline-success" type="submit">
                    <i class="fas fa-check me-1"></i>Close
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
