<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
helper('url');
$isEdit = isset($customer);
$meta = $isEdit && !empty($customer['metadata']) ? json_decode($customer['metadata'], true) : [];

$formAddresses = [];
if ($isEdit && !empty($customer['__addresses']) && is_array($customer['__addresses'])) {
    $formAddresses = $customer['__addresses'];
}
if (empty($formAddresses)) {
    $formAddresses[] = [
        'id' => null,
        'label' => '',
        'line1' => '',
        'line2' => '',
        'country_id' => null,
        'state_id' => null,
        'city_id' => null,
        'postal_code' => '',
        'is_billing' => 0,
        'is_shipping' => 1,
        'is_default' => 1,
    ];
}
$defaultAddrIdx = 0;
foreach ($formAddresses as $i => $a) {
    if (!empty($a['is_default'])) {
        $defaultAddrIdx = (int)$i;
        break;
    }
}

$countryList = [];
if (!empty($countries) && is_array($countries)) {
    foreach ($countries as $c) {
        $countryList[] = ['id' => (int)($c['id'] ?? 0), 'name' => (string)($c['name'] ?? '')];
    }
}

$emailFieldValue = old('email');
if ($emailFieldValue === null) {
  $emailFieldValue = $isEdit ? (string)($customer['email'] ?? '') : '';
  if ($emailFieldValue !== '' && !filter_var($emailFieldValue, FILTER_VALIDATE_EMAIL)) {
    // Legacy invalid values should not block edit form submission.
    $emailFieldValue = '';
  }
}

$websiteFieldValue = old('website');
if ($websiteFieldValue === null) {
  $websiteFieldValue = $isEdit ? (string)($customer['website'] ?? '') : '';
  if ($websiteFieldValue !== '' && !filter_var($websiteFieldValue, FILTER_VALIDATE_URL)) {
    // Legacy invalid values should not block edit form submission.
    $websiteFieldValue = '';
  }
}
?>

<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1><?= $isEdit ? 'Edit Customer' : 'Add Customer' ?></h1>
    <a href="<?= site_url('customers') ?>" class="btn btn-secondary">Back to List</a>
  </div>

  <?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= session()->getFlashdata('error') ?></div>
  <?php endif; ?>
  <?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
  <?php endif; ?>

  <form id="customerForm" method="post" action="<?= $isEdit ? site_url('customers/' . entityRouteIdentifier($customer) . '/edit') : site_url('customers/create') ?>">
    <?= csrf_field() ?>
    <?php if (!$isEdit && !empty($form_submit_token)): ?>
      <input type="hidden" name="_form_submit_token" value="<?= esc($form_submit_token) ?>">
    <?php endif; ?>

    <div class="card">
      <div class="card-header"><h5 class="mb-0">Customer Information</h5></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Customer Code</label>
            <input type="text" class="form-control" value="<?= esc($isEdit ? ($customer['customer_code'] ?? '') : ($next_customer_code ?? '')) ?>" readonly>
            <div class="form-text"><?= $isEdit ? 'Auto-generated at creation.' : 'Auto-generated on save from next available number.' ?></div>
          </div>
          <div class="col-md-5">
            <label class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required value="<?= old('name') ?? ($isEdit ? esc($customer['name'] ?? '') : '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Company Name</label>
            <input type="text" name="company_name" class="form-control" value="<?= old('company_name') ?? ($isEdit ? esc($customer['company_name'] ?? '') : '') ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Type</label>
            <?php $selType = old('type') ?? ($isEdit ? ($customer['type'] ?? 'retail') : 'retail'); ?>
            <select name="type" class="form-select">
              <option value="retail" <?= $selType === 'retail' ? 'selected' : '' ?>>Retail</option>
              <option value="wholesale" <?= $selType === 'wholesale' ? 'selected' : '' ?>>Wholesale</option>
              <option value="government" <?= $selType === 'government' ? 'selected' : '' ?>>Government</option>
              <option value="partner" <?= $selType === 'partner' ? 'selected' : '' ?>>Partner</option>
              <option value="other" <?= $selType === 'other' ? 'selected' : '' ?>>Other</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <?php $selStatus = old('status') ?? ($isEdit ? ($customer['status'] ?? 'active') : 'active'); ?>
            <select name="status" class="form-select">
              <option value="active" <?= $selStatus === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= $selStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
              <option value="prospect" <?= $selStatus === 'prospect' ? 'selected' : '' ?>>Prospect</option>
              <option value="suspended" <?= $selStatus === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Legacy Number</label>
            <input type="text" name="legacy_number" class="form-control" value="<?= old('legacy_number') ?? ($meta['legacy_number'] ?? '') ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">Primary Email</label>
            <input type="email" name="email" class="form-control" value="<?= esc($emailFieldValue) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" class="form-control" value="<?= old('phone') ?? ($isEdit ? esc($customer['phone'] ?? '') : '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Mobile</label>
            <input type="tel" name="mobile" class="form-control" value="<?= old('mobile') ?? ($isEdit ? esc($customer['mobile'] ?? '') : '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Website</label>
            <input type="url" name="website" class="form-control" value="<?= esc($websiteFieldValue) ?>">
          </div>
          <input type="hidden" name="odoo_id" value="<?= $isEdit ? esc($customer['odoo_id'] ?? '') : '' ?>">
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Addresses</h5>
        <button type="button" id="btnAddAddress" class="btn btn-sm btn-outline-primary">+ Add Address</button>
      </div>
      <div class="card-body p-2" id="addressContainer">
        <?php foreach ($formAddresses as $addrIdx => $addr): ?>
          <div class="address-card border rounded p-3 mb-2"
               data-idx="<?= (int)$addrIdx ?>"
               data-pre-country="<?= esc($addr['country_id'] ?? '') ?>"
               data-pre-state="<?= esc($addr['state_id'] ?? '') ?>"
               data-pre-city="<?= esc($addr['city_id'] ?? '') ?>">
            <input type="hidden" name="addresses[<?= (int)$addrIdx ?>][id]" value="<?= esc($addr['id'] ?? '') ?>">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3 pb-2 border-bottom">
              <div class="form-check mb-0">
                <input type="radio" class="form-check-input addr-default-radio" name="default_address_idx" value="<?= (int)$addrIdx ?>" id="addr_default_<?= (int)$addrIdx ?>" <?= ((int)$addrIdx === $defaultAddrIdx) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="addr_default_<?= (int)$addrIdx ?>">Default</label>
              </div>
              <input type="text" class="form-control form-control-sm" style="width:170px" name="addresses[<?= (int)$addrIdx ?>][label]" placeholder="Label (Home, Office)" value="<?= esc($addr['label'] ?? '') ?>">
              <div class="form-check mb-0 ms-1">
                <input type="checkbox" class="form-check-input" name="addresses[<?= (int)$addrIdx ?>][is_billing]" value="1" id="addr_bill_<?= (int)$addrIdx ?>" <?= !empty($addr['is_billing']) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="addr_bill_<?= (int)$addrIdx ?>">Billing</label>
              </div>
              <div class="form-check mb-0">
                <input type="checkbox" class="form-check-input" name="addresses[<?= (int)$addrIdx ?>][is_shipping]" value="1" id="addr_ship_<?= (int)$addrIdx ?>" <?= !empty($addr['is_shipping']) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="addr_ship_<?= (int)$addrIdx ?>">Shipping</label>
              </div>
              <button type="button" class="btn btn-sm btn-outline-danger ms-auto addr-remove-btn">Remove</button>
            </div>

            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label small">Address Line 1</label>
                <input type="text" class="form-control form-control-sm" name="addresses[<?= (int)$addrIdx ?>][line1]" value="<?= esc($addr['line1'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small">Address Line 2</label>
                <input type="text" class="form-control form-control-sm" name="addresses[<?= (int)$addrIdx ?>][line2]" value="<?= esc($addr['line2'] ?? '') ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small">Country</label>
                <select class="form-select form-select-sm addr-country" name="addresses[<?= (int)$addrIdx ?>][country_id]">
                  <option value="">-- Select country --</option>
                  <?php foreach ($countryList as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (!empty($addr['country_id']) && (int)$addr['country_id'] === (int)$c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small">State / Province</label>
                <select class="form-select form-select-sm addr-state" name="addresses[<?= (int)$addrIdx ?>][state_id]">
                  <option value="">-- Select state --</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small">City</label>
                <select class="form-select form-select-sm addr-city" name="addresses[<?= (int)$addrIdx ?>][city_id]">
                  <option value="">-- Select city --</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small">Postal / ZIP Code</label>
                <input type="text" class="form-control form-control-sm addr-zip" name="addresses[<?= (int)$addrIdx ?>][postal_code]" value="<?= esc($addr['postal_code'] ?? '') ?>">
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div id="deletedAddressIds"></div>

    <div class="card mt-3">
      <div class="card-body text-end">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update Customer' : 'Create Customer' ?></button>
        <a href="<?= site_url('customers') ?>" class="btn btn-secondary ms-2">Cancel</a>
      </div>
    </div>
  </form>
</div>

<script>
(function () {
  const form = document.getElementById('customerForm');
  const container = document.getElementById('addressContainer');
  const deletedIdsWrap = document.getElementById('deletedAddressIds');
  const addBtn = document.getElementById('btnAddAddress');
  const countryOptions = <?= json_encode($countryList) ?>;
  let nextIdx = <?= (int)count($formAddresses) ?>;

  if (form) {
    form.addEventListener('submit', function (e) {
      if (form.dataset.submitting === '1') {
        e.preventDefault();
        return;
      }
      form.dataset.submitting = '1';
      form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (btn) { btn.disabled = true; });
    });

    window.addEventListener('pageshow', function () {
      form.dataset.submitting = '0';
      form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (btn) { btn.disabled = false; });
    });
  }

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
  }

  function countryOptionsHtml() {
    let html = '<option value="">-- Select country --</option>';
    countryOptions.forEach(function (c) {
      html += '<option value="' + c.id + '">' + esc(c.name) + '</option>';
    });
    return html;
  }

  function loadStates(card, countryId, selectedStateId, cb) {
    const stateSel = card.querySelector('.addr-state');
    const citySel = card.querySelector('.addr-city');
    if (!countryId) {
      stateSel.innerHTML = '<option value="">-- Select state --</option>';
      citySel.innerHTML = '<option value="">-- Select city --</option>';
      if (cb) cb();
      return;
    }
    fetch('<?= site_url('customers/states') ?>/' + encodeURIComponent(countryId))
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (rows) {
        stateSel.innerHTML = '<option value="">-- Select state --</option>';
        rows.forEach(function (st) {
          const op = document.createElement('option');
          op.value = st.id;
          op.textContent = st.name;
          if (selectedStateId && String(st.id) === String(selectedStateId)) op.selected = true;
          stateSel.appendChild(op);
        });
        if (cb) cb();
      })
      .catch(function () { if (cb) cb(); });
  }

  function loadCities(card, stateId, selectedCityId, cb) {
    const citySel = card.querySelector('.addr-city');
    if (!stateId) {
      citySel.innerHTML = '<option value="">-- Select city --</option>';
      if (cb) cb();
      return;
    }
    fetch('<?= site_url('customers/cities') ?>/' + encodeURIComponent(stateId))
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (rows) {
        citySel.innerHTML = '<option value="">-- Select city --</option>';
        rows.forEach(function (ct) {
          const op = document.createElement('option');
          op.value = ct.id;
          op.textContent = ct.name;
          if (selectedCityId && String(ct.id) === String(selectedCityId)) op.selected = true;
          citySel.appendChild(op);
        });
        if (cb) cb();
      })
      .catch(function () { if (cb) cb(); });
  }

  function bindCard(card) {
    const countrySel = card.querySelector('.addr-country');
    const stateSel = card.querySelector('.addr-state');
    const zipInput = card.querySelector('.addr-zip');
    const removeBtn = card.querySelector('.addr-remove-btn');

    countrySel.addEventListener('change', function () {
      loadStates(card, this.value, null);
    });

    stateSel.addEventListener('change', function () {
      loadCities(card, this.value, null);
    });

    let zt = null;
    zipInput.addEventListener('input', function () {
      clearTimeout(zt);
      const q = String(zipInput.value || '').trim();
      if (q.length < 2) return;
      zt = setTimeout(function () {
        fetch('<?= site_url('customers/zip-search') ?>?q=' + encodeURIComponent(q))
          .then(function (r) { return r.ok ? r.json() : []; })
          .then(function (rows) {
            if (!rows || !rows.length) return;
            const item = rows[0];
            if (item.country_id) countrySel.value = String(item.country_id);
            loadStates(card, countrySel.value, item.state_id || null, function () {
              loadCities(card, item.state_id || card.querySelector('.addr-state').value, item.city_id || null);
            });
          })
          .catch(function () {});
      }, 300);
    });

    removeBtn.addEventListener('click', function () {
      const cards = container.querySelectorAll('.address-card');
      if (cards.length <= 1) {
        alert('At least one address is required.');
        return;
      }
      const idInput = card.querySelector('input[type="hidden"][name$="[id]"]');
      if (idInput && idInput.value) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'deleted_address_ids[]';
        hidden.value = idInput.value;
        deletedIdsWrap.appendChild(hidden);
      }
      const wasDefault = !!card.querySelector('.addr-default-radio')?.checked;
      card.remove();
      if (wasDefault) {
        const first = container.querySelector('.addr-default-radio');
        if (first) first.checked = true;
      }
    });

    const preCountry = card.getAttribute('data-pre-country');
    const preState = card.getAttribute('data-pre-state');
    const preCity = card.getAttribute('data-pre-city');
    if (preCountry) {
      loadStates(card, preCountry, preState, function () {
        if (preState) loadCities(card, preState, preCity);
      });
    }
  }

  function buildCardHtml(idx) {
    return '' +
      '<div class="address-card border rounded p-3 mb-2" data-idx="' + idx + '" data-pre-country="" data-pre-state="" data-pre-city="">' +
      '<input type="hidden" name="addresses[' + idx + '][id]" value="">' +
      '<div class="d-flex flex-wrap align-items-center gap-2 mb-3 pb-2 border-bottom">' +
      '<div class="form-check mb-0">' +
      '<input type="radio" class="form-check-input addr-default-radio" name="default_address_idx" value="' + idx + '" id="addr_default_' + idx + '">' +
      '<label class="form-check-label small" for="addr_default_' + idx + '">Default</label>' +
      '</div>' +
      '<input type="text" class="form-control form-control-sm" style="width:170px" name="addresses[' + idx + '][label]" placeholder="Label (Home, Office)">' +
      '<div class="form-check mb-0 ms-1">' +
      '<input type="checkbox" class="form-check-input" name="addresses[' + idx + '][is_billing]" value="1" id="addr_bill_' + idx + '">' +
      '<label class="form-check-label small" for="addr_bill_' + idx + '">Billing</label>' +
      '</div>' +
      '<div class="form-check mb-0">' +
      '<input type="checkbox" class="form-check-input" name="addresses[' + idx + '][is_shipping]" value="1" id="addr_ship_' + idx + '" checked>' +
      '<label class="form-check-label small" for="addr_ship_' + idx + '">Shipping</label>' +
      '</div>' +
      '<button type="button" class="btn btn-sm btn-outline-danger ms-auto addr-remove-btn">Remove</button>' +
      '</div>' +
      '<div class="row g-2">' +
      '<div class="col-md-6"><label class="form-label small">Address Line 1</label><input type="text" class="form-control form-control-sm" name="addresses[' + idx + '][line1]"></div>' +
      '<div class="col-md-6"><label class="form-label small">Address Line 2</label><input type="text" class="form-control form-control-sm" name="addresses[' + idx + '][line2]"></div>' +
      '<div class="col-md-4"><label class="form-label small">Country</label><select class="form-select form-select-sm addr-country" name="addresses[' + idx + '][country_id]">' + countryOptionsHtml() + '</select></div>' +
      '<div class="col-md-4"><label class="form-label small">State / Province</label><select class="form-select form-select-sm addr-state" name="addresses[' + idx + '][state_id]"><option value="">-- Select state --</option></select></div>' +
      '<div class="col-md-4"><label class="form-label small">City</label><select class="form-select form-select-sm addr-city" name="addresses[' + idx + '][city_id]"><option value="">-- Select city --</option></select></div>' +
      '<div class="col-md-4"><label class="form-label small">Postal / ZIP Code</label><input type="text" class="form-control form-control-sm addr-zip" name="addresses[' + idx + '][postal_code]"></div>' +
      '</div></div>';
  }

  addBtn.addEventListener('click', function () {
    const idx = nextIdx++;
    container.insertAdjacentHTML('beforeend', buildCardHtml(idx));
    const card = container.querySelector('.address-card[data-idx="' + idx + '"]');
    if (card) bindCard(card);
  });

  container.querySelectorAll('.address-card').forEach(bindCard);
})();
</script>

<?= $this->endSection() ?>
