<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('admin');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name']        ?? '');
    $type       = trim($_POST['type']        ?? '');
    $location   = trim($_POST['location']    ?? '');
    $capacity   = (int)($_POST['capacity']   ?? 0);
    $openTime   = trim($_POST['open_time']   ?? '');
    $closeTime  = trim($_POST['close_time']  ?? '');
    $description= trim($_POST['description'] ?? '');
    $equipment  = trim($_POST['equipment']   ?? '');

    if (!$name)     $errors[] = 'Name is required.';
    if (!$type)     $errors[] = 'Type is required.';
    if (!$location) $errors[] = 'Location is required.';
    if ($capacity < 1) $errors[] = 'Capacity must be at least 1.';
    if (!$openTime || !$closeTime) $errors[] = 'Operating hours are required.';
    if ($openTime >= $closeTime) $errors[] = 'Close time must be after open time.';

    if (empty($errors)) {
        $pdo->prepare('INSERT INTO facilities (name,type,location,capacity,open_time,close_time,description,equipment,status) VALUES (?,?,?,?,?,?,?,?,"active")')
            ->execute([$name, $type, $location, $capacity, $openTime, $closeTime, $description, $equipment]);
        flash('success', 'Facility added successfully.');
        header('Location: ' . BASE_URL . '/admin/manage_facilities.php'); exit;
    }
}

$pageTitle = 'Add Facility';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container-fluid py-3 px-3 px-lg-4 admin-page">

<?= showFlash() ?>

<div class="admin-hero mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex align-items-center gap-3">
      <div class="admin-hero-icon"><i class="fas fa-plus"></i></div>
      <div>
        <h4 class="mb-0 fw-bold text-white">Add Facility</h4>
        <div class="admin-hero-sub">Create a new bookable facility</div>
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
    <form method="POST">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Facility Name *</label>
          <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Type *</label>
          <select name="type" class="form-select" required>
            <option value="">-- Select --</option>
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
              <option value="<?= $val ?>" <?= ($_POST['type'] ?? '')===$val ? 'selected' : '' ?>><?= $lbl ?></option>
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
            $selLoc = $_POST['location'] ?? '';
            foreach ($locations as $loc): ?>
              <option value="<?= e($loc) ?>" <?= $selLoc === $loc ? 'selected' : '' ?>><?= e($loc) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Capacity *</label>
          <input type="number" name="capacity" class="form-control" min="1" value="<?= e($_POST['capacity'] ?? '') ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Open Time *</label>
          <select name="open_time" class="form-select" required>
            <?php
            $selOpen = $_POST['open_time'] ?? '08:00';
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
            $selClose = $_POST['close_time'] ?? '18:00';
            $s = strtotime('07:00'); $e = strtotime('18:00');
            for ($t = $s; $t <= $e; $t += 1800):
                $val = date('H:i', $t);
                $lbl = date('g:i A', $t);
            ?>
            <option value="<?= $val ?>" <?= $selClose === $val ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Description</label>
          <textarea name="description" class="form-control" rows="3"><?= e($_POST['description'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Equipment / Amenities</label>
          <textarea name="equipment" class="form-control" rows="2" placeholder="e.g. Projector, Whiteboard, AC"><?= e($_POST['equipment'] ?? '') ?></textarea>
        </div>
      </div>
      <button type="submit" class="btn btn-primary mt-3">Add Facility</button>
      <a href="<?= BASE_URL ?>/admin/manage_facilities.php" class="btn btn-outline-secondary mt-3 ms-2">Cancel</a>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
