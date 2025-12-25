<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

require "db_connect.php";

// Kullanıcı bilgileri
$stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = :id");
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Panel</title>
    <style>
        :root {
            --sidebar-bg: #e53935;
            --sidebar-hover: #c62828;
            --text-main: #1f2933;
            --text-muted: #6b7280;
            --card-bg: #ffffff;
            --border-soft: #e5e7eb;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f4f5fb;
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
            color: white;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .sidebar-logo {
            font-size: 1.4rem;
            font-weight: bold;
            margin-bottom: 30px;
        }

        .sidebar a {
            text-decoration: none;
            color: white;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 6px;
            display: block;
            font-size: 0.95rem;
        }

        .sidebar a:hover {
            background: var(--sidebar-hover);
        }

        .logout {
            margin-top: auto;
            background: rgba(0,0,0,0.3);
            text-align: center;
        }

        /* MAIN */
        .main {
            flex: 1;
            padding: 30px;
        }

        .welcome {
            font-size: 1.6rem;
            font-weight: 600;
        }

        .subtitle {
            color: var(--text-muted);
        }

        .cards {
            margin-top: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 18px;
            border: 1px solid var(--border-soft);
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            cursor: pointer;
            transition: 0.2s;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .card-desc {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-logo">User Panel</div>

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
        <div class="welcome">Welcome, <?= htmlspecialchars($user['full_name']) ?> 👋</div>
        <div class="subtitle">What would you like to do today?</div>

        <div class="cards">

            <div class="card" onclick="location.href='user_tab_profile.php'">
                <div class="card-title">My Profile</div>
                <div class="card-desc">View and edit your personal information.</div>
            </div>

            <div class="card" onclick="location.href='user_tab_favorites.php'">
                <div class="card-title">Favorites</div>
                <div class="card-desc">See the businesses you saved.</div>
            </div>

            <div class="card" onclick="location.href='user_tab_reviews.php'">
                <div class="card-title">My Reviews</div>
                <div class="card-desc">Check your ratings and comments.</div>
            </div>

            <div class="card" onclick="location.href='user_tab_offers.php'">
                <div class="card-title">My Offers</div>
                <div class="card-desc">View price offers you sent or received.</div>
            </div>

            <div class="card" onclick="location.href='user_tab_messages.php'">
                <div class="card-title">Messages</div>
                <div class="card-desc">Chat with business owners.</div>
            </div>

            <div class="card" onclick="location.href='user_map.php'">
                <div class="card-title">Map</div>
                <div class="card-desc">Explore businesses around you.</div>
            </div>

        </div>
    </main>

</div>

</body>
</html>
