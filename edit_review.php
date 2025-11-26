<?php
// edit_review.php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

$id = $_GET['id'] ?? null;
if (!$id) die("No ID specified");

// Fetch Review Data
$stmt = $pdo->prepare("SELECT * FROM reviews WHERE review_id = ?");
$stmt->execute([$id]);
$review = $stmt->fetch();

if (!$review) die("Review not found");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Review</title>
    <link rel="stylesheet" href="admin.css">
    <style>
        .edit-container { max-width: 500px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn-save { background: #3498db; color: white; padding: 12px; width: 100%; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
    </style>
</head>
<body>

    <div class="edit-container">
        <h2>Edit Review #<?php echo $review['review_id']; ?></h2>
        
        <form action="admin_process.php" method="POST">
            <input type="hidden" name="action" value="update_review">
            <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">

            <div class="form-group">
                <label>Rating</label>
                <select name="rank">
                    <option value="5" <?php if($review['rank']==5) echo 'selected'; ?>>5 Stars</option>
                    <option value="4" <?php if($review['rank']==4) echo 'selected'; ?>>4 Stars</option>
                    <option value="3" <?php if($review['rank']==3) echo 'selected'; ?>>3 Stars</option>
                    <option value="2" <?php if($review['rank']==2) echo 'selected'; ?>>2 Stars</option>
                    <option value="1" <?php if($review['rank']==1) echo 'selected'; ?>>1 Star</option>
                </select>
            </div>

            <div class="form-group">
                <label>Comment</label>
                <textarea name="comments" rows="6"><?php echo htmlspecialchars($review['comments']); ?></textarea>
            </div>

            <button type="submit" class="btn-save">Update Review</button>
        </form>
        
        <br>
        <a href="review_management.php" style="text-align:center; display:block; color:#666;">Cancel</a>
    </div>

</body>
</html>