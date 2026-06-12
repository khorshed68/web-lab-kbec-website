<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

startSession();

// Already logged in → go to dashboard
if (isLoggedIn()) {
    header('Location: member/dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email    = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM `members` WHERE `email` = ? LIMIT 1");
        $stmt->execute([$email]);
        $member = $stmt->fetch();

        if (!$member || !password_verify($password, $member['password_hash'])) {
            $error = 'Invalid email or password.';
        } elseif (!$member['verified']) {
            $error = 'Your account is not verified. Please contact the admin.';
        } else {
            loginMember($member);

            // Remember me: extend session cookie lifetime
            if ($remember) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(), session_id(),
                    time() + 60 * 60 * 24 * 30,
                    $params['path'], $params['domain'],
                    $params['secure'], $params['httponly']
                );
            }

            header('Location: member/dashboard.php');
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
  <title>Login | KBEC</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cinzel:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root { --gold: #c9a84c; --dark: #0a0d14; --dark2: #141926; }
    body { background: var(--dark); color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh;
           display: flex; align-items: center; justify-content: center;
           background-image: radial-gradient(circle at 20% 20%, rgba(0,102,204,.08) 0%, transparent 50%),
                             radial-gradient(circle at 80% 80%, rgba(201,168,76,.06) 0%, transparent 50%); }
    .auth-card { background: var(--dark2); border: 1px solid rgba(255,255,255,.08); border-radius: 20px;
                 padding: 40px; width: 100%; max-width: 440px; box-shadow: 0 40px 80px rgba(0,0,0,.6); }
    .auth-logo { font-family: 'Cinzel', serif; font-size: 1.8rem; color: var(--gold); letter-spacing: .1em; }
    .auth-sub { font-size: .75rem; color: rgba(255,255,255,.4); letter-spacing: .15em; text-transform: uppercase; }
    .auth-title { font-size: 1.3rem; font-weight: 600; margin: 28px 0 6px; }
    .auth-desc { font-size: .85rem; color: rgba(255,255,255,.5); margin-bottom: 24px; }
    .form-label { font-size: .78rem; font-weight: 600; color: rgba(255,255,255,.65); }
    .form-control { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1);
                    color: #fff; border-radius: 9px; padding: 11px 14px; font-size: .9rem; }
    .form-control:focus { background: rgba(255,255,255,.08); border-color: rgba(201,168,76,.5);
                          color: #fff; box-shadow: 0 0 0 3px rgba(201,168,76,.1); }
    .form-control::placeholder { color: rgba(255,255,255,.25); }
    .btn-kbec { background: linear-gradient(135deg, #c9a84c, #a8873a); color: #0a0d14;
                border: none; border-radius: 9px; padding: 12px; font-weight: 700;
                font-size: .9rem; width: 100%; letter-spacing: .03em;
                transition: all .2s; }
    .btn-kbec:hover { background: linear-gradient(135deg, #d4b355, #b8933e); transform: translateY(-1px); }
    .auth-link { color: var(--gold); text-decoration: none; font-weight: 600; }
    .auth-link:hover { color: #d4b355; }
    .alert-dark-danger { background: rgba(231,76,60,.12); border: 1px solid rgba(231,76,60,.3);
                         color: #e74c3c; border-radius: 9px; padding: 12px 14px; font-size: .85rem; }
    .divider { display: flex; align-items: center; gap: 12px; margin: 20px 0;
               color: rgba(255,255,255,.25); font-size: .75rem; }
    .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,.1); }
    .form-check-input { background-color: rgba(255,255,255,.1); border-color: rgba(255,255,255,.2); }
    .form-check-input:checked { background-color: var(--gold); border-color: var(--gold); }
    .form-check-label { font-size: .82rem; color: rgba(255,255,255,.55); }
  </style>
</head>
<body>
  <div class="auth-card">
    <div class="auth-logo">KBEC</div>
    <div class="auth-sub">Executive Committee Portal</div>

    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-desc">Sign in with your KUET member account.</p>

    <?php if ($error): ?>
      <div class="alert-dark-danger mb-3"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrfField() ?>

      <div class="mb-3">
        <label class="form-label">KUET Email Address</label>
        <input type="email" name="email" class="form-control"
               placeholder="you@kuet.ac.bd" required
               value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control"
               placeholder="••••••••" required>
      </div>

      <div class="mb-4 form-check">
        <input type="checkbox" class="form-check-input" id="remember" name="remember">
        <label class="form-check-label" for="remember">Remember me for 30 days</label>
      </div>

      <button type="submit" class="btn-kbec">Sign In</button>
    </form>

    <div class="divider">OR</div>

    <p class="text-center" style="font-size:.85rem; color:rgba(255,255,255,.5)">
      Don't have an account?
      <a href="register.php" class="auth-link">Register here</a>
    </p>
    <p class="text-center mt-2" style="font-size:.82rem;">
      <a href="index.php" class="auth-link" style="font-size:.78rem;opacity:.6">← Back to website</a>
    </p>
  </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
