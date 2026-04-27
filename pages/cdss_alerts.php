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
