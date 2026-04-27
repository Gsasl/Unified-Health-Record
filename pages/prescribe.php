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
