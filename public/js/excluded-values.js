// Excluded Values UI (simple + robust)
(function () {
    function parseJson(value, fallback) {
        try {
            const parsed = JSON.parse(value || '');
            return parsed == null ? fallback : parsed;
        } catch (e) {
            return fallback;
        }
    }

    function esc(text) {
        if (text === null || text === undefined) return '';
        return String(text).replace(/[&<>"'`]/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '`': '&#96;' })[ch];
        });
    }

    function normalizeCombo(attributes) {
        const out = {};
        Object.keys(attributes || {}).sort().forEach(function (k) {
            const key = String(k || '').trim();
            const val = String(attributes[k] || '').trim();
            if (key && val) out[key] = val;
        });
        return out;
    }

    function itemKey(item) {
        if (!item || typeof item !== 'object') return '';
        if (item.attributes && typeof item.attributes === 'object') {
            const attrs = normalizeCombo(item.attributes);
            const type = String(item.type || 'combo').toLowerCase();
            return type + ':' + JSON.stringify(attrs).toLowerCase();
        }
        const attr = String(item.attribute || '').trim().toLowerCase();
        const val = String(item.value || '').trim().toLowerCase();
        return 'value:' + attr + '=' + val;
    }

    function setup() {
        const excludedCombosInput = document.getElementById('excluded_combos');
        const excludedValuesList = document.getElementById('excludedValuesList');
        const addValueBtn = document.getElementById('addExcludedValueBtn');
        const addComboBtn = document.getElementById('addExcludedComboBtn');
        const addOnlyComboBtn = document.getElementById('addOnlyComboBtn');
        const attributesDefInput = document.getElementById('attributes_definitions');
        const comboGroupBy = document.getElementById('comboGroupBy');
        const comboBrowserSearch = document.getElementById('comboBrowserSearch');
        const comboBrowserSummary = document.getElementById('comboBrowserSummary');
        const comboBrowserTree = document.getElementById('comboBrowserTree');

        if (!excludedCombosInput || !excludedValuesList) return;

        function getDefs() {
            const defs = parseJson(attributesDefInput ? attributesDefInput.value : '[]', []);
            if (!Array.isArray(defs)) return [];
            return defs
                .filter(function (d) { return d && d.name && Array.isArray(d.values) && d.values.length; })
                .map(function (d) {
                    return {
                        name: String(d.name).trim(),
                        values: d.values.map(function (v) { return String(v).trim(); }).filter(Boolean)
                    };
                });
        }

        function getExcluded() {
            const arr = parseJson(excludedCombosInput.value || '[]', []);
            return Array.isArray(arr) ? arr : [];
        }

        function setExcluded(arr) {
            excludedCombosInput.value = JSON.stringify(Array.isArray(arr) ? arr : []);
            renderExcluded();
        }

        function combinationsFromDefs(defs) {
            const validDefs = (defs || []).filter(function (d) {
                return d && d.name && Array.isArray(d.values) && d.values.length;
            });
            if (!validDefs.length) return [];

            let combos = [{}];
            validDefs.forEach(function (def) {
                const next = [];
                def.values.forEach(function (value) {
                    combos.forEach(function (combo) {
                        const out = Object.assign({}, combo);
                        out[def.name] = value;
                        next.push(out);
                    });
                });
                combos = next;
            });

            return combos.map(normalizeCombo);
        }

        function comboEquals(left, right) {
            return JSON.stringify(normalizeCombo(left || {})).toLowerCase() === JSON.stringify(normalizeCombo(right || {})).toLowerCase();
        }

        function getComboState(attributes, excludedItems) {
            const attrs = normalizeCombo(attributes || {});
            let hasOnlyAllow = false;
            let isOnlyAllowed = false;
            let isForceAllowed = false;
            let isExcluded = false;

            (excludedItems || []).forEach(function (item) {
                if (!item || typeof item !== 'object') return;
                if (item.type === 'only_allow_combo' && item.attributes) {
                    hasOnlyAllow = true;
                    if (comboEquals(item.attributes, attrs)) isOnlyAllowed = true;
                    return;
                }
                if (item.type === 'force_allow_combo' && item.attributes) {
                    if (comboEquals(item.attributes, attrs)) isForceAllowed = true;
                    return;
                }
                if (item.attributes && comboEquals(item.attributes, attrs)) {
                    isExcluded = true;
                    return;
                }
                if (item.type === 'value' && item.attribute && item.value) {
                    const attrName = String(item.attribute).trim().toLowerCase();
                    const attrValue = String(item.value).trim().toLowerCase();
                    Object.keys(attrs).forEach(function (key) {
                        if (String(key).trim().toLowerCase() === attrName && String(attrs[key]).trim().toLowerCase() === attrValue) {
                            isExcluded = true;
                        }
                    });
                }
            });

            if (isForceAllowed) return { type: 'force-allow', label: 'Allowed Override', badgeClass: 'bg-info-subtle text-info-emphasis' };
            if (hasOnlyAllow && isOnlyAllowed) return { type: 'only-allow', label: 'Only Allow', badgeClass: 'bg-success-subtle text-success-emphasis' };
            if (isExcluded) return { type: 'excluded', label: 'Excluded', badgeClass: 'bg-danger-subtle text-danger-emphasis' };
            if (hasOnlyAllow) return { type: 'blocked-by-only-allow', label: 'Skipped by Only Allow', badgeClass: 'bg-warning text-dark' };
            return { type: 'available', label: 'Available', badgeClass: 'bg-secondary-subtle text-secondary-emphasis' };
        }

        function removeMatchingItems(predicate) {
            const arr = getExcluded().filter(function (item) { return !predicate(item); });
            setExcluded(arr);
        }

        function removeExactCombo(type, attributes) {
            removeMatchingItems(function (item) {
                if (!item || typeof item !== 'object') return false;
                if (type === 'excluded') {
                    return item.type !== 'only_allow_combo' && item.type !== 'force_allow_combo' && item.attributes && comboEquals(item.attributes, attributes);
                }
                if (type === 'only_allow_combo' || type === 'force_allow_combo') {
                    return item.type === type && item.attributes && comboEquals(item.attributes, attributes);
                }
                return false;
            });
        }

        function addOrReplaceCombo(item) {
            const attrs = normalizeCombo(item && item.attributes ? item.attributes : {});
            const type = String(item && item.type ? item.type : 'combo');
            let arr = getExcluded();

            arr = arr.filter(function (existing) {
                if (!existing || typeof existing !== 'object') return true;
                if (!existing.attributes || !comboEquals(existing.attributes, attrs)) return true;
                if (type === 'combo') {
                    return existing.type === 'only_allow_combo' || existing.type === 'force_allow_combo';
                }
                return existing.type !== type;
            });

            const exists = arr.some(function (existing) {
                return existing && existing.type === type && existing.attributes && comboEquals(existing.attributes, attrs);
            });

            if (!exists) arr.push({ type: type, attributes: attrs });
            setExcluded(arr);
        }

        function preferredGroupAttribute(defs) {
            const names = (defs || []).map(function (d) { return String(d.name || '').trim(); }).filter(Boolean);
            const preferred = names.find(function (name) { return name.toLowerCase() === 'size'; });
            return preferred || names[0] || '';
        }

        function buildGroupSelector(defs) {
            if (!comboGroupBy) return;
            const names = (defs || []).map(function (d) { return String(d.name || '').trim(); }).filter(Boolean);
            const current = comboGroupBy.value;
            comboGroupBy.innerHTML = '';
            names.forEach(function (name) {
                const opt = document.createElement('option');
                opt.value = name;
                opt.textContent = name;
                comboGroupBy.appendChild(opt);
            });
            const nextValue = names.includes(current) ? current : preferredGroupAttribute(defs);
            comboGroupBy.value = nextValue;
            comboGroupBy.disabled = names.length <= 1;
        }

        function comboSearchText(combo) {
            return Object.keys(combo || {}).map(function (key) {
                return key + ' ' + combo[key];
            }).join(' ').toLowerCase();
        }

        function renderComboBrowser() {
            if (!comboBrowserTree || !comboGroupBy || !comboBrowserSummary) return;

            const defs = getDefs();
            buildGroupSelector(defs);

            const combos = combinationsFromDefs(defs);
            const excludedItems = getExcluded();
            const groupAttr = comboGroupBy.value || preferredGroupAttribute(defs);
            const query = String(comboBrowserSearch && comboBrowserSearch.value ? comboBrowserSearch.value : '').trim().toLowerCase();

            if (!defs.length || !combos.length || !groupAttr) {
                comboBrowserSummary.textContent = 'Add attributes with values to browse combinations here.';
                comboBrowserTree.innerHTML = '<div class="text-muted small">No combinations available yet.</div>';
                return;
            }

            const grouped = {};
            combos.forEach(function (combo) {
                if (query && comboSearchText(combo).indexOf(query) === -1) return;
                const groupValue = combo[groupAttr] || 'Ungrouped';
                if (!grouped[groupValue]) grouped[groupValue] = [];
                grouped[groupValue].push(combo);
            });

            const groupNames = Object.keys(grouped).sort(function (a, b) {
                return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
            });

            let totalVisible = 0;
            let excludedCount = 0;
            let allowedCount = 0;
            let onlyAllowCount = 0;

            comboBrowserTree.innerHTML = '';
            groupNames.forEach(function (groupName, index) {
                const list = grouped[groupName] || [];
                totalVisible += list.length;

                const details = document.createElement('details');
                details.className = 'combo-group border rounded-3 mb-2 bg-white';
                if (index < 2) details.open = true;

                const summary = document.createElement('summary');
                summary.className = 'd-flex justify-content-between align-items-center px-3 py-2';

                const groupStats = list.reduce(function (stats, combo) {
                    const state = getComboState(combo, excludedItems);
                    if (state.type === 'excluded' || state.type === 'blocked-by-only-allow') stats.excluded += 1;
                    if (state.type === 'force-allow') stats.allowed += 1;
                    if (state.type === 'only-allow') stats.onlyAllow += 1;
                    return stats;
                }, { excluded: 0, allowed: 0, onlyAllow: 0 });

                excludedCount += groupStats.excluded;
                allowedCount += groupStats.allowed;
                onlyAllowCount += groupStats.onlyAllow;

                summary.innerHTML =
                    '<span class="fw-semibold">' + esc(groupAttr) + ': ' + esc(groupName) + '</span>' +
                    '<span class="small text-muted">' + list.length + ' combos • ' + groupStats.excluded + ' skipped • ' + groupStats.onlyAllow + ' only-allow</span>';
                details.appendChild(summary);

                const body = document.createElement('div');
                body.className = 'px-3 pb-3';

                list.forEach(function (combo) {
                    const state = getComboState(combo, excludedItems);
                    const row = document.createElement('div');
                    row.className = 'combo-leaf d-flex justify-content-between align-items-start gap-3 border-top py-2';

                    const attrsHtml = Object.keys(combo).filter(function (key) {
                        return key !== groupAttr;
                    }).map(function (key) {
                        return '<span class="badge text-bg-light border me-1 mb-1">' + esc(key) + ': ' + esc(combo[key]) + '</span>';
                    }).join('');

                    const actionParts = [];
                    if (state.type === 'excluded') {
                        actionParts.push('<button type="button" class="btn btn-sm btn-outline-secondary" data-action="remove-combo" data-remove-type="excluded">Remove Skip</button>');
                    } else if (state.type === 'only-allow') {
                        actionParts.push('<button type="button" class="btn btn-sm btn-outline-secondary" data-action="remove-combo" data-remove-type="only_allow_combo">Remove Only Allow</button>');
                    } else if (state.type === 'force-allow') {
                        actionParts.push('<button type="button" class="btn btn-sm btn-outline-secondary" data-action="remove-combo" data-remove-type="force_allow_combo">Remove Override</button>');
                    } else {
                        actionParts.push('<button type="button" class="btn btn-sm btn-outline-danger" data-action="exclude-combo">Skip Combo</button>');
                        actionParts.push('<button type="button" class="btn btn-sm btn-outline-success" data-action="only-allow-combo">Only Allow</button>');
                    }
                    if (state.type !== 'force-allow') {
                        actionParts.push('<button type="button" class="btn btn-sm btn-outline-info" data-action="force-allow-combo">Allow Override</button>');
                    }

                    row.innerHTML =
                        '<div class="flex-grow-1">' +
                            '<div class="d-flex align-items-center gap-2 flex-wrap mb-1">' +
                                '<span class="badge ' + state.badgeClass + '">' + esc(state.label) + '</span>' +
                                '<span class="small text-muted">' + esc(groupAttr) + ': ' + esc(groupName) + '</span>' +
                            '</div>' +
                            '<div>' + (attrsHtml || '<span class="text-muted small">No secondary attributes</span>') + '</div>' +
                        '</div>' +
                        '<div class="d-flex flex-wrap gap-2">' + actionParts.join('') + '</div>';

                    row.dataset.comboAttributes = JSON.stringify(combo);
                    body.appendChild(row);
                });

                details.appendChild(body);
                comboBrowserTree.appendChild(details);
            });

            if (!groupNames.length) {
                comboBrowserSummary.textContent = 'No combinations match the current filter.';
                comboBrowserTree.innerHTML = '<div class="text-muted small">Try clearing the filter to see all combinations.</div>';
                return;
            }

            comboBrowserSummary.textContent = totalVisible + ' combinations shown across ' + groupNames.length + ' ' + groupAttr + ' groups. ' + excludedCount + ' currently skipped, ' + onlyAllowCount + ' marked Only Allow, ' + allowedCount + ' allowed overrides.';
        }

        function addExcluded(item) {
            const arr = getExcluded();
            const key = itemKey(item);
            if (!key) return;
            const exists = arr.some(function (x) { return itemKey(x) === key; });
            if (exists) {
                alert('Already excluded.');
                return;
            }
            arr.push(item);
            setExcluded(arr);
        }

        function renderExcluded() {
            const arr = getExcluded();
            excludedValuesList.innerHTML = '';

            if (!arr.length) {
                excludedValuesList.innerHTML = '<span class="text-muted small">No exclusions yet.</span>';
                return;
            }

            arr.forEach(function (item, idx) {
                const pill = document.createElement('span');
                pill.className = 'badge bg-danger-subtle text-danger-emphasis px-3 py-2';

                let label = '';
                if (item && item.type === 'only_allow_combo' && item.attributes && typeof item.attributes === 'object') {
                    const parts = Object.keys(item.attributes).map(function (k) {
                        return esc(k) + '=' + esc(item.attributes[k]);
                    });
                    label = '<span><b>Only Allow</b>: ' + parts.join(' + ') + '</span>';
                    pill.className = 'badge bg-success-subtle text-success-emphasis px-3 py-2';
                } else if (item && item.type === 'force_allow_combo' && item.attributes && typeof item.attributes === 'object') {
                    const parts = Object.keys(item.attributes).map(function (k) {
                        return esc(k) + '=' + esc(item.attributes[k]);
                    });
                    label = '<span><b>Allowed Override</b>: ' + parts.join(' + ') + '</span>';
                    pill.className = 'badge bg-info-subtle text-info-emphasis px-3 py-2';
                } else if (item && item.attributes && typeof item.attributes === 'object') {
                    const parts = Object.keys(item.attributes).map(function (k) {
                        return esc(k) + '=' + esc(item.attributes[k]);
                    });
                    label = '<span><b>Combo</b>: ' + parts.join(' + ') + '</span>';
                } else {
                    label = '<span><b>Value</b>: ' + esc(item ? item.attribute : '') + '=' + '<b>' + esc(item ? item.value : '') + '</b></span>';
                }

                pill.innerHTML = label + ' <a href="#" class="ms-2 text-danger" data-action="remove-excluded" data-idx="' + idx + '">&times;</a>';
                excludedValuesList.appendChild(pill);
            });

            renderComboBrowser();
        }

        excludedValuesList.addEventListener('click', function (e) {
            const btn = e.target && e.target.closest ? e.target.closest('[data-action="remove-excluded"]') : null;
            if (!btn) return;
            e.preventDefault();
            const idx = parseInt(btn.getAttribute('data-idx'), 10);
            if (isNaN(idx)) return;
            const arr = getExcluded();
            arr.splice(idx, 1);
            setExcluded(arr);
        });

        if (comboBrowserTree) {
            comboBrowserTree.addEventListener('click', function (e) {
                const btn = e.target && e.target.closest ? e.target.closest('button[data-action]') : null;
                if (!btn) return;
                e.preventDefault();

                const row = btn.closest('[data-combo-attributes]');
                if (!row) return;

                const attributes = parseJson(row.getAttribute('data-combo-attributes') || '{}', {});
                if (!attributes || typeof attributes !== 'object') return;

                const action = btn.getAttribute('data-action');
                if (action === 'exclude-combo') {
                    addOrReplaceCombo({ type: 'combo', attributes: attributes });
                    return;
                }
                if (action === 'only-allow-combo') {
                    addOrReplaceCombo({ type: 'only_allow_combo', attributes: attributes });
                    return;
                }
                if (action === 'force-allow-combo') {
                    addOrReplaceCombo({ type: 'force_allow_combo', attributes: attributes });
                    return;
                }
                if (action === 'remove-combo') {
                    removeExactCombo(btn.getAttribute('data-remove-type') || '', attributes);
                }
            });
        }

        function openExcludeValue() {
            const defs = getDefs();
            if (!defs.length) {
                alert('Add attributes and values first.');
                return;
            }

            if (!window.bootstrap || typeof window.bootstrap.Modal !== 'function') {
                const attrNames = defs.map(function (d) { return d.name; });
                const chosenAttr = prompt('Choose attribute (exact name):\n' + attrNames.join('\n'));
                if (!chosenAttr) return;
                const def = defs.find(function (d) { return d.name === chosenAttr; });
                if (!def) { alert('Invalid attribute.'); return; }
                const chosenVal = prompt('Choose value (exact value):\n' + def.values.join('\n'));
                if (!chosenVal) return;
                if (!def.values.includes(chosenVal)) { alert('Invalid value.'); return; }
                addExcluded({ type: 'value', attribute: chosenAttr, value: chosenVal });
                return;
            }

            let modal = document.getElementById('excludeValueModal');
            if (modal) modal.remove();
            modal = document.createElement('div');
            modal.id = 'excludeValueModal';
            modal.className = 'modal fade';
            modal.tabIndex = -1;
            modal.innerHTML =
                '<div class="modal-dialog"><div class="modal-content">' +
                '<div class="modal-header"><h5 class="modal-title">Exclude Value</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>' +
                '<div class="modal-body">' +
                '<div class="mb-3"><label class="form-label">Attribute</label><select class="form-select" id="excludeValueAttr"><option value="">Select...</option>' +
                defs.map(function (d) { return '<option value="' + esc(d.name) + '">' + esc(d.name) + '</option>'; }).join('') +
                '</select></div>' +
                '<div class="mb-0"><label class="form-label">Value</label><select class="form-select" id="excludeValueVal" disabled><option value="">Select...</option></select></div>' +
                '</div>' +
                '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="confirmExcludeValue" disabled>Add Exclusion</button></div>' +
                '</div></div>';
            document.body.appendChild(modal);

            const bsModal = new bootstrap.Modal(modal);
            const attrSel = modal.querySelector('#excludeValueAttr');
            const valSel = modal.querySelector('#excludeValueVal');
            const confirmBtn = modal.querySelector('#confirmExcludeValue');

            attrSel.addEventListener('change', function () {
                valSel.innerHTML = '<option value="">Select...</option>';
                valSel.disabled = true;
                confirmBtn.disabled = true;
                const def = defs.find(function (d) { return d.name === attrSel.value; });
                if (!def) return;
                def.values.forEach(function (v) {
                    const opt = document.createElement('option');
                    opt.value = v;
                    opt.textContent = v;
                    valSel.appendChild(opt);
                });
                valSel.disabled = false;
            });

            valSel.addEventListener('change', function () {
                confirmBtn.disabled = !(attrSel.value && valSel.value);
            });

            confirmBtn.addEventListener('click', function () {
                addExcluded({ type: 'value', attribute: attrSel.value, value: valSel.value });
                bsModal.hide();
            });

            modal.addEventListener('hidden.bs.modal', function () { modal.remove(); });
            bsModal.show();
        }

        function openExcludeCombo() {
            const defs = getDefs();
            if (!defs.length) {
                alert('Add attributes and values first.');
                return;
            }

            if (!window.bootstrap || typeof window.bootstrap.Modal !== 'function') {
                const attrs = {};
                for (let i = 0; i < defs.length; i++) {
                    const def = defs[i];
                    const chosen = prompt('Pick value for ' + def.name + ' (exact value):\n' + def.values.join('\n'));
                    if (!chosen) return;
                    if (!def.values.includes(chosen)) { alert('Invalid value for ' + def.name); return; }
                    attrs[def.name] = chosen;
                }
                addExcluded({ type: 'combo', attributes: normalizeCombo(attrs) });
                return;
            }

            let modal = document.getElementById('excludeComboModal');
            if (modal) modal.remove();
            modal = document.createElement('div');
            modal.id = 'excludeComboModal';
            modal.className = 'modal fade';
            modal.tabIndex = -1;

            const fieldsHtml = defs.map(function (def, idx) {
                return '<div class="mb-2">' +
                    '<label class="form-label mb-1">' + esc(def.name) + '</label>' +
                    '<select class="form-select combo-value-select" data-attr="' + esc(def.name) + '">' +
                    '<option value="">Select...</option>' +
                    def.values.map(function (v) { return '<option value="' + esc(v) + '">' + esc(v) + '</option>'; }).join('') +
                    '</select></div>';
            }).join('');

            modal.innerHTML =
                '<div class="modal-dialog"><div class="modal-content">' +
                '<div class="modal-header"><h5 class="modal-title">Exclude Exact Combo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>' +
                '<div class="modal-body">' +
                '<div class="small text-muted mb-2">Choose one value for each attribute. This exact combination will be blocked.</div>' +
                fieldsHtml +
                '</div>' +
                '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="confirmExcludeCombo">Add Exclusion</button></div>' +
                '</div></div>';
            document.body.appendChild(modal);

            const bsModal = new bootstrap.Modal(modal);
            const confirmBtn = modal.querySelector('#confirmExcludeCombo');

            confirmBtn.addEventListener('click', function () {
                const attrs = {};
                const selects = modal.querySelectorAll('.combo-value-select');
                for (let i = 0; i < selects.length; i++) {
                    const s = selects[i];
                    const attrName = s.getAttribute('data-attr');
                    const value = s.value;
                    if (!value) {
                        alert('Please select a value for ' + attrName + '.');
                        return;
                    }
                    attrs[attrName] = value;
                }
                addExcluded({ type: 'combo', attributes: normalizeCombo(attrs) });
                bsModal.hide();
            });

            modal.addEventListener('hidden.bs.modal', function () { modal.remove(); });
            bsModal.show();
        }

        function openOnlyAllowCombo() {
            const defs = getDefs();
            if (!defs.length) {
                alert('Add attributes and values first.');
                return;
            }

            if (!window.bootstrap || typeof window.bootstrap.Modal !== 'function') {
                const attrs = {};
                for (let i = 0; i < defs.length; i++) {
                    const def = defs[i];
                    const chosen = prompt('Pick value for ' + def.name + ' (exact value):\n' + def.values.join('\n'));
                    if (!chosen) return;
                    if (!def.values.includes(chosen)) { alert('Invalid value for ' + def.name); return; }
                    attrs[def.name] = chosen;
                }
                addExcluded({ type: 'only_allow_combo', attributes: normalizeCombo(attrs) });
                return;
            }

            let modal = document.getElementById('onlyComboModal');
            if (modal) modal.remove();
            modal = document.createElement('div');
            modal.id = 'onlyComboModal';
            modal.className = 'modal fade';
            modal.tabIndex = -1;

            const fieldsHtml = defs.map(function (def) {
                return '<div class="mb-2">' +
                    '<label class="form-label mb-1">' + esc(def.name) + '</label>' +
                    '<select class="form-select only-combo-value-select" data-attr="' + esc(def.name) + '">' +
                    '<option value="">Select...</option>' +
                    def.values.map(function (v) { return '<option value="' + esc(v) + '">' + esc(v) + '</option>'; }).join('') +
                    '</select></div>';
            }).join('');

            modal.innerHTML =
                '<div class="modal-dialog"><div class="modal-content">' +
                '<div class="modal-header"><h5 class="modal-title">Only Allow This Combo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>' +
                '<div class="modal-body">' +
                '<div class="small text-muted mb-2">Select one value for each attribute. Only this combo will be generated (plus any other combos you mark as Only Allow).</div>' +
                fieldsHtml +
                '</div>' +
                '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-success" id="confirmOnlyCombo">Save Only Allow</button></div>' +
                '</div></div>';
            document.body.appendChild(modal);

            const bsModal = new bootstrap.Modal(modal);
            const confirmBtn = modal.querySelector('#confirmOnlyCombo');

            confirmBtn.addEventListener('click', function () {
                const attrs = {};
                const selects = modal.querySelectorAll('.only-combo-value-select');
                for (let i = 0; i < selects.length; i++) {
                    const s = selects[i];
                    const attrName = s.getAttribute('data-attr');
                    const value = s.value;
                    if (!value) {
                        alert('Please select a value for ' + attrName + '.');
                        return;
                    }
                    attrs[attrName] = value;
                }
                addExcluded({ type: 'only_allow_combo', attributes: normalizeCombo(attrs) });
                bsModal.hide();
            });

            modal.addEventListener('hidden.bs.modal', function () { modal.remove(); });
            bsModal.show();
        }

        if (addValueBtn) {
            addValueBtn.addEventListener('click', function (e) {
                e.preventDefault();
                openExcludeValue();
            });
        }

        if (addComboBtn) {
            addComboBtn.addEventListener('click', function (e) {
                e.preventDefault();
                openExcludeCombo();
            });
        }

        if (addOnlyComboBtn) {
            addOnlyComboBtn.addEventListener('click', function (e) {
                e.preventDefault();
                openOnlyAllowCombo();
            });
        }

        if (comboGroupBy) {
            comboGroupBy.addEventListener('change', renderComboBrowser);
        }

        if (comboBrowserSearch) {
            comboBrowserSearch.addEventListener('input', renderComboBrowser);
        }

        document.addEventListener('corelynk:attributes-definitions-changed', renderComboBrowser);

        window.CorelynkExcludeValue = openExcludeValue;
        window.CorelynkExcludeCombo = openExcludeCombo;
        window.CorelynkOnlyCombo = openOnlyAllowCombo;

        renderExcluded();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setup);
    } else {
        setup();
    }
})();
