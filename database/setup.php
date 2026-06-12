<?php
/**
 * KBEC Database Setup via PHP CLI
 * Run: php c:\xampp\htdocs\kbec\database\setup.php
 */

$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$pass = '';

echo "Connecting to MySQL at $host:$port...\n";

try {
    // Connect without selecting a DB first
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "Connected! MariaDB/MySQL version: " . $pdo->query("SELECT VERSION()")->fetchColumn() . "\n\n";

    // ---------- DATABASE ----------
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `kbec_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `kbec_db`");
    echo "Database kbec_db ready.\n";

    // ---------- MEMBERS TABLE ----------
    $pdo->exec("DROP TABLE IF EXISTS `event_registrations`");
    $pdo->exec("DROP TABLE IF EXISTS `feedback`");
    $pdo->exec("DROP TABLE IF EXISTS `members`");
    $pdo->exec("DROP TABLE IF EXISTS `events`");

    $pdo->exec("CREATE TABLE `members` (
        `id`              INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `member_code`     VARCHAR(20)      NOT NULL,
        `name`            VARCHAR(120)     NOT NULL,
        `student_id`      VARCHAR(30)      NOT NULL,
        `email`           VARCHAR(150)     NOT NULL,
        `password_hash`   VARCHAR(255)     NOT NULL,
        `department`      VARCHAR(100)     DEFAULT NULL,
        `batch`           VARCHAR(10)      DEFAULT NULL,
        `phone`           VARCHAR(20)      DEFAULT NULL,
        `interest`        VARCHAR(100)     DEFAULT NULL,
        `bio`             TEXT             DEFAULT NULL,
        `profile_image`   VARCHAR(255)     DEFAULT NULL,
        `verified`        TINYINT(1)       NOT NULL DEFAULT 1,
        `role`            ENUM('member','admin') NOT NULL DEFAULT 'member',
        `verify_token`    VARCHAR(100)     DEFAULT NULL,
        `verify_expires`  DATETIME         DEFAULT NULL,
        `created_at`      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_member_code` (`member_code`),
        UNIQUE KEY `uq_student_id`  (`student_id`),
        UNIQUE KEY `uq_email`       (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Table: members OK\n";

    // ---------- EVENTS TABLE ----------
    $pdo->exec("CREATE TABLE `events` (
        `id`                    INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `slug`                  VARCHAR(100)     NOT NULL,
        `title`                 VARCHAR(200)     NOT NULL,
        `type`                  VARCHAR(60)      DEFAULT 'General',
        `description`           TEXT             DEFAULT NULL,
        `location`              VARCHAR(200)     DEFAULT NULL,
        `event_date_start`      DATE             DEFAULT NULL,
        `event_date_end`        DATE             DEFAULT NULL,
        `registration_deadline` DATE             DEFAULT NULL,
        `capacity`              INT(11) UNSIGNED NOT NULL DEFAULT 100,
        `banner`                VARCHAR(255)     DEFAULT NULL,
        `created_at`            TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`            TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Table: events OK\n";

    // ---------- REGISTRATIONS TABLE ----------
    $pdo->exec("CREATE TABLE `event_registrations` (
        `id`            INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `member_id`     INT(11) UNSIGNED NOT NULL,
        `event_id`      INT(11) UNSIGNED NOT NULL,
        `note`          TEXT             DEFAULT NULL,
        `ticket_code`   VARCHAR(60)      DEFAULT NULL,
        `ticket_token`  VARCHAR(100)     DEFAULT NULL,
        `attended_at`   DATETIME         DEFAULT NULL,
        `registered_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_ticket_token` (`ticket_token`),
        UNIQUE KEY `uq_member_event` (`member_id`, `event_id`),
        CONSTRAINT `fk_reg_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_reg_event`  FOREIGN KEY (`event_id`)  REFERENCES `events`  (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Table: event_registrations OK\n";

    // ---------- FEEDBACK TABLE ----------
    $pdo->exec("CREATE TABLE `feedback` (
        `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `type`       ENUM('Suggestion','Complaint') NOT NULL DEFAULT 'Suggestion',
        `name`       VARCHAR(120)  DEFAULT NULL,
        `email`      VARCHAR(150)  DEFAULT NULL,
        `subject`    VARCHAR(200)  DEFAULT NULL,
        `message`    TEXT          NOT NULL,
        `attachment` VARCHAR(255)  DEFAULT NULL,
        `ip`         VARCHAR(45)   DEFAULT NULL,
        `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Table: feedback OK\n";

    // ---------- SEED: ADMIN ----------
    $adminHash = password_hash('Admin@2026!', PASSWORD_BCRYPT, ['cost' => 12]);
    $ins = $pdo->prepare("INSERT IGNORE INTO `members`
        (member_code, name, student_id, email, password_hash, department, batch, verified, role)
        VALUES (?,?,?,?,?,?,?,1,'admin')");
    $ins->execute(['KBEC-ADMIN-0000','KBEC Admin','00-00-0000','admin@kbec-official.org',$adminHash,'Administration','00']);
    echo "Seed: Admin account inserted.\n";

    // ---------- SEED: EVENTS ----------
    $evIns = $pdo->prepare("INSERT IGNORE INTO `events`
        (slug,title,type,description,location,event_date_start,event_date_end,registration_deadline,capacity)
        VALUES (?,?,?,?,?,?,?,?,?)");

    $events = [
        ['nexus-case-challenge-2026','NEXUS National Case Challenge 2026','Case Competition','Inter-university business case competition focused on strategy, analysis, and presentation.','KUET Auditorium','2026-05-16','2026-05-17','2026-05-12',180],
        ['innovate-tech-fest-2026','InnovateTech Fest 2026','Tech Fest','A three-day festival of startup pitches, hackathons, product showcases, and founder talks.','KUET Campus','2026-06-05','2026-06-07','2026-06-01',240],
        ['tdexkuet-ideas-leadership-session','TDExKUET Ideas & Leadership Session','Talk','A curated leadership session featuring inspiring speakers and practical ideas for students.','ECE Building, Room 204','2026-07-10','2026-07-10','2026-07-04',120],
        ['kbec-entrepreneurship-summit-2026','KBEC Entrepreneurship Summit 2026','Summit','Our flagship summit bringing together entrepreneurs, investors, and thought leaders.','KUET Gymnasium','2026-08-20','2026-08-21','2026-08-13',300],
    ];
    foreach ($events as $ev) {
        $evIns->execute($ev);
        echo "Seed: Event '{$ev[1]}' inserted.\n";
    }

    echo "\n========================================\n";
    echo "✓ Database setup COMPLETE!\n";
    echo "Admin login: admin@kbec-official.org / Admin@2026!\n";
    echo "Site URL:    http://localhost/kbec/\n";
    echo "========================================\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
