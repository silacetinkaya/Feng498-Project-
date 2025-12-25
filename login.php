<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT id, full_name, password, role_type FROM users WHERE email = :email";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {

            // SESSION SET
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role_type'];
            $_SESSION['full_name'] = $user['full_name'];

            // ROLE REDIRECT
            switch ($user['role_type']) {

                case 'admin':
                    header("Location: admin_panel.php");
                    exit;

                case 'business':
                    header("Location: business_panel.php");
                    exit;

                case 'user':
                    header("Location: user_panel.php");
                    exit;

                default:
                    header("Location: index.html?error=no_role");
                    exit;
            }

        } else {
            header("Location: index.html?error=invalid_credentials");
            exit;
        }

    } catch (PDOException $e) {
        header("Location: index.html?error=db_error");
        exit;
    }
}
?>
