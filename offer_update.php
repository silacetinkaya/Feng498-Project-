<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: business.html");
  exit;
}

$ownerId = (int)$_SESSION['user_id'];

// Owner'ın business'ını bul
$stmtB = $pdo->prepare("SELECT shop_id FROM business WHERE owner_id = ? LIMIT 1");
$stmtB->execute([$ownerId]);
$bizId = (int)($stmtB->fetchColumn() ?: 0);

if ($bizId <= 0) {
  header("Location: business_dashboard.php?tab=offers&err=no_business");
  exit;
}

$offerId = (int)($_POST['offer_id'] ?? 0);
$action  = trim($_POST['action'] ?? '');

if ($offerId <= 0 || !in_array($action, ['accept','reject','counter'], true)) {
  header("Location: business_dashboard.php?tab=offers&err=bad_request");
  exit;
}

try {
  $pdo->beginTransaction();

  // Offer gerçekten bu business'a mı ait?
  $stmtO = $pdo->prepare("
    SELECT id, chat_id, product_id, user_id, offered_price, status
    FROM offers
    WHERE id = ? AND business_id = ?
    LIMIT 1
  ");
  $stmtO->execute([$offerId, $bizId]);
  $offer = $stmtO->fetch(PDO::FETCH_ASSOC);

  if (!$offer) {
    $pdo->rollBack();
    header("Location: business_dashboard.php?tab=offers&err=offer_not_found");
    exit;
  }

  $chatId    = (int)($offer['chat_id'] ?? 0);
  $userId    = (int)($offer['user_id'] ?? 0);
  $productId = (int)($offer['product_id'] ?? 0);

  if ($action === 'accept') {
    $pdo->prepare("UPDATE offers SET status='accepted' WHERE id=? AND business_id=?")
        ->execute([$offerId, $bizId]);

    $msgText = "✅ OFFER #{$offerId} accepted.";
  }

  if ($action === 'reject') {
    $pdo->prepare("UPDATE offers SET status='rejected' WHERE id=? AND business_id=?")
        ->execute([$offerId, $bizId]);

    $msgText = "❌ OFFER #{$offerId} rejected.";
  }

  if ($action === 'counter') {
    $counter = (float)($_POST['counter_price'] ?? 0);
    if ($counter <= 0) {
      $pdo->rollBack();
      header("Location: business_dashboard.php?tab=offers&err=bad_counter");
      exit;
    }

    // İstersen status 'countered' yap, istersen 'pending' bırak.
    $pdo->prepare("UPDATE offers SET status='countered', offered_price=? WHERE id=? AND business_id=?")
        ->execute([$counter, $offerId, $bizId]);

    $msgText = "🔁 COUNTER for OFFER #{$offerId}: " . number_format($counter, 2) . " TL";
  }

  // Mesaj olarak chat'e düşsün (user görsün)
  if ($chatId > 0) {
    $insMsg = $pdo->prepare("
      INSERT INTO messages (chat_id, sender_type, sender_ref_id, content, created_at, is_read)
      VALUES (?, 'business', ?, ?, NOW(), FALSE)
    ");
    $insMsg->execute([$chatId, $ownerId, $msgText]);
  }

  $pdo->commit();

  header("Location: business_dashboard.php?tab=offers&ok=1");
  exit;

} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  header("Location: business_dashboard.php?tab=offers&err=server");
  exit;
}
