<?php
// delete_user.php
session_start();
require_once 'db_connect.php';

// 1. Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // 2. Prevent Self-Deletion
    if ($id == $_SESSION['user_id']) {
        echo "<script>alert('You cannot delete yourself!'); window.location.href='admin_dashboard.php';</script>";
        exit;
    }

    try {
        // 3. Delete Query
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);

        header("Location: admin_dashboard.php?msg=deleted");

    } catch (PDOException $e) {
        die("Error deleting user: " . $e->getMessage());
    }
} else {
    header("Location: admin_dashboard.php");
}
?>