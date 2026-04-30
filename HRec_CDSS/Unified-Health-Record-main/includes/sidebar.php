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
      <a class="nav-link <?= $currentPage === 'inventory.php' ? 'active' : '' ?>" href="<?= basePath() ?>/pages/inventory.php">
        <span class="material-symbols-outlined">inventory_2</span> Inventory
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
