<?php
session_start();
require_once "db_connect.php";

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;

// Check if business ID is provided
if (!isset($_GET['id'])) {
    die("Business not found.");
}

$businessId = (int)$_GET['id'];

// Handle Favorite Toggle
if (isset($_GET['toggle_favorite']) && $isLoggedIn) {
    $favCheck = $pdo->prepare("SELECT id FROM business_favorites WHERE user_id = ? AND business_id = ?");
    $favCheck->execute([$userId, $businessId]);

    if ($favCheck->rowCount() > 0) {
        $pdo->prepare("DELETE FROM business_favorites WHERE user_id = ? AND business_id = ?")
            ->execute([$userId, $businessId]);
    } else {
        $pdo->prepare("INSERT INTO business_favorites (user_id, business_id, created_at) VALUES (?, ?, NOW())")
            ->execute([$userId, $businessId]);
    }

    header("Location: business_detail.php?id=$businessId");
    exit;
}

// Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && $isLoggedIn) {
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);

    if ($rating >= 1 && $rating <= 5 && !empty($comment)) {
        try {
            // Check if user already reviewed this business
            $checkReview = $pdo->prepare("SELECT review_id FROM reviews WHERE user_id = ? AND business_id = ?");
            $checkReview->execute([$userId, $businessId]);

            if ($checkReview->rowCount() > 0) {
                // Update existing review
                $updateReview = $pdo->prepare("UPDATE reviews SET rank = ?, comments = ?, time = NOW(), is_approved = FALSE WHERE user_id = ? AND business_id = ?");
                $updateReview->execute([$rating, $comment, $userId, $businessId]);
                $reviewMessage = "review_updated";
            } else {
                // Insert new review
                $insReview = $pdo->prepare("INSERT INTO reviews (user_id, business_id, rank, comments, time, is_approved) VALUES (?, ?, ?, ?, NOW(), FALSE)");
                $insReview->execute([$userId, $businessId, $rating, $comment]);
                $reviewMessage = "review_submitted";
            }

            header("Location: business_detail.php?id=$businessId&msg=$reviewMessage");
            exit;
        } catch (PDOException $e) {
            // Handle duplicate review error
            header("Location: business_detail.php?id=$businessId&msg=review_exists");
            exit;
        }
    }
}

// Handle Report Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report']) && $isLoggedIn) {
    $reportType = $_POST['report_type'];
    $reportReason = trim($_POST['report_reason']);

    if (!empty($reportReason)) {
        $insReport = $pdo->prepare("INSERT INTO reports (user_id, reason, report_type, status) VALUES (?, ?, ?, 'Pending')");
        $insReport->execute([$userId, "Business ID: $businessId - " . $reportReason, $reportType]);

        header("Location: business_detail.php?id=$businessId&msg=report_submitted");
        exit;
    }
}

/* BUSINESS INFO */
$stmt = $pdo->prepare("
    SELECT b.*, COALESCE(AVG(r.rank),0) AS rating
    FROM business b
    LEFT JOIN reviews r ON r.business_id = b.shop_id AND r.is_approved = TRUE
    WHERE b.shop_id = ?
    GROUP BY b.shop_id
");
$stmt->execute([$businessId]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$business) {
    die("Business not found.");
}

// Check if business is favorited by current user
$isFavorited = false;
if ($isLoggedIn) {
    $favStmt = $pdo->prepare("SELECT id FROM business_favorites WHERE user_id = ? AND business_id = ?");
    $favStmt->execute([$userId, $businessId]);
    $isFavorited = $favStmt->rowCount() > 0;
}

/* PRODUCTS */
$stmtP = $pdo->prepare("SELECT * FROM products WHERE business_id = ?");
$stmtP->execute([$businessId]);
$products = $stmtP->fetchAll(PDO::FETCH_ASSOC);

/* REVIEWS - Only show approved reviews */
$stmtR = $pdo->prepare("
    SELECT r.*, u.full_name
    FROM reviews r
    JOIN users u ON u.id = r.user_id
    WHERE r.business_id = ? AND r.is_approved = TRUE
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

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f5fb;
            padding: 80px 30px 30px 30px;
            /* Added top padding for fixed buttons */
        }

        .top-actions {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }

        .btn-back,
        .btn-favorite,
        .btn-report {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .btn-back {
            background: #666;
            color: white;
        }

        .btn-back:hover {
            background: #555;
        }

        .btn-favorite {
            background: white;
            border: 2px solid #e53935;
            color: #e53935;
        }

        .btn-favorite.active {
            background: #e53935;
            color: white;
        }

        .btn-favorite:hover {
            transform: scale(1.05);
        }

        .btn-report {
            background: #ff9800;
            color: white;
        }

        .btn-report:hover {
            background: #f57c00;
        }

        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid #ddd;
            padding: 25px;
            margin-bottom: 20px;
        }

        h1 {
            margin-top: 0;
            color: #e53935;
        }

        .badge {
            background: #e53935;
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
        }

        .review-form,
        .report-form {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .star-rating-input {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
            font-size: 2rem;
            margin: 10px 0;
        }

        .star-rating-input input[type="radio"] {
            display: none;
        }

        .star-rating-input label {
            cursor: pointer;
            color: #ddd;
            transition: color 0.2s;
        }

        .star-rating-input input[type="radio"]:checked~label,
        .star-rating-input label:hover,
        .star-rating-input label:hover~label {
            color: #f1c40f;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 12px;
            width: 500px;
            max-width: 90%;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
    </style>
</head>

<body>

    <div class="top-actions">
        <?php if ($isLoggedIn): ?>
            <button class="btn-report" onclick="document.getElementById('reportModal').style.display='block'">
                <i class="fas fa-flag"></i> Report
            </button>
            <a href="business_detail.php?id=<?= $businessId ?>&toggle_favorite=1" class="btn-favorite <?= $isFavorited ? 'active' : '' ?>">
                <i class="fas fa-star"></i>
                <?= $isFavorited ? 'Favorited' : 'Add to Favorites' ?>
            </a>
        <?php endif; ?>
        <a href="user_panel.php?tab=home" class="btn-back" target="_parent">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>
    </div>
    <!-- Photo Slideshow -->
    <?php
    $photoStmt = $pdo->prepare("SELECT * FROM business_photos WHERE business_id = ? AND is_approved = TRUE ORDER BY created_at DESC");
    $photoStmt->execute([$businessId]);
    $businessPhotos = $photoStmt->fetchAll();
    ?>

    <?php if (!empty($businessPhotos)): ?>
        <div class="card">
            <h3>Business Photos</h3>
            <div style="position: relative; max-width: 800px; margin: 0 auto;">
                <div id="slideshow" style="position: relative; height: 400px; border-radius: 12px; overflow: hidden; background: #000;">
                    <?php foreach ($businessPhotos as $index => $photo): ?>
                        <img src="<?= htmlspecialchars($photo['image_url']) ?>"
                            class="slide"
                            style="display: <?= $index === 0 ? 'block' : 'none' ?>; width: 100%; height: 100%; object-fit: contain; position: absolute; top: 0; left: 0;">
                    <?php endforeach; ?>
                </div>

                <?php if (count($businessPhotos) > 1): ?>
                    <button onclick="changeSlide(-1)"
                        style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: white; border: none; padding: 15px 20px; cursor: pointer; border-radius: 5px; font-size: 1.5rem;">
                        ❮
                    </button>
                    <button onclick="changeSlide(1)"
                        style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: white; border: none; padding: 15px 20px; cursor: pointer; border-radius: 5px; font-size: 1.5rem;">
                        ❯
                    </button>

                    <div style="text-align: center; margin-top: 10px;">
                        <span id="slideCounter" style="color: #666;">1 / <?= count($businessPhotos) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            let currentSlide = 0;
            const slides = document.querySelectorAll('.slide');
            const totalSlides = slides.length;

            function showSlide(n) {
                slides[currentSlide].style.display = 'none';
                currentSlide = (n + totalSlides) % totalSlides;
                slides[currentSlide].style.display = 'block';
                document.getElementById('slideCounter').textContent = (currentSlide + 1) + ' / ' + totalSlides;
            }

            function changeSlide(direction) {
                showSlide(currentSlide + direction);
            }

            // Optional: Auto-advance slideshow every 5 seconds
            // setInterval(() => changeSlide(1), 5000);
        </script>
    <?php endif; ?>
    <!-- REPORT MODAL -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('reportModal').style.display='none'">&times;</span>
            <h2><i class="fas fa-flag"></i> Report Business</h2>

            <?php if (!$isLoggedIn): ?>
                <p style="color: #666;">Please <a href="user_login.php" style="color: #e53935;">login</a> to report.</p>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="submit_report" value="1">

                    <div class="form-group">
                        <label>Report Type:</label>
                        <select name="report_type" required>
                            <option value="">Select a type...</option>
                            <option value="Inappropriate Content">Inappropriate Content</option>
                            <option value="Spam">Spam</option>
                            <option value="Fraud">Fraud/Scam</option>
                            <option value="Misleading Information">Misleading Information</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Description:</label>
                        <textarea name="report_reason" rows="4" required placeholder="Please describe the issue..."></textarea>
                    </div>

                    <button type="submit" style="background: #ff9800; color: white; padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%;">
                        Submit Report
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'report_submitted'): ?>
        <div class="alert-info">
            <i class="fas fa-check-circle"></i> Report submitted successfully! Our team will review it.
        </div>
    <?php endif; ?>
    <div class="card">
        <h1><?= htmlspecialchars($business['name']) ?></h1>
        <span class="badge"><?= $business['category'] ?></span>
        <p style="margin-top:10px; color:#666;">
            ⭐ <?= number_format($business['rating'], 1) ?> Rating
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
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 20px;">
                <?php foreach ($products as $p): ?>
                    <div style="border: 1px solid #ddd; border-radius: 12px; padding: 15px; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                        <?php
                        // Get product image
                        $imgStmt = $pdo->prepare("SELECT image_url FROM photos WHERE product_id = ? AND is_approved = TRUE LIMIT 1");
                        $imgStmt->execute([$p['id']]);
                        $productImage = $imgStmt->fetchColumn();
                        ?>

                        <?php if ($productImage): ?>
                            <img src="<?= htmlspecialchars($productImage) ?>"
                                style="width: 100%; height: 180px; object-fit: cover; border-radius: 8px; margin-bottom: 15px;">
                        <?php else: ?>
                            <div style="width: 100%; height: 180px; background: #eee; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; color: #999;">
                                <i class="fas fa-image" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>

                        <h4 style="margin: 0 0 10px 0; font-size: 1rem; color: #333;">
                            <?= htmlspecialchars($p['name']) ?>
                        </h4>

                        <?php if ($p['description']): ?>
                            <p style="color: #666; font-size: 0.85rem; margin: 5px 0; line-height: 1.4;">
                                <?= htmlspecialchars(substr($p['description'], 0, 60)) ?><?= strlen($p['description']) > 60 ? '...' : '' ?>
                            </p>
                        <?php endif; ?>

                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                            <span style="font-size: 1.3rem; font-weight: bold; color: #e53935;">
                                <?= number_format($p['product_prices'], 2) ?> TL
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card">
        <h3>Write a Review</h3>

        <?php if (!$isLoggedIn): ?>
            <p style="color: #666;">Please <a href="user_login.php" style="color: #e53935;">login</a> to write a review.</p>
        <?php else: ?>
            <?php if (isset($_GET['msg'])): ?>
                <?php if ($_GET['msg'] === 'review_submitted'): ?>
                    <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                        Review submitted successfully! It will be visible after admin approval.
                    </div>
                <?php elseif ($_GET['msg'] === 'review_updated'): ?>
                    <div style="background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                        Your review has been updated! It will be visible after admin approval.
                    </div>
                <?php elseif ($_GET['msg'] === 'review_exists'): ?>
                    <div style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                        You've already reviewed this business. You can only submit one review per business.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="POST" class="review-form">
                <input type="hidden" name="submit_review" value="1">

                <label style="font-weight: 600; margin-bottom: 10px; display: block;">Your Rating:</label>
                <div class="star-rating-input">
                    <input type="radio" name="rating" value="5" id="star5" required>
                    <label for="star5">★</label>
                    <input type="radio" name="rating" value="4" id="star4">
                    <label for="star4">★</label>
                    <input type="radio" name="rating" value="3" id="star3">
                    <label for="star3">★</label>
                    <input type="radio" name="rating" value="2" id="star2">
                    <label for="star2">★</label>
                    <input type="radio" name="rating" value="1" id="star1">
                    <label for="star1">★</label>
                </div>

                <label style="font-weight: 600; margin-top: 15px; display: block;">Your Review:</label>
                <textarea name="comment" rows="4" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; margin-top: 10px;"></textarea>

                <button type="submit" style="background: #e53935; color: white; padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; margin-top: 15px; font-weight: 600;">
                    Submit Review
                </button>
            </form>
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
    <script>
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('reportModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>

</html>