<?php
/**
 * KBEC Data Migration Script
 * Migrates data from JSON flat-files → MySQL kbec_db
 *
 * Run ONCE via browser: http://localhost/kbec/database/migrate.php
 * Or via CLI:           php migrate.php
 *
 * ⚠ Delete this file after running!
 */

require_once __DIR__ . '/../config/database.php';

// ── Security: only allow CLI or localhost ─────────────────
$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remoteIp, ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        die('Migration script can only be run from localhost.');
    }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>KBEC Migration</title>';
    echo '<style>body{font-family:monospace;background:#0a0d14;color:#c9a84c;padding:30px;line-height:1.7}';
    echo '.ok{color:#27ae60}.err{color:#e74c3c}.info{color:#3498db}.section{color:#fff;font-size:1.1em;border-bottom:1px solid rgba(255,255,255,.1);padding-bottom:6px;margin:20px 0 10px}</style></head><body>';
    echo '<h2>KBEC Data Migration</h2>';
}

function out(string $msg, string $type = 'info'): void {
    global $isCli;
    if ($isCli) {
        echo strip_tags($msg) . "\n";
    } else {
        $cls = match($type) { 'ok' => 'ok', 'err' => 'err', default => 'info' };
        echo "<div class='$cls'>$msg</div>\n";
        flush();
    }
}

function section(string $title): void {
    global $isCli;
    if ($isCli) echo "\n=== $title ===\n";
    else echo "<div class='section'>$title</div>";
}

$db = getDB();

// ── Data paths ────────────────────────────────────────────
$dataDir  = __DIR__ . '/../data/';
$membersFile = $dataDir . 'members.json';
$eventsFile  = $dataDir . 'events.json';
$regsFile    = $dataDir . 'event-registrations.json';
$subsFile    = $dataDir . 'submissions.json';

// ── MEMBERS ───────────────────────────────────────────────
section('Migrating Members');
if (!file_exists($membersFile)) {
    out('members.json not found — skipping.', 'err');
} else {
    $members    = json_decode(file_get_contents($membersFile), true) ?? [];
    $skipped    = 0; $imported = 0;

    foreach ($members as $m) {
        // Check already exists
        $chk = $db->prepare("SELECT id FROM `members` WHERE email=? OR student_id=?");
        $chk->execute([$m['email'], $m['studentId']]);
        if ($chk->fetchColumn()) { $skipped++; continue; }

        // Old scrypt hashes are incompatible with PHP bcrypt.
        // Assign a temporary password — members must reset via admin.
        $tmpHash = password_hash('KbecReset2026!', PASSWORD_BCRYPT, ['cost'=>12]);

        // Preserve old member code format as-is
        $code = $m['memberCode'] ?? ('KBEC-2026-' . strtoupper(substr(bin2hex(random_bytes(3)),0,4)));

        $ins = $db->prepare("
            INSERT IGNORE INTO `members`
              (member_code, name, student_id, email, password_hash,
               department, batch, phone, interest, bio, verified, role, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $ins->execute([
            $code,
            $m['name']        ?? 'Unknown',
            $m['studentId']   ?? ('migrated-' . substr(md5($m['email']),0,8)),
            $m['email'],
            $tmpHash,
            $m['department']  ?? null,
            $m['batch']       ?? null,
            $m['phone']       ?? null,
            $m['interest']    ?? null,
            $m['bio']         ?? null,
            $m['verified']    ? 1 : 0,
            'member',
            $m['createdAt']   ?? date('Y-m-d H:i:s'),
        ]);
        $imported++;
        out("✓ Imported: {$m['name']} ({$m['email']})", 'ok');
    }
    out("Members — Imported: $imported | Skipped (already exist): $skipped");
}

// ── EVENTS ────────────────────────────────────────────────
section('Migrating Events');
if (!file_exists($eventsFile)) {
    out('events.json not found — skipping.', 'err');
} else {
    $events   = json_decode(file_get_contents($eventsFile), true) ?? [];
    $imported = 0; $skipped = 0;

    foreach ($events as $ev) {
        $chk = $db->prepare("SELECT id FROM `events` WHERE slug=?");
        $chk->execute([$ev['id']]);
        if ($chk->fetchColumn()) { $skipped++; continue; }

        $ins = $db->prepare("
            INSERT IGNORE INTO `events`
              (slug, title, type, description, location,
               event_date_start, event_date_end, registration_deadline, capacity)
            VALUES (?,?,?,?,?,?,?,?,?)
        ");
        $ins->execute([
            $ev['id'],
            $ev['title']    ?? 'Untitled',
            $ev['type']     ?? 'General',
            $ev['summary']  ?? null,
            $ev['venue']    ?? null,
            $ev['start']    ?? null,
            $ev['end']      ?? null,
            $ev['deadline'] ?? null,
            $ev['capacity'] ?? 100,
        ]);
        $imported++;
        out("✓ Event: {$ev['title']}", 'ok');
    }
    out("Events — Imported: $imported | Skipped: $skipped");
}

// ── EVENT REGISTRATIONS ───────────────────────────────────
section('Migrating Event Registrations');
if (!file_exists($regsFile)) {
    out('event-registrations.json not found — skipping.', 'err');
} else {
    $regs = json_decode(file_get_contents($regsFile), true) ?? [];
    $imported = 0; $skipped = 0; $notFound = 0;

    foreach ($regs as $r) {
        // Find member by email
        $mStmt = $db->prepare("SELECT id FROM `members` WHERE email=?");
        $mStmt->execute([$r['memberEmail']]);
        $memberId = $mStmt->fetchColumn();

        // Find event by slug
        $eStmt = $db->prepare("SELECT id FROM `events` WHERE slug=?");
        $eStmt->execute([$r['eventId']]);
        $eventId = $eStmt->fetchColumn();

        if (!$memberId || !$eventId) { $notFound++; continue; }

        $chk = $db->prepare("SELECT id FROM `event_registrations` WHERE member_id=? AND event_id=?");
        $chk->execute([$memberId, $eventId]);
        if ($chk->fetchColumn()) { $skipped++; continue; }

        $ins = $db->prepare("
            INSERT IGNORE INTO `event_registrations`
              (member_id, event_id, note, ticket_code, ticket_token, attended_at, registered_at)
            VALUES (?,?,?,?,?,?,?)
        ");
        $ins->execute([
            $memberId, $eventId,
            $r['note']          ?? null,
            $r['ticketCode']    ?? null,
            $r['ticketToken']   ?? bin2hex(random_bytes(20)),
            $r['attendedAt']    ?? null,
            $r['registeredAt']  ?? date('Y-m-d H:i:s'),
        ]);
        $imported++;
        out("✓ Registration: {$r['memberName']} → {$r['eventId']}", 'ok');
    }
    out("Registrations — Imported: $imported | Skipped: $skipped | Not found: $notFound");
}

// ── SUBMISSIONS (feedback) ────────────────────────────────
section('Migrating Feedback/Submissions');
if (!file_exists($subsFile)) {
    out('submissions.json not found — skipping.', 'err');
} else {
    $subs = json_decode(file_get_contents($subsFile), true) ?? [];
    $imported = 0;
    foreach ($subs as $s) {
        $ins = $db->prepare("
            INSERT INTO `feedback` (type, name, email, subject, message, ip, created_at)
            VALUES (?,?,?,?,?,?,?)
        ");
        $type = in_array($s['type'] ?? '', ['Suggestion','Complaint']) ? $s['type'] : 'Suggestion';
        $ins->execute([
            $type,
            $s['name']      ?? null,
            $s['email']     ?? null,
            $s['subject']   ?? 'No subject',
            $s['message']   ?? '',
            $s['ip']        ?? null,
            $s['createdAt'] ?? date('Y-m-d H:i:s'),
        ]);
        $imported++;
    }
    out("Feedback — Imported: $imported", 'ok');
}

section('Migration Complete');
out('<b>⚠ IMPORTANT:</b> Migrated members have a temporary password: <code>KbecReset2026!</code><br>Ask them to change it after logging in.', 'err');
out('<b>Delete this file</b> (migrate.php) after confirming all data is correct!', 'err');

if (!$isCli) echo '</body></html>';
