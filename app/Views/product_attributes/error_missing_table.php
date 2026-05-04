<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Attributes area not available</h5>
            </div>
            <div class="card-body">
                <p class="lead">The product attributes section is not available because the expected database table <code>product_attributes</code> was not found or a database error occurred.</p>
                <p class="small text-muted">This usually means the migrations that add the attributes table haven't been run yet.</p>

                <div class="alert alert-light">
                    <strong>Technical error:</strong>
                    <pre style="white-space:pre-wrap;word-break:break-word;"><?= esc($message) ?></pre>
                </div>

                <p>Please run the migrations to create the required table and then reload this page.</p>

                <div class="mb-3">
                    <label class="form-label">Run migrations (from project root)</label>
                    <div class="bg-dark rounded p-3 text-white"><code>php spark migrate</code></div>
                    <div class="form-text mt-2">If you're using XAMPP on Windows, run the above command from the project folder (where <code>spark</code> is located). Alternatively, run migrations from your deployment tooling.</div>
                </div>

                <p class="mt-3">After running migrations, refresh this page. If the problem persists, check your database connection and migration logs.</p>

                <a href="<?= base_url('/') ?>" class="btn btn-outline-secondary">Back to Dashboard</a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
