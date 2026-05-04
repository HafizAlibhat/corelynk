<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication – CoreLynk</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
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
                --cl-bg: #0f172a; --cl-card: #1e293b; --cl-text: #e2e8f0;
                --cl-muted: #94a3b8; --cl-border: #334155; --cl-input-bg: #0f172a;
                --cl-shadow: 0 4px 24px rgba(0,0,0,.3);
            }
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--cl-bg); color: var(--cl-text);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0;
        }
        body::before {
            content:''; position:fixed; inset:0;
            background: radial-gradient(ellipse at 20% 50%, rgba(79,70,229,.08) 0%, transparent 60%),
                        radial-gradient(ellipse at 80% 20%, rgba(99,102,241,.06) 0%, transparent 50%);
            z-index:0;
        }
        .mfa-wrapper { position:relative; z-index:1; width:100%; max-width:420px; padding:1rem; }

        .mfa-card {
            background: var(--cl-card); border: 1px solid var(--cl-border);
            border-radius: 16px; box-shadow: var(--cl-shadow); overflow: hidden;
        }
        .mfa-header { text-align:center; padding:2.5rem 2rem 1rem; }
        .mfa-icon {
            width:64px; height:64px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-radius:16px; display:inline-flex; align-items:center; justify-content:center;
            margin-bottom:1rem; box-shadow:0 4px 12px rgba(245,158,11,.25);
        }
        .mfa-icon i { font-size:1.75rem; color:#fff; }
        .mfa-header h1 { font-size:1.25rem; font-weight:700; margin:0; }
        .mfa-header p { color:var(--cl-muted); font-size:.8125rem; margin:.5rem 0 0; line-height:1.5; }

        .mfa-body { padding:0 2rem 2rem; }

        .otp-input {
            background: var(--cl-input-bg); border:2px solid var(--cl-border);
            border-radius:12px; padding:.75rem 1rem; font-size:1.75rem;
            font-weight:600; text-align:center; letter-spacing:.5em;
            color:var(--cl-text); width:100%; transition:border-color .15s, box-shadow .15s;
        }
        .otp-input:focus {
            border-color:var(--cl-primary);
            box-shadow:0 0 0 3px rgba(79,70,229,.15);
            outline:none;
        }

        .btn-verify {
            background:var(--cl-primary); color:#fff; border:none; border-radius:10px;
            padding:.7rem; font-size:.9375rem; font-weight:600; width:100%;
            transition:background .15s, transform .1s;
        }
        .btn-verify:hover { background:var(--cl-primary-dark); color:#fff; transform:translateY(-1px); }

        .mfa-help { font-size:.8125rem; color:var(--cl-muted); text-align:center; margin-top:1rem; }
        .mfa-help a { color:var(--cl-primary); text-decoration:none; }

        .alert { font-size:.8125rem; border-radius:10px; padding:.6rem .875rem; }
    </style>
</head>
<body>

<div class="mfa-wrapper">
    <div class="mfa-card">
        <div class="mfa-header">
            <div class="mfa-icon"><i class="bi bi-shield-lock"></i></div>
            <h1>Two-Factor Authentication</h1>
            <p>Enter the 6-digit code from your authenticator app, or use a recovery code.</p>
        </div>

        <div class="mfa-body">
            <?php if (session()->has('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i><?= session('error') ?>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?= form_open('/auth/mfa-verify', ['id' => 'mfaForm']) ?>

                <div class="mb-3">
                    <input type="text"
                           class="otp-input"
                           id="code"
                           name="code"
                           maxlength="8"
                           inputmode="numeric"
                           autocomplete="one-time-code"
                           placeholder="------"
                           autofocus
                           required>
                </div>

                <button type="submit" class="btn btn-verify">
                    <i class="bi bi-check-circle me-1"></i>Verify
                </button>
            <?= form_close() ?>

            <div class="mfa-help">
                <p class="mb-1">Can't access your authenticator?</p>
                <p>Use one of your <strong>recovery codes</strong> instead.</p>
                <a href="<?= base_url('/auth/login') ?>"><i class="bi bi-arrow-left me-1"></i>Back to login</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-submit when 6 digits entered
    const codeInput = document.getElementById('code');
    codeInput.addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9a-zA-Z]/g, '');
        if (/^\d{6}$/.test(this.value)) {
            document.getElementById('mfaForm').submit();
        }
    });
</script>
</body>
</html>
