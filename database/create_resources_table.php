<?php
/**
 * Migration: Create resources table for Tools, Templates & Guides
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

// Create the resources table
$db->exec("
    CREATE TABLE IF NOT EXISTS `resources` (
        `id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `title`       VARCHAR(150)     NOT NULL,
        `category`    ENUM('template','guide','tool','workshop','youtube') NOT NULL DEFAULT 'template',
        `description` TEXT             NOT NULL,
        `tags`        VARCHAR(200)     NOT NULL DEFAULT '',
        `link`        VARCHAR(500)     NOT NULL DEFAULT '',
        `icon_svg`    TEXT             NULL,
        `sort_order`  INT(11)          NOT NULL DEFAULT 0,
        `is_active`   TINYINT(1)       NOT NULL DEFAULT 1,
        `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "Table `resources` created (or already exists).\n";

// Seed with the 3 default cards from the website
$seeds = [
    [
        'title'       => 'Presentation Slides',
        'category'    => 'template',
        'description' => 'Polished deck structures for pitches, case presentations, workshop recaps, and club showcase sessions.',
        'tags'        => 'Pitch decks,Templates,Editable',
        'link'        => 'mailto:bec@kuet.ac.bd?subject=Resource%20Request%20-%20Presentation%20Slides',
        'sort_order'  => 1,
    ],
    [
        'title'       => 'Business Templates',
        'category'    => 'template',
        'description' => 'Ready-to-use formats for proposals, budgets, market research notes, and team planning documents.',
        'tags'        => 'Proposals,Reports,Planning',
        'link'        => 'mailto:bec@kuet.ac.bd?subject=Resource%20Request%20-%20Business%20Templates',
        'sort_order'  => 2,
    ],
    [
        'title'       => 'Startup Guides',
        'category'    => 'guide',
        'description' => 'Practical guides that cover idea validation, launching a product, and organizing a student-led startup.',
        'tags'        => 'Launch,Validation,Founders',
        'link'        => 'mailto:bec@kuet.ac.bd?subject=Resource%20Request%20-%20Startup%20Guides',
        'sort_order'  => 3,
    ],
];

$chk = $db->query("SELECT COUNT(*) FROM `resources`")->fetchColumn();
if ((int)$chk === 0) {
    $ins = $db->prepare("
        INSERT INTO `resources` (title, category, description, tags, link, sort_order)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    foreach ($seeds as $s) {
        $ins->execute([$s['title'], $s['category'], $s['description'], $s['tags'], $s['link'], $s['sort_order']]);
    }
    echo "Seeded " . count($seeds) . " default resource entries.\n";
} else {
    echo "Table already has data — skipping seed.\n";
}

echo "Done!\n";
