<?php /* Dynamic Accordion Demo (only) - styles + empty mount; JS builds content after modal opens */ ?>
<style>
  .acc-root{ font-size:0.85rem; }
  /* Remove rectangular boxes and use dotted tree connectors */
  details.acc{ padding:.15rem .2rem .2rem .2rem; margin:.25rem 0; position:relative; }
  details.acc > summary{ cursor:pointer; font-weight:600; display:flex; align-items:center; gap:.5rem; }
  /* Hide native disclosure marker */
  details.acc > summary::-webkit-details-marker{ display:none; }
  details.acc > summary::marker { content:""; }
  .acc-tgl, .acc-log-tgl{ width:1rem; height:1rem; display:inline-flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:700; line-height:1; border:1px solid rgba(255,255,255,.35); border-radius:.25rem; background:rgba(255,255,255,.05); cursor:pointer; user-select:none; }
  /* Inline toggle buttons (no absolute centering) */
  .acc-tgl:hover, .acc-log-tgl:hover{ background:rgba(255,255,255,.12); }
  .acc-tgl:focus-visible, .acc-log-tgl:focus-visible{ outline:2px solid #0d6efd; }
  .acc-badges{ display:flex; flex-wrap:wrap; gap:.35rem; font-weight:500; }
  .acc-badge{ background:#0d6efd1a; color:#69a6ff; padding:.12rem .45rem; border-radius:999px; font-size:.65rem; letter-spacing:.3px; }
  .acc-badge.green{ background:#1987541a; color:#67c18c; }
  .acc-badge.orange{ background:#fd7e141a; color:#ffb47a; }
  .acc-badge.slate{ background:#6c757d26; color:#adb5bd; }
  .acc-badge.purple{ background:#6f42c11a; color:#b998ff; }
  .acc-badge.dark{ background:#00000033; color:#bbb; }
  .acc-metrics{ display:flex; flex-wrap:wrap; gap:.25rem .5rem; margin-top:.35rem; font-size:.65rem; }
  .acc-line{ display:flex; align-items:center; gap:.35rem; justify-content:space-between; padding:.25rem .4rem; border:1px solid rgba(255,255,255,.07); border-radius:.4rem; background:rgba(255,255,255,.025); position:relative; margin:.25rem 0 .25rem 1.2rem; }
  /* Batch connector branches */
  .acc-line::before{ content:""; position:absolute; left:-1.05rem; top:50%; width:1rem; border-top:2px dotted rgba(255,255,255,.25); }
  .acc-line::after{ content:""; position:absolute; left:-1.1rem; top:calc(50% - .3rem); width:.55rem; height:.55rem; border-radius:50%; background:rgba(255,255,255,.55); }
  .acc-line:hover{ background:rgba(255,255,255,.07); }
  .acc-line .code{ font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; font-size:.7rem; font-weight:600; }
  .acc-line .status{ font-size:.6rem; padding:.12rem .4rem; border-radius:999px; font-weight:600; letter-spacing:.3px; }
  .status.complete{ background:#1987541a; color:#20c997; }
  .status.progress{ background:#0d6efd1a; color:#4da3ff; }
  .status.pending{ background:#fd7e141a; color:#ffa45a; }
  .status.empty{ background:#6c757d33; color:#adb5bd; }
   /* Batch details summary behaves like other hierarchy items */
   details.batch-acc > summary{ display:flex; flex-wrap:wrap; align-items:center; gap:.4rem; padding:.25rem .45rem .3rem .45rem; border:1px solid rgba(255,255,255,.07); border-radius:.45rem; background:rgba(255,255,255,.025); font-size:.7rem; }
   details.batch-acc[open] > summary{ background:rgba(255,255,255,.06); }
   details.batch-acc > summary .code{ font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace; font-weight:600; }
   details.batch-acc > summary .status{ font-size:.6rem; padding:.12rem .4rem; border-radius:999px; font-weight:600; letter-spacing:.3px; }
  /* Dotted connectors */
  .acc-nested{ margin:.25rem 0 .2rem 1.4rem; position:relative; }
  .acc-nested::before{ content:""; position:absolute; left:.4rem; top:.15rem; bottom:.3rem; border-left:2px dotted rgba(255,255,255,.25); }
  .acc-nested > details.acc{ position:relative; padding-left:1rem; }
  .acc-nested > details.acc::before{ content:""; position:absolute; left:-.6rem; top: 1rem; width:.9rem; border-top:2px dotted rgba(255,255,255,.25); }
  /* Node circle at junctions */
  .acc-nested > details.acc::after{ content:""; position:absolute; left:.35rem; top:.84rem; width:.35rem; height:.35rem; border-radius:50%; background:rgba(255,255,255,.5); box-shadow:0 0 0 2px rgba(0,0,0,.2) inset; }
  /* Product -> process connector extension */
  .acc-nested.process-level::before{ left:.25rem; }
  .acc-nested.process-level > details.acc::before{ left:-.75rem; width:1.1rem; }
  .acc-nested.process-level > details.acc::after{ left:.2rem; }
  .acc-sublist{ margin:.25rem 0 .4rem .9rem; position:relative; }
  .acc-sublist::before{ content:""; position:absolute; left:.25rem; top:.05rem; bottom:.4rem; border-left:2px dotted rgba(255,255,255,.25); }
   /* Logs list under batch (only final level has dot connector) */
   .acc-log-lines{ position:relative; margin:.25rem 0 .3rem 1.6rem; }
   .acc-log-lines::before{ content:""; position:absolute; left:-1.1rem; top:.2rem; bottom:.3rem; border-left:2px dotted rgba(255,255,255,.28); }
  /* Log group connectors */
  .acc-log-group{ position:relative; margin:.15rem 0 .25rem 2.4rem; }
  .acc-log-group::before{ content:""; position:absolute; left:-1.25rem; top:.2rem; bottom:.4rem; border-left:2px dotted rgba(255,255,255,.25); }
  /* Logs toggle remains inline */
  .acc-log-line{ font-size:.6rem; display:flex; flex-wrap:wrap; gap:.4rem; background:rgba(255,255,255,.02); border:1px solid rgba(255,255,255,.07); border-radius:.35rem; padding:.18rem .4rem .22rem; position:relative; margin:.2rem 0 .2rem .6rem; }
  .acc-log-line::before{ content:""; position:absolute; left:-.65rem; top:50%; width:.6rem; border-top:2px dotted rgba(255,255,255,.25); }
  .acc-log-line::after{ content:""; position:absolute; left:-.7rem; top:calc(50% - .25rem); width:.45rem; height:.45rem; border-radius:50%; background:rgba(255,255,255,.45); }
    .acc-log-line{ font-size:.6rem; display:flex; flex-wrap:wrap; gap:.4rem; background:rgba(255,255,255,.02); border:1px solid rgba(255,255,255,.07); border-radius:.35rem; padding:.18rem .4rem .22rem; position:relative; margin:.2rem 0 .2rem .8rem; }
    .acc-log-line::before{ content:""; position:absolute; left:-.75rem; top:50%; width:.7rem; border-top:2px dotted rgba(255,255,255,.25); }
    .acc-log-line::after{ content:""; position:absolute; left:-.82rem; top:calc(50% - .25rem); width:.45rem; height:.45rem; border-radius:50%; background:rgba(255,255,255,.45); }
  .acc-log-line .log-date{ font-weight:600; letter-spacing:.4px; }
  .acc-log-line .metric{ font-size:.55rem; padding:.1rem .35rem; border-radius:.4rem; background:#0d6efd1a; color:#69a6ff; font-weight:500; }
  .acc-log-line .metric.green{ background:#1987541a; color:#67c18c; }
  .acc-log-line .metric.orange{ background:#fd7e141a; color:#ffb47a; }
  .acc-log-line .metric.slate{ background:#6c757d33; color:#adb5bd; }
  .acc-subheading{ font-size:.6rem; font-weight:600; opacity:.7; text-transform:uppercase; letter-spacing:.5px; margin-top:.25rem; }
  .acc-loading{ font-size:.75rem; opacity:.7; }
  .acc-error{ font-size:.75rem; color:#dc3545; }
  .acc-divider{ height:6px; }
  @media (max-width: 680px){ .acc-line{ flex-direction:column; align-items:flex-start; gap:.2rem; } }
</style>
<div class="acc-root" id="accordionDemoMount">
  <div class="acc-loading">Loading hierarchy…</div>
</div>
