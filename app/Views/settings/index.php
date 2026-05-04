<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Settings<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $artBrand = esc($company['art_number_prefix'] ?? 'RI');
    $artNext  = (int)($art_counter ?? 1);
    $artPad   = str_pad((string)$artNext, 5, '0', STR_PAD_LEFT);
    $custPrefix = esc($customer_code_prefix ?? ($company['customer_code_prefix'] ?? $company['art_number_prefix'] ?? 'RI'));
    $custNext = (int)($customer_counter ?? 1);
    $vendorPrefix = esc($vendor_code_prefix ?? ($company['vendor_code_prefix'] ?? 'VEN'));
    $vendorNext = (int)($vendor_counter ?? 1);
?>
<style>
/* ── Settings Page ── */
.st-wrap { max-width: 1280px; margin: 0 auto; }

.st-page-hdr { display:flex; align-items:flex-start; justify-content:space-between; padding:.5rem 0 1rem; }
.st-page-title { font-size:1.05rem; font-weight:700; color:var(--gray-700); display:flex; align-items:center; gap:.4rem; margin:0; }
.st-page-title i { color:var(--primary-color); font-size:1rem; }
.st-page-sub { font-size:.73rem; color:var(--gray-500); margin-top:2px; }

/* ── Sidebar nav ── */
.st-nav {
  position: sticky; top: 72px;
  background: var(--white);
  border: 1px solid var(--gray-200);
  border-radius: .5rem;
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}
.st-nav-group-label {
  font-size: .6rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .8px; color: var(--gray-400);
  padding: .6rem 1rem .15rem; display:block;
}
.st-nav a {
  display: flex; align-items: center; gap: .5rem;
  padding: .45rem 1rem; font-size: .78rem; font-weight: 500;
  color: var(--gray-500); text-decoration: none;
  transition: background .1s, color .1s, border-color .1s;
  border-left: 3px solid transparent;
}
.st-nav a i { font-size: .82rem; width: 16px; text-align: center; flex-shrink: 0; }
.st-nav a:hover { background: var(--gray-100); color: var(--gray-700); }
.st-nav a.active {
  background: rgba(79,70,229,.08);
  color: var(--primary-color);
  border-left-color: var(--primary-color);
  font-weight: 600;
}
.st-nav a.active i { color: var(--primary-color); }
.st-nav-divider { height: 1px; background: var(--gray-200); margin: .2rem 0; }

/* ── Show/hide sections ── */
.settings-section { display: none; }
.settings-section.active { display: block; animation: stFade .18s ease; }
@keyframes stFade { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:translateY(0); } }

/* ── Card ── */
.settings-card {
  background: var(--white);
  border: 1px solid var(--gray-200);
  border-radius: .5rem;
  overflow: hidden;
  margin-bottom: 1rem;
  box-shadow: var(--shadow-sm);
}
.settings-card-header {
  display: flex; align-items: center; gap: .45rem;
  padding: .6rem 1rem;
  background: var(--gray-50);
  border-bottom: 1px solid var(--gray-200);
  font-size: .8rem; font-weight: 700; color: var(--gray-700);
}
.settings-card-header > i:first-child { color: var(--primary-color); font-size: .88rem; }
.settings-card-body { padding: 1rem; }

/* ── Labels ── */
.compact-label {
  display: block; font-size: .7rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: .3px;
  color: var(--gray-500); margin-bottom: .25rem;
}

/* ── Art preview ── */
.art-preview-box {
  background: rgba(79,70,229,.06);
  border: 1px solid rgba(79,70,229,.18);
  border-radius: .45rem; padding: .8rem 1rem; margin-bottom: 1rem;
}

/* ── Form save footer ── */
.st-form-footer {
  display: flex; justify-content: flex-end;
  padding-top: .65rem;
  border-top: 1px solid var(--gray-200);
  margin-top: .75rem;
}

/* ── Tables inside settings ── */
.settings-card .table { margin-bottom:0; }
.settings-card .table th {
  font-size:.68rem; text-transform:uppercase; letter-spacing:.4px;
  color:var(--gray-500); background:var(--gray-50); border-color:var(--gray-200);
}
.settings-card .table td { font-size:.8rem; color:var(--gray-600); border-color:var(--gray-200); vertical-align:middle; }

/* ── Active dot on form inputs (dark theme fix) ── */
.settings-card-body .text-muted { color: var(--gray-500) !important; }
.settings-card-body small, .settings-card-body p { color: var(--gray-500); }

/* ── PDF Template Picker ── */
.pdf-template-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 4px;
}
.tpl-card {
  cursor: pointer;
  width: 120px;
  border: 2px solid var(--gray-200, #e5e7eb);
  border-radius: 8px;
  overflow: hidden;
  background: var(--gray-50, #f9fafb);
  transition: border-color .15s, box-shadow .15s;
  position: relative;
}
.tpl-card:hover { border-color: #6b7280; box-shadow: 0 0 0 3px rgba(107,114,128,.15); }
.tpl-card.selected { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.2); }
.tpl-card.selected::after {
  content: '✓';
  position: absolute;
  top: 4px;
  right: 5px;
  width: 16px;
  height: 16px;
  background: #2563eb;
  color: #fff;
  font-size: 9px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  line-height: 16px;
  text-align: center;
}
.tpl-preview {
  width: 100%;
  height: 90px;
  padding: 4px;
  background: #fff;
  overflow: hidden;
  position: relative;
}
.tpl-name {
  font-size: 10px;
  font-weight: 600;
  text-align: center;
  padding: 5px 4px;
  color: var(--gray-700, #374151);
  border-top: 1px solid var(--gray-200, #e5e7eb);
  background: var(--gray-50, #f9fafb);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
/* Shared mini-preview atoms */
.tp-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:3px; }
.tp-logo-box { width:22px; height:13px; background:#cbd5e1; border-radius:2px; flex-shrink:0; }
.tp-company-lines { text-align:right; }
.tp-line { height:2px; border-radius:1px; background:#d1d5db; margin-bottom:1px; }
.tp-line.w18 { width:18%; } .tp-line.w25 { width:25%; } .tp-line.w30 { width:30%; }
.tp-line.w35 { width:35%; } .tp-line.w40 { width:40%; } .tp-line.w45 { width:45%; }
.tp-line.w50 { width:50%; } .tp-line.w55 { width:55%; } .tp-line.w60 { width:60%; }
.tp-line.w70 { width:70%; } .tp-line.w80 { width:80%; }
.tp-line.bold { height:3px; } .tp-line.darker { background:#94a3b8; } .tp-line.mt2 { margin-top:2px; }
.tp-divider { border-top:1px solid; margin:3px 0; }
.tp-title-row { margin-bottom:3px; }
.tp-customer { margin-bottom:3px; }
.tp-banner { margin-bottom:4px; }
.tp-table { width:100%; }
.tp-thead { height:5px; border-radius:1px; margin-bottom:1px; background:#e5e7eb; }
.tp-trow { height:4px; margin-bottom:1px; background:#f9fafb; }
.tp-trow.alt { background:#f3f4f6; }
.tp-totals-right { margin-top:3px; margin-left:auto; width:55%; }
.tp-customer-card { margin-bottom:4px; }
</style>
<div class="accounting-scope">
<div class="container-fluid px-3 py-2">
<div class="st-wrap">

    <?php if(session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show py-2 small mb-2"><i class="bi bi-check-circle me-1"></i><?= esc(session()->getFlashdata('success')) ?><button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button></div>
    <?php elseif(session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show py-2 small mb-2"><i class="bi bi-exclamation-triangle me-1"></i><?= esc(session()->getFlashdata('error')) ?><button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="st-page-hdr">
        <div>
            <div class="st-page-title"><i class="bi bi-gear-wide-connected"></i>Settings</div>
            <div class="st-page-sub">Configure your workspace, accounting, and integrations</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" style="font-size:.75rem;padding:.3rem .7rem" data-bs-toggle="modal" data-bs-target="#cleanDbModal">
            <i class="bi bi-trash3 me-1"></i>Clean Database
        </button>
    </div>

    <div class="row g-3">
        <!-- ═══ LEFT NAV ═══ -->
        <div class="col-lg-2 col-md-3">
            <nav class="st-nav" id="settingsNav">
                <div class="st-nav-group-label">General</div>
                <a href="#" class="active" data-section="company"><i class="bi bi-building"></i>Company</a>
                <a href="#" data-section="numbering"><i class="bi bi-hash"></i>Numbering</a>
                <a href="#" data-section="art-numbers"><i class="bi bi-upc-scan"></i>Art Numbers</a>
                <a href="#" data-section="fiscal"><i class="bi bi-calendar-range"></i>Fiscal Year</a>
                <div class="st-nav-divider"></div>
                <div class="st-nav-group-label">Finance</div>
                <a href="#" data-section="payments"><i class="bi bi-credit-card"></i>Payments</a>
                <a href="#" data-section="currencies"><i class="bi bi-coin"></i>Currencies</a>
                <a href="#" data-section="exchange"><i class="bi bi-arrow-left-right"></i>Exchange Rates</a>
                <div class="st-nav-divider"></div>
                <div class="st-nav-group-label">System</div>
                <a href="#" data-section="security"><i class="bi bi-shield-lock"></i>Security</a>
                <a href="#" data-section="odoo"><i class="bi bi-box-seam"></i>Odoo</a>
                <div class="st-nav-divider"></div>
                <div class="st-nav-group-label">Mobile</div>
                <a href="#" data-section="mobile"><i class="bi bi-phone"></i>Mobile App</a>
            </nav>
        </div>

        <!-- ═══ RIGHT CONTENT ═══ -->
        <div class="col-lg-10 col-md-9">

            <!-- ─── COMPANY ─── -->
            <div class="settings-section active" id="section-company">
                <div class="settings-card">
                    <div class="settings-card-header"><i class="bi bi-building"></i>Company Information</div>
                    <div class="settings-card-body">
                    <form method="post" action="<?= site_url('settings/saveCompany') ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="compact-label">Company Name *</label>
                                <input type="text" class="form-control form-control-sm" name="name" value="<?= esc($company['name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="compact-label">Contact</label>
                                <input type="text" class="form-control form-control-sm" name="contact" value="<?= esc($company['contact'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="compact-label">Email</label>
                                <input type="email" class="form-control form-control-sm" name="email" value="<?= esc($company['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="compact-label">Address</label>
                                <input type="text" class="form-control form-control-sm" name="address" value="<?= esc($company['address'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="compact-label">Phone</label>
                                <input type="text" class="form-control form-control-sm" name="phone" value="<?= esc($company['phone'] ?? '') ?>" placeholder="For PDF footer">
                            </div>
                            <div class="col-md-3">
                                <label class="compact-label">Website</label>
                                <input type="text" class="form-control form-control-sm" name="website" value="<?= esc($company['website'] ?? '') ?>" placeholder="e.g. www.company.com">
                            </div>
                            <div class="col-md-6">
                                <label class="compact-label">PDF Footer Text</label>
                                <input type="text" class="form-control form-control-sm" name="invoice_footer" value="<?= esc($company['invoice_footer'] ?? '') ?>" placeholder="Custom text for PDF footer (optional)">
                            </div>
                            <div class="col-12">
                                <label class="compact-label mb-2">PDF Template</label>
                                <?php $pdfTemplate = $company['pdf_template'] ?? 'default'; ?>
                                <input type="hidden" name="pdf_template" id="pdf_template_input" value="<?= esc($pdfTemplate) ?>">
                                <div class="pdf-template-grid">

                                    <!-- Default -->
                                    <div class="tpl-card <?= $pdfTemplate === 'default' ? 'selected' : '' ?>" data-tpl="default" title="Default">
                                        <div class="tpl-preview tpl-default">
                                            <div class="tp-header">
                                                <div class="tp-logo-box"></div>
                                                <div class="tp-company-lines">
                                                    <div class="tp-line w70 darker"></div>
                                                    <div class="tp-line w50"></div>
                                                </div>
                                            </div>
                                            <div class="tp-divider" style="border-color:#e5e7eb;"></div>
                                            <div class="tp-title-row">
                                                <div class="tp-line w40 bold" style="background:#0f172a;"></div>
                                                <div class="tp-line w25 mt2"></div>
                                            </div>
                                            <div class="tp-customer">
                                                <div class="tp-line w55 bold"></div>
                                                <div class="tp-line w40 mt2"></div>
                                            </div>
                                            <div class="tp-table">
                                                <div class="tp-thead" style="background:#e5e7eb;"></div>
                                                <div class="tp-trow"></div>
                                                <div class="tp-trow alt"></div>
                                                <div class="tp-trow"></div>
                                            </div>
                                            <div class="tp-totals-right">
                                                <div class="tp-line w60"></div>
                                                <div class="tp-line w60 mt2 bold accent" style="background:#0f172a;"></div>
                                            </div>
                                        </div>
                                        <div class="tpl-name">Default</div>
                                    </div>

                                    <!-- Modern Blue -->
                                    <div class="tpl-card <?= $pdfTemplate === 'modern_blue' ? 'selected' : '' ?>" data-tpl="modern_blue" title="Modern Blue">
                                        <div class="tpl-preview tpl-modern-blue">
                                            <div class="tp-header">
                                                <div class="tp-logo-box"></div>
                                                <div class="tp-company-lines">
                                                    <div class="tp-line w70 darker" style="background:#2563eb;"></div>
                                                    <div class="tp-line w50"></div>
                                                </div>
                                            </div>
                                            <div class="tp-banner" style="background:#2563eb; height:14px; border-radius:2px; margin-bottom:5px; padding:2px 4px;">
                                                <div class="tp-line w30" style="background:rgba(255,255,255,.8);"></div>
                                            </div>
                                            <div class="tp-customer-card" style="border-left:2px solid #2563eb; background:#f8fafc; padding:2px 3px; margin-bottom:4px;">
                                                <div class="tp-line w50 bold"></div>
                                                <div class="tp-line w40 mt2"></div>
                                            </div>
                                            <div class="tp-table">
                                                <div class="tp-thead" style="background:#2563eb;"></div>
                                                <div class="tp-trow" style="border-bottom:1px solid #e2e8f0;"></div>
                                                <div class="tp-trow alt" style="border-bottom:1px solid #e2e8f0; background:#f8fafc;"></div>
                                                <div class="tp-trow" style="border-bottom:1px solid #e2e8f0;"></div>
                                            </div>
                                            <div class="tp-totals-right">
                                                <div class="tp-line w60"></div>
                                                <div class="tp-line w60 mt2" style="background:#2563eb;"></div>
                                            </div>
                                        </div>
                                        <div class="tpl-name">Modern Blue</div>
                                    </div>

                                    <!-- Classic Green -->
                                    <div class="tpl-card <?= $pdfTemplate === 'classic_green' ? 'selected' : '' ?>" data-tpl="classic_green" title="Classic Green">
                                        <div class="tpl-preview tpl-classic-green">
                                            <div class="tp-header" style="border-bottom:2px double #16a34a; padding-bottom:4px;">
                                                <div class="tp-logo-box"></div>
                                                <div class="tp-company-lines">
                                                    <div class="tp-line w70 darker" style="background:#15803d;"></div>
                                                    <div class="tp-line w50"></div>
                                                </div>
                                            </div>
                                            <div style="text-align:center; margin:4px 0; background:#f0fdf4; padding:3px; border-top:1px solid #16a34a; border-bottom:1px solid #16a34a;">
                                                <div class="tp-line w40 bold" style="background:#15803d; margin:0 auto;"></div>
                                                <div class="tp-line w30 mt2" style="margin:0 auto;"></div>
                                            </div>
                                            <div style="display:flex; gap:3px; margin-bottom:4px;">
                                                <div style="flex:1; border:1px solid #d1d5db; padding:2px 3px;">
                                                    <div class="tp-line w40" style="background:#16a34a; margin-bottom:2px;"></div>
                                                    <div class="tp-line w60 bold"></div>
                                                    <div class="tp-line w50 mt2"></div>
                                                </div>
                                                <div style="flex:1; border:1px solid #d1d5db; padding:2px 3px;">
                                                    <div class="tp-line w40" style="background:#16a34a; margin-bottom:2px;"></div>
                                                    <div class="tp-line w60"></div>
                                                    <div class="tp-line w50 mt2"></div>
                                                </div>
                                            </div>
                                            <div class="tp-table">
                                                <div class="tp-thead" style="background:#16a34a; border:1px solid #15803d;"></div>
                                                <div class="tp-trow" style="border:1px solid #d1d5db;"></div>
                                                <div class="tp-trow alt" style="border:1px solid #d1d5db; background:#f9fafb;"></div>
                                            </div>
                                        </div>
                                        <div class="tpl-name">Classic Green</div>
                                    </div>

                                    <!-- Professional Gray -->
                                    <div class="tpl-card <?= $pdfTemplate === 'professional_gray' ? 'selected' : '' ?>" data-tpl="professional_gray" title="Professional Gray">
                                        <div class="tpl-preview tpl-pro-gray">
                                            <div style="background:#1f2937; padding:4px 5px; margin:-4px -4px 5px -4px; display:flex; justify-content:space-between; align-items:center;">
                                                <div class="tp-logo-box" style="background:rgba(255,255,255,.3); width:22px; height:12px;"></div>
                                                <div style="text-align:right;">
                                                    <div class="tp-line w35" style="background:rgba(255,255,255,.9);"></div>
                                                    <div class="tp-line w25 mt2" style="background:rgba(255,255,255,.5);"></div>
                                                </div>
                                            </div>
                                            <div style="border-bottom:2px solid #e5e7eb; padding-bottom:4px; margin-bottom:4px;">
                                                <div class="tp-line w40 bold" style="font-size:8px; background:#374151;"></div>
                                                <div class="tp-line w55 mt2"></div>
                                            </div>
                                            <div style="display:flex; gap:3px; margin-bottom:4px;">
                                                <div style="flex:1;">
                                                    <div class="tp-line w30" style="background:#6b7280; margin-bottom:2px;"></div>
                                                    <div class="tp-line w60 bold"></div>
                                                    <div class="tp-line w45 mt2"></div>
                                                </div>
                                                <div style="flex:1;">
                                                    <div class="tp-line w30" style="background:#6b7280; margin-bottom:2px;"></div>
                                                    <div class="tp-line w60 bold"></div>
                                                </div>
                                            </div>
                                            <div class="tp-table">
                                                <div class="tp-thead" style="background:#f3f4f6; border-bottom:2px solid #9ca3af;"></div>
                                                <div class="tp-trow" style="border-bottom:1px solid #e5e7eb;"></div>
                                                <div class="tp-trow" style="border-bottom:1px solid #e5e7eb;"></div>
                                            </div>
                                        </div>
                                        <div class="tpl-name">Professional Gray</div>
                                    </div>

                                    <!-- Bold Red -->
                                    <div class="tpl-card <?= $pdfTemplate === 'bold_red' ? 'selected' : '' ?>" data-tpl="bold_red" title="Bold Red">
                                        <div class="tpl-preview tpl-bold-red">
                                            <div style="background:#dc2626; padding:5px; margin:-4px -4px 5px -4px; display:flex; justify-content:space-between; align-items:center;">
                                                <div>
                                                    <div class="tp-line w25" style="background:rgba(255,255,255,.9);"></div>
                                                    <div class="tp-line w18 mt2" style="background:rgba(255,255,255,.6);"></div>
                                                </div>
                                                <div style="text-align:right;">
                                                    <div class="tp-line w35" style="background:rgba(255,255,255,.95);"></div>
                                                    <div class="tp-line w25 mt2" style="background:rgba(255,255,255,.6);"></div>
                                                </div>
                                            </div>
                                            <div style="background:#fef2f2; border-left:3px solid #dc2626; padding:3px 4px; margin-bottom:4px;">
                                                <div class="tp-line w50 bold"></div>
                                                <div class="tp-line w40 mt2"></div>
                                            </div>
                                            <div class="tp-table">
                                                <div class="tp-thead" style="background:#dc2626;"></div>
                                                <div class="tp-trow" style="border-bottom:1px solid #fecaca;"></div>
                                                <div class="tp-trow alt" style="border-bottom:1px solid #fecaca; background:#fef2f2;"></div>
                                                <div class="tp-trow" style="border-bottom:1px solid #fecaca;"></div>
                                            </div>
                                            <div class="tp-totals-right">
                                                <div class="tp-line w60"></div>
                                                <div class="tp-line w60 mt2" style="background:#dc2626;"></div>
                                            </div>
                                        </div>
                                        <div class="tpl-name">Bold Red</div>
                                    </div>

                                    <!-- Elegant Purple -->
                                    <div class="tpl-card <?= $pdfTemplate === 'elegant_purple' ? 'selected' : '' ?>" data-tpl="elegant_purple" title="Elegant Purple">
                                        <div class="tpl-preview tpl-elegant-purple">
                                            <div style="padding-bottom:5px; margin-bottom:5px; border-bottom:3px solid #7c3aed; display:flex; justify-content:space-between; align-items:flex-start;">
                                                <div class="tp-logo-box"></div>
                                                <div class="tp-company-lines">
                                                    <div class="tp-line w70" style="background:#6d28d9;"></div>
                                                    <div class="tp-line w50 mt2"></div>
                                                </div>
                                            </div>
                                            <div style="text-align:center; margin-bottom:5px;">
                                                <div class="tp-line w40" style="background:#6d28d9; margin:0 auto; height:2.5px;"></div>
                                                <div class="tp-line w35 mt2" style="margin:0 auto;"></div>
                                            </div>
                                            <div style="display:flex; gap:3px; margin-bottom:4px;">
                                                <div style="flex:1; border:1px solid #e9d5ff; border-radius:3px; padding:2px 3px; background:linear-gradient(135deg,#faf5ff,#f5f3ff);">
                                                    <div class="tp-line w60 bold"></div>
                                                    <div class="tp-line w45 mt2"></div>
                                                </div>
                                                <div style="flex:1; border:1px solid #e9d5ff; border-radius:3px; padding:2px 3px; background:linear-gradient(135deg,#faf5ff,#f5f3ff);">
                                                    <div class="tp-line w60 bold"></div>
                                                    <div class="tp-line w45 mt2"></div>
                                                </div>
                                            </div>
                                            <div class="tp-table">
                                                <div class="tp-thead" style="background:#7c3aed; border-radius:3px 3px 0 0;"></div>
                                                <div class="tp-trow" style="border-bottom:1px solid #ede9fe;"></div>
                                                <div class="tp-trow alt" style="border-bottom:1px solid #ede9fe; background:#faf5ff;"></div>
                                                <div class="tp-trow" style="border-bottom:1px solid #ede9fe;"></div>
                                            </div>
                                        </div>
                                        <div class="tpl-name">Elegant Purple</div>
                                    </div>

                                </div><!-- /.pdf-template-grid -->
                            </div>
                            <div class="col-md-3">
                                <label class="compact-label">Default Sales Currency</label>
                                <select name="default_sales_currency" class="form-select form-select-sm">
                                    <?php $salesCurr = $company['default_sales_currency'] ?? ($company['base_currency'] ?? 'USD'); $currList = $currencies ?? []; ?>
                                    <?php foreach ($currList as $cur): ?>
                                        <option value="<?= esc($cur['code']) ?>" <?= ($salesCurr === ($cur['code'] ?? '')) ? 'selected' : '' ?>><?= esc($cur['code']) ?></option>
                                    <?php endforeach; ?>
                                    <?php if (empty($currList)): ?><option value="<?= esc($salesCurr) ?>" selected><?= esc($salesCurr) ?></option><?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="compact-label">Default Purchase Currency</label>
                                <select name="default_purchase_currency" class="form-select form-select-sm">
                                    <?php $purchaseCurr = $company['default_purchase_currency'] ?? ($company['base_currency'] ?? 'USD'); ?>
                                    <?php foreach ($currList as $cur): ?>
                                        <option value="<?= esc($cur['code']) ?>" <?= ($purchaseCurr === ($cur['code'] ?? '')) ? 'selected' : '' ?>><?= esc($cur['code']) ?></option>
                                    <?php endforeach; ?>
                                    <?php if (empty($currList)): ?><option value="<?= esc($purchaseCurr) ?>" selected><?= esc($purchaseCurr) ?></option><?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="compact-label">Logo</label>
                                <input type="file" class="form-control form-control-sm" name="logo" accept="image/png,image/jpeg,image/webp">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <?php if (!empty($company['logo_path'])): ?>
                                    <img src="<?= base_url($company['logo_path']) ?>" alt="Logo" style="height:32px; object-fit:contain; border:1px solid rgba(255,255,255,.1); padding:2px; border-radius:4px;">
                                <?php endif; ?>
                            </div>
                            <div class="col-12 mt-3">
                                <label class="compact-label fw-bold"><i class="bi bi-file-pdf me-1"></i>PDF Visibility Controls</label>
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless align-middle mb-0" style="font-size: .8rem;">
                                        <thead>
                                            <tr class="text-muted" style="border-bottom: 1px solid rgba(0,0,0,.05);">
                                                <th>Document Type</th>
                                                <th class="text-center">Show Header Address</th>
                                                <th class="text-center">Show Footer</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                                $docs = [
                                                    ['key' => 'inv', 'label' => 'Customer Invoice'],
                                                    ['key' => 'quote', 'label' => 'Quotation'],
                                                    ['key' => 'so', 'label' => 'Sales Order'],
                                                    ['key' => 'po', 'label' => 'Purchase Order'],
                                                    ['key' => 'rfq', 'label' => 'Request for Quotation (RFQ)'],
                                                ];
                                                foreach($docs as $d):
                                                    $hKey = "pdf_{$d['key']}_show_header";
                                                    $fKey = "pdf_{$d['key']}_show_footer";
                                                    $hVal = !isset($company[$hKey]) || $company[$hKey];
                                                    $fVal = !isset($company[$fKey]) || $company[$fKey];
                                            ?>
                                            <tr>
                                                <td class="fw-semibold"><?= $d['label'] ?></td>
                                                <td class="text-center">
                                                    <div class="form-check form-switch d-inline-block">
                                                        <input class="form-check-input" type="checkbox" name="<?= $hKey ?>" value="1" <?= $hVal ? 'checked' : '' ?>>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check form-switch d-inline-block">
                                                        <input class="form-check-input" type="checkbox" name="<?= $fKey ?>" value="1" <?= $fVal ? 'checked' : '' ?>>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch mt-3">
                                    <input class="form-check-input" type="checkbox" id="use_demo_data" name="use_demo_data" value="1" <?= !empty($company['use_demo_data']) ? 'checked' : '' ?>>
                                    <label class="form-check-label small text-muted" for="use_demo_data">Use demo data on dashboard</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="st-form-footer">
                                    <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    </div>
                </div>
            </div>

            <!-- ─── NUMBERING ─── -->
            <div class="settings-section" id="section-numbering">
                <div class="settings-card">
                    <div class="settings-card-header"><i class="bi bi-hash"></i>Sales Document Numbering</div>
                    <div class="settings-card-body">
                    <p class="small mb-3" style="color:var(--gray-500,#64748b)">Prefix codes used for quotation and sales order numbers (e.g., <code>RI</code>-Q0001).</p>
                    <form method="post" action="<?= site_url('settings/saveCompany') ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <!-- hidden fields to preserve company data when this sub-form saves -->
                        <input type="hidden" name="name" value="<?= esc($company['name'] ?? '') ?>">
                        <input type="hidden" name="address" value="<?= esc($company['address'] ?? '') ?>">
                        <input type="hidden" name="contact" value="<?= esc($company['contact'] ?? '') ?>">
                        <input type="hidden" name="email" value="<?= esc($company['email'] ?? '') ?>">
                        <input type="hidden" name="use_demo_data" value="<?= $company['use_demo_data'] ?? 0 ?>">
                        <input type="hidden" name="art_number_prefix" value="<?= $artBrand ?>">
                        <input type="hidden" name="art_number_next" value="<?= $artNext ?>">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="compact-label">Quotation Prefix</label>
                                <input type="text" class="form-control form-control-sm text-uppercase" name="quotation_prefix" value="<?= esc($company['quotation_prefix'] ?? 'RI') ?>" placeholder="RI">
                            </div>
                            <div class="col-md-4">
                                <label class="compact-label">Sales Order Prefix</label>
                                <input type="text" class="form-control form-control-sm text-uppercase" name="sales_order_prefix" value="<?= esc($company['sales_order_prefix'] ?? 'RI') ?>" placeholder="RI">
                            </div>
                            <div class="col-12">
                                <div class="st-form-footer">
                                    <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    </div>
                </div>
            </div>

            <!-- ─── ART NUMBERS ─── -->
            <div class="settings-section" id="section-art-numbers">
                <div class="settings-card">
                    <div class="settings-card-header"><i class="bi bi-upc-scan"></i>Product Art Number Configuration</div>
                    <div class="settings-card-body">
                    <p class="small mb-3" style="color:var(--gray-500,#64748b)">Controls the format and sequence of art numbers generated across all product categories.</p>

                    <!-- Live Preview -->
                    <div class="art-preview-box mb-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-muted" style="font-size:.7rem; text-transform:uppercase; letter-spacing:1px;">Next Art Number Preview</div>
                                <div class="font-monospace fw-bold mt-1" style="font-size:1.4rem; letter-spacing:3px;" id="artPreviewSettings"><?= $artBrand ?>-[CODE]-<?= $artPad ?></div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary px-2 py-1" id="brandBadge" style="font-size:.75rem;"><?= $artBrand ?></span>
                                <span class="text-muted small">-</span>
                                <span class="badge bg-success px-2 py-1" style="font-size:.75rem;">CODE</span>
                                <span class="text-muted small">-</span>
                                <span class="badge bg-secondary px-2 py-1" id="seqBadge" style="font-size:.75rem;"><?= $artPad ?></span>
                            </div>
                        </div>
                    </div>

                    <form method="post" action="<?= site_url('settings/saveCompany') ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="name" value="<?= esc($company['name'] ?? '') ?>">
                        <input type="hidden" name="address" value="<?= esc($company['address'] ?? '') ?>">
                        <input type="hidden" name="contact" value="<?= esc($company['contact'] ?? '') ?>">
                        <input type="hidden" name="email" value="<?= esc($company['email'] ?? '') ?>">
                        <input type="hidden" name="use_demo_data" value="<?= $company['use_demo_data'] ?? 0 ?>">
                        <input type="hidden" name="quotation_prefix" value="<?= esc($company['quotation_prefix'] ?? 'RI') ?>">
                        <input type="hidden" name="sales_order_prefix" value="<?= esc($company['sales_order_prefix'] ?? 'RI') ?>">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="compact-label">Brand Code</label>
                                <input type="text" class="form-control form-control-sm text-uppercase fw-bold" name="art_number_prefix" id="artBrandInput"
                                       value="<?= $artBrand ?>" placeholder="RI" maxlength="10" style="letter-spacing:2px;">
                                <div class="text-muted" style="font-size:.7rem; margin-top:2px;">Company identifier at the start of every art number.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="compact-label">Next Sequence Number</label>
                                <input type="number" class="form-control form-control-sm fw-bold" name="art_number_next" id="artNextInput"
                                       value="<?= $artNext ?>" min="1" max="99999999">
                                <div class="text-muted" style="font-size:.7rem; margin-top:2px;">
                                    <i class="bi bi-info-circle me-1"></i>Min: <?= $artNext ?> (current). Cannot go backwards.
                                </div>
                            </div>

                            <div class="col-12"><hr style="border-color:var(--gray-200,#e2e8f0);margin:.5rem 0 .2rem"></div>

                            <div class="col-md-4">
                                <label class="compact-label">Customer Code Prefix</label>
                                <input type="text" class="form-control form-control-sm text-uppercase fw-bold" name="customer_code_prefix" id="custPrefixInput"
                                       value="<?= $custPrefix ?>" placeholder="RI" maxlength="20" style="letter-spacing:1px;">
                                <div class="text-muted" style="font-size:.7rem; margin-top:2px;">Prefix used for customer codes (e.g. RI-528).</div>
                            </div>
                            <div class="col-md-4">
                                <label class="compact-label">Customer Next Number</label>
                                <input type="number" class="form-control form-control-sm fw-bold" name="customer_code_next" id="custNextInput"
                                       value="<?= $custNext ?>" min="1" max="99999999">
                                <div class="text-muted" style="font-size:.7rem; margin-top:2px;">
                                    <i class="bi bi-info-circle me-1"></i>Min: <?= $custNext ?> (current). Cannot go backwards.
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="compact-label">Customer Code Preview</label>
                                <div class="form-control form-control-sm fw-bold font-monospace" id="custPreviewSettings" style="letter-spacing:1px;"><?= $custPrefix ?>-<?= $custNext ?></div>
                                <div class="text-muted" style="font-size:.7rem; margin-top:2px;">Next created customer will use this code.</div>
                            </div>

                            <div class="col-12"><hr style="border-color:var(--gray-200,#e2e8f0);margin:.5rem 0 .2rem"></div>

                            <div class="col-md-4">
                                <label class="compact-label">Vendor Code Prefix</label>
                                <input type="text" class="form-control form-control-sm text-uppercase fw-bold" name="vendor_code_prefix" id="vendorPrefixInput"
                                       value="<?= $vendorPrefix ?>" placeholder="VEN" maxlength="20" style="letter-spacing:1px;">
                                <div class="text-muted" style="font-size:.7rem; margin-top:2px;">Prefix used for vendor codes (e.g. VEN-102).</div>
                            </div>
                            <div class="col-md-4">
                                <label class="compact-label">Vendor Next Number</label>
                                <input type="number" class="form-control form-control-sm fw-bold" name="vendor_code_next" id="vendorNextInput"
                                       value="<?= $vendorNext ?>" min="1" max="99999999">
                                <div class="text-muted" style="font-size:.7rem; margin-top:2px;">
                                    <i class="bi bi-info-circle me-1"></i>Min: <?= $vendorNext ?> (current). Cannot go backwards.
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="compact-label">Vendor Code Preview</label>
                                <div class="form-control form-control-sm fw-bold font-monospace" id="vendorPreviewSettings" style="letter-spacing:1px;"><?= $vendorPrefix ?>-<?= $vendorNext ?></div>
                                <div class="text-muted" style="font-size:.7rem; margin-top:2px;">Next created vendor will use this code.</div>
                            </div>

                            <div class="col-12">
                                <div class="st-form-footer">
                                    <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="mt-2 small border-top pt-2" style="line-height:1.6;color:var(--gray-400,#94a3b8)">
                        <strong>Format:</strong> <code>[BRAND]-[CATEGORY CODE]-[SEQ]</code>
                        &nbsp;|&nbsp; <strong>Brand Code</strong> = global company prefix (shared by all products)
                        &nbsp;|&nbsp; <strong>Category Code</strong> = set per category (2-4 letters)
                        &nbsp;|&nbsp; <strong>Sequence</strong> = auto-incrementing global counter
                    </div>
                    </div>
                </div>
            </div>

            <!-- ─── FISCAL YEAR ─── -->
            <div class="settings-section" id="section-fiscal">
                <div class="settings-card">
                    <div class="settings-card-header"><i class="bi bi-calendar-range"></i>Fiscal Year</div>
                    <div class="settings-card-body">
                    <form method="post" action="<?= site_url('settings/saveFiscalYear') ?>">
                        <?= csrf_field() ?>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="compact-label">Start Date</label>
                                <input type="date" class="form-control form-control-sm" name="start_date" value="<?= esc($fy['start_date'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="compact-label">End Date</label>
                                <input type="date" class="form-control form-control-sm" name="end_date" value="<?= esc($fy['end_date'] ?? '') ?>" required>
                            </div>
                            <div class="col-12">
                                <div class="st-form-footer">
                                    <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    </div>
                </div>
            </div>

            <!-- ─── SECURITY ─── -->
            <div class="settings-section" id="section-security">
                <div class="settings-card">
                    <div class="settings-card-header"><i class="bi bi-shield-lock"></i>Security</div>
                    <div class="settings-card-body">
                    <form method="post" action="<?= site_url('settings/saveSecurity') ?>">
                        <?= csrf_field() ?>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label class="compact-label">Back-date Password</label>
                                <input type="password" class="form-control form-control-sm" name="backdate_password" placeholder="Set a password for back-dating" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-center">
                                <?php if(!empty($security['backdate_password_hash'])): ?>
                                    <span class="text-success small"><i class="bi bi-check-circle me-1"></i>Password is set</span>
                                <?php else: ?>
                                    <span class="text-warning small"><i class="bi bi-exclamation-circle me-1"></i>No password set</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-12">
                                <div class="st-form-footer">
                                    <button class="btn btn-sm btn-primary"><i class="bi bi-shield-check me-1"></i>Save</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    </div>
                </div>

                <?php
                    $flags = $feature_flags ?? [];
                    $securityFlagDefs = [
                        'enable_2fa' => [
                            'label' => 'Enforce Two-Factor Authentication',
                            'hint'  => 'When enabled, users without MFA are redirected to MFA setup after login.',
                        ],
                        'force_https' => [
                            'label' => 'Force HTTPS Redirect',
                            'hint'  => 'Redirect all HTTP requests to HTTPS. Enable only when SSL is configured.',
                        ],
                        'enable_auth_logging' => [
                            'label' => 'Authentication Logging',
                            'hint'  => 'Write login success/failure, logout, and MFA events into auth logs.',
                        ],
                        'enable_public_ids' => [
                            'label' => 'Public IDs in URLs',
                            'hint'  => 'Use UUID-style public IDs in links instead of numeric IDs.',
                        ],
                        'enable_tenant_isolation' => [
                            'label' => 'Tenant Data Isolation',
                            'hint'  => 'Apply tenant-level data scoping for supported models.',
                        ],
                    ];
                ?>

                <div class="settings-card mt-3">
                    <div class="settings-card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-toggles"></i>Security Feature Toggles</span>
                        <a href="<?= base_url('admin/security') ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Advanced Security Page
                        </a>
                    </div>
                    <div class="settings-card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:32%">Feature</th>
                                        <th>Description</th>
                                        <th class="text-end" style="width:110px">On/Off</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($securityFlagDefs as $flagKey => $meta): ?>
                                        <?php $isEnabled = !empty($flags[$flagKey]['enabled']); ?>
                                        <tr>
                                            <td class="fw-semibold"><?= esc($meta['label']) ?></td>
                                            <td class="small text-muted"><?= esc($meta['hint']) ?></td>
                                            <td class="text-end">
                                                <div class="form-check form-switch d-inline-block mb-0">
                                                    <input class="form-check-input security-flag-toggle" type="checkbox" role="switch"
                                                        data-flag="<?= esc($flagKey) ?>" <?= $isEnabled ? 'checked' : '' ?>>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="small text-muted mt-2">
                            CSRF toggle intentionally hidden from quick panel. Enable CSRF only after verifying all forms have tokens.
                        </div>
                    </div>
                </div>

                <div class="settings-card mt-3">
                    <div class="settings-card-header"><i class="bi bi-hdd-network"></i>System Network Access</div>
                    <div class="settings-card-body">
                    <form method="post" action="<?= site_url('settings/saveNetwork') ?>">
                        <?= csrf_field() ?>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="compact-label">Local IP Address</label>
                                <input type="text" class="form-control form-control-sm font-monospace fw-bold" name="network_ip" value="<?= esc($current_ip ?? '') ?>" placeholder="192.168.1.x">
                            </div>
                            <div class="col-md-8 d-flex align-items-center pb-1">
                                <?php if(!empty($current_ip)): ?>
                                    <span class="text-success small fw-semibold" style="letter-spacing:0.3px;"><i class="bi bi-broadcast me-1"></i>Now active at: http://<?= esc($current_ip) ?>/corelynk/</span>
                                <?php else: ?>
                                    <span class="text-secondary small"><i class="bi bi-pc-display me-1"></i>Currently local only (localhost)</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 mt-1">
                                <div class="text-muted" style="font-size: .75rem;">Enter your PC's Wi-Fi IPv4 address here to permit access to this system from other devices on your network. (Leave blank to revert to localhost default).</div>
                            </div>
                            <div class="col-12 mt-2">
                                <div class="st-form-footer">
                                    <button class="btn btn-sm btn-primary"><i class="bi bi-link me-1"></i>Update Access</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    </div>
                </div>

                <div class="settings-card mt-3">
                    <div class="settings-card-header"><i class="bi bi-calendar3"></i>Global Date Format</div>
                    <div class="settings-card-body">
                    <form method="post" action="<?= site_url('settings/saveDateFormat') ?>">
                        <?= csrf_field() ?>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="compact-label">Date Format</label>
                                <select class="form-select form-select-sm" name="global_date_format" required>
                                    <?php $gdf = (string)($global_date_format ?? 'Y-m-d'); ?>
                                    <option value="Y-m-d" <?= $gdf === 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD</option>
                                    <option value="d-m-Y" <?= $gdf === 'd-m-Y' ? 'selected' : '' ?>>DD-MM-YYYY</option>
                                    <option value="d/m/Y" <?= $gdf === 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY</option>
                                    <option value="m/d/Y" <?= $gdf === 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <div class="text-muted" style="font-size:.75rem;">This controls how dates are displayed in Corelynk screens that use the global formatter.</div>
                            </div>
                            <div class="col-12 mt-2">
                                <div class="st-form-footer">
                                    <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save Date Format</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    </div>
                </div>
            </div>

            <!-- ─── PAYMENT METHODS ─── -->
            <div class="settings-section" id="section-payments">
                <div class="settings-card">
                    <div class="settings-card-header"><i class="bi bi-credit-card"></i>Payment Methods</div>
                    <div class="settings-card-body">
                    <form class="row g-2 align-items-end mb-3" method="post" action="<?= site_url('settings/addPaymentMethod') ?>">
                        <?= csrf_field() ?>
                        <div class="col">
                            <input type="text" class="form-control form-control-sm" name="method_name" placeholder="New method name (e.g., Cheque)" required>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-plus me-1"></i>Add</button>
                        </div>
                    </form>
                    <div class="d-flex flex-wrap gap-2 mt-1">
                        <?php foreach(($methods ?? []) as $m): ?>
                            <span class="badge <?= $m['is_active'] ? 'bg-primary bg-opacity-10 text-primary' : 'bg-secondary bg-opacity-10 text-secondary' ?> px-3 py-2" style="font-size:.75rem;border:1px solid rgba(99,102,241,.15)">
                                <?= esc($m['method_name']) ?>
                                <?php if($m['is_active']): ?><i class="bi bi-check-circle-fill ms-1"></i><?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                        <?php if (empty($methods)): ?>
                            <span style="color:var(--gray-400,#94a3b8);font-size:.8rem">No payment methods defined.</span>
                        <?php endif; ?>
                    </div>
                    </div>
                </div>
            </div>

            <!-- ─── CURRENCIES ─── -->
            <div class="settings-section" id="section-currencies">
                <div class="settings-card">
                    <div class="settings-card-header"><i class="bi bi-coin"></i>Currencies</div>
                    <div class="settings-card-body">
                    <form class="row g-2 align-items-end mb-3" method="post" action="<?= site_url('settings/addCurrency') ?>">
                        <?= csrf_field() ?>
                        <div class="col-md-2">
                            <label class="compact-label">Code</label>
                            <input type="text" name="code" class="form-control form-control-sm" placeholder="USD" required>
                        </div>
                        <div class="col-md-4">
                            <label class="compact-label">Name</label>
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="US Dollar">
                        </div>
                        <div class="col-md-2">
                            <label class="compact-label">Symbol</label>
                            <input type="text" name="symbol" class="form-control form-control-sm" placeholder="$">
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-plus me-1"></i>Add</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" style="font-size:.85rem;">
                            <thead><tr><th>Code</th><th>Name</th><th>Symbol</th><th class="text-end">Active</th></tr></thead>
                            <tbody>
                            <?php foreach (($currencies ?? []) as $c): ?>
                                <tr>
                                    <td class="fw-semibold"><?= esc($c['code']) ?></td>
                                    <td><?= esc($c['name']) ?></td>
                                    <td><?= esc($c['symbol'] ?? '') ?></td>
                                    <td class="text-end">
                                        <div class="form-check form-switch d-inline">
                                            <input class="form-check-input currency-toggle" data-code="<?= esc($c['code']) ?>" type="checkbox" <?= (!isset($c['is_active']) || $c['is_active']) ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($currencies)): ?>
                                <tr><td colspan="4" class="text-muted">No currencies defined.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    </div>
                </div>
            </div>

            <!-- ─── EXCHANGE RATES ─── -->
            <div class="settings-section" id="section-exchange">
                <div class="settings-card">
                    <div class="settings-card-header">
                        <i class="bi bi-arrow-left-right"></i>Exchange Rates (USD → PKR)
                        <span class="ms-auto badge bg-primary bg-opacity-10 text-primary" style="font-size:.75rem;">
                            Active: <?= isset($activeRate['rate']) ? number_format($activeRate['rate'], 4) : 'N/A' ?>
                        </span>
                    </div>
                    <div class="settings-card-body">
                    <form class="row g-2 align-items-end mb-3" method="post" action="<?= site_url('settings/addExchangeRate') ?>">
                        <?= csrf_field() ?>
                        <div class="col-md-3">
                            <label class="compact-label">Rate</label>
                            <input type="number" step="0.0001" class="form-control form-control-sm" name="rate" required>
                        </div>
                        <div class="col-md-3">
                            <label class="compact-label">Effective Date</label>
                            <input type="date" class="form-control form-control-sm" name="as_of" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-sm btn-primary"><i class="bi bi-plus me-1"></i>Add Rate</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" style="font-size:.85rem;">
                            <thead><tr><th>Base</th><th>Quote</th><th>Rate</th><th>As Of</th></tr></thead>
                            <tbody>
                            <?php foreach(($rates ?? []) as $r): ?>
                                <tr>
                                    <td><?= esc($r['base_code']) ?></td>
                                    <td><?= esc($r['quote_code']) ?></td>
                                    <td class="fw-semibold"><?= number_format($r['rate'], 4) ?></td>
                                    <td><?= esc($r['as_of']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($rates)): ?>
                                <tr><td colspan="4" class="text-muted">No rates recorded.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2 small" style="color:var(--gray-400,#94a3b8)"><i class="bi bi-info-circle me-1"></i>Historical tracking ensures old transactions stay at their original rate.</div>
                    </div>
                </div>
            </div>

            <!-- ─── ODOO ─── -->
            <div class="settings-section" id="section-odoo">
                <div class="settings-card">
                    <div class="settings-card-header"><i class="bi bi-box-seam"></i>Odoo Integration</div>
                    <div class="settings-card-body">
                    <form method="post" action="<?= site_url('settings/saveOdoo') ?>" id="odooForm">
                        <?= csrf_field() ?>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="compact-label">Host</label>
                                <input type="text" class="form-control form-control-sm" name="host" value="<?= esc($odoo['host'] ?? '') ?>" placeholder="http://odoo.local">
                            </div>
                            <div class="col-md-1">
                                <label class="compact-label">Port</label>
                                <input type="number" class="form-control form-control-sm" name="port" value="<?= esc($odoo['port'] ?? '') ?>" placeholder="8069">
                            </div>
                            <div class="col-md-2">
                                <label class="compact-label">Database</label>
                                <input type="text" class="form-control form-control-sm" name="db_name" value="<?= esc($odoo['db_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="compact-label">Username</label>
                                <input type="text" class="form-control form-control-sm" name="username" value="<?= esc($odoo['username'] ?? '') ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="compact-label">Password</label>
                                <input type="password" class="form-control form-control-sm" name="password" value="<?= esc($odoo['password'] ?? '') ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-1">
                                <button type="button" id="odooTest" class="btn btn-sm btn-outline-secondary flex-fill">Test</button>
                                <button class="btn btn-sm btn-primary flex-fill">Save</button>
                            </div>
                        </div>
                    </form>
                    <div id="odooTestResult" class="mt-2"></div>

                    <hr class="my-2 opacity-10">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="compact-label">Fetch Mode</label>
                            <select class="form-select form-select-sm" name="job_mode">
                                <option value="disabled" <?= (isset($odoo['job_mode']) && $odoo['job_mode']=='disabled')? 'selected':'' ?>>Disabled</option>
                                <option value="manual" <?= (empty($odoo['job_mode']) || $odoo['job_mode']=='manual')? 'selected':'' ?>>Manual</option>
                                <option value="cron" <?= (isset($odoo['job_mode']) && $odoo['job_mode']=='cron')? 'selected':'' ?>>Scheduled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="compact-label">Interval (sec)</label>
                            <input type="number" class="form-control form-control-sm" name="job_interval" value="<?= esc($odoo['job_interval'] ?? 30) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="compact-label">Fetch Limit</label>
                            <input type="number" class="form-control form-control-sm" name="fetch_limit" value="<?= esc($odoo['fetch_limit'] ?? 10) ?>">
                        </div>
                        <div class="col-md-3">
                            <button id="manualRefresh" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-arrow-clockwise me-1"></i>Reload From Odoo</button>
                        </div>
                    </div>
                    <div id="manualRefreshResult" class="mt-2"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

    <!-- Clean DB Modal -->
    <div class="modal fade" id="cleanDbModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-trash3 me-2"></i>Clean Database</h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="<?= site_url('settings/cleanDatabase') ?>">
                    <?= csrf_field() ?>
                    <div class="modal-body">
                        <div class="alert alert-warning py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i>This will permanently delete selected module data. This action cannot be undone.</div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="cleanSelectAll">
                            <label class="form-check-label small fw-semibold" for="cleanSelectAll">Select All</label>
                        </div>
                        <div class="row g-1">
                            <?php
                            $cleanModules = [
                                'po' => 'Purchase Orders', 'rfq' => 'RFQ', 'sales_orders' => 'Sales Orders',
                                'quotations' => 'Quotations', 'invoices' => 'Invoices', 'accounting_journals' => 'Journals',
                                'accounting_cheques' => 'Cheques', 'products' => 'Products', 'grn' => 'GRN',
                                'delivery_orders' => 'Delivery Orders', 'shipped_dos' => 'Shipped DOs', 'ready_to_ship' => 'Ready to Ship',
                            ];
                            foreach ($cleanModules as $val => $label): ?>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input clean-module" type="checkbox" name="modules[]" value="<?= $val ?>" id="clean-<?= $val ?>">
                                    <label class="form-check-label small" for="clean-<?= $val ?>"><?= $label ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3">
                            <label class="compact-label">Confirm Password</label>
                            <input type="password" class="form-control form-control-sm" name="clean_password" placeholder="Enter password" required>
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash3 me-1"></i>Clean Selected</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

            <!-- ─── MOBILE APP ─── -->
            <div class="settings-section" id="section-mobile">
                <div class="settings-card">
                    <div class="settings-card-header"><i class="bi bi-phone"></i>Mobile App Server</div>
                    <div class="settings-card-body">
                        <form method="post" action="<?= site_url('settings/saveMobileSettings') ?>">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="compact-label">API Server URL</label>
                                <input type="url" name="mobile_api_url" class="form-control form-control-sm"
                                       value="<?= esc($mobile_api_url ?? '') ?>"
                                       placeholder="http://192.168.1.100/corelynk/public/api">
                                <small class="text-muted">The base URL the mobile app will connect to. Leave empty to use the device default.</small>
                            </div>
                            <div class="p-2 bg-light rounded border mb-3 small">
                                <strong>Current server base URL:</strong> <?= esc(base_url('api')) ?>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-floppy me-1"></i>Save</button>
                        </form>
                    </div>
                </div>
            </div>

</div>
</div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
// ─── Settings Navigation ───
(function(){
    var links = document.querySelectorAll('#settingsNav a[data-section]');
    var sections = document.querySelectorAll('.settings-section');
    links.forEach(function(link){
        link.addEventListener('click', function(e){
            e.preventDefault();
            links.forEach(function(l){ l.classList.remove('active'); });
            sections.forEach(function(s){ s.classList.remove('active'); });
            link.classList.add('active');
            var target = document.getElementById('section-' + link.dataset.section);
            if(target) target.classList.add('active');
            // scroll content to top smoothly
            var right = document.querySelector('.col-lg-10');
            if(right) right.scrollIntoView({ behavior:'smooth', block:'nearest' });
        });
    });
})();

// ─── Security Flag Toggles ───
(function(){
    var toggles = document.querySelectorAll('.security-flag-toggle');
    if(!toggles.length) return;

    toggles.forEach(function(toggle){
        toggle.addEventListener('change', async function(){
            var flagKey = this.getAttribute('data-flag');
            var enabled = this.checked ? '1' : '0';
            var original = this.checked;
            this.disabled = true;

            try {
                var resp = await fetch('<?= base_url('admin/security/toggle-flag') ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'flag_key=' + encodeURIComponent(flagKey)
                        + '&enabled=' + encodeURIComponent(enabled)
                        + '&<?= csrf_token() ?>=<?= csrf_hash() ?>'
                });

                var data = await resp.json();
                if(!resp.ok || !data.success){
                    this.checked = !original;
                    alert((data && data.message) ? data.message : 'Unable to update security flag.');
                }
            } catch (err) {
                this.checked = !original;
                alert('Network error while updating security flag.');
            } finally {
                this.disabled = false;
            }
        });
    });
})();

// ─── Art Number Live Preview ───
(function(){
    var brandIn = document.getElementById('artBrandInput');
    var nextIn  = document.getElementById('artNextInput');
    var custPrefixIn = document.getElementById('custPrefixInput');
    var custNextIn = document.getElementById('custNextInput');
    var custPreview = document.getElementById('custPreviewSettings');
    var vendorPrefixIn = document.getElementById('vendorPrefixInput');
    var vendorNextIn = document.getElementById('vendorNextInput');
    var vendorPreview = document.getElementById('vendorPreviewSettings');
    var preview = document.getElementById('artPreviewSettings');
    var brandB  = document.getElementById('brandBadge');
    var seqB    = document.getElementById('seqBadge');
    function upd(){
        var b = (brandIn && brandIn.value||'RI').toUpperCase().replace(/[^A-Z]/g,'');
        var n = parseInt(nextIn && nextIn.value)||1;
        var pad = String(n).padStart(5,'0');
        if(preview) preview.textContent = b+'-[CODE]-'+pad;
        if(brandB) brandB.textContent = b||'??';
        if(seqB)   seqB.textContent = pad;

        var cp = (custPrefixIn && custPrefixIn.value || 'RI').toUpperCase().replace(/[^A-Z0-9]/g,'');
        var cn = parseInt(custNextIn && custNextIn.value) || 1;
        if (custPreview) custPreview.textContent = (cp || 'RI') + '-' + cn;

        var vp = (vendorPrefixIn && vendorPrefixIn.value || 'VEN').toUpperCase().replace(/[^A-Z0-9]/g,'');
        var vn = parseInt(vendorNextIn && vendorNextIn.value) || 1;
        if (vendorPreview) vendorPreview.textContent = (vp || 'VEN') + '-' + vn;
    }
    if(brandIn){ brandIn.addEventListener('input',function(){ this.value=this.value.toUpperCase().replace(/[^A-Z]/g,''); upd(); }); }
    if(nextIn){ nextIn.addEventListener('input', upd); }
    if(custPrefixIn){ custPrefixIn.addEventListener('input', function(){ this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,''); upd(); }); }
    if(custNextIn){ custNextIn.addEventListener('input', upd); }
    if(vendorPrefixIn){ vendorPrefixIn.addEventListener('input', function(){ this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,''); upd(); }); }
    if(vendorNextIn){ vendorNextIn.addEventListener('input', upd); }
})();

// ─── Currency Toggle ───
Array.from(document.querySelectorAll('.currency-toggle')).forEach(function(cb){
    cb.addEventListener('change', function(){
        var code = this.dataset.code;
        fetch('<?= site_url('settings/toggleCurrency') ?>/' + encodeURIComponent(code), { method: 'POST', headers: { 'X-Requested-With':'XMLHttpRequest', '<?= csrf_token() ?>':'<?= csrf_hash() ?>' } })
            .then(function(r){ return r.json(); })
            .then(function(d){ if (!d.success) alert(d.message || 'Failed'); })
            .catch(function(err){ console.error(err); alert('Request failed'); });
    });
});

// ─── Odoo Test ───
document.getElementById('odooTest')?.addEventListener('click', function(){
    var btn = this; btn.disabled = true; btn.textContent = 'Testing...';
    var resultEl = document.getElementById('odooTestResult'); resultEl.innerHTML = '';
    var form = document.getElementById('odooForm');
    var data = new FormData(form);
    fetch('<?= site_url('settings/saveOdoo') ?>', { method: 'POST', body: data })
        .then(function(){ return fetch('<?= base_url('/integrations/odoo/api/test') ?>'); })
        .then(function(r){ return r.json(); })
        .then(function(d){
            btn.disabled = false; btn.textContent = 'Test';
            if(d && d.data && d.data.error) resultEl.innerHTML = '<div class="text-danger small">'+d.data.error+'</div>';
            else if(d && d.data && d.data.result) resultEl.innerHTML = '<div class="text-success small">Connected (uid: '+d.data.result+')</div>';
            else resultEl.innerHTML = '<div class="text-muted small">Unexpected response</div>';
        })
        .catch(function(err){ btn.disabled = false; btn.textContent = 'Test'; resultEl.innerHTML = '<div class="text-danger small">'+err.message+'</div>'; });
});

// ─── Odoo Manual Refresh ───
document.getElementById('manualRefresh')?.addEventListener('click', function(e){
    e.preventDefault();
    var btn = this; btn.disabled = true; btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1 spin"></i>Reloading...';
    fetch('<?= base_url('/integrations/odoo/screen/action/refresh') ?>',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({})})
        .then(function(r){ return r.json(); }).then(function(d){
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Reload From Odoo';
            var el = document.getElementById('manualRefreshResult');
            if(d && d.ok) el.innerHTML = '<div class="text-success small">Done. Last run: '+(d.last_run||'')+'</div>';
            else el.innerHTML = '<div class="text-danger small">Reload failed</div>';
        }).catch(function(err){ btn.disabled=false; btn.innerHTML='<i class="bi bi-arrow-clockwise me-1"></i>Reload From Odoo'; document.getElementById('manualRefreshResult').innerHTML = '<div class="text-danger small">'+err.message+'</div>'; });
});

// ─── Clean DB Select All ───
var cleanAll = document.getElementById('cleanSelectAll');
if(cleanAll){
    cleanAll.addEventListener('change', function(){ document.querySelectorAll('.clean-module').forEach(function(cb){ cb.checked = cleanAll.checked; }); });
}
var cleanForm = document.querySelector('#cleanDbModal form');
if(cleanForm){
    cleanForm.addEventListener('submit', function(e){
        if(!Array.from(document.querySelectorAll('.clean-module')).some(function(cb){ return cb.checked; })){ e.preventDefault(); alert('Select at least one module.'); return; }
        if(!confirm('This will permanently delete selected data. Continue?')) e.preventDefault();
    });
}

// ─── PDF Template Picker ───
document.querySelectorAll('.tpl-card').forEach(function(card) {
    card.addEventListener('click', function() {
        document.querySelectorAll('.tpl-card').forEach(function(c){ c.classList.remove('selected'); });
        card.classList.add('selected');
        var input = document.getElementById('pdf_template_input');
        if (input) input.value = card.getAttribute('data-tpl');
    });
});
</script>
<?= $this->endSection() ?>
