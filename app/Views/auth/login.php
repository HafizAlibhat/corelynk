<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – CoreLynk</title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Inter font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --cl-primary: #4f46e5;
            --cl-primary-dark: #4338ca;
            --cl-bg: #f1f5f9;
            --cl-card: #ffffff;
            --cl-text: #1e293b;
            --cl-muted: #64748b;
            --cl-border: #e2e8f0;
            --cl-input-bg: #f8fafc;
            --cl-shadow: 0 4px 24px rgba(0,0,0,.08);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --cl-bg: #0f172a;
                --cl-card: #1e293b;
                --cl-text: #e2e8f0;
                --cl-muted: #94a3b8;
                --cl-border: #334155;
                --cl-input-bg: #0f172a;
                --cl-shadow: 0 4px 24px rgba(0,0,0,.3);
            }
        }

        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--cl-bg);
            color: var(--cl-text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        /* Subtle animated bg pattern */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(79,70,229,.08) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(99,102,241,.06) 0%, transparent 50%);
            z-index: 0;
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            padding: 1rem;
        }

        .login-card {
            background: var(--cl-card);
            border: 1px solid var(--cl-border);
            border-radius: 16px;
            box-shadow: var(--cl-shadow);
            overflow: hidden;
        }

        .login-header {
            text-align: center;
            padding: 2.5rem 2rem 1.5rem;
        }
        .login-logo {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--cl-primary), #7c3aed);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(79,70,229,.25);
        }
        .login-logo i {
            font-size: 1.75rem;
            color: #fff;
        }
        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: -.02em;
        }
        .login-header p {
            color: var(--cl-muted);
            font-size: .875rem;
            margin: .25rem 0 0;
        }

        .login-body {
            padding: 0 2rem 2rem;
        }

        /* Form controls */
        .cl-label {
            font-size: .8125rem;
            font-weight: 500;
            margin-bottom: .375rem;
            color: var(--cl-muted);
        }
        .cl-input {
            background: var(--cl-input-bg);
            border: 1px solid var(--cl-border);
            border-radius: 10px;
            padding: .65rem .875rem;
            font-size: .9375rem;
            color: var(--cl-text);
            transition: border-color .15s, box-shadow .15s;
        }
        .cl-input:focus {
            border-color: var(--cl-primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,.15);
            outline: none;
        }
        .cl-input.is-invalid {
            border-color: #ef4444;
        }

        .input-group .cl-input { border-top-right-radius: 0; border-bottom-right-radius: 0; }
        .input-group .btn-toggle-pw {
            background: var(--cl-input-bg);
            border: 1px solid var(--cl-border);
            border-left: 0;
            border-radius: 0 10px 10px 0;
            color: var(--cl-muted);
            padding: 0 .75rem;
        }
        .input-group .btn-toggle-pw:hover { color: var(--cl-primary); }

        .btn-signin {
            background: var(--cl-primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: .7rem;
            font-size: .9375rem;
            font-weight: 600;
            width: 100%;
            transition: background .15s, transform .1s;
        }
        .btn-signin:hover {
            background: var(--cl-primary-dark);
            color: #fff;
            transform: translateY(-1px);
        }
        .btn-signin:active { transform: translateY(0); }

        .login-footer {
            text-align: center;
            padding: .75rem 0 0;
        }
        .login-footer a {
            color: var(--cl-muted);
            font-size: .8125rem;
            text-decoration: none;
        }
        .login-footer a:hover { color: var(--cl-primary); }

        .page-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            color: var(--cl-muted);
            font-size: .75rem;
            padding: 1rem;
        }

        /* Alert overrides */
        .login-body .alert {
            font-size: .8125rem;
            border-radius: 10px;
            padding: .6rem .875rem;
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo"><i class="bi bi-link-45deg"></i></div>
            <h1>CoreLynk</h1>
            <p>Sign in to your account</p>
        </div>

        <div class="login-body">
            <?php if (session()->has('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i><?= session('error') ?>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (session()->has('success')): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle-fill me-1"></i><?= session('success') ?>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (session()->has('info')): ?>
                <div class="alert alert-info alert-dismissible fade show">
                    <i class="bi bi-info-circle-fill me-1"></i><?= session('info') ?>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?= form_open('/auth/login', ['class' => 'needs-validation', 'novalidate' => true, 'id' => 'loginForm']) ?>

                <div class="mb-3">
                    <label for="email" class="cl-label">Email Address</label>
                    <input type="email"
                           class="form-control cl-input <?= session('validation') && session('validation')->hasError('email') ? 'is-invalid' : '' ?>"
                           id="email"
                           name="email"
                           value="<?= old('email') ?>"
                           placeholder="you@company.com"
                           autocomplete="email"
                           autofocus
                           required>
                    <?php if (session('validation') && session('validation')->hasError('email')): ?>
                        <div class="invalid-feedback"><?= session('validation')->getError('email') ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="password" class="cl-label">Password</label>
                    <div class="input-group">
                        <input type="password"
                               class="form-control cl-input <?= session('validation') && session('validation')->hasError('password') ? 'is-invalid' : '' ?>"
                               id="password"
                               name="password"
                               placeholder="Enter your password"
                               autocomplete="current-password"
                               required>
                        <button class="btn btn-toggle-pw" type="button" id="togglePassword" tabindex="-1"
                                aria-label="Toggle password visibility">
                            <i class="bi bi-eye"></i>
                        </button>
                        <?php if (session('validation') && session('validation')->hasError('password')): ?>
                            <div class="invalid-feedback"><?= session('validation')->getError('password') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                        <label class="form-check-label small" for="remember_me">Remember me</label>
                    </div>
                    <a href="<?= base_url('/auth/forgot-password') ?>" class="small text-decoration-none" style="color:var(--cl-primary)">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-signin" id="btnSignin">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
                </button>
            <?= form_close() ?>

            <div class="login-footer">
                <a href="<?= base_url('/') ?>"><i class="bi bi-arrow-left me-1"></i>Back to home</a>
            </div>
        </div>
    </div>
</div>

<div class="page-footer">
    <small>&copy; <?= date('Y') ?> CoreLynk &mdash; Enterprise Resource Planning</small>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle password
    document.getElementById('togglePassword').addEventListener('click', function () {
        const pw = document.getElementById('password');
        const ic = this.querySelector('i');
        pw.type = pw.type === 'password' ? 'text' : 'password';
        ic.classList.toggle('bi-eye');
        ic.classList.toggle('bi-eye-slash');
    });

    // Bootstrap validation
    (function () {
        'use strict';
        document.getElementById('loginForm').addEventListener('submit', function (e) {
            if (!this.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
            this.classList.add('was-validated');
        });
    })();

    // Auto-dismiss alerts
    setTimeout(function () {
        document.querySelectorAll('.alert').forEach(function (el) {
            try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch (_) {}
        });
    }, 6000);
</script>
</body>
</html>
