<?php
require_once __DIR__ . '/admin_shell.php';
$db  = getDB();
$msg = ''; $msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        if (empty($_FILES['images']['tmp_name'][0])) { $msg='No files selected.'; $msgType='danger'; }
        else {
            $caption  = trim($_POST['caption'] ?? '');
            $category = trim($_POST['category'] ?? 'General');
            $uploaded = 0;
            foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
                if (!$tmp) continue;
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($tmp);
                if (!in_array($mime,['image/jpeg','image/png','image/webp'])) continue;
                $ext   = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime];
                $fname = 'gallery_'.time().'_'.$i.'_'.bin2hex(random_bytes(3)).'.'.$ext;
                $dest  = UPLOAD_DIR.'gallery/';
                if (!is_dir($dest)) mkdir($dest, 0755, true);
                if (move_uploaded_file($tmp, $dest.$fname)) {
                    $db->prepare("INSERT INTO gallery (image_path, caption, category, sort_order) VALUES (?,?,?,?)")
                       ->execute(['uploads/gallery/'.$fname, $caption, $category, 99]);
                    $uploaded++;
                }
            }
            $msg = "Uploaded {$uploaded} image(s)!";
        }
    } elseif ($action === 'update') {
        $id       = (int)$_POST['id'];
        $caption  = trim($_POST['caption'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $sort     = (int)($_POST['sort_order'] ?? 99);
        $db->prepare("UPDATE gallery SET caption=?,category=?,sort_order=? WHERE id=?")->execute([$caption,$category,$sort,$id]);
        $msg = 'Image updated.';
    } elseif ($action === 'delete') {
        $id  = (int)$_POST['id'];
        $row = $db->prepare("SELECT image_path FROM gallery WHERE id=?");
        $row->execute([$id]);
        $r   = $row->fetch();
        if ($r && $r['image_path']) { @unlink(__DIR__.'/../'.$r['image_path']); }
        $db->prepare("DELETE FROM gallery WHERE id=?")->execute([$id]);
        $msg = 'Image deleted.';
    } elseif ($action === 'delete_all') {
        $rows = $db->query("SELECT image_path FROM gallery")->fetchAll();
        foreach ($rows as $r) { if ($r['image_path']) @unlink(__DIR__.'/../'.$r['image_path']); }
        $db->exec("DELETE FROM gallery");
        $msg = 'All gallery images cleared.';
    }
}

$categoryFilter = $_GET['cat'] ?? '';
$where  = $categoryFilter ? "WHERE category=?" : "";
$params = $categoryFilter ? [$categoryFilter] : [];
$stmt = $db->prepare("SELECT * FROM gallery $where ORDER BY sort_order ASC, created_at DESC");
$stmt->execute($params);
$images = $stmt->fetchAll();
$categories = $db->query("SELECT DISTINCT category FROM gallery ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

adminShellOpen('Gallery', 'gallery');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div style="font-size:.82rem;color:var(--ap-muted)"><?= count($images) ?> image(s) in gallery</div>
  <div class="d-flex gap-2">
    <?php if (!empty($images)): ?>
    <form method="POST" style="display:inline" onsubmit="return confirm('Delete ALL gallery images permanently?')">
      <?= csrfField() ?><input type="hidden" name="action" value="delete_all">
      <button type="submit" class="ap-btn ap-btn-danger ap-btn-sm"><i class="bi bi-trash"></i> Clear All</button>
    </form>
    <?php endif; ?>
    <button class="ap-btn ap-btn-gold" onclick="document.getElementById('uploadSection').style.display=document.getElementById('uploadSection').style.display==='none'?'block':'none'">
      <i class="bi bi-cloud-upload-fill"></i> Upload Images
    </button>
  </div>
</div>
<?php if ($msg) echo adminAlert($msg, $msgType); ?>

<!-- Upload Form -->
<div id="uploadSection" style="display:<?= (isset($_GET['upload'])?'block':'none') ?>">
  <div class="ap-card mb-3">
    <div class="ap-card-title"><i class="bi bi-cloud-upload-fill"></i> Upload New Images</div>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="upload">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="ap-label">Select Images (multi-select, JPG/PNG/WebP)</label>
          <input type="file" name="images[]" class="ap-input" accept="image/jpeg,image/png,image/webp" multiple required style="padding:8px">
        </div>
        <div class="col-md-3">
          <label class="ap-label">Category</label>
          <input type="text" name="category" class="ap-input" placeholder="e.g. NEXUS 2026" value="General">
        </div>
        <div class="col-md-3">
          <label class="ap-label">Caption (applies to all)</label>
          <input type="text" name="caption" class="ap-input" placeholder="Optional caption">
        </div>
        <div class="col-12">
          <button type="submit" class="ap-btn ap-btn-gold"><i class="bi bi-upload"></i> Upload</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Category filter -->
<?php if (!empty($categories)): ?>
<div class="d-flex gap-2 flex-wrap mb-3">
  <a href="gallery.php" class="ap-btn ap-btn-<?= !$categoryFilter?'gold':'outline' ?> ap-btn-sm" style="text-decoration:none">All</a>
  <?php foreach ($categories as $c): ?>
    <a href="?cat=<?= urlencode($c) ?>" class="ap-btn ap-btn-<?= $categoryFilter===$c?'gold':'outline' ?> ap-btn-sm" style="text-decoration:none"><?= htmlspecialchars($c,ENT_QUOTES) ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Gallery Grid -->
<?php if (empty($images)): ?>
<div class="ap-card" style="text-align:center;padding:50px;color:var(--ap-muted)">
  <div style="font-size:3rem;margin-bottom:12px">🖼️</div>
  No images yet. Upload some photos!
</div>
<?php else: ?>
<div class="ap-gallery-grid">
  <?php foreach ($images as $img): ?>
  <div class="ap-gallery-item">
    <img src="<?= htmlspecialchars('../'.$img['image_path'],ENT_QUOTES) ?>" alt="<?= htmlspecialchars($img['caption']??'',ENT_QUOTES) ?>" loading="lazy">
    <div class="ap-gallery-item-overlay">
      <button class="ap-btn ap-btn-outline ap-btn-sm" onclick='openEditImg(<?= (int)$img["id"] ?>, <?= json_encode($img["caption"]??'') ?>, <?= json_encode($img["category"]??'') ?>, <?= (int)$img["sort_order"] ?>)'>
        <i class="bi bi-pencil"></i>
      </button>
      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this image?')">
        <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
        <button type="submit" class="ap-btn ap-btn-danger ap-btn-sm"><i class="bi bi-trash"></i></button>
      </form>
    </div>
    <?php if ($img['caption']): ?>
      <div style="position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(0,0,0,.8));padding:8px;font-size:.7rem;color:#fff"><?= htmlspecialchars(substr($img['caption'],0,35),ENT_QUOTES) ?></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Edit modal -->
<div class="ap-modal-bg" id="editImgModal">
  <div class="ap-modal">
    <div class="ap-modal-title"><i class="bi bi-pencil-fill"></i> Edit Image Details</div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="editImgId">
      <div class="mb-3">
        <label class="ap-label">Caption</label>
        <input type="text" name="caption" id="editImgCaption" class="ap-input">
      </div>
      <div class="mb-3">
        <label class="ap-label">Category</label>
        <input type="text" name="category" id="editImgCategory" class="ap-input">
      </div>
      <div class="mb-3">
        <label class="ap-label">Sort Order</label>
        <input type="number" name="sort_order" id="editImgSort" class="ap-input">
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="ap-btn ap-btn-gold">Save</button>
        <button type="button" class="ap-btn ap-btn-outline" onclick="document.getElementById('editImgModal').classList.remove('open')">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
function openEditImg(id, caption, category, sort) {
  document.getElementById('editImgId').value      = id;
  document.getElementById('editImgCaption').value = caption;
  document.getElementById('editImgCategory').value= category;
  document.getElementById('editImgSort').value    = sort;
  document.getElementById('editImgModal').classList.add('open');
}
document.getElementById('editImgModal')?.addEventListener('click', e => {
  if (e.target === e.currentTarget) e.currentTarget.classList.remove('open');
});
</script>
<?php adminShellClose(); ?>
