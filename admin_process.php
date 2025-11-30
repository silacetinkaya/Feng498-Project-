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
    // --- DELETE CATEGORY ---
    elseif ($action === 'delete_category') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: category_management.php?msg=deleted");
    }
    // --- CREATE CATEGORY ---
    elseif ($action === 'create_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $type = trim($_POST['type']);
        if (!empty($type)) {
            $check = $pdo->prepare("SELECT id FROM categories WHERE type ILIKE ?");
            $check->execute([$type]);
            if ($check->rowCount() == 0) {
                $stmt = $pdo->prepare("INSERT INTO categories (type, business_id) VALUES (?, NULL)");
                $stmt->execute([$type]);
            }
        }
        header("Location: category_management.php");
    }
     // --- UPDATE REVIEW (Add Photos) ---
    elseif ($action === 'update_review' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['review_id'];
        $rank = $_POST['rank'];
        $comments = $_POST['comments'];

        $pdo->beginTransaction();

        $sql = "UPDATE reviews SET rank=?, comments=? WHERE review_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rank, $comments, $id]);

        // Check current photo count
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM photos WHERE review_id = ?");
        $cntStmt->execute([$id]);
        $currCount = $cntStmt->fetchColumn();

        // Handle New Images
        if (isset($_FILES['review_images'])) {
            $files = $_FILES['review_images'];
            $fileCount = count($files['name']);
            $remainingSlots = 4 - $currCount;
            $limit = min($fileCount, $remainingSlots);

            if ($limit > 0) {
                $uploadDir = 'uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                for ($i = 0; $i < $limit; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                            $newFile = uniqid('rev_') . '.' . $ext;
                            $dest = $uploadDir . $newFile;
                            if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                                $insPhoto = $pdo->prepare("INSERT INTO photos (image_url, review_id) VALUES (?, ?)");
                                $insPhoto->execute([$dest, $id]);
                            }
                        }
                    }
                }
            }
        }

        $pdo->commit();
        header("Location: review_management.php?msg=updated");
    }

    // --- DELETE REVIEW PHOTO ---
    elseif ($action === 'delete_review_photo') {
        $photoId = $_GET['id'];
        $reviewId = $_GET['review_id'];

        $stmt = $pdo->prepare("DELETE FROM photos WHERE photo_id = ?");
        $stmt->execute([$photoId]);

        header("Location: edit_review.php?id=$reviewId&msg=photo_deleted");
    }

    // --- DELETE REVIEW ---
    elseif ($action === 'delete_review') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE review_id = ?");
        $stmt->execute([$id]);
        header("Location: review_management.php?msg=deleted");
    }
    // --- USER/BUSINESS ACTIONS (RETAINED) ---
    elseif ($action === 'create_user') {
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role_type, address, registration_date) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$_POST['full_name'], $_POST['email'], $pass, $_POST['role_type'], $_POST['address']]);
        header("Location: user_management.php?msg=user_created");
    }
    elseif ($action === 'delete_user') {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        header("Location: user_management.php?msg=deleted");
    }
    elseif ($action === 'delete_business') {
        $stmt = $pdo->prepare("DELETE FROM business WHERE shop_id = ?");
        $stmt->execute([$_GET['id']]);
        header("Location: user_management.php?tab=business&msg=deleted");
    }
    elseif ($action === 'create_report') {
        $stmt = $pdo->prepare("INSERT INTO reports (user_id, reason, report_type, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['user_id'], $_POST['reason'], $_POST['report_type'], $_POST['status']]);
        header("Location: report_management.php?msg=created");
    }
    elseif ($action === 'delete_report') {
        $stmt = $pdo->prepare("DELETE FROM reports WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        header("Location: report_management.php?msg=deleted");
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>