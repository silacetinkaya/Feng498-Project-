<?php
// business_dashboard.php
session_start();
require_once 'db_connect.php';

// 1. SECURITY: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: business.html"); // Redirect to login if not logged in
    exit;
}

$ownerId = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'info';
$successMessage = null;
$errorMessage = null;

// 2. HANDLE "CREATE BUSINESS" (First Time Setup)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_business'])) {
    try {
        $name = $_POST['biz_name'];
        $address = $_POST['biz_address'];
        $tel = $_POST['biz_tel'];
        
        // Insert new business linked to this user
        $sql = "INSERT INTO business (name, owner_id, address, tel_no) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $ownerId, $address, $tel]);
        
        // Refresh page to load the dashboard
        header("Location: business_dashboard.php");
        exit;
    } catch (Exception $e) {
        $errorMessage = "Error creating business: " . $e->getMessage();
    }
}

// 3. FETCH BUSINESS
$stmt = $pdo->prepare("SELECT * FROM business WHERE owner_id = :id LIMIT 1");
$stmt->execute(['id' => $ownerId]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

// --- SCENARIO A: NO BUSINESS FOUND -> SHOW REGISTRATION FORM ---
if (!$business) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup Your Business</title>
    <link rel="stylesheet" href="admin.css">
    <style>
        body { background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .setup-card { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 450px; }
        .setup-card h2 { color: #333; margin-bottom: 20px; text-align: center; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #666; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn-start { width: 100%; padding: 12px; background: #2ecc71; color: white; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; }
        .btn-start:hover { background: #27ae60; }
    </style>
</head>
<body>
    <div class="setup-card">
        <h2>Setup Your Business</h2>
        <p style="text-align:center; color:#777; margin-bottom:30px;">Welcome! It looks like you haven't set up your shop yet. Let's get started.</p>
        
        <?php if($errorMessage): ?><p style="color:red; text-align:center;"><?php echo $errorMessage; ?></p><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="register_business" value="1">
            <div class="form-group">
                <label>Business Name</label>
                <input type="text" name="biz_name" required placeholder="e.g. Joe's Coffee">
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="biz_tel" required placeholder="+1 234 567 890">
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="biz_address" required placeholder="123 Main St">
            </div>
            <button type="submit" class="btn-start">Create My Business</button>
        </form>
        <div style="text-align:center; margin-top:20px;">
            <a href="logout.php" style="color:#999; text-decoration:none;">Logout</a>
        </div>
    </div>
</body>
</html>
<?php
    exit; // Stop script here, don't show dashboard yet
}

// --- SCENARIO B: BUSINESS EXISTS -> SHOW DASHBOARD ---

$businessId = $business['shop_id'];

// 4. HANDLE DASHBOARD POST REQUESTS (Updates)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- UPDATE INFO & HOURS ---
    if (isset($_POST['update_business'])) {
        try {
            // Update Basic Info
            $upd = $pdo->prepare("UPDATE business SET name=?, address=?, tel_no=?, description=? WHERE shop_id=?");
            $upd->execute([
                $_POST['name'], 
                $_POST['address'], 
                $_POST['tel_no'], 
                $_POST['description'], 
                $businessId
            ]);

            // Update Hours
            $days = ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"];
            foreach ($days as $day) {
                $open   = $_POST["open_$day"]   ?? null;
                $close  = $_POST["close_$day"]  ?? null;
                
                // FIX: Use strings '1' or '0' to avoid empty string errors with PostgreSQL booleans
                $closed = isset($_POST["closed_$day"]) ? '1' : '0';

                if($open === '') $open = null;
                if($close === '') $close = null;

                $check = $pdo->prepare("SELECT id FROM business_hours WHERE business_id = ? AND day_of_week = ?");
                $check->execute([$businessId, $day]);
                $exists = $check->fetchColumn();

                if ($exists) {
                    $u = $pdo->prepare("UPDATE business_hours SET open_hour=?, close_hour=?, is_closed=? WHERE id=?");
                    $u->execute([$open, $close, $closed, $exists]);
                } else {
                    $i = $pdo->prepare("INSERT INTO business_hours (business_id, day_of_week, open_hour, close_hour, is_closed) VALUES (?, ?, ?, ?, ?)");
                    $i->execute([$businessId, $day, $open, $close, $closed]);
                }
            }
            $successMessage = "Business information updated successfully.";
        } catch (Exception $e) {
            $errorMessage = "Error updating info: " . $e->getMessage();
        }
    }

    // --- ADD PRODUCT ---
    if (isset($_POST['add_product'])) {
        try {
            // FIX: Changed literal '1' to 'true' for the boolean 'available' column
            $ins = $pdo->prepare("INSERT INTO products (business_id, name, categories, description, product_prices, available) VALUES (?, ?, ?, ?, ?, true)");
            $ins->execute([
                $businessId,
                $_POST['p_name'],
                $_POST['p_category'],
                $_POST['p_description'],
                $_POST['p_price']
            ]);
            $successMessage = "Product added successfully.";
        } catch (Exception $e) {
            $errorMessage = "Error adding product: " . $e->getMessage();
        }
    }

    // --- DELETE PRODUCT ---
    if (isset($_POST['delete_product'])) {
        $del = $pdo->prepare("DELETE FROM products WHERE id = ? AND business_id = ?");
        $del->execute([$_POST['product_id'], $businessId]);
        $successMessage = "Product deleted.";
    }

    // --- UPDATE PRICE ---
    if (isset($_POST['update_price'])) {
        $upd = $pdo->prepare("UPDATE products SET product_prices = ? WHERE id = ? AND business_id = ?");
        $upd->execute([$_POST['new_price'], $_POST['product_id'], $businessId]);
        $successMessage = "Price updated.";
    }
}

// 5. FETCH FRESH DATA FOR VIEWS
$stmt->execute(['id' => $ownerId]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

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

$stmtProd = $pdo->prepare("SELECT * FROM products WHERE business_id = ? ORDER BY id DESC");
$stmtProd->execute([$businessId]);
$products = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

$stmtRev = $pdo->prepare("SELECT r.*, u.full_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.business_id = ? ORDER BY r.time DESC");
$stmtRev->execute([$businessId]);
$reviews = $stmtRev->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Business Dashboard</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .hours-table input[type="time"] { padding: 5px; border: 1px solid #ddd; border-radius: 4px; }
        .hours-table td { padding: 8px 5px; }
        .btn-small { padding: 5px 10px; font-size: 0.8rem; }
        .msg { padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .msg.success { background: #d4edda; color: #155724; }
        .msg.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-store"></i> BusinessPanel
        </div>
        <ul class="nav-links">
            <li><a href="?tab=info" class="<?php echo $tab=='info'?'active':''; ?>"><i class="fas fa-info-circle"></i> Business Info</a></li>
            <li><a href="?tab=products" class="<?php echo $tab=='products'?'active':''; ?>"><i class="fas fa-box"></i> Products</a></li>
            <li><a href="?tab=reviews" class="<?php echo $tab=='reviews'?'active':''; ?>"><i class="fas fa-star"></i> Reviews</a></li>
        </ul>
        <div class="logout-section">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        
        <header>
            <div class="header-title">
                <h2><?php echo htmlspecialchars($business['name']); ?></h2>
                <span>Owner Dashboard</span>
            </div>
            <div class="system-status">
                <span class="status-dot"></span> Online
            </div>
        </header>

        <?php if ($successMessage): ?>
            <div class="msg success"><?php echo $successMessage; ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="msg error"><?php echo $errorMessage; ?></div>
        <?php endif; ?>

        <?php 
        if ($tab == 'info') include 'business_info.php';
        elseif ($tab == 'products') include 'business_products.php';
        elseif ($tab == 'reviews') include 'business_reviews.php';
        ?>

    </div>

</body>
</html>