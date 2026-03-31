<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {

    // Toggle Favorite (AJAX) - sayfa yönlendirme YOK
    if ($action === 'toggle_favorite_ajax') {
        header('Content-Type: application/json; charset=utf-8');

        $businessId = (int)($_POST['business_id'] ?? 0);
        if ($businessId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'invalid_business']);
            exit;
        }

        $check = $pdo->prepare("SELECT 1 FROM business_favorites WHERE user_id=? AND business_id=?");
        $check->execute([$userId, $businessId]);
        $exists = (bool)$check->fetchColumn();

        if ($exists) {
            $pdo->prepare("DELETE FROM business_favorites WHERE user_id=? AND business_id=?")
                ->execute([$userId, $businessId]);

            echo json_encode(['ok' => true, 'is_favorite' => false]);
            exit;
        } else {
            $pdo->prepare("INSERT INTO business_favorites (user_id, business_id) VALUES (?, ?)")
                ->execute([$userId, $businessId]);

            echo json_encode(['ok' => true, 'is_favorite' => true]);
            exit;
        }
    }

    // Add Favorite (redirectli yöntem - kalsın istersen)
    elseif ($action === 'add_favorite') {
        $businessId = (int)($_GET['business_id'] ?? 0);
        $redirect = $_GET['redirect'] ?? 'user_panel.php?tab=favorites&msg=added';

        if ($businessId > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO business_favorites (user_id, business_id)
                VALUES (?, ?)
                ON CONFLICT DO NOTHING
            ");
            $stmt->execute([$userId, $businessId]);
        }

        header("Location: " . $redirect);
        exit;
    }

    // Remove Favorite (redirectli yöntem - kalsın istersen)
    elseif ($action === 'remove_favorite') {
        $businessId = (int)($_GET['business_id'] ?? 0);
        $redirect = $_GET['redirect'] ?? 'user_panel.php?tab=favorites&msg=removed';

        $stmt = $pdo->prepare("DELETE FROM business_favorites WHERE user_id = ? AND business_id = ?");
        $stmt->execute([$userId, $businessId]);

        header("Location: " . $redirect);
        exit;
    }

    // Delete Review
    elseif ($action === 'delete_review') {
        $reviewId = (int)($_GET['review_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE review_id = ? AND user_id = ?");
        $stmt->execute([$reviewId, $userId]);
        header("Location: user_panel.php?tab=reviews&msg=deleted");
        exit;
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
