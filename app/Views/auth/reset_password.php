<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="theme-color" content="#090718">
    <title>Reset Password - CoreLynk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link href="<?= base_url('assets/css/auth-login.css') ?>" rel="stylesheet">
</head>
<body><main class="auth-shell">
    <section class="auth-showcase" aria-labelledby="resetIntro"><div class="brand-lockup"><span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span><span>CoreLynk</span></div><div class="showcase-copy"><span class="eyebrow"><i class="bi bi-key"></i> One-time link</span><h1 id="resetIntro">Choose a new<br><span>password.</span></h1><p>This link expires after 30 minutes and cannot be used twice.</p></div></section>
    <section class="auth-panel" aria-labelledby="resetTitle"><div class="login-card"><header class="login-header"><span class="login-kicker">ACCOUNT RECOVERY</span><h2 id="resetTitle">Reset password</h2></header><div class="login-body">
        <?php $validation = session('validation'); ?>
        <?php if (session('error')): ?><div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div><?php endif; ?>
        <?php if (! empty($tokenValid)): ?>
            <?= form_open('/auth/reset-password') ?><input type="hidden" name="token" value="<?= esc($token) ?>"><div class="field-group"><label for="new_password" class="cl-label">New password</label><div class="input-shell"><i class="bi bi-lock"></i><input id="new_password" type="password" name="new_password" class="form-control cl-input" minlength="8" autocomplete="new-password" required></div></div><div class="field-group"><label for="confirm_password" class="cl-label">Confirm new password</label><div class="input-shell"><i class="bi bi-lock-fill"></i><input id="confirm_password" type="password" name="confirm_password" class="form-control cl-input" minlength="8" autocomplete="new-password" required></div></div><?php if ($validation): ?><div class="alert alert-danger" role="alert"><?= esc(implode(' ', $validation->getErrors())) ?></div><?php endif; ?><button type="submit" class="btn-signin"><span>Update password</span><i class="bi bi-check2"></i></button><?= form_close() ?>
        <?php else: ?><div class="alert alert-danger" role="alert">This reset link is invalid, expired, or has already been used.</div><a class="btn-signin d-block text-center text-decoration-none" href="<?= base_url('/auth/forgot-password') ?>">Request a new link</a><?php endif; ?>
        <div class="login-footer"><a href="<?= base_url('/auth/login') ?>"><i class="bi bi-arrow-left"></i> Back to sign in</a></div>
    </div></div></section>
</main></body></html>
