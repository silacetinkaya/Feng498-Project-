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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --red: #e53935;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f5fb;
            padding: 20px;
        }

        .edit-container {
            max-width: 700px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        h2 {
            color: var(--red);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
        }

        .btn-save {
            background: #2ecc71;
            color: white;
            padding: 14px;
            width: 100%;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn-save:hover {
            transform: translateY(-2px);
        }

        .cancel-link {
            text-align: center;
            display: block;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
        }

        .cancel-link:hover {
            color: var(--red);
        }
    </style>
</head>
<body>

    <div class="edit-container">
        <h2><i class="fas fa-store"></i> Edit Business: <?= htmlspecialchars($biz['name']) ?></h2>
        
        <form action="admin_process.php" method="POST">
            <input type="hidden" name="action" value="update_business">
            <input type="hidden" name="shop_id" value="<?= $biz['shop_id'] ?>">

            <div class="form-group">
                <label>Business Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($biz['name']) ?>" required>
            </div>

            <div class="form-group">
                <label>Owner ID</label>
                <input type="number" name="owner_id" value="<?= htmlspecialchars($biz['owner_id']) ?>" required>
            </div>

            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="tel_no" value="<?= htmlspecialchars($biz['tel_no']) ?>">
            </div>

            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" value="<?= htmlspecialchars($biz['address']) ?>">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4"><?= htmlspecialchars($biz['description']) ?></textarea>
            </div>

            <button type="submit" class="btn-save">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </form>
        
        <a href="admin_dashboard.php?tab=business" class="cancel-link">
            <i class="fas fa-arrow-left"></i> Back to Business Management
        </a>
    </div>

</body>
</html>