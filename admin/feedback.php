<?php
require_once __DIR__ . '/admin_shell.php';
$db  = getDB();
$msg = ''; $msgType = 'success';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    if ($_POST['action']==='delete' && !empty($_POST['id'])) {
        $db->prepare("DELETE FROM feedback WHERE id=?")->execute([(int)$_POST['id']]);
        $msg='Feedback deleted.';
    } elseif ($_POST['action']==='delete_all') {
        $db->exec("DELETE FROM feedback"); $msg='All feedback cleared.';
    }
}

$typeFilter = $_GET['type']??'';
$page   = max(1,(int)($_GET['page']??1));
$perPage= 15; $offset=($page-1)*$perPage;
$where  = $typeFilter?"WHERE type=?":'';
$params = $typeFilter?[$typeFilter]:[];
$cntStmt= $db->prepare("SELECT COUNT(*) FROM feedback $where"); $cntStmt->execute($params);
$total  = (int)$cntStmt->fetchColumn(); $pages=max(1,(int)ceil($total/$perPage));
$stmt   = $db->prepare("SELECT * FROM feedback $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $rows=$stmt->fetchAll();
$counts = ['Suggestion'=>0,'Complaint'=>0];
foreach ($db->query("SELECT type,COUNT(*) AS c FROM feedback GROUP BY type")->fetchAll() as $r) $counts[$r['type']]=(int)$r['c'];

adminShellOpen('Feedback','feedback');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div style="color:var(--ap-muted);font-size:.82rem"><?=number_format($total)?> submissions · <?=$counts['Suggestion']?> suggestions · <?=$counts['Complaint']?> complaints</div>
  <?php if (!empty($rows)): ?>
  <form method="POST" onsubmit="return confirm('Delete ALL feedback permanently?')" style="display:inline">
    <?=csrfField()?><input type="hidden" name="action" value="delete_all">
    <button type="submit" class="ap-btn ap-btn-danger ap-btn-sm"><i class="bi bi-trash"></i> Clear All</button>
  </form>
  <?php endif; ?>
</div>
<?php if ($msg) echo adminAlert($msg,$msgType); ?>

<!-- Filter tabs -->
<div class="d-flex gap-2 mb-3">
  <a href="feedback.php" class="ap-btn ap-btn-<?=!$typeFilter?'gold':'outline'?> ap-btn-sm" style="text-decoration:none">All (<?=$total?>)</a>
  <a href="?type=Suggestion" class="ap-btn ap-btn-<?=$typeFilter==='Suggestion'?'gold':'outline'?> ap-btn-sm" style="text-decoration:none">💡 Suggestions (<?=$counts['Suggestion']?>)</a>
  <a href="?type=Complaint"  class="ap-btn ap-btn-<?=$typeFilter==='Complaint'?'gold':'outline'?>  ap-btn-sm" style="text-decoration:none">⚠️ Complaints (<?=$counts['Complaint']?>)</a>
</div>

<?php if (empty($rows)): ?>
<div class="ap-card" style="text-align:center;padding:50px;color:var(--ap-muted)"><div style="font-size:3rem;margin-bottom:12px">💬</div>No feedback submissions yet.</div>
<?php endif; ?>

<?php foreach($rows as $f): ?>
<div class="ap-card mb-3">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="ap-badge ap-badge-<?=$f['type']==='Complaint'?'red':'blue'?>"><?=$f['type']?></span>
      <b style="font-size:.88rem"><?=htmlspecialchars($f['name']??'Anonymous',ENT_QUOTES)?></b>
      <?php if($f['email']):?><span style="font-size:.75rem;color:var(--ap-muted)">&lt;<?=htmlspecialchars($f['email'],ENT_QUOTES)?>&gt;</span><?php endif;?>
      <?php if($f['ip']):?><span style="font-size:.72rem;color:rgba(255,255,255,.2);font-family:monospace">IP: <?=htmlspecialchars($f['ip'],ENT_QUOTES)?></span><?php endif;?>
    </div>
    <span style="font-size:.72rem;color:var(--ap-muted)"><?=date('M j, Y H:i',strtotime($f['created_at']))?></span>
  </div>
  <div style="font-weight:600;color:#fff;font-size:.9rem;margin-bottom:8px"><?=htmlspecialchars($f['subject']??'',ENT_QUOTES)?></div>
  <div style="font-size:.84rem;color:rgba(255,255,255,.65);line-height:1.7;white-space:pre-wrap"><?=htmlspecialchars($f['message'],ENT_QUOTES)?></div>
  <?php if($f['attachment']):?>
    <div class="mt-2"><a href="<?=htmlspecialchars(SITE_URL.'/'.$f['attachment'],ENT_QUOTES)?>" target="_blank" style="color:var(--ap-gold);font-size:.8rem"><i class="bi bi-paperclip"></i> View Attachment</a></div>
  <?php endif;?>
  <div class="mt-3">
    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this feedback?')">
      <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$f['id']?>">
      <button type="submit" class="ap-btn ap-btn-danger ap-btn-sm"><i class="bi bi-trash"></i> Delete</button>
    </form>
    <?php if($f['email']):?>
    <a href="mailto:<?=htmlspecialchars($f['email'],ENT_QUOTES)?>" class="ap-btn ap-btn-outline ap-btn-sm ms-2"><i class="bi bi-envelope"></i> Reply</a>
    <?php endif;?>
  </div>
</div>
<?php endforeach; ?>

<?php if($pages>1):?><div class="ap-pagination"><?php for($p=1;$p<=$pages;$p++):?><a href="?page=<?=$p?>&type=<?=urlencode($typeFilter)?>" class="ap-page-btn <?=$p==$page?'active':''?>"><?=$p?></a><?php endfor;?></div><?php endif;?>
<?php adminShellClose(); ?>
