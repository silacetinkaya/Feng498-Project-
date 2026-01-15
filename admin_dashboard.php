<?php
// admin_dashboard.php - Complete Unified Admin Panel - PART 1
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php?error=access_denied");
    exit;
}

$tab = $_GET['tab'] ?? 'overview';
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? 'all';
$rankFilter = $_GET['rank'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if ($page < 1) $page = 1;
if ($limit < 1) $limit = 20;
$offset = ($page - 1) * $limit;

$successMessage = $_GET['msg'] ?? null;

// OVERVIEW TAB DATA
if ($tab === 'overview') {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalBusiness = $pdo->query("SELECT COUNT(*) FROM business")->fetchColumn();
    $totalReports = $pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
    $recentUsers = $pdo->query("SELECT * FROM users ORDER BY id DESC LIMIT 5")->fetchAll();
}

// USERS TAB DATA
if ($tab === 'users') {
    $countSql = "SELECT COUNT(*) FROM users WHERE (full_name ILIKE :search OR email ILIKE :search)";
    $countParams = [':search' => "%$search%"];
    if ($roleFilter !== 'all') {
        $countSql .= " AND role_type = :role";
        $countParams[':role'] = $roleFilter;
    }
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($countParams);
    $totalRecords = $stmtCount->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    $userQuery = "SELECT * FROM users WHERE (full_name ILIKE :search OR email ILIKE :search)";
    if ($roleFilter !== 'all') $userQuery .= " AND role_type = :role";
    $userQuery .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

    $stmtUsers = $pdo->prepare($userQuery);
    $stmtUsers->bindValue(':search', "%$search%");
    if ($roleFilter !== 'all') $stmtUsers->bindValue(':role', $roleFilter);
    $stmtUsers->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtUsers->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtUsers->execute();
    $users = $stmtUsers->fetchAll();
}

// BUSINESS TAB DATA
if ($tab === 'business') {
    $countSql = "SELECT COUNT(*) FROM business b WHERE b.name ILIKE :search";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute([':search' => "%$search%"]);
    $totalRecords = $stmtCount->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    $bizQuery = "SELECT b.*, u.full_name as owner_name FROM business b 
                 LEFT JOIN users u ON b.owner_id = u.id 
                 WHERE b.name ILIKE :search
                 ORDER BY b.shop_id DESC LIMIT :limit OFFSET :offset";

    $stmtBiz = $pdo->prepare($bizQuery);
    $stmtBiz->bindValue(':search', "%$search%");
    $stmtBiz->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtBiz->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtBiz->execute();
    $businesses = $stmtBiz->fetchAll();
}

// REVIEWS TAB DATA
if ($tab === 'reviews') {
    $countSql = "SELECT COUNT(*)
                 FROM reviews r
                 LEFT JOIN users u ON r.user_id = u.id
                 LEFT JOIN business b ON r.business_id = b.shop_id
                 WHERE (b.name ILIKE :search OR u.full_name ILIKE :search OR r.comments ILIKE :search)";
    $params = [':search' => "%$search%"];
    if ($rankFilter !== 'all') {
        $countSql .= " AND r.rank = :rank";
        $params[':rank'] = $rankFilter;
    }
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalRecords = $stmtCount->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    $sql = "SELECT r.*, u.full_name AS user_name, b.name AS business_name,
            rr.response_text, rr.created_at AS response_date, rr.is_approved AS response_approved,
            string_agg(p.image_url, ',') AS photo_urls
            FROM reviews r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN business b ON r.business_id = b.shop_id
            LEFT JOIN photos p ON r.review_id = p.review_id
            LEFT JOIN review_responses rr ON r.review_id = rr.review_id
            WHERE (b.name ILIKE :search OR u.full_name ILIKE :search OR r.comments ILIKE :search)";
    if ($rankFilter !== 'all') $sql .= " AND r.rank = :rank";
    $sql .= " GROUP BY r.review_id, u.full_name, b.name, rr.response_text, rr.created_at, rr.is_approved
              ORDER BY r.time DESC LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':search', "%$search%");
    if ($rankFilter !== 'all') $stmt->bindValue(':rank', $rankFilter);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $reviews = $stmt->fetchAll();
}

// REPORTS TAB DATA
if ($tab === 'reports') {
    $sql = "SELECT r.*, u.full_name as reported_by FROM reports r LEFT JOIN users u ON r.user_id = u.id";
    if ($statusFilter === 'solved') $sql .= " WHERE r.status = 'Solved'";
    elseif ($statusFilter === 'unsolved') $sql .= " WHERE r.status != 'Solved' OR r.status IS NULL";
    $sql .= " ORDER BY r.id DESC";
    $reports = $pdo->query($sql)->fetchAll();
}
// EDITOR'S CHOICE TAB DATA
if ($tab === 'editors_choice') {
    $search = $_GET['search'] ?? '';

    $sql = "SELECT b.shop_id, b.name, b.is_editors_choice,
            COALESCE(AVG(r.rank), 0) as rating,
            COUNT(r.review_id) as review_count
            FROM business b
            LEFT JOIN reviews r ON r.business_id = b.shop_id AND r.is_approved = TRUE
            WHERE b.name ILIKE :search
            GROUP BY b.shop_id
            ORDER BY b.is_editors_choice DESC, rating DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':search' => "%$search%"]);
    $editorsChoiceBusinesses = $stmt->fetchAll();
}

// CATEGORIES TAB DATA
if ($tab === 'categories') {
    $categories = $pdo->query("SELECT * FROM categories WHERE business_id IS NULL ORDER BY id DESC")->fetchAll();
}
// APPROVALS TAB DATA
if ($tab === 'approvals') {
    $approvalFilter = $_GET['approval'] ?? 'pending';
    $typeFilter = $_GET['type'] ?? 'all';

    // PHOTOS from photos table
    $sql = "SELECT p.photo_id,
            p.image_url,
            p.is_approved,
            r.review_id,
            r.comments as review_comment,
            pr.id as product_id,
            pr.name as product_name,
            b.name as business_name,
            b.shop_id,
            u.full_name as user_name,
            'photo' as source_table
            FROM photos p
            LEFT JOIN reviews r ON p.review_id = r.review_id
            LEFT JOIN products pr ON p.product_id = pr.id
            LEFT JOIN business b ON p.business_id = b.shop_id
            LEFT JOIN users u ON r.user_id = u.id
            WHERE 1=1";

    if ($approvalFilter === 'pending') {
        $sql .= " AND (p.is_approved = FALSE OR p.is_approved IS NULL)";
    } elseif ($approvalFilter === 'approved') {
        $sql .= " AND p.is_approved = TRUE";
    }

    // PRICE LISTS from price_lists table
    $plSql = "SELECT pl.id as photo_id,
              pl.image_url,
              pl.is_approved,
              NULL::integer as review_id,
              NULL::text as review_comment,
              NULL::integer as product_id,
              NULL::varchar as product_name,
              b.name as business_name,
              b.shop_id,
              NULL::varchar as user_name,
              'pricelist' as source_table
              FROM price_lists pl
              LEFT JOIN business b ON pl.business_id = b.shop_id
              WHERE 1=1";

    if ($approvalFilter === 'pending') {
        $plSql .= " AND (pl.is_approved = FALSE OR pl.is_approved IS NULL)";
    } elseif ($approvalFilter === 'approved') {
        $plSql .= " AND pl.is_approved = TRUE";
    }

    // BUSINESS PHOTOS from business_photos table
    $bpSql = "SELECT bp.id as photo_id,
              bp.image_url,
              bp.is_approved,
              NULL::integer as review_id,
              NULL::text as review_comment,
              NULL::integer as product_id,
              NULL::varchar as product_name,
              b.name as business_name,
              b.shop_id,
              NULL::varchar as user_name,
              'business_photo' as source_table
              FROM business_photos bp
              LEFT JOIN business b ON bp.business_id = b.shop_id
              WHERE 1=1";

    if ($approvalFilter === 'pending') {
        $bpSql .= " AND (bp.is_approved = FALSE OR bp.is_approved IS NULL)";
    } elseif ($approvalFilter === 'approved') {
        $bpSql .= " AND bp.is_approved = TRUE";
    }

    // Combine queries based on type filter
    if ($typeFilter === 'photos') {
        $finalSql = $sql . " ORDER BY p.photo_id DESC";
    } elseif ($typeFilter === 'pricelists') {
        $finalSql = $plSql . " ORDER BY pl.id DESC";
    } elseif ($typeFilter === 'business') {
        $finalSql = $bpSql . " ORDER BY bp.id DESC";
    } else {
        // All - combine all three
        $finalSql = "($sql) UNION ALL ($plSql) UNION ALL ($bpSql) ORDER BY photo_id DESC";
    }

    $photos = $pdo->query($finalSql)->fetchAll();
}


function getLink($page, $currentLimit, $tab, $search = '', $filter = '', $filterType = 'role')
{
    return "?tab=$tab&search=$search&{$filterType}=$filter&limit=$currentLimit&page=$page";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --red: #e53935;
            --bg: #f4f5fb;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
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
            flex-shrink: 0;
        }

        .sidebar h2 {
            margin-bottom: 30px;
            font-size: 1.5rem;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s;
        }

        .sidebar a.active,
        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.2);
            font-weight: bold;
        }

        .sidebar a.logout-link {
            margin-top: auto;
        }

        /* MAIN */
        .main {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2rem;
            color: #333;
        }

        .system-status {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-dot {
            height: 8px;
            width: 8px;
            background-color: #2ecc71;
            border-radius: 50%;
            box-shadow: 0 0 0 2px rgba(46, 204, 113, 0.3);
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .stat-card h3 {
            font-size: 2rem;
            color: #333;
        }

        .stat-card p {
            color: #666;
            margin-top: 5px;
        }

        .stat-icon {
            font-size: 2.5rem;
            color: var(--red);
            opacity: 0.3;
        }

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .search-box {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-box input,
        .search-box select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .btn {
            padding: 10px 18px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-success {
            background: #2ecc71;
            color: white;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-action {
            padding: 8px 12px;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            font-weight: 600;
            color: #555;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status.admin {
            background: #ffebee;
            color: #c62828;
        }

        .status.user {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .badge-pending {
            background: #ffeeba;
            color: #856404;
        }

        .badge-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status-solved {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .status-unsolved {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination a {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background: white;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
        }

        .pagination a.active {
            background: var(--red);
            color: white;
            border-color: var(--red);
        }

        .pagination a:hover:not(.active) {
            background: #eee;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .review-thumb {
            width: 40px;
            height: 40px;
            object-fit: cover;
            margin-right: 4px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .star-rating {
            color: #f1c40f;
        }

        .cat-list {
            list-style: none;
            padding: 0;
        }

        .cat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .cat-item:last-child {
            border-bottom: none;
        }

        .filter-group {
            display: flex;
            gap: 10px;
        }

        .filter-group a {
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            background: #eee;
            color: #333;
        }

        .filter-group a.active {
            background: var(--red);
            color: white;
        }

        .response-cell {
            background: #fcfcfc;
            border-left: 3px solid #e0e0e0;
        }

        .form-inline {
            display: flex;
            gap: 10px;
        }

        .form-inline input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="layout">
        <!-- SIDEBAR -->
        <nav class="sidebar">
            <h2><i class="fas fa-shield-alt"></i> AdminPanel</h2>
            <a href="?tab=overview" class="<?= $tab == 'overview' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i> Overview
            </a>
            <a href="?tab=users" class="<?= $tab == 'users' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> Users
            </a>
            <a href="?tab=business" class="<?= $tab == 'business' ? 'active' : '' ?>">
                <i class="fas fa-briefcase"></i> Businesses
            </a>
            <a href="?tab=reviews" class="<?= $tab == 'reviews' ? 'active' : '' ?>">
                <i class="fas fa-star"></i> Reviews
            </a>
            <a href="?tab=reports" class="<?= $tab == 'reports' ? 'active' : '' ?>">
                <i class="fas fa-flag"></i> Reports
            </a>
            <a href="?tab=categories" class="<?= $tab == 'categories' ? 'active' : '' ?>">
                <i class="fas fa-tags"></i> Categories
            </a>
            <a href="?tab=approvals" class="<?= $tab == 'approvals' ? 'active' : '' ?>">
                <i class="fas fa-images"></i> Photo Approvals
            </a>
            <a href="?tab=editors_choice" class="<?= $tab == 'editors_choice' ? 'active' : '' ?>">
                <i class="fas fa-award"></i> Editor's Choice
            </a>
            <a href="logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>

        </nav>

        <!-- MAIN CONTENT -->
        <main class="main">
            <?php if ($successMessage): ?>
                <div class="alert alert-success">
                    <?php
                    $messages = [
                        'user_created' => 'User created successfully!',
                        'biz_created' => 'Business created successfully!',
                        'updated' => 'Updated successfully!',
                        'deleted' => 'Deleted successfully!',
                        'approved' => 'Approved successfully!',
                        'resp_approved' => 'Response approved successfully!',
                        'resp_deleted' => 'Response deleted successfully!',
                        'created' => 'Created successfully!'
                    ];
                    echo $messages[$successMessage] ?? 'Operation successful!';
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'overview'): ?>
                <!-- OVERVIEW TAB -->
                <div class="header">
                    <div>
                        <h1>Overview</h1>
                        <p style="color: #666;">Welcome, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></p>
                    </div>
                    <div class="system-status">
                        <span class="status-dot"></span>
                        System Operational
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div>
                            <h3><?= $totalUsers ?></h3>
                            <p>Total Users</p>
                        </div>
                        <i class="fas fa-users stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <div>
                            <h3><?= $totalBusiness ?></h3>
                            <p>Businesses</p>
                        </div>
                        <i class="fas fa-store stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <div>
                            <h3><?= $totalReports ?></h3>
                            <p>Active Reports</p>
                        </div>
                        <i class="fas fa-exclamation-circle stat-icon"></i>
                    </div>
                </div>

                <div class="card">
                    <h3 style="margin-bottom: 20px;">Recent Users</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td>#<?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="status <?= $user['role_type'] === 'admin' ? 'admin' : 'user' ?>">
                                            <?= htmlspecialchars($user['role_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($user['registration_date'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($tab === 'users'): ?>
                <!-- USERS TAB -->
                <div class="header">
                    <h1>User Management</h1>
                </div>

                <div class="controls">
                    <form class="search-box" method="GET">
                        <input type="hidden" name="tab" value="users">
                        <select name="limit" onchange="this.form.submit()">
                            <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                            <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                        </select>
                        <input type="text" name="search" placeholder="Search Name/Email..." value="<?= htmlspecialchars($search) ?>">
                        <select name="role">
                            <option value="all">All Roles</option>
                            <option value="user" <?= $roleFilter == 'user' ? 'selected' : '' ?>>User</option>
                            <option value="admin" <?= $roleFilter == 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </form>
                    <button class="btn btn-success" onclick="document.getElementById('createUserModal').style.display='block'">
                        <i class="fas fa-plus"></i> Create User
                    </button>
                </div>

                <div class="card">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>#<?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="status <?= $user['role_type'] === 'admin' ? 'admin' : 'user' ?>">
                                            <?= htmlspecialchars($user['role_type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn-action btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="admin_process.php?action=delete_user&id=<?= $user['id'] ?>"
                                                class="btn-action btn-danger"
                                                onclick="return confirm('Delete this user?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="<?= getLink($page - 1, $limit, 'users', $search, $roleFilter, 'role') ?>">&laquo; Prev</a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="<?= getLink($i, $limit, 'users', $search, $roleFilter, 'role') ?>"
                                    class="<?= $page == $i ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="<?= getLink($page + 1, $limit, 'users', $search, $roleFilter, 'role') ?>">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($tab === 'business'): ?>
                <!-- BUSINESS TAB -->
                <div class="header">
                    <h1>Business Management</h1>
                </div>

                <div class="controls">
                    <form class="search-box" method="GET">
                        <input type="hidden" name="tab" value="business">
                        <select name="limit" onchange="this.form.submit()">
                            <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                            <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                        </select>
                        <input type="text" name="search" placeholder="Search Business Name..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>
                    <button class="btn btn-success" onclick="document.getElementById('createBusinessModal').style.display='block'">
                        <i class="fas fa-plus"></i> Create Business
                    </button>
                </div>

                <div class="card">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Business Name</th>
                                <th>Owner</th>
                                <th>Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($businesses as $biz): ?>
                                <tr>
                                    <td>#<?= $biz['shop_id'] ?></td>
                                    <td><?= htmlspecialchars($biz['name']) ?></td>
                                    <td><?= htmlspecialchars($biz['owner_name']) ?></td>
                                    <td><?= htmlspecialchars($biz['tel_no']) ?></td>
                                    <td>
                                        <a href="edit_business.php?id=<?= $biz['shop_id'] ?>" class="btn-action btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="admin_process.php?action=delete_business&id=<?= $biz['shop_id'] ?>"
                                            class="btn-action btn-danger"
                                            onclick="return confirm('Delete this business?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="<?= getLink($page - 1, $limit, 'business', $search) ?>">&laquo; Prev</a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="<?= getLink($i, $limit, 'business', $search) ?>"
                                    class="<?= $page == $i ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="<?= getLink($page + 1, $limit, 'business', $search) ?>">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab === 'reviews'): ?>
                <!-- REVIEWS TAB -->
                <div class="header">
                    <h1>Review Management</h1>
                </div>

                <div class="controls">
                    <form class="search-box" method="GET">
                        <input type="hidden" name="tab" value="reviews">
                        <select name="limit" onchange="this.form.submit()">
                            <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                            <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                        </select>
                        <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                        <select name="rank">
                            <option value="all">All Ratings</option>
                            <option value="5" <?= $rankFilter == '5' ? 'selected' : '' ?>>5 Stars</option>
                            <option value="4" <?= $rankFilter == '4' ? 'selected' : '' ?>>4 Stars</option>
                            <option value="3" <?= $rankFilter == '3' ? 'selected' : '' ?>>3 Stars</option>
                            <option value="2" <?= $rankFilter == '2' ? 'selected' : '' ?>>2 Stars</option>
                            <option value="1" <?= $rankFilter == '1' ? 'selected' : '' ?>>1 Star</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </form>
                </div>

                <div class="card">
                    <table>
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="15%">Business / User</th>
                                <th width="30%">User Review</th>
                                <th width="30%">Business Response</th>
                                <th width="10%">Photos</th>
                                <th width="10%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $r): ?>
                                <tr>
                                    <td valign="top">#<?= $r['review_id'] ?></td>

                                    <!-- BUSINESS + USER -->
                                    <td valign="top">
                                        <strong><?= htmlspecialchars($r['business_name'] ?? 'Unknown') ?></strong><br>
                                        <small>User: <?= htmlspecialchars($r['user_name']) ?></small><br>
                                        <?php if ($r['is_approved'] == 't'): ?>
                                            <span class="badge badge-approved">Live</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- USER REVIEW -->
                                    <td valign="top">
                                        <div class="star-rating">
                                            <?php echo str_repeat('<i class="fas fa-star"></i>', $r['rank']); ?>
                                            <?php echo str_repeat('<i class="far fa-star"></i>', 5 - $r['rank']); ?>
                                        </div>
                                        <p><?= htmlspecialchars($r['comments']) ?></p>
                                        <small><?= date('M d, Y', strtotime($r['time'])) ?></small><br><br>

                                        <?php if ($r['is_approved'] != 't'): ?>
                                            <a href="admin_process.php?action=approve_review&id=<?= $r['review_id'] ?>">
                                                <button class="btn btn-success" style="padding:6px 10px; font-size:0.8rem;">Approve Review</button>
                                            </a>
                                        <?php endif; ?>
                                    </td>

                                    <!-- BUSINESS RESPONSE -->
                                    <td valign="top" class="response-cell">
                                        <?php if (!empty($r['response_text'])): ?>
                                            <?php if ($r['response_approved'] == 't'): ?>
                                                <span class="badge badge-approved">Live</span>
                                            <?php else: ?>
                                                <span class="badge badge-pending">Pending</span>
                                            <?php endif; ?>

                                            <div style="margin-top:5px;">
                                                <i class="fas fa-reply"></i>
                                                "<?= htmlspecialchars($r['response_text']) ?>"
                                            </div>

                                            <small><?= date('M d, Y', strtotime($r['response_date'])) ?></small><br><br>

                                            <?php if ($r['response_approved'] != 't'): ?>
                                                <a href="admin_process.php?action=approve_response&review_id=<?= $r['review_id'] ?>">
                                                    <button class="btn btn-success" style="padding:6px 10px; font-size:0.8rem;">Approve Reply</button>
                                                </a>
                                            <?php endif; ?>

                                            <a href="admin_process.php?action=delete_response&review_id=<?= $r['review_id'] ?>"
                                                onclick="return confirm('Delete this reply?');">
                                                <button class="btn btn-danger" style="padding:6px 10px; font-size:0.8rem;"><i class="fas fa-trash"></i></button>
                                            </a>
                                        <?php else: ?>
                                            <span style="color:#ccc">No response</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- PHOTOS -->
                                    <td valign="top">
                                        <?php
                                        if ($r['photo_urls']) {
                                            foreach (explode(',', $r['photo_urls']) as $p) {
                                                echo '<img src="' . htmlspecialchars($p) . '" class="review-thumb">';
                                            }
                                        } else {
                                            echo '<span style="color:#ccc;font-size:0.8rem;">No photos</span>';
                                        }
                                        ?>
                                    </td>

                                    <!-- DELETE REVIEW -->
                                    <td valign="top">
                                        <a href="admin_process.php?action=delete_review&id=<?= $r['review_id'] ?>"
                                            class="btn-action btn-danger"
                                            onclick="return confirm('Delete this entire review?');">
                                            Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php
                            if ($page > 1) echo "<a href='" . getLink($page - 1, $limit, 'reviews', $search, $rankFilter, 'rank') . "'>&laquo; Prev</a>";
                            for ($i = 1; $i <= $totalPages; $i++) {
                                $active = ($i == $page) ? 'active' : '';
                                echo "<a href='" . getLink($i, $limit, 'reviews', $search, $rankFilter, 'rank') . "' class='$active'>$i</a>";
                            }
                            if ($page < $totalPages) echo "<a href='" . getLink($page + 1, $limit, 'reviews', $search, $rankFilter, 'rank') . "'>Next &raquo;</a>";
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($tab === 'reports'): ?>
                <!-- REPORTS TAB -->
                <div class="header">
                    <h1>Report Management</h1>
                </div>

                <div class="controls">
                    <div class="filter-group">
                        <a href="?tab=reports&status=all" class="<?= $statusFilter == 'all' ? 'active' : '' ?>">All Reports</a>
                        <a href="?tab=reports&status=unsolved" class="<?= $statusFilter == 'unsolved' ? 'active' : '' ?>">Unsolved</a>
                        <a href="?tab=reports&status=solved" class="<?= $statusFilter == 'solved' ? 'active' : '' ?>">Solved</a>
                    </div>
                    <button class="btn btn-success" onclick="document.getElementById('createReportModal').style.display='block'">
                        <i class="fas fa-plus"></i> Add Report
                    </button>
                </div>

                <div class="card">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Reported By</th>
                                <th>Reason</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $r): ?>
                                <tr>
                                    <td>#<?= $r['id'] ?></td>
                                    <td>
                                        <?= htmlspecialchars($r['reported_by'] ?? 'Unknown') ?>
                                        <small>(ID: <?= $r['user_id'] ?>)</small>
                                    </td>
                                    <td><?= htmlspecialchars(substr($r['reason'], 0, 50)) . (strlen($r['reason']) > 50 ? '...' : '') ?></td>
                                    <td><?= htmlspecialchars($r['report_type']) ?></td>
                                    <td>
                                        <span class="status-badge <?= $r['status'] == 'Solved' ? 'status-solved' : 'status-unsolved' ?>">
                                            <?= htmlspecialchars($r['status'] ?? 'Pending') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit_report.php?id=<?= $r['id'] ?>" class="btn-action btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="admin_process.php?action=delete_report&id=<?= $r['id'] ?>"
                                            class="btn-action btn-danger"
                                            onclick="return confirm('Delete this report?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($tab === 'categories'): ?>
                <!-- CATEGORIES TAB -->
                <div class="header">
                    <h1>Category Management</h1>
                </div>

                <div class="card">
                    <h3 style="margin-bottom: 20px;">Add New Global Category</h3>
                    <form action="admin_process.php" method="POST" class="form-inline" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px dashed #ccc;">
                        <input type="hidden" name="action" value="create_category">
                        <input type="text" name="type" required placeholder="Enter category name (e.g. Automotive)">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Category
                        </button>
                    </form>

                    <h3 style="margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Existing Categories</h3>

                    <ul class="cat-list">
                        <?php foreach ($categories as $c): ?>
                            <li class="cat-item">
                                <span>
                                    <i class="fas fa-tag" style="color:#aaa; margin-right:10px;"></i>
                                    <?= htmlspecialchars($c['type']) ?>
                                </span>
                                <a href="admin_process.php?action=delete_category&id=<?= $c['id'] ?>"
                                    onclick="return confirm('Remove this category?');">
                                    <button class="btn btn-danger" style="padding: 5px 10px; font-size: 0.85rem;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif ($tab === 'editors_choice'): ?>
                <!-- EDITOR'S CHOICE TAB -->
                <div class="header">
                    <h1>Editor's Choice Management</h1>
                </div>

                <div class="controls">
                    <form class="search-box" method="GET">
                        <input type="hidden" name="tab" value="editors_choice">
                        <input type="text" name="search" placeholder="Search Business..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>
                </div>

                <div class="card">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Business Name</th>
                                <th>Rating</th>
                                <th>Reviews</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($editorsChoiceBusinesses as $b): ?>
                                <tr>
                                    <td>#<?= $b['shop_id'] ?></td>
                                    <td><?= htmlspecialchars($b['name']) ?></td>
                                    <td>
                                        <span style="color: #f1c40f;">
                                            <?= str_repeat('★', round($b['rating'])) ?>
                                            <?= str_repeat('☆', 5 - round($b['rating'])) ?>
                                        </span>
                                        <span style="color: #666; font-size: 0.9rem;">
                                            (<?= number_format($b['rating'], 1) ?>)
                                        </span>
                                    </td>
                                    <td><?= $b['review_count'] ?> reviews</td>
                                    <td>
                                        <?php if ($b['is_editors_choice'] == 't'): ?>
                                            <span class="badge badge-approved">
                                                <i class="fas fa-award"></i> Editor's Choice
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-pending">Not Selected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($b['is_editors_choice'] == 't'): ?>
                                            <a href="admin_process.php?action=remove_editors_choice&id=<?= $b['shop_id'] ?>">
                                                <button class="btn-action btn-danger">
                                                    <i class="fas fa-times"></i> Remove
                                                </button>
                                            </a>
                                        <?php else: ?>
                                            <a href="admin_process.php?action=add_editors_choice&id=<?= $b['shop_id'] ?>">
                                                <button class="btn-action btn-success">
                                                    <i class="fas fa-check"></i> Add
                                                </button>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($tab === 'approvals'): ?>
                <!-- APPROVALS TAB -->
                <div class="header">
                    <h1>Photo Approval Management</h1>
                </div>

                <div class="controls">
                    <div class="filter-group">
                        <a href="?tab=approvals&approval=pending&type=<?= $typeFilter ?>"
                            class="<?= $approvalFilter == 'pending' ? 'active' : '' ?>">Pending</a>
                        <a href="?tab=approvals&approval=approved&type=<?= $typeFilter ?>"
                            class="<?= $approvalFilter == 'approved' ? 'active' : '' ?>">Approved</a>
                        <a href="?tab=approvals&approval=all&type=<?= $typeFilter ?>"
                            class="<?= $approvalFilter == 'all' ? 'active' : '' ?>">All Photos</a>
                    </div>

                    <div class="filter-group">
                        <a href="?tab=approvals&approval=<?= $approvalFilter ?>&type=all"
                            class="<?= $typeFilter == 'all' ? 'active' : '' ?>">All Types</a>
                        <a href="?tab=approvals&approval=<?= $approvalFilter ?>&type=photos"
                            class="<?= $typeFilter == 'photos' ? 'active' : '' ?>">Review Photos</a>
                        <a href="?tab=approvals&approval=<?= $approvalFilter ?>&type=business"
                            class="<?= $typeFilter == 'business' ? 'active' : '' ?>">Business Photos</a>
                        <a href="?tab=approvals&approval=<?= $approvalFilter ?>&type=pricelists"
                            class="<?= $typeFilter == 'pricelists' ? 'active' : '' ?>">Price Lists</a>
                    </div>
                </div>

                <div class="card">
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
                        <?php foreach ($photos as $p): ?>
                            <div style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: #f9f9f9;">
                                <img src="<?= htmlspecialchars($p['image_url']) ?>"
                                    style="width: 100%; height: 200px; object-fit: cover; border-radius: 4px; margin-bottom: 10px;">

                                <div style="font-size: 0.85rem; color: #666; margin-bottom: 10px;">
                                    <strong>ID:</strong> #<?= $p['photo_id'] ?><br>

                                    <?php if ($p['source_table'] === 'pricelist'): ?>
                                        <span style="background: #3498db; color: white; padding: 2px 8px; border-radius: 3px; font-size: 0.75rem;">PRICE LIST</span><br>
                                        <strong>Business:</strong> <?= htmlspecialchars($p['business_name']) ?>

                                    <?php elseif ($p['source_table'] === 'business_photo'): ?>
                                        <span style="background: #27ae60; color: white; padding: 2px 8px; border-radius: 3px; font-size: 0.75rem;">BUSINESS PHOTO</span><br>
                                        <strong>Business:</strong> <?= htmlspecialchars($p['business_name']) ?>

                                    <?php elseif ($p['review_id']): ?>
                                        <span style="background: #9b59b6; color: white; padding: 2px 8px; border-radius: 3px; font-size: 0.75rem;">REVIEW</span><br>
                                        <strong>From:</strong> <?= htmlspecialchars($p['user_name']) ?><br>
                                        <strong>Business:</strong> <?= htmlspecialchars($p['business_name']) ?>

                                    <?php elseif ($p['product_id']): ?>
                                        <span style="background: #e67e22; color: white; padding: 2px 8px; border-radius: 3px; font-size: 0.75rem;">PRODUCT</span><br>
                                        <strong>Product:</strong> <?= htmlspecialchars($p['product_name']) ?>

                                    <?php elseif ($p['business_name']): ?>
                                        <span style="background: #27ae60; color: white; padding: 2px 8px; border-radius: 3px; font-size: 0.75rem;">BUSINESS</span><br>
                                        <strong>Business:</strong> <?= htmlspecialchars($p['business_name']) ?>
                                    <?php endif; ?>

                                </div>

                                <?php if ($p['is_approved'] == 't'): ?>
                                    <span class="badge badge-approved">Approved</span>
                                <?php else: ?>
                                    <span class="badge badge-pending">Pending</span>
                                    <div style="margin-top: 10px;">
                                        <a href="admin_process.php?action=approve_photo&id=<?= $p['photo_id'] ?>&source=<?= $p['source_table'] ?>">
                                            <button class="btn btn-success" style="width: 100%; padding: 8px; font-size: 0.85rem;">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <a href="admin_process.php?action=delete_photo&id=<?= $p['photo_id'] ?>&source=<?= $p['source_table'] ?>"
                                    onclick="return confirm('Delete this photo?');">
                                    <button class="btn btn-danger" style="width: 100%; margin-top: 5px; padding: 8px; font-size: 0.85rem;">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>
    <!-- CREATE USER MODAL -->
    <div id="createUserModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('createUserModal').style.display='none'">&times;</span>
            <h2>Create New User</h2>
            <form action="admin_process.php" method="POST">
                <input type="hidden" name="action" value="create_user">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role_type">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success" style="width:100%">Create User</button>
            </form>
        </div>
    </div>
    <!-- CREATE BUSINESS MODAL -->
    <div id="createBusinessModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('createBusinessModal').style.display='none'">&times;</span>
            <h2>Create Business & Owner Account</h2>
            <form action="admin_process.php" method="POST">
                <input type="hidden" name="action" value="create_business_with_user">

                <h3 style="margin-top: 20px; color: #666;">Business Information</h3>
                <div class="form-group">
                    <label>Business Name</label>
                    <input type="text" name="business_name" required>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="tel_no">
                </div>

                <h3 style="margin-top: 20px; color: #666;">Owner Account</h3>
                <div class="form-group">
                    <label>Owner Full Name</label>
                    <input type="text" name="owner_name" required>
                </div>
                <div class="form-group">
                    <label>Owner Email (Username)</label>
                    <input type="email" name="owner_email" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="owner_password" required>
                </div>

                <button type="submit" class="btn btn-success" style="width:100%">Create Business & Owner</button>
            </form>
        </div>
    </div>

    <!-- CREATE REPORT MODAL -->
    <div id="createReportModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('createReportModal').style.display='none'">&times;</span>
            <h2>Add Manual Report</h2>
            <form action="admin_process.php" method="POST">
                <input type="hidden" name="action" value="create_report">
                <div class="form-group">
                    <label>User ID (Reporter)</label>
                    <input type="number" name="user_id" required>
                </div>
                <div class="form-group">
                    <label>Report Type</label>
                    <select name="report_type">
                        <option value="Inappropriate Content">Inappropriate Content</option>
                        <option value="Spam">Spam</option>
                        <option value="Fraud">Fraud</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Reason / Details</label>
                    <textarea name="reason" rows="4" required></textarea>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Solved">Solved</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success" style="width:100%">Create Report</button>
            </form>
        </div>
    </div>

    <script>
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = "none";
            }
        }
    </script>

</body>

</html>