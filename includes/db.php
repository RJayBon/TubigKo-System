<?php
/**
 * TubigKo — centralized database connection.
 * Every page includes this (indirectly, via data.php or auth.php)
 * instead of opening its own connection.
 */

// ---- connection settings -------------------------------------------------
// Override these with environment variables in production instead of
// editing this file directly, e.g. putenv('DB_HOST=...') in an
// untracked includes/config.local.php that is required before this file.
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'tubigko_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');

/**
 * Returns a shared PDO connection (created once per request).
 * Never expose PDOException details to the browser — log them and
 * show a generic message instead.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('TubigKo DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        die('The system is temporarily unavailable. Please try again in a moment.');
    }

    return $pdo;
}

/**
 * Run a query that returns rows. $params is bound positionally or by name.
 */
function db_query(string $sql, array $params = []): array
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('TubigKo query failed: ' . $e->getMessage() . ' | SQL: ' . $sql);
        return [];
    }
}

/** Run a query and return only the first row (or null). */
function db_one(string $sql, array $params = []): ?array
{
    $rows = db_query($sql, $params);
    return $rows[0] ?? null;
}

/**
 * Run an INSERT/UPDATE/DELETE. Returns true on success, false on failure.
 * On failure, $error is filled with a safe, generic message (never the
 * raw SQL error) so calling code can show something to the user.
 */
function db_exec(string $sql, array $params, ?string &$error = null): bool
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return true;
    } catch (PDOException $e) {
        error_log('TubigKo exec failed: ' . $e->getMessage() . ' | SQL: ' . $sql);
        $error = 'Database operation failed. Please try again.';
        return false;
    }
}

function db_last_insert_id(): string
{
    return db()->lastInsertId();
}

/**
 * Escape a value for safe HTML output. Use this around every piece of
 * user-supplied data (names, addresses, notes, messages, ...) printed
 * into a page.
 */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
