<h2>My Products</h2>

<?php if (empty($products)): ?>
    <p>No products yet.</p>
<?php else: ?>
<table>
    <tr>
        <th>ID</th>
        <th>Name / Category</th>
        <th>Description</th>
        <th>Price</th>
        <th>Edit Price</th>
        <th>Delete</th>
    </tr>

    <?php foreach ($products as $p): ?>
        <tr>
            <td><?= $p['id'] ?></td>
            <td>
                <strong><?= htmlspecialchars($p['name']) ?></strong><br>
                <small><?= htmlspecialchars($p['categories'] ?? '') ?></small>
            </td>
            <td><?= htmlspecialchars($p['description'] ?? '') ?></td>
            <td><?= htmlspecialchars($p['product_prices']) ?></td>

            <td>
                <form method="post" style="display:flex;gap:6px;">
                    <input type="hidden" name="update_price" value="1">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <input type="number" step="0.01" name="new_price" value="<?= $p['product_prices'] ?>" style="width:80px">
                    <button type="submit">Save</button>
                </form>
            </td>

            <td>
                <form method="post" onsubmit="return confirm('Delete this product?');">
                    <input type="hidden" name="delete_product" value="1">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <button style="background:#d00;color:white;">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<h3>Latest Reviews for this Business</h3>

<?php if (empty($reviews)): ?>
    <p>No reviews yet.</p>
<?php else: ?>
    <?php foreach ($reviews as $r): ?>
        <div style="border-bottom:1px solid #ccc;margin-bottom:8px;padding-bottom:4px;">
            <strong><?= htmlspecialchars($r['full_name'] ?? 'User') ?></strong>
            — <?= (int)$r['rank'] ?> ★<br>
            <?= nl2br(htmlspecialchars($r['comments'] ?? '')) ?><br>
            <small><?= htmlspecialchars($r['time']) ?></small>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
