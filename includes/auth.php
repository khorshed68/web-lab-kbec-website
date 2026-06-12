<?php
/**
 * KBEC Auth Helpers
 * Session management, login/logout, role checks, member-code generation.
 */

/**
 * Start a secure PHP session (call once at top of every page).
 */
function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => false,   // set true if using HTTPS
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * Is the current visitor logged in?
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['member_id']);
}

/**
 * Is the current visitor an admin?
 */
function isAdmin(): bool
{
    return isLoggedIn() && ($_SESSION['member_role'] ?? '') === 'admin';
}

/**
 * Signed in through the dedicated admin login portal?
 */
function isAdminPortalAuthenticated(): bool
{
    return isAdmin() && !empty($_SESSION['admin_portal_auth']);
}

/**
 * Return the current member's session data as an associative array,
 * or null if not logged in.
 */
function currentMember(): ?array
{
    if (!isLoggedIn()) return null;

    return [
        'id'          => $_SESSION['member_id'],
        'member_code' => $_SESSION['member_code'],
        'name'        => $_SESSION['member_name'],
        'email'       => $_SESSION['member_email'],
        'role'        => $_SESSION['member_role'],
        'verified'    => $_SESSION['member_verified'] ?? 1,
        'profile_image' => $_SESSION['member_avatar'] ?? null,
    ];
}

/**
 * Redirect to $redirect if visitor is not logged in.
 */
function requireLogin(string $redirect = '../login.php'): void
{
    if (!isLoggedIn()) {
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Redirect to $redirect if visitor is not an admin.
 */
function requireAdmin(string $redirect = '../index.php'): void
{
    if (!isAdmin()) {
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Redirect if visitor has not signed in via the admin login portal.
 */
function requireAdminPortal(string $redirect = 'login.php'): void
{
    if (!isAdminPortalAuthenticated()) {
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Populate session variables from a members DB row.
 */
function loginMember(array $member): void
{
    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);

    $_SESSION['member_id']       = $member['id'];
    $_SESSION['member_code']     = $member['member_code'];
    $_SESSION['member_name']     = $member['name'];
    $_SESSION['member_email']    = $member['email'];
    $_SESSION['member_role']     = $member['role'];
    $_SESSION['member_verified'] = $member['verified'];
    $_SESSION['member_avatar']   = $member['profile_image'] ?? null;
    unset($_SESSION['admin_portal_auth']);
}

/**
 * Admin portal sign-in (admin/login.php only).
 */
function loginAdminPortal(array $member): void
{
    loginMember($member);
    $_SESSION['admin_portal_auth'] = true;
}

/**
 * Destroy session and clear the session cookie.
 */
function logoutMember(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Generate the next sequential KBEC member code: KBEC-2026-XXXX
 * Uses a SELECT COUNT(*) to determine the next number.
 * This is safe for low-concurrency club usage.
 */
function generateMemberCode(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM `members` WHERE `role` = 'member'");
    $count = (int) $stmt->fetchColumn();
    $next  = $count + 1;
    return sprintf('KBEC-2026-%04d', $next);
}

/**
 * Sanitise a string for safe HTML output.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
