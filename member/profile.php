<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

startSession();
requireLogin('../login.php');

$db       = getDB();
$memberId = (int)$_SESSION['member_id'];
$stmt     = $db->prepare("SELECT * FROM `members` WHERE id = ?");
$stmt->execute([$memberId]);
$member   = $stmt->fetch();
$csrf     = csrfToken();

$departments = ['Computer Science and Engineering','Electrical and Electronic Engineering','Mechanical Engineering','Civil Engineering','Industrial and Production Engineering','Electronics and Communication Engineering','Leather Engineering','Chemistry','Mathematics','Physics','Other'];
$interests   = ['startup','finance','marketing','technology','social enterprise','other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
  <title>Edit Profile | KBEC</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cinzel:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; renderHeader('Edit Profile', 'profile'); ?>

<style>
  .avatar-wrap { position:relative; display:inline-block; }
  .avatar-lg { width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid rgba(201,168,76,.4); }
  .avatar-init { width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,#1e3a5f,#0d2340);display:flex;align-items:center;justify-content:center;font-family:'Cinzel',serif;font-size:2rem;font-weight:700;color:#c9a84c;border:3px solid rgba(201,168,76,.3); }
  .upload-btn { position:absolute;bottom:0;right:0;width:28px;height:28px;border-radius:50%;background:#c9a84c;color:#0a0d14;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.8rem;border:2px solid #0a0d14; }
</style>

<h1 class="kbec-page-title">Edit Profile</h1>
<p class="kbec-page-sub">Update your personal information and profile picture.</p>

<div id="msg-area"></div>

<div class="row g-4">
  <!-- Avatar card -->
  <div class="col-lg-3">
    <div class="kbec-card text-center">
      <div class="mb-3 d-flex justify-content-center">
        <div class="avatar-wrap">
          <?php if ($member['profile_image']): ?>
            <img src="<?= htmlspecialchars(SITE_URL.'/'.$member['profile_image'], ENT_QUOTES) ?>" class="avatar-lg" id="avatar-preview" alt="Avatar">
          <?php else: ?>
            <div class="avatar-init" id="avatar-preview-init"><?= strtoupper(substr($member['name'],0,1)) ?></div>
          <?php endif; ?>
          <label for="avatarInput" class="upload-btn" title="Change photo"><i class="bi bi-camera-fill"></i></label>
        </div>
      </div>
      <div style="font-size:.85rem;font-weight:600;color:#fff"><?= htmlspecialchars($member['name'],ENT_QUOTES) ?></div>
      <div style="font-size:.7rem;color:#c9a84c;font-family:'Courier New',monospace"><?= htmlspecialchars($member['member_code'],ENT_QUOTES) ?></div>
      <div style="font-size:.75rem;color:rgba(255,255,255,.4);margin-top:8px"><?= htmlspecialchars($member['email'],ENT_QUOTES) ?></div>
      <input type="file" id="avatarInput" accept="image/jpeg,image/png,image/webp" style="display:none">
      <button class="kbec-btn kbec-btn-outline mt-3 w-100" onclick="document.getElementById('avatarInput').click()">
        <i class="bi bi-camera"></i> Change Photo
      </button>
      <div id="avatar-status" class="mt-2" style="font-size:.78rem"></div>
    </div>
  </div>

  <!-- Profile form -->
  <div class="col-lg-9">
    <div class="kbec-card">
      <h5 class="mb-3" style="color:#c9a84c;font-size:.9rem;letter-spacing:.06em;text-transform:uppercase;font-weight:700">Personal Information</h5>
      <form id="profileForm">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="kbec-label">Full Name *</label>
            <input type="text" name="name" class="kbec-input" required value="<?= htmlspecialchars($member['name'],ENT_QUOTES) ?>">
          </div>
          <div class="col-md-6">
            <label class="kbec-label">Phone</label>
            <input type="text" name="phone" class="kbec-input" value="<?= htmlspecialchars($member['phone']??'',ENT_QUOTES) ?>">
          </div>
          <div class="col-md-6">
            <label class="kbec-label">Department</label>
            <select name="department" class="kbec-input">
              <?php foreach($departments as $d): ?>
                <option value="<?= htmlspecialchars($d,ENT_QUOTES) ?>" <?= $member['department']==$d?'selected':'' ?>><?= htmlspecialchars($d,ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="kbec-label">Batch</label>
            <select name="batch" class="kbec-input">
              <?php foreach(range(25,17) as $b): ?>
                <option value="<?= $b ?>" <?= $member['batch']==$b?'selected':'' ?>><?= $b ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="kbec-label">Area of Interest</label>
            <select name="interest" class="kbec-input">
              <option value="">—</option>
              <?php foreach($interests as $i): ?>
                <option value="<?= htmlspecialchars($i,ENT_QUOTES) ?>" <?= $member['interest']==$i?'selected':'' ?>><?= ucfirst($i) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="kbec-label">Short Bio</label>
            <textarea name="bio" class="kbec-input" rows="3"><?= htmlspecialchars($member['bio']??'',ENT_QUOTES) ?></textarea>
          </div>
          <div class="col-12">
            <button type="submit" class="kbec-btn kbec-btn-gold"><i class="bi bi-check-lg"></i> Save Changes</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

function showMsg(msg, ok=true) {
  const el = document.getElementById('msg-area');
  el.innerHTML = `<div class="kbec-alert kbec-alert-${ok?'success':'error'} mb-3"><i class="bi bi-${ok?'check':'exclamation'}-circle me-2"></i>${msg}</div>`;
  setTimeout(() => el.innerHTML = '', 4000);
}

// Profile form submit
document.getElementById('profileForm').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const data = Object.fromEntries(fd.entries());
  try {
    const r = await fetch('../api/update_profile.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify(data)
    });
    const d = await r.json();
    if (d.ok) showMsg(d.message || 'Profile updated!');
    else showMsg(d.message || 'Update failed.', false);
  } catch { showMsg('Network error.', false); }
});

// Avatar upload
document.getElementById('avatarInput').addEventListener('change', async function() {
  if (!this.files[0]) return;
  const status = document.getElementById('avatar-status');
  status.textContent = 'Uploading…';
  const fd = new FormData();
  fd.append('avatar', this.files[0]);
  fd.append('csrf_token', CSRF);
  try {
    const r = await fetch('../api/upload_avatar.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) {
      status.innerHTML = '<span style="color:#27ae60">✓ Photo updated</span>';
      // Update preview
      const prev = document.getElementById('avatar-preview');
      const init = document.getElementById('avatar-preview-init');
      const newSrc = `<?= SITE_URL ?>/` + d.profile_image + '?t=' + Date.now();
      if (prev) prev.src = newSrc;
      else if (init) { const img = document.createElement('img'); img.src = newSrc; img.className = 'avatar-lg'; img.id = 'avatar-preview'; init.replaceWith(img); }
    } else {
      status.innerHTML = `<span style="color:#e74c3c">${d.message}</span>`;
    }
  } catch { status.innerHTML = '<span style="color:#e74c3c">Upload failed.</span>'; }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; renderFooter(); ?>
