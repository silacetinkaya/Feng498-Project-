<?php
session_start();
require_once "db_connect.php";

/* =========================
   AUTH
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

$userId = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'home';
// --- Path helper (MAMP project folder uyumu için) ---
$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
function asset($path) {
    global $BASE;
    if (empty($path)) return '';
    return $BASE . '/' . ltrim((string)$path, '/');
}
function isTruePg($v) {
    return $v === true || $v === 1 || $v === '1' || $v === 't' || $v === 'true';
}

/* =========================
   USER INFO
========================= */
$stmt = $pdo->prepare("SELECT full_name, email, address FROM users WHERE id = :id");
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   MESSAGES
========================= */
$successMessage = "";
$errorMessage = "";

/* =========================
   POST ACTIONS
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST['update_profile'])) {
        try {
            $email = trim($_POST['email']);

            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = "Invalid email format. Please enter a valid email address.";
            } else {
                // Check if email already exists for another user
                $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $checkEmail->execute([$email, $userId]);

                if ($checkEmail->rowCount() > 0) {
                    $errorMessage = "This email is already in use by another account.";
                } else {
                    $up = $pdo->prepare("UPDATE users SET full_name=?, email=?, address=? WHERE id=?");
                    $up->execute([
                        $_POST['full_name'],
                        $email,
                        $_POST['address'],
                        $userId
                    ]);
                    $successMessage = "Profile updated successfully!";
                    $stmt->execute(['id' => $userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
        } catch (Exception $e) {
            $errorMessage = "Error updating profile.";
        }
    }

    if (isset($_POST['update_password'])) {
        try {
            if (
                empty($_POST['current_password']) ||
                empty($_POST['new_password']) ||
                empty($_POST['confirm_password'])
            ) {
                $errorMessage = "All password fields are required.";
            } else {
                $stmtPass = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmtPass->execute([$userId]);
                $dbPass = $stmtPass->fetchColumn();

                $newPassword = $_POST['new_password'];

                if (!password_verify($_POST['current_password'], $dbPass)) {
                    $errorMessage = "Current password is incorrect.";
                } elseif ($newPassword !== $_POST['confirm_password']) {
                    $errorMessage = "Passwords do not match.";
                } elseif (
                    strlen($newPassword) < 8 ||
                    !preg_match('/[A-Z]/', $newPassword) ||
                    !preg_match('/\d/', $newPassword)
                ) {
                    $errorMessage = "Password must be at least 8 characters, include one uppercase letter and one number.";
                } else {
                    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                        ->execute([$hashed, $userId]);

                    $successMessage = "Password updated successfully.";
                }
            }
        } catch (Exception $e) {
            $errorMessage = "Password update error.";
        }
    }
}

/* =========================
   OTHER TABS DATA
========================= */
$favoriteBusinesses = $favoriteProducts = $myReviews = $myOffers = $myChats = [];

if ($tab === 'favorites') {
    $stmt = $pdo->prepare("SELECT b.shop_id as business_id, b.name, b.category 
                            FROM business_favorites bf 
                            JOIN business b ON b.shop_id = bf.business_id
                            WHERE bf.user_id=?");
    $stmt->execute([$userId]);
    $favoriteBusinesses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($tab === 'reviews') {
    $stmt = $pdo->prepare("SELECT r.review_id, r.comments, r.rank, r.time, 
                            b.name AS business_name, b.shop_id as business_id
                            FROM reviews r JOIN business b ON b.shop_id=r.business_id
                            WHERE r.user_id=? ORDER BY r.time DESC");
    $stmt->execute([$userId]);
    $myReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($tab === 'offers') {
    $stmt = $pdo->prepare("
        SELECT
            o.id,
            o.offered_price,
            o.counter_price,
            o.status,
            o.created_time,
            o.chat_id,
            b.shop_id AS business_id,
            b.name AS business_name,
            p.name AS product_name
        FROM offers o
        JOIN business b ON b.shop_id = o.business_id
        LEFT JOIN products p ON p.id = o.product_id
        WHERE o.user_id = ?
        ORDER BY o.created_time DESC
    ");
    $stmt->execute([$userId]);
    $myOffers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$discountProducts = [];
if ($tab === 'discounts') {
    $stmt = $pdo->prepare("
        SELECT 
            p.id AS product_id,
            p.name AS product_name,
            p.original_price,
            p.discounted_price,
            p.discount_percent,
            b.shop_id,
            b.name AS business_name,
            b.category,
            (
              SELECT ph.image_url
              FROM photos ph
              WHERE ph.product_id = p.id
                AND ph.is_approved = TRUE
              ORDER BY ph.photo_id DESC
              LIMIT 1
            ) AS product_image
        FROM products p
        JOIN business b ON b.shop_id = p.business_id
        WHERE p.is_discounted = TRUE
          AND p.available = TRUE
        ORDER BY p.discount_percent DESC NULLS LAST, p.id DESC
        LIMIT 200
    ");
    $stmt->execute();
    $discountProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


if ($tab === 'messages') {
    $stmt = $pdo->prepare("SELECT DISTINCT b.name AS business_name, b.owner_id
                            FROM messages m JOIN business b ON b.owner_id=m.owner_id
                            WHERE m.user_id=? OR b.owner_id=?");
    $stmt->execute([$userId, $userId]);
    $myChats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// EDITOR'S CHOICE TAB DATA
if ($tab === 'editors_choice') {
    $stmt = $pdo->prepare("
        SELECT b.shop_id, b.name, b.category, b.address,
               COALESCE(AVG(r.rank), 0) as rating,
               COUNT(r.review_id) as review_count
        FROM business b
        LEFT JOIN reviews r ON r.business_id = b.shop_id AND r.is_approved = TRUE
        WHERE b.is_editors_choice = TRUE
        GROUP BY b.shop_id
        ORDER BY rating DESC
    ");
    $stmt->execute();
    $editorsChoiceList = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root {
            --red: #e53935;
            --bg: #f4f5fb;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg);
            height: 100vh;
            overflow: hidden;
        }

        .layout {
            display: flex;
            height: 100%;
        }

        /* SIDEBAR */
        .sidebar {
            width: 240px;
            background: var(--red);
            color: white;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 6px;
        }

        .sidebar a.active,
        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.2);
            font-weight: bold;
        }

        /* MAIN */
        .main {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            padding: 15px;
        }

        .alert-danger {
            background: #f8d7da;
            padding: 15px;
        }

        .btn {
            background: var(--red);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }

        .valid {
            color: green;
        }

        .invalid {
            color: red;
        }

        /* Make grid responsive */
        @media (max-width: 1400px) {
            .editors-choice-grid {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }

        @media (max-width: 1000px) {
            .editors-choice-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }

        @media (max-width: 600px) {
            .editors-choice-grid {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>
<script>
    document.addEventListener('DOMContentLoaded', function() {

        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const btn = document.getElementById('updatePasswordBtn');

        if (!newPassword || !confirmPassword || !btn) return;

        const rules = {
            length: document.getElementById('rule-length'),
            uppercase: document.getElementById('rule-uppercase'),
            number: document.getElementById('rule-number'),
            match: document.getElementById('rule-match')
        };

        function updateRule(el, valid) {
            el.textContent = el.textContent.replace(/^❌|^✅/, valid ? '✅' : '❌');
            el.className = valid ? 'valid' : 'invalid';
        }

        function validatePassword() {
            const value = newPassword.value;

            const hasLength = value.length >= 8;
            const hasUppercase = /[A-Z]/.test(value);
            const hasNumber = /\d/.test(value);
            const passwordsMatch = value.length > 0 && value === confirmPassword.value;

            updateRule(rules.length, hasLength);
            updateRule(rules.uppercase, hasUppercase);
            updateRule(rules.number, hasNumber);
            updateRule(rules.match, passwordsMatch);

            btn.disabled = !(hasLength && hasUppercase && hasNumber && passwordsMatch);
        }

        newPassword.addEventListener('input', validatePassword);
        confirmPassword.addEventListener('input', validatePassword);
    });
</script>

<body>

    <div class="layout">
        <nav class="sidebar">
            <h2>UserPanel</h2>
            <a href="?tab=home" class="<?= $tab == 'home' ? 'active' : '' ?>">Home</a>
            <a href="?tab=profile" class="<?= $tab == 'profile' ? 'active' : '' ?>">Profile</a>
            <a href="?tab=favorites" class="<?= $tab == 'favorites' ? 'active' : '' ?>">Favorites</a>
            <a href="?tab=reviews" class="<?= $tab == 'reviews' ? 'active' : '' ?>">Reviews</a>
            <a href="?tab=editors_choice" class="<?= $tab == 'editors_choice' ? 'active' : '' ?>">Editor's Choice</a>
            <a href="?tab=discounts" class="<?= $tab == 'discounts' ? 'active' : '' ?>">Discounts</a>
            <a href="?tab=offers" class="<?= $tab == 'offers' ? 'active' : '' ?>">Offers</a>
            <a href="?tab=messages" class="<?= $tab == 'messages' ? 'active' : '' ?>">Messages</a>
            <a href="logout.php" style="margin-top:auto;">Logout</a>
        </nav>

        <main class="main">

            <?php if ($successMessage): ?><div class="alert-success"><?= $successMessage ?></div><?php endif; ?>
            <?php if ($errorMessage): ?><div class="alert-danger"><?= $errorMessage ?></div><?php endif; ?>

            <?php if ($tab === 'home'): ?>

                <?php if (isset($_GET['view_business'])): ?>
                    <!-- Show Business Detail -->
                    <?php
                    $viewBusinessId = (int)$_GET['view_business'];
                    ?>
                    <div style="margin-bottom: 20px;">
                        <a href="?tab=home" class="btn" style="background: #666; color: white; text-decoration: none; padding: 10px 20px; border-radius: 8px; display: inline-block;">
                            <i class="fas fa-arrow-left"></i> Back to Map
                        </a>
                    </div>

                    <div style="width:100%; height:calc(100vh - 150px); border-radius:12px; overflow:hidden;">
                        <iframe
                            src="business_detail.php?id=<?= $viewBusinessId ?>"
                            style="width:100%; height:100%; border:none;"
                            loading="lazy">
                        </iframe>
                    </div>
                <?php else: ?>
                    <!-- Show Map -->
                    <h1>Welcome, <?= htmlspecialchars($user['full_name']) ?>!</h1>

                    <div class="card">
                        <div style="width:100%; height:550px; border-radius:12px; overflow:hidden;">
                            <iframe
                                src="user_map.php"
                                style="width:100%; height:100%; border:none;"
                                loading="lazy">
                            </iframe>
                        </div>
                    </div>
                <?php endif; ?>
            <?php elseif ($tab === 'profile'): ?>

                <div class="card">
                    <h3>Edit Profile</h3>
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <input class="form-control" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>">
                        <input class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>">
                        <textarea class="form-control" name="address"><?= htmlspecialchars($user['address']) ?></textarea>
                        <button class="btn">Save</button>
                    </form>
                </div>

                <div class="card">
                    <h3>Change Password</h3>
                    <form method="POST" id="passwordForm">
                        <input type="hidden" name="update_password" value="1">

                        <input type="password" name="current_password" class="form-control" placeholder="Current password" required>

                        <input type="password" name="new_password" id="new_password"
                            class="form-control" placeholder="New password" required>

                        <input type="password" name="confirm_password" id="confirm_password"
                            class="form-control" placeholder="Confirm password" required>

                        <ul id="passwordRules" style="font-size:14px; margin-bottom:15px;">
                            <li id="rule-length" class="invalid">❌ At least 8 characters</li>
                            <li id="rule-uppercase" class="invalid">❌ At least 1 uppercase letter</li>
                            <li id="rule-number" class="invalid">❌ At least 1 number</li>
                            <li id="rule-match" class="invalid">❌ Passwords match</li>
                        </ul>

                        <button class="btn" id="updatePasswordBtn">Update Password</button>

                    </form>
                </div>


            <?php elseif ($tab === 'favorites'): ?>

                <div class="card">
                    <h3>Favorite Businesses</h3>
                    <?php if (empty($favoriteBusinesses)): ?>
                        <p style="color: #666; text-align: center; padding: 20px;">No favorite businesses yet.</p>
                    <?php else: ?>
                        <?php foreach ($favoriteBusinesses as $b): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #eee;">
                                <div>
                                    <strong style="font-size: 1.1rem;"><?= htmlspecialchars($b['name']) ?></strong>
                                    <span style="color: #666; margin-left: 10px;">(<?= htmlspecialchars($b['category']) ?>)</span>
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <a href="?tab=home&view_business=<?= $b['business_id'] ?>">
                                        <button class="btn" style="background: #3498db; padding: 8px 16px;">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </a>
                                    <a href="user_process.php?action=remove_favorite&business_id=<?= $b['business_id'] ?>"
                                        onclick="return confirm('Remove from favorites?');">
                                        <button class="btn" style="background: #e74c3c; padding: 8px 16px;">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php elseif ($tab === 'reviews'): ?>

                <?php if (empty($myReviews)): ?>
                    <div class="card">
                        <p style="color: #666; text-align: center; padding: 20px;">No reviews yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($myReviews as $r): ?>
                        <div class="card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div style="flex: 1;">
                                    <h3 style="margin: 0 0 10px 0;"><?= htmlspecialchars($r['business_name']) ?></h3>
                                    <div style="color: #f1c40f; margin-bottom: 10px;">
                                        <?= str_repeat('★', $r['rank']) ?><?= str_repeat('☆', 5 - $r['rank']) ?>
                                    </div>
                                    <p style="color: #666; margin-bottom: 10px;"><?= htmlspecialchars($r['comments']) ?></p>
                                    <small style="color: #999;">
                                        <i class="fas fa-calendar"></i> Reviewed on <?= date('M d, Y', strtotime($r['time'])) ?>
                                    </small>
                                </div>
                                <div style="display: flex; gap: 10px; margin-left: 20px;">
                                    <a href="?tab=home&view_business=<?= $r['business_id'] ?>">
                                        <button class="btn" style="background: #3498db; padding: 8px 16px;">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </a>
                                    <a href="user_process.php?action=delete_review&review_id=<?= $r['review_id'] ?>"
                                        onclick="return confirm('Delete this review?');">
                                        <button class="btn" style="background: #e74c3c; padding: 8px 16px;">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

              <?php elseif ($tab === 'offers'): ?>

    <div class="card">
        <h3 style="margin-top:0;">My Offers</h3>

        <?php if (empty($myOffers)): ?>
            <p style="color:#666; text-align:center; padding:20px;">No offers yet.</p>
        <?php else: ?>
            <?php foreach ($myOffers as $o): ?>
                <?php
                    $status = $o['status'] ?? 'pending';
                    $badgeColor = [
                        'pending'   => '#f39c12',
                        'accepted'  => '#2ecc71',
                        'declined'  => '#e74c3c',
                        'countered' => '#3498db',
                    ][$status] ?? '#999';
                ?>

                <div style="border:1px solid #eee; border-radius:12px; padding:15px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start;">
                        <div style="flex:1;">
                            <div style="font-weight:900; font-size:1.05rem;">
                                <?= htmlspecialchars($o['business_name'] ?? '') ?>
                            </div>

                            <div style="color:#666; margin-top:6px;">
                                Product: <b><?= htmlspecialchars($o['product_name'] ?? 'Unknown') ?></b>
                            </div>

                            <div style="margin-top:8px; font-weight:900;">
                                Your offer: <?= number_format((float)($o['offered_price'] ?? 0), 2) ?> TL
                            </div>

                            <?php if (($status === 'countered') && $o['counter_price'] !== null): ?>
                                <div style="margin-top:6px; font-weight:900; color:#3498db;">
                                    Counter offer: <?= number_format((float)$o['counter_price'], 2) ?> TL
                                </div>
                            <?php endif; ?>

                            <div style="margin-top:10px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <span style="background:<?= $badgeColor ?>; color:#fff; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:900;">
                                    <?= strtoupper($status) ?>
                                </span>

                                <?php if (!empty($o['created_time'])): ?>
                                    <span style="color:#999; font-size:12px;">
                                        <?= date('M d, Y H:i', strtotime($o['created_time'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <a href="?tab=home&view_business=<?= (int)$o['business_id'] ?>" style="text-decoration:none;">
                                <button class="btn" style="background:#3498db;">View</button>
                            </a>

                            <?php if (!empty($o['chat_id'])): ?>
                                <a href="?tab=messages&chat_id=<?= (int)$o['chat_id'] ?>" style="text-decoration:none;">
                                    <button class="btn" style="background:#666;">Open Chat</button>
                                </a>
                            <?php else: ?>
                                <a href="chat_start.php?business_id=<?= (int)$o['business_id'] ?>" style="text-decoration:none;">
                                    <button class="btn" style="background:#666;">Open Chat</button>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>


            <?php elseif ($tab === 'messages'): ?>

<div style="display:flex; gap:16px;">

  <!-- LEFT: CHAT LIST -->
  <div class="card" style="flex:0 0 320px; max-height:650px; overflow:auto;">
    <h3 style="margin-top:0;">Messages</h3>
    <div id="chatList" style="display:flex; flex-direction:column; gap:10px;"></div>
  </div>

  <!-- RIGHT: CHAT VIEW -->
  <div class="card" style="flex:1; display:flex; flex-direction:column; min-height:650px;">
    <div style="display:flex; align-items:center; justify-content:space-between;">
      <h3 id="chatTitle" style="margin:0;">Select a chat</h3>
      <span id="chatMeta" style="color:#777;"></span>
    </div>

    <div id="msgBox" style="flex:1; margin-top:12px; padding:12px; border:1px solid #eee; border-radius:12px; background:#fafafa; overflow:auto;">
      <div style="color:#777;">No chat selected.</div>
    </div>

    <div style="display:flex; gap:10px; margin-top:12px;">
      <input id="msgInput" class="form-control" type="text" placeholder="Type a message..." style="flex:1;" disabled>
      <button id="sendBtn" class="btn btn-primary" disabled>Send</button>
    </div>
  </div>

</div>

<script>
let currentChatId = null;
let pollTimer = null;

function esc(s){ return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

async function loadChats(){
  const res = await fetch('chat_list.php');
  const data = await res.json();
  const list = document.getElementById('chatList');
  list.innerHTML = '';

  if (!data.chats || data.chats.length === 0){
    list.innerHTML = '<div style="color:#777; padding:8px;">No chats yet.</div>';
    return data;
  }

  data.chats.forEach(c => {
    const unread = parseInt(c.unread_count || 0, 10);
    const last = c.last_message ? esc(c.last_message) : '<span style="color:#aaa;">No messages</span>';

    const item = document.createElement('div');
    item.style.border = '1px solid #eee';
    item.style.borderRadius = '12px';
    item.style.padding = '10px';
    item.style.cursor = 'pointer';
    item.style.background = (currentChatId == c.chat_id) ? '#f1f7ff' : '#fff';

    item.innerHTML = `
      <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
        <div style="font-weight:800;">${esc(c.business_name)}</div>
        ${unread>0 ? `<div style="background:#e53935;color:#fff;border-radius:999px;padding:2px 8px;font-size:12px;font-weight:800;">${unread}</div>` : ``}
      </div>
      <div style="color:#666; margin-top:6px; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${last}</div>
    `;

    item.onclick = () => openChat(c.chat_id, c.business_name);
    list.appendChild(item);
  });
   return data; 
}

async function openChat(chatId, businessName){
  currentChatId = chatId;
  document.getElementById('chatTitle').textContent = businessName;
  document.getElementById('msgInput').disabled = false;
  document.getElementById('sendBtn').disabled = false;

  await loadMessages();
  await loadChats();

  if (pollTimer) clearInterval(pollTimer);
  pollTimer = setInterval(loadMessages, 2500);
}

async function loadMessages() {
  if (!currentChatId) return;

  const res = await fetch('chat_fetch_messages.php?chat_id=' + encodeURIComponent(currentChatId));
  const data = await res.json();

  const box = document.getElementById('msgBox');
  box.innerHTML = '';

  // Normal messages
  (data.messages || []).forEach(m => {
    const mine = (m.sender_type === 'user');

    const bubble = document.createElement('div');
    bubble.style.maxWidth = '75%';
    bubble.style.margin = mine ? '8px 0 8px auto' : '8px auto 8px 0';
    bubble.style.padding = '10px 12px';
    bubble.style.borderRadius = '14px';
    bubble.style.background = mine ? '#dff0ff' : '#ffffff';
    bubble.style.border = '1px solid #e6e6e6';

    bubble.innerHTML = `
      <div style="white-space:pre-wrap;">${esc(m.content)}</div>
      <div style="margin-top:6px;color:#999;font-size:12px;">${esc(m.created_at)}</div>
    `;

    box.appendChild(bubble);
  });

  // Offer cards
  (data.offers || []).forEach(o => {
    const card = document.createElement('div');
    card.style.maxWidth = '75%';
    card.style.margin = '8px 0 8px auto'; // user offer -> right
    card.style.padding = '12px 12px';
    card.style.borderRadius = '14px';
    card.style.background = '#fffbe6';
    card.style.border = '1px solid #f1e0a6';

    const status = String(o.status || 'pending').toUpperCase();

    let extra = '';
    if (o.status === 'countered' && o.counter_price != null) {
      extra = `
        <div style="margin-top:6px; font-weight:900; color:#3498db;">
          Counter: ${esc(o.counter_price)} TL
        </div>
      `;
    }

    card.innerHTML = `
      <div style="font-weight:900;">💸 Offer</div>
      <div style="margin-top:6px; color:#444;">
        Product: <b>${esc(o.product_name || 'Unknown')}</b>
      </div>
      <div style="margin-top:6px; font-weight:900;">
        Offered: ${esc(o.offered_price)} TL
      </div>
      ${extra}
      <div style="margin-top:8px; color:#999; font-size:12px;">
        ${status} • ${esc(o.created_time)}
      </div>
    `;

    box.appendChild(card);
  });

  box.scrollTop = box.scrollHeight;
}


async function sendMessage(){
  if (!currentChatId) return;

  const inp = document.getElementById('msgInput');
  const content = inp.value.trim();
  if (!content) return;

  inp.value = '';
  inp.focus();

  const res = await fetch('chat_send_message.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'chat_id=' + encodeURIComponent(currentChatId) + '&content=' + encodeURIComponent(content)
  });

  if (!res.ok) {
    const txt = await res.text().catch(() => '');
    alert('Mesaj gönderilemedi. (HTTP ' + res.status + ')\n' + txt);
    return;
  }

  await loadMessages();
  await loadChats();
}

document.getElementById('sendBtn').addEventListener('click', sendMessage);
document.getElementById('msgInput').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    sendMessage();
  }
});




// Auto-open chat if chat_id exists in URL
const openId = new URLSearchParams(window.location.search).get('chat_id');

(async () => {
  await loadChats();

  if (openId) {
    const res = await fetch('chat_list.php');
    const data = await res.json();
    const found = (data.chats || []).find(x => String(x.chat_id) === String(openId));
    if (found) openChat(found.chat_id, found.business_name);
  }
})();


</script>

<?php elseif ($tab === 'editors_choice'): ?>

                <div class="card">
                    <h2 style="color: #e53935; margin-bottom: 30px;">
                        <i class="fas fa-award"></i> Editor's Choice
                    </h2>

                    <?php if (empty($editorsChoiceList)): ?>
                        <p style="color: #666; text-align: center; padding: 40px;">No editor's choice businesses yet.</p>
                    <?php else: ?>
                        <div class="editors-choice-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                            <?php foreach ($editorsChoiceList as $b): ?>
                                <div style="border: 1px solid #ddd; border-radius: 12px; padding: 20px; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: transform 0.2s; cursor: pointer;"
                                    onclick="window.location.href='?tab=home&view_business=<?= $b['shop_id'] ?>'">

                                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px; border-radius: 8px; text-align: center; margin-bottom: 15px;">
                                        <i class="fas fa-award"></i> Editor's Choice
                                    </div>

                                    <h3 style="margin: 0 0 10px 0; font-size: 1.1rem; color: #333;">
                                        <?= htmlspecialchars($b['name']) ?>
                                    </h3>

                                    <p style="color: #666; font-size: 0.9rem; margin: 5px 0;">
                                        <i class="fas fa-tag"></i> <?= htmlspecialchars($b['category']) ?>
                                    </p>

                                    <p style="color: #666; font-size: 0.85rem; margin: 5px 0;">
                                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars(substr($b['address'], 0, 30)) ?>...
                                    </p>

                                    <div style="margin: 15px 0; padding-top: 15px; border-top: 1px solid #eee;">
                                        <div style="color: #f1c40f; font-size: 1.2rem;">
                                            <?= str_repeat('★', round($b['rating'])) ?>
                                            <?= str_repeat('☆', 5 - round($b['rating'])) ?>
                                        </div>
                                        <p style="color: #999; font-size: 0.85rem; margin: 5px 0;">
                                            <?= number_format($b['rating'], 1) ?> (<?= $b['review_count'] ?> reviews)
                                        </p>
                                    </div>

                                    <a href="?tab=home&view_business=<?= $b['shop_id'] ?>"
                                        style="display: block; text-align: center; background: #e53935; color: white; padding: 10px; border-radius: 8px; text-decoration: none; font-weight: 600; margin-top: 15px;">
                                        View Details <i class="fas fa-arrow-right"></i>
                                    </a>
                                 <a href="chat_start.php?business_id=<?= (int)$businessId ?>" target="_top" style="text-decoration:none;">

  <button style="background:#3498db; color:white; border:none; padding:10px 14px; border-radius:10px; cursor:pointer; font-weight:700;">
    💬 Message
  </button>
</a>


                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($tab === 'discounts'): ?>

    <div class="card">
        <h3 style="margin-top:0;">Special Discounts</h3>

        <?php if (empty($discountProducts)): ?>
            <p style="color:#666; text-align:center; padding:30px;">No discounted products yet.</p>
        <?php else: ?>
            <?php foreach ($discountProducts as $p): ?>
                <div style="display:flex; gap:15px; align-items:center; padding:15px 0; border-bottom:1px solid #eee;">

                    <div style="width:90px; height:90px; border-radius:12px; overflow:hidden; border:1px solid #ddd; background:#f7f7f7; display:flex; align-items:center; justify-content:center;">
                        <?php if (!empty($p['product_image'])): ?>
                            <img src="<?= htmlspecialchars(asset($p['product_image'])) ?>" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <span style="color:#999;">No Image</span>
                        <?php endif; ?>
                    </div>

                    <div style="flex:1;">
                        <div style="font-weight:900; font-size:1.05rem;">
                            <?= htmlspecialchars($p['product_name']) ?>
                        </div>

                        <div style="color:#666; margin-top:4px;">
                            <?= htmlspecialchars($p['business_name']) ?> • <?= htmlspecialchars($p['category'] ?? '') ?>
                        </div>

                        <div style="margin-top:6px;">
                            <span style="text-decoration:line-through; color:#999; margin-right:8px;">
                                <?= number_format((float)($p['original_price'] ?? 0), 2) ?> TL
                            </span>

                            <span style="font-weight:900; font-size:1.1rem;">
                                <?= number_format((float)($p['discounted_price'] ?? 0), 2) ?> TL
                            </span>

                            <?php if (!empty($p['discount_percent'])): ?>
                                <span style="margin-left:10px; background:#e53935; color:#fff; padding:3px 10px; border-radius:999px; font-size:0.85rem; font-weight:700;">
                                    -<?= (int)$p['discount_percent'] ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <a href="business_detail.php?id=<?= (int)$p['shop_id'] ?>" style="text-decoration:none;">
                            <button class="btn btn-primary" style="padding:10px 14px;">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </a>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php endif; ?>


        </main>
    </div>
</body>

</html>