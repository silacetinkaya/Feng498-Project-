<?php
// review_management.php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin.html");
    exit;
}

$search = $_GET['search'] ?? '';
$rankFilter = $_GET['rank'] ?? 'all';

// Fetch Reviews with Photo URLs (Using string_agg for Postgres to comma separate them)
// Postgres uses string_agg, MySQL uses GROUP_CONCAT
$sql = "SELECT r.*, u.full_name as user_name, b.name as business_name,
        string_agg(p.image_url, ',') as photo_urls
        FROM reviews r 
        LEFT JOIN users u ON r.user_id = u.id 
        LEFT JOIN business b ON r.business_id = b.shop_id 
        LEFT JOIN photos p ON r.review_id = p.review_id
        WHERE (b.name ILIKE :search OR u.full_name ILIKE :search OR r.comments ILIKE :search)";

$params = [':search' => "%$search%"];

if ($rankFilter !== 'all') {
    $sql .= " AND r.rank = :rank";
    $params[':rank'] = $rankFilter;
}

$sql .= " GROUP BY r.review_id, u.full_name, b.name ORDER BY r.time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review Management</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .search-box { display: flex; gap: 10px; }
        .search-box input, .search-box select { padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        
        .btn-action { padding: 8px 12px; border-radius: 5px; color: white; cursor: pointer; border: none; font-size: 0.9rem; text-decoration:none; display:inline-block;}
        .btn-create { background: #2ecc71; }
        .btn-edit { background: #3498db; }
        .btn-delete { background: #e74c3c; }

        .star-rating { color: #f1c40f; }
        .review-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 2px; border: 1px solid #ddd; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 500px; border-radius: 10px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo"><i class="fas fa-shield-alt"></i> AdminPanel</div>
    <ul class="nav-links">
        <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="user_management.php"><i class="fas fa-users"></i> User Management</a></li>
        <li><a href="category_management.php"><i class="fas fa-tags"></i> Categories</a></li>
        <li><a href="user_management.php?tab=business"><i class="fas fa-briefcase"></i> Businesses</a></li>
        <li><a href="review_management.php" class="active"><i class="fas fa-star"></i> Reviews</a></li>
        <li><a href="report_management.php"><i class="fas fa-flag"></i> Reports</a></li>
        <li><a href="#"><i class="fas fa-trophy"></i> Best of Day</a></li>
    </ul>
    <div class="logout-section"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
</div>

<div class="main-content">
    <header>
        <h2>Review Management</h2>
        <div class="system-status"><span class="status-dot"></span>System Operational</div>
    </header>

    <div class="controls">
        <form class="search-box" method="GET">
            <input type="text" name="search" placeholder="Search Review, User, Business..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="rank">
                <option value="all">All Ratings</option>
                <option value="5" <?php if($rankFilter=='5') echo 'selected'; ?>>5 Stars</option>
                <option value="4" <?php if($rankFilter=='4') echo 'selected'; ?>>4 Stars</option>
                <option value="3" <?php if($rankFilter=='3') echo 'selected'; ?>>3 Stars</option>
                <option value="2" <?php if($rankFilter=='2') echo 'selected'; ?>>2 Stars</option>
                <option value="1" <?php if($rankFilter=='1') echo 'selected'; ?>>1 Star</option>
            </select>
            <button type="submit" class="btn-action btn-edit">Filter</button>
        </form>
        <button class="btn-action btn-create" onclick="document.getElementById('createReviewModal').style.display='block'">
            <i class="fas fa-plus"></i> Add Review
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <table width="100%">
                <thead>
                    <tr>
                        <td>ID</td>
                        <td>Business</td>
                        <td>User</td>
                        <td>Rating</td>
                        <td>Comment</td>
                        <td>Photos</td>
                        <td>Actions</td>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $r): ?>
                    <tr>
                        <td>#<?php echo $r['review_id']; ?></td>
                        <td><?php echo htmlspecialchars($r['business_name'] ?? 'Unknown'); ?> <small>(ID:<?php echo $r['business_id']; ?>)</small></td>
                        <td><?php echo htmlspecialchars($r['user_name'] ?? 'Unknown'); ?></td>
                        <td>
                            <span class="star-rating">
                                <?php echo str_repeat('<i class="fas fa-star"></i>', $r['rank']); ?>
                                <?php echo str_repeat('<i class="far fa-star"></i>', 5 - $r['rank']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars(substr($r['comments'], 0, 40)) . (strlen($r['comments'])>40?'...':''); ?></td>
                        <td>
                            <?php 
                            if($r['photo_urls']) {
                                $photos = explode(',', $r['photo_urls']);
                                foreach($photos as $p) {
                                    echo '<img src="'.htmlspecialchars($p).'" class="review-thumb">';
                                }
                            } else {
                                echo '<span style="color:#ccc; font-size:0.8rem;">No photos</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="edit_review.php?id=<?php echo $r['review_id']; ?>" class="btn-action btn-edit"><i class="fas fa-edit"></i></a>
                            
                            <a href="admin_process.php?action=delete_review&id=<?php echo $r['review_id']; ?>" 
                               class="btn-action btn-delete"
                               onclick="return confirm('Delete this review?');">
                               <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- CREATE REVIEW MODAL WITH PHOTOS -->
<div id="createReviewModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('createReviewModal').style.display='none'">&times;</span>
        <h2>Add Manual Review</h2>
        <form action="admin_process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_review">
            
            <div class="form-group">
                <label>Business ID</label>
                <input type="number" name="business_id" required placeholder="ID of the shop">
            </div>
            
            <div class="form-group">
                <label>User ID</label>
                <input type="number" name="user_id" required placeholder="ID of the user">
            </div>

            <div class="form-group">
                <label>Rating (1-5)</label>
                <select name="rank">
                    <option value="5">5 Stars</option>
                    <option value="4">4 Stars</option>
                    <option value="3">3 Stars</option>
                    <option value="2">2 Stars</option>
                    <option value="1">1 Star</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Comment</label>
                <textarea name="comments" rows="3" required></textarea>
            </div>

            <div class="form-group">
                <label>Photos (Max 4)</label>
                <input type="file" name="review_images[]" multiple accept="image/*">
                <small style="color:#666;">Hold Ctrl/Cmd to select multiple images.</small>
            </div>

            <button type="submit" class="btn-action btn-create" style="width:100%">Submit Review</button>
        </form>
    </div>
</div>

<script>
    window.onclick = function(event) {
        if (event.target.className === 'modal') {
            event.target.style.display = "none";
        }
    }
</script>

</body>
</html>