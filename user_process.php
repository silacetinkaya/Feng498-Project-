<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {
    // Remove Favorite
    if ($action === 'remove_favorite') {
        $businessId = $_GET['business_id'];
        $stmt = $pdo->prepare("DELETE FROM business_favorites WHERE user_id = ? AND business_id = ?");
        $stmt->execute([$userId, $businessId]);
        header("Location: user_panel.php?tab=favorites&msg=removed");
        exit;
    }
    
    // Delete Review
    elseif ($action === 'delete_review') {
        $reviewId = $_GET['review_id'];
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE review_id = ? AND user_id = ?");
        $stmt->execute([$reviewId, $userId]);
        header("Location: user_panel.php?tab=reviews&msg=deleted");
        exit;
    }
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>