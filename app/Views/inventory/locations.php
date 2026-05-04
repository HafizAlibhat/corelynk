<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Locations<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
/* ── Page layout ───────────────────────────────────────────── */
.loc-page { max-width: 1200px; }
.loc-col-wh  { flex: 0 0 320px; min-width: 0; }
.loc-col-det { flex: 1 1 0; min-width: 0; }

/* ── Warehouse list ────────────────────────────────────────── */
.wh-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: .55rem .75rem; border-radius: 8px; cursor: pointer;
    border: 2px solid transparent; transition: all .12s;
    background: rgba(30,41,59,.45);
    margin-bottom: .35rem;
}
.wh-item:hover { border-color: rgba(99,102,241,.25); background: rgba(30,41,59,.75); }
.wh-item.active { border-color: #6366f1; background: rgba(99,102,241,.08); }
.wh-item .wh-name { font-weight: 600; font-size: .82rem; color: var(--cl-text-primary,#e2e8f0); }
.wh-item .wh-meta { font-size: .68rem; color: #64748b; }
.wh-item .wh-badge { font-size: .6rem; background: rgba(148,163,184,.12); color: #94a3b8; border-radius: 4px; padding: .1rem .4rem; }
.wh-item .wh-actions { display: flex; gap: .25rem; opacity: 0; transition: opacity .12s; }
.wh-item:hover .wh-actions { opacity: 1; }

/* ── Tree ──────────────────────────────────────────────────── */
.loc-tree { padding-left: 0; list-style: none; margin: 0; }
.loc-tree .loc-tree { padding-left: 1.4rem; border-left: 2px solid rgba(99,102,241,.1); margin-left: .6rem; }

.loc-node {
    display: flex; align-items: center; gap: .45rem;
    padding: .4rem .6rem; margin: .15rem 0; border-radius: 7px;
    background: rgba(30,41,59,.35); border: 1px solid transparent;
    transition: all .12s; cursor: default;
    min-height: 36px;
}
.loc-node:hover { background: rgba(99,102,241,.06); border-color: rgba(99,102,241,.12); }

.loc-node .loc-icon { font-size: .82rem; color: #6366f1; flex-shrink: 0; }
.loc-node .loc-name { font-size: .8rem; font-weight: 600; color: var(--cl-text-primary,#e2e8f0); flex: 1; min-width: 0; }
.loc-node .loc-child-count { font-size: .64rem; color: #64748b; background: rgba(148,163,184,.08); border-radius: 3px; padding: .05rem .35rem; }
.loc-node .loc-inactive { font-size: .6rem; background: rgba(248,113,113,.1); color: #f87171; border-radius: 3px; padding: .05rem .35rem; }

.loc-node .loc-btns { display: flex; gap: .2rem; opacity: 0; transition: opacity .12s; flex-shrink: 0; }
.loc-node:hover .loc-btns { opacity: 1; }

.loc-btn {
    border: none; background: none; padding: .15rem .3rem;
    border-radius: 4px; font-size: .72rem; cursor: pointer;
    color: #94a3b8; transition: all .1s;
}
.loc-btn:hover { background: rgba(99,102,241,.12); color: #a5b4fc; }
.loc-btn.danger:hover { background: rgba(248,113,113,.12); color: #f87171; }
.loc-btn.success:hover { background: rgba(52,211,153,.12); color: #34d399; }

/* ── Drop target highlight ─────────────────────────────────── */
.loc-node.drop-target { border-color: #6366f1 !important; background: rgba(99,102,241,.1) !important; }
.loc-node.drop-root   { border-color: #34d399 !important; background: rgba(52,211,153,.08) !important; }
.loc-node.dragging     { opacity: .4; }

/* ── Inline edit ───────────────────────────────────────────── */
.loc-name-edit {
    background: rgba(15,23,42,.6); border: 1px solid #6366f1; border-radius: 4px;
    color: var(--cl-text-primary,#e2e8f0); font-size: .8rem; font-weight: 600;
    padding: .1rem .4rem; outline: none; width: 100%;
}

/* ── Collapse / expand toggle ──────────────────────────────── */
.loc-toggle {
    border: none; background: none; padding: .1rem .25rem; margin-right: .1rem;
    border-radius: 4px; font-size: .7rem; cursor: pointer;
    color: #64748b; transition: all .12s; flex-shrink: 0; line-height: 1;
}
.loc-toggle:hover { background: rgba(99,102,241,.12); color: #a5b4fc; }
.loc-toggle i { transition: transform .15s ease; display: inline-block; }
.loc-toggle.collapsed i { transform: rotate(-90deg); }

/* When collapsed, hide child tree */
li.collapsed > .loc-tree { display: none; }

/* ── Empty state ───────────────────────────────────────────── */
.empty-state { text-align: center; padding: 2rem 1rem; color: #64748b; font-size: .82rem; }
.empty-state i { font-size: 1.6rem; color: #475569; display: block; margin-bottom: .5rem; }

/* ── Move modal select ─────────────────────────────────────── */
.move-opt { padding: .35rem .5rem; }
.move-opt-indent { color: #64748b; }

/* ── Section card ──────────────────────────────────────────── */
.loc-section {
    background: var(--cl-surface,#1e293b); border: 1px solid #1e293b;
    border-radius: 10px; padding: 1rem 1.1rem;
}

/* ── Root drop zone ────────────────────────────────────────── */
.root-drop-zone {
    border: 2px dashed rgba(99,102,241,.15); border-radius: 8px;
    padding: .5rem; margin-top: .5rem; text-align: center;
    font-size: .72rem; color: #475569; transition: all .15s;
    min-height: 32px; display: flex; align-items: center; justify-content: center;
}
.root-drop-zone.drop-hover { border-color: #34d399; background: rgba(52,211,153,.06); color: #34d399; }

/* ── Toast ─────────────────────────────────────────────────── */
.loc-toast {
    position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 2000;
    padding: .55rem 1rem; border-radius: 8px; font-size: .8rem;
    color: #fff; box-shadow: 0 4px 15px rgba(0,0,0,.4);
    animation: loc-toast-in .25s ease;
}
@keyframes loc-toast-in { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
.loc-toast.success { background: #059669; }
.loc-toast.error   { background: #dc2626; }
.loc-toast.info    { background: #2563eb; }
</style>

<div class="main-content-wrapper loc-page">

    <!-- Page header -->
    <div class="page-header mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title mb-0">
                    <i class="bi bi-building text-info me-2"></i>Warehouses &amp; Locations
                </h1>
                <p class="text-muted mb-0" style="font-size:.8rem">
                    Manage your warehouse layout. Drag locations to rearrange, click to rename, hover for actions.
                </p>
            </div>
        </div>
    </div>

    <div class="d-flex gap-3" style="align-items:flex-start">

        <!-- ═══ Left: Warehouse list ═══════════════════════════════════════════ -->
        <div class="loc-col-wh">
            <div class="loc-section">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="fw-bold" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b">Warehouses</div>
                    <button class="btn btn-sm" id="addWhBtn" style="background:#6366f1;color:#fff;font-size:.72rem;border:none;padding:.25rem .6rem;border-radius:6px">
                        <i class="bi bi-plus-circle me-1"></i>Add
                    </button>
                </div>
                <div id="whList"></div>
            </div>
        </div>

        <!-- ═══ Right: Location tree ═══════════════════════════════════════════ -->
        <div class="loc-col-det">
            <div class="loc-section" id="detailPanel">
                <div class="empty-state">
                    <i class="bi bi-arrow-left-circle"></i>
                    Select a warehouse to manage its locations
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Warehouse modal ═══════════════════════════════════════════════════ -->
<div class="modal fade" id="whModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:var(--cl-surface,#1e2d44);border:1px solid #334155">
            <div class="modal-header" style="border-bottom:1px solid #334155;padding:.6rem 1rem">
                <h6 class="modal-title" id="whModalTitle" style="font-size:.85rem;font-weight:700;color:var(--cl-text-primary,#e2e8f0)">Add Warehouse</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:.85rem 1rem">
                <input type="hidden" id="whId">
                <div class="mb-2">
                    <label class="form-label" style="font-size:.74rem;font-weight:600">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="whName" placeholder="e.g. Main Warehouse">
                </div>
                <div class="mb-2">
                    <label class="form-label" style="font-size:.74rem;font-weight:600">Code <span class="text-muted">(optional)</span></label>
                    <input type="text" class="form-control form-control-sm" id="whCode" placeholder="e.g. WH-1">
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="whActive" checked>
                    <label class="form-check-label" for="whActive" style="font-size:.78rem">Active</label>
                </div>
                <div id="whMsg" class="small mt-2"></div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #334155;padding:.45rem .8rem">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="whSaveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Move Location modal ═══════════════════════════════════════════════ -->
<div class="modal fade" id="moveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content" style="background:var(--cl-surface,#1e2d44);border:1px solid #334155">
            <div class="modal-header" style="border-bottom:1px solid #334155;padding:.6rem 1rem">
                <h6 class="modal-title" style="font-size:.85rem;font-weight:700;color:var(--cl-text-primary,#e2e8f0)">
                    <i class="bi bi-arrows-move me-1 text-info"></i>Move Location
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:.85rem 1rem">
                <input type="hidden" id="moveLocId">
                <div class="mb-2" style="font-size:.78rem;color:#94a3b8">
                    Moving: <strong id="moveLocName" style="color:var(--cl-text-primary,#e2e8f0)"></strong>
                </div>
                <label class="form-label" style="font-size:.74rem;font-weight:600">New parent:</label>
                <select id="moveParentSelect" class="form-select form-select-sm"></select>
                <div id="moveMsg" class="small mt-2"></div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #334155;padding:.45rem .8rem">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="moveSaveBtn">
                    <i class="bi bi-check2 me-1"></i>Move
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var BASE = '<?= rtrim(site_url(), '/') ?>';
    var _activeWhId = null;
    var _whData     = null;  // { warehouse:{}, locations:[] }
    var _dragLocId  = null;

    // ── Helpers ─────────────────────────────────────────────────────────────

    function esc(s) { var d = document.createElement('div'); d.textContent = String(s||''); return d.innerHTML; }

    function toast(text, type) {
        type = type || 'info';
        var el = document.createElement('div');
        el.className = 'loc-toast ' + type;
        el.textContent = text;
        document.body.appendChild(el);
        setTimeout(function () { el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 300); }, 3500);
    }

    async function api(url, opts) {
        var r = await fetch(url, opts || {});
        var j = await r.json();
        if (!r.ok) throw new Error(j.error || r.statusText);
        return j;
    }

    // ── Warehouse list ──────────────────────────────────────────────────────

    async function loadWarehouses() {
        var j = await api(BASE + '/inventory/warehouses');
        var list = j.data || [];
        var el = document.getElementById('whList');
        if (!list.length) {
            el.innerHTML = '<div class="empty-state"><i class="bi bi-building"></i>No warehouses yet. Click "Add" to create one.</div>';
            return;
        }
        el.innerHTML = '';
        list.forEach(function (w) {
            var div = document.createElement('div');
            div.className = 'wh-item' + (_activeWhId == w.id ? ' active' : '');
            div.dataset.id = w.id;
            div.innerHTML =
                '<div>' +
                    '<div class="wh-name">' + esc(w.name) + (!w.is_active || w.is_active == '0' ? ' <span class="wh-badge" style="background:rgba(248,113,113,.1);color:#f87171">Inactive</span>' : '') + '</div>' +
                    (w.code ? '<div class="wh-meta">Code: ' + esc(w.code) + '</div>' : '') +
                '</div>' +
                '<div class="wh-actions">' +
                    '<button class="loc-btn" data-act="edit" title="Edit"><i class="bi bi-pencil"></i></button>' +
                    '<button class="loc-btn danger" data-act="deactivate" title="Deactivate"><i class="bi bi-x-circle"></i></button>' +
                '</div>';
            el.appendChild(div);
        });
    }

    document.getElementById('whList').addEventListener('click', function (e) {
        var btn = e.target.closest('[data-act]');
        var item = e.target.closest('.wh-item');
        if (!item) return;
        var id = item.dataset.id;

        if (btn) {
            e.stopPropagation();
            if (btn.dataset.act === 'edit') editWarehouse(id);
            if (btn.dataset.act === 'deactivate') deactivateWarehouse(id);
            return;
        }
        // Select warehouse
        _activeWhId = id;
        document.querySelectorAll('.wh-item').forEach(function (i) { i.classList.remove('active'); });
        item.classList.add('active');
        loadDetail(id);
    });

    // ── Warehouse CRUD ──────────────────────────────────────────────────────

    document.getElementById('addWhBtn').addEventListener('click', function () { openWhModal(null); });

    function openWhModal(wh) {
        document.getElementById('whId').value     = wh ? wh.id : '';
        document.getElementById('whName').value   = wh ? wh.name : '';
        document.getElementById('whCode').value   = wh ? (wh.code || '') : '';
        document.getElementById('whActive').checked = wh ? (wh.is_active == 1) : true;
        document.getElementById('whModalTitle').textContent = wh ? 'Edit Warehouse' : 'Add Warehouse';
        document.getElementById('whMsg').innerHTML = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('whModal')).show();
    }

    async function editWarehouse(id) {
        try {
            var j = await api(BASE + '/inventory/warehouses/' + id);
            openWhModal(j.warehouse);
        } catch (e) { toast(e.message, 'error'); }
    }

    document.getElementById('whSaveBtn').addEventListener('click', async function () {
        var btn = this; btn.disabled = true;
        try {
            await api(BASE + '/inventory/warehouses/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id:        document.getElementById('whId').value || null,
                    name:      document.getElementById('whName').value,
                    code:      document.getElementById('whCode').value || null,
                    is_active: document.getElementById('whActive').checked ? 1 : 0,
                }),
            });
            bootstrap.Modal.getInstance(document.getElementById('whModal')).hide();
            await loadWarehouses();
            if (_activeWhId) loadDetail(_activeWhId);
            toast('Warehouse saved', 'success');
        } catch (e) { document.getElementById('whMsg').innerHTML = '<span class="text-danger">' + esc(e.message) + '</span>'; }
        finally { btn.disabled = false; }
    });

    async function deactivateWarehouse(id) {
        if (!confirm('Deactivate this warehouse? (only possible if no stock is linked)')) return;
        try {
            await api(BASE + '/inventory/warehouses/' + id + '/deactivate', { method: 'POST' });
            toast('Warehouse deactivated', 'success');
            if (_activeWhId == id) {
                _activeWhId = null;
                document.getElementById('detailPanel').innerHTML = '<div class="empty-state"><i class="bi bi-arrow-left-circle"></i>Select a warehouse</div>';
            }
            await loadWarehouses();
        } catch (e) { toast(e.message, 'error'); }
    }

    // ── Location detail panel ───────────────────────────────────────────────

    async function loadDetail(whId) {
        var panel = document.getElementById('detailPanel');
        panel.innerHTML = '<div class="text-center text-muted py-3" style="font-size:.78rem"><span class="spinner-border spinner-border-sm me-1"></span>Loading…</div>';
        try {
            var j = await api(BASE + '/inventory/warehouses/' + whId);
            _whData = j;
            renderDetail(j.warehouse, j.locations || []);
        } catch (e) {
            var p = document.getElementById('detailPanel');
            p.innerHTML = '<div class="text-danger">' + esc(e.message) + '</div>';
        }
    }

    function renderDetail(wh, locs) {
        var activeLocs = locs.filter(function (l) { return l.is_active != 0; });
        var panel = document.getElementById('detailPanel'); // always re-query — may have been cloned

        var header = '<div class="d-flex align-items-center justify-content-between mb-3">' +
            '<div>' +
                '<div class="fw-bold" style="font-size:.9rem;color:var(--cl-text-primary,#e2e8f0)">' +
                    '<i class="bi bi-building me-1 text-info"></i>' + esc(wh.name) +
                '</div>' +
                '<div style="font-size:.7rem;color:#64748b">' +
                    (wh.code ? 'Code: ' + esc(wh.code) + ' · ' : '') +
                    activeLocs.length + ' location' + (activeLocs.length !== 1 ? 's' : '') +
                '</div>' +
            '</div>' +
            '<button class="btn btn-sm" id="addRootLocBtn" style="background:#6366f1;color:#fff;font-size:.72rem;border:none;padding:.25rem .65rem;border-radius:6px">' +
                '<i class="bi bi-plus-circle me-1"></i>Add Location' +
            '</button>' +
        '</div>';

        var treeHtml = buildTree(activeLocs, null, 0);
        if (!treeHtml) {
            treeHtml = '<div class="empty-state"><i class="bi bi-geo-alt"></i>No locations yet. Add one above.</div>';
        }

        // Root drop zone for dragging locations to root level
        treeHtml += '<div class="root-drop-zone" id="rootDropZone"><i class="bi bi-arrow-bar-down me-1"></i>Drop here to make root-level</div>';

        // Clone the panel to strip ALL previously-attached event listeners
        // (prevents duplicate handlers accumulating across reloads)
        var fresh = panel.cloneNode(false);
        panel.replaceWith(fresh);
        fresh.innerHTML = header + treeHtml;

        // Wire add-root button
        fresh.querySelector('#addRootLocBtn').addEventListener('click', function () {
            addLocationPrompt(wh.id, null);
        });

        // Wire all buttons
        wireTreeActions(fresh);
        wireTreeDrag(fresh);
    }

    function buildTree(locs, parentId, depth) {
        var children = locs.filter(function (l) {
            var p = l.parent_id ? parseInt(l.parent_id) : null;
            return p === parentId;
        });
        if (!children.length) return '';

        children.sort(function (a, b) { return (a.name || '').localeCompare(b.name || ''); });

        var html = '<ul class="loc-tree">';
        children.forEach(function (c) {
            var cid = parseInt(c.id);
            var childCount = locs.filter(function (l) { return parseInt(l.parent_id || 0) === cid; }).length;
            var isInactive = (c.is_active == 0);
            var childHtml = buildTree(locs, cid, depth + 1);

            html += '<li data-loc-id="' + cid + '">';
            html += '<div class="loc-node" data-id="' + cid + '" draggable="true">';
            if (childCount > 0) {
                html += '<button class="loc-toggle" data-toggle-id="' + cid + '" title="Collapse / Expand"><i class="bi bi-chevron-down"></i></button>';
            }
            html +=   '<i class="bi ' + (childCount > 0 ? 'bi-folder2-open' : 'bi-geo-alt') + ' loc-icon"></i>';
            html +=   '<span class="loc-name" title="Double-click to rename">' + esc(c.name) + '</span>';
            if (childCount > 0) html += '<span class="loc-child-count">' + childCount + ' sub</span>';
            if (isInactive) html += '<span class="loc-inactive">Inactive</span>';
            html +=   '<div class="loc-btns">';
            html +=     '<button class="loc-btn success" data-act="add" data-id="' + cid + '" title="Add child location"><i class="bi bi-plus-circle"></i></button>';
            html +=     '<button class="loc-btn" data-act="rename" data-id="' + cid + '" title="Rename"><i class="bi bi-pencil"></i></button>';
            html +=     '<button class="loc-btn" data-act="move" data-id="' + cid + '" title="Move under another parent"><i class="bi bi-arrows-move"></i></button>';
            html +=     '<button class="loc-btn danger" data-act="delete" data-id="' + cid + '" title="Delete (if no stock)"><i class="bi bi-trash3"></i></button>';
            html +=   '</div>';
            html += '</div>';
            html += childHtml;
            html += '</li>';
        });
        html += '</ul>';
        return html;
    }

    function wireTreeActions(panel) {
        // Collapse/expand toggle
        panel.addEventListener('click', function (e) {
            var toggle = e.target.closest('.loc-toggle');
            if (toggle) {
                e.stopPropagation();
                var locId = toggle.dataset.toggleId;
                var li = panel.querySelector('li[data-loc-id="' + locId + '"]');
                if (li) li.classList.toggle('collapsed');
                toggle.classList.toggle('collapsed');
                // Swap folder icon
                var node = toggle.closest('.loc-node');
                if (node) {
                    var icon = node.querySelector('.loc-icon');
                    if (icon) {
                        icon.classList.toggle('bi-folder2-open');
                        icon.classList.toggle('bi-folder2');
                    }
                }
                return;
            }

            var btn = e.target.closest('[data-act]');
            if (!btn) return;
            var act = btn.dataset.act;
            var id  = btn.dataset.id;
            if (act === 'add')    addLocationPrompt(_activeWhId, id);
            if (act === 'rename') startRename(id);
            if (act === 'move')   openMoveModal(id);
            if (act === 'delete') deleteLocation(id);
        });

        // Double-click on name to rename
        panel.addEventListener('dblclick', function (e) {
            var nameEl = e.target.closest('.loc-name');
            if (!nameEl) return;
            var node = nameEl.closest('.loc-node');
            if (node) startRename(node.dataset.id);
        });
    }

    // ── Add location (inline prompt) ────────────────────────────────────────

    function addLocationPrompt(warehouseId, parentId) {
        var name = prompt('New location name:');
        if (!name || !name.trim()) return;
        api(BASE + '/inventory/warehouses/' + warehouseId + '/locations', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: name.trim(), parent_id: parentId || null }),
        }).then(function () {
            toast('Location created', 'success');
            loadDetail(warehouseId);
        }).catch(function (e) { toast(e.message, 'error'); });
    }

    // ── Inline rename ───────────────────────────────────────────────────────

    function startRename(locId) {
        var node = document.querySelector('.loc-node[data-id="' + locId + '"]');
        if (!node) return;
        var nameSpan = node.querySelector('.loc-name');
        var oldName  = nameSpan.textContent;

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'loc-name-edit';
        input.value = oldName;
        nameSpan.replaceWith(input);
        input.focus();
        input.select();

        function finish() {
            var newName = input.value.trim();
            if (!newName || newName === oldName) {
                // Revert
                var sp = document.createElement('span');
                sp.className = 'loc-name';
                sp.title = 'Double-click to rename';
                sp.textContent = oldName;
                input.replaceWith(sp);
                return;
            }
            api(BASE + '/inventory/locations/' + locId + '/rename', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: newName }),
            }).then(function () {
                toast('Renamed to "' + newName + '"', 'success');
                loadDetail(_activeWhId);
            }).catch(function (e) {
                toast(e.message, 'error');
                loadDetail(_activeWhId);
            });
        }

        input.addEventListener('blur', finish);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter')  { e.preventDefault(); input.blur(); }
            if (e.key === 'Escape') { input.value = oldName; input.blur(); }
        });
    }

    // ── Move modal ──────────────────────────────────────────────────────────

    function openMoveModal(locId) {
        if (!_whData) return;
        var locs    = (_whData.locations || []).filter(function (l) { return l.is_active != 0; });
        var loc     = locs.find(function (l) { return parseInt(l.id) === parseInt(locId); });
        if (!loc) return;

        // Collect descendants of locId (they can't be a parent)
        var blocked = getDescendantIds(locs, parseInt(locId));
        blocked.push(parseInt(locId));

        document.getElementById('moveLocId').value = locId;
        document.getElementById('moveLocName').textContent = loc.name;
        document.getElementById('moveMsg').innerHTML = '';

        var sel = document.getElementById('moveParentSelect');
        sel.innerHTML = '<option value="">— Root level (no parent) —</option>';

        // Build hierarchical options
        buildMoveOpts(locs, null, 0, blocked, sel, parseInt(loc.parent_id || 0));

        bootstrap.Modal.getOrCreateInstance(document.getElementById('moveModal')).show();
    }

    function buildMoveOpts(locs, parentId, depth, blocked, sel, currentParentId) {
        var children = locs.filter(function (l) {
            var p = l.parent_id ? parseInt(l.parent_id) : null;
            return p === parentId;
        });
        children.sort(function (a, b) { return (a.name||'').localeCompare(b.name||''); });
        children.forEach(function (c) {
            var cid = parseInt(c.id);
            if (blocked.indexOf(cid) !== -1) return; // skip self + descendants
            var prefix = '';
            for (var i = 0; i < depth; i++) prefix += '\u00A0\u00A0\u00A0';
            if (depth > 0) prefix += '\u203A ';
            var opt = document.createElement('option');
            opt.value = cid;
            opt.textContent = prefix + c.name;
            if (cid === currentParentId) opt.selected = true;
            sel.appendChild(opt);
            buildMoveOpts(locs, cid, depth + 1, blocked, sel, currentParentId);
        });
    }

    function getDescendantIds(locs, parentId) {
        var result = [];
        var children = locs.filter(function (l) { return parseInt(l.parent_id || 0) === parentId; });
        children.forEach(function (c) {
            result.push(parseInt(c.id));
            result = result.concat(getDescendantIds(locs, parseInt(c.id)));
        });
        return result;
    }

    document.getElementById('moveSaveBtn').addEventListener('click', async function () {
        var locId    = document.getElementById('moveLocId').value;
        var parentId = document.getElementById('moveParentSelect').value || null;
        var btn = this; btn.disabled = true;
        try {
            await api(BASE + '/inventory/locations/' + locId + '/move', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ parent_id: parentId }),
            });
            bootstrap.Modal.getInstance(document.getElementById('moveModal')).hide();
            toast('Location moved', 'success');
            loadDetail(_activeWhId);
        } catch (e) {
            document.getElementById('moveMsg').innerHTML = '<span class="text-danger">' + esc(e.message) + '</span>';
        }
        finally { btn.disabled = false; }
    });

    // ── Drag & drop to reparent ─────────────────────────────────────────────

    function wireTreeDrag(panel) {
        panel.addEventListener('dragstart', function (e) {
            var node = e.target.closest('.loc-node');
            if (!node) return;
            _dragLocId = node.dataset.id;
            node.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', _dragLocId);
        });

        panel.addEventListener('dragend', function (e) {
            _dragLocId = null;
            document.querySelectorAll('.loc-node').forEach(function (n) { n.classList.remove('dragging', 'drop-target', 'drop-root'); });
            var rdz = document.getElementById('rootDropZone');
            if (rdz) rdz.classList.remove('drop-hover');
        });

        panel.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            // Highlight the drop target
            var node = e.target.closest('.loc-node');
            document.querySelectorAll('.loc-node.drop-target').forEach(function (n) { n.classList.remove('drop-target'); });
            if (node && node.dataset.id !== _dragLocId) {
                node.classList.add('drop-target');
            }
        });

        panel.addEventListener('dragleave', function (e) {
            var node = e.target.closest('.loc-node');
            if (node) node.classList.remove('drop-target');
        });

        panel.addEventListener('drop', function (e) {
            e.preventDefault();
            var targetNode = e.target.closest('.loc-node');
            document.querySelectorAll('.drop-target').forEach(function (n) { n.classList.remove('drop-target'); });

            if (!_dragLocId) return;
            var targetId = targetNode ? targetNode.dataset.id : null;
            if (targetId === _dragLocId) return;

            // Check: is targetId a descendant of dragLocId? Block it.
            if (targetId && _whData) {
                var activeLocs = (_whData.locations || []).filter(function (l) { return l.is_active != 0; });
                var descendants = getDescendantIds(activeLocs, parseInt(_dragLocId));
                if (descendants.indexOf(parseInt(targetId)) !== -1) {
                    toast('Can\'t move under its own child', 'error');
                    return;
                }
            }

            doMove(_dragLocId, targetId);
        });

        // Root drop zone
        var rdz = document.getElementById('rootDropZone');
        if (rdz) {
            rdz.addEventListener('dragover', function (e) { e.preventDefault(); rdz.classList.add('drop-hover'); });
            rdz.addEventListener('dragleave', function () { rdz.classList.remove('drop-hover'); });
            rdz.addEventListener('drop', function (e) {
                e.preventDefault(); e.stopPropagation();
                rdz.classList.remove('drop-hover');
                if (!_dragLocId) return;
                doMove(_dragLocId, null);
            });
        }
    }

    function doMove(locId, newParentId) {
        api(BASE + '/inventory/locations/' + locId + '/move', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ parent_id: newParentId }),
        }).then(function () {
            toast('Location moved', 'success');
            loadDetail(_activeWhId);
        }).catch(function (e) { toast(e.message, 'error'); });
    }

    // ── Delete location ─────────────────────────────────────────────────────

    async function deleteLocation(locId) {
        // First check stock
        try {
            var check = await api(BASE + '/inventory/locations/' + locId + '/stock-check');
            var loc = (_whData.locations || []).find(function (l) { return parseInt(l.id) === parseInt(locId); });
            var locName = loc ? loc.name : 'this location';

            if (check.has_stock) {
                var details = [];
                if (check.movements > 0) details.push(check.movements + ' stock movement(s)');
                if (check.balance !== 0) details.push('balance of ' + parseFloat(check.balance).toFixed(2));
                toast('Cannot delete "' + locName + '" — has ' + details.join(' and ') + '. Remove product stock first.', 'error');
                return;
            }

            var msg = 'Delete "' + locName + '"?';
            if (check.child_count > 0) {
                msg += '\n\nThis will also delete ' + check.child_count + ' child location(s).';
            }
            msg += '\n\nThis cannot be undone.';

            if (!confirm(msg)) return;

            await api(BASE + '/inventory/locations/' + locId + '/delete', { method: 'POST' });
            toast('Deleted', 'success');
            loadDetail(_activeWhId);
        } catch (e) { toast(e.message, 'error'); }
    }

    // ── Init ────────────────────────────────────────────────────────────────
    loadWarehouses().catch(function (e) { toast(e.message, 'error'); });
}());
</script>

<?= $this->endSection() ?>
