
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

// 2. FETCH CATEGORIES (For Dropdown)
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
       RESPOND TO REVIEW
    ----------------------------- */
    if (isset($_POST['respond_to_review'])) {
        try {
            $revId = $_POST['review_id'];
            $respText = trim($_POST['response_text']);

            if (!empty($respText)) {
                // Upsert response
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
                $successMessage = "Response posted successfully. Waiting for admin approval.";
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

        $negotiable = isset($_POST['p_negotiable']) ? 't' : 'f';
        // Base price
        $price = (float)($_POST['p_price'] ?? 0);
        if ($price <= 0) {
            throw new Exception("Price must be greater than 0.");
        }

        // Discount logic
        $isDiscounted = isset($_POST['is_discounted']);
        $discountPercent = null;
        $discountedPrice = null;
        $originalPrice = $price;

        if ($isDiscounted) {
            $dp = trim($_POST['discount_percent'] ?? '');
            $newP = trim($_POST['discounted_price'] ?? '');

            if ($dp !== '') {
                $discountPercent = (int)$dp;
                if ($discountPercent < 1 || $discountPercent > 99) {
                    throw new Exception("Discount percent must be between 1 and 99.");
                }
                $discountedPrice = round($price * (1 - $discountPercent / 100), 2);
            } elseif ($newP !== '') {
                $discountedPrice = (float)$newP;
                if ($discountedPrice <= 0 || $discountedPrice >= $price) {
                    throw new Exception("Discounted price must be less than original price.");
                }
                $discountPercent = (int)round((1 - ($discountedPrice / $price)) * 100);
                $discountPercent = max(1, min(99, $discountPercent));
            } else {
                throw new Exception("Enter discount percent OR discounted price.");
            }
        }

        $currentPrice = $isDiscounted ? $discountedPrice : $price;

        // INSERT product (includes discount columns)
        $ins = $pdo->prepare("
            INSERT INTO products (
                business_id, name, categories, description,
                product_prices, available,
                original_price, discounted_price, discount_percent, is_discounted
            )
            VALUES (?, ?, ?, ?, ?, true, ?, ?, ?, ?)
            RETURNING id
        ");
        $ins->execute([
            $businessId,
            $_POST['p_name'],
            $_POST['p_category'],
            $_POST['p_description'],
            $currentPrice,
            $originalPrice,
            $discountedPrice,
            $discountPercent,
            $isDiscounted ? true : false
        ]);
        $newProdId = $ins->fetchColumn();

        // Save price history
        $insPrice = $pdo->prepare("
            INSERT INTO product_prices (product_id, price, is_negotiable, updated_at)
            VALUES (?, ?, ?, NOW())
        ");
        $insPrice->execute([$newProdId, $currentPrice, $negotiable]);

        // Save image
        if (isset($_FILES['p_image']) && $_FILES['p_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $ext = strtolower(pathinfo($_FILES['p_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allowed, true)) {
                throw new Exception("Invalid image type. Use JPG/JPEG/PNG/WEBP.");
            }

            $newFile = uniqid('prod_') . '.' . $ext;

            if (move_uploaded_file($_FILES['p_image']['tmp_name'], $uploadDir . $newFile)) {
                $insPhoto = $pdo->prepare("
                    INSERT INTO photos (image_url, business_id, product_id, is_approved)
                    VALUES (?, ?, ?, FALSE)
                ");
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
       UPDATE PRODUCT PRICE
    ----------------------------- */
    if (isset($_POST['update_price'])) {
        try {
            $upd = $pdo->prepare("UPDATE products SET product_prices = ? WHERE id = ? AND business_id = ?");
            $upd->execute([$_POST['new_price'], $_POST['product_id'], $businessId]);
            $successMessage = "Price updated.";
        } catch (Exception $e) {
            $errorMessage = "Error updating price.";
        }
    }

    /* ----------------------------
       UPDATE BUSINESS INFO
    ----------------------------- */
    if (isset($_POST['update_business'])) {
        try {
            $upd = $pdo->prepare("UPDATE business SET name=?, address=?, tel_no=?, description=?, category=?, latitude=?, longitude=? WHERE shop_id=?");
            $upd->execute([
                $_POST['name'] ?? '',
                $_POST['address'] ?? '',
                $_POST['tel_no'] ?? '',
                $_POST['description'] ?? '',
                $_POST['category'] ?? '', 
                !empty($_POST['latitude']) ? $_POST['latitude'] : null,
                !empty($_POST['longitude']) ? $_POST['longitude'] : null,
                $businessId
            ]);
            
            // Refresh business data
            $stmt->execute(['id' => $ownerId]);
            $business = $stmt->fetch(PDO::FETCH_ASSOC);

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
        } catch (Exception $e) {
            $errorMessage = "Error updating: " . $e->getMessage();
        }
    }

    /* ----------------------------
       UPLOAD BUSINESS PHOTOS
    ----------------------------- */
    if (isset($_POST["upload_business_photos"])) {
        try {
            if (!isset($_FILES["business_photos"]) || empty($_FILES["business_photos"]["name"][0])) {
                throw new Exception("No files selected. Please choose at least one image.");
            }

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM business_photos WHERE business_id = ?");
            $countStmt->execute([$businessId]);
            $currentCount = (int)$countStmt->fetchColumn();

            if ($currentCount >= 10) {
                throw new Exception("Maximum 10 photos limit reached.");
            }

            $uploadDir = "uploads/businesses/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $files = $_FILES["business_photos"];
            $allowedExt = ["jpg", "jpeg", "png", "webp"];
            $remainingSlots = 10 - $currentCount;
            $uploadCount = 0;
            $errors = [];

            $fileCount = count($files["name"]);
            for ($i = 0; $i < $fileCount && $uploadCount < $remainingSlots; $i++) {
                if (empty($files["name"][$i])) continue;

                if ($files["error"][$i] !== UPLOAD_ERR_OK) {
                    $errors[] = "Upload error code: " . $files["error"][$i];
                    continue;
                }

                $ext = strtolower(pathinfo($files["name"][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) {

                    $errors[] = "Invalid file type: " . $ext;
                    continue;
                }

                $newFile = uniqid("biz_" . $businessId . "_") . "." . $ext;
                $destination = $uploadDir . $newFile;

                if (!move_uploaded_file($files["tmp_name"][$i], $destination)) {
                    $errors[] = "Failed to move file: " . $files["name"][$i];
                    continue;
                }

                $ins = $pdo->prepare("INSERT INTO business_photos (business_id, image_url, is_approved) VALUES (?, ?, FALSE)");
                $ins->execute([$businessId, $destination]);
                $uploadCount++;
            }

            if ($uploadCount > 0) {
                $successMessage = "$uploadCount photo(s) uploaded! Waiting for admin approval.";
            }

            if (!empty($errors)) {
                $errorMessage = implode(" | ", $errors);
            } elseif ($uploadCount === 0) {
                $errorMessage = "No valid files to upload.";
            }

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    }

    /* ----------------------------
       DELETE BUSINESS PHOTO
    ----------------------------- */
    if (isset($_POST["delete_business_photo"])) {
        $photoId = $_POST["delete_business_photo"];
        $del = $pdo->prepare("DELETE FROM business_photos WHERE id = ? AND business_id = ?");
        $del->execute([$photoId, $businessId]);
        $successMessage = "Photo deleted.";
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
   REVIEWS
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

// Address Fetching for Info Tab
$stmtAddr = $pdo->prepare("SELECT * FROM address WHERE business_id = :bid LIMIT 1");
$stmtAddr->execute(['bid' => $businessId]);
$addr = $stmtAddr->fetch(PDO::FETCH_ASSOC);

if (!$addr) {
    $addr = [
        'city'         => '',
        'district'     => '',
        'neighbourhood'=> '',
        'country'      => 'Turkey',
        'address'      => ''
    ];
}

$categories = [
    "Repair","Hair Dresser","Grocery","Restaurant","Cafe",
    "Kiosk","Nail Bar","Pub","Club","Bakery",
    "Flower Shop","Pet-Shop","Gym","Tattoo"
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Business Dashboard</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

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
        
        /* Form specifics */
        .form-control { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; color: white; }
        .btn-primary { background: #3498db; }
        .btn-success { background: #2ecc71; }
        .btn-danger { background: #e74c3c; }
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
            <li><a href="?tab=messages" class="<?php echo $tab=='messages'?'active':''; ?>"><i class="fas fa-comments"></i> Messages</a></li>
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

        <?php if ($tab == 'info'): ?>
            <!-- BUSINESS INFO CONTENT -->
            <h2>Business Information</h2>

            <!--
              IMPORTANT:
              - This form ONLY updates business info (Save Changes)
              - Photo upload/delete uses separate forms below (no nested forms)
            -->
            <form method="POST">
                <input type="hidden" name="update_business" value="1">
                <input type="hidden" name="latitude" value="<?= htmlspecialchars($business['latitude'] ?? '') ?>">
                <input type="hidden" name="longitude" value="<?= htmlspecialchars($business['longitude'] ?? '') ?>">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">

                    <!-- LEFT -->
                    <div>
                        <label>Business Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($business['name']) ?>" required class="form-control">

                        <label>Category</label>
                        <select name="category" required class="form-control">
                            <option value="">Select...</option>
                            <?php
                            $catStmt = $pdo->query("SELECT type FROM categories WHERE business_id IS NULL ORDER BY type ASC");
                            $categoryList = $catStmt->fetchAll(PDO::FETCH_COLUMN);
                            foreach ($categoryList as $c):
                            ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= ($business['category'] == $c ? 'selected' : '') ?>>
                                    <?= htmlspecialchars($c) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label>Phone</label>
                        <input type="text" name="tel_no" value="<?= htmlspecialchars($business['tel_no']) ?>" class="form-control">

                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($business['description']) ?></textarea>

                        <!-- City -->
                        <label>City</label>
                        <select id="citySelect" name="city" required class="form-control">
                            <option value="">Select city...</option>
                        </select>

                        <!-- District -->
                        <label>District</label>
                        <select id="districtSelect" name="district" required class="form-control">
                            <option value="">Select district...</option>
                        </select>

                        <!-- Neighbourhood -->
                        <label>Neighbourhood</label>
                        <select id="neighbourhoodSelect" name="neighbourhood" required class="form-control">
                            <option value="">Select neighbourhood...</option>
                        </select>

                        <!-- Full address -->
                        <label>Full Address</label>
                        <textarea name="address" required class="form-control" rows="2"><?= htmlspecialchars($business['address'] ?? ''); ?></textarea>
                    </div>

                    <!-- RIGHT: MAP + HOURS -->
                    <div>
                        <h4>Select Business Location</h4>
                        <p>Click OR drag the marker to set your business location.</p>

                        <div id="map" style="height:300px; border-radius:10px;"></div>

                        <h4 style="margin-top:20px;">Operating Hours</h4>
                        <table style="width:100%;">
                            <?php foreach ($days as $day): ?>
                                <tr>
                                    <td><?= $day ?></td>
                                    <td><input type="time" name="open_<?= $day ?>" value="<?= $hours[$day]['open'] ?>"></td>
                                    <td><input type="time" name="close_<?= $day ?>" value="<?= $hours[$day]['close'] ?>"></td>
                                    <td><input type="checkbox" name="closed_<?= $day ?>" <?= $hours[$day]['closed'] ? 'checked' : '' ?>> Closed</td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>

                <button type="submit" class="btn btn-success" style="margin-top:20px;">Save Changes</button>
            </form>

            <!-- BUSINESS PHOTOS (SEPARATE SECTION / SEPARATE FORMS) -->
            <h4 style="margin-top: 30px;">Business Photos</h4>
            <p style="color: #666; font-size: 0.9rem;">Upload photos of your business (Max 10 photos). The latest photo will be used as the "cover" photo in listings.</p>

            <?php
            $photoStmt = $pdo->prepare("SELECT * FROM business_photos WHERE business_id = ? ORDER BY created_at DESC");
            $photoStmt->execute([$businessId]);
            $businessPhotos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <?php if (!empty($businessPhotos)): ?>
                <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin: 15px 0;">
                    <?php foreach ($businessPhotos as $photo): ?>
                        <div style="position: relative;">
                            <img src="<?= htmlspecialchars($photo['image_url']) ?>"
                                style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px; border: 2px solid <?= $photo['is_approved'] == 't' ? '#2ecc71' : '#f39c12' ?>;">

                            <div style="position: absolute; top: 5px; right: 5px;">
                                <?php if ($photo['is_approved'] == 't'): ?>
                                    <span style="background: #2ecc71; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.7rem;">Approved</span>
                                <?php else: ?>
                                    <span style="background: #f39c12; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.7rem;">Pending</span>
                                <?php endif; ?>
                            </div>

                            <form method="POST" style="margin-top: 5px;">
                                <input type="hidden" name="delete_business_photo" value="<?= $photo['id'] ?>">
                                <button type="submit" onclick="return confirm('Delete this photo?');"
                                    style="width: 100%; padding: 5px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">
                                    Delete
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Upload New Photos -->
            <?php if (count($businessPhotos) < 10): ?>
                <form method="POST" enctype="multipart/form-data" style="margin: 20px 0; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                    <input type="hidden" name="upload_business_photos" value="1">
                    <label style="display: block; font-weight: 600; margin-bottom: 10px;">Upload New Photos:</label>
                    <input type="file" name="business_photos[]" multiple accept="image/jpeg,image/jpg,image/png,image/webp"
                        style="margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                    <small style="color: #666; display: block; margin-bottom: 10px;">
                        Accepted formats: JPG, JPEG, PNG, WEBP. You can upload up to <?= 10 - count($businessPhotos) ?> more photos.
                    </small>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload Photos
                    </button>
                </form>
            <?php else: ?>
                <p style="color: #e74c3c; font-size: 0.9rem; background: #ffebee; padding: 15px; border-radius: 8px;">
                    Maximum 10 photos reached. Delete some to upload new ones.
                </p>
            <?php endif; ?>

            <script>
                // MAP JS
                let defaultLat = <?= $business['latitude'] ?: "38.4192" ?>;
                let defaultLng = <?= $business['longitude'] ?: "27.1287" ?>;

                const map = L.map('map').setView([defaultLat, defaultLng], 12);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19
                }).addTo(map);

                let marker = L.marker([defaultLat, defaultLng], {
                    draggable: true
                }).addTo(map);

                // Initialize form inputs with default values
                document.getElementsByName("latitude")[0].value = defaultLat;
                document.getElementsByName("longitude")[0].value = defaultLng;

                // Update coordinates when map is clicked
                map.on("click", function(e) {
                    marker.setLatLng(e.latlng);
                    document.getElementsByName("latitude")[0].value = e.latlng.lat;
                    document.getElementsByName("longitude")[0].value = e.latlng.lng;
                });

                // Update coordinates when marker is dragged
                marker.on("dragend", function() {
                    let pos = marker.getLatLng();
                    document.getElementsByName("latitude")[0].value = pos.lat;
                    document.getElementsByName("longitude")[0].value = pos.lng;
                });

                // TURKEY JSON DROPDOWN
                let turkeyData = [];
                let citySelect = document.getElementById("citySelect");
                let districtSelect = document.getElementById("districtSelect");
                let neighbourhoodSelect = document.getElementById("neighbourhoodSelect");

                fetch("turkey.json")
                    .then(res => res.json())
                    .then(data => {
                        turkeyData = data;
                        data.forEach(province => {
                            let opt = document.createElement("option");
                            opt.value = province.Province;
                            opt.textContent = province.Province;
                            if ("<?= $addr['city'] ?? '' ?>" === province.Province) opt.selected = true;
                            citySelect.appendChild(opt);
                        });

                        // Pre-fill district if city is selected
                        if ("<?= $addr['city'] ?? '' ?>") {
                            fillDistricts("<?= $addr['city'] ?? '' ?>");
                            districtSelect.value = "<?= $addr['district'] ?? '' ?>";
                        }

                        // Pre-fill neighbourhood if district is selected
                        if ("<?= $addr['district'] ?? '' ?>") {
                            fillNeighbourhoods("<?= $addr['city'] ?? '' ?>", "<?= $addr['district'] ?? '' ?>");
                            neighbourhoodSelect.value = "<?= $addr['neighbourhood'] ?? '' ?>";
                        }
                    })
                    .catch(err => console.error("Error loading turkey.json:", err));

                function fillDistricts(cityName) {
                    districtSelect.innerHTML = '<option value="">Select district...</option>';
                    neighbourhoodSelect.innerHTML = '<option value="">Select neighbourhood...</option>';
                    if (!cityName) return;
                    let city = turkeyData.find(c => c.Province === cityName);
                    if (!city) return;
                    city.Districts.forEach(d => {
                        let opt = document.createElement("option");
                        opt.value = d.District;
                        opt.textContent = d.District;
                        districtSelect.appendChild(opt);
                    });
                }

                function fillNeighbourhoods(cityName, districtName) {
                    neighbourhoodSelect.innerHTML = '<option value="">Select neighbourhood...</option>';
                    if (!cityName || !districtName) return;
                    let city = turkeyData.find(c => c.Province === cityName);
                    if (!city) return;
                    let district = city.Districts.find(d => d.District === districtName);
                    if (!district) return;
                    district.Towns.forEach(town => {
                        town.Neighbourhoods.forEach(n => {
                            let opt = document.createElement("option");
                            opt.value = n;
                            opt.textContent = n;
                            neighbourhoodSelect.appendChild(opt);
                        });
                    });
                }

                // When city changes: fill districts and update map
                citySelect.addEventListener("change", () => {
                    fillDistricts(citySelect.value);
                    let city = turkeyData.find(c => c.Province === citySelect.value);
                    if (city) {
                        let [lat, lng] = city.Coordinates.split(",").map(Number);
                        map.setView([lat, lng], 9);
                        marker.setLatLng([lat, lng]);
                        document.getElementsByName("latitude")[0].value = lat;
                        document.getElementsByName("longitude")[0].value = lng;
                    }
                });

                // When district changes: fill neighbourhoods and update map
                districtSelect.addEventListener("change", () => {
                    fillNeighbourhoods(citySelect.value, districtSelect.value);
                    let city = turkeyData.find(c => c.Province === citySelect.value);
                    let district = city?.Districts.find(d => d.District === districtSelect.value);
                    if (district) {
                        let [lat, lng] = district.Coordinates.split(",").map(Number);
                        map.setView([lat, lng], 12);
                        marker.setLatLng([lat, lng]);
                        document.getElementsByName("latitude")[0].value = lat;
                        document.getElementsByName("longitude")[0].value = lng;
                    }
                });
            </script>

        <?php elseif ($tab == 'products'): ?>
            <!-- PRODUCTS CONTENT -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <h3>Add New Product</h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" style="display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap;">
                        <input type="hidden" name="add_product" value="1">
                        
                        <div style="flex:1; min-width:200px;">
                            <label>Name</label><br>
                            <input type="text" name="p_name" required class="form-control">
                        </div>

                        <div style="flex:1; min-width:150px;">
                            <label>Category</label><br>
                            <select name="p_category" required class="form-control">
                                <option value="" disabled selected>Select...</option>
                                <?php foreach($categoryList as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="flex:0 0 120px;">
                            <label>Price</label><br>
                            <input type="number" step="0.01" name="p_price" required class="form-control">
                        </div>
                        <!-- DISCOUNT -->
<div style="width:100%; margin-top:8px;">
    <input type="checkbox" id="is_discounted" name="is_discounted" onchange="toggleDiscountFields(this.checked)">
    <label for="is_discounted"><b>Discount</b></label>
</div>

<div id="discountFields" style="display:none; width:100%; gap:15px; margin-top:8px; align-items:flex-end;">
    <div style="flex:0 0 160px;">
        <label>Discount %</label><br>
        <input type="number" name="discount_percent" min="1" max="99" class="form-control" placeholder="e.g. 20">
    </div>

    <div style="flex:0 0 200px;">
        <label>Discounted Price (optional)</label><br>
        <input type="number" step="0.01" name="discounted_price" class="form-control" placeholder="e.g. 160">
    </div>

    <div style="color:#777; font-size:0.85rem;">
        Fill <b>%</b> OR <b>new price</b>.
    </div>
</div>

<script>
function toggleDiscountFields(on){
    document.getElementById("discountFields").style.display = on ? "flex" : "none";
}
</script>


                        <div style="flex:1; min-width:200px;">
                            <label>Photo</label><br>
                            <input type="file" name="p_image" accept="image/*" class="form-control">
                        </div>

                        <div style="flex:2; min-width:300px;">
                            <label>Description</label><br>
                            <input type="text" name="p_description" class="form-control">
                        </div>


                        <div style="width:100%; margin-top:5px;">
                            <input type="checkbox" id="neg" name="p_negotiable"> 
                            <label for="neg">Price is Negotiable</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top:10px;">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Product Catalog (Page <?= $prodPage ?> of <?= $totalProdPages ?: 1 ?>)</h3>
                </div>
                <div class="card-body">
                    <table width="100%">
                        <thead>
                            <tr>
                                <th width="80">Image</th>
                                <th>Details</th>
                                <th>Category</th>
                                <th>Pricing</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($products)): ?>
                                <tr><td colspan="5" style="text-align:center; padding:20px;">No products yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($products as $p): ?>
                                <tr>
                                    <td>
                                        <?php if(!empty($p['image_url'])): ?>
                                            <img src="<?= htmlspecialchars($p['image_url']) ?>" class="product-img-thumb" alt="Img">
                                        <?php else: ?>
                                            <div class="product-img-thumb" style="background:#eee; display:flex; align-items:center; justify-content:center; color:#999;"><i class="fas fa-image"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($p['name']) ?></strong><br>
                                        <small style="color:#666;"><?= htmlspecialchars($p['description']) ?></small>
                                    </td>
                                    <td><span style="background:#eee; padding:3px 8px; border-radius:10px; font-size:0.8rem;"><?= htmlspecialchars($p['categories']) ?></span></td>
                                    <td>
                                        <form method="POST" style="display:flex; align-items:center; gap:5px;">
                                            <input type="hidden" name="update_price" value="1">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <input type="number" step="0.01" name="new_price" value="<?= $p['product_prices'] ?>" style="width:70px; padding:4px; border:1px solid #ddd; border-radius:4px;">
                                            <button type="submit" class="btn btn-success" style="padding:5px;"><i class="fas fa-check"></i></button>
                                        </form>
                                        <?php 
                                            $isNeg = $p['is_negotiable'] ?? false; 
                                            if($isNeg === true || $isNeg === 't' || $isNeg === 1): 
                                        ?>
                                            <small style="color:orange; display:block;"><i class="fas fa-handshake"></i> Negotiable</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Delete this product?');">
                                            <input type="hidden" name="delete_product" value="1">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-danger" style="padding:5px 10px;"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if ($totalProdPages > 1): ?>
                    <div class="pagination">
                        <?php 
                        if($prodPage > 1) echo '<a href="?tab=products&prod_page='.($prodPage-1).'">&laquo; Prev</a>';
                        for($i = 1; $i <= $totalProdPages; $i++) {
                            $active = ($prodPage == $i) ? 'active' : '';
                            echo '<a href="?tab=products&prod_page='.$i.'" class="'.$active.'">'.$i.'</a>';
                        }
                        if($prodPage < $totalProdPages) echo '<a href="?tab=products&prod_page='.($prodPage+1).'">Next &raquo;</a>';
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($tab == 'reviews'): ?>
            <!-- REVIEWS CONTENT -->
            <div class="card">
                <div class="card-header">
                    <h3>Customer Reviews</h3>
                </div>
                <div class="card-body">
                    <?php if(empty($reviews)): ?>
                        <div style="text-align:center; padding:30px; color:#666;">No reviews yet.</div>
                    <?php else: ?>
                        <div style="display:flex; flex-direction:column; gap:20px;">
                            <?php foreach ($reviews as $r): ?>
                            <div style="border:1px solid #eee; padding:15px; border-radius:8px;">
                                
                                <div style="display:flex; justify-content:space-between;">
                                    <strong><?= htmlspecialchars($r['full_name']) ?></strong>
                                    <span style="color:#f1c40f;">
                                        <?= str_repeat('★', $r['rank']) ?>
                                    </span>
                                </div>
                                
                                <p style="margin:10px 0; color:#444;"><?= nl2br(htmlspecialchars($r['comments'])) ?></p>
                                
                                <?php 
                                    $isRevApproved = $r['is_approved'] ?? false;
                                    if($isRevApproved != 't' && $isRevApproved !== true && $isRevApproved != 1): 
                                ?>
                                    <div style="background:#fff3cd; color:#856404; padding:5px; font-size:0.8rem; border-radius:3px; margin-bottom:10px;">
                                        <i class="fas fa-clock"></i> This review is waiting for admin approval before going public.
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($r['response_text'])): ?>
                                    <div style="background:#f9f9f9; padding:10px; border-left:4px solid #3498db; margin-top:10px;">
                                        <div style="display:flex; justify-content:space-between;">
                                            <strong style="color:#2c3e50;"><i class="fas fa-reply"></i> Your Response:</strong>
                                            <?php 
                                                $isRespApproved = $r['resp_approved'] ?? false;
                                                if($isRespApproved == 't' || $isRespApproved === true || $isRespApproved == 1): 
                                            ?>
                                                <span style="background:#d4edda; color:#155724; padding:2px 6px; font-size:0.7rem; border-radius:3px;">Live</span>
                                            <?php else: ?>
                                                <span style="background:#fff3cd; color:#856404; padding:2px 6px; font-size:0.7rem; border-radius:3px;">Pending Approval</span>
                                            <?php endif; ?>
                                        </div>
                                        <p style="margin:5px 0; font-size:0.9rem;"><?= nl2br(htmlspecialchars($r['response_text'])) ?></p>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-top:10px; border-top:1px dashed #eee; padding-top:10px;">
                                        <button onclick="document.getElementById('reply-form-<?= $r['review_id'] ?>').style.display='block'; this.style.display='none';" 
                                                style="background:none; border:1px solid #3498db; color:#3498db; padding:5px 10px; border-radius:4px; cursor:pointer;">
                                            Reply
                                        </button>
                                        <form id="reply-form-<?= $r['review_id'] ?>" method="POST" style="display:none; margin-top:10px;">
                                            <input type="hidden" name="respond_to_review" value="1">
                                            <input type="hidden" name="review_id" value="<?= $r['review_id'] ?>">
                                            <textarea name="response_text" rows="3" required style="width:100%; padding:10px; border:1px solid #ddd;"></textarea>
                                            <button type="submit" class="btn btn-primary" style="margin-top:5px;">Post Response</button>
                                        </form>
                                    </div>
                                <?php endif; ?>

                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($tab == 'pricelist'): ?>
            <!-- PRICELIST CONTENT -->
            <div class="card">
                <div class="card-header">
                    <h3>Manage Price Lists</h3>
                </div>
                <div class="card-body">
                    <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px dashed #ccc;">
                        <form method="POST" enctype="multipart/form-data" style="display: flex; align-items: center; gap: 15px;">
                            <input type="hidden" name="upload_pricelist" value="1">
                            <div style="flex: 1;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Upload New Price List / Menu Image</label>
                                <input type="file" name="pl_image" accept="image/*" required class="form-control" style="background: white;">
                            </div>
                            <button type="submit" class="btn btn-primary" style="height: 42px; margin-top: 18px;">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                        </form>
                    </div>

                    <h4 style="margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Current Price Lists</h4>
                    
                    <?php if(empty($priceLists)): ?>
                        <p style="color: #777; text-align: center; margin-top: 20px;">No price lists uploaded yet.</p>
                    <?php else: ?>
                        <div class="pl-grid">
                            <?php foreach($priceLists as $pl): ?>
                                <div class="pl-item">
                                    <a href="<?= htmlspecialchars($pl['image_url']) ?>" target="_blank">
                                        <img src="<?= htmlspecialchars($pl['image_url']) ?>" alt="Price List">
                                    </a>
                                    <div style="padding: 10px;">
                                        <small style="color: #999; display: block; margin-bottom: 5px;"><?= date('M d, Y', strtotime($pl['created_at'])) ?></small>
                                        <form method="POST" onsubmit="return confirm('Delete this price list?');">
                                            <input type="hidden" name="delete_pricelist" value="1">
                                            <input type="hidden" name="pl_id" value="<?= $pl['id'] ?>">
                                            <button type="submit" class="btn btn-danger" style="width: 100%;">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($tab == 'messages'): ?>
    <div style="display:flex; gap:16px;">
      <div class="card" style="flex:0 0 320px; max-height:650px; overflow:auto;">
        <h3 style="margin-top:0;">Customer Messages</h3>
        <div id="bizChatList" style="display:flex; flex-direction:column; gap:10px;"></div>
      </div>

      <div class="card" style="flex:1; display:flex; flex-direction:column; min-height:650px;">
        <h3 id="bizChatTitle" style="margin:0;">Select a Customer</h3>
        <div id="bizMsgBox" style="flex:1; margin-top:12px; padding:12px; border:1px solid #eee; border-radius:12px; background:#fafafa; overflow:auto;">
           <div style="color:#777;">Please select a conversation to start messaging.</div>
        </div>
        <div style="display:flex; gap:10px; margin-top:12px;">
          <input id="bizMsgInput" class="form-control" type="text" placeholder="Type your reply..." disabled>
          <button id="bizSendBtn" class="btn btn-primary" disabled>Send</button>
        </div>
      </div>
    </div>

    <script>
    let currentBizChatId = null;
    let bizPollTimer = null;

    async function loadBizChats(){
      const res = await fetch('biz_chat_list.php');
      const data = await res.json();
      const list = document.getElementById('bizChatList');
      list.innerHTML = '';
      data.chats.forEach(c => {
        const item = document.createElement('div');
        item.className = 'pl-item'; // Mevcut stilini kullanabilirsin
        item.style.cursor = 'pointer';
        item.style.textAlign = 'left';
        item.style.padding = '10px';
        item.style.background = (currentBizChatId == c.chat_id) ? '#e3f2fd' : '#fff';
        item.innerHTML = `<strong>${c.full_name}</strong><br><small>${c.last_message || 'No messages'}</small>`;
        item.onclick = () => openBizChat(c.chat_id, c.full_name);
        list.appendChild(item);
      });
    }

    async function openBizChat(id, name){
      currentBizChatId = id;
      document.getElementById('bizChatTitle').textContent = "Chat with " + name;
      document.getElementById('bizMsgInput').disabled = false;
      document.getElementById('bizSendBtn').disabled = false;
      loadBizMessages();
      if(bizPollTimer) clearInterval(bizPollTimer);
      bizPollTimer = setInterval(loadBizMessages, 3000);
    }

    async function loadBizMessages(){
      if(!currentBizChatId) return;
      const res = await fetch('biz_chat_fetch_messages.php?chat_id=' + currentBizChatId);
      const data = await res.json();
      const box = document.getElementById('bizMsgBox');
      box.innerHTML = '';
      data.messages.forEach(m => {
        const isBiz = (m.sender_type === 'business');
        box.innerHTML += `<div style="margin: 8px 0; text-align: ${isBiz?'right':'left'};">
          <div style="display:inline-block; padding:8px 12px; border-radius:12px; background:${isBiz?'#dcf8c6':'#fff'}; border:1px solid #ddd;">
            ${m.content}
          </div>
        </div>`;
      });
      box.scrollTop = box.scrollHeight;
    }

    document.getElementById('bizSendBtn').onclick = async () => {
      const inp = document.getElementById('bizMsgInput');
      if(!inp.value.trim()) return;
      await fetch('biz_chat_send.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `chat_id=${currentBizChatId}&content=${encodeURIComponent(inp.value)}`
      });
      inp.value = '';
      loadBizMessages();
    };

    loadBizChats();
    </script>
<?php endif; ?>

    </div>
</body>
</html>
