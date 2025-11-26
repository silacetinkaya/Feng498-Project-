<h2>Business Information</h2>

<form method="post">
    <input type="hidden" name="update_business" value="1">

    <label>Name</label>
    <input type="text" name="name" value="<?= htmlspecialchars($business['name'] ?? '') ?>">

    <label>Address</label>
    <input type="text" name="address" value="<?= htmlspecialchars($business['address'] ?? '') ?>">

    <label>Phone</label>
    <input type="text" name="tel_no" value="<?= htmlspecialchars($business['tel_no'] ?? '') ?>">

    <label>Description</label>
    <textarea name="description" rows="3"><?= htmlspecialchars($business['description'] ?? '') ?></textarea>

    <h3>Business Hours</h3>
    <table>
        <tr>
            <th>Day</th>
            <th>Open</th>
            <th>Close</th>
            <th>Closed</th>
        </tr>
        <?php foreach ($days as $day): ?>
            <tr>
                <td><?= $day ?></td>
                <td>
                    <input type="time" name="open_<?= $day ?>" value="<?= htmlspecialchars($hours[$day]['open']) ?>">
                </td>
                <td>
                    <input type="time" name="close_<?= $day ?>" value="<?= htmlspecialchars($hours[$day]['close']) ?>">
                </td>
                <td style="text-align:center;">
                    <input type="checkbox" name="closed_<?= $day ?>" <?= $hours[$day]['closed'] ? 'checked' : '' ?>>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <button type="submit">Save</button>
</form>
