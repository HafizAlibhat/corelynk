<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Document Studio<?= $this->endSection() ?>

<?= $this->section('content') ?>

<style>
/* ═══════════════════ DOCUMENT STUDIO v4 ═══════════════════ */
:root {
    --ds-doc-w: 480px;
    --ds-accent: #4f46e5;
    --ds-accent-light: #eef2ff;
    --ds-success: #10b981;
    --ds-danger: #ef4444;
    --ds-warning: #f59e0b;
    --ds-blue: #3b82f6;
}
body.theme-dark { --ds-accent-light: rgba(79,70,229,0.15); }

.ds-wrap { display:flex; height:calc(100vh - 60px); overflow:hidden; gap:0; }

/* ── LEFT: Document Panel ── */
.ds-doc {
    width:var(--ds-doc-w); min-width:340px; max-width:560px;
    display:flex; flex-direction:column; overflow:hidden;
    background:var(--white,#fff); border-right:1px solid var(--gray-200,#e5e7eb);
}
.ds-topbar {
    display:flex; align-items:center; gap:6px;
    padding:5px 10px; border-bottom:1px solid var(--gray-200);
    background:var(--gray-50); flex-shrink:0;
}
.ds-type-radios { display:flex; gap:2px; margin-right:auto; }
.ds-type-radios label {
    font-size:.68rem; font-weight:700; padding:3px 8px;
    border-radius:4px; cursor:pointer; color:var(--gray-500);
    border:1.5px solid transparent; transition:all .15s; white-space:nowrap;
    text-transform:uppercase; letter-spacing:.3px; user-select:none;
}
.ds-type-radios input { display:none; }
.ds-type-radios input:checked + label {
    color:var(--ds-accent); border-color:var(--ds-accent); background:var(--ds-accent-light);
}
.ds-topbar .btn-icon {
    padding:3px 6px; border-radius:5px; font-size:.82rem;
    border:1px solid var(--gray-200); background:transparent;
    color:var(--gray-500); cursor:pointer; transition:all .15s; line-height:1;
}
.ds-topbar .btn-icon:hover { color:var(--gray-700); border-color:var(--gray-400); }
.ds-topbar .btn-icon.danger:hover { color:var(--ds-danger); border-color:var(--ds-danger); }
.ds-topbar .btn-icon.primary:hover { color:var(--ds-accent); border-color:var(--ds-accent); }

/* Compact header */
.ds-header { padding:6px 10px; border-bottom:1px solid var(--gray-200); background:var(--gray-50); flex-shrink:0; }
.ds-h-row { display:flex; gap:6px; align-items:flex-end; flex-wrap:wrap; }
.ds-h-row .fld { display:flex; flex-direction:column; min-width:0; }
.ds-h-row .fld > span { font-size:.6rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--gray-400); margin-bottom:1px; line-height:1; }
.ds-h-row input, .ds-h-row select { padding:4px 6px; border-radius:5px; border:1.5px solid var(--gray-200); font-size:.76rem; background:var(--white); min-width:0; }
.ds-h-row input:focus, .ds-h-row select:focus { outline:none; border-color:var(--ds-accent); }

/* Party */
.ds-party-wrap { position:relative; flex:1; min-width:120px; }
.ds-party-wrap input { width:100%; padding:4px 6px 4px 24px; border-radius:5px; border:1.5px solid var(--gray-200); font-size:.76rem; background:var(--white); }
.ds-party-wrap input:focus { outline:none; border-color:var(--ds-accent); }
.ds-party-wrap .si { position:absolute; left:6px; top:50%; transform:translateY(-50%); color:var(--gray-400); font-size:.72rem; }
.ds-party-sel { display:flex; align-items:center; gap:4px; padding:3px 8px; border-radius:5px; background:var(--ds-accent-light); border:1.5px solid var(--ds-accent); font-size:.74rem; font-weight:600; color:var(--gray-800); }
.ds-party-sel .rx { cursor:pointer; color:var(--ds-danger); opacity:.5; margin-left:auto; }
.ds-party-sel .rx:hover { opacity:1; }
.ds-party-dd { position:absolute; top:100%; left:0; right:0; z-index:100; background:var(--white); border:1px solid var(--gray-200); border-radius:6px; box-shadow:0 6px 20px rgba(0,0,0,.12); max-height:180px; overflow-y:auto; display:none; }
.ds-party-dd.show { display:block; }
.ds-party-dd .opt { padding:6px 10px; cursor:pointer; font-size:.76rem; border-bottom:1px solid var(--gray-100); transition:background .1s; }
.ds-party-dd .opt:hover { background:var(--gray-50); }
.ds-party-dd .opt:last-child { border-bottom:none; }
.ds-party-dd .opt b { color:var(--gray-700); }
.ds-party-dd .opt small { color:var(--gray-400); display:block; font-size:.65rem; }

.ds-ship-fld { display:none; }
.ds-ship-fld.vis { display:flex; }

/* ── Line Items ── */
.ds-lines { flex:1; overflow-y:auto; min-height:60px; }
.ds-line {
    display:flex; align-items:center; gap:6px; padding:4px 10px;
    border-bottom:1px solid var(--gray-200); cursor:pointer;
    transition:background .1s; font-size:.74rem;
}
.ds-line:hover { background:var(--gray-50); }
.ds-line .ln-img-btn {
    width:22px; height:22px; border-radius:4px; border:1px solid var(--gray-200);
    background:var(--gray-100); display:flex; align-items:center; justify-content:center;
    cursor:pointer; flex-shrink:0; font-size:.7rem; color:var(--gray-400); overflow:hidden;
}
.ds-line .ln-img-btn:hover { border-color:var(--ds-accent); color:var(--ds-accent); }
.ds-line .ln-img-btn img { width:100%; height:100%; object-fit:cover; border-radius:3px; }
.ds-line .ln-code {
    font-size:.64rem; color:var(--gray-500); font-weight:600;
    min-width:70px; max-width:90px; flex-shrink:0;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.ds-line .ln-name {
    flex:1; min-width:0; font-weight:600; color:var(--gray-700);
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.ds-line .ln-name:hover { white-space:normal; word-break:break-word; }
.ds-line .ln-detail {
    font-size:.62rem; color:var(--gray-400);
}
.ds-line .ln-qty {
    background:var(--gray-100); border-radius:3px; padding:1px 6px;
    font-size:.72rem; font-weight:600; color:var(--gray-600); text-align:center; flex-shrink:0;
}
.ds-line .ln-total {
    font-size:.78rem; font-weight:700; color:var(--gray-800);
    min-width:55px; text-align:right; flex-shrink:0;
}
.ds-line .ln-rm {
    color:var(--ds-danger); opacity:.3; cursor:pointer; font-size:.8rem;
    transition:opacity .15s; flex-shrink:0;
}
.ds-line .ln-rm:hover { opacity:1; }

/* Lines header */
.ds-lines-hdr {
    display:flex; align-items:center; gap:6px; padding:3px 10px;
    font-size:.58rem; font-weight:700; color:var(--gray-400);
    text-transform:uppercase; letter-spacing:.3px;
    border-bottom:1px solid var(--gray-200); background:var(--gray-50); flex-shrink:0;
}
.ds-lines-hdr .lh-img { width:22px; flex-shrink:0; }
.ds-lines-hdr .lh-code { min-width:70px; max-width:90px; flex-shrink:0; }
.ds-lines-hdr .lh-name { flex:1; }
.ds-lines-hdr .lh-qty { width:30px; text-align:center; flex-shrink:0; }
.ds-lines-hdr .lh-total { min-width:55px; text-align:right; flex-shrink:0; }
.ds-lines-hdr .lh-x { width:16px; flex-shrink:0; }

.ds-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--gray-400); padding:20px; font-size:.82rem; }
.ds-empty i { font-size:2rem; margin-bottom:6px; }

/* Footer */
.ds-footer { border-top:2px solid var(--gray-200); background:var(--gray-50); flex-shrink:0; padding:6px 10px; position:sticky; bottom:0; z-index:10; }
.ds-totals-grid { display:grid; grid-template-columns:1fr 1fr; gap:0 16px; font-size:.72rem; color:var(--gray-600); }
.ds-totals-grid .trow { display:flex; justify-content:space-between; padding:1px 0; }
.ds-totals-grid .trow.grand { grid-column:1/3; font-size:.92rem; font-weight:700; color:var(--gray-800); border-top:2px solid var(--gray-300); margin-top:2px; padding-top:3px; }
.ds-notes-inline { margin-top:4px; }
.ds-notes-inline textarea { width:100%; border-radius:4px; border:1px solid var(--gray-200); font-size:.72rem; padding:3px 6px; resize:none; height:28px; background:var(--white); }
.ds-notes-inline textarea:focus { outline:none; border-color:var(--ds-accent); }
.ds-actions-row { display:flex; gap:6px; margin-top:4px; }
.ds-actions-row .btn { padding:7px 8px; font-weight:700; font-size:.78rem; border-radius:6px; }

/* Resizer */
.ds-resizer { width:5px; cursor:col-resize; background:transparent; border-left:1px solid var(--gray-200); flex-shrink:0; transition:background .15s; }
.ds-resizer:hover, .ds-resizer.dragging { background:var(--ds-accent-light); border-left-color:var(--ds-accent); }

/* ── RIGHT: Products Panel ── */
.ds-products { flex:1; display:flex; flex-direction:column; overflow:hidden; background:var(--light-bg,#f8f9fa); min-width:300px; }
.ds-prod-top { padding:6px 10px; border-bottom:1px solid var(--gray-200); background:var(--white); display:flex; align-items:center; gap:8px; flex-shrink:0; }
.ds-prod-top .sb { flex:1; min-width:140px; position:relative; }
.ds-prod-top .sb input { width:100%; padding:6px 8px 6px 28px; border-radius:6px; border:1.5px solid var(--gray-200); font-size:.8rem; background:var(--gray-50); }
.ds-prod-top .sb input:focus { outline:none; border-color:var(--ds-accent); background:var(--white); }
.ds-prod-top .sb .si { position:absolute; left:8px; top:50%; transform:translateY(-50%); color:var(--gray-400); font-size:.82rem; }
.ds-prod-top select { min-width:130px; max-width:180px; padding:6px 8px; border-radius:6px; border:1.5px solid var(--gray-200); font-size:.76rem; background:var(--white); cursor:pointer; }
.ds-prod-top select:focus { outline:none; border-color:var(--ds-accent); }
.ds-vt { display:flex; gap:3px; }
.ds-vt .btn { padding:4px 8px; border-radius:5px; font-size:.7rem; }

/* ── Products list header ── */
.ds-pl-hdr {
    display:none;
    grid-template-columns:36px 100px 1fr 78px 74px;
    gap:0; padding:5px 10px 5px 14px;
    border-bottom:2px solid var(--cl-border,#e5e7eb);
    background:var(--cl-surface-alt,#f8f9fa);
    font-size:.6rem; font-weight:700;
    color:var(--gray-400); text-transform:uppercase; letter-spacing:.06em;
}
.ds-pl-hdr span { padding:0 5px; }
.ds-pl-hdr span:nth-child(4), .ds-pl-hdr span:nth-child(5) { text-align:right; padding-right:6px; }
.ds-products.list-mode .ds-pl-hdr { display:grid; }
body.theme-dark .ds-pl-hdr { background:#162033!important; border-bottom-color:#2d4060!important; color:#64748b!important; }

/* ── Products grid container ── */
.ds-pgrid { flex:1; overflow-y:auto; padding:8px; display:grid; grid-template-columns:repeat(auto-fill,minmax(195px,1fr)); gap:6px; align-content:start; }
.ds-pgrid.list-v { display:block; padding:0; }

/* ─────────────────────────────────────
   Product card — GRID / THUMBNAIL VIEW
   Layout: 3px top stripe + abs image left
   + code/name/badge/price in flex column
───────────────────────────────────── */
.ds-pc {
    background:var(--white);
    border-radius:8px;
    border:1px solid var(--cl-border, #e5e7eb);
    cursor:pointer;
    transition:border-color .13s, box-shadow .13s, transform .1s;
    display:flex;
    flex-direction:column;
    position:relative;
    overflow:hidden;
    /* left space = 7px edge + 34px img + 7px gap */
    padding:10px 9px 9px 48px;
    min-height:100px;
}
.ds-pc:hover {
    border-color:var(--ds-accent);
    box-shadow:0 4px 14px rgba(79,70,229,.15);
    transform:translateY(-1px);
}
.ds-pc:active { transform:scale(.98); }

/* Top colour stripe */
.ds-pc::after {
    content:''; position:absolute; top:0; left:0; right:0;
    height:3px; border-radius:8px 8px 0 0; z-index:1; pointer-events:none;
}
.ds-pc[data-color="green"]::after   { background:#10b981; }
.ds-pc[data-color="blue"]::after    { background:#3b82f6; }
.ds-pc[data-color="yellow"]::after  { background:#f59e0b; }
.ds-pc[data-color="red"]::after     { background:#ef4444; }
.ds-pc[data-color="purple"]::after  { background:#8b5cf6; }
.ds-pc[data-color="pink"]::after    { background:#ec4899; }
.ds-pc[data-color="teal"]::after    { background:#14b8a6; }
.ds-pc[data-color="orange"]::after  { background:#f97316; }

/* Product image — absolute, left column */
.ds-pc .pi {
    position:absolute; left:7px; top:12px;
    width:34px; height:34px;
    object-fit:cover;
    border-radius:5px;
    border:1px solid var(--cl-border-light, #f1f5f9);
    background:var(--gray-100);
}

/* No-image card: collapse the reserved left space */
.ds-pc:not(:has(.pi)) { padding-left:10px; }

/* Code line */
.ds-pc .pk {
    font-size:.58rem; font-weight:700;
    color:var(--gray-400);
    font-family:ui-monospace,monospace;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    padding-right:36px; /* room for stock badge */
    margin:0 0 2px;
    line-height:1.2;
}

/* Product name */
.ds-pc .pn {
    font-size:.71rem; font-weight:500;
    color:var(--gray-700);
    display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
    overflow:hidden; line-height:1.35;
    flex:1; margin:0;
    word-break:break-word;
}

/* Variant / Template badge */
.ds-pc .pv {
    font-size:.56rem; font-weight:700; color:#0369a1;
    background:rgba(14,165,233,.1); border:1px solid rgba(14,165,233,.2);
    border-radius:999px; padding:1px 7px; margin:4px 0 0;
    max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    display:block;
}
.ds-pc .pt-flag {
    font-size:.56rem; font-weight:700; color:#d97706;
    background:rgba(245,158,11,.1); border:1px solid rgba(245,158,11,.2);
    border-radius:999px; padding:1px 6px; margin:4px 0 0; width:fit-content;
}

/* Price — always visible at bottom */
.ds-pc .pp {
    font-size:.82rem; font-weight:700;
    color:var(--ds-accent);
    margin-top:auto;
    padding-top:5px;
    border-top:1px solid var(--gray-100);
    line-height:1;
}

/* Stock badge — absolute top-right */
.ds-pc .ps {
    position:absolute; top:9px; right:8px;
    font-size:.57rem; font-weight:700;
    padding:2px 6px; border-radius:4px; line-height:1.3;
    background:rgba(16,185,129,.1); color:#059669;
    border:1px solid rgba(16,185,129,.22);
    white-space:nowrap;
}
.ds-pc .ps.low {
    background:rgba(239,68,68,.08); color:#dc2626;
    border-color:rgba(239,68,68,.22);
}

/* ── Dark mode overrides ── */
body.theme-dark .ds-pc { background:#1a2740!important; border-color:#2d4060; }
body.theme-dark .ds-pc:hover { border-color:var(--ds-accent); background:#1e3050!important; }
body.theme-dark .ds-pc .pn { color:#d1dde9; }
body.theme-dark .ds-pc .pk { color:#5a7a9a; }
body.theme-dark .ds-pc .pp { color:#818cf8; border-top-color:#263352; }
body.theme-dark .ds-pc .pi { border-color:#2d4060; }
body.theme-dark .ds-pc .ps { background:rgba(16,185,129,.13); color:#34d399; border-color:rgba(16,185,129,.3); }
body.theme-dark .ds-pc .ps.low { background:rgba(239,68,68,.12); color:#f87171; border-color:rgba(239,68,68,.28); }
body.theme-dark .ds-pc .pv { background:rgba(14,165,233,.12); border-color:rgba(14,165,233,.28); }
body.theme-dark .ds-pc .pt-flag { background:rgba(245,158,11,.12); border-color:rgba(245,158,11,.28); }

/* Template dimmed */
.ds-pc.is-template { opacity:.65; cursor:not-allowed; }
.ds-pc.is-template:hover { border-color:var(--gray-200)!important; box-shadow:none; transform:none; }

/* Custom item add-button card */
.ds-pc.cust-item {
    border:1.5px dashed var(--gray-300); border-radius:8px;
    align-items:center; justify-content:center; text-align:center;
    padding:14px 8px!important;
    background:transparent!important;
    flex-direction:column;
}
.ds-pc.cust-item:hover { border-color:var(--ds-accent)!important; background:var(--ds-accent-light)!important; }
.ds-pc.cust-item::after { display:none; }
.ds-pc.cust-item i { font-size:1.4rem; color:var(--gray-400); margin-bottom:4px; display:block; }
.ds-pc.cust-item .pn { color:var(--gray-500); flex:0; font-size:.74rem; display:block; -webkit-line-clamp:unset; }
body.theme-dark .ds-pc.cust-item { border-color:#2d4060; }
body.theme-dark .ds-pc.cust-item:hover { border-color:var(--ds-accent)!important; background:rgba(79,70,229,.12)!important; }

/* ─────────────────────────────────────
   Product cards — LIST VIEW ROWS
   Columns: [img 36px] [code 100px] [name 1fr] [price 78px] [stock 74px]
───────────────────────────────────── */
.ds-pgrid.list-v .ds-pc {
    min-height:0;
    display:grid;
    grid-template-columns:36px 100px 1fr 78px 74px;
    grid-template-rows:auto auto;
    align-items:center;
    gap:0;
    margin:0; padding:7px 10px 7px 14px;
    border-radius:0; border:0;
    border-bottom:1px solid var(--cl-border-light,#f1f5f9);
    box-shadow:none!important; transform:none!important;
    background:var(--white);
    transition:background .08s;
    min-height:38px;
}
.ds-pgrid.list-v .ds-pc:hover { background:var(--gray-50)!important; }
body.theme-dark .ds-pgrid.list-v .ds-pc { background:#1a2740; border-bottom-color:#1e2f4a; }
body.theme-dark .ds-pgrid.list-v .ds-pc:hover { background:#1e304e!important; }
/* Remove top accent in list mode */
.ds-pgrid.list-v .ds-pc::after { display:none; }
/* Left category accent dot in list mode */
.ds-pgrid.list-v .ds-pc::before {
    content:''; display:block;
    position:absolute; left:0; top:0; bottom:0; width:3px;
    border-radius:0;
}
.ds-pgrid.list-v .ds-pc[data-color="green"]::before  { background:#10b981; }
.ds-pgrid.list-v .ds-pc[data-color="blue"]::before   { background:#3b82f6; }
.ds-pgrid.list-v .ds-pc[data-color="yellow"]::before { background:#f59e0b; }
.ds-pgrid.list-v .ds-pc[data-color="red"]::before    { background:#ef4444; }
.ds-pgrid.list-v .ds-pc[data-color="purple"]::before { background:#8b5cf6; }
.ds-pgrid.list-v .ds-pc[data-color="pink"]::before   { background:#ec4899; }
.ds-pgrid.list-v .ds-pc[data-color="teal"]::before   { background:#14b8a6; }
.ds-pgrid.list-v .ds-pc[data-color="orange"]::before { background:#f97316; }
/* Image cell */
.ds-pgrid.list-v .ds-pc .pi {
    grid-column:1; grid-row:1/3;
    width:28px; height:28px; margin:0;
    border-radius:5px; border:1px solid var(--cl-border-light,#f1f5f9);
    object-fit:cover; align-self:center;
    background:var(--gray-100);
}
/* No-image placeholder in list */
.ds-pgrid.list-v .ds-pc:not(:has(.pi))::after {
    content:''; display:block;
    grid-column:1; grid-row:1/3;
    width:28px; height:28px;
    background:var(--gray-100); border-radius:5px;
    border:1px solid var(--cl-border-light,#f1f5f9);
    position:static; /* override ::after from grid mode */
}
/* Code cell */
.ds-pgrid.list-v .ds-pc .pk {
    grid-column:2; grid-row:1;
    padding:0 7px; margin:0;
    font-size:.66rem; font-weight:700; color:var(--gray-500);
    font-family:ui-monospace,monospace; letter-spacing:.01em;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
body.theme-dark .ds-pgrid.list-v .ds-pc .pk { color:#5a7090; }
/* Name cell */
.ds-pgrid.list-v .ds-pc .pn {
    grid-column:3; grid-row:1;
    padding:0 7px; margin:0;
    font-size:.76rem; font-weight:600; color:var(--gray-800);
    -webkit-line-clamp:1; display:-webkit-box;
    -webkit-box-orient:vertical; overflow:hidden; line-height:1.3;
}
body.theme-dark .ds-pgrid.list-v .ds-pc .pn { color:#c8d8e8; }
/* Price cell */
.ds-pgrid.list-v .ds-pc .pp {
    grid-column:4; grid-row:1;
    justify-self:end; margin:0; padding:0 4px 0 0; border:none;
    font-size:.78rem; font-weight:700; color:var(--ds-accent);
}
body.theme-dark .ds-pgrid.list-v .ds-pc .pp { color:#818cf8; }
/* Stock badge cell */
.ds-pgrid.list-v .ds-pc .ps {
    position:static;
    grid-column:5; grid-row:1;
    justify-self:end; margin:0;
    font-size:.6rem; padding:2px 7px; border-radius:4px;
    white-space:nowrap;
}
/* Variant/Template badge — sub-row */
.ds-pgrid.list-v .ds-pc .pv {
    grid-column:3; grid-row:2;
    margin:2px 0 0 7px; font-size:.54rem; padding:1px 7px;
    border-radius:999px;
    width:fit-content; max-width:90%;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.ds-pgrid.list-v .ds-pc .pt-flag {
    grid-column:3; grid-row:2;
    margin:2px 0 0 7px; font-size:.54rem; padding:1px 7px;
    border-radius:999px;
    width:fit-content; max-width:90%;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
/* Custom item row */
.ds-pgrid.list-v .ds-pc.cust-item {
    border:1px dashed var(--gray-200);
    border-bottom:1px dashed var(--gray-200)!important;
    background:transparent!important;
    text-align:left;
}
.ds-pgrid.list-v .ds-pc.cust-item:hover { background:var(--ds-accent-light)!important; }
.ds-pgrid.list-v .ds-pc.cust-item i { margin:0; grid-column:1; grid-row:1; font-size:.9rem; color:var(--gray-400); display:block; }
.ds-pgrid.list-v .ds-pc.cust-item .pn { color:var(--gray-500); -webkit-line-clamp:1; }

/* Custom item — grid */
.ds-pc.cust-item {
    border:1.5px dashed var(--gray-300); border-radius:8px;
    align-items:center; justify-content:center; text-align:center;
    min-height:88px; padding:8px;
    background:transparent;
}
.ds-pc.cust-item:hover { border-color:var(--ds-accent); background:var(--ds-accent-light)!important; }
.ds-pc.cust-item::after { display:none; }
.ds-pc.cust-item i { font-size:1.3rem; color:var(--gray-400); margin-bottom:3px; }
.ds-pc.cust-item .pn { color:var(--gray-500); padding-left:0; flex:0; font-size:.72rem; }

/* Modals */
.ds-emod .modal-content { border-radius:12px; border:none; }
.ds-emod .modal-header { border-bottom:1px solid var(--gray-200); padding:12px 16px; }
.ds-emod .modal-body { padding:16px; }
.ds-emod .form-label { font-size:.74rem; font-weight:600; }

/* Image preview modal */
.ds-img-modal .modal-body { text-align:center; padding:10px; }
.ds-img-modal .modal-body img { max-width:100%; max-height:60vh; border-radius:8px; }

/* Toast */
.ds-toast { position:fixed; top:16px; right:16px; z-index:3000; padding:12px 20px; border-radius:10px; font-weight:600; font-size:.84rem; box-shadow:0 6px 24px rgba(0,0,0,.18); display:none; color:#fff; transition:all .3s; }
.ds-toast.success { background:var(--ds-success); display:block; }
.ds-toast.error { background:var(--ds-danger); display:block; }

/* Offcanvas */
.ds-oc { width:480px!important; max-width:90vw; }
.ds-oc .offcanvas-header { border-bottom:1px solid var(--gray-200); background:var(--gray-50); padding:10px 14px; }
.ds-oc .offcanvas-body { padding:0; }
.ds-oc-tabs { display:flex; border-bottom:1px solid var(--gray-200); background:var(--gray-50); }
.ds-oc-tab { flex:1; padding:7px 4px; text-align:center; font-size:.7rem; font-weight:700; border:none; background:transparent; color:var(--gray-500); cursor:pointer; border-bottom:2px solid transparent; }
.ds-oc-tab.active { color:var(--ds-accent); border-bottom-color:var(--ds-accent); }
.ds-oc-list { overflow-y:auto; max-height:calc(100vh - 140px); }
.ds-oc-row { display:flex; align-items:center; padding:8px 14px; border-bottom:1px solid var(--gray-100); gap:8px; transition:background .1s; }
.ds-oc-row:hover { background:var(--gray-50); }
.ds-oc-row .di { flex:1; min-width:0; }
.ds-oc-row .dn { font-size:.78rem; font-weight:700; color:var(--gray-700); }
.ds-oc-row .dm { font-size:.65rem; color:var(--gray-400); }
.ds-oc-row .dt { font-size:.8rem; font-weight:700; color:var(--gray-800); min-width:60px; text-align:right; }
.ds-oc-row .dst { font-size:.6rem; font-weight:700; padding:2px 6px; border-radius:999px; text-transform:uppercase; }
.ds-oc-row .dst.draft { background:rgba(245,158,11,.12); color:#d97706; }
.ds-oc-row .dst.active { background:rgba(16,185,129,.12); color:#059669; }
.ds-oc-row .da .btn { padding:2px 6px; font-size:.66rem; }
.ds-oc-empty { padding:30px; text-align:center; color:var(--gray-400); }

/* Responsive */
@media(max-width:1100px){ .ds-doc { width:380px; min-width:300px; } }
@media(max-width:900px){
    .ds-wrap { flex-direction:column; height:auto; }
    .ds-doc { width:100%!important; max-width:100%; height:auto; max-height:50vh; border-right:none; border-bottom:1px solid var(--gray-200); }
    .ds-products { height:50vh; min-width:unset; }
    .ds-resizer { display:none; }
}
</style>

<!-- ════════════════════════ DOCUMENT STUDIO v4 ════════════════════════ -->
<div class="ds-wrap" id="dsApp">

    <!-- ══ LEFT: Document Panel ══ -->
    <div class="ds-doc" id="dsDocPanel">
        <div class="ds-topbar">
            <div class="ds-type-radios">
                <input type="radio" name="dsType" id="dtQuote" value="quotation" checked onchange="DS.setDocType('quotation')"><label for="dtQuote">Quote</label>
                <input type="radio" name="dsType" id="dtSales" value="sales_order" onchange="DS.setDocType('sales_order')"><label for="dtSales">Sales</label>
                <input type="radio" name="dsType" id="dtRfq" value="purchase_rfq" onchange="DS.setDocType('purchase_rfq')"><label for="dtRfq">RFQ</label>
                <input type="radio" name="dsType" id="dtPo" value="purchase_order" onchange="DS.setDocType('purchase_order')"><label for="dtPo">PO</label>
            </div>
            <button class="btn-icon" onclick="DS.saveDraft()" title="Save as Draft"><i class="bi bi-save"></i></button>
            <button class="btn-icon danger" onclick="DS.clearAll()" title="Clear All"><i class="bi bi-trash"></i></button>
            <button class="btn-icon primary" onclick="DS.openDocsList()" title="Saved Documents"><i class="bi bi-journal-text"></i></button>
        </div>

        <div class="ds-header">
            <div class="ds-h-row">
                <div class="fld ds-party-wrap" id="partyWrap" style="flex:2;">
                    <span id="partyLabel">Customer</span>
                    <div id="partySearchBox">
                        <i class="bi bi-search si"></i>
                        <input type="text" id="partyInput" placeholder="Search customer..." oninput="DS.searchParty(this.value)" onfocus="DS.searchParty(this.value)" autocomplete="off">
                        <div class="ds-party-dd" id="partyDD"></div>
                    </div>
                    <div class="ds-party-sel" id="partySel" style="display:none;">
                        <i class="bi bi-person-circle" id="partyIcon"></i>
                        <span id="partySelName"></span>
                        <span class="rx" onclick="DS.clearParty()"><i class="bi bi-x-circle-fill"></i></span>
                    </div>
                </div>
                <div class="fld" style="flex:0 0 120px;"><span>Date</span><input type="date" id="dsDate" value="<?= date('Y-m-d') ?>"></div>
                <div class="fld" style="flex:0 0 70px;"><span>Curr</span>
                    <select id="dsCurrency">
                        <option value="PKR" <?= ($defaultSalesCurrency ?? 'PKR')==='PKR'?'selected':'' ?>>PKR</option>
                        <option value="USD" <?= ($defaultSalesCurrency ?? '')==='USD'?'selected':'' ?>>USD</option>
                        <option value="EUR">EUR</option><option value="GBP">GBP</option>
                        <option value="AED">AED</option><option value="CNY">CNY</option>
                    </select>
                </div>
                <div class="fld ds-ship-fld" id="dsShipFld" style="flex:0 0 80px;">
                    <span><i class="bi bi-truck"></i> Ship</span>
                    <input type="number" id="dsShipping" min="0" step="any" value="0" placeholder="0" oninput="DS.recalcTotals()">
                </div>
            </div>
        </div>

        <!-- Lines header -->
        <div class="ds-lines-hdr">
            <span class="lh-img"></span>
            <span class="lh-code">Code</span>
            <span class="lh-name">Item</span>
            <span class="lh-qty">Qty</span>
            <span class="lh-total">Total</span>
            <span class="lh-x"></span>
        </div>

        <!-- Line items -->
        <div class="ds-lines" id="dsLines"></div>

        <!-- Footer -->
        <div class="ds-footer">
            <div class="ds-totals-grid">
                <div class="trow"><span>Subtotal</span><span id="dsSubtotal">0.00</span></div>
                <div class="trow"><span>Discount</span><span id="dsDiscount">0.00</span></div>
                <div class="trow"><span>Tax</span><span id="dsTax">0.00</span></div>
                <div class="trow" id="dsShipTR" style="display:none;"><span>Shipping</span><span id="dsShipVal">0.00</span></div>
                <div class="trow grand"><span>Total</span><span id="dsTotal">0.00</span></div>
            </div>
            <div class="ds-notes-inline"><textarea id="dsNotes" placeholder="Notes / instructions..." rows="1"></textarea></div>
            <div class="ds-actions-row">
                <button class="btn btn-outline-secondary" onclick="DS.clearAll()" style="flex:0 0 auto; padding:7px 12px;"><i class="bi bi-arrow-counterclockwise"></i></button>
                <button class="btn btn-success" id="dsSaveBtn" onclick="DS.save()" style="flex:1;"><i class="bi bi-check2-circle me-1"></i><span id="dsSaveTxt">Create Quotation</span></button>
            </div>
        </div>
    </div>

    <div class="ds-resizer" id="dsResizer" title="Drag to resize"></div>

    <!-- ══ RIGHT: Products Panel ══ -->
    <div class="ds-products" id="dsProductsPanel">
        <div class="ds-prod-top">
            <div class="sb">
                <i class="bi bi-search si"></i>
                <input type="text" id="dsSearch" placeholder="Search products by name, code, barcode..." oninput="DS.filterProducts(this.value)" autofocus>
            </div>
            <select id="dsCatSel" onchange="DS.selectCategory(this.value)">
                <option value="all">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= esc($cat['id']) ?>"><?= esc($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="ds-vt">
                <button type="button" class="btn btn-sm btn-outline-secondary active" id="dsGridBtn" onclick="DS.setView('grid')" title="Grid"><i class="bi bi-grid-3x3-gap"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="dsListBtn" onclick="DS.setView('list')" title="List"><i class="bi bi-list-ul"></i></button>
            </div>
        </div>

        <div class="ds-pl-hdr" id="dsPLH">
            <span></span>
            <span>Code</span>
            <span>Item / Name</span>
            <span style="text-align:right;">Price</span>
            <span style="text-align:right;">Stock</span>
        </div>

        <div class="ds-pgrid" id="dsPGrid">
            <?php
            $colors = ['green','blue','yellow','red','purple','pink','teal','orange'];
            $ci = 0;
            foreach (($catalogItems ?? $products) as $p):
                $salePrice = (float)($p['sale_price'] ?? 0);
                $costPrice = (float)($p['cost_price'] ?? ($p['unit_cost'] ?? 0));
                $stock     = (float)($p['stock'] ?? $p['current_stock'] ?? 0);
                $color     = $colors[$ci % count($colors)]; $ci++;
                $isTemplate = !empty($p['is_template']);
                $imgUrl    = $p['image_url'] ?? '';
            ?>
                <div class="ds-pc <?= $isTemplate ? 'is-template' : '' ?>"
                     data-color="<?= $color ?>"
                     data-id="<?= esc($p['product_id'] ?? $p['id']) ?>"
                     data-product-id="<?= esc($p['product_id'] ?? $p['id']) ?>"
                     data-variant-id="<?= esc($p['variant_id'] ?? '') ?>"
                     data-is-variant="<?= !empty($p['variant_id']) ? '1' : '0' ?>"
                     data-is-template="<?= $isTemplate ? '1' : '0' ?>"
                     data-variant-name="<?= esc($p['variant_name'] ?? '') ?>"
                     data-name="<?= esc($p['name']) ?>"
                     data-code="<?= esc($p['code'] ?? ($p['sku'] ?? '')) ?>"
                     data-barcode="<?= esc($p['barcode'] ?? '') ?>"
                     data-sale-price="<?= esc($salePrice) ?>"
                     data-cost-price="<?= esc($costPrice) ?>"
                     data-unit="<?= esc($p['unit'] ?? 'pcs') ?>"
                     data-stock="<?= esc($stock) ?>"
                     data-category="<?= esc($p['category_id'] ?? '') ?>"
                     data-img="<?= esc($imgUrl) ?>"
                     onclick="DS.addProduct(this)">
                    <?php
                        // For variant products: show base name + actual variant label separately
                        $variantLabel = '';
                        $displayName  = (string)($p['name'] ?? '');
                        if (!empty($p['variant_id']) && !empty($p['variant_name'])) {
                            $variantLabel = (string)$p['variant_name'];
                            $suffix = ' — ' . $variantLabel;
                            if (str_ends_with($displayName, $suffix)) {
                                $displayName = substr($displayName, 0, -strlen($suffix));
                            }
                        }
                    ?>
                    <?php if ($imgUrl): ?>
                        <img class="pi" src="<?= esc($imgUrl) ?>" alt="" loading="lazy" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <span class="pk"><?= esc($p['code'] ?? ($p['sku'] ?? '')) ?></span>
                    <span class="pn"><?= esc($displayName) ?></span>
                    <?php if ($variantLabel !== ''): ?>
                        <span class="pv"><?= esc($variantLabel) ?></span>
                    <?php elseif ($isTemplate): ?>
                        <span class="pt-flag"><i class="bi bi-layers-half"></i> Template</span>
                    <?php endif; ?>
                    <span class="pp" data-sale="<?= number_format($salePrice, 2) ?>" data-cost="<?= number_format($costPrice, 2) ?>"><?= number_format($salePrice, 2) ?></span>
                    <span class="ps <?= $stock < 5 ? 'low' : '' ?>">QTY <?= number_format($stock, 0) ?></span>
                </div>
            <?php endforeach; ?>

            <div class="ds-pc cust-item" onclick="DS.addCustomItem()">
                <i class="bi bi-pencil-square"></i>
                <span class="pn">Custom Item</span>
            </div>
        </div>
    </div>
</div>

<!-- Offcanvas -->
<div class="offcanvas offcanvas-end ds-oc" tabindex="-1" id="dsOC">
    <div class="offcanvas-header">
        <h6 class="offcanvas-title"><i class="bi bi-journal-text me-2"></i>Saved Documents</h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="ds-oc-tabs">
            <button class="ds-oc-tab active" onclick="DS.switchDocsTab('quotation',this)">Quotes</button>
            <button class="ds-oc-tab" onclick="DS.switchDocsTab('sales_order',this)">Sales</button>
            <button class="ds-oc-tab" onclick="DS.switchDocsTab('purchase_rfq',this)">RFQs</button>
            <button class="ds-oc-tab" onclick="DS.switchDocsTab('purchase_order',this)">POs</button>
        </div>
        <div class="ds-oc-list" id="dsOCList"><div class="ds-oc-empty"><i class="bi bi-inbox d-block" style="font-size:1.8rem;"></i>Click a tab to load</div></div>
    </div>
</div>

<!-- Edit Line Modal -->
<div class="modal fade ds-emod" id="dsEditModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Line</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" onkeydown="if(event.key==='Enter'){event.preventDefault();DS.saveEditLine();}">
                <div class="mb-2"><label class="form-label">Description</label><input type="text" class="form-control form-control-sm" id="editName"></div>
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label">Quantity</label><input type="number" class="form-control form-control-sm" id="editQty" min="0.01" step="any"></div>
                    <div class="col-6"><label class="form-label">Unit Price</label><input type="number" class="form-control form-control-sm" id="editPrice" min="0" step="any"></div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label">Discount</label><input type="number" class="form-control form-control-sm" id="editDisc" min="0" step="any" value="0"></div>
                    <div class="col-6"><label class="form-label">Tax %</label><input type="number" class="form-control form-control-sm" id="editTax" min="0" step="any" value="0"></div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-danger btn-sm flex-fill" onclick="DS.deleteEditLine()"><i class="bi bi-trash me-1"></i>Remove</button>
                    <button class="btn btn-primary btn-sm flex-fill" onclick="DS.saveEditLine()"><i class="bi bi-check me-1"></i>Update</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Item Modal -->
<div class="modal fade ds-emod" id="dsCustomModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Custom Item</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">Item Name</label><input type="text" class="form-control form-control-sm" id="customName" placeholder="e.g. Custom Service"></div>
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label">Quantity</label><input type="number" class="form-control form-control-sm" id="customQty" min="1" step="any" value="1"></div>
                    <div class="col-6"><label class="form-label">Unit Price</label><input type="number" class="form-control form-control-sm" id="customPrice" min="0" step="any" placeholder="0.00"></div>
                </div>
                <button class="btn btn-primary btn-sm w-100 mt-2" onclick="DS.confirmCustomItem()"><i class="bi bi-plus me-1"></i>Add to Document</button>
            </div>
        </div>
    </div>
</div>

<!-- Image Preview Modal -->
<div class="modal fade ds-img-modal" id="dsImgModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2"><h6 class="modal-title" id="dsImgTitle">Product Image</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><img id="dsImgPreview" src="" alt="Product Image"></div>
        </div>
    </div>
</div>

<div class="ds-toast" id="dsToast"></div>

<script>
const DS = {
    docType: 'quotation', lines: [], priceMode: 'sale', productView: 'grid',
    party: null, partySelections: { customer: null, vendor: null },
    activeCategory: 'all', searchQuery: '', editIdx: -1, searchTimer: null,
    docsListType: 'quotation', BASE: window.APP_BASE || '',
    editId: null, editDocType: null, editQuoteNumber: null,

    docConfig: {
        quotation:      { party:'customer', label:'Customer', saveLabel:'Create Quotation',     priceDefault:'sale', endpoint:'save-quotation',      hasShipping:true },
        sales_order:    { party:'customer', label:'Customer', saveLabel:'Create Sales Order',    priceDefault:'sale', endpoint:'save-sales-order',    hasShipping:true },
        purchase_rfq:   { party:'vendor',   label:'Vendor',   saveLabel:'Create RFQ',            priceDefault:'cost', endpoint:'save-rfq',            hasShipping:false },
        purchase_order: { party:'vendor',   label:'Vendor',   saveLabel:'Create Purchase Order', priceDefault:'cost', endpoint:'save-purchase-order', hasShipping:false },
    },

    getCards() { return Array.from(document.querySelectorAll('.ds-pc:not(.cust-item)')); },

    /* ═══════ Doc Type Switch ═══════ */
    setDocType(type) {
        this.docType = type;
        const cfg = this.docConfig[type];
        document.getElementById('partyLabel').textContent = cfg.label;
        document.getElementById('partyInput').placeholder = 'Search ' + cfg.label.toLowerCase() + '...';
        document.getElementById('dsSaveTxt').textContent = cfg.saveLabel;
        this.priceMode = cfg.priceDefault;
        this.refreshPrices();
        const sf = document.getElementById('dsShipFld'), str = document.getElementById('dsShipTR');
        if (cfg.hasShipping) { sf.classList.add('vis'); str.style.display=''; }
        else { sf.classList.remove('vis'); str.style.display='none'; }
        this.party = this.partySelections[cfg.party] ? { ...this.partySelections[cfg.party] } : null;
        this.renderParty();
        document.getElementById('dsCurrency').value = cfg.party === 'vendor'
            ? '<?= esc($defaultPurchaseCurrency ?? "PKR") ?>'
            : '<?= esc($defaultSalesCurrency ?? "USD") ?>';
        this.applyFilters(); this.renderLines();
    },

    refreshPrices() {
        document.querySelectorAll('.ds-pc .pp').forEach(el => {
            el.textContent = this.priceMode === 'cost' ? el.dataset.cost : el.dataset.sale;
        });
    },

    /* ═══════ Product View ═══════ */
    setView(v) {
        this.productView = v === 'list' ? 'list' : 'grid';
        const g = document.getElementById('dsPGrid'), p = document.getElementById('dsProductsPanel');
        g && g.classList.toggle('list-v', this.productView === 'list');
        p && p.classList.toggle('list-mode', this.productView === 'list');
        document.getElementById('dsGridBtn').classList.toggle('active', this.productView === 'grid');
        document.getElementById('dsListBtn').classList.toggle('active', this.productView === 'list');
        try { localStorage.setItem('ds_view', this.productView); } catch(_){}
    },

    initResize() {
        const panel = document.getElementById('dsDocPanel'), resizer = document.getElementById('dsResizer');
        if (!panel || !resizer) return;
        try { const s = parseInt(localStorage.getItem('ds_doc_w')||'',10); if(!isNaN(s)&&s>=340&&s<=560) panel.style.width=s+'px'; } catch(_){}
        let drag = false;
        const onMove = e => { if(!drag||innerWidth<=900) return; panel.style.width=Math.max(340,Math.min(560,e.clientX))+'px'; };
        const onUp = () => { if(!drag) return; drag=false; resizer.classList.remove('dragging'); const w=parseInt(panel.style.width||'',10); if(!isNaN(w)) try{localStorage.setItem('ds_doc_w',String(w))}catch(_){} removeEventListener('mousemove',onMove); removeEventListener('mouseup',onUp); };
        resizer.addEventListener('mousedown', e => { if(innerWidth<=900) return; drag=true; resizer.classList.add('dragging'); e.preventDefault(); addEventListener('mousemove',onMove); addEventListener('mouseup',onUp); });
    },

    /* ═══════ Category / Filter ═══════ */
    selectCategory(id) { this.activeCategory = id; this.applyFilters(); },
    filterProducts(q) { this.searchQuery = (q||'').toLowerCase().trim(); this.applyFilters(); },
    applyFilters() {
        this.getCards().forEach(c => {
            const catOk = this.activeCategory === 'all' || c.dataset.category == this.activeCategory;
            if (!catOk) { c.style.display = 'none'; return; }
            const q = this.searchQuery;
            if (!q) { c.style.display = ''; return; }
            const t = [c.dataset.name, c.dataset.code, c.dataset.barcode, c.dataset.variantName].join(' ').toLowerCase();
            c.style.display = t.includes(q) ? '' : 'none';
        });
    },

    /* ═══════ Party ═══════ */
    searchParty(query) {
        clearTimeout(this.searchTimer);
        const q = query.trim(), dd = document.getElementById('partyDD');
        if (q.length < 1) { dd.classList.remove('show'); return; }
        this.searchTimer = setTimeout(() => {
            const cfg = this.docConfig[this.docType];
            const url = cfg.party === 'customer'
                ? this.BASE + '/document-studio/search-customers?q=' + encodeURIComponent(q)
                : this.BASE + '/document-studio/search-vendors?q=' + encodeURIComponent(q);
            fetch(url, { headers:{'Accept':'application/json'} })
            .then(r => r.json()).then(data => {
                if (!data||!data.length) { dd.innerHTML='<div class="opt"><small>No results</small></div>'; dd.classList.add('show'); return; }
                dd.innerHTML = data.map(i => {
                    const sub = cfg.party==='customer' ? (i.company||i.code||i.email||'') : (i.contact_person||'');
                    return `<div class="opt" onclick="DS.selectParty(${i.id},'${this.escJs(i.name||'')}','${cfg.party}')"><b>${this.escHtml(i.name||'')}</b>${sub?'<small>'+this.escHtml(sub)+'</small>':''}</div>`;
                }).join('');
                dd.classList.add('show');
            }).catch(() => dd.classList.remove('show'));
        }, 250);
    },
    selectParty(id, name, type) { this.party={id,name,type}; this.partySelections[type]={id,name,type}; this.renderParty(); },
    renderParty() {
        const sel=document.getElementById('partySel'), box=document.getElementById('partySearchBox');
        const nm=document.getElementById('partySelName'), dd=document.getElementById('partyDD'), ico=document.getElementById('partyIcon');
        if (!this.party) { sel.style.display='none'; box.style.display=''; document.getElementById('partyInput').value=''; dd&&dd.classList.remove('show'); return; }
        box.style.display='none'; sel.style.display='flex'; nm.textContent=this.party.name;
        ico.className = this.party.type==='vendor' ? 'bi bi-building' : 'bi bi-person-circle';
        dd&&dd.classList.remove('show');
    },
    clearParty() { const cfg=this.docConfig[this.docType]; if(cfg&&cfg.party) this.partySelections[cfg.party]=null; this.party=null; this.renderParty(); },

    /* ═══════ Add Product ═══════ */
    addProduct(el) {
        // Block template products
        if (el.dataset.isTemplate === '1') {
            this.toast('This is a template product — add its variants instead', 'error');
            return;
        }

        const id = parseInt(el.dataset.productId||el.dataset.id||'0',10);
        const vid = parseInt(el.dataset.variantId||'0',10)||null;
        const name = el.dataset.name, code = el.dataset.code||'';
        const imgUrl = el.dataset.img||'';
        const sale = parseFloat(el.dataset.salePrice)||0;
        const cost = parseFloat(el.dataset.costPrice)||0;
        const stock = parseFloat(el.dataset.stock)||0;
        const price = this.priceMode==='cost' ? cost : sale;
        const unit = el.dataset.unit||'pcs';
        const key = id+':'+(vid||0);

        const ex = this.lines.find(l => l._key===key && !l.is_custom);
        if (ex) { ex.quantity += 1; }
        else {
            this.lines.push({
                product_id:id, variant_id:vid,
                product_name:name, product_code:code, description:name,
                image_url:imgUrl,
                unit, quantity:1, unit_price:price,
                sale_price:sale, cost_price:cost, stock_qty:stock,
                discount_value:0, discount_type:'fixed', tax_rate:0,
                is_custom:false, _key:key,
            });
        }
        this.renderLines();
        el.style.transform='scale(.94)'; setTimeout(()=>el.style.transform='',150);
    },

    addCustomItem() {
        document.getElementById('customName').value='';
        document.getElementById('customQty').value='1';
        document.getElementById('customPrice').value='';
        new bootstrap.Modal(document.getElementById('dsCustomModal')).show();
    },
    confirmCustomItem() {
        const name=document.getElementById('customName').value.trim();
        const qty=parseFloat(document.getElementById('customQty').value)||1;
        const price=parseFloat(document.getElementById('customPrice').value)||0;
        if (!name) { alert('Enter item name'); return; }
        this.lines.push({
            product_id:null, product_name:name, product_code:'', description:name,
            image_url:'', unit:'pcs', quantity:qty, unit_price:price,
            sale_price:price, cost_price:price, variant_id:null, stock_qty:0,
            discount_value:0, discount_type:'fixed', tax_rate:0,
            is_custom:true, _key:'c:'+Date.now(),
        });
        bootstrap.Modal.getInstance(document.getElementById('dsCustomModal')).hide();
        this.renderLines();
    },

    /* ═══════ Show Image ═══════ */
    showImage(url, name, evt) {
        evt.stopPropagation();
        if (!url) return;
        document.getElementById('dsImgPreview').src = url;
        document.getElementById('dsImgTitle').textContent = name || 'Product Image';
        new bootstrap.Modal(document.getElementById('dsImgModal')).show();
    },

    /* ═══════ Render Lines — single-row layout ═══════ */
    renderLines() {
        const c = document.getElementById('dsLines');
        if (!this.lines.length) {
            c.innerHTML = '<div class="ds-empty" id="dsEmpty"><i class="bi bi-inbox"></i><span>No items yet — pick products from the right panel</span></div>';
            this.recalcTotals();
            return;
        }
        c.innerHTML = this.lines.map((it, i) => {
            const g = it.quantity * it.unit_price, d = it.discount_value||0;
            const taxable = Math.max(0, g-d), tax = (it.tax_rate/100)*taxable, tot = taxable+tax;
            const hasImg = it.image_url && it.image_url.indexOf('no-image') === -1;
            const imgBtn = hasImg
                ? `<span class="ln-img-btn" onclick="DS.showImage('${this.escJs(it.image_url)}','${this.escJs(it.product_name)}',event)" title="View image"><img src="${this.escHtml(it.image_url)}" alt=""></span>`
                : '<span class="ln-img-btn" title="No image"><i class="bi bi-image"></i></span>';
            return `<div class="ds-line" onclick="DS.editLine(${i})" title="${this.escHtml(it.product_name)}">
                ${imgBtn}
                <span class="ln-code">${this.escHtml(it.product_code||'—')}</span>
                <span class="ln-name">${this.escHtml(it.product_name)}</span>
                <span class="ln-qty">x${it.quantity}</span>
                <span class="ln-total">${tot.toFixed(2)}</span>
                <span class="ln-rm" onclick="event.stopPropagation();DS.removeLine(${i})"><i class="bi bi-x-circle"></i></span>
            </div>`;
        }).join('');
        this.recalcTotals();
    },

    editLine(idx) {
        this.editIdx=idx; const it=this.lines[idx]; if(!it) return;
        document.getElementById('editName').value=it.product_name;
        document.getElementById('editQty').value=it.quantity;
        document.getElementById('editPrice').value=it.unit_price;
        document.getElementById('editDisc').value=it.discount_value||0;
        document.getElementById('editTax').value=it.tax_rate||0;
        new bootstrap.Modal(document.getElementById('dsEditModal')).show();
    },
    saveEditLine() {
        const i=this.editIdx; if(i<0||!this.lines[i]) return;
        this.lines[i].product_name=document.getElementById('editName').value.trim()||this.lines[i].product_name;
        this.lines[i].description=this.lines[i].product_name;
        this.lines[i].quantity=parseFloat(document.getElementById('editQty').value)||1;
        this.lines[i].unit_price=parseFloat(document.getElementById('editPrice').value)||0;
        this.lines[i].discount_value=parseFloat(document.getElementById('editDisc').value)||0;
        this.lines[i].tax_rate=parseFloat(document.getElementById('editTax').value)||0;
        bootstrap.Modal.getInstance(document.getElementById('dsEditModal')).hide();
        this.renderLines();
    },
    deleteEditLine() {
        if (this.editIdx>=0) { this.lines.splice(this.editIdx,1); this.editIdx=-1; bootstrap.Modal.getInstance(document.getElementById('dsEditModal')).hide(); this.renderLines(); }
    },
    removeLine(i) { this.lines.splice(i,1); this.renderLines(); },

    /* ═══════ Totals ═══════ */
    recalcTotals() {
        let sub=0,disc=0,tax=0;
        this.lines.forEach(it => {
            const g=it.quantity*it.unit_price, d=it.discount_value||0;
            const taxable=Math.max(0,g-d); sub+=g; disc+=d; tax+=(it.tax_rate/100)*taxable;
        });
        const cfg=this.docConfig[this.docType];
        let ship=cfg.hasShipping?(parseFloat(document.getElementById('dsShipping').value)||0):0;
        document.getElementById('dsSubtotal').textContent=sub.toFixed(2);
        document.getElementById('dsDiscount').textContent=disc.toFixed(2);
        document.getElementById('dsTax').textContent=tax.toFixed(2);
        document.getElementById('dsShipVal').textContent=ship.toFixed(2);
        document.getElementById('dsTotal').textContent=(sub-disc+tax+ship).toFixed(2);
    },

    clearAll() {
        if(this.lines.length>0&&!confirm('Clear all items?')) return;
        this.lines=[]; this.party=null;
        this.partySelections={customer:null,vendor:null};
        this.renderParty();
        document.getElementById('dsDate').value=new Date().toISOString().split('T')[0];
        document.getElementById('dsNotes').value='';
        document.getElementById('dsShipping').value='0';
        this.renderLines();
    },

    /* ═══════ Load existing document for editing ═══════ */
    loadForEdit(type, id) {
        if (type !== 'quotation') { this.toast('Only quotation editing is supported','error'); return; }
        this.toast('Loading quotation...','success');
        fetch(this.BASE+'/document-studio/load-quotation/'+id, {headers:{'Accept':'application/json'}})
        .then(r=>r.json())
        .then(data=>{
            if(!data.success){ this.toast(data.error||'Failed to load','error'); return; }
            this.editId = data.id;
            this.editDocType = 'quotation';
            this.editQuoteNumber = data.quote_number || '';
            this.setDocType('quotation');

            // Set customer
            if(data.customer_id){
                this.party = { id: data.customer_id, label: data.customer_label };
                this.partySelections.customer = this.party;
                const inp = document.getElementById('partyInput');
                const sel = document.getElementById('partySel');
                if(inp) inp.value = data.customer_label || '';
                if(sel){ sel.textContent = data.customer_label || ''; sel.style.display = data.customer_label ? 'inline-block' : 'none'; }
                if(inp) inp.style.display = data.customer_label ? 'none' : '';
            }

            // Set date (convert DD-MM-YYYY to YYYY-MM-DD for date input)
            const dateStr = data.issue_date || '';
            const dm = dateStr.match(/^(\d{2})-(\d{2})-(\d{4})$/);
            document.getElementById('dsDate').value = dm ? (dm[3]+'-'+dm[2]+'-'+dm[1]) : dateStr;

            // Set currency
            if(data.currency) document.getElementById('dsCurrency').value = data.currency;

            // Set notes
            document.getElementById('dsNotes').value = data.notes || '';

            // Set shipping
            document.getElementById('dsShipping').value = data.shipping_amount || 0;

            // Load lines
            this.lines = (data.lines || []).map(ln => ({
                product_id: ln.product_id, variant_id: ln.variant_id || null,
                product_code: ln.product_code || '', product_name: ln.product_name || '',
                description: ln.description || ln.product_name || '',
                unit: ln.unit || 'pcs', quantity: ln.quantity || 0,
                unit_price: ln.unit_price || 0,
                discount_type: ln.discount_type || 'fixed',
                discount_value: ln.discount_value || 0,
                tax_rate: ln.tax_rate || 0,
                image_url: ln.image_url || '',
                line_id: ln.id || 0,
            }));
            this.renderLines();
            this.recalcTotals();

            // Update save button text
            document.getElementById('dsSaveTxt').textContent = 'Update Quotation';
            this.toast('Quotation '+this.editQuoteNumber+' loaded for editing','success');
        })
        .catch(err=>{ this.toast('Network error loading quotation','error'); console.error(err); });
    },

    _payload() {
        const cfg=this.docConfig[this.docType];
        const p={
            issue_date:document.getElementById('dsDate').value,
            currency:document.getElementById('dsCurrency').value,
            notes:document.getElementById('dsNotes').value.trim()||null,
            lines:this.lines.map(l=>({
                product_id:l.product_id, product_name:l.product_name,
                product_code:l.product_code||'',
                description:l.description||l.product_name,
                unit:l.unit, quantity:l.quantity, unit_price:l.unit_price,
                discount_type:l.discount_type||'fixed',
                discount_value:l.discount_value||0, discount:l.discount_value||0,
                tax_rate:l.tax_rate||0, tax_percent:l.tax_rate||0,
                variant_id:l.variant_id||null,
                line_id:l.line_id||0,
            })),
        };
        if(cfg.hasShipping) p.shipping_amount=parseFloat(document.getElementById('dsShipping').value)||0;
        if(cfg.party==='customer') p.customer_id=this.party?this.party.id:null;
        else p.vendor_id=this.party?this.party.id:null;
        return p;
    },

    saveDraft() {
        if(!this.lines.length){this.toast('Add at least one item','error');return;}
        const p=this._payload(); p.is_draft=true;
        const cfg=this.docConfig[this.docType];
        const isUpdate = !!this.editId;
        const endpoint = isUpdate
            ? '/document-studio/update-quotation/'+this.editId
            : '/document-studio/'+cfg.endpoint;
        const btn=document.querySelector('.ds-topbar .btn-icon:first-of-type');
        const prev=btn?btn.innerHTML:'';
        if(btn){btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm" style="width:14px;height:14px;"></span>';}
        fetch(this.BASE+endpoint,{
            method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
            body:JSON.stringify(p),
        }).then(r=>r.json().then(d=>({ok:r.ok,data:d}))).then(({ok,data})=>{
            if(btn){btn.disabled=false;btn.innerHTML=prev;}
            if(ok&&data.success){this.toast(data.message||'Saved as draft!','success');if(!isUpdate){this.lines=[];this.renderLines();document.getElementById('dsNotes').value='';document.getElementById('dsShipping').value='0';}}
            else this.toast(data.error||'Save failed','error');
        }).catch(()=>{if(btn){btn.disabled=false;btn.innerHTML=prev;}this.toast('Network error','error');});
    },

    save() {
        const cfg=this.docConfig[this.docType];
        if(!this.party){this.toast('Please select a '+cfg.label.toLowerCase(),'error');return;}
        if(!this.lines.length){this.toast('Add at least one item','error');return;}
        const p=this._payload();
        const isUpdate = !!this.editId;
        const endpoint = isUpdate
            ? '/document-studio/update-quotation/'+this.editId
            : '/document-studio/'+cfg.endpoint;
        const btn=document.getElementById('dsSaveBtn'), prev=btn.innerHTML;
        btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
        fetch(this.BASE+endpoint,{
            method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
            body:JSON.stringify(p),
        }).then(r=>r.json().then(d=>({ok:r.ok,data:d}))).then(({ok,data})=>{
            btn.disabled=false; btn.innerHTML=prev;
            if(ok&&data.success){
                this.toast(data.message||(isUpdate?'Updated!':'Created!'),'success');
                setTimeout(()=>{
                    if(data.view_url&&confirm((isUpdate?'Quotation updated!':'Document created!')+' View it now?')) window.location.href=data.view_url;
                    else if(!isUpdate){this.lines=[];this.renderLines();document.getElementById('dsNotes').value='';document.getElementById('dsShipping').value='0';}
                },300);
            } else this.toast(data.error||'Save failed','error');
        }).catch(()=>{btn.disabled=false;btn.innerHTML=prev;this.toast('Network error','error');});
    },

    /* ═══════ Documents List ═══════ */
    openDocsList() { new bootstrap.Offcanvas(document.getElementById('dsOC')).show(); this.loadDocs(this.docsListType); },
    switchDocsTab(type,btn) { document.querySelectorAll('.ds-oc-tab').forEach(b=>b.classList.remove('active')); if(btn)btn.classList.add('active'); this.docsListType=type; this.loadDocs(type); },
    loadDocs(type) {
        const list=document.getElementById('dsOCList');
        list.innerHTML='<div class="ds-oc-empty"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</div>';
        fetch(this.BASE+'/document-studio/list-documents?type='+encodeURIComponent(type),{headers:{'Accept':'application/json'}})
        .then(r=>r.json()).then(data=>{
            if(!data||!data.length){list.innerHTML='<div class="ds-oc-empty"><i class="bi bi-inbox d-block" style="font-size:1.6rem;"></i>No documents</div>';return;}
            list.innerHTML=data.map(d=>{
                const isDraft=(d.status||'').toLowerCase()==='draft';
                return `<div class="ds-oc-row"><div class="di"><div class="dn">${this.escHtml(d.number||('#'+d.id))}</div><div class="dm">${this.escHtml(d.party_name||'')} &middot; ${this.escHtml(d.date||'')}</div></div><span class="dst ${isDraft?'draft':'active'}">${this.escHtml(d.status||'')}</span><span class="dt">${this.escHtml(d.total||'0.00')}</span><div class="da">${d.view_url?'<a href="'+d.view_url+'" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>':''}</div></div>`;
            }).join('');
        }).catch(()=>{list.innerHTML='<div class="ds-oc-empty text-danger">Failed to load</div>';});
    },

    toast(msg,type){const el=document.getElementById('dsToast');el.textContent=msg;el.className='ds-toast '+(type||'success');setTimeout(()=>el.className='ds-toast',3500);},
    escHtml(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;},
    escJs(s){return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'\\"');},
};

document.addEventListener('click',e=>{const w=document.getElementById('partyWrap');if(w&&!w.contains(e.target))document.getElementById('partyDD').classList.remove('show');});

DS.renderLines();
DS.initResize();
try{DS.setView(localStorage.getItem('ds_view')==='list'?'list':'grid');}catch(_){DS.setView('grid');}
DS.setDocType('quotation');

// Auto-load document for editing if URL has ?edit=quotation&id=N
(function(){
    const params = new URLSearchParams(window.location.search);
    const editType = params.get('edit');
    const editId = parseInt(params.get('id'), 10);
    if (editType && editId) {
        DS.loadForEdit(editType, editId);
    }
})();
</script>

<?= $this->endSection() ?>
