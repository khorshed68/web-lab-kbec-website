<?php
/**
 * API: POST /api/upload_avatar.php (multipart/form-data, field: avatar)
 * Uploads and saves a member profile picture.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

startSession();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'message'=>'Method not allowed.']); exit; }
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['ok'=>false,'message'=>'Not logged in.']); exit; }

verifyCsrf();

try {
    if (empty($_FILES['avatar']['tmp_name'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'No file uploaded.']);
        exit;
    }

    $file    = $_FILES['avatar'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $extMap  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    // Validate MIME via finfo (not just extension)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Only JPG, PNG, and WebP images are allowed.']);
        exit;
    }
    if ($file['size'] > MAX_AVATAR_SIZE) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Avatar must be under 2 MB.']);
        exit;
    }

    $memberId = (int)$_SESSION['member_id'];
    $ext      = $extMap[$mimeType];
    $filename = 'avatar_' . $memberId . '_' . time() . '.' . $ext;
    $destPath = AVATAR_DIR . $filename;

    // Delete old avatar
    $db      = getDB();
    $old     = $db->prepare("SELECT profile_image FROM `members` WHERE id = ?");
    $old->execute([$memberId]);
    $oldImg  = $old->fetchColumn();
    if ($oldImg && file_exists(__DIR__ . '/../' . $oldImg)) {
        @unlink(__DIR__ . '/../' . $oldImg);
    }

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Failed to save uploaded file.');
    }

    $relativePath = 'uploads/avatars/' . $filename;
    $upd = $db->prepare("UPDATE `members` SET `profile_image` = ? WHERE `id` = ?");
    $upd->execute([$relativePath, $memberId]);

    $_SESSION['member_avatar'] = $relativePath;

    echo json_encode([
        'ok'            => true,
        'message'       => 'Profile picture updated.',
        'profile_image' => $relativePath,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
