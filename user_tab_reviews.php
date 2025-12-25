<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

require "db_connect.php";

$userId = $_SESSION['user_id'];

// Kullanıcının yaptığı yorumları çek
$stmt = $pdo->prepare("
    SELECT 
        r.review_id,
        r.rank,
        r.comments,
        r.time,
        b.name AS business_name,
        b.shop_id
    FROM reviews r
    JOIN business b ON b.shop_id = r.business_id
    WHERE r.user_id = :uid
    ORDER BY r.time DESC
");
$stmt->execute(['uid' => $userId]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Reviews</title>
    <style>
        body {
            margin: 0;
            font-family: system-ui, sans-serif;
            background: #f4f5fb;
        }

        .container {
            padding: 30px;
        }

        h2 {
            margin-bottom: 15px;
        }

        .review-card {
            background: white;
            padding: 18px;
            border-radius: 12px;
            border: 1px solid #ddd;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }

        .business-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #e53935;
        }

        .rating {
            color: #ffb400;
            font-size: 1rem;
            margin: 4px 0;
        }

        .review-text {
            margin: 6px 0;
            color: #333;
        }

        .timestamp {
            font-size: 0.85rem;
            color: gray;
        }

        .no-reviews {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #ddd;
        }

        .back-btn {
            background: #e53935;
            color: white;
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }

        .back-btn:hover {
            background: #c62828;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>My Reviews</h2>

    <a href="user_panel.php" class="back-btn">← Back to Dashboard</a>
    <br><br>

    <?php if (empty($reviews)): ?>
        <div class="no-reviews">You haven't written any reviews yet.</div>
    <?php else: ?>

        <?php foreach ($reviews as $r): ?>
            <div class="review-card">

                <div class="business-name">
                    <a href="business_detail.php?id=<?= $r['shop_id'] ?>" style="color:#e53935; text-decoration:none;">
                        <?= htmlspecialchars($r['business_name']) ?>
                    </a>
                </div>

                <div class="rating">
                    <?= str_repeat("⭐", (int)$r['rank']) ?>
                </div>

                <div class="review-text">
                    <?= htmlspecialchars($r['comments']) ?>
                </div>

                <div class="timestamp">
                    Reviewed on <?= date("F j, Y, g:i a", strtotime($r['time'])) ?>
                </div>

            </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

</body>
</html>
