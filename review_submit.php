<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php?error=login_required");
  exit;
}

$business_id = (int)($_POST['business_id'] ?? 0);
$rank        = (int)($_POST['rank'] ?? 0);
$comments    = trim($_POST['comments'] ?? '');
$user_id     = (int)$_SESSION['user_id'];

if ($business_id <= 0 || $rank < 1 || $rank > 5 || $comments === '') {
  header("Location: business_detail.php?id={$business_id}&error=invalid_review");
  exit;
}

// aynı kullanıcı aynı işletmeye 2. kez review atamasın (constraint var ama biz de yakalayalım)
$check = $pdo->prepare("SELECT 1 FROM reviews WHERE user_id = ? AND business_id = ?");
$check->execute([$user_id, $business_id]);
if ($check->fetchColumn()) {
  header("Location: business_detail.php?id={$business_id}&error=already_reviewed");
  exit;
}

$stmt = $pdo->prepare("
  INSERT INTO reviews (comments, rank, user_id, business_id, is_approved)
  VALUES (?, ?, ?, ?, FALSE)
");
$stmt->execute([$comments, $rank, $user_id, $business_id]);

header("Location: business_detail.php?id={$business_id}&msg=review_submitted");
exit;
