<?php
/**
 * index.php — Login Page
 * Entry point for all users. Authenticates and redirects by role.
 */
require_once __DIR__ . '/db.php';

// Already logged in → redirect to role dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'Patient')    { header('Location: ' . basePath() . '/pages/patient_dashboard.php'); exit; }
    if ($role === 'Doctor')     { header('Location: ' . basePath() . '/pages/doctor_overview.php');  exit; }
    if ($role === 'Pharmacist') { header('Location: ' . basePath() . '/pages/pharmacy.php');          exit; }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $stmt = $pdo->prepare('SELECT UserID, FullName, Role, PasswordHash FROM Users WHERE Email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['PasswordHash'])) {
            $_SESSION['user_id']  = $user['UserID'];
            $_SESSION['role']     = $user['Role'];
            $_SESSION['full_name'] = $user['FullName'];

            auditLog($pdo, 'LOGIN', 'Users', $user['UserID'], 'Login from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

            if ($user['Role'] === 'Patient')    { header('Location: ' . basePath() . '/pages/patient_dashboard.php'); exit; }
            if ($user['Role'] === 'Doctor')     { header('Location: ' . basePath() . '/pages/doctor_overview.php');  exit; }
            if ($user['Role'] === 'Pharmacist') { header('Location: ' . basePath() . '/pages/pharmacy.php');          exit; }
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please enter both email and password.';
    }
}

$loggedOut    = isset($_GET['logout']);
$unauthorized = isset($_GET['error']) && $_GET['error'] === 'unauthorized';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — HRec Unified Health Record</title>
  <meta name="description" content="Sign in to HRec — Unified Health Record and Clinical Decision Support System.">
  <link rel="stylesheet" href="<?= basePath() ?>/css/style.css?v=<?= time() ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <style>
    body { display:flex; align-items:center; justify-content:center; min-height:100vh; background: linear-gradient(135deg, #000000 0%, #0a0a0a 50%, #17102e 100%); }
    .login-wrap { width:100%; max-width:420px; padding:24px; }
    .login-card { background: rgba(20,20,20,0.7); border:1px solid rgba(255,255,255,0.05); border-radius:var(--radius-lg); padding:40px 36px; box-shadow:var(--shadow-lg); backdrop-filter: blur(20px); }
    .login-brand { display:flex; align-items:center; gap:12px; margin-bottom:32px; }
    .login-brand-icon { width:44px; height:44px; background:var(--accent); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; }
    .login-brand-name { font-size:1.5rem; font-weight:800; color:var(--text-primary); }
    .login-brand-sub  { font-size:.75rem; color:var(--text-secondary); margin-top:1px; }
    .login-title { font-size:1.25rem; font-weight:700; color:var(--text-primary); margin-bottom:6px; }
    .login-sub   { font-size:.85rem; color:var(--text-secondary); margin-bottom:28px; }
    .demo-table  { width:100%; border-collapse:collapse; font-size:.78rem; margin-top:24px; }
    .demo-table th { text-align:left; padding:6px 10px; color:var(--text-secondary); font-weight:600; border-bottom:1px solid var(--border); }
    .demo-table td { padding:7px 10px; color:var(--text-primary); border-bottom:1px solid var(--border); font-family:monospace; font-size:.76rem; }
    .demo-table tr:last-child td { border-bottom:none; }
    .demo-box { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-sm); margin-top:24px; overflow:hidden; }
    .demo-box-title { padding:10px 14px; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--text-secondary); border-bottom:1px solid var(--border); }
    .alert-msg { display:flex; align-items:center; gap:10px; padding:12px 16px; border-radius:var(--radius-sm); margin-bottom:20px; font-size:.875rem; font-weight:500; }
    .alert-msg.error   { background: rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.4); color:#fca5a5; }
    .alert-msg.success { background: rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.4); color:#6ee7b7; }
    .alert-msg.warn    { background: rgba(245,158,11,0.15); border:1px solid rgba(245,158,11,0.4); color:#fcd34d; }
    .pw-wrap { position:relative; }
    .pw-wrap .form-control { padding-right: 44px; }
    .pw-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-muted); display:flex; padding:4px; }
    .pw-toggle:hover { color:var(--text-primary); }
    .pw-toggle .material-symbols-outlined { font-size:20px; }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-brand">
      <div class="login-brand-icon"><span class="material-symbols-outlined">local_hospital</span></div>
      <div>
        <div class="login-brand-name">HRec</div>
        <div class="login-brand-sub">Unified Health Record · CDSS</div>
      </div>
    </div>

    <div class="login-title">Sign in to your account</div>
    <div class="login-sub">CSE370 Database Project Demo</div>

    <?php if ($loggedOut): ?>
      <div class="alert-msg success"><span class="material-symbols-outlined">check_circle</span> Signed out successfully.</div>
    <?php endif; ?>
    <?php if ($unauthorized): ?>
      <div class="alert-msg warn"><span class="material-symbols-outlined">lock</span> Unauthorised — please sign in with the correct role.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert-msg error"><span class="material-symbols-outlined">error</span> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" style="display:flex;flex-direction:column;gap:16px;">
      <div class="form-group">
        <label class="form-label" for="email">Email address</label>
        <input class="form-control" type="email" id="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="you@hrec.test" required autofocus>
      </div>
      <div class="form-group" style="margin-bottom:24px;">
        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
          <label class="form-label" for="password" style="margin-bottom:0;">Password</label>
          <a href="<?= basePath() ?>/reset_password.php" style="font-size:.75rem; color:var(--accent); text-decoration:none; font-weight:500;">Forgot password?</a>
        </div>
        <div class="pw-wrap">
          <input class="form-control" type="password" id="password" name="password" required>
          <button type="button" class="pw-toggle" onclick="togglePw('password', this)" title="Show/hide password">
            <span class="material-symbols-outlined" id="pw-icon">visibility</span>
          </button>
        </div>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:4px;">
        <span class="material-symbols-outlined">login</span> Sign In
      </button>

      <div style="display:flex; justify-content:center; margin-top:8px; font-size:0.875rem;">
        <a href="<?= basePath() ?>/register.php" style="color:var(--accent); text-decoration:none; font-weight:600;">Create account</a>
      </div>
    </form>

    <div class="demo-box">
      <div class="demo-box-title">🔑 Demo Credentials</div>
      <table class="demo-table">
        <thead><tr><th>Role</th><th>Email</th><th>Password</th></tr></thead>
        <tbody>
          <tr><td>Patient</td>    <td>patient1@hrec.test</td><td>patient123</td></tr>
          <tr><td>Doctor</td>     <td>doctor@hrec.test</td>  <td>doctor123</td></tr>
          <tr><td>Pharmacist</td> <td>pharma@hrec.test</td>  <td>pharma123</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script src="<?= basePath() ?>/js/app.js"></script>
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
