<?php /** Premium View Balance for vendors/employees */ ?>

<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Balance Inquiry<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<style>
    .filter-panel { 
        background: var(--gray-50); 
        border: 1px solid var(--gray-200); 
        border-radius: 10px; 
        padding: 1.25rem; 
    }
    body.theme-dark .filter-panel { 
        background: var(--gray-100); 
        border-color: var(--gray-300); 
    }

    .period-btn { min-width: 75px; }
    
    .health-progress-wrap { background: var(--gray-200); height: 8px; border-radius: 4px; overflow: hidden; margin: 10px 0; }
    .health-progress-bar { background: var(--success-color); height: 100%; transition: width 0.5s ease; }
    body.theme-dark .health-progress-wrap { background: var(--gray-800); }

    .empty-state-box { padding: 4rem 1rem; text-align: center; color: var(--gray-500); }
    .empty-state-box i { font-size: 3.5rem; opacity: 0.5; margin-bottom: 1rem; display: block; }

    .narrative-alert { border-left: 4px solid var(--primary-color); }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-end mb-3">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-wallet2 text-primary me-2"></i>Balance Inquiry</h4>
        <div class="small text-muted mt-1">Review payables, settlements, and advance balances</div>
    </div>
    <div class="btn-group btn-group-sm" id="formatToggleGroup" role="group" style="display:none;">
        <input type="radio" class="btn-check" name="formatToggle" id="fmtCompact" value="compact" autocomplete="off" checked>
        <label class="btn btn-outline-secondary" for="fmtCompact">Compact (e.g. 7M)</label>
        <input type="radio" class="btn-check" name="formatToggle" id="fmtFull" value="full" autocomplete="off">
        <label class="btn btn-outline-secondary" for="fmtFull">Full Value</label>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body p-2">
        <form id="balanceForm" class="row g-2 align-items-center m-0">
            <div class="col-auto">
                <select id="type" name="type" class="form-select form-select-sm border-0 bg-transparent fw-semibold text-muted shadow-none">
                    <option value="vendor">Vendor</option>
                    <option value="employee">Employee</option>
                </select>
            </div>
            <div class="col-md-3" id="vendorSelectWrap">
                <select id="vendor_id" name="id" class="form-select form-select-sm select2">
                    <option value="">-- Choose Vendor --</option>
                    <?php foreach (($vendors ?? []) as $v): ?>
                        <option value="<?= esc($v['id']) ?>"><?= esc($v['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-none" id="employeeSelectWrap">
                <select id="employee_id" name="id" class="form-select form-select-sm">
                    <option value="">-- Choose Employee --</option>
                    <?php foreach (($employees ?? []) as $e): ?>
                        <?php $label = trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')); ?>
                        <option value="<?= esc($e['id']) ?>"><?= esc($label ?: ('#'.$e['id'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-auto ms-auto">
                <div class="btn-group btn-group-sm border rounded" role="group" id="quickPeriods">
                    <button type="button" class="btn btn-light border-0 period-btn" data-val="1w">Week</button>
                    <button type="button" class="btn border-0 period-btn active bg-primary text-white" data-val="1m">Month</button>
                    <button type="button" class="btn btn-light border-0 period-btn" data-val="3m">Quarter</button>
                    <button type="button" class="btn btn-light border-0 period-btn" data-val="custom"><i class="bi bi-calendar3"></i></button>
                </div>
            </div>

            <div id="customRangeArea" class="col-auto d-none align-items-center gap-2">
                <input id="start_date" type="date" class="form-control form-control-sm px-2" style="width: 120px;">
                <span class="text-muted small">to</span>
                <input id="end_date" type="date" class="form-control form-control-sm px-2" style="width: 120px;">
            </div>
            
            <div class="col-auto">
                <button id="btnView" type="button" class="btn btn-primary btn-sm px-3 fw-bold">
                    <i class="bi bi-search"></i> Analyze
                </button>
            </div>
        </form>
    </div>
</div>

<div id="welcomeState" class="empty-state-box card border-0 shadow-sm mt-4">
    <i class="bi bi-bar-chart-steps"></i>
    <h5 class="fw-bold">Ready to analyze</h5>
    <p class="mb-0">Select an entity and a time period to generate a complete financial breakdown.</p>
</div>

<div id="resultArea" style="display:none">
    <div class="row mb-4">
        <!-- Card 1: Total Billed -->
        <div class="col-md-2">
            <div class="stats-card bg-gradient-secondary" style="background: linear-gradient(135deg, #64748b, #334155);">
                <div class="stats-content">
                    <i class="bi bi-receipt text-white opacity-75 stats-icon"></i>
                    <div class="stats-info">
                        <p class="text-uppercase text-light mb-1" style="font-size:0.7rem;">Total Billed</p>
                        <h3 id="totalAmt" class="fs-5 text-white" data-value="0">0.00</h3>
                        <div class="mt-2 small opacity-75 fw-normal text-white" id="billsCountMeta">0 bills</div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Card 2: Paid from Advance -->
        <div class="col-md-2">
            <div class="stats-card bg-gradient-success" style="background: linear-gradient(135deg, #4ade80, #22c55e);">
                <div class="stats-content">
                    <i class="bi bi-gift text-white opacity-75 stats-icon"></i>
                    <div class="stats-info">
                        <p class="text-uppercase text-white mb-1" style="font-size:0.7rem;">From Advance</p>
                        <h3 id="paidAdvanceAmt" class="fs-5 text-white" data-value="0">0.00</h3>
                        <div class="mt-2 small opacity-75 fw-normal text-white">Applied to bills</div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Card 3: Paid from Cash/Bank -->
        <div class="col-md-2">
            <div class="stats-card bg-gradient-info" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                <div class="stats-content">
                    <i class="bi bi-bank text-white opacity-75 stats-icon"></i>
                    <div class="stats-info">
                        <p class="text-uppercase text-white mb-1" style="font-size:0.7rem;">From Bank/Cash</p>
                        <h3 id="paidCashAmt" class="fs-5 text-white" data-value="0">0.00</h3>
                        <div class="mt-2 small opacity-75 fw-normal text-white" id="lastPaymentMeta">Last: N/A</div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Card 4: Available Advance -->
        <div class="col-md-2">
            <div class="stats-card bg-gradient-warning" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <div class="stats-content">
                    <i class="bi bi-arrow-down-square text-white opacity-75 stats-icon"></i>
                    <div class="stats-info">
                        <p class="text-uppercase text-white mb-1" style="font-size:0.7rem;">Available Advance</p>
                        <h3 id="advanceAmt" class="fs-5 text-white" data-value="0">0.00</h3>
                        <div class="mt-2 small opacity-75 fw-normal text-white">To use</div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Card 5: Owed / Balance -->
        <div class="col-md-2">
            <div class="stats-card bg-gradient-primary" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                <div class="stats-content">
                    <i class="bi bi-exclamation-circle text-white opacity-75 stats-icon"></i>
                    <div class="stats-info">
                        <p class="text-uppercase text-white mb-1" style="font-size:0.7rem;">Balance Due</p>
                        <h3 id="owedAmt" class="fs-5 text-white" data-value="0">0.00</h3>
                        <div class="mt-2 small opacity-75 fw-normal text-white" id="overdueWarnTag">Action required</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Health Ribbon (Moved below cards) -->
    <div class="card shadow-sm border border-secondary border-opacity-25 mb-4 bg-body-tertiary">
        <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-3 flex-grow-1">
                <div>
                    <h6 class="fw-bold mb-1 text-body"><i class="bi bi-activity text-primary me-2"></i>Overall Payment Health</h6>
                    <div class="small fw-medium text-body-secondary" id="narrativeSummary">Fetching narrative analysis...</div>
                </div>
                <div class="flex-grow-1 ms-4 d-none d-md-block">
                    <div class="health-progress-wrap mb-0 border border-secondary border-opacity-10 bg-body-secondary" style="height: 6px;">
                        <div id="healthBar" class="health-progress-bar bg-success" style="box-shadow: 0 0 5px rgba(16, 185, 129, 0.4);"></div>
                    </div>
                </div>
            </div>
            <div class="text-end">
                <span id="healthScore" class="fs-4 fw-black text-success" style="letter-spacing: -0.5px;">0% Settled</span>
            </div>
        </div>
    </div>

                    <div id="entriesArea">
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                                <h6 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2"></i>Payment Breakdown</h6>
                                <span class="badge bg-secondary" id="breakdownCount">Calculating...</span>
                            </div>
                            <div class="card-body p-0 border-top">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="breakdownTable">
                                        <thead class="table-light text-secondary" style="font-size:0.85rem;">
                                            <tr>
                                                <th style="width:110px">Date</th>
                                                <th style="width:150px">Bill #</th>
                                                <th style="width:120px">Payment #</th>
                                                <th class="text-end">From Advance</th>
                                                <th class="text-end">From Bank/Cash</th>
                                                <th class="text-end">Total</th>
                                                <th style="width:200px">Notes / Memo</th>
                                            </tr>
                                        </thead>
                                        <tbody id="breakdownList" style="font-size:0.9rem;">
                                            <tr style="height:60px;"><td colspan="7" class="text-center text-muted">Refreshing data...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                                <h6 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2"></i>Recent Transactions</h6>
                                <span class="badge bg-secondary" id="historyCount">0 entries</span>
                            </div>
                            <div class="card-body p-0 border-top">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="txnTable">
                                        <thead class="table-light text-secondary">
                                            <tr>
                                                <th style="width:120px">Date</th>
                                                <th>Vendor Bill #</th>
                                                <th>Payment Number</th>
                                                <th class="text-end">Amount</th>
                                                <th class="actions-col text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="entriesList">
                                            <!-- Dynamically injected -->
                                        </tbody>
                                    </table>
                                </div>
                                <div id="entriesLoader" class="p-4 text-center d-none">
                                    <div class="spinner-border spinner-border-sm text-secondary me-2"></div> Loading...
                                </div>
                                <div id="entriesPagerArea" class="p-3 text-center border-top">
                                    <button class="btn btn-sm btn-outline-secondary" id="btnLoadMore" style="display:none">Load More</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
(function(){
    const typeEl = document.getElementById('type');
    const vendorWrap = document.getElementById('vendorSelectWrap');
    const employeeWrap = document.getElementById('employeeSelectWrap');
    const customRangeArea = document.getElementById('customRangeArea');
    const btn = document.getElementById('btnView');
    const welcome = document.getElementById('welcomeState');
    const result = document.getElementById('resultArea');
    
    // Quick period logic
    const quickBtns = document.querySelectorAll('.period-btn');
    const startEl = document.getElementById('start_date');
    const endEl = document.getElementById('end_date');

    const setDates = (val) => {
        const today = new Date();
        const start = new Date();
        if (val === '1w') start.setDate(today.getDate() - 7);
        else if (val === '1m') start.setMonth(today.getMonth() - 1);
        else if (val === '3m') start.setMonth(today.getMonth() - 3);
        
        startEl.value = start.toISOString().split('T')[0];
        endEl.value = today.toISOString().split('T')[0];
    };
    setDates('1m'); // Default

    quickBtns.forEach(b => {
        b.addEventListener('click', () => {
            quickBtns.forEach(x => {
                x.classList.remove('active');
                x.classList.replace('btn-secondary', 'btn-outline-secondary');
            });
            b.classList.add('active');
            b.classList.replace('btn-outline-secondary', 'btn-secondary');

            const val = b.dataset.val;
            if (val === 'custom') {
                customRangeArea.classList.remove('d-none');
            } else {
                customRangeArea.classList.add('d-none');
                setDates(val);
            }
        });
    });
    // Set active state styling for default
    document.querySelector('.period-btn.active').classList.replace('btn-outline-secondary', 'btn-secondary');

    typeEl.addEventListener('change', () => {
        if (typeEl.value === 'vendor') {
            vendorWrap.classList.remove('d-none'); employeeWrap.classList.add('d-none');
        } else {
            vendorWrap.classList.add('d-none'); employeeWrap.classList.remove('d-none');
        }
    });

    // Formatting helpers
    const fmt = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'PKR' });
    const fmtCompact = new Intl.NumberFormat('en-US', { notation: 'compact', currency: 'PKR' });
    
    const formatDateObj = (dStr) => {
        if (!dStr) return '';
        const parts = dStr.split('-');
        if (parts.length === 3) return `${parts[2]}-${parts[1]}-${parts[0]}`;
        return dStr;
    };

    const getFormat = () => document.getElementById('fmtFull').checked ? fmt : fmtCompact;

    const updateDisplayFormats = () => {
        const formatter = getFormat();
        const updateVal = (id) => {
            const el = document.getElementById(id);
            if (el && el.dataset.value !== undefined) {
                el.innerText = formatter.format(parseFloat(el.dataset.value) || 0);
            }
        };
        updateVal('totalAmt');
        updateVal('paidAdvanceAmt');
        updateVal('paidCashAmt');
        updateVal('advanceAmt');
        updateVal('owedAmt');
    };

    document.querySelectorAll('input[name="formatToggle"]').forEach(el => {
        el.addEventListener('change', updateDisplayFormats);
    });

    btn.addEventListener('click', () => {
        const type = typeEl.value;
        const id = (type === 'vendor') ? (window.jQuery ? $('#vendor_id').val() : document.getElementById('vendor_id').value) : (window.jQuery ? $('#employee_id').val() : document.getElementById('employee_id').value);
        
        if (!id) { 
            welcome.innerHTML = `<i class="bi bi-exclamation-triangle text-warning"></i>
                                 <h5 class="fw-bold mt-2">Selection Required</h5>
                                 <p class="text-danger">Please choose a ${type} from the dropdown before analyzing.</p>`;
            welcome.style.display = 'block';
            result.style.display = 'none';
            return; 
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Analyze';
        
        welcome.style.display = 'none';
        result.style.display = 'none';
        document.getElementById('formatToggleGroup').style.display = 'none';

        fetch(`<?= base_url('/accounting/cheques/balanceData') ?>?type=${type}&id=${id}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.json())
            .then(json => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-search"></i> Analyze';
                
                if (!json.success) { 
                    welcome.innerHTML = `<i class="bi bi-x-circle text-danger"></i>
                                         <h5 class="fw-bold mt-2 text-danger">Analysis Failed</h5>
                                         <p class="text-secondary">${json.message}</p>`;
                    welcome.style.display = 'block'; 
                    document.getElementById('formatToggleGroup').style.display = 'none';
                    return; 
                }

                result.style.display = 'block';
                document.getElementById('formatToggleGroup').style.display = 'inline-flex';

                const d = json.details || {};
                
                document.getElementById('totalAmt').dataset.value = json.total_bills || 0;
                document.getElementById('paidAdvanceAmt').dataset.value = d.paid_from_advance || 0;
                document.getElementById('paidCashAmt').dataset.value = d.paid_from_cash_bank || 0;
                document.getElementById('advanceAmt').dataset.value = json.advance || 0;
                document.getElementById('owedAmt').dataset.value = json.owed || 0;
                
                updateDisplayFormats();

                document.getElementById('lastPaymentMeta').innerText = d.last_payment_date ? `Last: ${formatDateObj(d.last_payment_date)}` : 'No previous payments';
                document.getElementById('billsCountMeta').innerText = `${d.bills_count || 0} confirmed bills`;
                
                if (d.overdue_amount > 0) {
                    document.getElementById('overdueWarnTag').innerHTML = `<i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> ${fmtCompact.format(d.overdue_amount)} Overdue!`;
                } else {
                    document.getElementById('overdueWarnTag').innerText = "Current and valid";
                }
                
                const health = d.health_percentage || 0;
                document.getElementById('healthScore').innerText = `${health}% Settled`;
                document.getElementById('healthBar').style.width = `${health}%`;
                
                let narrative = '';
                if (type === 'vendor') {
                    if (d.overdue_amount > 0) {
                        narrative = `<strong class="text-danger"><i class="bi bi-exclamation-octagon me-1"></i> Action Required:</strong> You have <strong>${fmt.format(d.overdue_amount)}</strong> overdue across <strong>${d.pending_bills}</strong> bills.`;
                    } else if (json.owed > 0) {
                        narrative = `<strong class="text-body"><i class="bi bi-info-circle text-primary me-1"></i> Status Good:</strong> Owed amount is <strong>${fmt.format(json.owed)}</strong>, but all payments are currently within their due dates.`;
                    } else if (json.total_bills > 0) {
                        narrative = `<strong class="text-success"><i class="bi bi-check-circle me-1"></i> Excellent:</strong> All bills with this vendor are fully settled.`;
                    } else {
                        narrative = "No bill history found for this vendor.";
                    }
                } else {
                    narrative = `Total payments processed: <strong>${fmt.format(json.paid)}</strong>.`;
                }
                document.getElementById('narrativeSummary').innerHTML = narrative;

                fetchPaymentBreakdown();
                fetchEntries(1, true);
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-search"></i> Analyze';
                welcome.innerHTML = `<i class="bi bi-wifi-off text-danger"></i>
                                     <h5 class="fw-bold mt-2 text-danger">Connection Error</h5>
                                     <p class="text-secondary">Could not fetch data. Please try again.</p>`;
                welcome.style.display = 'block';
                document.getElementById('formatToggleGroup').style.display = 'none';
            });
    });

    let currentTxns = [];
    let shownTxns = 0;
    const PAGE_SIZE = 15;

    function fetchPaymentBreakdown() {
        const type = typeEl.value;
        const id = (type === 'vendor') ? (window.jQuery ? $('#vendor_id').val() : document.getElementById('vendor_id').value) : (window.jQuery ? $('#employee_id').val() : document.getElementById('employee_id').value);
        
        fetch(`<?= base_url('/accounting/cheques/paymentBreakdown') ?>?type=${type}&id=${id}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.json())
            .then(json => {
                if (!json.success || !json.payments || !json.payments.length) {
                    document.getElementById('breakdownCount').innerText = '0 payments';
                    document.getElementById('breakdownList').innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No payments found for this ' + type + '.</td></tr>';
                    return;
                }

                const tbody = document.getElementById('breakdownList');
                tbody.innerHTML = '';
                
                const fmt = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'PKR' });
                
                json.payments.forEach(p => {
                    const advAmount = parseFloat(p.advance_amount || 0);
                    const cashAmount = parseFloat(p.amount || 0) - advAmount;
                    const totalAmount = parseFloat(p.amount || 0);

                    // Format date as DD-MM-YYYY
                    let formattedDate = 'N/A';
                    if (p.payment_date) {
                        const d = new Date(p.payment_date);
                        if (!isNaN(d)) {
                            const day = String(d.getDate()).padStart(2, '0');
                            const month = String(d.getMonth() + 1).padStart(2, '0');
                            const year = d.getFullYear();
                            formattedDate = `${day}-${month}-${year}`;
                        }
                    }

                    // Combine notes and memo if present
                    let notesMemo = '';
                    if (p.notes && p.memo) {
                        notesMemo = `<div><span class='text-muted'>${p.notes}</span><br><span class='text-info'>${p.memo}</span></div>`;
                    } else if (p.notes) {
                        notesMemo = `<span class='text-muted'>${p.notes}</span>`;
                    } else if (p.memo) {
                        notesMemo = `<span class='text-info'>${p.memo}</span>`;
                    } else {
                        notesMemo = '<span class="text-secondary">-</span>';
                    }

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><small class=\"fw-semibold text-secondary\">${formattedDate}</small></td>
                        <td><small class=\"text-muted\">${p.bill_number || 'N/A'}</small></td>
                        <td><span class=\"badge bg-light border text-secondary\">Payment #${p.payment_id}</span></td>
                        <td class=\"text-end\"><span style=\"color:#4ade80; font-weight:600;\">${advAmount > 0 ? fmt.format(advAmount) : '-'}</span></td>
                        <td class=\"text-end\"><span style=\"color:#3b82f6; font-weight:600;\">${cashAmount > 0 ? fmt.format(cashAmount) : '-'}</span></td>
                        <td class=\"text-end\"><strong>${fmt.format(totalAmount)}</strong></td>
                        <td>${notesMemo}</td>
                    `;
                    tbody.appendChild(tr);
                });
                
                document.getElementById('breakdownCount').innerText = `${json.payments.length} payments`;
            })
            .catch(_ => {
                document.getElementById('breakdownCount').innerText = 'Error';
                document.getElementById('breakdownList').innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Failed to load payment breakdown.</td></tr>';
            });
    }

    function fetchEntries(page = 1, forceRefresh = false) {
        const type = typeEl.value;
        const id = (type === 'vendor') ? (window.jQuery ? $('#vendor_id').val() : document.getElementById('vendor_id').value) : (window.jQuery ? $('#employee_id').val() : document.getElementById('employee_id').value);
        const s = startEl.value;
        const e = endEl.value;

        const tbody = document.getElementById('entriesList');
        const loader = document.getElementById('entriesLoader');
        const btnMore = document.getElementById('btnLoadMore');

        if (forceRefresh) {
            tbody.innerHTML = '';
            btnMore.style.display = 'none';
            currentTxns = [];
            shownTxns = 0;
        }

        loader.classList.remove('d-none');

        fetch(`<?= base_url('/accounting/cheques/balanceEntries') ?>?type=${type}&id=${id}&start_date=${s}&end_date=${e}&page=${page}&per_page=100`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.json())
            .then(json => {
                loader.classList.add('d-none');
                
                if (!json.success || !json.data.length) {
                    if (forceRefresh) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No transactions found for the selected period.</td></tr>';
                        document.getElementById('historyCount').innerText = '0 entries';
                    }
                    return;
                }

                // Flatten grouped data if nested
                let flat = [];
                if (json.data && json.data.length && json.data[0].items !== undefined) {
                    json.data.forEach(g => flat.push(...g.items));
                } else {
                    flat = json.data;
                }
                
                // Sort desc
                flat.sort((a,b) => String(b.date).localeCompare(String(a.date)));
                
                currentTxns = flat;
                document.getElementById('historyCount').innerText = `${json.total || flat.length} entries found`;
                
                renderNextBatch();
            });
    }

    function renderNextBatch() {
        const tbody = document.getElementById('entriesList');
        const endIdx = Math.min(shownTxns + PAGE_SIZE, currentTxns.length);
        
        for (let i = shownTxns; i < endIdx; i++) {
            const it = currentTxns[i];
            const badgeClass = it.type === 'Cheque' ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-success-subtle text-success border border-success-subtle';
            
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="py-1 align-middle">
                    <span class="small fw-semibold text-body-secondary">${formatDateObj(it.date)}</span>
                </td>
                <td class="py-1 align-middle">
                    ${it.bills ? `<span class="small fw-medium text-dark"><i class="bi bi-hash text-muted"></i>${it.bills}</span>` : '<span class="small text-muted fst-italic">N/A</span>'}
                </td>
                <td class="py-1 align-middle">
                    <span class="badge rounded bg-light border text-secondary me-1 py-1" style="font-size:0.75rem;">${it.type}</span>
                    <span class="small fw-medium text-secondary">${it.label || ''}</span>
                </td>
                <td class="py-1 align-middle text-end fw-bold small">${fmt.format(it.amount)}</td>
                <td class="py-1 align-middle actions-col text-end">
                    <a href="${it.url || '#'}" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Document">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            `;
            tbody.appendChild(tr);
        }
        
        shownTxns = endIdx;
        const btnMore = document.getElementById('btnLoadMore');
        
        if (shownTxns < currentTxns.length) {
            btnMore.style.display = 'inline-block';
            btnMore.onclick = renderNextBatch;
        } else {
            btnMore.style.display = 'none';
        }
    }
})();
</script>
<?= $this->endSection() ?>
