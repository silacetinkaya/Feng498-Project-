<?php
// generate_data.php
// RUN THIS ONCE TO POPULATE DATABASE
require_once 'db_connect.php';

echo "<h1>Generating Sample Data...</h1>";

try {
    $pdo->beginTransaction();

    // 1. GENERATE 50 USERS
    echo "<p>Creating 50 Users...</p>";
    $names = ['John', 'Jane', 'Mike', 'Emily', 'Chris', 'Sarah', 'David', 'Laura', 'Robert', 'Emma'];
    $surnames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
    
    $userIds = [];
    
    for ($i = 0; $i < 50; $i++) {
        $fname = $names[array_rand($names)];
        $lname = $surnames[array_rand($surnames)];
        $fullName = "$fname $lname";
        $email = strtolower($fname . "." . $lname . rand(100,999) . "@example.com");
        $pass = password_hash('password123', PASSWORD_DEFAULT);
        $role = ($i < 5) ? 'admin' : 'user'; // First 5 are admins

        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role_type, registration_date) VALUES (?, ?, ?, ?, NOW()) RETURNING id");
        $stmt->execute([$fullName, $email, $pass, $role]);
        $userIds[] = $stmt->fetchColumn();
    }

    // 2. ENSURE BUSINESSES EXIST
    echo "<p>Checking Businesses...</p>";
    $bizStmt = $pdo->query("SELECT shop_id FROM business");
    $bizIds = $bizStmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($bizIds) < 5) {
        echo "<p>Creating 5 Dummy Businesses...</p>";
        $bizNames = ['Tech World', 'Tasty Bites', 'Fashion Hub', 'Home Depot', 'Auto Fix'];
        
        foreach ($bizNames as $bName) {
            $owner = $userIds[array_rand($userIds)]; // Random owner
            $stmt = $pdo->prepare("INSERT INTO business (name, owner_id, address, tel_no) VALUES (?, ?, '123 Fake St', '555-0199') RETURNING shop_id");
            $stmt->execute([$bName, $owner]);
            $bizIds[] = $stmt->fetchColumn();
        }
    }

    // 3. GENERATE 50 PRODUCTS
    echo "<p>Creating 50 Products...</p>";
    $prodAdjectives = ['Super', 'Mega', 'Ultra', 'Eco', 'Smart'];
    $prodNouns = ['Phone', 'Burger', 'Shirt', 'Table', 'Wrench', 'Laptop', 'Coffee', 'Jeans'];
    $cats = ['Electronics', 'Food & Beverage', 'Clothing', 'Home & Garden', 'Services'];

    for ($i = 0; $i < 50; $i++) {
        $pName = $prodAdjectives[array_rand($prodAdjectives)] . " " . $prodNouns[array_rand($prodNouns)];
        $cat = $cats[array_rand($cats)];
        $price = rand(10, 500) + 0.99;
        $bizId = $bizIds[array_rand($bizIds)];
        $desc = "This is a great sample product.";

        $stmt = $pdo->prepare("INSERT INTO products (business_id, name, categories, description, product_prices, available) VALUES (?, ?, ?, ?, ?, true)");
        $stmt->execute([$bizId, $pName, $cat, $desc, $price]);
    }

    // 4. GENERATE 50 REVIEWS
    echo "<p>Creating 50 Reviews...</p>";
    $comments = [
        "Amazing service!", "Terrible experience.", "It was okay.", "Highly recommended.", 
        "Will come back again.", "Not worth the price.", "Five stars!", "Quick shipping."
    ];

    for ($i = 0; $i < 50; $i++) {
        $uId = $userIds[array_rand($userIds)];
        $bId = $bizIds[array_rand($bizIds)];
        $rank = rand(1, 5);
        $comment = $comments[array_rand($comments)];

        $stmt = $pdo->prepare("INSERT INTO reviews (user_id, business_id, rank, comments, time) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$uId, $bId, $rank, $comment]);
    }

    $pdo->commit();
    echo "<h2 style='color:green;'>SUCCESS: Data Generation Complete!</h2>";
    echo "<a href='admin_dashboard.php'>Go to Dashboard</a>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2 style='color:red;'>ERROR: " . $e->getMessage() . "</h2>";
}
?>