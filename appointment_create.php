<?php
session_start();
require_once "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    die("Please log in first.");
}

function isTruePg($v) {
    return $v === true || $v === 1 || $v === '1' || $v === 't' || $v === 'true';
}

$userId = (int)$_SESSION['user_id'];
$businessId = isset($_POST['business_id']) ? (int)$_POST['business_id'] : 0;
$productId  = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$staffId    = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : 0;
$appointmentDate = $_POST['appointment_date'] ?? '';
$appointmentTime = $_POST['appointment_time'] ?? '';

if (
    $businessId <= 0 ||
    $productId <= 0 ||
    $staffId <= 0 ||
    empty($appointmentDate) ||
    empty($appointmentTime)
) {
    die("Missing required fields.");
}

/* PRODUCT */
$stmt = $pdo->prepare("
    SELECT *
    FROM products
    WHERE id = ? AND business_id = ?
    LIMIT 1
");
$stmt->execute([$productId, $businessId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Service not found.");
}

if (!isTruePg($product['bookable'] ?? false)) {
    die("This service is not bookable.");
}

$duration = (int)($product['duration_minutes'] ?? 0);
if ($duration <= 0) {
    die("Invalid service duration.");
}

/* STAFF VALIDATION */
$stmtStaff = $pdo->prepare("
    SELECT s.id
    FROM staff s
    INNER JOIN staff_services ss ON ss.staff_id = s.id
    WHERE s.id = ?
      AND s.business_id = ?
      AND ss.product_id = ?
      AND s.is_active = TRUE
    LIMIT 1
");
$stmtStaff->execute([$staffId, $businessId, $productId]);
if (!$stmtStaff->fetchColumn()) {
    die("Selected staff is invalid for this service.");
}

$listedPrice = !empty($product['discounted_price'])
    ? (float)$product['discounted_price']
    : (float)($product['product_prices'] ?? 0);

$agreedPrice = $listedPrice;

$depositRequired = isTruePg($product['deposit_required'] ?? false);
$depositPercent  = (int)($product['deposit_percent'] ?? 0);
$depositAmount   = 0;

if ($depositRequired && $depositPercent > 0) {
    $depositAmount = round($agreedPrice * ($depositPercent / 100), 2);
}

/* DATE/TIME */
try {
    $start = new DateTime($appointmentDate . ' ' . $appointmentTime . ':00');
} catch (Exception $e) {
    die("Invalid date/time.");
}

$end = clone $start;
$end->modify("+{$duration} minutes");

$startTime = $start->format('H:i:s');
$endTime   = $end->format('H:i:s');

/* AVAILABILITY CHECK */
$dayOfWeek = $start->format('l');

$stmtAvail = $pdo->prepare("
    SELECT *
    FROM staff_availability
    WHERE staff_id = ?
      AND day_of_week = ?
    LIMIT 1
");
$stmtAvail->execute([$staffId, $dayOfWeek]);
$availability = $stmtAvail->fetch(PDO::FETCH_ASSOC);

if (!$availability) {
    die("Selected staff has no working hours for this day.");
}

if (isTruePg($availability['is_closed'] ?? false)) {
    die("Selected staff is not working on this day.");
}

$workStart = $availability['start_time'] ?? null;
$workEnd   = $availability['end_time'] ?? null;

if (!$workStart || !$workEnd) {
    die("Staff working hours are incomplete.");
}

if ($startTime < $workStart || $endTime > $workEnd) {
    die("Selected time is outside staff working hours.");
}

/* OVERLAP CHECK */
$stmtOverlap = $pdo->prepare("
    SELECT id
    FROM appointments
    WHERE staff_id = ?
      AND appointment_date = ?
      AND status IN ('pending', 'confirmed')
      AND NOT (
            end_time <= ?
            OR start_time >= ?
      )
    LIMIT 1
");
$stmtOverlap->execute([
    $staffId,
    $appointmentDate,
    $startTime,
    $endTime
]);

if ($stmtOverlap->fetchColumn()) {
    die("This time slot is no longer available.");
}

/* INSERT */
$ins = $pdo->prepare("
    INSERT INTO appointments (
        business_id,
        user_id,
        product_id,
        staff_id,
        appointment_date,
        start_time,
        end_time,
        listed_price,
        agreed_price,
        deposit_amount,
        status,
        booking_type,
        created_at,
        updated_at
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'direct', NOW(), NOW())
");

$ins->execute([
    $businessId,
    $userId,
    $productId,
    $staffId,
    $appointmentDate,
    $startTime,
    $endTime,
    $listedPrice,
    $agreedPrice,
    $depositAmount
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Created</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f5fb;
            padding: 40px;
            margin: 0;
        }
        .card {
            max-width: 620px;
            margin: 0 auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 14px;
            padding: 30px;
            text-align: center;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            background: #3498db;
            color: white;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>Appointment created successfully</h1>
    <p>Your booking has been saved.</p>
    <a href="user_panel.php">Go back</a>
</div>
</body>
</html>