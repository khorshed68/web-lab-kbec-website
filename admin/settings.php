<?php
require_once __DIR__ . '/admin_shell.php';
$db  = getDB();
$msg = ''; $msgType = 'success';

// ── Save settings ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $upsert = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $saved  = 0;
    foreach ($_POST as $key => $value) {
        if ($key === 'csrf_token' || $key === '_method') continue;
        $upsert->execute([preg_replace('/[^a-z0-9_]/','',$key), trim($value)]);
        $saved++;
    }
    // Handle hero image upload
    if (!empty($_FILES['hero_image']['tmp_name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($_FILES['hero_image']['tmp_name']);
        if (in_array($mime,['image/jpeg','image/png','image/webp'])) {
            $ext   = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime];
            $fname = 'hero_'.time().'.'.$ext;
            move_uploaded_file($_FILES['hero_image']['tmp_name'], UPLOAD_DIR.$fname);
            $upsert->execute(['hero_bg_image','uploads/'.$fname]);
        }
    }
    $msg = "Settings saved ({$saved} values updated).";
}

// Load all settings as key→value
$rows = $db->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
$S    = [];
foreach ($rows as $r) $S[$r['setting_key']] = $r['setting_value'];
$get  = fn(string $k, string $d='') => htmlspecialchars($S[$k] ?? $d, ENT_QUOTES);

adminShellOpen('Site Settings', 'settings');
?>
<?php if ($msg) echo adminAlert($msg, $msgType); ?>

<form method="POST" enctype="multipart/form-data">
  <?= csrfField() ?>

  <!-- Hero Section -->
  <div class="ap-card mb-3">
    <div class="ap-card-title"><i class="bi bi-easel-fill"></i> Hero / Banner Section</div>
    <div class="ap-setting-row">
      <div class="ap-setting-label">Hero Main Title</div>
      <div class="ap-setting-control"><input type="text" name="hero_title" class="ap-input" value="<?= $get('hero_title','KUET Business & Entrepreneurship Club') ?>"></div>
    </div>
    <div class="ap-setting-row">
      <div class="ap-setting-label">Hero Subtitle</div>
      <div class="ap-setting-control"><textarea name="hero_subtitle" class="ap-input" rows="2"><?= $get('hero_subtitle') ?></textarea></div>
    </div>
    <div class="ap-setting-row">
      <div class="ap-setting-label">Primary CTA Button</div>
      <div class="ap-setting-control"><input type="text" name="hero_cta_primary" class="ap-input" value="<?= $get('hero_cta_primary','Explore Events') ?>"></div>
    </div>
    <div class="ap-setting-row">
      <div class="ap-setting-label">Secondary CTA Button</div>
      <div class="ap-setting-control"><input type="text" name="hero_cta_secondary" class="ap-input" value="<?= $get('hero_cta_secondary','Join KBEC') ?>"></div>
    </div>
    <div class="ap-setting-row">
      <div class="ap-setting-label">Hero Background Image</div>
      <div class="ap-setting-control">
        <?php if (!empty($S['hero_bg_image'])): ?>
          <img src="<?= htmlspecialchars('../'.$S['hero_bg_image'],ENT_QUOTES) ?>" class="ap-img-preview-lg mb-2" alt="hero">
        <?php endif; ?>
        <input type="file" name="hero_image" class="ap-input" accept="image/jpeg,image/png,image/webp" style="padding:8px">
      </div>
    </div>
  </div>

  <!-- Marquee Ticker -->
  <div class="ap-card mb-3">
    <div class="ap-card-title"><i class="bi bi-lightning-fill"></i> Marquee / News Ticker</div>
    <div class="ap-setting-row">
      <div class="ap-setting-label">Ticker Text</div>
      <div class="ap-setting-control">
        <textarea name="marquee_text" class="ap-input" rows="2" placeholder="Separate items with ·"><?= $get('marquee_text') ?></textarea>
        <div style="font-size:.72rem;color:var(--ap-muted);margin-top:4px">Tip: Use the · (middle dot) character to separate ticker items.</div>
      </div>
    </div>
  </div>

  <!-- About Section -->
  <div class="ap-card mb-3">
    <div class="ap-card-title"><i class="bi bi-info-circle-fill"></i> About Section</div>
    <div class="ap-setting-row">
      <div class="ap-setting-label">Section Title</div>
      <div class="ap-setting-control"><input type="text" name="about_title" class="ap-input" value="<?= $get('about_title','About KBEC') ?>"></div>
    </div>
    <div class="ap-setting-row">
      <div class="ap-setting-label">About Body Text</div>
      <div class="ap-setting-control"><textarea name="about_body" class="ap-input" rows="5"><?= $get('about_body') ?></textarea></div>
    </div>
  </div>

  <!-- Contact & Social -->
  <div class="ap-card mb-3">
    <div class="ap-card-title"><i class="bi bi-envelope-fill"></i> Contact & Social Links</div>
    <div class="ap-setting-row">
      <div class="ap-setting-label">Contact Email</div>
      <div class="ap-setting-control"><input type="email" name="contact_email" class="ap-input" value="<?= $get('contact_email') ?>"></div>
    </div>
    <div class="ap-setting-row">
      <div class="ap-setting-label">Contact Phone</div>
      <div class="ap-setting-control"><input type="text" name="contact_phone" class="ap-input" value="<?= $get('contact_phone') ?>"></div>
    </div>
    <div class="ap-setting-row">
      <div class="ap-setting-label">Facebook URL</div>
      <div class="ap-setting-control"><input type="url" name="social_facebook" class="ap-input" placeholder="https://facebook.com/…" value="<?= $get('social_facebook') ?>"></div>
    </div>
    <div class="ap-setting-row">
      <div class="ap-setting-label">LinkedIn URL</div>
      <div class="ap-setting-control"><input type="url" name="social_linkedin" class="ap-input" value="<?= $get('social_linkedin') ?>"></div>
    </div>
    <div class="ap-setting-row">
      <div class="ap-setting-label">Instagram URL</div>
      <div class="ap-setting-control"><input type="url" name="social_instagram" class="ap-input" value="<?= $get('social_instagram') ?>"></div>
    </div>
  </div>

  <!-- Footer -->
  <div class="ap-card mb-3">
    <div class="ap-card-title"><i class="bi bi-file-earmark-text-fill"></i> Footer</div>
    <div class="ap-setting-row">
      <div class="ap-setting-label">Footer Text / Copyright</div>
      <div class="ap-setting-control"><textarea name="footer_text" class="ap-input" rows="2"><?= $get('footer_text') ?></textarea></div>
    </div>
  </div>

  <!-- Admin Password Change -->
  <div class="ap-card mb-4">
    <div class="ap-card-title"><i class="bi bi-shield-lock-fill"></i> Change Admin Password</div>
    <div class="ap-setting-row">
      <div class="ap-setting-label">New Password</div>
      <div class="ap-setting-control">
        <input type="password" id="newAdminPwd" class="ap-input" placeholder="Leave blank to keep current" style="max-width:300px">
        <div style="font-size:.72rem;color:var(--ap-muted);margin-top:4px">Min 8 chars, 1 uppercase, 1 number.</div>
      </div>
    </div>
    <div class="ap-setting-row">
      <div class="ap-setting-label">Confirm Password</div>
      <div class="ap-setting-control">
        <input type="password" id="confirmAdminPwd" class="ap-input" placeholder="Confirm new password" style="max-width:300px">
        <div id="pwdMatchMsg" style="font-size:.72rem;margin-top:4px"></div>
      </div>
    </div>
    <div class="ap-setting-row">
      <div class="ap-setting-label"></div>
      <div class="ap-setting-control">
        <button type="button" id="changePwdBtn" class="ap-btn ap-btn-outline"><i class="bi bi-key-fill"></i> Update Password</button>
        <span id="pwdStatus" style="font-size:.8rem;margin-left:10px"></span>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="ap-btn ap-btn-gold"><i class="bi bi-floppy-fill"></i> Save All Settings</button>
    <a href="index.php" class="ap-btn ap-btn-outline">Cancel</a>
    <a href="../index.php" target="_blank" class="ap-btn ap-btn-outline"><i class="bi bi-eye"></i> Preview Website</a>
  </div>
</form>

<script>
// Password match check
document.getElementById('confirmAdminPwd')?.addEventListener('input', function() {
  const np  = document.getElementById('newAdminPwd').value;
  const msg = document.getElementById('pwdMatchMsg');
  if (!this.value) { msg.textContent=''; return; }
  if (np === this.value) { msg.style.color='#4cde8a'; msg.textContent='✓ Passwords match'; }
  else { msg.style.color='#ff8080'; msg.textContent='✗ Do not match'; }
});

// AJAX password change
document.getElementById('changePwdBtn')?.addEventListener('click', async () => {
  const np = document.getElementById('newAdminPwd').value;
  const cp = document.getElementById('confirmAdminPwd').value;
  const st = document.getElementById('pwdStatus');
  if (!np) { st.style.color='#ff8080'; st.textContent='Enter a new password.'; return; }
  if (np !== cp) { st.style.color='#ff8080'; st.textContent='Passwords do not match.'; return; }
  try {
    const r = await fetch('../api/change_password.php', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token': CSRF_TOKEN},
      body: JSON.stringify({ current_password: prompt('Enter your CURRENT admin password to confirm:'), new_password: np, confirm_password: cp })
    });
    const d = await r.json();
    st.style.color = d.ok ? '#4cde8a' : '#ff8080';
    st.textContent = d.message;
  } catch { st.textContent='Error.'; }
});
</script>
<?php adminShellClose(); ?>
