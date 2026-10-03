<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="theme-color" content="#090718">
    <title>Choose Company - CoreLynk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link href="<?= base_url('assets/css/auth-login.css') ?>" rel="stylesheet">
</head>
<body><main class="auth-shell">
    <section class="auth-showcase" aria-labelledby="companyIntro"><div class="brand-lockup"><span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span><span>CoreLynk</span></div><div class="showcase-copy"><span class="eyebrow"><i class="bi bi-buildings"></i> Separate workspaces</span><h1 id="companyIntro">Choose your<br><span>company.</span></h1><p>Each workspace has separate users, permissions, records, inventory, and accounts.</p></div></section>
    <section class="auth-panel" aria-labelledby="companyTitle"><div class="login-card"><header class="login-header"><span class="login-kicker">YOUR WORKSPACES</span><h2 id="companyTitle"><?= $isPendingLogin ? 'Continue to a company' : 'Switch company' ?></h2></header><div class="login-body">
        <?php if (session('error')): ?><div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div><?php endif; ?>
        <?php if (empty($companies)): ?><div class="alert alert-warning" role="alert">No active company has been assigned to this account. Contact an administrator.</div><?php endif; ?>
        <?php foreach ($companies as $company): ?>
            <?= form_open('/companies/switch/' . rawurlencode($company['public_id']), ['class' => 'mb-3']) ?>
                <button class="btn-signin d-flex align-items-center justify-content-between" type="submit"<?= (int) $company['organization_id'] === $activeOrganizationId && ! $isPendingLogin ? ' disabled' : '' ?>><span><i class="bi bi-building me-2"></i><?= esc($company['name']) ?><small class="d-block opacity-75 text-start"><?= esc(ucfirst($company['access_role'])) ?></small></span><i class="bi bi-arrow-right"></i></button>
            <?= form_close() ?>
        <?php endforeach; ?>
        <?php if (! $isPendingLogin): ?><a class="btn-signin d-block text-center text-decoration-none mb-3" href="<?= base_url('/companies/create') ?>"><i class="bi bi-plus-lg me-2"></i>Create another company</a><?php endif; ?>
        <?php foreach (($provisioning ?? []) as $request): ?><div class="alert alert-info" role="status"><strong><?= esc($request['name']) ?></strong><br><small>Status: <?= esc(ucfirst($request['status'])) ?></small></div><?php endforeach; ?>
        <?php if (! $isPendingLogin): ?><div class="login-footer"><a href="<?= base_url('/') ?>"><i class="bi bi-arrow-left"></i> Back to dashboard</a></div><?php endif; ?>
    </div></div></section>
</main></body></html>
