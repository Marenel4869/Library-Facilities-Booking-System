<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$qRaw = $_GET['q'] ?? '';
$q = trim(is_string($qRaw) ? $qRaw : '');

$facilities = [];
$announcements = [];

if ($q !== '') {
    $like = '%' . $q . '%';

    try {
        $sf = $pdo->prepare('SELECT id, name, location, facility_group FROM facilities WHERE status="active" AND (name LIKE ? OR location LIKE ? OR facility_group LIKE ?) ORDER BY name LIMIT 25');
        $sf->execute([$like, $like, $like]);
        $facilities = $sf->fetchAll();
    } catch (PDOException $e) {
        $facilities = [];
    }

    try {
        ensureContentTables($pdo);
        $sa = $pdo->prepare('SELECT id, title, body, created_at, type FROM announcements WHERE is_active=1 AND (title LIKE ? OR body LIKE ?) ORDER BY created_at DESC LIMIT 10');
        $sa->execute([$like, $like]);
        $announcements = $sa->fetchAll();
    } catch (PDOException $e) {
        $announcements = [];
    }
}

$role = (string)($_SESSION['role'] ?? '');
$pageTitle = 'Search';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

function facilityLink(string $role, int $id): string {
    if ($role === 'admin') {
        return BASE_URL . '/admin/edit_facility.php?id=' . $id;
    }
    if ($role === 'faculty') {
        return BASE_URL . '/faculty/book_facility.php?id=' . $id;
    }
    return BASE_URL . '/student/book_facility.php?id=' . $id;
}

$typeBadge = [
    'info'    => 'primary',
    'success' => 'success',
    'warning' => 'warning text-dark',
    'danger'  => 'danger',
];
?>

<main><div class="container py-4" style="max-width:1000px">

  <?= showFlash() ?>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h4 class="mb-0 fw-bold"><i class="fas fa-search me-2 text-primary"></i>Search</h4>
      <small class="text-muted">Find facilities and announcements</small>
    </div>
  </div>

  <div class="card facility-card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="get" class="d-flex gap-2 flex-wrap">
        <input type="search" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Search facilities, announcements..." style="max-width:520px" autocomplete="off">
        <button class="btn btn-primary" type="submit"><i class="fas fa-search me-1"></i>Search</button>
        <?php if ($q !== ''): ?>
          <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/search.php">Clear</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <?php if ($q === ''): ?>
    <div class="alert alert-info border-0 soft-radius">
      Type a keyword above to search (e.g., "Audio", "Library", "closure").
    </div>
  <?php else: ?>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card facility-card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="fw-bold"><i class="fas fa-building me-2 text-primary"></i>Facilities</div>
              <span class="badge bg-light text-dark"><?= count($facilities) ?></span>
            </div>
            <?php if (empty($facilities)): ?>
              <div class="text-muted small">No facilities matched “<?= e($q) ?>”.</div>
            <?php else: ?>
              <div class="list-group list-group-flush">
                <?php foreach ($facilities as $f): ?>
                  <a class="list-group-item list-group-item-action" href="<?= facilityLink($role, (int)$f['id']) ?>">
                    <div class="fw-semibold"><?= e($f['name']) ?></div>
                    <div class="text-muted small">
                      <?= e($f['facility_group'] ?? '') ?>
                      <?= (!empty($f['location'])) ? ' • ' . e($f['location']) : '' ?>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card facility-card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="fw-bold"><i class="fas fa-bullhorn me-2 text-primary"></i>Announcements</div>
              <span class="badge bg-light text-dark"><?= count($announcements) ?></span>
            </div>
            <?php if (empty($announcements)): ?>
              <div class="text-muted small">No announcements matched “<?= e($q) ?>”.</div>
            <?php else: ?>
              <div class="d-flex flex-column gap-2">
                <?php foreach ($announcements as $a):
                  $badge = $typeBadge[$a['type']] ?? 'secondary';
                ?>
                  <a class="text-decoration-none" href="<?= BASE_URL ?>/announcements.php" style="color:inherit">
                    <div class="p-3 soft-panel">
                      <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="fw-semibold"><?= e($a['title']) ?></div>
                        <span class="badge bg-<?= $badge ?>"><?= strtoupper(e($a['type'])) ?></span>
                      </div>
                      <div class="text-muted small mb-1"><?= date('M j, Y g:i A', strtotime($a['created_at'])) ?></div>
                      <div class="text-muted small"><?= e(mb_strimwidth((string)$a['body'], 0, 140, '...')) ?></div>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  <?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
