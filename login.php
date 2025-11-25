<?php
// login.php
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
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role_type'];
            
            // Redirect to a simple success page or dashboard
            echo "<h1 style='color:green; text-align:center; font-family:sans-serif; margin-top:50px;'>Login Successful!</h1>";
            echo "<p style='text-align:center; font-family:sans-serif;'>Welcome, " . htmlspecialchars($user['full_name']) . "</p>";
        } else {
            header("Location: index.html?error=invalid_credentials");
        }

    } catch (PDOException $e) {
        header("Location: index.html?error=db_error");
    }
}
?>