<?php
session_start();
require_once "db_connect.php";

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

$userId = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'map'; // Default tab is now 'map'

// 2. FETCH USER INFO (Always needed for sidebar/header)
$stmt = $pdo->prepare("SELECT full_name, email, address FROM users WHERE id = :id");
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// 3. HANDLE POST REQUESTS (Profile Updates)
$successMessage = "";
$errorMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Update Profile Info
    if (isset($_POST['update_profile'])) {
        try {
            $up = $pdo->prepare("UPDATE users SET full_name = :n, email = :e, address = :a WHERE id = :id");
            $up->execute([
                'n' => $_POST['full_name'],
                'e' => $_POST['email'],
                'a' => $_POST['address'],
                'id' => $userId
            ]);
            $successMessage = "Profile updated successfully!";
            // Refresh user data
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $errorMessage = "Error updating profile.";
        }
    }

    // Update Password
    if (isset($_POST['update_password'])) {
        try {
            $currentPass = $_POST['current_password'];
            $newPass = $_POST['new_password'];
            $confirmPass = $_POST['confirm_password'];

            // 1. Verify Current Password
            $stmtPass = $pdo->prepare("SELECT password FROM users WHERE id = :id");
            $stmtPass->execute(['id' => $userId]);
            $dbPass = $stmtPass->fetchColumn();

            if (!password_verify($currentPass, $dbPass)) {
                $errorMessage = "Current password is incorrect.";
            } 
            // 2. Check Matching New Passwords
            elseif ($newPass !== $confirmPass) {
                $errorMessage = "New passwords do not match.";
            } 
            // 3. Check Complexity (8 chars, 1 Upper, 1 Number)
            elseif (strlen($newPass) < 8 || !preg_match('/[A-Z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
                $errorMessage = "Password must be at least 8 characters long, contain at least one uppercase letter and one number.";
            } 
            else {
                // 4. Update Password
                $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                $up2 = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
                $up2->execute(['p' => $hashed, 'id' => $userId]);
                $successMessage = "Password changed successfully!";
            }
        } catch (Exception $e) {
            $errorMessage = "Error updating password.";
        }
    }
}

// 4. DATA FETCHING FOR SPECIFIC TABS

// --- FAVORITES TAB DATA ---
$favoriteBusinesses = [];
$favoriteProducts = [];
if ($tab === 'favorites') {
    // Biz
    $stmtFavB = $pdo->prepare("
        SELECT bf.id, b.shop_id, b.name, b.category, b.address
        FROM business_favorites bf
        JOIN business b ON b.shop_id = bf.business_id
        WHERE bf.user_id = :uid
    ");
    $stmtFavB->execute(['uid' => $userId]);
    $favoriteBusinesses = $stmtFavB->fetchAll(PDO::FETCH_ASSOC);

    // Prod
    $stmtFavP = $pdo->prepare("
        SELECT pf.id, p.id AS product_id, p.name, p.description, 
               p.product_prices, p.categories, p.available
        FROM product_favorites pf
        JOIN products p ON p.id = pf.product_id
        WHERE pf.user_id = :uid
    ");
    $stmtFavP->execute(['uid' => $userId]);
    $favoriteProducts = $stmtFavP->fetchAll(PDO::FETCH_ASSOC);
}

// --- REVIEWS TAB DATA ---
$myReviews = [];
if ($tab === 'reviews') {
    $stmtRev = $pdo->prepare("
        SELECT r.review_id, r.rank, r.comments, r.time, b.name AS business_name, b.shop_id
        FROM reviews r
        JOIN business b ON b.shop_id = r.business_id
        WHERE r.user_id = :uid
        ORDER BY r.time DESC
    ");
    $stmtRev->execute(['uid' => $userId]);
    $myReviews = $stmtRev->fetchAll(PDO::FETCH_ASSOC);
}

// --- OFFERS TAB DATA ---
$myOffers = [];
if ($tab === 'offers') {
    $stmtOff = $pdo->prepare("
        SELECT o.id, o.offered_price, o.status, o.created_time, b.name AS business_name, b.shop_id
        FROM offers o
        LEFT JOIN business b ON b.shop_id = o.business_id
        WHERE o.user_id = :uid
        ORDER BY o.created_time DESC
    ");
    $stmtOff->execute(['uid' => $userId]);
    $myOffers = $stmtOff->fetchAll(PDO::FETCH_ASSOC);
}

// --- MESSAGES TAB DATA ---
$myChats = [];
if ($tab === 'messages') {
    $stmtChat = $pdo->prepare("
        SELECT DISTINCT m.owner_id, b.name AS business_name
        FROM messages m
        JOIN business b ON b.owner_id = m.owner_id
        WHERE m.user_id = :uid OR b.owner_id = :uid
    ");
    $stmtChat->execute(['uid' => $userId]);
    $myChats = $stmtChat->fetchAll(PDO::FETCH_ASSOC);
}

// --- MAP TAB DATA ---
$mapBusinesses = [];
if ($tab === 'map') {
    $stmtMap = $pdo->prepare("
        SELECT b.shop_id, b.name, b.category, b.latitude, b.longitude,
               COALESCE(AVG(r.rank), 0) AS rating
        FROM business b
        LEFT JOIN reviews r ON r.business_id = b.shop_id
        GROUP BY b.shop_id
    ");
    $stmtMap->execute();
    $mapBusinesses = $stmtMap->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    
    <style>
        :root {
            --sidebar-bg: #e53935;
            --sidebar-hover: #c62828;
            --text-main: #1f2933;
            --bg: #f4f5fb;
            --card-bg: #ffffff;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text-main);
            height: 100vh;
            overflow: hidden;
        }

        .layout {
            display: flex;
            height: 100%;
        }

        /* SIDEBAR */
        .sidebar {
            width: 240px;
            background: var(--sidebar-bg);
            color: white;
            display: flex;
            flex-direction: column;
            padding: 20px;
            flex-shrink: 0;
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar a {
            text-decoration: none;
            color: rgba(255,255,255,0.9);
            padding: 12px 15px;
            border-radius: 12px;
            margin-bottom: 8px;
            font-size: 0.95rem;
            display: block;
            transition: 0.2s;
        }

        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.15);
            color: white;
            font-weight: 600;
        }

        .logout {
            margin-top: auto;
            background: rgba(0,0,0,0.2) !important;
            text-align: center;
        }

        /* MAIN CONTENT AREA */
        .main {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .header {
            margin-bottom: 25px;
        }
        .header h1 { margin: 0; font-size: 1.8rem; }
        .header p { color: #666; margin: 5px 0 0 0; }

        /* CARDS & CONTAINERS */
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        /* Forms */
        label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.9rem; }
        input, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; margin-bottom: 15px; }
        button.btn-primary { background: #e53935; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 1rem; }
        button.btn-primary:hover { background: #c62828; }

        /* Messages */
        .msg { padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }

        /* Favorites Grid */
        .grid-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .item-card { background: white; padding: 15px; border: 1px solid #eee; border-radius: 10px; }
        .item-title { font-weight: bold; font-size: 1.1rem; }
        .item-sub { color: #777; font-size: 0.9rem; margin-bottom: 10px; }
        .remove-link { color: #e53935; font-size: 0.85rem; text-decoration: none; border: 1px solid #e53935; padding: 4px 10px; border-radius: 6px; }
        
        /* Map Container */
        #map { height: 600px; width: 100%; border-radius: 12px; z-index: 1; }
    </style>
</head>
<body>

<div class="layout">

    <!-- SIDEBAR NAVIGATION -->
    <nav class="sidebar">
        <div class="sidebar-logo">Profile</div>

        <a href="?tab=profile" class="<?= $tab=='profile'?'active':'' ?>">Edit Profile</a>
        <a href="?tab=favorites" class="<?= $tab=='favorites'?'active':'' ?>">Favorites</a>
        <a href="?tab=reviews" class="<?= $tab=='reviews'?'active':'' ?>">Reviews</a>
        <a href="?tab=offers" class="<?= $tab=='offers'?'active':'' ?>">Offers</a>
        <a href="?tab=messages" class="<?= $tab=='messages'?'active':'' ?>">Messages</a>
        <a href="?tab=map" class="<?= $tab=='map'?'active':'' ?>">Map Explorer</a>

        <a href="logout.php" class="logout">Logout</a>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main">
        
        <!-- STATUS MESSAGES -->
        <?php if ($successMessage): ?><div class="msg success"><?= $successMessage ?></div><?php endif; ?>
        <?php if ($errorMessage): ?><div class="msg error"><?= $errorMessage ?></div><?php endif; ?>

        <!-- 1. PROFILE TAB -->
        <?php if ($tab == 'profile'): ?>
            <div class="header">
                <h1>Edit Profile</h1>
                <p>Manage your personal information and security.</p>
            </div>

            <div class="card">
                <h3>Personal Info</h3>
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    
                    <label>Address</label>
                    <textarea name="address" rows="2"><?= htmlspecialchars($user['address']) ?></textarea>
                    
                    <button type="submit" class="btn-primary">Save Changes</button>
                </form>
            </div>

            <div class="card">
                <h3>Change Password</h3>
                <form method="POST">
                    <input type="hidden" name="update_password" value="1">
                    
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>

                    <label>New Password</label>
                    <input type="password" name="new_password" required>
                    <small style="color:#666; display:block; margin-bottom:10px;">At least 8 characters, one number, one uppercase letter.</small>

                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>

                    <button type="submit" class="btn-primary">Update Password</button>
                </form>
            </div>

        <!-- 2. FAVORITES TAB -->
        <?php elseif ($tab == 'favorites'): ?>
            <div class="header">
                <h1>Favorites</h1>
                <p>Your saved businesses and products.</p>
            </div>

            <h3>Saved Businesses</h3>
            <?php if(empty($favoriteBusinesses)): ?>
                <p style="color:#777">No favorite businesses yet.</p>
            <?php else: ?>
                <div class="grid-list">
                    <?php foreach($favoriteBusinesses as $b): ?>
                        <div class="item-card">
                            <div class="item-title"><?= htmlspecialchars($b['name']) ?></div>
                            <div class="item-sub"><?= htmlspecialchars($b['category']) ?></div>
                            <a href="remove_fav_business.php?id=<?= $b['id'] ?>" class="remove-link">Remove</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h3 style="margin-top:30px;">Saved Products</h3>
            <?php if(empty($favoriteProducts)): ?>
                <p style="color:#777">No favorite products yet.</p>
            <?php else: ?>
                <div class="grid-list">
                    <?php foreach($favoriteProducts as $p): ?>
                        <div class="item-card">
                            <div class="item-title"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="item-sub">$<?= htmlspecialchars($p['product_prices']) ?></div>
                            <a href="remove_fav_product.php?id=<?= $p['id'] ?>" class="remove-link">Remove</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <!-- 3. REVIEWS TAB -->
        <?php elseif ($tab == 'reviews'): ?>
            <div class="header">
                <h1>My Reviews</h1>
            </div>
            
            <?php if(empty($myReviews)): ?>
                <p>You haven't written any reviews yet.</p>
            <?php else: ?>
                <?php foreach($myReviews as $r): ?>
                    <div class="card">
                        <div style="font-weight:bold; font-size:1.1rem; color:#e53935;"><?= htmlspecialchars($r['business_name']) ?></div>
                        <div style="color:#ffb400; margin:5px 0;"><?= str_repeat("⭐", (int)$r['rank']) ?></div>
                        <p style="color:#333;"><?= htmlspecialchars($r['comments']) ?></p>
                        <div style="font-size:0.8rem; color:#888;">Posted on <?= date('d M Y', strtotime($r['time'])) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        <!-- 4. OFFERS TAB -->
        <?php elseif ($tab == 'offers'): ?>
            <div class="header"><h1>My Offers</h1></div>
            
            <?php if(empty($myOffers)): ?>
                <p>No active offers.</p>
            <?php else: ?>
                <div class="grid-list">
                    <?php foreach($myOffers as $o): ?>
                        <div class="item-card">
                            <div class="item-title"><?= htmlspecialchars($o['business_name']) ?></div>
                            <div style="font-size:1.2rem; font-weight:bold; margin:5px 0;">$<?= number_format($o['offered_price'], 2) ?></div>
                            <div style="margin-bottom:10px;">Status: <strong><?= ucfirst($o['status']) ?></strong></div>
                            <small><?= date('d M Y', strtotime($o['created_time'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <!-- 5. MESSAGES TAB -->
        <?php elseif ($tab == 'messages'): ?>
            <div class="header"><h1>Messages</h1></div>
            <!-- NOTE: Full Chat logic is complex for a single file. Linking to detail view is cleaner. -->
            <?php if(empty($myChats)): ?>
                <p>No active chats.</p>
            <?php else: ?>
                <div class="grid-list">
                    <?php foreach($myChats as $c): ?>
                        <div class="item-card" onclick="location.href='user_tab_messages.php?owner=<?= $c['owner_id'] ?>'" style="cursor:pointer; border-left:4px solid #e53935;">
                            <div class="item-title"><?= htmlspecialchars($c['business_name']) ?></div>
                            <div class="item-sub">Click to view chat</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <!-- 6. MAP TAB -->
        <?php elseif ($tab == 'map'): ?>
            <div class="header"><h1>Map Explorer</h1></div>
            <div id="map"></div>
            
            <!-- Leaflet JS -->
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script>
                // Get business data from PHP
                const businesses = <?= json_encode($mapBusinesses) ?>;
                
                // Initialize Map (Default İzmir)
                const map = L.map('map').setView([38.4192, 27.1287], 12);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19
                }).addTo(map);

                // Add Markers
                businesses.forEach(b => {
                    if (b.latitude && b.longitude) {
                        const marker = L.marker([b.latitude, b.longitude]).addTo(map);
                        marker.bindPopup(`
                            <b>${b.name}</b><br>
                            ${b.category}<br>
                            Rating: ${b.rating ? parseFloat(b.rating).toFixed(1) : 'New'} ★
                        `);
                    }
                });
            </script>
        <?php endif; ?>

    </main>
</div>

</body>
</html>