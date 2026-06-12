<?php
/**
 * KBEC Shared Header – Member & Admin pages
 * @param string $pageTitle  Page title shown in <title> and <h1>
 * @param string $activeNav  Which nav item is active: dashboard|profile|password|admin
 */
function renderHeader(string $pageTitle = 'KBEC', string $activeNav = 'dashboard'): void
{
    $member = currentMember();
    $name   = htmlspecialchars($member['name'] ?? 'Member', ENT_QUOTES);
    $code   = htmlspecialchars($member['member_code'] ?? '', ENT_QUOTES);
    $isAdm  = isAdmin();
    $avatar = $member['profile_image'] ?? null;
    $avatarHtml = $avatar
        ? '<img src="' . htmlspecialchars(SITE_URL . '/' . $avatar, ENT_QUOTES) . '" alt="' . $name . '" class="kbec-avatar-img">'
        : '<div class="kbec-avatar-initials">' . strtoupper(substr($member['name'] ?? 'K', 0, 1)) . '</div>';

    $basePath = $isAdm ? '../' : '../';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?> | KBEC</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cinzel:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{--gold:#c9a84c;--dark:#0a0d14;--dark2:#141926;--dark3:#1e2438;--text-muted:rgba(255,255,255,.55)}
    *{box-sizing:border-box}
    body{background:var(--dark);color:#fff;font-family:'Inter',sans-serif;margin:0}
    /* Sidebar */
    .kbec-sidebar{position:fixed;top:0;left:0;width:260px;height:100vh;background:var(--dark2);border-right:1px solid rgba(255,255,255,.07);display:flex;flex-direction:column;z-index:100;overflow-y:auto}
    .kbec-sidebar-logo{padding:24px 20px 16px;border-bottom:1px solid rgba(255,255,255,.07)}
    .kbec-sidebar-logo .brand{font-family:'Cinzel',serif;font-size:1.1rem;color:var(--gold);letter-spacing:.08em}
    .kbec-sidebar-logo .sub{font-size:.7rem;color:var(--text-muted);letter-spacing:.12em;text-transform:uppercase}
    .kbec-sidebar-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;gap:10px}
    .kbec-avatar-img,.kbec-avatar-initials{width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0}
    .kbec-avatar-initials{background:linear-gradient(135deg,#1e3a5f,#0d2340);display:flex;align-items:center;justify-content:center;font-family:'Cinzel',serif;font-size:1rem;font-weight:700;color:var(--gold);border:2px solid rgba(201,168,76,.3)}
    .kbec-user-info .name{font-size:.85rem;font-weight:600;color:#fff;line-height:1.2}
    .kbec-user-info .code{font-size:.65rem;color:var(--gold);letter-spacing:.08em;text-transform:uppercase}
    .kbec-nav{padding:12px 0;flex:1}
    .kbec-nav a{display:flex;align-items:center;gap:10px;padding:11px 20px;color:var(--text-muted);text-decoration:none;font-size:.85rem;font-weight:500;transition:all .2s;border-left:3px solid transparent}
    .kbec-nav a:hover{color:#fff;background:rgba(255,255,255,.05);border-left-color:rgba(201,168,76,.4)}
    .kbec-nav a.active{color:var(--gold);background:rgba(201,168,76,.08);border-left-color:var(--gold)}
    .kbec-nav a i{width:18px;text-align:center}
    .kbec-nav .nav-section{padding:12px 20px 4px;font-size:.62rem;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.3);font-weight:600}
    .kbec-sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,.07)}
    .kbec-sidebar-footer a{color:var(--text-muted);text-decoration:none;font-size:.8rem;display:flex;align-items:center;gap:8px;transition:color .2s}
    .kbec-sidebar-footer a:hover{color:#e74c3c}
    /* Main content */
    .kbec-main{margin-left:260px;min-height:100vh;padding:32px}
    .kbec-page-title{font-family:'Cinzel',serif;font-size:1.5rem;color:#fff;margin-bottom:8px}
    .kbec-page-sub{color:var(--text-muted);font-size:.85rem;margin-bottom:28px}
    /* Cards */
    .kbec-card{background:var(--dark2);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:24px}
    .kbec-stat-card{background:linear-gradient(135deg,var(--dark2),var(--dark3));border:1px solid rgba(201,168,76,.12);border-radius:14px;padding:20px;transition:transform .2s}
    .kbec-stat-card:hover{transform:translateY(-2px)}
    .kbec-stat-value{font-size:2rem;font-weight:700;color:var(--gold)}
    .kbec-stat-label{font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.1em}
    /* Tables */
    .kbec-table{width:100%;border-collapse:collapse}
    .kbec-table th{background:rgba(201,168,76,.08);color:var(--gold);font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;padding:10px 14px;text-align:left;border-bottom:1px solid rgba(201,168,76,.2)}
    .kbec-table td{padding:11px 14px;border-bottom:1px solid rgba(255,255,255,.05);font-size:.85rem;color:rgba(255,255,255,.8);vertical-align:middle}
    .kbec-table tr:hover td{background:rgba(255,255,255,.03)}
    /* Badges */
    .badge-gold{background:rgba(201,168,76,.15);color:var(--gold);border:1px solid rgba(201,168,76,.3);padding:3px 8px;border-radius:999px;font-size:.65rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
    .badge-green{background:rgba(39,174,96,.12);color:#27ae60;border:1px solid rgba(39,174,96,.3);padding:3px 8px;border-radius:999px;font-size:.65rem;font-weight:700}
    .badge-red{background:rgba(231,76,60,.12);color:#e74c3c;border:1px solid rgba(231,76,60,.3);padding:3px 8px;border-radius:999px;font-size:.65rem;font-weight:700}
    .badge-blue{background:rgba(52,152,219,.12);color:#3498db;border:1px solid rgba(52,152,219,.3);padding:3px 8px;border-radius:999px;font-size:.65rem;font-weight:700}
    /* Forms */
    .kbec-label{font-size:.8rem;font-weight:600;color:rgba(255,255,255,.7);margin-bottom:6px;display:block}
    .kbec-input{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#fff;border-radius:9px;padding:11px 14px;width:100%;font-size:.9rem;outline:none;transition:border-color .2s}
    .kbec-input:focus{border-color:rgba(201,168,76,.5);background:rgba(255,255,255,.07)}
    .kbec-input::placeholder{color:rgba(255,255,255,.3)}
    .kbec-input option{background:#1a2035;color:#fff}
    .kbec-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border-radius:9px;font-size:.85rem;font-weight:600;cursor:pointer;border:none;transition:all .2s;letter-spacing:.02em}
    .kbec-btn-gold{background:linear-gradient(135deg,#c9a84c,#a8873a);color:#0a0d14}
    .kbec-btn-gold:hover{background:linear-gradient(135deg,#d4b355,#b8933e);transform:translateY(-1px)}
    .kbec-btn-outline{background:transparent;border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.7)}
    .kbec-btn-outline:hover{background:rgba(255,255,255,.06);color:#fff}
    .kbec-btn-danger{background:rgba(231,76,60,.15);border:1px solid rgba(231,76,60,.3);color:#e74c3c}
    .kbec-btn-danger:hover{background:rgba(231,76,60,.25)}
    /* Alert messages */
    .kbec-alert{padding:12px 16px;border-radius:9px;font-size:.85rem;margin-bottom:16px}
    .kbec-alert-success{background:rgba(39,174,96,.12);border:1px solid rgba(39,174,96,.3);color:#27ae60}
    .kbec-alert-error{background:rgba(231,76,60,.12);border:1px solid rgba(231,76,60,.3);color:#e74c3c}
    /* Mobile sidebar */
    @media(max-width:900px){.kbec-sidebar{transform:translateX(-100%)}.kbec-main{margin-left:0;padding:16px}}
  </style>
</head>
<body>
<!-- Sidebar -->
<aside class="kbec-sidebar">
  <div class="kbec-sidebar-logo">
    <div class="brand">KBEC</div>
    <div class="sub">Business & Entrepreneurship Club</div>
  </div>
  <div class="kbec-sidebar-user">
    <?= $avatarHtml ?>
    <div class="kbec-user-info">
      <div class="name"><?= $name ?></div>
      <div class="code"><?= $code ?></div>
    </div>
  </div>
  <nav class="kbec-nav">
    <div class="nav-section">Member</div>
    <a href="<?= $isAdm ? '../member/dashboard.php' : 'dashboard.php' ?>" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">
      <i class="bi bi-grid-1x2"></i> Dashboard
    </a>
    <a href="<?= $isAdm ? '../member/profile.php' : 'profile.php' ?>" class="<?= $activeNav === 'profile' ? 'active' : '' ?>">
      <i class="bi bi-person-circle"></i> Edit Profile
    </a>
    <a href="<?= $isAdm ? '../member/password.php' : 'password.php' ?>" class="<?= $activeNav === 'password' ? 'active' : '' ?>">
      <i class="bi bi-shield-lock"></i> Change Password
    </a>
    <?php if ($isAdm): ?>
    <div class="nav-section">Admin</div>
    <a href="<?= ($activeNav === 'admin_dashboard' ? '' : '../') ?>admin/login.php" class="<?= $activeNav === 'admin_dashboard' ? 'active' : '' ?>">
      <i class="bi bi-speedometer2"></i> Admin Dashboard
    </a>
    <a href="<?= ($activeNav === 'admin_members' ? '' : '../') ?>admin/members.php" class="<?= $activeNav === 'admin_members' ? 'active' : '' ?>">
      <i class="bi bi-people"></i> Members
    </a>
    <a href="<?= ($activeNav === 'admin_events' ? '' : '../') ?>admin/events.php" class="<?= $activeNav === 'admin_events' ? 'active' : '' ?>">
      <i class="bi bi-calendar-event"></i> Events
    </a>
    <a href="<?= ($activeNav === 'admin_regs' ? '' : '../') ?>admin/registrations.php" class="<?= $activeNav === 'admin_regs' ? 'active' : '' ?>">
      <i class="bi bi-ticket-perforated"></i> Registrations
    </a>
    <a href="<?= ($activeNav === 'admin_feedback' ? '' : '../') ?>admin/feedback.php" class="<?= $activeNav === 'admin_feedback' ? 'active' : '' ?>">
      <i class="bi bi-chat-square-text"></i> Feedback
    </a>
    <?php endif; ?>
    <div class="nav-section">Site</div>
    <a href="<?= $isAdm ? '../index.php' : '../index.php' ?>">
      <i class="bi bi-globe"></i> Back to Website
    </a>
  </nav>
  <div class="kbec-sidebar-footer">
    <a href="<?= $isAdm ? '../logout.php' : '../logout.php' ?>">
      <i class="bi bi-box-arrow-left"></i> Sign Out
    </a>
  </div>
</aside>
<!-- Main -->
<main class="kbec-main">
<?php
}
?>
