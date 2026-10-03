<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="theme-color" content="#090718">
    <title>Forgot Password - CoreLynk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link href="<?= base_url('assets/css/auth-login.css') ?>" rel="stylesheet">
</head>
<body><main class="auth-shell">
    <section class="auth-showcase" aria-labelledby="resetIntro"><div class="brand-lockup"><span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span><span>CoreLynk</span></div><div class="showcase-copy"><span class="eyebrow"><i class="bi bi-shield-lock"></i> Secure recovery</span><h1 id="resetIntro">Recover access<br><span>safely.</span></h1><p>We will send a short-lived, one-time link to the email on your account.</p></div></section>
    <section class="auth-panel" aria-labelledby="forgotTitle"><div class="login-card"><header class="login-header"><span class="login-kicker">ACCOUNT RECOVERY</span><h2 id="forgotTitle">Forgot your password?</h2></header><div class="login-body">
        <?php $validation = session('validation'); ?>
        <?php if (session('error')): ?><div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div><?php endif; ?>
        <?= form_open('/auth/forgot-password') ?><div class="field-group"><label for="email" class="cl-label">Email address</label><div class="input-shell"><i class="bi bi-envelope" aria-hidden="true"></i><input id="email" type="email" name="email" class="form-control cl-input<?= $validation && $validation->hasError('email') ? ' is-invalid' : '' ?>" value="<?= esc(old('email')) ?>" autocomplete="email" required autofocus></div><?php if ($validation && $validation->hasError('email')): ?><div class="invalid-feedback d-block"><?= esc($validation->getError('email')) ?></div><?php endif; ?></div><button type="submit" class="btn-signin"><span>Send reset link</span><i class="bi bi-arrow-right"></i></button><?= form_close() ?>
        <div class="login-footer"><a href="<?= base_url('/auth/login') ?>"><i class="bi bi-arrow-left"></i> Back to sign in</a></div>
    </div></div></section>
</main></body></html>
