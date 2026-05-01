<?php
/**
 * reset_password.php — Functional Password Reset (Academic Demo)
 * Allows users to reset their password directly by providing their email.
 * Note: In a real production app, this must send an email link with a secure token!
 */
require_once __DIR__ . '/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . basePath() . '/index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $stmt = $pdo->prepare('SELECT UserID, FullName FROM Users WHERE Email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Functional Update: Generate the new Bcrypt hash
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            
            // Update the database with the new hash
            $updateStmt = $pdo->prepare('UPDATE Users SET PasswordHash = ? WHERE UserID = ?');
            $updateStmt->execute([$newHash, $user['UserID']]);
            
            // Log the actual reset event
            auditLog($pdo, 'PASSWORD_RESET', 'Users', $user['UserID'], "Password reset via form for " . $email);
            
            $success = 'Password successfully updated! You can now return to the login screen and sign in.';
        } else {
            // For security, generic message (even though it's a demo)
            $error = 'If that email is registered, the password has been updated.';
        }
    } else {
        $error = 'Please enter both your email and a new password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password — HRec</title>
  <link rel="stylesheet" href="<?= basePath() ?>/css/style.css?v=<?= time() ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <style>
    body { display:flex; align-items:center; justify-content:center; min-height:100vh; background: linear-gradient(135deg, #000000 0%, #0a0a0a 50%, #17102e 100%); padding:20px; }
    .login-wrap { width:100%; max-width:420px; }
    .login-card { background: rgba(20,20,20,0.7); border:1px solid rgba(255,255,255,0.05); border-radius:var(--radius-lg); padding:40px 36px; box-shadow:var(--shadow-lg); backdrop-filter: blur(20px); }
    .login-brand { display:flex; align-items:center; gap:12px; margin-bottom:24px; }
    .login-brand-icon { width:44px; height:44px; background:var(--accent); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; }
    .login-brand-name { font-size:1.5rem; font-weight:800; color:var(--text-primary); }
    .login-title { font-size:1.25rem; font-weight:700; color:var(--text-primary); margin-bottom:8px; }
    .login-sub   { font-size:.85rem; color:var(--text-secondary); margin-bottom:28px; line-height:1.4; }
    .alert-msg { display:flex; align-items:center; gap:10px; padding:12px 16px; border-radius:var(--radius-sm); margin-bottom:20px; font-size:.875rem; font-weight:500; }
    .alert-msg.error   { background: rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.4); color:#fca5a5; }
    .alert-msg.success { background: rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.4); color:#6ee7b7; }
    .pw-wrap { position:relative; }
    .pw-wrap .form-control { padding-right: 44px; }
    .pw-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-muted); display:flex; padding:4px; }
    .pw-toggle:hover { color:var(--text-primary); }
    .pw-toggle .material-symbols-outlined { font-size:20px; }
    .footer-link { text-align:center; margin-top:24px; font-size:0.875rem; color:var(--text-secondary); }
    .footer-link a { color:var(--accent); text-decoration:none; font-weight:600; }
    .footer-link a:hover { text-decoration:underline; }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-brand">
      <div class="login-brand-icon"><span class="material-symbols-outlined">lock_reset</span></div>
      <div class="login-brand-name">HRec</div>
    </div>
    
    <div class="login-title">Reset Password</div>
    <div class="login-sub">Enter your email and your new password. In a real system, you would receive a secure email link.</div>

    <?php if ($success): ?>
      <div class="alert-msg success"><span class="material-symbols-outlined">check_circle</span> <?= htmlspecialchars($success) ?></div>
    <?php elseif ($error): ?>
      <div class="alert-msg error"><span class="material-symbols-outlined">error</span> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input class="form-control" type="email" id="email" name="email" required autofocus placeholder="you@hrec.test">
      </div>
      
      <div class="form-group" style="margin-bottom:24px;">
        <label class="form-label" for="password">New Password</label>
        <div class="pw-wrap">
          <input class="form-control" type="password" id="password" name="password" required placeholder="••••••••">
          <button type="button" class="pw-toggle" onclick="togglePw('password', this)" title="Show/hide password">
            <span class="material-symbols-outlined" id="pw-icon">visibility</span>
          </button>
        </div>
      </div>
      
      <button class="btn btn-primary" type="submit" style="width:100%;">
        <span class="material-symbols-outlined">key</span> Reset Password
      </button>
    </form>
    <?php endif; ?>

    <div class="footer-link">
      <a href="<?= basePath() ?>/index.php">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:-3px;">arrow_back</span> 
        Back to Sign In
      </a>
    </div>
  </div>
</div>

<script>
function togglePw(inputId, btn) {
  const input = document.getElementById(inputId);
  const icon = btn.querySelector('.material-symbols-outlined');
  if (input.type === 'password') {
    input.type = 'text';
    icon.textContent = 'visibility_off';
  } else {
    input.type = 'password';
    icon.textContent = 'visibility';
  }
}
</script>
</body>
</html>
