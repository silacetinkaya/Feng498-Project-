<?php
session_start();
require_once "db_connect.php";

$ownerId = $_SESSION['user_id'] ?? null;
$chatId = (int)($_GET['chat_id'] ?? 0);

if (!$ownerId || $chatId <= 0) { http_response_code(400); exit; }

// Bu chat bu owner'a mı ait?
$chk = $pdo->prepare("
  SELECT c.id
  FROM chats c
  JOIN business b ON b.shop_id = c.business_id
  WHERE c.id = ? AND b.owner_id = ?
");
$chk->execute([$chatId, $ownerId]);
if (!$chk->fetchColumn()) { http_response_code(403); exit; }

$st = $pdo->prepare("
  SELECT id, sender_type, sender_ref_id, content, created_at
  FROM messages
  WHERE chat_id = ?
  ORDER BY created_at ASC
");
$st->execute([$chatId]);
$msgs = $st->fetchAll(PDO::FETCH_ASSOC);

// User'dan gelenleri okundu yap
$mark = $pdo->prepare("
  UPDATE messages
  SET is_read = TRUE
  WHERE chat_id = ?
    AND sender_type = 'user'
");
$mark->execute([$chatId]);

header("Content-Type: application/json");
echo json_encode(["messages" => $msgs]);
