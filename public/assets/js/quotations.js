document.addEventListener('DOMContentLoaded', function(){
    var form = document.getElementById('quotation-form');
    var statusInput = document.getElementById('quote-status');
    var debugOut = document.getElementById('quote-debug-output');
    var btnShowPayload = document.getElementById('debug-show-payload');
    // NOTE: Add/remove line UI is handled by quotation_page_inline.js.
    // Keep this script focused on form submission + lookups.
    var addLineBtn = null;
    var table = document.getElementById('quote-lines-table');
    var subtotalEl = document.getElementById('subtotal');
    var taxEl = document.getElementById('tax');
    var totalEl = document.getElementById('grand-total');
    var shippingEl = document.getElementById('shipping-total');
    var shippingInput = document.getElementById('shipping_amount');
    var APP_BASE = (window.APP_BASE || '').replace(/\/$/, '');
    function withBase(path){
        return (APP_BASE && APP_BASE.length) ? (APP_BASE + path) : path;
    }

    if (!form) return;

    var lineIndex = (table && table.querySelectorAll('tbody tr.quote-line').length) || 1;

    function qs(selector, el){ if (!el) el = document; return el.querySelector(selector); }
    function qsa(selector, el){ if (!el) el = document; return Array.prototype.slice.call(el.querySelectorAll(selector)); }

    // Totals are calculated server-side. Keep this as a no-op to avoid UI lies.
    function recalcClientTotals(){ /* intentionally empty */ }

    function bindRowEvents(tr){
      var qty = tr.querySelector('.line-qty');
      var price = tr.querySelector('.line-price');
      var disc = tr.querySelector('.line-discount');
      var tax = tr.querySelector('.line-tax');
      var removeBtn = tr.querySelector('.btn-remove-line');
    [qty, price, disc, tax].forEach(function(el){ if (el) el.addEventListener('input', recalcClientTotals); });
      if (removeBtn) removeBtn.addEventListener('click', function(){
          var rows = qsa('tbody tr.quote-line', table);
          if (rows.length <= 1) {
              // clear row instead of removing
              tr.querySelectorAll('input').forEach(function(i){ if (i.type !== 'hidden') i.value = ''; });
              recalcClientTotals();
              return;
          }
          tr.remove();
          recalcClientTotals();
      });

        // product code blur -> search product
    var codeInput = tr.querySelector('.product-code');
        // Enhanced product lookup: show a small inline dropdown and support keyboard selection
        if (codeInput) {
            const drop = document.createElement('div');
            drop.className = 'card autocomplete-list';
            drop.style.position = 'absolute';
            drop.style.zIndex = 1400;
            drop.style.display = 'none';
            drop.style.minWidth = '260px';
            tr.querySelector('td').appendChild(drop);

            var doProdSearch = debounce(function(){
                var q = codeInput.value.trim();
                if (!q) { drop.style.display='none'; return; }
                var _custEl = document.getElementById('customer_id');
                var customerId = (_custEl && _custEl.value) || '';
                var url = '/quotations/search-products?q=' + encodeURIComponent(q) + '&customer_id=' + encodeURIComponent(customerId);
                fetch(url).then(function(res){ return res.json(); }).then(function(items){
                    if (!items || !items.length) { drop.style.display='none'; return; }
                    renderList(drop, items, function(it){ return '' +
                        '<div style="display:flex;gap:8px;align-items:center">' +
                            '<img src="' + (it.image_url || it.image || '') + '" style="width:40px;height:40px;object-fit:cover;border-radius:4px;margin-right:6px"/>' +
                            '<div style="flex:1">' +
                                '<div style="font-weight:600">' + (it.code || '') + ' — ' + (it.name || '') + '</div>' +
                                '<div style="font-size:0.85rem;color:#6b7280">' + (it.description || '') + '</div>' +
                            '</div>' +
                            '<div style="font-weight:700">' + ( (it.special_price || it.sale_price || 0).toFixed ? (it.special_price || it.sale_price || 0).toFixed(2) : (it.special_price || it.sale_price || 0) ) + '</div>' +
                        '</div>'; });
                    attachKeyboardNav(drop, function(idx){
                        var p = items[idx];
                        tr.querySelector('.product-id').value = p.product_id || p.id;
                        var nameInput = tr.querySelector('.product-name'); if (nameInput) nameInput.value = p.name || p.description || '';
                        var priceInput = tr.querySelector('.line-price'); if (priceInput) priceInput.value = (p.special_price || p.sale_price || 0).toFixed(2);
                        var weightVal = p.unit_weight || p.weight || p.weight_net || p.weight_gross || 0;
                        var weightUnit = (p.weight_unit || 'KG').toString().toUpperCase();
                        var stockVal = p.current_stock || p.stock || p.available_stock || p.quantity || p.qty || 0;
                        var weightInput = tr.querySelector('.unit-weight'); if (weightInput) weightInput.value = weightVal;
                        var weightUnitInput = tr.querySelector('.weight-unit'); if (weightUnitInput) weightUnitInput.value = weightUnit;
                        try { tr.setAttribute('data-unit-weight', weightVal); } catch(e){}
                        var metaWeight = tr.querySelector('.meta-weight'); if (metaWeight) metaWeight.textContent = weightVal + ' ' + weightUnit;
                        var metaStock = tr.querySelector('.meta-stock'); if (metaStock) metaStock.textContent = stockVal;
                        var thumb = tr.querySelector('.product-thumb'); if (thumb && (p.image_url || p.image)) thumb.src = p.image_url || p.image;
                        drop.style.display = 'none'; recalcClientTotals();
                    });
                    drop.style.display = 'block';
                }).catch(function(){ drop.style.display='none'; });
            }, 220);

            codeInput.addEventListener('input', doProdSearch);
            codeInput.addEventListener('blur', function(){ setTimeout(function(){ drop.style.display='none'; }, 200); });
        }
    }

    // bind existing rows
    qsa('tbody tr.quote-line', table).forEach(function(row){ bindRowEvents(row); });

    // (add-line handler removed)

    if (shippingInput) {
        shippingInput.addEventListener('input', recalcClientTotals);
        shippingInput.addEventListener('change', recalcClientTotals);
    }

    // Show payload helper
    if (btnShowPayload) {
        btnShowPayload.addEventListener('click', function(){
            var fd = new FormData(form);
            var obj = {};
            try{
                var iter = fd.entries(); var pair = iter.next();
                while(!pair.done){
                    var k = pair.value[0]; var v = pair.value[1];
                    if (k.substr(k.length-2) === '[]'){
                        var key = k.replace(/\[\]$/, ''); obj[key] = obj[key] || []; obj[key].push(v);
                    } else { obj[k] = v; }
                    pair = iter.next();
                }
            }catch(e){ /* fallback: try FormData.forEach if available */
                try{ fd.forEach(function(v,k){ if (k.substr(k.length-2) === '[]'){ var key = k.replace(/\[\]$/, ''); obj[key] = obj[key] || []; obj[key].push(v); } else { obj[k]=v; } }); }catch(e2){}
            }
            if (debugOut) debugOut.textContent = JSON.stringify(obj, null, 2);
        });
    }

    // customer minimal lookup (debounced) to fill customer_id and price lists
    var customerSearch = document.getElementById('customer_search');
    var customerList = document.getElementById('customer_list');
    var customerIdHidden = document.getElementById('customer_id');
    var priceListSelect = document.getElementById('price_list_id');

    function debounce(fn, wait){ var t; return function(){ var args = Array.prototype.slice.call(arguments); clearTimeout(t); t = setTimeout(function(){ fn.apply(this, args); }, wait); }; }

    function renderList(container, items, renderItem){
        container.innerHTML = '';
        items.forEach(function(it, idx){
            var el = document.createElement('div');
            el.className = 'px-2 py-1 autocomplete-item';
            el.tabIndex = 0;
            el.style.cursor = 'pointer';
            el.dataset.index = idx;
            el.innerHTML = renderItem(it);
            container.appendChild(el);
        });
        container.style.display = items.length ? 'block' : 'none';
    }

    function attachKeyboardNav(container, onSelect){
        var active = -1;
        container.addEventListener('keydown', function(e){
            var items = container.querySelectorAll('.autocomplete-item');
            if (!items.length) return;
            if (e.key === 'ArrowDown') { active = Math.min(active+1, items.length-1); items[active].focus(); e.preventDefault(); }
            else if (e.key === 'ArrowUp') { active = Math.max(active-1, 0); items[active].focus(); e.preventDefault(); }
            else if (e.key === 'Enter') { e.preventDefault(); items[active >= 0 ? active : 0].click(); }
        });
        container.addEventListener('click', function(e){
            var item = e.target.closest('.autocomplete-item');
            if (!item) return;
            var idx = parseInt(item.dataset.index,10);
            onSelect(idx);
        });
    }

    if (customerSearch) {
        var doSearch = debounce(function(){
            var q = customerSearch.value.trim();
            if (!q) { customerList.style.display='none'; return; }
            try {
                var items = [];
                var res = null;
                var handleResponse = function(r){ return r.json ? r.json() : Promise.resolve([]); };
                var fetchPromise = fetch(withBase('/quotations/search-customers?q=' + encodeURIComponent(q))).catch(function(){ return fetch('/quotations/search-customers?q=' + encodeURIComponent(q)); });
                fetchPromise.then(function(r){ return handleResponse(r); }).then(function(data){
                    items = data || [];
                    renderList(customerList, items, function(it){ return it.code + ' — ' + it.name + (it.company ? ' ('+it.company+')' : ''); });
                    attachKeyboardNav(customerList, function(idx){
                        var it = items[idx];
                        customerSearch.value = it.name;
                        if (customerIdHidden) customerIdHidden.value = it.id;
                        customerList.style.display = 'none';
                        if (priceListSelect) {
                            fetch(withBase('/quotations/price-lists/' + encodeURIComponent(it.id))).then(function(r){ return r.json(); }).then(function(lists){
                                priceListSelect.innerHTML = '<option value="">Default</option>' + (lists.map(function(ls){ return '<option value="'+ls.id+'">'+ls.name+'</option>'; }).join(''));
                            }).catch(function(){});
                        }
                    });
                }).catch(function(){ customerList.style.display='none'; });
            } catch (e) { customerList.style.display='none'; }
        }, 250);
        customerSearch.addEventListener('input', doSearch);
    }

    function clearInlineErrors(){
        // clear customer
        const errCust = document.getElementById('error-customer');
        if (errCust) errCust.textContent = '';
        // clear js error box
        const jsErr = document.getElementById('js-error-box');
        if (jsErr) { jsErr.style.display='none'; jsErr.textContent=''; }
        // clear line errors and invalid classes
    qsa('.line-errors').forEach(function(el){ el.textContent = ''; });
    qsa('.is-invalid').forEach(function(i){ i.classList.remove('is-invalid'); });
    }

    function renderServerErrors(resp){
        // resp may contain errors (structured) or error string
        clearInlineErrors();
        if (!resp) return;
        // global error message
        if (resp.error && typeof resp.error === 'string'){
            const jsErr = document.getElementById('js-error-box');
            if (jsErr) { jsErr.style.display='block'; jsErr.textContent = resp.error; }
        }
        const errs = resp.errors || resp.form_errors || null;
        if (!errs) return;
        // customer error
        if (errs.customer_id) {
            const ce = document.getElementById('error-customer');
            if (ce) ce.textContent = Array.isArray(errs.customer_id) ? errs.customer_id.join(', ') : errs.customer_id;
        }
        // lines errors: errs.lines is expected to be an array or object keyed by index
        const lineErrs = errs.lines || {};
        for (const k in lineErrs){
            const idx = parseInt(k,10);
            if (Number.isNaN(idx)) continue;
            const row = table.querySelectorAll('tbody tr.quote-line')[idx];
            if (!row) continue;
            const parts = lineErrs[k];
            // parts may be object of field->message
            let messages = [];
            if (typeof parts === 'string') messages.push(parts);
            else if (Array.isArray(parts)) messages = parts;
            else if (typeof parts === 'object') {
                for (const fk in parts){
                    const val = parts[fk];
                    messages.push(Array.isArray(val) ? val.join(', ') : val);
                    // mark field invalid if we can map it
                    try {
                        // map known keys to inputs
                        if (fk === 'quantity') {
                            const el = row.querySelector('.line-qty'); if (el) el.classList.add('is-invalid');
                        } else if (fk === 'unit_price' || fk === 'unit_price'){
                            const el = row.querySelector('.line-price'); if (el) el.classList.add('is-invalid');
                        } else if (fk === 'description'){
                            const el = row.querySelector('.line-desc'); if (el) el.classList.add('is-invalid');
                        }
                    } catch (e){}
                }
            }
            const errEl = row.querySelector('.line-errors'); if (errEl) errEl.textContent = messages.join('; ');
        }
    }

    // Intercept submit and send as AJAX (FormData) with X-Requested-With so server returns JSON
    async function submitQuote(statusOverride){
        if (statusInput) statusInput.value = statusOverride || 'quoted';
        clearInlineErrors();
        
        // Validate customer is selected
        const customerId = (document.getElementById('customer_id') || {}).value;
        if (!customerId || customerId.trim() === '') {
            const errCust = document.getElementById('error-customer');
            if (errCust) errCust.textContent = 'Please select a customer before saving';
            return;
        }
        
        const fd = new FormData(form);
        try {
            const resp = await fetch(form.action, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const contentType = resp.headers.get('Content-Type') || '';
            let data = null;
            if (contentType.includes('application/json')) {
                data = await resp.json();
            } else {
                // not JSON (may be redirect HTML) - read as text
                const text = await resp.text();
                data = { success: false, error: 'Server returned non-JSON response', raw: text };
            }
            // debug output is optional (removed from production UI)
            try {
                if (typeof debugOut !== 'undefined' && debugOut) debugOut.textContent = JSON.stringify(data, null, 2);
            } catch (e) {}
            if (data && data.success && Number.isInteger(data.id) && data.id > 0) {
                var base = (window.APP_BASE || '').replace(/\/$/, '');
                window.location.href = base + '/quotations/view/' + data.id;
            } else {
                // render inline validation errors if provided
                renderServerErrors(data);
            }
        } catch (err) {
            try {
                if (typeof debugOut !== 'undefined' && debugOut) debugOut.textContent = 'AJAX error: ' + err.toString();
            } catch (e) {}
            const jsErr = document.getElementById('js-error-box');
            if (jsErr) { jsErr.style.display='block'; jsErr.textContent = 'AJAX error: ' + err.toString(); }
        }
    }

    form.addEventListener('submit', function(e){
        e.preventDefault();
        submitQuote();
    });

    var btnQuote = document.getElementById('btn-save-quote');
    if (btnQuote) {
        btnQuote.addEventListener('click', function(e){ e.preventDefault(); submitQuote('quoted'); });
    }

    // initial calc
    recalcClientTotals();
});
