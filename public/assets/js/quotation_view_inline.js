(function(){
    // Inline edit / save for quotation lines
    function qs(sel, el){ if (!el) el = document; return el.querySelector(sel); }
    function qsa(sel, el){ if (!el) el = document; return Array.prototype.slice.call(el.querySelectorAll(sel)); }

    var editingAllowed = true;

    function toNumber(v){ return v===null||v===undefined||v===''?0:parseFloat(String(v).replace(/,/g,''))||0; }

    function escapeHtml(v){
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // Modern non-blocking notifications for save/errors on quotation page.
    function ensureQuoteToastApi(){
        if (typeof window.quoteNotify === 'function') return;

        var styleId = 'quote-toast-style';
        if (!document.getElementById(styleId)) {
            var st = document.createElement('style');
            st.id = styleId;
            st.textContent = '' +
                '.quote-toast-container{position:fixed;top:16px;right:16px;z-index:2000;display:flex;flex-direction:column;gap:8px;max-width:360px;}' +
                '.quote-toast{border-radius:10px;padding:10px 12px;font-size:12px;line-height:1.35;box-shadow:0 8px 22px rgba(0,0,0,.35);border:1px solid rgba(148,163,184,.25);background:#0f172a;color:#e2e8f0;opacity:.98;}' +
                '.quote-toast.error{border-left:4px solid #ef4444;}' +
                '.quote-toast.success{border-left:4px solid #10b981;}' +
                '.quote-toast.info{border-left:4px solid #3b82f6;}';
            document.head.appendChild(st);
        }

        window.quoteNotify = function(message, level){
            var lvl = (level || 'info').toLowerCase();
            var host = document.getElementById('quote-toast-container');
            if (!host) {
                host = document.createElement('div');
                host.id = 'quote-toast-container';
                host.className = 'quote-toast-container';
                document.body.appendChild(host);
            }
            var toast = document.createElement('div');
            toast.className = 'quote-toast ' + (lvl === 'error' ? 'error' : (lvl === 'success' ? 'success' : 'info'));
            toast.textContent = message;
            host.appendChild(toast);
            setTimeout(function(){ toast.remove(); }, 4200);
        };
    }

    function notify(message, level){
        ensureQuoteToastApi();
        if (typeof window.quoteNotify === 'function') {
            window.quoteNotify(message, level);
            return;
        }
        alert(message);
    }

    function lineDisplayText(line){
        var name = (line && line.product_name ? String(line.product_name) : '').trim();
        var desc = (line && line.description ? String(line.description) : '').trim();
        if (name) return name;
        if (desc) return desc;
        var code = (line && line.product_code ? String(line.product_code) : '').trim();
        return code || 'New line';
    }

    function lineDiscountTypeNormalized(v){
        var t = String(v || 'percent').toLowerCase();
        return (t === 'fixed' ? 'fixed' : 'percent');
    }

    function buildStaticRowHtml(line){
        var discType = lineDiscountTypeNormalized(line.discount_type);
        var discLabel = (discType === 'fixed' ? 'Fix' : '%');
        var appBase = window.location.pathname.split('/quotations/')[0] || '';
        var fallbackImg = window.location.origin + appBase + '/assets/images/no-image.png';
        var img = (line.product_image_url && String(line.product_image_url).trim())
            ? String(line.product_image_url).trim()
            : fallbackImg;
        var code = String(line.product_code || '').trim();
        var text = lineDisplayText(line);
        var desc = String(line.description || '').trim();
        var unitRaw = String(line.unit || 'PCS').trim() || 'PCS';
        var unit = unitRaw.toUpperCase();
        var qty = toNumber(line.quantity).toFixed(2).replace(/\.00$/, '');
        var price = toNumber(line.unit_price).toFixed(2);
        var discValue = toNumber(line.discount_value).toFixed(2).replace(/\.00$/, '');
        var discAmt = toNumber(line.discount_amount).toFixed(2);
        var taxType = String(line.tax_type || 'percent').toLowerCase();
        if (taxType !== 'fixed') taxType = 'percent';
        var taxLabel = (taxType === 'fixed' ? 'Fix' : '%');
        var taxValue = toNumber(line.tax_value != null ? line.tax_value : line.tax_rate).toFixed(2).replace(/\.00$/, '');
        var taxAmt = toNumber(line.tax_amount).toFixed(2);
        var lineTotal = toNumber(line.line_total).toFixed(2);

        return '' +
            '<td class="line-code">' + escapeHtml(code) + '</td>' +
            '<td><img src="' + escapeHtml(img) + '" alt="" class="quote-line-image quote-image-thumb js-product-hover-thumb" data-preview-src="' + escapeHtml(img) + '" style="width:40px;height:40px;object-fit:cover;border-radius:4px" onerror="this.onerror=null;this.src=\'' + escapeHtml(fallbackImg) + '\';this.setAttribute(\'data-preview-src\',\'' + escapeHtml(fallbackImg) + '\');"></td>' +
            '<td class="line-desc">' +
                '<div class="fw-semibold" style="line-height:1.2;">' + escapeHtml(text) + '</div>' +
                ((desc && desc !== text) ? ('<div class="text-muted" style="font-size:0.8rem; line-height:1.2;">' + escapeHtml(desc) + '</div>') : '') +
            '</td>' +
            '<td class="line-unit">' + escapeHtml(unit) + '</td>' +
            '<td class="text-end line-qty">' + qty + '</td>' +
            '<td class="text-end line-unit-price">' + price + '</td>' +
            '<td class="text-end line-disc-percent col-disc" data-discount-type="' + discType + '">' +
                '<span class="badge bg-secondary-subtle text-light-emphasis me-1" style="font-size:0.68rem;">' + escapeHtml(discLabel) + '</span>' +
                '<span>' + discValue + '</span>' +
            '</td>' +
            '<td class="text-end line-disc-amt col-disc">' + discAmt + '</td>' +
            '<td class="text-end line-tax-percent col-tax" data-tax-type="' + taxType + '">' +
                '<span class="badge bg-secondary-subtle text-light-emphasis me-1" style="font-size:0.68rem;">' + escapeHtml(taxLabel) + '</span>' +
                '<span>' + taxValue + '</span>' +
            '</td>' +
            '<td class="text-end line-tax-amt col-tax">' + taxAmt + '</td>' +
            '<td class="text-end line-total">' + lineTotal + '</td>' +
            '<td><div class="btn-group" role="group">' +
                '<button type="button" class="btn btn-sm btn-outline-secondary btn-edit-line">Edit</button>' +
                '<button type="button" class="btn btn-sm btn-success btn-save-line" style="display:none">Save</button>' +
                '<button type="button" class="btn btn-sm btn-outline-danger btn-cancel-line" style="display:none">Cancel</button>' +
            '</div></td>';
    }

    function createInlineNewRow(tbody){
        var tr = document.createElement('tr');
        tr.className = 'line-new-row';
        tr.innerHTML = '' +
            '<td>' +
                '<input type="text" class="form-control form-control-sm new-line-code product-code" placeholder="Code">' +
                '<input type="hidden" class="product-id" value="">' +
                '<input type="hidden" class="product-variant-id" value="">' +
                '<input type="hidden" class="product-image-url" value="">' +
                '<input type="hidden" class="unit-weight" value="0">' +
                '<input type="hidden" class="weight-unit" value="KG">' +
            '</td>' +
            '<td style="vertical-align:middle;padding:2px;">' +
                '<div class="product-thumb-wrap" style="width:32px;height:32px;border-radius:4px;overflow:hidden;background:#0b1220;display:flex;align-items:center;justify-content:center;position:relative;">' +
                    '<img src="" class="product-thumb" style="width:32px;height:32px;object-fit:cover;display:none" alt="" onload="this.style.display=\'block\';if(this.parentElement){var ic=this.parentElement.querySelector(\'.thumb-icon\');if(ic)ic.style.display=\'none\';}" onerror="this.style.display=\'none\';if(this.parentElement){var ic=this.parentElement.querySelector(\'.thumb-icon\');if(ic)ic.style.display=\'flex\';}">' +
                    '<div class="thumb-icon" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#94a3b8;">' +
                        '<i class="bi bi-image"></i>' +
                    '</div>' +
                '</div>' +
            '</td>' +
            '<td><input type="text" class="form-control form-control-sm new-line-name product-name line-desc" placeholder="Product / Description"></td>' +
            '<td><input type="text" class="form-control form-control-sm new-line-unit line-unit" value="PCS"></td>' +
            '<td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm text-end new-line-qty line-qty" value="1"></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end new-line-price line-price" value="0"></td>' +
            '<td class="col-disc">' +
                '<div class="d-flex gap-1 justify-content-end align-items-center">' +
                    '<select class="form-select form-select-sm new-line-disc-type line-discount-type" style="width:74px; min-width:74px;">' +
                        '<option value="percent" selected>%</option>' +
                        '<option value="fixed">Fix</option>' +
                    '</select>' +
                    '<input type="number" step="0.01" min="0" class="form-control form-control-sm text-end new-line-disc line-discount" style="width:86px; min-width:86px;" value="0">' +
                '</div>' +
            '</td>' +
            '<td class="text-end col-disc">0.00</td>' +
            '<td class="col-tax">' +
                '<div class="d-flex gap-1 justify-content-end align-items-center">' +
                    '<select class="form-select form-select-sm new-line-tax-type line-tax-type" style="width:74px; min-width:74px;">' +
                        '<option value="percent" selected>%</option>' +
                        '<option value="fixed">Fix</option>' +
                    '</select>' +
                    '<input type="number" step="0.01" min="0" class="form-control form-control-sm text-end new-line-tax line-tax" value="0">' +
                '</div>' +
            '</td>' +
            '<td class="text-end col-tax">0.00</td>' +
            '<td class="text-end">0.00</td>' +
            '<td><div class="btn-group" role="group">' +
                '<button type="button" class="btn btn-sm btn-success btn-create-line">Save</button>' +
                '<button type="button" class="btn btn-sm btn-outline-danger btn-cancel-create-line">Cancel</button>' +
            '</div></td>';

        tbody.appendChild(tr);
        return tr;
    }

    function attachCreateRowAutocomplete(row){
        if (!row || !window.CoreLynkAutocomplete || typeof window.CoreLynkAutocomplete.attachProductAutocomplete !== 'function') return;
        var codeInput = row.querySelector('input.product-code');
        var nameInput = row.querySelector('input.product-name');

        function tryAttach(inputEl, byName, attempt){
            if (!inputEl) return;
            var n = attempt || 0;
            try {
                if (inputEl.dataset) delete inputEl.dataset.autocompleteAttached;
                window.CoreLynkAutocomplete.attachProductAutocomplete(inputEl, !!byName);
            } catch (e) {
                if (n < 8) {
                    setTimeout(function(){ tryAttach(inputEl, byName, n + 1); }, 120);
                }
            }
        }

        tryAttach(codeInput, false, 0);
        tryAttach(nameInput, true, 0);
    }

    function attachRowHandlers(row){
        var id = row.getAttribute('data-line-id');
        var btnEdit = row.querySelector('.btn-edit-line');
        var btnSave = row.querySelector('.btn-save-line');
        var btnCancel = row.querySelector('.btn-cancel-line');
        var btnDelete = row.querySelector('.btn-delete-line');

        if (!editingAllowed) {
            if (btnEdit) btnEdit.disabled = true;
            if (btnSave) btnSave.style.display = 'none';
            if (btnCancel) btnCancel.style.display = 'none';
            if (btnDelete) btnDelete.disabled = true;
            return;
        }

        if (!btnEdit) return;

        // Delete button handler
        if (btnDelete) {
            btnDelete.addEventListener('click', function(){
                if (!confirm('Are you sure you want to delete this line? This action cannot be undone.')) {
                    return;
                }

                var appBase = window.location.pathname.split('/quotations/')[0] || '';
                var quoteId = (typeof window !== 'undefined' && window.quoteId) ? window.quoteId : 0;
                
                fetch(window.location.origin + appBase + '/quotations/delete-line/' + quoteId, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify({ line_id: id })
                }).then(function(resp){ return resp.text(); }).then(function(text){
                    var json = null;
                    try { json = JSON.parse(text); } catch(e){ notify('Invalid response from server', 'error'); console.error(text); return; }
                    if (!json || !json.success){ notify('Delete failed: ' + (json && json.error ? json.error : 'unknown'), 'error'); return; }

                    // Remove row from table
                    if (row && row.parentNode) {
                        row.parentNode.removeChild(row);
                    }

                    // Update totals
                    if (json.totals) {
                        var s = parseFloat(json.totals.subtotal).toFixed(2);
                        var d = parseFloat(json.totals.discount).toFixed(2);
                        var t = parseFloat(json.totals.tax).toFixed(2);
                        var wt = json.totals.total_weight != null ? (parseFloat(json.totals.total_weight).toFixed(3)) : null;
                        var g = parseFloat(json.totals.total).toFixed(2);
                        
                        document.getElementById('view-subtotal').textContent = s;
                        
                        // discount: show as negative and toggle row
                        var rowDisc = document.getElementById('row-discount');
                        if (rowDisc) {
                            if (parseFloat(d) > 0) {
                                rowDisc.style.display = '';
                                document.getElementById('view-discount').textContent = '-' + parseFloat(d).toFixed(2);
                            } else {
                                rowDisc.style.display = 'none';
                            }
                        }
                        
                        // tax: toggle row
                        var rowTax = document.getElementById('row-tax');
                        if (rowTax) {
                            if (parseFloat(t) > 0) {
                                rowTax.style.display = '';
                                document.getElementById('view-tax').textContent = parseFloat(t).toFixed(2);
                            } else {
                                rowTax.style.display = 'none';
                            }
                        }
                        
                        var wtEl = document.getElementById('view-weight');
                        if (wtEl && wt !== null && wtEl.dataset.weightMode !== 'draft') {
                            wtEl.textContent = (wt >= 1 ? parseFloat(wt).toFixed(3) + ' kg' : Math.round(parseFloat(wt) * 1000) + ' g');
                            wtEl.dataset.weightMode = 'calculated';
                        }
                        
                        document.getElementById('view-total').textContent = g;
                    }

                    notify('Line deleted successfully.', 'success');
                }).catch(function(e){ notify('Delete failed: ' + e.toString(), 'error'); });
            });
        }

        btnEdit.addEventListener('click', function(){
            var toggleDiscTax = document.getElementById('toggle-discount-tax');
            if (toggleDiscTax && !toggleDiscTax.checked) {
                toggleDiscTax.checked = true;
                toggleDiscTax.dispatchEvent(new Event('change'));
            }

            // show inputs for editable cells
            var qtyCell = row.querySelector('.line-qty');
            var priceCell = row.querySelector('.line-unit-price');
            var discCell = row.querySelector('.line-disc-percent');
            var taxCell = row.querySelector('.line-tax-percent');
            var descCell = row.querySelector('.line-desc');

            qtyCell._orig = qtyCell.textContent.trim();
            priceCell._orig = priceCell.textContent.trim();
            discCell._orig = discCell.textContent.trim();
            discCell._origType = (discCell.getAttribute('data-discount-type') || row.getAttribute('data-discount-type') || 'percent').toLowerCase();
            taxCell._orig = taxCell.textContent.trim();
            taxCell._origType = (taxCell.getAttribute('data-tax-type') || row.getAttribute('data-tax-type') || 'percent').toLowerCase();
            descCell._orig = descCell.textContent.trim();

            var discNum = discCell._orig;
            if (discNum.indexOf(' ') !== -1) {
                discNum = discNum.split(' ')[0];
            }

            qtyCell.innerHTML = '<input type="number" step="0.01" class="form-control form-control-sm inline-qty" value="' + qtyCell._orig + '">';
            priceCell.innerHTML = '<input type="number" step="0.01" class="form-control form-control-sm inline-price text-end" value="' + priceCell._orig.replace(/,/g,'') + '">';
            discCell.innerHTML =
                '<div class="d-flex gap-1 justify-content-end align-items-center">' +
                    '<select class="form-select form-select-sm inline-disc-type" style="width:74px; min-width:74px;">' +
                        '<option value="percent"' + (discCell._origType === 'percent' ? ' selected' : '') + '>%</option>' +
                        '<option value="fixed"' + (discCell._origType === 'fixed' ? ' selected' : '') + '>Fix</option>' +
                    '</select>' +
                    '<input type="number" step="0.01" class="form-control form-control-sm inline-disc text-end" style="width:86px; min-width:86px;" value="' + discNum + '">' +
                '</div>';
            taxCell.innerHTML =
                '<div class="d-flex gap-1 justify-content-end align-items-center">' +
                    '<select class="form-select form-select-sm inline-tax-type" style="width:74px; min-width:74px;">' +
                        '<option value="percent"' + (taxCell._origType === 'fixed' ? '' : ' selected') + '>%</option>' +
                        '<option value="fixed"' + (taxCell._origType === 'fixed' ? ' selected' : '') + '>Fix</option>' +
                    '</select>' +
                    '<input type="number" step="0.01" class="form-control form-control-sm inline-tax text-end" value="' + taxCell._orig.replace(/[^0-9.\-]/g, '') + '">' +
                '</div>';
            descCell.innerHTML = '<input type="text" class="form-control form-control-sm inline-desc" value="' + descCell._orig + '">';

            btnEdit.style.display='none'; btnSave.style.display='inline-block'; btnCancel.style.display='inline-block';
        });

        btnCancel.addEventListener('click', function(){
            // restore
            var qtyCell = row.querySelector('.line-qty');
            var priceCell = row.querySelector('.line-unit-price');
            var discCell = row.querySelector('.line-disc-percent');
            var taxCell = row.querySelector('.line-tax-percent');
            var descCell = row.querySelector('.line-desc');
            qtyCell.textContent = qtyCell._orig;
            priceCell.textContent = priceCell._orig;
            discCell.textContent = discCell._orig;
            taxCell.textContent = taxCell._orig;
            descCell.textContent = descCell._orig;
            btnEdit.style.display='inline-block'; btnSave.style.display='none'; btnCancel.style.display='none';
        });

        btnSave.addEventListener('click', function(){
            var qty = toNumber(row.querySelector('.inline-qty').value);
            var price = toNumber(row.querySelector('.inline-price').value);
            var disc = toNumber(row.querySelector('.inline-disc').value);
            var discType = (row.querySelector('.inline-disc-type') ? row.querySelector('.inline-disc-type').value : 'percent');
            var tax = toNumber(row.querySelector('.inline-tax').value);
            var taxType = (row.querySelector('.inline-tax-type') ? row.querySelector('.inline-tax-type').value : 'percent');
            var desc = row.querySelector('.inline-desc').value;

            var payload = { quantity: qty, unit_price: price, discount_value: disc, discount_type: discType, tax_type: taxType, tax_value: tax, tax_rate: tax, description: desc };

            var appBase = window.location.pathname.split('/quotations/')[0] || '';
            fetch(window.location.origin + appBase + '/quotations/update-line/' + id, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function(resp){ return resp.text(); }).then(function(text){
                var json = null;
                try { json = JSON.parse(text); } catch(e){ notify('Invalid response from server', 'error'); console.error(text); return; }
                if (!json || !json.success){ notify('Save failed: ' + (json && json.error ? json.error : 'unknown'), 'error'); return; }

                var line = json.line;
                // update cells with returned values
                row.querySelector('.line-qty').textContent = line.quantity;
                row.querySelector('.line-unit-price').textContent = parseFloat(line.unit_price).toFixed(2);
                var lineDiscType = (line.discount_type || 'percent').toLowerCase();
                var lineDiscLabel = lineDiscType === 'fixed' ? 'Fix' : '%';
                var discCellOut = row.querySelector('.line-disc-percent');
                discCellOut.innerHTML = '<span class="badge bg-secondary-subtle text-light-emphasis me-1" style="font-size:0.68rem;">' + lineDiscLabel + '</span><span>' + (line.discount_value != null ? line.discount_value : '') + '</span>';
                discCellOut.setAttribute('data-discount-type', lineDiscType);
                row.setAttribute('data-discount-type', lineDiscType);
                row.querySelector('.line-disc-amt').textContent = parseFloat((line.discount_amount != null ? line.discount_amount : 0)).toFixed(2);
                var lineTaxType = String(line.tax_type || 'percent').toLowerCase();
                if (lineTaxType !== 'fixed') lineTaxType = 'percent';
                var lineTaxLabel = lineTaxType === 'fixed' ? 'Fix' : '%';
                var lineTaxValue = (line.tax_value != null ? line.tax_value : line.tax_rate);
                row.querySelector('.line-tax-percent').innerHTML = '<span class="badge bg-secondary-subtle text-light-emphasis me-1" style="font-size:0.68rem;">' + lineTaxLabel + '</span><span>' + (lineTaxValue != null ? lineTaxValue : '') + '</span>';
                row.querySelector('.line-tax-amt').textContent = parseFloat((line.tax_amount != null ? line.tax_amount : 0)).toFixed(2);
                row.querySelector('.line-total').textContent = parseFloat(line.line_total).toFixed(2);
                row.querySelector('.line-desc').textContent = (line.description != null ? line.description : '');

                // update document totals
                if (json.totals) {
                    var s = parseFloat(json.totals.subtotal).toFixed(2);
                    var d = parseFloat(json.totals.discount).toFixed(2);
                    var t = parseFloat(json.totals.tax).toFixed(2);
                    var wt = json.totals.total_weight != null ? (parseFloat(json.totals.total_weight).toFixed(3)) : null;
                    var g = parseFloat(json.totals.total).toFixed(2);
                    document.getElementById('view-subtotal').textContent = s;
                    // discount: show as negative and toggle row
                    var rowDisc = document.getElementById('row-discount');
                    if (rowDisc) {
                        if (parseFloat(d) > 0) {
                            rowDisc.style.display = '';
                            document.getElementById('view-discount').textContent = '-' + parseFloat(d).toFixed(2);
                        } else {
                            rowDisc.style.display = 'none';
                        }
                    }
                    // tax: toggle row
                    var rowTax = document.getElementById('row-tax');
                    if (rowTax) {
                        if (parseFloat(t) > 0) {
                            rowTax.style.display = '';
                            document.getElementById('view-tax').textContent = parseFloat(t).toFixed(2);
                        } else {
                            rowTax.style.display = 'none';
                        }
                    }
                    var wtEl = document.getElementById('view-weight');
                    if (wtEl && wt !== null && wtEl.dataset.weightMode !== 'draft') {
                        wtEl.textContent = parseFloat(wt).toFixed(3) + ' kg';
                        wtEl.dataset.weightMode = 'calculated';
                    }
                    document.getElementById('view-total').textContent = g;
                }

                btnEdit.style.display='inline-block'; btnSave.style.display='none'; btnCancel.style.display='none';
            }).catch(function(e){ notify('Save failed: ' + e.toString(), 'error'); });
        });
    }

    function bindAllQuoteRows(){
        qsa('tr[data-line-id]').forEach(function(r){ attachRowHandlers(r); });
    }

    window.bindAllQuoteRows = bindAllQuoteRows;

    // attach to existing rows
    document.addEventListener('DOMContentLoaded', function(){
        var currentStatus = String((window.quoteStatus || '')).toLowerCase();
        editingAllowed = !(window.quoteReadOnly === true || currentStatus === 'converted');

        bindAllQuoteRows();

        var addBtn = document.getElementById('btn-add-line');
        var linesBody = qs('table.so-lines-table tbody');
        if (!editingAllowed && addBtn) {
            addBtn.style.display = 'none';
        }
        if (!addBtn || !linesBody) return;

        var creating = false;
        addBtn.addEventListener('click', function(){
            if (!editingAllowed) return;
            if (creating) return;
            creating = true;

            var row = createInlineNewRow(linesBody);
            attachCreateRowAutocomplete(row);
            var btnSave = row.querySelector('.btn-create-line');
            var btnCancel = row.querySelector('.btn-cancel-create-line');

            function closeRow(){
                if (row && row.parentNode) row.parentNode.removeChild(row);
                creating = false;
            }

            btnCancel.addEventListener('click', function(){
                closeRow();
            });

            btnSave.addEventListener('click', function(){
                var qty = toNumber(row.querySelector('.new-line-qty').value);
                var price = toNumber(row.querySelector('.new-line-price').value);
                var disc = toNumber(row.querySelector('.new-line-disc').value);
                var tax = toNumber(row.querySelector('.new-line-tax').value);
                var taxType = lineDiscountTypeNormalized(row.querySelector('.new-line-tax-type').value);
                var name = (row.querySelector('.new-line-name').value || '').trim();
                var descVal = ((row.querySelector('.line-desc') || {}).value || '').trim();
                var code = (row.querySelector('.new-line-code').value || '').trim();
                var unit = (row.querySelector('.new-line-unit').value || '').trim();
                var discType = lineDiscountTypeNormalized(row.querySelector('.new-line-disc-type').value);

                if (qty <= 0) {
                    notify('Quantity must be greater than zero.', 'error');
                    return;
                }

                var payload = {
                    product_id: toNumber((row.querySelector('.product-id') || {}).value),
                    product_variant_id: toNumber((row.querySelector('.product-variant-id') || {}).value),
                    product_code: code,
                    product_name: name,
                    description: descVal || name || code || 'New line',
                    unit: unit || 'PCS',
                    quantity: qty,
                    unit_price: price,
                    discount_type: discType,
                    discount_value: disc,
                    tax_type: taxType,
                    tax_value: tax,
                    tax_rate: tax,
                    unit_weight: toNumber((row.querySelector('.unit-weight') || {}).value),
                    weight_unit: ((row.querySelector('.weight-unit') || {}).value || 'KG'),
                    product_image_url: ((row.querySelector('.product-image-url') || {}).value || '')
                };

                var quoteId = ((typeof window !== 'undefined' && window.quoteId) ? window.quoteId : 0) || 0;
                if (!quoteId) {
                    var pathParts = window.location.pathname.split('/');
                    quoteId = pathParts[pathParts.length - 1] || 0;
                }

                var appBase = window.location.pathname.split('/quotations/')[0] || '';
                fetch(window.location.origin + appBase + '/quotations/add-line/' + quoteId, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                }).then(function(resp){ return resp.text(); }).then(function(text){
                    var json = null;
                    try { json = JSON.parse(text); } catch (e) {
                        notify('Invalid response from server', 'error');
                        return;
                    }

                    if (!json || !json.success || !json.line) {
                        notify('Add line failed: ' + (json && json.error ? json.error : 'unknown'), 'error');
                        return;
                    }

                    var line = json.line;
                    var staticRow = document.createElement('tr');
                    staticRow.setAttribute('data-line-id', line.id);
                    staticRow.setAttribute('data-unit-weight', toNumber(line.unit_weight || 0));
                    staticRow.setAttribute('data-discount-type', lineDiscountTypeNormalized(line.discount_type));
                    staticRow.innerHTML = buildStaticRowHtml(line);

                    linesBody.insertBefore(staticRow, row);
                    attachRowHandlers(staticRow);
                    closeRow();

                    var toggleDiscTax = document.getElementById('toggle-discount-tax');
                    if (toggleDiscTax) {
                        toggleDiscTax.dispatchEvent(new Event('change'));
                    }

                    if (json.totals) {
                        var evt = new CustomEvent('quote-totals-updated', { detail: json.totals });
                        document.dispatchEvent(evt);
                        var s = parseFloat(json.totals.subtotal || 0).toFixed(2);
                        var d = parseFloat(json.totals.discount || 0).toFixed(2);
                        var t = parseFloat(json.totals.tax || 0).toFixed(2);
                        var g = parseFloat(json.totals.total || 0).toFixed(2);
                        var subEl = document.getElementById('view-subtotal');
                        var discEl = document.getElementById('view-discount');
                        var discRow = document.getElementById('row-discount');
                        var taxEl = document.getElementById('view-tax');
                        var taxRow = document.getElementById('row-tax');
                        var totalEl = document.getElementById('view-total');
                        if (subEl) subEl.textContent = s;
                        if (discRow && discEl) {
                            if (parseFloat(d) > 0) {
                                discRow.style.display = '';
                                discEl.textContent = '-' + d;
                            } else {
                                discRow.style.display = 'none';
                            }
                        }
                        if (taxRow && taxEl) {
                            if (parseFloat(t) > 0) {
                                taxRow.style.display = '';
                                taxEl.textContent = t;
                            } else {
                                taxRow.style.display = 'none';
                            }
                        }
                        if (totalEl) totalEl.textContent = g;
                    }

                    notify('Line added successfully.', 'success');
                }).catch(function(e){
                    notify('Add line failed: ' + e.toString(), 'error');
                });
            });
        });
    });

    document.addEventListener('doc-lines:rows-updated', function(e){
        if (!e || !e.detail || e.detail.docType !== 'quotation') return;
        bindAllQuoteRows();
    });
})();
