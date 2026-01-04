<?php
// business_login.php
session_start();
require_once 'db_connect.php';

// ---------------------------------------------------------
// LOGIN LOGIC (BUSINESS)
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT id, full_name, password, role_type FROM users WHERE email = :email";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            // ❌ Block normal users from business portal
            if ($user['role_type'] === 'user') {
                header("Location: business_login.php?error=access_denied");
                exit;
            }

            // ✅ Business/Admin login allowed
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role_type'];
            $_SESSION['user_name'] = $user['full_name'];

            // Redirect to Business Dashboard
            header("Location: business_dashboard.php");
            exit;

        } else {
            header("Location: business_login.php?error=invalid_credentials");
            exit;
        }

    } catch (PDOException $e) {
        header("Location: business_login.php?error=db_error");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Portal Login</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%) !important;
        }
        .btn-business {
            background: linear-gradient(to right, #2c3e50, #4ca1af);
        }
    </style>
</head>
<body>

    <div class="site-header">BUSINESS PORTAL</div>

    <div class="container">

        <!-- Message Box -->
        <div id="message-box"></div>

        <!-- Business Login Form -->
        <div id="business-login-form">
            <form action="business_login.php" method="POST">
                <h2>Owner Login</h2>
                <p style="text-align:center;color:#666;font-size:0.9rem;margin-bottom:20px;">
                    Authorized personnel only.
                </p>

                <input type="email" name="email" placeholder="Business Email" required />
                <input type="password" name="password" placeholder="Password" required />

                <button type="submit" class="btn-business">Access Dashboard</button>

                <div class="toggle-link" style="margin-top:25px;">
                    <a href="index.html" style="text-decoration:none;color:#666;">
                        &larr; Back to User Login
                    </a>
                </div>
            </form>
        </div>

    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const error = urlParams.get('error');
        const msgBox = document.getElementById('message-box');

        if (error) {
            msgBox.style.display = 'block';
            msgBox.className = 'msg-error';

            if (error === 'access_denied')
                msgBox.textContent = "Access Denied: You do not have business privileges.";
            else if (error === 'invalid_credentials')
                msgBox.textContent = "Invalid email or password.";
            else
                msgBox.textContent = "Login failed. Please try again.";
        }
    </script>

</body>
</html>
