<?php
session_start();
require "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

$productId = $_GET['id'] ?? null;
$userId = $_SESSION['user_id'];

if ($productId) {
    // Daha önce eklenmiş mi kontrol
    $check = $pdo->prepare("SELECT id FROM product_favorites 
                            WHERE product_id = :pid AND user_id = :uid");
    $check->execute(['pid' => $productId, 'uid' => $userId]);

    if (!$check->fetchColumn()) {
        $stmt = $pdo->prepare("
            INSERT INTO product_favorites (product_id, user_id)
            VALUES (:pid, :uid)
        ");
        $stmt->execute(['pid' => $productId, 'uid' => $userId]);
    }
}

header("Location: user_tab_favorites.php");
exit;
