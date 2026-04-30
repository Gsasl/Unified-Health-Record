<?php
/**
 * dbms_panel.php — Academic explanation panel
 * Expects $dbmsConcept, $dbmsTables, $dbmsWhy to be set.
 */
?>
<div class="card mb-24 dbms-panel">
  <div class="card-body">
    <h3 class="text-sm fw-700" style="color: var(--accent); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
      <span class="material-symbols-outlined" style="font-size:18px;">school</span> 
      DBMS Concept Demo
    </h3>
    <div class="grid-3 gap-md">
      <div>
        <strong class="text-xs text-muted" style="display:block; text-transform:uppercase; margin-bottom:4px;">Concept Demonstrated</strong>
        <div class="fw-600 text-sm"><?= htmlspecialchars($dbmsConcept ?? '') ?></div>
      </div>
      <div>
        <strong class="text-xs text-muted" style="display:block; text-transform:uppercase; margin-bottom:4px;">Main Tables Used</strong>
        <div class="fw-600 text-sm" style="font-family: monospace; color: var(--accent-hover);"><?= htmlspecialchars($dbmsTables ?? '') ?></div>
      </div>
      <div>
        <strong class="text-xs text-muted" style="display:block; text-transform:uppercase; margin-bottom:4px;">Why it matters</strong>
        <div class="text-sm" style="color: var(--text-secondary);"><?= htmlspecialchars($dbmsWhy ?? '') ?></div>
      </div>
    </div>
  </div>
</div>
