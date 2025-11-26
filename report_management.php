<?php
// report_management.php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin.html");
    exit;
}

// FILTER LOGIC
$statusFilter = $_GET['status'] ?? 'all'; // 'all', 'solved', 'unsolved'

$sql = "SELECT r.*, u.full_name as reported_by 
        FROM reports r 
        LEFT JOIN users u ON r.user_id = u.id";

if ($statusFilter === 'solved') {
    $sql .= " WHERE r.status = 'Solved'";
} elseif ($statusFilter === 'unsolved') {
    $sql .= " WHERE r.status != 'Solved' OR r.status IS NULL";
}

$sql .= " ORDER BY r.id DESC";

$stmt = $pdo->query($sql);
$reports = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Management</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .filter-group a { padding: 8px 15px; margin-right: 5px; text-decoration: none; border-radius: 5px; background: #eee; color: #333; }
        .filter-group a.active { background: #d32f2f; color: white; }
        .btn-action { padding: 8px 12px; border-radius: 5px; color: white; cursor: pointer; border: none; font-size: 0.9rem; text-decoration:none; display:inline-block;}
        .btn-create { background: #2ecc71; }
        .btn-edit { background: #3498db; }
        .btn-delete { background: #e74c3c; }
        
        .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: bold; }
        .status-solved { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .status-unsolved { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 400px; border-radius: 10px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo"><i class="fas fa-shield-alt"></i> AdminPanel</div>
    <ul class="nav-links">
        <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="user_management.php"><i class="fas fa-users"></i> User Management</a></li>
        <li><a href="user_management.php?tab=business"><i class="fas fa-briefcase"></i> Businesses</a></li>
        <!-- FIXED LINK BELOW -->
        <li><a href="review_management.php"><i class="fas fa-star"></i> Reviews</a></li>
        <li><a href="report_management.php" class="active"><i class="fas fa-flag"></i> Reports</a></li>
        <li><a href="#"><i class="fas fa-trophy"></i> Best of Day</a></li>
    </ul>
    <div class="logout-section">
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    
    <header>
        <h2>Report Management</h2>
        <div class="system-status"><span class="status-dot"></span>System Operational</div>
    </header>

    <div class="controls">
        <div class="filter-group">
            <a href="?status=all" class="<?php echo $statusFilter=='all'?'active':''; ?>">All Reports</a>
            <a href="?status=unsolved" class="<?php echo $statusFilter=='unsolved'?'active':''; ?>">Unsolved</a>
            <a href="?status=solved" class="<?php echo $statusFilter=='solved'?'active':''; ?>">Solved</a>
        </div>
        <button class="btn-action btn-create" onclick="document.getElementById('createReportModal').style.display='block'">
            <i class="fas fa-plus"></i> Add Report
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <table width="100%">
                <thead>
                    <tr>
                        <td>ID</td>
                        <td>Reported By (ID)</td>
                        <td>Reason</td>
                        <td>Type</td>
                        <td>Status</td>
                        <td>Actions</td>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $r): ?>
                    <tr>
                        <td>#<?php echo $r['id']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($r['reported_by'] ?? 'Unknown'); ?> 
                            <small>(ID: <?php echo $r['user_id']; ?>)</small>
                        </td>
                        <td><?php echo htmlspecialchars(substr($r['reason'], 0, 50)) . (strlen($r['reason'])>50?'...':''); ?></td>
                        <td><?php echo htmlspecialchars($r['report_type']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $r['status']=='Solved'?'status-solved':'status-unsolved'; ?>">
                                <?php echo htmlspecialchars($r['status'] ?? 'Pending'); ?>
                            </span>
                        </td>
                        <td>
                            <a href="edit_report.php?id=<?php echo $r['id']; ?>" class="btn-action btn-edit"><i class="fas fa-edit"></i></a>
                            
                            <a href="admin_process.php?action=delete_report&id=<?php echo $r['id']; ?>" 
                               class="btn-action btn-delete"
                               onclick="return confirm('Delete this report?');">
                               <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
            <button type="submit" class="btn-action btn-create" style="width:100%">Create Report</button>
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