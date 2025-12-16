<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <h3>Add New Product</h3>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" style="display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="add_product" value="1">
            <div style="flex:1; min-width:200px;">
                <label>Name</label><br>
                <input type="text" name="p_name" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
            </div>
            <div style="flex:1; min-width:150px;">
                <label>Category</label><br>
                <select name="p_category" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; background:white;">
                    <option value="" disabled selected>Select...</option>
                    <?php foreach($categoryList as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:0 0 120px;">
                <label>Price</label><br>
                <input type="number" step="0.01" name="p_price" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
            </div>
            <div style="flex:1; min-width:200px;">
                <label>Photo</label><br>
                <input type="file" name="p_image" accept="image/*" style="width:100%; padding:5px; border:1px solid #ddd; background:#f9f9f9; border-radius:4px;">
            </div>
            <div style="flex:2; min-width:300px;">
                <label>Description</label><br>
                <input type="text" name="p_description" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
            </div>
            <div style="width:100%; display:flex; gap:10px; align-items:center; margin-top:5px;">
                <input type="checkbox" id="neg" name="p_negotiable"> 
                <label for="neg" style="margin:0; font-weight:normal;">Price is Negotiable</label>
            </div>
            <button type="submit" style="background:#3498db; color:white; padding:10px 15px; border:none; border-radius:4px; height:36px; cursor:pointer; margin-top:10px;">
                <i class="fas fa-plus"></i> Add
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Product Catalog (Page <?php echo $prodPage; ?> of <?php echo $totalProdPages ?: 1; ?>)</h3>
    </div>
    <div class="card-body">
        <table width="100%">
            <thead>
                <tr>
                    <td>Image</td>
                    <td>Details</td>
                    <td>Category</td>
                    <td>Pricing</td>
                    <td>Action</td>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($products)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:20px;">No products yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td>
                            <?php if(!empty($p['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($p['image_url']); ?>" class="product-img-thumb" alt="Img">
                            <?php else: ?>
                                <div class="product-img-thumb" style="background:#eee; display:flex; align-items:center; justify-content:center; color:#999;"><i class="fas fa-image"></i></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($p['name']); ?></strong><br>
                            <small style="color:#666;"><?php echo htmlspecialchars($p['description']); ?></small>
                        </td>
                        <td><span style="background:#eee; padding:3px 8px; border-radius:10px; font-size:0.8rem;"><?php echo htmlspecialchars($p['categories']); ?></span></td>
                        <td>
                            <?php echo htmlspecialchars($p['product_prices']); ?>
                            <?php if($p['is_negotiable'] === true || $p['is_negotiable'] === 't'): ?>
                                <small style="color:orange; display:block;"><i class="fas fa-handshake"></i> Negotiable</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Delete?');">
                                <input type="hidden" name="delete_product" value="1">
                                <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                <button type="submit" style="background:#e74c3c; color:white; border:none; padding:5px 10px; border-radius:3px; cursor:pointer;"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- PAGINATION UI -->
        <?php if ($totalProdPages > 1): ?>
        <div class="pagination">
            <?php 
            // Previous Link
            if($prodPage > 1) {
                echo '<a href="?tab=products&prod_page='.($prodPage-1).'">&laquo; Prev</a>';
            }
            // Page Numbers
            for($i = 1; $i <= $totalProdPages; $i++) {
                $active = ($prodPage == $i) ? 'active' : '';
                echo '<a href="?tab=products&prod_page='.$i.'" class="'.$active.'">'.$i.'</a>';
            }
            // Next Link
            if($prodPage < $totalProdPages) {
                echo '<a href="?tab=products&prod_page='.($prodPage+1).'">Next &raquo;</a>';
            }
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>