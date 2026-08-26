<?php
/**
 * Database configuration.
 * Edit these constants to match your MySQL setup, or set them via
 * environment variables (recommended for anything beyond local dev).
 */
define('DB_HOST', getenv('HR_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('HR_DB_PORT') ?: '3306');
define('DB_NAME', getenv('HR_DB_NAME') ?: 'meridian_hr');
define('DB_USER', getenv('HR_DB_USER') ?: 'root');
define('DB_PASS', getenv('HR_DB_PASS') ?: '');

// Fixed "office" coordinates used for GPS geofence checks (Attendance page).
define('OFFICE_LAT', 12.9716);
define('OFFICE_LNG', 77.5946);
define('OFFICE_NAME', 'Meridian HQ, Bengaluru');
define('GEOFENCE_RADIUS_METERS', 300);

// Attendance-linked payroll adjustment, in rupees.
define('ATTENDANCE_CREDIT', 50);
define('ATTENDANCE_DEBIT', 50);

/**
 * Returns a shared PDO connection. Uses prepared statements everywhere in
 * this app to avoid SQL injection; exceptions are thrown on error so callers
 * can decide how to handle failures instead of failing silently.
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
