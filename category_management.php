<?php
// category_management.php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin.html");
    exit;
}

// Fetch existing categories (Global ones have business_id IS NULL)
$stmt = $pdo->query("SELECT * FROM categories WHERE business_id IS NULL ORDER BY id DESC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Category Management</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .cat-card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .cat-list { list-style: none; padding: 0; margin-top: 20px; }
        .cat-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #eee; }
        .cat-item:last-child { border-bottom: none; }
        .btn-del { background: #e74c3c; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; }
        .form-inline { display: flex; gap: 10px; }
        .form-inline input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .form-inline button { background: #2ecc71; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo"><i class="fas fa-shield-alt"></i> AdminPanel</div>
    <ul class="nav-links">
        <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="user_management.php"><i class="fas fa-users"></i> User Management</a></li>
        <li><a href="category_management.php" class="active"><i class="fas fa-tags"></i> Categories</a></li>
        <li><a href="user_management.php?tab=business"><i class="fas fa-briefcase"></i> Businesses</a></li>
        <li><a href="review_management.php"><i class="fas fa-star"></i> Reviews</a></li>
        <li><a href="report_management.php"><i class="fas fa-flag"></i> Reports</a></li>
        <li><a href="#"><i class="fas fa-trophy"></i> Best of Day</a></li>
    </ul>
    <div class="logout-section"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
</div>

<div class="main-content">
    <header>
        <h2>Category Management</h2>
        <div class="system-status"><span class="status-dot"></span>System Operational</div>
    </header>

    <div class="cat-card">
        <h3>Add New Global Category</h3>
        <form action="admin_process.php" method="POST" class="form-inline">
            <input type="hidden" name="action" value="create_category">
            <input type="text" name="type" required placeholder="Enter category name (e.g. Automotive)">
            <button type="submit"><i class="fas fa-plus"></i> Add Category</button>
        </form>

        <h3>Existing Categories</h3>
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') echo "<p style='color:red'>Category deleted.</p>"; ?>
        
        <ul class="cat-list">
            <?php foreach ($categories as $c): ?>
            <li class="cat-item">
                <span><i class="fas fa-tag" style="color:#aaa; margin-right:10px;"></i> <?php echo htmlspecialchars($c['type']); ?></span>
                <a href="admin_process.php?action=delete_category&id=<?php echo $c['id']; ?>" 
                   onclick="return confirm('Remove this category?');">
                    <button class="btn-del"><i class="fas fa-trash"></i></button>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

</body>
</html>