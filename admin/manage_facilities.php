<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('admin');

// Toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle'])) {
    verifyCsrf();
    $fid = (int)$_POST['facility_id'];
    $stmt = $pdo->prepare('SELECT status, name FROM facilities WHERE id = ?');
    $stmt->execute([$fid]);
    $frow = $stmt->fetch();
    $newStatus = ($frow['status'] === 'active') ? 'inactive' : 'active';
    $pdo->prepare('UPDATE facilities SET status=? WHERE id=?')->execute([$newStatus, $fid]);
    logAudit($pdo, 'facility_status_changed', "{$frow['name']} (ID $fid) → $newStatus");
    flash('success', 'Facility status updated.');
    header('Location: ' . BASE_URL . '/admin/manage_facilities.php'); exit;
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    verifyCsrf();
    $fid = (int)$_POST['facility_id'];
    $frow = $pdo->prepare('SELECT name FROM facilities WHERE id=?');
    $frow->execute([$fid]);
    $frow = $frow->fetch();
    if ($frow) {
        $pdo->prepare('DELETE FROM facilities WHERE id=?')->execute([$fid]);
        logAudit($pdo, 'facility_deleted', "{$frow['name']} (ID $fid) deleted");
    }
    flash('success', 'Facility deleted.');
    header('Location: ' . BASE_URL . '/admin/manage_facilities.php'); exit;
}

$facilities = $pdo->query('SELECT f.*, (SELECT COUNT(*) FROM bookings WHERE facility_id=f.id) AS booking_count FROM facilities f ORDER BY f.name')->fetchAll();

$facilityImages = [
    'CL Room 1'    => 'CL 1.jpg',
    'CL Room 2'    => 'CL 2.jpg',
    'CL Room 3'    => 'CL 3.jpg',
    'EIRC'         => 'EIRC.jpg',
    'Museum'       => 'MUSEUM.jpg',
    'Reading Area' => 'Reading Area.jpg',
    'Faculty Area' => 'Faculty Area.jpg',
];

$pageTitle = 'Manage Facilities';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container-fluid py-3 px-3 px-lg-4 admin-page">

<?= showFlash() ?>

<div class="admin-hero mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex align-items-center gap-3">
      <div class="admin-hero-icon"><i class="fas fa-building"></i></div>
      <div>
        <h4 class="mb-0 fw-bold text-white">Manage Facilities</h4>
        <div class="admin-hero-sub">Add, edit, activate/deactivate facilities</div>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= BASE_URL ?>/admin/add_facility.php" class="btn btn-light btn-sm">
        <i class="fas fa-plus me-1"></i>Add Facility
      </a>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (empty($facilities)): ?>
      <p class="text-center text-muted py-4">No facilities yet. <a href="<?= BASE_URL ?>/admin/add_facility.php">Add one</a></p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Photo</th><th>Name</th><th>Type</th><th>Location</th><th>Capacity</th><th>Hours</th><th>Bookings</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($facilities as $f): ?>
          <tr>
            <td>
              <?php if (isset($facilityImages[$f['name']])): ?>
              <img src="<?= BASE_URL ?>/images/<?= rawurlencode($facilityImages[$f['name']]) ?>"
                   alt="<?= e($f['name']) ?>"
                   style="width:56px;height:40px;object-fit:cover;border-radius:6px;"
                   onerror="this.style.display='none'">
              <?php else: ?>
              <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
            <td><strong><?= e($f['name']) ?></strong></td>
            <td><?= facilityLabel($f['type']) ?></td>
            <td><?= e($f['location']) ?></td>
            <td><?= $f['capacity'] ?></td>
            <td class="small"><?= substr($f['open_time'],0,5) ?> – <?= substr($f['close_time'],0,5) ?></td>
            <td><?= $f['booking_count'] ?></td>
            <td><span class="badge <?= $f['status']==='active' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($f['status']) ?></span></td>
            <td>
              <div class="admin-actions">
                <a href="<?= BASE_URL ?>/admin/edit_facility.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                <form class="d-inline" method="POST">
                  <?= csrfField() ?>
                  <input type="hidden" name="facility_id" value="<?= $f['id'] ?>">
                  <button name="toggle" class="btn btn-sm btn-outline-secondary"><?= $f['status']==='active' ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <form class="d-inline" method="POST" onsubmit="return confirm('Delete this facility?')">
                  <?= csrfField() ?>
                  <input type="hidden" name="facility_id" value="<?= $f['id'] ?>">
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
