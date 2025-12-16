<div class="card">
    <div class="card-header">
        <h3>Manage Price Lists</h3>
    </div>
    <div class="card-body">
        
        <!-- UPLOAD FORM -->
        <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px dashed #ccc;">
            <form method="POST" enctype="multipart/form-data" style="display: flex; align-items: center; gap: 15px;">
                <input type="hidden" name="upload_pricelist" value="1">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Upload New Price List / Menu Image</label>
                    <input type="file" name="pl_image" accept="image/*" required style="width: 100%; padding: 8px; background: white; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <button type="submit" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; height: 42px; margin-top: 18px;">
                    <i class="fas fa-upload"></i> Upload
                </button>
            </form>
        </div>

        <!-- GALLERY -->
        <h4 style="margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Current Price Lists</h4>
        
        <?php if(empty($priceLists)): ?>
            <p style="color: #777; text-align: center; margin-top: 20px;">No price lists uploaded yet.</p>
        <?php else: ?>
            <div class="pl-grid">
                <?php foreach($priceLists as $pl): ?>
                    <div class="pl-item">
                        <a href="<?php echo htmlspecialchars($pl['image_url']); ?>" target="_blank">
                            <img src="<?php echo htmlspecialchars($pl['image_url']); ?>" alt="Price List">
                        </a>
                        <div style="padding: 10px;">
                            <small style="color: #999; display: block; margin-bottom: 5px;"><?php echo date('M d, Y', strtotime($pl['created_at'])); ?></small>
                            <form method="POST" onsubmit="return confirm('Delete this price list?');">
                                <input type="hidden" name="delete_pricelist" value="1">
                                <input type="hidden" name="pl_id" value="<?php echo $pl['id']; ?>">
                                <button type="submit" style="background: #e74c3c; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; width: 100%;">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>