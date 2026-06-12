<?php
/**
 * API: POST /api/register_event.php
 * Registers the logged-in member for an event and returns a ticket.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

startSession();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please log in to register for events.']);
    exit;
}

verifyCsrf();

try {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $eventRaw = $body['event_id'] ?? $_POST['event_id'] ?? '';
    $note     = trim($body['note'] ?? $_POST['note'] ?? '');
    $memberId = (int)$_SESSION['member_id'];
    $now      = date('Y-m-d');

    if (!$eventRaw) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Please select an event.']);
        exit;
    }

    $db = getDB();

    // ── Fetch event by ID (integer) or slug (string) ──────────────────
    if (is_numeric($eventRaw)) {
        $evStmt = $db->prepare("SELECT * FROM `events` WHERE `id` = ?");
        $evStmt->execute([(int)$eventRaw]);
    } else {
        $evStmt = $db->prepare("SELECT * FROM `events` WHERE `slug` = ?");
        $evStmt->execute([(string)$eventRaw]);
    }
    $event = $evStmt->fetch();

    if (!$event) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Event not found.']);
        exit;
    }

    // ── Status check ──────────────────────────────────────────────────
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM `event_registrations` WHERE `event_id` = ?");
    $cntStmt->execute([$event['id']]);
    $regCount = (int)$cntStmt->fetchColumn();

    if ($event['registration_deadline'] < $now || $event['event_date_end'] < $now) {
        http_response_code(410);
        echo json_encode(['ok' => false, 'message' => 'Registration for this event is closed.']);
        exit;
    }

    if ($regCount >= (int)$event['capacity']) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'This event is full.']);
        exit;
    }

    // ── Already registered? ───────────────────────────────────────────
    $chkStmt = $db->prepare("
        SELECT r.*, e.title AS event_title, e.location, e.event_date_start
        FROM `event_registrations` r
        JOIN `events` e ON e.id = r.event_id
        WHERE r.member_id = ? AND r.event_id = ?
    ");
    $chkStmt->execute([$memberId, $event['id']]);
    $existing = $chkStmt->fetch();

    if ($existing) {
        echo json_encode(['ok' => true, 'alreadyRegistered' => true, 'ticket' => $existing]);
        exit;
    }

    // ── Generate ticket ───────────────────────────────────────────────
    $slugPart    = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $event['slug']), 0, 6));
    $ticketCode  = 'KBEC-' . $slugPart . '-' . strtoupper(bin2hex(random_bytes(3)));
    $ticketToken = bin2hex(random_bytes(20));

    $ins = $db->prepare("
        INSERT INTO `event_registrations`
          (member_id, event_id, note, ticket_code, ticket_token)
        VALUES (?, ?, ?, ?, ?)
    ");
    $ins->execute([$memberId, $event['id'], $note, $ticketCode, $ticketToken]);
    $regId = (int)$db->lastInsertId();

    echo json_encode([
        'ok'      => true,
        'message' => 'Registration confirmed! Your ticket has been generated.',
        'ticket'  => [
            'id'              => $regId,
            'ticket_code'     => $ticketCode,
            'ticket_token'    => $ticketToken,
            'event_title'     => $event['title'],
            'event_date_start'=> $event['event_date_start'],
            'location'        => $event['location'],
            'note'            => $note,
            'registered_at'   => date('c'),
        ],
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
