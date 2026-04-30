<?php
/**
 * db.php — Database Connection & Session Bootstrap
 * Include this file at the top of every page.
 * Provides: $pdo (PDO instance), session management, auth helpers.
 */

session_start();

// ── Database credentials ──────────────────────────────────
$host = 'localhost';
$db   = 'bracculs_hrec';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:Inter,sans-serif;padding:40px;text-align:center;">
         <h2>⚠️ Database Connection Failed</h2>
         <p>Make sure XAMPP MySQL is running and <code>bracculs_hrec</code> database exists.</p>
         <p style="color:#888;font-size:13px;">Import <code>setup.sql</code> in phpMyAdmin first.</p>
         <pre style="color:red;font-size:12px;">' . htmlspecialchars($e->getMessage()) . '</pre>
         </div>');
}

// ── Auth helpers ──────────────────────────────────────────

/**
 * Require that the user is logged in. Redirects to login if not.
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /HRec_CDSS/Unified-Health-Record-main/index.php');
        exit;
    }
}

/**
 * Require a specific role. Redirects to login if unauthorized.
 * @param string|array $roles  Single role string or array of allowed roles.
 */
function requireRole($roles) {
    requireLogin();
    if (is_string($roles)) $roles = [$roles];
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: /HRec_CDSS/Unified-Health-Record-main/index.php?error=unauthorized');
        exit;
    }
}

/**
 * Get the base URL path for the project.
 */
function basePath() {
    return '/HRec_CDSS/Unified-Health-Record-main';
}

/**
 * Get the currently logged-in user's ID.
 */
function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get the currently logged-in user's role.
 */
function currentRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Get the currently logged-in user's full name.
 */
function currentUserName() {
    return $_SESSION['full_name'] ?? 'Guest';
}

/**
 * Log an action to the SECURITY_AUDIT_LOG.
 */
function auditLog($pdo, $action, $table = null, $recordId = null, $details = null) {
    $stmt = $pdo->prepare('INSERT INTO SECURITY_AUDIT_LOG (UserID, Action, TableName, RecordID, Details, IPAddress) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        currentUserId(),
        $action,
        $table,
        $recordId,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}
