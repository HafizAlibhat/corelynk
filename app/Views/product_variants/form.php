<?php
// Simple form to create a variant. If used as modal, controller will avoid layout.
?>
<?= isset($product_id) ? '<h5 class="mb-3">Create Variant for Product #' . esc($product_id) . '</h5>' : '' ?>

<?= form_open(base_url('/product-variants/store'), ['id' => 'variantForm']) ?>
    <input type="hidden" name="product_id" value="<?= esc($product_id ?? '') ?>">
    <div class="mb-3">
        <label class="form-label">Variant Name</label>
        <input type="text" name="name" class="form-control" placeholder="e.g., Red / Large">
    </div>

    <div class="mb-3">
        <label class="form-label">Art Number (leave empty to auto-generate)</label>
        <input type="text" name="art_number" class="form-control" placeholder="ART-0001">
        <div class="form-text">If left empty, system will allocate next number from the product's category.</div>
    </div>

    <div class="mb-3 row gx-2">
        <div class="col-md-6">
            <label class="form-label">Price</label>
            <input type="number" step="0.01" name="price" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">Cost</label>
            <input type="number" step="0.01" name="cost" class="form-control">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Weight</label>
        <input type="number" step="0.001" min="0" name="weight" class="form-control" placeholder="Leave empty to inherit from template">
    </div>

    <div class="mb-3">
        <label class="form-label">Attributes (JSON)</label>
        <textarea name="attributes" class="form-control" rows="3" placeholder='{"color":"red","size":"M"}'></textarea>
    </div>

    <div class="d-flex justify-content-end">
        <button class="btn btn-secondary me-2" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Create Variant</button>
    </div>

<?= form_close() ?>
