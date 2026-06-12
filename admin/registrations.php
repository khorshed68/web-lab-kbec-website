<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
startSession();
requireAdmin('../index.php');

$db   = getDB();
$csrf = csrfToken();

// ── CSV Export ──────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $where  = '';
    $params = [];
    if (!empty($_GET['event_id'])) { $where = 'WHERE r.event_id = ?'; $params = [(int)$_GET['event_id']]; }

    $rows = $db->prepare("
        SELECT r.id, m.member_code, m.name, m.email, m.phone, m.department, m.batch,
               e.title AS event, r.ticket_code, r.note,
               r.registered_at, r.attended_at
        FROM event_registrations r
        JOIN members m ON m.id=r.member_id
        JOIN events  e ON e.id=r.event_id
        $where ORDER BY r.registered_at DESC
    ");
    $rows->execute($params);
    $data = $rows->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kbec-registrations-' . date('Y-m-d') . '.csv"');
    $f = fopen('php://output', 'w');
    fputcsv($f, ['ID','Member Code','Name','Email','Phone','Department','Batch','Event','Ticket','Note','Registered At','Attended At']);
    foreach ($data as $r) fputcsv($f, array_values($r));
    fclose($f);
    exit;
}

// ── Filters ──────────────────────────────────────────────
$eventId = (int)($_GET['event_id'] ?? 0);
$search  = trim($_GET['q'] ?? '');
$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where  = '1=1';
$params = [];
if ($eventId)  { $where .= ' AND r.event_id=?'; $params[] = $eventId; }
if ($search)   { $where .= ' AND (m.name LIKE ? OR m.email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$totalStmt = $db->prepare("SELECT COUNT(*) FROM event_registrations r JOIN members m ON m.id=r.member_id WHERE $where");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$pages = max(1,(int)ceil($total/$perPage));

$regs = $db->prepare("
    SELECT r.*, m.member_code, m.name, m.email, m.department,
           e.title AS event_title, e.slug AS event_slug
    FROM event_registrations r
    JOIN members m ON m.id=r.member_id
    JOIN events  e ON e.id=r.event_id
    WHERE $where ORDER BY r.registered_at DESC LIMIT $perPage OFFSET $offset
");
$regs->execute($params);
$rows = $regs->fetchAll();

$eventList = $db->query("SELECT id, title FROM events ORDER BY event_date_start DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Registrations | KBEC Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cinzel:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; renderHeader('Registrations','admin_regs'); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h1 class="kbec-page-title mb-1">Registrations</h1>
    <p class="kbec-page-sub mb-0"><?= number_format($total) ?> total registrations</p>
  </div>
  <a href="?export=csv&event_id=<?= $eventId ?>&q=<?= urlencode($search) ?>" class="kbec-btn kbec-btn-gold" style="text-decoration:none">
    <i class="bi bi-download"></i> Export CSV
  </a>
</div>

<!-- Filters -->
<form method="GET" class="mb-3 d-flex gap-2 flex-wrap">
  <select name="event_id" class="kbec-input" style="max-width:260px">
    <option value="">All Events</option>
    <?php foreach($eventList as $e): ?>
      <option value="<?= (int)$e['id'] ?>" <?= $eventId===$e['id']?'selected':'' ?>><?= htmlspecialchars($e['title'],ENT_QUOTES) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="text" name="q" class="kbec-input" style="max-width:220px" placeholder="Search member…" value="<?= htmlspecialchars($search,ENT_QUOTES) ?>">
  <button type="submit" class="kbec-btn kbec-btn-gold"><i class="bi bi-filter"></i> Filter</button>
  <a href="registrations.php" class="kbec-btn kbec-btn-outline" style="text-decoration:none">Reset</a>
</form>

<div class="kbec-card p-0" style="overflow-x:auto">
  <table class="kbec-table">
    <thead><tr><th>#</th><th>Member</th><th>Event</th><th>Ticket Code</th><th>Note</th><th>Registered</th><th>Attended</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
      <tr>
        <td style="color:rgba(255,255,255,.3);font-size:.75rem"><?= (int)$r['id'] ?></td>
        <td>
          <b style="font-size:.85rem"><?= htmlspecialchars($r['name'],ENT_QUOTES) ?></b><br>
          <small style="color:rgba(255,255,255,.4)"><?= htmlspecialchars($r['email'],ENT_QUOTES) ?></small>
        </td>
        <td style="font-size:.8rem;max-width:180px"><?= htmlspecialchars($r['event_title'],ENT_QUOTES) ?></td>
        <td><span style="font-family:monospace;font-size:.75rem;color:#c9a84c"><?= htmlspecialchars($r['ticket_code'],ENT_QUOTES) ?></span></td>
        <td style="font-size:.78rem;color:rgba(255,255,255,.5);max-width:130px"><?= htmlspecialchars(substr($r['note']??'',0,40),ENT_QUOTES) ?></td>
        <td style="font-size:.75rem;color:rgba(255,255,255,.45)"><?= date('M j, Y H:i',strtotime($r['registered_at'])) ?></td>
        <td>
          <?php if ($r['attended_at']): ?>
            <span class="badge-green">✓ <?= date('M j',strtotime($r['attended_at'])) ?></span>
          <?php else: ?>
            <span style="color:rgba(255,255,255,.3);font-size:.78rem">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div class="d-flex gap-1 mt-3 flex-wrap">
  <?php for($p=1;$p<=$pages;$p++): ?>
    <a href="?page=<?= $p ?>&event_id=<?= $eventId ?>&q=<?= urlencode($search) ?>" class="kbec-btn kbec-btn-<?= $p==$page?'gold':'outline' ?>" style="padding:6px 12px;font-size:.8rem;text-decoration:none"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; renderFooter(); ?>
