<?php
// admin_process.php
session_start();
require_once 'db_connect.php';

// Security: Only Admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

$action = $_REQUEST['action'] ?? '';

try {
    // --- CREATE USER ---
    if ($action === 'create_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $fullName = $_POST['full_name'];
        $email = $_POST['email'];
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role_type'];
        $address = $_POST['address'];

        $sql = "INSERT INTO users (full_name, email, password, role_type, address, registration_date) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fullName, $email, $pass, $role, $address]);
        
        header("Location: user_management.php?msg=user_created");
    }

    // --- CREATE BUSINESS ---
    elseif ($action === 'create_business' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['name'];
        $ownerId = $_POST['owner_id'];
        $address = $_POST['address'];
        $tel = $_POST['tel_no'];

        $check = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $check->execute([$ownerId]);
        if ($check->rowCount() == 0) die("Error: User ID $ownerId does not exist.");

        $sql = "INSERT INTO business (name, owner_id, address, tel_no) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $ownerId, $address, $tel]);

        header("Location: user_management.php?tab=business&msg=biz_created");
    }

    // --- CREATE REPORT ---
    elseif ($action === 'create_report' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $userId = $_POST['user_id'];
        $reason = $_POST['reason'];
        $type = $_POST['report_type'];
        $status = $_POST['status'];

        $sql = "INSERT INTO reports (user_id, reason, report_type, status) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $reason, $type, $status]);

        header("Location: report_management.php?msg=created");
    }

    // --- CREATE REVIEW (NEW) ---
    elseif ($action === 'create_review' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $bizId = $_POST['business_id'];
        $userId = $_POST['user_id'];
        $rank = $_POST['rank'];
        $comments = $_POST['comments'];

        // Verify IDs exist
        $checkBiz = $pdo->prepare("SELECT shop_id FROM business WHERE shop_id = ?");
        $checkBiz->execute([$bizId]);
        $checkUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $checkUser->execute([$userId]);

        if ($checkBiz->rowCount() == 0 || $checkUser->rowCount() == 0) {
            die("Error: Business ID or User ID does not exist.");
        }

        $sql = "INSERT INTO reviews (business_id, user_id, rank, comments, time) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$bizId, $userId, $rank, $comments]);

        header("Location: review_management.php?msg=review_created");
    }

    // --- UPDATE USER ---
    elseif ($action === 'update_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'];
        $fullName = $_POST['full_name'];
        $email = $_POST['email'];
        $role = $_POST['role_type'];
        $address = $_POST['address'];

        $sql = "UPDATE users SET full_name = ?, email = ?, role_type = ?, address = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fullName, $email, $role, $address, $id]);

        header("Location: user_management.php?msg=updated");
    }

    // --- UPDATE BUSINESS ---
    elseif ($action === 'update_business' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['shop_id'];
        $name = $_POST['name'];
        $ownerId = $_POST['owner_id'];
        $address = $_POST['address'];
        $tel = $_POST['tel_no'];
        $desc = $_POST['description'];

        $sql = "UPDATE business SET name=?, owner_id=?, address=?, tel_no=?, description=? WHERE shop_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $ownerId, $address, $tel, $desc, $id]);

        header("Location: user_management.php?tab=business&msg=updated");
    }

    // --- UPDATE REPORT ---
    elseif ($action === 'update_report' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $reason = $_POST['reason'];
        $type = $_POST['report_type'];

        $sql = "UPDATE reports SET status=?, reason=?, report_type=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $reason, $type, $id]);

        header("Location: report_management.php?msg=updated");
    }

    // --- UPDATE REVIEW (NEW) ---
    elseif ($action === 'update_review' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['review_id'];
        $rank = $_POST['rank'];
        $comments = $_POST['comments'];

        $sql = "UPDATE reviews SET rank=?, comments=? WHERE review_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rank, $comments, $id]);

        header("Location: review_management.php?msg=updated");
    }

    // --- DELETE OPERATIONS ---
    elseif ($action === 'delete_user') {
        $id = $_GET['id'];
        if ($id == $_SESSION['user_id']) die("Cannot delete yourself.");
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: user_management.php?msg=deleted");
    }

    elseif ($action === 'delete_business') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM business WHERE shop_id = ?");
        $stmt->execute([$id]);
        header("Location: user_management.php?tab=business&msg=deleted");
    }

    elseif ($action === 'delete_report') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM reports WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: report_management.php?msg=deleted");
    }

    elseif ($action === 'delete_review') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE review_id = ?");
        $stmt->execute([$id]);
        header("Location: review_management.php?msg=deleted");
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>