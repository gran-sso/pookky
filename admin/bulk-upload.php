<?php
// admin/bulk-upload.php
// Bulk Product Import & Catalog Management Interface

$page_title = 'Bulk Product Import';
$active_menu = 'bulk_upload';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Ensure permissions
require_admin_permission('products');
?>

<div class="row g-4">
    <!-- Header Hero Banner -->
    <div class="col-12">
        <div class="card border-0 shadow-sm p-4 text-white position-relative overflow-hidden" style="background: linear-gradient(135deg, var(--brand-charcoal) 0%, var(--brand-mauve) 100%); border-radius: 16px;">
            <div class="row align-items-center">
                <div class="col-12 col-lg-8">
                    <span class="badge bg-warning text-dark px-3 py-1 rounded-pill mb-2 fw-semibold" style="font-size: 11px; letter-spacing: 0.5px;">CATALOG ACCELERATOR</span>
                    <h2 class="font-display text-white mb-2" style="font-size: 28px;">Bulk Product & Variant Importer</h2>
                    <p class="mb-0 text-white-50 small" style="max-width: 650px;">
                        Upload hundreds of products, size-color variant matrices, and associate high-resolution images in seconds using standard CSV spreadsheets and ZIP archives.
                    </p>
                </div>
                <div class="col-12 col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <a href="products.php" class="btn btn-outline-light rounded-pill px-3 btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Back to Products
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Left Column: Template Downloads & Step Guide -->
    <div class="col-12 col-lg-4">
        <!-- 1. Download Templates Card -->
        <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 14px;">
            <h6 class="fw-bold mb-3 d-flex align-items-center">
                <i class="bi bi-file-earmark-arrow-down text-primary me-2 fs-5"></i> Template & References
            </h6>
            <p class="text-muted small mb-3">
                Download the official pre-formatted template with example rows before preparing your catalog data.
            </p>

            <div class="d-grid gap-2">
                <a href="export-template.php?type=sample" class="btn btn-outline-primary d-flex align-items-center justify-content-between text-start p-2 rounded-3">
                    <div>
                        <div class="fw-bold small"><i class="bi bi-filetype-csv me-1"></i> Sample Import Template</div>
                        <small class="text-muted" style="font-size: 11px;">Includes single & multi-variant examples</small>
                    </div>
                    <i class="bi bi-download"></i>
                </a>

                <a href="export-template.php?type=reference" class="btn btn-outline-secondary d-flex align-items-center justify-content-between text-start p-2 rounded-3">
                    <div>
                        <div class="fw-bold small"><i class="bi bi-card-checklist me-1"></i> System Attributes Reference</div>
                        <small class="text-muted" style="font-size: 11px;">Active Categories, Sizes & Colors list</small>
                    </div>
                    <i class="bi bi-download"></i>
                </a>

                <a href="export-template.php?type=catalog" class="btn btn-outline-dark d-flex align-items-center justify-content-between text-start p-2 rounded-3">
                    <div>
                        <div class="fw-bold small"><i class="bi bi-cloud-download me-1"></i> Export Existing Catalog</div>
                        <small class="text-muted" style="font-size: 11px;">Edit stock & prices in bulk</small>
                    </div>
                    <i class="bi bi-download"></i>
                </a>
            </div>
        </div>

        <!-- 2. Workflow Guide Card -->
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 14px;">
            <h6 class="fw-bold mb-3 d-flex align-items-center">
                <i class="bi bi-lightbulb text-warning me-2 fs-5"></i> Best Practices
            </h6>
            <ul class="list-unstyled small text-muted mb-0" style="line-height: 1.7;">
                <li class="mb-2 d-flex">
                    <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                    <span><strong>Group by SKU:</strong> Rows sharing the same <code>product_sku</code> will be combined into a single product with multiple size/color variants.</span>
                </li>
                <li class="mb-2 d-flex">
                    <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                    <span><strong>Image Uploads:</strong> You can upload a <code>.zip</code> file of photos alongside your CSV, or use full web image URLs (e.g. <code>https://...</code>).</span>
                </li>
                <li class="mb-2 d-flex">
                    <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                    <span><strong>Color Matching:</strong> Images listed on a variant row are automatically tagged to that color swatch.</span>
                </li>
                <li class="d-flex">
                    <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                    <span><strong>Transaction Safe:</strong> The importer checks and saves everything inside an atomic transaction.</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Right Column: Upload Form & Live Results Dashboard -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 14px;">
            <h5 class="fw-bold mb-4 d-flex align-items-center">
                <i class="bi bi-cloud-arrow-up text-primary me-2"></i> Upload Catalog Files
            </h5>

            <form id="bulkUploadForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                <div class="row g-3 mb-4">
                    <!-- CSV Spreadsheet File Drop -->
                    <div class="col-12">
                        <label class="form-label fw-bold small text-dark">
                            1. Select Spreadsheet File (.CSV) <span class="text-danger">*</span>
                        </label>
                        <div class="upload-dropzone p-4 text-center rounded-3 border-2 border-dashed bg-light position-relative" id="csvDropzone" style="border: 2px dashed #cbd5e1; cursor: pointer;">
                            <i class="bi bi-file-earmark-spreadsheet text-primary fs-1 d-block mb-2"></i>
                            <div class="fw-bold text-dark" id="csvLabel">Drag & drop your CSV file here, or click to browse</div>
                            <small class="text-muted d-block mt-1">Accepts standard UTF-8 encoded .csv files</small>
                            <input type="file" name="csv_file" id="csv_file" accept=".csv, .txt" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="cursor: pointer;" required>
                        </div>
                    </div>

                    <!-- ZIP Images Archive (Optional) -->
                    <div class="col-12">
                        <label class="form-label fw-bold small text-dark">
                            2. Product Images Archive (.ZIP) <span class="badge bg-secondary ms-1 fw-normal" style="font-size: 10px;">Optional</span>
                        </label>
                        <div class="upload-dropzone p-3 text-center rounded-3 border-2 border-dashed bg-light position-relative" id="zipDropzone" style="border: 2px dashed #cbd5e1; cursor: pointer;">
                            <i class="bi bi-file-earmark-zip text-secondary fs-2 d-block mb-1"></i>
                            <div class="fw-bold small text-dark" id="zipLabel">Attach a .ZIP folder containing product images (JPG, PNG, WEBP)</div>
                            <small class="text-muted d-block" style="font-size: 11px;">Filenames should match the <code>image_filenames</code> column in your CSV</small>
                            <input type="file" name="zip_file" id="zip_file" accept=".zip" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="cursor: pointer;">
                        </div>
                    </div>
                </div>

                <!-- Import Settings Options -->
                <div class="p-3 bg-light rounded-3 mb-4 border">
                    <h6 class="fw-bold small mb-3 text-uppercase text-muted" style="letter-spacing: 0.5px;">Import Preferences</h6>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-dark">Duplicate SKU Handling</label>
                            <select class="form-select form-select-sm" name="import_mode">
                                <option value="create_and_update" selected>Create New & Update Existing Products</option>
                                <option value="create_only">Create New Only (Skip Existing SKUs)</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 d-flex align-items-center pt-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="auto_create_attributes" id="auto_create_attributes" value="1" checked>
                                <label class="form-check-label small fw-semibold text-dark" for="auto_create_attributes">
                                    Auto-create missing Categories, Sizes & Colors
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button & Progress Indicator -->
                <div class="d-flex align-items-center justify-content-between">
                    <button type="submit" class="btn btn-brand px-4 rounded-pill py-2" id="submitBtn">
                        <i class="bi bi-cloud-arrow-up me-2"></i> Start Bulk Import
                    </button>
                    <span class="text-muted small" id="statusMessage"></span>
                </div>

                <!-- Live Progress Bar -->
                <div class="progress mt-3 d-none" id="importProgress" style="height: 8px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 100%"></div>
                </div>
            </form>

            <!-- Results & Statistics Panel (Initially Hidden) -->
            <div id="resultsContainer" class="mt-4 pt-4 border-top d-none">
                <h5 class="fw-bold mb-3 d-flex align-items-center">
                    <i class="bi bi-clipboard-check text-success me-2"></i> Import Execution Summary
                </h5>

                <!-- Metric Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="p-3 bg-light rounded-3 text-center border">
                            <span class="text-muted small d-block">Products Created</span>
                            <span class="fs-4 fw-bold text-success" id="statProductsCreated">0</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 bg-light rounded-3 text-center border">
                            <span class="text-muted small d-block">Products Updated</span>
                            <span class="fs-4 fw-bold text-primary" id="statProductsUpdated">0</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 bg-light rounded-3 text-center border">
                            <span class="text-muted small d-block">Variants Processed</span>
                            <span class="fs-4 fw-bold text-dark" id="statVariants">0</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 bg-light rounded-3 text-center border">
                            <span class="text-muted small d-block">Images Linked</span>
                            <span class="fs-4 fw-bold text-purple" id="statImages">0</span>
                        </div>
                    </div>
                </div>

                <!-- Warnings & Errors Section -->
                <div id="logsSection"></div>

                <!-- Action Footer -->
                <div class="mt-3 text-end">
                    <a href="products.php" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                        <i class="bi bi-box-seam me-1"></i> View Catalog
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csvInput = document.getElementById('csv_file');
    const zipInput = document.getElementById('zip_file');
    const csvLabel = document.getElementById('csvLabel');
    const zipLabel = document.getElementById('zipLabel');
    const form = document.getElementById('bulkUploadForm');
    const submitBtn = document.getElementById('submitBtn');
    const statusMessage = document.getElementById('statusMessage');
    const importProgress = document.getElementById('importProgress');
    const resultsContainer = document.getElementById('resultsContainer');

    // Update file labels on select
    csvInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            csvLabel.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check2-circle me-1"></i> ' + this.files[0].name + '</span> (' + (this.files[0].size / 1024).toFixed(1) + ' KB)';
        }
    });

    zipInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            zipLabel.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check2-circle me-1"></i> ' + this.files[0].name + '</span> (' + (this.files[0].size / (1024 * 1024)).toFixed(2) + ' MB)';
        }
    });

    // Handle AJAX Form Submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        if (!csvInput.files || !csvInput.files[0]) {
            alert('Please choose a CSV file to import.');
            return;
        }

        const formData = new FormData(form);

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing Import...';
        statusMessage.textContent = 'Parsing rows, validating variants & linking images...';
        importProgress.classList.remove('d-none');
        resultsContainer.classList.add('d-none');

        fetch('process-bulk-upload.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-2"></i> Start Bulk Import';
            importProgress.classList.add('d-none');

            if (res.status === 'success') {
                statusMessage.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i> ' + res.message + '</span>';
                renderResults(res.data);
            } else {
                statusMessage.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle me-1"></i> ' + (res.message || 'Import failed') + '</span>';
                if (res.data && res.data.stats) {
                    renderResults(res.data.stats, res.message);
                } else {
                    alert('Error: ' + res.message);
                }
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-2"></i> Start Bulk Import';
            importProgress.classList.add('d-none');
            statusMessage.innerHTML = '<span class="text-danger">Network or server error during import.</span>';
            alert('An unexpected error occurred while communicating with the server: ' + err.message);
        });
    });

    function renderResults(stats, errorMessage = null) {
        resultsContainer.classList.remove('d-none');

        document.getElementById('statProductsCreated').textContent = stats.products_created || 0;
        document.getElementById('statProductsUpdated').textContent = stats.products_updated || 0;
        document.getElementById('statVariants').textContent = (stats.variants_created || 0) + (stats.variants_updated || 0);
        document.getElementById('statImages').textContent = stats.images_attached || 0;

        const logsSection = document.getElementById('logsSection');
        let html = '';

        if (errorMessage) {
            html += `<div class="alert alert-danger small mb-3"><i class="bi bi-exclamation-octagon-fill me-2"></i> ${errorMessage}</div>`;
        }

        if (stats.errors && stats.errors.length > 0) {
            html += `
                <div class="alert alert-danger p-3 small mb-3">
                    <h6 class="fw-bold text-danger mb-2"><i class="bi bi-x-circle me-1"></i> Errors Encountered (${stats.errors.length}):</h6>
                    <ul class="mb-0 ps-3">
                        ${stats.errors.map(err => `<li>${err}</li>`).join('')}
                    </ul>
                </div>
            `;
        }

        if (stats.warnings && stats.warnings.length > 0) {
            html += `
                <div class="alert alert-warning p-3 small mb-0">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-exclamation-triangle me-1"></i> Warnings & Fallbacks (${stats.warnings.length}):</h6>
                    <div style="max-height: 200px; overflow-y: auto;">
                        <ul class="mb-0 ps-3">
                            ${stats.warnings.map(w => `<li>${w}</li>`).join('')}
                        </ul>
                    </div>
                </div>
            `;
        }

        if ((!stats.errors || stats.errors.length === 0) && (!stats.warnings || stats.warnings.length === 0)) {
            html += `
                <div class="alert alert-success p-3 small mb-0">
                    <i class="bi bi-check-circle-fill me-2 text-success"></i> All catalog records, variants, stock entries, and photos were processed cleanly without errors!
                </div>
            `;
        }

        logsSection.innerHTML = html;

        // Smooth scroll down to results
        resultsContainer.scrollIntoView({ behavior: 'smooth' });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
