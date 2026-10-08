<?php
// admin/variants.php
// Product Variant Matrix Management with Stock Logs and Dual-Tier Pricing

$page_title = 'Product Variants Matrix';
$active_menu = 'products';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Check permissions
require_admin_permission('products');

$product_id = (int)($_GET['product_id'] ?? 0);
if (!$product_id) {
    set_flash_message('error', 'Please choose a product to manage variants.');
    header('Location: products.php');
    exit;
}

// Fetch target product details with category info
$p_stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.parent_id, parent.name as parent_category_name
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN categories parent ON c.parent_id = parent.id
    WHERE p.id = ? LIMIT 1
");
$p_stmt->execute([$product_id]);
$product = $p_stmt->fetch();

if (!$product) {
    set_flash_message('error', 'Product not found.');
    header('Location: products.php');
    exit;
}

$error = '';
$success = '';

// Handle insertions & updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        // 1. ADD NEW VARIANT
        if (isset($_POST['add_variant'])) {
            $size_id = (int)$_POST['size_id'];
            $color_id = (int)$_POST['color_id'];
            $sku = trim($_POST['sku'] ?? '');
            $regular_price = (isset($_POST['regular_price']) && $_POST['regular_price'] !== '') ? (float)$_POST['regular_price'] : null;
            $offer_price = (isset($_POST['offer_price']) && $_POST['offer_price'] !== '') ? (float)$_POST['offer_price'] : null;
            $price = $offer_price !== null ? $offer_price : ($regular_price !== null ? $regular_price : null);
            $stock = (int)($_POST['stock'] ?? 0);

            if (empty($sku)) {
                // Auto generate SKU if empty
                // Format: [PRODUCT-SKU]-[SIZE-CODE]-[COLOR-SHORT]
                $s_stmt = $pdo->prepare("SELECT size_code FROM sizes WHERE id = ? LIMIT 1");
                $s_stmt->execute([$size_id]);
                $sz = $s_stmt->fetch();
                
                $c_stmt = $pdo->prepare("SELECT * FROM colors WHERE id = ? LIMIT 1");
                $c_stmt->execute([$color_id]);
                $cl = $c_stmt->fetch();

                $sz_code = $sz ? $sz['size_code'] : $size_id;
                $cl_short = $cl ? get_color_short_code($cl['color_name'], $cl['short_code'] ?? null) : $color_id;
                $sku = $product['sku'] . '-' . $sz_code . '-' . $cl_short;
            }

            if (empty($size_id) || empty($color_id)) {
                $error = 'Please select both Size and Color.';
            } else {
                try {
                    // Check duplicate variant combination or duplicate SKU
                    $check = $pdo->prepare("
                        SELECT id FROM product_variants 
                        WHERE (product_id = ? AND size_id = ? AND color_id = ?) 
                        OR sku = ? LIMIT 1
                    ");
                    $check->execute([$product_id, $size_id, $color_id, $sku]);
                    if ($check->fetch()) {
                        $error = 'A variant with this Size/Color combination or SKU already exists.';
                    } else {
                        $pdo->beginTransaction();

                        $stmt = $pdo->prepare("
                            INSERT INTO product_variants (product_id, size_id, color_id, sku, regular_price, offer_price, price, stock) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$product_id, $size_id, $color_id, $sku, $regular_price, $offer_price, $price, $stock]);
                        
                        $variant_id = $pdo->lastInsertId();

                        // Write to inventory log
                        if ($stock > 0) {
                            $log_stmt = $pdo->prepare("
                                INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) 
                                VALUES (?, ?, 'restock', 'Initial stock setup for new variant')
                            ");
                            $log_stmt->execute([$variant_id, $stock]);
                        }

                        $pdo->commit();
                        log_admin_action($current_admin_id, "Created product variant: $sku");
                        set_flash_message('success', 'Variant configured successfully!');
                        header('Location: variants.php?product_id=' . $product_id);
                        exit;
                    }
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = 'Failed to create variant: ' . $e->getMessage();
                }
            }
        }

        // 2. QUICK EDIT STOCK / DUAL-TIER PRICE OVERRIDE
        if (isset($_POST['edit_variant'])) {
            $variant_id = (int)$_POST['variant_id'];
            $regular_price = (isset($_POST['regular_price']) && $_POST['regular_price'] !== '') ? (float)$_POST['regular_price'] : null;
            $offer_price = (isset($_POST['offer_price']) && $_POST['offer_price'] !== '') ? (float)$_POST['offer_price'] : null;
            $price = $offer_price !== null ? $offer_price : ($regular_price !== null ? $regular_price : null);
            $new_stock = (int)($_POST['stock'] ?? 0);
            $sku = $_POST['sku'];

            try {
                // Fetch current variant details
                $var_stmt = $pdo->prepare("SELECT stock FROM product_variants WHERE id = ? LIMIT 1");
                $var_stmt->execute([$variant_id]);
                $curr = $var_stmt->fetch();

                if ($curr) {
                    $old_stock = (int)$curr['stock'];
                    $diff = $new_stock - $old_stock;

                    $pdo->beginTransaction();

                    // Update variant
                    $stmt = $pdo->prepare("UPDATE product_variants SET regular_price = ?, offer_price = ?, price = ?, stock = ? WHERE id = ?");
                    $stmt->execute([$regular_price, $offer_price, $price, $new_stock, $variant_id]);

                    // Add inventory log if stock count changed
                    if ($diff != 0) {
                        $log_type = $diff > 0 ? 'restock' : 'adjustment';
                        $notes = $diff > 0 ? 'Admin restock adjustment' : 'Admin shrinkage/correction adjustment';
                        
                        $log_stmt = $pdo->prepare("
                            INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $log_stmt->execute([$variant_id, $diff, $log_type, $notes]);
                    }

                    $pdo->commit();
                    log_admin_action($current_admin_id, "Modified product variant: $sku (Stock change: $diff)");
                    set_flash_message('success', 'Variant details updated!');
                    header('Location: variants.php?product_id=' . $product_id);
                    exit;
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Update failed: ' . $e->getMessage();
            }
        }

        // 3. BATCH UPDATE VARIANTS (BY SIZE OR ALL)
        if (isset($_POST['batch_update_variants'])) {
            $batch_target = $_POST['batch_target'] ?? 'all'; // 'all' or 'size_X'
            $batch_set_regular = trim($_POST['batch_regular_price'] ?? '');
            $batch_set_offer = trim($_POST['batch_offer_price'] ?? '');
            $batch_set_stock = trim($_POST['batch_stock'] ?? '');
            $batch_clear_prices = isset($_POST['batch_clear_prices']) ? 1 : 0;

            try {
                $target_where = "product_id = ?";
                $target_params = [$product_id];

                if (strpos($batch_target, 'size_') === 0) {
                    $target_size_id = (int)str_replace('size_', '', $batch_target);
                    $target_where .= " AND size_id = ?";
                    $target_params[] = $target_size_id;
                }

                $updates = [];
                $update_params = [];

                if ($batch_clear_prices) {
                    $updates[] = "regular_price = NULL";
                    $updates[] = "offer_price = NULL";
                    $updates[] = "price = NULL";
                } else {
                    if ($batch_set_regular !== '') {
                        $updates[] = "regular_price = ?";
                        $update_params[] = (float)$batch_set_regular;
                    }
                    if ($batch_set_offer !== '') {
                        $updates[] = "offer_price = ?";
                        $update_params[] = (float)$batch_set_offer;
                    }
                    if ($batch_set_offer !== '') {
                        $updates[] = "price = ?";
                        $update_params[] = (float)$batch_set_offer;
                    } elseif ($batch_set_regular !== '') {
                        $updates[] = "price = ?";
                        $update_params[] = (float)$batch_set_regular;
                    }
                }

                if ($batch_set_stock !== '') {
                    $updates[] = "stock = ?";
                    $update_params[] = (int)$batch_set_stock;
                }

                if (empty($updates)) {
                    $error = 'No batch update actions were specified.';
                } else {
                    $sql = "UPDATE product_variants SET " . implode(', ', $updates) . " WHERE " . $target_where;
                    $merged_params = array_merge($update_params, $target_params);

                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($merged_params);
                    $affected_count = $stmt->rowCount();
                    $pdo->commit();

                    log_admin_action($current_admin_id, "Batch updated $affected_count variants for product ID $product_id");
                    set_flash_message('success', "Batch updated $affected_count variant(s) successfully!");
                    header('Location: variants.php?product_id=' . $product_id);
                    exit;
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Batch update failed: ' . $e->getMessage();
            }
        }

        // 4. DELETE VARIANT
        if (isset($_POST['delete_variant'])) {
            $variant_id = (int)$_POST['variant_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM product_variants WHERE id = ?");
                $stmt->execute([$variant_id]);
                log_admin_action($current_admin_id, "Deleted variant id: $variant_id");
                set_flash_message('success', 'Variant deleted successfully!');
                header('Location: variants.php?product_id=' . $product_id);
                exit;
            } catch (PDOException $e) {
                $error = 'Cannot delete variant. It may be linked in cart or order items.';
            }
        }
    }
}

// Fetch configured variants
$variants_list = $pdo->prepare("
    SELECT v.*, s.size_code, s.size_name, s.category_type as size_cat, c.color_name, c.color_code 
    FROM product_variants v 
    JOIN sizes s ON v.size_id = s.id 
    JOIN colors c ON v.color_id = c.id 
    WHERE v.product_id = ? 
    ORDER BY s.id ASC, c.id ASC
");
$variants_list->execute([$product_id]);
$variants = $variants_list->fetchAll();

// 1. Fetch Sizes for this product's category / type
$cat_name = trim($product['category_name'] ?? '');
$parent_cat_name = trim($product['parent_category_name'] ?? '');

$matching_sizes = [];
if (!empty($cat_name) || !empty($parent_cat_name)) {
    $sz_stmt = $pdo->prepare("
        SELECT DISTINCT s.* 
        FROM sizes s 
        WHERE LOWER(s.category_type) = LOWER(?) 
           OR LOWER(s.category_type) = LOWER(?)
           OR s.category_type LIKE CONCAT('%', ?, '%')
           OR ? LIKE CONCAT('%', s.category_type, '%')
           OR s.id IN (SELECT size_id FROM product_variants WHERE product_id = ?)
        ORDER BY s.category_type ASC, s.id ASC
    ");
    $sz_stmt->execute([$cat_name, $parent_cat_name, $cat_name, $cat_name, $product_id]);
    $matching_sizes = $sz_stmt->fetchAll();
    sort_sizes($matching_sizes);
}

$all_sizes = $pdo->query("SELECT * FROM sizes ORDER BY category_type ASC, id ASC")->fetchAll();
sort_sizes($all_sizes, true);

// Distinct sizes present in current matrix for batch updater
$matrix_size_ids = array_unique(array_column($variants, 'size_id'));
$matrix_sizes = array_filter($all_sizes, function($s) use ($matrix_size_ids) {
    return in_array($s['id'], $matrix_size_ids);
});
sort_sizes($matrix_sizes);

// 2. Fetch Colors available for this product (from images and existing variants)
$color_query = $pdo->prepare("
    SELECT DISTINCT c.* 
    FROM colors c 
    WHERE c.id IN (
        SELECT color_id FROM product_images WHERE product_id = ? AND color_id IS NOT NULL
        UNION
        SELECT color_id FROM product_variants WHERE product_id = ? AND color_id IS NOT NULL
    )
    ORDER BY c.color_name ASC
");
$color_query->execute([$product_id, $product_id]);
$product_colors = $color_query->fetchAll();

$all_colors = $pdo->query("SELECT * FROM colors ORDER BY color_name ASC")->fetchAll();
?>

<div class="mb-4 d-flex justify-content-between align-items-center">
    <a href="products.php" class="btn btn-outline-secondary btn-sm rounded-pill">
        <i class="bi bi-arrow-left"></i> Back to Products Catalog
    </a>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-dark btn-sm rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#batchUpdateCard" aria-expanded="false">
            <i class="bi bi-lightning-charge me-1"></i> Batch Price & Stock Updater
        </button>
        <a href="products.php?action=edit&id=<?php echo $product_id; ?>" class="btn btn-outline-primary btn-sm rounded-pill">
            <i class="bi bi-pencil me-1"></i> Edit Product & Colors
        </a>
    </div>
</div>

<!-- Product Master Info Banner -->
<div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h5 class="fw-bold mb-1">Product: <?php echo e($product['name']); ?></h5>
            <div class="text-muted small d-flex flex-wrap align-items-center gap-2">
                <span>Category: <span class="badge bg-light text-dark border"><?php echo e($product['category_name'] ?: 'Uncategorized'); ?></span></span>
                <span>•</span>
                <span>Master SKU: <code><?php echo e($product['sku']); ?></code></span>
                <span>•</span>
                <span>Master Regular Price (MRP): <strong><?php echo format_price($product['regular_price']); ?></strong></span>
                <?php if ($product['offer_price']): ?>
                    <span>•</span>
                    <span>Master Offer Price: <strong class="text-rose"><?php echo format_price($product['offer_price']); ?></strong></span>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <span class="badge bg-primary-subtle text-primary border px-3 py-2">
                <i class="bi bi-grid-3x3-gap me-1"></i> <?php echo count($variants); ?> Configured SKUs
            </span>
        </div>
    </div>
</div>

<!-- Batch Price & Stock Updater Collapse -->
<div class="collapse mb-4" id="batchUpdateCard">
    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px; border-left: 4px solid var(--brand-charcoal) !important;">
        <h5 class="fw-bold mb-2"><i class="bi bi-lightning-charge-fill text-warning me-2"></i> Batch Update Sizing Prices & Stock</h5>
        <p class="text-muted small mb-3">Quickly update Regular Price, Offer Price, or Stock across all variants or specific sizes (e.g., oversized / plus sizes like 38D, 40D, 42E).</p>
        
        <form action="variants.php?product_id=<?php echo $product_id; ?>" method="POST" class="row g-3 align-items-end">
            <?php echo csrf_field(); ?>
            <div class="col-12 col-md-3">
                <label for="batch_target" class="form-label small fw-bold">Target Variants</label>
                <select name="batch_target" id="batch_target" class="form-select">
                    <option value="all">All Sizes (All <?php echo count($variants); ?> SKUs)</option>
                    <?php if (!empty($matrix_sizes)): ?>
                        <optgroup label="Specific Sizes in Matrix">
                            <?php foreach ($matrix_sizes as $msz): ?>
                                <option value="size_<?php echo $msz['id']; ?>">Size <?php echo e($msz['size_code']); ?> (<?php echo e($msz['size_name']); ?>)</option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="batch_regular_price" class="form-label small fw-bold">Regular Price (MRP)</label>
                <input type="number" step="0.01" class="form-control" id="batch_regular_price" name="batch_regular_price" placeholder="Leave unchanged">
            </div>
            <div class="col-6 col-md-2">
                <label for="batch_offer_price" class="form-label small fw-bold">Offer / Selling Price</label>
                <input type="number" step="0.01" class="form-control" id="batch_offer_price" name="batch_offer_price" placeholder="Leave unchanged">
            </div>
            <div class="col-6 col-md-2">
                <label for="batch_stock" class="form-label small fw-bold">Set Stock</label>
                <input type="number" class="form-control" id="batch_stock" name="batch_stock" placeholder="Leave unchanged" min="0">
            </div>
            <div class="col-6 col-md-3 d-flex flex-column gap-1">
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="batch_clear_prices" id="batch_clear_prices" value="1">
                    <label class="form-check-label small text-muted" for="batch_clear_prices">Reset to Master Prices</label>
                </div>
                <button type="submit" name="batch_update_variants" class="btn btn-dark w-100" onclick="return confirm('Apply batch update to the selected variants?');">
                    Apply Batch Update
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <!-- Add new variant form -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <h5 class="fw-bold mb-3">Add Variant SKU</h5>
            
            <?php if (!empty($error) && isset($_POST['add_variant'])): ?>
                <div class="alert alert-danger p-2 small"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form action="variants.php?product_id=<?php echo $product_id; ?>" method="POST">
                <?php echo csrf_field(); ?>
                
                <div class="mb-3">
                    <label for="size_id" class="form-label small fw-bold">Select Size <span class="text-danger">*</span></label>
                    <select class="form-select" id="size_id" name="size_id" required>
                        <option value="">-- Choose Size --</option>
                        <?php if (!empty($matching_sizes)): ?>
                            <optgroup label="Sizes for <?php echo e($product['category_name'] ?: 'this Product'); ?>">
                                <?php foreach ($matching_sizes as $sz): ?>
                                    <option value="<?php echo $sz['id']; ?>" data-size-code="<?php echo e($sz['size_code']); ?>">
                                        <?php echo e($sz['size_code']); ?> (<?php echo e($sz['size_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php 
                            $other_sizes = array_filter($all_sizes, function($s) use ($matching_sizes) {
                                return !in_array($s['id'], array_column($matching_sizes, 'id'));
                            });
                            if (!empty($other_sizes)): ?>
                                <optgroup label="Other Category Sizes">
                                    <?php foreach ($other_sizes as $sz): ?>
                                        <option value="<?php echo $sz['id']; ?>" data-size-code="<?php echo e($sz['size_code']); ?>">
                                            <?php echo e($sz['size_code']); ?> (<?php echo e($sz['category_type']); ?> - <?php echo e($sz['size_name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php foreach ($all_sizes as $sz): ?>
                                <option value="<?php echo $sz['id']; ?>" data-size-code="<?php echo e($sz['size_code']); ?>">
                                    <?php echo e($sz['size_code']); ?> (<?php echo e($sz['category_type']); ?> - <?php echo e($sz['size_name']); ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="color_id" class="form-label small fw-bold">Select Color Swatch <span class="text-danger">*</span></label>
                    <select class="form-select" id="color_id" name="color_id" required>
                        <option value="">-- Choose Color --</option>
                        <?php if (!empty($product_colors)): ?>
                            <optgroup label="Colors Available for this Product (<?php echo count($product_colors); ?>)">
                                <?php foreach ($product_colors as $cl): ?>
                                    <option value="<?php echo $cl['id']; ?>" data-color-short="<?php echo e(get_color_short_code($cl['color_name'], $cl['short_code'] ?? null)); ?>">
                                        <?php echo e($cl['color_name']); ?> (<?php echo e($cl['color_code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php 
                            $other_colors = array_filter($all_colors, function($c) use ($product_colors) {
                                return !in_array($c['id'], array_column($product_colors, 'id'));
                            });
                            if (!empty($other_colors)): ?>
                                <optgroup label="Other Catalog Colors">
                                    <?php foreach ($other_colors as $cl): ?>
                                        <option value="<?php echo $cl['id']; ?>" data-color-short="<?php echo e(get_color_short_code($cl['color_name'], $cl['short_code'] ?? null)); ?>">
                                            <?php echo e($cl['color_name']); ?> (<?php echo e($cl['color_code']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php foreach ($all_colors as $cl): ?>
                                <option value="<?php echo $cl['id']; ?>" data-color-short="<?php echo e(get_color_short_code($cl['color_name'], $cl['short_code'] ?? null)); ?>">
                                    <?php echo e($cl['color_name']); ?> (<?php echo e($cl['color_code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="sku" class="form-label small fw-bold">Variant SKU <span class="text-muted fw-normal">(Auto-generated if empty)</span></label>
                    <input type="text" class="form-control" id="sku" name="sku" placeholder="e.g. <?php echo e($product['sku']); ?>-SIZE-COLOR">
                    <small class="text-muted d-block mt-1" id="sku-hint">Pattern: <code><?php echo e($product['sku']); ?>-[SIZE]-[COLOR]</code></small>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="regular_price" class="form-label small fw-bold">Regular Price (MRP)</label>
                        <input type="number" step="0.01" class="form-control" id="regular_price" name="regular_price" placeholder="<?php echo e($product['regular_price']); ?>">
                        <small class="text-muted" style="font-size:11px;">Override MRP for large sizes</small>
                    </div>
                    <div class="col-6">
                        <label for="offer_price" class="form-label small fw-bold">Offer Price</label>
                        <input type="number" step="0.01" class="form-control" id="offer_price" name="offer_price" placeholder="<?php echo e($product['offer_price'] ?? $product['regular_price']); ?>">
                        <small class="text-muted" style="font-size:11px;">Active selling price</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="stock" class="form-label small fw-bold">Initial Stock Count</label>
                    <input type="number" class="form-control" id="stock" name="stock" value="10" min="0">
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" name="add_variant" class="btn btn-brand">Create SKU Variant</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Variants grid list -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">Existing Variant Matrix</h5>
                <small class="text-muted"><?php echo count($variants); ?> variant(s) available</small>
            </div>
            
            <?php if (!empty($error) && (isset($_POST['edit_variant']) || isset($_POST['delete_variant']) || isset($_POST['batch_update_variants']))): ?>
                <div class="alert alert-danger p-2 small"><?php echo e($error); ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Size</th>
                            <th>Color</th>
                            <th>SKU</th>
                            <th>Pricing (Selling / MRP)</th>
                            <th>Stock</th>
                            <th class="text-end" style="width: 140px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($variants)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No variants added for this product yet. Add sizing combinations on the left form.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($variants as $var): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo e($var['size_code']); ?></span>
                                        <span class="small text-muted ms-1"><?php echo e($var['size_name']); ?></span>
                                    </td>
                                    <td>
                                        <span class="d-flex align-items-center gap-1">
                                            <span class="border rounded-circle d-inline-block" style="background-color: <?php echo e($var['color_code']); ?>; width: 16px; height: 16px;"></span>
                                            <?php echo e($var['color_name']); ?>
                                        </span>
                                    </td>
                                    <td><code><?php echo e($var['sku']); ?></code></td>
                                    <td>
                                        <?php 
                                        $has_reg_override = ($var['regular_price'] !== null && $var['regular_price'] > 0);
                                        $has_off_override = ($var['offer_price'] !== null && $var['offer_price'] > 0);
                                        $has_legacy_override = (!$has_off_override && !$has_reg_override && $var['price'] !== null && $var['price'] > 0);

                                        if ($has_reg_override || $has_off_override || $has_legacy_override): ?>
                                            <div>
                                                <?php if ($has_off_override): ?>
                                                    <span class="fw-bold text-rose"><?php echo format_price($var['offer_price']); ?></span>
                                                <?php elseif ($has_legacy_override): ?>
                                                    <span class="fw-bold text-rose"><?php echo format_price($var['price']); ?></span>
                                                <?php else: ?>
                                                    <span class="fw-bold text-rose"><?php echo format_price($product['offer_price'] ?? $var['regular_price']); ?></span>
                                                <?php endif; ?>

                                                <?php if ($has_reg_override): ?>
                                                    <br><span class="text-muted small text-decoration-line-through">MRP: <?php echo format_price($var['regular_price']); ?></span>
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border" style="font-size: 10px;">Custom MRP</span>
                                                <?php else: ?>
                                                    <br><span class="text-muted small">MRP: <?php echo format_price($product['regular_price']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">Default (<?php echo format_price($product['offer_price'] ?? $product['regular_price']); ?> / MRP <?php echo format_price($product['regular_price']); ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($var['stock'] > 5): ?>
                                            <span class="badge bg-success"><?php echo $var['stock']; ?> units</span>
                                        <?php elseif ($var['stock'] > 0): ?>
                                            <span class="badge bg-warning text-dark">Low (<?php echo $var['stock']; ?>)</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Out of stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex gap-1 justify-content-end">
                                            <!-- Edit Trigger modal button -->
                                            <button class="btn btn-sm btn-outline-secondary p-1 px-2" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#variantEditModal" 
                                                    data-variant-id="<?php echo $var['id']; ?>"
                                                    data-sku="<?php echo e($var['sku']); ?>"
                                                    data-regular-price="<?php echo e($var['regular_price'] ?? ''); ?>"
                                                    data-offer-price="<?php echo e($var['offer_price'] ?? ($var['price'] ?? '')); ?>"
                                                    data-stock="<?php echo $var['stock']; ?>"
                                                    title="Quick Edit Pricing & Stock">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            
                                            <!-- Delete Variant -->
                                            <form action="variants.php?product_id=<?php echo $product_id; ?>" method="POST" onsubmit="return confirm('Delete this SKU variant? All inventory and logs will be deleted.');" style="display:inline;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="variant_id" value="<?php echo $var['id']; ?>">
                                                <button type="submit" name="delete_variant" class="btn btn-sm btn-outline-danger p-1 px-2" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Variant Edit Modal -->
<div class="modal fade" id="variantEditModal" tabindex="-1" aria-labelledby="variantEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius:16px;">
            <div class="modal-header border-0 bg-light" style="border-top-left-radius:16px; border-top-right-radius:16px;">
                <h5 class="modal-title fw-bold" id="variantEditModalLabel">Edit Variant SKU Pricing & Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="variants.php?product_id=<?php echo $product_id; ?>" method="POST">
                <div class="modal-body p-4">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="variant_id" id="modal-variant-id">
                    
                    <div class="mb-3">
                        <label for="modal-sku" class="form-label small fw-bold">Variant SKU</label>
                        <input type="text" class="form-control" name="sku" id="modal-sku" readonly>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="modal-regular-price" class="form-label small fw-bold">Regular Price (MRP Override)</label>
                            <input type="number" step="0.01" class="form-control" name="regular_price" id="modal-regular-price" placeholder="Default: <?php echo e($product['regular_price']); ?>">
                            <small class="text-muted" style="font-size: 11px;">Leave blank to use master MRP</small>
                        </div>
                        <div class="col-6">
                            <label for="modal-offer-price" class="form-label small fw-bold">Offer / Selling Price</label>
                            <input type="number" step="0.01" class="form-control" name="offer_price" id="modal-offer-price" placeholder="Default: <?php echo e($product['offer_price'] ?? $product['regular_price']); ?>">
                            <small class="text-muted" style="font-size: 11px;">Leave blank to use master Offer</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="modal-stock" class="form-label small fw-bold">Inventory Stock Count</label>
                        <input type="number" class="form-control" name="stock" id="modal-stock" required min="0">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_variant" class="btn btn-brand">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const baseSku = <?php echo json_encode($product['sku']); ?>;
    const sizeSelect = document.getElementById('size_id');
    const colorSelect = document.getElementById('color_id');
    const skuInput = document.getElementById('sku');
    const skuHint = document.getElementById('sku-hint');
    let userEditedSku = false;

    if (skuInput) {
        skuInput.addEventListener('input', function() {
            userEditedSku = this.value.trim() !== '';
        });
    }

    function updateSuggestedSku() {
        if (!sizeSelect || !colorSelect || !skuInput) return;
        if (userEditedSku && skuInput.value.trim() !== '') return;
        
        const sizeOption = sizeSelect.options[sizeSelect.selectedIndex];
        const colorOption = colorSelect.options[colorSelect.selectedIndex];

        const sizeCode = sizeOption && sizeOption.dataset.sizeCode ? sizeOption.dataset.sizeCode : '';
        const colorShort = colorOption && colorOption.dataset.colorShort ? colorOption.dataset.colorShort : '';

        if (sizeCode && colorShort) {
            const previewSku = baseSku + '-' + sizeCode + '-' + colorShort;
            skuInput.placeholder = previewSku;
            if (skuHint) skuHint.innerHTML = 'Suggested SKU: <code>' + previewSku + '</code>';
        } else if (sizeCode) {
            skuInput.placeholder = baseSku + '-' + sizeCode + '-[COLOR]';
            if (skuHint) skuHint.innerHTML = 'Pattern: <code>' + baseSku + '-' + sizeCode + '-[COLOR]</code>';
        } else if (colorShort) {
            skuInput.placeholder = baseSku + '-[SIZE]-' + colorShort;
            if (skuHint) skuHint.innerHTML = 'Pattern: <code>' + baseSku + '-[SIZE]-' + colorShort + '</code>';
        } else {
            skuInput.placeholder = 'e.g. ' + baseSku + '-SIZE-COLOR';
            if (skuHint) skuHint.innerHTML = 'Pattern: <code>' + baseSku + '-[SIZE]-[COLOR]</code>';
        }
    }

    if (sizeSelect) sizeSelect.addEventListener('change', updateSuggestedSku);
    if (colorSelect) colorSelect.addEventListener('change', updateSuggestedSku);

    // Modal populate logic
    const editModal = document.getElementById('variantEditModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            const variantId = button.getAttribute('data-variant-id');
            const sku = button.getAttribute('data-sku');
            const regularPrice = button.getAttribute('data-regular-price');
            const offerPrice = button.getAttribute('data-offer-price');
            const stock = button.getAttribute('data-stock');

            document.getElementById('modal-variant-id').value = variantId || '';
            document.getElementById('modal-sku').value = sku || '';
            document.getElementById('modal-regular-price').value = regularPrice || '';
            document.getElementById('modal-offer-price').value = offerPrice || '';
            document.getElementById('modal-stock').value = stock || '0';
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
