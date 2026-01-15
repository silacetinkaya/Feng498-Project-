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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --red: #e53935;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f5fb;
            padding: 20px;
        }

        .edit-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        h2 {
            color: var(--red);
            margin-bottom: 30px;
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
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
        }

        .btn-save {
            background: #3498db;
            color: white;
            padding: 14px;
            width: 100%;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn-save:hover {
            transform: translateY(-2px);
        }

        .cancel-link {
            text-align: center;
            display: block;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
        }

        .cancel-link:hover {
            color: var(--red);
        }
    </style>
</head>
<body>

    <div class="edit-container">
        <h2><i class="fas fa-flag"></i> Edit Report #<?= $report['id'] ?></h2>
        
        <form action="admin_process.php" method="POST">
            <input type="hidden" name="action" value="update_report">
            <input type="hidden" name="id" value="<?= $report['id'] ?>">

            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="Pending" <?= $report['status']=='Pending'?'selected':'' ?>>Pending</option>
                    <option value="In Progress" <?= $report['status']=='In Progress'?'selected':'' ?>>In Progress</option>
                    <option value="Solved" <?= $report['status']=='Solved'?'selected':'' ?>>Solved</option>
                    <option value="Dismissed" <?= $report['status']=='Dismissed'?'selected':'' ?>>Dismissed</option>
                </select>
            </div>

            <div class="form-group">
                <label>Report Type</label>
                <input type="text" name="report_type" value="<?= htmlspecialchars($report['report_type']) ?>">
            </div>

            <div class="form-group">
                <label>Reason / Content</label>
                <textarea name="reason" rows="5"><?= htmlspecialchars($report['reason']) ?></textarea>
            </div>

            <button type="submit" class="btn-save">
                <i class="fas fa-save"></i> Update Report
            </button>
        </form>
        
        <a href="admin_dashboard.php?tab=reports" class="cancel-link">
            <i class="fas fa-arrow-left"></i> Back to Reports
        </a>
    </div>

</body>
</html>