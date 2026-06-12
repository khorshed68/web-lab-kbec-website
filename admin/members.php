<?php
require_once __DIR__ . '/admin_shell.php';
$db  = getDB();
$msg = ''; $msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id) {
        if ($id === (int)$_SESSION['member_id']) { $msg='Cannot delete your own account.'; $msgType='danger'; }
        else { $db->prepare("DELETE FROM members WHERE id=?")->execute([$id]); $msg='Member deleted.'; }
    } elseif ($action === 'update_role' && $id) {
        $role     = in_array($_POST['role'],['member','admin'],true)?$_POST['role']:'member';
        $verified = (int)($_POST['verified']??1);
        $db->prepare("UPDATE members SET role=?,verified=? WHERE id=?")->execute([$role,$verified,$id]);
        $msg='Member updated.';
    } elseif ($action === 'reset_password' && $id) {
        $newPwd  = 'KbecReset2026!';
        $newHash = password_hash($newPwd, PASSWORD_BCRYPT);
        $db->prepare("UPDATE members SET password_hash=? WHERE id=?")->execute([$newHash,$id]);
        $msg="Password reset to: {$newPwd}";
    }
}

$q       = trim($_GET['q'] ?? '');
$page    = max(1,(int)($_GET['page']??1));
$perPage = 20; $offset = ($page-1)*$perPage;
$where   = $q ? "WHERE (name LIKE ? OR email LIKE ? OR student_id LIKE ? OR member_code LIKE ?)" : "";
$params  = $q ? ["%$q%","%$q%","%$q%","%$q%"] : [];
$cntStmt = $db->prepare("SELECT COUNT(*) FROM members $where"); $cntStmt->execute($params);
$total   = (int)$cntStmt->fetchColumn();
$pages   = max(1,(int)ceil($total/$perPage));
$listStmt= $db->prepare("SELECT * FROM members $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$listStmt->execute($params);
$members = $listStmt->fetchAll();

adminShellOpen('Members', 'members');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div style="color:var(--ap-muted);font-size:.82rem"><?= number_format($total) ?> total members</div>
  <a href="../register.php" target="_blank" class="ap-btn ap-btn-outline ap-btn-sm"><i class="bi bi-person-plus"></i> Register Page</a>
</div>
<?php if ($msg) echo adminAlert($msg, $msgType); ?>

<!-- Search -->
<form method="GET" class="ap-search">
  <input type="text" name="q" class="ap-input" placeholder="Search name, email, student ID, code…" value="<?= htmlspecialchars($q,ENT_QUOTES) ?>">
  <button type="submit" class="ap-btn ap-btn-gold"><i class="bi bi-search"></i> Search</button>
  <?php if($q):?><a href="members.php" class="ap-btn ap-btn-outline">Clear</a><?php endif;?>
</form>

<div class="ap-card p-0">
  <div class="ap-table-wrap">
    <table class="ap-table">
      <thead><tr><th>#</th><th>Code</th><th>Member</th><th>Dept / Batch</th><th>Role</th><th>Verified</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($members as $m): ?>
        <tr>
          <td style="color:var(--ap-muted);font-size:.72rem"><?= (int)$m['id'] ?></td>
          <td><span style="font-family:monospace;font-size:.72rem;color:var(--ap-gold)"><?= htmlspecialchars($m['member_code'],ENT_QUOTES) ?></span></td>
          <td>
            <b><?= htmlspecialchars($m['name'],ENT_QUOTES) ?></b><br>
            <small style="color:var(--ap-muted)"><?= htmlspecialchars($m['email'],ENT_QUOTES) ?></small><br>
            <small style="color:var(--ap-muted);font-family:monospace"><?= htmlspecialchars($m['student_id'],ENT_QUOTES) ?></small>
          </td>
          <td style="font-size:.78rem;color:var(--ap-muted)"><?= htmlspecialchars($m['department']??'—',ENT_QUOTES) ?><?= $m['batch']?' ('.$m['batch'].')':'' ?></td>
          <td><span class="ap-badge ap-badge-<?= $m['role']==='admin'?'gold':'blue' ?>"><?= $m['role'] ?></span></td>
          <td><?= $m['verified']?'<span class="ap-badge ap-badge-green">✓ Yes</span>':'<span class="ap-badge ap-badge-red">✗ No</span>' ?></td>
          <td style="color:var(--ap-muted);font-size:.72rem"><?= date('M j, Y',strtotime($m['created_at'])) ?></td>
          <td>
            <div class="d-flex gap-1 flex-wrap">
              <button class="ap-btn ap-btn-outline ap-btn-sm" onclick='openEdit(<?= (int)$m["id"]?>,<?= (int)$m["verified"]?>,`<?= $m["role"] ?>`)'>
                <i class="bi bi-pencil"></i>
              </button>
              <?php if($m['id']!=$_SESSION['member_id']): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this member?')">
                <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$m['id']?>">
                <button type="submit" class="ap-btn ap-btn-danger ap-btn-sm"><i class="bi bi-trash"></i></button>
              </form>
              <form method="POST" style="display:inline" onsubmit="return confirm('Reset password to KbecReset2026!?')">
                <?=csrfField()?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?=(int)$m['id']?>">
                <button type="submit" class="ap-btn ap-btn-outline ap-btn-sm" title="Reset Password"><i class="bi bi-key"></i></button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($pages>1): ?>
<div class="ap-pagination">
  <?php for($p=1;$p<=$pages;$p++): ?>
    <a href="?page=<?=$p?>&q=<?=urlencode($q)?>" class="ap-page-btn <?=$p==$page?'active':''?>"><?=$p?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<!-- Edit Modal -->
<div class="ap-modal-bg" id="editModal">
  <div class="ap-modal">
    <div class="ap-modal-title"><i class="bi bi-person-gear"></i> Edit Member</div>
    <form method="POST">
      <?=csrfField()?>
      <input type="hidden" name="action" value="update_role">
      <input type="hidden" name="id" id="editId">
      <div class="mb-3">
        <label class="ap-label">Role</label>
        <select name="role" id="editRole" class="ap-input">
          <option value="member">member</option>
          <option value="admin">admin</option>
        </select>
      </div>
      <div class="mb-4">
        <label class="ap-label">Verified</label>
        <select name="verified" id="editVerified" class="ap-input">
          <option value="1">Yes — Verified</option>
          <option value="0">No — Unverified</option>
        </select>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="ap-btn ap-btn-gold">Save Changes</button>
        <button type="button" class="ap-btn ap-btn-outline" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
function openEdit(id,verified,role){
  document.getElementById('editId').value=id;
  document.getElementById('editRole').value=role;
  document.getElementById('editVerified').value=verified;
  document.getElementById('editModal').classList.add('open');
}
document.getElementById('editModal').addEventListener('click',e=>{if(e.target===e.currentTarget)e.currentTarget.classList.remove('open');});
</script>
<?php adminShellClose(); ?>
