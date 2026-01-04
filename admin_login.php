<?php
// admin_login.php
ob_start(); // Fixes "Headers already sent" errors
session_start();
require_once 'db_connect.php';

// Handle Login Logic
$error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT id, full_name, password, role_type FROM users WHERE email = :email";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            
            // Normalize role
            $role = strtolower(trim($user['role_type']));

            // SECURITY CHECK
            if ($role !== 'admin') {
                $error = 'access_denied';
            } else {
                // Success: Set Session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = 'admin';
                $_SESSION['admin_name'] = $user['full_name'];
                
                // Redirect
                if (!headers_sent()) {
                    header("Location: admin_dashboard.php");
                    exit;
                } else {
                    echo '<script>window.location.href="admin_dashboard.php";</script>';
                    exit;
                }
            }

        } else {
            $error = 'invalid_credentials';
        }

    } catch (PDOException $e) {
        $error = 'db_error';
    }
}
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <style>
        /* General Reset */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        /* Admin Theme: Dark and Serious */
        body {
            background: #1a1a1a; 
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            flex-direction: column;
        }

        .site-header {
            margin-bottom: 20px;
            color: #666;
            font-size: 1.5rem;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 14px 28px rgba(0,0,0,0.25), 0 10px 10px rgba(0,0,0,0.22);
            position: relative;
            overflow: hidden;
            width: 400px;
            max-width: 90%;
            padding: 40px;
            border-top: 5px solid #d32f2f; /* Red accent */
        }

        h2 {
            text-align: center;
            color: #d32f2f;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }

        p.sub-text {
            text-align: center; 
            color: #999; 
            font-size: 0.8rem; 
            margin-bottom: 30px;
        }

        input {
            background-color: #eee;
            border: none;
            padding: 12px 15px;
            margin: 10px 0;
            width: 100%;
            border-radius: 5px;
            outline: none;
        }

        button.btn-admin {
            border-radius: 20px;
            border: none;
            background: linear-gradient(to right, #d32f2f, #b71c1c);
            color: #FFFFFF;
            font-size: 12px;
            font-weight: bold;
            padding: 12px 45px;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: transform 80ms ease-in, box-shadow 0.2s;
            cursor: pointer;
            margin-top: 20px;
            width: 100%;
        }

        button.btn-admin:active { transform: scale(0.95); }
        button.btn-admin:hover { box-shadow: 0 5px 15px rgba(211, 47, 47, 0.4); }
        button:focus { outline: none; }

        .toggle-link {
            margin-top: 25px;
            text-align: center;
            font-size: 14px;
        }

        .toggle-link a {
            color: #666;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .toggle-link a:hover { color: #d32f2f; }

        /* Messages */
        #message-box {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            text-align: center;
            font-size: 14px;
            display: none; /* Hidden by default */
        }
        .msg-error { background-color: #ffdddd; color: #a80000; border: 1px solid #ffcccc; }
    </style>
</head>
<body>

    <div class="site-header">ADMINISTRATION</div>

    <div class="container">
        
        <!-- Message Box -->
        <div id="message-box"></div>

        <div id="admin-login-form">
            <form method="POST">
                <h2>System Access</h2>
                <p class="sub-text">
                    Restricted Area. All actions are logged.
                </p>

                <input type="email" name="email" placeholder="Admin Email" required />
                <input type="password" name="password" placeholder="Password" required />
                
                <button type="submit" class="btn-admin">Authenticate</button>
                
                <div class="toggle-link">
                    <a href="index.html">
                        &larr; Return to Site
                    </a>
                </div>
            </form>
        </div>

    </div>

    <script>
        // Check for PHP errors passed via variable or URL
        const phpError = "<?php echo $error; ?>";
        const urlParams = new URLSearchParams(window.location.search);
        const urlError = urlParams.get('error');
        
        const finalError = phpError || urlError;
        const msgBox = document.getElementById('message-box');

        if (finalError) {
            msgBox.style.display = 'block';
            msgBox.className = 'msg-error';
            
            if (finalError === 'access_denied') msgBox.textContent = "Access Denied: You are not an administrator.";
            else if (finalError === 'invalid_credentials') msgBox.textContent = "Invalid credentials.";
            else if (finalError === 'db_error') msgBox.textContent = "Database connection error.";
            else msgBox.textContent = "Login failed.";
        }
    </script>
</body>
</html>