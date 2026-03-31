<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

function isTruePg($v): bool {
  return $v === true || $v === 1 || $v === '1' || $v === 't' || $v === 'true';
}

try {
  // Business login kontrolü (sende business_dashboard.php user_id ile gidiyor)
  if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'login_required']);
    exit;
  }
  $ownerId = (int)$_SESSION['user_id'];

  $action   = $_POST['action'] ?? '';
  $offerId  = (int)($_POST['offer_id'] ?? 0);
  $counter  = isset($_POST['counter_price']) ? (float)$_POST['counter_price'] : null;

  if ($offerId <= 0 || !in_array($action, ['accept','reject','counter'], true)) {
    echo json_encode(['ok' => false, 'error' => 'invalid_request']);
    exit;
  }

  // chats.message kolonu var mı?
  $colQ = $pdo->prepare("
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema='public'
      AND table_name='chats'
      AND column_name='message'
    LIMIT 1
  ");
  $colQ->execute();
  $hasChatMessageCol = (bool)$colQ->fetchColumn();

  // Offer + business ownership doğrula
  $stmt = $pdo->prepare("
    SELECT o.*, b.shop_id, b.owner_id
    FROM offers o
    JOIN business b ON b.shop_id = o.business_id
    WHERE o.id = ?
    LIMIT 1
  ");
  $stmt->execute([$offerId]);
  $o = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$o) {
    echo json_encode(['ok' => false, 'error' => 'offer_not_found']);
    exit;
  }
  if ((int)$o['owner_id'] !== $ownerId) {
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
  }

  $chatId = (int)$o['chat_id'];
  $userId = (int)$o['user_id'];
  $businessId = (int)$o['business_id'];

  // action rules
  if ($action === 'counter') {
    if ($counter === null || $counter <= 0) {
      echo json_encode(['ok' => false, 'error' => 'invalid_counter']);
      exit;
    }
  }

  $pdo->beginTransaction();

  if ($action === 'accept') {
    $upd = $pdo->prepare("UPDATE offers SET status='accepted' WHERE id=?");
    $upd->execute([$offerId]);

    $msgText = "✅ OFFER #{$offerId} accepted.";
  }

  if ($action === 'reject') {
    $upd = $pdo->prepare("UPDATE offers SET status='rejected' WHERE id=?");
    $upd->execute([$offerId]);

    $msgText = "❌ OFFER #{$offerId} rejected.";
  }

  if ($action === 'counter') {
    $upd = $pdo->prepare("UPDATE offers SET status='countered', counter_price=? WHERE id=?");
    $upd->execute([$counter, $offerId]);

    $msgText = "🔁 COUNTER for OFFER #{$offerId}: " . number_format($counter, 2) . " TL";
  }

  // messages’a business mesajı
  $insMsg = $pdo->prepare("
    INSERT INTO messages (chat_id, sender_type, sender_ref_id, content, created_at, is_read)
    VALUES (?, 'business', ?, ?, NOW(), FALSE)
  ");
  $insMsg->execute([$chatId, $businessId, $msgText]);

  if ($hasChatMessageCol) {
    $updChat = $pdo->prepare("UPDATE chats SET message=? WHERE id=?");
    $updChat->execute([$msgText, $chatId]);
  }

  $pdo->commit();

  echo json_encode(['ok' => true]);
  exit;

} catch (Exception $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
  exit;
}
