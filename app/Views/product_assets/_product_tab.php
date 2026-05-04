<?php
// Product Assets Management - Redesigned Flow
// New Flow: 1. Select Brand + Channel 2. Upload 3. Manage

$productIdentifier = $productIdentifier ?? null;
if (!$productIdentifier) {
    echo '<div class="alert alert-danger">Error: Product identifier not found</div>';
    return;
}
?>

<style>
.asset-flow-card {
    background: linear-gradient(135deg, #101a2b 0%, #0f172a 100%);
    border: 1px solid #334155;
    border-radius: 8px;
}

.asset-flow-step {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    border-radius: 999px;
    background: #1e293b;
    color: #93c5fd;
    font-size: 0.68rem;
    font-weight: 700;
}

.asset-refresh-btn[disabled] {
    opacity: 0.7;
    cursor: wait;
}

.asset-link-wrap {
    position: relative;
    padding-left: 12px;
}

.asset-link-rail {
    position: absolute;
    left: 4px;
    top: 2px;
    bottom: 2px;
    width: 1px;
    background: #263445;
}

.asset-link-rail::after {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    width: 7px;
    height: 1px;
    background: #263445;
}

.asset-link-node {
    position: absolute;
    left: 10px;
    top: calc(50% - 2px);
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: #38bdf8;
}

.asset-link-card {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #0d1626;
    border: 1px solid #243448;
    border-radius: 6px;
    padding: 0.14rem 0.3rem;
    max-width: 100%;
}

.asset-source-tag {
    font-size: 0.54rem;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    white-space: nowrap;
}

.asset-source-name {
    font-size: 0.64rem;
    color: #cbd5e1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 220px;
}

.asset-type-mini {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 1px solid #334155;
    background: #111827;
    font-size: 0.56rem;
    font-weight: 800;
    letter-spacing: -0.2px;
    text-transform: uppercase;
    padding: 0 4px;
}
</style>

<div class="card border-0" style="background-color: #1f2937;">
    <div class="card-body">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-0" style="color: #e5e7eb;"><i class="bi bi-images"></i> Product Assets</h5>
                <small style="color: #9ca3af;">Upload images, manage versions, and organize files</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm asset-refresh-btn" id="refreshAssetsBtn" style="background-color:#1e293b;border-color:#334155;color:#cbd5e1;">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button type="button" class="btn btn-sm" id="createBrandBtn" style="background-color: #10b981; border-color: #10b981; color: white;">
                    <i class="bi bi-plus-lg"></i> Brand
                </button>
                <button type="button" class="btn btn-sm" id="createChannelBtn" style="background-color: #0891b2; border-color: #0891b2; color: white;">
                    <i class="bi bi-plus-lg"></i> Channel
                </button>
                <button type="button" class="btn btn-sm" id="manageSetupBtn" style="background-color: #374151; border-color: #374151; color: #e5e7eb;">
                    <i class="bi bi-gear"></i> Manage Setup
                </button>
                <a href="<?= site_url('product-assets') ?>" class="btn btn-sm" style="background-color: #3b82f6; border-color: #3b82f6; color: white;">
                    <i class="bi bi-grid"></i> Assets Hub
                </a>
            </div>
        </div>

        <!-- Error/Status alerts -->
        <div id="statusAlert"></div>

        <div class="mb-3" style="background:#0f172a;border:1px solid #374151;border-radius:6px;padding:0.5rem;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="btn-group btn-group-sm" role="group" aria-label="Asset focus tabs">
                    <button type="button" class="btn" id="focusTabRaw" style="background:#3b82f6;color:#fff;border-color:#3b82f6;">Raw Images</button>
                    <button type="button" class="btn" id="focusTabFinal" style="background:#1f2937;color:#9ca3af;border-color:#374151;">Final Images</button>
                    <button type="button" class="btn" id="focusTabChannels" style="background:#1f2937;color:#9ca3af;border-color:#374151;">Channels</button>
                </div>
                <small id="assetsSyncLabel" style="color:#64748b;">Synced just now</small>
            </div>
        </div>

        <!-- ============================================================
             STEP 1: SELECT BRAND & CHANNEL
             ============================================================ -->
        <div class="mb-3" id="step1Block" style="background-color: #0f172a; border: 1px solid #374151; border-radius: 8px; padding: 0.85rem;">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1" style="color:#9ca3af;letter-spacing:0.04em;font-size:0.72rem;text-transform:uppercase;">Brand</label>
                    <select id="selectBrand" class="form-select form-select-sm" style="background-color:#1f2937;color:#e5e7eb;border-color:#374151;border-radius:5px;">
                        <option value="">-- Select brand --</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1" style="color:#9ca3af;letter-spacing:0.04em;font-size:0.72rem;text-transform:uppercase;">Channel</label>
                    <select id="selectChannel" class="form-select form-select-sm" style="background-color:#1f2937;color:#e5e7eb;border-color:#374151;border-radius:5px;" disabled>
                        <option value="">-- Select channel --</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1" style="color:#9ca3af;letter-spacing:0.04em;font-size:0.72rem;text-transform:uppercase;">Section</label>
                    <select id="uploadSectionSelect" class="form-select form-select-sm" style="background-color:#1f2937;color:#e5e7eb;border-color:#374151;border-radius:5px;" disabled>
                        <option value="">-- Select channel first --</option>
                    </select>
                </div>
            </div>

            <!-- Channel Rules + Status row -->
            <div class="d-flex align-items-center gap-3 mt-2 flex-wrap">
                <div id="selectionStatus" class="small" style="color:#6b7280;flex:1;min-width:0;">
                    <i class="bi bi-info-circle"></i> Select Brand, Channel and Section to upload
                </div>
                <div id="channelRulesBox" class="d-none" style="font-size:0.72rem;color:#9ca3af;">
                    <span id="channelRules"></span>
                </div>
            </div>
            <!-- Hidden placeholder kept for legacy JS hooks -->
            <div id="channelSummaryRows" class="d-none" aria-hidden="true"></div>
        </div>

        <!-- ============================================================
             COMMON PRODUCT ASSETS: RAW + FINAL (NOT TIED TO CHANNEL/BRAND)
             ============================================================ -->
        <div class="mb-3" id="commonBlock" style="background-color: #111827; border: 1px solid #374151; border-radius: 6px; padding: 0.75rem;">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="badge rounded-pill" style="background-color: #10b981; font-size: 0.85rem; padding: 0.4rem 0.7rem;">COMMON</div>
                    <h6 class="mb-0" style="color: #06b6d4;">Common Product Assets (Raw + Final)</h6>
                </div>
                <div class="small" style="color:#9ca3af;">Windows-style quick browse and management</div>
            </div>
            <div class="small mb-3" style="color: #9ca3af;">
                Common domain assets only. Use Raw for reference photos and Final for export-ready output with required source file.
            </div>

            <div class="row g-3">
                <div class="col-lg-6" id="commonRawPanelCol">
                    <div style="background:#0f172a;border:1px solid #374151;border-radius:6px;padding:0.75rem;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div style="color:#e5e7eb;font-weight:600;">Raw Images <span id="rawTotalCount" class="small" style="color:#9ca3af;">(0)</span></div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Raw assets view mode">
                                    <button type="button" class="btn" id="rawListViewBtn" style="background-color:#3b82f6;color:#fff;border-color:#3b82f6;">List</button>
                                    <button type="button" class="btn" id="rawThumbViewBtn" style="background-color:#1f2937;color:#9ca3af;border-color:#374151;">Thumb</button>
                                </div>
                                <button type="button" class="btn btn-sm" id="commonRawUploadBtn" style="background:#0891b2;color:#fff;border-color:#0891b2;margin-left:0.2rem;">
                                    <i class="bi bi-upload"></i> Upload Raw
                                </button>
                            </div>
                        </div>
                        <div id="commonRawProgressWrap" class="d-none mb-2">
                            <div class="progress" style="height: 6px; background-color: #374151;">
                                <div id="commonRawProgressBar" style="width: 0%; background: linear-gradient(90deg, #06b6d4 0%, #0891b2 100%); transition: width 0.2s;"></div>
                            </div>
                            <small id="commonRawProgressText" style="color:#9ca3af;">Uploading...</small>
                        </div>
                        <input type="file" id="commonRawInput" class="d-none" multiple>
                        <div id="commonRawGrid" class="d-flex flex-wrap gap-2"></div>
                    </div>
                </div>
                <div class="col-lg-6" id="commonFinalPanelCol">
                    <div style="background:#0f172a;border:1px solid #374151;border-radius:6px;padding:0.75rem;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div style="color:#e5e7eb;font-weight:600;">Final Images <span id="finalTotalCount" class="small" style="color:#9ca3af;">(0)</span></div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Final assets view mode">
                                    <button type="button" class="btn" id="finalListViewBtn" style="background-color:#3b82f6;color:#fff;border-color:#3b82f6;">List</button>
                                    <button type="button" class="btn" id="finalThumbViewBtn" style="background-color:#1f2937;color:#9ca3af;border-color:#374151;">Thumb</button>
                                </div>
                                <button type="button" class="btn btn-sm" id="commonFinalUploadBtn" style="background:#06b6d4;color:#0f172a;border-color:#06b6d4;">
                                    <i class="bi bi-plus-lg"></i> Add Final Image
                                </button>
                            </div>
                        </div>
                        <!-- Paired upload card -->
                        <div id="finalUploadCard" class="d-none mb-2 p-2 asset-flow-card">
                            <div class="small mb-2" style="color:#cbd5e1;">Simple flow: pick final image, pick source file, then upload pair.</div>
                            <div class="d-flex gap-2 flex-wrap align-items-center mb-2">
                                <div class="d-flex align-items-center gap-2" style="flex:1;min-width:220px;">
                                    <span class="asset-flow-step">1</span>
                                    <button type="button" id="finalPickImageBtn" class="btn btn-sm w-100" style="background:#1f2937;color:#e5e7eb;border-color:#334155;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        <i class="bi bi-image"></i> <span id="finalPickImageLabel">Choose Final Image</span>
                                    </button>
                                </div>
                                <div class="d-flex align-items-center gap-2" style="color:#64748b;"><i class="bi bi-arrow-right"></i></div>
                                <div class="d-flex align-items-center gap-2" style="flex:1;min-width:220px;">
                                    <span class="asset-flow-step">2</span>
                                    <button type="button" id="finalPickSourceBtn" class="btn btn-sm w-100" style="background:#1f2937;color:#e5e7eb;border-color:#334155;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        <i class="bi bi-paperclip"></i> <span id="finalPickSourceLabel">Choose Source File</span>
                                    </button>
                                </div>
                                <div class="d-flex align-items-center gap-2" style="flex:0 0 auto;">
                                    <span class="asset-flow-step">3</span>
                                    <button type="button" id="finalDoUploadBtn" class="btn btn-sm" disabled style="background:#06b6d4;color:#0f172a;border-color:#06b6d4;opacity:0.5;">
                                        <i class="bi bi-upload"></i> Upload Pair
                                    </button>
                                    <button type="button" id="finalCancelUploadBtn" class="btn btn-sm" style="background:#374151;color:#9ca3af;border-color:#374151;">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                            <div id="commonFinalProgressWrap" class="d-none">
                                <div class="progress" style="height: 6px; background-color: #374151;">
                                    <div id="commonFinalProgressBar" class="progress-bar" style="width: 0%; background: linear-gradient(90deg, #06b6d4 0%, #0891b2 100%); transition: width 0.2s;"></div>
                                </div>
                                <small id="commonFinalProgressText" style="color:#9ca3af;">Uploading...</small>
                            </div>
                        </div>
                        <input type="file" id="commonFinalInput" class="d-none">
                        <input type="file" id="commonFinalSourceInput" class="d-none">
                        <div id="commonFinalGrid" class="d-flex flex-wrap gap-2"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
             STEP 2: UPLOAD FILES (Enabled after Channel & Brand selected)
             ============================================================ -->
        <div class="mb-3" style="background-color: #111827; border: 1px solid #374151; border-radius: 6px; padding: 0.75rem;" id="step2Block">
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="badge rounded-pill" style="background-color: #3b82f6; font-size: 0.85rem; padding: 0.4rem 0.7rem;">STEP 2</div>
                <h6 class="mb-0" style="color: #06b6d4;">Upload Files</h6>
            </div>

            <div class="mb-2 small" style="color: #9ca3af;">
                Upload only channel-specific variants here. Select one channel image <strong style="color:#e5e7eb;">and</strong> its source file per upload.
            </div>

            <!-- Channel upload card -->
            <div id="uploadZone" class="rounded p-3 asset-flow-card" style="border: 2px dashed #334155; opacity: 0.5; transition: all 0.2s;">
                <div class="d-flex gap-3 flex-wrap align-items-end">
                    <div style="flex:1;min-width:160px;">
                        <label class="small mb-1 d-flex align-items-center gap-2" style="color:#9ca3af;"><span class="asset-flow-step">1</span> Channel Image <span style="color:#ef4444;">*</span></label>
                        <button type="button" class="btn btn-sm w-100" id="uploadBrowseBtn" disabled
                            style="background-color:#1f2937;color:#e5e7eb;border-color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <i class="bi bi-image"></i> <span id="uploadImageLabel">Choose Image</span>
                        </button>
                        <input type="file" id="assetFileInput" class="d-none">
                    </div>
                    <div style="flex:1;min-width:160px;">
                        <label class="small mb-1 d-flex align-items-center gap-2" style="color:#9ca3af;"><span class="asset-flow-step">2</span> Source File <span style="color:#ef4444;">*</span></label>
                        <button type="button" class="btn btn-sm w-100" id="uploadSourceBrowseBtn" disabled
                            style="background-color:#1f2937;color:#e5e7eb;border-color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <i class="bi bi-paperclip"></i> <span id="uploadSourceLabel">Choose Source</span>
                        </button>
                        <input type="file" id="assetSourceInput" class="d-none">
                    </div>
                    <div>
                        <label class="small mb-1 d-flex align-items-center gap-2" style="color:#9ca3af;"><span class="asset-flow-step">3</span> Upload</label>
                        <button type="button" class="btn btn-sm" id="channelUploadNowBtn" disabled
                            style="background:#06b6d4;color:#0f172a;border-color:#06b6d4;opacity:0.5;">
                            <i class="bi bi-upload"></i> Upload Pair
                        </button>
                    </div>
                </div>
                <div class="mt-2 small" id="uploadSourceName" style="color:#9ca3af;">For non-technical users: just pick 1 image + 1 source file, then click Upload Pair.</div>
            </div>

            <!-- Upload Progress -->
            <div id="uploadProgressWrap" class="d-none mt-2">
                <div class="progress" style="height: 6px; background-color: #374151;">
                    <div id="uploadProgressBar" style="width: 0%; background: linear-gradient(90deg, #06b6d4 0%, #0891b2 100%); transition: width 0.2s;"></div>
                </div>
                <small id="uploadStatus" style="color: #9ca3af; margin-top: 0.5rem; display: block;">Preparing upload...</small>
            </div>
        </div>

        <!-- ============================================================
             STEP 3: MANAGE ASSETS
             ============================================================ -->
        <div id="step3Block" style="background-color: #111827; border: 1px solid #374151; border-radius: 6px; padding: 0.75rem;">
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="badge rounded-pill" style="background-color: #10b981; font-size: 0.85rem; padding: 0.4rem 0.7rem;">STEP 3</div>
                <h6 class="mb-0" style="color: #06b6d4;">Your Assets</h6>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle" style="color: #e5e7eb; border-color: #374151; margin-bottom: 0;">
                    <thead style="background-color: #0f172a; border-bottom: 1px solid #374151;">
                        <tr>
                            <th style="color: #9ca3af; width:44px;">No.</th>
                            <th style="color: #9ca3af; width:42px;">Icon</th>
                            <th style="color: #9ca3af;">File</th>
                            <th style="color: #9ca3af;">Brand / Section</th>
                            <th style="color: #9ca3af;">Size</th>
                            <th style="color: #9ca3af; width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="assetRows" style="border-color: #374151;">
                        <tr>
                            <td colspan="7" class="text-center py-3" style="color: #9ca3af;">
                                <small>No assets yet. Start by uploading files!</small>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Product Listings -->
        <div id="listingsBlock" class="mt-4 pt-3" style="border-top: 1px solid #374151;">
            <h6 style="color: #e5e7eb; margin-bottom: 1rem;"><i class="bi bi-link"></i> Product Listings</h6>
            <form id="listingForm" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-4">
                    <label class="form-label small" style="color: #d1d5db;">Channel</label>
                    <select name="channel_id" id="listingChannelSelect" class="form-select form-select-sm" style="background-color: #1f2937; color: #e5e7eb; border-color: #374151;" required>
                        <option value="">Select channel...</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small" style="color: #d1d5db;">Listing URL</label>
                    <input type="url" name="listing_url" class="form-control form-control-sm" style="background-color: #1f2937; color: #e5e7eb; border-color: #374151;" placeholder="https://..." required>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm w-100" type="submit" style="background-color: #3b82f6; color: white; font-weight: 500;">
                        <i class="bi bi-save"></i> Save
                    </button>
                </div>
            </form>

            <div id="listingsList" class="mt-2">
                <small style="color: #9ca3af;">No listings yet</small>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for uploads -->
<form id="assetUploadForm" enctype="multipart/form-data" class="d-none">
    <?= csrf_field() ?>
    <input type="hidden" name="asset_group_id" id="uploadBrandId">
    <input type="hidden" name="channel_id" id="uploadChannelId">
    <input type="hidden" name="type" id="uploadType" value="final">
</form>
<input type="file" id="assetReplaceInput" class="d-none">
<input type="file" id="assetAttachSourceInput" class="d-none">

<div id="hoverPreviewBox" class="d-none" style="position: fixed; z-index: 2200; pointer-events: none; background: #0f172a; border: 1px solid #374151; border-radius: 8px; padding: 0.35rem; box-shadow: 0 12px 30px rgba(0,0,0,0.35);">
    <img id="hoverPreviewImg" src="" alt="Preview" style="width: 220px; height: 220px; object-fit: contain; display: block; background: #111827; border-radius: 6px;">
</div>

<div class="modal fade" id="assetLightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="background:#0b1220;border:1px solid #374151;">
            <div class="modal-header" style="border-color:#374151;">
                <h6 id="assetLightboxTitle" class="modal-title" style="color:#e5e7eb;">Image Preview</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:0.75rem;">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <button type="button" class="btn btn-sm" id="lightboxPrevBtn" style="background:#1f2937;color:#e5e7eb;border-color:#374151;">Prev</button>
                    <div style="flex:1; min-height:70vh; display:flex; align-items:center; justify-content:center; background:#111827; border:1px solid #374151; border-radius:8px;">
                        <img id="assetLightboxImg" src="" alt="Image" style="max-width:100%; max-height:68vh; object-fit:contain;">
                    </div>
                    <button type="button" class="btn btn-sm" id="lightboxNextBtn" style="background:#1f2937;color:#e5e7eb;border-color:#374151;">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="channelSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background-color: #111827; border: 1px solid #374151; color: #e5e7eb;">
            <div class="modal-header" style="border-color: #374151;">
                <h5 class="modal-title" id="channelSettingsTitle">Channel Settings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="channelSettingsForm">
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" id="channelSettingsMode" value="create">
                    <input type="hidden" id="channelSettingsId" value="">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label small">Channel Name</label>
                            <input type="text" id="channelNameInput" name="name" class="form-control form-control-sm" style="background-color: #1f2937; color: #e5e7eb; border-color: #374151;" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Short Code</label>
                            <input type="text" id="channelCodeInput" name="short_code" class="form-control form-control-sm" maxlength="20" style="background-color: #1f2937; color: #e5e7eb; border-color: #374151;" placeholder="AB">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Width (px)</label>
                            <input type="number" min="1" name="width" id="channelWidthInput" class="form-control form-control-sm" style="background-color: #1f2937; color: #e5e7eb; border-color: #374151;">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Height (px)</label>
                            <input type="number" min="1" name="height" id="channelHeightInput" class="form-control form-control-sm" style="background-color: #1f2937; color: #e5e7eb; border-color: #374151;">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Final Max Size (MB)</label>
                            <input type="number" min="1" name="final_max_file_size_mb" id="channelMaxSizeInput" value="50" class="form-control form-control-sm" style="background-color: #1f2937; color: #e5e7eb; border-color: #374151;" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Background Rule</label>
                            <select name="background_rule" id="channelBackgroundInput" class="form-select form-select-sm" style="background-color: #1f2937; color: #e5e7eb; border-color: #374151;">
                                <option value="any">Any</option>
                                <option value="white">White</option>
                                <option value="transparent">Transparent</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3 p-2" style="background-color: #0f172a; border: 1px solid #374151; border-radius: 6px;">
                        <div class="small mb-2" style="color: #9ca3af;">Core sections (mandatory for every product/channel)</div>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="badge" style="background-color: #1f2937; color: #10b981; border: 1px solid #374151;">Raw Images</span>
                            <span class="badge" style="background-color: #1f2937; color: #10b981; border: 1px solid #374151;">Source Files (single file)</span>
                            <span class="badge" style="background-color: #1f2937; color: #10b981; border: 1px solid #374151;">Final Images</span>
                        </div>
                        <div class="small mb-2" style="color: #9ca3af;">Optional final variants (pre-selected; uncheck to remove)</div>
                        <div class="row g-2">
                            <div class="col-md-4"><label><input type="checkbox" name="enable_final_watermark" checked> Final With Watermark</label></div>
                            <div class="col-md-4"><label><input type="checkbox" name="enable_final_template" checked> Final With Template</label></div>
                        </div>
                        <div class="mt-2">
                            <label class="form-label small">Extra Final Sections (comma separated)</label>
                            <input type="text" name="extra_final_sections" id="extraFinalSectionsInput" class="form-control form-control-sm" style="background-color: #1f2937; color: #e5e7eb; border-color: #374151;" placeholder="Marketplace Hero, 1:1 Final, Banner Final">
                        </div>
                    </div>

                    <div class="row g-2 mt-2">
                        <div class="col-md-6">
                            <label class="form-label small">Final Allowed Formats (CSV)</label>
                            <input type="text" name="final_allowed_formats" id="finalFormatsInput" class="form-control form-control-sm" value="jpg,jpeg,png,webp,pdf" style="background-color: #1f2937; color: #e5e7eb; border-color: #374151;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Source Allowed Formats (CSV)</label>
                            <input type="text" name="source_allowed_formats" id="sourceFormatsInput" class="form-control form-control-sm" value="psd,ai,cdr,pdf,svg,eps" style="background-color: #1f2937; color: #e5e7eb; border-color: #374151;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-color: #374151;">
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal" style="background-color: #374151; color: #e5e7eb;">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color: #06b6d4; color: #0f172a; font-weight: 600;">Save Channel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="setupManagerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="background-color: #111827; border: 1px solid #374151; color: #e5e7eb;">
            <div class="modal-header" style="border-color: #374151;">
                <h5 class="modal-title">Brand & Channel Management</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-lg-5">
                        <div style="background-color: #0f172a; border: 1px solid #374151; border-radius: 6px; padding: 0.75rem;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0" style="color: #06b6d4;">Brands</h6>
                                <button type="button" class="btn btn-sm" id="manageCreateBrandBtn" style="background-color: #10b981; color: white; border-color: #10b981;">
                                    <i class="bi bi-plus-lg"></i> Add Brand
                                </button>
                            </div>
                            <div class="small mb-2" style="color: #9ca3af;">Create only real brands that your team uses.</div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle" style="color: #e5e7eb; margin-bottom: 0;">
                                    <thead>
                                        <tr>
                                            <th style="color: #9ca3af;">Brand</th>
                                            <th style="color: #9ca3af; width: 110px;">Assets</th>
                                        </tr>
                                    </thead>
                                    <tbody id="manageBrandRows">
                                        <tr><td colspan="2" style="color: #9ca3af;">No brands</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div style="background-color: #0f172a; border: 1px solid #374151; border-radius: 6px; padding: 0.75rem;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0" style="color: #06b6d4;">Channels</h6>
                                <button type="button" class="btn btn-sm" id="manageCreateChannelBtn" style="background-color: #0891b2; color: white; border-color: #0891b2;">
                                    <i class="bi bi-plus-lg"></i> Add Channel
                                </button>
                            </div>
                            <div class="small mb-2" style="color: #9ca3af;">Core sections are mandatory: Raw Images, Source Files, Final Images.</div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle" style="color: #e5e7eb; margin-bottom: 0;">
                                    <thead>
                                        <tr>
                                            <th style="color: #9ca3af;">Channel</th>
                                            <th style="color: #9ca3af;">Code</th>
                                            <th style="color: #9ca3af; width: 80px;">Assets</th>
                                            <th style="color: #9ca3af; width: 170px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="manageChannelRows">
                                        <tr><td colspan="4" style="color: #9ca3af;">No channels</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-color: #374151;">
                <button type="button" class="btn btn-sm" data-bs-dismiss="modal" style="background-color: #374151; color: #e5e7eb;">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Product Assets Manager initializing...');
    
    const productId = '<?= esc($productIdentifier) ?>';
    console.log('Product ID:', productId);
    
    if (!productId) {
        showError('Product identifier missing from view');
        return;
    }

    const apiBase = <?= json_encode(site_url('products/' . rawurlencode((string) $productIdentifier) . '/assets')) ?>;

    const selectChannel = document.getElementById('selectChannel');
    const selectBrand = document.getElementById('selectBrand');
    const focusTabRaw = document.getElementById('focusTabRaw');
    const focusTabFinal = document.getElementById('focusTabFinal');
    const focusTabChannels = document.getElementById('focusTabChannels');
    const step1Block = document.getElementById('step1Block');
    const commonBlock = document.getElementById('commonBlock');
    const commonRawPanelCol = document.getElementById('commonRawPanelCol');
    const commonFinalPanelCol = document.getElementById('commonFinalPanelCol');
    const step2Block = document.getElementById('step2Block');
    const step3Block = document.getElementById('step3Block');
    const listingsBlock = document.getElementById('listingsBlock');
    const refreshAssetsBtn = document.getElementById('refreshAssetsBtn');
    const assetsSyncLabel = document.getElementById('assetsSyncLabel');
    const createBrandBtn = document.getElementById('createBrandBtn');
    const createChannelBtn = document.getElementById('createChannelBtn');
    const manageSetupBtn = document.getElementById('manageSetupBtn');
    const manageCreateBrandBtn = document.getElementById('manageCreateBrandBtn');
    const manageCreateChannelBtn = document.getElementById('manageCreateChannelBtn');
    const manageBrandRows = document.getElementById('manageBrandRows');
    const manageChannelRows = document.getElementById('manageChannelRows');
    const commonRawUploadBtn = document.getElementById('commonRawUploadBtn');
    const commonRawInput = document.getElementById('commonRawInput');
    const commonFinalUploadBtn = document.getElementById('commonFinalUploadBtn');
    const commonFinalInput = document.getElementById('commonFinalInput');
    const commonFinalSourceInput = document.getElementById('commonFinalSourceInput');
    const finalUploadCard = document.getElementById('finalUploadCard');
    const finalPickImageBtn = document.getElementById('finalPickImageBtn');
    const finalPickSourceBtn = document.getElementById('finalPickSourceBtn');
    const finalPickImageLabel = document.getElementById('finalPickImageLabel');
    const finalPickSourceLabel = document.getElementById('finalPickSourceLabel');
    const finalDoUploadBtn = document.getElementById('finalDoUploadBtn');
    const finalCancelUploadBtn = document.getElementById('finalCancelUploadBtn');
    const commonRawGrid = document.getElementById('commonRawGrid');
    const commonFinalGrid = document.getElementById('commonFinalGrid');
    const rawTotalCount = document.getElementById('rawTotalCount');
    const finalTotalCount = document.getElementById('finalTotalCount');
    const rawListViewBtn = document.getElementById('rawListViewBtn');
    const rawThumbViewBtn = document.getElementById('rawThumbViewBtn');
    const finalListViewBtn = document.getElementById('finalListViewBtn');
    const finalThumbViewBtn = document.getElementById('finalThumbViewBtn');
    const commonRawProgressWrap = document.getElementById('commonRawProgressWrap');
    const commonRawProgressBar = document.getElementById('commonRawProgressBar');
    const commonRawProgressText = document.getElementById('commonRawProgressText');
    const commonFinalProgressWrap = document.getElementById('commonFinalProgressWrap');
    const commonFinalProgressBar = document.getElementById('commonFinalProgressBar');
    const commonFinalProgressText = document.getElementById('commonFinalProgressText');
    const assetReplaceInput = document.getElementById('assetReplaceInput');
    const assetAttachSourceInput = document.getElementById('assetAttachSourceInput');
    const hoverPreviewBox = document.getElementById('hoverPreviewBox');
    const hoverPreviewImg = document.getElementById('hoverPreviewImg');
    const assetLightboxModalEl = document.getElementById('assetLightboxModal');
    const assetLightboxTitle = document.getElementById('assetLightboxTitle');
    const assetLightboxImg = document.getElementById('assetLightboxImg');
    const lightboxPrevBtn = document.getElementById('lightboxPrevBtn');
    const lightboxNextBtn = document.getElementById('lightboxNextBtn');
    const uploadSectionSelect = document.getElementById('uploadSectionSelect');
    const uploadZone = document.getElementById('uploadZone');
    const uploadBrowseBtn = document.getElementById('uploadBrowseBtn');
    const uploadSourceBrowseBtn = document.getElementById('uploadSourceBrowseBtn');
    const uploadSourceName = document.getElementById('uploadSourceName');
    const uploadImageLabel = document.getElementById('uploadImageLabel');
    const uploadSourceLabel = document.getElementById('uploadSourceLabel');
    const channelUploadNowBtn = document.getElementById('channelUploadNowBtn');
    const assetSourceInput = document.getElementById('assetSourceInput');
    const assetFileInput = document.getElementById('assetFileInput');
    const channelRulesBox = document.getElementById('channelRulesBox');
    const channelRules = document.getElementById('channelRules');
    const selectionStatus = document.getElementById('selectionStatus');
    const channelSummaryRows = document.getElementById('channelSummaryRows');
    const channelSettingsModalEl = document.getElementById('channelSettingsModal');
    const channelSettingsForm = document.getElementById('channelSettingsForm');
    const channelSettingsTitle = document.getElementById('channelSettingsTitle');
    const channelSettingsMode = document.getElementById('channelSettingsMode');
    const channelSettingsId = document.getElementById('channelSettingsId');
    const channelNameInput = document.getElementById('channelNameInput');
    const channelCodeInput = document.getElementById('channelCodeInput');
    const channelWidthInput = document.getElementById('channelWidthInput');
    const channelHeightInput = document.getElementById('channelHeightInput');
    const channelMaxSizeInput = document.getElementById('channelMaxSizeInput');
    const finalFormatsInput = document.getElementById('finalFormatsInput');
    const sourceFormatsInput = document.getElementById('sourceFormatsInput');
    const extraFinalSectionsInput = document.getElementById('extraFinalSectionsInput');
    const channelBackgroundInput = document.getElementById('channelBackgroundInput');
    const channelModal = new bootstrap.Modal(channelSettingsModalEl);
    const setupManagerModalEl = document.getElementById('setupManagerModal');
    const setupManagerModal = new bootstrap.Modal(setupManagerModalEl);
    const assetLightboxModal = new bootstrap.Modal(assetLightboxModalEl);
    
    let channels = [];
    let brands = [];
    let currentChannel = null;
    let sectionOptions = [];
    let canManage = false;
    let reopenSetupManagerOnChannelClose = false;
    let commonRawViewMode = 'list';
    let commonFinalViewMode = 'list';
    let replacingAssetId = null;
    let attachingSourceToId = null;
    let lightboxItems = [];
    let lightboxIndex = -1;
    let currentFocusTab = 'raw';

    function updateAssetsSyncLabel() {
        if (!assetsSyncLabel) {
            return;
        }
        const now = new Date();
        const hh = String(now.getHours()).padStart(2, '0');
        const mm = String(now.getMinutes()).padStart(2, '0');
        const ss = String(now.getSeconds()).padStart(2, '0');
        assetsSyncLabel.textContent = `Synced at ${hh}:${mm}:${ss}`;
    }

    // Load initial data
    loadData();
    setCommonAreaViewMode('raw', 'list');
    setCommonAreaViewMode('final', 'list');
    setFocusTab('raw');

    // Brand selection changed
    selectBrand.addEventListener('change', function() {
        if (!this.value) {
            selectChannel.value = '';
            selectChannel.disabled = true;
            currentChannel = null;
            uploadSectionSelect.innerHTML = '<option value="">Select channel first...</option>';
            uploadSectionSelect.disabled = true;
            channelRulesBox.classList.add('d-none');
        } else {
            selectChannel.disabled = false;
            if (!selectChannel.value) {
                uploadSectionSelect.innerHTML = '<option value="">Select channel first...</option>';
                uploadSectionSelect.disabled = true;
            }
        }
        updateSelectionStatus();
        if (selectChannel.value && selectBrand.value && uploadSectionSelect.value) {
            enableUpload();
        } else {
            disableUpload();
        }
    });

    uploadSectionSelect.addEventListener('change', function() {
        if (selectChannel.value && selectBrand.value && uploadSectionSelect.value) {
            enableUpload();
        } else {
            disableUpload();
        }
        updateSelectionStatus();
    });

    // Channel selection changed
    selectChannel.addEventListener('change', function() {
        if (!selectBrand.value && this.value) {
            this.value = '';
            alert('Please select Brand first.');
            return;
        }

        const channelId = this.value;
        
        if (channelId) {
            currentChannel = channels.find(c => parseInt(c.id) === parseInt(channelId));
            showChannelRules();
            populateUploadSections(currentChannel);
            updateSelectionStatus();
        } else {
            currentChannel = null;
            channelRulesBox.classList.add('d-none');
            uploadSectionSelect.innerHTML = '<option value="">Select channel first...</option>';
            uploadSectionSelect.disabled = true;
            updateSelectionStatus();
        }

        if (selectChannel.value && selectBrand.value && uploadSectionSelect.value) {
            enableUpload();
        } else {
            disableUpload();
        }
    });

    createBrandBtn.addEventListener('click', function() {
        if (!canManage) {
            alert('You do not have permission to create brands.');
            return;
        }

        const name = (prompt('Enter brand name:') || '').trim();
        if (!name) {
            return;
        }

        const fd = new FormData();
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        fd.append('name', name);

        fetch(`${apiBase}/groups`, {
            method: 'POST',
            body: fd,
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to create brand');
                }
                loadData();
            })
            .catch(err => showError('Failed to create brand: ' + err.message));
    });

    createChannelBtn.addEventListener('click', function() {
        if (!canManage) {
            alert('You do not have permission to create channels.');
            return;
        }

        openChannelEditor(null);
    });

    manageSetupBtn.addEventListener('click', openSetupManager);
    refreshAssetsBtn.addEventListener('click', function() {
        if (this.disabled) {
            return;
        }
        this.disabled = true;
        this.innerHTML = '<i class="bi bi-arrow-repeat"></i> Refreshing...';
        loadData()
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Refresh';
            });
    });
    manageCreateBrandBtn.addEventListener('click', () => createBrandBtn.click());
    manageCreateChannelBtn.addEventListener('click', () => openChannelEditor(null, true));
    commonRawUploadBtn.addEventListener('click', () => commonRawInput.click());
    commonRawInput.addEventListener('change', () => uploadCommonAssets('raw_images', commonRawInput));

    // Final Images: toggle upload card
    commonFinalUploadBtn.addEventListener('click', () => {
        finalUploadCard.classList.toggle('d-none');
        if (!finalUploadCard.classList.contains('d-none')) {
            // reset pick labels when card opens
            finalPickImageLabel.textContent = 'Choose Final Image';
            finalPickSourceLabel.textContent = 'Choose Source File';
            commonFinalInput.value = '';
            commonFinalSourceInput.value = '';
            finalDoUploadBtn.disabled = true;
            finalDoUploadBtn.style.opacity = '0.5';
            document.getElementById('commonFinalProgressWrap').classList.add('d-none');
        }
    });
    finalCancelUploadBtn.addEventListener('click', () => {
        finalUploadCard.classList.add('d-none');
        commonFinalInput.value = '';
        commonFinalSourceInput.value = '';
        finalPickImageLabel.textContent = 'Choose Final Image';
        finalPickSourceLabel.textContent = 'Choose Source File';
        finalDoUploadBtn.disabled = true;
        finalDoUploadBtn.style.opacity = '0.5';
    });
    finalPickImageBtn.addEventListener('click', () => commonFinalInput.click());
    finalPickSourceBtn.addEventListener('click', () => commonFinalSourceInput.click());

    function checkFinalUploadReady() {
        const hasImage = commonFinalInput.files && commonFinalInput.files[0];
        const hasSource = commonFinalSourceInput.files && commonFinalSourceInput.files[0];
        finalDoUploadBtn.disabled = !(hasImage && hasSource);
        finalDoUploadBtn.style.opacity = (hasImage && hasSource) ? '1' : '0.5';
    }
    commonFinalInput.addEventListener('change', () => {
        const f = commonFinalInput.files && commonFinalInput.files[0] ? commonFinalInput.files[0] : null;
        finalPickImageLabel.textContent = f ? f.name : 'Choose Final Image';
        if (f) finalPickImageBtn.style.borderColor = '#10b981';
        else finalPickImageBtn.style.borderColor = '#374151';
        checkFinalUploadReady();
    });
    commonFinalSourceInput.addEventListener('change', () => {
        const f = commonFinalSourceInput.files && commonFinalSourceInput.files[0] ? commonFinalSourceInput.files[0] : null;
        finalPickSourceLabel.textContent = f ? f.name : 'Choose Source File';
        if (f) finalPickSourceBtn.style.borderColor = '#10b981';
        else finalPickSourceBtn.style.borderColor = '#374151';
        checkFinalUploadReady();
    });
    finalDoUploadBtn.addEventListener('click', () => {
        uploadCommonAssets('final_plain', commonFinalInput, commonFinalSourceInput);
    });

    // Channel upload: both pickers
    uploadSourceBrowseBtn.addEventListener('click', () => assetSourceInput.click());
    uploadBrowseBtn.addEventListener('click', () => assetFileInput.click());

    function checkChannelUploadReady() {
        const hasImage = assetFileInput.files && assetFileInput.files[0];
        const hasSource = assetSourceInput.files && assetSourceInput.files[0];
        const enabled = !!(hasImage && hasSource && selectChannel.value && selectBrand.value && uploadSectionSelect.value);
        channelUploadNowBtn.disabled = !enabled;
        channelUploadNowBtn.style.opacity = enabled ? '1' : '0.5';
    }
    assetFileInput.addEventListener('change', () => {
        const f = assetFileInput.files && assetFileInput.files[0] ? assetFileInput.files[0] : null;
        uploadImageLabel.textContent = f ? f.name : 'Choose Image';
        if (f) uploadBrowseBtn.style.borderColor = '#10b981';
        else uploadBrowseBtn.style.borderColor = '#374151';
        checkChannelUploadReady();
    });
    assetSourceInput.addEventListener('change', () => {
        const f = assetSourceInput.files && assetSourceInput.files[0] ? assetSourceInput.files[0] : null;
        uploadSourceLabel.textContent = f ? f.name : 'Choose Source';
        if (f) uploadSourceBrowseBtn.style.borderColor = '#10b981';
        else uploadSourceBrowseBtn.style.borderColor = '#374151';
        uploadSourceName.textContent = f ? `Source: ${f.name}` : 'Select channel, brand, and section to enable upload.';
        checkChannelUploadReady();
    });
    channelUploadNowBtn.addEventListener('click', performUpload);
    focusTabRaw.addEventListener('click', () => setFocusTab('raw'));
    focusTabFinal.addEventListener('click', () => setFocusTab('final'));
    focusTabChannels.addEventListener('click', () => setFocusTab('channels'));
    rawListViewBtn.addEventListener('click', () => setCommonAreaViewMode('raw', 'list'));
    rawThumbViewBtn.addEventListener('click', () => setCommonAreaViewMode('raw', 'thumb'));
    finalListViewBtn.addEventListener('click', () => setCommonAreaViewMode('final', 'list'));
    finalThumbViewBtn.addEventListener('click', () => setCommonAreaViewMode('final', 'thumb'));
    assetReplaceInput.addEventListener('change', handleReplaceAssetFile);
    assetAttachSourceInput.addEventListener('change', handleAttachSourceFile);
    lightboxPrevBtn.addEventListener('click', () => moveLightbox(-1));
    lightboxNextBtn.addEventListener('click', () => moveLightbox(1));

    channelSettingsForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const mode = channelSettingsMode.value;
        const channelId = channelSettingsId.value;
        const fd = new FormData(channelSettingsForm);

        const endpoint = mode === 'edit'
            ? `${apiBase}/channels/${channelId}/update`
            : `${apiBase}/channels`;

        fetch(endpoint, {
            method: 'POST',
            body: fd,
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to save channel');
                }
                const shouldReopenManager = reopenSetupManagerOnChannelClose;
                reopenSetupManagerOnChannelClose = false;
                channelModal.hide();
                loadData();
                if (shouldReopenManager) {
                    setTimeout(() => {
                        renderSetupManager();
                        setupManagerModal.show();
                    }, 120);
                }
            })
            .catch(err => showError('Failed to save channel: ' + err.message));
    });

    channelSettingsModalEl.addEventListener('hidden.bs.modal', function() {
        if (!reopenSetupManagerOnChannelClose) {
            return;
        }
        renderSetupManager();
        setupManagerModal.show();
    });

    function openChannelEditor(channel, fromSetupManager = false) {
        if (!canManage) {
            alert('You do not have permission to manage channels.');
            return;
        }

        reopenSetupManagerOnChannelClose = fromSetupManager;

        const isEdit = !!channel;
        channelSettingsTitle.textContent = isEdit ? 'Edit Channel' : 'Create Channel';
        channelSettingsMode.value = isEdit ? 'edit' : 'create';
        channelSettingsId.value = isEdit ? String(channel.id || '') : '';
        channelSettingsForm.reset();
        channelMaxSizeInput.value = '50';
        finalFormatsInput.value = 'jpg,jpeg,png,webp,pdf';
        sourceFormatsInput.value = 'psd,ai,cdr,pdf,svg,eps';
        channelSettingsForm.querySelector('input[name="enable_final_watermark"]').checked = true;
        channelSettingsForm.querySelector('input[name="enable_final_template"]').checked = true;

        if (isEdit) {
            if ((channel.asset_count || 0) > 0) {
                alert('Cannot edit this channel because assets are attached to it.');
                return;
            }
            const rules = channel.rules || {};
            const sections = Array.isArray(rules.sections) ? rules.sections : [];
            const has = key => sections.some(s => s && s.key === key);
            const extra = sections
                .filter(s => s && !['raw_images', 'source', 'final_plain', 'final_watermark', 'final_template'].includes(s.key))
                .map(s => s.label)
                .join(', ');

            channelNameInput.value = channel.name || '';
            channelCodeInput.value = channel.short_code || '';
            channelWidthInput.value = channel.width || '';
            channelHeightInput.value = channel.height || '';
            channelBackgroundInput.value = channel.background_rule || 'any';
            channelMaxSizeInput.value = Math.round(((rules.final?.max_file_size_bytes || (50 * 1024 * 1024)) / 1024 / 1024));
            finalFormatsInput.value = (rules.final?.formats || ['jpg', 'jpeg', 'png', 'webp', 'pdf']).join(',');
            sourceFormatsInput.value = (rules.source?.formats || ['psd', 'ai', 'cdr', 'pdf', 'svg', 'eps']).join(',');
            extraFinalSectionsInput.value = extra;
            channelSettingsForm.querySelector('input[name="enable_final_watermark"]').checked = has('final_watermark');
            channelSettingsForm.querySelector('input[name="enable_final_template"]').checked = has('final_template');
        }

        if (fromSetupManager && setupManagerModalEl.classList.contains('show')) {
            setupManagerModal.hide();
            setTimeout(() => channelModal.show(), 180);
        } else {
            channelModal.show();
        }
    }

    function openSetupManager() {
        renderSetupManager();
        setupManagerModal.show();
    }

    function renderSetupManager() {
        const brandCountMap = {};
        const channelCountMap = {};
        (window.__assetsCache || []).forEach(asset => {
            const gid = String(asset.asset_group_id || '');
            const cid = String(asset.channel_id || '');
            brandCountMap[gid] = (brandCountMap[gid] || 0) + 1;
            channelCountMap[cid] = (channelCountMap[cid] || 0) + 1;
        });

        if (!brands.length) {
            manageBrandRows.innerHTML = '<tr><td colspan="2" style="color:#9ca3af;">No brands</td></tr>';
        } else {
            manageBrandRows.innerHTML = brands.map(b => `
                <tr>
                    <td>${b.name}</td>
                    <td><span class="badge" style="background:#1f2937;border:1px solid #374151;color:#9ca3af;">${brandCountMap[String(b.id)] || 0}</span></td>
                </tr>
            `).join('');
        }

        if (!channels.length) {
            manageChannelRows.innerHTML = '<tr><td colspan="4" style="color:#9ca3af;">No channels</td></tr>';
            return;
        }

        manageChannelRows.innerHTML = channels.map(c => {
            const locked = (c.asset_count || 0) > 0;
            return `
                <tr>
                    <td>${c.name}</td>
                    <td>${c.short_code || '-'}</td>
                    <td><span class="badge" style="background:#1f2937;border:1px solid #374151;color:#9ca3af;">${channelCountMap[String(c.id)] || 0}</span></td>
                    <td>
                        <button type="button" class="btn btn-sm me-1 manage-edit-channel" data-id="${c.id}" ${locked ? 'disabled' : ''} style="background:#374151;color:#e5e7eb;border-color:#374151;">Edit</button>
                        <button type="button" class="btn btn-sm manage-delete-channel" data-id="${c.id}" ${locked ? 'disabled' : ''} style="background:#7f1d1d;color:#fecaca;border-color:#7f1d1d;">Delete</button>
                    </td>
                </tr>
            `;
        }).join('');

        manageChannelRows.querySelectorAll('.manage-edit-channel').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = parseInt(this.getAttribute('data-id') || '0');
                const channel = channels.find(c => parseInt(c.id) === id);
                if (channel) {
                    openChannelEditor(channel, true);
                }
            });
        });

        manageChannelRows.querySelectorAll('.manage-delete-channel').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = parseInt(this.getAttribute('data-id') || '0');
                const channel = channels.find(c => parseInt(c.id) === id);
                if (!channel) {
                    return;
                }
                if (!confirm(`Delete channel "${channel.name}"?`)) {
                    return;
                }

                const fd = new FormData();
                fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
                fetch(`${apiBase}/channels/${channel.id}/delete`, {
                    method: 'POST',
                    body: fd,
                })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Failed to delete channel');
                        }
                        loadData();
                    })
                    .catch(err => showError('Failed to delete channel: ' + err.message));
            });
        });
    }

    // Upload drag & drop
    uploadZone.addEventListener('dragover', e => {
        e.preventDefault();
        uploadZone.style.backgroundColor = '#1f2937';
        uploadZone.style.borderColor = '#0891b2';
    });

    uploadZone.addEventListener('dragleave', () => {
        uploadZone.style.backgroundColor = 'transparent';
    });

    uploadZone.addEventListener('drop', e => {
        e.preventDefault();
        uploadZone.style.backgroundColor = 'transparent';
        if (selectChannel.value && selectBrand.value && e.dataTransfer.files.length) {
            // Drag-drop populates the image picker; user must still choose source file
            const dt = new DataTransfer();
            for (const f of e.dataTransfer.files) dt.items.add(f);
            assetFileInput.files = dt.files;
            const f = assetFileInput.files[0];
            if (f) {
                uploadImageLabel.textContent = f.name;
                uploadBrowseBtn.style.borderColor = '#10b981';
            }
            checkChannelUploadReady();
        }
    });

    // Functions
    function loadData() {
        console.log('📡 Fetching initial data...');
        const prevBrand = selectBrand.value;
        const prevChannel = selectChannel.value;
        const prevSection = uploadSectionSelect.value;
        return fetch(`${apiBase}/data`)
            .then(r => {
                console.log('API Response status:', r.status, r.statusText);
                if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                return r.text();
            })
            .then(text => {
                console.log('Response text length:', text.length);
                try {
                    return JSON.parse(text);
                } catch(e) {
                    console.error('JSON parse error:', e);
                    console.error('Response text:', text.substring(0, 500));
                    throw new Error('Invalid JSON response');
                }
            })
            .then(data => {
                console.log('✅ Data loaded:', data);
                if (!data.success || !data.data) {
                    throw new Error(data.message || 'Unknown error');
                }
                
                canManage = data.data.can_manage === true;
                channels = data.data.channels || [];
                brands = data.data.groups || [];
                
                populateChannels(channels);
                populateBrandSelect(brands);
                renderChannelSummary(channels);
                if (prevBrand && brands.some(b => String(b.id) === String(prevBrand))) {
                    selectBrand.value = prevBrand;
                }
                if (selectBrand.value) {
                    selectChannel.disabled = false;
                } else {
                    selectChannel.value = '';
                    selectChannel.disabled = true;
                }

                if (prevChannel && selectBrand.value && channels.some(c => String(c.id) === String(prevChannel))) {
                    selectChannel.value = prevChannel;
                    currentChannel = channels.find(c => parseInt(c.id) === parseInt(prevChannel)) || null;
                    if (currentChannel) {
                        showChannelRules();
                        populateUploadSections(currentChannel);
                        if (prevSection) {
                            uploadSectionSelect.value = prevSection;
                        }
                    }
                }
                loadAssets();
                populateListingChannels(channels);
                updateSelectionStatus();
                updateAssetsSyncLabel();
            })
            .catch(err => {
                console.error('❌ Error:', err);
                showError('Failed to load assets: ' + err.message);
                disableUpload();
            });
    }

    function populateChannels(list) {
        selectChannel.innerHTML = '<option value="">Select a channel...</option>';
        list.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.short_code ? `${c.name} (${c.short_code})` : c.name;
            selectChannel.appendChild(opt);
        });
    }

    function renderChannelSummary(list) {
        if (!channelSummaryRows) {
            return;
        }

        if (!list.length) {
            channelSummaryRows.innerHTML = '';
            return;
        }

        // Summary panel was removed from UI; keep this hook no-op for compatibility.
        channelSummaryRows.innerHTML = '';
    }

    function populateBrandSelect(list) {
        selectBrand.innerHTML = '<option value="">Select a brand...</option>';
        list.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.id;
            opt.textContent = b.name;
            selectBrand.appendChild(opt);
        });
    }

    function populateUploadSections(channel) {
        sectionOptions = [];
        uploadSectionSelect.innerHTML = '<option value="">Select upload section...</option>';

        const rules = channel?.rules || {};
        const sections = Array.isArray(rules.sections) ? rules.sections : [];
        sections.forEach(s => {
            if (!s || !s.key || !s.label) {
                return;
            }

            // Raw/Final are common-domain sections; Source is auto-attached during variant uploads.
            if (s.key === 'raw_images' || s.key === 'final_plain' || s.key === 'source') {
                return;
            }

            sectionOptions.push({
                key: String(s.key),
                label: String(s.label),
                type: String(s.type || 'final'),
            });
        });

        if (sectionOptions.length === 0) {
            uploadSectionSelect.innerHTML = '<option value="">No channel-specific sections</option>';
            uploadSectionSelect.disabled = true;
            return;
        }

        sectionOptions.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.key;
            opt.textContent = `${s.label} (${s.type})`;
            uploadSectionSelect.appendChild(opt);
        });
        uploadSectionSelect.disabled = false;
    }

    function populateListingChannels(list) {
        const sel = document.getElementById('listingChannelSelect');
        sel.innerHTML = '<option value="">Select channel...</option>';
        list.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            sel.appendChild(opt);
        });
    }

    function showChannelRules() {
        if (!currentChannel) return;
        
        let parts = [];
        const cfg = currentChannel.rules || {};
        
        if (currentChannel.width && currentChannel.height) {
            parts.push(`${currentChannel.width}x${currentChannel.height}px`);
        }
        
        if (cfg.final?.max_file_size_bytes) {
            const mb = (cfg.final.max_file_size_bytes / 1024 / 1024).toFixed(1);
            parts.push(`Max ${mb}MB`);
        }
        
        if (Array.isArray(cfg.final?.formats) && cfg.final.formats.length > 0) {
            parts.push(`Final: ${cfg.final.formats.slice(0, 4).join('/').toUpperCase()}${cfg.final.formats.length > 4 ? '+' : ''}`);
        }

        if (Array.isArray(cfg.source?.formats) && cfg.source.formats.length > 0) {
            parts.push(`Source: ${cfg.source.formats.slice(0, 4).join('/').toUpperCase()}${cfg.source.formats.length > 4 ? '+' : ''}`);
        }

        if (Array.isArray(cfg.sections) && cfg.sections.length > 0) {
            parts.push(`Sections: ${cfg.sections.length}`);
        }
        
        if (currentChannel.background_rule) {
            parts.push(`BG: ${String(currentChannel.background_rule).toLowerCase()}`);
        }

        const fullDetail = [
            currentChannel.width && currentChannel.height ? `Dimensions ${currentChannel.width}x${currentChannel.height}px` : null,
            cfg.final?.max_file_size_bytes ? `Final max ${(cfg.final.max_file_size_bytes / 1024 / 1024).toFixed(1)}MB` : null,
            Array.isArray(cfg.final?.formats) ? `Final formats ${cfg.final.formats.join(', ').toUpperCase()}` : null,
            Array.isArray(cfg.source?.formats) ? `Source formats ${cfg.source.formats.join(', ').toUpperCase()}` : null,
            Array.isArray(cfg.sections) ? `Sections ${cfg.sections.map(s => s.label).join(', ')}` : null,
            currentChannel.background_rule ? `Background ${currentChannel.background_rule}` : null,
        ].filter(Boolean).join(' | ');

        channelRules.textContent = parts.length > 0 ? `Rules: ${parts.join(' | ')}` : 'Rules: default';
        channelRules.title = fullDetail || 'Default channel rules';
        
        channelRulesBox.classList.remove('d-none');
    }

    function updateSelectionStatus() {
        const channel = selectChannel.value ? selectChannel.options[selectChannel.selectedIndex].text : null;
        const brand = selectBrand.value ? selectBrand.options[selectBrand.selectedIndex].text : null;
        const section = uploadSectionSelect.value ? uploadSectionSelect.options[uploadSectionSelect.selectedIndex].text : null;
        
        if (channel && brand && section) {
            selectionStatus.innerHTML = `✅ <strong style="color: #10b981;">Ready to upload</strong> - ${brand} in ${channel} to ${section}`;
        } else if (channel && brand) {
            selectionStatus.innerHTML = `⏳ Selected brand and channel - now select channel-specific section`;
        } else if (brand) {
            selectionStatus.innerHTML = `⏳ Selected brand <strong>${brand}</strong> - now select a channel`;
        } else {
            selectionStatus.innerHTML = `<i class="bi bi-info-circle"></i> Please select Brand, Channel, and upload section to proceed`;
        }
    }

    function enableUpload() {
        uploadZone.style.opacity = '1';
        uploadZone.style.cursor = 'default';
        uploadBrowseBtn.disabled = false;
        uploadSourceBrowseBtn.disabled = false;
        document.getElementById('uploadProgressWrap').classList.add('d-none');
        checkChannelUploadReady();
    }

    function disableUpload() {
        uploadZone.style.opacity = '0.5';
        uploadZone.style.cursor = 'not-allowed';
        uploadBrowseBtn.disabled = true;
        uploadSourceBrowseBtn.disabled = true;
        channelUploadNowBtn.disabled = true;
        channelUploadNowBtn.style.opacity = '0.5';
    }

    function uploadCommonAssets(sectionKey, inputEl, sourceInputEl = null) {
        if (!canManage) {
            showError('You do not have permission to upload files.');
            inputEl.value = '';
            return;
        }

        if (!inputEl.files || inputEl.files.length === 0) {
            return;
        }

        const isRaw = sectionKey === 'raw_images';
        const existingCount = (window.__assetsCache || []).filter(a => String(a.section_key || '') === sectionKey).length;
        const sourceFile = sourceInputEl && sourceInputEl.files ? sourceInputEl.files[0] : null;
        if (!isRaw) {
            if (!sourceFile) {
                showError('Source file is required for final image upload.');
                inputEl.value = '';
                return;
            }
            if (inputEl.files.length !== 1) {
                showError('Upload one final image at a time with its source file.');
                inputEl.value = '';
                return;
            }
        }

        const fd = new FormData();
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        fd.append('section_key', sectionKey);
        fd.append('type', 'final');
        if (!isRaw && sourceFile) {
            fd.append('source_file', sourceFile);
        }

        for (const file of inputEl.files) {
            fd.append('files[]', file);
        }

        const progressWrap = isRaw ? commonRawProgressWrap : commonFinalProgressWrap;
        const progressBar = isRaw ? commonRawProgressBar : commonFinalProgressBar;
        const progressText = isRaw ? commonRawProgressText : commonFinalProgressText;

        progressWrap.classList.remove('d-none');
        progressBar.style.width = '0%';
        progressText.textContent = `Uploading... 0% | Existing images: ${existingCount}`;
        // Keep current gallery visible while upload progresses.
        renderCommonGalleries(window.__assetsCache || [], channels, brands);

        const xhr = new XMLHttpRequest();
        xhr.upload.addEventListener('progress', e => {
            if (e.lengthComputable) {
                const pct = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = pct + '%';
                progressText.textContent = `Uploading... ${pct}% | Existing images: ${existingCount}`;
            }
        });

        xhr.addEventListener('load', () => {
            inputEl.value = '';
            try {
                const data = JSON.parse(xhr.responseText || '{}');
                if (!(xhr.status === 200 || xhr.status === 201) || !data.success) {
                    const msg = data.message || (Array.isArray(data.errors) && data.errors.length > 0 ? data.errors.join(' | ') : 'Upload failed');
                    throw new Error(msg);
                }

                if (Array.isArray(data.errors) && data.errors.length > 0) {
                    showError('Some files were skipped: ' + data.errors.join(' | '));
                }

                progressBar.style.width = '100%';
                progressText.textContent = 'Upload complete | Refreshing list...';
                if (!isRaw && sourceInputEl) {
                    sourceInputEl.value = '';
                    finalPickImageLabel.textContent = 'Choose Final Image';
                    finalPickSourceLabel.textContent = 'Choose Source File';
                    finalPickImageBtn.style.borderColor = '#374151';
                    finalPickSourceBtn.style.borderColor = '#374151';
                    finalDoUploadBtn.disabled = true;
                    finalDoUploadBtn.style.opacity = '0.5';
                    finalUploadCard.classList.add('d-none');
                }
                setTimeout(() => progressWrap.classList.add('d-none'), 1200);
                loadData();
            } catch (err) {
                progressText.textContent = 'Upload failed';
                showError('Common upload failed: ' + err.message);
            }
        });

        xhr.addEventListener('error', () => {
            inputEl.value = '';
            progressText.textContent = 'Network error';
            showError('Common upload failed: Network error');
        });

        xhr.open('POST', `${apiBase}/upload`);
        xhr.send(fd);
    }

    function setCommonAreaViewMode(area, mode) {
        const normalized = mode === 'thumb' ? 'thumb' : 'list';
        if (area === 'raw') {
            commonRawViewMode = normalized;
        } else {
            commonFinalViewMode = normalized;
        }

        rawListViewBtn.style.backgroundColor = commonRawViewMode === 'list' ? '#3b82f6' : '#1f2937';
        rawListViewBtn.style.color = commonRawViewMode === 'list' ? '#fff' : '#9ca3af';
        rawListViewBtn.style.borderColor = commonRawViewMode === 'list' ? '#3b82f6' : '#374151';
        rawThumbViewBtn.style.backgroundColor = commonRawViewMode === 'thumb' ? '#3b82f6' : '#1f2937';
        rawThumbViewBtn.style.color = commonRawViewMode === 'thumb' ? '#fff' : '#9ca3af';
        rawThumbViewBtn.style.borderColor = commonRawViewMode === 'thumb' ? '#3b82f6' : '#374151';

        finalListViewBtn.style.backgroundColor = commonFinalViewMode === 'list' ? '#3b82f6' : '#1f2937';
        finalListViewBtn.style.color = commonFinalViewMode === 'list' ? '#fff' : '#9ca3af';
        finalListViewBtn.style.borderColor = commonFinalViewMode === 'list' ? '#3b82f6' : '#374151';
        finalThumbViewBtn.style.backgroundColor = commonFinalViewMode === 'thumb' ? '#3b82f6' : '#1f2937';
        finalThumbViewBtn.style.color = commonFinalViewMode === 'thumb' ? '#fff' : '#9ca3af';
        finalThumbViewBtn.style.borderColor = commonFinalViewMode === 'thumb' ? '#3b82f6' : '#374151';

        renderCommonGalleries(window.__assetsCache || [], channels, brands);
    }

    function setFocusTab(tab) {
        currentFocusTab = ['raw', 'final', 'channels'].includes(tab) ? tab : 'raw';

        const buttonMap = {
            raw: focusTabRaw,
            final: focusTabFinal,
            channels: focusTabChannels,
        };

        Object.keys(buttonMap).forEach(key => {
            const btn = buttonMap[key];
            const isActive = key === currentFocusTab;
            btn.style.backgroundColor = isActive ? '#3b82f6' : '#1f2937';
            btn.style.color = isActive ? '#fff' : '#9ca3af';
            btn.style.borderColor = isActive ? '#3b82f6' : '#374151';
        });

        const channelsMode = currentFocusTab === 'channels';
        const rawMode = currentFocusTab === 'raw';
        const finalMode = currentFocusTab === 'final';

        step1Block.classList.toggle('d-none', !channelsMode);
        step2Block.classList.toggle('d-none', !channelsMode);
        step3Block.classList.toggle('d-none', !channelsMode);
        listingsBlock.classList.toggle('d-none', !channelsMode);

        commonBlock.classList.toggle('d-none', channelsMode);
        commonRawPanelCol.classList.toggle('d-none', !rawMode);
        commonFinalPanelCol.classList.toggle('d-none', !finalMode);
    }

    function performUpload() {
        if (!canManage) {
            alert('You do not have permission to upload files.');
            return;
        }

        if (!selectChannel.value) {
            alert('Please select a channel first');
            return;
        }

        if (!selectBrand.value) {
            alert('Please select a brand first');
            return;
        }

        if (!uploadSectionSelect.value) {
            alert('Please select an upload section first');
            return;
        }

        if (assetFileInput.files.length === 0) {
            alert('Please select files to upload');
            return;
        }

        if (!assetSourceInput.files || !assetSourceInput.files[0]) {
            alert('Please choose source file first');
            return;
        }

        if (assetFileInput.files.length !== 1) {
            alert('Upload one section image at a time with its source file');
            return;
        }

        const fd = new FormData();
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        fd.append('asset_group_id', selectBrand.value);
        fd.append('channel_id', selectChannel.value);
        const selectedSection = sectionOptions.find(s => s.key === uploadSectionSelect.value);
        fd.append('section_key', uploadSectionSelect.value);
        fd.append('type', selectedSection ? selectedSection.type : 'final');
        fd.append('source_file', assetSourceInput.files[0]);

        for (let file of assetFileInput.files) {
            fd.append('files[]', file);
        }

        const progressWrap = document.getElementById('uploadProgressWrap');
        const progressBar = document.getElementById('uploadProgressBar');
        const uploadStatus = document.getElementById('uploadStatus');

        progressWrap.classList.remove('d-none');
        uploadBrowseBtn.disabled = true;

        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', e => {
            if (e.lengthComputable) {
                const pct = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = pct + '%';
                uploadStatus.textContent = `⏳ Uploading... ${pct}%`;
            }
        });

        xhr.addEventListener('load', () => {
            uploadBrowseBtn.disabled = false;
            let payload = null;
            try {
                payload = JSON.parse(xhr.responseText || '{}');
            } catch (e) {
                payload = null;
            }

            if (!(xhr.status === 200 || xhr.status === 201)) {
                const message = payload?.message || xhr.statusText || 'Upload failed';
                uploadStatus.textContent = '❌ Upload failed: ' + message;
                uploadStatus.style.color = '#ef4444';
                return;
            }

            const uploadedCount = parseInt(payload?.uploaded || '0', 10);
            const errors = Array.isArray(payload?.errors) ? payload.errors : [];
            if (!payload || payload.success !== true || uploadedCount <= 0) {
                const reason = payload?.message || (errors.length ? errors.join(' | ') : 'No files were saved');
                uploadStatus.textContent = '❌ Upload failed: ' + reason;
                uploadStatus.style.color = '#ef4444';
                showError('Channel upload failed: ' + reason);
                return;
            }

            uploadStatus.textContent = errors.length
                ? `✅ Uploaded ${uploadedCount} file(s) with warnings`
                : `✅ Uploaded ${uploadedCount} file(s)`;
            uploadStatus.style.color = '#10b981';
            if (errors.length) {
                showError('Some files were skipped: ' + errors.join(' | '));
            }

            setTimeout(() => {
                progressWrap.classList.add('d-none');
                assetFileInput.value = '';
                assetSourceInput.value = '';
                uploadImageLabel.textContent = 'Choose Image';
                uploadSourceLabel.textContent = 'Choose Source';
                uploadBrowseBtn.style.borderColor = '#374151';
                uploadSourceBrowseBtn.style.borderColor = '#374151';
                uploadSourceName.textContent = 'Select channel, brand, and section to enable upload.';
                channelUploadNowBtn.disabled = true;
                channelUploadNowBtn.style.opacity = '0.5';
                loadAssets();
            }, 900);
        });

        xhr.addEventListener('error', () => {
            uploadBrowseBtn.disabled = false;
            uploadStatus.textContent = '❌ Network error';
            uploadStatus.style.color = '#ef4444';
        });

        xhr.open('POST', `${apiBase}/upload`);
        xhr.send(fd);
    }

    function loadAssets() {
        console.log('📡 Loading assets...');
        fetch(`${apiBase}/data`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const assets = data.data.assets || [];
                window.__assetsCache = assets;
                lightboxItems = assets.filter(a => String(a.mime_type || '').startsWith('image/') && (a.file_url || a.thumbnail_url));
                renderAssets(assets, channels, brands);
                renderCommonGalleries(assets, channels, brands);
                if (setupManagerModalEl.classList.contains('show')) {
                    renderSetupManager();
                }
            })
            .catch(err => console.error('Error loading assets:', err));
    }

    function getFileTypeMeta(extRaw) {
        const ext = String(extRaw || '').toLowerCase();
        const map = {
            psd:  { bg:'#001E36', color:'#31A8FF', label:'Ps',  title:'Photoshop' },
            ai:   { bg:'#2b1700', color:'#FF9A00', label:'Ai',  title:'Illustrator' },
            indd: { bg:'#2E001E', color:'#FF3366', label:'Id',  title:'InDesign' },
            cdr:  { bg:'#003319', color:'#00A550', label:'CDR', title:'CorelDRAW' },
            xd:   { bg:'#2B002B', color:'#FF61F6', label:'Xd',  title:'Adobe XD' },
            fig:  { bg:'#1A1A2E', color:'#a855f7', label:'Fig', title:'Figma' },
            eps:  { bg:'#4A1500', color:'#FF6000', label:'EPS', title:'EPS' },
            svg:  { bg:'#1A1A00', color:'#F0AB00', label:'SVG', title:'SVG' },
            pdf:  { bg:'#3D0000', color:'#FA0F00', label:'PDF', title:'PDF' },
            jpg:  { bg:'#003D3D', color:'#06b6d4', label:'JPG', title:'JPEG' },
            jpeg: { bg:'#003D3D', color:'#06b6d4', label:'JPG', title:'JPEG' },
            png:  { bg:'#001A40', color:'#3b82f6', label:'PNG', title:'PNG' },
            webp: { bg:'#003320', color:'#10b981', label:'WP',  title:'WebP' },
            gif:  { bg:'#2D0040', color:'#a855f7', label:'GIF', title:'GIF' },
            tif:  { bg:'#1A1A00', color:'#d4af37', label:'TIF', title:'TIFF' },
            tiff: { bg:'#1A1A00', color:'#d4af37', label:'TIF', title:'TIFF' },
            zip:  { bg:'#1A1000', color:'#f59e0b', label:'ZIP', title:'ZIP' },
            rar:  { bg:'#1A1000', color:'#f59e0b', label:'RAR', title:'RAR' },
        };
        return map[ext] || null;
    }

    function getFileTypeIcon(fileName) {
        const ext = (String(fileName || '').split('.').pop() || '').toLowerCase();
        const s = getFileTypeMeta(ext);
        if (!s) return `<div style="width:34px;height:34px;border-radius:4px;border:1px solid #374151;display:flex;align-items:center;justify-content:center;background:#1f2937;" title="${ext.toUpperCase() || 'FILE'}"><span style="font-size:0.55rem;font-weight:700;color:#9ca3af;text-transform:uppercase;">${ext.toUpperCase() || 'FILE'}</span></div>`;
        return `<div style="width:34px;height:34px;border-radius:4px;border:1px solid ${s.color}33;display:flex;align-items:center;justify-content:center;background:${s.bg};" title="${s.title}"><span style="font-size:0.6rem;font-weight:800;color:${s.color};letter-spacing:-0.5px;">${s.label}</span></div>`;
    }

    function getFileTypeIconLg(fileName) {
        const ext = (String(fileName || '').split('.').pop() || '').toLowerCase();
        const s = getFileTypeMeta(ext);
        if (!s) return `<div style="width:44px;height:44px;border-radius:4px;border:1px solid #374151;display:flex;align-items:center;justify-content:center;background:#1f2937;" title="${ext.toUpperCase() || 'FILE'}"><span style="font-size:0.6rem;font-weight:700;color:#9ca3af;text-transform:uppercase;">${ext.toUpperCase() || 'FILE'}</span></div>`;
        return `<div style="width:44px;height:44px;border-radius:4px;border:1px solid ${s.color}33;display:flex;align-items:center;justify-content:center;background:${s.bg};" title="${s.title}"><span style="font-size:0.7rem;font-weight:800;color:${s.color};letter-spacing:-0.5px;">${s.label}</span></div>`;
    }

    function getFileTypeMiniIcon(fileName) {
        const ext = (String(fileName || '').split('.').pop() || '').toLowerCase();
        const s = getFileTypeMeta(ext);
        const label = s ? s.label : (ext || 'FILE').slice(0, 3).toUpperCase();
        const color = s ? s.color : '#93c5fd';
        const bg = s ? s.bg : '#111827';
        return `<span class="asset-type-mini" style="color:${color};background:${bg};border-color:${color}33;" title="${s ? s.title : label}">${label}</span>`;
    }

    function renderCommonGalleries(assets, chList, brList) {
        const strictRaw = assets.filter(a => String(a.section_key || '') === 'raw_images');
        const strictFinal = assets.filter(a => String(a.section_key || '') === 'final_plain');

        let rawAssets = strictRaw;
        let finalAssets = strictFinal;

        if (rawAssets.length === 0) {
            rawAssets = assets.filter(a => {
                const key = String(a.section_key || '').toLowerCase();
                const label = String(a.section_label || '').toLowerCase();
                const type = String(a.type || '').toLowerCase();
                const linkedSource = parseInt(a.source_asset_id || '0', 10) > 0;
                if (key === 'raw_images' || label.includes('raw image')) {
                    return true;
                }
                return type === 'final' && !linkedSource && !['final_plain', 'final_watermark', 'final_template'].includes(key);
            });
        }

        if (finalAssets.length === 0) {
            finalAssets = assets.filter(a => {
                const key = String(a.section_key || '').toLowerCase();
                const label = String(a.section_label || '').toLowerCase();
                if (key === 'final_plain') {
                    return true;
                }
                return label === 'final images' || label === 'final image';
            });
        }

        rawTotalCount.textContent = `(${rawAssets.length})`;
        finalTotalCount.textContent = `(${finalAssets.length})`;

        renderCommonGrid(rawAssets, commonRawGrid, 'No raw images uploaded yet', chList, brList, commonRawViewMode, assets);
        renderCommonGrid(finalAssets, commonFinalGrid, 'No final images uploaded yet', chList, brList, commonFinalViewMode, assets);
    }

    function renderCommonGrid(list, container, emptyText, chList, brList, viewMode, allAssets) {
        if (!container) {
            return;
        }

        if (!list.length) {
            container.innerHTML = `<div class="small" style="color:#6b7280;">${emptyText}</div>`;
            return;
        }

        if (viewMode === 'list') {
            container.innerHTML = `
                <div class="table-responsive">
                    <table class="table table-sm align-middle" style="color:#e5e7eb;margin-bottom:0;">
                        <thead>
                            <tr>
                                <th style="color:#9ca3af;width:44px;">No.</th>
                                <th style="color:#9ca3af;width:42px;">Icon</th>
                                <th style="color:#9ca3af;">File</th>
                                <th style="color:#9ca3af;">Size</th>
                                <th style="color:#9ca3af;width:220px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${list.map((asset, index) => {
                                const previewUrl = asset.file_url || asset.thumbnail_url || '';
                                const isImageType = String(asset.mime_type || '').startsWith('image/');
                                const preview = (asset.thumbnail_url || (isImageType && asset.file_url))
                                    ? `<img src="${asset.thumbnail_url || asset.file_url}" alt="${(asset.file_name || '').replace(/"/g, '&quot;')}" class="asset-preview-image" data-asset-id="${asset.id}" data-preview-url="${previewUrl}" style="width:34px;height:34px;object-fit:cover;border-radius:4px;border:1px solid #374151;cursor:zoom-in;">`
                                    : getFileTypeIcon(asset.file_name);
                                // find linked source file
                                const srcId = parseInt(asset.source_asset_id || '0', 10);
                                const srcAsset = srcId && allAssets ? (allAssets || []).find(a => parseInt(a.id) === srcId) : null;
                                const isFinal = String(asset.section_key || '') === 'final_plain';
                                const srcRow = (isFinal && srcAsset) ? `
                                    <tr style="border-bottom:1px solid #1f2937;background:linear-gradient(90deg,#0c1524 0%,#0b1220 100%);">
                                        <td></td>
                                        <td></td>
                                        <td colspan="3">
                                            <div class="asset-link-wrap">
                                                <span class="asset-link-rail"></span>
                                                <span class="asset-link-node"></span>
                                                <div class="asset-link-card">
                                                    ${getFileTypeMiniIcon(srcAsset.file_name)}
                                                    <span class="asset-source-tag">attached source</span>
                                                    <span class="asset-source-name" title="${(srcAsset.file_name || '').replace(/"/g, '&quot;')}">${srcAsset.file_name || ''}</span>
                                                    <span style="color:#64748b;font-size:0.65rem;white-space:nowrap;">${formatFileSize(srcAsset.file_size || 0)}</span>
                                                    <button type="button" class="btn btn-sm download-asset-action" data-asset-id="${srcAsset.id}" title="Download source" style="background:#1e293b;color:#93c5fd;border-color:#334155;padding:0.05rem 0.26rem;font-size:0.62rem;"><i class="bi bi-download"></i></button>
                                                    <button type="button" class="btn btn-sm delete-source-action" data-source-id="${srcAsset.id}" data-source-name="${(srcAsset.file_name || '').replace(/"/g, '&quot;')}" title="Delete source" style="background:#7f1d1d;color:#fecaca;border-color:#7f1d1d;padding:0.05rem 0.26rem;font-size:0.62rem;"><i class="bi bi-trash"></i></button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>` : '';
                                const missingSrc = isFinal && !srcAsset;
                                return `
                                    <tr style="border-bottom:${(isFinal && srcAsset) ? '1px solid #1f2937' : '1px solid #2d3748'};">
                                        <td style="color:#9ca3af;">${index + 1}</td>
                                        <td>${preview}</td>
                                        <td style="color:#e5e7eb;max-width:200px;">
                                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${(asset.file_name||'').replace(/"/g,'&quot;')}">${asset.file_name || 'File'}</div>
                                        </td>
                                        <td style="color:#9ca3af;">${formatFileSize(asset.file_size || 0)}</td>
                                        <td style="white-space:nowrap;">
                                            <button type="button" class="btn btn-sm download-asset-action" data-asset-id="${asset.id}" title="Download image" style="background:#1e293b;color:#93c5fd;border-color:#334155;padding:0.1rem 0.4rem;font-size:0.72rem;"><i class="bi bi-download"></i></button>
                                            <button type="button" class="btn btn-sm common-update-action" data-asset-id="${asset.id}" title="Replace image" style="background:#374151;color:#e5e7eb;border-color:#374151;padding:0.1rem 0.4rem;font-size:0.72rem;"><i class="bi bi-arrow-repeat"></i></button>
                                            ${missingSrc ? `<button type="button" class="btn btn-sm attach-source-action" data-asset-id="${asset.id}" title="Attach source file" style="background:#78350f;color:#fde68a;border-color:#92400e;padding:0.1rem 0.4rem;font-size:0.72rem;"><i class="bi bi-paperclip"></i> +Src</button>` : ''}
                                            <button type="button" class="btn btn-sm common-delete-action" data-asset-id="${asset.id}" data-asset-name="${(asset.file_name || '').replace(/"/g, '&quot;')}" title="Delete" style="background:#7f1d1d;color:#fecaca;border-color:#7f1d1d;padding:0.1rem 0.4rem;font-size:0.72rem;"><i class="bi bi-trash"></i></button>
                                        </td>
                                    </tr>
                                    ${srcRow}
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        } else {
            container.innerHTML = list.map(asset => {
            const previewUrl = asset.file_url || asset.thumbnail_url || '';
            const isImageType = String(asset.mime_type || '').startsWith('image/');
            const preview = (asset.thumbnail_url || (isImageType && asset.file_url))
                ? `<img src="${asset.thumbnail_url || asset.file_url}" alt="${(asset.file_name || '').replace(/"/g, '&quot;')}" class="asset-preview-image" data-asset-id="${asset.id}" data-preview-url="${previewUrl}" style="width:44px;height:44px;object-fit:cover;border-radius:4px;border:1px solid #374151;cursor:zoom-in;">`
                : getFileTypeIconLg(asset.file_name);
            const srcId = parseInt(asset.source_asset_id || '0', 10);
            const srcAsset = srcId && allAssets ? (allAssets || []).find(a => parseInt(a.id) === srcId) : null;
            const isFinal = String(asset.section_key || '') === 'final_plain';
            const missingSrc = isFinal && !srcAsset;
            const srcBadge = (isFinal && srcAsset)
                ? `<div class="asset-link-wrap mt-1">
                        <span class="asset-link-rail"></span>
                        <span class="asset-link-node"></span>
                        <div class="asset-link-card">
                            ${getFileTypeMiniIcon(srcAsset.file_name)}
                            <span class="asset-source-tag">attached</span>
                            <span style="font-size:0.62rem;color:#cbd5e1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:88px;" title="${(srcAsset.file_name||'').replace(/"/g,'&quot;')}">${srcAsset.file_name || ''}</span>
                            <button type="button" class="btn btn-sm download-asset-action" data-asset-id="${srcAsset.id}" title="Download source" style="background:#1e293b;color:#93c5fd;border-color:#334155;padding:0.04rem 0.2rem;font-size:0.58rem;"><i class="bi bi-download"></i></button>
                            <button type="button" class="btn btn-sm delete-source-action" data-source-id="${srcAsset.id}" data-source-name="${(srcAsset.file_name||'').replace(/"/g,'&quot;')}" title="Delete source" style="background:#7f1d1d;color:#fecaca;border-color:#7f1d1d;padding:0.04rem 0.2rem;font-size:0.58rem;"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>`
                : (missingSrc ? `<div class="mt-1"><button type="button" class="btn btn-sm attach-source-action" data-asset-id="${asset.id}" style="background:#78350f;color:#fde68a;border-color:#92400e;padding:0.1rem 0.4rem;font-size:0.65rem;"><i class="bi bi-paperclip"></i> + Source</button></div>` : '');

            return `
                <div style="background:#111827;border:1px solid #374151;border-radius:6px;padding:0.45rem;min-width:210px;max-width:260px;">
                    <div class="d-flex gap-2 align-items-start">
                        ${preview}
                        <div style="min-width:0;flex:1;">
                            <div class="small" style="color:#e5e7eb;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${(asset.file_name || '').replace(/"/g, '&quot;')}">${asset.file_name || 'File'}</div>
                            <div class="small" style="color:#9ca3af;">${formatFileSize(asset.file_size || 0)}</div>
                            ${srcBadge}
                            <div class="mt-1 d-flex gap-1">
                                <button type="button" class="btn btn-sm download-asset-action" data-asset-id="${asset.id}" title="Download" style="background:#1e293b;color:#93c5fd;border-color:#334155;padding:0.1rem 0.4rem;font-size:0.72rem;"><i class="bi bi-download"></i></button>
                                <button type="button" class="btn btn-sm common-update-action" data-asset-id="${asset.id}" title="Replace" style="background:#374151;color:#e5e7eb;border-color:#374151;padding:0.1rem 0.4rem;font-size:0.72rem;"><i class="bi bi-arrow-repeat"></i></button>
                                <button type="button" class="btn btn-sm common-delete-action" data-asset-id="${asset.id}" data-asset-name="${(asset.file_name || '').replace(/"/g, '&quot;')}" title="Delete" style="background:#7f1d1d;color:#fecaca;border-color:#7f1d1d;padding:0.1rem 0.4rem;font-size:0.72rem;"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            }).join('');
        }

        container.querySelectorAll('.common-update-action').forEach(el => {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                const assetId = parseInt(this.getAttribute('data-asset-id') || '0');
                requestReplaceAssetFile(assetId);
            });
        });

        container.querySelectorAll('.attach-source-action').forEach(el => {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                const assetId = parseInt(this.getAttribute('data-asset-id') || '0');
                requestAttachSource(assetId);
            });
        });

        container.querySelectorAll('.download-asset-action').forEach(el => {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                const assetId = parseInt(this.getAttribute('data-asset-id') || '0');
                downloadAssetById(assetId);
            });
        });

        container.querySelectorAll('.delete-source-action').forEach(el => {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                const sourceId = parseInt(this.getAttribute('data-source-id') || '0');
                const sourceName = this.getAttribute('data-source-name') || 'source file';
                deleteAssetById(sourceId, sourceName);
            });
        });

        container.querySelectorAll('.common-delete-action').forEach(el => {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                const assetId = parseInt(this.getAttribute('data-asset-id') || '0');
                const fileName = this.getAttribute('data-asset-name') || 'this file';
                deleteAssetById(assetId, fileName);
            });
        });

        bindPreviewInteractions(container);
    }

    function renderAssets(assets, chList, brList) {
        const assetRows = document.getElementById('assetRows');

        // Only show channel-specific assets (skip common raw/final and standalone source files)
        // Common assets have channel_id = 0/null – they are shown in the Raw/Final panels
        const channelAssets = assets.filter(a => {
            const hasChannel = parseInt(a.channel_id || '0') > 0;
            const type = String(a.type || '');
            return hasChannel && type !== 'source';
        });

        if (channelAssets.length === 0) {
            assetRows.innerHTML = '<tr><td colspan="6" class="text-center py-3" style="color: #9ca3af;"><small>No channel assets yet. Upload from the Channels tab.</small></td></tr>';
            return;
        }

        // Group by channel
        const groups = {}; // channelId -> { channel, items[] }
        channelAssets.forEach(asset => {
            const cid = String(asset.channel_id || '0');
            if (!groups[cid]) {
                groups[cid] = { channel: chList.find(c => String(c.id) === cid) || { id: cid, name: 'Unknown' }, items: [] };
            }
            groups[cid].items.push(asset);
        });

        let html = '';
        Object.values(groups).forEach(group => {
            const ch = group.channel;
            const count = group.items.length;
            const gid = `channelGroup_${ch.id}`;
            html += `
                <tr class="channel-group-header" data-group="${gid}" style="background:linear-gradient(90deg,#0f172a 0%,#111b2d 100%);cursor:pointer;user-select:none;">
                    <td colspan="6" style="padding:0.5rem 0.6rem;">
                        <div class="d-flex align-items-center gap-2">
                            <span class="ch-toggle-icon" style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:4px;background:#18263a;color:#38bdf8;font-size:0.85rem;font-weight:700;border:1px solid #334155;">−</span>
                            <span style="color:#06b6d4;font-weight:600;font-size:0.85rem;">${ch.name || 'Channel'}</span>
                            ${ch.short_code ? `<span style="font-size:0.66rem;background:#1f2937;color:#9ca3af;border:1px solid #334155;border-radius:999px;padding:0.04rem 0.35rem;">${ch.short_code}</span>` : ''}
                            <span style="font-size:0.68rem;color:#94a3b8;background:#1e293b;border:1px solid #334155;border-radius:999px;padding:0.04rem 0.35rem;">${count} asset${count !== 1 ? 's' : ''}</span>
                        </div>
                    </td>
                </tr>
            `;
            group.items.forEach((asset, idx) => {
                const br = brList.find(b => String(b.id) === String(asset.asset_group_id)) || { name: '?' };
                const previewUrl = asset.file_url || asset.thumbnail_url || '';
                const isImgType = String(asset.mime_type || '').startsWith('image/');
                const preview = (asset.thumbnail_url || (isImgType && asset.file_url))
                    ? `<img src="${asset.thumbnail_url || asset.file_url}" class="asset-preview-image" data-asset-id="${asset.id}" data-preview-url="${previewUrl}" style="width:34px;height:34px;object-fit:cover;border-radius:4px;border:1px solid #374151;cursor:zoom-in;">`
                    : getFileTypeIcon(asset.file_name);
                // Find source asset
                const srcId = parseInt(asset.source_asset_id || '0', 10);
                const srcAsset = srcId ? assets.find(a => parseInt(a.id) === srcId) : null;
                const srcCell = srcAsset
                    ? `<div class="asset-link-wrap mt-1">
                           <span class="asset-link-rail"></span>
                           <span class="asset-link-node"></span>
                           <div class="asset-link-card">
                               ${getFileTypeMiniIcon(srcAsset.file_name)}
                               <span class="asset-source-tag">attached</span>
                               <span style="font-size:0.62rem;color:#cbd5e1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:95px;" title="${(srcAsset.file_name||'').replace(/"/g,'&quot;')}">${srcAsset.file_name}</span>
                               <button type="button" class="btn btn-sm download-asset-action" data-asset-id="${srcAsset.id}" title="Download source" style="background:#1e293b;color:#93c5fd;border-color:#334155;padding:0.04rem 0.2rem;font-size:0.58rem;"><i class="bi bi-download"></i></button>
                               <button type="button" class="btn btn-sm delete-source-action" data-source-id="${srcAsset.id}" data-source-name="${(srcAsset.file_name||'').replace(/"/g,'&quot;')}" title="Delete source" style="background:#7f1d1d;color:#fecaca;border-color:#7f1d1d;padding:0.04rem 0.2rem;font-size:0.58rem;"><i class="bi bi-trash"></i></button>
                           </div>
                       </div>`
                    : `<div class="mt-1"><button type="button" class="btn btn-sm attach-source-action" data-asset-id="${asset.id}" title="Attach source file" style="background:#78350f;color:#fde68a;border-color:#92400e;padding:0.08rem 0.35rem;font-size:0.65rem;"><i class="bi bi-paperclip"></i> + Source</button></div>`;
                html += `
                    <tr class="channel-group-row" data-group="${gid}" style="border-bottom:1px solid #1f2937;">
                        <td style="color:#6b7280;padding-left:1.5rem;">${idx + 1}</td>
                        <td>${preview}</td>
                        <td>
                            <div style="color:#e5e7eb;font-size:0.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;" title="${(asset.file_name||'').replace(/"/g,'&quot;')}">${asset.file_name || 'File'}</div>
                            ${srcCell}
                        </td>
                        <td><small style="color:#9ca3af;">${br.name}</small><br><small class="badge" style="background:#1f2937;color:#10b981;font-size:0.65rem;">${asset.section_label || asset.section_key || asset.type}</small></td>
                        <td><small style="color:#9ca3af;">${formatFileSize(asset.file_size || 0)}</small></td>
                        <td>
                            <div class="d-flex gap-1 justify-content-end">
                                <button type="button" class="btn btn-sm download-asset-action" data-asset-id="${asset.id}" title="Download image" style="background:#1e293b;color:#93c5fd;border-color:#334155;padding:0.15rem 0.4rem;">
                                    <i class="bi bi-download"></i>
                                </button>
                                <button type="button" class="btn btn-sm asset-update-action" data-asset-id="${asset.id}" title="Replace image" style="background:#374151;color:#e5e7eb;border-color:#374151;padding:0.15rem 0.4rem;">
                                    <i class="bi bi-arrow-repeat" style="color:#93c5fd;"></i>
                                </button>
                                <button type="button" class="btn btn-sm asset-delete-action" data-asset-id="${asset.id}" data-asset-name="${(asset.file_name || '').replace(/"/g, '&quot;')}" title="Delete" style="background:#7f1d1d;color:#fecaca;border-color:#7f1d1d;padding:0.15rem 0.4rem;">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
        });

        assetRows.innerHTML = html;

        assetRows.querySelectorAll('.asset-update-action').forEach(el => {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                requestReplaceAssetFile(parseInt(this.getAttribute('data-asset-id') || '0'));
            });
        });
        assetRows.querySelectorAll('.attach-source-action').forEach(el => {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                requestAttachSource(parseInt(this.getAttribute('data-asset-id') || '0'));
            });
        });
        assetRows.querySelectorAll('.download-asset-action').forEach(el => {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                downloadAssetById(parseInt(this.getAttribute('data-asset-id') || '0'));
            });
        });
        assetRows.querySelectorAll('.delete-source-action').forEach(el => {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                deleteAssetById(
                    parseInt(this.getAttribute('data-source-id') || '0'),
                    this.getAttribute('data-source-name') || 'source file'
                );
            });
        });
        assetRows.querySelectorAll('.asset-delete-action').forEach(el => {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                deleteAssetById(parseInt(this.getAttribute('data-asset-id') || '0'), this.getAttribute('data-asset-name') || 'this file');
            });
        });
        assetRows.querySelectorAll('.channel-group-header').forEach(el => {
            el.addEventListener('click', function() {
                const gid = this.getAttribute('data-group') || '';
                if (!gid) {
                    return;
                }
                const rows = assetRows.querySelectorAll(`.channel-group-row[data-group="${gid}"]`);
                if (!rows.length) {
                    return;
                }
                const currentlyHidden = Array.from(rows).every(r => r.style.display === 'none');
                rows.forEach(r => {
                    r.style.display = currentlyHidden ? '' : 'none';
                });
                const icon = this.querySelector('.ch-toggle-icon');
                if (icon) {
                    icon.textContent = currentlyHidden ? '−' : '+';
                }
            });
        });
        bindPreviewInteractions(assetRows);
    }

    function deleteAssetById(assetId, fileName) {
        if (!assetId) {
            return;
        }

        if (!confirm(`Delete ${fileName}? This will remove it from database and server disk.`)) {
            return;
        }

        const fd = new FormData();
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        fetch(`${apiBase}/${assetId}/delete`, {
            method: 'POST',
            body: fd,
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to delete asset');
                }
                loadAssets();
            })
            .catch(err => showError('Failed to delete asset: ' + err.message));
    }

    function downloadAssetById(assetId) {
        if (!assetId) {
            return;
        }

        const asset = (window.__assetsCache || []).find(a => parseInt(a.id || '0', 10) === parseInt(assetId, 10));
        if (!asset) {
            showError('Unable to find file for download. Refresh and try again.');
            return;
        }

        const fileUrl = asset.file_url || asset.thumbnail_url || '';
        if (!fileUrl) {
            showError('File URL is not available for this asset.');
            return;
        }

        const link = document.createElement('a');
        link.href = fileUrl;
        link.download = asset.file_name || 'file';
        link.target = '_blank';
        link.rel = 'noopener';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function requestReplaceAssetFile(assetId) {
        if (!canManage) {
            showError('You do not have permission to update images.');
            return;
        }
        replacingAssetId = assetId;
        assetReplaceInput.value = '';
        assetReplaceInput.click();
    }

    function requestAttachSource(assetId) {
        if (!canManage) {
            showError('You do not have permission to manage assets.');
            return;
        }
        attachingSourceToId = assetId;
        assetAttachSourceInput.value = '';
        assetAttachSourceInput.click();
    }

    function handleAttachSourceFile() {
        if (!attachingSourceToId) return;
        const file = assetAttachSourceInput.files && assetAttachSourceInput.files[0] ? assetAttachSourceInput.files[0] : null;
        if (!file) {
            attachingSourceToId = null;
            return;
        }
        const targetId = attachingSourceToId;
        attachingSourceToId = null;

        const fd = new FormData();
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        fd.append('source_file', file);

        fetch(`${apiBase}/${targetId}/attach-source`, {
            method: 'POST',
            body: fd,
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.message || 'Failed to attach source');
                assetAttachSourceInput.value = '';
                loadAssets();
            })
            .catch(err => {
                assetAttachSourceInput.value = '';
                showError('Failed to attach source: ' + err.message);
            });
    }

    function handleReplaceAssetFile() {
        if (!replacingAssetId) {
            return;
        }
        const file = assetReplaceInput.files && assetReplaceInput.files[0] ? assetReplaceInput.files[0] : null;
        if (!file) {
            replacingAssetId = null;
            return;
        }

        const fd = new FormData();
        fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        fd.append('file', file);

        fetch(`${apiBase}/${replacingAssetId}/update-file`, {
            method: 'POST',
            body: fd,
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to update image');
                }
                replacingAssetId = null;
                assetReplaceInput.value = '';
                loadAssets();
            })
            .catch(err => {
                replacingAssetId = null;
                assetReplaceInput.value = '';
                showError('Failed to update image: ' + err.message);
            });
    }

    function bindPreviewInteractions(rootEl) {
        if (!rootEl) {
            return;
        }

        rootEl.querySelectorAll('.asset-preview-image').forEach(el => {
            const previewUrl = el.getAttribute('data-preview-url') || '';
            const assetId = parseInt(el.getAttribute('data-asset-id') || '0');

            el.addEventListener('mouseenter', e => {
                if (!previewUrl) {
                    return;
                }
                hoverPreviewImg.src = previewUrl;
                hoverPreviewBox.classList.remove('d-none');
                hoverPreviewBox.style.left = (e.clientX + 16) + 'px';
                hoverPreviewBox.style.top = (e.clientY + 16) + 'px';
            });

            el.addEventListener('mousemove', e => {
                if (hoverPreviewBox.classList.contains('d-none')) {
                    return;
                }
                hoverPreviewBox.style.left = (e.clientX + 16) + 'px';
                hoverPreviewBox.style.top = (e.clientY + 16) + 'px';
            });

            el.addEventListener('mouseleave', () => {
                hoverPreviewBox.classList.add('d-none');
            });

            el.addEventListener('click', e => {
                e.preventDefault();
                if (!assetId) {
                    return;
                }
                openLightbox(assetId);
            });
        });
    }

    function openLightbox(assetId) {
        if (!lightboxItems.length) {
            return;
        }
        const idx = lightboxItems.findIndex(a => parseInt(a.id) === parseInt(assetId));
        lightboxIndex = idx >= 0 ? idx : 0;
        renderLightboxCurrent();
        assetLightboxModal.show();
    }

    function moveLightbox(step) {
        if (!lightboxItems.length) {
            return;
        }
        lightboxIndex = (lightboxIndex + step + lightboxItems.length) % lightboxItems.length;
        renderLightboxCurrent();
    }

    function renderLightboxCurrent() {
        if (!lightboxItems.length || lightboxIndex < 0 || lightboxIndex >= lightboxItems.length) {
            return;
        }
        const item = lightboxItems[lightboxIndex];
        const src = item.file_url || item.thumbnail_url || '';
        assetLightboxImg.src = src;
        assetLightboxTitle.textContent = `${item.file_name || 'Image'} (${lightboxIndex + 1}/${lightboxItems.length})`;
    }

    function formatFileSize(bytes) {
        const sizes = ['Bytes', 'KB', 'MB'];
        if (bytes === 0) return '0 Bytes';
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return Math.round((bytes / Math.pow(1024, i)) * 100) / 100 + ' ' + sizes[i];
    }

    function showError(msg) {
        console.error('❌ Error message:', msg);
        const alert = document.getElementById('statusAlert');
        alert.innerHTML = `
            <div style="background-color: #7f1d1d; border-left: 4px solid #ef4444; color: #fecaca; padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem;">
                <i class="bi bi-exclamation-triangle"></i> <strong>Error:</strong> ${msg}
            </div>
        `;
    }

    // Listings form
    document.getElementById('listingForm').addEventListener('submit', e => {
        e.preventDefault();
        if (!canManage) {
            alert('No permission');
            return;
        }
        const fd = new FormData(e.target);
        fetch(`${apiBase}/listings`, {
            method: 'POST',
            body: fd
        }).then(r => r.json()).then(data => {
            if (data.success) {
                e.target.reset();
                loadData();
            }
        });
    });
});
</script>
