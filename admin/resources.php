<?php
require_once __DIR__ . '/admin_shell.php';
$db  = getDB();
$msg = ''; $msgType = 'success';

// ── Handle CRUD Actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $title       = trim($_POST['title']       ?? '');
        $category    = trim($_POST['category']    ?? 'template');
        $description = trim($_POST['description'] ?? '');
        $tags        = trim($_POST['tags']        ?? '');
        $link        = trim($_POST['link']        ?? '');
        $sort_order  = (int)($_POST['sort_order'] ?? 0);
        $is_active   = isset($_POST['is_active']) ? 1 : 0;

        $validCats = ['template', 'guide', 'tool', 'workshop', 'youtube'];

        if (!$title || !$description) {
            $msg = 'Title and Description are required.';
            $msgType = 'danger';
        } elseif (!in_array($category, $validCats)) {
            $msg = 'Invalid category selected.';
            $msgType = 'danger';
        } elseif ($action === 'create') {
            $db->prepare("
                INSERT INTO resources (title, category, description, tags, link, sort_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$title, $category, $description, $tags, $link, $sort_order, $is_active]);
            $msg = 'Resource created successfully!';
        } else {
            $id = (int)$_POST['id'];
            $db->prepare("
                UPDATE resources
                SET title=?, category=?, description=?, tags=?, link=?, sort_order=?, is_active=?
                WHERE id=?
            ")->execute([$title, $category, $description, $tags, $link, $sort_order, $is_active, $id]);
            $msg = 'Resource updated successfully.';
        }
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM resources WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Resource deleted.';
    } elseif ($action === 'toggle') {
        $db->prepare("UPDATE resources SET is_active = 1 - is_active WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Resource status toggled.';
    }
}

// Fetch all resources
$resources = $db->query("SELECT * FROM resources ORDER BY sort_order ASC, created_at DESC")->fetchAll();
$editRow   = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM resources WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $editRow = $s->fetch();
}
$showForm = (isset($_GET['action']) && $_GET['action'] === 'new') || $editRow;

$catLabels = [
    'template' => 'Template',
    'guide'    => 'Guide',
    'tool'     => 'Tool',
    'workshop' => 'Workshop',
    'youtube'  => 'YouTube Links',
];
$catBadge = [
    'template' => 'ap-badge-blue',
    'guide'    => 'ap-badge-green',
    'tool'     => 'ap-badge-purple',
    'workshop' => 'ap-badge-gold',
    'youtube'  => 'ap-badge-red',
];

adminShellOpen('Resources — Tools, Templates & Guides', 'resources');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div style="font-size:.82rem;color:var(--ap-muted)">
    Manage the Tools, Templates &amp; Guides cards shown in the Resource Hub on the website.
  </div>
  <a href="?action=new" class="ap-btn ap-btn-gold"><i class="bi bi-plus-lg"></i> New Resource</a>
</div>

<?php if ($msg) echo adminAlert($msg, $msgType); ?>

<!-- ── Form ── -->
<?php if ($showForm): ?>
<div class="ap-card mb-3">
  <div class="ap-card-title">
    <i class="bi bi-<?= $editRow ? 'pencil' : 'plus-circle' ?>-fill"></i>
    <?= $editRow ? 'Edit' : 'New' ?> Resource
  </div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'create' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif; ?>

    <div class="row g-3">
      <!-- Title -->
      <div class="col-md-6">
        <label class="ap-label">Resource Title *</label>
        <input type="text" name="title" class="ap-input" required
               placeholder="e.g. Presentation Slides"
               value="<?= htmlspecialchars($editRow['title'] ?? '', ENT_QUOTES) ?>">
      </div>

      <!-- Category -->
      <div class="col-md-3">
        <label class="ap-label">Category *</label>
        <select name="category" class="ap-input" required>
          <?php foreach ($catLabels as $val => $label): ?>
            <option value="<?= $val ?>" <?= ($editRow['category'] ?? '') === $val ? 'selected' : '' ?>>
              <?= $label ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Sort Order -->
      <div class="col-md-3">
        <label class="ap-label">Sort Order</label>
        <input type="number" name="sort_order" class="ap-input" min="0"
               placeholder="0 = first"
               value="<?= (int)($editRow['sort_order'] ?? 0) ?>">
      </div>

      <!-- Description -->
      <div class="col-12">
        <label class="ap-label">Description *</label>
        <textarea name="description" class="ap-input" rows="3" required
                  placeholder="Describe what this resource contains and how members can use it…"><?= htmlspecialchars($editRow['description'] ?? '', ENT_QUOTES) ?></textarea>
      </div>

      <!-- Tags (comma-separated) -->
      <div class="col-md-8">
        <label class="ap-label">Tags <span style="font-weight:400;color:var(--ap-muted)">(comma-separated, shown as pills)</span></label>
        <input type="text" name="tags" class="ap-input"
               placeholder="e.g. Pitch decks,Templates,Editable"
               value="<?= htmlspecialchars($editRow['tags'] ?? '', ENT_QUOTES) ?>">
      </div>

      <!-- Link -->
      <div class="col-md-10">
        <label class="ap-label">Access Link / Email</label>
        <input type="text" name="link" class="ap-input"
               placeholder="e.g. https://drive.google.com/… or mailto:bec@kuet.ac.bd?subject=…"
               value="<?= htmlspecialchars($editRow['link'] ?? '', ENT_QUOTES) ?>">
      </div>

      <!-- Active toggle -->
      <div class="col-md-2">
        <label class="ap-label">Active</label>
        <div style="padding-top:10px">
          <label class="ap-toggle">
            <input type="checkbox" name="is_active" <?= ($editRow['is_active'] ?? 1) ? 'checked' : '' ?>>
            <span class="ap-toggle-slider"></span>
          </label>
        </div>
      </div>

      <div class="col-12 d-flex gap-2">
        <button type="submit" class="ap-btn ap-btn-gold">
          <i class="bi bi-check-lg"></i> <?= $editRow ? 'Update Resource' : 'Publish Resource' ?>
        </button>
        <a href="resources.php" class="ap-btn ap-btn-outline">Cancel</a>
      </div>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- ── Table ── -->
<div class="ap-card p-0">
  <div class="ap-table-wrap">
    <table class="ap-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Title</th>
          <th>Category</th>
          <th>Tags</th>
          <th>Link</th>
          <th>Order</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($resources as $r): ?>
        <tr>
          <td style="color:var(--ap-muted);font-size:.78rem"><?= (int)$r['id'] ?></td>
          <td><b><?= htmlspecialchars($r['title'], ENT_QUOTES) ?></b>
            <div style="font-size:.75rem;color:var(--ap-muted);margin-top:2px">
              <?= htmlspecialchars(mb_strimwidth($r['description'], 0, 70, '…'), ENT_QUOTES) ?>
            </div>
          </td>
          <td>
            <span class="ap-badge <?= $catBadge[$r['category']] ?? 'ap-badge-grey' ?>">
              <?= htmlspecialchars($catLabels[$r['category']] ?? ucfirst($r['category']), ENT_QUOTES) ?>
            </span>
          </td>
          <td style="font-size:.75rem;color:var(--ap-muted)">
            <?= htmlspecialchars($r['tags'], ENT_QUOTES) ?>
          </td>
          <td style="font-size:.75rem;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
              title="<?= htmlspecialchars($r['link'], ENT_QUOTES) ?>">
            <?= $r['link'] ? htmlspecialchars($r['link'], ENT_QUOTES) : '<span style="color:var(--ap-muted)">—</span>' ?>
          </td>
          <td style="text-align:center"><?= (int)$r['sort_order'] ?></td>
          <td>
            <form method="POST" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" class="ap-badge <?= $r['is_active'] ? 'ap-badge-green' : 'ap-badge-grey' ?>"
                      style="border:none;cursor:pointer;background:inherit">
                <?= $r['is_active'] ? '🟢 Live' : '⚫ Off' ?>
              </button>
            </form>
          </td>
          <td>
            <div class="d-flex gap-1">
              <a href="?edit=<?= (int)$r['id'] ?>" class="ap-btn ap-btn-outline ap-btn-sm">
                <i class="bi bi-pencil"></i>
              </a>
              <form method="POST" style="display:inline"
                    onsubmit="return confirm('Delete this resource permanently?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="ap-btn ap-btn-danger ap-btn-sm">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($resources)): ?>
        <tr>
          <td colspan="8" style="text-align:center;color:var(--ap-muted);padding:30px">
            No resources found. Click <strong>New Resource</strong> to add one.
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php adminShellClose(); ?>
