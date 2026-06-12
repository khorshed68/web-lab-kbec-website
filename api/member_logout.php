<?php
/** API: POST /api/member_logout.php — destroys session */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();
header('Content-Type: application/json; charset=utf-8');
logoutMember();
echo json_encode(['ok' => true, 'message' => 'Logged out.']);
