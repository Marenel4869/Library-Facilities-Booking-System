<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$type = $_GET['type'] ?? '';
$allowedTypes = ['','info','success','warning','danger'];
if (!in_array($type, $allowedTypes, true)) $type = '';

$announcements = [];
try {
    ensureContentTables($pdo);
    if ($type === '') {
        $stmt = $pdo->prepare(
            'SELECT id,title,body,type,created_at
             FROM announcements
             WHERE is_active=1
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at   IS NULL OR ends_at   >= NOW())
             ORDER BY created_at DESC
             LIMIT 20'
        );
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare(
            'SELECT id,title,body,type,created_at
             FROM announcements
             WHERE is_active=1
               AND type=?
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at   IS NULL OR ends_at   >= NOW())
             ORDER BY created_at DESC
             LIMIT 20'
        );
        $stmt->execute([$type]);
    }
    $announcements = $stmt->fetchAll();
} catch (PDOException $e) {
    $announcements = [];
}

$pageTitle = 'Announcements';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$typeBadge = [
    'info'    => 'primary',
    'success' => 'success',
    'warning' => 'warning text-dark',
    'danger'  => 'danger',
];
?>
<main><div class="container-fluid py-3 px-3 px-lg-4">

<?= showFlash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0">Announcements</h4>
  <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
    <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/admin/manage_announcements.php">
      <i class="fas fa-gear me-1"></i>Manage
    </a>
  <?php endif; ?>
</div>

<!-- Filter tabs -->
<div class="mb-3">
  <?php foreach (['' => 'All', 'info' => 'Info', 'success' => 'Success', 'warning' => 'Warning', 'danger' => 'Important'] as $val => $lbl): ?>
    <a href="?type=<?= $val ?>" class="btn btn-sm <?= $type === $val ? 'btn-primary' : 'btn-outline-secondary' ?> me-1 mb-1">
      <?= $lbl ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-body">

    <?php if (empty($announcements)): ?>
      <p class="text-center text-muted py-4 mb-0">No announcements found.</p>
    <?php else: ?>
      <div class="d-flex flex-column gap-3">
        <?php foreach ($announcements as $a):
          $badge = $typeBadge[$a['type']] ?? 'secondary';
        ?>
        <div class="card facility-card border-0 shadow-sm">
          <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
              <div>
                <div class="fw-bold" style="font-size:1.05rem;"><?= e($a['title']) ?></div>
                <div class="text-muted small"><?= date('M j, Y g:i A', strtotime($a['created_at'])) ?></div>
              </div>
              <span class="badge bg-<?= $badge ?>"><?= strtoupper(e($a['type'])) ?></span>
            </div>
            <hr class="my-3 hr-soft">
            <div style="color:#334155;line-height:1.55; white-space:pre-wrap;"><?= e($a['body']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
