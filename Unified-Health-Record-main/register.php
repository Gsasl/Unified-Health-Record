<?php
/**
 * register.php — User Registration
 * Allows new users to create an account in the system.
 */
require_once __DIR__ . '/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . basePath() . '/index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = $_POST['role'] ?? 'Patient';
    $dob      = $_POST['dob'] ?? null;

    if ($fullName && $email && $password && $role) {
        // Check if email already exists
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM Users WHERE Email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Email is already registered.';
        } else {
            try {
                $pdo->beginTransaction();

                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('INSERT INTO Users (FullName, Email, PasswordHash, Role, DOB) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$fullName, $email, $hash, $role, $dob ?: null]);
                $newUserId = $pdo->lastInsertId();

                if ($role === 'Patient') {
                    // Create an empty health record
                    $hrStmt = $pdo->prepare('INSERT INTO Health_Records (UserID, DOB, KnownAllergies, ChronicConditions) VALUES (?, ?, "[]", "[]")');
                    $hrStmt->execute([$newUserId, $dob ?: null]);
                }

                auditLog($pdo, 'REGISTER', 'Users', $newUserId, "User $email registered as $role");

                $pdo->commit();
                $success = 'Account created successfully! You can now sign in.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'An error occurred during registration. Please try again.';
            }
        }
    } else {
        $error = 'Please fill out all required fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account — HRec</title>
  <link rel="stylesheet" href="<?= basePath() ?>/css/style.css?v=<?= time() ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <style>
    body { display:flex; align-items:center; justify-content:center; min-height:100vh; background: linear-gradient(135deg, #000000 0%, #0a0a0a 50%, #17102e 100%); padding:20px; }
    .login-wrap { width:100%; max-width:480px; }
    .login-card { background: rgba(20,20,20,0.7); border:1px solid rgba(255,255,255,0.05); border-radius:var(--radius-lg); padding:40px 36px; box-shadow:var(--shadow-lg); backdrop-filter: blur(20px); }
    .login-brand { display:flex; align-items:center; gap:12px; margin-bottom:24px; }
    .login-brand-icon { width:44px; height:44px; background:var(--accent); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; }
    .login-brand-name { font-size:1.5rem; font-weight:800; color:var(--text-primary); }
    .login-title { font-size:1.25rem; font-weight:700; color:var(--text-primary); margin-bottom:24px; }
    .alert-msg { display:flex; align-items:center; gap:10px; padding:12px 16px; border-radius:var(--radius-sm); margin-bottom:20px; font-size:.875rem; font-weight:500; }
    .alert-msg.error   { background: rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.4); color:#fca5a5; }
    .alert-msg.success { background: rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.4); color:#6ee7b7; }
    .footer-link { text-align:center; margin-top:20px; font-size:0.875rem; color:var(--text-secondary); }
    .footer-link a { color:var(--accent); text-decoration:none; font-weight:600; }
    .footer-link a:hover { text-decoration:underline; }
    .form-row { display:flex; gap:16px; margin-bottom:16px; }
    .form-row .form-group { flex:1; margin-bottom:0; }
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
      <div class="login-brand-icon"><span class="material-symbols-outlined">person_add</span></div>
      <div class="login-brand-name">HRec</div>
    </div>
    <div class="login-title">Create your account</div>

    <?php if ($success): ?>
      <div class="alert-msg success"><span class="material-symbols-outlined">check_circle</span> <?= htmlspecialchars($success) ?></div>
    <?php elseif ($error): ?>
      <div class="alert-msg error"><span class="material-symbols-outlined">error</span> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label" for="full_name">Full Name <span style="color:#ef4444">*</span></label>
        <input class="form-control" type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label" for="email">Email Address <span style="color:#ef4444">*</span></label>
        <input class="form-control" type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </div>
      
      <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="role">Role <span style="color:#ef4444">*</span></label>
            <select class="form-control" id="role" name="role" required>
              <option value="Patient" <?= (($_POST['role'] ?? '') === 'Patient') ? 'selected' : '' ?>>Patient</option>
              <option value="Doctor" <?= (($_POST['role'] ?? '') === 'Doctor') ? 'selected' : '' ?>>Doctor</option>
              <option value="Pharmacist" <?= (($_POST['role'] ?? '') === 'Pharmacist') ? 'selected' : '' ?>>Pharmacist</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="dob">Date of Birth</label>
            <input class="form-control" type="date" id="dob" name="dob" value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
          </div>
      </div>

      <div class="form-group" style="margin-bottom:24px;">
        <label class="form-label" for="password">Password <span style="color:#ef4444">*</span></label>
        <div class="pw-wrap">
          <input class="form-control" type="password" id="password" name="password" required placeholder="Min. 6 characters">
          <button type="button" class="pw-toggle" onclick="togglePw('password', this)" title="Show/hide password">
            <span class="material-symbols-outlined" id="pw-icon">visibility</span>
          </button>
        </div>
      </div>
      
      <button class="btn btn-primary" type="submit" style="width:100%;">
        <span class="material-symbols-outlined">how_to_reg</span> Create Account
      </button>
    </form>
    <?php endif; ?>

    <div class="footer-link">
      Already have an account? <a href="<?= basePath() ?>/index.php">Sign In here</a>
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
