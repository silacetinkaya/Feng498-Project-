<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

try {
  if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'login_required']);
    exit;
  }

  $userId = (int)$_SESSION['user_id'];

  $productId  = (int)($_POST['product_id'] ?? 0);
  $businessId = (int)($_POST['business_id'] ?? 0);
  $offered    = (float)($_POST['offered_price'] ?? 0);
  $note       = trim($_POST['note'] ?? '');

  if ($productId <= 0 || $businessId <= 0 || $offered <= 0) {
    echo json_encode(['ok' => false, 'error' => 'invalid_request']);
    exit;
  }

  // ✅ Product + negotiable + base price (pp için LATEST kaydı al)
  $stmt = $pdo->prepare("
    SELECT
      p.id,
      p.business_id,
      p.product_prices,
      p.is_discounted,
      p.discounted_price,
      COALESCE((
        SELECT pp.is_negotiable
        FROM product_prices pp
        WHERE pp.product_id = p.id
        ORDER BY pp.updated_at DESC NULLS LAST
        LIMIT 1
      ), FALSE) AS is_negotiable
    FROM products p
    WHERE p.id = ?
    LIMIT 1
  ");
  $stmt->execute([$productId]);
  $p = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$p || (int)$p['business_id'] !== $businessId) {
    echo json_encode(['ok' => false, 'error' => 'product_not_found']);
    exit;
  }

  $isNegotiable = ($p['is_negotiable'] === true || $p['is_negotiable'] === 1 || $p['is_negotiable'] === 't' || $p['is_negotiable'] === 'true');
  if (!$isNegotiable) {
    echo json_encode(['ok' => false, 'error' => 'not_negotiable']);
    exit;
  }

  $isDiscounted = ($p['is_discounted'] === true || $p['is_discounted'] === 1 || $p['is_discounted'] === 't' || $p['is_discounted'] === 'true');

  $basePrice = ($isDiscounted && (float)$p['discounted_price'] > 0)
    ? (float)$p['discounted_price']
    : (float)$p['product_prices'];

  if ($basePrice <= 0) {
    echo json_encode(['ok' => false, 'error' => 'invalid_base_price']);
    exit;
  }

  // ✅ Min %70 kuralı
  $minOffer = $basePrice * 0.70;
  if ($offered < $minOffer) {
    echo json_encode(['ok' => false, 'error' => 'too_low', 'min' => round($minOffer, 2)]);
    exit;
  }

  $pdo->beginTransaction();

  // ✅ chat var mı?
  $chatQ = $pdo->prepare("SELECT id FROM chats WHERE user_id=? AND business_id=? ORDER BY id DESC LIMIT 1");
  $chatQ->execute([$userId, $businessId]);
  $chatId = (int)($chatQ->fetchColumn() ?: 0);

  if ($chatId <= 0) {
    // chats.message kolonu var mı?
    $colQ = $pdo->prepare("
      SELECT 1
      FROM information_schema.columns
      WHERE table_schema = 'public'
        AND table_name = 'chats'
        AND column_name = 'message'
      LIMIT 1
    ");
    $colQ->execute();
    $hasMessage = (bool)$colQ->fetchColumn();

    if ($hasMessage) {
      $insChat = $pdo->prepare("INSERT INTO chats (business_id, user_id, message) VALUES (?, ?, '') RETURNING id");
      $insChat->execute([$businessId, $userId]);
      $chatId = (int)$insChat->fetchColumn();
    } else {
      $insChat = $pdo->prepare("INSERT INTO chats (business_id, user_id) VALUES (?, ?) RETURNING id");
      $insChat->execute([$businessId, $userId]);
      $chatId = (int)$insChat->fetchColumn();
    }
  }

  // ✅ Offer insert
  $insOffer = $pdo->prepare("
    INSERT INTO offers (business_id, user_id, chat_id, product_id, offered_price, note, status)
    VALUES (?, ?, ?, ?, ?, ?, 'pending')
    RETURNING id
  ");
  $insOffer->execute([$businessId, $userId, $chatId, $productId, $offered, $note]);
  $offerId = (int)$insOffer->fetchColumn();

  // ✅ Chat’e mesaj olarak da düş (business bunu görsün)
  $msgText = "💸 OFFER #{$offerId} | Product #{$productId} | Offered: " . number_format($offered, 2) . " TL";
  if ($note !== '') $msgText .= " | Note: " . $note;

  // ⚠️ messages tablonun kolon adları farklıysa sadece burayı değiştir
  $insMsg = $pdo->prepare("
    INSERT INTO messages (chat_id, sender_type, sender_ref_id, content, created_at, is_read)
    VALUES (?, 'user', ?, ?, NOW(), FALSE)
  ");
  $insMsg->execute([$chatId, $userId, $msgText]);

  // ✅ chat list preview için chats.message güncelle (varsa)
  if ($hasMessage ?? true) { // hasMessage yoksa bile denemek istersen true bırak
    try {
      $updChat = $pdo->prepare("UPDATE chats SET message = ? WHERE id = ?");
      $updChat->execute([$msgText, $chatId]);
    } catch (Exception $ignore) {}
  }

  $pdo->commit();

  echo json_encode([
    'ok' => true,
    'chat_url' => "chat_start.php?business_id=" . $businessId
  ]);
  exit;

} catch (Exception $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();

  echo json_encode([
    'ok' => false,
    'error' => 'server_error',
    'detail' => $e->getMessage()
  ]);
  exit;
}
