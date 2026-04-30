
<?php /* ==========================================
   ORIGINAL FILE: db.php
   ========================================== */ ?>

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
        header('Location: /Unified-Health-Record-main/index.php');
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
        header('Location: /Unified-Health-Record-main/index.php?error=unauthorized');
        exit;
    }
}

/**
 * Get the base URL path for the project.
 */
function basePath() {
    return '/Unified-Health-Record-main';
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



<?php /* ==========================================
   ORIGINAL FILE: index.php
   ========================================== */ ?>




<?php /* ==========================================
   ORIGINAL FILE: logout.php
   ========================================== */ ?>

<?php
session_start();
session_destroy();
header('Location: index.php?logout=1');
exit;



<?php /* ==========================================
   ORIGINAL FILE: register.php
   ========================================== */ ?>




<?php /* ==========================================
   ORIGINAL FILE: reset_password.php
   ========================================== */ ?>




<?php /* ==========================================
   ORIGINAL FILE: setup_passwords.php
   ========================================== */ ?>

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



<?php /* ==========================================
   ORIGINAL FILE: includes/sidebar.php
   ========================================== */ ?>

<?php
/**
 * sidebar.php — Role-aware navigation sidebar
 * Included by all feature pages.
 */
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$role = currentRole();
$userName = currentUserName();
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $userName), 0, 2)));

// Count unresolved flags for badge
$flagCount = 0;
if ($role === 'Patient') {
    $fc = $pdo->prepare('SELECT COUNT(*) FROM Medical_Flags WHERE PatientID = ? AND ResolvedAt IS NULL');
    $fc->execute([currentUserId()]);
    $flagCount = $fc->fetchColumn();
} elseif ($role === 'Doctor') {
    $fc = $pdo->query('SELECT COUNT(*) FROM Medical_Flags WHERE ResolvedAt IS NULL');
    $flagCount = $fc->fetchColumn();
}

// Count pending prescriptions for pharmacist badge
$pendingRx = 0;
if ($role === 'Pharmacist') {
    $pc = $pdo->query("SELECT COUNT(*) FROM Prescriptions WHERE Status = 'Pending'");
    $pendingRx = $pc->fetchColumn();
}
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-brand">
      <div class="brand-icon"><span class="material-symbols-outlined">local_hospital</span></div>
      HRec
    </div>
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?= htmlspecialchars($initials) ?></div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= htmlspecialchars($userName) ?></div>
        <div class="sidebar-user-role"><?= htmlspecialchars($role) ?></div>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php if ($role === 'Patient'): ?>
      <div class="nav-label">My Health</div>
      <a class="nav-link <?= $currentPage === 'patient_dashboard.php' ? 'active' : '' ?>" href="<?= basePath() ?>/pages/patient_dashboard.php">
        <span class="material-symbols-outlined">dashboard</span> Dashboard
      </a>
      <a class="nav-link <?= $currentPage === 'medication_log.php' ? 'active' : '' ?>" href="<?= basePath() ?>/pages/medication_log.php">
        <span class="material-symbols-outlined">medication</span> Medication Log
      </a>
      <a class="nav-link <?= $currentPage === 'cdss_alerts.php' ? 'active' : '' ?>" href="<?= basePath() ?>/pages/cdss_alerts.php">
        <span class="material-symbols-outlined">warning</span> CDSS Alerts
        <?php if ($flagCount > 0): ?><span class="nav-badge"><?= $flagCount ?></span><?php endif; ?>
      </a>
      <a class="nav-link <?= $currentPage === 'allergy_check.php' ? 'active' : '' ?>" href="<?= basePath() ?>/pages/allergy_check.php">
        <span class="material-symbols-outlined">health_and_safety</span> Allergy Check
      </a>

    <?php elseif ($role === 'Doctor'): ?>
      <div class="nav-label">Clinical</div>
      <a class="nav-link <?= $currentPage === 'doctor_overview.php' ? 'active' : '' ?>" href="<?= basePath() ?>/pages/doctor_overview.php">
        <span class="material-symbols-outlined">groups</span> Patient Overview
      </a>
      <a class="nav-link <?= $currentPage === 'prescribe.php' ? 'active' : '' ?>" href="<?= basePath() ?>/pages/prescribe.php">
        <span class="material-symbols-outlined">edit_note</span> Prescribe
      </a>
      <a class="nav-link <?= $currentPage === 'cdss_alerts.php' ? 'active' : '' ?>" href="<?= basePath() ?>/pages/cdss_alerts.php">
        <span class="material-symbols-outlined">warning</span> CDSS Alerts
        <?php if ($flagCount > 0): ?><span class="nav-badge"><?= $flagCount ?></span><?php endif; ?>
      </a>

    <?php elseif ($role === 'Pharmacist'): ?>
      <div class="nav-label">Pharmacy</div>
      <a class="nav-link <?= $currentPage === 'pharmacy.php' ? 'active' : '' ?>" href="<?= basePath() ?>/pages/pharmacy.php">
        <span class="material-symbols-outlined">local_pharmacy</span> Fulfillment
        <?php if ($pendingRx > 0): ?><span class="nav-badge"><?= $pendingRx ?></span><?php endif; ?>
      </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <a class="nav-link" href="<?= basePath() ?>/logout.php">
      <span class="material-symbols-outlined">logout</span> Sign Out
    </a>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>



<?php /* ==========================================
   ORIGINAL FILE: pages/allergy_check.php
   ========================================== */ ?>

<?php
require_once __DIR__ . '/../db.php';
requireRole('Patient');
$uid = currentUserId();

// Handle add allergy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_allergy'])) {
    $allergy = trim($_POST['allergy_name']);
    if ($allergy) {
        $pdo->prepare("UPDATE Health_Records SET KnownAllergies = JSON_ARRAY_APPEND(COALESCE(KnownAllergies, JSON_ARRAY()), '$', ?) WHERE UserID = ?")->execute([$allergy, $uid]);
        auditLog($pdo, 'ALLERGY_ADDED', 'Health_Records', $uid, "Added allergy: $allergy");
        header('Location: allergy_check.php?msg=added');
        exit;
    }
}

// Fetch allergies
$hr = $pdo->prepare('SELECT KnownAllergies FROM Health_Records WHERE UserID = ?');
$hr->execute([$uid]);
$record = $hr->fetch();
$allergies = json_decode($record['KnownAllergies'] ?? '[]', true) ?: [];

// Fetch active meds with warnings
$meds = $pdo->prepare("SELECT b.BrandName, g.GenericName, g.StopIfCondition, g.DietWarning, g.BlackBoxWarn
    FROM USER_MED_LOG uml
    JOIN Brands b ON uml.BrandID = b.BrandID
    JOIN Generics g ON b.GenericID = g.GenericID
    WHERE uml.UserID = ? AND uml.Status = 'Active'");
$meds->execute([$uid]);
$activeMeds = $meds->fetchAll();

// Cross-reference: check if any allergy keyword appears in active drug warnings
$crossRefs = [];
foreach ($activeMeds as $med) {
    foreach ($allergies as $allergy) {
        $allergyLower = strtolower($allergy);
        $stop = strtolower($med['StopIfCondition'] ?? '');
        $diet = strtolower($med['DietWarning'] ?? '');
        if (strpos($stop, $allergyLower) !== false || strpos($diet, $allergyLower) !== false) {
            $crossRefs[] = ['allergy' => $allergy, 'brand' => $med['BrandName'], 'generic' => $med['GenericName'], 'warning' => $med['StopIfCondition']];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Allergy Check — HRec</title>
  <link rel="stylesheet" href="<?= basePath() ?>/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div>
        <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')"><span class="material-symbols-outlined">menu</span></button>
        <span class="topbar-title">Allergy Cross-Reference</span>
        <div class="topbar-subtitle">Check your allergies against active medications</div>
      </div>
    </header>
    <div class="page-content">
      <?php if (isset($_GET['msg'])): ?>
        <div class="toast-container"><div class="toast"><span class="material-symbols-outlined" style="color:#10b981">check_circle</span>Allergy added.</div></div>
      <?php endif; ?>

      <?php if (!empty($crossRefs)): ?>
        <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:24px;">
          <?php foreach ($crossRefs as $cr): ?>
            <div class="alert alert-critical">
              <span class="material-symbols-outlined alert-icon">emergency</span>
              <div class="alert-content">
                <div class="alert-title">Allergy Conflict: <?= htmlspecialchars($cr['allergy']) ?></div>
                <div class="alert-text">
                  Your allergy to <strong><?= htmlspecialchars($cr['allergy']) ?></strong> may conflict with active medication
                  <strong><?= htmlspecialchars($cr['brand']) ?></strong> (<?= htmlspecialchars($cr['generic']) ?>).
                  <br><em><?= htmlspecialchars(substr($cr['warning'], 0, 200)) ?></em>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="grid-2 gap-md">
        <!-- Current Allergies -->
        <div class="card stagger-1">
          <div class="card-header"><h2 class="card-title"><span class="material-symbols-outlined">health_and_safety</span> Your Allergies</h2></div>
          <div class="card-body">
            <?php if (empty($allergies)): ?>
              <div class="empty-state"><p>No allergies recorded.</p></div>
            <?php else: ?>
              <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <?php foreach ($allergies as $a): ?>
                  <span class="badge badge-critical" style="font-size:.8rem;padding:6px 14px;"><?= htmlspecialchars($a) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <form method="POST" style="margin-top:20px;display:flex;gap:10px;">
              <input type="hidden" name="add_allergy" value="1">
              <input class="form-control" type="text" name="allergy_name" placeholder="Add new allergy…" required style="flex:1">
              <button class="btn btn-primary btn-sm" type="submit"><span class="material-symbols-outlined" style="font-size:16px">add</span></button>
            </form>
          </div>
        </div>

        <!-- Active Meds with Warnings -->
        <div class="card stagger-2">
          <div class="card-header"><h2 class="card-title"><span class="material-symbols-outlined">medication</span> Active Medication Warnings</h2></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach ($activeMeds as $med): ?>
              <div style="padding:12px;background:var(--bg);border-radius:var(--radius-sm);border-left:3px solid <?= $med['BlackBoxWarn'] ? 'var(--danger)' : 'var(--border)' ?>">
                <div class="fw-600"><?= htmlspecialchars($med['BrandName']) ?> <span class="text-muted text-sm">(<?= htmlspecialchars($med['GenericName']) ?>)</span></div>
                <?php if ($med['BlackBoxWarn']): ?><span class="badge badge-critical" style="margin:6px 0">⚠ BLACK BOX WARNING</span><?php endif; ?>
                <?php if ($med['DietWarning']): ?><div class="text-xs text-muted mt-16">🍽️ <?= htmlspecialchars($med['DietWarning']) ?></div><?php endif; ?>
              </div>
            <?php endforeach; ?>
            <?php if (empty($activeMeds)): ?><div class="text-sm text-muted">No active medications.</div><?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="<?= basePath() ?>/js/app.js"></script>
</body>
</html>



<?php /* ==========================================
   ORIGINAL FILE: pages/cdss_alerts.php
   ========================================== */ ?>

<?php
require_once __DIR__ . '/../db.php';
requireRole(['Patient', 'Doctor']);
$role = currentRole();

// Resolve flag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_flag'])) {
    $flagId = intval($_POST['flag_id']);
    $pdo->prepare('UPDATE Medical_Flags SET ResolvedAt = NOW() WHERE FlagID = ?')->execute([$flagId]);
    auditLog($pdo, 'FLAG_RESOLVED', 'Medical_Flags', $flagId, "Resolved by " . currentUserName());
    header('Location: cdss_alerts.php?msg=resolved');
    exit;
}

// Fetch active conflicts from VIEW
if ($role === 'Patient') {
    $conflicts = $pdo->prepare('SELECT * FROM CDSS_Active_Conflicts WHERE PatientID = ?');
    $conflicts->execute([currentUserId()]);
} else {
    $conflicts = $pdo->query('SELECT * FROM CDSS_Active_Conflicts ORDER BY FIELD(Severity,"CRITICAL","HIGH","MODERATE","LOW")');
}
$conflictList = $conflicts->fetchAll();

// Fetch medical flags
if ($role === 'Patient') {
    $flags = $pdo->prepare('SELECT * FROM Medical_Flags WHERE PatientID = ? ORDER BY ResolvedAt IS NULL DESC, CreatedAt DESC');
    $flags->execute([currentUserId()]);
} else {
    $flags = $pdo->query('SELECT mf.*, u.FullName FROM Medical_Flags mf JOIN Users u ON mf.PatientID = u.UserID ORDER BY mf.ResolvedAt IS NULL DESC, mf.CreatedAt DESC');
}
$flagList = $flags->fetchAll();

$severityClass = ['CRITICAL' => 'alert-critical', 'HIGH' => 'alert-high', 'MODERATE' => 'alert-moderate', 'LOW' => 'alert-low'];
$severityIcon = ['CRITICAL' => 'error', 'HIGH' => 'warning', 'MODERATE' => 'warning_amber', 'LOW' => 'info'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CDSS Alerts — HRec</title>
  <link rel="stylesheet" href="<?= basePath() ?>/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div>
        <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')"><span class="material-symbols-outlined">menu</span></button>
        <span class="topbar-title">CDSS Alert Engine</span>
        <div class="topbar-subtitle">Clinical Decision Support — Drug Conflict Detection</div>
      </div>
      <div class="topbar-actions"><span class="badge badge-active">Engine Active</span></div>
    </header>
    <div class="page-content">
      <?php if (isset($_GET['msg'])): ?>
        <div class="toast-container"><div class="toast"><span class="material-symbols-outlined" style="color:#10b981">check_circle</span>Flag resolved.</div></div>
      <?php endif; ?>

      <!-- Active Conflicts from VIEW -->
      <h2 class="page-title">Active Drug Conflicts</h2>
      <p class="page-desc">Real-time conflicts detected from the CDSS_Active_Conflicts VIEW.</p>

      <?php if (empty($conflictList)): ?>
        <div class="card mb-24"><div class="card-body" style="text-align:center;padding:40px;">
          <span class="material-symbols-outlined" style="font-size:48px;color:var(--success);opacity:.5">verified</span>
          <p class="text-sm text-muted mt-16">No active drug conflicts detected. All clear.</p>
        </div></div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:16px;" class="mb-24">
          <?php foreach ($conflictList as $i => $c): ?>
            <div class="alert <?= $severityClass[$c['Severity']] ?? 'alert-low' ?> stagger-<?= min($i+1, 5) ?>">
              <span class="material-symbols-outlined alert-icon"><?= $severityIcon[$c['Severity']] ?? 'info' ?></span>
              <div class="alert-content">
                <div class="alert-title flex justify-between items-center">
                  <?= htmlspecialchars($c['Severity']) ?> Interaction
                  <span class="badge badge-<?= strtolower($c['Severity']) ?>"><?= $c['Severity'] ?></span>
                </div>
                <div class="alert-text">
                  <strong><?= htmlspecialchars($c['Drug1_Brand']) ?></strong> (<?= htmlspecialchars($c['Drug1_Generic']) ?>)
                  &amp; <strong><?= htmlspecialchars($c['Drug2_Brand']) ?></strong> (<?= htmlspecialchars($c['Drug2_Generic']) ?>)
                  <?php if ($role === 'Doctor'): ?> — Patient: <strong><?= htmlspecialchars($c['PatientName']) ?></strong><?php endif; ?>
                </div>
                <div class="text-xs text-muted mt-16"><?= htmlspecialchars($c['AlertMessage']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Medical Flags History -->
      <div class="card stagger-3">
        <div class="card-header">
          <h2 class="card-title"><span class="material-symbols-outlined">flag</span> Medical Flags</h2>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr>
              <?php if ($role === 'Doctor'): ?><th>Patient</th><?php endif; ?>
              <th>Type</th><th>Severity</th><th>Message</th><th>Created</th><th>Status</th><th></th>
            </tr></thead>
            <tbody>
              <?php foreach ($flagList as $f): ?>
                <tr<?= $f['ResolvedAt'] ? ' style="opacity:.5"' : '' ?>>
                  <?php if ($role === 'Doctor'): ?><td class="fw-600"><?= htmlspecialchars($f['FullName'] ?? '') ?></td><?php endif; ?>
                  <td><span class="badge badge-low"><?= htmlspecialchars($f['TriggerType']) ?></span></td>
                  <td><span class="badge badge-<?= strtolower($f['Severity']) ?>"><?= $f['Severity'] ?></span></td>
                  <td class="text-sm" style="max-width:400px"><?= htmlspecialchars(substr($f['Message'], 0, 150)) ?>…</td>
                  <td class="text-sm text-muted"><?= date('M j, Y', strtotime($f['CreatedAt'])) ?></td>
                  <td>
                    <?php if ($f['ResolvedAt']): ?>
                      <span class="badge badge-completed">Resolved</span>
                    <?php else: ?>
                      <span class="badge badge-critical">Active</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!$f['ResolvedAt']): ?>
                      <form method="POST" style="display:inline">
                        <input type="hidden" name="resolve_flag" value="1">
                        <input type="hidden" name="flag_id" value="<?= $f['FlagID'] ?>">
                        <button class="btn btn-sm btn-outline" type="submit" data-confirm="Mark this flag as resolved?">Resolve</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="<?= basePath() ?>/js/app.js"></script>
</body>
</html>



<?php /* ==========================================
   ORIGINAL FILE: pages/doctor_overview.php
   ========================================== */ ?>

<?php
require_once __DIR__ . '/../db.php';
requireRole('Doctor');

// Fetch all patients with key metrics
$patients = $pdo->query("
    SELECT u.UserID, u.FullName, u.DOB, u.Email,
           hr.BMI, hr.BloodType, hr.Sex, hr.KnownAllergies, hr.ChronicConditions,
           (SELECT COUNT(*) FROM USER_MED_LOG WHERE UserID = u.UserID AND Status='Active') AS active_meds,
           (SELECT COUNT(*) FROM Medical_Flags WHERE PatientID = u.UserID AND ResolvedAt IS NULL) AS unresolved_flags
    FROM Users u
    LEFT JOIN Health_Records hr ON u.UserID = hr.UserID
    WHERE u.Role = 'Patient'
    ORDER BY unresolved_flags DESC, u.FullName ASC
");
$patientList = $patients->fetchAll();

// Detail view for selected patient
$selectedPatient = null;
$selectedMeds = [];
$selectedFlags = [];
if (isset($_GET['pid'])) {
    $pid = intval($_GET['pid']);
    $sp = $pdo->prepare('SELECT u.*, hr.* FROM Users u LEFT JOIN Health_Records hr ON u.UserID = hr.UserID WHERE u.UserID = ? AND u.Role = "Patient"');
    $sp->execute([$pid]);
    $selectedPatient = $sp->fetch();

    if ($selectedPatient) {
        $sm = $pdo->prepare('SELECT uml.*, b.BrandName, g.GenericName, g.DrugClass FROM USER_MED_LOG uml JOIN Brands b ON uml.BrandID = b.BrandID JOIN Generics g ON b.GenericID = g.GenericID WHERE uml.UserID = ? ORDER BY uml.Status ASC, uml.DateAdded DESC');
        $sm->execute([$pid]);
        $selectedMeds = $sm->fetchAll();

        $sf = $pdo->prepare('SELECT * FROM Medical_Flags WHERE PatientID = ? AND ResolvedAt IS NULL ORDER BY CreatedAt DESC');
        $sf->execute([$pid]);
        $selectedFlags = $sf->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Overview — HRec</title>
  <link rel="stylesheet" href="<?= basePath() ?>/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div>
        <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')"><span class="material-symbols-outlined">menu</span></button>
        <span class="topbar-title">Patient Overview</span>
        <div class="topbar-subtitle">Dr. <?= htmlspecialchars(currentUserName()) ?></div>
      </div>
    </header>
    <div class="page-content">
      <!-- Patient List -->
      <div class="card mb-24 stagger-1">
        <div class="card-header"><h2 class="card-title"><span class="material-symbols-outlined">groups</span> All Patients</h2></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Patient</th><th>DOB</th><th>BMI</th><th>Blood</th><th>Active Meds</th><th>Alerts</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($patientList as $p): ?>
                <tr style="<?= (isset($_GET['pid']) && intval($_GET['pid']) === $p['UserID']) ? 'background:var(--accent-light)' : '' ?>">
                  <td class="fw-600"><?= htmlspecialchars($p['FullName']) ?></td>
                  <td class="text-sm text-muted"><?= $p['DOB'] ? date('M j, Y', strtotime($p['DOB'])) : '—' ?></td>
                  <td class="fw-600"><?= $p['BMI'] ?? '—' ?></td>
                  <td><?= htmlspecialchars($p['BloodType'] ?? '—') ?></td>
                  <td><span class="badge badge-active"><?= $p['active_meds'] ?></span></td>
                  <td>
                    <?php if ($p['unresolved_flags'] > 0): ?>
                      <span class="badge badge-critical"><?= $p['unresolved_flags'] ?> ⚠</span>
                    <?php else: ?>
                      <span class="badge badge-completed">0</span>
                    <?php endif; ?>
                  </td>
                  <td><a href="?pid=<?= $p['UserID'] ?>" class="btn btn-sm btn-outline">View</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if ($selectedPatient): ?>
      <!-- Detail Panel -->
      <h2 class="page-title"><?= htmlspecialchars($selectedPatient['FullName']) ?></h2>
      <p class="page-desc"><?= htmlspecialchars($selectedPatient['Sex'] ?? '') ?> · <?= htmlspecialchars($selectedPatient['BloodType'] ?? '') ?> · BMI: <?= $selectedPatient['BMI'] ?? '—' ?></p>

      <?php if (!empty($selectedFlags)): ?>
        <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:20px;">
          <?php foreach ($selectedFlags as $f): ?>
            <div class="alert alert-<?= strtolower($f['Severity']) === 'critical' ? 'critical' : (strtolower($f['Severity']) === 'high' ? 'high' : 'moderate') ?>">
              <span class="material-symbols-outlined alert-icon">warning</span>
              <div class="alert-content">
                <div class="alert-title"><?= htmlspecialchars($f['Severity']) ?> — <?= htmlspecialchars($f['TriggerType']) ?></div>
                <div class="alert-text"><?= htmlspecialchars(substr($f['Message'], 0, 300)) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="grid-2 gap-md">
        <div class="card stagger-2">
          <div class="card-header"><h2 class="card-title"><span class="material-symbols-outlined">health_and_safety</span> Allergies & Conditions</h2></div>
          <div class="card-body">
            <div class="form-label mb-20">Known Allergies</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">
              <?php foreach (json_decode($selectedPatient['KnownAllergies'] ?? '[]', true) ?: [] as $a): ?>
                <span class="badge badge-critical"><?= htmlspecialchars($a) ?></span>
              <?php endforeach; ?>
            </div>
            <div class="form-label mb-20">Chronic Conditions</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
              <?php foreach (json_decode($selectedPatient['ChronicConditions'] ?? '[]', true) ?: [] as $c): ?>
                <span class="badge badge-moderate"><?= htmlspecialchars($c) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="card stagger-3">
          <div class="card-header"><h2 class="card-title"><span class="material-symbols-outlined">medication</span> Medication Log</h2></div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Brand</th><th>Generic</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($selectedMeds as $m): ?>
                  <tr<?= $m['Status'] !== 'Active' ? ' style="opacity:.5"' : '' ?>>
                    <td class="fw-600"><?= htmlspecialchars($m['BrandName']) ?></td>
                    <td class="text-sm"><?= htmlspecialchars($m['GenericName']) ?></td>
                    <td><span class="badge badge-<?= strtolower($m['Status']) ?>"><?= $m['Status'] ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div style="margin-top:16px">
        <a href="prescribe.php?pid=<?= $selectedPatient['UserID'] ?>" class="btn btn-primary"><span class="material-symbols-outlined">edit_note</span> Prescribe for this Patient</a>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
<script src="<?= basePath() ?>/js/app.js"></script>
</body>
</html>



<?php /* ==========================================
   ORIGINAL FILE: pages/medication_log.php
   ========================================== */ ?>

<?php
require_once __DIR__ . '/../db.php';
requireRole('Patient');
$uid = currentUserId();

// Handle add medication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_med'])) {
    $brandId = intval($_POST['brand_id']);
    $notes = trim($_POST['notes'] ?? '');
    $stmt = $pdo->prepare("INSERT INTO USER_MED_LOG (UserID, BrandID, PrescribedBy, Status, Notes) VALUES (?, ?, NULL, 'Active', ?)");
    $stmt->execute([$uid, $brandId, $notes]);
    auditLog($pdo, 'MED_ADDED', 'USER_MED_LOG', $pdo->lastInsertId(), "Self-logged BrandID: $brandId");
    header('Location: medication_log.php?msg=added');
    exit;
}

// Handle discontinue
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['discontinue'])) {
    $logId = intval($_POST['log_id']);
    $stmt = $pdo->prepare("UPDATE USER_MED_LOG SET Status = 'Discontinued' WHERE LogID = ? AND UserID = ?");
    $stmt->execute([$logId, $uid]);
    auditLog($pdo, 'MED_DISCONTINUED', 'USER_MED_LOG', $logId, "Discontinued by patient");
    header('Location: medication_log.php?msg=discontinued');
    exit;
}

// Fetch medication log
$meds = $pdo->prepare('SELECT uml.*, b.BrandName, b.Manufacturer, g.GenericName, g.DrugClass, d.UserID as DocUID, u2.FullName as DoctorName
    FROM USER_MED_LOG uml
    JOIN Brands b ON uml.BrandID = b.BrandID
    JOIN Generics g ON b.GenericID = g.GenericID
    LEFT JOIN Doctors d ON uml.PrescribedBy = d.UserID
    LEFT JOIN Users u2 ON d.UserID = u2.UserID
    WHERE uml.UserID = ?
    ORDER BY uml.Status ASC, uml.DateAdded DESC');
$meds->execute([$uid]);
$medList = $meds->fetchAll();

// Fetch all brands grouped by generic for the add form
$brands = $pdo->query('SELECT b.BrandID, b.BrandName, b.Manufacturer, g.GenericName FROM Brands b JOIN Generics g ON b.GenericID = g.GenericID ORDER BY g.GenericName, b.BrandName');
$brandOptions = $brands->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Medication Log — HRec</title>
  <link rel="stylesheet" href="<?= basePath() ?>/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div>
        <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')"><span class="material-symbols-outlined">menu</span></button>
        <span class="topbar-title">Medication Log</span>
        <div class="topbar-subtitle">Track your active and past medications</div>
      </div>
    </header>
    <div class="page-content">
      <?php if (isset($_GET['msg'])): ?>
        <div class="toast-container"><div class="toast"><span class="material-symbols-outlined" style="color:#10b981">check_circle</span>
          <?= $_GET['msg'] === 'added' ? 'Medication added!' : 'Medication discontinued.' ?>
        </div></div>
      <?php endif; ?>

      <!-- Add Medication Form -->
      <div class="card mb-24 stagger-1">
        <div class="card-header"><h2 class="card-title"><span class="material-symbols-outlined">add_circle</span> Log New Medication</h2></div>
        <div class="card-body">
          <form method="POST" class="form-row" style="align-items:flex-end;gap:16px;">
            <input type="hidden" name="add_med" value="1">
            <div class="form-group" style="flex:2">
              <label class="form-label" for="brand_id">Brand (Generic)</label>
              <select class="form-control" name="brand_id" id="brand_id" required>
                <option value="">Select medication…</option>
                <?php $lastGeneric = ''; foreach ($brandOptions as $bo): ?>
                  <?php if ($bo['GenericName'] !== $lastGeneric): $lastGeneric = $bo['GenericName']; ?>
                    <?php if ($bo !== $brandOptions[0]): ?></optgroup><?php endif; ?>
                    <optgroup label="<?= htmlspecialchars($bo['GenericName']) ?>">
                  <?php endif; ?>
                  <option value="<?= $bo['BrandID'] ?>"><?= htmlspecialchars($bo['BrandName']) ?> (<?= htmlspecialchars($bo['Manufacturer']) ?>)</option>
                <?php endforeach; ?>
                </optgroup>
              </select>
            </div>
            <div class="form-group" style="flex:1">
              <label class="form-label" for="notes">Notes</label>
              <input class="form-control" type="text" name="notes" id="notes" placeholder="e.g. Take with food">
            </div>
            <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">add</span> Add</button>
          </form>
        </div>
      </div>

      <!-- Medication Table -->
      <div class="card stagger-2">
        <div class="card-header">
          <h2 class="card-title"><span class="material-symbols-outlined">medication</span> Your Medications</h2>
          <span class="badge badge-active"><?= count(array_filter($medList, fn($m) => $m['Status'] === 'Active')) ?> Active</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr>
              <th>Brand</th><th>Generic</th><th>Class</th><th>Prescribed By</th><th>Date Added</th><th>Status</th><th>Notes</th><th></th>
            </tr></thead>
            <tbody>
              <?php if (empty($medList)): ?>
                <tr><td colspan="8" class="text-center text-muted" style="padding:32px">No medications logged yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($medList as $m): ?>
                <tr<?= $m['Status'] !== 'Active' ? ' style="opacity:.5"' : '' ?>>
                  <td class="fw-600"><?= htmlspecialchars($m['BrandName']) ?></td>
                  <td class="text-sm"><?= htmlspecialchars($m['GenericName']) ?></td>
                  <td><span class="badge badge-low" style="font-size:.6rem"><?= htmlspecialchars($m['DrugClass'] ?? '—') ?></span></td>
                  <td class="text-sm"><?= $m['DoctorName'] ? htmlspecialchars($m['DoctorName']) : '<span class="text-muted">Self</span>' ?></td>
                  <td class="text-sm text-muted"><?= date('M j, Y', strtotime($m['DateAdded'])) ?></td>
                  <td><span class="badge badge-<?= strtolower($m['Status']) ?>"><?= $m['Status'] ?></span></td>
                  <td class="text-sm text-muted"><?= htmlspecialchars($m['Notes'] ?? '') ?></td>
                  <td>
                    <?php if ($m['Status'] === 'Active'): ?>
                      <form method="POST" style="display:inline">
                        <input type="hidden" name="discontinue" value="1">
                        <input type="hidden" name="log_id" value="<?= $m['LogID'] ?>">
                        <button class="btn btn-sm btn-outline" type="submit" data-confirm="Discontinue this medication?">
                          <span class="material-symbols-outlined" style="font-size:16px">cancel</span>
                        </button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="<?= basePath() ?>/js/app.js"></script>
</body>
</html>



<?php /* ==========================================
   ORIGINAL FILE: pages/patient_dashboard.php
   ========================================== */ ?>

<?php
require_once __DIR__ . '/../db.php';
requireRole('Patient');

$uid = currentUserId();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $weight = floatval($_POST['weight']);
    $height = floatval($_POST['height']);
    if ($weight > 0 && $height > 0) {
        $stmt = $pdo->prepare('UPDATE Health_Records SET WeightKG = ?, HeightCM = ? WHERE UserID = ?');
        $stmt->execute([$weight, $height, $uid]);
        auditLog($pdo, 'PROFILE_UPDATE', 'Health_Records', $uid, "Weight: {$weight}kg, Height: {$height}cm");
        header('Location: patient_dashboard.php?msg=updated');
        exit;
    }
}

// Fetch health record
$hr = $pdo->prepare('SELECT hr.*, u.FullName, u.DOB as UserDOB, u.Email FROM Health_Records hr JOIN Users u ON hr.UserID = u.UserID WHERE hr.UserID = ?');
$hr->execute([$uid]);
$record = $hr->fetch();

// Calculate age
$age = $record ? floor((time() - strtotime($record['UserDOB'])) / 31557600) : '—';

// BMI category
function bmiCategory($bmi) {
    if (!$bmi) return ['Unknown', 'badge-discontinued'];
    if ($bmi < 18.5) return ['Underweight', 'badge-bmi-underweight'];
    if ($bmi < 25)   return ['Normal', 'badge-bmi-normal'];
    if ($bmi < 30)   return ['Overweight', 'badge-bmi-overweight'];
    return ['Obese', 'badge-bmi-obese'];
}
$bmiCat = bmiCategory($record['BMI'] ?? null);

// Parse JSON fields
$allergies = $record ? json_decode($record['KnownAllergies'] ?? '[]', true) : [];
$conditions = $record ? json_decode($record['ChronicConditions'] ?? '[]', true) : [];

// Active meds count
$mc = $pdo->prepare("SELECT COUNT(*) FROM USER_MED_LOG WHERE UserID = ? AND Status = 'Active'");
$mc->execute([$uid]);
$activeMeds = $mc->fetchColumn();

// Unresolved flags
$fc = $pdo->prepare('SELECT COUNT(*) FROM Medical_Flags WHERE PatientID = ? AND ResolvedAt IS NULL');
$fc->execute([$uid]);
$activeFlags = $fc->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — HRec</title>
  <link rel="stylesheet" href="<?= basePath() ?>/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div>
        <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
          <span class="material-symbols-outlined">menu</span>
        </button>
        <span class="topbar-title">Health Dashboard</span>
        <div class="topbar-subtitle">Your unified health record</div>
      </div>
      <div class="topbar-actions">
        <span class="text-sm text-muted"><?= date('D, M j, Y') ?></span>
      </div>
    </header>

    <div class="page-content">
      <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
        <div class="toast-container"><div class="toast"><span class="material-symbols-outlined" style="color:#10b981">check_circle</span>Profile updated successfully!</div></div>
      <?php endif; ?>

      <?php if (!$record): ?>
        <div class="card"><div class="card-body empty-state">
          <span class="material-symbols-outlined">person_add</span>
          <p>No health record found. Please contact your administrator.</p>
        </div></div>
      <?php else: ?>

      <!-- Stat Cards -->
      <div class="stat-grid mb-24">
        <div class="stat-card stagger-1">
          <div class="stat-label">BMI</div>
          <div class="stat-value"><?= $record['BMI'] ?? '—' ?></div>
          <span class="badge <?= $bmiCat[1] ?>" data-bmi="<?= $record['BMI'] ?? 0 ?>"><?= $bmiCat[0] ?></span>
        </div>
        <div class="stat-card stagger-2">
          <div class="stat-label">Blood Type</div>
          <div class="stat-value"><?= htmlspecialchars($record['BloodType'] ?? '—') ?></div>
          <div class="stat-sub"><?= htmlspecialchars($record['Sex'] ?? '') ?>, Age <?= $age ?></div>
        </div>
        <div class="stat-card stagger-3">
          <div class="stat-label">Active Medications</div>
          <div class="stat-value"><?= $activeMeds ?></div>
          <a href="medication_log.php" class="text-sm" style="color:var(--accent)">View log →</a>
        </div>
        <div class="stat-card stagger-4">
          <div class="stat-label">Active Alerts</div>
          <div class="stat-value" style="color: <?= $activeFlags > 0 ? 'var(--danger)' : 'var(--success)' ?>"><?= $activeFlags ?></div>
          <?php if ($activeFlags > 0): ?>
            <a href="cdss_alerts.php" class="text-sm" style="color:var(--danger)">Review now →</a>
          <?php else: ?>
            <div class="stat-sub text-success">All clear</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="grid-2 gap-md">
        <!-- Health Profile Card -->
        <div class="card stagger-2">
          <div class="card-header">
            <h2 class="card-title"><span class="material-symbols-outlined">monitor_heart</span> Health Profile</h2>
          </div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
            <div class="flex justify-between items-center" style="padding:10px 14px;background:var(--bg);border-radius:var(--radius-sm);">
              <span class="text-sm text-muted">Date of Birth</span>
              <span class="fw-600"><?= htmlspecialchars($record['DOB'] ?? '—') ?></span>
            </div>
            <div class="flex justify-between items-center" style="padding:10px 14px;background:var(--bg);border-radius:var(--radius-sm);">
              <span class="text-sm text-muted">Weight</span>
              <span class="fw-600"><?= $record['WeightKG'] ?? '—' ?> kg</span>
            </div>
            <div class="flex justify-between items-center" style="padding:10px 14px;background:var(--bg);border-radius:var(--radius-sm);">
              <span class="text-sm text-muted">Height</span>
              <span class="fw-600"><?= $record['HeightCM'] ?? '—' ?> cm</span>
            </div>
            <div class="flex justify-between items-center" style="padding:10px 14px;background:var(--bg);border-radius:var(--radius-sm);">
              <span class="text-sm text-muted">Last Updated</span>
              <span class="fw-600 text-sm"><?= htmlspecialchars($record['LastUpdated'] ?? '—') ?></span>
            </div>
          </div>
        </div>

        <!-- Update Weight/Height -->
        <div class="card stagger-3">
          <div class="card-header">
            <h2 class="card-title"><span class="material-symbols-outlined">edit</span> Update Vitals</h2>
          </div>
          <div class="card-body">
            <form method="POST" style="display:flex;flex-direction:column;gap:16px;">
              <input type="hidden" name="update_profile" value="1">
              <div class="form-group">
                <label class="form-label" for="weight">Weight (kg)</label>
                <input class="form-control" type="number" step="0.1" id="weight" name="weight" value="<?= $record['WeightKG'] ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="height">Height (cm)</label>
                <input class="form-control" type="number" step="0.1" id="height" name="height" value="<?= $record['HeightCM'] ?>" required>
              </div>
              <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">save</span> Save Changes</button>
            </form>
          </div>
        </div>
      </div>

      <!-- Allergies & Conditions -->
      <div class="grid-2 gap-md mt-20">
        <div class="card stagger-4">
          <div class="card-header"><h2 class="card-title"><span class="material-symbols-outlined">health_and_safety</span> Known Allergies</h2></div>
          <div class="card-body">
            <?php if (empty($allergies)): ?>
              <div class="text-sm text-muted">No allergies recorded.</div>
            <?php else: ?>
              <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ($allergies as $a): ?>
                  <span class="badge badge-critical"><?= htmlspecialchars($a) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="card stagger-5">
          <div class="card-header"><h2 class="card-title"><span class="material-symbols-outlined">medical_information</span> Chronic Conditions</h2></div>
          <div class="card-body">
            <?php if (empty($conditions)): ?>
              <div class="text-sm text-muted">No chronic conditions recorded.</div>
            <?php else: ?>
              <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ($conditions as $c): ?>
                  <span class="badge badge-moderate"><?= htmlspecialchars($c) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php endif; ?>
    </div>
  </main>
</div>
<script src="<?= basePath() ?>/js/app.js"></script>
</body>
</html>



<?php /* ==========================================
   ORIGINAL FILE: pages/pharmacy.php
   ========================================== */ ?>

<?php
require_once __DIR__ . '/../db.php';
requireRole('Pharmacist');

// Handle ACID fulfillment
$fulfillError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fulfill'])) {
    $rxId = intval($_POST['rx_id']);
    try {
        $pdo->beginTransaction();

        // 1. Get the prescription
        $rx = $pdo->prepare("SELECT * FROM Prescriptions WHERE PrescriptionID = ? AND Status = 'Pending'");
        $rx->execute([$rxId]);
        $prescription = $rx->fetch();
        if (!$prescription) throw new Exception('Prescription not found or already fulfilled.');

        // 2. Parse items and decrement stock
        $items = json_decode($prescription['Items'], true);
        foreach ($items as $item) {
            $brandId = $item['brand_id'];
            $upd = $pdo->prepare('UPDATE Brands SET Stock = Stock - 1 WHERE BrandID = ? AND Stock > 0');
            $upd->execute([$brandId]);
            if ($upd->rowCount() === 0) {
                throw new Exception("Insufficient stock for brand ID $brandId ({$item['brand_name']}).");
            }
        }

        // 3. Mark prescription as fulfilled
        $pdo->prepare("UPDATE Prescriptions SET Status = 'Fulfilled', FulfilledAt = NOW() WHERE PrescriptionID = ?")
            ->execute([$rxId]);

        // 4. Audit log
        auditLog($pdo, 'PRESCRIPTION_FULFILLED', 'Prescriptions', $rxId,
            "Fulfilled by " . currentUserName() . ". Items: " . count($items));

        $pdo->commit();
        header('Location: pharmacy.php?msg=fulfilled');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $fulfillError = $e->getMessage();
    }
}

// Fetch prescriptions
$rxList = $pdo->query("
    SELECT p.*, u_doc.FullName AS DoctorName, u_pat.FullName AS PatientName
    FROM Prescriptions p
    JOIN Users u_doc ON p.DoctorID = u_doc.UserID
    JOIN Users u_pat ON p.PatientID = u_pat.UserID
    ORDER BY FIELD(p.Status, 'Pending', 'Fulfilled', 'Cancelled'), p.IssuedAt DESC
")->fetchAll();

// Trend data: most prescribed brands
$trends = $pdo->query("
    SELECT b.BrandName, g.GenericName, COUNT(*) AS times_prescribed
    FROM USER_MED_LOG uml
    JOIN Brands b ON uml.BrandID = b.BrandID
    JOIN Generics g ON b.GenericID = g.GenericID
    GROUP BY b.BrandID
    ORDER BY times_prescribed DESC
    LIMIT 8
")->fetchAll();

$maxTrend = !empty($trends) ? max(array_column($trends, 'times_prescribed')) : 1;

// Stock overview
$stockList = $pdo->query("SELECT b.BrandID, b.BrandName, b.Stock, g.GenericName FROM Brands b JOIN Generics g ON b.GenericID = g.GenericID ORDER BY b.Stock ASC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pharmacy — HRec</title>
  <link rel="stylesheet" href="<?= basePath() ?>/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div>
        <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')"><span class="material-symbols-outlined">menu</span></button>
        <span class="topbar-title">Pharmacy Fulfillment</span>
        <div class="topbar-subtitle">ACID-compliant prescription processing</div>
      </div>
    </header>
    <div class="page-content">
      <?php if (isset($_GET['msg'])): ?>
        <div class="toast-container"><div class="toast"><span class="material-symbols-outlined" style="color:#10b981">check_circle</span>Prescription fulfilled! Stock decremented.</div></div>
      <?php endif; ?>
      <?php if ($fulfillError): ?>
        <div class="alert alert-critical mb-24"><span class="material-symbols-outlined alert-icon">error</span>
          <div class="alert-content"><div class="alert-title">Transaction Rolled Back</div><div class="alert-text"><?= htmlspecialchars($fulfillError) ?></div></div>
        </div>
      <?php endif; ?>

      <!-- Stat Cards -->
      <div class="stat-grid mb-24">
        <div class="stat-card stagger-1">
          <div class="stat-label">Pending</div>
          <div class="stat-value" style="color:var(--warning)"><?= count(array_filter($rxList, fn($r) => $r['Status'] === 'Pending')) ?></div>
        </div>
        <div class="stat-card stagger-2">
          <div class="stat-label">Fulfilled</div>
          <div class="stat-value" style="color:var(--success)"><?= count(array_filter($rxList, fn($r) => $r['Status'] === 'Fulfilled')) ?></div>
        </div>
        <div class="stat-card stagger-3">
          <div class="stat-label">Total Prescriptions</div>
          <div class="stat-value"><?= count($rxList) ?></div>
        </div>
      </div>

      <!-- Prescriptions Table -->
      <div class="card mb-24 stagger-2">
        <div class="card-header"><h2 class="card-title"><span class="material-symbols-outlined">receipt_long</span> Prescriptions</h2></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Rx ID</th><th>Patient</th><th>Doctor</th><th>Items</th><th>Issued</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($rxList as $r):
                $items = json_decode($r['Items'], true);
                $itemStr = implode(', ', array_map(fn($it) => ($it['brand_name'] ?? 'Brand#'.$it['brand_id']) . ' ' . ($it['dosage'] ?? ''), $items));
              ?>
                <tr<?= $r['Status'] !== 'Pending' ? ' style="opacity:.5"' : '' ?>>
                  <td class="fw-600">#<?= $r['PrescriptionID'] ?></td>
                  <td class="fw-600"><?= htmlspecialchars($r['PatientName']) ?></td>
                  <td class="text-sm"><?= htmlspecialchars($r['DoctorName']) ?></td>
                  <td class="text-sm"><?= htmlspecialchars($itemStr) ?></td>
                  <td class="text-sm text-muted"><?= date('M j, Y H:i', strtotime($r['IssuedAt'])) ?></td>
                  <td><span class="badge badge-<?= strtolower($r['Status']) ?>"><?= $r['Status'] ?></span></td>
                  <td>
                    <?php if ($r['Status'] === 'Pending'): ?>
                      <form method="POST" style="display:inline">
                        <input type="hidden" name="fulfill" value="1">
                        <input type="hidden" name="rx_id" value="<?= $r['PrescriptionID'] ?>">
                        <button class="btn btn-sm btn-success" type="submit" data-confirm="Fulfill this prescription? Stock will be decremented (ACID transaction).">
                          <span class="material-symbols-outlined" style="font-size:16px">check</span> Fulfill
                        </button>
                      </form>
                    <?php elseif ($r['Status'] === 'Fulfilled'): ?>
                      <span class="text-xs text-muted"><?= $r['FulfilledAt'] ? date('M j H:i', strtotime($r['FulfilledAt'])) : '' ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="grid-2 gap-md">
        <!-- Trend Chart -->
        <div class="card stagger-3">
          <div class="card-header"><h2 class="card-title"><span class="material-symbols-outlined">trending_up</span> Prescription Trends</h2></div>
          <div class="card-body">
            <div class="trend-bar" style="margin-bottom:28px;">
              <?php foreach ($trends as $t): ?>
                <div class="trend-col" style="height:<?= round(($t['times_prescribed'] / $maxTrend) * 100) ?>%" title="<?= htmlspecialchars($t['BrandName']) ?>: <?= $t['times_prescribed'] ?>">
                  <span class="trend-label"><?= htmlspecialchars($t['BrandName']) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Stock Overview -->
        <div class="card stagger-4">
          <div class="card-header"><h2 class="card-title"><span class="material-symbols-outlined">inventory_2</span> Low Stock Alert</h2></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($stockList as $s): ?>
              <div class="flex justify-between items-center" style="padding:8px 12px;background:var(--bg);border-radius:var(--radius-sm);">
                <div>
                  <span class="fw-600"><?= htmlspecialchars($s['BrandName']) ?></span>
                  <span class="text-xs text-muted"> (<?= htmlspecialchars($s['GenericName']) ?>)</span>
                </div>
                <span class="fw-700 <?= $s['Stock'] > 20 ? 'stock-high' : ($s['Stock'] > 5 ? 'stock-medium' : 'stock-low') ?>"><?= $s['Stock'] ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="<?= basePath() ?>/js/app.js"></script>
</body>
</html>



<?php /* ==========================================
   ORIGINAL FILE: pages/prescribe.php
   ========================================== */ ?>

<?php
require_once __DIR__ . '/../db.php';
requireRole('Doctor');
$docId = currentUserId();

// Handle prescription submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prescribe'])) {
    $patientId = intval($_POST['patient_id']);
    $brandId = intval($_POST['brand_id']);
    $dosage = trim($_POST['dosage']);
    $frequency = trim($_POST['frequency']);
    $notes = trim($_POST['notes'] ?? '');

    // Get brand info
    $bi = $pdo->prepare('SELECT BrandName FROM Brands WHERE BrandID = ?');
    $bi->execute([$brandId]);
    $brandInfo = $bi->fetch();

    $items = json_encode([['brand_id' => $brandId, 'brand_name' => $brandInfo['BrandName'], 'dosage' => $dosage, 'frequency' => $frequency]]);

    // Insert prescription
    $stmt = $pdo->prepare("INSERT INTO Prescriptions (DoctorID, PatientID, Items, Status, Notes) VALUES (?, ?, ?, 'Pending', ?)");
    $stmt->execute([$docId, $patientId, $items, $notes]);

    // Also log into USER_MED_LOG
    $pdo->prepare("INSERT INTO USER_MED_LOG (UserID, BrandID, PrescribedBy, Status, Notes) VALUES (?, ?, ?, 'Active', ?)")
        ->execute([$patientId, $brandId, $docId, "Rx: $dosage $frequency"]);

    header('Location: prescribe.php?msg=prescribed');
    exit;
}

// Fetch patients
$patients = $pdo->query("SELECT UserID, FullName FROM Users WHERE Role = 'Patient' ORDER BY FullName");
$patientList = $patients->fetchAll();

// Fetch generics and brands
$generics = $pdo->query('SELECT * FROM Generics ORDER BY GenericName');
$genericList = $generics->fetchAll();

// If a generic is selected, fetch its brands ranked
$selectedGeneric = isset($_GET['gid']) ? intval($_GET['gid']) : null;
$brandCards = [];
if ($selectedGeneric) {
    $bs = $pdo->prepare("SELECT b.*, g.GenericName, g.DrugClass,
        ROUND((CAST(JSON_UNQUOTE(JSON_EXTRACT(Ratings, '$.efficacy')) AS DECIMAL(3,1)) + CAST(JSON_UNQUOTE(JSON_EXTRACT(Ratings, '$.price')) AS DECIMAL(3,1)) + CAST(JSON_UNQUOTE(JSON_EXTRACT(Ratings, '$.popularity')) AS DECIMAL(3,1))) / 3, 1) AS composite
        FROM Brands b JOIN Generics g ON b.GenericID = g.GenericID
        WHERE b.GenericID = ? ORDER BY composite DESC");
    $bs->execute([$selectedGeneric]);
    $brandCards = $bs->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prescribe — HRec</title>
  <link rel="stylesheet" href="<?= basePath() ?>/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div>
        <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')"><span class="material-symbols-outlined">menu</span></button>
        <span class="topbar-title">Write Prescription</span>
        <div class="topbar-subtitle">Select the best brand for your patient</div>
      </div>
    </header>
    <div class="page-content">
      <?php if (isset($_GET['msg'])): ?>
        <div class="toast-container"><div class="toast"><span class="material-symbols-outlined" style="color:#10b981">check_circle</span>Prescription created and logged!</div></div>
      <?php endif; ?>

      <!-- Step 1: Select Generic to see Brand Rankings -->
      <div class="card mb-24 stagger-1">
        <div class="card-header"><h2 class="card-title"><span class="material-symbols-outlined">search</span> Step 1: Select Drug Class</h2></div>
        <div class="card-body">
          <form method="GET" class="flex gap-8" style="flex-wrap:wrap">
            <select class="form-control" name="gid" style="max-width:400px" onchange="this.form.submit()">
              <option value="">Choose a generic drug…</option>
              <?php foreach ($genericList as $g): ?>
                <option value="<?= $g['GenericID'] ?>" <?= $selectedGeneric == $g['GenericID'] ? 'selected' : '' ?>><?= htmlspecialchars($g['GenericName']) ?> (<?= htmlspecialchars($g['DrugClass'] ?? '') ?>)</option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($_GET['pid'])): ?><input type="hidden" name="pid" value="<?= intval($_GET['pid']) ?>"><?php endif; ?>
          </form>
        </div>
      </div>

      <?php if (!empty($brandCards)): ?>
      <!-- Step 2: Brand Rankings -->
      <h2 class="page-title">Brand Rankings — <?= htmlspecialchars($brandCards[0]['GenericName']) ?></h2>
      <p class="page-desc">Ranked by composite score (efficacy + price + popularity) / 3</p>

      <div class="grid-auto gap-md mb-24">
        <?php foreach ($brandCards as $i => $bc):
          $ratings = json_decode($bc['Ratings'] ?? '{}', true);
          $isBest = ($i === 0);
        ?>
          <div class="brand-card <?= $isBest ? 'best' : '' ?> stagger-<?= min($i+1,5) ?>">
            <?php if ($isBest): ?><div class="best-pick" style="margin-bottom:12px">⭐ Best Pick</div><?php endif; ?>
            <div class="fw-700" style="font-size:1.1rem"><?= htmlspecialchars($bc['BrandName']) ?></div>
            <div class="text-sm text-muted mb-20"><?= htmlspecialchars($bc['Manufacturer']) ?> · ৳<?= number_format($bc['UnitPrice'], 2) ?></div>

            <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
              <div class="rating-bar"><span class="text-xs" style="min-width:60px">Efficacy</span><div class="rating-track"><div class="rating-fill" style="width:<?= ($ratings['efficacy'] ?? 0) * 20 ?>%"></div></div><span class="rating-value"><?= $ratings['efficacy'] ?? 0 ?></span></div>
              <div class="rating-bar"><span class="text-xs" style="min-width:60px">Price</span><div class="rating-track"><div class="rating-fill" style="width:<?= ($ratings['price'] ?? 0) * 20 ?>%"></div></div><span class="rating-value"><?= $ratings['price'] ?? 0 ?></span></div>
              <div class="rating-bar"><span class="text-xs" style="min-width:60px">Popularity</span><div class="rating-track"><div class="rating-fill" style="width:<?= ($ratings['popularity'] ?? 0) * 20 ?>%"></div></div><span class="rating-value"><?= $ratings['popularity'] ?? 0 ?></span></div>
            </div>

            <div class="flex justify-between items-center">
              <span class="fw-700" style="color:var(--accent)">Score: <?= $bc['composite'] ?>/5.0</span>
              <span class="text-xs <?= $bc['Stock'] > 20 ? 'stock-high' : ($bc['Stock'] > 5 ? 'stock-medium' : 'stock-low') ?>">Stock: <?= $bc['Stock'] ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Step 3: Prescription Form -->
      <div class="card stagger-3">
        <div class="card-header"><h2 class="card-title"><span class="material-symbols-outlined">edit_note</span> Step 2: Write Prescription</h2></div>
        <div class="card-body">
          <form method="POST" style="display:flex;flex-direction:column;gap:16px;">
            <input type="hidden" name="prescribe" value="1">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Patient</label>
                <select class="form-control" name="patient_id" required>
                  <option value="">Select patient…</option>
                  <?php foreach ($patientList as $p): ?>
                    <option value="<?= $p['UserID'] ?>" <?= (isset($_GET['pid']) && intval($_GET['pid']) == $p['UserID']) ? 'selected' : '' ?>><?= htmlspecialchars($p['FullName']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Brand</label>
                <select class="form-control" name="brand_id" required>
                  <?php foreach ($brandCards as $bc): ?>
                    <option value="<?= $bc['BrandID'] ?>"><?= htmlspecialchars($bc['BrandName']) ?> (৳<?= number_format($bc['UnitPrice'], 2) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Dosage</label>
                <input class="form-control" type="text" name="dosage" placeholder="e.g. 10mg" required>
              </div>
              <div class="form-group">
                <label class="form-label">Frequency</label>
                <input class="form-control" type="text" name="frequency" placeholder="e.g. twice daily" required>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Clinical Notes</label>
              <textarea class="form-control" name="notes" placeholder="Clinical notes…"></textarea>
            </div>
            <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">send</span> Submit Prescription</button>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
<script src="<?= basePath() ?>/js/app.js"></script>
</body>
</html>


