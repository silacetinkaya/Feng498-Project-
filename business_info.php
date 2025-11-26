<div class="card">
    <div class="card-header">
        <h3>Update Details</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="update_business" value="1">

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div>
                    <div class="form-group">
                        <label>Business Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($business['name']); ?>" required class="form-control" style="width:100%; padding:10px; margin-bottom:10px; border:1px solid #ddd; border-radius:5px;">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($business['address']); ?>" class="form-control" style="width:100%; padding:10px; margin-bottom:10px; border:1px solid #ddd; border-radius:5px;">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="tel_no" value="<?php echo htmlspecialchars($business['tel_no']); ?>" class="form-control" style="width:100%; padding:10px; margin-bottom:10px; border:1px solid #ddd; border-radius:5px;">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="5" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;"><?php echo htmlspecialchars($business['description']); ?></textarea>
                    </div>
                </div>

                <div>
                    <h4>Operating Hours</h4>
                    <table class="hours-table" width="100%">
                        <thead>
                            <tr style="text-align:left; color:#666;">
                                <th>Day</th>
                                <th>Open</th>
                                <th>Close</th>
                                <th>Closed?</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($days as $day): ?>
                            <tr>
                                <td><strong><?php echo $day; ?></strong></td>
                                <td><input type="time" name="open_<?php echo $day; ?>" value="<?php echo $hours[$day]['open']; ?>"></td>
                                <td><input type="time" name="close_<?php echo $day; ?>" value="<?php echo $hours[$day]['close']; ?>"></td>
                                <td>
                                    <input type="checkbox" name="closed_<?php echo $day; ?>" <?php echo $hours[$day]['closed'] ? 'checked' : ''; ?>>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <button type="submit" style="background:#2ecc71; color:white; padding:10px 20px; border:none; border-radius:5px; margin-top:20px; cursor:pointer;">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </form>
    </div>
</div>