<?php
/**
 * TubigKo — authentication, session, role-based access control, CSRF.
 * Include this at the very top of every page (before any HTML output).
 */

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

// ---------------------------------------------------------------------
// Session helpers
// ---------------------------------------------------------------------

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

/** Full current-user row from the DB, or null if not logged in / not found. */
function current_user(): ?array
{
    static $cached = null;
    static $loaded = false;
    if ($loaded) {
        return $cached;
    }
    $loaded = true;
    if (!is_logged_in()) {
        return null;
    }
    $cached = db_one('SELECT id, full_name, username, email, phone, address, barangay, landmark, role, status FROM users WHERE id = ?', [$_SESSION['user_id']]);
    return $cached;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Works out the relative path back to the project root from whichever
 * script is currently running (root, /admin, or /customer), so redirects
 * always resolve correctly regardless of which page triggered them.
 */
function base_path(): string
{
    $dir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    return in_array($dir, ['admin', 'customer'], true) ? '..' : '.';
}

/**
 * Require the visitor to be logged in, optionally with a specific role.
 * Redirects to the correct login/dashboard page otherwise. Must be
 * called before any HTML is echoed.
 */
function require_login(?string $role = null): void
{
    $base = base_path();

    if (!is_logged_in()) {
        flash_set('error', 'Please sign in to continue.');
        header('Location: ' . $base . '/login.php');
        exit;
    }

    if ($role !== null && current_role() !== $role) {
        // Logged in, but wrong role for this page — bounce to their own dashboard.
        flash_set('error', 'You are not authorized to view that page.');
        if (current_role() === 'admin') {
            header('Location: ' . $base . '/admin/dashboard.php');
        } else {
            header('Location: ' . $base . '/customer/gallons.php');
        }
        exit;
    }

    $user = current_user();
    if (!$user || $user['status'] !== 'active') {
        logout_user();
        flash_set('error', 'Your account is inactive. Please contact the station.');
        header('Location: ' . $base . '/login.php');
        exit;
    }
}

// ---------------------------------------------------------------------
// Flash messages (one-time notices shown after a redirect)
// ---------------------------------------------------------------------

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

/** Fetch + clear all pending flash messages. */
function flash_all(): array
{
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

// ---------------------------------------------------------------------
// CSRF protection
// ---------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/** Call at the top of a POST handler; stops the request if the token is bad. */
function csrf_check_or_die(string $base = '.'): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}


