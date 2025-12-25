<?php
session_start();
require "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

$userId = $_SESSION['user_id'];

/* ---------------------------------------------
   FAVORİ İŞLETMELERİ ÇEK
----------------------------------------------*/
$stmt = $pdo->prepare("
    SELECT bf.id, b.shop_id, b.name, b.category, b.address
    FROM business_favorites bf
    JOIN business b ON b.shop_id = bf.business_id
    WHERE bf.user_id = :uid
");
$stmt->execute(['uid' => $userId]);
$favoriteBusinesses = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------------------------------------
   FAVORİ ÜRÜNLERİ ÇEK
----------------------------------------------*/
$stmt = $pdo->prepare("
    SELECT pf.id, p.id AS product_id, p.name, p.description, 
           p.product_prices, p.categories, p.available
    FROM product_favorites pf
    JOIN products p ON p.id = pf.product_id
    WHERE pf.user_id = :uid
");
$stmt->execute(['uid' => $userId]);
$favoriteProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Favorites</title>

    <style>
        body {
            margin: 0;
            background: #f4f5fb;
            font-family: system-ui, sans-serif;
        }

        .container {
            padding: 30px;
        }

        h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        h2 {
            margin-top: 30px;
            font-size: 1.4rem;
            color: #e53935;
        }

        .card-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
            margin-top: 15px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #ddd;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: 0.2s;
        }

        .card:hover {
            transform: translateY(-3px);
        }

        .title {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .desc {
            color: #555;
            font-size: 0.9rem;
            margin-top: 4px;
        }

        .price {
            color: #2ecc71;
            font-weight: bold;
            margin-top: 8px;
        }

        .empty {
            padding: 20px;
            text-align: center;
            font-size: 1rem;
            color: #777;
        }

        .remove-btn {
            margin-top: 10px;
            display: inline-block;
            background: #e53935;
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            text-decoration: none;
        }

        .remove-btn:hover {
            background: #b71c1c;
        }
    </style>
</head>
<body>

<div class="container">

    <h1>⭐ Your Favorites</h1>

    <!-- FAVORİ İŞLETMELER -->
    <h2>Favorite Businesses</h2>

    <?php if (empty($favoriteBusinesses)): ?>
        <div class="empty">You have no favorite businesses yet.</div>
    <?php else: ?>
        <div class="card-list">
            <?php foreach ($favoriteBusinesses as $b): ?>
                <div class="card">
                    <div class="title"><?= htmlspecialchars($b['name']) ?></div>
                    <div class="desc"><?= htmlspecialchars($b['category']) ?></div>
                    <div class="desc"><?= htmlspecialchars($b['address']) ?></div>
                    <a href="remove_fav_business.php?id=<?= $b['id'] ?>" class="remove-btn">Remove</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>


    <!-- FAVORİ ÜRÜNLER -->
    <h2>Favorite Products</h2>

    <?php if (empty($favoriteProducts)): ?>
        <div class="empty">You have no favorite products yet.</div>
    <?php else: ?>
        <div class="card-list">
            <?php foreach ($favoriteProducts as $p): ?>
                <div class="card">
                    <div class="title"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="desc"><?= htmlspecialchars($p['description']) ?></div>
                    <div class="price">$<?= htmlspecialchars($p['product_prices']) ?></div>
                    <a href="remove_fav_product.php?id=<?= $p['id'] ?>" class="remove-btn">Remove</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
