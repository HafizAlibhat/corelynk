
<div class="card shadow-sm">
    <div class="card-header">
        <strong>System Modules Tree</strong>
    </div>
    <div class="card-body">
        <div id="corelynk-modules-tree"></div>
        <div class="mt-3 small text-muted">
            <div>Notes:</div>
            <ul>
                <li>Tree structure is auto-generated from <code>/modules.json</code>. Run <code>php scripts/generate_modules_json.php</code> after adding new modules.</li>
                <li>Click any module to open its page in a new tab.</li>
            </ul>
        </div>
    </div>
</div>
<script>
    // Provide a correct absolute URL for modules.json to the tree script
    window.MODULES_JSON_URL = '<?= rtrim(base_url('/'), '/') ?>/modules.json';
</script>
<script src="<?= base_url('assets/js/modules-tree.js') ?>"></script>
