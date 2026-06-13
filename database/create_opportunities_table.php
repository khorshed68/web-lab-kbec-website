<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();

    // 1. Create table
    $sql = "CREATE TABLE IF NOT EXISTS `opportunities` (
        `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(150) NOT NULL,
        `category` ENUM('internship', 'startup', 'competition', 'scholarship') NOT NULL,
        `deadline` VARCHAR(50) NOT NULL,
        `description` TEXT NOT NULL,
        `meta_1` VARCHAR(50) NOT NULL,
        `meta_2` VARCHAR(50) NOT NULL,
        `meta_3` VARCHAR(50) NOT NULL,
        `link` VARCHAR(255) NOT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $db->exec($sql);
    echo "Opportunities table created successfully.\n";

    // 2. Clear table to prevent duplicates on rerun
    $db->exec("DELETE FROM `opportunities`");
    echo "Cleared any existing opportunities.\n";

    // 3. Seed data
    $opportunities = [
        [
            'title' => 'Product Design Intern - Nova Labs',
            'category' => 'internship',
            'deadline' => 'Apply by Jun 2',
            'description' => 'Work on real product flows, user research, and design systems with a fast-moving startup team.',
            'meta_1' => 'Remote / Hybrid',
            'meta_2' => 'Paid',
            'meta_3' => '3 months',
            'link' => 'mailto:bec@kuet.ac.bd?subject=Internship%20Opportunity%20-%20Nova%20Labs'
        ],
        [
            'title' => 'Growth Associate - FinTech Foundry',
            'category' => 'startup',
            'deadline' => 'Open now',
            'description' => 'Join an early-stage fintech startup to support outreach, customer growth, and founder operations.',
            'meta_1' => 'Full-time',
            'meta_2' => 'Dhaka',
            'meta_3' => 'Startup',
            'link' => 'mailto:bec@kuet.ac.bd?subject=Startup%20Hiring%20-%20FinTech%20Foundry'
        ],
        [
            'title' => 'National Strategy Sprint 2026',
            'category' => 'competition',
            'deadline' => 'Register by Jun 18',
            'description' => 'Compete in a business case challenge focused on market entry, pricing strategy, and presentation.',
            'meta_1' => 'Team of 3-4',
            'meta_2' => 'Prize Pool',
            'meta_3' => 'Online rounds',
            'link' => 'mailto:bec@kuet.ac.bd?subject=Case%20Competition%20-%20National%20Strategy%20Sprint%202026'
        ],
        [
            'title' => 'Future Leaders Scholarship',
            'category' => 'scholarship',
            'deadline' => 'Deadline Jul 5',
            'description' => 'Merit-based scholarship for students with leadership experience, academic performance, and community impact.',
            'meta_1' => 'Undergraduate',
            'meta_2' => 'Partial funding',
            'meta_3' => 'Essay required',
            'link' => 'mailto:bec@kuet.ac.bd?subject=Scholarship%20Opportunity%20-%20Future%20Leaders%20Scholarship'
        ]
    ];

    $stmt = $db->prepare("INSERT INTO `opportunities` (title, category, deadline, description, meta_1, meta_2, meta_3, link) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($opportunities as $o) {
        $stmt->execute([
            $o['title'],
            $o['category'],
            $o['deadline'],
            $o['description'],
            $o['meta_1'],
            $o['meta_2'],
            $o['meta_3'],
            $o['link']
        ]);
        echo "Seeded opportunity: {$o['title']}\n";
    }

    echo "Seeding opportunities completed successfully!\n";
} catch (Exception $ex) {
    echo "ERROR: " . $ex->getMessage() . "\n";
}
