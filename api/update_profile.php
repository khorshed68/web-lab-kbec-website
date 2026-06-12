<?php
/**
 * API: POST /api/update_profile.php
 * Updates the logged-in member's profile fields.
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
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $name       = trim($body['name']       ?? '');
    $phone      = trim($body['phone']      ?? '');
    $department = trim($body['department'] ?? '');
    $batch      = trim($body['batch']      ?? '');
    $interest   = trim($body['interest']   ?? '');
    $bio        = trim($body['bio']        ?? '');

    if (!$name) { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Name is required.']); exit; }

    $db  = getDB();
    $upd = $db->prepare("
        UPDATE `members`
        SET name=?, phone=?, department=?, batch=?, interest=?, bio=?
        WHERE id=?
    ");
    $upd->execute([$name, $phone, $department, $batch, $interest, $bio, $_SESSION['member_id']]);

    // Update session name
    $_SESSION['member_name'] = $name;

    echo json_encode([
        'ok'     => true,
        'message'=> 'Profile updated successfully.',
        'member' => compact('name','phone','department','batch','interest','bio'),
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
