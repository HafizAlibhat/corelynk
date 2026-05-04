(function(){
    // Inline edit / save for quotation lines
    function qs(sel, el){ if (!el) el = document; return el.querySelector(sel); }
    function qsa(sel, el){ if (!el) el = document; return Array.prototype.slice.call(el.querySelectorAll(sel)); }

    var editingAllowed = true;

    function toNumber(v){ return v===null||v===undefined||v===''?0:parseFloat(String(v).replace(/,/g,''))||0; }

    function attachRowHandlers(row){
        var id = row.getAttribute('data-line-id');
        var btnEdit = row.querySelector('.btn-edit-line');
        var btnSave = row.querySelector('.btn-save-line');
        var btnCancel = row.querySelector('.btn-cancel-line');

        if (!editingAllowed) {
            if (btnEdit) btnEdit.disabled = true;
            if (btnSave) btnSave.style.display = 'none';
            if (btnCancel) btnCancel.style.display = 'none';
            return;
        }

        if (!btnEdit) return;

        btnEdit.addEventListener('click', function(){
            // show inputs for editable cells
            var qtyCell = row.querySelector('.line-qty');
            var priceCell = row.querySelector('.line-unit-price');
            var discCell = row.querySelector('.line-disc-percent');
            var taxCell = row.querySelector('.line-tax-percent');
            var descCell = row.querySelector('.line-desc');

            qtyCell._orig = qtyCell.textContent.trim();
            priceCell._orig = priceCell.textContent.trim();
            discCell._orig = discCell.textContent.trim();
            taxCell._orig = taxCell.textContent.trim();
            descCell._orig = descCell.textContent.trim();

            qtyCell.innerHTML = '<input type="number" step="0.01" class="form-control form-control-sm inline-qty" value="' + qtyCell._orig + '">';
            priceCell.innerHTML = '<input type="number" step="0.01" class="form-control form-control-sm inline-price text-end" value="' + priceCell._orig.replace(/,/g,'') + '">';
            discCell.innerHTML = '<input type="number" step="0.01" class="form-control form-control-sm inline-disc text-end" value="' + discCell._orig + '">';
            taxCell.innerHTML = '<input type="number" step="0.01" class="form-control form-control-sm inline-tax text-end" value="' + taxCell._orig + '">';
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
            var tax = toNumber(row.querySelector('.inline-tax').value);
            var desc = row.querySelector('.inline-desc').value;

            var payload = { quantity: qty, unit_price: price, discount_value: disc, tax_rate: tax, description: desc };

            fetch(window.location.origin + '/corelynk/quotations/update-line/' + id, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function(resp){ return resp.text(); }).then(function(text){
                var json = null;
                try { json = JSON.parse(text); } catch(e){ alert('Invalid response from server'); console.error(text); return; }
                if (!json || !json.success){ alert('Save failed: ' + (json && json.error ? json.error : 'unknown')); return; }

                var line = json.line;
                // update cells with returned values
                row.querySelector('.line-qty').textContent = line.quantity;
                row.querySelector('.line-unit-price').textContent = parseFloat(line.unit_price).toFixed(2);
                row.querySelector('.line-disc-percent').textContent = (line.discount_value != null ? line.discount_value : '');
                row.querySelector('.line-disc-amt').textContent = parseFloat((line.discount_amount != null ? line.discount_amount : (line.discount_value ? (line.quantity*line.unit_price*(line.discount_value/100)) : 0))).toFixed(2);
                row.querySelector('.line-tax-percent').textContent = (line.tax_rate != null ? line.tax_rate : '');
                row.querySelector('.line-tax-amt').textContent = parseFloat((line.tax_amount != null ? line.tax_amount : 0)).toFixed(2);
                row.querySelector('.line-total').textContent = parseFloat(line.line_total).toFixed(2);
                row.querySelector('.line-desc').textContent = (line.description != null ? line.description : '');

                // update document totals
                if (json.totals) {
                    var s = parseFloat(json.totals.subtotal).toFixed(2);
                    var d = parseFloat(json.totals.discount).toFixed(2);
                    var t = parseFloat(json.totals.tax).toFixed(2);
                    var ship = parseFloat(json.totals.shipping_amount || 0).toFixed(2);
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
                    var shipEl = document.getElementById('view-shipping');
                    if (shipEl) {
                        var shipInput = shipEl.querySelector('input');
                        if (shipInput) {
                            shipInput.value = ship;
                        } else {
                            shipEl.textContent = ship;
                        }
                    }
                    var wtEl = document.getElementById('view-weight');
                    if (wtEl && wt !== null && wtEl.dataset.weightMode !== 'draft') {
                        wtEl.textContent = 'Shipment Weight: ' + parseFloat(wt).toFixed(3) + ' kg (calculated)';
                        wtEl.dataset.weightMode = 'calculated';
                    }
                    document.getElementById('view-total').textContent = g;
                }

                btnEdit.style.display='inline-block'; btnSave.style.display='none'; btnCancel.style.display='none';
            }).catch(function(e){ alert('Save failed: ' + e.toString()); });
        });
    }

    // attach to existing rows
    document.addEventListener('DOMContentLoaded', function(){
        qsa('tr[data-line-id]').forEach(function(r){ attachRowHandlers(r); });
    });
})();
