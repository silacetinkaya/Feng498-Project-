<?php
// business_login.php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Select role_type as well to verify permissions
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

            // Login Successful for Business Owner/Manager
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role_type'];
            
            // Redirect to a Business Dashboard (or echo success for now)
            echo "<body style='background-color:#f4f4f4; font-family:sans-serif; text-align:center; padding-top:50px;'>";
            echo "<h1 style='color:#2c3e50;'>Business Dashboard</h1>";
            echo "<div style='background:white; max-width:500px; margin:0 auto; padding:30px; border-radius:10px; box-shadow:0 5px 15px rgba(0,0,0,0.1);'>";
            echo "<p>Welcome, <strong>" . htmlspecialchars($user['full_name']) . "</strong></p>";
            echo "<p>Status: <span style='color:green; font-weight:bold;'>Authorized Business Owner</span></p>";
            echo "</div>";
            echo "</body>";

        } else {
            header("Location: business.html?error=invalid_credentials");
        }

    } catch (PDOException $e) {
        header("Location: business.html?error=db_error");
    }
}
?>