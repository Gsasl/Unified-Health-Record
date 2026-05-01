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

$pageTitle = 'Patient Overview';
$pageSubtitle = 'Dr. ' . htmlspecialchars(currentUserName());

$dbmsConcept = 'Joins & Aggregates';
$dbmsTables = 'Users + Health_Records + Aggregates';
$dbmsWhy = 'Demonstrates using LEFT JOINs to fetch optional 1:1 records, combined with subqueries/aggregates for real-time counts (like active meds) in a single query.';

include __DIR__ . '/../includes/header.php';
?>

<div class="page-content">
  <?php include __DIR__ . '/../includes/dbms_panel.php'; ?>

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

<?php include __DIR__ . '/../includes/footer.php'; ?>
