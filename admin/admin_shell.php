<?php
/**
 * KBEC Admin Panel — Shared layout helper
 * Included at top of every admin page.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
startSession();

requireAdminPortal('login.php');

/**
 * Render the full admin shell open tags.
 * @param string $pageTitle  Title shown in <title> and page header
 * @param string $activeNav  Active sidebar key
 */
function adminShellOpen(string $pageTitle = 'Admin', string $activeNav = 'dashboard'): void
{
    $name = htmlspecialchars($_SESSION['member_name'] ?? 'Admin', ENT_QUOTES);
    $csrf = csrfToken();
    $nav  = [
        'dashboard'     => ['Dashboard',       'bi-speedometer2',       'index.php'],
        'announcements' => ['Announcements',   'bi-megaphone',          'announcements.php'],
        'team'          => ['Executive Team',  'bi-people-fill',        'team.php'],
        'events'        => ['Events',          'bi-calendar-event-fill','events.php'],
        'opportunities' => ['Opportunities',   'bi-briefcase-fill',     'opportunities.php'],
        'members'       => ['Members',         'bi-person-badge-fill',  'members.php'],
        'sponsors'      => ['Sponsors',        'bi-award-fill',         'sponsors.php'],
        'gallery'       => ['Gallery',         'bi-images',             'gallery.php'],
        'registrations' => ['Registrations',   'bi-ticket-fill',        'registrations.php'],
        'feedback'      => ['Feedback',        'bi-chat-quote-fill',    'feedback.php'],
        'settings'      => ['Site Settings',   'bi-gear-fill',          'settings.php'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?> | KBEC Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cinzel:wght@600;700;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/admin_panel.css" rel="stylesheet">
</head>
<body class="admin-body">

<!-- Mobile toggle -->
<button class="sidebar-hamburger d-lg-none" id="sidebarToggle">
  <i class="bi bi-list"></i>
</button>
<div class="sidebar-overlay d-lg-none" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="ap-sidebar" id="apSidebar">
  <div class="ap-sidebar-brand">
    <div class="ap-brand-logo">KBEC</div>
    <div class="ap-brand-sub">Control Panel</div>
  </div>

  <div class="ap-sidebar-admin">
    <div class="ap-admin-avatar"><?= strtoupper(substr($_SESSION['member_name']??'A',0,1)) ?></div>
    <div>
      <div class="ap-admin-name"><?= $name ?></div>
      <div class="ap-admin-role"><i class="bi bi-shield-check-fill text-warning"></i> Administrator</div>
    </div>
  </div>

  <nav class="ap-nav">
    <div class="ap-nav-section">Main</div>
    <?php foreach ($nav as $key => [$label, $icon, $href]): ?>
      <?php if ($key === 'registrations'): ?>
        <div class="ap-nav-section">Reports</div>
      <?php endif; ?>
      <?php if ($key === 'settings'): ?>
        <div class="ap-nav-section">Configuration</div>
      <?php endif; ?>
      <a href="<?= $href ?>" class="ap-nav-link <?= $activeNav === $key ? 'active' : '' ?>">
        <i class="bi <?= $icon ?>"></i>
        <span><?= $label ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="ap-sidebar-footer">
    <a href="../index.php" class="ap-footer-link" target="_blank">
      <i class="bi bi-globe"></i> View Website
    </a>
    <a href="logout.php" class="ap-footer-link ap-logout">
      <i class="bi bi-box-arrow-left"></i> Sign Out
    </a>
  </div>
</aside>

<!-- MAIN -->
<div class="ap-main">
  <!-- Top bar -->
  <header class="ap-topbar">
    <div>
      <h1 class="ap-page-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?></h1>
    </div>
    <div class="ap-topbar-right">
      <a href="../index.php" target="_blank" class="ap-topbar-btn" title="View website">
        <i class="bi bi-box-arrow-up-right"></i>
      </a>
      <a href="logout.php" class="ap-topbar-btn ap-topbar-logout" title="Logout">
        <i class="bi bi-box-arrow-left"></i>
      </a>
    </div>
  </header>
  <div class="ap-content">
<?php
}

function adminShellClose(): void { ?>
  </div><!-- /.ap-content -->
</div><!-- /.ap-main -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle (mobile)
const toggle  = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('apSidebar');
const overlay = document.getElementById('sidebarOverlay');
if (toggle) toggle.addEventListener('click', () => {
  sidebar.classList.toggle('open');
  overlay.classList.toggle('active');
});
if (overlay) overlay.addEventListener('click', () => {
  sidebar.classList.remove('open');
  overlay.classList.remove('active');
});

// CSRF global for AJAX
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

// Auto-dismiss alerts
document.querySelectorAll('.admin-alert').forEach(el => {
  setTimeout(() => { el.style.opacity='0'; el.style.transform='translateY(-6px)'; }, 3500);
  setTimeout(() => el.remove(), 4000);
});
</script>
</body>
</html>
<?php
}

/** Flash alert HTML */
function adminAlert(string $msg, string $type = 'success'): string {
    $icons = ['success' => 'check-circle-fill', 'danger' => 'x-circle-fill', 'warning' => 'exclamation-triangle-fill', 'info' => 'info-circle-fill'];
    $icon  = $icons[$type] ?? 'info-circle-fill';
    return "<div class='admin-alert admin-alert-{$type}'><i class='bi bi-{$icon}'></i> " . htmlspecialchars($msg, ENT_QUOTES) . "</div>";
}
