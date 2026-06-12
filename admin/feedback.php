<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
startSession();
requireAdmin('../index.php');

$db   = getDB();
$csrf = csrfToken();
$msg  = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    if ($_POST['action']==='delete' && !empty($_POST['id'])) {
        $db->prepare("DELETE FROM `feedback` WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Feedback deleted.';
    }
}

$typeFilter = $_GET['type'] ?? '';
$page    = max(1,(int)($_GET['page']??1));
$perPage = 20; $offset = ($page-1)*$perPage;
$where  = $typeFilter ? "WHERE type=?" : '';
$params = $typeFilter ? [$typeFilter] : [];

$total  = (int)$db->prepare("SELECT COUNT(*) FROM feedback $where")->execute($params) && true ? $db->prepare("SELECT COUNT(*) FROM feedback $where")->execute($params) : 0;
$cntStmt = $db->prepare("SELECT COUNT(*) FROM feedback $where"); $cntStmt->execute($params); $total=(int)$cntStmt->fetchColumn();
$pages  = max(1,(int)ceil($total/$perPage));

$stmt = $db->prepare("SELECT * FROM `feedback` $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Feedback | KBEC Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cinzel:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; renderHeader('Feedback','admin_feedback'); ?>

<h1 class="kbec-page-title">Feedback & Submissions</h1>
<p class="kbec-page-sub"><?= number_format($total) ?> total submissions</p>

<?php if ($msg): ?><div class="kbec-alert kbec-alert-success mb-3"><?= htmlspecialchars($msg,ENT_QUOTES) ?></div><?php endif; ?>

<!-- Filter -->
<div class="mb-3 d-flex gap-2">
  <a href="feedback.php" class="kbec-btn kbec-btn-<?= !$typeFilter?'gold':'outline' ?>" style="text-decoration:none">All</a>
  <a href="?type=Suggestion" class="kbec-btn kbec-btn-<?= $typeFilter==='Suggestion'?'gold':'outline' ?>" style="text-decoration:none">Suggestions</a>
  <a href="?type=Complaint"  class="kbec-btn kbec-btn-<?= $typeFilter==='Complaint'?'gold':'outline' ?>"  style="text-decoration:none">Complaints</a>
</div>

<!-- Feedback cards -->
<?php foreach($rows as $f): ?>
<div class="kbec-card mb-3">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="<?= $f['type']==='Complaint'?'badge-red':'badge-blue' ?>"><?= $f['type'] ?></span>
      <b style="font-size:.88rem"><?= htmlspecialchars($f['name']??'Anonymous',ENT_QUOTES) ?></b>
      <?php if ($f['email']): ?><span style="font-size:.78rem;color:rgba(255,255,255,.4)">&lt;<?= htmlspecialchars($f['email'],ENT_QUOTES) ?>&gt;</span><?php endif; ?>
    </div>
    <span style="font-size:.75rem;color:rgba(255,255,255,.35)"><?= date('M j, Y H:i',strtotime($f['created_at'])) ?></span>
  </div>
  <div style="font-size:.92rem;font-weight:600;color:#fff;margin-bottom:6px"><?= htmlspecialchars($f['subject']??'',ENT_QUOTES) ?></div>
  <div style="font-size:.85rem;color:rgba(255,255,255,.65);line-height:1.6"><?= nl2br(htmlspecialchars($f['message'],ENT_QUOTES)) ?></div>
  <?php if ($f['attachment']): ?>
    <div class="mt-2"><a href="<?= htmlspecialchars(SITE_URL.'/'.$f['attachment'],ENT_QUOTES) ?>" target="_blank" style="color:#c9a84c;font-size:.8rem"><i class="bi bi-paperclip me-1"></i>Attachment</a></div>
  <?php endif; ?>
  <div class="mt-3">
    <form method="POST" style="display:inline">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
      <button type="submit" class="kbec-btn kbec-btn-danger" style="padding:6px 12px;font-size:.78rem" onclick="return confirm('Delete this feedback?')">
        <i class="bi bi-trash"></i> Delete
      </button>
    </form>
  </div>
</div>
<?php endforeach; ?>

<?php if (empty($rows)): ?>
<div class="kbec-card" style="text-align:center;color:rgba(255,255,255,.4);padding:40px">No feedback found.</div>
<?php endif; ?>

<?php if ($pages>1): ?>
<div class="d-flex gap-1 mt-3 flex-wrap">
  <?php for($p=1;$p<=$pages;$p++): ?>
    <a href="?page=<?= $p ?>&type=<?= urlencode($typeFilter) ?>" class="kbec-btn kbec-btn-<?= $p==$page?'gold':'outline' ?>" style="padding:6px 12px;font-size:.8rem;text-decoration:none"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; renderFooter(); ?>
