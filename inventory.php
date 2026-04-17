<?php
// --- DATABASE CONFIGURATION ---
$host = 'localhost'; 
$db   = 'bracculs_hrec'; 
$user = 'bracculs_hrec'; 
$pass = 'Fdp';          

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Query updated to match the corrected schema
    $sql = "
        SELECT 
            b.BrandName, 
            g.GenericName, 
            b.Manufacturer, 
            b.UnitPrice, 
            g.StopIfCondition AS Warnings 
        FROM Brands b
        JOIN Generics g ON b.GenericID = g.GenericID
        ORDER BY g.GenericName ASC, b.BrandName ASC
    ";
    
    $stmt = $pdo->query($sql);
    $medications = $stmt->fetchAll();

} catch (PDOException $e) {
    die("<h3 style='color:red;'>Database Connection Failed: " . $e->getMessage() . "</h3><p>Check your \$user, \$pass, and \$db variables.</p>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Database View</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background-color: #f8f9fa; 
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        h2 {
            border-bottom: 2px solid #0056b3;
            padding-bottom: 10px;
            color: #0056b3;
        }
        table { 
            border-collapse: collapse; 
            width: 100%; 
            margin-top: 20px;
        }
        th, td { 
            border: 1px solid #dee2e6; 
            padding: 12px 15px; 
            text-align: left; 
        }
        th { 
            background-color: #e9ecef; 
            font-weight: bold;
            color: #495057;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        tr:hover {
            background-color: #e2e6ea;
        }
        .price {
            font-family: monospace;
            font-weight: bold;
            color: #28a745;
        }
        .warning-text { 
            color: #dc3545; 
            font-size: 0.85em; 
            line-height: 1.4;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Pharmacy Inventory & Clinical Data Overview</h2>
    <p>Total Medications Loaded: <strong><?= count($medications) ?></strong></p>

    <table>
        <thead>
            <tr>
                <th>Brand Name</th>
                <th>Generic Composition</th>
                <th>Manufacturer</th>
                <th>Unit Price</th>
                <th>Clinical Warnings (Excerpt)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($medications) > 0): ?>
                <?php foreach ($medications as $med): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($med['BrandName']) ?></strong></td>
                        <td><?= htmlspecialchars($med['GenericName']) ?></td>
                        <td><?= htmlspecialchars($med['Manufacturer']) ?></td>
                        <td class="price">৳ <?= number_format($med['UnitPrice'], 2) ?></td>
                        <td class="warning-text">
                            <?= htmlspecialchars(mb_strimwidth($med['Warnings'], 0, 150, "...")) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center;">No medication data found in the database.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
