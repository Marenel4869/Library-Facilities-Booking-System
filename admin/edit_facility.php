<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('admin');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM facilities WHERE id = ?');
$stmt->execute([$id]);
$facility = $stmt->fetch();

if (!$facility) {
    flash('danger', 'Facility not found.');
    header('Location: ' . BASE_URL . '/admin/manage_facilities.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name        = trim($_POST['name']        ?? '');
    $type        = trim($_POST['type']        ?? '');
    $location    = trim($_POST['location']    ?? '');
    $capacity    = (int)($_POST['capacity']   ?? 0);
    $openTime    = trim($_POST['open_time']   ?? '');
    $closeTime   = trim($_POST['close_time']  ?? '');
    $description = trim($_POST['description'] ?? '');
    $equipment   = trim($_POST['equipment']   ?? '');
    $status      = trim($_POST['status']      ?? 'active');

    if (!$name)     $errors[] = 'Name is required.';
    if (!$type)     $errors[] = 'Type is required.';
    if (!$location) $errors[] = 'Location is required.';
    if ($capacity < 1) $errors[] = 'Capacity must be at least 1.';
    if (!$openTime || !$closeTime) $errors[] = 'Operating hours are required.';
    if ($openTime && $closeTime && $openTime >= $closeTime) $errors[] = 'Close time must be after open time.';

    if (empty($errors)) {
        try {
            $pdo->prepare('UPDATE facilities SET name=?,type=?,location=?,capacity=?,open_time=?,close_time=?,description=?,equipment=?,status=? WHERE id=?')
                ->execute([$name, $type, $location, $capacity, $openTime, $closeTime, $description, $equipment, $status, $id]);
            flash('success', 'Facility updated successfully.');
            header('Location: ' . BASE_URL . '/admin/manage_facilities.php'); exit;
        } catch (PDOException $ex) {
            $errors[] = 'Database error: ' . htmlspecialchars($ex->getMessage());
        }
    }
    // Restore values on error
    $facility = array_merge($facility, $_POST);
}

$pageTitle = 'Edit Facility';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container-fluid py-3 px-3 px-lg-4 admin-page">

<?= showFlash() ?>

<div class="admin-hero mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex align-items-center gap-3">
      <div class="admin-hero-icon"><i class="fas fa-pen"></i></div>
      <div>
        <h4 class="mb-0 fw-bold text-white">Edit Facility</h4>
        <div class="admin-hero-sub">Update details, hours, and status</div>
      </div>
    </div>
    <a href="<?= BASE_URL ?>/admin/manage_facilities.php" class="btn btn-light btn-sm">← Back</a>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <form method="POST" action="?id=<?= $id ?>">
      <?= csrfField() ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Facility Name *</label>
          <input type="text" name="name" class="form-control" value="<?= e($facility['name']) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Type *</label>
          <select name="type" class="form-select" required>
            <?php foreach ([
                'collaboration_room'   => 'Collaboration/Study Room',
                'electronic_resources' => 'Electronic Resources Room',
                'meeting_room'         => 'Meeting/Discussion Room',
                'artifacts_room'       => 'Artifacts Room',
                'reading_hall'         => 'Reading Hall',
                'study_room'           => 'Study Room',
                'conference_room'      => 'Conference Room',
                'auditorium'           => 'Auditorium',
                'other'                => 'Other',
            ] as $val=>$lbl): ?>
              <option value="<?= $val ?>" <?= $facility['type']===$val ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label fw-semibold">Location *</label>
          <select name="location" class="form-select" required>
            <?php
            $locations = [
              'CB 2nd Floor Tangelder Library',
              'CB 2nd Floor',
              'CB 3rd Floor',
              'Basic Ed. Library 2nd Floor',
              'Basic Ed. Library Ground Floor',
            ];
            $selLoc = $facility['location'] ?? '';
            foreach ($locations as $loc): ?>
              <option value="<?= e($loc) ?>" <?= $selLoc === $loc ? 'selected' : '' ?>><?= e($loc) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Capacity *</label>
          <input type="number" name="capacity" class="form-control" min="1" value="<?= e($facility['capacity']) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Open Time *</label>
          <select name="open_time" class="form-select" required>
            <?php
            $selOpen = substr($facility['open_time'], 0, 5);
            $s = strtotime('07:00'); $e = strtotime('18:00');
            for ($t = $s; $t <= $e; $t += 1800):
                $val = date('H:i', $t);
                $lbl = date('g:i A', $t);
            ?>
            <option value="<?= $val ?>" <?= $selOpen === $val ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Close Time *</label>
          <select name="close_time" class="form-select" required>
            <?php
            $selClose = substr($facility['close_time'], 0, 5);
            $s = strtotime('07:00'); $e = strtotime('18:00');
            for ($t = $s; $t <= $e; $t += 1800):
                $val = date('H:i', $t);
                $lbl = date('g:i A', $t);
            ?>
            <option value="<?= $val ?>" <?= $selClose === $val ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Status</label>
          <select name="status" class="form-select">
            <option value="active"   <?= $facility['status']==='active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $facility['status']==='inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Description</label>
          <textarea name="description" class="form-control" rows="3"><?= e($facility['description'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Equipment / Amenities</label>
          <textarea name="equipment" class="form-control" rows="2"><?= e($facility['equipment'] ?? '') ?></textarea>
        </div>
      </div>
      <button type="submit" class="btn btn-primary mt-3">Save Changes</button>
      <a href="<?= BASE_URL ?>/admin/manage_facilities.php" class="btn btn-outline-secondary mt-3 ms-2">Cancel</a>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
