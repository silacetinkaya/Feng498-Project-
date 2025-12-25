<?php
session_start();
require "db_connect.php";

if (!isset($_SESSION['user_id'])) exit("Not logged in.");

$userId = $_SESSION['user_id'];
$ownerId = $_POST['owner_id'];
$content = trim($_POST['content']);

if ($content == "") exit;

$stmt = $pdo->prepare("
    INSERT INTO messages (content, user_id, owner_id)
    VALUES (:content, :uid, :oid)
");
$stmt->execute([
    'content' => $content,
    'uid' => $userId,
    'oid' => $ownerId
]);

header("Location: user_tab_messages.php?owner=" . $ownerId);
exit;
