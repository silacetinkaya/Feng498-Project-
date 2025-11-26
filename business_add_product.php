<h2>Add New Product</h2>

<form method="post">
    <input type="hidden" name="add_product" value="1">

    <label>Name</label>
    <input type="text" name="p_name" required>

    <label>Description</label>
    <textarea name="p_description"></textarea>

    <label>Category</label>
    <input type="text" name="p_category">

    <label>Price</label>
    <input type="number" step="0.01" name="p_price" required>

    <label>
        <input type="checkbox" name="p_available" checked>
        Active
    </label>

    <button type="submit">Add Product</button>
</form>
