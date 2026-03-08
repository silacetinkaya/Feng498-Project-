<?php
session_start();
require_once "db_connect.php";

header('Content-Type: application/json');

function isTruePg($v) {
    return $v === true || $v === 1 || $v === '1' || $v === 't' || $v === 'true';
}

$businessId = isset($_GET['business_id']) ? (int)$_GET['business_id'] : 0;
$productId  = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$staffId    = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;
$date       = $_GET['date'] ?? '';

if ($businessId <= 0 || $productId <= 0 || $staffId <= 0 || empty($date)) {
    echo json_encode(['ok' => false, 'error' => 'Missing parameters.']);
    exit;
}

/* PRODUCT */
$stmtProduct = $pdo->prepare("
    SELECT *
    FROM products
    WHERE id = ? AND business_id = ?
    LIMIT 1
");
$stmtProduct->execute([$productId, $businessId]);
$product = $stmtProduct->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode(['ok' => false, 'error' => 'Service not found.']);
    exit;
}

$duration = (int)($product['duration_minutes'] ?? 0);
if ($duration <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid service duration.']);
    exit;
}

/* VALID STAFF FOR SERVICE */
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
    echo json_encode(['ok' => false, 'error' => 'Invalid staff for this service.']);
    exit;
}

/* STAFF AVAILABILITY */
try {
    $dayOfWeek = (new DateTime($date))->format('l');
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'Invalid date.']);
    exit;
}

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
    echo json_encode(['ok' => true, 'slots' => []]);
    exit;
}

if (isTruePg($availability['is_closed'] ?? false)) {
    echo json_encode(['ok' => true, 'slots' => []]);
    exit;
}

$startTime = $availability['start_time'] ?? null;
$endTime   = $availability['end_time'] ?? null;

if (!$startTime || !$endTime) {
    echo json_encode(['ok' => true, 'slots' => []]);
    exit;
}

/* EXISTING APPOINTMENTS */
$stmtAppointments = $pdo->prepare("
    SELECT start_time, end_time
    FROM appointments
    WHERE staff_id = ?
      AND appointment_date = ?
      AND status IN ('pending', 'confirmed')
    ORDER BY start_time ASC
");
$stmtAppointments->execute([$staffId, $date]);
$appointments = $stmtAppointments->fetchAll(PDO::FETCH_ASSOC);

/* SLOT GENERATOR: 15-minute grid */
$slots = [];

$workStart = new DateTime($date . ' ' . substr($startTime, 0, 8));
$workEnd   = new DateTime($date . ' ' . substr($endTime, 0, 8));

$current = clone $workStart;

while ($current < $workEnd) {
    $candidateStart = clone $current;
    $candidateEnd = clone $candidateStart;
    $candidateEnd->modify("+{$duration} minutes");

    if ($candidateEnd > $workEnd) {
        break;
    }

    $isAvailable = true;

    foreach ($appointments as $appt) {
        $apptStart = new DateTime($date . ' ' . substr($appt['start_time'], 0, 8));
        $apptEnd   = new DateTime($date . ' ' . substr($appt['end_time'], 0, 8));

        $overlaps = !(
            $candidateEnd <= $apptStart ||
            $candidateStart >= $apptEnd
        );

        if ($overlaps) {
            $isAvailable = false;
            break;
        }
    }

    if ($isAvailable) {
        $slots[] = $candidateStart->format('H:i');
    }

    $current->modify('+15 minutes');
}

echo json_encode([
    'ok' => true,
    'slots' => $slots
]);