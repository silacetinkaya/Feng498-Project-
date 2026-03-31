<?php
// offer_update.php
session_start();
require_once 'db_connect.php';

// Business login kontrolü
if (!isset($_SESSION['user_id'])) {
  // form submit olduğu için JSON yerine login sayfasına atmak daha mantıklı
  header("Location: business.html?error=login_required");
  exit;
}

$ownerId = (int)$_SESSION['user_id'];

// Bu owner'ın business'ını bul
$stmtBiz = $pdo->prepare("SELECT shop_id FROM business WHERE owner_id = ? LIMIT 1");
$stmtBiz->execute([$ownerId]);
$businessId = (int)($stmtBiz->fetchColumn() ?: 0);

if ($businessId <= 0) {
  header("Location: business_dashboard.php?tab=offers&err=no_business");
  exit;
}

$offerId = (int)($_POST['offer_id'] ?? 0);
$action  = trim($_POST['action'] ?? '');
$counter = (float)($_POST['counter_price'] ?? 0);

if ($offerId <= 0 || !in_array($action, ['accept','reject','counter'], true)) {
  header("Location: business_dashboard.php?tab=offers&err=bad_request");
  exit;
}

// Offer bu business'a mı ait?
$stmt = $pdo->prepare("SELECT id, chat_id, product_id, offered_price, status FROM offers WHERE id=? AND business_id=? LIMIT 1");
$stmt->execute([$offerId, $businessId]);
$o = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$o) {
  header("Location: business_dashboard.php?tab=offers&err=offer_not_found");
  exit;
}

$chatId = (int)$o['chat_id'];

try {
  $pdo->beginTransaction();

  if ($action === 'accept') {
    $upd = $pdo->prepare("UPDATE offers SET status='accepted' WHERE id=? AND business_id=?");
    $upd->execute([$offerId, $businessId]);

    $msg = "✅ OFFER #{$offerId} accepted.";

  } elseif ($action === 'reject') {
    $upd = $pdo->prepare("UPDATE offers SET status='rejected' WHERE id=? AND business_id=?");
    $upd->execute([$offerId, $businessId]);

    $msg = "❌ OFFER #{$offerId} rejected.";

  } else { // counter
    if ($counter <= 0) {
      throw new Exception("counter_price_invalid");
    }

    $upd = $pdo->prepare("UPDATE offers SET status='countered', offered_price=? WHERE id=? AND business_id=?");
    $upd->execute([$counter, $offerId, $businessId]);

    $msg = "🔁 COUNTER for OFFER #{$offerId}: " . number_format($counter, 2) . " TL";
  }

  // Mesajı chat'e düş (business tarafı)
  // (messages şeman sende nullable olduğu için bu insert rahat)
  $insMsg = $pdo->prepare("
    INSERT INTO messages (chat_id, sender_type, sender_ref_id, owner_id, content, created_at)
    VALUES (?, 'business', ?, ?, ?, NOW())
  ");
  $insMsg->execute([$chatId, $businessId, $ownerId, $msg]);

  $pdo->commit();

  header("Location: business_dashboard.php?tab=offers&msg=updated");
  exit;

} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  header("Location: business_dashboard.php?tab=offers&err=" . urlencode($e->getMessage()));
  exit;
}
