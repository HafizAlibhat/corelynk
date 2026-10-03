<?php
// ACTIVE NAVIGATION RENDERER — sidebar shell.
// Menu data, ordering and RBAC filtering all come from helper cl_nav_* (app/Helpers/nav_helper.php).
// Global actions (search, quick create, settings, profile) live in partials/top_nav.
helper('nav');
$modules = cl_nav_modules();
$currentUrl = current_url();

// Segments that are action words and should never be standalone links
$nonLinkableSegments = ['view', 'edit', 'create', 'show', 'update', 'delete', 'add', 'new'];

// Build breadcrumb from URL segments
$segments = service('uri')->getSegments();
$breadcrumbs = [];
$buildPath = '';
foreach ($segments as $seg) {
    $buildPath .= '/' . $seg;
    $label = ucwords(str_replace(['-', '_'], ' ', $seg));
    $isNumeric = ctype_digit($seg);
    $isAction  = in_array(strtolower($seg), $nonLinkableSegments, true);
    // Detect UUID / public_id segments (standard UUID or 32-char hex) — show short form
    $isUuid = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $seg)
           || (bool) preg_match('/^[0-9a-f]{32}$/i', $seg);
    if ($isUuid) {
        $label   = '#' . strtolower(substr($seg, 0, 8));
        $isAction = true; // treat as non-linkable last crumb
    }
    $breadcrumbs[] = [
        'label'    => $label,
        'url'      => base_url($buildPath),
        'no_link'  => $isNumeric || $isAction,
    ];
}
?>
<?php // The left sidebar was removed: navigation lives entirely in partials/top_nav.php. ?>

<!-- Global Search Palette -->
<div id="clGlobalSearchPalette" class="cl-global-palette d-none" aria-hidden="true" data-search-endpoint="<?= site_url('search') ?>">
  <div class="cl-global-palette-backdrop" data-close="1"></div>
  <div class="cl-global-palette-panel" role="dialog" aria-label="Global Search" aria-modal="true">
    <div class="cl-global-palette-inputwrap">
      <i class="bi bi-search"></i>
      <input
        id="clGlobalSearchInput"
        type="search"
        class="form-control"
        placeholder="Search PO, RFQ, SO, quotation, customer, product..."
        autocomplete="off"
      >
      <button id="clCloseGlobalSearch" type="button" class="cl-global-palette-close" aria-label="Close search">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div id="clGlobalSearchDropdown" class="cl-global-search-dropdown d-none" role="listbox" aria-label="Global search results"></div>
  </div>
</div>

<!-- Breadcrumb Bar -->
<?php if (!empty($breadcrumbs)): ?>
<div class="cl-breadcrumb-bar">
  <a href="<?= base_url('/') ?>">Dashboard</a>
  <?php foreach ($breadcrumbs as $i => $bc): ?>
    <span class="separator">/</span>
    <?php if ($i === count($breadcrumbs) - 1 || !empty($bc['no_link'])): ?>
      <span class="cl-breadcrumb-current"><?= esc($bc['label']) ?></span>
    <?php else: ?>
      <a href="<?= esc($bc['url']) ?>"><?= esc($bc['label']) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>
