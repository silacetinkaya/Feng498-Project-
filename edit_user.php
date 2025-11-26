<?php
// edit_user.php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

$id = $_GET['id'] ?? null;
if (!$id) die("No ID specified");

// Fetch User Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) die("User not found");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link rel="stylesheet" href="admin.css">
    <style>
        .edit-container { max-width: 500px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn-save { background: #2ecc71; color: white; padding: 12px; width: 100%; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
    </style>
</head>
<body>

    <div class="edit-container">
        <h2>Edit User: <?php echo htmlspecialchars($user['full_name']); ?></h2>
        
        <form action="admin_process.php" method="POST">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" value="<?php echo htmlspecialchars($user['address']); ?>">
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role_type">
                    <option value="user" <?php if($user['role_type']=='user') echo 'selected'; ?>>User</option>
                    <option value="admin" <?php if($user['role_type']=='admin') echo 'selected'; ?>>Admin</option>
                </select>
            </div>

            <button type="submit" class="btn-save">Save Changes</button>
        </form>
        
        <br>
        <a href="user_management.php" style="text-align:center; display:block; color:#666;">Cancel</a>
    </div>

</body>
</html>