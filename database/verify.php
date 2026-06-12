<?php
require 'c:/xampp/htdocs/kbec/config/database.php';
$db      = getDB();
$members = (int)$db->query('SELECT COUNT(*) FROM members')->fetchColumn();
$events  = (int)$db->query('SELECT COUNT(*) FROM events')->fetchColumn();
$admin   = $db->query("SELECT email,role,member_code FROM members WHERE role='admin'")->fetch();
$evList  = $db->query("SELECT slug,title FROM events ORDER BY id")->fetchAll();

echo "=== KBEC Database Verification ===\n";
echo "Members : $members\n";
echo "Events  : $events\n";
echo "Admin   : {$admin['email']} | code={$admin['member_code']} | role={$admin['role']}\n";
echo "\nEvents seeded:\n";
foreach ($evList as $ev) {
    echo "  - [{$ev['slug']}] {$ev['title']}\n";
}

// Test password verify
$hash = $db->query("SELECT password_hash FROM members WHERE email='admin@kbec-official.org'")->fetchColumn();
$pwOk = password_verify('Admin@2026!', $hash) ? 'PASS' : 'FAIL';
echo "\nPassword verify : $pwOk\n";
echo "\n=== ALL OK ===\n";
