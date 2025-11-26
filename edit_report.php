<?php
// edit_report.php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

$id = $_GET['id'] ?? null;
if (!$id) die("No ID specified");

// Fetch Report Data
$stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
$stmt->execute([$id]);
$report = $stmt->fetch();

if (!$report) die("Report not found");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Report</title>
    <link rel="stylesheet" href="admin.css">
    <style>
        .edit-container { max-width: 500px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn-save { background: #3498db; color: white; padding: 12px; width: 100%; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
    </style>
</head>
<body>

    <div class="edit-container">
        <h2>Edit Report #<?php echo $report['id']; ?></h2>
        
        <form action="admin_process.php" method="POST">
            <input type="hidden" name="action" value="update_report">
            <input type="hidden" name="id" value="<?php echo $report['id']; ?>">

            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="Pending" <?php if($report['status']=='Pending') echo 'selected'; ?>>Pending</option>
                    <option value="In Progress" <?php if($report['status']=='In Progress') echo 'selected'; ?>>In Progress</option>
                    <option value="Solved" <?php if($report['status']=='Solved') echo 'selected'; ?>>Solved</option>
                    <option value="Dismissed" <?php if($report['status']=='Dismissed') echo 'selected'; ?>>Dismissed</option>
                </select>
            </div>

            <div class="form-group">
                <label>Report Type</label>
                <input type="text" name="report_type" value="<?php echo htmlspecialchars($report['report_type']); ?>">
            </div>

            <div class="form-group">
                <label>Reason / Content</label>
                <textarea name="reason" rows="5"><?php echo htmlspecialchars($report['reason']); ?></textarea>
            </div>

            <button type="submit" class="btn-save">Update Report</button>
        </form>
        
        <br>
        <a href="report_management.php" style="text-align:center; display:block; color:#666;">Cancel</a>
    </div>

</body>
</html>