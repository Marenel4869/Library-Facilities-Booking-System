<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('admin');

// Suspend/unsuspend
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    verifyCsrf();
    $uid = (int)$_POST['user_id'];
    $stmt = $pdo->prepare('SELECT status, name FROM users WHERE id = ? AND role != "admin"');
    $stmt->execute([$uid]);
    $urow = $stmt->fetch();
    if ($urow) {
        $newStatus = ($urow['status'] === 'active') ? 'suspended' : 'active';
        $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$newStatus, $uid]);
        logAudit($pdo, 'user_status_changed', "{$urow['name']} (ID $uid) → $newStatus");
        flash('success', 'User status updated to ' . $newStatus . '.');
    }
    header('Location: ' . BASE_URL . '/admin/manage_users.php'); exit;
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    verifyCsrf();
    $uid = (int)$_POST['user_id'];
    $urow = $pdo->prepare('SELECT name FROM users WHERE id=? AND role!="admin"');
    $urow->execute([$uid]);
    $urow = $urow->fetch();
    if ($urow) {
        $pdo->prepare('DELETE FROM users WHERE id=? AND role != "admin"')->execute([$uid]);
        logAudit($pdo, 'user_deleted', "{$urow['name']} (ID $uid) deleted");
    }
    flash('success', 'User deleted.');
    header('Location: ' . BASE_URL . '/admin/manage_users.php'); exit;
}

$role   = $_GET['role']  ?? '';
$search = trim($_GET['q'] ?? '');

$sql = 'SELECT * FROM users WHERE role != "admin"';
$params = [];
if ($role)   { $sql .= ' AND role = ?';   $params[] = $role; }
if ($search) { $sql .= ' AND (name LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container-fluid py-3 px-3 px-lg-4 admin-page">

<?= showFlash() ?>

<div class="admin-hero mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex align-items-center gap-3">
      <div class="admin-hero-icon"><i class="fas fa-users"></i></div>
      <div>
        <h4 class="mb-0 fw-bold text-white">Manage Users</h4>
        <div class="admin-hero-sub">Search and manage student/faculty accounts</div>
      </div>
    </div>
  </div>
</div>

<!-- Filter -->
<form class="row g-2 mb-3" method="GET">
  <div class="col-md-5">
    <input type="text" name="q" class="form-control" placeholder="Search name or email..." value="<?= e($search) ?>">
  </div>
  <div class="col-md-3">
    <select name="role" class="form-select">
      <option value="">All Roles</option>
      <option value="student"  <?= $role==='student'  ? 'selected' : '' ?>>Student</option>
      <option value="faculty"  <?= $role==='faculty'  ? 'selected' : '' ?>>Faculty</option>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
  <div class="col-md-2"><a href="<?= BASE_URL ?>/admin/manage_users.php" class="btn btn-outline-secondary w-100">Reset</a></div>
</form>

<div class="card">
  <div class="card-body p-0">
    <?php if (empty($users)): ?>
      <p class="text-center text-muted py-4">No users found.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>ID Number</th><th>Department</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= e($u['name']) ?></td>
            <td class="small"><?= e($u['email']) ?></td>
            <td><span class="badge bg-secondary"><?= ucfirst($u['role']) ?></span></td>
            <td class="small"><?= e($u['id_number'] ?? '–') ?></td>
            <td class="small"><?= e($u['department'] ?? '–') ?></td>
            <td>
              <?php if ($u['status'] === 'active'): ?>
                <span class="badge bg-success">Active</span>
              <?php else: ?>
                <span class="badge bg-warning text-dark">Suspended</span>
              <?php endif; ?>
            </td>
            <td class="small text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
            <td>
              <div class="admin-actions">
                <form class="d-inline" method="POST">
                  <?= csrfField() ?>
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button name="toggle_status" class="btn btn-sm btn-outline-secondary"><?= $u['status']==='active' ? 'Suspend' : 'Unsuspend' ?></button>
                </form>
                <form class="d-inline" method="POST" onsubmit="return confirm('Delete this user?')">
                  <?= csrfField() ?>
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button name="delete" class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
