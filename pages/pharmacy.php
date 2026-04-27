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
