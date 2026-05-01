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

$pageTitle = 'Medication Log';
$pageSubtitle = 'Track your active and past medications';

$dbmsConcept = 'M:N Bridge Table with Payload';
$dbmsTables = 'Users ↔ USER_MED_LOG ↔ Brands';
$dbmsWhy = 'USER_MED_LOG resolves the many-to-many relationship between Users and Brands, and carries "payload" columns like Status and PrescribedBy.';

include __DIR__ . '/../includes/header.php';
?>

<div class="page-content">
  <?php include __DIR__ . '/../includes/dbms_panel.php'; ?>

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

<?php include __DIR__ . '/../includes/footer.php'; ?>
