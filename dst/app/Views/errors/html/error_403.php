<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>403 — Access Denied</title>
    <style>
        body {
            height: 100%;
            background: #1a1d21;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #adb5bd;
            font-weight: 300;
            margin: 0;
        }
        h1 {
            font-weight: lighter;
            font-size: 3rem;
            margin-top: 0;
            margin-bottom: 0.5rem;
            color: #e9ecef;
        }
        .wrap {
            max-width: 600px;
            margin: 8rem auto;
            padding: 2rem 3rem;
            background: #212529;
            text-align: center;
            border: 1px solid #343a40;
            border-radius: 0.5rem;
        }
        .code {
            font-size: 6rem;
            font-weight: 700;
            color: #dc3545;
            margin-bottom: 0;
            line-height: 1;
        }
        p { margin-top: 1rem; }
        a {
            color: #0d6efd;
            text-decoration: none;
        }
        a:hover { text-decoration: underline; }
        .actions { margin-top: 2rem; }
        .btn {
            display: inline-block;
            padding: 0.5rem 1.5rem;
            border-radius: 0.375rem;
            font-size: 0.9rem;
            text-decoration: none;
            margin: 0 0.25rem;
        }
        .btn-primary { background: #0d6efd; color: #fff; }
        .btn-secondary { background: #6c757d; color: #fff; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="code">403</div>
        <h1>Access Denied</h1>
        <p><?= esc($message ?? 'You do not have permission to access this resource.') ?></p>
        <div class="actions">
            <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
        </div>
    </div>
</body>
</html>
