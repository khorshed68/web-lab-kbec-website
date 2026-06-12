<?php
/**
 * API: POST /api/member_register.php (JSON)
 * Used by the inline register form on index.php.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'message'=>'Method not allowed.']); exit; }

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$name       = trim($body['name']       ?? '');
$studentId  = trim($body['studentId']  ?? $body['student_id'] ?? '');
$email      = trim(strtolower($body['email'] ?? ''));
$department = trim($body['department'] ?? '');
$batch      = trim($body['batch']      ?? '');
$phone      = trim($body['phone']      ?? '');
$interest   = trim($body['interest']   ?? '');
$bio        = trim($body['bio']        ?? '');
$password   = $body['password'] ?? '';

if (!$name || !$studentId || !$email || !$department || !$batch || !$password) {
    http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Please fill in all required fields.']); exit;
}
if (!preg_match('/@kuet\.ac\.bd$/i', $email)) {
    http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Only KUET email addresses (@kuet.ac.bd) are allowed.']); exit;
}
if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
    http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Password must be at least 8 characters with one uppercase and one number.']); exit;
}

try {
    $db  = getDB();
    $chk = $db->prepare("SELECT COUNT(*) FROM `members` WHERE `email`=? OR `student_id`=?");
    $chk->execute([$email, $studentId]);
    if ((int)$chk->fetchColumn() > 0) {
        http_response_code(409); echo json_encode(['ok'=>false,'message'=>'An account with this email or Student ID already exists.']); exit;
    }

    $codeStmt = $db->query("SELECT COUNT(*) FROM `members` WHERE `role`='member'");
    $code     = sprintf('KBEC-2026-%04d', (int)$codeStmt->fetchColumn() + 1);
    $hash     = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);

    $ins = $db->prepare("INSERT INTO `members` (member_code,name,student_id,email,password_hash,department,batch,phone,interest,bio,verified,role) VALUES (?,?,?,?,?,?,?,?,?,?,1,'member')");
    $ins->execute([$code,$name,$studentId,$email,$hash,$department,$batch,$phone,$interest,$bio]);

    $newId  = (int)$db->lastInsertId();
    $mStmt  = $db->prepare("SELECT * FROM `members` WHERE id=?");
    $mStmt->execute([$newId]);
    $member = $mStmt->fetch();
    loginMember($member);

    echo json_encode([
        'ok'      => true,
        'message' => 'Account created! Welcome to KBEC.',
        'member'  => ['id'=>$member['id'],'member_code'=>$member['member_code'],'name'=>$member['name'],'email'=>$member['email'],'department'=>$member['department'],'batch'=>$member['batch'],'verified'=>true,'role'=>$member['role']],
    ]);
} catch (Throwable $e) {
    http_response_code(500); echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
}
