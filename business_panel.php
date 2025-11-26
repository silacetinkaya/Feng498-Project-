<?php
require 'db.php';

// TEMP login — later use: $_SESSION['user_id'];
$ownerId = 1;

// active tab
$tab = $_GET['tab'] ?? 'info';

// 1) Fetch business
$stmt = $pdo->prepare("SELECT * FROM business WHERE owner_id = :id LIMIT 1");
$stmt->execute(['id' => $ownerId]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$business) {
    die("No business found for this owner.");
}

$businessId = $business['shop_id'];

// 2) Days of week
$days = ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"];

// 3) Fetch hours
$hours = [];
foreach ($days as $day) {
    $hours[$day] = ['open'=>'', 'close'=>'', 'closed'=>false];
}

$stmtHours = $pdo->prepare("SELECT * FROM business_hours WHERE business_id = :bid");
$stmtHours->execute(['bid' => $businessId]);

foreach ($stmtHours->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $day = $row['day_of_week'];
    if (!isset($hours[$day])) continue;

    $hours[$day] = [
        'open'   => $row['open_hour'] ? substr($row['open_hour'],0,5) : '',
        'close'  => $row['close_hour'] ? substr($row['close_hour'],0,5) : '',
        'closed' => (bool)$row['is_closed']
    ];
}

$successMessage = null;
$errorMessage   = null;

// ---------------------------
// HANDLE POST (ALL TABS)
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Update business info + hours
    if (isset($_POST['update_business'])) {
        try {
            $upd = $pdo->prepare("
                UPDATE business SET
                    name = :name,
                    address = :address,
                    tel_no = :tel_no,
                    description = :description
                WHERE shop_id = :sid
            ");
            $upd->execute([
                'name' => $_POST['name'] ?? '',
                'address' => $_POST['address'] ?? '',
                'tel_no' => $_POST['tel_no'] ?? '',
                'description' => $_POST['description'] ?? '',
                'sid' => $businessId
            ]);

            foreach ($days as $day) {
                $open   = $_POST["open_$day"]   ?? '';
                $close  = $_POST["close_$day"]  ?? '';
                $closed = isset($_POST["closed_$day"]) ? 1 : 0;

                $openVal  = $closed || $open  === '' ? null : $open;
                $closeVal = $closed || $close === '' ? null : $close;

                $check = $pdo->prepare("SELECT id FROM business_hours WHERE business_id = :bid AND day_of_week = :day");
                $check->execute(['bid'=>$businessId,'day'=>$day]);
                $id = $check->fetchColumn();

                if ($id) {
                    $u = $pdo->prepare("
                        UPDATE business_hours
                        SET open_hour=:o, close_hour=:c, is_closed=:closed
                        WHERE id=:id
                    ");
                    $u->execute(['o'=>$openVal,'c'=>$closeVal,'closed'=>$closed,'id'=>$id]);
                } else {
                    $i = $pdo->prepare("
                        INSERT INTO business_hours (business_id, day_of_week, open_hour, close_hour, is_closed)
                        VALUES (:bid,:day,:o,:c,:closed)
                    ");
                    $i->execute([
                        'bid'=>$businessId,
                        'day'=>$day,
                        'o'=>$openVal,
                        'c'=>$closeVal,
                        'closed'=>$closed
                    ]);
                }
            }

            $successMessage = "Business info updated.";
            $tab = 'info';

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    }

    // Update product price
    if (isset($_POST['update_price'])) {
        try {
            $upd = $pdo->prepare("
                UPDATE products SET product_prices = :p
                WHERE id = :id AND business_id = :bid
            ");
            $upd->execute([
                'p'  => $_POST['new_price'],
                'id' => $_POST['product_id'],
                'bid'=> $businessId
            ]);

            $successMessage = "Price updated.";
            $tab = 'products';

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    }

    // Delete product
    if (isset($_POST['delete_product'])) {
        try {
            $del = $pdo->prepare("
                DELETE FROM products
                WHERE id = :id AND business_id = :bid
            ");
            $del->execute([
                'id'  => $_POST['product_id'],
                'bid' => $businessId
            ]);

            $successMessage = "Product deleted.";
            $tab = 'products';

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    }

    // Add product
    if (isset($_POST['add_product'])) {
        try {
            $ins = $pdo->prepare("
                INSERT INTO products (business_id, description, product_prices, categories, available, name)
                VALUES (:bid,:d,:p,:c,:a,:n)
            ");
            $ins->execute([
                'bid'=>$businessId,
                'd'=>$_POST['p_description'],
                'p'=>$_POST['p_price'],
                'c'=>$_POST['p_category'],
                'a'=>isset($_POST['p_available'])?1:0,
                'n'=>$_POST['p_name']
            ]);

            $successMessage = "Product added.";
            $tab = 'products';

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

// ---------------------------
// RE-FETCH after POST
// ---------------------------

// business
$stmt = $pdo->prepare("SELECT * FROM business WHERE shop_id = :id");
$stmt->execute(['id'=>$businessId]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

// hours
$hours = [];
foreach ($days as $day) {
    $hours[$day] = ['open'=>'', 'close'=>'', 'closed'=>false];
}
$stmtHours = $pdo->prepare("SELECT * FROM business_hours WHERE business_id = :bid");
$stmtHours->execute(['bid' => $businessId]);
foreach ($stmtHours->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $day = $row['day_of_week'];
    if (!isset($hours[$day])) continue;
    $hours[$day] = [
        'open'   => $row['open_hour'] ? substr($row['open_hour'],0,5) : '',
        'close'  => $row['close_hour'] ? substr($row['close_hour'],0,5) : '',
        'closed' => (bool)$row['is_closed']
    ];
}

// products
$stmt = $pdo->prepare("SELECT * FROM products WHERE business_id = :bid ORDER BY id");
$stmt->execute(['bid'=>$businessId]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// business rating & reviews (business_id bazlı)
$avg = $pdo->prepare("
    SELECT AVG(rank) AS avg, COUNT(*) AS cnt
    FROM reviews
    WHERE business_id = :bid
");
$avg->execute(['bid'=>$businessId]);
$ratingData = $avg->fetch(PDO::FETCH_ASSOC);
$businessRating = $ratingData['cnt'] > 0 ? round($ratingData['avg'],1) : null;
$ratingCount    = (int)$ratingData['cnt'];

$rev = $pdo->prepare("
    SELECT r.rank, r.comments, r.time, u.full_name
    FROM reviews r
    LEFT JOIN users u ON u.id = r.user_id
    WHERE r.business_id = :bid
    ORDER BY r.time DESC
    LIMIT 20
");
$rev->execute(['bid'=>$businessId]);
$reviews = $rev->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Business Panel</title>
    <style>
        :root {
            --sidebar-bg: #e53935;
            --sidebar-bg-dark: #c62828;
            --accent: #ffb300;
            --bg: #f4f5fb;
            --card-bg: #ffffff;
            --text-main: #1f2933;
            --text-muted: #6b7280;
            --border-soft: #e5e7eb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text-main);
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */

        .sidebar {
            width: 230px;
            background: var(--sidebar-bg);
            color: #fff;
            padding: 20px 16px;
            display: flex;
            flex-direction: column;
        }

        .sidebar-logo {
            font-weight: 700;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 32px;
        }

        .sidebar-logo span.icon {
            width: 30px;
            height: 30px;
            border-radius: 999px;
            background: rgba(255,255,255,0.14);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .sidebar-section-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            opacity: 0.75;
            margin-bottom: 8px;
            margin-top: 8px;
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 10px;
            color: #ffecec;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.15s ease, transform 0.05s ease;
        }

        .sidebar-link span.icon {
            width: 22px;
            display: inline-flex;
            justify-content: center;
        }

        .sidebar-link.active {
            background: rgba(255,255,255,0.15);
        }

        .sidebar-link:hover {
            background: rgba(255,255,255,0.08);
            transform: translateX(2px);
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.18);
        }

        .logout-btn {
            width: 100%;
            border: none;
            border-radius: 999px;
            padding: 8px 0;
            background: rgba(0,0,0,0.15);
            color: #fff;
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        /* MAIN AREA */

        .main {
            flex: 1;
            padding: 20px 32px;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .topbar-title {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .topbar-title h1 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .topbar-title span {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            background: #e5f9e7;
            color: #15803d;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #bbf7d0;
        }

        /* SUMMARY CARDS */

        .summary-row {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 14px 16px;
            border: 1px solid var(--border-soft);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .summary-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .summary-card-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: rgba(229, 57, 53, 0.08);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: var(--sidebar-bg);
        }

        .summary-card-value {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .summary-card-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* ALERTS */

        .msg {
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 12px;
            font-size: 0.9rem;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        /* CONTENT CARD */

        .content-wrapper {
            flex: 1;
        }

        .content-card {
            background: var(--card-bg);
            border-radius: 14px;
            border: 1px solid var(--border-soft);
            padding: 18px 20px;
        }

        .content-card h2 {
            margin-top: 0;
            margin-bottom: 14px;
            font-size: 1.1rem;
        }

        /* FORMS & TABLES (apply to included files too) */

        form {
            font-size: 0.9rem;
        }

        label {
            display: block;
            margin-top: 10px;
            margin-bottom: 4px;
            font-weight: 500;
        }

        input[type="text"],
        input[type="number"],
        input[type="time"],
        textarea {
            width: 100%;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            font-size: 0.9rem;
        }

        textarea {
            resize: vertical;
            min-height: 60px;
        }

        button {
            margin-top: 12px;
            padding: 8px 14px;
            border-radius: 999px;
            border: none;
            background: var(--sidebar-bg);
            color: #fff;
            cursor: pointer;
            font-size: 0.9rem;
        }

        button:hover {
            background: var(--sidebar-bg-dark);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            margin-top: 10px;
        }

        th, td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        th {
            background: #f9fafb;
            font-weight: 600;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .danger-btn {
            background: #ef4444;
        }

        .danger-btn:hover {
            background: #b91c1c;
        }

        @media (max-width: 900px) {
            .layout {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                flex-direction: row;
                align-items: center;
                gap: 16px;
            }
            .sidebar-footer {
                margin-top: 0;
                margin-left: auto;
                border-top: none;
            }
            .summary-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="layout">
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div>
            <div class="sidebar-logo">
                <span class="icon">🏪</span>
                <div>BusinessPanel</div>
            </div>

            <div class="sidebar-section-title">Menu</div>
            <nav class="sidebar-menu">
                <a href="business_panel.php?tab=info"
                   class="sidebar-link <?= $tab=='info'?'active':'' ?>">
                    <span class="icon">ℹ️</span>
                    <span>Business Info</span>
                </a>
                <a href="business_panel.php?tab=products"
                   class="sidebar-link <?= $tab=='products'?'active':'' ?>">
                    <span class="icon">📦</span>
                    <span>Products</span>
                </a>
                <a href="business_panel.php?tab=add_product"
                   class="sidebar-link <?= $tab=='add_product'?'active':'' ?>">
                    <span class="icon">➕</span>
                    <span>Add Product</span>
                </a>
            </nav>
        </div>

        <div class="sidebar-footer">
            <button class="logout-btn">
                ⬅ Logout
            </button>
        </div>
    </aside>

    <!-- MAIN AREA -->
    <main class="main">
        <header class="topbar">
            <div class="topbar-title">
                <h1><?= htmlspecialchars($business['name'] ?? 'My Business') ?></h1>
                <span>Owner panel · manage your business information, products and reviews.</span>
            </div>
            <div class="status-badge">
                ● Online
                <span style="font-weight:500;">System Operational</span>
            </div>
        </header>

        <!-- SUMMARY CARDS -->
        <section class="summary-row">
            <div class="summary-card">
                <div class="summary-card-header">
                    <span>Overall Rating</span>
                    <span class="summary-card-icon">★</span>
                </div>
                <div class="summary-card-value">
                    <?= $businessRating !== null ? $businessRating : '—' ?>
                </div>
                <div class="summary-card-sub">
                    <?= $ratingCount ?> total review(s)
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-card-header">
                    <span>Total Products</span>
                    <span class="summary-card-icon">📦</span>
                </div>
                <div class="summary-card-value">
                    <?= count($products) ?>
                </div>
                <div class="summary-card-sub">
                    Active items in your catalog
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-card-header">
                    <span>Panel</span>
                    <span class="summary-card-icon">⚙️</span>
                </div>
                <div class="summary-card-value">
                    <?= $tab === 'info' ? 'Info' : ($tab === 'products' ? 'Products' : 'Add Product') ?>
                </div>
                <div class="summary-card-sub">
                    Current section
                </div>
            </div>
        </section>

        <!-- MESSAGES -->
        <?php if ($successMessage): ?>
            <div class="msg success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="msg error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <!-- TAB CONTENT -->
        <div class="content-wrapper">
            <div class="content-card">
                <?php
                if ($tab === 'info') {
                    include 'business_info.php';
                } elseif ($tab === 'products') {
                    include 'business_products.php';
                } elseif ($tab === 'add_product') {
                    include 'business_add_product.php';
                }
                ?>
            </div>
        </div>
    </main>
</div>

</body>
</html>
