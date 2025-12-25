<?php
session_start();
require "db_connect.php";

if (!isset($_GET['id'])) {
    die("Business ID missing.");
}

$businessId = intval($_GET['id']);

// Fetch business
$stmt = $pdo->prepare("SELECT * FROM business WHERE shop_id = ?");
$stmt->execute([$businessId]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$business) {
    die("Business not found.");
}

// Fetch address
$stmtAddr = $pdo->prepare("SELECT * FROM address WHERE business_id = ?");
$stmtAddr->execute([$businessId]);
$addr = $stmtAddr->fetch(PDO::FETCH_ASSOC);

// Fetch products
$stmtProd = $pdo->prepare("
    SELECT p.*, 
        (SELECT image_url FROM photos ph WHERE ph.product_id = p.id LIMIT 1) AS image_url
    FROM products p
    WHERE p.business_id = ?
");
$stmtProd->execute([$businessId]);
$products = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

// Fetch reviews
$stmtReviews = $pdo->prepare("
    SELECT r.*, u.full_name
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.business_id = ?
    ORDER BY r.time DESC
");
$stmtReviews->execute([$businessId]);
$reviews = $stmtReviews->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($business['name']) ?></title>
    <link rel="stylesheet" href="style.css">

    <style>
        body { font-family: system-ui; background: #f4f5fb; margin: 0; }
        .container { width: 80%; margin: auto; padding: 20px; }

        .business-header { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        .business-header h1 { margin: 0; }
        .fav-btn {
            background: #ffca28; color: black; padding: 8px 14px;
            border-radius: 8px; text-decoration:none; font-weight:600;
        }
        .fav-btn:hover { background:#ffb300; }

        .section { background:white; padding:20px; border-radius:12px; margin-bottom:20px; }

        .product-card {
            display:flex; gap:15px; padding:12px; border-bottom:1px solid #eee;
        }
        .product-img { width:80px; height:80px; object-fit:cover; border-radius:8px; }

        .review { padding:10px 0; border-bottom:1px solid #eee; }
    </style>
</head>

<body>
<div class="container">

    <!-- Header -->
    <div class="business-header">
        <h1><?= htmlspecialchars($business['name']) ?></h1>
        <p><?= htmlspecialchars($business['description']) ?></p>

        <a href="add_fav_business.php?id=<?= $businessId ?>" class="fav-btn">⭐ Add to Favorites</a>
    </div>

    <!-- Address -->
    <div class="section">
        <h2>📍 Address</h2>
        <?php if ($addr): ?>
            <p><?= htmlspecialchars($addr['address']) ?><br>
               <?= htmlspecialchars($addr['city']) ?> - <?= htmlspecialchars($addr['district']) ?>
            </p>
        <?php else: ?>
            <p>No address found.</p>
        <?php endif; ?>
    </div>

    <!-- Products -->
    <div class="section">
        <h2>🛒 Products</h2>

        <?php if (empty($products)): ?>
            <p>No products yet.</p>
        <?php else: ?>
            <?php foreach ($products as $p): ?>
                <div class="product-card">
                    <?php if ($p['image_url']): ?>
                        <img class="product-img" src="<?= $p['image_url'] ?>">
                    <?php endif; ?>

                    <div>
                        <strong><?= htmlspecialchars($p['name']) ?></strong><br>
                        <?= htmlspecialchars($p['description']) ?><br>
                        <b>$<?= $p['product_prices'] ?></b>
                        <br>
                        <a href="add_fav_product.php?id=<?= $p['id'] ?>" class="fav-btn" style="font-size:0.8rem;">
                            ⭐ Favorite Product
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Reviews -->
    <div class="section">
        <h2>⭐ Reviews</h2>
        <?php if (empty($reviews)): ?>
            <p>No reviews yet.</p>
        <?php else: ?>
            <?php foreach ($reviews as $r): ?>
                <div class="review">
                    <b><?= htmlspecialchars($r['full_name']) ?></b> – <?= $r['rank'] ?>★<br>
                    <?= htmlspecialchars($r['comments']) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
