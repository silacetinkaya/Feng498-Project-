<?php
session_start();
require_once "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    die("Please log in first.");
}

$businessId = isset($_GET['business_id']) ? (int)$_GET['business_id'] : 0;
$productId  = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($businessId <= 0 || $productId <= 0) {
    die("Invalid request.");
}

function isTruePg($v) {
    return $v === true || $v === 1 || $v === '1' || $v === 't' || $v === 'true';
}

/* BUSINESS */
$stmtB = $pdo->prepare("
    SELECT *
    FROM business
    WHERE shop_id = ?
    LIMIT 1
");
$stmtB->execute([$businessId]);
$business = $stmtB->fetch(PDO::FETCH_ASSOC);

if (!$business) {
    die("Business not found.");
}

/* PRODUCT / SERVICE */
$stmtP = $pdo->prepare("
    SELECT *
    FROM products
    WHERE id = ? AND business_id = ?
    LIMIT 1
");
$stmtP->execute([$productId, $businessId]);
$product = $stmtP->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Service not found.");
}

$isBookable = isTruePg($product['bookable'] ?? false);
if (!$isBookable) {
    die("This item is not bookable.");
}

$duration = (int)($product['duration_minutes'] ?? 0);
$depositRequired = isTruePg($product['deposit_required'] ?? false);
$depositPercent = (int)($product['deposit_percent'] ?? 0);

$price = !empty($product['discounted_price'])
    ? (float)$product['discounted_price']
    : (float)($product['product_prices'] ?? 0);

$depositAmount = 0;
if ($depositRequired && $depositPercent > 0) {
    $depositAmount = round($price * ($depositPercent / 100), 2);
}

/* STAFF OPTIONS */
$stmtStaff = $pdo->prepare("
    SELECT 
        s.id,
        s.full_name,
        s.role
    FROM staff s
    INNER JOIN staff_services ss ON ss.staff_id = s.id
    WHERE s.business_id = ?
      AND ss.product_id = ?
      AND s.is_active = TRUE
    ORDER BY s.full_name ASC
");
$stmtStaff->execute([$businessId, $productId]);
$staffOptions = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Appointment</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f5fb;
            margin: 0;
            padding: 40px;
        }
        .card {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 18px;
            padding: 30px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.05);
        }
        h1 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #222;
        }
        .info {
            margin-bottom: 24px;
            padding: 18px;
            background: #fafafa;
            border: 1px solid #eee;
            border-radius: 12px;
        }
        .info p {
            margin: 8px 0;
            font-size: 15px;
        }
        .deposit {
            color: #e67e22;
            font-weight: bold;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        .label {
            font-weight: bold;
            display: block;
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 10px;
            box-sizing: border-box;
            font-size: 15px;
            background: #fff;
        }
        .warn {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 18px;
        }
        .slot-section {
            margin-top: 24px;
        }
        .slot-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .slot-header h3 {
            margin: 0;
            font-size: 18px;
        }
        .slot-count {
            font-size: 13px;
            color: #666;
        }
        .slots {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 22px;
        }
        .slot {
            padding: 10px 16px;
            border-radius: 999px;
            border: 1px solid #2ecc71;
            background: #eafaf1;
            color: #27ae60;
            cursor: pointer;
            font-weight: 700;
            transition: 0.2s ease;
        }
        .slot:hover {
            background: #2ecc71;
            color: #fff;
        }
        .slot.selected {
            background: #3498db;
            border-color: #3498db;
            color: #fff;
        }
        .summary {
            margin-top: 18px;
            padding: 14px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 12px;
        }
        .summary strong {
            color: #111827;
        }
        .btn {
            background: #2ecc71;
            color: #fff;
            border: none;
            padding: 13px 20px;
            border-radius: 12px;
            font-weight: bold;
            cursor: pointer;
            font-size: 15px;
            margin-top: 16px;
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .empty-slots {
            color: #777;
            font-size: 14px;
            padding: 10px 0;
        }
        .loading {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
        @media (max-width: 700px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="card">
    <h1>Book Appointment</h1>

    <div class="info">
        <p><strong>Business:</strong> <?= htmlspecialchars($business['name']) ?></p>
        <p><strong>Service:</strong> <?= htmlspecialchars($product['name']) ?></p>
        <p><strong>Price:</strong> <?= number_format($price, 2) ?> TL</p>
        <p><strong>Duration:</strong> <?= $duration ?> minutes</p>

        <?php if ($depositRequired): ?>
            <p class="deposit">Deposit required: <?= $depositPercent ?>% (<?= number_format($depositAmount, 2) ?> TL)</p>
        <?php else: ?>
            <p>No deposit required.</p>
        <?php endif; ?>
    </div>

    <?php if (empty($staffOptions)): ?>
        <div class="warn">No active staff is assigned to this service yet.</div>
    <?php else: ?>
        <form action="appointment_create.php" method="POST" id="bookingForm">
            <input type="hidden" name="business_id" value="<?= (int)$businessId ?>">
            <input type="hidden" name="product_id" value="<?= (int)$productId ?>">
            <input type="hidden" name="appointment_time" id="selected_time">

            <div class="grid">
                <div>
                    <label class="label">Choose Staff</label>
                    <select name="staff_id" id="staff_id" class="form-control" required>
                        <option value="">Select staff...</option>
                        <?php foreach ($staffOptions as $st): ?>
                            <option value="<?= (int)$st['id'] ?>">
                                <?= htmlspecialchars($st['full_name']) ?>
                                <?php if (!empty($st['role'])): ?>
                                    - <?= htmlspecialchars($st['role']) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="label">Appointment Date</label>
                    <input type="date" name="appointment_date" id="appointment_date" class="form-control" required>
                </div>
            </div>

            <div id="loadingText" class="loading" style="display:none;">Loading available slots...</div>

            <div id="slotsContainer" style="display:none;">
                <div class="slot-section">
                    <div class="slot-header">
                        <h3>Morning</h3>
                        <span class="slot-count" id="countMorning">0 available</span>
                    </div>
                    <div class="slots" id="slotsMorning"></div>
                </div>

                <div class="slot-section">
                    <div class="slot-header">
                        <h3>Afternoon</h3>
                        <span class="slot-count" id="countAfternoon">0 available</span>
                    </div>
                    <div class="slots" id="slotsAfternoon"></div>
                </div>

                <div class="slot-section">
                    <div class="slot-header">
                        <h3>Evening</h3>
                        <span class="slot-count" id="countEvening">0 available</span>
                    </div>
                    <div class="slots" id="slotsEvening"></div>
                </div>
            </div>

            <div class="summary" id="bookingSummary" style="display:none;">
                <div><strong>Selected staff:</strong> <span id="summaryStaff">-</span></div>
                <div><strong>Date:</strong> <span id="summaryDate">-</span></div>
                <div><strong>Time:</strong> <span id="summaryTime">-</span></div>
                <div><strong>Duration:</strong> <?= $duration ?> min</div>
            </div>

            <button type="submit" class="btn" id="confirmBtn" disabled>Confirm Appointment</button>
        </form>
    <?php endif; ?>
</div>

<script>
const staffSelect = document.getElementById("staff_id");
const dateInput = document.getElementById("appointment_date");
const selectedTimeInput = document.getElementById("selected_time");
const loadingText = document.getElementById("loadingText");
const slotsContainer = document.getElementById("slotsContainer");
const confirmBtn = document.getElementById("confirmBtn");

const slotsMorning = document.getElementById("slotsMorning");
const slotsAfternoon = document.getElementById("slotsAfternoon");
const slotsEvening = document.getElementById("slotsEvening");

const countMorning = document.getElementById("countMorning");
const countAfternoon = document.getElementById("countAfternoon");
const countEvening = document.getElementById("countEvening");

const bookingSummary = document.getElementById("bookingSummary");
const summaryStaff = document.getElementById("summaryStaff");
const summaryDate = document.getElementById("summaryDate");
const summaryTime = document.getElementById("summaryTime");

function clearSlots() {
    slotsMorning.innerHTML = "";
    slotsAfternoon.innerHTML = "";
    slotsEvening.innerHTML = "";
    countMorning.textContent = "0 available";
    countAfternoon.textContent = "0 available";
    countEvening.textContent = "0 available";
    selectedTimeInput.value = "";
    confirmBtn.disabled = true;
    bookingSummary.style.display = "none";
}

function createEmptyMessage(container) {
    const div = document.createElement("div");
    div.className = "empty-slots";
    div.textContent = "No available slots";
    container.appendChild(div);
}

function selectSlot(btn) {
    document.querySelectorAll(".slot").forEach(b => b.classList.remove("selected"));
    btn.classList.add("selected");
    selectedTimeInput.value = btn.dataset.time;

    const selectedOption = staffSelect.options[staffSelect.selectedIndex];
    summaryStaff.textContent = selectedOption ? selectedOption.text : "-";
    summaryDate.textContent = dateInput.value || "-";
    summaryTime.textContent = btn.dataset.time;
    bookingSummary.style.display = "block";

    confirmBtn.disabled = false;
}

function renderSlots(slots) {
    clearSlots();

    let morningCount = 0;
    let afternoonCount = 0;
    let eveningCount = 0;

    slots.forEach(time => {
        const hour = parseInt(time.split(":")[0], 10);

        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "slot";
        btn.innerText = time;
        btn.dataset.time = time;
        btn.onclick = function () { selectSlot(this); };

        if (hour < 12) {
            slotsMorning.appendChild(btn);
            morningCount++;
        } else if (hour < 17) {
            slotsAfternoon.appendChild(btn);
            afternoonCount++;
        } else {
            slotsEvening.appendChild(btn);
            eveningCount++;
        }
    });

    countMorning.textContent = `${morningCount} available`;
    countAfternoon.textContent = `${afternoonCount} available`;
    countEvening.textContent = `${eveningCount} available`;

    if (morningCount === 0) createEmptyMessage(slotsMorning);
    if (afternoonCount === 0) createEmptyMessage(slotsAfternoon);
    if (eveningCount === 0) createEmptyMessage(slotsEvening);

    slotsContainer.style.display = "block";
}

async function loadAvailableSlots() {
    const staffId = staffSelect.value;
    const date = dateInput.value;

    clearSlots();
    slotsContainer.style.display = "none";

    if (!staffId || !date) {
        return;
    }

    loadingText.style.display = "block";

    try {
        const params = new URLSearchParams({
            business_id: "<?= (int)$businessId ?>",
            product_id: "<?= (int)$productId ?>",
            staff_id: staffId,
            date: date
        });

        const res = await fetch("get_available_slots.php?" + params.toString());
        const data = await res.json();

        loadingText.style.display = "none";

        if (!data.ok) {
            alert(data.error || "Could not load slots.");
            return;
        }

        renderSlots(data.slots || []);
    } catch (err) {
        loadingText.style.display = "none";
        alert("Failed to load available slots.");
    }
}

staffSelect?.addEventListener("change", loadAvailableSlots);
dateInput?.addEventListener("change", loadAvailableSlots);

document.getElementById("bookingForm")?.addEventListener("submit", function(e) {
    if (!selectedTimeInput.value) {
        e.preventDefault();
        alert("Please select an available time slot.");
    }
});
</script>

</body>
</html>