<!DOCTYPE html>
<html>
<head>
    <title>Debug Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .error { color: red; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1 class="error">System Error Debug</h1>
    <p><strong>Error Message:</strong> <?= htmlspecialchars($error ?? 'Unknown error') ?></p>
    <h3>Stack Trace:</h3>
    <pre><?= htmlspecialchars($trace ?? 'No trace available') ?></pre>
    
    <hr>
    <p><a href="/batches">← Back to Batches</a> | <a href="/testsystem">Test System</a></p>
</body>
</html>
