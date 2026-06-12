<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
startSession();
requireLogin('../login.php');
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf,ENT_QUOTES) ?>">
  <title>Change Password | KBEC</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cinzel:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; renderHeader('Change Password', 'password'); ?>

<h1 class="kbec-page-title">Change Password</h1>
<p class="kbec-page-sub">Update your account password. Minimum 8 characters with one uppercase and one number.</p>

<div class="row justify-content-center">
  <div class="col-lg-5 col-md-7">
    <div class="kbec-card">
      <div id="msg-area" class="mb-3"></div>
      <form id="pwdForm" novalidate>
        <div class="mb-3">
          <label class="kbec-label">Current Password</label>
          <div class="position-relative">
            <input type="password" id="cur" name="current_password" class="kbec-input" required placeholder="Current password">
            <span class="pwd-toggle" data-target="cur"><i class="bi bi-eye"></i></span>
          </div>
        </div>
        <div class="mb-3">
          <label class="kbec-label">New Password</label>
          <div class="position-relative">
            <input type="password" id="np" name="new_password" class="kbec-input" required placeholder="New password (min 8 chars)">
            <span class="pwd-toggle" data-target="np"><i class="bi bi-eye"></i></span>
          </div>
          <!-- Strength bar -->
          <div id="strength-bar" style="height:4px;border-radius:2px;margin-top:6px;background:rgba(255,255,255,.1);transition:all .3s"></div>
          <div id="strength-label" style="font-size:.7rem;color:rgba(255,255,255,.4);margin-top:3px"></div>
        </div>
        <div class="mb-4">
          <label class="kbec-label">Confirm New Password</label>
          <div class="position-relative">
            <input type="password" id="cp" name="confirm_password" class="kbec-input" required placeholder="Repeat new password">
            <span class="pwd-toggle" data-target="cp"><i class="bi bi-eye"></i></span>
          </div>
          <div id="match-label" style="font-size:.7rem;margin-top:4px"></div>
        </div>
        <button type="submit" class="kbec-btn kbec-btn-gold w-100"><i class="bi bi-shield-check me-1"></i> Update Password</button>
      </form>
    </div>
  </div>
</div>

<style>
  .pwd-toggle { position:absolute;right:12px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.4);cursor:pointer;font-size:.95rem; }
  .pwd-toggle:hover { color:#c9a84c; }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// Toggle visibility
document.querySelectorAll('.pwd-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const inp = document.getElementById(btn.dataset.target);
    const isText = inp.type === 'text';
    inp.type = isText ? 'password' : 'text';
    btn.innerHTML = `<i class="bi bi-eye${isText ? '' : '-slash'}"></i>`;
  });
});

// Password strength
document.getElementById('np').addEventListener('input', function() {
  const v = this.value;
  let score = 0;
  if (v.length >= 8) score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^a-zA-Z0-9]/.test(v)) score++;
  const bar = document.getElementById('strength-bar');
  const lbl = document.getElementById('strength-label');
  const colors = ['#e74c3c','#e67e22','#f1c40f','#27ae60'];
  const labels = ['Weak','Fair','Good','Strong'];
  bar.style.width = (score * 25) + '%';
  bar.style.background = colors[score-1] || '#e74c3c';
  lbl.textContent = v ? labels[score-1] || 'Very Weak' : '';
  lbl.style.color = colors[score-1] || '#e74c3c';
  checkMatch();
});

// Match check
function checkMatch() {
  const np = document.getElementById('np').value;
  const cp = document.getElementById('cp').value;
  const lbl = document.getElementById('match-label');
  if (!cp) { lbl.textContent = ''; return; }
  if (np === cp) { lbl.style.color='#27ae60'; lbl.textContent='✓ Passwords match'; }
  else { lbl.style.color='#e74c3c'; lbl.textContent='✗ Passwords do not match'; }
}
document.getElementById('cp').addEventListener('input', checkMatch);

// Form submit
document.getElementById('pwdForm').addEventListener('submit', async e => {
  e.preventDefault();
  const area = document.getElementById('msg-area');
  const data = { current_password: document.getElementById('cur').value, new_password: document.getElementById('np').value, confirm_password: document.getElementById('cp').value };
  try {
    const r = await fetch('../api/change_password.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF}, body:JSON.stringify(data) });
    const d = await r.json();
    if (d.ok) {
      area.innerHTML = `<div class="kbec-alert kbec-alert-success"><i class="bi bi-check-circle me-2"></i>${d.message}</div>`;
      e.target.reset();
      document.getElementById('strength-bar').style.width='0';
      document.getElementById('strength-label').textContent='';
    } else {
      area.innerHTML = `<div class="kbec-alert kbec-alert-error"><i class="bi bi-x-circle me-2"></i>${d.message}</div>`;
    }
    setTimeout(() => area.innerHTML='', 4000);
  } catch { area.innerHTML='<div class="kbec-alert kbec-alert-error">Network error.</div>'; }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; renderFooter(); ?>
