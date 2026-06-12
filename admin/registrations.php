<?php
require_once __DIR__ . '/admin_shell.php';
$db = getDB();

// CSV export
if (isset($_GET['export']) && $_GET['export']==='csv') {
    $eid    = (int)($_GET['event_id']??0);
    $where  = $eid ? 'WHERE r.event_id=?' : '';
    $params = $eid ? [$eid] : [];
    $rows   = $db->prepare("SELECT r.id,m.member_code,m.name,m.email,m.phone,m.department,m.batch,e.title AS event,r.ticket_code,r.note,r.registered_at,r.attended_at FROM event_registrations r JOIN members m ON m.id=r.member_id JOIN events e ON e.id=r.event_id $where ORDER BY r.registered_at DESC");
    $rows->execute($params); $data=$rows->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kbec-registrations-'.date('Y-m-d').'.csv"');
    $f=fopen('php://output','w');
    fputcsv($f,['ID','Member Code','Name','Email','Phone','Dept','Batch','Event','Ticket','Note','Registered At','Attended At']);
    foreach($data as $r) fputcsv($f,array_values($r));
    fclose($f); exit;
}

// Mark attended
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    if ($_POST['action']==='mark_attended') {
        $db->prepare("UPDATE event_registrations SET attended_at=NOW() WHERE id=? AND attended_at IS NULL")->execute([(int)$_POST['id']]);
    } elseif ($_POST['action']==='unmark_attended') {
        $db->prepare("UPDATE event_registrations SET attended_at=NULL WHERE id=?")->execute([(int)$_POST['id']]);
    } elseif ($_POST['action']==='delete') {
        $db->prepare("DELETE FROM event_registrations WHERE id=?")->execute([(int)$_POST['id']]);
    }
}

$eid    = (int)($_GET['event_id']??0);
$q      = trim($_GET['q']??'');
$page   = max(1,(int)($_GET['page']??1));
$perPage= 25; $offset=($page-1)*$perPage;
$where  = '1=1'; $params=[];
if ($eid)  { $where.=' AND r.event_id=?'; $params[]=$eid; }
if ($q)    { $where.=' AND (m.name LIKE ? OR m.email LIKE ?)'; $params[]="%$q%"; $params[]="%$q%"; }
$cnt    = $db->prepare("SELECT COUNT(*) FROM event_registrations r JOIN members m ON m.id=r.member_id WHERE $where");
$cnt->execute($params); $total=(int)$cnt->fetchColumn(); $pages=max(1,(int)ceil($total/$perPage));
$rows   = $db->prepare("SELECT r.*,m.member_code,m.name,m.email,m.department,e.title AS event_title,e.slug AS event_slug FROM event_registrations r JOIN members m ON m.id=r.member_id JOIN events e ON e.id=r.event_id WHERE $where ORDER BY r.registered_at DESC LIMIT $perPage OFFSET $offset");
$rows->execute($params); $regs=$rows->fetchAll();
$eventList=$db->query("SELECT id,title FROM events ORDER BY event_date_start DESC")->fetchAll();

adminShellOpen('Registrations','registrations');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div style="color:var(--ap-muted);font-size:.82rem"><?= number_format($total) ?> registrations</div>
  <a href="?export=csv&event_id=<?=$eid?>&q=<?=urlencode($q)?>" class="ap-btn ap-btn-gold ap-btn-sm" style="text-decoration:none"><i class="bi bi-download"></i> Export CSV</a>
</div>
<?php if (isset($_POST['action'])) echo adminAlert('Action completed.','success'); ?>

<!-- Filters -->
<form method="GET" class="ap-search">
  <select name="event_id" class="ap-input" style="max-width:240px">
    <option value="">All Events</option>
    <?php foreach($eventList as $e): ?><option value="<?=(int)$e['id']?>" <?=$eid===(int)$e['id']?'selected':''?>><?=htmlspecialchars($e['title'],ENT_QUOTES)?></option><?php endforeach;?>
  </select>
  <input type="text" name="q" class="ap-input" style="max-width:200px" placeholder="Search member…" value="<?=htmlspecialchars($q,ENT_QUOTES)?>">
  <button type="submit" class="ap-btn ap-btn-gold"><i class="bi bi-funnel"></i> Filter</button>
  <a href="registrations.php" class="ap-btn ap-btn-outline">Reset</a>
</form>

<div class="ap-card p-0">
  <div class="ap-table-wrap">
    <table class="ap-table">
      <thead><tr><th>#</th><th>Member</th><th>Event</th><th>Ticket Code</th><th>Registered</th><th>Attended</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($regs as $r): ?>
        <tr>
          <td style="color:var(--ap-muted);font-size:.72rem"><?=(int)$r['id']?></td>
          <td><b><?=htmlspecialchars($r['name'],ENT_QUOTES)?></b><br><small style="color:var(--ap-muted)"><?=htmlspecialchars($r['email'],ENT_QUOTES)?></small></td>
          <td style="font-size:.78rem;max-width:160px"><?=htmlspecialchars($r['event_title'],ENT_QUOTES)?></td>
          <td><span style="font-family:monospace;font-size:.72rem;color:var(--ap-gold)"><?=htmlspecialchars($r['ticket_code'],ENT_QUOTES)?></span></td>
          <td style="font-size:.72rem;color:var(--ap-muted)"><?=date('M j, Y H:i',strtotime($r['registered_at']))?></td>
          <td>
            <?php if ($r['attended_at']): ?>
              <span class="ap-badge ap-badge-green">✓ <?=date('M j',strtotime($r['attended_at']))?></span>
            <?php else: ?>
              <span class="ap-badge ap-badge-grey">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="d-flex gap-1 flex-wrap">
              <?php if (!$r['attended_at']): ?>
              <form method="POST" style="display:inline">
                <?=csrfField()?><input type="hidden" name="action" value="mark_attended"><input type="hidden" name="id" value="<?=(int)$r['id']?>">
                <button type="submit" class="ap-btn ap-btn-success ap-btn-sm" title="Mark attended"><i class="bi bi-check2"></i></button>
              </form>
              <?php else: ?>
              <form method="POST" style="display:inline">
                <?=csrfField()?><input type="hidden" name="action" value="unmark_attended"><input type="hidden" name="id" value="<?=(int)$r['id']?>">
                <button type="submit" class="ap-btn ap-btn-outline ap-btn-sm" title="Unmark"><i class="bi bi-x"></i></button>
              </form>
              <?php endif; ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this registration?')">
                <?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$r['id']?>">
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
<?php if($pages>1): ?><div class="ap-pagination"><?php for($p=1;$p<=$pages;$p++): ?><a href="?page=<?=$p?>&event_id=<?=$eid?>&q=<?=urlencode($q)?>" class="ap-page-btn <?=$p==$page?'active':''?>"><?=$p?></a><?php endfor;?></div><?php endif;?>
<?php adminShellClose(); ?>
