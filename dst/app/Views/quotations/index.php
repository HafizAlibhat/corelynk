<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Quotations
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card cl-list-page cl-list-table-card data-table">
    <div class="card-header section-header d-flex justify-content-between align-items-center">
        <div>
            <h3 class="section-title">Quotations</h3>
            <div class="section-sub">Manage customer quotations</div>
        </div>
        <div>
            <a href="<?= site_url('quotations/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Create New</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="cl-table table table-striped table-hover data-table-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Quote Number</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th class="text-end">Total</th>
                        <th>Tags</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($quotations)): ?>
                    <tr><td colspan="7" class="text-center text-muted">No quotations found</td></tr>
                <?php else: ?>
                    <?php foreach ($quotations as $q): ?>
                        <tr>
                            <td><?= esc($q['id']) ?></td>
                            <td><?= esc($q['quote_number'] ?? '-') ?></td>
                            <td><?= esc($q['customer_name'] ?? $q['customer_id']) ?></td>
                            <td><?= esc($q['issue_date'] ?? '') ?></td>
                            <td class="text-end"><?= number_format((float)($q['total'] ?? ($q['tax_total'] ?? 0)), 2) ?></td>
                            <td class="doc-tags" data-doc-type="quotation" data-doc-id="<?= (int)$q['id'] ?>">&hellip;</td>
                            <td class="tt-actions">
                                <div class="btn-group" role="group" aria-label="Actions">
                                    <?php 
                                        $quotationIdentifier = (!empty($q['public_id']) && featureEnabled('enable_public_ids')) ? $q['public_id'] : $q['id'];
                                    ?>
                                    <a href="<?= site_url('quotations/view/'. urlencode($quotationIdentifier)) ?>" class="btn btn-sm btn-outline-secondary" title="View" aria-label="View quotation">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </a>
                                    <a href="<?= site_url('document-studio?edit=quotation&id='.$q['id']) ?>" class="btn btn-sm btn-outline-info" title="Edit in Document Studio" aria-label="Edit quotation in Document Studio">
                                        <i class="bi bi-easel" aria-hidden="true"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-manage-tags" data-doc-type="quotation" data-doc-id="<?= (int)$q['id'] ?>" title="Manage Tags" aria-label="Manage quotation tags">
                                        <i class="bi bi-tags" aria-hidden="true"></i>
                                    </button>
                                    <form method="post" action="<?= site_url('quotations/delete/'.$q['id']) ?>" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this quotation?');">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete quotation">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                    <a href="<?= site_url('sales-orders/create-from-quotation/'.$q['id']) ?>" class="btn btn-sm btn-outline-primary" title="Convert to Sales Order" aria-label="Convert quotation to sales order">
                                        <i class="bi bi-arrow-right-square" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.doc-tags').forEach(function(td){
        var id = td.dataset.docId;
        if (!id) return;
        fetch('<?= site_url('document-tags') ?>?document_type=quotation&document_id=' + id)
            .then(r => r.json())
            .then(data => {
                if (!data || !data.success) { td.innerHTML = ''; return; }
                td.innerHTML = '';
                data.data.forEach(function(tag){
                    var span = document.createElement('span');
                    span.className = 'badge bg-light text-dark me-1';
                    span.textContent = tag.name;
                    td.appendChild(span);
                });
            }).catch(function(){ td.innerHTML = ''; });
    });
});
</script>
<?= $this->include('components/tag_modal') ?>
<?= $this->endSection() ?>
