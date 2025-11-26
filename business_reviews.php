<div class="card">
    <div class="card-header">
        <h3>Customer Reviews</h3>
    </div>
    <div class="card-body">
        <?php if(empty($reviews)): ?>
            <div style="text-align:center; padding:30px; color:#666;">
                <i class="far fa-comment-dots" style="font-size:2rem; margin-bottom:10px;"></i><br>
                No reviews received yet.
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:15px;">
                <?php foreach ($reviews as $r): ?>
                <div style="border-bottom:1px solid #eee; padding-bottom:15px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                        <div>
                            <strong><?php echo htmlspecialchars($r['full_name']); ?></strong>
                            <span style="color:#f1c40f; margin-left:10px;">
                                <?php echo str_repeat('<i class="fas fa-star"></i>', $r['rank']); ?>
                                <?php echo str_repeat('<i class="far fa-star"></i>', 5 - $r['rank']); ?>
                            </span>
                        </div>
                        <small style="color:#999;"><?php echo date('M d, Y h:i A', strtotime($r['time'])); ?></small>
                    </div>
                    <p style="color:#444; margin:0; line-height:1.5;">
                        <?php echo nl2br(htmlspecialchars($r['comments'])); ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>