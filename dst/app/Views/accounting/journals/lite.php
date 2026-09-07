<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Journal Entry<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-3">
            <div class="section-icon section-accent-green"><i class="bi bi-journal-plus"></i></div>
            <div>
              <h5 class="mb-0 section-title">Journal Entry</h5>
              <small class="section-sub">Fast journal entry posting</small>
            </div>
          </div>
          <div>

              <!-- Attachments (supporting docs) - moved into the form below so files submit correctly -->
            <a href="<?= base_url('accounting/trial-balance') ?>" class="btn btn-outline-info btn-sm">
              <i class="bi bi-calculator"></i> Trial Balance
            </a>
          </div>
        </div>
        <div class="card-body">
          <?php if(session()->getFlashdata('error')): ?>
            <div class="alert alert-danger py-2 mb-3">
              <i class="bi bi-exclamation-triangle me-2"></i><?= esc(session()->getFlashdata('error')) ?>
            </div>
          <?php endif; ?>
          <?php if(session()->getFlashdata('success')): ?>
            <div class="alert alert-success py-2 mb-3">
              <i class="bi bi-check-circle me-2"></i><?= esc(session()->getFlashdata('success')) ?>
            </div>
          <?php endif; ?>
          

          <form method="post" action="<?= base_url('accounting/journal-lite') ?>" id="journalForm" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row mb-3">
              <div class="col-12">
                <label class="form-label fw-bold mb-1">Attachments <small class="text-muted">(optional)</small></label>
                <div class="d-flex align-items-center gap-3">
                  <input type="file" name="attachments[]" class="form-control form-control-sm" multiple accept="application/pdf,image/png,image/jpeg">
                  <small class="text-muted">PDF/JPG/PNG, up to 10MB each. Uploads are supporting-only (no effect on accounting).</small>
                </div>
              </div>
            </div>
            <div class="row g-4">
              <!-- Date & Description Row -->
              <div class="col-md-3">
                <label class="form-label fw-bold">Date *</label>
      <input type="date" name="entry_date" id="entryDate" class="form-control form-control-sm" 
                       value="<?= date('Y-m-d') ?>" required tabindex="1">
              </div>
              <div class="col-md-9">
                <label class="form-label fw-bold">Description</label>
      <input type="text" name="memo" id="memo" class="form-control form-control-sm" 
                       placeholder="Transaction description" maxlength="255" tabindex="2">
              </div>
              
              <!-- Journal Lines -->
              <div class="col-12">
                <label class="form-label fw-bold">Lines <small class="text-muted">(multi-line, multi-currency)</small></label>
                <div class="table-responsive">
                  <table class="table table-sm table-compact align-middle" id="linesTable">
                    <thead class="table-light">
                      <tr>
                        <th style="width: 26%">Account</th>
                        <th style="width: 20%">Description</th>
                        <th style="width: 11%" class="text-end">Debit</th>
                        <th style="width: 11%" class="text-end">Credit</th>
                        <th style="width: 10%">Currency</th>
                        <th style="width: 10%" class="text-end">FX Rate</th>
                        <th style="width: 6%"></th>
                      </tr>
                    </thead>
                    <tbody id="linesBody">
                      <!-- Rows injected by JS -->
                    </tbody>
                  </table>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addLine()"><i class="bi bi-plus-lg"></i> Add Line</button>
                  <button type="button" class="btn btn-outline-primary btn-sm" onclick="openUsdSaleModal()"><i class="bi bi-currency-dollar"></i> USD Sale Wizard</button>
                  <small class="text-muted">Use wizard for foreign currency sale with fees & gain/loss</small>
                </div>
                <div class="mt-2 d-flex align-items-center gap-3 flex-wrap">
                  <span id="accountStatus" class="badge rounded-pill bg-secondary px-3 py-2">Accounts: loading…</span>
                  <span id="totalsBar" class="totals-bar badge px-3 py-2 bg-light border">Debits: 0.00 | Credits: 0.00 <span class="ms-2 status-text text-danger">Not Balanced</span></span>
                </div>
                <div id="amount_in_words" class="mt-2 text-muted small" style="font-style: italic;"></div>
                <div id="balanceError" class="mt-2 text-danger fw-semibold" style="display:none;"></div>
              </div>
              
              <!-- Submit Row -->
              <div class="col-12 d-flex align-items-center justify-content-between mt-2">
                <div class="text-muted"><small>Totals are validated in PKR using the exchange rate on the entry date.</small></div>
                <div>
                  <a href="<?= base_url('accounting/journals') ?>" class="btn btn-outline-info btn-lg me-2">
                    <i class="bi bi-list"></i> All Journals
                  </a>
                  <button type="button" class="btn btn-outline-secondary btn-lg me-2" onclick="clearForm()">
                    <i class="bi bi-arrow-clockwise"></i> Clear
                  </button>
                  <button type="submit" class="btn btn-primary btn-lg" tabindex="6">
                    <i class="bi bi-check-circle"></i> Post Entry
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Recent Entries -->
      <div class="card mt-4">
        <div class="card-header d-flex align-items-center gap-3">
          <div class="section-icon section-accent-amber"><i class="bi bi-clock-history"></i></div>
          <div>
            <h5 class="mb-0 section-title">Recent Entries</h5>
            <small class="section-sub">Latest transactions</small>
          </div>
        </div>
        <div class="card-body">
          <?php if (empty($entries)): ?>
            <div class="alert alert-info mb-0 text-center">
              <i class="bi bi-info-circle me-2"></i>Database is clean. Ready for fresh data entry!
            </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-compact table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th width="80">ID</th>
                  <th width="120">Date</th>
                  <th>Description</th>
                  <th width="140" class="text-end">Amount</th>
                  <th width="120"></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (($entries ?? []) as $e): ?>
                <tr>
                  <td><span class="badge bg-secondary">#<?= $e['id'] ?></span></td>
                  <td><small class="text-muted"><?= date('M d', strtotime($e['entry_date'])) ?></small></td>
                  <td><strong><?= esc($e['memo'] ?: 'Journal Entry') ?></strong></td>
                  <td class="text-end">
                    <span class="badge bg-success fs-6">PKR <?= number_format((float)($e['total_debits'] ?? 0), 2) ?></span>
                  </td>
                  <td class="text-end">
                    <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('accounting/journals/receipt/' . (int)$e['id']) ?>" target="_blank">
                      <i class="bi bi-receipt"></i> View
                    </a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Account Search JavaScript -->
<script>
// Convert number to words (supports up to billions)
function numberToWords(num) {
  if (num === 0) return 'Zero';
  
  const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
  const teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
  const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
  
  function convertLessThanThousand(n) {
    if (n === 0) return '';
    if (n < 10) return ones[n];
    if (n < 20) return teens[n - 10];
    if (n < 100) {
      const ten = Math.floor(n / 10);
      const one = n % 10;
      return tens[ten] + (one > 0 ? ' ' + ones[one] : '');
    }
    const hundred = Math.floor(n / 100);
    const remainder = n % 100;
    return ones[hundred] + ' Hundred' + (remainder > 0 ? ' ' + convertLessThanThousand(remainder) : '');
  }
  
  let intPart = Math.floor(num);
  const decimalPart = Math.round((num - intPart) * 100);
  
  let result = '';
  
  if (intPart >= 1000000000) {
    const billions = Math.floor(intPart / 1000000000);
    result += convertLessThanThousand(billions) + ' Billion ';
    intPart %= 1000000000;
  }
  if (intPart >= 1000000) {
    const millions = Math.floor(intPart / 1000000);
    result += convertLessThanThousand(millions) + ' Million ';
    intPart %= 1000000;
  }
  if (intPart >= 1000) {
    const thousands = Math.floor(intPart / 1000);
    result += convertLessThanThousand(thousands) + ' Thousand ';
    intPart %= 1000;
  }
  if (intPart > 0) {
    result += convertLessThanThousand(intPart);
  }
  
  result = result.trim() + ' Rupees';
  
  if (decimalPart > 0) {
    result += ' and ' + convertLessThanThousand(decimalPart) + ' Paisa';
  }
  
  return result + ' Only';
}

// Full accounts master list (never mutated after initial load)
let fullAccounts = <?= json_encode($accounts ?? []) ?>;
// Working filtered set (used for current dropdown render)
let accounts = fullAccounts.slice();
let currentFocus = -1;

// Multi-line builder
function createAccountCell(rowIdx) {
  const wrapper = document.createElement('div');
  wrapper.className = 'position-relative';
  const searchId = `accSearch_${rowIdx}`;
  const hiddenId = `accHidden_${rowIdx}`;
  const dropdownId = `accDropdown_${rowIdx}`;
  wrapper.innerHTML = `
    <input type="text" id="${searchId}" class="form-control form-control-sm" placeholder="Type account name or code..." autocomplete="off">
    <input type="hidden" name="lines[${rowIdx}][account_id]" id="${hiddenId}" required>
    <div id="${dropdownId}" class="dropdown-menu acc-dropdown-menu"></div>
  `;
  return wrapper;
}

function escapeHtml(str) {
  return (str ?? '').toString()
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function highlightTokens(text, tokens) {
  const safe = escapeHtml(text);
  if (!tokens || tokens.length === 0) return safe;

  // Highlight each token (non-overlapping best-effort).
  let out = safe;
  for (const t of tokens) {
    if (!t) continue;
    const re = new RegExp('(' + t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
    out = out.replace(re, '<mark class="acc-mark">$1</mark>');
  }
  return out;
}

function positionAccountDropdown(dropdown, inputEl) {
  if (!dropdown || !inputEl) return;

  // Use a FIXED overlay so it doesn't get clipped by `.table-responsive`
  // overflow rules (common bootstrap issue).
  const maxDesired = 320;
  const rect = inputEl.getBoundingClientRect();
  const viewportW = window.innerWidth || document.documentElement.clientWidth;
  const viewportH = window.innerHeight || document.documentElement.clientHeight;

  const spaceBelow = viewportH - rect.bottom;
  const spaceAbove = rect.top;
  const openUp = spaceBelow < 220 && spaceAbove > spaceBelow;
  const usable = Math.max(160, Math.min(maxDesired, (openUp ? spaceAbove : spaceBelow) - 12));

  // Horizontal clamp (also cap width so it doesn't cover the whole row)
  const maxWidth = Math.min(520, viewportW - 16);
  const width = Math.max(260, Math.min(rect.width, maxWidth));
  let left = rect.left;
  if (left + width > viewportW - 8) left = Math.max(8, viewportW - width - 8);
  if (left < 8) left = 8;

  dropdown.style.position = 'fixed';
  dropdown.style.left = left + 'px';
  dropdown.style.width = width + 'px';
  dropdown.style.right = 'auto';
  dropdown.style.maxHeight = usable + 'px';
  dropdown.style.marginTop = '0';
  dropdown.style.marginBottom = '0';

  if (openUp) {
    dropdown.style.top = 'auto';
    dropdown.style.bottom = (viewportH - rect.top) + 'px';
  } else {
    dropdown.style.bottom = 'auto';
    dropdown.style.top = rect.bottom + 'px';
  }
}

// Ensure accounts loaded (fallback to AJAX when PHP didn't inject or list is empty)
let accountsLoaded = false;
let accountsFetchTimer = null;
async function ensureAccountsLoaded(q = '') {
  const query = (q || '').trim();
  const statusEl = document.getElementById('accountStatus');
  if (query.length > 0) {
    try {
      statusEl && (statusEl.textContent = 'Searching…');
      const url = '<?= base_url('accounting/journal-lite/accounts') ?>' + '?q=' + encodeURIComponent(query);
      const res = await fetch(url);
      const data = await res.json();
      if (data && data.success && Array.isArray(data.data)) {
        accounts = data.data; // filtered subset only
        accountsLoaded = true;
        statusEl && (statusEl.textContent = 'Matches: ' + accounts.length);
      } else {
        statusEl && (statusEl.textContent = 'Search failed');
      }
    } catch (e) { console.warn('Account search failed', e); statusEl && (statusEl.textContent = 'Search error'); }
    return;
  }
  // Initial full load if master list empty
  if (!accountsLoaded || !Array.isArray(fullAccounts) || fullAccounts.length === 0) {
    try {
      statusEl && (statusEl.textContent = 'Loading…');
      const res = await fetch('<?= base_url('accounting/journal-lite/accounts') ?>');
      const data = await res.json();
      if (data && data.success && Array.isArray(data.data)) {
        fullAccounts = data.data;
        accounts = fullAccounts.slice();
        accountsLoaded = true;
        statusEl && (statusEl.textContent = 'Accounts: ' + fullAccounts.length);
      } else {
        statusEl && (statusEl.textContent = 'Load failed');
      }
    } catch (e) { console.warn('Account load failed', e); statusEl && (statusEl.textContent = 'Load error'); }
  } else {
    accounts = fullAccounts.slice();
    statusEl && (statusEl.textContent = 'Accounts: ' + fullAccounts.length);
  }
}

function setupSearchForIds(searchId, hiddenId, dropdownId) {
  const searchInput = document.getElementById(searchId);
  const hiddenInput = document.getElementById(hiddenId);
  const dropdown = document.getElementById(dropdownId);
  
  if (!searchInput || !hiddenInput || !dropdown) {
    console.error('Could not find elements:', searchId, hiddenId, dropdownId);
    return;
  }

  // Keep a link for scroll/resize reposition.
  dropdown.dataset.anchorInputId = searchId;
  
  searchInput.addEventListener('input', async function() {
    const q = this.value;
    // Slight debounce to avoid spamming requests
    if (accountsFetchTimer) clearTimeout(accountsFetchTimer);
    accountsFetchTimer = setTimeout(async () => {
      await ensureAccountsLoaded(q);
      filterAccountsGeneric(q, dropdown, hiddenInput, searchInput);
    }, 150);
  });
  
  searchInput.addEventListener('keydown', function(e){ 
    handleKeyNavigationGeneric(e, dropdown, hiddenInput, searchInput); 
  });
  
  searchInput.addEventListener('focus', async function(){ 
    await ensureAccountsLoaded(this.value);
    filterAccountsGeneric(this.value, dropdown, hiddenInput, searchInput); 
  });
}

// Close any open account dropdown when clicking elsewhere (bound once).
if (!window.__accDropdownOutsideClickBound) {
  window.__accDropdownOutsideClickBound = true;
  document.addEventListener('click', function (e) {
    const open = document.querySelectorAll('.acc-dropdown-menu.show');
    open.forEach(dd => {
      // if click is inside dropdown or its associated input wrapper, do nothing
      if (dd.contains(e.target) || (dd.parentElement && dd.parentElement.contains(e.target))) return;
      dd.classList.remove('show');
      dd.style.display = 'none';
    });
  });
}

// Keep dropdown attached to the input on scroll/resize (bound once).
if (!window.__accDropdownRepositionBound) {
  window.__accDropdownRepositionBound = true;
  const reposition = () => {
    const open = document.querySelectorAll('.acc-dropdown-menu.show');
    open.forEach(dd => {
      const anchorId = dd.dataset.anchorInputId;
      if (!anchorId) return;
      const inputEl = document.getElementById(anchorId);
      if (!inputEl) return;
      positionAccountDropdown(dd, inputEl);
    });
  };
  window.addEventListener('resize', reposition);
  // Capture scroll from nested containers (like `.table-responsive`).
  window.addEventListener('scroll', reposition, true);
}

function filterAccountsGeneric(query, dropdown, hiddenInput, searchInput) {
  const raw = (query || '').toString();
  const q = raw.trim().toLowerCase();
  const tokens = q ? q.split(/\s+/).filter(Boolean) : [];
  const source = (Array.isArray(fullAccounts) && fullAccounts.length > 0) ? fullAccounts : accounts;
  let filtered = source.filter(acc => {
    const code = (acc.code || '').toLowerCase();
    const name = (acc.name || '').toLowerCase();
    if (tokens.length === 0) return true;
    return tokens.every(t => code.includes(t) || name.includes(t));
  });

  // Sort: code prefix match first, then name prefix, then code, then name.
  if (tokens.length > 0) {
    const t0 = tokens[0];
    filtered.sort((a, b) => {
      const ac = (a.code || '').toLowerCase();
      const bc = (b.code || '').toLowerCase();
      const an = (a.name || '').toLowerCase();
      const bn = (b.name || '').toLowerCase();
      const ap = ac.startsWith(t0) ? 0 : (an.startsWith(t0) ? 1 : 2);
      const bp = bc.startsWith(t0) ? 0 : (bn.startsWith(t0) ? 1 : 2);
      if (ap !== bp) return ap - bp;
      if (ac !== bc) return ac.localeCompare(bc);
      return an.localeCompare(bn);
    });
  }

  dropdown.innerHTML = '';
  if (filtered.length === 0) {
    dropdown.innerHTML = '<div class="dropdown-item-text text-muted px-3 py-2">No accounts found</div>';
  } else {
    const limit = 60;
    const total = filtered.length;
    const showing = Math.min(limit, total);

    const head = document.createElement('div');
    head.className = 'acc-dropdown-head';
    head.innerHTML = `<span>Results: <strong>${showing}</strong>${total > showing ? ` of ${total}` : ''}</span><span class="text-muted">Tip: search "1100 bank"</span>`;
    dropdown.appendChild(head);

    filtered.slice(0, limit).forEach(acc => {
      const item = document.createElement('a');
      item.className = 'dropdown-item';
      item.href = '#';
      const code = highlightTokens(acc.code || '', tokens);
      const name = highlightTokens(acc.name || '', tokens);
      const type = escapeHtml(acc.type || '');
      item.innerHTML = `<div class="acc-item"><div class="acc-code">${code}</div><div class="acc-name">${name} <span class="acc-type">(${type})</span></div></div>`;
      item.onclick = function(e){ e.preventDefault(); selectAccountGeneric(acc, hiddenInput, searchInput, dropdown); };
      dropdown.appendChild(item);
    });
    if (filtered.length > limit) {
      const more = document.createElement('div');
      more.className = 'dropdown-item-text text-muted px-3 py-2';
      more.textContent = 'Keep typing to narrow results…';
      dropdown.appendChild(more);
    }
  }

  positionAccountDropdown(dropdown, searchInput);
  dropdown.style.display = 'block';
  dropdown.classList.add('show');
  currentFocus = -1;
}

function handleKeyNavigationGeneric(e, dropdown, hiddenInput, searchInput) {
  const items = dropdown.querySelectorAll('.dropdown-item');
  if (e.key === 'ArrowDown') { e.preventDefault(); currentFocus++; if (currentFocus >= items.length) currentFocus = 0; setActive(items); }
  else if (e.key === 'ArrowUp') { e.preventDefault(); currentFocus--; if (currentFocus < 0) currentFocus = items.length - 1; setActive(items); }
  else if (e.key === 'Enter') {
    e.preventDefault();
    if (items.length > 0) {
      const idx = currentFocus > -1 ? currentFocus : 0;
      items[idx].click();
    }
  }
  else if (e.key === 'Escape') { dropdown.classList.remove('show'); dropdown.style.display = 'none'; }
}

function setActive(items) { items.forEach((item, index) => { item.classList.toggle('active', index === currentFocus); }); }

function selectAccountGeneric(acc, hiddenInput, searchInput, dropdown){
  hiddenInput.value = acc.id;
  searchInput.value = `${acc.code} — ${acc.name}`;
  dropdown.classList.remove('show');
  dropdown.style.display = 'none';
  // Fire an event so other logic (totals, validation) could hook in later
  searchInput.dispatchEvent(new CustomEvent('account:selected', { detail: acc }));
}

// Helper: find account id by partial name or code (global scope for wizard)
function findAccountId(term){
  try {
    if(!Array.isArray(fullAccounts)) return null;
    term = (term||'').toLowerCase();
    const direct = fullAccounts.find(a => (a.code||'').toLowerCase() === term || (a.name||'').toLowerCase() === term);
    if (direct) return direct.id;
    const contains = fullAccounts.find(a => (a.name||'').toLowerCase().includes(term));
    return contains ? contains.id : null;
  } catch(e){ console.warn('[findAccountId] lookup failed', e); return null; }
}

// Format number with commas and 2 decimal places
function formatAmount(num) {
  if (!num && num !== 0) return '';
  const n = parseFloat(num);
  if (isNaN(n)) return '';
  return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Parse formatted amount back to number (remove commas)
function parseAmount(str) {
  if (!str) return 0;
  return parseFloat(str.toString().replace(/,/g, '')) || 0;
}

// Live-format input while preserving caret position
function formatInputPreserveCaret(el) {
  if (!el) return;
  const raw = el.value;
  // Build unformatted numeric string: remove commas and spaces
  const unformatted = raw.toString().replace(/,/g, '');

  // Count numeric chars (digits and '.') before caret
  const caret = el.selectionStart || 0;
  const before = raw.slice(0, caret).replace(/,/g, '');
  const numericBeforeCount = before.length;

  // Try to parse as number, but preserve trailing dot or decimals the user typed
  const m = unformatted.match(/^([0-9]*)(\.?)([0-9]*)$/);
  let formatted;
  if (m) {
    const intPart = m[1] || '';
    const dot = m[2] || '';
    const decPart = m[3] || '';
    const intNum = intPart === '' ? 0 : parseInt(intPart, 10);
    // Format integer part with commas
    const intFormatted = intPart === '' ? '' : intNum.toLocaleString('en-US');
    if (dot) {
      // User has typed a decimal point – keep decimals as typed (no rounding)
      formatted = (intFormatted === '' ? '0' : intFormatted) + '.' + decPart;
    } else {
      // No decimal point typed – standard format with 2 decimals on blur only
      formatted = intFormatted;
    }
  } else {
    // Fallback to simple formatting
    const num = parseFloat(unformatted) || 0;
    formatted = num.toLocaleString('en-US');
  }

  // Set the formatted value and restore caret at equivalent numeric position
  el.value = formatted;

  // Restore caret: move to the position where numericBeforeCount characters have been seen
  let seen = 0; let pos = 0;
  const s = formatted.toString();
  while (pos < s.length && seen < numericBeforeCount) {
    // count all characters except commas
    if (s[pos] !== ',') seen++;
    pos++;
  }
  try { el.setSelectionRange(pos, pos); } catch (e) { /* ignore */ }
}

// Setup amount field formatting (commas + decimals)
function setupAmountField(field) {
  if (!field) return;
  
  // Store raw value in a data attribute
  field.addEventListener('focus', function() {
    const raw = parseAmount(this.value);
    if (raw > 0) {
      this.value = raw.toString();
    }
  });

  field.addEventListener('blur', function() {
    const raw = parseAmount(this.value);
    if (raw > 0) {
      this.value = formatAmount(raw);
      // Update the underlying numeric value for form submission
      this.dataset.rawValue = raw.toString();
    } else {
      this.value = '';
      this.dataset.rawValue = '0';
    }
    // Clear any pending formatter timer
    if (this.__formatTimer) { clearTimeout(this.__formatTimer); this.__formatTimer = null; }
  });

  // Live formatting while typing, caret-preserving
  field.addEventListener('input', function() {
    this.dataset.rawValue = parseAmount(this.value).toString();
    formatInputPreserveCaret(this);
  });
}

function addLine(prefill) {
  const body = document.getElementById('linesBody');
  const idx = body.children.length;
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td></td>
    <td><input type="text" name="lines[${idx}][description]" class="form-control form-control-sm" placeholder="Optional"></td>
    <td><input type="text" inputmode="decimal" name="lines[${idx}][debit]" class="form-control form-control-sm text-end debit-field" placeholder="0.00"></td>
    <td><input type="text" inputmode="decimal" name="lines[${idx}][credit]" class="form-control form-control-sm text-end credit-field" placeholder="0.00"></td>
    <td>
      <select name="lines[${idx}][currency]" class="form-select form-select-sm currency-select">
        <option value="PKR" selected>PKR</option>
        <option value="USD">USD</option>
      </select>
    </td>
    <td class="text-end">
      <input type="number" step="0.0001" min="0" name="lines[${idx}][fx_rate]" class="form-control form-control-sm text-end fx-rate-field" value="1" disabled>
    </td>
    <td class="text-end">
      <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); renumberLines();"><i class="bi bi-x"></i></button>
    </td>
  `;
  body.appendChild(tr);

  // account cell must be inserted after in DOM to bind events
  const accountCell = createAccountCell(idx);
  tr.children[0].appendChild(accountCell);
  // Bind search events now that the elements exist in DOM
  setupSearchForIds(`accSearch_${idx}`, `accHidden_${idx}`, `accDropdown_${idx}`);

  // Setup debit/credit mutual exclusion & currency logic
  const debitField = tr.querySelector('.debit-field');
  const creditField = tr.querySelector('.credit-field');
  const currencySelect = tr.querySelector('.currency-select');
  const fxRateField = tr.querySelector('.fx-rate-field');

  // Setup amount formatting for debit/credit fields
  setupAmountField(debitField);
  setupAmountField(creditField);

  debitField.addEventListener('input', function() {
    const raw = parseAmount(this.value);
    if (raw > 0) {
      creditField.disabled = true;
      creditField.value = '';
      creditField.classList.add('text-muted');
    } else {
      creditField.disabled = false;
      creditField.classList.remove('text-muted');
    }
  });

  creditField.addEventListener('input', function() {
    const raw = parseAmount(this.value);
    if (raw > 0) {
      debitField.disabled = true;
      debitField.value = '';
      debitField.classList.add('text-muted');
    } else {
      debitField.disabled = false;
      debitField.classList.remove('text-muted');
    }
  });

  currencySelect.addEventListener('change', function() {
    if (this.value === 'USD') {
      fxRateField.disabled = false;
      if (!fxRateField.value || parseFloat(fxRateField.value) <= 0) {
        fxRateField.value = '';
        fxRateField.placeholder = 'Rate';
      }
    } else {
      fxRateField.disabled = true;
      fxRateField.value = '1';
    }
    updateTotalsStatus();
    updateForeignPkREquivalents();
  });
  fxRateField.addEventListener('input', () => { updateTotalsStatus(); updateForeignPkREquivalents(); });

  // Update totals on change
  const recalc = () => updateTotalsStatus();
  debitField.addEventListener('input', recalc);
  creditField.addEventListener('input', recalc);

  if (prefill) {
    if (prefill.description) tr.querySelector(`input[name='lines[${idx}][description]']`).value = prefill.description;
    if (prefill.debit) {
      const debitInput = tr.querySelector(`input[name='lines[${idx}][debit]']`);
      debitInput.value = formatAmount(prefill.debit);
      debitInput.dispatchEvent(new Event('input'));
    }
    if (prefill.credit) {
      const creditInput = tr.querySelector(`input[name='lines[${idx}][credit]']`);
      creditInput.value = formatAmount(prefill.credit);
      creditInput.dispatchEvent(new Event('input'));
    }
    if (prefill.currency) currencySelect.value = prefill.currency;
    if (currencySelect.value === 'USD') fxRateField.disabled = false;
    if (prefill.fx_rate && currencySelect.value === 'USD') {
      fxRateField.value = prefill.fx_rate;
    }
    if (prefill.account_id) {
      const accObj = Array.isArray(fullAccounts) ? fullAccounts.find(a => a.id == prefill.account_id) : null;
      if (accObj) {
        const hiddenInput = tr.querySelector(`input[type='hidden'][name='lines[${idx}][account_id]']`);
        const searchInput = tr.querySelector(`#accSearch_${idx}`);
        if (hiddenInput && searchInput) {
          hiddenInput.value = accObj.id;
          searchInput.value = `${accObj.code} — ${accObj.name}`;
        }
      }
    }
  }
  updateForeignPkREquivalents();
}

function renumberLines(){
  const body = document.getElementById('linesBody');
  const rows = Array.from(body.children);
  rows.forEach((tr, idx) => {
    // Update names/ids to keep indices sequential
    const hidden = tr.querySelector('input[type="hidden"][name$="[account_id]"]');
    const search = tr.querySelector(`#accSearch_${idx}`) || tr.querySelector('input[type="text"]');
    const desc = tr.querySelectorAll('input[type="text"]')[1];
    const debit = tr.querySelector('input[name$="[debit]"]');
    const credit = tr.querySelector('input[name$="[credit]"]');
    const currency = tr.querySelector('select');
    const fxRate = tr.querySelector('input[name$="[fx_rate]"]');
    
    // Store current values
    const debitValue = debit.value;
    const creditValue = credit.value;
    const descValue = desc.value;
    const currencyValue = currency.value;
    const accountIdValue = hidden ? hidden.value : '';
    const accountSearchText = search ? search.value : '';
    
    // Rebuild account cell
    tr.children[0].innerHTML = '';
  const accountCell = createAccountCell(idx);
  tr.children[0].appendChild(accountCell);
  // Re-bind search events for rebuilt row
  setupSearchForIds(`accSearch_${idx}`, `accHidden_${idx}`, `accDropdown_${idx}`);
  // Restore account selection if existed
  if (accountIdValue) {
    const newHidden = tr.querySelector(`input[type='hidden'][name='lines[${idx}][account_id]']`);
    const newSearch = document.getElementById(`accSearch_${idx}`);
    if (newHidden) newHidden.value = accountIdValue;
    if (newSearch && accountSearchText) newSearch.value = accountSearchText;
  }
    
  // Reassign other fields
    desc.name = `lines[${idx}][description]`;
    debit.name = `lines[${idx}][debit]`;
    credit.name = `lines[${idx}][credit]`;
    currency.name = `lines[${idx}][currency]`;
  if (fxRate) fxRate.name = `lines[${idx}][fx_rate]`;
    
    // Add CSS classes back for debit/credit logic
    debit.classList.add('debit-field');
    credit.classList.add('credit-field');
    
    // Restore values
    desc.value = descValue;
    debit.value = debitValue;
    credit.value = creditValue;
    currency.value = currencyValue;
    if (fxRate) {
      if (currencyValue === 'USD') {
        fxRate.disabled = false;
        if (!fxRate.value || parseFloat(fxRate.value) <= 0) fxRate.value = '';
      } else {
        fxRate.disabled = true;
        fxRate.value = '1';
      }
    }
    
    // Re-setup debit/credit mutual exclusion
    debit.addEventListener('input', function() {
      if (this.value && parseFloat(this.value) > 0) {
        credit.disabled = true;
        credit.value = '';
        credit.classList.add('text-muted');
      } else {
        credit.disabled = false;
        credit.classList.remove('text-muted');
      }
    });
    
    credit.addEventListener('input', function() {
      if (this.value && parseFloat(this.value) > 0) {
        debit.disabled = true;
        debit.value = '';
        debit.classList.add('text-muted');
      } else {
        debit.disabled = false;
        debit.classList.remove('text-muted');
      }
    });
  // Rebind totals updater
    const recalc = () => updateTotalsStatus();
    debit.addEventListener('input', recalc);
    credit.addEventListener('input', recalc);
  currency.addEventListener('change', recalc);
  if (fxRate) fxRate.addEventListener('input', recalc);
  if (fxRate) fxRate.addEventListener('input', updateForeignPkREquivalents);
    
    // Trigger logic if fields have values
    if (debitValue) debit.dispatchEvent(new Event('input'));
    if (creditValue) credit.dispatchEvent(new Event('input'));
  });
  enforceFxRates();
  // After renumber ensure each amount line with a value has an account_id; attempt intelligent auto-fill if missing
  autoAssignMissingAccounts();
}

// Calculate totals and show/hide red error when not balanced
function updateTotalsStatus() {
  const body = document.getElementById('linesBody');
  let td = 0.0, tc = 0.0; let fxError = false;
  body.querySelectorAll('#linesBody tr').forEach(tr => {
    const debitInput = tr.querySelector('input[name$="[debit]"]');
    const creditInput = tr.querySelector('input[name$="[credit]"]');
    const currency = tr.querySelector('select[name$="[currency]"]');
    const fxRateInput = tr.querySelector('input[name$="[fx_rate]"]');
    
    // Use parseAmount to handle formatted values with commas
    const debitRaw = parseAmount(debitInput?.value || '0');
    const creditRaw = parseAmount(creditInput?.value || '0');
    const cur = currency?.value || 'PKR';
    let fx = 1;
    if (cur === 'USD') {
      fx = parseFloat(fxRateInput?.value || '0') || 0;
      if (fx <= 0) fxError = true;
    }
    const debitPKR = cur === 'USD' ? debitRaw * fx : debitRaw;
    const creditPKR = cur === 'USD' ? creditRaw * fx : creditRaw;
    td += debitPKR; tc += creditPKR;
  });
  const err = document.getElementById('balanceError');
  const totalsBar = document.getElementById('totalsBar');
  const balanced = Math.abs(td - tc) <= 0.01 && !fxError;
  if (!balanced) {
    err.style.display = '';
    if (fxError) {
      err.textContent = 'Provide FX rate for all USD lines (realized bank advice rate incl. charges).';
    } else {
      err.textContent = `Debits (PKR ${formatAmount(td)}) and Credits (PKR ${formatAmount(tc)}) must be equal before posting.`;
    }
  } else {
    err.style.display = 'none';
    err.textContent = '';
  }
  if (totalsBar) {
    totalsBar.innerHTML = `Debits (PKR): <strong>${formatAmount(td)}</strong> | Credits (PKR): <strong>${formatAmount(tc)}</strong> <span class="ms-3 status-text ${balanced ? 'text-success' : 'text-danger'}">${balanced ? 'Balanced' : 'Not Balanced'}</span>`;
  }
  
  // Update amount in words (use debit total as it should equal credit when balanced)
  const wordsElement = document.getElementById('amount_in_words');
  if (wordsElement) {
    if (td > 0 && balanced) {
      wordsElement.innerText = numberToWords(td);
    } else {
      wordsElement.innerText = '';
    }
  }
  
  updateForeignPkREquivalents();
  return balanced;
}

// Attempt to auto assign accounts for lines with monetary values but no account selected
function autoAssignMissingAccounts(){
  if(!Array.isArray(fullAccounts) || fullAccounts.length===0) return;
  document.querySelectorAll('#linesBody tr').forEach(tr => {
    const hidden = tr.querySelector('input[type="hidden"][name$="[account_id]"]');
    const debit = parseFloat(tr.querySelector('input[name$="[debit]"]')?.value||'0');
    const credit = parseFloat(tr.querySelector('input[name$="[credit]"]')?.value||'0');
    const descInput = tr.querySelector('input[name$="[description]"]');
    if (!hidden || hidden.value) return; // already selected or no field
    const hasAmount = debit>0 || credit>0;
    if (!hasAmount) return;
    const descText = (descInput?.value||'').toLowerCase();
    let pickedId = null;
    // Heuristics based on description
    if (descText.includes('revenue')) pickedId = findAccountId('sales revenue') || findAccountId('revenue');
    else if (descText.includes('deposit') || descText.includes('bank')) pickedId = findAccountId('cash') || findAccountId('bank');
    else if (descText.includes('fee')) pickedId = findAccountId('bank fees') || findAccountId('fees');
    else if (descText.includes('gain')) pickedId = findAccountId('exchange gain');
    else if (descText.includes('loss')) pickedId = findAccountId('exchange loss');
    // Fallback by type using amount side
    if (!pickedId) {
      if (debit>0) pickedId = findAccountByType('asset') || findAccountByType('expense');
      if (!pickedId && credit>0) pickedId = findAccountByType('revenue') || findAccountByType('liability');
    }
    if (pickedId) {
      hidden.value = pickedId;
      const accObj = fullAccounts.find(a => a.id==pickedId);
      const searchInput = tr.querySelector('input[id^="accSearch_"]');
      if (accObj && searchInput) searchInput.value = `${accObj.code} — ${accObj.name}`;
    }
  });
}

// Show PKR equivalent as a tooltip on FX Rate field (non-intrusive)
function updateForeignPkREquivalents(){
  document.querySelectorAll('#linesBody tr').forEach(tr => {
    const currency = tr.querySelector('select[name$="[currency]"]');
    const fxRateInput = tr.querySelector('input[name$="[fx_rate]"]');
    const debitInput = tr.querySelector('input[name$="[debit]"]');
    const creditInput = tr.querySelector('input[name$="[credit]"]');
    if (!currency || !fxRateInput) return;
    // Remove tooltip for PKR lines
    if (currency.value === 'PKR') {
      const inst = window.bootstrap ? window.bootstrap.Tooltip.getInstance(fxRateInput) : null;
      if (inst) inst.dispose();
      fxRateInput.removeAttribute('title');
      fxRateInput.removeAttribute('data-bs-toggle');
      return;
    }
    const fx = parseFloat(fxRateInput.value || '1') || 1;
    const amt = parseFloat(debitInput?.value || creditInput?.value || '0') || 0;
    const pkr = amt * fx;
    const formatted = pkr.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    const fxDisplay = fx.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    const title = `≈ PKR ${formatted} @ ${fxDisplay}`;
    fxRateInput.setAttribute('title', title);
    fxRateInput.setAttribute('data-bs-toggle', 'tooltip');
    fxRateInput.setAttribute('data-bs-placement', 'top');
    if (window.bootstrap && window.bootstrap.Tooltip) {
      let inst = window.bootstrap.Tooltip.getInstance(fxRateInput);
      if (inst) { inst.setContent({'.tooltip-inner': title}); }
      else { inst = new window.bootstrap.Tooltip(fxRateInput, {container: 'body'}); }
    }
  });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+S to save
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        document.getElementById('journalForm').submit();
    }
    
    // Escape to clear form
    if (e.key === 'Escape') {
        clearForm();
    }
    
  // Enter to submit (avoid when typing in dropdown searches)
  if (e.key === 'Enter' && !e.target.closest('#linesTable')) {
        e.preventDefault();
        if (updateTotalsStatus()) {
          document.getElementById('journalForm').submit();
        } else {
          // Focus the first amount field if not balanced
          const firstAmt = document.querySelector('#linesBody input[name$="[debit]"], #linesBody input[name$="[credit]"]');
          if (firstAmt) firstAmt.focus();
        }
    }
});

function clearForm() {
    document.getElementById('journalForm').reset();
    document.getElementById('entryDate').value = new Date().toISOString().split('T')[0];
  document.getElementById('linesBody').innerHTML = '';
  // Pre-add two helpful rows
  addLine();
  addLine();
    document.getElementById('entryDate').focus();
}



// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
  // Seed with two rows for convenience
  addLine(); // e.g., Bank (Debit)
  addLine(); // e.g., Revenue/AR (Credit)
  document.getElementById('entryDate').focus();
  // First totals compute and ensure accounts present
  updateTotalsStatus();
  // Prefetch accounts once for immediate availability
  ensureAccountsLoaded('');
  // Visual row focus highlight
  document.getElementById('linesBody').addEventListener('focusin', function(e){
    const tr = e.target.closest('tr');
    if (!tr) return;
    this.querySelectorAll('tr').forEach(r => r.classList.remove('active-row'));
    tr.classList.add('active-row');
  });
  
  // Form-level guard: prevent submit unless balanced and at least one valid line
  const form = document.getElementById('journalForm');
  form.addEventListener('submit', function(e){
    // Convert formatted amounts back to plain numbers before submission
    document.querySelectorAll('#linesBody tr').forEach(tr => {
      const debitField = tr.querySelector('input[name$="[debit]"]');
      const creditField = tr.querySelector('input[name$="[credit]"]');
      if (debitField) {
        const raw = parseAmount(debitField.value);
        debitField.value = raw > 0 ? raw.toString() : '';
      }
      if (creditField) {
        const raw = parseAmount(creditField.value);
        creditField.value = raw > 0 ? raw.toString() : '';
      }
    });
    
    // Basic per-line validation: either debit or credit, and account must be selected when amount > 0
    const rows = Array.from(document.querySelectorAll('#linesBody tr'));
    let anyLine = false; let lineError = '';
    rows.forEach((tr, i) => {
      const debit = parseAmount(tr.querySelector('input[name$="[debit]"]').value || '0');
      const credit = parseAmount(tr.querySelector('input[name$="[credit]"]').value || '0');
      const acc = tr.querySelector('input[type="hidden"][name^="lines["]')?.value || '';
      if ((debit > 0 || credit > 0)) {
        anyLine = true;
        if (debit > 0 && credit > 0) lineError = `Row #${i+1}: Provide either debit or credit, not both`;
        if (!acc) lineError = `Row #${i+1}: Select an account for the amount entered`;
      }
    });
    if (lineError) {
      e.preventDefault();
      const err = document.getElementById('balanceError');
      err.style.display = '';
      err.textContent = lineError;
      return;
    }
    if (!anyLine) {
      e.preventDefault();
      const err = document.getElementById('balanceError');
      err.style.display = '';
      err.textContent = 'Add at least one line with a debit or credit amount.';
      return;
    }
    if (!updateTotalsStatus()) {
      e.preventDefault();
    }
  });
  // Attach modal close on ESC
  document.addEventListener('keydown', function(ev){ if(ev.key==='Escape'){ closeUsdSaleModal(); } });
});

// USD Sale Wizard logic
function openUsdSaleModal(){ const m=document.getElementById('usdSaleModal'); if(m){ m.style.display='block'; } }
function closeUsdSaleModal(){ const m=document.getElementById('usdSaleModal'); if(m){ m.style.display='none'; } }
async function buildUsdSale(){
  const amt = parseFloat(document.getElementById('usdAmount')?.value||'0');
  const spot = parseFloat(document.getElementById('usdSpotRate')?.value||'0');
  const settle = parseFloat(document.getElementById('usdSettlementRate')?.value||spot);
  const fee = parseFloat(document.getElementById('usdBankFee')?.value||'0');
  const feeCur = document.getElementById('usdFeeCurrency')?.value||'PKR';
  if (amt<=0 || spot<=0 || settle<=0){ alert('Enter USD amount, spot and settlement rates (>0).'); return; }
  // Ensure accounts loaded before lookup (async safe)
  if ((!Array.isArray(fullAccounts) || fullAccounts.length === 0) || !accountsLoaded) {
    console.log('[USD Wizard] Accounts not loaded; fetching before building lines');
    await ensureAccountsLoaded('');
  }
  const feePKR = feeCur==='USD'? fee*settle : fee;
  const grossPKR = amt*spot;
  const settlementPKR = amt*settle;
  const netBankPKR = settlementPKR - feePKR;
  const diff = settlementPKR - grossPKR; // gain if >0
  window.lastSpotRate = spot; // remember for auto-fill
  // Remove empty placeholder rows (no debit/credit and no account)
  Array.from(document.querySelectorAll('#linesBody tr')).forEach(tr => {
    const d = parseFloat(tr.querySelector('input[name$="[debit]"]')?.value||'0');
    const c = parseFloat(tr.querySelector('input[name$="[credit]"]')?.value||'0');
    const acc = tr.querySelector('input[type="hidden"]')?.value||'';
    if (d===0 && c===0 && !acc) tr.remove();
  });
  // Build lines
  let revId=null, bankId=null, feeId=null, gainId=null, lossId=null;
  try {
    revId = findAccountId('sales revenue') || findAccountId('revenue');
    bankId = findAccountId('cash') || findAccountId('bank');
    feeId = findAccountId('bank fees') || findAccountId('fees');
    gainId = findAccountId('exchange gain');
    lossId = findAccountId('exchange loss');
  } catch(e){ console.warn('[USD Wizard] Account lookup failed', e); }
  addLine({description:'USD Sale Revenue', credit: amt, currency:'USD', fx_rate: spot, account_id: revId});
  addLine({description:'Bank Deposit', debit: parseFloat(netBankPKR.toFixed(2)), currency:'PKR', account_id: bankId});
  if (feePKR>0){ addLine({description:'Bank Fees', debit: parseFloat(feePKR.toFixed(2)), currency:'PKR', account_id: feeId}); }
  if (Math.abs(diff) > 0.01){
     if (diff>0){ addLine({description:'Exchange Gain', credit: parseFloat(diff.toFixed(2)), currency:'PKR', account_id: gainId}); }
     else { addLine({description:'Exchange Loss', debit: parseFloat(Math.abs(diff).toFixed(2)), currency:'PKR', account_id: lossId}); }
  }
  console.log('[USD Wizard] Lines inserted', {amt, spot, settle, feePKR, netBankPKR, diff});
  // Force re-number to ensure indices sequential after removals
  renumberLines();
  updateForeignPkREquivalents();
  // Alert summary
  alert('Lines generated:\nRevenue (USD '+amt+') @ '+spot+' = PKR '+grossPKR.toFixed(2)+'\nBank Deposit: PKR '+netBankPKR.toFixed(2)+'\nFee PKR: '+feePKR.toFixed(2)+'\nFX '+(diff>=0?'Gain':'Loss')+': '+Math.abs(diff).toFixed(2));
  closeUsdSaleModal(); updateTotalsStatus();
}

// Auto-fill missing FX rates for foreign currency lines to prevent posting errors
function enforceFxRates(){
  const rows = document.querySelectorAll('#linesBody tr');
  let lastSpot = window.lastSpotRate || null;
  rows.forEach(r => {
    const curSel = r.querySelector('select[name$="[currency]"]');
    const fxInput = r.querySelector('input[name$="[fx_rate]"]');
    if (!curSel || !fxInput) return;
    if (curSel.value !== 'PKR') {
      if (!fxInput.value || parseFloat(fxInput.value) <= 0) {
        if (lastSpot) {
          fxInput.value = lastSpot;
        } else {
          fxInput.value = '1';
        }
        fxInput.disabled = false;
      }
    }
  });
}
document.addEventListener('click', function(e){ const m=document.getElementById('usdSaleModal'); if(m && e.target===m){ closeUsdSaleModal(); } });
</script>

<!-- USD Sale Wizard Modal -->
<div id="usdSaleModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:5000;">
  <div class="modal-dialog" style="max-width:560px; margin:60px auto;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-currency-dollar me-1"></i>USD Sale Wizard</h5>
        <button type="button" class="btn-close" onclick="closeUsdSaleModal()"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">USD Amount *</label>
            <input type="number" step="0.01" min="0" id="usdAmount" class="form-control" placeholder="1000">
          </div>
          <div class="col-md-6">
            <label class="form-label">Spot Rate (PKR/USD) *</label>
            <input type="number" step="0.0001" min="0" id="usdSpotRate" class="form-control" placeholder="270">
          </div>
          <div class="col-md-6">
            <label class="form-label">Settlement Rate (Bank) *</label>
            <input type="number" step="0.0001" min="0" id="usdSettlementRate" class="form-control" placeholder="269.50">
            <small class="text-muted">Rate actually credited by bank.</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">Bank Fee</label>
            <div class="input-group">
              <input type="number" step="0.01" min="0" id="usdBankFee" class="form-control" placeholder="25">
              <select id="usdFeeCurrency" class="form-select" style="max-width:120px;">
                <option value="PKR">PKR</option>
                <option value="USD">USD</option>
              </select>
            </div>
            <small class="text-muted">If fee is USD it converts using settlement rate.</small>
          </div>
          <div class="col-12">
            <div class="alert alert-info py-2 small mb-0">
              <strong>Posting Logic:</strong> Revenue = USD * Spot. Bank = USD * Settlement - Fee. Difference is Exchange Gain/Loss. Fee posted separately.
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" onclick="closeUsdSaleModal()">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="buildUsdSale()"><i class="bi bi-magic"></i> Generate Lines</button>
      </div>
    </div>
  </div>
</div>

<style>
.dropdown-item:hover, .dropdown-item.active {
    background-color: var(--bs-primary) !important;
    color: white !important;
}

/*
  IMPORTANT:
  Do not globally override `.dropdown-menu` here because it affects the
  navbar dropdowns from the main layout.
  We only style the Journal Lite account dropdown via `.acc-dropdown-menu`.
*/
.acc-dropdown-menu {
  display: none;
  position: fixed;
  left: 0;
  top: 0;
  width: auto;
  min-width: 260px;
  max-height: 320px;
  overflow-y: auto;
  /* Above headers/inputs, but below Bootstrap modals */
  z-index: 1040;
  background: var(--bs-body-bg);
}
.acc-dropdown-menu.show { display: block; }

.acc-dropdown-head {
  position: sticky;
  top: 0;
  z-index: 1;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 6px 12px;
  background: var(--bs-body-bg);
  border-bottom: 1px solid rgba(0,0,0,0.08);
  font-size: 0.8rem;
}

.acc-item { display: flex; flex-direction: column; line-height: 1.1; }
.acc-code { font-weight: 700; color: #0d6efd; }
.acc-name { font-size: 0.85rem; }
.acc-type { font-size: 0.75rem; color: #6c757d; }
.acc-mark {
  padding: 0 2px;
  border-radius: 3px;
  background: rgba(255, 193, 7, 0.35);
  color: inherit;
}

.form-control-lg {
    font-size: 1.1rem;
    padding: 0.75rem 1rem;
}

.form-control-sm, .form-select-sm {
  font-size: 0.95rem;
}

/* Disabled field styling */
.form-control:disabled {
    background-color: #f8f9fa !important;
    opacity: 0.6;
}

.text-muted.form-control {
    background-color: #f8f9fa !important;
    color: #6c757d !important;
}

.section-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.section-accent-green {
    background: linear-gradient(135deg, #10b981, #059669);
}

.section-accent-amber {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

kbd {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 3px;
    padding: 2px 6px;
    font-size: 0.85em;
}

/* Totals bar styling */
.totals-bar {
  font-size: 0.85rem;
  background: linear-gradient(90deg,#f8fafc,#f1f5f9);
  border: 1px solid #e2e8f0 !important;
  border-radius: 20px;
}

/* (PKR equivalent now shown as a tooltip on FX Rate field; no extra CSS needed) */

/* Active row highlight */
#linesBody tr.active-row {
  outline: 2px solid #0d6efd20;
  background: #f8fbff;
}

#linesBody tr.active-row input:focus {
  box-shadow: none;
  border-color: #0d6efd;
}

/* Zebra striping */
#linesBody tr:nth-child(even) { background: #fcfcfc; }

/* Dropdown improvements */
#linesBody .acc-dropdown-menu {
  border-radius: 8px;
  box-shadow: 0 8px 24px -4px rgba(0,0,0,0.15);
  border: 1px solid #e2e8f0;
}

#linesBody .dropdown-item {
  font-size: 0.85rem;
  padding: 6px 12px;
}

#linesBody .dropdown-item strong { color: #0d6efd; }
</style>

<?= $this->endSection() ?>