// CoreLynk universal autocomplete module
// NOTE: This is a behavior-preserving extraction of the quotation product autocomplete.
// Do NOT change selectors/UX without auditing quotation + purchases.

(function () {
    'use strict';

    // Minimal quotation calculator & product autocomplete (ES5-compat)
    function debounce(fn, wait) {
        if (typeof wait === 'undefined') wait = 300;
        var t;
        return function () {
            var args = Array.prototype.slice.call(arguments);
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(null, args); }, wait);
        };
    }

    // Helper for nullish-coalescing replacement on older engines
    function nvl() {
        for (var i = 0; i < arguments.length; i++) {
            if (arguments[i] !== undefined && arguments[i] !== null) return arguments[i];
        }
        return null;
    }

    // Resolve application base path exactly like quotation_calculator.js
    function resolveBase() {
        // prefer window.APP_BASE, otherwise use first path segment from pathname
        var base = (window.APP_BASE && window.APP_BASE !== '')
            ? window.APP_BASE
            : (function () {
                var parts = location.pathname.split('/');
                return (parts && parts.length > 1) ? '/' + parts[1] : '';
            })();
        try { if (console && console.log) console.log('APP_BASE resolved', base); } catch (e) { }
        return base;
    }

    // Inline placeholder image as an SVG data-URI to avoid missing-file 404s
    function placeholderDataUri() {
        try {
            var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="46" height="46">' +
                '<rect fill="#f0f0f0" width="100%" height="100%"/>' +
                '<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#999" font-size="10">No image</text>' +
                '</svg>';
            return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
        } catch (e) {
            return '';
        }
    }

    // Behavior-preserving product autocomplete from quotation_calculator.js
    // Public API: window.CoreLynkAutocomplete.attachProductAutocomplete(input, byName)
    function attachProductAutocomplete(input, byName, context) {
        if (typeof byName === 'undefined') byName = false;
        if (typeof context === 'undefined') context = 'sales'; // 'sales' or 'purchase'
        if (!input) return;

        // Avoid double-attaching
        if (input.dataset && input.dataset.autocompleteAttached) return;
        if (input.dataset) input.dataset.autocompleteAttached = '1';

        // Remove any old dropdown for this input
        if (input._autocompleteList && input._autocompleteList.parentNode) {
            try { input._autocompleteList.parentNode.removeChild(input._autocompleteList); } catch (e) {}
        }

        var list = document.createElement('div');
        list.className = 'autocomplete-list card';
        list.style.position = 'absolute';
        list.style.zIndex = 9999;
        list.style.display = 'none';
        list.style.maxHeight = '280px';
        list.style.overflowY = 'auto';
        try { list.style.minWidth = Math.max(320, (input.offsetWidth || 200)) + 'px'; } catch (e) {}
        input._autocompleteList = list;

        var base = resolveBase();
        var latestResults = [];
        var overlay = null;
        var overlayKeyHandler = null;

        function findLineContainer(el) {
            var cur = el;
            while (cur) {
                try {
                    if (cur.matches && (cur.matches('tr') || (cur.classList && cur.classList.contains('row')))) return cur;
                } catch (e) {}
                cur = cur.parentNode;
            }
            return null;
        }

        function setField(el, val) {
            if (!el) return;
            try {
                if ('value' in el) el.value = (val === null || val === undefined) ? '' : val;
                else el.textContent = (val === null || val === undefined) ? '' : val;
            } catch (e) {}
        }

        function resolveImageUrl(rawValue, isVariant) {
            var raw = (rawValue === null || rawValue === undefined) ? '' : ('' + rawValue).trim();
            if (!raw) return '';
            if (/^https?:\/\//i.test(raw)) return raw;
            if (raw.charAt(0) === '/') return raw;
            if (/uploads\//i.test(raw)) return base + '/' + raw.replace(/^\/+/, '');
            if (isVariant) return base + '/uploads/variants/' + raw.replace(/^\/+/, '');
            return base + '/uploads/products/' + raw.replace(/^\/+/, '');
        }

        function pickImageCandidate(product) {
            if (product.variant_image) return { value: product.variant_image, isVariant: true };
            if (product.image_url) return { value: product.image_url, isVariant: false };
            if (product.image) return { value: product.image, isVariant: false };
            if (product.thumbnail) return { value: product.thumbnail, isVariant: false };
            if (product.thumb) return { value: product.thumb, isVariant: false };
            if (product.photo) return { value: product.photo, isVariant: false };
            if (product.photos && product.photos.length) return { value: product.photos[0], isVariant: false };
            if (typeof product.photos === 'string') {
                try {
                    var parsedPhotos = JSON.parse(product.photos);
                    if (parsedPhotos && parsedPhotos.length) return { value: parsedPhotos[0], isVariant: false };
                } catch (e) {}
            }
            if (product.images && product.images.length) return { value: product.images[0], isVariant: false };
            if (typeof product.images === 'string') {
                try {
                    var parsedImages = JSON.parse(product.images);
                    if (parsedImages && parsedImages.length) return { value: parsedImages[0], isVariant: false };
                } catch (e) {}
            }
            return { value: '', isVariant: false };
        }

        function formatVariantAttributes(rawAttrs) {
            if (!rawAttrs) return '';
            var attrs = rawAttrs;
            if (typeof attrs === 'string') {
                var trimmed = attrs.trim();
                if (trimmed === '') return '';
                if (trimmed.charAt(0) === '{' || trimmed.charAt(0) === '[') {
                    try { attrs = JSON.parse(trimmed); } catch (e) { return trimmed; }
                } else {
                    return trimmed;
                }
            }

            if (Object.prototype.toString.call(attrs) === '[object Array]') {
                var partsArr = [];
                attrs.forEach(function(entry){
                    if (!entry) return;
                    if (typeof entry === 'string') { if (entry.trim()) partsArr.push(entry.trim()); return; }
                    var key = entry.name || entry.key || entry.attribute || entry.label || '';
                    var val = entry.value || entry.val || entry.option || entry.text || '';
                    if (key && val) partsArr.push(key + ': ' + val);
                    else if (key) partsArr.push(key);
                    else if (val) partsArr.push(val);
                });
                return partsArr.join(' • ');
            }

            if (typeof attrs === 'object') {
                var partsObj = [];
                for (var k in attrs) {
                    if (!Object.prototype.hasOwnProperty.call(attrs, k)) continue;
                    var v = attrs[k];
                    if (v === null || v === undefined) continue;
                    if (typeof v === 'object') {
                        try { v = JSON.stringify(v); } catch (e) { v = String(v); }
                    }
                    var keyLabel = String(k).replace(/_/g, ' ');
                    partsObj.push(keyLabel + ': ' + String(v));
                }
                return partsObj.join(' • ');
            }

            return String(attrs || '').trim();
        }

        function closeOverlayPanel() {
            if (overlay && overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
            overlay = null;
            if (overlayKeyHandler) {
                document.removeEventListener('keydown', overlayKeyHandler);
                overlayKeyHandler = null;
            }
        }

        function handleProductSelection(product) {
            var tr = findLineContainer(input) || input.parentNode;
            if (!tr) return;

            try { if (console && console.log) console.log('product selected', product); } catch (e) {}

            var pid = tr.querySelector('.product-id'); if (pid) { try { pid.value = product.product_id || product.id || ''; } catch (e) {} }
            var isVariant = !!(product.variant_id || product.variant_name || product.attributes);
            var vidEl = tr.querySelector('.product-variant-id') || tr.querySelector('.variant-id');
            if (vidEl) {
                var variantId = product.variant_id || (isVariant ? (product.id || product.variantId || '') : '');
                try { vidEl.value = variantId || ''; } catch (e) { setField(vidEl, variantId || ''); }
            }
            var displayName = product.name || product.variant_name || product.code || '';
            var variantAttrs = formatVariantAttributes(product.attributes);
            var description = '';
            if (isVariant) {
                description = variantAttrs || (product.variant_name && product.variant_name !== displayName ? product.variant_name : '');
            } else {
                description = product.description || product.name || '';
            }
            if (displayName && description && description.indexOf(displayName) !== -1) {
                description = description.replace(displayName, '').replace(/^[-•—\s]+/, '').trim();
            }

            var codeEl = tr.querySelector('.product-code'); setField(codeEl, product.code || '');
            var nameEl = tr.querySelector('.product-name'); setField(nameEl, displayName);
            
            // Store product image URL in hidden field for persistence
            var imgUrlEl = tr.querySelector('.product-image-url');
            if (imgUrlEl) {
                var imgCandidate = pickImageCandidate(product);
                var imgFullUrl = resolveImageUrl(imgCandidate.value, imgCandidate.isVariant);
                if (imgFullUrl) {
                    try { imgUrlEl.value = imgFullUrl; } catch (e) { setField(imgUrlEl, imgFullUrl); }
                }
            }
            
            var priceEl = tr.querySelector('.line-price');
            if (priceEl) {
                try {
                    if (context === 'purchase') {
                        var vendorId = null;
                        try {
                            var vEl = document.getElementById('vendorId') || document.getElementById('vendor_id') || document.querySelector('[name="vendor_id"]') || null;
                            if (!vEl) {
                                var rootForm = tr.closest('form') || document;
                                try { vEl = rootForm.querySelector && rootForm.querySelector('input[name="vendor_id"]'); } catch (e) { vEl = null; }
                            }
                            if (!vEl) {
                                vEl = document.querySelector('#vendorId') || document.querySelector('input[name="vendor_id"]');
                            }
                            if (vEl) vendorId = (vEl.value !== undefined && vEl.value !== '') ? vEl.value : (vEl.getAttribute && vEl.getAttribute('value')) || null;
                        } catch (e) { vendorId = null; }

                        try { var msgArea = document.getElementById('messages'); if (msgArea) msgArea.innerHTML = ''; } catch (e) {}

                        if (vendorId) {
                            var priceUrl = base + '/product-vendors/price?product_id=' + encodeURIComponent(product.id) + '&vendor_id=' + encodeURIComponent(vendorId);
                            try { if (console && console.log) console.log('Fetching vendor cost', priceUrl); } catch (e) {}
                            fetch(priceUrl).then(function(r){ return r.json(); }).then(function(resp){
                                try { if (console && console.log) console.log('vendor cost response', resp); } catch (e) {}
                                if (resp && resp.success && typeof resp.cost_price !== 'undefined') {
                                    try { priceEl.value = (parseFloat(resp.cost_price) || 0).toFixed(2); }
                                    catch (e) { setField(priceEl, (parseFloat(resp.cost_price) || 0).toFixed(2)); }
                                    try { priceEl.dataset.currency = resp.currency || 'PKR'; priceEl.title = (resp.currency || 'PKR'); } catch (e) {}
                                } else {
                                    try {
                                        var msgEl = document.getElementById('messages');
                                        if (msgEl) msgEl.innerHTML = '<div class="alert alert-warning">This product has no cost set for selected vendor.</div>';
                                    } catch (e) { try { if (console && console.warn) console.warn('No messages area to show vendor-price warning'); } catch (_) {} }
                                }
                                try { var ev2 = new Event('change'); priceEl.dispatchEvent(ev2); } catch (e) {}
                            }).catch(function(err){
                                try { if (console && console.error) console.error('vendor cost fetch failed', err); } catch (e) {}
                            });
                        } else {
                            try {
                                var msgEl2 = document.getElementById('messages');
                                if (msgEl2) msgEl2.innerHTML = '<div class="alert alert-info">Select a vendor first to load vendor cost.</div>';
                            } catch (e) { try { if (console && console.warn) console.warn('No messages area to show vendor selection info'); } catch (_) {} }
                        }
                    } else {
                        priceEl.value = (nvl(product.special_price, product.sale_price, 0)).toFixed(2);
                    }
                }
                catch (e) { if (context !== 'purchase') setField(priceEl, (nvl(product.special_price, product.sale_price, 0)).toFixed(2)); }
            }
            var descEl = tr.querySelector('.line-desc'); setField(descEl, description);

            var unitEl = tr.querySelector('input[name*="[unit]"]') || tr.querySelector('.line-unit') || tr.querySelector('[name*="[unit]"]');
            if (unitEl) {
                try { unitEl.value = product.unit || product.uom || 'pcs'; }
                catch (e) { setField(unitEl, product.unit || product.uom || 'pcs'); }
            }
            var taxEl = tr.querySelector('.line-tax') || tr.querySelector('[name*="[tax]"]');
            if (taxEl) {
                try { taxEl.value = nvl(product.tax_rate, product.tax, 0); }
                catch (e) { setField(taxEl, nvl(product.tax_rate, product.tax, 0)); }
            }
            var uw = tr.querySelector('.unit-weight') || tr.querySelector('input[name*="[unit_weight]"]') || tr.querySelector('input[name*="[weight]"]');
            var weightVal = nvl(product.unit_weight, product.weight, product.weight_net, product.weight_gross, 0);
            var weightUnit = String(product.weight_unit || 'KG').toUpperCase();
            if (uw) {
                try { uw.value = weightVal; }
                catch (e) { setField(uw, weightVal); }
            }
            var wu = tr.querySelector('.weight-unit') || tr.querySelector('input[name*="[weight_unit]"]');
            if (wu) {
                try { wu.value = weightUnit; }
                catch (e) { setField(wu, weightUnit); }
            }
            try { tr.setAttribute('data-unit-weight', weightVal); } catch(e){}
            try { tr.setAttribute('data-weight-unit', weightUnit); } catch(e){}

            var ms = tr.querySelector('.meta-stock'); setField(ms, nvl(product.current_stock, product.stock, product.available_stock, product.quantity, product.qty, 0));
            var mv = tr.querySelector('.meta-vendor'); setField(mv, product.vendor_name || product.vendor_id || '-');
            var mw = tr.querySelector('.meta-weight'); setField(mw, nvl(product.unit_weight, product.weight, product.weight_net, product.weight_gross, 0) + ' ' + weightUnit);

            try {
                ['.line-qty', '.line-price', '.unit-weight'].forEach(function(sel){
                    var el = tr.querySelector(sel);
                    if (el) {
                        var evt = new Event('input', { bubbles:true });
                        el.dispatchEvent(evt);
                    }
                });
            } catch (e) {}

            var thumb = tr.querySelector('.product-thumb');
            if (thumb) {
                try {
                    var candidate2 = pickImageCandidate(product);
                    var imgUrl = resolveImageUrl(candidate2.value, candidate2.isVariant) || placeholderDataUri();
                    if (thumb.tagName && thumb.tagName.toUpperCase() === 'IMG') {
                        thumb.onerror = function () { try { this.onerror = null; this.src = placeholderDataUri(); } catch (e) {} };
                        thumb.src = imgUrl;
                        // Ensure icon visibility is properly toggled
                        var thumbIcon = thumb.parentElement ? thumb.parentElement.querySelector('.thumb-icon') : null;
                        if (thumbIcon && imgUrl && imgUrl.length > 0) {
                            thumbIcon.style.display = 'none';
                        }
                    } else {
                        thumb.style.backgroundImage = 'url("' + imgUrl + '")';
                    }
                } catch (e) { }
            }

            var ev;
            try { ev = new Event('change'); }
            catch (e) { ev = document.createEvent('Event'); ev.initEvent('change', true, true); }
            if (priceEl) priceEl.dispatchEvent(ev);

            try { if (typeof computeShipment === 'function') computeShipment(); } catch (e) { }
            list.style.display = 'none';
            closeOverlayPanel();
        }

        function createResultRow(product) {
            var row = document.createElement('div');
            row.className = 'p-2 d-flex align-items-center';
            row.style.cursor = 'pointer';
            var imgSrc = placeholderDataUri();
            var candidate = pickImageCandidate(product);
            var resolved = resolveImageUrl(candidate.value, candidate.isVariant);
            if (resolved) imgSrc = resolved;
            var listName = product.name || product.variant_name || '';
            var listAttrs = formatVariantAttributes(product.attributes);
            row.innerHTML = '' +
                '<div style="width:46px;height:46px;flex:0 0 46px;margin-right:8px;border-radius:6px;overflow:hidden;background:#0b1220;display:flex;align-items:center;justify-content:center">' +
                '<img src="' + imgSrc + '" style="width:46px;height:46px;object-fit:cover;display:block">' +
                '</div>' +
                '<div style="flex:1;min-width:0">' +
                '<div style="font-weight:600"><strong>' + (product.code || '') + '</strong> — ' + listName + '</div>' +
                (listAttrs ? ('<div class="text-muted small">' + listAttrs + '</div>') : '') +
                '<div class="text-muted small">' + ((nvl(product.special_price, product.sale_price, 0)).toFixed(2)) + ' ' + (product.sale_currency || '') + ' • <i class="bi bi-box-seam"></i> ' + (nvl(product.current_stock, product.stock, product.available_stock, product.quantity, product.qty, 0)) + ' • <i class="bi bi-shop"></i> ' + (product.vendor_name || (product.vendor_id || '-')) + ' • <i class="bi bi-balance-scale"></i> ' + (nvl(product.unit_weight, product.weight, product.weight_net, product.weight_gross, 0)) + '</div>' +
                '</div>';

            try {
                var tmpImg = row.querySelector && row.querySelector('img');
                if (tmpImg) tmpImg.onerror = function () { try { this.onerror = null; this.src = placeholderDataUri(); } catch (e) { } };
            } catch (e) { }

            row.addEventListener('click', function () {
                handleProductSelection(product);
            });
            return row;
        }

        function openOverlayPanel() {
            if (!latestResults || !latestResults.length) return;
            closeOverlayPanel();

            overlay = document.createElement('div');
            overlay.style.position = 'fixed';
            overlay.style.inset = '0';
            overlay.style.zIndex = 15000;
            overlay.style.backgroundColor = 'rgba(2,7,18,0.85)';
            overlay.style.display = 'flex';
            overlay.style.alignItems = 'flex-start';
            overlay.style.justifyContent = 'center';
            overlay.style.padding = '32px 12px 24px';
            overlay.style.overflowY = 'auto';

            var panel = document.createElement('div');
            panel.style.backgroundColor = '#050915';
            panel.style.borderRadius = '16px';
            panel.style.boxShadow = '0 24px 60px rgba(0,0,0,0.55)';
            panel.style.width = 'min(960px, calc(100% - 32px))';
            panel.style.maxWidth = '960px';
            panel.style.maxHeight = 'calc(100vh - 56px)';
            panel.style.display = 'flex';
            panel.style.flexDirection = 'column';
            panel.style.padding = '16px 18px 12px';
            panel.style.gap = '12px';
            panel.style.position = 'relative';

            var header = document.createElement('div');
            header.style.display = 'flex';
            header.style.alignItems = 'center';
            header.style.justifyContent = 'space-between';
            header.style.gap = '12px';

            var title = document.createElement('div');
            title.style.fontSize = '1rem';
            title.style.fontWeight = '600';
            title.style.color = '#f7f7f7';
            title.textContent = 'Product results (' + latestResults.length + ')';
            header.appendChild(title);

            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.textContent = 'Close';
            closeBtn.style.background = 'transparent';
            closeBtn.style.border = '1px solid rgba(255,255,255,0.2)';
            closeBtn.style.color = '#f7f7f7';
            closeBtn.style.borderRadius = '8px';
            closeBtn.style.padding = '4px 10px';
            closeBtn.style.cursor = 'pointer';
            closeBtn.addEventListener('click', function(event){ event.stopPropagation(); closeOverlayPanel(); });
            header.appendChild(closeBtn);
            panel.appendChild(header);

            var hint = document.createElement('div');
            hint.style.fontSize = '0.85rem';
            hint.style.color = '#94a3b8';
            hint.textContent = 'Click an item to fill the quotation line. Press Esc to close.';
            panel.appendChild(hint);

            var overlayList = document.createElement('div');
            overlayList.style.flex = '1 1 auto';
            overlayList.style.overflowY = 'auto';
            overlayList.style.display = 'flex';
            overlayList.style.flexDirection = 'column';
            overlayList.style.gap = '6px';
            overlayList.style.maxHeight = 'calc(100vh - 180px)';
            overlayList.style.padding = '4px 2px 10px';

            latestResults.forEach(function(product){
                var row = createResultRow(product);
                row.style.borderRadius = '10px';
                row.style.backgroundColor = '#0f1b34';
                row.style.boxShadow = '0 8px 18px rgba(0,0,0,0.45)';
                overlayList.appendChild(row);
            });
            panel.appendChild(overlayList);

            overlay.appendChild(panel);
            overlay.addEventListener('click', function(event){ if (event.target === overlay) closeOverlayPanel(); });
            panel.addEventListener('click', function(event){ event.stopPropagation(); });
            document.body.appendChild(overlay);

            overlayKeyHandler = function(event){
                if (event.key === 'Escape' || event.key === 'Esc') {
                    event.preventDefault();
                    closeOverlayPanel();
                }
            };
            document.addEventListener('keydown', overlayKeyHandler);
        }

        function hasOverflowClipping(el) {
            var cur = el;
            while (cur && cur !== document.body) {
                try {
                    var cs = window.getComputedStyle ? window.getComputedStyle(cur) : null;
                    if (cs) {
                        var ox = cs.overflowX || '';
                        var oy = cs.overflowY || '';
                        if (ox === 'hidden' || ox === 'auto' || ox === 'scroll' || oy === 'hidden' || oy === 'auto' || oy === 'scroll') return true;
                    }
                } catch (e) {}
                cur = cur.parentNode;
            }
            return false;
        }

        function ensureContainer() {
            var useFixed = false;
            try { useFixed = hasOverflowClipping(input); } catch (e) { useFixed = false; }

            if (useFixed) {
                try { if (list.dataset) list.dataset.fixed = '1'; } catch (e) {}
                try {
                    if (list.parentNode !== document.body) document.body.appendChild(list);
                } catch (e) {}
                list.style.position = 'fixed';
                list.style.zIndex = 12000;
            } else {
                try {
                    if (input.parentNode) {
                        input.parentNode.style.position = 'relative';
                        try { input.parentNode.style.overflow = 'visible'; } catch (e) {}
                        if (list.parentNode !== input.parentNode) input.parentNode.appendChild(list);
                    }
                } catch (e) {}
                try { if (list.dataset) delete list.dataset.fixed; } catch (e) {}
                list.style.position = 'absolute';
                list.style.zIndex = 9999;
            }
        }

        function positionList() {
            try {
                if (!list || list.style.display === 'none') return;
                ensureContainer();
                if (list.dataset && list.dataset.fixed === '1') {
                    var r = input.getBoundingClientRect();
                    list.style.left = Math.max(8, r.left) + 'px';
                    list.style.top = (r.bottom + 4) + 'px';
                    list.style.width = Math.max(320, r.width) + 'px';
                    var maxW = Math.min(520, Math.max(320, (window.innerWidth || 800) - r.left - 12));
                    list.style.maxWidth = maxW + 'px';
                }
            } catch (e) {}
        }

        ensureContainer();

        var onSearch = debounce(function (term) {
            if (!term) { list.style.display = 'none'; latestResults = []; closeOverlayPanel(); return; }

            var customerId = document.getElementById('customer_id') ? document.getElementById('customer_id').value : '';
            var url;
            try {
                if (context === 'purchase') {
                    url = base + '/products/search?q=' + encodeURIComponent(term);
                } else {
                    url = base + '/quotations/search-products?q=' + encodeURIComponent(term) + '&customer_id=' + encodeURIComponent(customerId);
                }
            } catch (e) {
                url = base + '/quotations/search-products?q=' + encodeURIComponent(term) + '&customer_id=' + encodeURIComponent(customerId);
            }

            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var arr = data;
                    try {
                        if (data && data.data && Object.prototype.toString.call(data.data) === '[object Array]') arr = data.data;
                    } catch (e) {}

                    list.innerHTML = '';
                    latestResults = [];
                    if (!arr || Object.prototype.toString.call(arr) !== '[object Array]' || arr.length === 0) {
                        closeOverlayPanel();
                        if (context === 'purchase') {
                            ensureContainer();
                            var emptyRow = document.createElement('div');
                            emptyRow.className = 'p-2 text-muted small';
                            emptyRow.textContent = 'No products found';
                            list.appendChild(emptyRow);
                            list.style.display = 'block';
                            positionList();
                            return;
                        }
                        list.style.display = 'none';
                        return;
                    }

                    ensureContainer();
                    closeOverlayPanel();
                    latestResults = arr.slice();
                    arr.forEach(function (p) {
                        list.appendChild(createResultRow(p));
                    });

                    if (latestResults.length > 6) {
                        var actionRow = document.createElement('div');
                        actionRow.className = 'd-flex align-items-center justify-content-center small';
                        actionRow.style.cursor = 'pointer';
                        actionRow.style.borderTop = '1px solid rgba(255,255,255,0.12)';
                        actionRow.style.padding = '6px';
                        actionRow.style.color = '#7dd3fc';
                        actionRow.style.fontWeight = '600';
                        actionRow.textContent = 'Open full search panel (' + latestResults.length + ' results)';
                        actionRow.addEventListener('click', openOverlayPanel);
                        list.appendChild(actionRow);
                    }

                    list.style.display = 'block';
                    positionList();
                })
                .catch(function (err) {
                    try { if (console && console.error) console.error('product autocomplete search failed', err); } catch (e) {}
                    list.style.display = 'none';
                });
        }, 200);

        input.addEventListener('input', function (e) { onSearch(e.target ? e.target.value : ''); });
        input.addEventListener('focus', function () { positionList(); });
        input.addEventListener('blur', function () { setTimeout(function () { list.style.display = 'none'; }, 200); });
        try {
            window.addEventListener('scroll', positionList, true);
            window.addEventListener('resize', positionList, true);
        } catch (e) {}
    }

    // Optional convenience initializer (does not change quotation selectors)
    // Public API: window.CoreLynkAutocomplete.initProductAutofill()
    function initProductAutofill(root) {
        root = root || document;
        var prodCodeInputs = root.querySelectorAll('input.product-code');
        for (var i = 0; i < prodCodeInputs.length; i++) {
            attachProductAutocomplete(prodCodeInputs[i], false);
            if (prodCodeInputs[i].dataset) prodCodeInputs[i].dataset.autocompleteAttached = '1';
        }
        var prodNameInputs = root.querySelectorAll('input.product-name');
        for (var j = 0; j < prodNameInputs.length; j++) {
            attachProductAutocomplete(prodNameInputs[j], true);
            if (prodNameInputs[j].dataset) prodNameInputs[j].dataset.autocompleteAttached = '1';
        }

        // Support dynamic rows: attach on focus for inputs added later
        document.addEventListener('focusin', function (e) {
            var t = e.target;
            if (!t) return;
            if ((t.classList && t.classList.contains('product-code')) || (t.classList && t.classList.contains('product-name'))) {
                if (!t.dataset.autocompleteAttached) {
                    attachProductAutocomplete(t, t.classList.contains('product-name'));
                    t.dataset.autocompleteAttached = '1';
                }
            }
        });
    }

    window.CoreLynkAutocomplete = window.CoreLynkAutocomplete || {};
    window.CoreLynkAutocomplete.attachProductAutocomplete = attachProductAutocomplete;
    window.CoreLynkAutocomplete.initProductAutofill = initProductAutofill;
})();
