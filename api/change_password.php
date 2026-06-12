<?php
/**
 * API: POST /api/change_password.php
 * Changes the logged-in member's password.
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
    $body            = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $currentPassword = $body['current_password'] ?? '';
    $newPassword     = $body['new_password']      ?? '';
    $confirmPassword = $body['confirm_password']  ?? '';

    if (!$currentPassword || !$newPassword || !$confirmPassword) {
        http_response_code(400); echo json_encode(['ok'=>false,'message'=>'All fields are required.']); exit;
    }
    if (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        http_response_code(400); echo json_encode(['ok'=>false,'message'=>'New password must be at least 8 characters with one uppercase letter and one number.']); exit;
    }
    if ($newPassword !== $confirmPassword) {
        http_response_code(400); echo json_encode(['ok'=>false,'message'=>'New passwords do not match.']); exit;
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT password_hash FROM `members` WHERE id = ?");
    $stmt->execute([$_SESSION['member_id']]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify($currentPassword, $hash)) {
        http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Current password is incorrect.']); exit;
    }

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $upd     = $db->prepare("UPDATE `members` SET password_hash = ? WHERE id = ?");
    $upd->execute([$newHash, $_SESSION['member_id']]);

    echo json_encode(['ok' => true, 'message' => 'Password changed successfully.']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
