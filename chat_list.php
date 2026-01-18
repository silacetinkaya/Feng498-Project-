<?php
session_start();
require_once "db_connect.php";

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { http_response_code(403); exit; }

$stmt = $pdo->prepare("
  SELECT
    c.id AS chat_id,
    b.shop_id AS business_id,
    b.name AS business_name,
    c.updated_at,
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
        AND m.sender_type = 'business'
    ) AS unread_count
  FROM chats c
  JOIN business b ON b.shop_id = c.business_id
  WHERE c.user_id = :uid
  ORDER BY c.updated_at DESC
");
$stmt->execute(['uid' => $userId]);

header("Content-Type: application/json");
echo json_encode(["chats" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
