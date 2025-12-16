<div class="card">
    <div class="card-header">
        <h3>Customer Reviews</h3>
    </div>
    <div class="card-body">
        <?php if(empty($reviews)): ?>
            <div style="text-align:center; padding:30px; color:#666;">No reviews yet.</div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:20px;">
                <?php foreach ($reviews as $r): ?>
                <div style="border:1px solid #eee; padding:15px; border-radius:8px;">
                    
                    <div style="display:flex; justify-content:space-between;">
                        <strong><?php echo htmlspecialchars($r['full_name']); ?></strong>
                        <span style="color:#f1c40f;">
                            <?php echo str_repeat('★', $r['rank']); ?>
                        </span>
                    </div>
                    
                    <p style="margin:10px 0; color:#444;"><?php echo nl2br(htmlspecialchars($r['comments'])); ?></p>
                    
                    <!-- PENDING REVIEW NOTICE -->
                    <?php if($r['is_approved'] != 't' && $r['is_approved'] !== true): ?>
                        <div style="background:#fff3cd; color:#856404; padding:5px; font-size:0.8rem; border-radius:3px; margin-bottom:10px;">
                            <i class="fas fa-clock"></i> This review is waiting for admin approval before going public.
                        </div>
                    <?php endif; ?>

                    <!-- RESPONSE SECTION -->
                    <?php if (!empty($r['response_text'])): ?>
                        <div style="background:#f9f9f9; padding:10px; border-left:4px solid #3498db; margin-top:10px;">
                            <div style="display:flex; justify-content:space-between;">
                                <strong style="color:#2c3e50;"><i class="fas fa-reply"></i> Your Response:</strong>
                                <!-- STATUS OF RESPONSE -->
                                <?php if($r['resp_approved'] == 't' || $r['resp_approved'] === true): ?>
                                    <span style="background:#d4edda; color:#155724; padding:2px 6px; font-size:0.7rem; border-radius:3px;">Live</span>
                                <?php else: ?>
                                    <span style="background:#fff3cd; color:#856404; padding:2px 6px; font-size:0.7rem; border-radius:3px;">Pending Approval</span>
                                <?php endif; ?>
                            </div>
                            <p style="margin:5px 0; font-size:0.9rem;"><?php echo nl2br(htmlspecialchars($r['response_text'])); ?></p>
                        </div>
                    <?php else: ?>
                        <div style="margin-top:10px; border-top:1px dashed #eee; padding-top:10px;">
                            <button onclick="document.getElementById('reply-form-<?php echo $r['review_id']; ?>').style.display='block'; this.style.display='none';" 
                                    style="background:none; border:1px solid #3498db; color:#3498db; padding:5px 10px; border-radius:4px; cursor:pointer;">
                                Reply
                            </button>
                            <form id="reply-form-<?php echo $r['review_id']; ?>" method="POST" style="display:none; margin-top:10px;">
                                <input type="hidden" name="respond_to_review" value="1">
                                <input type="hidden" name="review_id" value="<?php echo $r['review_id']; ?>">
                                <textarea name="response_text" rows="3" required style="width:100%; padding:10px; border:1px solid #ddd;"></textarea>
                                <button type="submit" style="background:#3498db; color:white; border:none; padding:8px 15px; margin-top:5px; cursor:pointer;">Post Response</button>
                            </form>
                        </div>
                    <?php endif; ?>

                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>