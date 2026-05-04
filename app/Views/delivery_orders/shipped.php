<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Shipped Orders
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    .do-card {
        transition: all 0.2s;
        border-left: 4px solid #10b981;
    }
    .do-card.overdue { border-left-color: #f87171; }
    .do-card.delivered { border-left-color: #60a5fa; }
    .do-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .status-badge {
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .status-confirmed {
        background: #d1fae5;
        color: #065f46;
    }
    .status-delivered {
        background: #bfdbfe;
        color: #1e3a8a;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-truck me-2"></i>Shipped Orders</h2>
        <p class="text-muted mb-0">All confirmed and delivered orders</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('delivery-orders/pending-followup') ?>" class="btn btn-outline-warning">
            <i class="bi bi-hourglass-split me-1"></i>Pending Follow-up
        </a>
        <a href="<?= site_url('warehouse/ready-to-ship') ?>" class="btn btn-outline-primary">
            <i class="bi bi-box-seam me-1"></i>Ready to Ship
        </a>
        <a href="<?= site_url('delivery-orders') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-list me-1"></i>All Delivery Orders
        </a>
    </div>
</div>

<?php if (empty($delivery_orders)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox" style="font-size: 3rem; color: #9ca3af;"></i>
            <p class="text-muted mt-3 mb-0">No shipped orders yet</p>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($delivery_orders as $do):
            $estDays = (int)($do['estimated_delivery_days'] ?? 0);
            $shippedAt = $do['shipped_at'] ?? null;
            $daysPassed = $shippedAt ? (int)floor((time() - strtotime($shippedAt)) / 86400) : 0;
            $isOverdue = $estDays > 0 && $daysPassed > $estDays && ($do['delivery_status'] ?? '') !== 'delivered' && $do['status'] !== 'delivered';
            $cardClass = ($do['status'] === 'delivered' || ($do['delivery_status'] ?? '') === 'delivered') ? 'delivered' : ($isOverdue ? 'overdue' : '');
        ?>
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card do-card <?= $cardClass ?> h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="mb-0">
                                <a href="<?= site_url('delivery-orders/view/' . (int)$do['do_id']) ?>" class="text-decoration-none">
                                    <?= esc($do['do_number']) ?>
                                </a>
                            </h5>
                            <span class="status-badge status-<?= esc($do['status']) ?>">
                                <?= esc($do['status']) ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($do['sales_order'])): ?>
                            <div class="mb-2">
                                <small class="text-muted">Sales Order:</small><br>
                                <a href="<?= site_url('sales-orders/view/' . (int)$do['sales_order']['id']) ?>" class="text-decoration-none">
                                    <?= esc($do['sales_order']['order_number'] ?? '') ?>
                                </a>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">Customer:</small><br>
                                <strong><?= esc($do['sales_order']['customer_code'] ?? 'N/A') ?></strong>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-2">
                            <small class="text-muted">Shipped:</small><br>
                            <?= $shippedAt ? date('d M Y H:i', strtotime($shippedAt)) : date('d M Y H:i', strtotime($do['updated_at'])) ?>
                        </div>

                        <?php if ($estDays > 0): ?>
                        <div class="mb-2">
                            <small class="text-muted">Delivery:</small>
                            <span class="ms-1 badge <?= $isOverdue ? 'bg-danger' : 'bg-secondary' ?>" style="font-size:.72rem;">
                                Day <?= $daysPassed ?> / <?= $estDays ?>
                            </span>
                            <?php if ($isOverdue): ?>
                                <span class="badge bg-danger" style="font-size:.68rem;">Overdue</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php
                            $ds = $do['delivery_status'] ?? null;
                            $dsLabels = ['delivered'=>'Delivered','lost'=>'Lost','customer_refused'=>'Refused','damaged_in_transit'=>'Damaged','returned_to_sender'=>'Returned','delayed'=>'Delayed','partial_delivery'=>'Partial'];
                            $dsColors = ['delivered'=>'bg-success','lost'=>'bg-danger','customer_refused'=>'bg-warning text-dark','damaged_in_transit'=>'bg-danger','returned_to_sender'=>'bg-secondary','delayed'=>'bg-warning text-dark','partial_delivery'=>'bg-info'];
                        ?>
                        <?php if ($ds): ?>
                        <div class="mb-2">
                            <span class="badge <?= $dsColors[$ds] ?? 'bg-secondary' ?>" style="font-size:.75rem;">
                                <?= $dsLabels[$ds] ?? ucfirst(str_replace('_',' ',$ds)) ?>
                            </span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($do['tracking_number'])): ?>
                        <div class="mb-2">
                            <small class="text-muted"><i class="bi bi-upc-scan me-1"></i><?= esc($do['tracking_number']) ?></small>
                            <button type="button" class="copy-tracking-btn btn btn-link p-0 ms-1" data-tracking="<?= esc($do['tracking_number']) ?>" title="Copy tracking number" style="font-size:.8rem;color:#60a5fa;vertical-align:middle;line-height:1;"><i class="bi bi-clipboard"></i></button>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($do['destination_country'])): ?>
                        <div class="text-muted small mb-1">
                            <i class="bi bi-geo-alt me-1"></i><?= esc($do['destination_country']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="text-muted small">
                            <i class="bi bi-box me-1"></i><?= (int)$do['line_count'] ?> items
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="<?= site_url('delivery-orders/view/' . (int)$do['do_id']) ?>" class="btn btn-sm btn-outline-primary w-100">
                            <i class="bi bi-eye me-1"></i>View Details
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('.copy-tracking-btn').forEach(function(btn){
    btn.addEventListener('click',function(e){
        e.preventDefault();e.stopPropagation();
        const tn=this.dataset.tracking||'';
        if(!tn)return;
        navigator.clipboard.writeText(tn).then(()=>{
            const icon=this.querySelector('i');
            if(icon){icon.className='bi bi-clipboard-check';icon.style.color='#34d399';}
            setTimeout(()=>{if(icon){icon.className='bi bi-clipboard';icon.style.color='';}},2000);
        }).catch(()=>{
            const ta=document.createElement('textarea');ta.value=tn;ta.style.position='fixed';ta.style.opacity='0';
            document.body.appendChild(ta);ta.focus();ta.select();document.execCommand('copy');document.body.removeChild(ta);
            const icon=this.querySelector('i');
            if(icon){icon.className='bi bi-clipboard-check';icon.style.color='#34d399';}
            setTimeout(()=>{if(icon){icon.className='bi bi-clipboard';icon.style.color='';}},2000);
        });
    });
});
</script>

<?= $this->endSection() ?>
