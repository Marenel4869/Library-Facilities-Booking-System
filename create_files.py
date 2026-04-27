import os

# Base path
base_path = r'C:\xampp\htdocs\Library-Facilities-Booking-System'

# File 1 content
file1_content = '''<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$uid    = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'mark_all':
        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$uid]);
        echo json_encode(['success' => true]);
        break;

    case 'mark_one':
        $nid = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([$nid, $uid]);
        echo json_encode(['success' => true]);
        break;

    case 'count':
        $count = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid AND is_read=0")->fetchColumn();
        echo json_encode(['success' => true, 'count' => $count]);
        break;

    case 'delete_one':
        $nid = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM notifications WHERE id=? AND user_id=?')->execute([$nid, $uid]);
        echo json_encode(['success' => true]);
        break;

    case 'delete_all':
        $pdo->prepare('DELETE FROM notifications WHERE user_id=?')->execute([$uid]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
exit;
?>
'''

# File 2 content
file2_content = '''<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$uid = (int)$_SESSION['user_id'];

// Check and fire reminders
checkReminders($pdo);

// Pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$filter = $_GET['filter'] ?? 'all';   // all | unread

$whereExtra = $filter === 'unread' ? 'AND is_read=0' : '';

$total = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid $whereExtra")->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? $whereExtra ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$uid, $perPage, $offset]);
$notifs = $stmt->fetchAll();

$unread = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid AND is_read=0")->fetchColumn();

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
        <small class="text-muted"><?= date('F j, Y \a\t g:i A', strtotime($n['created_at'])) ?></small>
        <?php if ($n['link']): ?>
          <a href="<?= e($n['link']) ?>" class="ms-2 small text-primary">View →</a>
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

</div></main>

<script>
function doAction(action) {
    fetch('<?= BASE_URL ?>/api/notify.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=' + action
    }).then(() => location.reload());
}

function markOne(id) {
    fetch('<?= BASE_URL ?>/api/notify.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=mark_one&id=' + id
    }).then(() => {
        const row = document.getElementById('notif-' + id);
        if (row) {
            row.classList.remove('bg-primary-subtle');
            row.querySelector('p') && row.querySelector('p').classList.replace('fw-semibold','text-muted');
            row.querySelector('[title="Mark read"]') && row.querySelector('[title="Mark read"]').remove();
        }
    });
}

function deleteOne(id) {
    fetch('<?= BASE_URL ?>/api/notify.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=delete_one&id=' + id
    }).then(() => {
        const row = document.getElementById('notif-' + id);
        if (row) row.remove();
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
'''

# File 3 content (empty)
file3_content = ''

# Define file paths
file1_path = os.path.join(base_path, 'api', 'notify.php')
file2_path = os.path.join(base_path, 'notifications.php')
file3_path = os.path.join(base_path, 'logs', '.gitkeep')

# Create directories
os.makedirs(os.path.dirname(file1_path), exist_ok=True)
os.makedirs(os.path.dirname(file3_path), exist_ok=True)

# Write files
with open(file1_path, 'w', encoding='utf-8') as f:
    f.write(file1_content)

with open(file2_path, 'w', encoding='utf-8') as f:
    f.write(file2_content)

with open(file3_path, 'w', encoding='utf-8') as f:
    f.write(file3_content)

# Count lines
def count_lines(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        return len(f.readlines())

line_count_1 = count_lines(file1_path)
line_count_2 = count_lines(file2_path)
line_count_3 = count_lines(file3_path)

print("✅ Files created successfully!")
print()
print("FILE SUMMARY:")
print("=" * 70)
print(f"FILE 1: {file1_path}")
print(f"        Lines: {line_count_1}")
print()
print(f"FILE 2: {file2_path}")
print(f"        Lines: {line_count_2}")
print()
print(f"FILE 3: {file3_path}")
print(f"        Lines: {line_count_3} (empty file)")
print()
print("DIRECTORY VERIFICATION:")
print("=" * 70)
print(f"✓ {os.path.dirname(file1_path)} - EXISTS" if os.path.isdir(os.path.dirname(file1_path)) else f"✗ {os.path.dirname(file1_path)} - MISSING")
print(f"✓ {os.path.dirname(file2_path)} - EXISTS" if os.path.isdir(os.path.dirname(file2_path)) else f"✗ {os.path.dirname(file2_path)} - MISSING")
print(f"✓ {os.path.dirname(file3_path)} - EXISTS" if os.path.isdir(os.path.dirname(file3_path)) else f"✗ {os.path.dirname(file3_path)} - MISSING")
