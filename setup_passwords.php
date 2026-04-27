<?php
/**
 * setup_passwords.php — One-time password setup helper
 * Run this ONCE after importing setup.sql to set proper bcrypt hashes.
 * Then delete this file.
 */
$host = 'localhost'; $db = 'bracculs_hrec'; $user = 'root'; $pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $credentials = [
        ['patient1@hrec.test', 'patient123'],
        ['patient2@hrec.test', 'patient123'],
        ['doctor@hrec.test',   'doctor123'],
        ['pharma@hrec.test',   'pharma123'],
    ];

    echo '<html><head><style>body{font-family:Inter,sans-serif;max-width:600px;margin:40px auto;padding:20px;}
    .ok{color:#10b981;font-weight:700}.card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin:12px 0;}</style></head><body>';
    echo '<h1>🔑 HRec Password Setup</h1>';

    foreach ($credentials as [$email, $plainPassword]) {
        $hash = password_hash($plainPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE Users SET PasswordHash = ? WHERE Email = ?');
        $stmt->execute([$hash, $email]);
        $affected = $stmt->rowCount();
        echo "<div class='card'><span class='ok'>✅</span> <strong>$email</strong> → password set to <code>$plainPassword</code> ($affected row affected)</div>";
    }

    echo '<br><p style="color:#64748b">All passwords updated. You can now <a href="index.php">sign in</a>.</p>';
    echo '<p style="color:#ef4444;font-weight:600">⚠️ Delete this file (setup_passwords.php) after use for security.</p>';
    echo '</body></html>';

} catch (PDOException $e) {
    echo '<h2>Database Error</h2><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '<p>Make sure XAMPP MySQL is running and <code>setup.sql</code> has been imported.</p>';
}
