<?php
// business_login.php
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
            
            // SECURITY CHECK: 
            // If the user has the role 'user', they are NOT allowed in the business portal.
            if ($user['role_type'] === 'user') {
                header("Location: business.html?error=access_denied");
                exit;
            }

            // Login Successful: Set Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role_type'];
            $_SESSION['user_name'] = $user['full_name'];
            
            // === FIX: Redirect to the Dashboard ===
            header("Location: business_dashboard.php");
            exit;

        } else {
            header("Location: business.html?error=invalid_credentials");
            exit;
        }

    } catch (PDOException $e) {
        header("Location: business.html?error=db_error");
        exit;
    }
}
?>