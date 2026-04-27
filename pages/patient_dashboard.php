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
