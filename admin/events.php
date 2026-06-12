<?php
require_once __DIR__ . '/admin_shell.php';
$db  = getDB();
$msg = ''; $msgType = 'success';
$types = ['Case Competition','Tech Fest','Talk','Summit','Workshop','Seminar','General'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $f = [
            'slug'                  => preg_replace('/[^a-z0-9\-]/','',strtolower(trim($_POST['slug']??''))),
            'title'                 => trim($_POST['title']??''),
            'type'                  => in_array($_POST['type']??'',$types,true)?$_POST['type']:'General',
            'description'           => trim($_POST['description']??''),
            'location'              => trim($_POST['location']??''),
            'event_date_start'      => $_POST['event_date_start']??null,
            'event_date_end'        => $_POST['event_date_end']??null,
            'registration_deadline' => $_POST['registration_deadline']??null,
            'capacity'              => max(1,(int)($_POST['capacity']??100)),
        ];
        if (!$f['title']||!$f['slug']) { $msg='Title and slug are required.'; $msgType='danger'; }
        else {
            $bannerPath = $_POST['current_banner']??null;
            if (!empty($_FILES['banner']['tmp_name'])) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($_FILES['banner']['tmp_name']);
                if (in_array($mime,['image/jpeg','image/png','image/webp'])) {
                    $ext   = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime];
                    $fname = 'banner_'.$f['slug'].'_'.time().'.'.$ext;
                    if (!is_dir(BANNER_DIR)) mkdir(BANNER_DIR,0755,true);
                    move_uploaded_file($_FILES['banner']['tmp_name'], BANNER_DIR.$fname);
                    $bannerPath = 'uploads/banners/'.$fname;
                }
            }
            $f['banner'] = $bannerPath;
            if ($action === 'create') {
                $db->prepare("INSERT INTO events (slug,title,type,description,location,event_date_start,event_date_end,registration_deadline,capacity,banner) VALUES (?,?,?,?,?,?,?,?,?,?)")
                   ->execute(array_values($f));
                $msg = "Event '{$f['title']}' created!";
            } else {
                $id = (int)$_POST['id'];
                $db->prepare("UPDATE events SET slug=?,title=?,type=?,description=?,location=?,event_date_start=?,event_date_end=?,registration_deadline=?,capacity=?,banner=? WHERE id=?")
                   ->execute([...array_values($f),$id]);
                $msg = 'Event updated.';
            }
        }
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM events WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Event deleted.';
    }
}

$editEvent = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM events WHERE id=?"); $s->execute([(int)$_GET['edit']]);
    $editEvent = $s->fetch();
}
$showForm = (isset($_GET['action'])&&$_GET['action']==='new')||$editEvent;

$events = $db->query("SELECT e.*, COUNT(r.id) AS reg_count FROM events e LEFT JOIN event_registrations r ON r.event_id=e.id GROUP BY e.id ORDER BY e.event_date_start DESC")->fetchAll();

adminShellOpen('Events', 'events');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div style="color:var(--ap-muted);font-size:.82rem"><?= count($events) ?> events</div>
  <a href="?action=new" class="ap-btn ap-btn-gold"><i class="bi bi-plus-lg"></i> New Event</a>
</div>
<?php if ($msg) echo adminAlert($msg, $msgType); ?>

<!-- Form -->
<?php if ($showForm): ?>
<div class="ap-card mb-3">
  <div class="ap-card-title"><i class="bi bi-calendar-<?= $editEvent?'check':'plus' ?>-fill"></i> <?= $editEvent?'Edit':'Create' ?> Event</div>
  <form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="<?= $editEvent?'edit':'create' ?>">
    <?php if ($editEvent): ?><input type="hidden" name="id" value="<?= (int)$editEvent['id'] ?>"><input type="hidden" name="current_banner" value="<?= htmlspecialchars($editEvent['banner']??'',ENT_QUOTES) ?>"><?php endif; ?>
    <div class="row g-3">
      <div class="col-md-7">
        <label class="ap-label">Event Title *</label>
        <input type="text" name="title" class="ap-input" required id="titleIn" oninput="autoSlug(this.value)" value="<?= htmlspecialchars($editEvent['title']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-3">
        <label class="ap-label">Type</label>
        <select name="type" class="ap-input">
          <?php foreach($types as $t): ?><option value="<?= $t ?>" <?= ($editEvent['type']??'')===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="ap-label">Capacity</label>
        <input type="number" name="capacity" class="ap-input" min="1" value="<?= (int)($editEvent['capacity']??100) ?>">
      </div>
      <div class="col-md-6">
        <label class="ap-label">Slug (URL ID) *</label>
        <input type="text" name="slug" id="slugIn" class="ap-input" required value="<?= htmlspecialchars($editEvent['slug']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-6">
        <label class="ap-label">Location / Venue</label>
        <input type="text" name="location" class="ap-input" value="<?= htmlspecialchars($editEvent['location']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-12">
        <label class="ap-label">Description</label>
        <textarea name="description" class="ap-input" rows="3"><?= htmlspecialchars($editEvent['description']??'',ENT_QUOTES) ?></textarea>
      </div>
      <div class="col-md-4">
        <label class="ap-label">Start Date</label>
        <input type="date" name="event_date_start" class="ap-input" value="<?= htmlspecialchars($editEvent['event_date_start']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-4">
        <label class="ap-label">End Date</label>
        <input type="date" name="event_date_end" class="ap-input" value="<?= htmlspecialchars($editEvent['event_date_end']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-md-4">
        <label class="ap-label">Reg. Deadline</label>
        <input type="date" name="registration_deadline" class="ap-input" value="<?= htmlspecialchars($editEvent['registration_deadline']??'',ENT_QUOTES) ?>">
      </div>
      <div class="col-12">
        <label class="ap-label">Event Banner (JPG/PNG/WebP)</label>
        <?php if (!empty($editEvent['banner'])): ?>
          <img src="<?= htmlspecialchars('../'.$editEvent['banner'],ENT_QUOTES) ?>" class="ap-img-preview-lg mb-2" alt="banner">
        <?php endif; ?>
        <input type="file" name="banner" class="ap-input" accept="image/jpeg,image/png,image/webp" style="padding:8px">
      </div>
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="ap-btn ap-btn-gold"><i class="bi bi-check-lg"></i> <?= $editEvent?'Update':'Create' ?> Event</button>
        <a href="events.php" class="ap-btn ap-btn-outline">Cancel</a>
      </div>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="ap-card p-0">
  <div class="ap-table-wrap">
    <table class="ap-table">
      <thead><tr><th>Title</th><th>Type</th><th>Dates</th><th>Reg Deadline</th><th>Capacity</th><th>Registered</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($events as $ev):
          $today = date('Y-m-d');
          $closed= ($ev['registration_deadline']<$today)||($ev['event_date_end']<$today);
          $full  = (int)$ev['reg_count']>=(int)$ev['capacity'];
          $status= $closed?'Closed':($full?'Full':'Open');
          $scls  = ['Open'=>'green','Full'=>'gold','Closed'=>'red'][$status];
        ?>
        <tr>
          <td><b><?= htmlspecialchars($ev['title'],ENT_QUOTES) ?></b><br><small style="font-family:monospace;color:var(--ap-muted)"><?= htmlspecialchars($ev['slug'],ENT_QUOTES) ?></small></td>
          <td><span class="ap-badge ap-badge-blue"><?= htmlspecialchars($ev['type'],ENT_QUOTES) ?></span></td>
          <td style="font-size:.75rem;color:var(--ap-muted)"><?= $ev['event_date_start'] ?> → <?= $ev['event_date_end'] ?></td>
          <td style="font-size:.75rem;color:var(--ap-muted)"><?= $ev['registration_deadline'] ?></td>
          <td><?= (int)$ev['capacity'] ?></td>
          <td><b><?= (int)$ev['reg_count'] ?></b></td>
          <td><span class="ap-badge ap-badge-<?= $scls ?>"><?= $status ?></span></td>
          <td>
            <div class="d-flex gap-1">
              <a href="?edit=<?= (int)$ev['id'] ?>" class="ap-btn ap-btn-outline ap-btn-sm"><i class="bi bi-pencil"></i></a>
              <a href="registrations.php?event_id=<?= (int)$ev['id'] ?>" class="ap-btn ap-btn-outline ap-btn-sm" title="View registrations"><i class="bi bi-ticket"></i></a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete event and ALL its registrations?')">
                <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$ev['id']?>">
                <button type="submit" class="ap-btn ap-btn-danger ap-btn-sm"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
function autoSlug(v){const s=document.getElementById('slugIn');if(!s.dataset.locked)s.value=v.toLowerCase().trim().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');}
document.getElementById('slugIn')?.addEventListener('input',()=>document.getElementById('slugIn').dataset.locked='true');
</script>
<?php adminShellClose(); ?>
