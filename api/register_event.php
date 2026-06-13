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

verifyCsrf();

try {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $eventRaw = $body['event_id'] ?? $_POST['event_id'] ?? '';
    $note     = trim($body['note'] ?? $_POST['note'] ?? '');
    $now      = date('Y-m-d');

    if (!$eventRaw) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Please select an event.']);
        exit;
    }

    $db = getDB();

    if (isLoggedIn()) {
        $memberId = (int)$_SESSION['member_id'];
    } else {
        $name       = trim($body['name'] ?? $_POST['name'] ?? '');
        $email      = trim(strtolower($body['email'] ?? $_POST['email'] ?? ''));
        $phone      = trim($body['phone'] ?? $_POST['phone'] ?? '');
        $department = trim($body['department'] ?? $_POST['department'] ?? '');
        $batch      = trim($body['batch'] ?? $_POST['batch'] ?? '');

        if (!$name || !$email) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Full Name and Email are required for event registration.']);
            exit;
        }

        // Check if member with this email already exists
        $stmt = $db->prepare("SELECT id FROM `members` WHERE `email` = ?");
        $stmt->execute([$email]);
        $existingMember = $stmt->fetch();

        if ($existingMember) {
            $memberId = (int)$existingMember['id'];
        } else {
            // Create a guest/temp member
            $codeStmt = $db->query("SELECT COUNT(*) FROM `members` WHERE `role` = 'member'");
            $count    = (int)$codeStmt->fetchColumn();
            $code     = sprintf('KBEC-2026-%04d', $count + 1);

            // Generate unique guest student ID
            $studentId = 'GUEST-' . bin2hex(random_bytes(6));

            // Generate a random password hash
            $randomPassword = bin2hex(random_bytes(16));
            $hash = password_hash($randomPassword, PASSWORD_BCRYPT, ['cost' => 12]);

            $ins = $db->prepare("
                INSERT INTO `members`
                  (member_code, name, student_id, email, password_hash,
                   department, batch, phone, verified, role)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'member')
            ");
            $ins->execute([$code, $name, $studentId, $email, $hash, $department, $batch, $phone]);
            $memberId = (int)$db->lastInsertId();
        }
    }

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
