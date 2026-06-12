<?php
/**
 * API: GET/POST /api/check_in.php?ticket=TOKEN
 * Marks event attendance by ticket token.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();
header('Content-Type: application/json; charset=utf-8');

$token = trim($_GET['ticket'] ?? $_POST['ticket'] ?? (json_decode(file_get_contents('php://input'), true)['ticket'] ?? ''));

if (!$token) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Ticket token is required.']);
    exit;
}

try {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT r.*, e.title AS event_title, e.location, e.event_date_start,
               m.name AS member_name, m.email AS member_email
        FROM `event_registrations` r
        JOIN `events` e  ON e.id = r.event_id
        JOIN `members` m ON m.id = r.member_id
        WHERE r.ticket_token = ?
    ");
    $stmt->execute([$token]);
    $reg = $stmt->fetch();

    if (!$reg) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Ticket not found.']);
        exit;
    }

    if (!$reg['attended_at']) {
        $upd = $db->prepare("UPDATE `event_registrations` SET `attended_at` = NOW() WHERE `ticket_token` = ?");
        $upd->execute([$token]);
        $reg['attended_at'] = date('c');
        $msg = 'Attendance recorded successfully.';
    } else {
        $msg = 'Already checked in at ' . $reg['attended_at'];
    }

    echo json_encode([
        'ok'      => true,
        'message' => $msg,
        'ticket'  => $reg,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
