<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

require "db_connect.php";

// Kullanıcı bilgilerini al
$stmt = $pdo->prepare("SELECT full_name, email, address FROM users WHERE id = :id");
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

// PROFİL GÜNCELLEME
$updateMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_profile'])) {
    $name = $_POST['full_name'];
    $email = $_POST['email'];
    $address = $_POST['address'];

    $up = $pdo->prepare("UPDATE users SET full_name = :n, email = :e, address = :a WHERE id = :id");
    $up->execute([
        'n' => $name,
        'e' => $email,
        'a' => $address,
        'id' => $_SESSION['user_id']
    ]);

    $updateMessage = "Profile updated successfully!";
}

// PASSWORD UPDATE
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_password'])) {
    $password = $_POST['new_password'];
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $up2 = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
    $up2->execute(['p' => $hashed, 'id' => $_SESSION['user_id']]);

    $updateMessage = "Password changed successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Profile</title>

    <style>
        body {
            margin: 0;
            background: #f4f5fb;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .layout {
            display: flex;
        }

        /* SIDEBAR (SAYFALAR ARASI TUTARLI) */
        .sidebar {
            width: 230px;
            background: #e53935;
            color: white;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 8px;
            display: block;
            font-size: 0.95rem;
        }

        .sidebar a:hover {
            background: #c62828;
        }

        .logout {
            margin-top: auto;
            background: rgba(0,0,0,0.3);
            text-align: center;
        }

        /* MAIN CONTENT */
        .main {
            flex: 1;
            padding: 40px;
        }

        .title {
            font-size: 1.6rem;
            font-weight: 600;
        }

        .back-btn {
            float: right;
            padding: 8px 14px;
            background: #e53935;
            color: white;
            border-radius: 8px;
            text-decoration: none;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 14px;
            border: 1px solid #e7e7e7;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            margin-top: 25px;
        }

        .card h3 {
            margin-top: 0;
        }

        input, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #dadada;
            border-radius: 10px;
            margin-bottom: 12px;
            font-size: 1rem;
        }

        button {
            padding: 10px 16px;
            background: #e53935;
            border: none;
            color: white;
            font-size: 1rem;
            border-radius: 10px;
            cursor: pointer;
        }

        button:hover {
            background: #c62828;
        }

        .success {
            background: #d4edda;
            padding: 10px;
            border-left: 5px solid #28a745;
            margin-bottom: 20px;
            border-radius: 6px;
            color: #155724;
        }

    </style>
</head>
<body>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <h2>User Panel</h2>

        <a href="user_panel.php">Dashboard</a>
        <a href="user_tab_profile.php">My Profile</a>
        <a href="user_tab_favorites.php">Favorites</a>
        <a href="user_tab_reviews.php">My Reviews</a>
        <a href="user_tab_offers.php">My Offers</a>
        <a href="user_tab_messages.php">Messages</a>
        <a href="user_map.php">Map</a>

        <a class="logout" href="logout.php">Logout</a>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">

        <a class="back-btn" href="user_panel.php">Back to Dashboard</a>
        <div class="title">My Profile</div>

        <?php if ($updateMessage): ?>
            <div class="success"><?= $updateMessage ?></div>
        <?php endif; ?>

        <!-- Profile Info -->
        <div class="card">
            <h3>Edit Your Information</h3>

            <form method="POST">
                <input type="hidden" name="update_profile" value="1">

                <label>Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>

                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

                <label>Home Address</label>
                <textarea name="address" rows="3"><?= htmlspecialchars($user['address']) ?></textarea>

                <button type="submit">Save Changes</button>
            </form>
        </div>

        <!-- PASSWORD -->
        <div class="card">
            <h3>Change Password</h3>

            <form method="POST">
                <input type="hidden" name="update_password" value="1">

                <label>New Password</label>
                <input type="password" name="new_password" required minlength="6">

                <button type="submit">Update Password</button>
            </form>
        </div>

    </main>
</div>

</body>
</html>
