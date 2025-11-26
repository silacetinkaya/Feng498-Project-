<?php
// user_management.php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin.html");
    exit;
}

// --- FILTER & SEARCH LOGIC ---
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? 'all';
$tab = $_GET['tab'] ?? 'users'; // 'users' or 'business'

// Users Query
$userQuery = "SELECT * FROM users WHERE (full_name ILIKE :search OR email ILIKE :search)";
$userParams = [':search' => "%$search%"];

if ($roleFilter !== 'all') {
    $userQuery .= " AND role_type = :role";
    $userParams[':role'] = $roleFilter;
}
$userQuery .= " ORDER BY id DESC";
$stmtUsers = $pdo->prepare($userQuery);
$stmtUsers->execute($userParams);
$users = $stmtUsers->fetchAll();

// Business Query
$bizQuery = "SELECT b.*, u.full_name as owner_name FROM business b 
             LEFT JOIN users u ON b.owner_id = u.id 
             WHERE b.name ILIKE :search";
$stmtBiz = $pdo->prepare($bizQuery);
$stmtBiz->execute([':search' => "%$search%"]);
$businesses = $stmtBiz->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Specific Styles for Management Page */
        .controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .search-box { display: flex; gap: 10px; }
        .search-box input, .search-box select { padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn-action { padding: 8px 12px; border-radius: 5px; color: white; cursor: pointer; border: none; font-size: 0.9rem; }
        .btn-create { background: #2ecc71; }
        .btn-edit { background: #3498db; text-decoration: none; display:inline-block;}
        .btn-delete { background: #e74c3c; text-decoration: none; display:inline-block;}
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 400px; border-radius: 10px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        
        .tabs { margin-bottom: 20px; border-bottom: 2px solid #ddd; }
        .tab-btn { padding: 10px 20px; background: none; border: none; font-size: 1.1rem; cursor: pointer; opacity: 0.6; }
        .tab-btn.active { border-bottom: 3px solid #d32f2f; opacity: 1; font-weight: bold; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo"><i class="fas fa-shield-alt"></i> AdminPanel</div>
    <ul class="nav-links">
        <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="user_management.php" class="<?php echo $tab=='users'?'active':''; ?>"><i class="fas fa-users"></i> User Management</a></li>
        <li><a href="user_management.php?tab=business" class="<?php echo $tab=='business'?'active':''; ?>"><i class="fas fa-briefcase"></i> Businesses</a></li>
        <!-- FIXED LINK BELOW -->
        <li><a href="review_management.php"><i class="fas fa-star"></i> Reviews</a></li>
        <li><a href="report_management.php"><i class="fas fa-flag"></i> Reports</a></li>
        <li><a href="#"><i class="fas fa-trophy"></i> Best of Day</a></li>
    </ul>
    <div class="logout-section">
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    
    <header>
        <h2>User & Business Management</h2>
        <div class="system-status"><span class="status-dot"></span>System Operational</div>
    </header>

    <!-- TABS -->
    <div class="tabs">
        <button class="tab-btn <?php echo $tab=='users'?'active':''; ?>" onclick="window.location.href='?tab=users'">Users & Admins</button>
        <button class="tab-btn <?php echo $tab=='business'?'active':''; ?>" onclick="window.location.href='?tab=business'">Businesses</button>
    </div>

    <!-- USERS TAB -->
    <?php if($tab == 'users'): ?>
    <div class="controls">
        <form class="search-box" method="GET">
            <input type="hidden" name="tab" value="users">
            <input type="text" name="search" placeholder="Search Name/Email..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="role">
                <option value="all">All Roles</option>
                <option value="user" <?php if($roleFilter=='user') echo 'selected'; ?>>User</option>
                <option value="admin" <?php if($roleFilter=='admin') echo 'selected'; ?>>Admin</option>
            </select>
            <button type="submit" class="btn-action btn-edit">Filter</button>
        </form>
        <button class="btn-action btn-create" onclick="document.getElementById('createUserModal').style.display='block'">
            <i class="fas fa-plus"></i> Create User/Admin
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <table width="100%">
                <thead>
                    <tr>
                        <td>ID</td>
                        <td>Full Name</td>
                        <td>Email</td>
                        <td>Role</td>
                        <td>Actions</td>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>#<?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><span class="status <?php echo $user['role_type'] === 'admin' ? 'admin' : 'user'; ?>"><?php echo htmlspecialchars($user['role_type']); ?></span></td>
                        <td>
                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn-action btn-edit"><i class="fas fa-edit"></i></a>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <a href="admin_process.php?action=delete_user&id=<?php echo $user['id']; ?>" 
                                   class="btn-action btn-delete"
                                   onclick="return confirm('Delete this user? This cannot be undone.');">
                                   <i class="fas fa-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- BUSINESS TAB -->
    <?php if($tab == 'business'): ?>
    <div class="controls">
        <form class="search-box" method="GET">
            <input type="hidden" name="tab" value="business">
            <input type="text" name="search" placeholder="Search Business Name..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn-action btn-edit">Search</button>
        </form>
        <button class="btn-action btn-create" onclick="document.getElementById('createBusinessModal').style.display='block'">
            <i class="fas fa-plus"></i> Create Business
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <table width="100%">
                <thead>
                    <tr>
                        <td>ID</td>
                        <td>Business Name</td>
                        <td>Owner</td>
                        <td>Phone</td>
                        <td>Actions</td>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($businesses as $biz): ?>
                    <tr>
                        <td>#<?php echo $biz['shop_id']; ?></td>
                        <td><?php echo htmlspecialchars($biz['name']); ?></td>
                        <td><?php echo htmlspecialchars($biz['owner_name']); ?></td>
                        <td><?php echo htmlspecialchars($biz['tel_no']); ?></td>
                        <td>
                             <a href="edit_business.php?id=<?php echo $biz['shop_id']; ?>" class="btn-action btn-edit"><i class="fas fa-edit"></i></a>
                             
                             <a href="admin_process.php?action=delete_business&id=<?php echo $biz['shop_id']; ?>" 
                               class="btn-action btn-delete"
                               onclick="return confirm('Delete this business?');">
                               <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

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
            <button type="submit" class="btn-action btn-create" style="width:100%">Create User</button>
        </form>
    </div>
</div>

<!-- CREATE BUSINESS MODAL -->
<div id="createBusinessModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('createBusinessModal').style.display='none'">&times;</span>
        <h2>Create Business</h2>
        <form action="admin_process.php" method="POST">
            <input type="hidden" name="action" value="create_business">
            <div class="form-group">
                <label>Business Name</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Owner ID (User ID)</label>
                <input type="number" name="owner_id" placeholder="Enter User ID of Owner" required>
                <small>Check User List for ID</small>
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address">
            </div>
             <div class="form-group">
                <label>Phone</label>
                <input type="text" name="tel_no">
            </div>
            <button type="submit" class="btn-action btn-create" style="width:100%">Create Business</button>
        </form>
    </div>
</div>

<script>
    window.onclick = function(event) {
        if (event.target.className === 'modal') {
            event.target.style.display = "none";
        }
    }
</script>

</body>
</html>