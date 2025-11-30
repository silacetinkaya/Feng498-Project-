<?php
// admin_dashboard.php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin.html?error=access_denied");
    exit;
}

try {
    $userCountStmt = $pdo->query("SELECT COUNT(*) FROM users");
    $totalUsers = $userCountStmt->fetchColumn();

    $bizCountStmt = $pdo->query("SELECT COUNT(*) FROM business");
    $totalBusiness = $bizCountStmt->fetchColumn();

    $reportCountStmt = $pdo->query("SELECT COUNT(*) FROM reports");
    $totalReports = $reportCountStmt->fetchColumn();

    $stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC LIMIT 5");
    $recentUsers = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-shield-alt"></i> AdminPanel
        </div>
        <ul class="nav-links">
            <li><a href="admin_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="user_management.php"><i class="fas fa-users"></i> User Management</a></li>
            <li><a href="category_management.php"><i class="fas fa-tags"></i> Categories</a></li>
            <li><a href="user_management.php?tab=business"><i class="fas fa-briefcase"></i> Businesses</a></li>
            <li><a href="review_management.php"><i class="fas fa-star"></i> Reviews</a></li>
            <li><a href="report_management.php"><i class="fas fa-flag"></i> Reports</a></li>
            <li><a href="#"><i class="fas fa-trophy"></i> Best of Day</a></li>
        </ul>
        <div class="logout-section">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        
        <header>
            <div class="header-title">
                <h2>Overview</h2>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
            </div>
            <div class="system-status">
                <span class="status-dot"></span>
                System Operational
            </div>
        </header>

        <div class="cards">
            <div class="card-single">
                <div><h1><?php echo $totalUsers; ?></h1><span>Total Users</span></div>
                <div><i class="fas fa-users icon-bg"></i></div>
            </div>
            <div class="card-single">
                <div><h1><?php echo $totalBusiness; ?></h1><span>Businesses</span></div>
                <div><i class="fas fa-store icon-bg"></i></div>
            </div>
            <div class="card-single">
                <div><h1><?php echo $totalReports; ?></h1><span>Active Reports</span></div>
                <div><i class="fas fa-exclamation-circle icon-bg"></i></div>
            </div>
        </div>

        <div class="recent-grid">
            <div class="projects">
                <div class="card">
                    <div class="card-header">
                        <h3>Newest Users</h3>
                        <a href="user_management.php"><button>Manage Users <i class="fas fa-arrow-right"></i></button></a>
                    </div>
                    <div class="card-body">
                        <table width="100%">
                            <thead>
                                <tr>
                                    <td>ID</td>
                                    <td>Full Name</td>
                                    <td>Email</td>
                                    <td>Role</td>
                                    <td>Date</td>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td>#<?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="status <?php echo $user['role_type'] === 'admin' ? 'admin' : 'user'; ?>">
                                            <?php echo htmlspecialchars($user['role_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['registration_date'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

</body>
</html>