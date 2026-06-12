<?php
/**
 * KBEC Admin Panel Database Extension
 * Run via: http://localhost/kbec/database/extend_db.php
 * Adds: announcements, team_members, sponsors, gallery, site_settings
 */

require_once __DIR__ . '/../config/database.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1','::1'], true)) { http_response_code(403); die('Localhost only.'); }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>KBEC DB Extend</title>';
    echo '<style>body{font-family:monospace;background:#0a0d14;color:#c9a84c;padding:30px;line-height:1.8}.ok{color:#27ae60}.err{color:#e74c3c}</style></head><body>';
    echo '<h2>KBEC – Extend Database</h2>';
}

function out($msg,$t='info'){global $isCli;if($isCli)echo strip_tags($msg)."\n";else echo "<div class='$t'>$msg</div>";}

try {
    $db = getDB();
    $db->exec("USE `kbec_db`");

    // ── site_settings ─────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS `site_settings` (
        `setting_key`   VARCHAR(80)  NOT NULL,
        `setting_value` TEXT         DEFAULT NULL,
        `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    out("Table: site_settings OK",'ok');

    // ── announcements ─────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS `announcements` (
        `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `title`      VARCHAR(200)     NOT NULL,
        `body`       TEXT             DEFAULT NULL,
        `type`       ENUM('info','warning','success','urgent') NOT NULL DEFAULT 'info',
        `link`       VARCHAR(300)     DEFAULT NULL,
        `link_label` VARCHAR(80)      DEFAULT NULL,
        `is_active`  TINYINT(1)       NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    out("Table: announcements OK",'ok');

    // ── team_members ──────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS `team_members` (
        `id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name`        VARCHAR(120)     NOT NULL,
        `position`    VARCHAR(150)     NOT NULL,
        `position_order` INT(11)       NOT NULL DEFAULT 99,
        `department`  VARCHAR(100)     DEFAULT NULL,
        `batch`       VARCHAR(10)      DEFAULT NULL,
        `email`       VARCHAR(150)     DEFAULT NULL,
        `linkedin`    VARCHAR(300)     DEFAULT NULL,
        `image`       VARCHAR(255)     DEFAULT NULL,
        `is_active`   TINYINT(1)       NOT NULL DEFAULT 1,
        `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_order` (`position_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    out("Table: team_members OK",'ok');

    // ── sponsors ──────────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS `sponsors` (
        `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name`       VARCHAR(120)     NOT NULL,
        `logo`       VARCHAR(255)     DEFAULT NULL,
        `website`    VARCHAR(300)     DEFAULT NULL,
        `category`   ENUM('Title','Gold','Silver','Media','General') NOT NULL DEFAULT 'General',
        `sort_order` INT(11)          NOT NULL DEFAULT 99,
        `is_active`  TINYINT(1)       NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_sort` (`sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    out("Table: sponsors OK",'ok');

    // ── gallery ───────────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS `gallery` (
        `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `image_path` VARCHAR(255)     NOT NULL,
        `caption`    VARCHAR(200)     DEFAULT NULL,
        `category`   VARCHAR(80)      DEFAULT 'General',
        `sort_order` INT(11)          NOT NULL DEFAULT 99,
        `created_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    out("Table: gallery OK",'ok');

    // ── Seed default site settings ────────────────────────────────────
    $settings = [
        ['hero_title',       'KUET Business & Entrepreneurship Club'],
        ['hero_subtitle',    'Empowering the next generation of entrepreneurs and business leaders at KUET.'],
        ['hero_cta_primary', 'Explore Events'],
        ['hero_cta_secondary','Join KBEC'],
        ['about_title',      'About KBEC'],
        ['about_body',       'KBEC – the KUET Business & Entrepreneurship Club – is a student-led organization dedicated to fostering entrepreneurship, business acumen, and leadership among KUET students.'],
        ['contact_email',    'kbec@kuet.ac.bd'],
        ['contact_phone',    '+880-XXXXXXXXXX'],
        ['social_facebook',  'https://facebook.com/kbeckuet'],
        ['social_linkedin',  ''],
        ['social_instagram', ''],
        ['footer_text',      '© 2026 KBEC – KUET Business & Entrepreneurship Club. All rights reserved.'],
        ['marquee_text',     'NEXUS 2026 · InnovateTech Fest · TDExKUET · KBEC Summit 2026 · Entrepreneurship Workshop · Case Competition · Leadership Talks'],
    ];

    $ins = $db->prepare("INSERT IGNORE INTO `site_settings` (setting_key, setting_value) VALUES (?,?)");
    foreach ($settings as [$k,$v]) { $ins->execute([$k,$v]); }
    out("Seeded ".count($settings)." site settings",'ok');

    out("\n========================================",'ok');
    out("✓ Database extension COMPLETE!",'ok');
    out("========================================",'ok');

} catch (Throwable $e) {
    out("ERROR: ".$e->getMessage(),'err');
}

if (!$isCli) echo '</body></html>';
