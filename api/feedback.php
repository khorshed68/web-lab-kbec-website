<?php
/**
 * API: POST /api/feedback.php
 * Handles contact / feedback form submissions (multipart or JSON).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $ip   = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ip   = trim(explode(',', $ip)[0]);
    $db   = getDB();

    // ── Rate limit: 1 per IP per 60 seconds ─────────────────────────
    $rlStmt = $db->prepare("
        SELECT COUNT(*) FROM `feedback`
        WHERE `ip` = ? AND `created_at` > DATE_SUB(NOW(), INTERVAL 60 SECOND)
    ");
    $rlStmt->execute([$ip]);
    if ((int)$rlStmt->fetchColumn() > 0) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'message' => 'Too many submissions. Please wait a minute.']);
        exit;
    }

    // ── Input (multipart form or JSON) ───────────────────────────────
    $isMultipart = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'multipart');
    if ($isMultipart) {
        $type    = trim($_POST['type']    ?? '');
        $name    = trim($_POST['name']    ?? '');
        $email   = trim($_POST['email']   ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $consent = $_POST['consent']      ?? '';
    } else {
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $type    = trim($body['type']    ?? '');
        $name    = trim($body['name']    ?? '');
        $email   = trim($body['email']   ?? '');
        $subject = trim($body['subject'] ?? '');
        $message = trim($body['message'] ?? '');
        $consent = $body['consent']      ?? '';
    }

    // ── Validation ───────────────────────────────────────────────────
    if (!in_array($type, ['Suggestion', 'Complaint'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid feedback type.']);
        exit;
    }
    if (!$subject || strlen($subject) > 200) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Subject is required (max 200 characters).']);
        exit;
    }
    if (!$message || strlen($message) < 10) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Message must be at least 10 characters.']);
        exit;
    }
    if (empty($consent) || $consent === 'false') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Consent is required.']);
        exit;
    }

    // ── File attachment (optional) ───────────────────────────────────
    $attachPath = null;
    if (!empty($_FILES['attachment']['tmp_name'])) {
        $file     = $_FILES['attachment'];
        $allowed  = ['image/jpeg', 'image/png', 'application/pdf'];
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Only JPG, PNG, and PDF attachments are allowed.']);
            exit;
        }
        if ($file['size'] > MAX_ATTACH_SIZE) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Attachment must be under 3 MB.']);
            exit;
        }

        $ext        = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeName   = bin2hex(random_bytes(12)) . '.' . strtolower($ext);
        $destPath   = ATTACH_DIR . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new RuntimeException('File upload failed.');
        }
        $attachPath = 'uploads/attachments/' . $safeName;
    }

    // ── Insert ───────────────────────────────────────────────────────
    $ins = $db->prepare("
        INSERT INTO `feedback` (type, name, email, subject, message, attachment, ip)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([$type, $name, $email, $subject, $message, $attachPath, $ip]);

    echo json_encode(['ok' => true, 'id' => (int)$db->lastInsertId()]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
