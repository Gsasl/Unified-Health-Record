<?php
// --- DATABASE CONFIGURATION ---
$host = 'localhost'; 
$db   = 'bracculs_hrec'; 
$user = 'bracculs_hrec'; 
$pass = 'Fdp';  

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

$current_user_id = 1;

// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'create_record') {
    $blood_type = $_POST['blood_type'];
    $allergies = $_POST['allergies'];
    $weight = $_POST['weight'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO Health_Records (UserID, BloodType, KnownAllergies, WeightKG) VALUES (?, ?, ?, ?)");
        $stmt->execute([$current_user_id, $blood_type, $allergies, $weight]);
    } catch (PDOException $e) {
        $error = "Failed to create record. (1:1 constraint may have blocked a duplicate).";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_med') {
    $brand_id = $_POST['brand_id'];
    $stmt = $pdo->prepare("INSERT INTO USER_MED_LOG (UserID, BrandID, Status) VALUES (?, ?, 'Active')");
    $stmt->execute([$current_user_id, $brand_id]);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'stop_med') {
    $log_id = $_POST['log_id'];
    $stmt = $pdo->prepare("UPDATE USER_MED_LOG SET Status = 'Discontinued' WHERE LogID = ? AND UserID = ?");
    $stmt->execute([$log_id, $current_user_id]);
}

// --- FETCH DATA ---
$stmt = $pdo->prepare("SELECT * FROM Users WHERE UserID = ?");
$stmt->execute([$current_user_id]);
$user_info = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM Health_Records WHERE UserID = ?");
$stmt->execute([$current_user_id]);
$health_record = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT uml.LogID, b.BrandName, g.GenericName, uml.DateAdded 
    FROM USER_MED_LOG uml
    JOIN Brands b ON uml.BrandID = b.BrandID
    JOIN Generics g ON b.GenericID = g.GenericID
    WHERE uml.UserID = ? AND uml.Status = 'Active'
    ORDER BY uml.DateAdded DESC
");
$stmt->execute([$current_user_id]);
$active_meds = $stmt->fetchAll();

$brands_query = $pdo->query("SELECT BrandID, BrandName, GenericName FROM Brands JOIN Generics ON Brands.GenericID = Generics.GenericID ORDER BY BrandName ASC");
$all_brands = $brands_query->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Dashboard - HRec</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f0f4f8; padding: 20px; color: #333; }
        .container { max-width: 900px; margin: auto; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 20px; border-top: 4px solid #3498db; }
        .card-green { border-top-color: #2ecc71; }
        h2, h3 { margin-top: 0; color: #2c3e50; }
        .btn { background-color: #3498db; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px;}
        .btn-danger { background-color: #e74c3c; }
        .btn:hover { opacity: 0.9; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;}
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #eee; padding: 10px; text-align: left; }
        th { background-color: #f9f9f9; }
    </style>
</head>
<body>

<div class="container">
    <h2>👤 Welcome, <?= htmlspecialchars($user_info['FullName'] ?? 'Test Patient') ?></h2>

    <div class="card">
        <h3>Lifetime Master Health Record</h3>
        
        <?php if (!$health_record): ?>
            <p style="color:#e74c3c;"><strong>Alert:</strong> No Master Health Record found. Please initialize your 1:1 record below.</p>
            <form method="POST">
                <input type="hidden" name="action" value="create_record">
                <div class="form-group">
                    <label>Blood Type:</label>
                    <select name="blood_type" required>
                        <option value="A+">A+</option><option value="A-">A-</option>
                        <option value="B+">B+</option><option value="B-">B-</option>
                        <option value="O+">O+</option><option value="O-">O-</option>
                        <option value="AB+">AB+</option><option value="AB-">AB-</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Weight (KG):</label>
                    <input type="number" step="0.1" name="weight" required>
                </div>
                <div class="form-group">
                    <label>Known Allergies / Existing Conditions (Comma separated):</label>
                    <input type="text" name="allergies" placeholder="e.g., Peanuts, Penicillin, Asthma" required>
                </div>
                <button type="submit" class="btn">Initialize Health Record</button>
            </form>
        <?php else: ?>
            <table style="width: 50%;">
                <tr><th>Record ID</th><td>#HREC-<?= str_pad($health_record['RecordID'], 5, "0", STR_PAD_LEFT) ?></td></tr>
                <tr><th>Blood Type</th><td><strong style="color:#e74c3c;"><?= htmlspecialchars($health_record['BloodType']) ?></strong></td></tr>
                <tr><th>Weight</th><td><?= htmlspecialchars($health_record['WeightKG']) ?> KG</td></tr>
                <tr><th>Allergies/Conditions</th><td><?= htmlspecialchars($health_record['KnownAllergies']) ?></td></tr>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($health_record): ?>
        <div class="card card-green">
            <h3>💊 My Active Medications (USER_MED_LOG)</h3>
            
            <form method="POST" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                <input type="hidden" name="action" value="add_med">
                <div class="form-group">
                    <label>Log a New Active Medication:</label>
                    <select name="brand_id" required>
                        <option value="">-- Search Database --</option>
                        <?php foreach($all_brands as $brand): ?>
                            <option value="<?= $brand['BrandID'] ?>"><?= htmlspecialchars($brand['BrandName']) ?> (<?= htmlspecialchars($brand['GenericName']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn">Add to Active Log</button>
            </form>

            <?php if ($active_meds && count($active_meds) > 0): ?>
                <table>
                    <tr>
                        <th>Brand Name</th>
                        <th>Generic Composition</th>
                        <th>Date Started</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($active_meds as $med): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($med['BrandName']) ?></strong></td>
                        <td><?= htmlspecialchars($med['GenericName']) ?></td>
                        <td><?= htmlspecialchars(date('Y-m-d', strtotime($med['DateAdded']))) ?></td>
                        <td>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="action" value="stop_med">
                                <input type="hidden" name="log_id" value="<?= $med['LogID'] ?>">
                                <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">Stop taking</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>You have no active medications logged.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
