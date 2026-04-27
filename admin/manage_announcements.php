<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin('admin');

ensureContentTables($pdo);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body'] ?? '');
        $type  = $_POST['type'] ?? 'info';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $startsAt = trim($_POST['starts_at'] ?? '');
        $endsAt   = trim($_POST['ends_at'] ?? '');

        if ($title === '' || $body === '') {
            flash('warning', 'Title and body are required.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO announcements (title, body, type, starts_at, ends_at, is_active, created_by) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([
                $title,
                $body,
                in_array($type, ['info','warning','success','danger'], true) ? $type : 'info',
                $startsAt !== '' ? $startsAt : null,
                $endsAt !== '' ? $endsAt : null,
                $isActive,
                (int)($_SESSION['user_id'] ?? 0),
            ]);
            flash('success', 'Announcement posted.');
        }
        header('Location: ' . BASE_URL . '/admin/manage_announcements.php');
        exit;
    }

    if ($action === 'update') {
        $id    = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body'] ?? '');
        $type  = $_POST['type'] ?? 'info';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $startsAt = trim($_POST['starts_at'] ?? '');
        $endsAt   = trim($_POST['ends_at'] ?? '');

        if ($id <= 0 || $title === '' || $body === '') {
            flash('warning', 'Invalid announcement data.');
        } else {
            $stmt = $pdo->prepare('UPDATE announcements SET title=?, body=?, type=?, starts_at=?, ends_at=?, is_active=? WHERE id=?');
            $stmt->execute([
                $title,
                $body,
                in_array($type, ['info','warning','success','danger'], true) ? $type : 'info',
                $startsAt !== '' ? $startsAt : null,
                $endsAt !== '' ? $endsAt : null,
                $isActive,
                $id,
            ]);
            flash('success', 'Announcement updated.');
        }
        header('Location: ' . BASE_URL . '/admin/manage_announcements.php');
        exit;
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $to = (int)($_POST['to'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('UPDATE announcements SET is_active=? WHERE id=?')->execute([$to, $id]);
            flash('success', 'Announcement status updated.');
        }
        header('Location: ' . BASE_URL . '/admin/manage_announcements.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM announcements WHERE id=?')->execute([$id]);
            flash('success', 'Announcement deleted.');
        }
        header('Location: ' . BASE_URL . '/admin/manage_announcements.php');
        exit;
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    $s = $pdo->prepare('SELECT * FROM announcements WHERE id=?');
    $s->execute([$editId]);
    $edit = $s->fetch();
}

$rows = $pdo->query('SELECT * FROM announcements ORDER BY created_at DESC LIMIT 80')->fetchAll();

$pageTitle = 'Manage Announcements';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main><div class="container-fluid py-3 px-3 px-lg-4 admin-page">

<?= showFlash() ?>

<div class="admin-hero mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex align-items-center gap-3">
      <div class="admin-hero-icon"><i class="fas fa-bullhorn"></i></div>
      <div>
        <h4 class="mb-0 fw-bold text-white">Announcements</h4>
        <div class="admin-hero-sub">Post events, closures, or maintenance notices</div>
      </div>
    </div>
    <a class="btn btn-light btn-sm" href="<?= BASE_URL ?>/announcements.php">
      <i class="fas fa-eye me-1"></i>View Public Page
    </a>
  </div>
</div>

<div class="card facility-card border-0 shadow-sm mb-4">
  <div class="card-body">
    <h6 class="fw-bold mb-3"><?= $edit ? 'Edit Announcement' : 'Create Announcement' ?></h6>
    <form method="post" class="row g-2">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

      <div class="col-md-6">
        <label class="form-label small fw-semibold">Title</label>
        <input class="form-control" name="title" maxlength="255" required value="<?= e($edit['title'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Type</label>
        <?php $t = $edit['type'] ?? 'info'; ?>
        <select class="form-select" name="type">
          <option value="info"    <?= $t==='info'?'selected':'' ?>>Info</option>
          <option value="success" <?= $t==='success'?'selected':'' ?>>Success</option>
          <option value="warning" <?= $t==='warning'?'selected':'' ?>>Warning</option>
          <option value="danger"  <?= $t==='danger'?'selected':'' ?>>Danger</option>
        </select>
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <?php $active = (int)($edit['is_active'] ?? 1); ?>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= $active ? 'checked' : '' ?>>
          <label class="form-check-label" for="isActive">Active</label>
        </div>
      </div>

      <div class="col-md-6">
        <label class="form-label small fw-semibold">Starts at (optional)</label>
        <input class="form-control" name="starts_at" placeholder="YYYY-MM-DD HH:MM:SS" value="<?= e($edit['starts_at'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-semibold">Ends at (optional)</label>
        <input class="form-control" name="ends_at" placeholder="YYYY-MM-DD HH:MM:SS" value="<?= e($edit['ends_at'] ?? '') ?>">
      </div>

      <div class="col-12">
        <label class="form-label small fw-semibold">Body</label>
        <textarea class="form-control" name="body" rows="4" required><?= e($edit['body'] ?? '') ?></textarea>
      </div>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary">
          <i class="fas fa-paper-plane me-1"></i><?= $edit ? 'Save Changes' : 'Post Announcement' ?>
        </button>
        <?php if ($edit): ?>
          <a class="btn btn-light" href="<?= BASE_URL ?>/admin/manage_announcements.php">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Title</th>
          <th>Type</th>
          <th>Status</th>
          <th>Created</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="5" class="text-muted">No announcements yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($r['title']) ?></div>
              <div class="text-muted small"><?= e(truncateText($r['body'], 90)) ?></div>
            </td>
            <td><span class="badge bg-secondary"><?= strtoupper(e($r['type'])) ?></span></td>
            <td><?= (int)$r['is_active'] ? '<span class="badge bg-success">ACTIVE</span>' : '<span class="badge bg-secondary">INACTIVE</span>' ?></td>
            <td class="text-muted small"><?= date('M j, Y g:i A', strtotime($r['created_at'])) ?></td>
            <td class="text-end">
              <div class="admin-actions" style="justify-content:flex-end;">
                <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$r['id'] ?>" title="Edit">
                  <i class="fas fa-pen"></i>
                </a>
                <form method="post" class="d-inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="to" value="<?= (int)$r['is_active'] ? 0 : 1 ?>">
                  <button class="btn btn-sm btn-outline-secondary" type="submit" title="Toggle active">
                    <i class="fas fa-toggle-<?= (int)$r['is_active'] ? 'on' : 'off' ?>"></i>
                  </button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this announcement?')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
