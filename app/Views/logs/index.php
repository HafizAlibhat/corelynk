<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Production Logs<?= $this->endSection() ?>
<?= $this->section('content') ?>

<style>
    :root {
        --tt-border:#e6eaef;
        --tt-bg:#ffffff;
        --tt-muted:#6c757d;
        --tt-accent:#0d6efd;
        --tt-soft:#f8f9fb;
        --tt-chip:#f1f3f5;
    }
    .tree { font-family: Arial, sans-serif; }
    .node { margin-left: 1rem; border-left: 1px dotted #ccc; padding-left: .5rem; }
    .toggle { cursor: pointer; user-select: none; }
    .toggle .chev { display: inline-block; transition: transform .15s ease; width: 1rem; }
    .children { overflow: hidden; transition: height .22s ease, opacity .22s ease; height: 0; opacity: 0; }
        .expanded > .children { height: auto; opacity: 1; }
        .collapsed > .label .chev { transform: rotate(0deg); }
        .badge-pill { border-radius: 50rem; padding: .25rem .5rem; }
        /* lighter badges */
        .badge-acc { background:#d1e7dd; color:#0f5132; border:1px solid #badbcc; }
        .badge-rej { background:#f8d7da; color:#842029; border:1px solid #f5c2c7; }
        .badge-rew { background:#fff3cd; color:#664d03; border:1px solid #ffecb5; }
        .badge-pend { background:#e2e3e5; color:#41464b; border:1px solid #d3d6d8; }
        .badge-stat { background:#cff4fc; color:#055160; border:1px solid #b6effb; }
    .badge-acc { background:#d1e7dd; color:#0f5132; border:1px solid #badbcc; }
    .badge-rej { background:#f8d7da; color:#842029; border:1px solid #f5c2c7; }
    .badge-rew { background:#fff3cd; color:#664d03; border:1px solid #ffecb5; }
    .badge-pend { background:#e2e3e5; color:#41464b; border:1px solid #d3d6d8; }
    .badge-stat { background:#cff4fc; color:#055160; border:1px solid #b6effb; }
    .small-muted { font-size: .85rem; color: #6c757d; }
    .flex-gap { gap: .5rem; }
    .log-row { font-size: .9rem; background: #f9fafb; border: 1px solid #edf1f5; border-radius: .375rem; padding: .35rem .6rem; margin-bottom: .35rem; }
    /* formula bar */
    .formula { border-top: 1px dashed #e5e7eb; padding-top: .5rem; margin-top: .25rem; }
    .formula-label { font-size: .8rem; color: #6c757d; }
    .formula-value { font-size: 1rem; }
    .formula-sep { font-size: 1.1rem; color: #6c757d; line-height: 1; margin-bottom: .2rem; }
    /* compact batch header and progress bars */
    .batch-header { padding: 6px 10px; }
    .vbar { width: 6px; height: 18px; background:#e9ecef; border-radius: 4px; overflow: hidden; display:inline-block; }
    .vbar-fill { width:100%; height:0; background-color:#0d6efd; background-image: linear-gradient(180deg, rgba(255,255,255,.35) 0, rgba(255,255,255,.35) 40%, transparent 40%); background-size: 100% 8px; animation: progressPulse 1.6s linear infinite; transition: height .35s ease; }
    .vbar-fill.warn { background-color:#fd7e14; }
    .vbar-fill.good { background-color:#20c997; }
    .vbar-fill.done { background-color:#198754; background-image:none; animation:none; }
    .hbar { display:inline-block; width: 120px; height: 6px; background:#eef2f6; border-radius: 4px; overflow:hidden; vertical-align: middle; }
    .hbar-fill { height:100%; width:0; background-color:#0d6efd; background-image: linear-gradient(90deg, rgba(255,255,255,.35) 0, rgba(255,255,255,.35) 40%, transparent 40%); background-size: 40px 100%; animation: progressSlide 1.2s linear infinite; transition: width .45s ease; }
    .hbar-fill.warn { background-color:#fd7e14; }
    .hbar-fill.good { background-color:#20c997; }
    .hbar-fill.done { background-color:#198754; background-image:none; animation:none; }
    @keyframes progressSlide { from { background-position: 0 0; } to { background-position: 40px 0; } }
    @keyframes progressPulse { 0% { opacity: .9; } 50% { opacity: .7; } 100% { opacity: .9; } }
        /* visual separation (modernized) */
        .wo-label { background:#fafbfc; border:1px solid #eceff3; border-radius:8px; padding:6px 8px; margin-bottom:6px; font-weight:600; }
        .product-label { background:#fbfcfe; border:1px solid #eceff3; border-left:3px solid #e1e7ee; border-radius:10px; padding:8px 10px; margin:6px 0; font-weight:600; }
        .process-label { background:#fbfcfe; border:1px solid #eceff3; border-left:3px solid #e8ecf2; border-radius:10px; padding:8px 10px; margin:6px 0; font-style: italic; }
        .batch-header { background:#ffffff; border:1px solid #eceff3; border-left:3px solid #e8ecf2; border-radius:12px; padding:10px 12px; margin:8px 0; box-shadow:0 1px 2px rgba(0,0,0,.03); }
        .logs-wrap { margin-left: .75rem; }
    .log-row small { color: #6c757d; }
    .w-110 { width: 110px; }
    @media (max-width: 768px) { .w-110 { width: auto; } }

    /* Tree table styles */
    .tree-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .tree-table th, .tree-table td { padding: .18rem .32rem; vertical-align: middle; border-bottom: 1px solid var(--tt-border); }
    .tree-table thead th { position: sticky; top: 0; background: #fff; z-index: 1; border-bottom: 1px solid var(--tt-border); }
    .tt-row.hidden { display: none; }
    .tt-toggle { cursor: pointer; user-select: none; color: #0d6efd; text-decoration: none; font-size: 0.9rem; }
    /* Make batch codes (level 3 toggles) more scannable */
    .tree-table tbody tr[data-level="3"] .tt-toggle { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-weight: 600; color:#111827; font-size: 0.85rem; }
    body.theme-dark .tree-table tbody tr[data-level="3"] .tt-toggle { color:#e5e7eb; }
    /* plus/minus caret styled like Windows tree */
    .tt-caret { display:inline-grid; place-items:center; width:1.1rem; height:1.1rem; line-height:1; font-size:.9rem; font-weight:700; color:#495057; border:1px solid #d0d7de; border-radius:3px; background:#fff; margin-right:.35rem; cursor:pointer; }
    .tt-caret:focus { outline:2px solid #93c5fd; outline-offset:1px; }
    body.theme-dark .tt-caret { background:#0b1220; border-color:#334155; color:#cbd5e1; }
    .tt-icon { color:#6b7280; margin-right:.25rem; }
    .tt-indent-0 { padding-left: .2rem; position: relative; }
    .tt-indent-1, .tt-indent-2, .tt-indent-3, .tt-indent-4 { position: relative; }
    .tt-indent-1 { padding-left: 2.2rem; }
    .tt-indent-2 { padding-left: 3.8rem; }
    .tt-indent-3 { padding-left: 5.4rem; }
    /* push child (log) rows slightly further for clearer hierarchy and add right spacing */
    /* tightened left padding to reduce empty space before log text */
    .tt-indent-4 { padding-left: 6.4rem; padding-right: .8rem; }
    /* nicer single-line chip for log details */
    .log-line { display:inline-block; background:#f8f9fb; border:1px solid #eef2f6; border-radius:8px; padding:.14rem .42rem; }
    /* tree connectors */
    .tt-indent-1::before, .tt-indent-2::before, .tt-indent-3::before, .tt-indent-4::before {
        content: '';
        position: absolute;
        left: 1.1rem;
        top: -10px;
        bottom: -10px;
        border-left: 1px dotted #d0d7de;
    }
    .tt-indent-1::after, .tt-indent-2::after, .tt-indent-3::after, .tt-indent-4::after {
        content: '';
        position: absolute;
        left: 1.1rem;
        top: 50%;
        width: .8rem;
        border-top: 1px dotted #d0d7de;
    }
    /* Stop connector after last child so branches terminate cleanly */
    .tree-table tbody tr[data-last="1"] td:first-child.tt-indent-1::before,
    .tree-table tbody tr[data-last="1"] td:first-child.tt-indent-2::before,
    .tree-table tbody tr[data-last="1"] td:first-child.tt-indent-3::before,
    .tree-table tbody tr[data-last="1"] td:first-child.tt-indent-4::before { bottom: 50%; }
    .tt-meta { color: #6c757d; font-size: .75rem; }
    /* tt-badge now globally defined in layout; keep only optional slimmer variant override */
    .tt-badge.slim { padding:.04rem .35rem; font-size:.65rem; margin-left:.2rem; }
    .tt-inline { display:inline-flex; align-items:center; gap:.3rem; white-space: nowrap; }
    /* Action button visual rules moved to public/assets/css/style.css; keep per-page styles minimal. */
    /* Semantic badge colors for batch stats (light) */
    .tt-badge[data-type="accepted"] { background:#e9f7ef; border-color:#d1f2e5; color:#0f5132; }
    .tt-badge[data-type="rejected"] { background:#fdecea; border-color:#f5c2c7; color:#842029; }
    .tt-badge[data-type="rework"]   { background:#fff7e6; border-color:#ffe0a3; color:#8a5a00; }
    .tt-badge[data-type="pending"]  { background:#eef2f6; border-color:#dce4ee; color:#334155; }

    /* row accents by level */
    /* Windows-like dotted connectors */
    .tt-indent-1, .tt-indent-2, .tt-indent-3, .tt-indent-4 { position:relative; }
    .tt-indent-1::before, .tt-indent-2::before, .tt-indent-3::before, .tt-indent-4::before {
        content:""; position:absolute; left:1.05rem; top:-10px; bottom:-10px; border-left:1px dotted #d0d7de;
    }
    .tt-indent-1::after, .tt-indent-2::after, .tt-indent-3::after, .tt-indent-4::after {
        content:""; position:absolute; left:1.05rem; top:50%; width:1rem; border-top:1px dotted #d0d7de;
    }
    /* Stop connector after last child so branches terminate cleanly */
    .tree-table tbody tr[data-last="1"] td:first-child.tt-indent-1::before,
    .tree-table tbody tr[data-last="1"] td:first-child.tt-indent-2::before,
    .tree-table tbody tr[data-last="1"] td:first-child.tt-indent-3::before,
    .tree-table tbody tr[data-last="1"] td:first-child.tt-indent-4::before { bottom: 50%; }
    /* Hover should not wipe out row tint; add a subtle outline instead */
    .tree-table tbody tr:hover td:first-child { box-shadow: inset 0 0 0 1px #d0d7de; }
    .tree-toolbar { display:flex; justify-content:flex-end; gap:.4rem; margin-bottom:.25rem; }
    .tree-toolbar .btn { padding:.15rem .5rem; font-size:.8rem; }

    /* Stronger visual hierarchy by level (background tint across the whole row) */
    .tree-table tbody tr[data-level="0"] td { background:#eef5ff; }
    .tree-table tbody tr[data-level="1"] td { background:#f3fff6; }
    .tree-table tbody tr[data-level="2"] td { background:#fff7ed; }
    .tree-table tbody tr[data-level="3"] td { background:#e3e7ff; }
    .tree-table tbody tr[data-level="3"] td:first-child { box-shadow: inset 3px 0 0 0 #3b5bdb; }
    .tree-table tbody tr[data-level="4"] td { background:#ffffff; }

    /* Dark theme: replace blue tints with warm ambers/greens/oranges for better contrast on dark */
    body.theme-dark .tree-table tbody tr[data-level="0"] td { background:#211a0f; } /* Work Order: warm amber tint */
    body.theme-dark .tree-table tbody tr[data-level="1"] td { background:#0f1f16; } /* Product: subtle green tint */
    body.theme-dark .tree-table tbody tr[data-level="2"] td { background:#20170f; } /* Process: subtle orange tint */
    body.theme-dark .tree-table tbody tr[data-level="3"] td { background:#141a26; } /* Batch: neutral indigo-slate tint */
    /* Solid batch row variant (refined) */
    /* Lighter orange for batch rows (keeps contrast with white text ~5:1) */
    :root { --batch-row-bg-light:#d97706; --batch-row-bg-light-hover:#c96805; --batch-row-accent:#f59e0b; }
    body.theme-dark { --batch-row-bg-dark:#a35412; --batch-row-bg-dark-hover:#8b440f; --batch-row-accent-dark:#22c55e; }
    /* Orange hover palette (accessible) */
    :root { --row-hover-orange:#b45309; --row-hover-orange-text:#ffffff; }
    body.theme-dark { --row-hover-orange:#92400e; --row-hover-orange-text:#ffffff; }
    .tree-table tbody tr.batch-row td { background:var(--batch-row-bg-light); color:#fff; transition:background .18s ease, box-shadow .18s ease; font-weight:600; }
    .tree-table tbody tr.batch-row:hover td { background:var(--batch-row-bg-light-hover); }
    body.theme-dark .tree-table tbody tr.batch-row td { background:var(--batch-row-bg-dark); color:#fff; }
    body.theme-dark .tree-table tbody tr.batch-row:hover td { background:var(--batch-row-bg-dark-hover); }
    .tree-table tbody tr.batch-row td:first-child { position:relative; }
    .tree-table tbody tr.batch-row td:first-child::before { content:""; position:absolute; left:0; top:0; bottom:0; width:6px; background:var(--batch-row-accent); border-radius:4px 0 0 4px; }
    body.theme-dark .tree-table tbody tr.batch-row td:first-child::before { background:var(--batch-row-accent-dark); }
    .tree-table tbody tr.batch-row a.tt-toggle { color:#fff !important; }
    body.theme-dark .tree-table tbody tr.batch-row a.tt-toggle { color:#e2e8f0 !important; }
    .tree-table tbody tr.batch-row .tt-badge.slim:not([data-type]) { background:rgba(255,255,255,.18); border-color:rgba(255,255,255,.3); color:#f1f5f9; }
    body.theme-dark .tree-table tbody tr.batch-row .tt-badge.slim:not([data-type]) { background:rgba(255,255,255,.10); border-color:#334155; color:#e2e8f0; }
    .tree-table tbody tr.batch-row .tt-status { background:rgba(255,255,255,.15); border-color:rgba(255,255,255,.28); }
    body.theme-dark .tree-table tbody tr.batch-row .tt-status { background:rgba(255,255,255,.08); border-color:#334155; }
    /* Strengthen caret contrast inside batch row */
    .tree-table tbody tr.batch-row .tt-caret { background:rgba(255,255,255,.15); border-color:rgba(255,255,255,.35); color:#f1f5f9; }
    body.theme-dark .tree-table tbody tr.batch-row .tt-caret { background:#253346; border-color:#334155; color:#e2e8f0; }

    /* Solid process row variant (teal) */
    :root { --process-row-bg-light:#0d9488; --process-row-bg-light-hover:#0b8277; --process-row-accent:#2dd4bf; }
    body.theme-dark { --process-row-bg-dark:#0b525b; --process-row-bg-dark-hover:#09464d; --process-row-accent-dark:#22d3ee; }
    .tree-table tbody tr.process-row td { background:var(--process-row-bg-light); color:#fff; transition:background .18s ease, box-shadow .18s ease; font-weight:600; }
    .tree-table tbody tr.process-row:hover td { background:var(--process-row-bg-light-hover); }
    body.theme-dark .tree-table tbody tr.process-row td { background:var(--process-row-bg-dark); color:#fff; }
    body.theme-dark .tree-table tbody tr.process-row:hover td { background:var(--process-row-bg-dark-hover); }
    .tree-table tbody tr.process-row td:first-child { position:relative; }
    .tree-table tbody tr.process-row td:first-child::before { content:""; position:absolute; left:0; top:0; bottom:0; width:6px; background:var(--process-row-accent); border-radius:4px 0 0 4px; }
    body.theme-dark .tree-table tbody tr.process-row td:first-child::before { background:var(--process-row-accent-dark); }
    .tree-table tbody tr.process-row a.tt-toggle { color:#fff !important; }
    body.theme-dark .tree-table tbody tr.process-row a.tt-toggle { color:#fff !important; }
    .tree-table tbody tr.process-row .tt-badge.slim:not([data-type]) { background:rgba(255,255,255,.16); border-color:rgba(255,255,255,.28); color:#fff; }
    .tree-table tbody tr.process-row .tt-caret { background:rgba(255,255,255,.15); border-color:rgba(255,255,255,.35); color:#f1f5f9; }

    /* Process group spacing - Only affects dark mode grouping, light mode stays original */
    :root { 
        --proc-group-spacing: 12px;
    }
    
    /* Spacing between groups - only in dark mode */
    body.theme-dark .proc-group-end { 
        margin-bottom: var(--proc-group-spacing); 
    }
    
    /* Hide old accent bars inside groups - only in dark mode */
    body.theme-dark .proc-group-start td:first-child::before,
    body.theme-dark .proc-group-mid td:first-child::before,
    body.theme-dark .proc-group-end td:first-child::before { 
        display: none !important; 
    }
    
    /* Remove internal row borders inside groups for cleaner look - only in dark mode */
    body.theme-dark .proc-group-start td, 
    body.theme-dark .proc-group-mid td { 
        border-bottom: 1px solid rgba(255,255,255,.02) !important;
    }
    
    /* Process row emphasis - only in dark mode */
    body.theme-dark .proc-group-start.process-row td { 
        font-weight: 600;
    }
    
    /* Dark mode: Ensure batch rows keep their orange */
    body.theme-dark .proc-group-start.batch-row td,
    body.theme-dark .proc-group-mid.batch-row td,
    body.theme-dark .proc-group-end.batch-row td {
        background: var(--batch-row-bg-dark) !important;
        color: #fff !important;
    }
    
    /* Dark mode: Log rows stay neutral */
    body.theme-dark .proc-group-mid.log-entry td,
    body.theme-dark .proc-group-end.log-entry td {
        background: #0b1220 !important;
    }

    /* Log entry rows: make them clearly lighter/quieter than batch rows */
    /* Light theme: higher contrast, very light slate */
    .tree-table tbody tr.log-entry td { background:#edf2fa; color:#334155; font-weight:500; }
    .tree-table tbody tr.log-entry:hover td { background:#e6eef9; }
    /* Dark theme: slightly lighter than surrounding to pop rows */
    body.theme-dark .tree-table tbody tr.log-entry td { background:#0f1a2a; color:#cbd5e1; }
    body.theme-dark .tree-table tbody tr.log-entry:hover td { background:#132238; }
    /* Log entry first cell subtle guide bar */
    .tree-table tbody tr.log-entry td:first-child { position:relative; }
    .tree-table tbody tr.log-entry td:first-child::before { content:""; position:absolute; left:0; top:0; bottom:0; width:3px; background:#60a5fa; opacity:.65; border-radius:3px; }
    body.theme-dark .tree-table tbody tr.log-entry td:first-child::before { background:#38bdf8; opacity:.55; }
    /* Log line chip tuned to stand out on log row */
    .log-line { background:#f8fafc; border:1px solid #e5e7eb; color:#111827; }
    body.theme-dark .log-line { background:#0f172a; border-color:#334155; color:#e2e8f0; }
    /* Log icon color enhancement */
    .tree-table tbody tr[data-level="4"] .tt-icon { color:#0d6efd; }
    body.theme-dark .tree-table tbody tr[data-level="4"] .tt-icon { color:#0d6efd; }
    /* Improve dark meta/date contrast */
    body.theme-dark .tt-meta, body.theme-dark .log-line { color:#cbd5e1; }
    body.theme-dark .tree-table tbody tr[data-level="4"] .tt-meta strong { color:#e2e8f0; }
    body.theme-dark .log-line { background:#1e293b; border-color:#334155; }
    body.theme-dark .tt-badge[data-type="accepted"] { background:#064e3b; border-color:#065f46; color:#34d399; }
    body.theme-dark .tt-badge[data-type="rejected"] { background:#4c0519; border-color:#7f1d1d; color:#fca5a5; }
    body.theme-dark .tt-badge[data-type="rework"]   { background:#78350f; border-color:#92400e; color:#fdba74; }
    body.theme-dark .tt-badge[data-type="pending"]  { background:#0b1220; border-color:#334155; color:#94a3b8; }
    /* High-visibility Started badge */
    .tt-badge[data-type="started"] { background:#fff3cd; border-color:#ffecb5; color:#664d03; }
    body.theme-dark .tt-badge[data-type="started"] { background:#3a2f14; border-color:#4d3b15; color:#facc15; }
    /* Label colors per level for readability */
    body.theme-dark .tree-table tbody tr[data-level="0"] td:first-child .tt-toggle { color:#f59e0b; } /* Work Order */
    body.theme-dark .tree-table tbody tr[data-level="1"] td:first-child .tt-toggle { color:#2dd4bf; } /* Product (teal) */
    body.theme-dark .tree-table tbody tr[data-level="2"] td:first-child .tt-toggle { color:#fb923c; } /* Process (orange) */
    body.theme-dark .tree-table tbody tr[data-level="3"] td:first-child .tt-toggle { color:#e2e8f0; } /* Batch (light slate) */
    body.theme-dark .tree-table tbody tr[data-level="3"] td:first-child { box-shadow: inset 3px 0 0 0 #f59e0b; } /* Batch left accent */
    body.theme-dark .tree-table tbody tr[data-expanded="true"] td:first-child { box-shadow: inset 3px 0 0 0 #f59e0b; } /* Expanded parent accent */

    /* Icon colors by level */
    body.theme-dark .tree-table tbody tr[data-level="0"] .tt-icon { color:#f59e0b; }
    body.theme-dark .tree-table tbody tr[data-level="1"] .tt-icon { color:#2dd4bf; }
    body.theme-dark .tree-table tbody tr[data-level="2"] .tt-icon { color:#fb923c; }
    body.theme-dark .tree-table tbody tr[data-level="3"] .tt-icon { color:#cbd5e1; }
    body.theme-dark .tt-caret { background:#0f172a; border-color:#475569; color:#e2e8f0; }

    /* Log details area readability in dark */
    body.theme-dark .log-row { background:#0f172a; border-color:#1f2937; color:#e2e8f0; }
    body.theme-dark .log-row small { color:#94a3b8; }
    body.theme-dark .log-line { background:#111827; border-color:#1f2937; color:#e2e8f0; }
    body.theme-dark .formula { border-top:1px dashed #334155; }
    body.theme-dark .formula-label { color:#94a3b8; }
    body.theme-dark .formula-sep { color:#94a3b8; }

    /* Status chips tuned for dark */
    body.theme-dark .tt-s-plan { background:#3a2f14; color:#facc15; border-color:#4d3b15; }
    body.theme-dark .tt-s-inprog { background:#15263b; color:#93c5fd; border-color:#1e3a5f; }
    body.theme-dark .tt-s-done { background:#173021; color:#86efac; border-color:#1e402a; }

    /* Action icons in dark (legacy selector) */
    body.theme-dark .tt-actions .legacy-btn-icon-deprecated i { color:#cbd5e1; }
    body.theme-dark .tt-actions .btn-outline-secondary { color:#cbd5e1; border-color:#475569; }
    /* Start Batch header styling */
    /* Header styles now global (.section-header etc) removed local duplicates */

    /* Improved action icon colors */
    .tt-actions .btn-outline-primary i { color:#0d6efd; }
    .tt-actions .btn-outline-secondary i { color:#495057; }
    .tt-actions .btn-outline-danger i { color:#dc3545; }
    .tt-actions .btn-outline-info i { color:#0dcaf0; }
    body.theme-dark .tt-actions .btn-outline-primary i { color:#93c5fd; }
    body.theme-dark .tt-actions .btn-outline-secondary i { color:#cbd5e1; }
    body.theme-dark .tt-actions .btn-outline-danger i { color:#f87171; }
    body.theme-dark .tt-actions .btn-outline-info i { color:#67e8f9; }

    /* Info popup panel (non-modal) */
    .log-info-panel { position:fixed; top:64px; right:32px; width:360px; background: var(--bs-body-bg); border:1px solid var(--bs-border-color-translucent); border-radius:12px; padding:16px 18px 18px; box-shadow:0 8px 28px rgba(0,0,0,.35); z-index:2001; animation:lipFade .22s ease; }
    .lip-overlay { position:fixed; inset:0; background:rgba(15,15,20,.55); backdrop-filter: blur(3px); z-index:2000; animation:lipFade .2s ease; }
    .log-info-header { display:flex; align-items:center; justify-content:space-between; margin:-4px -2px 10px; }
    .log-info-title { font-weight:600; letter-spacing:.4px; display:flex; align-items:center; gap:6px; font-size:.9rem; }
    .log-info-title i { opacity:.85; }
    .lip-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; margin-top:4px; }
    .lip-stat { background: var(--bs-tertiary-bg); padding:10px 12px 11px; border-radius:8px; position:relative; overflow:hidden; }
    .lip-stat span { font-size:.68rem; text-transform:uppercase; letter-spacing:.5px; font-weight:600; opacity:.75; }
    .lip-val { font-size:1rem; font-weight:600; margin:2px 0 4px; }
    .lip-bar { height:6px; background: var(--bs-secondary-bg); border-radius:4px; overflow:hidden; }
    .lip-bar-fill { height:100%; background:#0d6efd; width:0; transition:width .45s cubic-bezier(.4,.04,.3,1); }
    .lip-close { position:absolute; top:8px; right:8px; width:28px; height:28px; border:none; background:var(--bs-secondary-bg); display:flex; align-items:center; justify-content:center; border-radius:50%; cursor:pointer; font-size:.9rem; }
    .lip-close:hover { background:var(--bs-tertiary-bg); }
    .lip-donut { width:78px; height:78px; border-radius:50%; position:relative; box-shadow:0 2px 6px rgba(0,0,0,.25); }
    .lip-donut::after { content:""; position:absolute; inset:15px; background:var(--bs-body-bg); border-radius:50%; box-shadow:inset 0 0 0 1px var(--bs-border-color-translucent); }
    .lip-legend { font-size:.72rem; line-height:1.05rem; }
    .lip-legend .ll { display:flex; align-items:center; gap:6px; }
    .ll-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
    .ll-acc { background:#22c55e; }
    .ll-rej { background:#ef4444; }
    .ll-rew { background:#f59e0b; }
    @keyframes lipFade { from { opacity:0; transform:translateY(8px);} to {opacity:1; transform:translateY(0);} }
    body.theme-dark .log-info-panel { background:#1e1f24; }
    body.theme-dark .lip-close { background:#2b2d33; }
    body.theme-dark .lip-close:hover { background:#34363d; }

    /* Keep parent visually grouped while expanded */
    .tree-table tbody tr[data-expanded="true"] td:first-child { box-shadow: inset 3px 0 0 0 #0d6efd; }

    /* Context icons without changing markup (decorate the clickable label) */
    .tree-table tbody tr[data-level="0"] td:first-child .tt-toggle::before { content:"📄 "; }
    .tree-table tbody tr[data-level="1"] td:first-child .tt-toggle::before { content:"📦 "; }
    /* Remove process icon to avoid duplication with existing gear icon */
    /* .tree-table tbody tr[data-level="2"] td:first-child .tt-toggle::before { content:"⚙️ "; } */
    .tree-table tbody tr[data-level="4"] td:first-child .tt-meta::before { content:"🧾 "; }

     /* Action/button and icon sizing moved to public/assets/css/style.css. Legacy per-page icon classes kept for compatibility
         but visual sizing is governed centrally. */

    /* status badges */
    .tt-status { display:inline-flex; align-items:center; gap:.25rem; padding:.12rem .45rem; border-radius: .9rem; font-size: .7rem; border:1px solid transparent; }
    .tt-s-plan { background:#fff3cd; color:#664d03; border-color:#ffecb5; }
    .tt-s-inprog { background:#e7f1ff; color:#0a58ca; border-color:#d0e3ff; }
    .tt-s-done { background:#d1e7dd; color:#0f5132; border-color:#badbcc; }
    .tt-loading { width:.45rem; height:.45rem; border-radius:50%; background:#0d6efd; display:inline-block; animation: ttPulse 1s ease-in-out infinite; }
    @keyframes ttPulse { 0%, 100% { transform: scale(.8); opacity:.6; } 50% { transform: scale(1); opacity:1; } }

    /* (legend removed for clarity) */

    /* Start a Batch form stability */
    #startBatchForm .form-label { margin-bottom: .25rem; }
    #startBatchForm .form-select, #startBatchForm .form-control { min-height: 38px; height: 38px; }
    #startBatchForm .form-check-input { width: 2.25rem; height: 1.2rem; }
    #startBatchForm .form-check-input:checked { background-color: #0d6efd; border-color: #0d6efd; }
    #startBatchForm .form-check-label { margin-left: .25rem; }
    @media (min-width: 768px) {
        /* make two crisp rows: 4 cols then 3 cols; avoid accidental wrapping */
        #startBatchForm > .col-md-3 { flex: 0 0 auto; max-width: 25%; }
    }
    #startBatchForm .form-text { min-height: 1rem; }
    #productHint { min-height: .8rem; white-space: nowrap; }
    #startControls { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
    #startInfo { min-height: .8rem; display: inline-block; }

    /* Page-specific dark styles now derive solely from body.theme-dark; remove legacy .logs-dark class to allow global switch to work */
    body.theme-dark .card { background:#1e293b; color:#e2e8f0; border-color:#334155; }
    body.theme-dark .card-header { background:#0f172a; border-bottom-color:#334155; }
    body.theme-dark .form-select, body.theme-dark .form-control { background:#162033; color:#e2e8f0; border-color:#334155; }
    body.theme-dark .form-select:disabled, body.theme-dark .form-control:disabled { background:#162033; color:#94a3b8; }
    body.theme-dark .btn-outline-secondary { color:#cbd5e1; border-color:#475569; }
    body.theme-dark .btn-outline-primary { color:#93c5fd; border-color:#475569; }
    body.theme-dark .tree-table tbody tr:hover td { background:#162033; }
    body.theme-dark .tt-badge { background:#162033; border-color:#475569; color:#cbd5e1; }
    body.theme-dark .tt-status { border-color:#475569; }
    body.theme-dark .tree-table tbody tr.tt-row:not(.batch-row) td { background:transparent; border-color:#334155; }
    body.theme-dark .tt-indent-1::before, body.theme-dark .tt-indent-2::before, body.theme-dark .tt-indent-3::before, body.theme-dark .tt-indent-4::before,
    body.theme-dark .tt-indent-1::after, body.theme-dark .tt-indent-2::after, body.theme-dark .tt-indent-3::after, body.theme-dark .tt-indent-4::after { border-color:#334155; }

    /* Solid orange row hover treatment (limit to prominent rows in light theme) */
    body:not(.theme-dark) .tree-table tbody tr.batch-row:hover td,
    body:not(.theme-dark) .tree-table tbody tr.process-row:hover td {
        background: var(--row-hover-orange) !important;
        color: var(--row-hover-orange-text) !important;
    }
    body:not(.theme-dark) .tree-table tbody tr.batch-row:hover a.tt-toggle,
    body:not(.theme-dark) .tree-table tbody tr.process-row:hover a.tt-toggle,
    body:not(.theme-dark) .tree-table tbody tr.batch-row:hover .tt-icon,
    body:not(.theme-dark) .tree-table tbody tr.process-row:hover .tt-icon,
    body:not(.theme-dark) .tree-table tbody tr.batch-row:hover .tt-meta,
    body:not(.theme-dark) .tree-table tbody tr.process-row:hover .tt-meta {
        color: var(--row-hover-orange-text) !important;
    }
    body:not(.theme-dark) .tree-table tbody tr.batch-row:hover .tt-badge:not([data-type]),
    body:not(.theme-dark) .tree-table tbody tr.process-row:hover .tt-badge:not([data-type]) { background: rgba(255,255,255,.18); border-color: rgba(255,255,255,.32); color: #fff; }
    body:not(.theme-dark) .tree-table tbody tr.batch-row:hover .tt-status,
    body:not(.theme-dark) .tree-table tbody tr.process-row:hover .tt-status { background: rgba(255,255,255,.15); border-color: rgba(255,255,255,.28); color: #fff; }
    /* Focus and active states mirror hover for accessibility (only batch/process) */
    body:not(.theme-dark) .tree-table tbody tr.batch-row.is-active td,
    body:not(.theme-dark) .tree-table tbody tr.process-row.is-active td,
    body:not(.theme-dark) .tree-table tbody tr.batch-row:focus-within td,
    body:not(.theme-dark) .tree-table tbody tr.process-row:focus-within td {
        background: var(--row-hover-orange) !important;
        color: var(--row-hover-orange-text) !important;
    }
    body:not(.theme-dark) .tree-table tbody tr.batch-row:focus-within td,
    body:not(.theme-dark) .tree-table tbody tr.process-row:focus-within td { outline: 2px solid rgba(255,255,255,.18); outline-offset: -2px; }
    body:not(.theme-dark) .tree-table tbody tr.batch-row.is-active a.tt-toggle,
    body:not(.theme-dark) .tree-table tbody tr.process-row.is-active a.tt-toggle,
    body:not(.theme-dark) .tree-table tbody tr.batch-row:focus-within a.tt-toggle,
    body:not(.theme-dark) .tree-table tbody tr.process-row:focus-within a.tt-toggle,
    body:not(.theme-dark) .tree-table tbody tr.batch-row.is-active .tt-icon,
    body:not(.theme-dark) .tree-table tbody tr.process-row.is-active .tt-icon,
    body:not(.theme-dark) .tree-table tbody tr.batch-row:focus-within .tt-icon,
    body:not(.theme-dark) .tree-table tbody tr.process-row:focus-within .tt-icon,
    body:not(.theme-dark) .tree-table tbody tr.batch-row.is-active .tt-meta,
    body:not(.theme-dark) .tree-table tbody tr.process-row.is-active .tt-meta,
    body:not(.theme-dark) .tree-table tbody tr.batch-row:focus-within .tt-meta,
    body:not(.theme-dark) .tree-table tbody tr.process-row:focus-within .tt-meta { color: var(--row-hover-orange-text) !important; }
    body:not(.theme-dark) .tree-table tbody tr.batch-row.is-active .tt-badge:not([data-type]),
    body:not(.theme-dark) .tree-table tbody tr.process-row.is-active .tt-badge:not([data-type]),
    body:not(.theme-dark) .tree-table tbody tr.batch-row:focus-within .tt-badge:not([data-type]),
    body:not(.theme-dark) .tree-table tbody tr.process-row:focus-within .tt-badge:not([data-type]) { background: rgba(255,255,255,.18); border-color: rgba(255,255,255,.32); color: #fff; }
    body:not(.theme-dark) .tree-table tbody tr.batch-row.is-active .tt-status,
    body:not(.theme-dark) .tree-table tbody tr.process-row.is-active .tt-status,
    body:not(.theme-dark) .tree-table tbody tr.batch-row:focus-within .tt-status,
    body:not(.theme-dark) .tree-table tbody tr.process-row:focus-within .tt-status { background: rgba(255,255,255,.15); border-color: rgba(255,255,255,.28); color: #fff; }
    /* Log-entry guide bar adapts on hover for visibility */
    .tree-table tbody tr.log-entry:hover td:first-child::before { background: rgba(255,255,255,.8); opacity: 1; }

    /* =============================================================
       Hierarchical UI redesign enhancements (Nov 2025)
       -------------------------------------------------------------
       Non-destructive layer that refines visual structure:
       - Uses existing data-level attributes (0..4) and classes (batch-row, process-row, log-entry)
       - Adds subtle card / strip differentiation via shadow + radius
       - Keeps orange batch + teal process rows intact; only adjusts neutral levels
       - Alternating log-entry backgrounds for faster scanning
       - Dark theme counterparts for all new effects with accessible contrast
       Maintenance: Remove this block if reverting to pre-redesign visuals.
    ============================================================= */
    :root {
        --hier-radius:6px;
        --hier-shadow:0 1px 2px rgba(0,0,0,.04),0 0 0 1px var(--tt-border);
        --hier-shadow-soft:0 1px 2px rgba(0,0,0,.03);
        --hier-prod-bg:#fcfdff;
        --hier-wo-bg:#ffffff;
        --hier-log-alt:#f5f7fa;
    }
    body.theme-dark {
        --hier-prod-bg:#141d2b;
        --hier-wo-bg:#1a2535;
        --hier-log-alt:#162233;
        --hier-shadow:0 1px 2px rgba(0,0,0,.6),0 0 0 1px #334155;
        --hier-shadow-soft:0 1px 2px rgba(0,0,0,.55);
    }
    /* Work Order row refinement (level 0, not batch/process) */
    .tree-table tbody tr.tt-row[data-level="0"]:not(.batch-row):not(.process-row) td:first-child {
        background:var(--hier-wo-bg);
        border-radius:var(--hier-radius);
        box-shadow:var(--hier-shadow);
        position:relative;
    }
    .tree-table tbody tr.tt-row[data-level="0"]:not(.batch-row):not(.process-row) td:first-child::after {
        content:""; position:absolute; left:0; right:0; bottom:0; height:3px; border-radius:0 0 var(--hier-radius) var(--hier-radius);
        background:linear-gradient(90deg,#0d6efd 0%, #86b7fe 60%, #0d6efd 100%);
        opacity:.15;
    }
    body.theme-dark .tree-table tbody tr.tt-row[data-level="0"]:not(.batch-row):not(.process-row) td:first-child::after {
        background:linear-gradient(90deg,#1e3a8a 0%, #0d6efd 70%, #1e3a8a 100%);
        opacity:.25;
    }
    /* Product row (level 1) subtle card */
    .tree-table tbody tr.tt-row[data-level="1"]:not(.batch-row):not(.process-row) td:first-child {
        background:var(--hier-prod-bg);
        border-radius:var(--hier-radius);
        box-shadow:var(--hier-shadow-soft);
    }
    body.theme-dark .tree-table tbody tr.tt-row[data-level="1"]:not(.batch-row):not(.process-row) td:first-child {
        box-shadow:var(--hier-shadow-soft);
    }
    /* Process row already solid teal; add gentle elevation */
    .tree-table tbody tr.process-row td:first-child { box-shadow:var(--hier-shadow-soft); }
    body.theme-dark .tree-table tbody tr.process-row td:first-child { box-shadow:var(--hier-shadow-soft); }
    /* Batch row keep strong accent but smooth corners */
    .tree-table tbody tr.batch-row td:first-child { border-radius:var(--hier-radius); }
    /* Spacing between major groups (inserted divider rows already present); amplify separation */
    .tree-table tbody tr.tt-row[data-level="0"] + tr.tt-row[data-level="0"] td:first-child { margin-top:.35rem; }
    /* Log rows alternating background for scan speed */
    .tree-table tbody tr.log-entry:nth-of-type(2n) td { background:var(--hier-log-alt); }
    body.theme-dark .tree-table tbody tr.log-entry:nth-of-type(2n) td { background:var(--hier-log-alt); }
    /* Preserve hover intent without overriding batch/process specialized hover */
    .tree-table tbody tr.tt-row[data-level="0"]:hover td:first-child,
    .tree-table tbody tr.tt-row[data-level="1"]:hover td:first-child { box-shadow:0 0 0 1px #0d6efd; }
    body.theme-dark .tree-table tbody tr.tt-row[data-level="0"]:hover td:first-child,
    body.theme-dark .tree-table tbody tr.tt-row[data-level="1"]:hover td:first-child { box-shadow:0 0 0 1px #475569; }
    /* Accessibility: ensure focus outline visible atop new box-shadows */
    .tree-table tbody tr.tt-row:focus-within td:first-child { outline:2px solid #0d6efd; outline-offset:2px; }
    body.theme-dark .tree-table tbody tr.tt-row:focus-within td:first-child { outline:2px solid #93c5fd; }
    /* Slightly tighten vertical rhythm after redesign */
    .tree-table th, .tree-table td { padding:.14rem .28rem; }
    /* End redesign block */
    
    /* Responsive: keep tree scrollable horizontally on small screens */
    .tree-table-wrapper { overflow-x: auto; }
    .tree-table { min-width: 820px; }
    @media (max-width: 768px) {
        .tree-table { font-size: .80rem; }
        .tt-actions .btn, .tt-actions button { min-width: 56px; }
    }

    /* Level 4 (log entry) dot: inline + flex centering so it's always perfectly centered with text */
    .tree-table tbody tr[data-level="4"] td.tt-indent-4 { display:flex; align-items:center; position:relative; }
    .tree-table tbody tr[data-level="4"] .tt-caret {
        position: static; display:inline-flex; align-items:center; justify-content:center;
        width:10px; height:10px; border-radius:50%;
        background:#60a5fa; border:none; outline:1px solid #d0d7de; font-size:0;
        margin:0 6px 0 2px; flex:0 0 auto; pointer-events:none;
    }
    body.theme-dark .tree-table tbody tr[data-level="4"] .tt-caret { background:#38bdf8; outline-color:#334155; }

    /* Keep the journal icon slightly smaller to share the same midline */
    .tree-table tbody tr[data-level="4"] .tt-icon { font-size:.85rem; margin-right:6px; }

    /* Tighten vertical rhythm: smaller log row and less space between batches */
    .tree-table tbody tr[data-level="3"] td { padding-top: .04rem; padding-bottom: .04rem; }
    .tree-table tbody tr.log-entry td { padding-top: 0; padding-bottom: 0; line-height: 1.08; border-bottom: 0 !important; }
    .tree-table tbody tr.log-entry .tt-meta { line-height: 1.1; }
    .tree-table tbody tr.log-entry .log-line {  }
    .tree-table tbody tr.log-entry .log-line { padding: .08rem .30rem .10rem; border-radius: 6px; }
    .tt-inline { gap:.20rem; }
    /* Remove hairline borders to eliminate tiny visual gaps between rows */
    .tree-table tbody tr.batch-row td,
    .tree-table tbody tr.process-row td { border-bottom: 0; }
</style>

<div class="card shadow-sm mb-4">
    <div class="card-header start-batch-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="sbh-icon d-flex align-items-center justify-content-center"><i class="bi bi-rocket-takeoff"></i></div>
            <div>
                <h5 class="mb-0 sbh-title">Start a Batch</h5>
                <div class="sbh-sub small">Select Work Order → Product → Process → Vendor / Employees</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button id="startFormToggle" type="button" class="btn btn-sm btn-outline-secondary" aria-expanded="false" title="Show/Hide form"><i class="bi bi-chevron-down"></i></button>
        </div>
    </div>
    <div id="startFormCollapsible" class="card-body" style="display:none;">
    <form id="startBatchForm" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="workOrder" class="form-label">Work Order</label>
                <select id="workOrder" class="form-select">
                    <option value="">-- select --</option>
                    <?php foreach (($workOrders ?? []) as $wo): ?>
                        <option value="<?= (int)$wo['id'] ?>">#<?= esc($wo['wo_number']) ?> — <?= esc($wo['status'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">&nbsp;</div>
            </div>
            <div class="col-md-3">
                <label for="product" class="form-label">Product</label>
                <select id="product" class="form-select" disabled>
                    <option value="">-- select work order first --</option>
                </select>
                <div id="productHint" class="form-text"></div>
            </div>
            <div class="col-md-3">
                <label for="process" class="form-label">Process</label>
                <select id="process" class="form-select" disabled>
                    <option value="">-- select product first --</option>
                </select>
                <div class="form-text">&nbsp;</div>
            </div>
            <div class="col-md-3">
                <label for="vendor" class="form-label">Vendor</label>
                <select id="vendor" class="form-select" disabled>
                    <option value="">-- select process first --</option>
                </select>
                <div class="form-text" id="vendorHint">&nbsp;</div>
            </div>
            <div class="col-md-3" id="employeesCol" style="display:none;">
                <label for="employees" class="form-label">Assigned To</label>
                <select id="employees" class="form-select" disabled>
                    <option value="">-- select process first --</option>
                </select>
                <div class="form-text" id="employeesHint">&nbsp;</div>
            </div>
            <div class="col-md-3">
                <label for="qty" class="form-label">Required Qty</label>
                <input id="qty" type="number" min="1" class="form-control" disabled>
                <div class="form-text">&nbsp;</div>
            </div>
            <div class="col-md-3">
                <label for="startDate" class="form-label">Start Date</label>
                <input id="startDate" type="date" class="form-control" disabled>
                <div class="form-text">&nbsp;</div>
            </div>
            <div class="col-md-3">
                <label for="startTime" class="form-label">Start Time</label>
                <input id="startTime" type="time" class="form-control" disabled>
                <div class="form-text">&nbsp;</div>
            </div>
            <!-- Start button moved to the bottom so it covers both batch + optional first-log -->
            <div class="col-12">
                <hr class="my-2">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="addLogNow" disabled>
                    <label class="form-check-label" for="addLogNow">Add first log now (same-day finish or immediate receipt)</label>
                </div>
            </div>
            <div id="startLogSection" class="col-12" style="display:none;">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-2">
                        <label class="form-label">Received</label>
                        <input id="slog_received" type="number" min="0" class="form-control" placeholder="e.g., 100" disabled>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Accepted</label>
                        <input id="slog_accepted" type="number" min="0" class="form-control" disabled>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Rejected</label>
                        <input id="slog_rejected" type="number" min="0" class="form-control" disabled>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Rework</label>
                        <input id="slog_rework" type="number" min="0" class="form-control" disabled>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Log Date</label>
                        <input id="slog_date" type="date" class="form-control" disabled>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Log Time</label>
                        <input id="slog_time" type="time" class="form-control" disabled>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                        <div class="btn-group" role="group" aria-label="quick helpers">
                            <button id="copyFromBatch" type="button" class="btn btn-outline-primary" disabled><i class="bi bi-clipboard2-check me-1"></i> Copy from batch</button>
                            <button id="sameDayFinish" type="button" class="btn btn-outline-success" disabled><i class="bi bi-check2-circle me-1"></i> Complete now</button>
                        </div>
                        <span class="text-muted small">(Copy fills qty/date/time; Complete sets Accepted=Required and zeroes rejects/rework)</span>
                        <div class="flex-fill"></div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Copy from batch will fill Received with Required Qty and set Log Date/Time from Start Date/Time. "Complete now" also fills Accepted and zeroes rejections/rework.</small>
                    </div>
                </div>
            </div>
            <div class="col-12 mt-2">
                <div id="startControls" class="d-flex align-items-center gap-2 border-top pt-2">
                    <button id="startBtn" type="submit" class="btn btn-primary" disabled>
                        <i class="bi bi-play-circle me-1"></i> Start Batch
                    </button>
                    <span id="startInfo" class="small-muted"></span>
                </div>
            </div>
            <div id="messages" class="px-1 pb-1"></div>
        </form>
    </div>
    <script>
    // Resolve base from current URL so it works under XAMPP subfolders
    const LOGS_BASE = window.location.pathname.replace(/\/$/, '');
    function getCsrf() { return document.querySelector('meta[name="csrf-token"]')?.content || ''; }
    async function api(path, method='GET', body=null) {
        const url = path.startsWith('http') ? path : `${LOGS_BASE}/${path.replace(/^\//,'')}`;
        const opts = { method, headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': getCsrf() } };
        if (body) { opts.body = JSON.stringify(body); opts.headers['Content-Type'] = 'application/json'; }
        try {
            const r = await fetch(url, opts);
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) {
                const text = await r.text();
                return { success: false, message: `Unexpected response (${r.status})`, raw: text };
            }
            return await r.json();
        } catch (e) {
            return { success: false, message: 'Network error: ' + (e?.message || e) };
        }
    }

    const els = {
        workOrder: document.getElementById('workOrder'),
        product: document.getElementById('product'),
        process: document.getElementById('process'),
    vendor: document.getElementById('vendor'),
        employees: document.getElementById('employees'),
        qty: document.getElementById('qty'),
    startDate: document.getElementById('startDate'),
        startBtn: document.getElementById('startBtn'),
        productHint: document.getElementById('productHint'),
        vendorHint: document.getElementById('vendorHint'),
        startInfo: document.getElementById('startInfo'),
        messages: document.getElementById('messages'),
    };

    function showMessage(type, text) {
        const el = document.createElement('div');
        el.className = 'alert alert-' + type + ' mt-2 mb-0';
        el.textContent = text;
        els.messages.appendChild(el);
        setTimeout(() => el.remove(), 5000);
    }

    async function loadProducts() {
        // reset downstream
    els.product.innerHTML = '<option value="">-- select work order first --</option>';
    els.product.disabled = true; els.productHint.textContent = '';
    els.process.innerHTML = '<option value="">-- select product first --</option>';
    els.process.disabled = true; els.vendor.innerHTML = '<option value="">-- select process first --</option>'; els.vendor.disabled = true; els.vendorHint.textContent='';
    if (els.employees) { els.employees.innerHTML = '<option value="">-- select process first --</option>'; els.employees.disabled = true; document.getElementById('employeesCol').style.display='none'; document.getElementById('employeesHint').textContent=''; }
    els.qty.value = ''; els.qty.disabled = true; els.startDate.value=''; els.startDate.disabled=true;
    // reset inline log section
    const slogToggle = document.getElementById('addLogNow');
    slogToggle.checked = false; slogToggle.disabled = true; document.getElementById('startLogSection').style.display = 'none';
    ['slog_received','slog_accepted','slog_rejected','slog_rework','slog_date','slog_time'].forEach(id => { const el = document.getElementById(id); if (el) { el.value=''; el.disabled = true; } });
    document.getElementById('sameDayFinish').disabled = true;
    const _cfb0 = document.getElementById('copyFromBatch'); if (_cfb0) _cfb0.disabled = true;
    els.startBtn.disabled = true; els.startInfo.textContent = '';

        const wo = els.workOrder.value;
        if (!wo) return;
        els.product.innerHTML = '<option>Loading…</option>';
        const res = await api('getProducts/' + encodeURIComponent(wo));
        if (!res?.success) {
            els.product.innerHTML = '<option value="">Failed to load</option>';
            showMessage('danger', res?.message || 'Failed to load products');
            return;
        }
        if (!res.products?.length) {
            els.product.innerHTML = '<option value="">No products for this WO</option>';
            return;
        }
        els.product.innerHTML = '<option value="">-- select --</option>' + res.products.map(p =>
            `<option value="${p.work_order_item_id}" data-product-id="${p.product_id}" data-ordered="${p.quantity_ordered}">${p.product_name} (ordered: ${p.quantity_ordered})</option>`
        ).join('');
    els.product.disabled = false;
    }

    async function loadProcesses() {
        // reset downstream
    els.process.innerHTML = '<option value="">-- select product first --</option>';
    els.process.disabled = true; els.vendor.innerHTML = '<option value="">-- select process first --</option>'; els.vendor.disabled = true; els.vendorHint.textContent='';
    if (els.employees) { els.employees.innerHTML = '<option value="">-- select process first --</option>'; els.employees.disabled = true; document.getElementById('employeesCol').style.display='none'; document.getElementById('employeesHint').textContent=''; }
    els.qty.value = ''; els.qty.disabled = true; els.startDate.value=''; els.startDate.disabled=true;
    // reset inline log section
    const slogToggle2 = document.getElementById('addLogNow');
    slogToggle2.checked = false; slogToggle2.disabled = true; document.getElementById('startLogSection').style.display = 'none';
    ['slog_received','slog_accepted','slog_rejected','slog_rework','slog_date','slog_time'].forEach(id => { const el = document.getElementById(id); if (el) { el.value=''; el.disabled = true; } });
    document.getElementById('sameDayFinish').disabled = true;
    const _cfb1 = document.getElementById('copyFromBatch'); if (_cfb1) _cfb1.disabled = true;
    els.startBtn.disabled = true; els.startInfo.textContent = '';

        const opt = els.product.selectedOptions[0];
        if (!opt || !opt.dataset.productId) return;
        els.productHint.textContent = `Ordered: ${opt.dataset.ordered}`;
        els.process.innerHTML = '<option>Loading…</option>';
        const res = await api('getProcesses?product_id=' + encodeURIComponent(opt.dataset.productId));
        if (!res?.success) {
            els.process.innerHTML = '<option value="">Failed to load</option>';
            showMessage('danger', res?.message || 'Failed to load processes');
            return;
        }
        if (!res.processes?.length) {
            els.process.innerHTML = '<option value="">No processes for product</option>';
            return;
        }
        els.process.innerHTML = '<option value="">-- select --</option>' + res.processes.map(pr =>
            `<option value="${pr.process_id}">${pr.process_name}</option>`
        ).join('');
    els.process.disabled = false;
    }

    function onProcessChange() {
    els.qty.disabled = !els.process.value;
    els.qty.value = '';
    els.startBtn.disabled = true;
    els.startDate.disabled = !els.process.value;
    els.startDate.value = new Date().toISOString().slice(0,10);
    // enable inline log toggle and fields
    const addLogNow = document.getElementById('addLogNow');
    addLogNow.disabled = !els.process.value;
    // default time HH:MM now
    const now = new Date();
    const hh = String(now.getHours()).padStart(2,'0');
    const mm = String(now.getMinutes()).padStart(2,'0');
    const stEl = document.getElementById('startTime');
    if (stEl) { stEl.disabled = !els.process.value; stEl.value = `${hh}:${mm}`; }
    // default inline log date/time to match start date + now
    const slogDate = document.getElementById('slog_date');
    const slogTime = document.getElementById('slog_time');
    if (slogDate) slogDate.value = els.startDate.value;
    if (slogTime) slogTime.value = `${hh}:${mm}`;
    loadVendors();
    }

    function onQtyInput() {
        const q = parseFloat(els.qty.value || '0');
        const hasDate = !!els.startDate.value;
        const hasTime = !!document.getElementById('startTime')?.value;
        // when inline log is active, ensure basic numeric validation too
        let inlineOk = true;
        if (document.getElementById('addLogNow')?.checked) {
            const a = parseFloat(document.getElementById('slog_accepted').value||'0');
            const rj = parseFloat(document.getElementById('slog_rejected').value||'0');
            const rw = parseFloat(document.getElementById('slog_rework').value||'0');
            const rv = parseFloat(document.getElementById('slog_received').value||'0');
            inlineOk = (a>=0 && rj>=0 && rw>=0 && rv>=0);
        }
        // Assignee requirement enforcement: employees vs department
        let assigneeOk = true;
        if (document.getElementById('employeesCol')?.style.display !== 'none') {
            const mode = els.employees?.getAttribute('data-requires') || '';
            const val = els.employees?.value || '';
            if (mode === 'employees') {
                // must select an employee
                assigneeOk = !!val && val.startsWith('emp:');
            } else if (mode === 'department') {
                // must select department or employee
                assigneeOk = !!val && (val.startsWith('dept:') || val.startsWith('emp:'));
            }
        }
        els.startBtn.disabled = !(els.workOrder.value && els.product.value && els.process.value && q > 0 && hasDate && hasTime && inlineOk && assigneeOk);
        els.startInfo.textContent = q > 0 ? `Ready to create batch of ${q}` : '';
    }

    async function startBatch() {
        const woItemId = parseInt(els.product.value, 10);
        const processId = parseInt(els.process.value, 10);
        const vendorId = parseInt(els.vendor.value || '0', 10);
        const qty = parseFloat(els.qty.value || '0');
        const start_date = els.startDate.value || undefined;
        if (!(woItemId > 0 && processId > 0 && qty > 0)) return;
        els.startBtn.disabled = true;
        els.startBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Starting…';
        const payload = { work_order_item_id: woItemId, process_id: processId, planned_qty: qty };
        if (!isNaN(vendorId) && vendorId > 0) payload.vendor_id = vendorId;
        if (document.getElementById('employeesCol')?.style.display !== 'none' && els.employees) {
            const sel = els.employees.value || '';
            if (sel && sel.includes(':')) {
                payload.assignee = sel; // e.g., emp:3 or dept:Finishing
            }
        }
    if (start_date) payload.start_date = start_date;
        const start_time = document.getElementById('startTime')?.value;
        if (start_time) payload.start_time = start_time;
        // Inline log support
        if (document.getElementById('addLogNow')?.checked) {
            const a = parseFloat(document.getElementById('slog_accepted').value||'0');
            const rj = parseFloat(document.getElementById('slog_rejected').value||'0');
            const rw = parseFloat(document.getElementById('slog_rework').value||'0');
            const rv = parseFloat(document.getElementById('slog_received').value||'0');
            const ldate = document.getElementById('slog_date').value || start_date || undefined;
            const ltime = document.getElementById('slog_time').value || undefined;
            // simple consistency warning like modal
            if (rv > 0 && (a + rj + rw) !== rv) {
                showMessage('warning', `Sum mismatch: Accepted(${a}) + Rejected(${rj}) + Rework(${rw}) = ${a+rj+rw}, but Received is ${rv}. Align values or clear Received.`);
                els.startBtn.disabled = false; els.startBtn.innerHTML = '<i class="bi bi-play-circle me-1"></i> Start Batch';
                return;
            }
            payload.add_log_now = true;
            payload.qty_completed = a; payload.qty_rejected = rj; payload.rework_qty = rw; if (rv>0) payload.qty_received = rv;
            if (ldate) payload.log_date = ldate; if (ltime) payload.log_time = ltime;
        }
        const res = await api('createBatch', 'POST', payload);
        els.startBtn.innerHTML = '<i class="bi bi-play-circle me-1"></i> Start Batch';
        els.startBtn.disabled = false;
        showMessage(res.success ? 'success' : 'danger', res.message || (res.success ? 'Done' : 'Failed'));
        if (res.success) {
            els.qty.value = '';
            els.startBtn.disabled = true;
            setStepState(4, 'done');
            loadTree();
        }
    }

    // Load assignees (employee or department) for in-house process
    async function loadEmployees(processId) {
        if (!els.employees) return;
        document.getElementById('employeesCol').style.display='block';
        els.employees.innerHTML = '<option>Loading…</option>';
        els.employees.disabled = true; document.getElementById('employeesHint').textContent='';
        let res = await api('getEmployeesForProcess?process_id=' + encodeURIComponent(processId));
        if (!res?.success) { els.employees.innerHTML = '<option value="">Failed to load</option>'; return; }
        els.employees.setAttribute('data-requires', res.responsibility_mode || '');
        const emps = res.employees || [];
        const depts = res.departments || [];
        const options = [];
        depts.forEach(d => { options.push(`<option value="dept:${d}">Dept: ${d}</option>`); });
        emps.forEach(e => { options.push(`<option value="emp:${e.id}">${e.name}</option>`); });
        if (!options.length) { els.employees.innerHTML = '<option value="">No assignees</option>'; els.employees.disabled=true; document.getElementById('employeesHint').textContent='No employees/departments found'; return; }
        els.employees.innerHTML = '<option value="">-- select assignee --</option>' + options.join('');
        els.employees.disabled = false;
        const hint = (res.responsibility_mode === 'employees') ? 'Select an employee' : (res.responsibility_mode === 'department' ? 'Select department or employee' : 'Optional');
        document.getElementById('employeesHint').textContent = hint;
    }

    // Load vendors for selected process (will trigger employees fetch for in-house)
    async function loadVendors() {
        els.vendor.innerHTML = '<option value="">Loading…</option>';
        els.vendor.disabled = true; els.vendorHint.textContent='';
        if (els.employees) { document.getElementById('employeesCol').style.display='none'; els.employees.innerHTML=''; els.employees.disabled=true; document.getElementById('employeesHint').textContent=''; }
        const processId = parseInt(els.process.value||'0',10);
        if (!(processId>0)) { els.vendor.innerHTML = '<option value="">-- select process first --</option>'; return; }
        let res = await api('getVendorsForProcess?process_id=' + encodeURIComponent(processId));
        if (!res?.success) { els.vendor.innerHTML = '<option value="">Failed to load</option>'; return; }
        if (!res.is_vendor_process) {
            els.vendor.innerHTML = '<option value="">In-house (no vendor)</option>';
            els.vendor.disabled = true;
            loadEmployees(processId);
            return;
        }
        const vs = res.vendors || [];
        if (!vs.length) { els.vendor.innerHTML = '<option value="">No vendors configured</option>'; els.vendor.disabled = true; return; }
        els.vendor.innerHTML = '<option value="">-- select --</option>' + vs.map(v=>`<option value="${v.id}">${v.name}</option>`).join('');
        els.vendor.disabled = false;
    }

    els.workOrder.addEventListener('change', loadProducts);
    els.product.addEventListener('change', loadProcesses);
    els.process.addEventListener('change', onProcessChange);
    els.qty.addEventListener('input', onQtyInput);
    document.getElementById('startBatchForm').addEventListener('submit', function(e){ e.preventDefault(); startBatch(); });
    // Collapsible Start form
    (function(){
        const wrap = document.getElementById('startFormCollapsible');
        const btn = document.getElementById('startFormToggle');
        const key = 'logs_start_form_collapsed';
        const apply = (collapsed)=>{
            if (!wrap || !btn) return;
            wrap.style.display = collapsed ? 'none' : 'block';
            btn.setAttribute('aria-expanded', (!collapsed).toString());
            btn.innerHTML = collapsed ? '<i class="bi bi-chevron-down"></i>' : '<i class="bi bi-chevron-up"></i>';
        };
        const init = localStorage.getItem(key);
        const collapsed = (init === null) ? true : (init === '1');
        apply(collapsed);
        btn?.addEventListener('click', ()=>{
            const currentlyHidden = wrap.style.display === 'none';
            // If hidden, show (collapsed=false). If visible, hide (collapsed=true)
            const newCollapsed = !currentlyHidden ? true : false;
            apply(newCollapsed);
            localStorage.setItem(key, newCollapsed ? '1' : '0');
        });
    })();
    // Toggle inline log
    document.getElementById('addLogNow').addEventListener('change', (e) => {
        const on = e.target.checked;
        const sect = document.getElementById('startLogSection');
        sect.style.display = on ? 'block' : 'none';
        ['slog_received','slog_accepted','slog_rejected','slog_rework','slog_date','slog_time'].forEach(id => {
            const el = document.getElementById(id); if (el) { el.disabled = !on; }
        });
        document.getElementById('sameDayFinish').disabled = !on;
        const cfb = document.getElementById('copyFromBatch'); if (cfb) cfb.disabled = !on;
        // defaults
        if (on) {
            document.getElementById('slog_date').value = els.startDate.value || new Date().toISOString().slice(0,10);
            const now = new Date(); const hh = String(now.getHours()).padStart(2,'0'); const mm = String(now.getMinutes()).padStart(2,'0');
            document.getElementById('slog_time').value = `${hh}:${mm}`;
        }
    });
    // Copy from batch helper
    document.getElementById('copyFromBatch').addEventListener('click', () => {
        const planned = parseFloat(els.qty.value||'0')||0;
        document.getElementById('slog_received').value = planned;
        // Align date/time with start
        const sd = els.startDate.value || new Date().toISOString().slice(0,10);
        document.getElementById('slog_date').value = sd;
        const st = document.getElementById('startTime')?.value;
        if (st) document.getElementById('slog_time').value = st;
    });

    // Complete now helper (fills accepted too)
    document.getElementById('sameDayFinish').addEventListener('click', () => {
        const q = parseFloat(els.qty.value||'0')||0;
        document.getElementById('slog_received').value = q;
        document.getElementById('slog_accepted').value = q;
        document.getElementById('slog_rejected').value = 0;
        document.getElementById('slog_rework').value = 0;
        // keep date/time as set (defaults to today and now)
    });

    </script>
</div>

<div class="card shadow-sm">
    <div class="card-header active-batches-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="abh-icon d-flex align-items-center justify-content-center"><i class="bi bi-list-task"></i></div>
            <div>
                <h5 class="mb-0 abh-title">Active Batches</h5>
                <div class="abh-sub small">Work Orders → Products → Processes → Batches → Logs</div>
            </div>
        </div>
        <div class="tree-toolbar">
            <button class="btn btn-outline-secondary btn-sm" id="ttExpandAllTop" title="Expand all"><i class="bi bi-arrows-expand"></i></button>
            <button class="btn btn-outline-secondary btn-sm" id="ttCollapseAllTop" title="Collapse all"><i class="bi bi-arrows-collapse"></i></button>
            <button class="btn btn-outline-secondary btn-sm" id="ttSortDate" title="Sort batches by date">Date ↓</button>
            <button class="btn btn-outline-primary btn-sm" id="openCardDemo" title="Open card-based UI demo"><i class="bi bi-layout-text-window"></i> Card UI Demo</button>
            <button class="btn btn-outline-info btn-sm" id="openAltDemo" title="Open alternative UI demo"><i class="bi bi-layers-half"></i> Alt UI Demo</button>
            <div class="btn-group btn-group-sm ms-2" role="group" aria-label="View switch">
                <button class="btn btn-outline-secondary" id="viewTree">Tree</button>
                <button class="btn btn-outline-secondary" id="viewAccordion">Accordion</button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div id="tree" class="tree"></div>
        <div id="accordionRoot" class="acc-root" style="display:none"></div>
    </div>
</div>

<script>
// Minimal styles for the accordion view (reused from demo)
const accCss = `
.acc-root{ font-size:0.85rem; }
details.acc{ padding:.15rem .2rem .2rem .2rem; margin:.25rem 0; position:relative; }
details.acc > summary{ cursor:pointer; font-weight:600; display:flex; align-items:center; gap:.5rem; }
details.acc > summary::-webkit-details-marker{ display:none; }
details.acc > summary::marker { content:""; }
.acc-tgl{ width:1.05rem; height:1.05rem; display:inline-grid; place-items:center; font-size:.85rem; font-weight:700; line-height:1; border:1px solid rgba(255,255,255,.35); border-radius:.25rem; background:rgba(255,255,255,.08); user-select:none; }
.acc-badges{ display:flex; flex-wrap:wrap; gap:.35rem; font-weight:500; }
.acc-badge{ background:#0d6efd1a; color:#69a6ff; padding:.12rem .45rem; border-radius:999px; font-size:.65rem; letter-spacing:.3px; }
.acc-badge.green{ background:#1987541a; color:#67c18c; }
.acc-badge.orange{ background:#fd7e141a; color:#ffb47a; }
.acc-badge.slate{ background:#6c757d26; color:#adb5bd; }
.acc-badge.purple{ background:#6f42c11a; color:#b998ff; }
.acc-badge.dark{ background:#00000033; color:#bbb; }
.acc-metrics{ display:flex; flex-wrap:wrap; gap:.25rem .5rem; margin-top:.15rem; font-size:.65rem; }
/* product/process connectors */
.acc-nested{ margin:.25rem 0 .2rem 1.4rem; position:relative; }
.acc-nested::before{ content:""; position:absolute; left:.4rem; top:.15rem; bottom:.3rem; border-left:2px dotted rgba(255,255,255,.25); }
.acc-nested > details.acc{ position:relative; padding-left:1rem; }
.acc-nested > details.acc::before{ content:""; position:absolute; left:-.6rem; top: 1rem; width:.9rem; border-top:2px dotted rgba(255,255,255,.25); }
.acc-nested > details.acc::after{ content:""; position:absolute; left:.35rem; top:.84rem; width:.35rem; height:.35rem; border-radius:50%; background:rgba(255,255,255,.5); box-shadow:0 0 0 2px rgba(0,0,0,.2) inset; }
.acc-nested.process-level::before{ left:.25rem; }
.acc-nested.process-level > details.acc::before{ left:-.75rem; width:1.1rem; }
.acc-nested.process-level > details.acc::after{ left:.2rem; }
/* batch summary */
details.batch-acc > summary{ display:flex; flex-wrap:wrap; align-items:center; gap:.4rem; padding:.25rem .45rem .3rem .45rem; border:1px solid rgba(255,255,255,.07); border-radius:.45rem; background:rgba(255,255,255,.025); font-size:.7rem; }
details.batch-acc[open] > summary{ background:rgba(255,255,255,.06); }
details.batch-acc > summary .code{ font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace; font-weight:600; }
/* logs */
.acc-log-lines{ position:relative; margin:.25rem 0 .3rem 1.6rem; }
.acc-log-lines::before{ content:""; position:absolute; left:-1.1rem; top:.2rem; bottom:.3rem; border-left:2px dotted rgba(255,255,255,.28); }
.acc-log-line{ font-size:.6rem; display:flex; flex-wrap:wrap; gap:.4rem; background:#f3f6fb; border:1px solid #dbe3ec; border-radius:.40rem; padding:.22rem .5rem .26rem; position:relative; margin:.22rem 0 .22rem .8rem; color:#1e293b; }
.acc-log-line:hover{ background:#e9eff8; }
body.theme-dark .acc-log-line{ background:#142031; border-color:#334155; color:#cbd5e1; }
body.theme-dark .acc-log-line:hover{ background:#1b2a3d; }
.acc-log-line::before{ content:""; position:absolute; left:-.75rem; top:50%; width:.7rem; border-top:2px dotted rgba(255,255,255,.25); }
.acc-log-line::after{ content:""; position:absolute; left:-.82rem; top:50%; transform:translateY(-50%); width:.50rem; height:.50rem; border-radius:50%; background:#60a5fa; box-shadow:0 0 0 2px rgba(255,255,255,.25); }
body.theme-dark .acc-log-line::after{ background:#38bdf8; box-shadow:0 0 0 2px rgba(0,0,0,.4); }
`; (function(){ if(!document.getElementById('accStyles')){ const st=document.createElement('style'); st.id='accStyles'; st.textContent=accCss; document.head.appendChild(st); }})();
function el(tag, className, html) {
    const e = document.createElement(tag);
    if (className) e.className = className;
    if (html !== undefined) e.innerHTML = html;
    return e;
}

// View switch helpers (Tree <-> Accordion)
const VIEW_MODE_KEY = 'logs_view_mode';
function getViewMode(){ try { return localStorage.getItem(VIEW_MODE_KEY) || 'tree'; } catch(_) { return 'tree'; } }
function setViewMode(mode){ try { localStorage.setItem(VIEW_MODE_KEY, mode); } catch(_) { /* ignore */ } }
function markSwitchButtons(mode){
    const bt = document.getElementById('viewTree');
    const ba = document.getElementById('viewAccordion');
    if (bt && ba) {
        if (mode === 'accordion') {
            ba.classList.add('btn-primary'); ba.classList.remove('btn-outline-secondary');
            bt.classList.remove('btn-primary'); bt.classList.add('btn-outline-secondary');
        } else {
            bt.classList.add('btn-primary'); bt.classList.remove('btn-outline-secondary');
            ba.classList.remove('btn-primary'); ba.classList.add('btn-outline-secondary');
        }
    }
}
function applyViewMode(initial=false){
    const mode = getViewMode();
    const tree = document.getElementById('tree');
    const acc = document.getElementById('accordionRoot');
    if (!tree || !acc) return;
    if (mode === 'accordion') {
        tree.style.display = 'none';
        acc.style.display = '';
        if (!initial || acc.childElementCount === 0) buildMainAccordion();
    } else {
        acc.style.display = 'none';
        tree.style.display = '';
        if (!initial || tree.childElementCount === 0) loadTree();
    }
    markSwitchButtons(mode);
}
function refreshActiveView(){ const mode = getViewMode(); if (mode === 'accordion') buildMainAccordion(); else loadTree(); }

async function loadTree() {
    const tree = document.getElementById('tree');
    tree.innerHTML = '<div class="text-muted">Loading…</div>';
    let res;
    try {
        res = await api('hierarchy');
    } catch (e) {
        tree.innerHTML = `<div class="alert alert-danger">Error loading: ${e?.message || e}</div>`;
        return;
    }
    if (!res || res.success === false) { tree.innerHTML = `<div class=\"alert alert-danger\">Failed to load${res?.message ? ': ' + res.message : ''}</div>`; return; }
    if (!Array.isArray(res.hierarchy) || res.hierarchy.length === 0) { tree.innerHTML = '<div class="text-muted">No batches started</div>'; return; }

    // Helpers
    const sums = (batches) => {
        let planned=0, acc=0, rej=0, rew=0, started=0, pend=0;
        (batches||[]).forEach(b => {
            planned += Number(b.planned_qty||0);
            const t=b.totals||{}; const a=Number(t.accepted||0), r=Number(t.rejected||0), w=Number(t.rework||0);
            const s = Number(t.started ?? (a+r+w));
            const p = Number(t.pending ?? Math.max(0, (b.planned_qty||0) - s));
            acc += a; rej += r; rew += w; started += s; pend += p;
        });
        return { planned, acc, rej, rew, started, pend };
    };
    const fmt = (n) => {
        const v = Number(n||0);
        return v > 0 ? v : '';
    };
    const fmtDate = (str) => {
        if (!str) return '';
        // Normalize to a Date; if invalid keep original string
        const d = new Date(str);
        if (isNaN(d.getTime())) return str;
        const dd = String(d.getDate()).padStart(2,'0');
        const mmm = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getMonth()];
        const yy = String(d.getFullYear()).slice(-2);
        return `${dd}-${mmm}-${yy}`; // e.g., 31-Oct-25
    };
    const fmtTime = (str) => {
        if (!str) return '';
        const d = new Date(str);
        if (isNaN(d.getTime())) return '';
        const hh = String(d.getHours()).padStart(2,'0');
        const mm = String(d.getMinutes()).padStart(2,'0');
        return `${hh}:${mm}`;
    };
    const fmtDateTime = (str) => {
        const dd = fmtDate(str);
        const tt = fmtTime(str);
        return tt ? `${dd} ${tt}` : dd;
    };

    // Build toolbar + table
    const stateKey = 'logs_tree_state_v1';
    const loadState = () => { try { return JSON.parse(localStorage.getItem(stateKey) || '{}'); } catch(_) { return {}; } };
    const saveState = (s) => { try { localStorage.setItem(stateKey, JSON.stringify(s)); } catch(_) { /* ignore */ } };
    const treeState = loadState();
    // Tracks last depth expanded via quick buttons (L1/L2/L3). When user clicks the same depth again, collapse all.
    let quickDepth = null;
    const toolbar = el('div', 'tree-toolbar');
    toolbar.innerHTML = `
        <button class="btn btn-outline-secondary btn-sm" id="ttExpandAll">Expand All</button>
        <button class="btn btn-outline-secondary btn-sm" id="ttCollapseAll">Collapse All</button>
        <button class="btn btn-outline-secondary btn-sm" id="ttExpandL1" title="Expand to Products">L1</button>
        <button class="btn btn-outline-secondary btn-sm" id="ttExpandL2" title="Expand to Processes">L2</button>
        <button class="btn btn-outline-secondary btn-sm" id="ttExpandL3" title="Expand to Batches">L3</button>
        <button class="btn btn-outline-secondary btn-sm" id="ttSortDateInner" title="Sort batches by date">Date ↓</button>
    `;
    const table = el('table', 'tree-table table table-sm');
    // Remove column headings to reduce clutter; keep only tbody
    table.innerHTML = `<tbody></tbody>`;
    const tbody = table.querySelector('tbody');

    const addRow = (obj) => { tbody.appendChild(obj); return obj; };

    // Sorting preference
    const SORT_KEY = 'logs_batch_sort_order';
    function getSortOrder(){ try { return localStorage.getItem(SORT_KEY) || 'desc'; } catch(_) { return 'desc'; } }
    function setSortOrder(v){ try { localStorage.setItem(SORT_KEY, v); } catch(_) {} }
    function sortBatches(arr){
        const order = getSortOrder();
        const dir = order === 'asc' ? 1 : -1;
        return (arr||[]).slice().sort((a,b)=>{
            const ad = new Date(a.created_at||a.start_date||0).getTime() || 0;
            const bd = new Date(b.created_at||b.start_date||0).getTime() || 0;
            if (ad === bd) return (Number(a.batch_id||0) - Number(b.batch_id||0)) * dir;
            return (ad - bd) * dir;
        });
    }

    res.hierarchy.forEach((wo, widx, warr) => {
    const woId = `wo-${wo.work_order_id}`;
        // Collect all batches under this WO
        const woBatches = (wo.products||[]).flatMap(p => (p.processes||[]).flatMap(pr => pr.batches||[]));
        const wsum = sums(woBatches);
        const trWO = el('tr', 'tt-row');
        trWO.dataset.level = '0'; trWO.dataset.id = woId;
        trWO.innerHTML = `
            <td class="tt-indent-0">
                <span class="tt-caret" role="button" tabindex="0" aria-label="Toggle" data-target="${woId}">+</span>
                <i class="bi bi-clipboard-check tt-icon"></i>
                <a href="#" class="tt-toggle" data-target="${woId}">WO ${wo.wo_number}</a>
                <span class="tt-meta ms-2">${woBatches.length} batches</span>
            </td>
            <td class="text-end"></td>
        `;
        addRow(trWO);

        (wo.products||[]).forEach((p, pidx, parr) => {
            const pid = `prod-${wo.work_order_id}-${p.product_id}`;
            const pBatches = (p.processes||[]).flatMap(pr => pr.batches||[]);
            const psum = sums(pBatches);
            const trP = el('tr', 'tt-row hidden');
            trP.dataset.level = '1'; trP.dataset.id = pid; trP.dataset.parent = woId;
            trP.dataset.last = (pidx === (parr.length - 1)) ? '1' : '0';
            trP.innerHTML = `
                <td class="tt-indent-1">
                    <span class="tt-caret" role="button" tabindex="0" aria-label="Toggle" data-target="${pid}">+</span>
                    <i class="bi bi-box tt-icon"></i>
                    <a href="#" class="tt-toggle" data-target="${pid}">${p.product_name}</a>
                    ${p.ordered_qty ? `<span class=\"tt-badge\">Required ${p.ordered_qty}</span>` : ''}
                </td>
                <td class="text-end"></td>
            `;
            addRow(trP);

            (p.processes||[]).forEach((pr, ridx, rarr) => {
                const prid = `proc-${wo.work_order_id}-${p.product_id}-${pr.process_id}`;
                const rsum = sums(pr.batches||[]);
                const trR = el('tr', 'tt-row hidden process-row');
                trR.dataset.level = '2'; trR.dataset.id = prid; trR.dataset.parent = pid; trR.dataset.last = (ridx === (rarr.length - 1)) ? '1' : '0';
                const targetVal = pr.ordered_qty || '';
                const acceptedVal = rsum.acc || 0;
                const targetBadge = targetVal
                    ? `<span class=\"tt-badge\">Req: ${targetVal} / Acc: ${acceptedVal}</span>`
                    : '';
                trR.innerHTML = `
                    <td class="tt-indent-2">
                        <span class="tt-caret" role="button" tabindex="0" aria-label="Toggle" data-target="${prid}">+</span>
                        <a href="#" class="tt-toggle" data-target="${prid}">${pr.process_name}</a>
                        ${targetBadge}
                    </td>
                    <td class="text-end"></td>
                `;
                addRow(trR);

                // sort batches by date according to preference
                const batchesSorted = sortBatches(pr.batches||[]);
                batchesSorted.forEach((b, bidx, barr) => {
                    const bid = `batch-${b.batch_id}`;
                    const t = b.totals || { accepted:0, rejected:0, rework:0 };
                    const trB = el('tr', 'tt-row hidden batch-row');
                    trB.dataset.level = '3'; trB.dataset.id = bid; trB.dataset.parent = prid; trB.dataset.last = (bidx === (barr.length - 1)) ? '1' : '0';
                    const statusBadge = (pendingVal) => {
                        const p = Number(pendingVal||0);
                        if (p <= 0) return `<span class=\"tt-status tt-s-done\">✓ Completed</span>`;
                        return `<span class=\"tt-status tt-s-inprog\"><span class=\"tt-loading\"></span> In progress</span>`;
                    };
                    // Derive started/pending with preference: server totals -> derive from pending -> derive from logs
                    const aN = Number(t.accepted||0), rN = Number(t.rejected||0), wN = Number(t.rework||0);
                    const plannedN = Number(b.planned_qty||0);
                    let startedVal = (t.started != null ? Number(t.started) : null);
                    let pendingVal = (t.pending != null ? Number(t.pending) : null);
                    if (startedVal == null && pendingVal != null) {
                        startedVal = Math.max(0, plannedN - pendingVal);
                    } else if (startedVal != null && pendingVal == null) {
                        pendingVal = Math.max(0, plannedN - startedVal);
                    } else if (startedVal == null && pendingVal == null) {
                        // Prefer showing planned as started when no server totals exist (gives 103 instead of sum-based fallback 100)
                        startedVal = plannedN;
                        pendingVal = Math.max(0, plannedN - startedVal);
                    }
                    const empNames = (b.employees||[]).map(e=>e.name).join(', ');
                    const deptName = b.department || '';
                    trB.innerHTML = `
                        <td class="tt-indent-3">
                            <span class="tt-caret" role="button" tabindex="0" aria-label="Toggle" data-target="${bid}">+</span>
                            <i class="bi bi-collection tt-icon"></i>
                            <a href=\"#\" class=\"tt-toggle\" data-target=\"${bid}\">${b.batch_code}</a>
                            <span class=\"tt-inline\">
                                ${b.vendor_name ? `<span class=\"tt-badge slim\">Vendor: ${b.vendor_name}</span>` : ''}
                                ${deptName ? `<span class=\"tt-badge slim\">Dept: ${deptName}</span>` : ''}
                                ${empNames ? `<span class=\"tt-badge slim\">Emp: ${empNames}</span>` : ''}
                                ${b.created_at ? `<span class=\"tt-badge slim\">Start: ${fmtDateTime(b.created_at)}</span>` : ''}
                                ${statusBadge(pendingVal)}
                                <span class=\"tt-badge slim\" data-type=\"started\">Started: ${fmt(startedVal) || 0}</span>
                                <span class=\"tt-badge slim\" data-type=\"accepted\">Accepted: ${fmt(t.accepted) || 0}</span>
                                <span class=\"tt-badge slim\" data-type=\"rejected\">Rejected: ${fmt(t.rejected) || 0}</span>
                                <span class=\"tt-badge slim\" data-type=\"rework\">Rework: ${fmt(t.rework) || 0}</span>
                                <span class=\"tt-badge slim\" data-type=\"pending\">Remaining: ${fmt(pendingVal) || 0}</span>
                            </span>
                        </td>
                        <td class="text-end tt-actions">
                            <button class=\"btn btn-outline-primary btn-sm btn-icon\" title=\"Add Log\" aria-label=\"Add Log\" data-add-log=\"${b.batch_id}\" data-acc=\"${t.accepted||0}\" data-rej=\"${t.rejected||0}\" data-rew=\"${t.rework||0}\" data-pend=\"${pendingVal}\" data-started=\"${startedVal}\"><i class=\"bi bi-plus-square\"></i></button>
                        </td>
                    `;
                    addRow(trB);

                    const logs = b.logs || [];
                    // If no logs, show a Delete Batch button
                    if (!logs.length) {
                        const actCell = trB.querySelector('.tt-actions');
                        const delBtn = document.createElement('button');
                        delBtn.className = 'btn btn-outline-danger btn-sm btn-icon ms-1';
                        delBtn.title = 'Delete Batch';
                        delBtn.setAttribute('aria-label', 'Delete Batch');
                        delBtn.innerHTML = '<i class="bi bi-trash"></i>';
                        delBtn.setAttribute('data-del-batch', String(b.batch_id));
                        actCell.appendChild(delBtn);
                    }

                    logs.forEach((lg, lidx, larr) => {
                        const rcv = Number(lg.qty_received ?? lg.received_qty ?? 0);
                        // Accepted may be stored in qty_completed/accepted_qty, and some datasets put repaired_qty separately
                        const accBase = Number(lg.qty_completed ?? lg.accepted_qty ?? 0);
                        const accRep = Number(lg.repaired_qty ?? lg.qty_repaired ?? 0);
                        const acc = accBase + accRep;
                        const rej = Number(lg.qty_rejected ?? lg.rejected_qty ?? 0);
                        const rew = Number(lg.rework_qty ?? lg.qty_rework ?? lg.sent_for_rework ?? lg.reworked_qty ?? 0);
                        const dt = lg.log_date || lg.created_at || '';
                        // Build a single-line summary and format date per request
                        const dtFmt = fmtDateTime(lg.created_at || dt);
                        const lid = `log-${lg.id}`;
                        const trL = el('tr', 'tt-row hidden log-entry');
                        trL.dataset.level = '4'; trL.dataset.id = lid; trL.dataset.parent = bid; trL.dataset.last = (lidx === (larr.length - 1)) ? '1' : '0';
                        // Batch employees (same for all logs under this batch)
                        const logEmpNames = empNames ? ` <span class=\"tt-badge slim\">Emp: ${empNames}</span>` : '';
                        trL.innerHTML = `
                            <td class="tt-indent-4">
                                <span class="tt-caret">•</span>
                                <i class="bi bi-journal-text tt-icon"></i>
                                <span class="tt-meta"><strong>${dtFmt}</strong>${b.vendor_name ? ` <span class=\"tt-badge slim\">Vendor: ${b.vendor_name}</span>` : ''}${b.department ? ` <span class=\"tt-badge slim\">Dept: ${b.department}</span>` : ''}${logEmpNames} - <span class="log-line">Received = ${rcv} -> Accepted = ${acc} -> Rejected = ${rej} -> Rework = ${rew} ( Remaining = ${fmt(pendingVal) || 0} )</span></span>
                            </td>
                            <td class="text-end tt-actions">
                                <button class="btn btn-outline-info btn-sm btn-icon me-1" title="Info" aria-label="Info" data-info-log="${lg.id}" data-log-accepted="${acc}" data-log-rejected="${rej}" data-log-rework="${rew}" data-log-received="${rcv}" data-log-date="${dtFmt}" data-log-pending="${fmt(pendingVal) || 0}" data-log-started="${fmt(startedVal) || 0}" data-log-batch="${b.batch_code}"><i class="bi bi-bar-chart"></i></button>
                                <button class="btn btn-outline-secondary btn-sm btn-icon me-1" title="Edit" aria-label="Edit" data-edit-log="${lg.id}" data-rcv="${rcv}" data-acc="${acc}" data-rej="${rej}" data-rew="${rew}" data-dt="${dt}" data-notes=""><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-outline-danger btn-sm btn-icon" title="Delete" aria-label="Delete" data-del-log="${lg.id}"><i class="bi bi-trash"></i></button>
                            </td>
                        `;
                        addRow(trL);
                    });
                });
            });
        });

        // After each WO section, insert a light divider row
        const divTr = el('tr');
        const td = el('td', '', '<div style="height:4px;"></div>');
        td.colSpan = 2; divTr.appendChild(td); addRow(divTr);
    });

    // Render the table
    tree.innerHTML = '';
    tree.appendChild(toolbar);
    const wrapper = el('div', 'tree-table-wrapper');
    wrapper.appendChild(table);
    tree.appendChild(wrapper);
    // Initialize sort buttons labels to reflect current order
    function updateSortButtons(){
        const label = getSortOrder() === 'asc' ? 'Date ↑' : 'Date ↓';
        document.getElementById('ttSortDate')?.classList?.add('btn-outline-secondary');
        document.getElementById('ttSortDate') && (document.getElementById('ttSortDate').textContent = label);
        document.getElementById('ttSortDateInner') && (document.getElementById('ttSortDateInner').textContent = label);
    }
    updateSortButtons();
    document.getElementById('ttSortDate')?.addEventListener('click', ()=>{ const v=getSortOrder()==='asc'?'desc':'asc'; setSortOrder(v); loadTree(); });
    document.getElementById('ttSortDateInner')?.addEventListener('click', ()=>{ const v=getSortOrder()==='asc'?'desc':'asc'; setSortOrder(v); loadTree(); });

    // Wire toggles
    const getLevel = tr => parseInt(tr.dataset.level||'0',10);
    // Mark process groups for boxing (process row + all descendants until next level<=2)
    (function markProcessGroups(){
        // Clear existing markers (in case of reload)
        table.querySelectorAll('.proc-group-start,.proc-group-mid,.proc-group-end').forEach(tr=>{
            tr.classList.remove('proc-group-start','proc-group-mid','proc-group-end');
        });
        const rows = Array.from(table.querySelectorAll('tr.tt-row'));
        rows.forEach((tr, idx) => {
            if (!tr.classList.contains('process-row')) return;
            // this process row is group start
            tr.classList.add('proc-group-start');
            let j = idx + 1;
            let last = tr;
            while (j < rows.length) {
                const n = rows[j];
                if (!n.classList.contains('tt-row')) break;
                const nl = getLevel(n);
                if (nl <= 2) break; // reached next process/product/wo
                last = n;
                n.classList.add('proc-group-mid');
                j++;
            }
            // If no children, make the same row end as well
            if (last === tr) {
                tr.classList.add('proc-group-end');
            } else {
                // convert last mid to end
                last.classList.remove('proc-group-mid');
                last.classList.add('proc-group-end');
            }
        });
    })();
    function collapseFrom(tr) {
        const level = getLevel(tr);
        let n = tr.nextElementSibling;
        while (n && n.dataset && n.classList.contains('tt-row')) {
            const nl = getLevel(n);
            if (nl <= level) break;
            n.classList.add('hidden');
            // children carats should show + when collapsed (except level 4 which is a dot)
            const cchild = n.querySelector?.('.tt-caret');
            if (cchild) cchild.textContent = (getLevel(n) === 4 ? '•' : '+');
            n = n.nextElementSibling;
        }
        tr.querySelector('.tt-caret').textContent = '+';
        tr.dataset.expanded = 'false';
    }
    function expandImmediate(tr) {
        const id = tr.dataset.id; const level = getLevel(tr);
        let n = tr.nextElementSibling;
        while (n && n.dataset && n.classList.contains('tt-row')) {
            const nl = getLevel(n);
            if (nl <= level) break;
            if (n.dataset.parent === id) { n.classList.remove('hidden'); }
            n = n.nextElementSibling;
        }
        tr.querySelector('.tt-caret').textContent = '−';
        tr.dataset.expanded = 'true';
    }
    table.querySelectorAll('.tt-toggle').forEach(a => {
        a.addEventListener('click', e => {
            e.preventDefault();
            const tr = a.closest('tr');
            const id = tr?.dataset?.id;
            if (tr.dataset.expanded === 'true') {
                collapseFrom(tr);
                const c = tr.querySelector('.tt-caret'); if (c) c.textContent = '+';
                if (id) { treeState[id] = false; saveState(treeState); }
            } else {
                expandImmediate(tr);
                const c = tr.querySelector('.tt-caret'); if (c) c.textContent = '−';
                if (id) { treeState[id] = true; saveState(treeState); }
            }
        });
    });

    // Info popup handling
    function buildInfoPanel(data) {
        const panel = document.createElement('div');
        panel.className = 'log-info-panel';
        const donutBg = `conic-gradient(#22c55e 0 ${data.acceptedPct}%, #ef4444 ${data.acceptedPct}% ${data.accRejPct}%, #f59e0b ${data.accRejPct}% 100%)`;
        panel.innerHTML = `
            <div class="log-info-header">
                <div class="log-info-title"><i class="bi bi-bar-chart"></i> Log Summary</div>
                <button class="lip-close" type="button" aria-label="Close">Close</button>
            </div>
            <div class="mb-2 small text-muted">${data.date || ''}</div>
            <div class="row g-3 align-items-center">
                <div class="col-auto">
                    <div class="lip-donut" style="background:${donutBg}"></div>
                </div>
                <div class="col">
                    <div class="lip-legend">
                        <div class="ll"><span class="ll-dot ll-acc"></span> Accepted: <strong>${data.accepted}</strong> (${data.acceptedPct}%)</div>
                        <div class="ll"><span class="ll-dot ll-rej"></span> Rejected: <strong>${data.rejected}</strong> (${data.rejectedPct}%)</div>
                        <div class="ll"><span class="ll-dot ll-rew"></span> Rework: <strong>${data.rework}</strong> (${data.reworkPct}%)</div>
                    </div>
                </div>
            </div>
            <div class="mt-3 lip-grid">
                <div class="lip-stat"><span>Received</span><div class="lip-val">${data.received}</div><div class="lip-bar"><div class="lip-bar-fill" style="width:${data.receivedPct}%; background:#60a5fa"></div></div></div>
                <div class="lip-stat"><span>Accepted</span><div class="lip-val">${data.accepted}</div><div class="lip-bar"><div class="lip-bar-fill" style="width:${data.acceptedPct}%; background:#22c55e"></div></div></div>
                <div class="lip-stat"><span>Rejected</span><div class="lip-val">${data.rejected}</div><div class="lip-bar"><div class="lip-bar-fill" style="width:${data.rejectedPct}%; background:#ef4444"></div></div></div>
                <div class="lip-stat"><span>Rework</span><div class="lip-val">${data.rework}</div><div class="lip-bar"><div class="lip-bar-fill" style="width:${data.reworkPct}%; background:#f59e0b"></div></div></div>
            </div>
        `;
        panel.querySelector('.lip-close').addEventListener('click', ()=> closeInfoPanel());
        return panel;
    }
    function closeInfoPanel(){
        document.querySelectorAll('.log-info-panel').forEach(p=>p.remove());
        document.querySelectorAll('.lip-overlay').forEach(o=>o.remove());
        document.removeEventListener('keydown', escCloseInfo);
    }
    function escCloseInfo(e){ if (e.key === 'Escape') closeInfoPanel(); }
    function showLogInfo(btn){
        // Remove existing panel & overlay
        closeInfoPanel();
        const received = Number(btn.getAttribute('data-log-received')||0);
        const accepted = Number(btn.getAttribute('data-log-accepted')||0);
        const rejected = Number(btn.getAttribute('data-log-rejected')||0);
        const rework = Number(btn.getAttribute('data-log-rework')||0);
        const total = Math.max(received, accepted+rejected+rework, 1);
        const data = {
            date: btn.getAttribute('data-log-date')||'',
            received, accepted, rejected, rework,
            receivedPct: Math.min(100, (received/total)*100).toFixed(1),
            acceptedPct: Math.min(100, (accepted/total)*100).toFixed(1),
            rejectedPct: Math.min(100, (rejected/total)*100).toFixed(1),
            reworkPct: Math.min(100, (rework/total)*100).toFixed(1),
            accRejPct: Math.min(100, ((accepted+rejected)/total)*100).toFixed(1)
        };
        const panel = buildInfoPanel(data);
        const overlay = document.createElement('div'); overlay.className = 'lip-overlay';
        overlay.addEventListener('click', closeInfoPanel);
        document.body.appendChild(overlay);
        document.body.appendChild(panel);
        document.addEventListener('keydown', escCloseInfo);
    }
    table.addEventListener('click', (e)=>{
        const btn = e.target.closest('[data-info-log]');
        if (btn) { e.preventDefault(); showLogInfo(btn); }
    });

    // Make caret (+/-) clickable to toggle expand/collapse
    table.addEventListener('click', (e)=>{
        const caret = e.target.closest('.tt-caret');
        if (!caret) return;
        const tr = caret.closest('tr.tt-row');
        if (!tr) return;
        const id = caret.getAttribute('data-target') || tr.dataset.id;
        const expanded = tr.dataset.expanded === 'true';
        if (expanded) { collapseFrom(tr); caret.textContent = '+'; treeState[id] = false; }
        else { expandImmediate(tr); caret.textContent = '−'; treeState[id] = true; }
        saveState(treeState);
        e.preventDefault();
    });
    // Keyboard support for caret
    table.addEventListener('keydown', (e)=>{
        const caret = e.target.closest('.tt-caret');
        if (!caret) return;
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); caret.click(); }
    });

    // Top toolbar expand/collapse buttons
    document.getElementById('ttExpandAllTop')?.addEventListener('click', (e)=>{ e.preventDefault(); table.querySelectorAll('.tt-row').forEach(tr=>{ if(tr.dataset.level==='0'||tr.dataset.level==='1'||tr.dataset.level==='2'||tr.dataset.level==='3'){ expandImmediate(tr); } }); });
    document.getElementById('ttCollapseAllTop')?.addEventListener('click', (e)=>{ e.preventDefault(); table.querySelectorAll('.tt-row').forEach(tr=>{ if(tr.dataset.level!=='0'){ tr.classList.add('hidden'); tr.dataset.expanded='false'; const c=tr.querySelector('.tt-caret'); if(c) c.textContent = (tr.dataset.level==='4') ? '•' : '+'; } }); });

    // Toolbar expand/collapse
    document.getElementById('ttExpandAll').addEventListener('click', () => {
        table.querySelectorAll('tr.tt-row[data-level="0"], tr.tt-row[data-level="1"], tr.tt-row[data-level="2"], tr.tt-row[data-level="3"]').forEach(tr => {
            expandImmediate(tr);
            const c = tr.querySelector('.tt-caret'); if (c) c.textContent = '−';
            // also unhide direct children
            const id = tr.dataset.id; let n = tr.nextElementSibling; const level = getLevel(tr);
            while (n && n.classList && n.classList.contains('tt-row')) {
                const nl = getLevel(n); if (nl <= level) break; if (n.dataset.parent === id) n.classList.remove('hidden'); n = n.nextElementSibling;
            }
            treeState[tr.dataset.id] = true;
        });
        saveState(treeState);
    });
    function collapseAllFn(){
        table.querySelectorAll('tr.tt-row[data-level="0"], tr.tt-row[data-level="1"], tr.tt-row[data-level="2"], tr.tt-row[data-level="3"]').forEach(tr => {
            collapseFrom(tr);
            const c = tr.querySelector('.tt-caret'); if (c) c.textContent = '+';
            treeState[tr.dataset.id] = false;
        });
        // Hide all level>0 rows explicitly to ensure cleanliness
        table.querySelectorAll('tr.tt-row').forEach(tr => { if (getLevel(tr) > 0) tr.classList.add('hidden'); });
        saveState(treeState);
    }
    document.getElementById('ttCollapseAll').addEventListener('click', () => { collapseAllFn(); quickDepth = null; });
    // Expand to specific depth (0..3)
    function expandToLevel(depth) {
        // Toggle behavior: if clicking same depth twice, collapse all
        if (quickDepth === depth) { collapseAllFn(); quickDepth = null; return; }

        // Start from a clean collapsed state
        collapseAllFn();

        // Expand rows up to the selected depth
        // Expand level 0 first, then progressively make direct children visible
        [0,1,2,3].forEach(lvlTarget => {
            if (lvlTarget > depth) return;
            table.querySelectorAll(`tr.tt-row[data-level="${lvlTarget}"]`).forEach(tr => {
                expandImmediate(tr);
                const c = tr.querySelector('.tt-caret'); if (c) c.textContent = '−';
                if (tr.dataset.id) treeState[tr.dataset.id] = true;
            });
        });

        // Hide anything deeper than the requested depth (defensive)
        table.querySelectorAll('tr.tt-row').forEach(tr => {
            const lvl = getLevel(tr);
            if (lvl > depth) { tr.classList.add('hidden'); tr.dataset.expanded = 'false'; const c = tr.querySelector('.tt-caret'); if (c) c.textContent = (lvl === 4 ? '•' : '+'); if (tr.dataset.id) treeState[tr.dataset.id] = false; }
        });

        saveState(treeState);
        quickDepth = depth;
    }
    document.getElementById('ttExpandL1').addEventListener('click', ()=> expandToLevel(1));
    document.getElementById('ttExpandL2').addEventListener('click', ()=> expandToLevel(2));
    document.getElementById('ttExpandL3').addEventListener('click', ()=> expandToLevel(3));

    // Restore saved expand/collapse state from previous visits
    table.querySelectorAll('tr.tt-row').forEach(tr => {
        const id = tr.dataset.id; if (!id) return;
        if (treeState[id] === true) {
            expandImmediate(tr);
            const c = tr.querySelector('.tt-caret'); if (c) c.textContent = '−';
        }
    });

    // Wire actions
    table.querySelectorAll('[data-add-log]').forEach(btn => {
        btn.addEventListener('click', () => {
            const bid = parseInt(btn.getAttribute('data-add-log'), 10);
            // read totals from data attributes
            const totals = {
                accepted: parseFloat(btn.getAttribute('data-acc'))||0,
                rejected: parseFloat(btn.getAttribute('data-rej'))||0,
                rework: parseFloat(btn.getAttribute('data-rew'))||0,
                pending: parseFloat(btn.getAttribute('data-pend'))||0,
                started: parseFloat(btn.getAttribute('data-started'))||0,
            };
            const tr = btn.closest('tr');
            const code = tr.querySelector('.tt-toggle')?.textContent?.trim() || ('#' + bid);
            openAddLog(bid, code, totals);
        });
    });
    table.querySelectorAll('[data-edit-log]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = parseInt(btn.getAttribute('data-edit-log'), 10);
            // Extract row data
            const acc = parseFloat(btn.getAttribute('data-acc'))||0;
            const rej = parseFloat(btn.getAttribute('data-rej'))||0;
            const rew = parseFloat(btn.getAttribute('data-rew'))||0;
            const dt = btn.getAttribute('data-dt') || '';
            const notesTxt = btn.getAttribute('data-notes') || '';
            openEditLog(id, { acc, rej, rew, dt, notes: notesTxt });
        });
    });
    table.querySelectorAll('[data-del-log]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = parseInt(btn.getAttribute('data-del-log'), 10);
            deleteLog(id);
        });
    });
    // Delete batch (uses existing production endpoint). Only shown when no logs exist.
    table.querySelectorAll('[data-del-batch]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const bid = parseInt(btn.getAttribute('data-del-batch'), 10);
            if (!confirm('Delete this batch? This is only allowed when no logs exist.')) return;
            let res = { success:false, message:'Not attempted' };
            try {
                const r = await fetch('<?= base_url('production/ajax-delete-batch') ?>', {
                    method: 'POST',
                    headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': getCsrf(), 'Content-Type': 'application/json' },
                    body: JSON.stringify({ batch_id: bid })
                });
                const ct = r.headers.get('content-type')||'';
                res = ct.includes('application/json') ? await r.json() : { success:false, message: await r.text() };
            } catch(e) {
                res = { success:false, message: (e?.message||e) };
            }
            const messages = document.getElementById('messages');
            const msg = document.createElement('div');
            msg.className = 'alert ' + (res.success ? 'alert-success' : 'alert-danger');
            msg.textContent = res.message || (res.success ? 'Deleted' : 'Delete failed');
            messages.appendChild(msg);
            setTimeout(() => msg.remove(), 5000);
            if (res.success) loadTree();
        });
    });
}

// In-page Accordion builder (main view)
async function buildMainAccordion(){
    const mount = document.getElementById('accordionRoot');
    if (!mount) return;
    mount.innerHTML = '<div class="acc-loading">Loading hierarchy…</div>';
    let res;
    try { res = await api('hierarchy'); }
    catch(e){ mount.innerHTML = `<div class="acc-error">Error: ${e?.message||e}</div>`; return; }
    if (!res?.success) { mount.innerHTML = `<div class='acc-error'>Failed to load: ${res?.message||'Unknown error'}</div>`; return; }
    const data = Array.isArray(res.hierarchy) ? res.hierarchy : [];
    if (!data.length) { mount.innerHTML = '<div class="text-muted">No active batches.</div>'; return; }

    const sumBatches = (batches)=>{
        let acc=0, rej=0, rew=0, started=0, pend=0, planned=0;
        (batches||[]).forEach(b=>{
            planned += Number(b.planned_qty||0);
            const t=b.totals||{}; const a=Number(t.accepted||0), r=Number(t.rejected||0), w=Number(t.rework||0);
            const s = Number(t.started ?? (a+r+w));
            const p = Number(t.pending ?? Math.max(0,(b.planned_qty||0)-s));
            acc+=a; rej+=r; rew+=w; started+=s; pend+=p;
        });
        return {acc, rej, rew, started, pend, planned};
    };
    const fmt = (n)=> Number(n||0);
    const fmtDate=(str)=>{ if(!str) return ''; const d=new Date(str); if(isNaN(d)) return ''; const dd=String(d.getDate()).padStart(2,'0'); const mmm=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getMonth()]; const yy=String(d.getFullYear()).slice(-2); const hh=String(d.getHours()).padStart(2,'0'); const mm=String(d.getMinutes()).padStart(2,'0'); return `${dd}-${mmm}-${yy} ${hh}:${mm}`; };
    const badge=(cls,text)=>`<span class='acc-badge ${cls}'>${text}</span>`;
    const tgl=(open)=>`<span class='acc-tgl' data-tgl>${open? '−':'+'}</span>`;
    const statusBadge=(pending)=>{ const p=Number(pending||0); if(p<=0) return badge('green','✓ Completed'); if(p>0) return badge('orange','In Progress'); return badge('slate','—'); };
    const buildLogLines=(b)=>{
        const logs = Array.isArray(b.logs) ? b.logs : [];
        if (!logs.length) return '';
        return `<div class='acc-log-lines'>${logs.map(lg => {
            const rcv = lg.qty_received ?? lg.received_qty ?? 0;
            const acc = lg.qty_completed ?? lg.accepted_qty ?? 0;
            const rej = lg.qty_rejected ?? lg.rejected_qty ?? 0;
            const rew = lg.rework_qty ?? lg.qty_rework ?? lg.sent_for_rework ?? lg.reworked_qty ?? 0;
            const dtRaw = lg.created_at || lg.log_date || '';
            const dtFmt = fmtDate(dtRaw);
            return `<div class='acc-log-line'>
                <span class='log-date'><strong>${dtFmt}</strong></span>
                <span class='metric slate'>Received: ${rcv}</span>
                <span class='metric green'>Accepted: ${acc}</span>
                <span class='metric orange'>Rejected: ${rej}</span>
                <span class='metric'>Rework: ${rew}</span>
                <span class='ms-auto'>
                    <button class="btn btn-outline-secondary btn-sm btn-icon me-1" title="Edit" aria-label="Edit" data-edit-log="${lg.id}" data-acc="${acc}" data-rej="${rej}" data-rew="${rew}" data-dt="${(lg.log_date||'').slice(0,10)}" data-notes="${(lg.notes||'').toString().replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;')}"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-outline-danger btn-sm btn-icon" title="Delete" aria-label="Delete" data-del-log="${lg.id}"><i class="bi bi-trash"></i></button>
                </span>
            </div>`;
        }).join('')}</div>`;
    };
    // Sorting preference (shared with tree)
    const SORT_KEY = 'logs_batch_sort_order';
    const getSortOrder = ()=>{ try { return localStorage.getItem(SORT_KEY) || 'desc'; } catch(_) { return 'desc'; } };
    const sortBatches = (arr)=>{
        const order = getSortOrder(); const dir = order==='asc'?1:-1;
        return (arr||[]).slice().sort((a,b)=>{
            const ad = new Date(a.created_at||a.start_date||0).getTime() || 0;
            const bd = new Date(b.created_at||b.start_date||0).getTime() || 0;
            if (ad === bd) return (Number(a.batch_id||0) - Number(b.batch_id||0)) * dir;
            return (ad - bd) * dir;
        });
    };

    const buildBatch=(b)=>{
    const t=b.totals||{}; const a=fmt(t.accepted); const r=fmt(t.rejected); const w=fmt(t.rework); const s=fmt(t.started != null ? t.started : (b.planned_qty||0)); const p=fmt(t.pending != null ? t.pending : Math.max(0,(b.planned_qty||0)-(a+r+w)));
        const empNames = (b.employees||[]).map(e=>e.name).join(', ');
        const logsCount = Array.isArray(b.logs) ? b.logs.length : 0;
        const logsHtml = buildLogLines(b);
        const delBtn = logsCount ? '' : `<button class=\"btn btn-outline-danger btn-sm btn-icon ms-1\" data-del-batch=\"${b.batch_id}\" title=\"Delete Batch\" aria-label=\"Delete Batch\"><i class=\"bi bi-trash\"></i></button>`;
        return `<details class='acc batch-acc'>
            <summary>
                ${tgl(false)} <span class='code'>${b.batch_code}</span>
                ${badge('slate', 'Logs: '+logsCount)}
                ${statusBadge(p)}
                ${b.vendor_name? badge('purple','Vendor: '+b.vendor_name):''}
                ${empNames? badge('purple','Emp: '+empNames):''}
                ${b.created_at? badge('dark','Start: '+fmtDate(b.created_at)):''}
                <span class='acc-metrics d-flex flex-wrap'>
                    <span>Started: ${s}</span>
                    <span>Accepted: ${a}</span>
                    <span>Rejected: ${r}</span>
                    <span>Rework: ${w}</span>
                    <span>Remaining: ${p}</span>
                </span>
                <span class='ms-auto d-inline-flex align-items-center gap-1'>
                    <button class=\"btn btn-outline-primary btn-sm btn-icon\" data-add-log=\"${b.batch_id}\" data-acc=\"${a}\" data-rej=\"${r}\" data-rew=\"${w}\" data-started=\"${s}\" data-pend=\"${p}\" title=\"Add Log\" aria-label=\"Add Log\"><i class=\"bi bi-plus-square\"></i></button>
                    ${delBtn}
                </span>
            </summary>
            ${logsHtml}
        </details>`;
    };

    const htmlParts = [];
    data.forEach(wo => {
        const woBatches=(wo.products||[]).flatMap(p=> (p.processes||[]).flatMap(pr=> pr.batches||[]));
        const wsum=sumBatches(woBatches);
        const woPlanned = (wo.products||[]).reduce((sum,p)=> sum + Number(p.ordered_qty||0), 0);
        const woRemaining = Math.max(0, woPlanned - (wsum.acc + wsum.rej + wsum.rew));
        htmlParts.push(`<details class='acc' open>
            <summary>${tgl(true)} WO ${wo.wo_number} <span class='acc-badges'>${badge('slate', (wo.products||[]).length+' products')} ${badge('dark', woPlanned+' required')} ${badge('orange','Remaining '+woRemaining)}</span></summary>
            <div class='acc-nested'>`);
        (wo.products||[]).forEach(p => {
            const pBatches=(p.processes||[]).flatMap(pr=> pr.batches||[]); const psum=sumBatches(pBatches);
            const pPlanned = Number(p.ordered_qty||0);
            const pRemaining = Math.max(0, pPlanned - (psum.acc + psum.rej + psum.rew));
            htmlParts.push(`<details class='acc'>
                <summary>${tgl(false)} ${p.product_name} <span class='acc-badges'>${badge('slate',(p.processes||[]).length+' processes')} ${badge('dark', pPlanned+' required')} ${badge('orange','Remaining '+pRemaining)}</span></summary>
                <div class='acc-nested process-level'>`);
            (p.processes||[]).forEach(pr => {
                const rsum=sumBatches(pr.batches||[]);
                const rPlanned = Number(pr.ordered_qty || pPlanned || 0);
                const rRemaining = Math.max(0, rPlanned - (rsum.acc + rsum.rej + rsum.rew));
                htmlParts.push(`<details class='acc'>
                    <summary>${tgl(false)} ${pr.process_name} <span class='acc-badges'>${badge('dark', 'Req: '+rPlanned+' / Acc: '+rsum.acc)} ${badge('orange','Remaining '+rRemaining)}</span></summary>
                    <div class='acc-sublist'>`);
                sortBatches(pr.batches||[]).forEach(b => { htmlParts.push(buildBatch(b)); });
                htmlParts.push(`</div></details>`);
            });
            htmlParts.push(`</div></details>`);
        });
        htmlParts.push(`</div></details><div class='acc-divider'></div>`);
    });

    mount.innerHTML = htmlParts.join('');
    // +/- indicators
    mount.addEventListener('toggle', (e)=>{
        const det = e.target; if (!det || String(det.tagName) !== 'DETAILS') return;
        const tglEl = det.querySelector(':scope > summary .acc-tgl'); if (tglEl) tglEl.textContent = det.open ? '−' : '+';
    }, { passive: true });
    // Fallback: if some browsers don't fire toggle reliably when clicking the symbol, flip after click
    mount.addEventListener('click', (e)=>{
        const sum = e.target.closest('summary');
        if (!sum) return;
        const det = sum.parentElement;
        const tglEl = sum.querySelector('.acc-tgl');
        // allow native toggle to happen first
        setTimeout(()=>{ if (tglEl && det) tglEl.textContent = det.open ? '−' : '+'; }, 0);
    });
}

// Load vendors for selected process (employee-aware for in-house)
    async function loadVendors() {
    els.vendor.innerHTML = '<option value="">Loading…</option>';
    els.vendor.disabled = true;
    els.vendorHint.textContent = '';
    // Reset employees UI before deciding which path we're on
    if (els.employees) {
        const ec = document.getElementById('employeesCol');
        if (ec) ec.style.display = 'none';
        els.employees.innerHTML = '';
        els.employees.disabled = true;
        const eh = document.getElementById('employeesHint');
        if (eh) eh.textContent = '';
    }
    const processId = parseInt(els.process.value || '0', 10);
    if (!(processId > 0)) { els.vendor.innerHTML = '<option value="">-- select process first --</option>'; return; }
    let res = await api('getVendorsForProcess?process_id=' + encodeURIComponent(processId));
    if (!res?.success) {
        // Fallback to absolute URL in case relative base fails under subfolders
        try {
            const abs = '<?= base_url('logs/getVendorsForProcess') ?>' + '?process_id=' + encodeURIComponent(processId);
            const r = await fetch(abs, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const ct = r.headers.get('content-type')||'';
            if (ct.includes('application/json')) { res = await r.json(); }
        } catch(e) { /* ignore */ }
    }
    if (!res?.success) { els.vendor.innerHTML = '<option value="">No vendors</option>'; return; }
    if (!res.is_vendor_process) {
        els.vendor.innerHTML = '<option value="">In-house (no vendor)</option>';
        els.vendor.disabled = true;
        // Trigger employees panel for in-house processes
        loadEmployees(processId);
        return;
    }
    const vs = res.vendors || [];
    if (!vs.length) {
        els.vendor.innerHTML = '<option value="">No vendors configured</option>';
        els.vendor.disabled = true; return;
    }
    els.vendor.innerHTML = '<option value="">-- select --</option>' + vs.map(v => `<option value="${v.id}">${v.name}</option>`).join('');
    els.vendor.disabled = false;
}

// Add Log modal wiring
function openAddLog(batchId, batchCode, totals) {
    const t = totals || { accepted: 0, rejected: 0, rework: 0, pending: 0 };
    const html = `
        <div class="modal" tabindex="-1" style="display:block;background:rgba(0,0,0,.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Add Log for ${batchCode}</h5></div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <span class="badge badge-stat me-1">Started: ${t.started ?? (t.accepted + t.rejected + t.rework) ?? 0}</span>
                            <span class="badge badge-pend">Remaining: ${t.pending ?? 0}</span>
                        </div>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label">Remaining (info)</label><input id="log_pending" type="number" class="form-control" value="${t.pending ?? 0}" disabled></div>
                            <div class="col-6"><label class="form-label">Received (for QC)</label><input id="log_received" type="number" min="0" class="form-control" placeholder="e.g., 100"></div>
                            <div class="col-6"><label class="form-label">Accepted</label><input id="log_accepted" type="number" min="0" class="form-control"></div>
                            <div class="col-6"><label class="form-label">Rejected</label><input id="log_rejected" type="number" min="0" class="form-control"></div>
                            <div class="col-6"><label class="form-label">Rework</label><input id="log_rework" type="number" min="0" class="form-control"></div>
                            <div class="col-4"><label class="form-label">Date</label><input id="log_date" type="date" class="form-control" value="${new Date().toISOString().slice(0,10)}"></div>
                            <div class="col-4"><label class="form-label">Time</label><input id="log_time" type="time" class="form-control" value="${(() => { const d=new Date(); return String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0'); })()}"></div>
                            <div class="col-4"><label class="form-label">&nbsp;</label><div class="form-text">Leave time as-is to use current time.</div></div>
                        </div>
                        <div class="mt-2 small-muted">Totals — Started: <strong>${t.started ?? (t.accepted + t.rejected + t.rework) ?? 0}</strong> | Accepted: <strong>${t.accepted ?? 0}</strong> | Rejected: <strong>${t.rejected ?? 0}</strong> | Rework: <strong>${t.rework ?? 0}</strong> | Remaining: <strong>${t.pending ?? 0}</strong></div>
                        <div class="mt-2"><label class="form-label">Notes</label><textarea id="log_notes" class="form-control" rows="3"></textarea></div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.querySelector('.modal').remove()">Cancel</button>
                        <button class="btn btn-primary" onclick="submitAddLog(${batchId})">Save</button>
                    </div>
                </div>
            </div>
        </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
}

async function submitAddLog(batchId) {
    const received = parseFloat(document.getElementById('log_received').value || 0);
    const accepted = parseFloat(document.getElementById('log_accepted').value || 0);
    const rejected = parseFloat(document.getElementById('log_rejected').value || 0);
    const rework = parseFloat(document.getElementById('log_rework').value || 0);
    const log_date = document.getElementById('log_date').value || undefined;
    const log_time = document.getElementById('log_time')?.value || undefined;
    const notes = document.getElementById('log_notes').value || '';
    // simple validation to keep data quality
    const sum = accepted + rejected + rework;
    if (received > 0 && sum !== received) {
        const warn = document.createElement('div');
        warn.className = 'alert alert-warning';
        warn.textContent = `Sum mismatch: Accepted(${accepted}) + Rejected(${rejected}) + Rework(${rework}) = ${sum}, but Received is ${received}. Align values or clear Received.`;
        document.querySelector('.modal .modal-body').prepend(warn);
        setTimeout(() => warn.remove(), 5000);
        return;
    }
    const payload = { batch_id: batchId, qty_completed: accepted, qty_rejected: rejected, rework_qty: rework, log_date, notes };
    if (log_time) payload.log_time = log_time;
    if (received > 0) payload.qty_received = received;
    const res = await api('addLog', 'POST', payload);
    const messages = document.getElementById('messages');
    const msg = document.createElement('div');
    msg.className = 'alert ' + (res.success ? 'alert-success' : 'alert-danger');
    msg.textContent = res.message || (res.success ? 'Saved' : 'Failed');
    messages.appendChild(msg);
    setTimeout(() => msg.remove(), 5000);
    document.querySelector('.modal')?.remove();
document.addEventListener('DOMContentLoaded', () => {
    applyViewMode(true);
    // Switch view buttons
    document.getElementById('viewTree')?.addEventListener('click', (e)=>{ e.preventDefault(); setViewMode('tree'); applyViewMode(); });
    document.getElementById('viewAccordion')?.addEventListener('click', (e)=>{ e.preventDefault(); setViewMode('accordion'); applyViewMode(); });
    // Action delegation for in-page accordion
    const accRoot = document.getElementById('accordionRoot');
    if (accRoot && !accRoot._wired) {
        accRoot._wired = true;
        accRoot.addEventListener('click', async (e)=>{
            const btn = e.target.closest('[data-add-log],[data-edit-log],[data-del-log],[data-del-batch]');
            if (!btn) return;
            e.preventDefault(); e.stopPropagation();
            if (btn.hasAttribute('data-add-log')) {
                const bid = parseInt(btn.getAttribute('data-add-log'), 10);
                const totals = {
                    accepted: parseFloat(btn.getAttribute('data-acc'))||0,
                    rejected: parseFloat(btn.getAttribute('data-rej'))||0,
                    rework: parseFloat(btn.getAttribute('data-rew'))||0,
                    started: parseFloat(btn.getAttribute('data-started'))||0,
                    pending: parseFloat(btn.getAttribute('data-pend'))||0,
                };
                const code = btn.closest('summary')?.querySelector('.code')?.textContent?.trim() || ('#'+bid);
                openAddLog(bid, code, totals);
                return;
            }
            if (btn.hasAttribute('data-edit-log')) {
                const id = parseInt(btn.getAttribute('data-edit-log'), 10);
                const acc = parseFloat(btn.getAttribute('data-acc'))||0;
                const rej = parseFloat(btn.getAttribute('data-rej'))||0;
                const rew = parseFloat(btn.getAttribute('data-rew'))||0;
                const dt = btn.getAttribute('data-dt') || '';
                const notes = btn.getAttribute('data-notes') || '';
                openEditLog(id, { acc, rej, rew, dt, notes });
                return;
            }
            if (btn.hasAttribute('data-del-log')) {
                const id = parseInt(btn.getAttribute('data-del-log'), 10);
                await deleteLog(id);
                return;
            }
            if (btn.hasAttribute('data-del-batch')) {
                const bid = parseInt(btn.getAttribute('data-del-batch'), 10);
                if (!confirm('Delete this batch? This is only allowed when no logs exist.')) return;
                let res = { success:false, message:'Not attempted' };
                try {
                    const r = await fetch('<?= base_url('production/ajax-delete-batch') ?>', {
                        method: 'POST',
                        headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': getCsrf(), 'Content-Type': 'application/json' },
                        body: JSON.stringify({ batch_id: bid })
                    });
                    const ct = r.headers.get('content-type')||'';
                    res = ct.includes('application/json') ? await r.json() : { success:false, message: await r.text() };
                } catch(e) { res = { success:false, message: (e?.message||e) }; }
                const messages = document.getElementById('messages');
                const msg = document.createElement('div');
                msg.className = 'alert ' + (res.success ? 'alert-success' : 'alert-danger');
                msg.textContent = res.message || (res.success ? 'Deleted' : 'Delete failed');
                messages.appendChild(msg);
                setTimeout(() => msg.remove(), 5000);
                if (res.success) refreshActiveView();
                return;
            }
        });
    }
});
}

// initial tree load
document.addEventListener('DOMContentLoaded', () => {
    loadTree();
});

// Edit & Delete handlers
function openEditLog(id, data) {
    const html = `
        <div class="modal" tabindex="-1" style="display:block;background:rgba(0,0,0,.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Edit Log</h5></div>
                    <div class="modal-body">
                        <div class="row g-2">
                            <div class="col-4"><label class="form-label">Accepted</label><input id="e_accepted" type="number" min="0" class="form-control" value="${data.acc}"></div>
                            <div class="col-4"><label class="form-label">Rejected</label><input id="e_rejected" type="number" min="0" class="form-control" value="${data.rej}"></div>
                            <div class="col-4"><label class="form-label">Rework</label><input id="e_rework" type="number" min="0" class="form-control" value="${data.rew}"></div>
                            <div class="col-6"><label class="form-label">Date</label><input id="e_date" type="date" class="form-control" value="${data.dt}"></div>
                            <div class="col-12"><label class="form-label">Notes</label><textarea id="e_notes" class="form-control" rows="3">${data.notes || ''}</textarea></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.querySelector('.modal').remove()">Cancel</button>
                        <button class="btn btn-primary" onclick="submitEditLog(${id})">Save</button>
                    </div>
                </div>
            </div>
        </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
}

async function submitEditLog(id) {
    const payload = {
        qty_completed: parseFloat(document.getElementById('e_accepted').value || 0),
        qty_rejected: parseFloat(document.getElementById('e_rejected').value || 0),
        rework_qty: parseFloat(document.getElementById('e_rework').value || 0),
        log_date: document.getElementById('e_date').value || undefined,
        notes: document.getElementById('e_notes').value || ''
    };
    const res = await api('updateLog', 'POST', Object.assign({ id }, payload));
    const messages = document.getElementById('messages');
    const msg = document.createElement('div');
    msg.className = 'alert ' + (res.success ? 'alert-success' : 'alert-danger');
    msg.textContent = res.message || (res.success ? 'Saved' : 'Failed');
    messages.appendChild(msg);
    setTimeout(() => msg.remove(), 5000);
    document.querySelector('.modal')?.remove();
    document.querySelector('.modal')?.remove();
    refreshActiveView();
}

async function deleteLog(id) {
    if (!confirm('Delete this log entry?')) return;
    const res = await api('deleteLog', 'POST', { id });
    const messages = document.getElementById('messages');
    const msg = document.createElement('div');
    msg.className = 'alert ' + (res.success ? 'alert-success' : 'alert-danger');
    msg.textContent = res.message || (res.success ? 'Deleted' : 'Failed');
    messages.appendChild(msg);
    setTimeout(() => msg.remove(), 5000);
    document.querySelector('.modal')?.remove();
    refreshActiveView();
}

// --- Card UI Demo Modal ---
// Fetch template content at open-time so it's available even if template appears later in DOM
function openCardDemoModal() {
    const tpl = document.getElementById('cardUiDemoTemplate');
    const cardDemoTemplateHtml = tpl ? (tpl.innerHTML || '').trim() : '';
    const html = `
        <div class="modal" tabindex="-1" style="display:block;background:rgba(0,0,0,.5);">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Card-based UI Demo</h5>
                        <button type="button" class="btn-close" aria-label="Close" onclick="document.querySelector('.modal')?.remove()"></button>
                    </div>
                    <div class="modal-body">
                        ${cardDemoTemplateHtml || '<div class="text-muted">Demo content not found.</div>'}
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.querySelector('.modal')?.remove()">Close</button>
                    </div>
                </div>
            </div>
        </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    refreshActiveView();
}

document.getElementById('openCardDemo')?.addEventListener('click', (e)=>{
    e.preventDefault();
    openCardDemoModal();
});

// --- Alt UI Demo Modal ---
function openAltDemoModal() {
    const tpl = document.getElementById('altUiDemoTemplate');
    const tplHtml = tpl ? (tpl.innerHTML || '').trim() : '';
    const html = `
        <div class="modal" tabindex="-1" style="display:block;background:rgba(0,0,0,.5);">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Accordion Hierarchy (Live Data)</h5>
                        <button type="button" class="btn-close" aria-label="Close" onclick="document.querySelector('.modal')?.remove()"></button>
                    </div>
                    <div class="modal-body">
                        ${tplHtml || '<div class="text-muted">Demo content not found.</div>'}
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.querySelector('.modal')?.remove()">Close</button>
                    </div>
                </div>
            </div>
        </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    // After insertion, build live accordion in the modal mount
    buildAccordionInto(document.getElementById('accordionDemoMount'));
}

// Generic builder that can render accordion into any mount element
function buildAccordionInto(mount){
    if (!mount){ return; }
    mount.innerHTML = '<div class="acc-loading">Loading hierarchy…</div>';
    api('hierarchy').then(res => {
        if(!res?.success){ mount.innerHTML = `<div class='acc-error'>Failed to load: ${res?.message||'Unknown error'}</div>`; return; }
    const data = Array.isArray(res.hierarchy)? res.hierarchy: [];
        if(!data.length){ mount.innerHTML = '<div class="text-muted">No active batches.</div>'; return; }
        // helpers
        const sumBatches = (batches)=>{
            // Aggregates actuals from logs; planned/started are not summed here (they come from ordered_qty per group)
            let acc=0, rej=0, rew=0;
            (batches||[]).forEach(b=>{
                const t=b.totals||{}; const a=Number(t.accepted||0), r=Number(t.rejected||0), w=Number(t.rework||0);
                acc+=a; rej+=r; rew+=w;
            });
            return {acc, rej, rew};
        };
        const fmt = n => (Number(n||0));
        const fmtDate=(str)=>{ if(!str) return ''; const d=new Date(str); if(isNaN(d)) return ''; const dd=String(d.getDate()).padStart(2,'0'); const mmm=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getMonth()]; const yy=String(d.getFullYear()).slice(-2); const hh=String(d.getHours()).padStart(2,'0'); const mm=String(d.getMinutes()).padStart(2,'0'); return `${dd}-${mmm}-${yy} ${hh}:${mm}`; };
    const badge=(cls,text)=>`<span class='acc-badge ${cls}'>${text}</span>`;
    const tgl=(open)=>`<span class='acc-tgl' data-tgl>${open? '−':'+'}</span>`; // kept but inline style now
        const statusBadge=(pending)=>{ const p=Number(pending||0); if(p<=0) return badge('green','✓ Completed'); if(p>0) return badge('orange','In Progress'); return badge('slate','—'); };
        const buildLogLines=(logs)=>{
            const arr = Array.isArray(logs) ? logs : [];
            if (!arr.length) return '';
            return `<div class='acc-log-lines'>${arr.map(lg => {
                const rcv = lg.qty_received ?? lg.received_qty ?? 0;
                const acc = lg.qty_completed ?? lg.accepted_qty ?? 0;
                const rej = lg.qty_rejected ?? lg.rejected_qty ?? 0;
                const rew = lg.rework_qty ?? lg.qty_rework ?? lg.sent_for_rework ?? lg.reworked_qty ?? 0;
                const dtFmt = fmtDate(lg.created_at || lg.log_date || '');
                return `<div class='acc-log-line'>
                    <span class='log-date'>${dtFmt}</span>
                    <span class='metric slate'>Received: ${rcv}</span>
                    <span class='metric green'>Accepted: ${acc}</span>
                    <span class='metric orange'>Rejected: ${rej}</span>
                    <span class='metric'>Rework: ${rew}</span>
                    <span class='ms-auto'></span>
                    <button class="btn btn-outline-secondary btn-sm btn-icon me-1" title="Edit" aria-label="Edit" data-edit-log="${lg.id}" data-rcv="${rcv}" data-acc="${acc}" data-rej="${rej}" data-rew="${rew}" data-dt="${lg.log_date || ''}" data-notes=""><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-outline-danger btn-sm btn-icon" title="Delete" aria-label="Delete" data-del-log="${lg.id}"><i class="bi bi-trash"></i></button>
                </div>`;
            }).join('')}</div>`;
        };

    // Sorting preference (shared)
    const SORT_KEY = 'logs_batch_sort_order';
    const getSortOrder = ()=>{ try { return localStorage.getItem(SORT_KEY) || 'desc'; } catch(_) { return 'desc'; } };
    const sortBatches = (arr)=>{ const order=getSortOrder(); const dir=order==='asc'?1:-1; return (arr||[]).slice().sort((a,b)=>{ const ad=new Date(a.created_at||a.start_date||0).getTime()||0; const bd=new Date(b.created_at||b.start_date||0).getTime()||0; if(ad===bd) return (Number(a.batch_id||0)-Number(b.batch_id||0))*dir; return (ad-bd)*dir; }); };

    const buildBatchLine=(b)=>{
        const t=b.totals||{}; const a=fmt(t.accepted); const r=fmt(t.rejected); const w=fmt(t.rework);
        // Started must remain the planned quantity for the batch
        const s=fmt(b.planned_qty);
        // Pending is planned minus processed (accepted+rejected+rework)
        const p=fmt(Math.max(0,(Number(b.planned_qty||0) - (a+r+w))));
        const empNames = (b.employees||[]).map(e=>e.name).join(', ');
            const logsHtml = buildLogLines(b.logs);
            const logCount = Array.isArray(b.logs) ? b.logs.length : 0;
            return `<details class='acc batch-acc'>
                <summary>${tgl(false)} <span class='code'>${b.batch_code}</span>
                    ${statusBadge(p)}
                    ${b.vendor_name? badge('purple','Vendor: '+b.vendor_name):''}
                    ${empNames? badge('purple','Emp: '+empNames):''}
                    ${b.created_at? badge('dark','Start: '+fmtDate(b.created_at)):''}
                    <span class='acc-metrics d-flex flex-wrap'>
                        <span>Started: ${s}</span>
                        <span>Accepted: ${a}</span>
                        <span>Rejected: ${r}</span>
                        <span>Rework: ${w}</span>
                        <span>Remaining: ${p}</span>
                    </span>
                    <span class='ms-auto d-inline-flex align-items-center gap-1'>
                        <span class='acc-badge slate'>${logCount} logs</span>
                        <button class="btn btn-outline-primary btn-sm btn-icon" title="Add Log" aria-label="Add Log" data-add-log="${b.batch_id}" data-acc="${t.accepted||0}" data-rej="${t.rejected||0}" data-rew="${t.rework||0}" data-pend="${p}" data-started="${s}"><i class="bi bi-plus-square"></i></button>
                        ${logCount===0 ? `<button class='btn btn-outline-danger btn-sm btn-icon' title='Delete Batch' aria-label='Delete Batch' data-del-batch='${b.batch_id}'><i class='bi bi-trash'></i></button>` : ''}
                    </span>
                </summary>
                ${logsHtml}
            </details>`;
        };
        const htmlParts=[];
        data.forEach(wo=>{
            const woBatches=(wo.products||[]).flatMap(p=> (p.processes||[]).flatMap(pr=> pr.batches||[]));
            const wsum=sumBatches(woBatches);
            const woPlanned = (wo.products||[]).reduce((sum,p)=> sum + Number(p.ordered_qty||0), 0);
            const woPending = Math.max(0, woPlanned - (wsum.acc + wsum.rej + wsum.rew));
            htmlParts.push(`<details class='acc' open>
                <summary>${tgl(true)} WO ${wo.wo_number} <span class='acc-badges'>${badge('slate', (wo.products||[]).length+' products')} ${badge('dark', woPlanned+' required')} ${badge('orange','Remaining '+woPending)}</span></summary>
                <div class='acc-nested'>`);
            (wo.products||[]).forEach(p=>{
                const pBatches=(p.processes||[]).flatMap(pr=> pr.batches||[]); const psum=sumBatches(pBatches);
                const pPlanned = Number(p.ordered_qty||0);
                const pPending = Math.max(0, pPlanned - (psum.acc + psum.rej + psum.rew));
                htmlParts.push(`<details class='acc'>
                    <summary>${tgl(false)} ${p.product_name} <span class='acc-badges'>${badge('slate',(p.processes||[]).length+' processes')} ${badge('dark', pPlanned+' required')} ${badge('orange','Remaining '+pPending)}</span></summary>
                    <div class='acc-nested process-level'>`);
                (p.processes||[]).forEach(pr=>{
                    const rsum=sumBatches(pr.batches||[]);
                    const rPlanned = Number(pr.ordered_qty||pPlanned||0);
                    const rPending = Math.max(0, rPlanned - (rsum.acc + rsum.rej + rsum.rew));
                    htmlParts.push(`<details class='acc'>
                        <summary>${tgl(false)} ${pr.process_name} <span class='acc-badges'>${badge('dark', 'Req: '+rPlanned+' / Acc: '+rsum.acc)} ${badge('orange','Remaining '+rPending)}</span></summary>
                        <div class='acc-sublist'>`);
                    sortBatches(pr.batches||[]).forEach(b=>{ htmlParts.push(buildBatchLine(b)); });
                    htmlParts.push(`</div></details>`);
                });
                htmlParts.push(`</div></details>`);
            });
            htmlParts.push(`</div></details><div class='acc-divider'></div>`);
        });
        mount.innerHTML = htmlParts.join('');
        // Wire toggle symbols for this mount and delegate actions
        mount.addEventListener('toggle', (e)=>{
            const det = e.target;
            if (!det || String(det.tagName) !== 'DETAILS') return;
            const tglEl = det.querySelector(':scope > summary .acc-tgl');
            if (tglEl) tglEl.textContent = det.open ? '−' : '+';
        });
        // Fallback click handler to keep symbol in sync immediately
        mount.addEventListener('click', (e)=>{
            const sum = e.target.closest('summary');
            if (!sum) return;
            const det = sum.parentElement; const tglEl = sum.querySelector('.acc-tgl');
            setTimeout(()=>{ if (tglEl && det) tglEl.textContent = det.open ? '−' : '+'; }, 0);
        });
        mount.addEventListener('click', async (e)=>{
            const addBtn = e.target.closest('[data-add-log]');
            const delBatchBtn = e.target.closest('[data-del-batch]');
            const editBtn = e.target.closest('[data-edit-log]');
            const delLogBtn = e.target.closest('[data-del-log]');
            if (addBtn){
                e.preventDefault();
                const bid = parseInt(addBtn.getAttribute('data-add-log'),10);
                const totals = {
                    accepted: parseFloat(addBtn.getAttribute('data-acc'))||0,
                    rejected: parseFloat(addBtn.getAttribute('data-rej'))||0,
                    rework: parseFloat(addBtn.getAttribute('data-rew'))||0,
                    pending: parseFloat(addBtn.getAttribute('data-pend'))||0,
                    started: parseFloat(addBtn.getAttribute('data-started'))||0,
                };
                const summary = addBtn.closest('summary');
                const code = summary?.querySelector('.code')?.textContent?.trim() || ('#'+bid);
                openAddLog(bid, code, totals);
                return;
            }
            if (delBatchBtn){
                e.preventDefault();
                const bid = parseInt(delBatchBtn.getAttribute('data-del-batch'),10);
                if (!confirm('Delete this batch? This is only allowed when no logs exist.')) return;
                let resp = { success:false, message:'Not attempted' };
                try {
                    const r = await fetch('<?= base_url('production/ajax-delete-batch') ?>', {
                        method: 'POST', headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': getCsrf(), 'Content-Type':'application/json' }, body: JSON.stringify({ batch_id: bid })
                    });
                    const ct = r.headers.get('content-type')||''; resp = ct.includes('application/json') ? await r.json() : { success:false, message: await r.text() };
                } catch(e){ resp = { success:false, message: (e?.message||e) }; }
                const messages = document.getElementById('messages');
                const msg = document.createElement('div'); msg.className='alert '+(resp.success?'alert-success':'alert-danger'); msg.textContent = resp.message || (resp.success?'Deleted':'Delete failed'); messages.appendChild(msg); setTimeout(()=>msg.remove(),5000);
                if (resp.success){ buildAccordionInto(mount); }
                return;
            }
            if (editBtn){ e.preventDefault(); const id=parseInt(editBtn.getAttribute('data-edit-log'),10); const acc=parseFloat(editBtn.getAttribute('data-acc'))||0; const rej=parseFloat(editBtn.getAttribute('data-rej'))||0; const rew=parseFloat(editBtn.getAttribute('data-rew'))||0; const dt=editBtn.getAttribute('data-dt')||''; openEditLog(id, { acc, rej, rew, dt, notes:'' }); return; }
            if (delLogBtn){ e.preventDefault(); const id=parseInt(delLogBtn.getAttribute('data-del-log'),10); deleteLog(id); return; }
        });
    }).catch(err=>{
        mount.innerHTML = `<div class='acc-error'>Exception: ${err?.message||err}</div>`;
    });
}

document.getElementById('openAltDemo')?.addEventListener('click', (e)=>{
    e.preventDefault();
    openAltDemoModal();
});

// View switcher: Tree vs Accordion (main page)
document.getElementById('viewAccordion')?.addEventListener('click', (e)=>{
    e.preventDefault();
    document.getElementById('tree').style.display='none';
    const acc = document.getElementById('accordionRoot');
    acc.style.display='block';
    // Build or refresh accordion
    buildAccordionInto(acc);
});
document.getElementById('viewTree')?.addEventListener('click', (e)=>{
    e.preventDefault();
    document.getElementById('accordionRoot').style.display='none';
    const tree = document.getElementById('tree');
    tree.style.display='block';
    loadTree();
});
</script>

<!-- Hidden template containing the new card-based demo UI -->
<template id="cardUiDemoTemplate">
    <?= $this->include('logs/card_demo') ?>
    <div class="small text-muted ms-3 mt-2">Demo only — no backend calls; styles and structure are Bootstrap 5.</div>
</template>

<!-- Hidden template containing alternative paradigms demo -->
<template id="altUiDemoTemplate">
    <?= $this->include('logs/alt_demo') ?>
    <div class="small text-muted ms-3 mt-2">Accordion-only preview with dotted connectors — live data, no backend changes.</div>
    
</template>

<?= $this->endSection() ?>
