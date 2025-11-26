<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <h3>Add New Product</h3>
    </div>
    <div class="card-body">
        <form method="POST" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="add_product" value="1">
            
            <div style="flex:1; min-width:200px;">
                <label>Name</label><br>
                <input type="text" name="p_name" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
            </div>
            <div style="flex:1; min-width:150px;">
                <label>Category</label><br>
                <input type="text" name="p_category" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
            </div>
            <div style="flex:1; min-width:100px;">
                <label>Price</label><br>
                <input type="number" step="0.01" name="p_price" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
            </div>
            <div style="flex:2; min-width:300px;">
                <label>Description</label><br>
                <input type="text" name="p_description" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
            </div>
            
            <button type="submit" style="background:#3498db; color:white; padding:10px 15px; border:none; border-radius:4px; height:36px; cursor:pointer;">
                <i class="fas fa-plus"></i> Add
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Product Catalog</h3>
    </div>
    <div class="card-body">
        <table width="100%">
            <thead>
                <tr>
                    <td>ID</td>
                    <td>Name</td>
                    <td>Category</td>
                    <td>Description</td>
                    <td>Price</td>
                    <td>Actions</td>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($products)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:20px;">No products added yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td>#<?php echo $p['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                        <td><span style="background:#eee; padding:2px 8px; border-radius:10px; font-size:0.8rem;"><?php echo htmlspecialchars($p['categories']); ?></span></td>
                        <td><?php echo htmlspecialchars($p['description']); ?></td>
                        <td>
                            <!-- Inline Price Edit -->
                            <form method="POST" style="display:flex; gap:5px;">
                                <input type="hidden" name="update_price" value="1">
                                <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                <input type="number" step="0.01" name="new_price" value="<?php echo $p['product_prices']; ?>" style="width:70px; padding:4px; border:1px solid #ddd; border-radius:4px;">
                                <button type="submit" class="btn-small" style="background:#2ecc71; color:white; border:none; border-radius:3px; cursor:pointer;"><i class="fas fa-check"></i></button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                <input type="hidden" name="delete_product" value="1">
                                <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                <button type="submit" class="btn-small" style="background:#e74c3c; color:white; border:none; border-radius:3px; padding:6px 10px; cursor:pointer;">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>