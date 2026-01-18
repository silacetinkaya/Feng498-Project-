<?php
session_start();
require_once "db_connect.php";

$uid = $_SESSION['user_id'] ?? null;
$chatId = (int)($_GET['chat_id'] ?? 0);

if (!$uid || $chatId <= 0) { http_response_code(400); exit; }

$chat = $pdo->prepare("SELECT id, user_id FROM chats WHERE id=?");
$chat->execute([$chatId]);
$c = $chat->fetch(PDO::FETCH_ASSOC);

if (!$c) { http_response_code(404); exit; }
if ((int)$c['user_id'] !== (int)$uid) { http_response_code(403); exit; }

$st = $pdo->prepare("
  SELECT id, sender_type, sender_ref_id, content, created_at
  FROM messages
  WHERE chat_id = ?
  ORDER BY created_at ASC
");
$st->execute([$chatId]);
$msgs = $st->fetchAll(PDO::FETCH_ASSOC);

// Business'tan gelenleri okundu işaretle
$mark = $pdo->prepare("
  UPDATE messages
  SET is_read = TRUE
  WHERE chat_id = ?
    AND sender_type = 'business'
");
$mark->execute([$chatId]);

header("Content-Type: application/json");
echo json_encode(["messages" => $msgs]);
