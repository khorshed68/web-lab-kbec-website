<?php
require_once __DIR__ . '/admin_shell.php';
$db  = getDB();
$msg = ''; $msgType = 'success';

$positions = ['President','General Secretary','Senior Executive Vice President','Senior Vice President','Vice President (Internal Affairs)','Vice President (External Affairs)','Vice President (Operations)','Vice President (Marketing & Branding)','Treasurer','Organizing Secretary','Joint Secretary','Secretary of Human Resources','Secretary of Public Relations','Secretary of Operations','Assistant General Secretary','Assistant Organizing Secretary','Assistant Joint Secretary','Assistant Operations Secretary','Assistant Secretary of Corporate Alliance','Assistant Secretary of Public Relations','Assistant Secretary of Human Resource','Assistant Secretary of Finance','Assistant Secretary of Logistics'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $name           = trim($_POST['name'] ?? '');
        $position       = trim($_POST['position'] ?? '');
        $position_order = (int)($_POST['position_order'] ?? 99);
        $department     = trim($_POST['department'] ?? '');
        $batch          = trim($_POST['batch'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $linkedin       = trim($_POST['linkedin'] ?? '');
        $is_active      = isset($_POST['is_active']) ? 1 : 0;

        if (!$name || !$position) { $msg='Name and position are required.'; $msgType='danger'; }
        else {
            // Handle image upload
            $imagePath = $_POST['current_image'] ?? null;
            if (!empty($_FILES['image']['tmp_name'])) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($_FILES['image']['tmp_name']);
                if (in_array($mime,['image/jpeg','image/png','image/webp'])) {
                    $ext   = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime];
                    $fname = 'team_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
                    $dest  = UPLOAD_DIR.'avatars/'.$fname;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                        $imagePath = 'uploads/avatars/'.$fname;
                    }
                }
            }

            if ($action === 'create') {
                $db->prepare("INSERT INTO team_members (name,position,position_order,department,batch,email,linkedin,image,is_active) VALUES (?,?,?,?,?,?,?,?,?)")
                   ->execute([$name,$position,$position_order,$department,$batch,$email,$linkedin,$imagePath,$is_active]);
                $msg = "Team member '{$name}' added!";
            } else {
                $id = (int)$_POST['id'];
                $db->prepare("UPDATE team_members SET name=?,position=?,position_order=?,department=?,batch=?,email=?,linkedin=?,image=?,is_active=? WHERE id=?")
                   ->execute([$name,$position,$position_order,$department,$batch,$email,$linkedin,$imagePath,$is_active,$id]);
                $msg = 'Team member updated.';
            }
        }
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM team_members WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Team member removed.';
    } elseif ($action === 'toggle') {
        $db->prepare("UPDATE team_members SET is_active = 1-is_active WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Visibility toggled.';
    }
}

$editRow = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM team_members WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $editRow = $s->fetch();
}
$showForm = (isset($_GET['action']) && $_GET['action']==='new') || $editRow;

$members = $db->query("SELECT * FROM team_members ORDER BY position_order ASC, name ASC")->fetchAll();

adminShellOpen('Executive Team', 'team');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div style="font-size:.82rem;color:var(--ap-muted)">Manage the executive committee shown on the homepage</div>
  <a href="?action=new" class="ap-btn ap-btn-gold"><i class="bi bi-person-plus-fill"></i> Add Member</a>
</div>
<?php if ($msg) echo adminAlert($msg, $msgType); ?>

<!-- Form -->
<?php if ($showForm): ?>
<div class="ap-card mb-3">
  <div class="ap-card-title"><i class="bi bi-person-<?= $editRow?'gear':'plus-fill' ?>"></i> <?= $editRow?'Edit':'Add' ?> Team Member</div>
  <form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="<?= $editRow?'edit':'create' ?>">
    <?php if ($editRow): ?>
      <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
      <input type="hidden" name="current_image" value="<?= htmlspecialchars($editRow['image']??'',ENT_QUOTES) ?>">
    <?php endif; ?>
    <div class="row g-3">
      <div class="col-md-5">
        <label class="ap-label">Full Name *</label>
        <input type="text" name="name" class="ap-input" required value="<?= htmlspecialchars($editRow['name']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-5">
        <label class="ap-label">Position *</label>
        <select name="position" class="ap-input" required>
          <option value="">— Select Position —</option>
          <?php foreach ($positions as $i => $p): ?>
            <option value="<?= htmlspecialchars($p,ENT_QUOTES) ?>" <?= ($editRow['position']??'')===$p?'selected':'' ?>><?= htmlspecialchars($p,ENT_QUOTES) ?></option>
          <?php endforeach; ?>
          <option value="__custom">Other (type below)</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="ap-label">Sort Order</label>
        <input type="number" name="position_order" class="ap-input" min="1" max="999" value="<?= (int)($editRow['position_order']??99) ?>">
      </div>
      <div id="customPosWrap" style="display:none" class="col-md-8">
        <label class="ap-label">Custom Position Title</label>
        <input type="text" id="customPos" class="ap-input" placeholder="Enter custom position">
      </div>
      <div class="col-md-4">
        <label class="ap-label">Department</label>
        <input type="text" name="department" class="ap-input" value="<?= htmlspecialchars($editRow['department']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-2">
        <label class="ap-label">Batch</label>
        <input type="text" name="batch" class="ap-input" placeholder="21" value="<?= htmlspecialchars($editRow['batch']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-4">
        <label class="ap-label">Email</label>
        <input type="email" name="email" class="ap-input" value="<?= htmlspecialchars($editRow['email']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-4">
        <label class="ap-label">LinkedIn URL</label>
        <input type="url" name="linkedin" class="ap-input" placeholder="https://linkedin.com/in/…" value="<?= htmlspecialchars($editRow['linkedin']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-4">
        <label class="ap-label">Photo (JPG/PNG/WebP)</label>
        <?php if (!empty($editRow['image'])): ?>
          <img src="<?= htmlspecialchars('../'.$editRow['image'],ENT_QUOTES) ?>" class="ap-img-preview mb-2 d-block" alt="current">
        <?php endif; ?>
        <input type="file" name="image" class="ap-input" accept="image/jpeg,image/png,image/webp" style="padding:7px">
      </div>
      <div class="col-md-2">
        <label class="ap-label">Visible on Site</label>
        <div style="padding-top:10px">
          <label class="ap-toggle">
            <input type="checkbox" name="is_active" <?= ($editRow['is_active']??1)?'checked':'' ?>>
            <span class="ap-toggle-slider"></span>
          </label>
        </div>
      </div>
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="ap-btn ap-btn-gold"><i class="bi bi-check-lg"></i> Save</button>
        <a href="team.php" class="ap-btn ap-btn-outline">Cancel</a>
      </div>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- Team Table -->
<div class="ap-card p-0">
  <div class="ap-table-wrap">
    <table class="ap-table">
      <thead><tr><th>Photo</th><th>Name</th><th>Position</th><th>Dept / Batch</th><th>Order</th><th>Visible</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($members as $m): ?>
        <tr>
          <td>
            <?php if ($m['image']): ?>
              <img src="<?= htmlspecialchars('../'.$m['image'],ENT_QUOTES) ?>" class="ap-img-preview" alt="photo">
            <?php else: ?>
              <div class="ap-img-preview d-flex align-items-center justify-content-center" style="font-size:1.4rem;background:rgba(201,168,76,.07)">👤</div>
            <?php endif; ?>
          </td>
          <td><b><?= htmlspecialchars($m['name'],ENT_QUOTES) ?></b><?php if($m['email']):?><br><small style="color:var(--ap-muted)"><?= htmlspecialchars($m['email'],ENT_QUOTES)?></small><?php endif;?></td>
          <td style="font-size:.82rem"><?= htmlspecialchars($m['position'],ENT_QUOTES) ?></td>
          <td style="font-size:.78rem;color:var(--ap-muted)"><?= htmlspecialchars($m['department']??'',ENT_QUOTES) ?> <?= $m['batch']?'('.$m['batch'].')':'' ?></td>
          <td><span class="ap-badge ap-badge-gold"><?= (int)$m['position_order'] ?></span></td>
          <td>
            <form method="POST" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <button type="submit" class="ap-badge <?= $m['is_active']?'ap-badge-green':'ap-badge-grey' ?>" style="border:none;cursor:pointer;background:inherit">
                <?= $m['is_active']?'Shown':'Hidden' ?>
              </button>
            </form>
          </td>
          <td>
            <div class="d-flex gap-1">
              <a href="?edit=<?= (int)$m['id'] ?>" class="ap-btn ap-btn-outline ap-btn-sm"><i class="bi bi-pencil"></i></a>
              <form method="POST" style="display:inline">
                <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <button type="submit" class="ap-btn ap-btn-danger ap-btn-sm" onclick="return confirm('Remove this team member?')"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($members)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--ap-muted);padding:30px">No team members yet. Add the first one!</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
document.querySelector('select[name="position"]')?.addEventListener('change', function() {
  const wrap = document.getElementById('customPosWrap');
  if (this.value === '__custom') {
    wrap.style.display = 'block';
    document.getElementById('customPos').addEventListener('input', function() {
      document.querySelector('select[name="position"]').value = '__custom';
      // We inject a hidden input instead
      let hi = document.getElementById('customPosHidden');
      if (!hi) { hi = document.createElement('input'); hi.type='hidden'; hi.name='position'; hi.id='customPosHidden'; this.form.appendChild(hi); }
      hi.value = this.value;
    });
  } else { wrap.style.display = 'none'; }
});
<?php if ($editRow): ?>document.getElementById('apSidebar')?.querySelectorAll('.ap-nav-link').forEach(l=>l.classList.remove('active'));<?php endif; ?>
</script>
<?php adminShellClose(); ?>
