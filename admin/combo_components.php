<?php
// admin/combo_components.php
// Admin Management for Combo / Bundle Set Components

$page_title = 'Combo Set Components';
$active_menu = 'products';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

require_admin_permission('products');

$combo_id = (int)($_GET['combo_id'] ?? 0);
$error = '';
$success = '';

// Fetch Combo Product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND product_type = 'combo' LIMIT 1");
$stmt->execute([$combo_id]);
$combo_product = $stmt->fetch();

if (!$combo_product) {
    set_flash_message('error', 'Combo product not found.');
    header('Location: products.php');
    exit;
}

// Fetch all single products and categories for dropdowns
$all_single_products = $pdo->query("SELECT id, name, sku FROM products WHERE product_type = 'single' AND status = 1 ORDER BY name ASC")->fetchAll();
$all_categories = $pdo->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name ASC")->fetchAll();

// Handle Add Component
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_component'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed.';
    } else {
        $component_title = trim($_POST['component_title'] ?? '');
        $allowed_product_id = !empty($_POST['allowed_product_id']) ? (int)$_POST['allowed_product_id'] : null;
        $allowed_category_id = !empty($_POST['allowed_category_id']) ? (int)$_POST['allowed_category_id'] : null;
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if (empty($component_title)) {
            $error = 'Please provide a title for the component step.';
        } else {
            try {
                $ins = $pdo->prepare("
                    INSERT INTO combo_components 
                    (combo_product_id, component_title, allowed_category_id, allowed_product_id, default_quantity, sort_order) 
                    VALUES (?, ?, ?, ?, 1, ?)
                ");
                $ins->execute([$combo_id, $component_title, $allowed_category_id, $allowed_product_id, $sort_order]);

                log_admin_action($current_admin_id, "Added combo component '$component_title' to combo ID: $combo_id");
                set_flash_message('success', 'Component added successfully!');
                header('Location: combo_components.php?combo_id=' . $combo_id);
                exit;
            } catch (PDOException $e) {
                $error = 'Failed to add component: ' . $e->getMessage();
            }
        }
    }
}

// Handle Delete Component
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_component'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed.';
    } else {
        $component_id = (int)$_POST['component_id'];
        try {
            $del = $pdo->prepare("DELETE FROM combo_components WHERE id = ? AND combo_product_id = ?");
            $del->execute([$component_id, $combo_id]);

            log_admin_action($current_admin_id, "Deleted combo component ID $component_id from combo ID: $combo_id");
            set_flash_message('success', 'Component removed successfully!');
            header('Location: combo_components.php?combo_id=' . $combo_id);
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to delete component: ' . $e->getMessage();
        }
    }
}

// Handle Save Size Pricing Strategy (Model D Hybrid)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pricing_mode'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed.';
    } else {
        $mode = $_POST['combo_pricing_mode'] ?? 'inherit';
        if (!in_array($mode, ['inherit', 'custom', 'flat'])) {
            $mode = 'inherit';
        }

        $size_mode = $_POST['combo_size_mode'] ?? 'flexible';
        if (!in_array($size_mode, ['flexible', 'same_size'])) {
            $size_mode = 'flexible';
        }

        try {
            $pdo->beginTransaction();

            $up = $pdo->prepare("UPDATE products SET combo_pricing_mode = ?, combo_size_mode = ? WHERE id = ?");
            $up->execute([$mode, $size_mode, $combo_id]);

            // Save custom surcharges if in custom mode
            $del = $pdo->prepare("DELETE FROM combo_size_surcharges WHERE combo_product_id = ?");
            $del->execute([$combo_id]);

            if ($mode === 'custom') {
                $surcharges = $_POST['surcharges'] ?? [];
                $regular_surcharges = $_POST['regular_surcharges'] ?? [];

                $ins_sc = $pdo->prepare("
                    INSERT INTO combo_size_surcharges (combo_product_id, component_id, size_id, surcharge_amount, regular_surcharge_amount)
                    VALUES (?, 0, ?, ?, ?)
                ");
                $all_s_ids = array_unique(array_merge(array_keys($surcharges), array_keys($regular_surcharges)));
                foreach ($all_s_ids as $s_id) {
                    $amt_val = isset($surcharges[$s_id]) && $surcharges[$s_id] !== '' ? (float)$surcharges[$s_id] : 0.00;
                    $reg_amt_val = isset($regular_surcharges[$s_id]) && $regular_surcharges[$s_id] !== '' ? (float)$regular_surcharges[$s_id] : 0.00;
                    if ($amt_val > 0 || $reg_amt_val > 0) {
                        $ins_sc->execute([$combo_id, (int)$s_id, $amt_val, $reg_amt_val]);
                    }
                }
            }

            $pdo->commit();
            log_admin_action($current_admin_id, "Updated combo size pricing strategy to '{$mode}' (sizing mode: '{$size_mode}') for combo ID: {$combo_id}");
            set_flash_message('success', 'Size pricing strategy and sizing mode saved successfully!');
            header('Location: combo_components.php?combo_id=' . $combo_id);
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Failed to save pricing strategy: ' . $e->getMessage();
        }
    }
}

// Fetch existing components
$comp_stmt = $pdo->prepare("
    SELECT cc.*, 
           p.name as product_name, p.sku as product_sku,
           c.name as category_name
    FROM combo_components cc
    LEFT JOIN products p ON cc.allowed_product_id = p.id
    LEFT JOIN categories c ON cc.allowed_category_id = c.id
    WHERE cc.combo_product_id = ?
    ORDER BY cc.sort_order ASC, cc.id ASC
");
$comp_stmt->execute([$combo_id]);
$components = $comp_stmt->fetchAll();

$combo_pricing_mode = $combo_product['combo_pricing_mode'] ?? 'inherit';
$combo_size_mode = $combo_product['combo_size_mode'] ?? 'flexible';

// Fetch existing custom size surcharges for this combo
$surcharges_stmt = $pdo->prepare("SELECT size_id, surcharge_amount, regular_surcharge_amount FROM combo_size_surcharges WHERE combo_product_id = ?");
$surcharges_stmt->execute([$combo_id]);
$sc_rows = $surcharges_stmt->fetchAll();
$existing_surcharges = [];
$existing_regular_surcharges = [];
foreach ($sc_rows as $row) {
    $existing_surcharges[$row['size_id']] = $row['surcharge_amount'];
    $existing_regular_surcharges[$row['size_id']] = $row['regular_surcharge_amount'];
}

// Fetch all sizes grouped by category_type for custom surcharge configuration
$sizes_stmt = $pdo->query("SELECT * FROM sizes ORDER BY category_type ASC, id ASC");
$all_sizes = $sizes_stmt->fetchAll();
sort_sizes($all_sizes, true);
$sizes_by_cat = [];
foreach ($all_sizes as $sz) {
    $cat = !empty($sz['category_type']) ? $sz['category_type'] : 'General';
    $sizes_by_cat[$cat][] = $sz;
}
?>

<div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h4 class="fw-bold mb-0"><?php echo e($combo_product['name']); ?></h4>
                <span class="badge bg-purple-subtle text-purple border"><i class="bi bi-stars"></i> Combo Set</span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                SKU: <strong><?php echo e($combo_product['sku']); ?></strong> | 
                Price: <strong><?php echo format_price($combo_product['offer_price'] ?? $combo_product['regular_price']); ?></strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="../product-detail.php?slug=<?php echo e($combo_product['slug']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-box-arrow-up-right me-1"></i> Preview PDP
            </a>
            <a href="products.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Products
            </a>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo e($error); ?></div>
<?php endif; ?>

<!-- Bundle Size Variant Pricing Strategy Card (Model D Hybrid) -->
<div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
        <div>
            <h5 class="fw-bold mb-1 text-dark">
                <i class="bi bi-tag-fill me-2 text-rose"></i> Bundle Size Variant Pricing Strategy
            </h5>
            <p class="text-muted small mb-0">Control how prices adapt when customers select different sizes (e.g. plus sizes like 44B) in this bundle.</p>
        </div>
        <span class="badge <?php echo $combo_pricing_mode === 'inherit' ? 'bg-success-subtle text-success' : ($combo_pricing_mode === 'custom' ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary'); ?> border px-3 py-2">
            Active: <strong><?php echo ucfirst($combo_pricing_mode); ?> Mode</strong>
        </span>
    </div>

    <form action="combo_components.php?combo_id=<?php echo $combo_id; ?>" method="POST" id="pricingModeForm">
        <?php echo csrf_field(); ?>

        <!-- Customer Sizing Selection Mode -->
        <div class="mb-4 p-3 border rounded-3 bg-light">
            <label class="fw-bold text-dark mb-1 d-block">
                <i class="bi bi-aspect-ratio text-rose me-1"></i> Customer Sizing Experience
            </label>
            <p class="small text-muted mb-3">Choose whether customer selects sizes individually per piece, or picks one uniform size for the whole pack.</p>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="card h-100 p-3 border rounded-3 cursor-pointer <?php echo $combo_size_mode === 'flexible' ? 'border-primary bg-white shadow-sm' : 'bg-white'; ?>" style="cursor: pointer;">
                        <div class="d-flex align-items-start gap-2">
                            <input class="form-check-input mt-1" type="radio" name="combo_size_mode" value="flexible" <?php echo $combo_size_mode === 'flexible' ? 'checked' : ''; ?>>
                            <div>
                                <span class="fw-bold text-dark d-block">Flexible Sizes (Multi-Product Set)</span>
                                <small class="text-muted">Customer selects independent sizes for each piece (e.g., Bra 34B + Matching Panty M).</small>
                            </div>
                        </div>
                    </label>
                </div>
                <div class="col-12 col-md-6">
                    <label class="card h-100 p-3 border rounded-3 cursor-pointer <?php echo $combo_size_mode === 'same_size' ? 'border-primary bg-white shadow-sm' : 'bg-white'; ?>" style="cursor: pointer;">
                        <div class="d-flex align-items-start gap-2">
                            <input class="form-check-input mt-1" type="radio" name="combo_size_mode" value="same_size" <?php echo $combo_size_mode === 'same_size' ? 'checked' : ''; ?>>
                            <div>
                                <span class="fw-bold text-dark d-block">Same-Size Multi-Color Pack (e.g. 3-Pack Bras)</span>
                                <small class="text-muted">Customer chooses one uniform size for the pack, and then selects colors for each piece.</small>
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <!-- Mode 1: Inherit -->
            <div class="col-12 col-md-4">
                <label class="card h-100 p-3 border rounded-3 cursor-pointer pricing-mode-card <?php echo $combo_pricing_mode === 'inherit' ? 'border-success bg-success-subtle bg-opacity-10' : ''; ?>" style="cursor: pointer;">
                    <div class="d-flex align-items-start gap-2">
                        <input class="form-check-input mt-1" type="radio" name="combo_pricing_mode" value="inherit" <?php echo $combo_pricing_mode === 'inherit' ? 'checked' : ''; ?> onchange="toggleCustomSurcharges(this.value)">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold text-dark">1. Inherit from Single Products</span>
                                <span class="badge bg-success-subtle text-success small" style="font-size: 10px;">Recommended</span>
                            </div>
                            <p class="small text-muted mb-0 mt-1">
                                Automatically pulls price differences from single product variant matrices. If 44B costs +₹50 on the single bra, the bundle automatically adds +₹50. Zero manual setup.
                            </p>
                        </div>
                    </div>
                </label>
            </div>

            <!-- Mode 2: Custom -->
            <div class="col-12 col-md-4">
                <label class="card h-100 p-3 border rounded-3 cursor-pointer pricing-mode-card <?php echo $combo_pricing_mode === 'custom' ? 'border-primary bg-primary-subtle bg-opacity-10' : ''; ?>" style="cursor: pointer;">
                    <div class="d-flex align-items-start gap-2">
                        <input class="form-check-input mt-1" type="radio" name="combo_pricing_mode" value="custom" <?php echo $combo_pricing_mode === 'custom' ? 'checked' : ''; ?> onchange="toggleCustomSurcharges(this.value)">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold text-dark">2. Custom Bundle Surcharges</span>
                                <span class="badge bg-primary-subtle text-primary small" style="font-size: 10px;">Overrides</span>
                            </div>
                            <p class="small text-muted mb-0 mt-1">
                                Define exact extra charges for specific sizes inside this bundle only (e.g. charge a discounted +₹26 for 42 or +₹43 for 44).
                            </p>
                        </div>
                    </div>
                </label>
            </div>

            <!-- Mode 3: Flat -->
            <div class="col-12 col-md-4">
                <label class="card h-100 p-3 border rounded-3 cursor-pointer pricing-mode-card <?php echo $combo_pricing_mode === 'flat' ? 'border-secondary bg-light' : ''; ?>" style="cursor: pointer;">
                    <div class="d-flex align-items-start gap-2">
                        <input class="form-check-input mt-1" type="radio" name="combo_pricing_mode" value="flat" <?php echo $combo_pricing_mode === 'flat' ? 'checked' : ''; ?> onchange="toggleCustomSurcharges(this.value)">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold text-dark">3. Flat Bundle Price</span>
                                <span class="badge bg-secondary-subtle text-secondary small" style="font-size: 10px;">Promotional</span>
                            </div>
                            <p class="small text-muted mb-0 mt-1">
                                Any size, same price. All size-based extra charges are waived. Ideal for promotional "any size for ₹X" sales campaigns.
                            </p>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <!-- Custom Surcharge Table (Visible only in 'custom' mode) -->
        <div id="customSurchargesContainer" class="<?php echo $combo_pricing_mode === 'custom' ? '' : 'd-none'; ?> p-3 border rounded-3 bg-light mb-4">
            <h6 class="fw-bold text-dark mb-1">
                <i class="bi bi-sliders2 me-1 text-primary"></i> Custom Size Surcharge Matrix (₹ Extra for this Bundle)
            </h6>
            <p class="small text-muted mb-3">Leave blank or 0.00 for standard sizes with no extra charge. Enter extra amount for plus/extended sizes.</p>

            <ul class="nav nav-pills mb-3" id="sizeCategoryTabs" role="tablist">
                <?php $tab_idx = 0; foreach ($sizes_by_cat as $cat_name => $cat_sizes): $tab_idx++; ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link btn-sm <?php echo $tab_idx === 1 ? 'active' : ''; ?>" id="tab-btn-<?php echo e(slugify($cat_name)); ?>" data-bs-toggle="pill" data-bs-target="#tab-pane-<?php echo e(slugify($cat_name)); ?>" type="button" role="tab">
                            <?php echo e($cat_name); ?> (<?php echo count($cat_sizes); ?>)
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="tab-content">
                <?php $tab_idx = 0; foreach ($sizes_by_cat as $cat_name => $cat_sizes): $tab_idx++; ?>
                    <div class="tab-pane fade <?php echo $tab_idx === 1 ? 'show active' : ''; ?>" id="tab-pane-<?php echo e(slugify($cat_name)); ?>" role="tabpanel">
                        <div class="row g-2">
                            <?php foreach ($cat_sizes as $sz): ?>
                                <?php 
                                    $current_surcharge = $existing_surcharges[$sz['id']] ?? ''; 
                                    $current_reg_surcharge = $existing_regular_surcharges[$sz['id']] ?? ''; 
                                ?>
                                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                    <div class="p-2 border rounded bg-white">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold text-dark" style="font-size: 13px;"><?php echo e($sz['size_code']); ?></span>
                                            <span class="text-muted" style="font-size: 11px;"><?php echo e($sz['size_name']); ?></span>
                                        </div>
                                        <div class="mb-1">
                                            <label class="form-label text-muted mb-0" style="font-size: 10px;">Selling Extra</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text px-1 text-muted" style="font-size: 11px;">+₹</span>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm px-1 text-end" name="surcharges[<?php echo $sz['id']; ?>]" value="<?php echo e($current_surcharge); ?>" placeholder="0.00">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="form-label text-muted mb-0" style="font-size: 10px;">MRP Extra</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text px-1 text-muted" style="font-size: 11px;">+₹</span>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm px-1 text-end" name="regular_surcharges[<?php echo $sz['id']; ?>]" value="<?php echo e($current_reg_surcharge); ?>" placeholder="0.00">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" name="save_pricing_mode" class="btn btn-brand px-4 py-2">
                <i class="bi bi-check2-circle me-1"></i> Save Size Pricing Strategy
            </button>
        </div>
    </form>
</div>

<script>
function toggleCustomSurcharges(mode) {
    const container = document.getElementById('customSurchargesContainer');
    if (container) {
        if (mode === 'custom') {
            container.classList.remove('d-none');
        } else {
            container.classList.add('d-none');
        }
    }
}
</script>

<div class="row g-4">
    <!-- Existing Components List -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">
                <i class="bi bi-collection me-2 text-rose"></i> Configured Set Pieces (<?php echo count($components); ?>)
            </h5>

            <?php if (empty($components)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-layers fs-1 d-block mb-2"></i>
                    <p class="small mb-0">No components configured yet for this combo set. Add steps using the form on the right.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Step</th>
                                <th>Component Step Title</th>
                                <th>Eligible Item / Restriction</th>
                                <th class="text-end" style="width: 80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($components as $idx => $c): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-dark border fw-bold"><?php echo $idx + 1; ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark d-block"><?php echo e($c['component_title']); ?></span>
                                        <span class="text-muted small">Sort Order: <?php echo $c['sort_order']; ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($c['product_name'])): ?>
                                            <span class="badge bg-primary-subtle text-primary border">
                                                <i class="bi bi-tag-fill me-1"></i> Specific: <?php echo e($c['product_name']); ?> (<?php echo e($c['product_sku']); ?>)
                                            </span>
                                        <?php elseif (!empty($c['category_name'])): ?>
                                            <span class="badge bg-success-subtle text-success border">
                                                <i class="bi bi-folder me-1"></i> Category: <?php echo e($c['category_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border">Any Single Product</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <form action="combo_components.php?combo_id=<?php echo $combo_id; ?>" method="POST" onsubmit="return confirm('Remove this piece from the combo set?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="component_id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" name="delete_component" class="btn btn-sm btn-link text-danger p-0" title="Delete Piece">
                                                <i class="bi bi-trash fs-6"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Component Form -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">
                <i class="bi bi-plus-circle me-2 text-rose"></i> Add Set Piece
            </h5>

            <form action="combo_components.php?combo_id=<?php echo $combo_id; ?>" method="POST">
                <?php echo csrf_field(); ?>

                <div class="mb-3">
                    <label for="component_title" class="form-label fw-bold small">Step Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="component_title" name="component_title" placeholder="e.g. Step 1: Choose Bra Style & Fit" required>
                </div>

                <div class="mb-3">
                    <label for="allowed_product_id" class="form-label fw-bold small">Lock to Specific Product (Recommended)</label>
                    <select class="form-select" id="allowed_product_id" name="allowed_product_id">
                        <option value="">-- None (Choose Category Below) --</option>
                        <?php foreach ($all_single_products as $p): ?>
                            <option value="<?php echo $p['id']; ?>">
                                <?php echo e($p['name']); ?> (<?php echo e($p['sku']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text small">Lock this step to a specific base product (e.g. Elegance Lace Bra).</div>
                </div>

                <div class="mb-3">
                    <label for="allowed_category_id" class="form-label fw-bold small">Or Allow Any Product in Category</label>
                    <select class="form-select" id="allowed_category_id" name="allowed_category_id">
                        <option value="">-- None / Use Specific Product Above --</option>
                        <?php foreach ($all_categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>">
                                <?php echo e($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="sort_order" class="form-label fw-bold small">Sort Order (Step Order)</label>
                    <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?php echo count($components) + 1; ?>">
                </div>

                <button type="submit" name="add_component" class="btn btn-brand w-100 py-2">
                    <i class="bi bi-plus-circle me-1"></i> Add Component Step
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
