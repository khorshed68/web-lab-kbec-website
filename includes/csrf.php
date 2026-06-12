<?php
/**
 * KBEC CSRF Protection Helpers
 * Generates and validates synchroniser tokens stored in the PHP session.
 */

/**
 * Return (and create if needed) the CSRF token for this session.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Return a hidden HTML input containing the CSRF token.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify the CSRF token from a POST request.
 * Accepts the token from:
 *   1. $_POST['csrf_token']
 *   2. X-CSRF-Token HTTP header (for AJAX / JSON requests)
 *
 * Terminates with HTTP 403 if the token is missing or invalid.
 */
function verifyCsrf(): void
{
    $token = $_POST['csrf_token']
        ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    $expected = $_SESSION['csrf_token'] ?? '';

    if (!$expected || !hash_equals($expected, $token)) {
        http_response_code(403);
        // Return JSON for API endpoints, plain text otherwise
        if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token.']);
        } else {
            echo 'CSRF token validation failed. Please go back and try again.';
        }
        exit;
    }
}
