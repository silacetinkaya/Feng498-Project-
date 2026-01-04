<?php
require "db_connect.php";

$stmt = $pdo->query("
    SELECT 
        b.shop_id,
        b.name,
        b.category,
        b.latitude,
        b.longitude,
        COALESCE(AVG(r.rank), 0) AS rating
    FROM business b
    LEFT JOIN reviews r ON r.business_id = b.shop_id
    WHERE b.latitude IS NOT NULL AND b.longitude IS NOT NULL
    GROUP BY b.shop_id
");

header("Content-Type: application/json");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
