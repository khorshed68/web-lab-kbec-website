<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

startSession();

if (isLoggedIn()) {
    header('Location: member/dashboard.php');
    exit;
}

$departments = [
    'Computer Science and Engineering',
    'Electrical and Electronic Engineering',
    'Mechanical Engineering',
    'Civil Engineering',
    'Industrial and Production Engineering',
    'Electronics and Communication Engineering',
    'Leather Engineering',
    'Chemistry',
    'Mathematics',
    'Physics',
    'Other',
];
$batches = array_map(fn($y) => (string)$y, range(25, 17));

$error   = '';
$success = '';
$old     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $old = $_POST;

    $name       = trim($_POST['name'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $email      = trim(strtolower($_POST['email'] ?? ''));
    $department = trim($_POST['department'] ?? '');
    $batch      = trim($_POST['batch'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $interest   = trim($_POST['interest'] ?? '');
    $bio        = trim($_POST['bio'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    // ── Validation ──────────────────────────────────────
    if (!$name || !$student_id || !$email || !$department || !$batch || !$password) {
        $error = 'Please fill in all required fields.';
    } elseif (!preg_match('/@kuet\.ac\.bd$/i', $email)) {
        $error = 'Only KUET email addresses (@kuet.ac.bd) are allowed.';
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Password must be at least 8 characters with one uppercase letter and one number.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();

        // Check uniqueness
        $chk = $db->prepare("SELECT COUNT(*) FROM `members` WHERE `email` = ? OR `student_id` = ?");
        $chk->execute([$email, $student_id]);
        if ((int)$chk->fetchColumn() > 0) {
            $error = 'An account with this email or Student ID already exists.';
        } else {
            // Generate sequential member code
            $codeStmt = $db->query("SELECT COUNT(*) FROM `members` WHERE `role` = 'member'");
            $count    = (int)$codeStmt->fetchColumn();
            $code     = sprintf('KBEC-2026-%04d', $count + 1);

            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $ins = $db->prepare("
                INSERT INTO `members`
                  (member_code, name, student_id, email, password_hash,
                   department, batch, phone, interest, bio, verified, role)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'member')
            ");
            $ins->execute([$code, $name, $student_id, $email, $hash,
                           $department, $batch, $phone, $interest, $bio]);

            $newId  = (int)$db->lastInsertId();
            $member = $db->prepare("SELECT * FROM `members` WHERE `id` = ?");
            $member->execute([$newId]);
            $memberRow = $member->fetch();

            loginMember($memberRow);
            header('Location: member/dashboard.php?welcome=1');
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
  <title>Register | KBEC</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cinzel:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root { --gold: #c9a84c; --dark: #0a0d14; --dark2: #141926; }
    body { background: var(--dark); color: #fff; font-family: 'Inter', sans-serif; padding: 40px 0;
           background-image: radial-gradient(circle at 20% 20%, rgba(0,102,204,.08) 0%, transparent 50%),
                             radial-gradient(circle at 80% 80%, rgba(201,168,76,.06) 0%, transparent 50%); }
    .auth-card { background: var(--dark2); border: 1px solid rgba(255,255,255,.08); border-radius: 20px;
                 padding: 40px; width: 100%; max-width: 560px; margin: auto;
                 box-shadow: 0 40px 80px rgba(0,0,0,.6); }
    .auth-logo { font-family: 'Cinzel', serif; font-size: 1.8rem; color: var(--gold); letter-spacing: .1em; }
    .auth-sub  { font-size: .75rem; color: rgba(255,255,255,.4); letter-spacing: .15em; text-transform: uppercase; }
    .auth-title { font-size: 1.3rem; font-weight: 600; margin: 24px 0 6px; }
    .auth-desc  { font-size: .85rem; color: rgba(255,255,255,.5); margin-bottom: 24px; }
    .form-label { font-size: .78rem; font-weight: 600; color: rgba(255,255,255,.65); }
    .form-control, .form-select { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1);
                    color: #fff; border-radius: 9px; padding: 11px 14px; font-size: .88rem; }
    .form-control:focus, .form-select:focus { background: rgba(255,255,255,.08); border-color: rgba(201,168,76,.5);
                          color: #fff; box-shadow: 0 0 0 3px rgba(201,168,76,.1); }
    .form-control::placeholder { color: rgba(255,255,255,.25); }
    .form-select option { background: #1a2035; color: #fff; }
    .btn-kbec { background: linear-gradient(135deg, #c9a84c, #a8873a); color: #0a0d14;
                border: none; border-radius: 9px; padding: 12px; font-weight: 700;
                font-size: .9rem; width: 100%; letter-spacing: .03em; transition: all .2s; }
    .btn-kbec:hover { background: linear-gradient(135deg, #d4b355, #b8933e); transform: translateY(-1px); }
    .auth-link  { color: var(--gold); text-decoration: none; font-weight: 600; }
    .auth-link:hover { color: #d4b355; }
    .alert-dark-danger { background: rgba(231,76,60,.12); border: 1px solid rgba(231,76,60,.3);
                         color: #e74c3c; border-radius: 9px; padding: 12px 14px; font-size: .85rem; margin-bottom: 16px; }
    .pwd-hint { font-size: .72rem; color: rgba(255,255,255,.35); margin-top: 4px; }
    .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media(max-width:480px) { .row-2 { grid-template-columns: 1fr; } .auth-card { padding: 24px; } }
  </style>
</head>
<body>
  <div class="auth-card">
    <div class="auth-logo">KBEC</div>
    <div class="auth-sub">Member Registration</div>

    <h1 class="auth-title">Create your account</h1>
    <p class="auth-desc">Join the KUET Business &amp; Entrepreneurship Club.</p>

    <?php if ($error): ?>
      <div class="alert-dark-danger"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrfField() ?>

      <!-- Full Name -->
      <div class="mb-3">
        <label class="form-label">Full Name <span style="color:#e74c3c">*</span></label>
        <input type="text" name="name" class="form-control" placeholder="Your full name"
               value="<?= htmlspecialchars($old['name'] ?? '', ENT_QUOTES) ?>" required>
      </div>

      <div class="row-2 mb-3">
        <!-- Student ID -->
        <div>
          <label class="form-label">Student ID <span style="color:#e74c3c">*</span></label>
          <input type="text" name="student_id" class="form-control" placeholder="21-01-1234"
                 value="<?= htmlspecialchars($old['student_id'] ?? '', ENT_QUOTES) ?>" required>
        </div>
        <!-- Batch -->
        <div>
          <label class="form-label">Batch <span style="color:#e74c3c">*</span></label>
          <select name="batch" class="form-select" required>
            <option value="">Select batch</option>
            <?php foreach ($batches as $b): ?>
              <option value="<?= e($b) ?>" <?= ($old['batch'] ?? '') == $b ? 'selected' : '' ?>><?= e($b) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- KUET Email -->
      <div class="mb-3">
        <label class="form-label">KUET Email <span style="color:#e74c3c">*</span></label>
        <input type="email" name="email" class="form-control" placeholder="you@kuet.ac.bd"
               value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES) ?>" required>
      </div>

      <!-- Department -->
      <div class="mb-3">
        <label class="form-label">Department <span style="color:#e74c3c">*</span></label>
        <select name="department" class="form-select" required>
          <option value="">Select department</option>
          <?php foreach ($departments as $dept): ?>
            <option value="<?= e($dept) ?>" <?= ($old['department'] ?? '') == $dept ? 'selected' : '' ?>><?= e($dept) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Phone -->
      <div class="mb-3">
        <label class="form-label">Phone Number</label>
        <input type="text" name="phone" class="form-control" placeholder="01XXXXXXXXX"
               value="<?= htmlspecialchars($old['phone'] ?? '', ENT_QUOTES) ?>">
      </div>

      <!-- Interest -->
      <div class="mb-3">
        <label class="form-label">Area of Interest</label>
        <select name="interest" class="form-select">
          <option value="">Select interest</option>
          <?php foreach (['Startup', 'Finance', 'Marketing', 'Technology', 'Social Enterprise', 'Other'] as $i): ?>
            <option value="<?= strtolower(e($i)) ?>" <?= ($old['interest'] ?? '') == strtolower($i) ? 'selected' : '' ?>><?= e($i) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Bio -->
      <div class="mb-3">
        <label class="form-label">Short Bio</label>
        <textarea name="bio" class="form-control" rows="2" placeholder="Tell us about yourself..."><?= htmlspecialchars($old['bio'] ?? '', ENT_QUOTES) ?></textarea>
      </div>

      <div class="row-2 mb-4">
        <!-- Password -->
        <div>
          <label class="form-label">Password <span style="color:#e74c3c">*</span></label>
          <input type="password" name="password" id="pwd" class="form-control" placeholder="••••••••" required>
          <div class="pwd-hint">Min 8 chars, 1 uppercase, 1 number</div>
        </div>
        <!-- Confirm Password -->
        <div>
          <label class="form-label">Confirm Password <span style="color:#e74c3c">*</span></label>
          <input type="password" name="confirm_password" id="cpwd" class="form-control" placeholder="••••••••" required>
        </div>
      </div>

      <button type="submit" class="btn-kbec">Create Account</button>
    </form>

    <p class="text-center mt-4" style="font-size:.85rem; color:rgba(255,255,255,.5)">
      Already have an account?
      <a href="login.php" class="auth-link">Sign in</a>
    </p>
    <p class="text-center" style="font-size:.78rem;">
      <a href="index.php" class="auth-link" style="opacity:.6">← Back to website</a>
    </p>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Real-time password match check
  document.getElementById('cpwd').addEventListener('input', function() {
    const match = this.value === document.getElementById('pwd').value;
    this.style.borderColor = this.value ? (match ? 'rgba(39,174,96,.5)' : 'rgba(231,76,60,.5)') : '';
  });
</script>
</body>
</html>
