<?php
// business_dashboard.php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: business.html");
    exit;
}

$ownerId = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'info';
$successMessage = null;
$errorMessage = null;

// FETCH BUSINESS
$stmt = $pdo->prepare("SELECT * FROM business WHERE owner_id = :id LIMIT 1");
$stmt->execute(['id' => $ownerId]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$business) { echo "Business not found."; exit; }
$businessId = $business['shop_id'];

// FETCH CATEGORIES (Global only)
$catStmt = $pdo->query("SELECT type FROM categories WHERE business_id IS NULL ORDER BY type ASC");
$categoryList = $catStmt->fetchAll(PDO::FETCH_COLUMN);

// HANDLE POST REQUESTS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- ADD PRODUCT ---
    if (isset($_POST['add_product'])) {
        try {
            $pdo->beginTransaction();

            $name = $_POST['p_name'];
            $cat = $_POST['p_category'];
            $desc = $_POST['p_description'];
            $price = $_POST['p_price'];
            $negotiable = isset($_POST['p_negotiable']) ? 'true' : 'false';

            // 1. Insert into PRODUCTS
            $ins = $pdo->prepare("INSERT INTO products (business_id, name, categories, description, product_prices, available) VALUES (?, ?, ?, ?, ?, true) RETURNING id");
            $ins->execute([$businessId, $name, $cat, $desc, $price]);
            $newProdId = $ins->fetchColumn();

            // 2. Insert into PRODUCT_PRICES (Detailed Record)
            $insPrice = $pdo->prepare("INSERT INTO product_prices (product_id, price, is_negotiable, updated_at) VALUES (?, ?, ?, NOW())");
            $insPrice->execute([$newProdId, $price, $negotiable]);

            // 3. Handle IMAGE -> Insert into PHOTOS table
            if (isset($_FILES['p_image']) && $_FILES['p_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $fileExt = strtolower(pathinfo($_FILES['p_image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (in_array($fileExt, $allowed)) {
                    $newFileName = uniqid('prod_') . '.' . $fileExt;
                    $destPath = $uploadDir . $newFileName;
                    if (move_uploaded_file($_FILES['p_image']['tmp_name'], $destPath)) {
                        
                        // Insert into PHOTOS table linked to product_id
                        $insPhoto = $pdo->prepare("INSERT INTO photos (image_url, business_id, product_id) VALUES (?, ?, ?)");
                        $insPhoto->execute([$destPath, $businessId, $newProdId]);
                    }
                }
            }

            $pdo->commit();
            $successMessage = "Product added successfully!";

        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMessage = "Error adding product: " . $e->getMessage();
        }
    }

    // --- UPDATE PRICE ---
    if (isset($_POST['update_price'])) {
        try {
            $prodId = $_POST['product_id'];
            $newPrice = $_POST['new_price'];
            
            // 1. Update main table cache
            $upd = $pdo->prepare("UPDATE products SET product_prices = ? WHERE id = ? AND business_id = ?");
            $upd->execute([$newPrice, $prodId, $businessId]);
            
            // 2. Add new record to product_prices
            $insP = $pdo->prepare("INSERT INTO product_prices (product_id, price, is_negotiable, updated_at) VALUES (?, ?, false, NOW())");
            $insP->execute([$prodId, $newPrice]);
            
            $successMessage = "Price updated.";
        } catch (Exception $e) {
            $errorMessage = "Error updating price.";
        }
    }
    
    // --- DELETE PRODUCT ---
    if (isset($_POST['delete_product'])) {
        $del = $pdo->prepare("DELETE FROM products WHERE id = ? AND business_id = ?");
        $del->execute([$_POST['product_id'], $businessId]);
        $successMessage = "Product deleted.";
    }
}

// FETCH DATA FOR VIEWS
// Products with Image Joined from Photos Table
// We grab the latest photo if multiple exist
$prodSql = "
    SELECT p.*, 
           (SELECT image_url FROM photos ph WHERE ph.product_id = p.id LIMIT 1) as image_url,
           (SELECT is_negotiable FROM product_prices pp WHERE pp.product_id = p.id ORDER BY updated_at DESC LIMIT 1) as is_negotiable
    FROM products p 
    WHERE p.business_id = ? 
    ORDER BY p.id DESC
";
$stmtProd = $pdo->prepare($prodSql);
$stmtProd->execute([$businessId]);
$products = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

// Reviews
$stmtRev = $pdo->prepare("SELECT r.*, u.full_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.business_id = ? ORDER BY r.time DESC");
$stmtRev->execute([$businessId]);
$reviews = $stmtRev->fetchAll(PDO::FETCH_ASSOC);

// Hours (for Info tab)
$stmt->execute(['id' => $ownerId]); 
$business = $stmt->fetch(PDO::FETCH_ASSOC); // Refresh
$days = ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"];
$hours = [];
foreach ($days as $day) $hours[$day] = ['open'=>'', 'close'=>'', 'closed'=>false];
$stmtHours = $pdo->prepare("SELECT * FROM business_hours WHERE business_id = ?");
$stmtHours->execute([$businessId]);
while ($row = $stmtHours->fetch(PDO::FETCH_ASSOC)) {
    $hours[$row['day_of_week']] = [
        'open' => substr($row['open_hour'] ?? '', 0, 5),
        'close' => substr($row['close_hour'] ?? '', 0, 5),
        'closed' => (bool)$row['is_closed']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Business Dashboard</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .msg { padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .msg.success { background: #d4edda; color: #155724; }
        .msg.error { background: #f8d7da; color: #721c24; }
        .product-img-thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 5px; border: 1px solid #ddd; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="logo"><i class="fas fa-store"></i> BusinessPanel</div>
        <ul class="nav-links">
            <li><a href="?tab=info" class="<?php echo $tab=='info'?'active':''; ?>"><i class="fas fa-info-circle"></i> Info</a></li>
            <li><a href="?tab=products" class="<?php echo $tab=='products'?'active':''; ?>"><i class="fas fa-box"></i> Products</a></li>
            <li><a href="?tab=reviews" class="<?php echo $tab=='reviews'?'active':''; ?>"><i class="fas fa-star"></i> Reviews</a></li>
        </ul>
        <div class="logout-section"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <header>
            <div class="header-title"><h2><?php echo htmlspecialchars($business['name']); ?></h2><span>Owner Dashboard</span></div>
            <div class="system-status"><span class="status-dot"></span> Online</div>
        </header>

        <?php if ($successMessage): ?><div class="msg success"><?php echo $successMessage; ?></div><?php endif; ?>
        <?php if ($errorMessage): ?><div class="msg error"><?php echo $errorMessage; ?></div><?php endif; ?>

        <?php 
        if ($tab == 'info') include 'business_info.php';
        elseif ($tab == 'products') include 'business_products.php';
        elseif ($tab == 'reviews') include 'business_reviews.php';
        ?>
    </div>
</body>
</html>