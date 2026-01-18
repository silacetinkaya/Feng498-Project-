<?php
session_start();
require_once "db_connect.php";

$ownerId = $_SESSION['user_id'] ?? null;
$chatId = (int)($_POST['chat_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

if (!$ownerId || $chatId <= 0 || $content === '') { http_response_code(400); exit; }

// Bu chat bu owner'a mı ait?
$chk = $pdo->prepare("
  SELECT c.id
  FROM chats c
  JOIN business b ON b.shop_id = c.business_id
  WHERE c.id = ? AND b.owner_id = ?
");
$chk->execute([$chatId, $ownerId]);
if (!$chk->fetchColumn()) { http_response_code(403); exit; }

$pdo->beginTransaction();

$ins = $pdo->prepare("
  INSERT INTO messages (chat_id, sender_type, sender_ref_id, content, created_at, is_read)
  VALUES (?, 'business', ?, ?, NOW(), FALSE)
");
$ins->execute([$chatId, (int)$ownerId, $content]);

$upd = $pdo->prepare("UPDATE chats SET updated_at = NOW() WHERE id = ?");
$upd->execute([$chatId]);

$pdo->commit();

header("Content-Type: application/json");
echo json_encode(["ok" => true]);
