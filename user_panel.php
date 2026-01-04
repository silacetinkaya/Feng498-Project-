<?php
session_start();
require_once "db_connect.php";

/* =========================
   AUTH
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

$userId = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'home';

/* =========================
   USER INFO
========================= */
$stmt = $pdo->prepare("SELECT full_name, email, address FROM users WHERE id = :id");
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   MESSAGES
========================= */
$successMessage = "";
$errorMessage = "";

/* =========================
   POST ACTIONS
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST['update_profile'])) {
        try {
            $up = $pdo->prepare("UPDATE users SET full_name=?, email=?, address=? WHERE id=?");
            $up->execute([
                $_POST['full_name'],
                $_POST['email'],
                $_POST['address'],
                $userId
            ]);
            $successMessage = "Profile updated successfully!";
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $errorMessage = "Error updating profile.";
        }
    }

    if (isset($_POST['update_password'])) {
        try {
            $stmtPass = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmtPass->execute([$userId]);
            $dbPass = $stmtPass->fetchColumn();

            if (!password_verify($_POST['current_password'], $dbPass)) {
                $errorMessage = "Current password is incorrect.";
            } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
                $errorMessage = "Passwords do not match.";
            } else {
                $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                    ->execute([$hashed, $userId]);
                $successMessage = "Password updated.";
            }
        } catch (Exception $e) {
            $errorMessage = "Password update error.";
        }
    }
}

/* =========================
   OTHER TABS DATA
========================= */
$favoriteBusinesses = $favoriteProducts = $myReviews = $myOffers = $myChats = [];

if ($tab === 'favorites') {
    $stmt = $pdo->prepare("SELECT b.name, b.category FROM business_favorites bf 
                            JOIN business b ON b.shop_id = bf.business_id
                            WHERE bf.user_id=?");
    $stmt->execute([$userId]);
    $favoriteBusinesses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($tab === 'reviews') {
    $stmt = $pdo->prepare("SELECT r.comments, r.rank, b.name AS business_name
                            FROM reviews r JOIN business b ON b.shop_id=r.business_id
                            WHERE r.user_id=?");
    $stmt->execute([$userId]);
    $myReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($tab === 'offers') {
    $stmt = $pdo->prepare("SELECT o.offered_price, o.status, b.name AS business_name
                            FROM offers o LEFT JOIN business b ON b.shop_id=o.business_id
                            WHERE o.user_id=?");
    $stmt->execute([$userId]);
    $myOffers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($tab === 'messages') {
    $stmt = $pdo->prepare("SELECT DISTINCT b.name AS business_name, b.owner_id
                            FROM messages m JOIN business b ON b.owner_id=m.owner_id
                            WHERE m.user_id=? OR b.owner_id=?");
    $stmt->execute([$userId, $userId]);
    $myChats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
:root {
    --red: #e53935;
    --bg: #f4f5fb;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: var(--bg);
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
    background: var(--red);
    color: white;
    display: flex;
    flex-direction: column;
    padding: 20px;
}

.sidebar a {
    color: white;
    text-decoration: none;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 6px;
}

.sidebar a.active,
.sidebar a:hover {
    background: rgba(255,255,255,0.2);
    font-weight: bold;
}

/* MAIN */
.main {
    flex: 1;
    padding: 30px;
    overflow-y: auto;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    border: 1px solid #ddd;
    margin-bottom: 20px;
}

.alert-success { background:#d4edda; padding:15px; }
.alert-danger  { background:#f8d7da; padding:15px; }

.btn {
    background: var(--red);
    color: white;
    border: none;
    padding: 10px 18px;
    border-radius: 8px;
    cursor: pointer;
}

.form-control {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 8px;
    border: 1px solid #ccc;
}
</style>
</head>

<body>

<div class="layout">
<nav class="sidebar">
    <h2>UserPanel</h2>
    <a href="?tab=home" class="<?= $tab=='home'?'active':'' ?>">Home</a>
    <a href="?tab=profile" class="<?= $tab=='profile'?'active':'' ?>">Profile</a>
    <a href="?tab=favorites" class="<?= $tab=='favorites'?'active':'' ?>">Favorites</a>
    <a href="?tab=reviews" class="<?= $tab=='reviews'?'active':'' ?>">Reviews</a>
    <a href="?tab=offers" class="<?= $tab=='offers'?'active':'' ?>">Offers</a>
    <a href="?tab=messages" class="<?= $tab=='messages'?'active':'' ?>">Messages</a>
    <a href="logout.php" style="margin-top:auto;">Logout</a>
</nav>

<main class="main">

<?php if ($successMessage): ?><div class="alert-success"><?= $successMessage ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert-danger"><?= $errorMessage ?></div><?php endif; ?>

<?php if ($tab === 'home'): ?>

    <h1>Welcome, <?= htmlspecialchars($user['full_name']) ?>!</h1>

    <div class="card">
        <div style="width:100%; height:550px; border-radius:12px; overflow:hidden;">
            <iframe 
                src="user_map.php"
                style="width:100%; height:100%; border:none;"
                loading="lazy">
            </iframe>
        </div>
    </div>

<?php elseif ($tab === 'profile'): ?>

    <div class="card">
        <h3>Edit Profile</h3>
        <form method="POST">
            <input type="hidden" name="update_profile" value="1">
            <input class="form-control" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>">
            <input class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>">
            <textarea class="form-control" name="address"><?= htmlspecialchars($user['address']) ?></textarea>
            <button class="btn">Save</button>
        </form>
    </div>

    <div class="card">
        <h3>Change Password</h3>
        <form method="POST">
            <input type="hidden" name="update_password" value="1">
            <input type="password" name="current_password" class="form-control" placeholder="Current password">
            <input type="password" name="new_password" class="form-control" placeholder="New password">
            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password">
            <button class="btn">Update Password</button>
        </form>
    </div>

<?php elseif ($tab === 'favorites'): ?>

    <div class="card">
        <h3>Favorite Businesses</h3>
        <?php foreach ($favoriteBusinesses as $b): ?>
            <p><?= htmlspecialchars($b['name']) ?> (<?= $b['category'] ?>)</p>
        <?php endforeach; ?>
    </div>

<?php elseif ($tab === 'reviews'): ?>

    <?php foreach ($myReviews as $r): ?>
        <div class="card">
            <b><?= htmlspecialchars($r['business_name']) ?></b>
            <p><?= htmlspecialchars($r['comments']) ?></p>
        </div>
    <?php endforeach; ?>

<?php elseif ($tab === 'offers'): ?>

    <?php foreach ($myOffers as $o): ?>
        <div class="card">
            <?= htmlspecialchars($o['business_name']) ?> – <?= $o['offered_price'] ?> TL (<?= $o['status'] ?>)
        </div>
    <?php endforeach; ?>

<?php elseif ($tab === 'messages'): ?>

    <?php foreach ($myChats as $c): ?>
        <div class="card">
            <?= htmlspecialchars($c['business_name']) ?>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

</main>
</div>
</body>
</html>
