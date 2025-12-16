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

// 1. FETCH BUSINESS
$stmt = $pdo->prepare("SELECT * FROM business WHERE owner_id = :id LIMIT 1");
$stmt->execute(['id' => $ownerId]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$business) { echo "Business not found."; exit; }
$businessId = $business['shop_id'];

// 2. FETCH CATEGORIES
$catStmt = $pdo->query("SELECT type FROM categories WHERE business_id IS NULL ORDER BY type ASC");
$categoryList = $catStmt->fetchAll(PDO::FETCH_COLUMN);

// 3. HANDLE POST REQUESTS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ----------------------------
       ADD PRICE LIST IMAGE
    ----------------------------- */
    if (isset($_POST['upload_pricelist'])) {
        try {
            if (isset($_FILES['pl_image']) && $_FILES['pl_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/pricelists/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $ext = strtolower(pathinfo($_FILES['pl_image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (!in_array($ext, $allowed)) {
                    throw new Exception("Invalid file type.");
                }

                $newFile = uniqid('pl_') . '.' . $ext;

                if (move_uploaded_file($_FILES['pl_image']['tmp_name'], $uploadDir . $newFile)) {
                    $stmt = $pdo->prepare("INSERT INTO price_lists (business_id, image_url) VALUES (?, ?)");
                    $stmt->execute([$businessId, $uploadDir . $newFile]);
                    $successMessage = "Price list uploaded successfully.";
                }
            } else {
                throw new Exception("Please select a file.");
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    }

    /* ----------------------------
       DELETE PRICE LIST
    ----------------------------- */
    if (isset($_POST['delete_pricelist'])) {
        $plId = $_POST['pl_id'];
        $del = $pdo->prepare("DELETE FROM price_lists WHERE id = ? AND business_id = ?");
        $del->execute([$plId, $businessId]);
        $successMessage = "Price list removed.";
    }

    /* ----------------------------
       RESPOND TO REVIEW (FIXED)
    ----------------------------- */
    if (isset($_POST['respond_to_review'])) {
        try {
            $revId = $_POST['review_id'];
            $respText = trim($_POST['response_text']);

            if (!empty($respText)) {
                // FIX: Use ON CONFLICT to Update if it already exists
                // We also set is_approved = FALSE so admins must re-approve edited responses
                $sql = "INSERT INTO review_responses (review_id, business_id, response_text, is_approved) 
                        VALUES (:rid, :bid, :txt, FALSE)
                        ON CONFLICT (review_id) 
                        DO UPDATE SET 
                            response_text = :txt, 
                            is_approved = FALSE, 
                            updated_at = NOW()";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'rid' => $revId, 
                    'bid' => $businessId, 
                    'txt' => $respText
                ]);
                
                $successMessage = "Response saved successfully. Waiting for admin approval.";
            }
        } catch (Exception $e) {
            $errorMessage = "Error posting response: " . $e->getMessage();
        }
    }

    /* ----------------------------
       ADD PRODUCT
    ----------------------------- */
    if (isset($_POST['add_product'])) {
        try {
            $pdo->beginTransaction();
            $negotiable = isset($_POST['p_negotiable']) ? 'true' : 'false';

            $ins = $pdo->prepare("INSERT INTO products (business_id, name, categories, description, product_prices, available) 
                                  VALUES (?, ?, ?, ?, ?, true) RETURNING id");
            $ins->execute([
                $businessId, 
                $_POST['p_name'], 
                $_POST['p_category'], 
                $_POST['p_description'], 
                $_POST['p_price']
            ]);
            $newProdId = $ins->fetchColumn();

            // Save price history
            $insPrice = $pdo->prepare("INSERT INTO product_prices (product_id, price, is_negotiable, updated_at) 
                                       VALUES (?, ?, ?, NOW())");
            $insPrice->execute([$newProdId, $_POST['p_price'], $negotiable]);

            // Save image
            if (isset($_FILES['p_image']) && $_FILES['p_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/products/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $ext = strtolower(pathinfo($_FILES['p_image']['name'], PATHINFO_EXTENSION));
                $newFile = uniqid('prod_') . '.' . $ext;
                
                if(move_uploaded_file($_FILES['p_image']['tmp_name'], $uploadDir . $newFile)) {
                    $insPhoto = $pdo->prepare("INSERT INTO photos (image_url, business_id, product_id) VALUES (?, ?, ?)");
                    $insPhoto->execute([$uploadDir . $newFile, $businessId, $newProdId]);
                }
            }

            $pdo->commit();
            $successMessage = "Product added successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMessage = "Error: " . $e->getMessage();
        }
    }

    /* ----------------------------
       DELETE PRODUCT
    ----------------------------- */
    if (isset($_POST['delete_product'])) {
        $del = $pdo->prepare("DELETE FROM products WHERE id = ? AND business_id = ?");
        $del->execute([$_POST['product_id'], $businessId]);
        $successMessage = "Product deleted.";
    }

    /* ----------------------------
       UPDATE BUSINESS INFO
    ----------------------------- */
    if (isset($_POST['update_business'])) {
        $upd = $pdo->prepare("UPDATE business SET name=?, address=?, tel_no=?, description=? WHERE shop_id=?");
        $upd->execute([
            $_POST['name'], 
            $_POST['address'], 
            $_POST['tel_no'], 
            $_POST['description'], 
            $businessId
        ]);
        
        // Update hours logic
        $days = ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"];
        foreach ($days as $day) {
            $open = $_POST["open_$day"] ?? null;
            $close = $_POST["close_$day"] ?? null;
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

        $successMessage = "Info updated.";
    }
}

/* ----------------------------
   PRICE LIST FETCHING
----------------------------- */
$stmtPl = $pdo->prepare("SELECT * FROM price_lists WHERE business_id = ? ORDER BY created_at DESC");
$stmtPl->execute([$businessId]);
$priceLists = $stmtPl->fetchAll(PDO::FETCH_ASSOC);

/* ----------------------------
   PRODUCTS WITH PAGINATION
----------------------------- */
$prodPage = isset($_GET['prod_page']) ? (int)$_GET['prod_page'] : 1;
$prodLimit = 10; 
$prodOffset = ($prodPage - 1) * $prodLimit;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE business_id = ?");
$countStmt->execute([$businessId]);
$totalProducts = $countStmt->fetchColumn();
$totalProdPages = ceil($totalProducts / $prodLimit);

$prodSql = "
    SELECT p.*, 
           (SELECT image_url FROM photos ph WHERE ph.product_id = p.id LIMIT 1) as image_url,
           (SELECT is_negotiable FROM product_prices pp WHERE pp.product_id = p.id ORDER BY updated_at DESC LIMIT 1) as is_negotiable
    FROM products p 
    WHERE p.business_id = ? 
    ORDER BY p.id DESC
    LIMIT $prodLimit OFFSET $prodOffset
";
$stmtProd = $pdo->prepare($prodSql);
$stmtProd->execute([$businessId]);
$products = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

/* ----------------------------
   REVIEWS (Ensuring is_approved is selected)
----------------------------- */
$revSql = "
    SELECT r.*, u.full_name, 
           rr.response_text, rr.created_at as response_date, 
           rr.is_approved as resp_approved
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    LEFT JOIN review_responses rr ON r.review_id = rr.review_id 
    WHERE r.business_id = ? 
    ORDER BY r.time DESC
";
$stmtRev = $pdo->prepare($revSql);
$stmtRev->execute([$businessId]);
$reviews = $stmtRev->fetchAll(PDO::FETCH_ASSOC);

/* ----------------------------
   HOURS
----------------------------- */
$days = ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"];
$hours = [];

foreach ($days as $day) {
    $hours[$day] = ['open'=>'', 'close'=>'', 'closed'=>false];
}

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

        /* PRICE LIST GRID */
        .pl-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); 
            gap: 15px; 
            margin-top: 20px; 
        }
        .pl-item { 
            border: 1px solid #ddd; 
            padding: 5px; 
            border-radius: 5px; 
            text-align: center; 
        }
        .pl-item img { 
            width: 100%; 
            height: 200px; 
            object-fit: cover; 
            cursor: pointer; 
        }

        /* Pagination */
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .pagination a { padding: 6px 12px; background: white; border: 1px solid #ddd; color: #333; text-decoration: none; border-radius: 4px; }
        .pagination a.active { background: #d32f2f; color: white; border-color: #d32f2f; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo"><i class="fas fa-store"></i> BusinessPanel</div>
        <ul class="nav-links">
            <li><a href="?tab=info" class="<?php echo $tab=='info'?'active':''; ?>"><i class="fas fa-info-circle"></i> Info</a></li>
            <li><a href="?tab=products" class="<?php echo $tab=='products'?'active':''; ?>"><i class="fas fa-box"></i> Products</a></li>
            <li><a href="?tab=reviews" class="<?php echo $tab=='reviews'?'active':''; ?>"><i class="fas fa-star"></i> Reviews</a></li>
            <li><a href="?tab=pricelist" class="<?php echo $tab=='pricelist'?'active':''; ?>"><i class="fas fa-file-invoice-dollar"></i> Price Lists</a></li>
        </ul>
        <div class="logout-section">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <header>
            <div class="header-title">
                <h2><?php echo htmlspecialchars($business['name']); ?></h2>
                <span>Owner Dashboard</span>
            </div>
            <div class="system-status"><span class="status-dot"></span> Online</div>
        </header>

        <?php if ($successMessage): ?><div class="msg success"><?php echo $successMessage; ?></div><?php endif; ?>
        <?php if ($errorMessage): ?><div class="msg error"><?php echo $errorMessage; ?></div><?php endif; ?>

        <?php 
            if ($tab == 'info') include 'business_info.php';
            elseif ($tab == 'products') include 'business_products.php';
            elseif ($tab == 'reviews') include 'business_reviews.php';
            elseif ($tab == 'pricelist') include 'business_pricelist.php';
        ?>
    </div>
</body>
</html>