(function () {
  (function ensureStyles() {
    if (document.getElementById('doc-line-tools-style')) return;
    var st = document.createElement('style');
    st.id = 'doc-line-tools-style';
    st.textContent = '' +
      '[data-doc-lines-root] .doc-drag-handle{opacity:.2;transition:opacity .12s ease;}' +
      '[data-doc-lines-root] tr:hover .doc-drag-handle{opacity:.9;}' +
      '@media (hover:none){[data-doc-lines-root] .doc-drag-handle{opacity:.85;}}';
    document.head.appendChild(st);
  })();

  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  var toastHost = null;
  function ensureToastHost() {
    if (toastHost) return toastHost;
    toastHost = document.createElement('div');
    toastHost.style.position = 'fixed';
    toastHost.style.top = '16px';
    toastHost.style.right = '16px';
    toastHost.style.zIndex = '3000';
    toastHost.style.display = 'flex';
    toastHost.style.flexDirection = 'column';
    toastHost.style.gap = '8px';
    document.body.appendChild(toastHost);
    return toastHost;
  }

  function notify(message, level) {
    var host = ensureToastHost();
    var el = document.createElement('div');
    var isErr = (level || '').toLowerCase() === 'error';
    el.className = 'shadow-sm';
    el.style.padding = '10px 12px';
    el.style.borderRadius = '8px';
    el.style.fontSize = '12px';
    el.style.color = '#fff';
    el.style.maxWidth = '380px';
    el.style.background = isErr ? '#dc2626' : '#16a34a';
    el.textContent = message;
    host.appendChild(el);
    setTimeout(function () {
      if (el.parentNode) el.parentNode.removeChild(el);
    }, 3200);
  }

  function showInlineError(root, message) {
    var box = qs('.doc-lines-error', root);
    if (!box) {
      box = document.createElement('div');
      box.className = 'alert alert-danger py-2 px-3 mb-2 doc-lines-error';
      root.parentNode.insertBefore(box, root);
    }
    box.textContent = message;
    box.style.display = '';
  }

  function clearInlineError(root) {
    var box = qs('.doc-lines-error', root.parentNode || document);
    if (box) box.style.display = 'none';
  }

  function getCsrfPayload() {
    var token = '';
    var meta = qs('meta[name="csrf-token"]');
    if (meta) token = meta.getAttribute('content') || '';
    var payload = {};
    if (token) payload.csrf_test_name = token;
    return { token: token, payload: payload };
  }

  function fetchJson(url, bodyObj) {
    var csrf = getCsrfPayload();
    var payload = Object.assign({}, bodyObj || {}, csrf.payload);
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf.token || ''
      },
      body: JSON.stringify(payload)
    }).then(function (r) {
      return r.text().then(function (text) {
        var data = null;
        try { data = JSON.parse(text); } catch (e) {}
        if (!r.ok) {
          var msg = (data && data.error) ? data.error : ('Request failed (' + r.status + ')');
          throw new Error(msg);
        }
        if (!data) throw new Error('Invalid server response');
        return data;
      });
    });
  }

  function ensureSortableLoaded() {
    return new Promise(function (resolve, reject) {
      if (window.Sortable) return resolve(window.Sortable);
      var existing = qs('script[data-doc-sortable="1"]');
      if (existing) {
        existing.addEventListener('load', function () { resolve(window.Sortable); });
        existing.addEventListener('error', function () { reject(new Error('Failed to load SortableJS')); });
        return;
      }
      var s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
      s.async = true;
      s.dataset.docSortable = '1';
      s.onload = function () { resolve(window.Sortable); };
      s.onerror = function () { reject(new Error('Failed to load SortableJS')); };
      document.head.appendChild(s);
    });
  }

  function lineVersionMap(tbody) {
    var map = {};
    qsa('tr[data-line-id]', tbody).forEach(function (tr) {
      map[String(tr.getAttribute('data-line-id'))] = tr.getAttribute('data-line-updated-at') || '';
    });
    return map;
  }

  function collectLineOrder(tbody) {
    var ids = [];
    qsa('tr[data-line-id]', tbody).forEach(function (tr) {
      ids.push(parseInt(tr.getAttribute('data-line-id') || '0', 10));
    });
    return ids.filter(function (x) { return x > 0; });
  }

  function openSortModal(root, onApply) {
    var modalId = 'docLineSortModal';
    var m = document.getElementById(modalId);
    if (!m) {
      m = document.createElement('div');
      m.className = 'modal fade';
      m.id = modalId;
      m.tabIndex = -1;
      m.innerHTML = '' +
        '<div class="modal-dialog modal-dialog-centered">' +
          '<div class="modal-content">' +
            '<div class="modal-header"><h5 class="modal-title">Sort Lines</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>' +
            '<div class="modal-body">' +
              '<div class="form-check mb-2"><input class="form-check-input" type="radio" name="doc-sort-key" value="created_oldest" id="dls1" checked><label class="form-check-label" for="dls1">Creation date oldest first</label></div>' +
              '<div class="form-check mb-2"><input class="form-check-input" type="radio" name="doc-sort-key" value="created_newest" id="dls2"><label class="form-check-label" for="dls2">Creation date newest first</label></div>' +
              '<div class="form-check mb-2"><input class="form-check-input" type="radio" name="doc-sort-key" value="name_asc" id="dls3"><label class="form-check-label" for="dls3">Product name A to Z</label></div>' +
              '<div class="form-check mb-2"><input class="form-check-input" type="radio" name="doc-sort-key" value="name_desc" id="dls4"><label class="form-check-label" for="dls4">Product name Z to A</label></div>' +
              '<div class="form-check mb-2"><input class="form-check-input" type="radio" name="doc-sort-key" value="code_asc" id="dls5"><label class="form-check-label" for="dls5">Internal reference / product code ascending</label></div>' +
              '<div class="form-check mb-2"><input class="form-check-input" type="radio" name="doc-sort-key" value="code_desc" id="dls6"><label class="form-check-label" for="dls6">Internal reference / product code descending</label></div>' +
              '<div class="form-check mb-2"><input class="form-check-input" type="radio" name="doc-sort-key" value="price_asc" id="dls7"><label class="form-check-label" for="dls7">Unit price low to high</label></div>' +
              '<div class="form-check"><input class="form-check-input" type="radio" name="doc-sort-key" value="price_desc" id="dls8"><label class="form-check-label" for="dls8">Unit price high to low</label></div>' +
            '</div>' +
            '<div class="modal-footer">' +
              '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>' +
              '<button type="button" class="btn btn-primary" id="docSortApplyBtn">Apply Sort</button>' +
            '</div>' +
          '</div>' +
        '</div>';
      document.body.appendChild(m);
    }

    var modal = new bootstrap.Modal(m);
    var applyBtn = document.getElementById('docSortApplyBtn');
    applyBtn.onclick = function () {
      var selected = qs('input[name="doc-sort-key"]:checked', m);
      var key = selected ? selected.value : 'created_oldest';
      modal.hide();
      onApply(key);
    };

    modal.show();
  }

  function openSectionDeleteChoice(onChoice) {
    var modalId = 'docSectionDeleteModal';
    var m = document.getElementById(modalId);
    if (!m) {
      m = document.createElement('div');
      m.className = 'modal fade';
      m.id = modalId;
      m.tabIndex = -1;
      m.innerHTML = '' +
        '<div class="modal-dialog modal-dialog-centered">' +
          '<div class="modal-content">' +
            '<div class="modal-header"><h5 class="modal-title">Delete Section</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>' +
            '<div class="modal-body"><p class="mb-0">Choose delete mode:</p></div>' +
            '<div class="modal-footer">' +
              '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>' +
              '<button type="button" class="btn btn-outline-danger" id="deleteSectionOnlyBtn">Delete Section Header Only</button>' +
              '<button type="button" class="btn btn-danger" id="deleteSectionChildrenBtn">Delete Section + Lines Beneath</button>' +
            '</div>' +
          '</div>' +
        '</div>';
      document.body.appendChild(m);
    }

    var modal = new bootstrap.Modal(m);
    document.getElementById('deleteSectionOnlyBtn').onclick = function () {
      modal.hide();
      onChoice('header_only');
    };
    document.getElementById('deleteSectionChildrenBtn').onclick = function () {
      modal.hide();
      onChoice('with_children');
    };

    modal.show();
  }

  function bindDocumentLineTools(root) {
    var table = qs('table[data-doc-line-type]', root);
    if (!table) return;

    var docType = table.getAttribute('data-doc-line-type');
    var documentId = table.getAttribute('data-doc-id');
    var tbody = qs('tbody', table);
    if (!tbody || !docType || !documentId) return;

    if (table.dataset.docLineBound === '1') return;
    table.dataset.docLineBound = '1';

    var toolbar = qs('[data-doc-line-toolbar]', root) || table.parentNode;

    var sortBtn = qs('[data-doc-sort-btn]', toolbar);
    if (!sortBtn) {
      sortBtn = document.createElement('button');
      sortBtn.type = 'button';
      sortBtn.className = 'btn btn-sm btn-outline-primary';
      sortBtn.setAttribute('data-doc-sort-btn', '1');
      sortBtn.innerHTML = '<i class="bi bi-sort-down me-1"></i>Sort Lines';
      toolbar.appendChild(sortBtn);
    }


    function refreshRowsHtml(html) {
      if (typeof html !== 'string') return;
      tbody.innerHTML = html;
      document.dispatchEvent(new CustomEvent('doc-lines:rows-updated', {
        detail: { docType: docType, documentId: documentId, tbody: tbody }
      }));
    }

    function applyDomOrder(orderedIds) {
      if (!Array.isArray(orderedIds) || !orderedIds.length) return;
      var rowMap = {};
      qsa('tr[data-line-id]', tbody).forEach(function (tr) {
        var id = parseInt(tr.getAttribute('data-line-id') || '0', 10);
        if (id > 0) rowMap[id] = tr;
      });

      var frag = document.createDocumentFragment();
      orderedIds.forEach(function (id) {
        if (rowMap[id]) frag.appendChild(rowMap[id]);
      });

      qsa('tr[data-line-id]', tbody).forEach(function (tr) {
        if (!tr.parentNode || tr.parentNode !== tbody) return;
        frag.appendChild(tr);
      });

      tbody.innerHTML = '';
      tbody.appendChild(frag);
    }

    function hasSortableLines() {
      return qsa('tr[data-line-id]', tbody).length > 1;
    }

    function syncSortButtonState() {
      sortBtn.disabled = qsa('tr[data-line-id]', tbody).length === 0;
    }

    function callEndpoint(path, bodyObj) {
      clearInlineError(table);
      return fetchJson(path, bodyObj).catch(function (err) {
        showInlineError(table, err.message || 'Operation failed');
        throw err;
      });
    }

    function requestReorder() {
      if (!hasSortableLines()) return;
      var order = collectLineOrder(tbody);
      callEndpoint('document-lines/reorder', {
        doc_type: docType,
        document_id: documentId,
        line_ids: order,
        line_versions: lineVersionMap(tbody)
      }).then(function (res) {
        if (docType === 'sales_order' && Array.isArray(res.ordered_line_ids)) {
          applyDomOrder(res.ordered_line_ids);
        } else if (res.rows_html) {
          refreshRowsHtml(res.rows_html);
        }
        syncSortButtonState();
        notify('Line order saved', 'success');
      });
    }

    sortBtn.addEventListener('click', function () {
      openSortModal(root, function (sortKey) {
        callEndpoint('document-lines/sort', {
          doc_type: docType,
          document_id: documentId,
          sort_key: sortKey,
          line_versions: lineVersionMap(tbody)
        }).then(function (res) {
          if (docType === 'sales_order' && Array.isArray(res.ordered_line_ids)) {
            applyDomOrder(res.ordered_line_ids);
          } else if (res.rows_html) {
            refreshRowsHtml(res.rows_html);
          }
          syncSortButtonState();
          notify('Lines sorted successfully', 'success');
        });
      });
    });

    tbody.addEventListener('change', function () {
      // Section feature removed: no section title editing.
    });

    tbody.addEventListener('click', function () {
      // Section feature removed: no section delete actions.
    });

    var recalcTimer = null;
    function queueRecalc(payload) {
      if (recalcTimer) clearTimeout(recalcTimer);
      recalcTimer = setTimeout(function () {
        callEndpoint('document-lines/recalculate', payload).then(function (res) {
          if (res.rows_html) refreshRowsHtml(res.rows_html);
        }).catch(function () {
          // already surfaced
        });
      }, 400);
    }

    tbody.addEventListener('input', function (e) {
      var t = e.target;
      if (!t) return;
      var row = t.closest('tr[data-line-id]');
      if (!row) return;
      if (row.getAttribute('data-display-type') === 'section') return;
      if (!/inline-qty|inline-price|inline-tax/.test(t.className || '')) return;

      var lineId = parseInt(row.getAttribute('data-line-id') || '0', 10);
      if (lineId <= 0) return;

      var qtyEl = qs('.inline-qty', row);
      var priceEl = qs('.inline-price', row);
      var taxEl = qs('.inline-tax', row);

      var payload = {
        doc_type: docType,
        document_id: documentId,
        line_id: lineId,
        quantity: qtyEl ? parseFloat(qtyEl.value || '0') : undefined,
        qty: qtyEl ? parseFloat(qtyEl.value || '0') : undefined,
        unit_price: priceEl ? parseFloat(priceEl.value || '0') : undefined,
        tax_rate: taxEl ? parseFloat(taxEl.value || '0') : undefined
      };
      queueRecalc(payload);
    });

    ensureSortableLoaded().then(function (Sortable) {
      new Sortable(tbody, {
        animation: 150,
        handle: '.doc-drag-handle',
        draggable: 'tr[data-line-id]',
        ghostClass: 'table-warning',
        onEnd: function () {
          requestReorder();
        },
        touchStartThreshold: 8,
        fallbackTolerance: 8
      });
    }).catch(function (e) {
      showInlineError(table, e.message || 'Drag library could not be loaded');
    });

    syncSortButtonState();
  }

  document.addEventListener('DOMContentLoaded', function () {
    qsa('[data-doc-lines-root]').forEach(function (root) {
      bindDocumentLineTools(root);
    });
  });
})();
