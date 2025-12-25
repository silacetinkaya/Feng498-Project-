<?php
// -------------------------------------------
// Güvenlik: Business ID gelmeli
// -------------------------------------------
if (!isset($businessId)) {
    die("businessId missing.");
}

// -------------------------------------------
// PAGINATION AYARLARI
// -------------------------------------------
$itemsPerPage = 5;
$prodPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// toplam ürün
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM products WHERE business_id = :bid");
$stmtCount->execute(['bid' => $businessId]);
$totalProducts = $stmtCount->fetchColumn();

$totalProdPages = max(1, ceil($totalProducts / $itemsPerPage));
$offset = ($prodPage - 1) * $itemsPerPage;

// -------------------------------------------
// ÜRÜNLERİ LIMIT ile ÇEK
// -------------------------------------------
$stmt = $pdo->prepare("
    SELECT *
    FROM products
    WHERE business_id = :bid
    ORDER BY id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':bid', $businessId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------------------------------
// KATEGORİLERİ ÇEK
// -------------------------------------------
$categoryQuery = $pdo->query("SELECT DISTINCT type FROM categories ORDER BY type ASC");
$categoryList = $categoryQuery->fetchAll(PDO::FETCH_COLUMN);
?>

<!-- ============================
     ADD PRODUCT FORM
============================ -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <h3>Add New Product</h3>
    </div>
    <div class="card-body">

        <form method="POST" enctype="multipart/form-data"
              style="display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap;">
            
            <input type="hidden" name="add_product" value="1">

            <div style="flex:1; min-width:200px;">
                <label>Name</label>
                <input type="text" name="p_name" required class="form-control">
            </div>

            <div style="flex:1; min-width:150px;">
                <label>Category</label>
                <select name="p_category" required class="form-control">
                    <option value="" disabled selected>Select...</option>
                    <?php foreach ($categoryList as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>">
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex:0 0 120px;">
                <label>Price</label>
                <input type="number" name="p_price" step="0.01" required class="form-control">
            </div>

            <div style="flex:1; min-width:200px;">
                <label>Photo</label>
                <input type="file" name="p_image" accept="image/*" class="form-control">
            </div>

            <div style="flex:2; min-width:300px;">
                <label>Description</label>
                <input type="text" name="p_description" class="form-control">
            </div>

            <div style="width:100%; margin-top:5px;">
                <input type="checkbox" id="neg" name="p_negotiable">
                <label for="neg">Price is Negotiable</label>
            </div>

            <button type="submit" class="btn btn-primary">
                Add
            </button>
        </form>
    </div>
</div>


<!-- ============================
     PRODUCT LIST
============================ -->
<div class="card">
    <div class="card-header">
        <h3>
            Product Catalog (Page <?= $prodPage ?> of <?= $totalProdPages ?>)
        </h3>
    </div>

    <div class="card-body">
        <table width="100%">
            <thead>
                <tr>
                    <th width="80">Image</th>
                    <th>Details</th>
                    <th>Category</th>
                    <th>Pricing</th>

                    <!-- FAVORİ BUTONU -->
                    <th>Fav</th>

                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding:20px;">
                        No products yet.
                    </td>
                </tr>
            <?php else: ?>

                <?php foreach ($products as $p): ?>
                    <tr>

                        <!-- Image -->
                        <td>
                            <?php if (!empty($p['image_url'])): ?>
                                <img src="<?= htmlspecialchars($p['image_url']) ?>"
                                     style="width:60px; height:60px; object-fit:cover; border-radius:5px;">
                            <?php else: ?>
                                <div style="width:60px;height:60px;background:#eee;border-radius:5px;
                                            display:flex;align-items:center;justify-content:center;color:#777;">
                                    No Image
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Details -->
                        <td>
                            <strong><?= htmlspecialchars($p['name']) ?></strong><br>
                            <small><?= htmlspecialchars($p['description']) ?></small>
                        </td>

                        <!-- Category -->
                        <td>
                            <span style="background:#eee;padding:4px 8px;border-radius:8px;">
                                <?= htmlspecialchars($p['categories']) ?>
                            </span>
                        </td>

                        <!-- Price Update -->
                        <td>
                            <form method="POST" style="display:flex;gap:6px;">
                                <input type="hidden" name="update_price" value="1">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">

                                <input type="number" step="0.01" name="new_price"
                                       value="<?= $p['product_prices'] ?>"
                                       style="width:70px; padding:4px;">

                                <button type="submit" style="background:#2ecc71; color:white; border-radius:4px;">
                                    ✔
                                </button>
                            </form>

                            <?php if ($p['is_negotiable'] === true || $p['is_negotiable'] === 't'): ?>
                                <small style="color:orange;">Negotiable</small>
                            <?php endif; ?>
                        </td>

                        <!-- FAVORİ -->
                        <td style="text-align:center;">
                            <a href="add_fav_product.php?id=<?= $p['id'] ?>" 
                               style="color:#e53935; font-size:20px; text-decoration:none;">
                                ♥
                            </a>
                        </td>

                        <!-- Delete -->
                        <td>
                            <form method="POST" onsubmit="return confirm('Delete this product?');">
                                <input type="hidden" name="delete_product" value="1">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">

                                <button type="submit"
                                        style="background:#e74c3c; color:white; border-radius:4px;">
                                    🗑
                                </button>
                            </form>
                        </td>

                    </tr>
                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>
        </table>

        <!-- PAGINATION -->
        <?php if ($totalProdPages > 1): ?>
            <div style="margin-top:20px; display:flex; gap:6px;">
                <?php if ($prodPage > 1): ?>
                    <a href="?tab=products&page=<?= $prodPage - 1 ?>" style="padding:6px 12px; background:#eee;">
                        « Prev
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalProdPages; $i++): ?>
                    <a href="?tab=products&page=<?= $i ?>"
                       style="padding:6px 12px;
                       <?= $i == $prodPage ? 'background:#e53935;color:white;' : 'background:#f3f3f3;' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($prodPage < $totalProdPages): ?>
                    <a href="?tab=products&page=<?= $prodPage + 1 ?>" style="padding:6px 12px; background:#eee;">
                        Next »
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>
