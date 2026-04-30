<?php
require_once __DIR__ . '/db.php';
// Reset prescriptions back to Pending and restore stock
$pdo->exec("UPDATE Prescriptions SET Status = 'Pending', FulfilledAt = NULL");
$pdo->exec("UPDATE Brands SET Stock = 50"); // Reset all stock to default
echo "Done. All prescriptions reset to Pending, all stock reset to 50.<br>";
echo "<a href='/HRec_CDSS/Unified-Health-Record-main/index.php'>Go to Login</a>";
