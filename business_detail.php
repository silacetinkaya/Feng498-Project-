<?php
require_once "db_connect.php";

if (!isset($_GET['id'])) {
    die("Business not found.");
}

$businessId = (int)$_GET['id'];

/* BUSINESS INFO */
$stmt = $pdo->prepare("
    SELECT b.*, COALESCE(AVG(r.rank),0) AS rating
    FROM business b
    LEFT JOIN reviews r ON r.business_id = b.shop_id
    WHERE b.shop_id = ?
    GROUP BY b.shop_id
");
$stmt->execute([$businessId]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$business) {
    die("Business not found.");
}

/* PRODUCTS */
$stmtP = $pdo->prepare("SELECT * FROM products WHERE business_id = ?");
$stmtP->execute([$businessId]);
$products = $stmtP->fetchAll(PDO::FETCH_ASSOC);

/* REVIEWS */
$stmtR = $pdo->prepare("
    SELECT r.*, u.full_name
    FROM reviews r
    JOIN users u ON u.id = r.user_id
    WHERE r.business_id = ?
    ORDER BY r.time DESC
");
$stmtR->execute([$businessId]);
$reviews = $stmtR->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($business['name']) ?></title>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f5fb;
    padding: 30px;
}

.card {
    background: white;
    border-radius: 12px;
    border: 1px solid #ddd;
    padding: 25px;
    margin-bottom: 20px;
}

h1 { margin-top: 0; color: #e53935; }
.badge {
    background: #e53935;
    color: white;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.8rem;
}
</style>
</head>
<body>
<div class="card">
    <h1><?= htmlspecialchars($business['name']) ?></h1>
    <span class="badge"><?= $business['category'] ?></span>
    <p style="margin-top:10px; color:#666;">
        ⭐ <?= number_format($business['rating'],1) ?> Rating
    </p>
</div>

<div class="card">
    <h3>Business Information</h3>
    <p><?= nl2br(htmlspecialchars($business['description'])) ?></p>
    <p><b>Phone:</b> <?= htmlspecialchars($business['tel_no']) ?></p>
    <p><b>Address:</b> <?= htmlspecialchars($business['address']) ?></p>
</div>
<div class="card">
    <h3>Location</h3>
    <div id="map" style="height:300px; border-radius:10px;"></div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('map').setView(
    [<?= $business['latitude'] ?: 38.4192 ?>, <?= $business['longitude'] ?: 27.1287 ?>],
    14
);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

L.marker([
    <?= $business['latitude'] ?>,
    <?= $business['longitude'] ?>
]).addTo(map);
</script>
<div class="card">
    <h3>Products & Services</h3>

    <?php if (empty($products)): ?>
        <p>No products yet.</p>
    <?php else: ?>
        <?php foreach ($products as $p): ?>
            <p>
                <b><?= htmlspecialchars($p['name']) ?></b> – 
                <?= $p['product_prices'] ?> TL
            </p>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<div class="card">
    <h3>Customer Reviews</h3>

    <?php if (empty($reviews)): ?>
        <p>No reviews yet.</p>
    <?php else: ?>
        <?php foreach ($reviews as $r): ?>
            <div style="border-top:1px solid #eee; padding-top:10px; margin-top:10px;">
                <b><?= htmlspecialchars($r['full_name']) ?></b>
                – <?= str_repeat("⭐", $r['rank']) ?>
                <p><?= nl2br(htmlspecialchars($r['comments'])) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
