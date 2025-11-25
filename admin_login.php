<?php
// admin_login.php
ob_start(); // 1. Fixes "Headers already sent" errors
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT id, full_name, password, role_type FROM users WHERE email = :email";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            
            // 2. Normalize the role (remove spaces, convert to lowercase)
            // This ensures 'Admin ', 'ADMIN', or 'admin' all work.
            $role = strtolower(trim($user['role_type']));

            // SECURITY CHECK
            if ($role !== 'admin') {
                // User is valid, but NOT an admin
                header("Location: admin.html?error=access_denied");
                exit;
            }

            // Success: Set Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = 'admin';
            $_SESSION['admin_name'] = $user['full_name'];
            
            // 3. Javascript Backup Redirect 
            // If PHP header fails, this Javascript will force the move.
            if (!headers_sent()) {
                header("Location: admin_dashboard.php");
                exit;
            } else {
                echo '<script>window.location.href="admin_dashboard.php";</script>';
                exit;
            }

        } else {
            header("Location: admin.html?error=invalid_credentials");
            exit;
        }

    } catch (PDOException $e) {
        header("Location: admin.html?error=db_error");
        exit;
    }
}
ob_end_flush();
?>