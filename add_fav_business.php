<?php
session_start();
require "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

$businessId = $_GET['id'] ?? null;
$userId = $_SESSION['user_id'];

if ($businessId) {
    // Daha önce eklenmiş mi kontrol et
    $check = $pdo->prepare("SELECT id FROM business_favorites 
                            WHERE business_id = :bid AND user_id = :uid");
    $check->execute(['bid' => $businessId, 'uid' => $userId]);

    if (!$check->fetchColumn()) {
        $stmt = $pdo->prepare("
            INSERT INTO business_favorites (business_id, user_id)
            VALUES (:bid, :uid)
        ");
        $stmt->execute(['bid' => $businessId, 'uid' => $userId]);
    }
}

header("Location: user_tab_favorites.php");
exit;
