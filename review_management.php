<?php
// review_management.php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin.html");
    exit;
}

// Pagination & Filters
$search = $_GET['search'] ?? '';
$rankFilter = $_GET['rank'] ?? 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if ($page < 1) $page = 1;
if ($limit < 1) $limit = 20;
$offset = ($page - 1) * $limit;

// 1. Count
$countSql = "SELECT COUNT(*)
             FROM reviews r
             LEFT JOIN users u ON r.user_id = u.id
             LEFT JOIN business b ON r.business_id = b.shop_id
             WHERE (b.name ILIKE :search OR u.full_name ILIKE :search OR r.comments ILIKE :search)";

$params = [':search' => "%$search%"];

if ($rankFilter !== 'all') {
    $countSql .= " AND r.rank = :rank";
    $params[':rank'] = $rankFilter;
}

$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// 2. Fetch Data (MERGED: now includes is_approved fields)
$sql = "SELECT r.*, 
        u.full_name AS user_name, 
        b.name AS business_name,
        rr.response_text, 
        rr.created_at AS response_date, 
        rr.is_approved AS response_approved,
        string_agg(p.image_url, ',') AS photo_urls
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN business b ON r.business_id = b.shop_id
        LEFT JOIN photos p ON r.review_id = p.review_id
        LEFT JOIN review_responses rr ON r.review_id = rr.review_id
        WHERE (b.name ILIKE :search OR u.full_name ILIKE :search OR r.comments ILIKE :search)";

if ($rankFilter !== 'all') {
    $sql .= " AND r.rank = :rank";
}

$sql .= " GROUP BY r.review_id, u.full_name, b.name, rr.response_text, rr.created_at, rr.is_approved
          ORDER BY r.time DESC 
          LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':search', "%$search%");
if ($rankFilter !== 'all') $stmt->bindValue(':rank', $rankFilter);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
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
/* BASIC BADGES */
.badge { padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
.badge-pending { background: #ffeeba; color: #856404; }
.badge-approved { background: #d4edda; color: #155724; }

/* APPROVAL BUTTONS */
.btn-approve { background:#28a745; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer; font-size:0.8rem; }
.btn-reject { background:#dc3545; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer; font-size:0.8rem; }
.response-cell { background:#fcfcfc; border-left:3px solid #e0e0e0; }
.review-thumb { width:40px; height:40px; object-fit:cover; margin-right:2px; border-radius:4px; border:1px solid #ddd; }
.star-rating { color:#f1c40f; }

/* layout preserved from the second version */
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
    </ul>
    <div class="logout-section"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
</div>

<div class="main-content">
<header>
    <h2>Review Management</h2>
</header>

<!-- FILTER BAR -->
<div class="controls">
    <form class="search-box" method="GET">
         <select name="limit" onchange="this.form.submit()" style="width:80px;">
            <option value="20" <?php echo $limit==20?'selected':''; ?>>20</option>
            <option value="50" <?php echo $limit==50?'selected':''; ?>>50</option>
            <option value="100" <?php echo $limit==100?'selected':''; ?>>100</option>
        </select>

        <input type="text" name="search" placeholder="Search Review, User, Business..." 
               value="<?php echo htmlspecialchars($search); ?>">

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
</div>

<!-- TABLE -->
<div class="card">
<div class="card-body">

<table width="100%">
<thead>
<tr>
    <td width="5%">ID</td>
    <td width="15%">Business / User</td>
    <td width="30%">User Review</td>
    <td width="30%">Business Response</td>
    <td width="10%">Photos</td>
    <td width="10%">Actions</td>
</tr>
</thead>

<tbody>
<?php foreach ($reviews as $r): ?>
<tr>
<td valign="top">#<?php echo $r['review_id']; ?></td>

<!-- BUSINESS + USER -->
<td valign="top">
    <strong><?php echo htmlspecialchars($r['business_name'] ?? 'Unknown'); ?></strong><br>
    <small>User: <?php echo htmlspecialchars($r['user_name']); ?></small><br>

    <?php if ($r['is_approved'] == 't'): ?>
        <span class="badge badge-approved">Live</span>
    <?php else: ?>
        <span class="badge badge-pending">Pending</span>
    <?php endif; ?>
</td>

<!-- USER REVIEW -->
<td valign="top">
    <div class="star-rating">
        <?php echo str_repeat('<i class="fas fa-star"></i>', $r['rank']); ?>
        <?php echo str_repeat('<i class="far fa-star"></i>', 5 - $r['rank']); ?>
    </div>
    <p><?php echo htmlspecialchars($r['comments']); ?></p>
    <small><?php echo date('M d, Y', strtotime($r['time'])); ?></small><br><br>

    <?php if ($r['is_approved'] != 't'): ?>
        <a href="admin_process.php?action=approve_review&id=<?php echo $r['review_id']; ?>">
            <button class="btn-approve">Approve Review</button>
        </a>
    <?php endif; ?>
</td>

<!-- BUSINESS RESPONSE -->
<td valign="top" class="response-cell">

    <?php if (!empty($r['response_text'])): ?>

        <?php if ($r['response_approved'] == 't'): ?>
            <span class="badge badge-approved">Live</span>
        <?php else: ?>
            <span class="badge badge-pending">Pending</span>
        <?php endif; ?>

        <div style="margin-top:5px;">
            <i class="fas fa-reply"></i>
            "<?php echo htmlspecialchars($r['response_text']); ?>"
        </div>

        <small><?php echo date('M d, Y', strtotime($r['response_date'])); ?></small><br><br>

        <?php if ($r['response_approved'] != 't'): ?>
            <a href="admin_process.php?action=approve_response&review_id=<?php echo $r['review_id']; ?>">
                <button class="btn-approve">Approve Reply</button>
            </a>
        <?php endif; ?>

        <a href="admin_process.php?action=delete_response&review_id=<?php echo $r['review_id']; ?>"
           onclick="return confirm('Delete this reply?');">
            <button class="btn-reject"><i class="fas fa-trash"></i></button>
        </a>

    <?php else: ?>
        <span style="color:#ccc">No response</span>
    <?php endif; ?>
</td>

<!-- PHOTOS -->
<td valign="top">
    <?php 
    if ($r['photo_urls']) {
        foreach (explode(',', $r['photo_urls']) as $p) {
            echo '<img src="'.htmlspecialchars($p).'" class="review-thumb">';
        }
    } else {
        echo '<span style="color:#ccc;font-size:0.8rem;">No photos</span>';
    }
    ?>
</td>

<!-- DELETE REVIEW -->
<td valign="top">
    <a href="admin_process.php?action=delete_review&id=<?php echo $r['review_id']; ?>" 
       class="btn-reject" 
       onclick="return confirm('Delete this entire review?');">
       Delete
    </a>
</td>

</tr>
<?php endforeach; ?>
</tbody>
</table>

<!-- PAGINATION -->
<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php 
        $params = "&search=$search&rank=$rankFilter&limit=$limit";
        if($page > 1) echo "<a href='?page=".($page-1)."$params'>&laquo; Prev</a>";
        for($i=1; $i<=$totalPages; $i++) {
            echo "<a href='?page=$i$params' class='".($i==$page?'active':'')."'>$i</a>";
        }
        if($page < $totalPages) echo "<a href='?page=".($page+1)."$params'>Next &raquo;</a>";
        ?>
    </div>
<?php endif; ?>

</div>
</div>
</div>

</body>
</html>
