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
    $stmt = $pdo->prepare("SELECT o.offered_price, o.status, b.name AS business_name
                            FROM offers o LEFT JOIN business b ON b.shop_id=o.business_id
                            WHERE o.user_id=?");
    $stmt->execute([$userId]);
    $myOffers = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

                <?php foreach ($myOffers as $o): ?>
                    <div class="card">
                        <?= htmlspecialchars($o['business_name']) ?> – <?= $o['offered_price'] ?> TL (<?= $o['status'] ?>)
                    </div>
                <?php endforeach; ?>

            <?php elseif ($tab === 'messages'): ?>

                <?php foreach ($myChats as $c): ?>
                    <div class="card">
                        <?= htmlspecialchars($c['business_name']) ?>
                    </div>
                <?php endforeach; ?>
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
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($tab === 'discounts'): ?>

                <div class="card">
                    <h3>Special Discounts</h3>
                    <p style="color: #666; text-align: center; padding: 40px;">Coming soon...</p>
                </div>
            <?php endif; ?>

        </main>
    </div>
</body>

</html>