<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>POS Register<?= $this->endSection() ?>

<?= $this->section('content') ?>

<style>
/* ===================== POS REGISTER STYLES ===================== */
.pos-wrapper {
    display: flex;
    height: calc(100vh - 60px);
    overflow: hidden;
    background: var(--light-bg);
    gap: 0;
}

/* ── LEFT PANEL: Order ── */
.pos-order-panel {
    width: 320px;
    min-width: 280px;
    background: var(--white);
    border-right: 1px solid var(--gray-200);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.pos-order-header {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--gray-50);
}
.pos-order-header .order-type-badge {
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.pos-order-type-selector {
    display: flex;
    gap: 4px;
}
.pos-order-type-selector .btn {
    font-size: 0.75rem;
    padding: 4px 10px;
    border-radius: 6px;
}
.pos-order-type-selector .btn.active {
    background: var(--primary-color);
    color: #fff;
    border-color: var(--primary-color);
}

/* Order items list */
.pos-order-items {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}
.pos-order-item {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid var(--gray-100);
    transition: background 0.15s;
    cursor: pointer;
}
.pos-order-item:hover {
    background: var(--gray-50);
}
.pos-order-item.selected {
    background: #eef2ff;
    border-left: 3px solid var(--primary-color);
}
body.theme-dark .pos-order-item.selected {
    background: rgba(79, 70, 229, 0.15);
}
.pos-order-item .item-name {
    flex: 1;
    font-size: 0.88rem;
    font-weight: 500;
    color: var(--gray-700);
}
.pos-order-item .item-qty {
    background: var(--gray-100);
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gray-600);
    margin-right: 10px;
    min-width: 28px;
    text-align: center;
}
.pos-order-item .item-price {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--gray-800);
    min-width: 60px;
    text-align: right;
}
.pos-order-item .item-remove {
    margin-left: 8px;
    color: var(--danger-color);
    cursor: pointer;
    opacity: 0.5;
    transition: opacity 0.15s;
}
.pos-order-item .item-remove:hover {
    opacity: 1;
}

/* Empty order state */
.pos-order-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--gray-400);
    padding: 40px;
}
.pos-order-empty i {
    font-size: 3rem;
    margin-bottom: 12px;
}

/* Order totals */
.pos-order-totals {
    padding: 12px 16px;
    border-top: 1px solid var(--gray-200);
    background: var(--gray-50);
}
.pos-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 3px 0;
    font-size: 0.85rem;
    color: var(--gray-600);
}
.pos-total-row.grand-total {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--gray-800);
    padding-top: 8px;
    border-top: 2px solid var(--gray-200);
    margin-top: 4px;
}

/* Quick cash buttons */
.pos-quick-pay {
    padding: 8px 16px;
    border-top: 1px solid var(--gray-200);
    background: var(--gray-50);
}
.pos-quick-pay label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--gray-500);
    letter-spacing: 0.5px;
    margin-bottom: 6px;
    display: block;
}
.pos-quick-pay .btn-group {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.pos-quick-pay .btn-cash {
    flex: 1;
    min-width: 60px;
    padding: 8px 4px;
    font-size: 0.82rem;
    font-weight: 600;
    border-radius: 8px;
    background: var(--white);
    color: var(--gray-700);
    border: 1.5px solid var(--gray-200);
    transition: all 0.15s;
}
.pos-quick-pay .btn-cash:hover {
    border-color: var(--primary-color);
    background: #eef2ff;
    color: var(--primary-color);
}
.pos-quick-pay .btn-cash.active {
    background: var(--primary-color);
    color: #fff;
    border-color: var(--primary-color);
}

/* Action buttons (Save / Pay) */
.pos-order-actions {
    padding: 12px 16px;
    display: flex;
    gap: 8px;
    border-top: 1px solid var(--gray-200);
    background: var(--white);
}
.pos-order-actions .btn {
    flex: 1;
    padding: 12px;
    font-weight: 700;
    font-size: 0.95rem;
    border-radius: 10px;
}

/* ── CENTER PANEL: Categories ── */
.pos-categories-panel {
    width: 180px;
    min-width: 160px;
    background: var(--white);
    border-right: 1px solid var(--gray-200);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.pos-categories-header {
    padding: 12px 14px;
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--gray-500);
    background: var(--gray-50);
}
.pos-categories-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}
.pos-category-btn {
    display: block;
    width: 100%;
    text-align: left;
    padding: 10px 12px;
    margin-bottom: 4px;
    font-size: 0.82rem;
    font-weight: 600;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: var(--gray-600);
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.pos-category-btn:hover {
    background: var(--gray-100);
    color: var(--gray-800);
}
.pos-category-btn.active {
    background: var(--primary-color);
    color: #fff;
}
body.theme-dark .pos-category-btn.active {
    background: var(--primary-color);
    color: #fff;
}
/* Color bars on categories (visual variety) */
.pos-category-btn::before {
    content: '';
    display: inline-block;
    width: 4px;
    height: 16px;
    border-radius: 2px;
    margin-right: 8px;
    vertical-align: middle;
}
.pos-category-btn:nth-child(6n+1)::before { background: #10b981; }
.pos-category-btn:nth-child(6n+2)::before { background: #f59e0b; }
.pos-category-btn:nth-child(6n+3)::before { background: #3b82f6; }
.pos-category-btn:nth-child(6n+4)::before { background: #ef4444; }
.pos-category-btn:nth-child(6n+5)::before { background: #8b5cf6; }
.pos-category-btn:nth-child(6n+6)::before { background: #ec4899; }
.pos-category-btn.active::before { background: rgba(255,255,255,0.6); }

/* ── RIGHT PANEL: Products Grid ── */
.pos-products-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--light-bg);
}

/* Top bar: search + qty selector */
.pos-products-topbar {
    padding: 10px 16px;
    border-bottom: 1px solid var(--gray-200);
    background: var(--white);
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}
.pos-search-box {
    flex: 1;
    min-width: 200px;
    position: relative;
}
.pos-search-box input {
    width: 100%;
    padding: 8px 12px 8px 36px;
    border-radius: 8px;
    border: 1.5px solid var(--gray-200);
    font-size: 0.88rem;
    background: var(--gray-50);
    transition: border-color 0.15s;
}
.pos-search-box input:focus {
    outline: none;
    border-color: var(--primary-color);
    background: var(--white);
}
.pos-search-box .search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-400);
    font-size: 0.95rem;
}

/* Quantity selector row */
.pos-qty-bar {
    display: flex;
    align-items: center;
    gap: 2px;
}
.pos-qty-bar label {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--gray-500);
    margin-right: 8px;
    text-transform: uppercase;
}
.pos-qty-btn {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    border: 1.5px solid var(--gray-200);
    background: var(--white);
    color: var(--gray-600);
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
}
.pos-qty-btn:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}
.pos-qty-btn.active {
    background: var(--primary-color);
    color: #fff;
    border-color: var(--primary-color);
}

/* ORDERS, +, Refresh, CUSTOM buttons in top bar */
.pos-topbar-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}
.pos-topbar-actions .btn {
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 6px 14px;
}

/* Products grid */
.pos-products-grid {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
    gap: 10px;
    align-content: start;
}
.pos-product-card {
    background: var(--white);
    border-radius: 10px;
    border: 1.5px solid var(--gray-200);
    padding: 12px 10px;
    cursor: pointer;
    transition: all 0.15s;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-height: 80px;
    position: relative;
    overflow: hidden;
}
.pos-product-card:hover {
    border-color: var(--primary-color);
    box-shadow: 0 2px 8px rgba(79,70,229,0.10);
    transform: translateY(-1px);
}
.pos-product-card:active {
    transform: scale(0.97);
}
/* Color bar on left of product card */
.pos-product-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    border-radius: 10px 0 0 10px;
}
.pos-product-card[data-color="green"]::before  { background: #10b981; }
.pos-product-card[data-color="blue"]::before   { background: #3b82f6; }
.pos-product-card[data-color="yellow"]::before { background: #f59e0b; }
.pos-product-card[data-color="red"]::before    { background: #ef4444; }
.pos-product-card[data-color="purple"]::before { background: #8b5cf6; }
.pos-product-card[data-color="pink"]::before   { background: #ec4899; }
.pos-product-card[data-color="teal"]::before   { background: #14b8a6; }
.pos-product-card[data-color="orange"]::before { background: #f97316; }

.pos-product-card .product-name {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
    line-height: 1.2;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    padding-left: 6px;
}
.pos-product-card .product-price {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-top: auto;
    padding-left: 6px;
}
.pos-product-card .product-code {
    font-size: 0.68rem;
    color: var(--gray-400);
    padding-left: 6px;
}

/* Custom item card */
.pos-product-card.custom-item {
    border-style: dashed;
    border-color: var(--gray-300);
    align-items: center;
    justify-content: center;
    text-align: center;
}
.pos-product-card.custom-item i {
    font-size: 1.5rem;
    color: var(--gray-400);
    margin-bottom: 4px;
}
.pos-product-card.custom-item .product-name {
    color: var(--gray-500);
    padding-left: 0;
}

/* ── Orders Drawer ── */
.pos-orders-drawer {
    position: fixed;
    top: 0;
    right: -450px;
    width: 440px;
    height: 100vh;
    background: var(--white);
    border-left: 1px solid var(--gray-200);
    z-index: 2000;
    box-shadow: -4px 0 24px rgba(0,0,0,0.12);
    transition: right 0.3s ease;
    display: flex;
    flex-direction: column;
}
.pos-orders-drawer.open {
    right: 0;
}
.pos-orders-drawer-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.pos-orders-drawer-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px 20px;
}
.pos-orders-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.4);
    z-index: 1999;
    display: none;
}
.pos-orders-backdrop.show {
    display: block;
}

/* ── Payment Modal ── */
.pos-payment-modal .modal-content {
    border-radius: 16px;
    border: none;
}
.pos-payment-modal .modal-header {
    border-bottom: 1px solid var(--gray-200);
    padding: 16px 24px;
}
.pos-payment-modal .modal-body {
    padding: 24px;
}
.pos-payment-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}
.pos-payment-grid .numpad-btn {
    padding: 16px;
    font-size: 1.25rem;
    font-weight: 700;
    border-radius: 10px;
    border: 1.5px solid var(--gray-200);
    background: var(--white);
    color: var(--gray-700);
    cursor: pointer;
    transition: all 0.15s;
}
.pos-payment-grid .numpad-btn:hover {
    background: var(--gray-50);
    border-color: var(--primary-color);
}
.pos-payment-grid .numpad-btn.action {
    background: var(--primary-color);
    color: #fff;
    border-color: var(--primary-color);
}

/* ── Receipt Print Styles ── */
@media print {
    body * { visibility: hidden; }
    .receipt-print, .receipt-print * { visibility: visible; }
    .receipt-print {
        position: absolute;
        left: 0;
        top: 0;
        width: 80mm;
        font-family: 'Courier New', monospace;
        font-size: 12px;
    }
}

/* ── Responsive ── */
@media (max-width: 1024px) {
    .pos-categories-panel { width: 140px; min-width: 120px; }
    .pos-order-panel { width: 280px; min-width: 260px; }
}
@media (max-width: 768px) {
    .pos-wrapper { flex-direction: column; height: auto; }
    .pos-order-panel { width: 100%; height: 250px; }
    .pos-categories-panel { width: 100%; height: auto; flex-direction: row; }
    .pos-categories-list { display: flex; overflow-x: auto; padding: 4px; }
    .pos-category-btn { white-space: nowrap; margin-bottom: 0; margin-right: 4px; }
    .pos-products-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); }
}
</style>

<!-- ════════════════════════ POS REGISTER UI ════════════════════════ -->
<div class="pos-wrapper" id="posApp">

    <!-- ── LEFT: Order Panel ── -->
    <div class="pos-order-panel">
        <!-- Order header / type selector -->
        <div class="pos-order-header">
            <div>
                <div class="order-type-badge text-primary" id="orderTypeBadge">Dine In Order</div>
                <small class="text-muted" id="orderNumberDisplay"><?= esc($orderNumber) ?></small>
            </div>
            <div class="pos-order-type-selector">
                <button class="btn btn-sm btn-outline-secondary active" data-type="dine_in" onclick="POS.setOrderType('dine_in', this)">
                    <i class="bi bi-cup-hot"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary" data-type="takeout" onclick="POS.setOrderType('takeout', this)">
                    <i class="bi bi-bag"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary" data-type="delivery" onclick="POS.setOrderType('delivery', this)">
                    <i class="bi bi-truck"></i>
                </button>
            </div>
        </div>

        <!-- Order items list -->
        <div class="pos-order-items" id="orderItems">
            <div class="pos-order-empty" id="emptyOrderMsg">
                <i class="bi bi-cart3"></i>
                <span>No items yet</span>
                <small class="text-muted mt-1">Tap a product to add</small>
            </div>
        </div>

        <!-- Totals -->
        <div class="pos-order-totals">
            <div class="pos-total-row">
                <span>Subtotal</span>
                <span id="subtotalDisplay">0.00</span>
            </div>
            <div class="pos-total-row">
                <span>Tax <small class="text-muted" id="taxRateLabel">(0%)</small></span>
                <span id="taxDisplay">0.00</span>
            </div>
            <div class="pos-total-row">
                <span>Discount</span>
                <span id="discountDisplay">0.00</span>
            </div>
            <div class="pos-total-row grand-total">
                <span>Total</span>
                <span id="totalDisplay">0.00</span>
            </div>
        </div>

        <!-- Quick pay with cash -->
        <div class="pos-quick-pay">
            <label>Fast Pay With Cash</label>
            <div class="btn-group">
                <button class="btn-cash" onclick="POS.quickPay(5)">5.00</button>
                <button class="btn-cash" onclick="POS.quickPay(10)">10.00</button>
                <button class="btn-cash" onclick="POS.quickPay(20)">20.00</button>
                <button class="btn-cash" onclick="POS.quickPay(50)">50.00</button>
                <button class="btn-cash" onclick="POS.quickPay(100)">100.00</button>
                <button class="btn-cash" onclick="POS.quickPayCustom()">Custom</button>
            </div>
        </div>

        <!-- Actions -->
        <div class="pos-order-actions">
            <button class="btn btn-outline-secondary" onclick="POS.saveOrder('open')">
                <i class="bi bi-floppy me-1"></i> Save
            </button>
            <button class="btn btn-success" onclick="POS.openPaymentModal()">
                <i class="bi bi-credit-card me-1"></i> Pay
            </button>
        </div>
    </div>

    <!-- ── CENTER: Categories ── -->
    <div class="pos-categories-panel">
        <div class="pos-categories-header">Categories</div>
        <div class="pos-categories-list" id="categoryList">
            <button class="pos-category-btn active" data-cat="all" onclick="POS.selectCategory('all', this)">
                All Items
            </button>
            <?php foreach ($categories as $cat): ?>
                <button class="pos-category-btn" data-cat="<?= esc($cat['id']) ?>" onclick="POS.selectCategory(<?= esc($cat['id']) ?>, this)">
                    <?= esc($cat['name']) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── RIGHT: Products Grid ── -->
    <div class="pos-products-panel">
        <!-- Top bar -->
        <div class="pos-products-topbar">
            <!-- Search -->
            <div class="pos-search-box">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="posSearchInput" placeholder="Scan barcode or search..." 
                       oninput="POS.searchProducts(this.value)" autofocus>
            </div>

            <!-- Quantity -->
            <div class="pos-qty-bar">
                <label>QTY</label>
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <button class="pos-qty-btn <?= $i === 1 ? 'active' : '' ?>" 
                            onclick="POS.setQty(<?= $i ?>, this)"><?= $i ?></button>
                <?php endfor; ?>
                <button class="pos-qty-btn" onclick="POS.customQty()">
                    <i class="bi bi-pencil" style="font-size:0.72rem"></i>
                </button>
            </div>

            <!-- Actions -->
            <div class="pos-topbar-actions">
                <button class="btn btn-outline-primary" onclick="POS.toggleOrders()">
                    <i class="bi bi-receipt me-1"></i> ORDERS
                </button>
                <button class="btn btn-outline-secondary btn-icon" onclick="POS.newOrder()" title="New Order">
                    <i class="bi bi-plus-lg"></i>
                </button>
                <button class="btn btn-outline-secondary btn-icon" onclick="POS.refresh()" title="Refresh">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="pos-products-grid" id="productsGrid">
            <?php
            // Assign rotating colors to products
            $colors = ['green','blue','yellow','red','purple','pink','teal','orange'];
            $ci = 0;
            foreach ($products as $p):
                $price = $p['sale_price'] ?? $p['cost_price'] ?? 0;
                $color = $colors[$ci % count($colors)];
                $ci++;
            ?>
                <div class="pos-product-card" 
                     data-color="<?= $color ?>"
                     data-id="<?= esc($p['id']) ?>"
                     data-name="<?= esc($p['name']) ?>"
                     data-price="<?= esc($price) ?>"
                     data-code="<?= esc($p['code'] ?? '') ?>"
                     data-barcode="<?= esc($p['barcode'] ?? '') ?>"
                     data-category="<?= esc($p['category_id'] ?? '') ?>"
                     onclick="POS.addProduct(this)">
                    <span class="product-name"><?= esc($p['name']) ?></span>
                    <span class="product-code"><?= esc($p['code'] ?? '') ?></span>
                    <span class="product-price"><?= number_format((float)$price, 2) ?></span>
                </div>
            <?php endforeach; ?>

            <!-- Custom Item card -->
            <div class="pos-product-card custom-item" onclick="POS.addCustomItem()">
                <i class="bi bi-pencil-square"></i>
                <span class="product-name">Custom Item</span>
            </div>
        </div>
    </div>
</div>

<!-- ── Orders Drawer ── -->
<div class="pos-orders-backdrop" id="ordersBackdrop" onclick="POS.toggleOrders()"></div>
<div class="pos-orders-drawer" id="ordersDrawer">
    <div class="pos-orders-drawer-header">
        <div>
            <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Today's Orders</h5>
            <small class="text-muted">Daily Total: <strong id="dailyTotalDisplay">0.00</strong></small>
        </div>
        <button class="btn btn-sm btn-outline-secondary" onclick="POS.toggleOrders()">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="pos-orders-drawer-body" id="ordersDrawerBody">
        <div class="text-center text-muted py-4">Loading orders...</div>
    </div>
</div>

<!-- ── Payment Modal ── -->
<div class="modal fade pos-payment-modal" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-credit-card me-2"></i>Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="text-muted small">TOTAL DUE</div>
                    <div class="display-5 fw-bold text-primary" id="paymentTotalDisplay">0.00</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Payment Method</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="payMethod" id="payMethodCash" value="cash" checked>
                        <label class="btn btn-outline-primary" for="payMethodCash"><i class="bi bi-cash me-1"></i> Cash</label>
                        <input type="radio" class="btn-check" name="payMethod" id="payMethodCard" value="card">
                        <label class="btn btn-outline-primary" for="payMethodCard"><i class="bi bi-credit-card me-1"></i> Card</label>
                        <input type="radio" class="btn-check" name="payMethod" id="payMethodOther" value="other">
                        <label class="btn btn-outline-primary" for="payMethodOther"><i class="bi bi-wallet2 me-1"></i> Other</label>
                    </div>
                </div>

                <div class="mb-3" id="cashAmountSection">
                    <label class="form-label fw-semibold">Amount Received</label>
                    <input type="number" class="form-control form-control-lg text-center fw-bold" 
                           id="amountReceived" step="0.01" min="0" oninput="POS.calcChange()">
                </div>

                <div class="mb-3 text-center" id="changeSection" style="display:none;">
                    <div class="text-muted small">CHANGE DUE</div>
                    <div class="fs-3 fw-bold text-success" id="changeDueDisplay">0.00</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Customer Name <small class="text-muted">(optional)</small></label>
                    <input type="text" class="form-control" id="customerNameInput" placeholder="Walk-in">
                </div>

                <div class="d-grid gap-2 mt-4">
                    <button class="btn btn-success btn-lg" onclick="POS.processPayment()">
                        <i class="bi bi-check-circle me-2"></i> Complete Payment
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Custom Item Modal ── -->
<div class="modal fade" id="customItemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Custom Item</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Item Name</label>
                    <input type="text" class="form-control" id="customItemName" placeholder="e.g. Extra Ranch">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Price</label>
                    <input type="number" class="form-control" id="customItemPrice" step="0.01" min="0" placeholder="0.00">
                </div>
                <button class="btn btn-primary w-100 mt-2" onclick="POS.confirmCustomItem()">
                    Add to Order
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Hidden receipt template for printing ── -->
<div id="receiptContainer" style="display:none;"></div>

<script>
// ═══════════════════════════ POS ENGINE ═══════════════════════════
const POS = {
    // State
    order: [],
    selectedQty: 1,
    orderType: 'dine_in',
    orderNumber: '<?= esc($orderNumber) ?>',
    taxRate: 0, // % — set from settings or per-order
    discountAmount: 0,
    discountType: 'fixed',
    allProducts: document.querySelectorAll('.pos-product-card:not(.custom-item)'),
    BASE: window.APP_BASE || '',

    // ─── Order Type ───
    setOrderType(type, btn) {
        this.orderType = type;
        document.querySelectorAll('.pos-order-type-selector .btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const labels = { dine_in: 'Dine In Order', takeout: 'Takeout Order', delivery: 'Delivery Order' };
        document.getElementById('orderTypeBadge').textContent = labels[type] || type;
    },

    // ─── Quantity ───
    setQty(qty, btn) {
        this.selectedQty = qty;
        document.querySelectorAll('.pos-qty-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
    },

    customQty() {
        const val = prompt('Enter quantity:', '1');
        if (val && !isNaN(val) && parseInt(val) > 0) {
            this.selectedQty = parseInt(val);
            document.querySelectorAll('.pos-qty-btn').forEach(b => b.classList.remove('active'));
        }
    },

    // ─── Category Filter ───
    selectCategory(catId, btn) {
        document.querySelectorAll('.pos-category-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        this.allProducts.forEach(card => {
            if (catId === 'all' || card.dataset.category == catId) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    },

    // ─── Search ───
    searchProducts(query) {
        const q = query.toLowerCase().trim();
        this.allProducts.forEach(card => {
            if (!q) {
                card.style.display = '';
                return;
            }
            const name = (card.dataset.name || '').toLowerCase();
            const code = (card.dataset.code || '').toLowerCase();
            const barcode = (card.dataset.barcode || '').toLowerCase();
            card.style.display = (name.includes(q) || code.includes(q) || barcode.includes(q)) ? '' : 'none';
        });
    },

    // ─── Add Product ───
    addProduct(el) {
        const id   = el.dataset.id;
        const name = el.dataset.name;
        const price = parseFloat(el.dataset.price) || 0;
        const qty  = this.selectedQty;

        // Check if already in order → increase qty
        const existing = this.order.find(i => i.product_id == id && !i.is_custom);
        if (existing) {
            existing.qty += qty;
        } else {
            this.order.push({
                product_id: id,
                name: name,
                price: price,
                qty: qty,
                discount: 0,
                is_custom: false,
                notes: '',
            });
        }

        // Reset qty to 1
        this.setQty(1, document.querySelector('.pos-qty-btn'));
        this.renderOrder();

        // Brief flash effect
        el.style.transform = 'scale(0.94)';
        setTimeout(() => el.style.transform = '', 150);
    },

    // ─── Custom Item ───
    addCustomItem() {
        const modal = new bootstrap.Modal(document.getElementById('customItemModal'));
        document.getElementById('customItemName').value = '';
        document.getElementById('customItemPrice').value = '';
        modal.show();
    },

    confirmCustomItem() {
        const name  = document.getElementById('customItemName').value.trim();
        const price = parseFloat(document.getElementById('customItemPrice').value) || 0;
        if (!name) { alert('Please enter an item name'); return; }

        this.order.push({
            product_id: null,
            name: name,
            price: price,
            qty: this.selectedQty,
            discount: 0,
            is_custom: true,
            notes: '',
        });

        bootstrap.Modal.getInstance(document.getElementById('customItemModal')).hide();
        this.setQty(1, document.querySelector('.pos-qty-btn'));
        this.renderOrder();
    },

    // ─── Render Order ───
    renderOrder() {
        const container = document.getElementById('orderItems');
        const emptyMsg  = document.getElementById('emptyOrderMsg');

        if (this.order.length === 0) {
            container.innerHTML = '';
            container.appendChild(emptyMsg);
            emptyMsg.style.display = 'flex';
        } else {
            if (emptyMsg) emptyMsg.style.display = 'none';
            let html = '';
            this.order.forEach((item, idx) => {
                const lineTotal = (item.price * item.qty) - item.discount;
                html += `
                    <div class="pos-order-item" onclick="POS.selectOrderItem(${idx})">
                        <span class="item-name">${this.escHtml(item.name)}</span>
                        <span class="item-qty">x${item.qty}</span>
                        <span class="item-price">${lineTotal.toFixed(2)}</span>
                        <span class="item-remove" onclick="event.stopPropagation(); POS.removeItem(${idx})">
                            <i class="bi bi-x-circle"></i>
                        </span>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        this.recalcTotals();
    },

    // ─── Select an order item (for edit qty / discount) ───
    selectOrderItem(idx) {
        const item = this.order[idx];
        if (!item) return;

        const action = prompt(
            `${item.name} (x${item.qty} @ ${item.price.toFixed(2)})\n\nEnter new QTY or type "del" to remove:`,
            item.qty
        );

        if (action === null) return;
        if (action.toLowerCase() === 'del') {
            this.removeItem(idx);
            return;
        }
        const newQty = parseInt(action);
        if (!isNaN(newQty) && newQty > 0) {
            item.qty = newQty;
            this.renderOrder();
        }
    },

    // ─── Remove Item ───
    removeItem(idx) {
        this.order.splice(idx, 1);
        this.renderOrder();
    },

    // ─── Recalculate Totals ───
    recalcTotals() {
        let subtotal = 0;
        this.order.forEach(item => {
            subtotal += (item.price * item.qty) - item.discount;
        });

        const taxAmt = subtotal * (this.taxRate / 100);
        const total  = subtotal + taxAmt - this.discountAmount;

        document.getElementById('subtotalDisplay').textContent  = subtotal.toFixed(2);
        document.getElementById('taxDisplay').textContent        = taxAmt.toFixed(2);
        document.getElementById('taxRateLabel').textContent      = `(${this.taxRate}%)`;
        document.getElementById('discountDisplay').textContent   = this.discountAmount.toFixed(2);
        document.getElementById('totalDisplay').textContent      = total.toFixed(2);
    },

    getTotal() {
        let subtotal = 0;
        this.order.forEach(item => {
            subtotal += (item.price * item.qty) - item.discount;
        });
        return subtotal + (subtotal * (this.taxRate / 100)) - this.discountAmount;
    },

    // ─── Quick Pay ───
    quickPay(amount) {
        if (this.order.length === 0) { alert('Add items first'); return; }
        const total = this.getTotal();
        if (amount < total) {
            alert(`Insufficient: need ${total.toFixed(2)}, given ${amount.toFixed(2)}`);
            return;
        }
        this._completePayment('cash', amount);
    },

    quickPayCustom() {
        if (this.order.length === 0) { alert('Add items first'); return; }
        const val = prompt('Enter cash amount:', this.getTotal().toFixed(2));
        if (val !== null && !isNaN(val)) {
            this.quickPay(parseFloat(val));
        }
    },

    // ─── Payment Modal ───
    openPaymentModal() {
        if (this.order.length === 0) { alert('Add items first'); return; }
        const total = this.getTotal();
        document.getElementById('paymentTotalDisplay').textContent = total.toFixed(2);
        document.getElementById('amountReceived').value = total.toFixed(2);
        document.getElementById('changeSection').style.display = 'none';
        document.getElementById('customerNameInput').value = '';
        const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
        modal.show();
        this.calcChange();
    },

    calcChange() {
        const total    = this.getTotal();
        const received = parseFloat(document.getElementById('amountReceived').value) || 0;
        const change   = received - total;

        if (change >= 0) {
            document.getElementById('changeSection').style.display = 'block';
            document.getElementById('changeDueDisplay').textContent = change.toFixed(2);
        } else {
            document.getElementById('changeSection').style.display = 'none';
        }
    },

    processPayment() {
        const total    = this.getTotal();
        const method   = document.querySelector('input[name="payMethod"]:checked').value;
        const received = parseFloat(document.getElementById('amountReceived').value) || 0;
        const customer = document.getElementById('customerNameInput').value.trim() || 'Walk-in';

        if (method === 'cash' && received < total) {
            alert('Insufficient payment amount');
            return;
        }

        this._completePayment(method, received, customer);
        bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
    },

    // ─── Complete a payment (save + print receipt) ───
    async _completePayment(method, amountPaid, customerName = 'Walk-in') {
        const total   = this.getTotal();
        const change  = amountPaid - total;
        let subtotal  = 0;
        const items = this.order.map(item => {
            const lineTot = (item.price * item.qty) - item.discount;
            subtotal += lineTot;
            return {
                product_id:   item.product_id,
                variant_id:   null,
                name:         item.name,
                variant_name: '',
                qty:          item.qty,
                price:        item.price,
                discount:     item.discount,
                line_total:   lineTot,
                notes:        item.notes,
            };
        });

        const payload = {
            order_number:   this.orderNumber,
            order_type:     this.orderType,
            customer_name:  customerName,
            table_number:   null,
            subtotal:       subtotal,
            tax_rate:       this.taxRate,
            tax_amount:     subtotal * (this.taxRate / 100),
            discount_amount:this.discountAmount,
            discount_type:  this.discountType,
            total:          total,
            amount_paid:    amountPaid,
            change_due:     change > 0 ? change : 0,
            payment_method: method,
            status:         'paid',
            items:          items,
        };

        try {
            const resp = await fetch(`${this.BASE}/pos/save-order`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(payload),
            });
            const data = await resp.json();

            if (data.success) {
                // Print receipt
                this.printReceipt(data.order_id, payload, change);
                // Reset
                this.newOrder();
            } else {
                alert('Error: ' + (data.message || 'Failed to save'));
            }
        } catch (err) {
            console.error('POS save error:', err);
            // Still print locally if server fails
            this.printReceiptLocal(payload, change);
            this.newOrder();
        }
    },

    // ─── Save Order (without payment — "hold" / "open") ───
    async saveOrder(status = 'open') {
        if (this.order.length === 0) { alert('Add items first'); return; }

        let subtotal = 0;
        const items = this.order.map(item => {
            const lineTot = (item.price * item.qty) - item.discount;
            subtotal += lineTot;
            return {
                product_id: item.product_id,
                name: item.name,
                qty: item.qty,
                price: item.price,
                discount: item.discount,
                line_total: lineTot,
                notes: item.notes,
            };
        });

        try {
            const resp = await fetch(`${this.BASE}/pos/save-order`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    order_number: this.orderNumber,
                    order_type:   this.orderType,
                    subtotal:     subtotal,
                    tax_rate:     this.taxRate,
                    tax_amount:   subtotal * (this.taxRate / 100),
                    total:        this.getTotal(),
                    status:       status,
                    items:        items,
                }),
            });
            const data = await resp.json();
            if (data.success) {
                alert('Order saved!');
                this.newOrder();
            }
        } catch (err) {
            console.error(err);
            alert('Failed to save order');
        }
    },

    // ─── New Order ───
    newOrder() {
        this.order = [];
        this.discountAmount = 0;
        this.selectedQty = 1;
        // Get next order number
        fetch(`${this.BASE}/pos/next-order-number`)
            .then(r => r.json())
            .then(d => {
                if (d.order_number) {
                    this.orderNumber = d.order_number;
                    document.getElementById('orderNumberDisplay').textContent = d.order_number;
                }
            }).catch(() => {});
        this.setQty(1, document.querySelector('.pos-qty-btn'));
        this.renderOrder();
    },

    refresh() {
        location.reload();
    },

    // ═══════════════════════ RECEIPT PRINTING ═══════════════════════

    /**
     * Print receipt via a hidden iframe — works with thermal/receipt printers.
     * Uses ESC/POS-compatible HTML layout (80mm width).
     */
    printReceipt(orderId, payload, change) {
        const receiptHtml = this._buildReceiptHtml(payload, change);
        this._printHtml(receiptHtml);
    },

    /**
     * Print without server (fallback if save fails)
     */
    printReceiptLocal(payload, change) {
        const receiptHtml = this._buildReceiptHtml(payload, change);
        this._printHtml(receiptHtml);
    },

    /**
     * Build receipt HTML for 80mm thermal printer
     */
    _buildReceiptHtml(payload, change) {
        const company = <?= json_encode($company ?? []) ?>;
        const companyName = company.name || 'Corelynk';
        const companyAddr = company.address || '';
        const companyPhone = company.phone || '';
        const tagline = company.tagline || '';
        const now = new Date();
        const dateStr = now.toLocaleDateString() + ' ' + now.toLocaleTimeString();

        let itemsHtml = '';
        (payload.items || []).forEach(item => {
            itemsHtml += `
                <tr>
                    <td style="text-align:left;padding:2px 0;">${this.escHtml(item.name)}</td>
                    <td style="text-align:center;padding:2px 4px;">${item.qty}</td>
                    <td style="text-align:right;padding:2px 0;">${parseFloat(item.price).toFixed(2)}</td>
                    <td style="text-align:right;padding:2px 0;">${parseFloat(item.line_total).toFixed(2)}</td>
                </tr>
            `;
        });

        return `
<!DOCTYPE html>
<html>
<head>
<style>
    @page { margin: 0; size: 80mm auto; }
    body {
        font-family: 'Courier New', Courier, monospace;
        font-size: 12px;
        width: 72mm;
        margin: 4mm auto;
        color: #000;
    }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .line { border-top: 1px dashed #000; margin: 4px 0; }
    .double-line { border-top: 2px solid #000; margin: 4px 0; }
    table { width: 100%; border-collapse: collapse; }
    td { vertical-align: top; }
    .right { text-align: right; }
    .left  { text-align: left; }
    .big { font-size: 16px; font-weight: bold; }
</style>
</head>
<body>
    <div class="center bold big">${this.escHtml(companyName)}</div>
    ${tagline ? `<div class="center" style="font-size:10px;">${this.escHtml(tagline)}</div>` : ''}
    ${companyAddr ? `<div class="center" style="font-size:10px;">${this.escHtml(companyAddr)}</div>` : ''}
    ${companyPhone ? `<div class="center" style="font-size:10px;">Tel: ${this.escHtml(companyPhone)}</div>` : ''}

    <div class="line"></div>
    <div style="display:flex;justify-content:space-between;">
        <span>Order: ${this.escHtml(payload.order_number)}</span>
        <span>${this.escHtml(payload.order_type.replace('_',' '))}</span>
    </div>
    <div style="font-size:10px;">${dateStr}</div>
    ${payload.customer_name && payload.customer_name !== 'Walk-in' ? `<div>Customer: ${this.escHtml(payload.customer_name)}</div>` : ''}
    <div class="line"></div>

    <table>
        <thead>
            <tr style="font-weight:bold;border-bottom:1px solid #000;">
                <td class="left">Item</td>
                <td style="text-align:center;">Qty</td>
                <td class="right">Price</td>
                <td class="right">Total</td>
            </tr>
        </thead>
        <tbody>
            ${itemsHtml}
        </tbody>
    </table>

    <div class="line"></div>
    <table>
        <tr><td class="left">Subtotal</td><td class="right">${parseFloat(payload.subtotal).toFixed(2)}</td></tr>
        ${payload.tax_amount > 0 ? `<tr><td class="left">Tax (${payload.tax_rate}%)</td><td class="right">${parseFloat(payload.tax_amount).toFixed(2)}</td></tr>` : ''}
        ${payload.discount_amount > 0 ? `<tr><td class="left">Discount</td><td class="right">-${parseFloat(payload.discount_amount).toFixed(2)}</td></tr>` : ''}
    </table>
    <div class="double-line"></div>
    <table>
        <tr class="bold"><td class="left big">TOTAL</td><td class="right big">${parseFloat(payload.total).toFixed(2)}</td></tr>
    </table>
    <div class="line"></div>
    <table>
        <tr><td class="left">Paid (${this.escHtml(payload.payment_method)})</td><td class="right">${parseFloat(payload.amount_paid || 0).toFixed(2)}</td></tr>
        ${change > 0 ? `<tr><td class="left">Change</td><td class="right">${parseFloat(change).toFixed(2)}</td></tr>` : ''}
    </table>
    <div class="line"></div>
    <div class="center" style="font-size:10px;margin-top:8px;">Thank you for your purchase!</div>
    <div class="center" style="font-size:9px;color:#666;">Powered by Corelynk POS</div>
    <br><br>
</body>
</html>
        `;
    },

    /**
     * Print HTML via hidden iframe — triggers browser print which connects
     * to whatever printer is set as default (thermal/receipt printer).
     * For direct ESC/POS: see printReceiptESCPOS() below.
     */
    _printHtml(html) {
        const iframe = document.createElement('iframe');
        iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:none;';
        document.body.appendChild(iframe);

        iframe.contentWindow.document.open();
        iframe.contentWindow.document.write(html);
        iframe.contentWindow.document.close();

        iframe.onload = () => {
            setTimeout(() => {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
                setTimeout(() => document.body.removeChild(iframe), 2000);
            }, 300);
        };
    },

    /**
     * ESC/POS raw printing via WebUSB (for direct thermal printer connection).
     * Requires the browser to support WebUSB and the printer to be connected via USB.
     */
    async printReceiptESCPOS(payload, change) {
        try {
            // Request USB device (thermal printer)
            const device = await navigator.usb.requestDevice({
                filters: [] // Accept any USB device — user picks from browser dialog
            });
            await device.open();
            await device.selectConfiguration(1);
            await device.claimInterface(0);

            const encoder = new TextEncoder();
            const ESC = 0x1B;
            const GS  = 0x1D;
            const LF  = 0x0A;

            const commands = [];

            // Initialize printer
            commands.push(new Uint8Array([ESC, 0x40]));

            // Center align
            commands.push(new Uint8Array([ESC, 0x61, 0x01]));

            // Company name (double size)
            const company = <?= json_encode($company ?? []) ?>;
            commands.push(new Uint8Array([ESC, 0x21, 0x30])); // double width+height
            commands.push(encoder.encode((company.name || 'Corelynk') + '\n'));
            commands.push(new Uint8Array([ESC, 0x21, 0x00])); // normal

            if (company.tagline) commands.push(encoder.encode(company.tagline + '\n'));
            if (company.address) commands.push(encoder.encode(company.address + '\n'));
            if (company.phone) commands.push(encoder.encode('Tel: ' + company.phone + '\n'));

            // Separator
            commands.push(encoder.encode('--------------------------------\n'));

            // Left align
            commands.push(new Uint8Array([ESC, 0x61, 0x00]));

            commands.push(encoder.encode('Order: ' + payload.order_number + '\n'));
            commands.push(encoder.encode('Type: ' + payload.order_type.replace('_', ' ') + '\n'));
            commands.push(encoder.encode('Date: ' + new Date().toLocaleString() + '\n'));
            commands.push(encoder.encode('--------------------------------\n'));

            // Items
            (payload.items || []).forEach(item => {
                const line = item.name.substring(0, 16).padEnd(16) +
                             ('x' + item.qty).padStart(4) +
                             parseFloat(item.line_total).toFixed(2).padStart(12);
                commands.push(encoder.encode(line + '\n'));
            });

            commands.push(encoder.encode('--------------------------------\n'));
            commands.push(encoder.encode('Subtotal:'.padEnd(20) + parseFloat(payload.subtotal).toFixed(2).padStart(12) + '\n'));

            if (payload.tax_amount > 0) {
                commands.push(encoder.encode(('Tax ' + payload.tax_rate + '%:').padEnd(20) + parseFloat(payload.tax_amount).toFixed(2).padStart(12) + '\n'));
            }

            commands.push(encoder.encode('================================\n'));

            // Bold total
            commands.push(new Uint8Array([ESC, 0x21, 0x08])); // bold
            commands.push(encoder.encode('TOTAL:'.padEnd(20) + parseFloat(payload.total).toFixed(2).padStart(12) + '\n'));
            commands.push(new Uint8Array([ESC, 0x21, 0x00])); // normal

            commands.push(encoder.encode('--------------------------------\n'));
            commands.push(encoder.encode('Paid:'.padEnd(20) + parseFloat(payload.amount_paid || 0).toFixed(2).padStart(12) + '\n'));
            if (change > 0) {
                commands.push(encoder.encode('Change:'.padEnd(20) + parseFloat(change).toFixed(2).padStart(12) + '\n'));
            }

            // Center + thank you
            commands.push(new Uint8Array([ESC, 0x61, 0x01]));
            commands.push(encoder.encode('\nThank you!\n'));
            commands.push(encoder.encode('Powered by Corelynk POS\n\n'));

            // Cut paper
            commands.push(new Uint8Array([GS, 0x56, 0x00]));

            // Send all commands
            for (const cmd of commands) {
                await device.transferOut(1, cmd);
            }

            await device.close();
        } catch (err) {
            console.warn('ESC/POS printing not available, falling back to browser print:', err);
            this.printReceiptLocal(payload, change);
        }
    },

    // ─── Orders Drawer ───
    async toggleOrders() {
        const drawer   = document.getElementById('ordersDrawer');
        const backdrop = document.getElementById('ordersBackdrop');

        if (drawer.classList.contains('open')) {
            drawer.classList.remove('open');
            backdrop.classList.remove('show');
        } else {
            drawer.classList.add('open');
            backdrop.classList.add('show');
            this._loadOrders();
        }
    },

    async _loadOrders() {
        const body = document.getElementById('ordersDrawerBody');
        body.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div> Loading...</div>';

        try {
            const resp = await fetch(`${this.BASE}/pos/orders`);
            const data = await resp.json();

            document.getElementById('dailyTotalDisplay').textContent = parseFloat(data.daily_total || 0).toFixed(2);

            if (!data.orders || data.orders.length === 0) {
                body.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-receipt" style="font-size:2rem;"></i><br>No orders today</div>';
                return;
            }

            let html = '';
            data.orders.forEach(o => {
                const statusColor = { paid: 'success', open: 'warning', voided: 'danger', refunded: 'info' };
                html += `
                    <div class="card mb-2 border-0 shadow-sm">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${o.order_number}</strong>
                                    <span class="badge bg-${statusColor[o.status] || 'secondary'} ms-2">${o.status}</span>
                                    <br><small class="text-muted">${o.customer_name || 'Walk-in'} • ${o.payment_method || '—'}</small>
                                </div>
                                <div class="text-end">
                                    <strong>${parseFloat(o.total).toFixed(2)}</strong>
                                    <br>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="POS.reprintReceipt(${o.id})" title="Print Receipt">
                                        <i class="bi bi-printer"></i>
                                    </button>
                                    ${o.status === 'paid' ? `<button class="btn btn-sm btn-outline-danger" onclick="POS.voidOrderConfirm(${o.id})" title="Void"><i class="bi bi-x-circle"></i></button>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            body.innerHTML = html;
        } catch (err) {
            body.innerHTML = '<div class="text-center text-danger py-4">Failed to load orders</div>';
        }
    },

    async reprintReceipt(orderId) {
        try {
            const resp = await fetch(`${this.BASE}/pos/receipt/${orderId}`);
            const html = await resp.text();
            this._printHtml(html);
        } catch (err) {
            alert('Failed to load receipt');
        }
    },

    async voidOrderConfirm(orderId) {
        if (!confirm('Void this order? This cannot be undone.')) return;
        try {
            await fetch(`${this.BASE}/pos/void-order/${orderId}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            this._loadOrders();
        } catch (err) {
            alert('Failed to void order');
        }
    },

    // ─── Utility ───
    escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    },
};

// ── Keyboard shortcut: F2 = focus search ──
document.addEventListener('keydown', e => {
    if (e.key === 'F2') {
        e.preventDefault();
        document.getElementById('posSearchInput').focus();
    }
});

// ── Initialize ──
POS.renderOrder();
</script>

<?= $this->endSection() ?>
