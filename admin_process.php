<?php
// admin_process.php - COMPLETE VERSION
session_start();
require_once 'db_connect.php';

// Security: Only Admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

$action = $_REQUEST['action'] ?? '';

try {
    // ==================== CREATE OPERATIONS ====================

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

        header("Location: admin_dashboard.php?tab=users&msg=user_created");
        exit;
    }

    // --- CREATE BUSINESS WITH USER ACCOUNT ---
    elseif ($action === 'create_business_with_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();

        try {
            // 1. Create User Account (Owner)
            $ownerName = $_POST['owner_name'];
            $ownerEmail = $_POST['owner_email'];
            $ownerPassword = password_hash($_POST['owner_password'], PASSWORD_DEFAULT);

            // Check if email already exists
            $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkEmail->execute([$ownerEmail]);
            if ($checkEmail->rowCount() > 0) {
                throw new Exception("Email already exists!");
            }

            $userSql = "INSERT INTO users (full_name, email, password, role_type, registration_date) 
                        VALUES (?, ?, ?, 'business', NOW()) RETURNING id";
            $userStmt = $pdo->prepare($userSql);
            $userStmt->execute([$ownerName, $ownerEmail, $ownerPassword]);
            $ownerId = $userStmt->fetchColumn();

            // 2. Create Business
            $businessName = $_POST['business_name'];
            $address = $_POST['address'];
            $tel = $_POST['tel_no'];

            $bizSql = "INSERT INTO business (name, owner_id, address, tel_no) VALUES (?, ?, ?, ?)";
            $bizStmt = $pdo->prepare($bizSql);
            $bizStmt->execute([$businessName, $ownerId, $address, $tel]);

            $pdo->commit();
            header("Location: admin_dashboard.php?tab=business&msg=biz_created");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Error: " . $e->getMessage());
        }
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

        header("Location: admin_dashboard.php?tab=reports&msg=created");
        exit;
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
        header("Location: admin_dashboard.php?tab=categories&msg=created");
        exit;
    }

    // ==================== UPDATE OPERATIONS ====================

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

        header("Location: admin_dashboard.php?tab=users&msg=updated");
        exit;
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

        header("Location: admin_dashboard.php?tab=business&msg=updated");
        exit;
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

        header("Location: admin_dashboard.php?tab=reports&msg=updated");
        exit;
    }

    // --- UPDATE REVIEW (with photos) ---
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
        header("Location: admin_dashboard.php?tab=reviews&msg=updated");
        exit;
    }

    // ==================== DELETE OPERATIONS ====================

    // --- DELETE USER ---
    elseif ($action === 'delete_user') {
        $id = $_GET['id'];
        if ($id == $_SESSION['user_id']) die("Cannot delete yourself.");
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin_dashboard.php?tab=users&msg=deleted");
        exit;
    }

    // --- DELETE BUSINESS ---
    elseif ($action === 'delete_business') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM business WHERE shop_id = ?");
        $stmt->execute([$id]);
        header("Location: admin_dashboard.php?tab=business&msg=deleted");
        exit;
    }

    // --- DELETE REPORT ---
    elseif ($action === 'delete_report') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM reports WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin_dashboard.php?tab=reports&msg=deleted");
        exit;
    }

    // --- DELETE REVIEW ---
    elseif ($action === 'delete_review') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE review_id = ?");
        $stmt->execute([$id]);
        header("Location: admin_dashboard.php?tab=reviews&msg=deleted");
        exit;
    }

    // --- DELETE CATEGORY ---
    elseif ($action === 'delete_category') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin_dashboard.php?tab=categories&msg=deleted");
        exit;
    }

    // --- DELETE RESPONSE ONLY ---
    elseif ($action === 'delete_response') {
        $id = $_GET['review_id'];
        $stmt = $pdo->prepare("DELETE FROM review_responses WHERE review_id = ?");
        $stmt->execute([$id]);
        header("Location: admin_dashboard.php?tab=reviews&msg=resp_deleted");
        exit;
    }

    // ==================== APPROVAL OPERATIONS ====================

    // --- APPROVE REVIEW ---
    elseif ($action === 'approve_review') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("UPDATE reviews SET is_approved = TRUE WHERE review_id = ?");
        $stmt->execute([$id]);
        header("Location: admin_dashboard.php?tab=reviews&msg=approved");
        exit;
    }

    // --- APPROVE RESPONSE ---
    elseif ($action === 'approve_response') {
        $id = $_GET['review_id'];
        $stmt = $pdo->prepare("UPDATE review_responses SET is_approved = TRUE WHERE review_id = ?");
        $stmt->execute([$id]);
        header("Location: admin_dashboard.php?tab=reviews&msg=resp_approved");
        exit;
    }
    // --- APPROVE PHOTO ---
    elseif ($action === 'approve_photo') {
        $id = $_GET['id'];
        $source = $_GET['source'] ?? 'photo';

        if ($source === 'pricelist') {
            $stmt = $pdo->prepare("UPDATE price_lists SET is_approved = TRUE WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE photos SET is_approved = TRUE WHERE photo_id = ?");
        }
        $stmt->execute([$id]);
        header("Location: admin_dashboard.php?tab=approvals&msg=approved");
        exit;
    }

    // --- DELETE PHOTO ---
    elseif ($action === 'delete_photo') {
        $id = $_GET['id'];
        $source = $_GET['source'] ?? 'photo';

        if ($source === 'pricelist') {
            $stmt = $pdo->prepare("DELETE FROM price_lists WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("DELETE FROM photos WHERE photo_id = ?");
        }
        $stmt->execute([$id]);
        header("Location: admin_dashboard.php?tab=approvals&msg=deleted");
        exit;
    }
    // --- ADD EDITOR'S CHOICE ---
    elseif ($action === 'add_editors_choice') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("UPDATE business SET is_editors_choice = TRUE WHERE shop_id = ?");
        $stmt->execute([$id]);
        header("Location: admin_dashboard.php?tab=editors_choice&msg=added");
        exit;
    }

    // --- REMOVE EDITOR'S CHOICE ---
    elseif ($action === 'remove_editors_choice') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("UPDATE business SET is_editors_choice = FALSE WHERE shop_id = ?");
        $stmt->execute([$id]);
        header("Location: admin_dashboard.php?tab=editors_choice&msg=removed");
        exit;
    }
    // --- APPROVE PHOTO ---
    elseif ($action === 'approve_photo') {
        $id = $_GET['id'];
        $source = $_GET['source'] ?? 'photo';
        
        if ($source === 'pricelist') {
            $stmt = $pdo->prepare("UPDATE price_lists SET is_approved = TRUE WHERE id = ?");
        } elseif ($source === 'business_photo') {
            $stmt = $pdo->prepare("UPDATE business_photos SET is_approved = TRUE WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE photos SET is_approved = TRUE WHERE photo_id = ?");
        }
        $stmt->execute([$id]);
        header("Location: admin_dashboard.php?tab=approvals&msg=approved");
        exit;
    }

    // --- DELETE PHOTO ---
    elseif ($action === 'delete_photo') {
        $id = $_GET['id'];
        $source = $_GET['source'] ?? 'photo';
        
        if ($source === 'pricelist') {
            $stmt = $pdo->prepare("DELETE FROM price_lists WHERE id = ?");
        } elseif ($source === 'business_photo') {
            $stmt = $pdo->prepare("DELETE FROM business_photos WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("DELETE FROM photos WHERE photo_id = ?");
        }
        $stmt->execute([$id]);
        header("Location: admin_dashboard.php?tab=approvals&msg=deleted");
        exit;
    }

    // ==================== FALLBACK ====================
    else {
        die("Invalid action or method.");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
