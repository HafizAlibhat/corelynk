<!DOCTYPE html>
<html>
<head>
    <title>Journal Lite Form Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Journal Lite Form Test</h2>
        <div class="alert alert-info">
            <strong>Debug Info:</strong> This form will help us test if POST is working.
        </div>
        
        <!-- Test Form -->
        <form method="post" action="<?= base_url('accounting/journal-lite/test-post') ?>" class="mb-4">
            <div class="card">
                <div class="card-header">Test Form - Send to test-post endpoint</div>
                <div class="card-body">
                    <input type="text" name="test_field" value="test_value" class="form-control mb-2">
                    <button type="submit" class="btn btn-warning">Send Test POST</button>
                </div>
            </div>
        </form>

        <!-- Actual Form -->
        <form method="post" action="<?= base_url('accounting/journal-lite') ?>">
            <?= csrf_field() ?>
            <div class="card">
                <div class="card-header">Actual Journal Form</div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Date</label>
                            <input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Memo</label>
                            <input type="text" name="memo" class="form-control" value="Test Entry" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Debit Account</label>
                            <select name="account_debit" class="form-select" required>
                                <option value="1">1000 - Cash</option>
                                <option value="2">1100 - Bank</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Credit Account</label>
                            <select name="account_credit" class="form-select" required>
                                <option value="3">1200 - Accounts Receivable</option>
                                <option value="4">2000 - Accounts Payable</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" name="amount" class="form-control" value="100.00" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Post Journal Entry</button>
                </div>
            </div>
        </form>
        
        <div class="mt-4">
            <h4>Quick Links:</h4>
            <a href="<?= base_url('accounting/journal-lite/debug') ?>" class="btn btn-sm btn-info">Debug Info</a>
            <a href="<?= base_url('accounting/journal-lite/test-insert') ?>" class="btn btn-sm btn-success">Force Insert</a>
            <a href="<?= base_url('accounting/journal-lite') ?>" class="btn btn-sm btn-secondary">Back to Journal Lite</a>
        </div>
    </div>
</body>
</html>