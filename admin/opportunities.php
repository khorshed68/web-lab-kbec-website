<?php
require_once __DIR__ . '/admin_shell.php';
$db  = getDB();
$msg = ''; $msgType = 'success';

// ── Handle CRUD Actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $title       = trim($_POST['title'] ?? '');
        $category    = trim($_POST['category'] ?? 'internship');
        $deadline    = trim($_POST['deadline'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $meta_1      = trim($_POST['meta_1'] ?? '');
        $meta_2      = trim($_POST['meta_2'] ?? '');
        $meta_3      = trim($_POST['meta_3'] ?? '');
        $link        = trim($_POST['link'] ?? '');
        $is_active   = isset($_POST['is_active']) ? 1 : 0;

        // Validation
        if (!$title || !$deadline || !$description || !$meta_1 || !$meta_2 || !$meta_3 || !$link) {
            $msg = 'All fields (except body note, if optional) are required.';
            $msgType = 'danger';
        } elseif (!in_array($category, ['internship', 'startup', 'competition', 'scholarship'])) {
            $msg = 'Invalid category selected.';
            $msgType = 'danger';
        } elseif ($action === 'create') {
            $stmt = $db->prepare("INSERT INTO opportunities (title, category, deadline, description, meta_1, meta_2, meta_3, link, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $category, $deadline, $description, $meta_1, $meta_2, $meta_3, $link, $is_active]);
            $msg = 'Opportunity listing created successfully!';
        } else {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE opportunities SET title=?, category=?, deadline=?, description=?, meta_1=?, meta_2=?, meta_3=?, link=?, is_active=? WHERE id=?");
            $stmt->execute([$title, $category, $deadline, $description, $meta_1, $meta_2, $meta_3, $link, $is_active, $id]);
            $msg = 'Opportunity listing updated successfully.';
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM opportunities WHERE id=?")->execute([$id]);
        $msg = 'Opportunity listing deleted successfully.';
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE opportunities SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
        $msg = 'Opportunity status toggled.';
    }
}

// Fetch all listings
$opportunities = $db->query("SELECT * FROM opportunities ORDER BY created_at DESC")->fetchAll();
$editRow = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM opportunities WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $editRow = $s->fetch();
}
$showForm = (isset($_GET['action']) && $_GET['action'] === 'new') || $editRow;

adminShellOpen('Opportunity Board', 'opportunities');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <div style="font-size:.82rem;color:var(--ap-muted)">Manage internships, startup hiring, case competitions, and scholarships shown on the website</div>
  </div>
  <a href="?action=new" class="ap-btn ap-btn-gold"><i class="bi bi-plus-lg"></i> New Opportunity</a>
</div>

<?php if ($msg) echo adminAlert($msg, $msgType); ?>

<!-- Form -->
<?php if ($showForm): ?>
<div class="ap-card mb-3">
  <div class="ap-card-title"><i class="bi bi-<?= $editRow?'pencil':'plus-circle' ?>-fill"></i> <?= $editRow?'Edit':'New' ?> Opportunity</div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="<?= $editRow?'edit':'create' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif; ?>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="ap-label">Opportunity Title *</label>
        <input type="text" name="title" class="ap-input" required placeholder="e.g. Product Design Intern - Nova Labs"
               value="<?= htmlspecialchars($editRow['title']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-3">
        <label class="ap-label">Category *</label>
        <select name="category" class="ap-input" required>
          <option value="internship" <?= ($editRow['category']??'')==='internship'?'selected':'' ?>>Internship</option>
          <option value="startup" <?= ($editRow['category']??'')==='startup'?'selected':'' ?>>Startup Hiring</option>
          <option value="competition" <?= ($editRow['category']??'')==='competition'?'selected':'' ?>>Case Competition</option>
          <option value="scholarship" <?= ($editRow['category']??'')==='scholarship'?'selected':'' ?>>Scholarship</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="ap-label">Deadline Text *</label>
        <input type="text" name="deadline" class="ap-input" required placeholder="e.g. Apply by Jun 2 or Open now"
               value="<?= htmlspecialchars($editRow['deadline']??'',ENT_QUOTES) ?>">
      </div>
      
      <div class="col-md-4">
        <label class="ap-label">Metadata Tag 1 *</label>
        <input type="text" name="meta_1" class="ap-input" required placeholder="e.g. Remote / Hybrid or Full-time"
               value="<?= htmlspecialchars($editRow['meta_1']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-4">
        <label class="ap-label">Metadata Tag 2 *</label>
        <input type="text" name="meta_2" class="ap-input" required placeholder="e.g. Paid or Dhaka"
               value="<?= htmlspecialchars($editRow['meta_2']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-4">
        <label class="ap-label">Metadata Tag 3 *</label>
        <input type="text" name="meta_3" class="ap-input" required placeholder="e.g. 3 months or Startup"
               value="<?= htmlspecialchars($editRow['meta_3']??'',ENT_QUOTES) ?>">
      </div>

      <div class="col-12">
        <label class="ap-label">Description *</label>
        <textarea name="description" class="ap-input" rows="3" required placeholder="Describe the opportunity details…"><?= htmlspecialchars($editRow['description']??'',ENT_QUOTES) ?></textarea>
      </div>
      <div class="col-md-10">
        <label class="ap-label">Application Link / Email *</label>
        <input type="text" name="link" class="ap-input" required placeholder="e.g. mailto:bec@kuet.ac.bd?subject=Nova%20Labs or https://..."
               value="<?= htmlspecialchars($editRow['link']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-2">
        <label class="ap-label">Status (Active)</label>
        <div style="padding-top:10px">
          <label class="ap-toggle">
            <input type="checkbox" name="is_active" <?= ($editRow['is_active']??1)?'checked':'' ?>>
            <span class="ap-toggle-slider"></span>
          </label>
        </div>
      </div>
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="ap-btn ap-btn-gold"><i class="bi bi-check-lg"></i> <?= $editRow?'Update':'Publish' ?></button>
        <a href="opportunities.php" class="ap-btn ap-btn-outline">Cancel</a>
      </div>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- Table List -->
<div class="ap-card p-0">
  <div class="ap-table-wrap">
    <table class="ap-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Category</th>
          <th>Deadline</th>
          <th>Tags (Metadata)</th>
          <th>Application Link</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($opportunities as $o): ?>
        <tr>
          <td><b><?= htmlspecialchars($o['title'], ENT_QUOTES) ?></b></td>
          <td>
            <span class="ap-badge ap-badge-<?= ['internship'=>'blue','startup'=>'purple','competition'=>'gold','scholarship'=>'green'][$o['category']] ?? 'grey' ?>">
              <?= htmlspecialchars(ucfirst($o['category']), ENT_QUOTES) ?>
            </span>
          </td>
          <td style="font-size:.8rem;"><?= htmlspecialchars($o['deadline'], ENT_QUOTES) ?></td>
          <td style="font-size:.75rem;color:var(--ap-muted)">
            <?= htmlspecialchars($o['meta_1'] . ' • ' . $o['meta_2'] . ' • ' . $o['meta_3'], ENT_QUOTES) ?>
          </td>
          <td style="font-size:.75rem;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($o['link'], ENT_QUOTES) ?>">
            <?= htmlspecialchars($o['link'], ENT_QUOTES) ?>
          </td>
          <td>
            <form method="POST" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
              <button type="submit" class="ap-badge <?= $o['is_active']?'ap-badge-green':'ap-badge-grey' ?>" style="border:none;cursor:pointer;background:inherit">
                <?= $o['is_active']?'🟢 Live':'⚫ Off' ?>
              </button>
            </form>
          </td>
          <td>
            <div class="d-flex gap-1">
              <a href="?edit=<?= (int)$o['id'] ?>" class="ap-btn ap-btn-outline ap-btn-sm"><i class="bi bi-pencil"></i></a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this opportunity listing permanently?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                <button type="submit" class="ap-btn ap-btn-danger ap-btn-sm"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($opportunities)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--ap-muted);padding:30px">No opportunity listings found. Click "New Opportunity" to add one!</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php adminShellClose(); ?>
