<?php
session_start();
require_once "db_connect.php";




if (!isset($_GET['id'])) {
    die("Business not found.");
}

$businessId = (int)$_GET['id'];

/**
 * Fix image paths for MAMP projects like:
 * http://localhost:8888/Feng498-Project-/
 * so "uploads/..." becomes "/Feng498-Project-/uploads/..."
 */
$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
function asset($path) {
    global $BASE;
    if (empty($path)) return '';
    return $BASE . '/' . ltrim((string)$path, '/');
}

function isTruePg($v) {
    return $v === true || $v === 1 || $v === '1' || $v === 't' || $v === 'true';
}

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

/* BUSINESS PHOTOS (approved only) */
$stmtPhotos = $pdo->prepare("
    SELECT image_url
    FROM business_photos
    WHERE business_id = ? AND is_approved = TRUE
    ORDER BY created_at DESC
");
$stmtPhotos->execute([$businessId]);
$businessPhotos = $stmtPhotos->fetchAll(PDO::FETCH_ASSOC);

/* COVER PHOTO = latest approved photo */
$coverPhoto = !empty($businessPhotos) ? $businessPhotos[0]['image_url'] : null;

/* PRODUCTS + PRODUCT IMAGE (latest approved per product) */
$stmtP = $pdo->prepare("
    SELECT p.*,
           (
             SELECT ph.image_url
             FROM photos ph
             WHERE ph.product_id = p.id
               AND ph.is_approved = TRUE
             ORDER BY ph.photo_id DESC
             LIMIT 1
           ) AS product_image
    FROM products p
    WHERE p.business_id = ?
    ORDER BY p.id DESC
");
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

/* MAP SAFE DEFAULTS */
$lat = !empty($business['latitude']) ? (float)$business['latitude'] : 38.4192;
$lng = !empty($business['longitude']) ? (float)$business['longitude'] : 27.1287;
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
.cover {
    width: 100%;
    height: 260px;
    object-fit: cover;
    border-radius: 12px;
    margin-top: 15px;
    border: 1px solid #ddd;
}
.product-row {
    display: flex;
    gap: 12px;
    align-items: center;
    border-top: 1px solid #eee;
    padding-top: 12px;
    margin-top: 12px;
}
.product-img {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #ddd;
    background: #f7f7f7;
}
.product-img-placeholder {
    width: 70px;
    height: 70px;
    border-radius: 10px;
    border: 1px solid #ddd;
    background: #f2f2f2;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
    font-size: 12px;
}
.discount-badge {
    margin-left: 10px;
    background: #e53935;
    color: #fff;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}
</style>
</head>
<body>

<!-- HEADER CARD -->
<div class="card">
  <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:15px;">
    <div>
      <h1 style="margin-bottom:8px;"><?= htmlspecialchars($business['name']) ?></h1>
      <span class="badge"><?= htmlspecialchars($business['category'] ?? '') ?></span>

      <p style="margin-top:10px; color:#666;">
        ⭐ <?= number_format((float)$business['rating'], 1) ?> Rating
      </p>
    </div>

    <?php if (isset($_SESSION['user_id']) && ((int)$_SESSION['user_id'] !== (int)($business['owner_id'] ?? 0))): ?>
      <a href="chat_start.php?business_id=<?= (int)$businessId ?>"
         target="_top"
         style="text-decoration:none;"
         onclick="window.top.location.href=this.href; return false;">
        <button style="background:#3498db; color:white; border:none; padding:10px 14px; border-radius:10px; cursor:pointer; font-weight:700;">
          💬 Message
        </button>
      </a>
    <?php endif; ?>
  </div>

  <?php if (!empty($coverPhoto)): ?>
    <img src="<?= htmlspecialchars(asset($coverPhoto)) ?>" class="cover" alt="Business Cover">
  <?php endif; ?>
</div>




<!-- BUSINESS PHOTOS GRID -->
<?php if (!empty($businessPhotos)): ?>
    <div class="card">
        <h3>Business Photos</h3>

        <div style="
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 15px;
            margin-top: 15px;
        ">
            <?php foreach ($businessPhotos as $photo): ?>
                <img
                    src="<?= htmlspecialchars(asset($photo['image_url'])) ?>"
                    style="
                        width: 100%;
                        height: 180px;
                        object-fit: cover;
                        border-radius: 10px;
                        border: 1px solid #ddd;
                    "
                    alt="Business Photo"
                >
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- BUSINESS INFO -->
<div class="card">
    <h3>Business Information</h3>
    <p><?= nl2br(htmlspecialchars($business['description'] ?? '')) ?></p>
    <p><b>Phone:</b> <?= htmlspecialchars($business['tel_no'] ?? '') ?></p>
    <p><b>Address:</b> <?= htmlspecialchars($business['address'] ?? '') ?></p>
</div>

<!-- MAP -->
<div class="card">
    <h3>Location</h3>
    <div id="map" style="height:300px; border-radius:10px;"></div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('map').setView([<?= $lat ?>, <?= $lng ?>], 14);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
L.marker([<?= $lat ?>, <?= $lng ?>]).addTo(map);
</script>

<!-- PRODUCTS -->
<div class="card">
    <h3>Products & Services</h3>

    <?php if (empty($products)): ?>
        <p>No products yet.</p>
    <?php else: ?>
        <?php foreach ($products as $p): ?>
            <?php $isDisc = isTruePg($p['is_discounted'] ?? false); ?>

            <div class="product-row">
                <?php if (!empty($p['product_image'])): ?>
                    <img src="<?= htmlspecialchars(asset($p['product_image'])) ?>" class="product-img" alt="Product Image">
                <?php else: ?>
                    <div class="product-img-placeholder">No Image</div>
                <?php endif; ?>

                <div>
                    <div style="font-weight:700;"><?= htmlspecialchars($p['name'] ?? '') ?></div>

                    <?php if ($isDisc): ?>
                        <div style="margin-top:4px;">
                            <span style="text-decoration:line-through; color:#999; margin-right:8px;">
                                <?= number_format((float)($p['original_price'] ?? 0), 2) ?> TL
                            </span>

                            <span style="font-weight:900; color:#333;">
                                <?= number_format((float)($p['discounted_price'] ?? 0), 2) ?> TL
                            </span>

                            <?php if (!empty($p['discount_percent'])): ?>
                                <span class="discount-badge">
                                    -<?= (int)$p['discount_percent'] ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="margin-top:4px; color:#666;">
                            <?= number_format((float)($p['product_prices'] ?? 0), 2) ?> TL
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- REVIEWS -->
<div class="card">
    <h3>Customer Reviews</h3>

    <?php if (empty($reviews)): ?>
        <p>No reviews yet.</p>
    <?php else: ?>
        <?php foreach ($reviews as $r): ?>
            <div style="border-top:1px solid #eee; padding-top:10px; margin-top:10px;">
                <b><?= htmlspecialchars($r['full_name'] ?? '') ?></b>
                – <?= str_repeat("⭐", (int)($r['rank'] ?? 0)) ?>
                <p><?= nl2br(htmlspecialchars($r['comments'] ?? '')) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
