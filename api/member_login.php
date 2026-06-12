<?php
/**
 * API: POST /api/member_login.php  (JSON)
 * Used by the inline login form on index.php.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'message'=>'Method not allowed.']); exit; }

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$email    = trim(strtolower($body['email']    ?? ''));
$password = $body['password'] ?? '';

if (!$email || !$password) {
    http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Email and password are required.']); exit;
}

try {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM `members` WHERE `email` = ? LIMIT 1");
    $stmt->execute([$email]);
    $member = $stmt->fetch();

    if (!$member || !password_verify($password, $member['password_hash'])) {
        http_response_code(401); echo json_encode(['ok'=>false,'message'=>'Invalid email or password.']); exit;
    }
    if (!$member['verified']) {
        http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Account not verified. Please contact the admin.']); exit;
    }

    loginMember($member);

    echo json_encode([
        'ok'     => true,
        'message'=> 'Login successful.',
        'member' => [
            'id'          => $member['id'],
            'member_code' => $member['member_code'],
            'name'        => $member['name'],
            'email'       => $member['email'],
            'department'  => $member['department'],
            'batch'       => $member['batch'],
            'phone'       => $member['phone'],
            'interest'    => $member['interest'],
            'bio'         => $member['bio'],
            'profile_image'=> $member['profile_image'],
            'verified'    => (bool)$member['verified'],
            'role'        => $member['role'],
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500); echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
}
