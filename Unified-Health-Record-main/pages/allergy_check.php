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

$pageTitle = 'Allergy Cross-Reference';
$pageSubtitle = 'Check your allergies against active medications';

$dbmsConcept = 'JSON Tradeoff / Allergy Lookup';
$dbmsTables = 'Health_Records (KnownAllergies JSON)';
$dbmsWhy = 'Shows the academic tradeoff between full normalization (a separate Patient_Allergies table) and using a JSON array for simple, flat lists where individual elements are rarely queried independently.';

include __DIR__ . '/../includes/header.php';
?>

<div class="page-content">
  <?php include __DIR__ . '/../includes/dbms_panel.php'; ?>

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

<?php include __DIR__ . '/../includes/footer.php'; ?>
