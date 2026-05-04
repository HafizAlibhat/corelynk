<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Quotations
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header section-header d-flex justify-content-between align-items-center">
        <div>
            <h3 class="section-title">Quotations</h3>
            <div class="section-sub">Manage customer quotations</div>
        </div>
        <div>
            <a href="<?= site_url('quotations/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Create New</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Quote Number</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th class="text-end">Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($quotations)): ?>
                    <tr><td colspan="6" class="text-center text-muted">No quotations found</td></tr>
                <?php else: ?>
                    <?php foreach ($quotations as $q): ?>
                        <tr>
                            <td><?= esc($q['id']) ?></td>
                            <td><?= esc($q['quote_number'] ?? '-') ?></td>
                            <td><?= esc($q['customer_name'] ?? $q['customer_id']) ?></td>
                            <td><?= esc($q['issue_date'] ?? '') ?></td>
                            <td class="text-end"><?= number_format((float)($q['total'] ?? ($q['tax_total'] ?? 0)), 2) ?></td>
                            <td class="tt-actions">
                                <div class="btn-group" role="group" aria-label="Actions">
                                    <?php 
                                        $quotationIdentifier = (!empty($q['public_id']) && featureEnabled('enable_public_ids')) ? $q['public_id'] : $q['id'];
                                    ?>
                                    <a href="<?= site_url('quotations/view/'. urlencode($quotationIdentifier)) ?>" class="btn btn-sm btn-outline-secondary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= site_url('document-studio?edit=quotation&id='.$q['id']) ?>" class="btn btn-sm btn-outline-info" title="Edit in Document Studio">
                                        <i class="bi bi-easel"></i>
                                    </a>
                                    <form method="post" action="<?= site_url('quotations/delete/'.$q['id']) ?>" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this quotation?');">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <a href="<?= site_url('sales-orders/create-from-quotation/'.$q['id']) ?>" class="btn btn-sm btn-outline-primary" title="Convert to Sales Order">
                                        <i class="bi bi-arrow-right-square"></i>
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
<?= $this->endSection() ?>
