<?php
require_once __DIR__ . '/admin_shell.php';
$db  = getDB();
$msg = ''; $msgType = 'success';

// ── Handle POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $title      = trim($_POST['title'] ?? '');
        $body       = trim($_POST['body'] ?? '');
        $type       = in_array($_POST['type'],['info','warning','success','urgent'])?$_POST['type']:'info';
        $link       = trim($_POST['link'] ?? '');
        $link_label = trim($_POST['link_label'] ?? '');
        $is_active  = isset($_POST['is_active']) ? 1 : 0;

        if (!$title) { $msg = 'Title is required.'; $msgType = 'danger'; }
        elseif ($action === 'create') {
            $db->prepare("INSERT INTO announcements (title,body,type,link,link_label,is_active) VALUES (?,?,?,?,?,?)")
               ->execute([$title,$body,$type,$link,$link_label,$is_active]);
            $msg = 'Announcement published!';
        } else {
            $id = (int)$_POST['id'];
            $db->prepare("UPDATE announcements SET title=?,body=?,type=?,link=?,link_label=?,is_active=? WHERE id=?")
               ->execute([$title,$body,$type,$link,$link_label,$is_active,$id]);
            $msg = 'Announcement updated.';
        }
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM announcements WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Deleted.';
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE announcements SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
        $msg = 'Status toggled.';
    }
}

$announcements = $db->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
$editRow = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM announcements WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $editRow = $s->fetch();
}
$showForm = isset($_GET['action']) && $_GET['action']==='new' || $editRow;

adminShellOpen('Announcements', 'announcements');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <div style="font-size:.82rem;color:var(--ap-muted)">Manage banners and ticker messages shown on the homepage</div>
  </div>
  <a href="?action=new" class="ap-btn ap-btn-gold"><i class="bi bi-plus-lg"></i> New Announcement</a>
</div>

<?php if ($msg) echo adminAlert($msg, $msgType); ?>

<!-- Form -->
<?php if ($showForm): ?>
<div class="ap-card mb-3">
  <div class="ap-card-title"><i class="bi bi-<?= $editRow?'pencil':'plus-circle' ?>-fill"></i> <?= $editRow?'Edit':'New' ?> Announcement</div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="<?= $editRow?'edit':'create' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif; ?>
    <div class="row g-3">
      <div class="col-md-8">
        <label class="ap-label">Announcement Title *</label>
        <input type="text" name="title" class="ap-input" required placeholder="e.g. Registration Open for NEXUS 2026"
               value="<?= htmlspecialchars($editRow['title']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-2">
        <label class="ap-label">Type / Color</label>
        <select name="type" class="ap-input">
          <?php foreach(['info'=>'ℹ️ Info','warning'=>'⚠️ Warning','success'=>'✅ Success','urgent'=>'🚨 Urgent'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($editRow['type']??'info')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="ap-label">Active / Live</label>
        <div style="padding-top:10px">
          <label class="ap-toggle">
            <input type="checkbox" name="is_active" <?= ($editRow['is_active']??1)?'checked':'' ?>>
            <span class="ap-toggle-slider"></span>
          </label>
        </div>
      </div>
      <div class="col-12">
        <label class="ap-label">Message Body (optional)</label>
        <textarea name="body" class="ap-input" rows="3" placeholder="Additional details shown in expanded banner…"><?= htmlspecialchars($editRow['body']??'',ENT_QUOTES) ?></textarea>
      </div>
      <div class="col-md-6">
        <label class="ap-label">Call-to-Action Link (optional)</label>
        <input type="url" name="link" class="ap-input" placeholder="https://…"
               value="<?= htmlspecialchars($editRow['link']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-6">
        <label class="ap-label">CTA Button Label</label>
        <input type="text" name="link_label" class="ap-input" placeholder="e.g. Register Now"
               value="<?= htmlspecialchars($editRow['link_label']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="ap-btn ap-btn-gold"><i class="bi bi-check-lg"></i> <?= $editRow?'Update':'Publish' ?></button>
        <a href="announcements.php" class="ap-btn ap-btn-outline">Cancel</a>
      </div>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- Table -->
<div class="ap-card p-0">
  <div class="ap-table-wrap">
    <table class="ap-table">
      <thead><tr><th>Title</th><th>Type</th><th>Body</th><th>CTA</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($announcements as $a): ?>
        <tr>
          <td><b><?= htmlspecialchars($a['title'],ENT_QUOTES) ?></b></td>
          <td><span class="ap-badge ap-badge-<?= ['info'=>'blue','warning'=>'gold','success'=>'green','urgent'=>'red'][$a['type']] ?>"><?= $a['type'] ?></span></td>
          <td style="max-width:180px;font-size:.78rem;color:var(--ap-muted)"><?= htmlspecialchars(substr($a['body']??'',0,60),ENT_QUOTES) ?></td>
          <td style="font-size:.75rem;color:var(--ap-muted)"><?= $a['link'] ? htmlspecialchars($a['link_label']??'Link',ENT_QUOTES) : '—' ?></td>
          <td>
            <form method="POST" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <button type="submit" class="ap-badge <?= $a['is_active']?'ap-badge-green':'ap-badge-grey' ?>" style="border:none;cursor:pointer;background:inherit">
                <?= $a['is_active']?'🟢 Live':'⚫ Off' ?>
              </button>
            </form>
          </td>
          <td style="color:var(--ap-muted);font-size:.75rem"><?= date('M j, Y',strtotime($a['created_at'])) ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="?edit=<?= (int)$a['id'] ?>" class="ap-btn ap-btn-outline ap-btn-sm"><i class="bi bi-pencil"></i></a>
              <form method="POST" style="display:inline">
                <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button type="submit" class="ap-btn ap-btn-danger ap-btn-sm" onclick="return confirm('Delete this announcement?')"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($announcements)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--ap-muted);padding:30px">No announcements yet. Create one!</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php adminShellClose(); ?>
