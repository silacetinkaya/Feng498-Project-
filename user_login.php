<?php
// user_login.php
session_start();
require_once 'db_connect.php';

// Determine the action (login, register, or logout)
$action = $_GET['action'] ?? 'login';

// ---------------------------------------------------------
// LOGOUT LOGIC
// ---------------------------------------------------------
if ($action === 'logout') {
    session_unset();
    session_destroy();
    header("Location: user_login.php");
    exit;
}

// ---------------------------------------------------------
// REGISTER LOGIC
// ---------------------------------------------------------
if ($action === 'register' && $_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $address = trim($_POST['address']);
    $role_type = 'user';

    if (empty($full_name) || empty($email) || empty($password)) {
        header("Location: user_login.php?error=empty_fields");
        exit;
    }

    // Check duplicate email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);

    if ($stmt->rowCount() > 0) {
        header("Location: user_login.php?error=email_exists");
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (full_name, email, password, address, role_type, registration_date) 
            VALUES (:full_name, :email, :password, :address, :role_type, NOW())";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $full_name,
            ':email' => $email,
            ':password' => $hashed_password,
            ':address' => $address,
            ':role_type' => $role_type
        ]);

        // Auto-login after registration (Optional, or just redirect with success msg)
        // For now, let's redirect to login view with success message
        header("Location: user_login.php?success=registered");
        exit;
    } catch (PDOException $e) {
        header("Location: user_login.php?error=db_error");
        exit;
    }
}

// ---------------------------------------------------------
// LOGIN LOGIC
// ---------------------------------------------------------
if ($action === 'login' && $_SERVER["REQUEST_METHOD"] == "POST") {

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

            // Redirect to User Panel
            header("Location: user_panel.php");
            exit;
        } else {
            header("Location: user_login.php?error=invalid_credentials");
            exit;
        }
    } catch (PDOException $e) {
        header("Location: user_login.php?error=db_error");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Embedding necessary CSS just in case, reusing style.css generally */
        .hidden {
            display: none;
        }

        .valid {
            color: green;
        }

        .invalid {
            color: red;
        }
    </style>
</head>

<body>

    <div class="site-header">PRICELY</div>

    <div class="container">

        <div id="message-box"></div>

        <!-- Login Form -->
        <div id="login-form">
            <form action="user_login.php?action=login" method="POST">
                <h2>Welcome Back</h2>
                <input type="email" name="email" placeholder="Email Address" required />
                <input type="password" name="password" placeholder="Password" required />
                <button type="submit">Sign In</button>

                <div class="toggle-link">
                    New here? <span id="go-to-register" style="cursor:pointer; color:blue; text-decoration:underline;">Create an Account</span>
                </div>
            </form>
        </div>

        <!-- Register Form -->
        <div id="register-form" class="hidden">
            <form action="user_login.php?action=register" method="POST">
                <h2>Create Account</h2>

                <input type="text" name="full_name" placeholder="Full Name" required />
                <input type="email" name="email" placeholder="Email Address" required />
                <input type="text" name="address" placeholder="Home Address" />
                <input type="password" id="new_password" name="password" placeholder="Create Password" required />
                <input type="password" id="confirm_password" placeholder="Confirm Password" required />

                <ul id="passwordRules" style="font-size:14px; margin-bottom:15px;">
                    <li id="rule-length" class="invalid">❌ At least 8 characters</li>
                    <li id="rule-uppercase" class="invalid">❌ At least 1 uppercase letter</li>
                    <li id="rule-number" class="invalid">❌ At least 1 number</li>
                    <li id="rule-match" class="invalid">❌ Passwords match</li>
                </ul>

                <button type="submit" id="updatePasswordBtn" disabled>Sign Up</button>


                <div class="toggle-link">
                    Already have an account? <span id="go-to-login" style="cursor:pointer; color:blue; text-decoration:underline;">Sign In</span>
                </div>
            </form>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const loginForm = document.getElementById('login-form');
            const registerForm = document.getElementById('register-form');
            const showRegisterBtn = document.getElementById('go-to-register');
            const showLoginBtn = document.getElementById('go-to-login');
            const messageBox = document.getElementById('message-box');

            // Toggle Forms
            showRegisterBtn.addEventListener('click', () => {
                loginForm.classList.add('hidden');
                registerForm.classList.remove('hidden');
                messageBox.style.display = 'none'; // hide messages on toggle
            });

            showLoginBtn.addEventListener('click', () => {
                registerForm.classList.add('hidden');
                loginForm.classList.remove('hidden');
                messageBox.style.display = 'none';
            });

            // Check URL Parameters for Errors/Success
            const urlParams = new URLSearchParams(window.location.search);
            const error = urlParams.get('error');
            const success = urlParams.get('success');

            if (error) {
                messageBox.style.display = 'block';
                messageBox.className = 'msg-error'; // Ensure style.css defines .msg-error
                messageBox.style.backgroundColor = '#ffdddd';
                messageBox.style.color = 'red';
                messageBox.style.padding = '10px';
                messageBox.style.marginBottom = '10px';

                if (error === 'email_exists') messageBox.textContent = "That email is already registered.";
                else if (error === 'invalid_credentials') messageBox.textContent = "Invalid email or password.";
                else if (error === 'empty_fields') messageBox.textContent = "Please fill in all fields.";
                else messageBox.textContent = "An error occurred. Please try again.";
            }

            if (success === 'registered') {
                messageBox.style.display = 'block';
                messageBox.className = 'msg-success'; // Ensure style.css defines .msg-success
                messageBox.style.backgroundColor = '#ddffdd';
                messageBox.style.color = 'green';
                messageBox.style.padding = '10px';
                messageBox.style.marginBottom = '10px';
                messageBox.textContent = "Account created! Please log in.";
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const btn = document.getElementById('updatePasswordBtn');

            if (!newPassword || !confirmPassword || !btn) return;

            const rules = {
                length: document.getElementById('rule-length'),
                uppercase: document.getElementById('rule-uppercase'),
                number: document.getElementById('rule-number'),
                match: document.getElementById('rule-match')
            };

            function updateRule(el, valid) {
                el.textContent = el.textContent.replace(/^❌|^✅/, valid ? '✅' : '❌');
                el.className = valid ? 'valid' : 'invalid';
            }

            function validatePassword() {
                const value = newPassword.value;

                const hasLength = value.length >= 8;
                const hasUppercase = /[A-Z]/.test(value);
                const hasNumber = /\d/.test(value);
                const passwordsMatch = value.length > 0 && value === confirmPassword.value;

                updateRule(rules.length, hasLength);
                updateRule(rules.uppercase, hasUppercase);
                updateRule(rules.number, hasNumber);
                updateRule(rules.match, passwordsMatch);

                btn.disabled = !(hasLength && hasUppercase && hasNumber && passwordsMatch);
            }

            newPassword.addEventListener('input', validatePassword);
            confirmPassword.addEventListener('input', validatePassword);
        });
    </script>

</body>

</html>