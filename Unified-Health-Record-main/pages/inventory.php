<?php
require_once __DIR__ . '/../db.php';
requireRole('Pharmacist');

// Handle Restock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restock'])) {
    $brandId = intval($_POST['brand_id']);
    $amount = intval($_POST['amount']);
    if ($brandId > 0 && $amount > 0) {
        $stmt = $pdo->prepare('UPDATE Brands SET Stock = Stock + ? WHERE BrandID = ?');
        $stmt->execute([$amount, $brandId]);
        auditLog($pdo, 'INVENTORY_RESTOCK', 'Brands', $brandId, "Restocked {$amount} units");
        header('Location: inventory.php?msg=restocked');
        exit;
    }
}

// Fetch all inventory items
$inventoryList = $pdo->query("
    SELECT b.BrandID, b.BrandName, b.Manufacturer, b.UnitPrice, b.Stock, g.GenericName 
    FROM Brands b 
    JOIN Generics g ON b.GenericID = g.GenericID 
    ORDER BY g.GenericName ASC, b.BrandName ASC
")->fetchAll();

$groupedInventory = [];
foreach ($inventoryList as $item) {
    $groupedInventory[$item['GenericName']][] = $item;
}

$pageTitle = 'Inventory Management';
$pageSubtitle = 'Current stock levels and low stock alerts';

$dbmsConcept = 'Data Grouping & Thresholding';
$dbmsTables = 'Brands & Generics';
$dbmsWhy = 'Demonstrates grouping normalized data in the application layer and dynamically alerting based on threshold values (Stock < 30).';

include __DIR__ . '/../includes/header.php';
?>

<style>
  .inventory-group { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); margin-bottom: 12px; overflow: hidden; transition: all var(--transition); }
  .inventory-group:hover { border-color: var(--border-focus); }
  .inventory-group summary { padding: 16px 20px; font-weight: 700; cursor: pointer; list-style: none; display: flex; justify-content: space-between; align-items: center; color: var(--text-primary); }
  .inventory-group summary::-webkit-details-marker { display: none; }
  .inventory-group[open] summary { border-bottom: 1px solid var(--border); background: rgba(255,255,255,0.02); }
  .inventory-group table { margin: 0; }
  .inventory-group th { background: rgba(0,0,0,0.2); padding: 12px 20px; font-size: 0.7rem; color: var(--text-muted); }
  .inventory-group td { padding: 14px 20px; }
  .low-stock-row { background: rgba(239, 68, 68, 0.15) !important; color: #fca5a5 !important; }
  .low-stock-row td { color: inherit !important; border-bottom: 1px solid rgba(239, 68, 68, 0.3); }
</style>

<div class="page-content">
  <?php include __DIR__ . '/../includes/dbms_panel.php'; ?>

  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'restocked'): ?>
    <div class="toast-container"><div class="toast"><span class="material-symbols-outlined" style="color:#10b981">check_circle</span>Inventory restocked successfully!</div></div>
  <?php endif; ?>

  <!-- Low Stock Alert Banner -->
  <?php 
    $lowStockCount = count(array_filter($inventoryList, fn($i) => $i['Stock'] < 30));
    if ($lowStockCount > 0): 
  ?>
  <div class="alert alert-critical mb-24 stagger-1">
    <span class="material-symbols-outlined alert-icon">warning</span>
    <div class="alert-content">
      <div class="alert-title">Low Stock Alert</div>
      <div class="alert-text">There are <?= $lowStockCount ?> items with low stock (less than 30 remaining). Please restock soon.</div>
    </div>
  </div>
  <?php endif; ?>

  <div class="stagger-2">
    <div style="margin-bottom: 16px; display:flex; align-items:center; gap:8px;">
      <span class="material-symbols-outlined" style="color:var(--accent);">inventory_2</span>
      <h2 style="font-size:1.1rem; font-weight:700;">Inventory by Generic</h2>
    </div>

    <?php foreach ($groupedInventory as $generic => $brands): 
      $hasLowStock = count(array_filter($brands, fn($b) => $b['Stock'] < 30)) > 0;
    ?>
      <details class="inventory-group" <?= $hasLowStock ? 'open' : '' ?>>
        <summary>
          <div style="display:flex; align-items:center; gap:12px;">
            <span class="material-symbols-outlined text-muted" style="font-size:18px;">medication</span>
            <?= htmlspecialchars($generic) ?>
          </div>
          <div style="display:flex; gap:8px;">
            <?php if ($hasLowStock): ?><span class="badge badge-cancelled">Needs Restock</span><?php endif; ?>
            <span class="badge badge-low"><?= count($brands) ?> Brand(s)</span>
          </div>
        </summary>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Brand Name</th>
                <th>Manufacturer</th>
                <th>Unit Price</th>
                <th>Current Stock</th>
                <th>Status</th>
                <th style="text-align:right">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($brands as $item): ?>
                <tr class="<?= $item['Stock'] < 30 ? 'low-stock-row' : '' ?>">
                  <td class="fw-700"><?= htmlspecialchars($item['BrandName']) ?></td>
                  <td class="text-sm"><?= htmlspecialchars($item['Manufacturer']) ?></td>
                  <td class="text-sm">৳<?= number_format($item['UnitPrice'], 2) ?></td>
                  <td class="fw-700">
                    <?= $item['Stock'] ?>
                  </td>
                  <td>
                    <?php if ($item['Stock'] >= 30): ?>
                      <span class="badge badge-active">In Stock</span>
                    <?php else: ?>
                      <span class="badge badge-cancelled">Low Stock</span>
                    <?php endif; ?>
                  </td>
                  <td style="text-align:right;">
                    <form method="POST" action="inventory.php" style="display:flex; gap:6px; justify-content:flex-end;">
                      <input type="hidden" name="brand_id" value="<?= $item['BrandID'] ?>">
                      <input type="number" name="amount" min="1" max="500" value="50" class="form-control" style="width:70px; padding:4px 8px; height:32px; font-size:0.8rem;" title="Restock Amount">
                      <button type="submit" name="restock" value="1" class="btn btn-sm btn-primary" style="height:32px; padding:0 12px; font-size:0.75rem;">
                        <span class="material-symbols-outlined" style="font-size:16px">add_shopping_cart</span> Restock
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
