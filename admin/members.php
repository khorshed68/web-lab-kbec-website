<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
startSession();
requireAdmin('../index.php');

$db   = getDB();
$csrf = csrfToken();
$msg  = '';
$msgType = 'success';

// ── Handle POST actions ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        // Prevent deleting self or last admin
        if ($id === (int)$_SESSION['member_id']) { $msg='You cannot delete your own account.'; $msgType='error'; }
        else { $db->prepare("DELETE FROM `members` WHERE id=?")->execute([$id]); $msg='Member deleted.'; }
    } elseif ($action === 'update_role' && $id) {
        $role     = in_array($_POST['role'],['member','admin'],'true') ? $_POST['role'] : 'member';
        $verified = (int)($_POST['verified'] ?? 1);
        $db->prepare("UPDATE `members` SET role=?,verified=? WHERE id=?")->execute([$role,$verified,$id]);
        $msg = 'Member updated.';
    }
}

// ── Pagination & search ─────────────────────────────────
$q    = trim($_GET['q'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = $q ? "WHERE (name LIKE ? OR email LIKE ? OR student_id LIKE ? OR member_code LIKE ?)" : "";
$params = $q ? ["%$q%","%$q%","%$q%","%$q%"] : [];

$totalStmt = $db->prepare("SELECT COUNT(*) FROM `members` $where");
$totalStmt->execute($params);
$totalRows = (int)$totalStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $perPage);

$listStmt = $db->prepare("SELECT * FROM `members` $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$listStmt->execute($params);
$members = $listStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf,ENT_QUOTES) ?>">
  <title>Members | KBEC Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cinzel:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; renderHeader('Member Management','admin_members'); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h1 class="kbec-page-title mb-1">Members</h1>
    <p class="kbec-page-sub mb-0"><?= number_format($totalRows) ?> total members</p>
  </div>
</div>

<?php if ($msg): ?>
  <div class="kbec-alert kbec-alert-<?= $msgType === 'error' ? 'error' : 'success' ?> mb-3"><?= htmlspecialchars($msg,ENT_QUOTES) ?></div>
<?php endif; ?>

<!-- Search -->
<form method="GET" class="mb-3 d-flex gap-2">
  <input type="text" name="q" class="kbec-input" style="max-width:320px" placeholder="Search name, email, student ID…" value="<?= htmlspecialchars($q,ENT_QUOTES) ?>">
  <button type="submit" class="kbec-btn kbec-btn-gold"><i class="bi bi-search"></i></button>
  <?php if ($q): ?><a href="members.php" class="kbec-btn kbec-btn-outline">Clear</a><?php endif; ?>
</form>

<div class="kbec-card p-0" style="overflow-x:auto">
  <table class="kbec-table">
    <thead><tr><th>#</th><th>Code</th><th>Name</th><th>Email</th><th>Dept</th><th>Batch</th><th>Role</th><th>Verified</th><th>Joined</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($members as $m): ?>
      <tr>
        <td style="color:rgba(255,255,255,.3);font-size:.75rem"><?= (int)$m['id'] ?></td>
        <td><span style="font-family:monospace;font-size:.75rem;color:#c9a84c"><?= htmlspecialchars($m['member_code'],ENT_QUOTES) ?></span></td>
        <td><b><?= htmlspecialchars($m['name'],ENT_QUOTES) ?></b><br><small style="color:rgba(255,255,255,.4)"><?= htmlspecialchars($m['student_id'],ENT_QUOTES) ?></small></td>
        <td style="font-size:.8rem"><?= htmlspecialchars($m['email'],ENT_QUOTES) ?></td>
        <td style="font-size:.78rem;color:rgba(255,255,255,.6)"><?= htmlspecialchars(substr($m['department']??'',0,18),ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($m['batch']??'',ENT_QUOTES) ?></td>
        <td><span class="<?= $m['role']==='admin'?'badge-gold':'badge-blue' ?>"><?= $m['role'] ?></span></td>
        <td><?= $m['verified']?'<span class="badge-green">Yes</span>':'<span class="badge-red">No</span>' ?></td>
        <td style="font-size:.75rem;color:rgba(255,255,255,.4)"><?= date('M j, Y',strtotime($m['created_at'])) ?></td>
        <td>
          <div class="d-flex gap-1 flex-wrap">
            <!-- Edit button opens modal -->
            <button class="kbec-btn kbec-btn-outline" style="padding:5px 10px;font-size:.75rem"
              onclick='openEdit(<?= (int)$m['id'] ?>,<?= (int)$m['verified'] ?>,`<?= htmlspecialchars($m['role'],ENT_QUOTES) ?>`)'>
              <i class="bi bi-pencil"></i>
            </button>
            <!-- Delete -->
            <?php if ($m['id'] != $_SESSION['member_id']): ?>
            <form method="POST">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <button type="submit" class="kbec-btn kbec-btn-danger" style="padding:5px 10px;font-size:.75rem"
                onclick="return confirm('Delete this member?')"><i class="bi bi-trash"></i></button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="d-flex gap-1 mt-3 flex-wrap">
  <?php for($p=1;$p<=$totalPages;$p++): ?>
    <a href="?page=<?= $p ?>&q=<?= urlencode($q) ?>" class="kbec-btn kbec-btn-<?= $p==$page?'gold':'outline' ?>" style="padding:6px 12px;font-size:.8rem;text-decoration:none"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<!-- Edit Modal -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;display:none;align-items:center;justify-content:center">
  <div style="background:#141926;border:1px solid rgba(201,168,76,.2);border-radius:16px;padding:28px;min-width:320px;max-width:440px;width:90%">
    <h5 style="color:#c9a84c;margin-bottom:20px">Edit Member</h5>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="update_role">
      <input type="hidden" name="id" id="edit-id">
      <div class="mb-3">
        <label class="kbec-label">Role</label>
        <select name="role" id="edit-role" class="kbec-input">
          <option value="member">member</option>
          <option value="admin">admin</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="kbec-label">Verified</label>
        <select name="verified" id="edit-verified" class="kbec-input">
          <option value="1">Yes</option>
          <option value="0">No</option>
        </select>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="kbec-btn kbec-btn-gold">Save</button>
        <button type="button" class="kbec-btn kbec-btn-outline" onclick="closeEdit()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEdit(id, verified, role) {
  document.getElementById('edit-id').value = id;
  document.getElementById('edit-role').value = role;
  document.getElementById('edit-verified').value = verified;
  document.getElementById('editModal').style.display = 'flex';
}
function closeEdit() { document.getElementById('editModal').style.display = 'none'; }
document.getElementById('editModal').addEventListener('click', e => { if(e.target===e.currentTarget) closeEdit(); });
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; renderFooter(); ?>
