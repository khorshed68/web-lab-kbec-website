<?php
/**
 * KBEC Admin Login — Dedicated admin-only entry point
 * URL: http://localhost/kbec/admin/login.php
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

startSession();

if (isAdminPortalAuthenticated()) { header('Location: index.php'); exit; }
if (isLoggedIn()) { logoutMember(); }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email    = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your admin credentials.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM `members` WHERE `email` = ? AND `role` = 'admin' LIMIT 1");
        $stmt->execute([$email]);
        $member = $stmt->fetch();

        if (!$member || !password_verify($password, $member['password_hash'])) {
            // Log failed attempt (could extend with rate limiting)
            $error = 'Invalid admin credentials. Access denied.';
        } else {
            loginAdminPortal($member);
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login | KBEC Control Panel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cinzel:wght@600;700;900&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --gold: #c9a84c; --dark: #060810; --dark2: #0d1117; --dark3: #161b27;
      --border: rgba(201,168,76,.18); --text-dim: rgba(255,255,255,.45);
    }
    body {
      background: var(--dark);
      color: #fff;
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
    }
    /* Animated background grid */
    body::before {
      content: '';
      position: fixed; inset: 0;
      background-image:
        linear-gradient(rgba(201,168,76,.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(201,168,76,.04) 1px, transparent 1px);
      background-size: 50px 50px;
      animation: gridDrift 30s linear infinite;
    }
    body::after {
      content: '';
      position: fixed; inset: 0;
      background:
        radial-gradient(ellipse 60% 50% at 20% 30%, rgba(201,168,76,.07) 0%, transparent 60%),
        radial-gradient(ellipse 50% 40% at 80% 70%, rgba(0,102,204,.06) 0%, transparent 60%);
      pointer-events: none;
    }
    @keyframes gridDrift { from { transform: translate(0,0); } to { transform: translate(50px,50px); } }

    .login-wrap {
      position: relative; z-index: 10;
      width: 100%; max-width: 460px;
      padding: 20px;
    }

    /* Shield icon */
    .shield-icon {
      width: 70px; height: 70px;
      background: linear-gradient(135deg, rgba(201,168,76,.15), rgba(201,168,76,.05));
      border: 1px solid var(--border);
      border-radius: 20px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.8rem;
      margin: 0 auto 20px;
      box-shadow: 0 0 40px rgba(201,168,76,.1);
    }

    .login-card {
      background: var(--dark2);
      border: 1px solid var(--border);
      border-radius: 24px;
      padding: 44px 40px;
      box-shadow: 0 40px 100px rgba(0,0,0,.7), 0 0 0 1px rgba(201,168,76,.06);
    }

    .brand { font-family: 'Cinzel', serif; font-size: 2.2rem; font-weight: 900;
              letter-spacing: .12em; color: var(--gold); text-align: center; }
    .brand-sub { font-size: .7rem; letter-spacing: .22em; text-transform: uppercase;
                  color: var(--text-dim); text-align: center; margin-top: 4px; }

    .divider { height: 1px; background: var(--border); margin: 24px 0; }

    .login-title { font-size: 1.15rem; font-weight: 700; color: #fff; margin-bottom: 4px; }
    .login-sub   { font-size: .82rem; color: var(--text-dim); margin-bottom: 28px; }

    .form-group { margin-bottom: 16px; }
    label { display: block; font-size: .76rem; font-weight: 600;
             color: rgba(255,255,255,.6); margin-bottom: 7px; letter-spacing: .04em; }
    .input-wrap { position: relative; }
    .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
                   color: rgba(255,255,255,.3); font-size: .95rem; pointer-events: none; }
    input[type="email"], input[type="password"] {
      width: 100%;
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.1);
      color: #fff;
      border-radius: 11px;
      padding: 13px 14px 13px 42px;
      font-size: .9rem;
      font-family: 'Inter', sans-serif;
      outline: none;
      transition: border-color .2s, background .2s;
    }
    input:focus {
      border-color: rgba(201,168,76,.5);
      background: rgba(201,168,76,.04);
    }
    input::placeholder { color: rgba(255,255,255,.2); }
    .pwd-toggle {
      position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
      color: rgba(255,255,255,.3); cursor: pointer; font-size: .9rem;
      transition: color .2s;
    }
    .pwd-toggle:hover { color: var(--gold); }

    .alert-error {
      background: rgba(231,76,60,.1);
      border: 1px solid rgba(231,76,60,.3);
      color: #ff6b6b;
      border-radius: 11px;
      padding: 12px 16px;
      font-size: .84rem;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .btn-admin {
      width: 100%;
      background: linear-gradient(135deg, #c9a84c, #9a7a2e);
      color: #060810;
      border: none;
      border-radius: 11px;
      padding: 14px;
      font-size: .95rem;
      font-weight: 800;
      font-family: 'Inter', sans-serif;
      letter-spacing: .06em;
      text-transform: uppercase;
      cursor: pointer;
      transition: all .2s;
      margin-top: 6px;
      position: relative;
      overflow: hidden;
    }
    .btn-admin::before {
      content: '';
      position: absolute; inset: 0;
      background: linear-gradient(135deg, rgba(255,255,255,.15), transparent);
      opacity: 0;
      transition: opacity .2s;
    }
    .btn-admin:hover::before { opacity: 1; }
    .btn-admin:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(201,168,76,.3); }
    .btn-admin:active { transform: translateY(0); }

    .back-link {
      display: block; text-align: center; margin-top: 20px;
      color: var(--text-dim); font-size: .8rem; text-decoration: none;
      transition: color .2s;
    }
    .back-link:hover { color: var(--gold); }
    .back-link span { color: var(--gold); }

    .security-notice {
      margin-top: 20px;
      padding: 10px 14px;
      background: rgba(255,255,255,.02);
      border: 1px solid rgba(255,255,255,.06);
      border-radius: 9px;
      font-size: .73rem;
      color: rgba(255,255,255,.3);
      text-align: center;
      line-height: 1.5;
    }

    @keyframes fadeIn { from { opacity:0; transform: translateY(20px); } to { opacity:1; transform: translateY(0); } }
    .login-card { animation: fadeIn .4s ease; }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="shield-icon">🛡️</div>
  <div class="login-card">
    <!-- Brand -->
    <div class="brand">KBEC</div>
    <div class="brand-sub">Control Panel — Administrator Access</div>
    <div class="divider"></div>

    <div class="login-title">Admin Sign In</div>
    <div class="login-sub">This portal is restricted to authorized administrators only.</div>

    <?php if ($error): ?>
    <div class="alert-error">
      <span>⛔</span> <?= htmlspecialchars($error, ENT_QUOTES) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" autocomplete="off">
      <?= csrfField() ?>

      <div class="form-group">
        <label for="email">Administrator Email</label>
        <div class="input-wrap">
          <span class="input-icon">✉</span>
          <input type="email" id="email" name="email" placeholder="admin@kbec-official.org"
                 value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>" required autofocus>
        </div>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-wrap">
          <span class="input-icon">🔒</span>
          <input type="password" id="password" name="password" placeholder="••••••••••" required>
          <span class="pwd-toggle" onclick="togglePwd()">👁</span>
        </div>
      </div>

      <button type="submit" class="btn-admin">
        🔓 &nbsp; Access Control Panel
      </button>
    </form>

    <a href="../index.php" class="back-link">← Back to <span>KBEC Website</span></a>

    <div class="security-notice">
      🔐 This is a secure area. All access attempts are logged.<br>
      Unauthorized access is strictly prohibited.
    </div>
  </div>
</div>

<script>
function togglePwd() {
  const inp = document.getElementById('password');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}
// Shake animation on error
<?php if ($error): ?>
document.querySelector('.login-card').style.animation = 'none';
setTimeout(() => {
  document.querySelector('.login-card').style.animation = 'shake .4s ease';
}, 10);
<?php endif; ?>
</script>
<style>
@keyframes shake {
  0%,100%{transform:translateX(0)} 20%{transform:translateX(-8px)} 40%{transform:translateX(8px)}
  60%{transform:translateX(-5px)} 80%{transform:translateX(5px)}
}
</style>
</body>
</html>
