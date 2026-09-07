<?php
$currentPath = trim(service('request')->getUri()->getPath(), '/');
$basePath = trim((string) parse_url(base_url(), PHP_URL_PATH), '/');
if ($basePath !== '' && ($currentPath === $basePath || str_starts_with($currentPath, $basePath . '/'))) {
    $currentPath = trim(substr($currentPath, strlen($basePath)), '/');
}

// List pages share one visual shell while their existing forms and data bindings stay untouched.
$listViewPatterns = [
    '#^(products|vendors|customers|documents|quotations|sales-orders|customer-invoices)$#',
    '#^(components|price-lists|search|vendor-receive|odoo)$#',
    '#^inventory/(stock|journal|adjustments|transfers)$#',
    '#^(product-ledger|product-attributes|product-categories|product-variants|product-attribute-values|product-attribute-assignments|reports)$#',
    '#^(preparation-profiles|workflow-templates|process-templates|process-categories|customs-invoices)$#',
    '#^newpurchaseui/(rfqpo|pos|rfqs)$#',
    '#^new-purchase-rfqs$#',
    '#^new-purchase-grns/list$#',
    '#^(vendor-bills|subcontract-orders|gate_passes|work-orders|processes|batches|logs)$#',
    '#^accounting/(accounts|journals|cheques(?:/balances)?|credit-notes|purchase-orders|vendor-payments|customer-payments)$#',
    '#^(employees|quality-control)$#',
    '#^warehouse/(ready-to-ship|incoming-shipments)$#',
    '#^delivery-orders(?:/(?:shipped|pending-followup))?$#',
    '#^(audit-log|security/auth-logs)$#',
];
$isListView = false;
foreach ($listViewPatterns as $listViewPattern) {
    if (preg_match($listViewPattern, $currentPath) === 1) {
        $isListView = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?> - Corelynk</title>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <base href="<?= rtrim(site_url(), '\/') ?>/">
    <script>
        window.APP_BASE = window.APP_BASE || '<?= rtrim(site_url(), '\/') ?>';
    </script>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Manrope matches the rounded, high-legibility SaaS reference typography. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Accounting Dashboard Styles -->
        <link rel="stylesheet" href="<?= base_url('assets/css/accounting-dashboard.css') ?>">
    <!-- Fallback duplicate (root-relative) in case base_url misconfigured -->
    <link rel="stylesheet" href="/corelynk/public/assets/css/accounting-dashboard.css" onerror="this.remove()">
        <!-- Universal List/Table Styles -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>?v=2">
    <!-- Select2 (searchable selects) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= base_url('assets/css/product-image-hover-preview.css') ?>?v=1">
    <script src="<?= base_url('assets/js/theme-engine.js') ?>?v=9" defer></script>
    <!-- Speed: avoid blocking on third-party CDNs. Chart.js is now loaded lazily only on pages that need it. -->
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <!-- jQuery (required by Select2) and Select2 will be initialized at the end of the page -->

    <script>
        // Early theme application to avoid FOUC and ensure correct mode before paint
        (function() {
            try {
                var designTheme = localStorage.getItem('corelynk_design_theme_v1');
                var pref = localStorage.getItem('global_theme_pref') || 'light';
                var dark = designTheme
                    ? designTheme === 'graphite-dark'
                    : pref === 'dark' || (pref === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.setAttribute('data-cl-theme', designTheme || (dark ? 'graphite-dark' : 'corelynk-enterprise'));
                if (dark) {
                    document.documentElement.setAttribute('data-bs-theme', 'dark');
                    document.addEventListener('DOMContentLoaded', function(){ document.body.classList.add('theme-dark'); });
                } else {
                    document.documentElement.setAttribute('data-bs-theme', 'light');
                    document.addEventListener('DOMContentLoaded', function(){ document.body.classList.remove('theme-dark'); });
                }
            } catch(e) { /* ignore */ }
        })();
    </script>

    <!-- Select2 dark-theme fixes: ensure searchable selects fit the app dark theme -->
    <style>
        body.theme-dark .select2-container--default .select2-selection--single,
        body.theme-dark .select2-container--default .select2-selection--multiple {
            background: var(--gray-50) !important;
            border-color: var(--gray-300) !important;
            color: var(--gray-600) !important;
        }

        body.theme-dark .select2-container--default .select2-selection__rendered {
            color: var(--gray-600) !important;
        }

        body.theme-dark .select2-container--default .select2-selection__arrow b {
            border-top-color: var(--gray-600) !important;
        }

        body.theme-dark .select2-dropdown {
            background: var(--white) !important;
            color: var(--gray-600) !important;
            border-color: var(--gray-300) !important;
        }

        body.theme-dark .select2-results__option--highlighted,
        body.theme-dark .select2-results__option[aria-selected="true"] {
            background: var(--gray-100) !important;
            color: var(--gray-700) !important;
        }

        body.theme-dark .select2-search--dropdown .select2-search__field,
        body.theme-dark .select2-search--inline .select2-search__field {
            background: transparent !important;
            color: var(--gray-600) !important;
        }

        body.theme-dark .select2-container--default .select2-selection__placeholder {
            color: var(--gray-500) !important;
        }

        .select2-container .select2-selection--single { height: calc(1.5em + 0.75rem); }
        .select2-container .select2-selection__rendered { line-height: calc(1.5em + 0.75rem); }
    </style>
    <?= $this->renderSection('styles') ?>
    <!-- Shared shell assets load last so page styles cannot create a second navigation design. -->
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system.css') ?>?v=36">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/list-view.css') ?>?v=2">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/detail-view.css') ?>?v=2">
    <link rel="stylesheet" href="<?= base_url('assets/css/shell-navigation.css') ?>?v=1">
    <link rel="stylesheet" href="<?= base_url('assets/css/sidebar-navigation.css') ?>?v=3">
    <!-- Loaded last: single owner of the top bar and the shell grid geometry. -->
    <link rel="stylesheet" href="<?= base_url('assets/css/top-navigation.css') ?>?v=3">
    <script src="<?= base_url('assets/js/list-view.js') ?>?v=1" defer></script>
</head>
<body>
    <a class="cl-skip-link" href="#mainContent">Skip to page content</a>
    <?php // Full-width top navigation: the app's only menu. ?>
    <?= $this->include('partials/top_nav') ?>
    <div class="app-layout">
        <?php // Search palette + breadcrumb bar (the sidebar that used to live here is gone). ?>
        <?= $this->include('partials/global_nav') ?>

        <main class="content-wrapper main-content<?= $isListView ? ' cl-list-host-page' : '' ?>" id="mainContent" tabindex="-1">
            <!-- Flash Messages -->
            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?= session()->getFlashdata('success') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?= session()->getFlashdata('error') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('warning')): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?= session()->getFlashdata('warning') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('info')): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    <?= session()->getFlashdata('info') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Page Content -->
            <?= $this->renderSection('content') ?>
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery and Select2 for searchable dropdowns -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="<?= base_url('assets/js/product-image-hover-preview.js') ?>?v=1"></script>

    <script>
        // Initialize Select2 on elements with .searchable once DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            try {
                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                    window.jQuery('.searchable').each(function() {
                        try {
                            if (window.jQuery(this).data('select2')) {
                                window.jQuery(this).select2('destroy');
                            }
                            window.jQuery(this).select2({ width: '100%', placeholder: 'Search...', allowClear: true });
                        } catch (e) {
                            console.warn('Select2 init failed', e);
                        }
                    });
                }
            } catch (e) { /* ignore */ }
        });
    </script>


    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    try {
                        if (alert.classList.contains('show')) {
                            if (window.bootstrap && window.bootstrap.Alert) {
                                const bsAlert = new window.bootstrap.Alert(alert);
                                bsAlert.close();
                            } else {
                                alert.parentNode && alert.parentNode.removeChild(alert);
                            }
                        }
                    } catch (e) { /* ignore */ }
                }, 5000);
            });
        });

        // CSRF Token for AJAX requests
        window.csrfToken = '<?= csrf_token() ?>';
        window.csrfHash = '<?= csrf_hash() ?>';
    </script>

    <!-- Remote modal (loads pages via AJAX into a modal so users don't leave the current page) -->
    <div class="modal fade" id="remoteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="remoteModalLabel">Loading...</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-4" id="remoteModalSpinner">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                    </div>
                    <div id="remoteModalContent"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Delegated handler for opening links/buttons in the remote modal
        document.addEventListener('click', function(e) {
            var trigger = e.target.closest && e.target.closest('.open-remote-modal');
            if (!trigger) return;
            e.preventDefault();
            var url = trigger.getAttribute('data-url') || trigger.getAttribute('href');
            if (!url) return;
            var title = trigger.getAttribute('data-title') || trigger.getAttribute('title') || 'Manage';

            var modalEl = document.getElementById('remoteModal');
            var modal = new bootstrap.Modal(modalEl);
            document.getElementById('remoteModalLabel').textContent = title;
            document.getElementById('remoteModalContent').innerHTML = '';
            document.getElementById('remoteModalSpinner').style.display = 'block';
            modal.show();

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(res){ return res.text(); })
                .then(function(html){
                    document.getElementById('remoteModalSpinner').style.display = 'none';
                    var modalContent = document.getElementById('remoteModalContent');
                    modalContent.innerHTML = html;

                    // Execute any inline <script> tags included in the fetched HTML so that
                    // page-specific initializers (like the Add Value handlers) run. When
                    // HTML is injected via innerHTML the browser doesn't execute scripts,
                    // so we manually recreate them.
                    try {
                        Array.from(modalContent.querySelectorAll('script')).forEach(function(s){
                            var ns = document.createElement('script');
                            if (s.src) {
                                ns.src = s.src; // external script will load and execute
                                // preserve async/defer if present
                                if (s.async) ns.async = true;
                                if (s.defer) ns.defer = true;
                            } else {
                                ns.text = s.innerHTML;
                            }
                            s.parentNode.replaceChild(ns, s);
                        });
                    } catch(e) { console.error('Error executing modal scripts', e); }

                    // Initialize Select2 inside loaded content if available
                    try {
                        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                            window.jQuery('#remoteModalContent').find('.searchable').each(function(){
                                try { if (window.jQuery(this).data('select2')) window.jQuery(this).select2('destroy'); } catch(e){}
                                try { window.jQuery(this).select2({ width: '100%', placeholder: 'Search...', allowClear: true }); } catch(e){}
                            });
                        }
                    } catch(e) { /* ignore */ }
                })
                .catch(function(err){
                    document.getElementById('remoteModalSpinner').style.display = 'none';
                    document.getElementById('remoteModalContent').innerHTML = '<div class="alert alert-danger">Failed to load content. <a href="'+url+'" target="_blank">Open in new tab</a></div>';
                });
        });
    </script>

    <!-- Page-specific JavaScript -->
    <?= $this->renderSection('js') ?>

    <!-- Bootstrap JS (removed duplicate include) -->


</body>
</html>
