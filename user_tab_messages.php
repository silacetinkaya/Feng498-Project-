<?php
session_start();
require "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

$userId = $_SESSION['user_id'];

/* 1) Kullanıcının mesajlaştığı işletmeler */
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        m.owner_id,
        b.name AS business_name
    FROM messages m
    JOIN business b ON b.owner_id = m.owner_id
    WHERE m.user_id = :uid OR b.owner_id = :uid
");
$stmt->execute(['uid' => $userId]);
$chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* 2) Aktif sohbet */
$activeOwner = $_GET['owner'] ?? ($chats[0]['owner_id'] ?? null);
$messages = [];

if ($activeOwner) {
    $msgStmt = $pdo->prepare("
        SELECT m.*, 
               u.full_name AS sender_name
        FROM messages m
        JOIN users u ON u.id = m.user_id
        WHERE (m.owner_id = :oid AND m.user_id = :uid)
           OR (m.owner_id = :uid AND m.user_id = :oid)
        ORDER BY m.id ASC
    ");
    $msgStmt->execute([
        'oid' => $activeOwner,
        'uid' => $userId
    ]);
    $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Messages</title>

<style>
body {
    margin: 0;
    font-family: system-ui, sans-serif;
    display: flex;
    height: 100vh;
    background: #f5f6fa;
}

/* SOL LİSTE */
.sidebar {
    width: 270px;
    background: #ffffff;
    border-right: 1px solid #ddd;
    padding: 20px;
    box-shadow: 2px 0 8px rgba(0,0,0,0.05);
    overflow-y: auto;
}

.sidebar h3 {
    margin: 0 0 15px 0;
    font-size: 1.3rem;
    font-weight: 600;
}

.chat-item {
    padding: 12px 15px;
    background: #f3f3f3;
    margin-bottom: 8px;
    border-radius: 12px;
    cursor: pointer;
    font-size: 0.95rem;
    transition: 0.2s;
}

.chat-item:hover {
    background: #e8e8e8;
}

.active-chat {
    background: #e53935;
    color: white;
    font-weight: 600;
}

/* ANA CHAT ALANI */
.main {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.header {
    padding: 15px 20px;
    background: #ffffff;
    border-bottom: 1px solid #ddd;
    font-size: 1.2rem;
    font-weight: 600;
}

.messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
}

/* Mesaj baloncukları */
.msg {
    padding: 12px 16px;
    max-width: 65%;
    margin-bottom: 12px;
    border-radius: 18px;
    line-height: 1.4;
    font-size: 0.95rem;
}

.me {
    background: #e53935;
    color: white;
    margin-left: auto;
    border-bottom-right-radius: 4px;
}

.them {
    background: white;
    border: 1px solid #ddd;
    border-bottom-left-radius: 4px;
}

/* INPUT */
.input-box {
    display: flex;
    background: #ffffff;
    padding: 15px;
    border-top: 1px solid #ddd;
}

.input-box input {
    flex: 1;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 12px;
    margin-right: 10px;
    font-size: 1rem;
}

.send-btn {
    background: #e53935;
    color: white;
    padding: 12px 18px;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-size: 1rem;
    transition: 0.2s;
}

.send-btn:hover {
    background: #c62828;
}
.back-btn {
    text-decoration: none;
    font-size: 0.9rem;
    color: #e53935;
    background: #ffeaea;
    padding: 7px 14px;
    border-radius: 10px;
    margin-right: 12px;
    border: 1px solid #e53935;
    transition: 0.15s;
}

.back-btn:hover {
    background: #e53935;
    color: white;
}

.header {
    display: flex;
    align-items: center;
    gap: 10px;
}

</style>
</head>
<body>

<!-- SOL CHAT LİSTE -->
<div class="sidebar">
    <h3>Messages</h3>

    <?php foreach ($chats as $c): ?>
        <div class="chat-item <?= ($c['owner_id']==$activeOwner?'active-chat':'') ?>"
             onclick="location.href='user_tab_messages.php?owner=<?= $c['owner_id'] ?>'">
            <?= htmlspecialchars($c['business_name']) ?>
        </div>
    <?php endforeach; ?>

    <?php if (empty($chats)): ?>
        <p>No messages yet.</p>
    <?php endif; ?>
</div>

<!-- CHAT -->
<div class="main">

    <div class="header">
    <a href="user_panel.php" class="back-btn">← Back</a>
    <span class="chat-title">
        <?= $activeOwner ? "Chat with Business Owner #".$activeOwner : "Messages" ?>
    </span>
     </div>


    <div class="messages">
        <?php foreach ($messages as $m): ?>
            <div class="msg <?= ($m['user_id']==$userId ? 'me' : 'them') ?>">
                <?= nl2br(htmlspecialchars($m['content'])) ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($activeOwner): ?>
    <form class="input-box" action="send_message.php" method="POST">
        <input type="hidden" name="owner_id" value="<?= $activeOwner ?>">
        <input type="text" name="content" placeholder="Message..." required>
        <button class="send-btn">Send</button>
    </form>
    <?php endif; ?>
</div>

</body>
</html>
