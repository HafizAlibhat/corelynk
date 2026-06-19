// Minimal quotation calculator & product autocomplete
document.addEventListener('DOMContentLoaded', function(){
    const debounce = (fn, wait=300) => {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(()=>fn(...args), wait); };
    };

    // Simple autocomplete for product selects (select element replacement)
    // Attach autocomplete to dynamic product-code and product-name inputs
    function attachProductAutocomplete(input, byName=false){
        // Avoid double-attaching to the same input
        try {
            if (input._autocompleteAttached) return;
            input._autocompleteAttached = true;
        } catch (e) {}

        // Create or reuse dropdown appended to body to avoid overflow/hidden issues inside tables
        let list = input._autocompleteList;
        if (!list) {
            list = document.createElement('div'); list.className='autocomplete-list card';
            list.style.position='absolute'; list.style.zIndex=99999; list.style.display='none';
            list.style.minWidth = '260px'; list.style.maxHeight = '260px'; list.style.overflowY = 'auto';
            list.style.boxShadow = '0 6px 18px rgba(0,0,0,0.15)'; list.style.borderRadius = '6px';
            document.body.appendChild(list);
            input._autocompleteList = list;
        }
        const onSearch = debounce(function(term){
            if (!term) { list.style.display='none'; return; }
            const customerId = document.getElementById('customer_id') ? document.getElementById('customer_id').value : '';
            const url = (window.APP_BASE||'') + '/quotations/search-products?q=' + encodeURIComponent(term) + '&customer_id=' + encodeURIComponent(customerId);
            fetch(url).then(r=>r.json()).then(data=>{
                list.innerHTML = '';
                if (!data || data.length===0) { list.style.display='none'; return; }
                data.forEach(p=>{
                    const row = document.createElement('div'); row.className='p-2'; row.style.cursor='pointer';
                    row.innerHTML = `<div style="display:flex;gap:.6rem;align-items:center"><img src="${p.image}" style="width:40px;height:32px;object-fit:cover;border-radius:4px" alt=""> <div><strong>${p.code}</strong> — ${p.name}<div style="font-size:.8rem;color:#6b7280">${p.sale_price} ${p.sale_currency} • Stock: ${p.current_stock}</div></div></div>`;
                    row.addEventListener('click', function(){
                        const tr = input.closest('tr');
                        if (!tr) return;
                        tr.querySelector('.product-id').value = p.id;
                        tr.querySelector('.line-price').value = (p.special_price ?? p.sale_price ?? 0).toFixed(2);
                        tr.querySelector('.line-desc').value = p.name;
                        const meta = tr.querySelector('.product-meta'); if (meta) meta.innerHTML = `Stock: ${p.current_stock} • Vendor: ${p.vendor_id || '-'} • Weight: ${p.weight}`;
                        // update totals
                        const ev = new Event('change'); tr.querySelector('.line-price').dispatchEvent(ev);
                        list.style.display='none';
                    });
                    list.appendChild(row);
                });
                // position list below input
                const rect = input.getBoundingClientRect();
                list.style.left = (rect.left + window.scrollX) + 'px';
                list.style.top = (rect.bottom + window.scrollY + 6) + 'px';
                list.style.minWidth = Math.max(rect.width, 260) + 'px';
                list.style.display='block';
            }).catch(()=>{ list.style.display='none'; });
        }, 200);
        input.addEventListener('input', function(e){ onSearch(e.target.value); });
        // hide on blur (with slight delay to allow click)
        input.addEventListener('blur', function(){ setTimeout(()=>list.style.display='none',200); });

        // reposition on scroll/resize
        const reposition = () => {
            if (list.style.display === 'none') return;
            const rect = input.getBoundingClientRect();
            list.style.left = (rect.left + window.scrollX) + 'px';
            list.style.top = (rect.bottom + window.scrollY + 6) + 'px';
            list.style.minWidth = Math.max(rect.width, 260) + 'px';
        };
        window.addEventListener('resize', reposition);
        window.addEventListener('scroll', reposition, true);
        // expose for cleanup if needed
    }

    // Delegated focus handler: attach autocomplete to dynamically added rows on first focus
    document.addEventListener('focusin', function(e){
        try {
            const t = e.target;
            if (!t) return;
            if (t.matches && (t.matches('input.product-code') || t.matches('input.product-name'))) {
                // attach if not already
                attachProductAutocomplete(t, t.matches('input.product-name'));
            }
        } catch (err) { /* ignore */ }
    });

    // Attach to any existing rows
    document.querySelectorAll('input.product-code').forEach(i=>attachProductAutocomplete(i,false));
    document.querySelectorAll('input.product-name').forEach(i=>attachProductAutocomplete(i,true));

    // Expose attach function so dynamic rows can call it
    window.attachProductAutocomplete = attachProductAutocomplete;

    // calculation via API
    const recalc = debounce(function(){
        const payload = { lines: [] };
        document.querySelectorAll('tr.quote-line').forEach(function(row){
            const productId = row.querySelector('.product-id') ? row.querySelector('.product-id').value : '';
            const qty = parseFloat(row.querySelector('.line-qty').value) || 0;
            const price = parseFloat(row.querySelector('.line-price').value) || 0;
            const discountType = row.querySelector('[name*="[discount_type]"]') ? row.querySelector('[name*="[discount_type]"]').value : 'percent';
            const discountValue = row.querySelector('[name*="[discount_value]"]') ? parseFloat(row.querySelector('[name*="[discount_value]"]').value) || 0 : 0;
            const taxRate = row.querySelector('[name*="[tax_rate]"]') ? parseFloat(row.querySelector('[name*="[tax_rate]"]').value) || 0 : 0;
            payload.lines.push({ product_id: productId?parseInt(productId):null, quantity: qty, unit_price: price, discount_type: discountType, discount_value: discountValue, tax_rate: taxRate });
        });
        payload.document_discount_type = document.querySelector('[name="document_discount_type"]') ? document.querySelector('[name="document_discount_type"]').value : 'percent';
        payload.document_discount_value = document.querySelector('[name="document_discount_value"]') ? parseFloat(document.querySelector('[name="document_discount_value"]').value) || 0 : 0;
        payload.discount_exclude_shipping = document.querySelector('[name="discount_exclude_shipping"]') ? (document.querySelector('[name="discount_exclude_shipping"]').checked ? 1 : 0) : 1;
        payload.document_tax_type = document.querySelector('[name="document_tax_type"]') ? document.querySelector('[name="document_tax_type"]').value : 'percent';
        payload.document_tax_value = document.querySelector('[name="document_tax_value"]') ? parseFloat(document.querySelector('[name="document_tax_value"]').value) || 0 : 0;
        payload.shipping_amount = parseFloat(document.querySelector('[name="shipping_amount"]') ? document.querySelector('[name="shipping_amount"]').value : 0) || 0;

        fetch('/quotations/calculate', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) })
            .then(r=>r.json()).then(resp=>{
                if (resp && resp.data) {
                    document.getElementById('subtotal').textContent = (resp.data.subtotal || 0).toFixed(2);
                    document.getElementById('tax').textContent = (resp.data.tax_total || 0).toFixed(2);
                    document.getElementById('grand-total').textContent = (resp.data.total || 0).toFixed(2);
                }
            }).catch(()=>{});
    }, 200);

    // bind change events
    document.addEventListener('change', function(e){ if (e.target && (e.target.classList.contains('line-qty') || e.target.classList.contains('line-price') || e.target.classList.contains('line-price') )) recalc(); });
    document.addEventListener('input', function(e){ if (e.target && (e.target.classList.contains('line-qty') || e.target.classList.contains('line-price') )) recalc(); });
});
