<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
startSession();
requireAdmin('../index.php');

$db   = getDB();
$csrf = csrfToken();
$msg  = ''; $msgType = 'success';

// ── Handle POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $fields = [
            'slug'                  => preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['slug'] ?? ''))),
            'title'                 => trim($_POST['title'] ?? ''),
            'type'                  => trim($_POST['type'] ?? 'General'),
            'description'           => trim($_POST['description'] ?? ''),
            'location'              => trim($_POST['location'] ?? ''),
            'event_date_start'      => $_POST['event_date_start'] ?? null,
            'event_date_end'        => $_POST['event_date_end'] ?? null,
            'registration_deadline' => $_POST['registration_deadline'] ?? null,
            'capacity'              => max(1,(int)($_POST['capacity'] ?? 100)),
        ];

        if (!$fields['title'] || !$fields['slug']) { $msg='Title and slug are required.'; $msgType='error'; }
        else {
            // Handle banner upload
            $bannerPath = $_POST['current_banner'] ?? null;
            if (!empty($_FILES['banner']['tmp_name'])) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($_FILES['banner']['tmp_name']);
                if (in_array($mime,['image/jpeg','image/png','image/webp'])) {
                    $ext  = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime];
                    $fname = 'banner_' . $fields['slug'] . '_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['banner']['tmp_name'], BANNER_DIR . $fname);
                    $bannerPath = 'uploads/banners/' . $fname;
                }
            }
            $fields['banner'] = $bannerPath;

            if ($action === 'create') {
                $ins = $db->prepare("INSERT INTO `events` (slug,title,type,description,location,event_date_start,event_date_end,registration_deadline,capacity,banner) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $ins->execute(array_values($fields));
                $msg = 'Event created successfully.';
            } else {
                $id  = (int)($_POST['id'] ?? 0);
                $upd = $db->prepare("UPDATE `events` SET slug=?,title=?,type=?,description=?,location=?,event_date_start=?,event_date_end=?,registration_deadline=?,capacity=?,banner=? WHERE id=?");
                $upd->execute([...array_values($fields), $id]);
                $msg = 'Event updated.';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM `events` WHERE id=?")->execute([$id]);
        $msg = 'Event deleted.';
    }
}

// ── Load for edit ─────────────────────────────────────────
$editEvent = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $s = $db->prepare("SELECT * FROM `events` WHERE id=?");
    $s->execute([(int)$_GET['id']]);
    $editEvent = $s->fetch();
}

// ── List events ───────────────────────────────────────────
$events = $db->query("
    SELECT e.*, COUNT(r.id) AS reg_count
    FROM `events` e
    LEFT JOIN `event_registrations` r ON r.event_id=e.id
    GROUP BY e.id ORDER BY e.event_date_start ASC
")->fetchAll();

$types = ['Case Competition','Tech Fest','Talk','Summit','Workshop','Seminar','General'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf,ENT_QUOTES) ?>">
  <title>Events | KBEC Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cinzel:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; renderHeader('Event Management','admin_events'); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="kbec-page-title mb-1">Events</h1>
    <p class="kbec-page-sub mb-0">Create and manage club events</p>
  </div>
  <button class="kbec-btn kbec-btn-gold" onclick="toggleForm()"><i class="bi bi-plus-lg"></i> New Event</button>
</div>

<?php if ($msg): ?>
  <div class="kbec-alert kbec-alert-<?= $msgType==='error'?'error':'success' ?> mb-3"><?= htmlspecialchars($msg,ENT_QUOTES) ?></div>
<?php endif; ?>

<!-- Create / Edit Form -->
<div id="eventForm" style="<?= (!$editEvent && empty($_POST))?'display:none':'' ?>" class="kbec-card mb-4">
  <h5 class="mb-3" style="color:#c9a84c;font-size:.9rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700">
    <?= $editEvent ? 'Edit Event' : 'Create New Event' ?>
  </h5>
  <form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="<?= $editEvent?'edit':'create' ?>">
    <?php if ($editEvent): ?>
      <input type="hidden" name="id" value="<?= (int)$editEvent['id'] ?>">
      <input type="hidden" name="current_banner" value="<?= htmlspecialchars($editEvent['banner']??'',ENT_QUOTES) ?>">
    <?php endif; ?>
    <div class="row g-3">
      <div class="col-md-8">
        <label class="kbec-label">Event Title *</label>
        <input type="text" name="title" class="kbec-input" required value="<?= htmlspecialchars($editEvent['title']??$_POST['title']??'',ENT_QUOTES) ?>" id="titleInput" oninput="autoSlug(this.value)">
      </div>
      <div class="col-md-4">
        <label class="kbec-label">Type</label>
        <select name="type" class="kbec-input">
          <?php foreach($types as $t): ?>
            <option value="<?= htmlspecialchars($t,ENT_QUOTES) ?>" <?= ($editEvent['type']??'')====$t?'selected':'' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="kbec-label">Slug (URL-friendly ID) *</label>
        <input type="text" name="slug" id="slugInput" class="kbec-input" required value="<?= htmlspecialchars($editEvent['slug']??$_POST['slug']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-6">
        <label class="kbec-label">Location / Venue</label>
        <input type="text" name="location" class="kbec-input" value="<?= htmlspecialchars($editEvent['location']??$_POST['location']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-12">
        <label class="kbec-label">Description</label>
        <textarea name="description" class="kbec-input" rows="3"><?= htmlspecialchars($editEvent['description']??$_POST['description']??'',ENT_QUOTES) ?></textarea>
      </div>
      <div class="col-md-4">
        <label class="kbec-label">Start Date</label>
        <input type="date" name="event_date_start" class="kbec-input" value="<?= htmlspecialchars($editEvent['event_date_start']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-4">
        <label class="kbec-label">End Date</label>
        <input type="date" name="event_date_end" class="kbec-input" value="<?= htmlspecialchars($editEvent['event_date_end']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-4">
        <label class="kbec-label">Registration Deadline</label>
        <input type="date" name="registration_deadline" class="kbec-input" value="<?= htmlspecialchars($editEvent['registration_deadline']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-4">
        <label class="kbec-label">Capacity</label>
        <input type="number" name="capacity" class="kbec-input" min="1" value="<?= htmlspecialchars((string)($editEvent['capacity']??100),ENT_QUOTES) ?>">
      </div>
      <div class="col-md-8">
        <label class="kbec-label">Event Banner Image (JPG/PNG/WebP, max 5MB)</label>
        <input type="file" name="banner" class="kbec-input" accept="image/jpeg,image/png,image/webp" style="padding:8px">
      </div>
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="kbec-btn kbec-btn-gold"><i class="bi bi-check-lg"></i> <?= $editEvent?'Update Event':'Create Event' ?></button>
        <a href="events.php" class="kbec-btn kbec-btn-outline" style="text-decoration:none">Cancel</a>
      </div>
    </div>
  </form>
</div>

<!-- Events Table -->
<div class="kbec-card p-0" style="overflow-x:auto">
  <table class="kbec-table">
    <thead><tr><th>Title</th><th>Type</th><th>Dates</th><th>Capacity</th><th>Registered</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($events as $ev):
        $today    = date('Y-m-d');
        $isClosed = ($ev['registration_deadline']<$today)||($ev['event_date_end']<$today);
        $isFull   = (int)$ev['reg_count'] >= (int)$ev['capacity'];
        $status   = $isClosed?'Closed':($isFull?'Full':'Open');
        $statusClass = ['Open'=>'badge-green','Full'=>'badge-gold','Closed'=>'badge-red'][$status];
      ?>
      <tr>
        <td><b><?= htmlspecialchars($ev['title'],ENT_QUOTES) ?></b><br><small style="color:rgba(255,255,255,.35);font-family:monospace"><?= htmlspecialchars($ev['slug'],ENT_QUOTES) ?></small></td>
        <td><span class="badge-blue"><?= htmlspecialchars($ev['type'],ENT_QUOTES) ?></span></td>
        <td style="font-size:.78rem;color:rgba(255,255,255,.55)"><?= $ev['event_date_start'] ?> → <?= $ev['event_date_end'] ?></td>
        <td><?= (int)$ev['capacity'] ?></td>
        <td><?= (int)$ev['reg_count'] ?></td>
        <td><span class="<?= $statusClass ?>"><?= $status ?></span></td>
        <td>
          <div class="d-flex gap-1">
            <a href="?action=edit&id=<?= (int)$ev['id'] ?>" class="kbec-btn kbec-btn-outline" style="padding:5px 10px;font-size:.75rem;text-decoration:none"><i class="bi bi-pencil"></i></a>
            <form method="POST" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
              <button type="submit" class="kbec-btn kbec-btn-danger" style="padding:5px 10px;font-size:.75rem" onclick="return confirm('Delete this event and all its registrations?')"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleForm() {
  const f = document.getElementById('eventForm');
  f.style.display = f.style.display==='none' ? 'block' : 'none';
}
function autoSlug(val) {
  const s = document.getElementById('slugInput');
  if (!s.dataset.locked) {
    s.value = val.toLowerCase().trim().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
  }
}
document.getElementById('slugInput')?.addEventListener('input', () => {
  document.getElementById('slugInput').dataset.locked = 'true';
});
<?php if($editEvent): ?>document.getElementById('eventForm').scrollIntoView({behavior:'smooth'});<?php endif; ?>
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; renderFooter(); ?>
