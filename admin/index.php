<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
startSession();
requireAdmin('../index.php');

$db = getDB();
$totalMembers       = (int)$db->query("SELECT COUNT(*) FROM `members` WHERE role='member'")->fetchColumn();
$totalEvents        = (int)$db->query("SELECT COUNT(*) FROM `events`")->fetchColumn();
$totalRegistrations = (int)$db->query("SELECT COUNT(*) FROM `event_registrations`")->fetchColumn();
$totalFeedback      = (int)$db->query("SELECT COUNT(*) FROM `feedback`")->fetchColumn();

$recentRegs = $db->query("
    SELECT r.registered_at, r.ticket_code, m.name AS member_name, e.title AS event_title
    FROM event_registrations r
    JOIN members m ON m.id=r.member_id
    JOIN events  e ON e.id=r.event_id
    ORDER BY r.registered_at DESC LIMIT 8
")->fetchAll();

$recentFeedback = $db->query("
    SELECT id,type,name,subject,created_at FROM feedback ORDER BY created_at DESC LIMIT 5
")->fetchAll();

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf,ENT_QUOTES) ?>">
  <title>Admin Dashboard | KBEC</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cinzel:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; renderHeader('Admin Dashboard', 'admin_dashboard'); ?>

<h1 class="kbec-page-title">Admin Dashboard</h1>
<p class="kbec-page-sub">Welcome back, <?= htmlspecialchars($_SESSION['member_name'],ENT_QUOTES) ?>. Here's an overview of the club.</p>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <?php
  $stats = [
    ['Total Members','bi-people',$totalMembers,'rgba(201,168,76,.1)','#c9a84c','members.php'],
    ['Total Events','bi-calendar-event',$totalEvents,'rgba(52,152,219,.1)','#3498db','events.php'],
    ['Total Registrations','bi-ticket-perforated',$totalRegistrations,'rgba(155,89,182,.1)','#9b59b6','registrations.php'],
    ['Total Feedback','bi-chat-square-text',$totalFeedback,'rgba(231,76,60,.1)','#e74c3c','feedback.php'],
  ];
  foreach($stats as [$label,$icon,$val,$bg,$color,$link]):
  ?>
  <div class="col-6 col-md-3">
    <a href="<?= $link ?>" style="text-decoration:none">
      <div class="stat-card">
        <div class="stat-icon" style="background:<?= $bg ?>"><i class="bi <?= $icon ?>" style="color:<?= $color ?>"></i></div>
        <div class="stat-value" style="color:<?= $color ?>"><?= number_format($val) ?></div>
        <div class="stat-label"><?= $label ?></div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <!-- Recent Registrations -->
  <div class="col-lg-7">
    <div class="kbec-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 style="color:#c9a84c;font-size:.9rem;letter-spacing:.06em;text-transform:uppercase;font-weight:700;margin:0"><i class="bi bi-ticket-perforated me-2"></i>Recent Registrations</h5>
        <a href="registrations.php" style="font-size:.78rem;color:rgba(255,255,255,.4);text-decoration:none">View all →</a>
      </div>
      <table class="kbec-table">
        <thead><tr><th>Member</th><th>Event</th><th>Ticket</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach($recentRegs as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['member_name'],ENT_QUOTES) ?></td>
            <td style="font-size:.78rem;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($r['event_title'],ENT_QUOTES) ?></td>
            <td><span style="font-family:monospace;font-size:.75rem;color:#c9a84c"><?= htmlspecialchars($r['ticket_code'],ENT_QUOTES) ?></span></td>
            <td style="color:rgba(255,255,255,.45);font-size:.75rem"><?= date('M j', strtotime($r['registered_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Feedback -->
  <div class="col-lg-5">
    <div class="kbec-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 style="color:#c9a84c;font-size:.9rem;letter-spacing:.06em;text-transform:uppercase;font-weight:700;margin:0"><i class="bi bi-chat-square-text me-2"></i>Recent Feedback</h5>
        <a href="feedback.php" style="font-size:.78rem;color:rgba(255,255,255,.4);text-decoration:none">View all →</a>
      </div>
      <?php foreach($recentFeedback as $f): ?>
      <div style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,.05)">
        <div class="d-flex gap-2 align-items-center mb-1">
          <span class="<?= $f['type']==='Complaint'?'badge-red':'badge-blue' ?>"><?= $f['type'] ?></span>
          <span style="font-size:.78rem;color:rgba(255,255,255,.5)"><?= htmlspecialchars($f['name']??'Anonymous',ENT_QUOTES) ?></span>
        </div>
        <div style="font-size:.82rem;color:rgba(255,255,255,.7)"><?= htmlspecialchars(substr($f['subject']??'',0,60),ENT_QUOTES) ?>...</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<style>
.stat-card{background:linear-gradient(135deg,#141926,#1e2438);border:1px solid rgba(201,168,76,.12);border-radius:16px;padding:22px 18px;transition:transform .2s}
.stat-card:hover{transform:translateY(-2px)}
.stat-icon{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;margin-bottom:12px}
.stat-value{font-size:1.8rem;font-weight:700;line-height:1}
.stat-label{font-size:.7rem;color:rgba(255,255,255,.4);letter-spacing:.1em;text-transform:uppercase;margin-top:4px}
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; renderFooter(); ?>
