<?php
/**
 * API: GET /api/me.php
 * Returns the current logged-in member's data as JSON.
 * Used by index.php frontend JS to check session state.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not logged in.']);
    exit;
}

try {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT id, member_code, name, student_id, email, department,
               batch, phone, interest, bio, profile_image, verified, role, created_at
        FROM `members` WHERE `id` = ?
    ");
    $stmt->execute([$_SESSION['member_id']]);
    $member = $stmt->fetch();

    if (!$member) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Member not found.']);
        exit;
    }

    echo json_encode(['ok' => true, 'member' => $member]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
