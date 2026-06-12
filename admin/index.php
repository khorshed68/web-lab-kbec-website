<?php
require_once __DIR__ . '/admin_shell.php';
$db = getDB();

// Quick stats
$stats = [
    'members'       => (int)$db->query("SELECT COUNT(*) FROM members WHERE role='member'")->fetchColumn(),
    'events'        => (int)$db->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'registrations' => (int)$db->query("SELECT COUNT(*) FROM event_registrations")->fetchColumn(),
    'feedback'      => (int)$db->query("SELECT COUNT(*) FROM feedback")->fetchColumn(),
    'team'          => (int)$db->query("SELECT COUNT(*) FROM team_members WHERE is_active=1")->fetchColumn(),
    'sponsors'      => (int)$db->query("SELECT COUNT(*) FROM sponsors WHERE is_active=1")->fetchColumn(),
    'announcements' => (int)$db->query("SELECT COUNT(*) FROM announcements WHERE is_active=1")->fetchColumn(),
    'gallery'       => (int)$db->query("SELECT COUNT(*) FROM gallery")->fetchColumn(),
];

// Recent activity
$recentMembers = $db->query("SELECT name, member_code, email, created_at FROM members WHERE role='member' ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recentRegs    = $db->query("SELECT r.registered_at, r.ticket_code, m.name AS mname, e.title AS etitle FROM event_registrations r JOIN members m ON m.id=r.member_id JOIN events e ON e.id=r.event_id ORDER BY r.registered_at DESC LIMIT 5")->fetchAll();
$recentFb      = $db->query("SELECT type, name, subject, created_at FROM feedback ORDER BY created_at DESC LIMIT 5")->fetchAll();
$activeAnnounce= $db->query("SELECT title, type, is_active FROM announcements ORDER BY created_at DESC LIMIT 5")->fetchAll();

adminShellOpen('Dashboard', 'dashboard');
?>

<!-- Stats Grid -->
<div class="ap-stats">
  <a href="members.php" class="ap-stat">
    <div class="ap-stat-icon">👥</div>
    <div class="ap-stat-value"><?= $stats['members'] ?></div>
    <div class="ap-stat-label">Members</div>
  </a>
  <a href="events.php" class="ap-stat">
    <div class="ap-stat-icon">📅</div>
    <div class="ap-stat-value"><?= $stats['events'] ?></div>
    <div class="ap-stat-label">Events</div>
  </a>
  <a href="registrations.php" class="ap-stat">
    <div class="ap-stat-icon">🎟️</div>
    <div class="ap-stat-value"><?= $stats['registrations'] ?></div>
    <div class="ap-stat-label">Registrations</div>
  </a>
  <a href="team.php" class="ap-stat">
    <div class="ap-stat-icon">🏆</div>
    <div class="ap-stat-value"><?= $stats['team'] ?></div>
    <div class="ap-stat-label">Team Members</div>
  </a>
  <a href="sponsors.php" class="ap-stat">
    <div class="ap-stat-icon">🤝</div>
    <div class="ap-stat-value"><?= $stats['sponsors'] ?></div>
    <div class="ap-stat-label">Sponsors</div>
  </a>
  <a href="announcements.php" class="ap-stat">
    <div class="ap-stat-icon">📢</div>
    <div class="ap-stat-value"><?= $stats['announcements'] ?></div>
    <div class="ap-stat-label">Active Announcements</div>
  </a>
  <a href="gallery.php" class="ap-stat">
    <div class="ap-stat-icon">🖼️</div>
    <div class="ap-stat-value"><?= $stats['gallery'] ?></div>
    <div class="ap-stat-label">Gallery Items</div>
  </a>
  <a href="feedback.php" class="ap-stat">
    <div class="ap-stat-icon">💬</div>
    <div class="ap-stat-value"><?= $stats['feedback'] ?></div>
    <div class="ap-stat-label">Feedback</div>
  </a>
</div>

<!-- Quick Actions -->
<div class="ap-card mb-3">
  <div class="ap-card-title"><i class="bi bi-lightning-fill"></i> Quick Actions</div>
  <div class="d-flex flex-wrap gap-2">
    <a href="announcements.php?action=new" class="ap-btn ap-btn-gold"><i class="bi bi-megaphone"></i> New Announcement</a>
    <a href="events.php?action=new" class="ap-btn ap-btn-gold"><i class="bi bi-calendar-plus"></i> New Event</a>
    <a href="team.php?action=new" class="ap-btn ap-btn-outline"><i class="bi bi-person-plus"></i> Add Team Member</a>
    <a href="sponsors.php?action=new" class="ap-btn ap-btn-outline"><i class="bi bi-award"></i> Add Sponsor</a>
    <a href="gallery.php" class="ap-btn ap-btn-outline"><i class="bi bi-image"></i> Upload Gallery</a>
    <a href="settings.php" class="ap-btn ap-btn-outline"><i class="bi bi-gear"></i> Site Settings</a>
    <a href="../index.php" target="_blank" class="ap-btn ap-btn-outline"><i class="bi bi-box-arrow-up-right"></i> View Website</a>
  </div>
</div>

<div class="row g-3">
  <!-- Recent Members -->
  <div class="col-lg-6">
    <div class="ap-card h-100">
      <div class="ap-card-title"><i class="bi bi-people-fill"></i> Recent Members</div>
      <div class="ap-table-wrap">
        <table class="ap-table">
          <thead><tr><th>Name</th><th>Code</th><th>Joined</th></tr></thead>
          <tbody>
            <?php foreach ($recentMembers as $m): ?>
            <tr>
              <td><b><?= htmlspecialchars($m['name'],ENT_QUOTES) ?></b><br><small style="color:var(--ap-muted)"><?= htmlspecialchars($m['email'],ENT_QUOTES) ?></small></td>
              <td><span style="font-family:monospace;color:var(--ap-gold);font-size:.75rem"><?= htmlspecialchars($m['member_code'],ENT_QUOTES) ?></span></td>
              <td style="color:var(--ap-muted);font-size:.75rem"><?= date('M j',strtotime($m['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <a href="members.php" class="ap-btn ap-btn-outline ap-btn-sm mt-3">View All Members →</a>
    </div>
  </div>

  <!-- Recent Registrations -->
  <div class="col-lg-6">
    <div class="ap-card h-100">
      <div class="ap-card-title"><i class="bi bi-ticket-fill"></i> Recent Registrations</div>
      <div class="ap-table-wrap">
        <table class="ap-table">
          <thead><tr><th>Member</th><th>Event</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($recentRegs as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['mname'],ENT_QUOTES) ?></td>
              <td style="font-size:.78rem;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($r['etitle'],ENT_QUOTES) ?></td>
              <td style="color:var(--ap-muted);font-size:.75rem"><?= date('M j',strtotime($r['registered_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <a href="registrations.php" class="ap-btn ap-btn-outline ap-btn-sm mt-3">View All →</a>
    </div>
  </div>

  <!-- Active Announcements -->
  <div class="col-lg-6">
    <div class="ap-card h-100">
      <div class="ap-card-title"><i class="bi bi-megaphone-fill"></i> Active Announcements</div>
      <?php if (empty($activeAnnounce)): ?>
        <p style="color:var(--ap-muted);font-size:.84rem">No announcements yet.</p>
      <?php else: ?>
        <?php foreach ($activeAnnounce as $a): ?>
          <div style="padding:9px 0;border-bottom:1px solid rgba(255,255,255,.05);display:flex;align-items:center;gap:8px">
            <span class="ap-badge ap-badge-<?= ['info'=>'blue','warning'=>'gold','success'=>'green','urgent'=>'red'][$a['type']] ?>"><?= $a['type'] ?></span>
            <span style="font-size:.84rem;flex:1"><?= htmlspecialchars($a['title'],ENT_QUOTES) ?></span>
            <span class="ap-badge <?= $a['is_active']?'ap-badge-green':'ap-badge-grey' ?>"><?= $a['is_active']?'Live':'Off' ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <a href="announcements.php" class="ap-btn ap-btn-outline ap-btn-sm mt-3">Manage Announcements →</a>
    </div>
  </div>

  <!-- Recent Feedback -->
  <div class="col-lg-6">
    <div class="ap-card h-100">
      <div class="ap-card-title"><i class="bi bi-chat-quote-fill"></i> Recent Feedback</div>
      <?php foreach ($recentFb as $f): ?>
        <div style="padding:9px 0;border-bottom:1px solid rgba(255,255,255,.05)">
          <div class="d-flex gap-2 align-items-center mb-1">
            <span class="ap-badge ap-badge-<?= $f['type']==='Complaint'?'red':'blue' ?>"><?= $f['type'] ?></span>
            <span style="font-size:.8rem;color:var(--ap-muted)"><?= htmlspecialchars($f['name']??'Anon',ENT_QUOTES) ?></span>
          </div>
          <div style="font-size:.82rem"><?= htmlspecialchars(substr($f['subject']??'',0,55),ENT_QUOTES) ?>...</div>
        </div>
      <?php endforeach; ?>
      <a href="feedback.php" class="ap-btn ap-btn-outline ap-btn-sm mt-3">View All Feedback →</a>
    </div>
  </div>
</div>

<?php adminShellClose(); ?>
