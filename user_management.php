<?php
// user_management.php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin.html");
    exit;
}

// --- PAGINATION & FILTER LOGIC ---
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? 'all';
$tab = $_GET['tab'] ?? 'users'; 

// Pagination Variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20; // Default 20
if ($page < 1) $page = 1;
if ($limit < 1) $limit = 20;
$offset = ($page - 1) * $limit;

// --- DATA FETCHING ---
$users = [];
$businesses = [];
$totalPages = 1;

if ($tab == 'users') {
    // 1. Get Total Count for Users
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

    // 2. Fetch Data with Limit/Offset
    $userQuery = "SELECT * FROM users WHERE (full_name ILIKE :search OR email ILIKE :search)";
    if ($roleFilter !== 'all') {
        $userQuery .= " AND role_type = :role";
    }
    $userQuery .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";
    
    $stmtUsers = $pdo->prepare($userQuery);
    $stmtUsers->bindValue(':search', "%$search%");
    if ($roleFilter !== 'all') $stmtUsers->bindValue(':role', $roleFilter);
    $stmtUsers->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtUsers->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtUsers->execute();
    $users = $stmtUsers->fetchAll();

} elseif ($tab == 'business') {
    // 1. Get Total Count for Business
    $countSql = "SELECT COUNT(*) FROM business b WHERE b.name ILIKE :search";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute([':search' => "%$search%"]);
    $totalRecords = $stmtCount->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    // 2. Fetch Data with Limit/Offset
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

// Helper to build pagination links keeping current filters
function getLink($page, $currentLimit) {
    global $search, $roleFilter, $tab;
    return "?tab=$tab&search=$search&role=$roleFilter&limit=$currentLimit&page=$page";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .search-box { display: flex; gap: 10px; }
        .search-box input, .search-box select { padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn-action { padding: 8px 12px; border-radius: 5px; color: white; cursor: pointer; border: none; font-size: 0.9rem; }
        .btn-create { background: #2ecc71; }
        .btn-edit { background: #3498db; text-decoration: none; display:inline-block;}
        .btn-delete { background: #e74c3c; text-decoration: none; display:inline-block;}
        
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }
        .pagination a { padding: 8px 12px; border: 1px solid #ddd; background: white; text-decoration: none; color: #333; border-radius: 4px; }
        .pagination a.active { background: #d32f2f; color: white; border-color: #d32f2f; }
        .pagination a:hover:not(.active) { background: #eee; }

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
        <li><a href="category_management.php"><i class="fas fa-tags"></i> Categories</a></li>
        <li><a href="user_management.php?tab=business" class="<?php echo $tab=='business'?'active':''; ?>"><i class="fas fa-briefcase"></i> Businesses</a></li>
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
        <button class="tab-btn <?php echo $tab=='users'?'active':''; ?>" onclick="window.location.href='?tab=users&limit=<?php echo $limit; ?>'">Users & Admins</button>
        <button class="tab-btn <?php echo $tab=='business'?'active':''; ?>" onclick="window.location.href='?tab=business&limit=<?php echo $limit; ?>'">Businesses</button>
    </div>

    <!-- USERS TAB -->
    <?php if($tab == 'users'): ?>
    <div class="controls">
        <form class="search-box" method="GET">
            <input type="hidden" name="tab" value="users">
            
            <!-- Rows Per Page -->
            <select name="limit" onchange="this.form.submit()" style="width:80px;">
                <option value="20" <?php echo $limit==20?'selected':''; ?>>20</option>
                <option value="50" <?php echo $limit==50?'selected':''; ?>>50</option>
                <option value="100" <?php echo $limit==100?'selected':''; ?>>100</option>
            </select>

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
                                   onclick="return confirm('Delete this user?');">
                                   <i class="fas fa-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- PAGINATION USERS -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if($page > 1): ?>
                    <a href="<?php echo getLink($page-1, $limit); ?>">&laquo; Prev</a>
                <?php endif; ?>
                
                <?php for($i=1; $i<=$totalPages; $i++): ?>
                    <a href="<?php echo getLink($i, $limit); ?>" class="<?php echo $page==$i?'active':''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if($page < $totalPages): ?>
                    <a href="<?php echo getLink($page+1, $limit); ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- BUSINESS TAB -->
    <?php if($tab == 'business'): ?>
    <div class="controls">
        <form class="search-box" method="GET">
            <input type="hidden" name="tab" value="business">
            
             <!-- Rows Per Page -->
            <select name="limit" onchange="this.form.submit()" style="width:80px;">
                <option value="20" <?php echo $limit==20?'selected':''; ?>>20</option>
                <option value="50" <?php echo $limit==50?'selected':''; ?>>50</option>
                <option value="100" <?php echo $limit==100?'selected':''; ?>>100</option>
            </select>

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

            <!-- PAGINATION BUSINESS -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if($page > 1): ?>
                    <a href="<?php echo getLink($page-1, $limit); ?>">&laquo; Prev</a>
                <?php endif; ?>
                
                <?php for($i=1; $i<=$totalPages; $i++): ?>
                    <a href="<?php echo getLink($i, $limit); ?>" class="<?php echo $page==$i?'active':''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if($page < $totalPages): ?>
                    <a href="<?php echo getLink($page+1, $limit); ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Include existing modals here (create user/business) - keeping them hidden for brevity -->
<!-- Keep previous modal code here -->

</body>
</html>
<!-- MODALS -->
<div id="createUserModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('createUserModal').style.display='none'">&times;</span>
        <h2>Create New User</h2>
        <form action="admin_process.php" method="POST">
            <input type="hidden" name="action" value="create_user">
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <div class="form-group"><label>Address</label><input type="text" name="address"></div>
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

<div id="createBusinessModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('createBusinessModal').style.display='none'">&times;</span>
        <h2>Create Business</h2>
        <form action="admin_process.php" method="POST">
            <input type="hidden" name="action" value="create_business">
            <div class="form-group"><label>Business Name</label><input type="text" name="name" required></div>
            <div class="form-group"><label>Owner ID (User ID)</label><input type="number" name="owner_id" required><small>Check User List</small></div>
            <div class="form-group"><label>Address</label><input type="text" name="address"></div>
            <div class="form-group"><label>Phone</label><input type="text" name="tel_no"></div>
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