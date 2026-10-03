<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#090718">
    <title>Login - CoreLynk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link href="<?= base_url('assets/css/auth-login.css') ?>" rel="stylesheet">
</head>
<body>
<a class="skip-link" href="#loginForm">Skip to sign in</a>

<main class="auth-shell">
    <section class="auth-showcase" aria-labelledby="welcomeTitle">
        <div class="brand-lockup">
            <span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
            <span>CoreLynk</span>
        </div>

        <div class="showcase-copy">
            <span class="eyebrow"><i class="bi bi-stars" aria-hidden="true"></i> One connected workspace</span>
            <h1 id="welcomeTitle">Run your business<br><span>with clarity.</span></h1>
            <p>Sales, inventory, purchasing and finance—connected in one secure ERP workspace.</p>
        </div>

        <div class="insight-card" aria-hidden="true">
            <div class="insight-head">
                <div><span class="insight-label">SYSTEM OVERVIEW</span><strong>Everything in sync</strong></div>
                <span class="live-status"><i></i> LIVE</span>
            </div>
            <div class="insight-grid">
                <div><span>Sales</span><i class="bi bi-graph-up-arrow"></i></div>
                <div><span>Inventory</span><i class="bi bi-box-seam"></i></div>
                <div><span>Finance</span><i class="bi bi-wallet2"></i></div>
            </div>
            <div class="activity-line"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
        </div>

        <p class="showcase-note"><i class="bi bi-shield-check" aria-hidden="true"></i> Secure access to your organization workspace</p>
    </section>

    <section class="auth-panel" aria-labelledby="signInTitle">
        <div class="mobile-brand brand-lockup">
            <span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
            <span>CoreLynk</span>
        </div>

        <div class="login-card">
            <header class="login-header">
                <span class="login-kicker">WELCOME BACK</span>
                <h2 id="signInTitle">Sign in to CoreLynk</h2>
                <p>Enter your credentials to continue to the dashboard.</p>
            </header>

            <div class="login-body">
                <?php $validationErrors = $validation ?? session('validation'); ?>
                <div class="alert-stack" aria-live="polite">
                    <?php if (session()->has('error')): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span><?= session('error') ?></span>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss message"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (session()->has('success')): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="status">
                            <i class="bi bi-check-circle-fill" aria-hidden="true"></i><span><?= session('success') ?></span>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss message"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (session()->has('info')): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="status">
                            <i class="bi bi-info-circle-fill" aria-hidden="true"></i><span><?= session('info') ?></span>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss message"></button>
                        </div>
                    <?php endif; ?>
                </div>

                <?= form_open('/auth/login', ['class' => 'needs-validation', 'novalidate' => true, 'id' => 'loginForm']) ?>
                    <div class="field-group">
                        <label for="email" class="cl-label">Email address</label>
                        <div class="input-shell">
                            <i class="bi bi-envelope" aria-hidden="true"></i>
                            <input type="email" class="form-control cl-input <?= $validationErrors && $validationErrors->hasError('email') ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= old('email') ?>" placeholder="you@company.com" autocomplete="email" inputmode="email" autofocus required>
                            <?php if ($validationErrors && $validationErrors->hasError('email')): ?>
                                <div class="invalid-feedback"><?= $validationErrors->getError('email') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="field-group">
                        <div class="label-row">
                            <label for="password" class="cl-label">Password</label>
                            <a href="<?= base_url('/auth/forgot-password') ?>">Forgot password?</a>
                        </div>
                        <div class="input-shell">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                            <input type="password" class="form-control cl-input <?= $validationErrors && $validationErrors->hasError('password') ? 'is-invalid' : '' ?>" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                            <button class="btn-toggle-pw" type="button" id="togglePassword" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                            <?php if ($validationErrors && $validationErrors->hasError('password')): ?>
                                <div class="invalid-feedback"><?= $validationErrors->getError('password') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-options">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                            <label class="form-check-label" for="remember_me">Keep me signed in</label>
                        </div>
                        <span><i class="bi bi-shield-lock" aria-hidden="true"></i> Secure login</span>
                    </div>

                    <button type="submit" class="btn-signin" id="btnSignin"><span>Sign in to dashboard</span><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                <?= form_close() ?>

                <div class="login-footer">
                    <a href="<?= base_url('/') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to home</a>
                    <span>Need help? Contact your administrator.</span>
                </div>
            </div>
        </div>

        <footer class="page-footer">&copy; <?= date('Y') ?> CoreLynk <span></span> Enterprise Resource Planning</footer>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/auth-login.js') ?>"></script>
</body>
</html>
