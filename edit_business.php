<?php
// edit_business.php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

$id = $_GET['id'] ?? null;
if (!$id) die("No ID specified");

// Fetch Business Data
$stmt = $pdo->prepare("SELECT * FROM business WHERE shop_id = ?");
$stmt->execute([$id]);
$biz = $stmt->fetch();

if (!$biz) die("Business not found");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Business</title>
    <link rel="stylesheet" href="admin.css">
    <style>
        .edit-container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn-save { background: #2ecc71; color: white; padding: 12px; width: 100%; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
    </style>
</head>
<body>

    <div class="edit-container">
        <h2>Edit Business: <?php echo htmlspecialchars($biz['name']); ?></h2>
        
        <form action="admin_process.php" method="POST">
            <input type="hidden" name="action" value="update_business">
            <input type="hidden" name="shop_id" value="<?php echo $biz['shop_id']; ?>">

            <div class="form-group">
                <label>Business Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($biz['name']); ?>" required>
            </div>

            <div class="form-group">
                <label>Owner ID</label>
                <input type="number" name="owner_id" value="<?php echo htmlspecialchars($biz['owner_id']); ?>" required>
            </div>

            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="tel_no" value="<?php echo htmlspecialchars($biz['tel_no']); ?>">
            </div>

            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" value="<?php echo htmlspecialchars($biz['address']); ?>">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4"><?php echo htmlspecialchars($biz['description']); ?></textarea>
            </div>

            <button type="submit" class="btn-save">Save Changes</button>
        </form>
        
        <br>
        <a href="user_management.php?tab=business" style="text-align:center; display:block; color:#666;">Cancel</a>
    </div>

</body>
</html>