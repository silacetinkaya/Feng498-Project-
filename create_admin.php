<?php
// create_admin.php
// Run this file ONE TIME to create your initial admin account.
require_once 'db_connect.php';

// --- CONFIGURATION: EDIT THESE VALUES ---
$new_admin_email = "admin@example.com";
$new_admin_password = "admin123";
$new_admin_name = "Super Admin";
$new_admin_address = "Headquarters";
// ----------------------------------------

try {
    // 1. Check if this email already exists to prevent duplicates
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $checkStmt->execute(['email' => $new_admin_email]);

    if ($checkStmt->rowCount() > 0) {
        die("<h3 style='color:red'>Error: A user with email '$new_admin_email' already exists.</h3>");
    }

    // 2. Hash the password
    $hashed_password = password_hash($new_admin_password, PASSWORD_DEFAULT);

    // 3. Insert the Admin User
    // We explicitly set role_type to 'admin'
    $sql = "INSERT INTO users (full_name, email, password, address, role_type, registration_date) 
            VALUES (:full_name, :email, :password, :address, 'admin', NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':full_name' => $new_admin_name,
        ':email' => $new_admin_email,
        ':password' => $hashed_password,
        ':address' => $new_admin_address
    ]);

    echo "<h1 style='color:green'>Success!</h1>";
    echo "<p>Admin user <strong>$new_admin_email</strong> has been created.</p>";
    echo "<p>You can now <a href='admin.html'>Login to Admin Panel</a>.</p>";
    echo "<p style='color:red'><strong>IMPORTANT:</strong> Delete this file (create_admin.php) from your server now.</p>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>