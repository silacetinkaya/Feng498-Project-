<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

require "db_connect.php";

$userId = $_SESSION['user_id'];

// USER'IN TÜM TEKLİFLERİNİ ÇEK
$stmt = $pdo->prepare("
    SELECT 
        o.id,
        o.offered_price,
        o.status,
        o.created_time,
        b.name AS business_name,
        b.shop_id
    FROM offers o
    LEFT JOIN business b ON b.shop_id = o.business_id
    WHERE o.user_id = :uid
    ORDER BY o.created_time DESC
");
$stmt->execute(['uid' => $userId]);
$offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Offers</title>
    <style>
        body {
            margin: 0;
            background: #f4f5fb;
            font-family: system-ui, sans-serif;
        }

        .container {
            padding: 30px;
        }

        h2 {
            margin-bottom: 10px;
        }

        .offer-card {
            background: white;
            border: 1px solid #ddd;
            padding: 18px;
            margin-bottom: 15px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
        }

        .business-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #e53935;
        }

        .status {
            margin-top: 8px;
            padding: 6px 10px;
            display: inline-block;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .pending { background: #fff3cd; color: #856404; }
        .accepted { background: #d4edda; color: #155724; }
        .rejected { background: #f8d7da; color: #721c24; }

        .price {
            margin-top: 10px;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .timestamp {
            margin-top: 6px;
            color: gray;
            font-size: 0.85rem;
        }

        .no-offers {
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

    <h2>My Offers</h2>
    <a href="user_panel.php" class="back-btn">← Back to Dashboard</a>
    <br><br>

    <?php if (empty($offers)): ?>
        <div class="no-offers">You have not made any offers yet.</div>
    <?php else: ?>

        <?php foreach ($offers as $o): ?>

            <div class="offer-card">

                <div class="business-name">
                    <a href="business_detail.php?id=<?= $o['shop_id'] ?>" 
                       style="text-decoration:none; color:#e53935;">
                        <?= htmlspecialchars($o['business_name']) ?>
                    </a>
                </div>

                <div class="price">
                    Offered Price: <strong>$<?= number_format($o['offered_price'], 2) ?></strong>
                </div>

                <div class="status <?= strtolower($o['status']) ?>">
                    <?= ucfirst($o['status']) ?>
                </div>

                <div class="timestamp">
                    Sent on <?= date("F j, Y, g:i a", strtotime($o['created_time'])) ?>
                </div>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

</body>
</html>
