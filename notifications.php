<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$uid = (int)$_SESSION['user_id'];

// Open a notification: mark read then redirect to its link
if (isset($_GET['open'])) {
    $nid = (int)$_GET['open'];
    if ($nid > 0) {
        $s = $pdo->prepare('SELECT link FROM notifications WHERE id=? AND user_id=?');
        $s->execute([$nid, $uid]);
        $link = (string)($s->fetchColumn() ?: '');

        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([$nid, $uid]);

        // Only allow internal redirects (no protocol-relative URLs like //evil.com)
        $link = preg_replace("/[\r\n]+/", '', $link);
        $isBaseUrl = $link && strpos($link, BASE_URL) === 0;
        $isPath    = $link && strpos($link, '/') === 0 && strpos($link, '//') !== 0;
        if ($isBaseUrl || $isPath) {
            header('Location: ' . $link);
            exit;
        }
    }
    header('Location: ' . BASE_URL . '/notifications.php');
    exit;
}

// Check and fire reminders
checkReminders($pdo);

// Pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$filter = $_GET['filter'] ?? 'all';   // all | unread

$filterSql = $filter === 'unread' ? ' AND is_read=0' : '';

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=?" . $filterSql);
$stmtCount->execute([$uid]);
$total = (int)$stmtCount->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=?" . $filterSql . " ORDER BY created_at DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);
$stmt->execute([$uid]);
$notifs = $stmt->fetchAll();

$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$stmtUnread->execute([$uid]);
$unread = (int)$stmtUnread->fetchColumn();

$pageTitle = 'Notifications';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main><div class="container py-3" style="max-width:800px">

<?= showFlash() ?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <h4 class="mb-0">
    <i class="fas fa-bell me-2 text-primary"></i>Notifications
    <?php if ($unread > 0): ?>
      <span class="badge bg-danger ms-1"><?= $unread ?></span>
    <?php endif; ?>
  </h4>
  <div class="d-flex gap-2">
    <div class="btn-group btn-group-sm">
      <a href="?filter=all"    class="btn <?= $filter==='all'    ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
      <a href="?filter=unread" class="btn <?= $filter==='unread' ? 'btn-primary' : 'btn-outline-secondary' ?>">Unread</a>
    </div>
    <?php if (!empty($notifs)): ?>
    <button class="btn btn-sm btn-outline-secondary" onclick="doAction('mark_all')">
      <i class="fas fa-check-double me-1"></i>Mark all read
    </button>
    <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('Delete all notifications?'))doAction('delete_all')">
      <i class="fas fa-trash me-1"></i>Clear all
    </button>
    <?php endif; ?>
  </div>
</div>

<div class="card shadow-sm" id="notifList">
  <?php if (empty($notifs)): ?>
    <div class="text-center py-5 text-muted">
      <i class="fas fa-bell-slash fa-3x mb-3 d-block opacity-25"></i>
      <p class="mb-0"><?= $filter === 'unread' ? 'No unread notifications.' : 'No notifications yet.' ?></p>
    </div>
  <?php else: ?>
    <?php
      $typeIcon  = ['success'=>'check-circle','danger'=>'times-circle','warning'=>'exclamation-circle','info'=>'info-circle'];
      $typeColor = ['success'=>'success','danger'=>'danger','warning'=>'warning','info'=>'primary'];
    ?>
    <?php foreach ($notifs as $n):
      $ic  = $typeIcon[$n['type']]  ?? 'circle';
      $col = $typeColor[$n['type']] ?? 'secondary';
    ?>
    <div class="d-flex align-items-start gap-3 px-4 py-3 border-bottom notif-row <?= $n['is_read'] ? 'bg-white' : 'bg-primary-subtle' ?>"
         id="notif-<?= $n['id'] ?>">
      <div class="mt-1">
        <i class="fas fa-<?= $ic ?> fa-lg text-<?= $col ?>"></i>
      </div>
      <div class="flex-grow-1">
        <p class="mb-1 <?= $n['is_read'] ? 'text-muted' : 'fw-semibold' ?>" style="line-height:1.4"><?= e($n['message']) ?></p>
        <small class="text-muted"><?= date('F j, Y \\a\\t g:i A', strtotime($n['created_at'])) ?></small>
        <?php if ($n['link']): ?>
          <a href="<?= BASE_URL . '/notifications.php?open=' . (int)$n['id'] ?>" class="ms-2 small text-primary">View →</a>
        <?php endif; ?>
      </div>
      <div class="d-flex flex-column gap-1 ms-2 flex-shrink-0">
        <?php if (!$n['is_read']): ?>
          <button class="btn btn-sm btn-link p-0 text-muted" title="Mark read"
                  onclick="markOne(<?= $n['id'] ?>)"><i class="fas fa-check"></i></button>
        <?php endif; ?>
        <button class="btn btn-sm btn-link p-0 text-danger" title="Delete"
                onclick="deleteOne(<?= $n['id'] ?>)"><i class="fas fa-times"></i></button>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<nav class="mt-3">
  <ul class="pagination pagination-sm justify-content-center">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <li class="page-item <?= $p===$page ? 'active' : '' ?>">
        <a class="page-link" href="?filter=<?= $filter ?>&page=<?= $p ?>"><?= $p ?></a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<script>
function doAction(action) {
    fetch('<?= BASE_URL ?>/admin/notify.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=' + action
    }).then(() => location.reload());
}

function markOne(id) {
    fetch('<?= BASE_URL ?>/admin/notify.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=mark_one&id=' + id
    }).then(() => location.reload());
}

function deleteOne(id) {
    fetch('<?= BASE_URL ?>/admin/notify.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=delete_one&id=' + id
    }).then(() => location.reload());
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
