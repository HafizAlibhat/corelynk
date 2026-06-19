// Quotation page scripts (externalized)
(function(){
  // Minimal JS to add/remove lines and calculate totals
  document.addEventListener('DOMContentLoaded', function(){
    var tableEl = document.getElementById('quote-lines-table');
    var table = tableEl ? tableEl.getElementsByTagName('tbody')[0] : null;
    var addBtn = document.getElementById('add-line');

    // Client-side preview totals (server remains source of truth when saved).
    function n(v){ var x = parseFloat(v); return isFinite(x) ? x : 0; }
    function updateTotals(){
      try {
        var subtotal = 0, lineDiscountTotal = 0, taxTotal = 0;
        var rows = table ? table.querySelectorAll('tr.quote-line') : [];
        Array.prototype.forEach.call(rows, function(r){
          var qty = n((r.querySelector('.line-qty')||{}).value);
          var price = n((r.querySelector('.line-price')||{}).value);
          var discVal = n((r.querySelector('.line-discount')||{}).value);
          var discType = ((r.querySelector('.line-discount-type')||{}).value || 'percent').toLowerCase();
          var taxType = ((r.querySelector('.line-tax-type')||{}).value || 'percent').toLowerCase();
          var taxVal = n((r.querySelector('.line-tax')||{}).value);
          var raw = qty * price;
          var discAmt = discType === 'fixed' ? discVal : (raw * (discVal/100));
          discAmt = Math.min(raw, Math.max(0, discAmt));
          var taxable = Math.max(0, raw - discAmt);
          var taxAmt = taxType === 'fixed' ? taxVal : (taxable * (taxVal/100));
          var lineTotal = taxable + taxAmt;
          subtotal += raw;
          lineDiscountTotal += discAmt;
          taxTotal += taxAmt;
          var cell = r.querySelector('.line-total');
          if (cell) cell.textContent = lineTotal.toFixed(2);
        });
        var ship = shippingInput ? n(shippingInput.value) : 0;
        var lineNet = Math.max(0, subtotal - lineDiscountTotal);
        var docType = ((document.getElementById('document_discount_type')||{}).value || 'fixed').toLowerCase();
        var docVal = n((document.getElementById('document_discount_value')||{}).value);
        var excludeShipping = ((document.getElementById('discount_exclude_shipping')||{}).checked === true);
        var docBase = lineNet + taxTotal + (excludeShipping ? 0 : ship);
        var docDiscount = docType === 'percent' ? (docBase * (docVal/100)) : docVal;
        docDiscount = Math.min(Math.max(0, docDiscount), docBase);
        var totalDiscount = lineDiscountTotal + docDiscount;
        var grand = (lineNet + taxTotal + ship - docDiscount);
        var elSub = document.getElementById('subtotal');
        var elDisc = document.getElementById('discount-total');
        var elTax = document.getElementById('tax');
        var elShip = document.getElementById('shipping-total');
        var elGrand = document.getElementById('grand-total');
        if (elSub) elSub.textContent = subtotal.toFixed(2);
        if (elDisc) elDisc.textContent = totalDiscount.toFixed(2);
        if (elTax) elTax.textContent = taxTotal.toFixed(2);
        if (elShip) elShip.textContent = ship.toFixed(2);
        if (elGrand) elGrand.textContent = grand.toFixed(2);
      } catch(e) {
        // Ignore preview errors
      }
    }

    function bindRow(row){
  row.querySelectorAll('input, select').forEach(function(el){
        el.addEventListener('change', updateTotals);
        el.addEventListener('input', updateTotals);
      });
      row.querySelector('.btn-remove-line').addEventListener('click', function(){
        if (table.querySelectorAll('tr.quote-line').length > 1){ row.remove(); updateTotals(); }
      });
    }

    bindRow(table.querySelector('tr.quote-line'));

    if (addBtn && !addBtn._corelynkAddLineBound) {
    addBtn._corelynkAddLineBound = true;
    addBtn.addEventListener('click', function(){
      var idx = table.querySelectorAll('tr.quote-line').length;
      var newRow = table.querySelector('tr.quote-line').cloneNode(true);
      // Reset all inputs (including hidden IDs) so we don't copy previous line state
      newRow.querySelectorAll('input').forEach(function(i){
        if (i.type === 'hidden') { i.value = ''; }
        else { i.value=''; }
      });
      newRow.querySelector('.line-qty').value = '1';
      newRow.querySelector('.line-price').value = '0.00';
      var newDiscType = newRow.querySelector('.line-discount-type');
      if (newDiscType) newDiscType.value = 'percent';
      var newTaxType = newRow.querySelector('.line-tax-type');
      if (newTaxType) newTaxType.value = 'percent';
      var newTaxValue = newRow.querySelector('.line-tax');
      if (newTaxValue) newTaxValue.value = '0';
      // Reset display cells/meta
      var totalCell = newRow.querySelector('.line-total');
      if (totalCell) totalCell.textContent = '0.00';
      var metaWeight = newRow.querySelector('.meta-weight');
      if (metaWeight) metaWeight.textContent = '0';
      var metaStock = newRow.querySelector('.meta-stock');
      if (metaStock) metaStock.textContent = '0';
      var metaVendor = newRow.querySelector('.meta-vendor');
      if (metaVendor) metaVendor.textContent = '-';
      var thumb = newRow.querySelector('img.product-thumb');
      var thumbIcon = newRow.querySelector('.thumb-icon');
      var thumbWrap = newRow.querySelector('.product-thumb-wrap');
      if (thumb && thumbWrap) {
        // Create a completely new image element to avoid inherited src
        var newThumb = document.createElement('img');
        newThumb.className = 'product-thumb';
        newThumb.setAttribute('data-empty-src', '');
        newThumb.setAttribute('data-row', idx);
        newThumb.style.width = '32px';
        newThumb.style.height = '32px';
        newThumb.style.objectFit = 'cover';
        newThumb.style.display = 'none';
        newThumb.onload = function() { 
          this.style.display = 'block';
          var ic = this.parentElement.querySelector('.thumb-icon');
          if (ic) ic.style.display = 'none';
        };
        newThumb.onerror = function() { 
          this.style.display = 'none';
          var ic = this.parentElement.querySelector('.thumb-icon');
          if (ic) ic.style.display = 'flex';
        };
        // Replace old thumbnail with new one
        thumb.parentNode.replaceChild(newThumb, thumb);
      }
      if (thumbWrap) {
        thumbWrap.setAttribute('data-row-index', idx);
      }
      if (thumbIcon) thumbIcon.style.display = 'flex';
      newRow.querySelectorAll('select, input').forEach(function(el){ if (el.name){ el.name = el.name.replace(/lines\[\d+\]/, 'lines['+idx+']'); } });
      newRow.querySelectorAll('.autocomplete-list').forEach(function(list){ list.remove(); });
      ['product-code','product-name'].forEach(function(cls){ var inp = newRow.querySelector('input.'+cls); if (inp && inp.parentNode) inp.parentNode.style.position = 'relative'; });
      table.appendChild(newRow);
      bindRow(newRow);
      // attach autocomplete
      (function(){
        var pc = newRow.querySelector('input.product-code');
        var pn = newRow.querySelector('input.product-name');
        function _attachRetry(inputEl, byName, attempt){
          attempt = attempt || 0;
          if (!inputEl) return;
          if (inputEl.dataset) delete inputEl.dataset.autocompleteAttached;
          if (window.attachProductAutocomplete) {
            try { window.attachProductAutocomplete(inputEl, !!byName); } catch(e) { console.debug('attachProductAutocomplete error', e); }
          } else if (attempt < 12) {
            setTimeout(function(){ _attachRetry(inputEl, byName, attempt+1); }, 150);
          }
        }
        _attachRetry(pc, false, 0);
        _attachRetry(pn, true, 0);
      })();
  updateTotals();
    });
    }

    // shipping toggle
    var addShippingBtn = document.getElementById('add-shipping');
    var shippingInput = document.getElementById('shipping_amount');
    if (addShippingBtn) {
      addShippingBtn.addEventListener('click', function(){
        try {
          var modalEl = document.getElementById('modal-shipping-create');
          var inputEl = document.getElementById('modal-shipping-create-input');
          if (inputEl && shippingInput) inputEl.value = shippingInput.value || '0.00';
          if (window.bootstrap && modalEl) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
          } else if (modalEl) {
            modalEl.style.display='block'; modalEl.classList.add('show');
          }
        } catch(e){}
      });
    }
    (function(){
      var saveBtn = document.getElementById('modal-shipping-create-save');
      if (!saveBtn) return;
      saveBtn.addEventListener('click', function(){
        var inputEl = document.getElementById('modal-shipping-create-input');
        var val = inputEl ? parseFloat(inputEl.value||0)||0 : 0;
        if (shippingInput) shippingInput.value = val.toFixed(2);
        updateTotals();
        try {
          var modalEl = document.getElementById('modal-shipping-create');
          if (window.bootstrap && modalEl) window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
          else if (modalEl) { modalEl.style.display='none'; modalEl.classList.remove('show'); }
        } catch(e){}
      });
    })();
    if (shippingInput) {
      shippingInput.addEventListener('input', updateTotals);
      shippingInput.addEventListener('change', updateTotals);
    }
    var docTypeInput = document.getElementById('document_discount_type');
    var docValueInput = document.getElementById('document_discount_value');
    var docExcludeInput = document.getElementById('discount_exclude_shipping');
    var toggleDiscTax = document.getElementById('toggle-discount-tax');
    var quoteLines = document.getElementById('quote-lines-table');
    function syncDiscTaxVisibility(){
      if (!quoteLines || !toggleDiscTax) return;
      quoteLines.classList.toggle('show-discount-tax', toggleDiscTax.checked);
    }
    if (toggleDiscTax) {
      toggleDiscTax.addEventListener('change', syncDiscTaxVisibility);
      syncDiscTaxVisibility();
    }
    if (docTypeInput) docTypeInput.addEventListener('change', updateTotals);
    if (docValueInput) {
      docValueInput.addEventListener('input', updateTotals);
      docValueInput.addEventListener('change', updateTotals);
    }
    if (docExcludeInput) docExcludeInput.addEventListener('change', updateTotals);

  updateTotals();
  });

  // Global JS error catcher box hookup
  window.addEventListener('error', function(e) {
    try { console.error('GLOBAL JS ERROR', e.message, e.filename, e.lineno, e.colno, e.error); } catch(err){}
    var box = document.getElementById('js-error-box');
    if (box) {
      box.style.display = 'block';
      var loc = '';
      try { loc = (e.filename || '') + (e.lineno ? (':' + e.lineno) : '') + (e.colno ? (':' + e.colno) : ''); } catch(err){}
      box.textContent = 'JavaScript ERROR: ' + (e.message || e.error || e) + (loc ? (' — ' + loc) : '');
    }
  });
  window.addEventListener('unhandledrejection', function(e) {
    var box = document.getElementById('js-error-box');
    if (box) { box.style.display = 'block'; box.textContent = 'Unhandled Promise ERROR: ' + (e.reason && e.reason.message ? e.reason.message : e.reason); }
  });

  // Customer autocomplete
  document.addEventListener('DOMContentLoaded', function(){
    if (typeof window.APP_BASE === 'undefined') { window.APP_BASE = (document.querySelector('base') ? document.querySelector('base').href : '').replace(/\/$/, '') || ''; }
    var input = document.getElementById('customer_search');
    var list = document.getElementById('customer_list');
    var hid = document.getElementById('customer_id');
    var priceListSelect = document.getElementById('price_list_id');
    function debounce(fn, wait){ var t; return function(){ var ctx=this, args=arguments; clearTimeout(t); t=setTimeout(function(){ fn.apply(ctx,args); }, wait); }; }
    function doSearch(inputEl, term){
      if (!term) { list.style.display='none'; return; }
      var url = (window.APP_BASE||'') + '/quotations/search-customers?q=' + encodeURIComponent(term);
      fetch(url)
        .then(function(r){ return r.json(); })
        .catch(function(){ return fetch('/quotations/search-customers?q=' + encodeURIComponent(term)).then(function(r){ return r.json(); }); })
        .then(function(data){
        list.innerHTML='';
        if (!data || data.length===0) { list.style.display='none'; return; }
        data.forEach(function(c){
          var row = document.createElement('div'); row.className='p-2'; row.style.cursor='pointer';
          row.innerHTML = '<strong>'+c.code+'</strong> — '+c.name+' <div class="small text-muted">'+(c.company||'')+' '+(c.email?'- '+c.email:'')+'</div>';
          row.addEventListener('click', function(){
            inputEl.value = c.code + ' - ' + c.name;
            hid.value = c.id;
            list.style.display='none';
            var plurl = (window.APP_BASE||'') + '/quotations/price-lists/' + c.id;
            fetch(plurl)
              .then(function(r){ return r.json(); })
              .catch(function(){ return fetch('/quotations/price-lists/' + c.id).then(function(r){ return r.json(); }); })
              .then(function(pls){
              priceListSelect.innerHTML = '<option value="">Default</option>';
              pls.forEach(function(pl){ var opt = document.createElement('option'); opt.value=pl.id; opt.text=pl.name; priceListSelect.appendChild(opt); });
            }).catch(function(err){ console.error('Failed to load price lists', err); });
          });
          list.appendChild(row);
        });
        list.style.display='block';
      }).catch(function(){ list.style.display='none'; });
    }
    var debouncedSearch = debounce(function(e){ doSearch(input, e.target.value); }, 200);
    if (input) {
      input.addEventListener('input', debouncedSearch);
      input.addEventListener('blur', function(){ setTimeout(function(){ list.style.display='none'; },200); });
    }

    // Edit-mode prefill: if customer_id already exists, preload price lists
    try {
      if (hid && hid.value) {
        var plurl0 = (window.APP_BASE||'') + '/quotations/price-lists/' + hid.value;
        fetch(plurl0)
          .then(function(r){ return r.json(); })
          .catch(function(){ return fetch('/quotations/price-lists/' + hid.value).then(function(r){ return r.json(); }); })
          .then(function(pls){
            if (!priceListSelect) return;
            // Preserve current selection if already chosen
            var current = priceListSelect.value || '';
            priceListSelect.innerHTML = '<option value="">Default</option>';
            (pls||[]).forEach(function(pl){
              var opt = document.createElement('option');
              opt.value = pl.id; opt.text = pl.name;
              priceListSelect.appendChild(opt);
            });
            if (current) priceListSelect.value = current;
          });
      }
    } catch (e) {}
  });

  // AJAX submit
  (function(){
    function attachAjaxSubmitHandler(){
  var form = document.getElementById('quotation-form') || document.querySelector('form[action*="quotations/create"]') || document.querySelector('form[action*="quotations/update"]') || document.querySelector('form');
  var statusInput = document.getElementById('quote-status');
      if (!form) { var d = document.getElementById('quote-debug-output'); if (d) d.textContent = 'ERROR: Quotation form not found on page. AJAX submit handler NOT attached.'; return; }
      if (form._ajaxHandlerAttached) return; form._ajaxHandlerAttached = true;
        function sendAjaxForm(fd){
        var submitUrl = (form.action || '') + ((form.action || '').indexOf('?') === -1 ? '?ajax=1' : '&ajax=1');
        var debugEl = document.getElementById('quote-debug-output');
        try { document.getElementById('error-customer').textContent = ''; } catch(e){}
        document.querySelectorAll('.line-errors').forEach(function(el){ el.textContent = ''; });
        try {
          var entries = [];
          var lineKeys = {};
          fd.forEach(function(v,k){
            if (k.indexOf('lines[') === 0) {
              lineKeys[k] = v;
            } else {
              entries.push(k + '=' + String(v));
            }
          });
          var payloadSummary = entries.slice(0, 8).join('\n');
          if (Object.keys(lineKeys).length) {
            payloadSummary += (payloadSummary ? '\n' : '') + 'lines_count=' + Object.keys(lineKeys).filter(function(key){ return key.endsWith('[quantity]'); }).length;
          }
          if (debugEl) {
            debugEl.textContent = 'Request payload summary:\n' + (payloadSummary || '<empty>') + '\n\nPreparing to send...';
            debugEl.style.border = '';
          }
        } catch(e){
          if (debugEl) debugEl.textContent = '';
        }
        // Use redirect: 'manual' so we can detect if server redirects (common when session expired)
  // pick up CSRF token from meta if present and include it in headers for safety
  var csrfMeta = (document.querySelector('meta[name="csrf-token"]') || {}).content || null;
  var headers = { 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' };
  if (csrfMeta) headers['X-CSRF-TOKEN'] = csrfMeta;
    // Ensure server sees explicit ajax indicator in POST body as a fallback
    try { fd.append('ajax','1'); } catch(e) {}
    // Append CSRF token field if available on window (name + hash)
    try {
      if (window.csrfToken && window.csrfHash) {
        try { fd.append(window.csrfToken, window.csrfHash); } catch(e) {}
        // also include common header for servers that accept it
        headers['X-CSRF-TOKEN'] = window.csrfHash;
      }
    } catch(e) {}
  return fetch(submitUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: headers, redirect: 'manual' })
          .then(function(r){
            var contentType = r.headers.get('content-type') || '';
            var statusMeta = 'HTTP ' + r.status + ' ' + r.statusText + '\nContent-Type: ' + contentType + '\nRedirected: ' + (r.type === 'opaqueredirect' ? 'yes' : 'no');
            return r.text().then(function(text){
              var trimmed = text ? text.trim() : '';
              // Provide a longer preview and detect HTML/login pages to help debugging
              var preview = trimmed.substring(0, 400);
              if (debugEl) {
                debugEl.textContent = 'Response status:\n' + statusMeta + '\n\n';
                debugEl.textContent += 'Server response preview:\n' + (preview || '<empty response>');
                // If server returned a rendered page (DEBUG-VIEW markers), add an explanatory hint
                if (preview && preview.indexOf('DEBUG-VIEW START') !== -1) {
                  debugEl.textContent += '\n\nHint: The server returned a full HTML page (likely a redirect or a validation redirect back to the form). This means the server did not treat this request as AJAX or authentication failed. Try reloading the page and logging in, or check server logs.';
                }
              }
              return { ct: contentType, text: trimmed, meta: statusMeta, preview: preview, status: r.status };
            });
          })
          .then(function(resp){
            var json = null;
            // If server redirected (e.g. 302 Found) or returned HTML, likely session expired or auth problem
            if (resp.status === 302 || (resp.ct.indexOf('text/html')!==-1 && resp.preview && resp.preview.toLowerCase().indexOf('<form')!==-1)){
              var debugEl = document.getElementById('quote-debug-output');
              if (debugEl) debugEl.textContent += '\n\nHint: The server returned HTML (login/redirect). This often means your session expired or authentication failed. Try reloading the page and logging in again.';
              throw new Error('Non-JSON response (possible redirect/login)');
            }
            if (resp.ct.indexOf('application/json')!==-1){
              try{
                json = JSON.parse(resp.text);
                if (debugEl) {
                  debugEl.textContent += '\n\nParsed JSON:\n' + JSON.stringify(json,null,2);
                }
              }catch(e){
                if (debugEl) {
                  debugEl.textContent += '\n\nFailed to parse JSON: '+e.message;
                }
                throw e;
              }
            } else {
              var debugEl = document.getElementById('quote-debug-output');
              if (debugEl) debugEl.textContent += '\n\nError: Non-JSON response received. Server returned: ' + (resp.preview || '<no preview>');
              console.warn('Quotation create received non-JSON response', resp.preview || resp.text);
              throw new Error('Non-JSON response');
            }
            return json;
          })
          .then(function(json){
            if (json && json.message && json.message.toLowerCase().includes('authentication')){
              alert('Your session has expired or you are not authenticated. Please reload the page and login again.');
              return;
            }
            if (json && json.success){
              var base = (window.APP_BASE || '').replace(/\/$/, '');
              window.location = base + '/quotations/view/' + json.id;
              return;
            }
            if (json && json.errors){ if (json.errors.customer_id){ var el = document.getElementById('error-customer'); if (el) el.textContent = json.errors.customer_id; var cs = document.getElementById('customer_search'); if (cs) cs.classList.add('is-invalid'); } if (json.errors.lines && Array.isArray(json.errors.lines)){ json.errors.lines.forEach(function(lineErr, idx){ if (!lineErr) return; var rows = document.querySelectorAll('tr.quote-line'); var row = rows[idx]; if (row){ var container = row.querySelector('.line-errors'); var msgs = []; if (lineErr.quantity) msgs.push(lineErr.quantity); if (lineErr.unit_price) msgs.push(lineErr.unit_price); if (lineErr.description) msgs.push(lineErr.description); if (lineErr.general) msgs.push(lineErr.general); if (container) container.textContent = msgs.join(' • '); } }); } if (!json.errors.customer_id && (!json.errors.lines || json.errors.lines.length===0)) { alert('Failed to save quotation: '+(json.error||json.message||'validation error')); } } else { alert('Failed to save quotation: ' + (json && (json.error || json.message) ? (json.error || json.message) : 'server error')); } })
          .catch(function(err){ console.error('Quotation create AJAX failed', err); var debugEl = document.getElementById('quote-debug-output'); if (debugEl && debugEl.textContent){ debugEl.style.border = '1px solid #ff6b6b'; }
            // If we received a Non-JSON response, attempt the JSON API fallback automatically
            try {
              if (err && err.message && err.message.toLowerCase().indexOf('non-json')!==-1) { fallbackToApiCreate(); return; }
            } catch(e) {}
            alert('Unexpected error creating quotation — see debug output below and server logs'); });
      
        // Fallback: attempt JSON API create if non-JSON response occurs
        function fallbackToApiCreate(){
          try {
            var payload = { lines: [] };
            var formEl = form;
            var fdPairs = new FormData(formEl);
            // basic fields
            payload.customer_id = fdPairs.get('customer_id') || null;
            payload.issue_date = fdPairs.get('issue_date') || null;
            payload.price_list_id = fdPairs.get('price_list_id') || null;
            // include shipping from hidden field so API fallback preserves it
            var shipVal = fdPairs.get('shipping_amount');
            payload.shipping_amount = shipVal !== null ? parseFloat(shipVal || '0') || 0 : 0;
            // collect lines by index
            var lines = {};
            fdPairs.forEach(function(v,k){
              var m = k.match(/^lines\[(\d+)\]\[(.+)\]$/);
              if (m) {
                var idx = m[1]; var key = m[2];
                lines[idx] = lines[idx] || {};
                lines[idx][key] = v;
              }
            });
            Object.keys(lines).forEach(function(i){ payload.lines.push(lines[i]); });
            // send JSON
            fetch((window.APP_BASE||'') + '/quotations/api/create', { method: 'POST', credentials:'same-origin', headers: { 'Content-Type':'application/json', 'Accept':'application/json' }, body: JSON.stringify(payload) })
              .then(function(r){ return r.text().then(function(t){ var ct = r.headers.get('content-type')||''; return { status: r.status, ct: ct, text: t }; }); })
              .then(function(resp){
                var debugEl = document.getElementById('quote-debug-output');
                if (debugEl) debugEl.textContent += '\n\nAPI fallback response status: ' + resp.status + '\nPreview:\n' + (resp.text.substring(0,400) || '<empty>');
                if (resp.ct.indexOf('application/json')!==-1){
                  try {
                    var j = JSON.parse(resp.text);
                    if (j && j.success) {
                      var base2 = (window.APP_BASE || '').replace(/\/$/, '');
                      window.location = base2 + '/quotations/view/' + j.id;
                      return;
                    }
                    alert('API fallback failed: ' + (j.message||'unknown'));
                  } catch(e){ alert('API fallback returned invalid JSON'); }
                } else {
                  alert('API fallback returned non-JSON; check server logs');
                }
              })
              .catch(function(e){ alert('API fallback failed: ' + e.message); });
          } catch(e){ console.error('fallbackToApiCreate error', e); }
        }
      }
      form.addEventListener('submit', function(e){ if (e.shiftKey) return false; e.preventDefault(); var fd = new FormData(form); sendAjaxForm(fd); return false; });
      try { window.doQuoteAjaxSubmit = function(status){ try{ if (statusInput) statusInput.value = status || 'quoted'; var fd = new FormData(form); return sendAjaxForm(fd); } catch(e){ console.error('doQuoteAjaxSubmit failed', e); } }; } catch(e){}
      var btnQuote = document.getElementById('btn-save-quote');
      if (btnQuote){ btnQuote.addEventListener('click', function(ev){ ev.preventDefault(); try{ window.doQuoteAjaxSubmit('quoted'); } catch(e){ console.error(e); } return false; }); }
      form.addEventListener('keydown', function(ev){ if (ev.key==='Enter'){ var tag = (ev.target && ev.target.tagName) ? ev.target.tagName.toLowerCase() : ''; if (tag==='input' || tag==='select' || tag==='textarea'){ ev.preventDefault(); try{ window.doQuoteAjaxSubmit(); } catch(e){} return false; } } });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', attachAjaxSubmitHandler); } else { attachAjaxSubmitHandler(); }
  })();

  // Product autocomplete fallback (only if CoreLynkAutocomplete doesn't load)
  (function(){
    function initFallback(){
      if (window.CoreLynkAutocomplete && window.CoreLynkAutocomplete.attachProductAutocomplete) {
        return; // avoid duplicate dropdowns
      }
      var API_BASE = (window.APP_BASE || '') + '/quotations/search-products';
      var activeList = null;
      function closeList(){ if (activeList && activeList.parentNode) activeList.parentNode.removeChild(activeList); activeList = null; }
      function safeText(v){ if (v === undefined || v === null) return ''; var s = String(v); if (s.toLowerCase() === 'undefined' || s.toLowerCase() === 'null') return ''; return s; }
      function buildList(items, input){
        closeList();
        var list = document.createElement('div'); list.className = 'autocomplete-list card';
        list.style.position = 'absolute'; list.style.zIndex = 99999; list.style.minWidth = Math.max(260, input.getBoundingClientRect().width) + 'px';
        list.style.maxHeight = '260px'; list.style.overflowY = 'auto'; list.style.boxShadow = '0 6px 18px rgba(0,0,0,0.15)'; list.style.borderRadius = '6px';
        items.forEach(function(p){
          var row = document.createElement('div'); row.className = 'p-2'; row.style.cursor='pointer';
          var imgSrc = safeText(p.image_url) || safeText(p.image) || '';
          var salePrice = (p.special_price || p.sale_price || 0);
          var currency = safeText(p.sale_currency);
          var stock = (p.current_stock || 0);
          row.innerHTML = '<div style="display:flex;gap:.6rem;align-items:center"><img src="'+imgSrc+'" style="width:40px;height:32px;object-fit:cover;border-radius:4px" alt=""> <div><strong>'+safeText(p.code)+'</strong> — '+safeText(p.name)+'<div style="font-size:.8rem;color:#6b7280">'+salePrice+' '+currency+' • Stock: '+stock+'</div></div></div>';
          row.addEventListener('click', function(){
            var tr = input.closest('tr'); if (!tr) return; try{ tr.querySelector('.product-id').value = p.id; }catch(e){}
            try{ tr.querySelector('.line-price').value = (p.special_price || p.sale_price || 0).toFixed ? (p.special_price || p.sale_price || 0).toFixed(2) : (p.special_price || p.sale_price || 0); }catch(e){}
            try{ tr.querySelector('.line-desc').value = p.name; }catch(e){}
            try{ tr.querySelector('.line-price').dispatchEvent(new Event('change')); }catch(e){}
            closeList();
          });
          list.appendChild(row);
        });
        document.body.appendChild(list);
        var rect = input.getBoundingClientRect();
        list.style.left = (rect.left + window.scrollX) + 'px';
        list.style.top = (rect.bottom + window.scrollY + 6) + 'px';
        activeList = list;
      }
      function debounce(fn, wait){ var t; return function(){ var ctx=this, args=arguments; clearTimeout(t); t=setTimeout(function(){ fn.apply(ctx,args); }, wait); }; }
      function doSearch(input, term){
        if (!term) { closeList(); return; }
        var customerId = document.getElementById('customer_id') ? document.getElementById('customer_id').value : '';
        var url = API_BASE + '?q=' + encodeURIComponent(term) + '&customer_id=' + encodeURIComponent(customerId);
        fetch(url).then(function(r){ return r.json(); }).then(function(data){
          if (!data || data.length===0) { closeList(); return; }
          buildList(data, input);
        }).catch(function(){ closeList(); });
      }
      var debounced = debounce(function(e){
        var t = e.target; if (!t) return;
        if (t.matches('input.product-code') || t.matches('input.product-name')) { doSearch(t, t.value); }
      }, 180);
      document.addEventListener('input', debounced);
      document.addEventListener('click', function(e){ if (activeList && !activeList.contains(e.target)) closeList(); });
      window.addEventListener('resize', closeList);
      window.addEventListener('scroll', function(){ if (activeList) { try{ var inp = document.querySelector('input.product-code:focus, input.product-name:focus'); if (inp && activeList){ var rect = inp.getBoundingClientRect(); activeList.style.left = (rect.left + window.scrollX) + 'px'; activeList.style.top = (rect.bottom + window.scrollY + 6) + 'px'; } }catch(e){} } }, true);
    }

    if (window.CoreLynkAutocomplete && window.CoreLynkAutocomplete.attachProductAutocomplete) {
      return;
    }

    var tries = 0;
    var timer = setInterval(function(){
      tries++;
      if (window.CoreLynkAutocomplete && window.CoreLynkAutocomplete.attachProductAutocomplete) {
        clearInterval(timer);
        return;
      }
      if (tries >= 10) {
        clearInterval(timer);
        initFallback();
      }
    }, 50);
  })();
})();
