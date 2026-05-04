<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Accounting Dashboard<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
.test-dashboard {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    text-align: center;
    margin: 2rem 0;
}
.test-card {
    background: white;
    color: #333;
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem 0;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
</style>

<div class="container-fluid">
    <div class="test-dashboard">
        <h1>🎯 Financial Dashboard</h1>
        <p>This is a test to check if CSS is loading properly</p>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="test-card">
                <h5>Test Card 1</h5>
                <p>If you can see this styled properly, CSS is working!</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="test-card">
                <h5>Test Card 2</h5>
                <p>Bootstrap grid should work: <?= isset($error) ? 'Error: ' . $error : 'No errors detected' ?></p>
            </div>
        </div>
    </div>
    
    <div class="test-card">
        <h5>Debug Info</h5>
        <ul style="text-align: left;">
            <li>Controller: AccountingDashboard</li>
            <li>View: accounting/dashboard.php</li>
            <li>Time: <?= date('Y-m-d H:i:s') ?></li>
            <li>Data available: 
                <ul>
                    <li>financial_overview: <?= isset($financial_overview) ? 'Yes' : 'No' ?></li>
                    <li>cash_position: <?= isset($cash_position) ? 'Yes' : 'No' ?></li>
                    <li>account_summary: <?= isset($account_summary) ? 'Yes' : 'No' ?></li>
                    <li>recent_activity: <?= isset($recent_activity) ? 'Yes' : 'No' ?></li>
                    <li>key_metrics: <?= isset($key_metrics) ? 'Yes' : 'No' ?></li>
                    <li>alerts: <?= isset($alerts) ? 'Yes' : 'No' ?></li>
                </ul>
            </li>
        </ul>
    </div>
</div>

<?= $this->endSection() ?>