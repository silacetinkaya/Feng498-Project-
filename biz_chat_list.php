<?php
session_start();
require_once "db_connect.php";

$ownerId = $_SESSION['user_id'] ?? null;
if (!$ownerId) { http_response_code(403); exit; }

$stmt = $pdo->prepare("
  SELECT
    c.id AS chat_id,
    u.full_name,
    (
      SELECT m.content
      FROM messages m
      WHERE m.chat_id = c.id
      ORDER BY m.created_at DESC
      LIMIT 1
    ) AS last_message,
    (
      SELECT COUNT(*)
      FROM messages m
      WHERE m.chat_id = c.id
        AND m.is_read = FALSE
        AND m.sender_type = 'user'
    ) AS unread_count
  FROM chats c
  JOIN users u ON u.id = c.user_id
  JOIN business b ON b.shop_id = c.business_id
  WHERE b.owner_id = ?
  ORDER BY c.updated_at DESC
");
$stmt->execute([$ownerId]);

header("Content-Type: application/json");
echo json_encode(["chats" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
