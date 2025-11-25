<?php
// admin_dashboard.php
session_start();
require_once 'db_connect.php';

// 1. SECURITY: Enforce Admin Access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin.html?error=access_denied");
    exit;
}

// 2. DATA FETCHING: Get stats and user list
try {
    // Count total users
    $countStmt = $pdo->query("SELECT COUNT(*) FROM users");
    $totalUsers = $countStmt->fetchColumn();

    // Fetch all users ordered by ID
    $stmt = $pdo->query("SELECT * FROM users ORDER BY id ASC");
    $users = $stmt->fetchAll();

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
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="logo">AdminPanel</div>
        <ul class="nav-links">
            <li><a href="#" class="active">Dashboard</a></li>
            <li><a href="#">User Management</a></li>
            <li><a href="#">System Settings</a></li>
            <li><a href="#">Reports</a></li>
        </ul>
        <div class="logout-section">
            <!-- Simple logout just destroys session (create logout.php if needed) -->
            <a href="index.html" onclick="<?php session_destroy(); ?>">Logout</a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        
        <!-- Top Bar -->
        <header>
            <h2>Overview</h2>
            <div class="user-wrapper">
                <span>Welcome, <b><?php echo htmlspecialchars($_SESSION['admin_name']); ?></b></span>
            </div>
        </header>

        <!-- Stats Cards -->
        <div class="cards">
            <div class="card-single">
                <div>
                    <h1><?php echo $totalUsers; ?></h1>
                    <span>Total Users</span>
                </div>
            </div>
            <div class="card-single">
                <div>
                    <h1>Active</h1>
                    <span>System Status</span>
                </div>
            </div>
            <div class="card-single">
                <div>
                    <h1>0</h1>
                    <span>Pending Issues</span>
                </div>
            </div>
        </div>

        <!-- Recent Grid -->
        <div class="recent-grid">
            <div class="projects">
                <div class="card">
                    <div class="card-header">
                        <h3>User Management</h3>
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
                                    <td>Action</td>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
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
                                    <td>
                                        <!-- Delete Button (Prevent deleting self) -->
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="delete_user.php?id=<?php echo $user['id']; ?>" 
                                               class="btn-delete"
                                               onclick="return confirm('Are you sure you want to delete this user?');">
                                               Delete
                                            </a>
                                        <?php else: ?>
                                            <span style="color:#ccc;">(You)</span>
                                        <?php endif; ?>
                                    </td>
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