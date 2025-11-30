<?php
// edit_review.php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

$id = $_GET['id'] ?? null;
if (!$id) die("No ID specified");

// Fetch Review
$stmt = $pdo->prepare("SELECT * FROM reviews WHERE review_id = ?");
$stmt->execute([$id]);
$review = $stmt->fetch();

if (!$review) die("Review not found");

// Fetch Photos
$stmtPhotos = $pdo->prepare("SELECT * FROM photos WHERE review_id = ?");
$stmtPhotos->execute([$id]);
$photos = $stmtPhotos->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Review</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .edit-container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn-save { background: #3498db; color: white; padding: 12px; width: 100%; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
        .photo-grid { display: flex; gap: 10px; margin-bottom: 15px; }
        .photo-item { position: relative; width: 80px; height: 80px; }
        .photo-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 5px; }
        .btn-del-photo { position: absolute; top: -5px; right: -5px; background: red; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; cursor: pointer; }
    </style>
</head>
<body>

    <div class="edit-container">
        <h2>Edit Review #<?php echo $review['review_id']; ?></h2>
        
        <form action="admin_process.php" method="POST" enctype="multipart/form-data">
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

            <div class="form-group">
                <label>Current Photos (<?php echo count($photos); ?>/4)</label>
                <div class="photo-grid">
                    <?php foreach($photos as $p): ?>
                        <div class="photo-item">
                            <img src="<?php echo htmlspecialchars($p['image_url']); ?>" alt="Review Photo">
                            <a href="admin_process.php?action=delete_review_photo&id=<?php echo $p['photo_id']; ?>&review_id=<?php echo $review['review_id']; ?>" 
                               onclick="return confirm('Remove photo?');">
                                <button type="button" class="btn-del-photo">x</button>
                            </a>
                        </div>
                    <?php endforeach; ?>
                    <?php if(count($photos) == 0) echo "<span style='color:#999;'>No photos attached.</span>"; ?>
                </div>
            </div>

            <?php if(count($photos) < 4): ?>
            <div class="form-group">
                <label>Add More Photos (Max <?php echo 4 - count($photos); ?> more)</label>
                <input type="file" name="review_images[]" multiple accept="image/*">
            </div>
            <?php else: ?>
                <div style="color:orange; margin-bottom:15px; font-size:0.9rem;">Max photo limit reached. Delete some to add new ones.</div>
            <?php endif; ?>

            <button type="submit" class="btn-save">Update Review</button>
        </form>
        
        <br>
        <a href="review_management.php" style="text-align:center; display:block; color:#666;">Cancel</a>
    </div>

</body>
</html>