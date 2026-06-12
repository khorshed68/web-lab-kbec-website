<?php
require_once __DIR__ . '/admin_shell.php';
$db  = getDB();
$msg = ''; $msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $name       = trim($_POST['name'] ?? '');
        $website    = trim($_POST['website'] ?? '');
        $category   = in_array($_POST['category'],['Title','Gold','Silver','Media','General'])?$_POST['category']:'General';
        $sort_order = (int)($_POST['sort_order'] ?? 99);
        $is_active  = isset($_POST['is_active']) ? 1 : 0;

        if (!$name) { $msg='Sponsor name is required.'; $msgType='danger'; }
        else {
            $logoPath = $_POST['current_logo'] ?? null;
            if (!empty($_FILES['logo']['tmp_name'])) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($_FILES['logo']['tmp_name']);
                if (in_array($mime,['image/jpeg','image/png','image/webp','image/svg+xml'])) {
                    $ext   = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/svg+xml'=>'svg'][$mime];
                    $fname = 'sponsor_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
                    $dest  = UPLOAD_DIR.'avatars/'.$fname;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                        $logoPath = 'uploads/avatars/'.$fname;
                    }
                }
            }
            if ($action === 'create') {
                $db->prepare("INSERT INTO sponsors (name,logo,website,category,sort_order,is_active) VALUES (?,?,?,?,?,?)")
                   ->execute([$name,$logoPath,$website,$category,$sort_order,$is_active]);
                $msg = "Sponsor '{$name}' added!";
            } else {
                $id = (int)$_POST['id'];
                $db->prepare("UPDATE sponsors SET name=?,logo=?,website=?,category=?,sort_order=?,is_active=? WHERE id=?")
                   ->execute([$name,$logoPath,$website,$category,$sort_order,$is_active,$id]);
                $msg = 'Sponsor updated.';
            }
        }
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM sponsors WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Sponsor removed.';
    } elseif ($action === 'toggle') {
        $db->prepare("UPDATE sponsors SET is_active=1-is_active WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Visibility toggled.';
    }
}

$editRow  = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM sponsors WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $editRow = $s->fetch();
}
$showForm = (isset($_GET['action']) && $_GET['action']==='new') || $editRow;
$sponsors = $db->query("SELECT * FROM sponsors ORDER BY sort_order ASC, category ASC, name ASC")->fetchAll();

adminShellOpen('Sponsors & Partners', 'sponsors');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div style="font-size:.82rem;color:var(--ap-muted)">Manage sponsors and partners displayed on the homepage</div>
  <a href="?action=new" class="ap-btn ap-btn-gold"><i class="bi bi-plus-lg"></i> Add Sponsor</a>
</div>
<?php if ($msg) echo adminAlert($msg, $msgType); ?>

<?php if ($showForm): ?>
<div class="ap-card mb-3">
  <div class="ap-card-title"><i class="bi bi-award-fill"></i> <?= $editRow?'Edit':'Add' ?> Sponsor</div>
  <form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="<?= $editRow?'edit':'create' ?>">
    <?php if ($editRow): ?>
      <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
      <input type="hidden" name="current_logo" value="<?= htmlspecialchars($editRow['logo']??'',ENT_QUOTES) ?>">
    <?php endif; ?>
    <div class="row g-3">
      <div class="col-md-5">
        <label class="ap-label">Sponsor / Partner Name *</label>
        <input type="text" name="name" class="ap-input" required value="<?= htmlspecialchars($editRow['name']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-3">
        <label class="ap-label">Category / Tier</label>
        <select name="category" class="ap-input">
          <?php foreach(['Title','Gold','Silver','Media','General'] as $c): ?>
            <option value="<?= $c ?>" <?= ($editRow['category']??'')===$c?'selected':'' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="ap-label">Sort Order</label>
        <input type="number" name="sort_order" class="ap-input" value="<?= (int)($editRow['sort_order']??99) ?>">
      </div>
      <div class="col-md-2">
        <label class="ap-label">Visible</label>
        <div style="padding-top:10px">
          <label class="ap-toggle"><input type="checkbox" name="is_active" <?= ($editRow['is_active']??1)?'checked':'' ?>><span class="ap-toggle-slider"></span></label>
        </div>
      </div>
      <div class="col-md-6">
        <label class="ap-label">Website URL</label>
        <input type="url" name="website" class="ap-input" placeholder="https://…" value="<?= htmlspecialchars($editRow['website']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-6">
        <label class="ap-label">Logo (JPG/PNG/SVG/WebP)</label>
        <?php if (!empty($editRow['logo'])): ?>
          <img src="<?= htmlspecialchars('../'.$editRow['logo'],ENT_QUOTES) ?>" class="ap-img-preview mb-2 d-block" style="background:#fff;padding:4px" alt="logo">
        <?php endif; ?>
        <input type="file" name="logo" class="ap-input" accept="image/*" style="padding:7px">
      </div>
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="ap-btn ap-btn-gold"><i class="bi bi-check-lg"></i> Save Sponsor</button>
        <a href="sponsors.php" class="ap-btn ap-btn-outline">Cancel</a>
      </div>
    </div>
  </form>
</div>
<?php endif; ?>

<?php
$categories = ['Title','Gold','Silver','Media','General'];
foreach ($categories as $cat):
  $catSponsors = array_filter($sponsors, fn($s) => $s['category'] === $cat);
  if (empty($catSponsors)) continue;
?>
<div class="ap-card p-0 mb-3">
  <div style="padding:14px 20px 0;font-size:.78rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--ap-gold)">
    <i class="bi bi-award-fill me-1"></i> <?= $cat ?> Sponsors (<?= count($catSponsors) ?>)
  </div>
  <div class="ap-table-wrap">
    <table class="ap-table">
      <thead><tr><th>Logo</th><th>Name</th><th>Website</th><th>Order</th><th>Visible</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($catSponsors as $sp): ?>
        <tr>
          <td>
            <?php if ($sp['logo']): ?>
              <img src="<?= htmlspecialchars('../'.$sp['logo'],ENT_QUOTES) ?>" class="ap-img-preview" style="background:#fff;padding:3px;object-fit:contain" alt="logo">
            <?php else: ?>
              <div class="ap-img-preview d-flex align-items-center justify-content-center" style="font-size:1.2rem;background:rgba(255,255,255,.04)">🏢</div>
            <?php endif; ?>
          </td>
          <td><b><?= htmlspecialchars($sp['name'],ENT_QUOTES) ?></b></td>
          <td style="font-size:.78rem"><?= $sp['website']?'<a href="'.htmlspecialchars($sp['website'],ENT_QUOTES).'" target="_blank" style="color:var(--ap-gold)">Open ↗</a>':'—' ?></td>
          <td><span class="ap-badge ap-badge-gold"><?= (int)$sp['sort_order'] ?></span></td>
          <td>
            <form method="POST" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$sp['id'] ?>">
              <button type="submit" class="ap-badge <?= $sp['is_active']?'ap-badge-green':'ap-badge-grey' ?>" style="border:none;cursor:pointer;background:inherit"><?= $sp['is_active']?'Shown':'Hidden' ?></button>
            </form>
          </td>
          <td>
            <div class="d-flex gap-1">
              <a href="?edit=<?= (int)$sp['id'] ?>" class="ap-btn ap-btn-outline ap-btn-sm"><i class="bi bi-pencil"></i></a>
              <form method="POST" style="display:inline">
                <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$sp['id'] ?>">
                <button type="submit" class="ap-btn ap-btn-danger ap-btn-sm" onclick="return confirm('Remove sponsor?')"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>
<?php if (empty($sponsors)): ?>
<div class="ap-card" style="text-align:center;padding:40px;color:var(--ap-muted)">No sponsors yet. Add your first sponsor!</div>
<?php endif; ?>
<?php adminShellClose(); ?>
