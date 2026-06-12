<?php
/**
 * API: GET /api/events.php
 * Returns all events with registration counts + logged-in member's tickets.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();
header('Content-Type: application/json; charset=utf-8');

try {
    $db  = getDB();
    $now = date('Y-m-d');

    // ── Load all events ──────────────────────────────────────────────
    $evStmt = $db->query("
        SELECT e.*,
               COUNT(r.id) AS registered_count
        FROM `events` e
        LEFT JOIN `event_registrations` r ON r.event_id = e.id
        GROUP BY e.id
        ORDER BY e.event_date_start ASC
    ");
    $events = $evStmt->fetchAll();

    // Build public payload for each event
    foreach ($events as &$ev) {
        $regCount = (int)$ev['registered_count'];
        $capacity = (int)$ev['capacity'];
        $deadline = $ev['registration_deadline'];
        $endDate  = $ev['event_date_end'];

        // Determine status
        if ($deadline < $now || $endDate < $now) {
            $status = 'Closed';
        } elseif ($regCount >= $capacity) {
            $status = 'Full';
        } else {
            $status = 'Open';
        }

        $ev['registered_count']  = $regCount;
        $ev['remaining_seats']   = max(0, $capacity - $regCount);
        $ev['status']            = $status;
        $ev['start_label']       = date('F j, Y', strtotime($ev['event_date_start']));
        $ev['end_label']         = date('F j, Y', strtotime($ev['event_date_end']));
        $ev['deadline_label']    = date('F j, Y', strtotime($deadline));
        $ev['deadline_short']    = date('M j', strtotime($deadline));
    }
    unset($ev);

    // ── Load current member's tickets ─────────────────────────────────
    $myTickets = [];
    if (isLoggedIn()) {
        $tStmt = $db->prepare("
            SELECT r.id, r.ticket_code, r.ticket_token, r.note,
                   r.registered_at, r.attended_at,
                   e.title AS event_title, e.slug, e.type,
                   e.location, e.event_date_start, e.event_date_end
            FROM `event_registrations` r
            JOIN `events` e ON e.id = r.event_id
            WHERE r.member_id = ?
            ORDER BY r.registered_at DESC
        ");
        $tStmt->execute([$_SESSION['member_id']]);
        $myTickets = $tStmt->fetchAll();
    }

    echo json_encode([
        'ok'        => true,
        'events'    => $events,
        'myTickets' => $myTickets,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
