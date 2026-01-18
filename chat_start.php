<?php
session_start();
require_once "db_connect.php";

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
  header("Location: index.html");
  exit;
}

$businessId = (int)($_GET['business_id'] ?? 0);
if ($businessId <= 0) die("Invalid business.");

$st = $pdo->prepare("SELECT id FROM chats WHERE business_id = ? AND user_id = ? LIMIT 1");
$st->execute([$businessId, $userId]);
$chatId = $st->fetchColumn();

if (!$chatId) {
  $ins = $pdo->prepare("
    INSERT INTO chats (business_id, user_id, updated_at)
    VALUES (?, ?, NOW())
    RETURNING id
  ");
  $ins->execute([$businessId, $userId]);
  $chatId = $ins->fetchColumn();
}

header("Location: user_panel.php?tab=messages&chat_id=".(int)$chatId);
exit;
