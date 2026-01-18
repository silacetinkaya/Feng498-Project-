<?php
session_start();
require_once "db_connect.php";

$uid = $_SESSION['user_id'] ?? null;
$chatId = (int)($_POST['chat_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

if (!$uid || $chatId <= 0 || $content === '') { http_response_code(400); exit; }

$chat = $pdo->prepare("SELECT id, user_id FROM chats WHERE id=?");
$chat->execute([$chatId]);
$c = $chat->fetch(PDO::FETCH_ASSOC);

if (!$c) { http_response_code(404); exit; }
if ((int)$c['user_id'] !== (int)$uid) { http_response_code(403); exit; }

$pdo->beginTransaction();

$ins = $pdo->prepare("
  INSERT INTO messages (chat_id, sender_type, sender_ref_id, content, created_at, is_read)
  VALUES (?, 'user', ?, ?, NOW(), FALSE)
");
$ins->execute([$chatId, (int)$uid, $content]);

$upd = $pdo->prepare("UPDATE chats SET updated_at = NOW() WHERE id = ?");
$upd->execute([$chatId]);

$pdo->commit();

header("Content-Type: application/json");
echo json_encode(["ok" => true]);
