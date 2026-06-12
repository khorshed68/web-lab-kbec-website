<?php
/**
 * KBEC Database Configuration
 * PDO connection singleton + application constants
 */

// ── Application Constants ────────────────────────────────
define('SITE_URL',        'http://localhost/kbec');
define('UPLOAD_DIR',      __DIR__ . '/../uploads/');
define('AVATAR_DIR',      UPLOAD_DIR . 'avatars/');
define('BANNER_DIR',      UPLOAD_DIR . 'banners/');
define('ATTACH_DIR',      UPLOAD_DIR . 'attachments/');
define('MAX_AVATAR_SIZE', 2 * 1024 * 1024);   // 2 MB
define('MAX_BANNER_SIZE', 5 * 1024 * 1024);   // 5 MB
define('MAX_ATTACH_SIZE', 3 * 1024 * 1024);   // 3 MB
define('KUET_EMAIL_DOMAIN', 'kuet.ac.bd');

// ── Database Credentials ─────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'kbec_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHAR', 'utf8mb4');

/**
 * Returns a singleton PDO connection to kbec_db.
 * Throws PDOException on failure (caught at call site or
 * produces a readable error in development).
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHAR
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In production replace with a generic error page
            http_response_code(500);
            die('<h2>Database connection failed.</h2><p>'
                . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>'
                . '<p>Make sure XAMPP MySQL is running and the <code>kbec_db</code> '
                . 'database has been created by importing <code>database/kbec_db.sql</code>.</p>');
        }
    }

    return $pdo;
}

/**
 * Ensure required upload directories exist.
 */
function ensureUploadDirs(): void
{
    foreach ([UPLOAD_DIR, AVATAR_DIR, BANNER_DIR, ATTACH_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

ensureUploadDirs();
