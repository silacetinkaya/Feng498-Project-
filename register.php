<?php
// register.php
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $address = trim($_POST['address']);
    
    // HARDCODED ROLE: Everyone registering here is a 'user'
    $role_type = 'user'; 

    // Check for empty fields
    if (empty($full_name) || empty($email) || empty($password)) {
        header("Location: index.html?error=empty_fields");
        exit;
    }

    // Check duplicate email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    
    if ($stmt->rowCount() > 0) {
        header("Location: index.html?error=email_exists");
        exit;
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // UPDATED SQL: Explicitly adding 'registration_date' and using 'NOW()'
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

        header("Location: index.html?success=registered");

    } catch (PDOException $e) {
        // In production, log error to file, don't show to user
        header("Location: index.html?error=db_error");
    }
}
?>