<?php
/**
 * header.php — Shared HTML head and topbar shell
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'HRec') ?> — HRec</title>
  <link rel="stylesheet" href="<?= basePath() ?>/css/style.css?v=<?= time() ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div>
        <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
          <span class="material-symbols-outlined">menu</span>
        </button>
        <span class="topbar-title"><?= htmlspecialchars($pageTitle ?? '') ?></span>
        <div class="topbar-subtitle"><?= htmlspecialchars($pageSubtitle ?? '') ?></div>
      </div>
      <div class="topbar-actions">
        <span class="text-sm text-muted"><?= date('D, M j, Y') ?></span>
      </div>
    </header>
