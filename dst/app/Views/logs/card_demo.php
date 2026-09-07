<!--
Work Order → Processes → Batches demo layout
- Bootstrap 5 classes
- Hardcoded values based on your example
- No backend logic; purely presentational
- You can paste this block into your view file where the old UI was rendered
-->

<style>
  /* Subtle hierarchy helpers */
  .wo-card .progress { height: 12px; }
  .proc-card { border-left-width: .4rem !important; }
  .proc-card + .proc-card { margin-top: 1rem; }

  /* Batches list: subtle connector line and indent */
  .batch-list { position: relative; }
  .batch-list::before {
    /* connector from process header down through its batches */
    content: "";
    position: absolute;
    left: 12px;
    top: .5rem;
    bottom: .5rem;
    border-left: 2px dotted rgba(0,0,0,.1);
  }
  .batch-item {
    position: relative;
    margin-left: 24px; /* creates visual link to process */
    border-radius: .5rem;
  }
  .batch-item::before {
    content: "";
    position: absolute;
    left: -12px;
    top: 1.15rem;
    width: 12px;
    border-top: 2px dotted rgba(0,0,0,.1);
  }
  .batch-item .badge { font-weight: 500; }

  /* Smaller detail labels */
  .kv dt { color: #6c757d; font-size: .875rem; }
  .kv dd { margin-bottom: 0; }

  /* Light theme fine-tuning */
  .card-subtle { background: #fcfdff; }

  /* Dark mode tuning (works with body.theme-dark if you use it) */
  body.theme-dark .card,
  body.theme-dark .card-subtle { background: #1b2433; color: #e2e8f0; }
  body.theme-dark .list-group-item { background: #162033; color: #e2e8f0; }
  body.theme-dark .text-muted { color: #9fb0c5 !important; }
  body.theme-dark .batch-list::before,
  body.theme-dark .batch-item::before { border-color: #334155; }
  body.theme-dark .alert-light { background: #0f172a; color: #e2e8f0; border-color: #334155; }
</style>

<div class="container my-4">

  <!-- Work Order Header -->
  <div class="card shadow-sm wo-card mb-4">
    <div class="card-body">
      <!-- Primary heading: Work Order number and batches count -->
      <div class="d-flex align-items-baseline flex-wrap gap-2">
        <h1 class="h2 mb-1 me-2">WO S00077</h1>
        <span class="text-muted">11 batches</span>
      </div>

      <!-- Product subheading with target/completion -->
      <div class="mt-2">
        <div class="d-flex align-items-center flex-wrap gap-2">
          <h2 class="h5 mb-0 text-secondary">13&quot; Tactical Knife</h2>
          <span class="badge bg-secondary-subtle text-secondary border">Target: 2008</span>
          <span class="badge bg-success-subtle text-success border">Completed: 2367</span>
          <span class="badge bg-warning-subtle text-warning border">118% of target</span>
        </div>
        <!-- Product-level progress (capped at 100%) -->
        <div class="progress mt-2" role="progressbar" aria-label="Product progress" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-bar bg-success" style="width: 100%"></div>
        </div>
        <div class="small text-muted mt-1">Completed 2367 of 2008 (capped at 100% in bar; badge shows over‑target)</div>
      </div>
    </div>
  </div>

  <!-- Process: Temper (HRC) -->
  <div class="card proc-card border-start border-3 border-primary-subtle shadow-sm card-subtle">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h3 class="h5 mb-0">Temper (HRC)</h3>
        <span class="text-muted small">Process ID: T-HRC</span>
      </div>
      <!-- Process progress -->
      <div class="mt-2">
        <div class="d-flex justify-content-between">
          <span class="small text-muted">Progress</span>
          <span class="small">185 of 2000</span>
        </div>
        <div class="progress" style="height: 10px;" aria-label="Temper progress" aria-valuenow="9" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-bar bg-primary" style="width: 9%"></div>
        </div>
      </div>

      <!-- Batches -->
      <div class="mt-3 batch-list">
        <!-- Example batch item -->
        <div class="list-group">
          <div class="list-group-item batch-item">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <strong>TH-1101</strong>
                <span class="badge bg-primary ms-2">In progress</span>
              </div>
              <span class="text-muted small">Vendor: In‑house</span>
            </div>

            <!-- Details grid -->
            <dl class="row kv mt-2">
              <dt class="col-6 col-md-3">Start</dt>
              <dd class="col-6 col-md-3">Oct 31, 2025, 10:30 AM</dd>

              <dt class="col-6 col-md-3">Initial Qty</dt>
              <dd class="col-6 col-md-3">20</dd>

              <dt class="col-6 col-md-3">Selected</dt>
              <dd class="col-6 col-md-3">0</dd>

              <dt class="col-6 col-md-3">In Progress</dt>
              <dd class="col-6 col-md-3">0</dd>

              <dt class="col-6 col-md-3">Output</dt>
              <dd class="col-6 col-md-3">0</dd>
            </dl>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Process: Laser Cutting -->
  <div class="card proc-card border-start border-3 border-primary-subtle shadow-sm card-subtle mt-3">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h3 class="h5 mb-0">Laser Cutting</h3>
        <span class="text-muted small">Process ID: LC</span>
      </div>
      <div class="mt-2">
        <div class="d-flex justify-content-between">
          <span class="small text-muted">Progress</span>
          <span class="small">1704 of 2000</span>
        </div>
        <div class="progress" style="height: 10px;" aria-label="Laser Cutting progress" aria-valuenow="85" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-bar bg-primary" style="width: 85%"></div>
        </div>
      </div>

      <!-- Example: a single batch -->
      <div class="mt-3 batch-list">
        <div class="list-group">
          <div class="list-group-item batch-item">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <strong>LC-2205</strong>
                <span class="badge bg-success ms-2">Complete</span>
              </div>
              <span class="text-muted small">Vendor: In‑house</span>
            </div>
            <dl class="row kv mt-2">
              <dt class="col-6 col-md-3">Start</dt>
              <dd class="col-6 col-md-3">Oct 30, 2025, 09:10 AM</dd>
              <dt class="col-6 col-md-3">Initial Qty</dt>
              <dd class="col-6 col-md-3">480</dd>
              <dt class="col-6 col-md-3">Selected</dt>
              <dd class="col-6 col-md-3">0</dd>
              <dt class="col-6 col-md-3">In Progress</dt>
              <dd class="col-6 col-md-3">0</dd>
              <dt class="col-6 col-md-3">Output</dt>
              <dd class="col-6 col-md-3">480</dd>
            </dl>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Process: Surface Grinding -->
  <div class="card proc-card border-start border-3 border-primary-subtle shadow-sm card-subtle mt-3">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h3 class="h5 mb-0">Surface Grinding</h3>
        <span class="text-muted small">Process ID: SG</span>
      </div>
      <div class="mt-2">
        <div class="d-flex justify-content-between">
          <span class="small text-muted">Progress</span>
          <span class="small">378 of 2000</span>
        </div>
        <div class="progress" style="height: 10px;" aria-label="Surface Grinding progress" aria-valuenow="19" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-bar bg-primary" style="width: 19%"></div>
        </div>
      </div>

      <!-- Batches: SG-2401, SG-2402 -->
      <div class="mt-3 batch-list">
        <div class="list-group">
          <div class="list-group-item batch-item">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <strong>SG-2401</strong>
                <span class="badge bg-primary ms-2">In progress</span>
              </div>
              <span class="text-muted small">Vendor: In‑house</span>
            </div>
            <dl class="row kv mt-2">
              <dt class="col-6 col-md-3">Start</dt>
              <dd class="col-6 col-md-3">Oct 31, 2025, 10:30 AM</dd>
              <dt class="col-6 col-md-3">Initial Qty</dt>
              <dd class="col-6 col-md-3">20</dd>
              <dt class="col-6 col-md-3">Selected</dt>
              <dd class="col-6 col-md-3">0</dd>
              <dt class="col-6 col-md-3">In Progress</dt>
              <dd class="col-6 col-md-3">0</dd>
              <dt class="col-6 col-md-3">Output</dt>
              <dd class="col-6 col-md-3">0</dd>
            </dl>
          </div>

          <div class="list-group-item batch-item">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <strong>SG-2402</strong>
                <span class="badge bg-success ms-2">Complete</span>
              </div>
              <span class="text-muted small">Vendor: In‑house</span>
            </div>
            <dl class="row kv mt-2">
              <dt class="col-6 col-md-3">Start</dt>
              <dd class="col-6 col-md-3">Oct 31, 2025, 01:15 PM</dd>
              <dt class="col-6 col-md-3">Initial Qty</dt>
              <dd class="col-6 col-md-3">100</dd>
              <dt class="col-6 col-md-3">Selected</dt>
              <dd class="col-6 col-md-3">0</dd>
              <dt class="col-6 col-md-3">In Progress</dt>
              <dd class="col-6 col-md-3">0</dd>
              <dt class="col-6 col-md-3">Output</dt>
              <dd class="col-6 col-md-3">100</dd>
            </dl>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Process: Plasma Coloring -->
  <div class="card proc-card border-start border-3 border-primary-subtle shadow-sm card-subtle mt-3">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h3 class="h5 mb-0">Plasma Coloring</h3>
        <span class="text-muted small">Process ID: PC</span>
      </div>
      <div class="mt-2">
        <div class="d-flex justify-content-between">
          <span class="small text-muted">Progress</span>
          <span class="small">100 of 2000</span>
        </div>
        <div class="progress" style="height: 10px;" aria-label="Plasma Coloring progress" aria-valuenow="5" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-bar bg-primary" style="width: 5%"></div>
        </div>
      </div>

      <!-- Batch: PC-3501 with highlighted log line -->
      <div class="mt-3 batch-list">
        <div class="list-group">
          <div class="list-group-item batch-item">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <strong>PC-3501</strong>
                <span class="badge bg-primary ms-2">In progress</span>
              </div>
              <span class="text-muted small">Vendor: In‑house</span>
            </div>

            <dl class="row kv mt-2">
              <dt class="col-6 col-md-3">Start</dt>
              <dd class="col-6 col-md-3">Oct 31, 2025, 10:00 AM</dd>
              <dt class="col-6 col-md-3">Initial Qty</dt>
              <dd class="col-6 col-md-3">100</dd>
              <dt class="col-6 col-md-3">Selected</dt>
              <dd class="col-6 col-md-3">0</dd>
              <dt class="col-6 col-md-3">In Progress</dt>
              <dd class="col-6 col-md-3">0</dd>
              <dt class="col-6 col-md-3">Output</dt>
              <dd class="col-6 col-md-3">0</dd>
            </dl>

            <!-- Highlighted log line within the batch card -->
            <div class="alert alert-light border mt-2 mb-0 py-2">
              <div class="small">
                <strong>Oct 31, 2025, 10:00 AM</strong> — Received = 100 → Accepted = 95 → Rejected = 3 → Rework = 2
              </div>
            </div>
          </div>

          <!-- Another batch example -->
          <div class="list-group-item batch-item">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <strong>PC-3502</strong>
                <span class="badge bg-success ms-2">Complete</span>
              </div>
              <span class="text-muted small">Vendor: In‑house</span>
            </div>
            <dl class="row kv mt-2">
              <dt class="col-6 col-md-3">Start</dt>
              <dd class="col-6 col-md-3">Oct 31, 2025, 03:05 PM</dd>
              <dt class="col-6 col-md-3">Initial Qty</dt>
              <dd class="col-6 col-md-3">50</dd>
              <dt class="col-6 col-md-3">Selected</dt>
              <dd class="col-6 col-md-3">0</dd>
              <dt class="col-6 col-md-3">In Progress</dt>
              <dd class="col-6 col-md-3">0</dd>
              <dt class="col-6 col-md-3">Output</dt>
              <dd class="col-6 col-md-3">50</dd>
            </dl>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
