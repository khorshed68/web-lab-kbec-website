<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

startSession();
requireLogin('../login.php');

$db       = getDB();
$memberId = (int)$_SESSION['member_id'];

// Load full member row
$mStmt  = $db->prepare("SELECT * FROM `members` WHERE id = ?");
$mStmt->execute([$memberId]);
$member = $mStmt->fetch();

// Load member's registrations
$rStmt = $db->prepare("
    SELECT r.*, e.title, e.type, e.location, e.event_date_start, e.event_date_end, e.slug
    FROM `event_registrations` r
    JOIN `events` e ON e.id = r.event_id
    WHERE r.member_id = ?
    ORDER BY r.registered_at DESC
");
$rStmt->execute([$memberId]);
$myRegistrations = $rStmt->fetchAll();

// Load upcoming open events (not registered yet)
$today   = date('Y-m-d');
$oStmt   = $db->prepare("
    SELECT e.*, COUNT(r.id) AS reg_count
    FROM `events` e
    LEFT JOIN `event_registrations` r ON r.event_id = e.id
    WHERE e.event_date_start >= ? AND e.registration_deadline >= ?
      AND e.id NOT IN (SELECT event_id FROM `event_registrations` WHERE member_id = ?)
    GROUP BY e.id
    HAVING reg_count < e.capacity
    ORDER BY e.event_date_start ASC
    LIMIT 6
");
$oStmt->execute([$today, $today, $memberId]);
$openEvents = $oStmt->fetchAll();

$welcome = isset($_GET['welcome']);
$csrf    = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
  <title>Dashboard | KBEC</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cinzel:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; renderHeader('Dashboard', 'dashboard'); ?>

<style>
  /* Stat cards */
  .stat-card { background: linear-gradient(135deg, #141926, #1e2438); border: 1px solid rgba(201,168,76,.15); border-radius: 16px; padding: 24px 20px; }
  .stat-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 14px; }
  .stat-value { font-size: 1.9rem; font-weight: 700; color: #c9a84c; line-height: 1; }
  .stat-label { font-size: .72rem; color: rgba(255,255,255,.45); letter-spacing: .1em; text-transform: uppercase; margin-top: 4px; }
  /* Event cards */
  .event-card { background: #141926; border: 1px solid rgba(255,255,255,.07); border-radius: 14px; padding: 20px; transition: border-color .2s, transform .2s; }
  .event-card:hover { border-color: rgba(201,168,76,.3); transform: translateY(-2px); }
  .event-type { display: inline-block; background: rgba(201,168,76,.12); color: #c9a84c; border: 1px solid rgba(201,168,76,.25); border-radius: 999px; font-size: .65rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; padding: 3px 9px; margin-bottom: 10px; }
  .event-title { font-size: .95rem; font-weight: 600; color: #fff; margin-bottom: 6px; line-height: 1.35; }
  .event-meta { font-size: .78rem; color: rgba(255,255,255,.45); display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
  .btn-register { background: linear-gradient(135deg,#c9a84c,#a8873a); color: #0a0d14; border: none; border-radius: 8px; padding: 8px 16px; font-size: .8rem; font-weight: 700; cursor: pointer; transition: all .2s; }
  .btn-register:hover { transform: translateY(-1px); }
  /* Ticket row */
  .ticket-row { background: rgba(255,255,255,.02); border: 1px solid rgba(255,255,255,.06); border-radius: 10px; padding: 14px 18px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
  .ticket-code { font-family: 'Courier New', monospace; font-size: .8rem; color: #c9a84c; background: rgba(201,168,76,.08); padding: 3px 8px; border-radius: 5px; }
</style>

<?php if ($welcome): ?>
<div class="kbec-alert kbec-alert-success mb-4">
  <i class="bi bi-check-circle me-2"></i>
  Welcome to KBEC! Your account <strong><?= htmlspecialchars($member['member_code'], ENT_QUOTES) ?></strong> is now active.
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
  <div>
    <h1 class="kbec-page-title mb-1">Welcome, <?= htmlspecialchars(explode(' ', $member['name'])[0], ENT_QUOTES) ?>!</h1>
    <p class="kbec-page-sub mb-0">KBEC Member Dashboard — Executive Committee 2026-27</p>
  </div>
  <span style="background:linear-gradient(135deg,rgba(201,168,76,.15),rgba(201,168,76,.08));border:1px solid rgba(201,168,76,.3);color:#c9a84c;font-family:'Courier New',monospace;font-size:.85rem;font-weight:700;padding:8px 16px;border-radius:999px;">
    <?= htmlspecialchars($member['member_code'], ENT_QUOTES) ?>
  </span>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(201,168,76,.1)"><i class="bi bi-person-badge" style="color:#c9a84c"></i></div>
      <div class="stat-value"><?= htmlspecialchars($member['member_code'], ENT_QUOTES) ?></div>
      <div class="stat-label">Member Code</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(52,152,219,.1)"><i class="bi bi-ticket-perforated" style="color:#3498db"></i></div>
      <div class="stat-value"><?= count($myRegistrations) ?></div>
      <div class="stat-label">Events Registered</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(155,89,182,.1)"><i class="bi bi-calendar-event" style="color:#9b59b6"></i></div>
      <div class="stat-value"><?= count($openEvents) ?></div>
      <div class="stat-label">Open Events</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(39,174,96,.1)"><i class="bi bi-patch-check" style="color:#27ae60"></i></div>
      <div class="stat-value">Active</div>
      <div class="stat-label">Account Status</div>
    </div>
  </div>
</div>

<!-- My Registered Events -->
<div class="kbec-card mb-4">
  <h5 class="mb-3" style="color:#c9a84c;font-size:.95rem;letter-spacing:.04em;text-transform:uppercase;font-weight:700">
    <i class="bi bi-ticket-perforated me-2"></i>My Event Tickets
  </h5>
  <?php if (empty($myRegistrations)): ?>
    <p style="color:rgba(255,255,255,.4);font-size:.88rem">You haven't registered for any events yet.</p>
  <?php else: ?>
    <?php foreach ($myRegistrations as $reg): ?>
      <div class="ticket-row">
        <div>
          <div style="font-size:.9rem;font-weight:600;color:#fff;margin-bottom:4px"><?= htmlspecialchars($reg['title'], ENT_QUOTES) ?></div>
          <div style="font-size:.75rem;color:rgba(255,255,255,.45)">
            <i class="bi bi-calendar3 me-1"></i><?= date('M j, Y', strtotime($reg['event_date_start'])) ?>
            &nbsp;·&nbsp;<i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($reg['location'] ?? '', ENT_QUOTES) ?>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="ticket-code"><?= htmlspecialchars($reg['ticket_code'] ?? '', ENT_QUOTES) ?></span>
          <?php if ($reg['attended_at']): ?>
            <span class="badge-green">Attended</span>
          <?php else: ?>
            <span class="badge-blue">Registered</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Available Events -->
<?php if (!empty($openEvents)): ?>
<div class="kbec-card mb-4">
  <h5 class="mb-3" style="color:#c9a84c;font-size:.95rem;letter-spacing:.04em;text-transform:uppercase;font-weight:700">
    <i class="bi bi-calendar-event me-2"></i>Available Events
  </h5>
  <div class="row g-3">
    <?php foreach ($openEvents as $ev): ?>
      <div class="col-md-6 col-lg-4">
        <div class="event-card h-100">
          <span class="event-type"><?= htmlspecialchars($ev['type'], ENT_QUOTES) ?></span>
          <div class="event-title"><?= htmlspecialchars($ev['title'], ENT_QUOTES) ?></div>
          <div class="event-meta"><i class="bi bi-calendar3"></i><?= date('M j, Y', strtotime($ev['event_date_start'])) ?></div>
          <div class="event-meta"><i class="bi bi-geo-alt"></i><?= htmlspecialchars($ev['location'] ?? '', ENT_QUOTES) ?></div>
          <div class="event-meta"><i class="bi bi-people"></i><?= (int)$ev['capacity'] - (int)$ev['reg_count'] ?> seats left</div>
          <div class="mt-3">
            <button class="btn-register" onclick="registerEvent(<?= (int)$ev['id'] ?>, this)">
              <i class="bi bi-plus-circle me-1"></i>Register
            </button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Member Profile Card -->
<div class="kbec-card">
  <h5 class="mb-3" style="color:#c9a84c;font-size:.95rem;letter-spacing:.04em;text-transform:uppercase;font-weight:700">
    <i class="bi bi-person-circle me-2"></i>My Profile
  </h5>
  <div class="row g-3" style="font-size:.88rem">
    <div class="col-sm-6"><span style="color:rgba(255,255,255,.4)">Department</span><div><?= htmlspecialchars($member['department'] ?? '—', ENT_QUOTES) ?></div></div>
    <div class="col-sm-6"><span style="color:rgba(255,255,255,.4)">Batch</span><div><?= htmlspecialchars($member['batch'] ?? '—', ENT_QUOTES) ?></div></div>
    <div class="col-sm-6"><span style="color:rgba(255,255,255,.4)">Phone</span><div><?= htmlspecialchars($member['phone'] ?? '—', ENT_QUOTES) ?></div></div>
    <div class="col-sm-6"><span style="color:rgba(255,255,255,.4)">Interest</span><div><?= htmlspecialchars($member['interest'] ?? '—', ENT_QUOTES) ?></div></div>
    <?php if ($member['bio']): ?>
    <div class="col-12"><span style="color:rgba(255,255,255,.4)">Bio</span><div><?= htmlspecialchars($member['bio'], ENT_QUOTES) ?></div></div>
    <?php endif; ?>
    <div class="col-12 mt-1">
      <a href="profile.php" class="kbec-btn kbec-btn-outline" style="text-decoration:none">
        <i class="bi bi-pencil"></i> Edit Profile
      </a>
    </div>
  </div>
</div>

<!-- Toast notification -->
<div id="toast" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:none;min-width:280px;background:#1e2438;border:1px solid rgba(201,168,76,.3);border-radius:12px;padding:14px 18px;color:#fff;font-size:.88rem;box-shadow:0 10px 40px rgba(0,0,0,.5)"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
function showToast(msg, ok=true) {
  const t = document.getElementById('toast');
  t.innerHTML = `<i class="bi bi-${ok?'check-circle text-success':'x-circle text-danger'} me-2"></i>${msg}`;
  t.style.display = 'block';
  setTimeout(() => t.style.display='none', 3500);
}
async function registerEvent(eventId, btn) {
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  try {
    const r = await fetch('../api/register_event.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify({event_id: eventId, note: ''})
    });
    const d = await r.json();
    if (d.ok) {
      showToast(d.alreadyRegistered ? 'Already registered for this event.' : `Registered! Ticket: ${d.ticket.ticket_code}`);
      setTimeout(() => location.reload(), 1800);
    } else {
      showToast(d.message || 'Registration failed.', false);
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Register';
    }
  } catch(e) { showToast('Network error.', false); btn.disabled=false; btn.innerHTML='Register'; }
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; renderFooter(); ?>
