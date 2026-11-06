<?php
// admin/inventory.php
// Inventory Stock Ledger & Logs Manager

$page_title = 'Inventory Management';
$active_menu = 'products';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Check permissions
require_admin_permission('products');

$error = '';
$success = '';

// Handle restock / adjustment post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        if (isset($_POST['adjust_stock'])) {
            $variant_id = (int)($_POST['variant_id'] ?? 0);
            $adjustment_mode = $_POST['adjustment_mode'] ?? 'exact'; // 'exact' or 'relative'
            $change_type = $_POST['change_type'] ?? 'adjustment';
            $notes = trim($_POST['notes'] ?? '');

            if (empty($variant_id)) {
                $error = 'Please select a valid product variant to adjust.';
            } else {
                try {
                    // Fetch target variant and current stock
                    $v_stmt = $pdo->prepare("SELECT stock, sku FROM product_variants WHERE id = ? LIMIT 1");
                    $v_stmt->execute([$variant_id]);
                    $variant = $v_stmt->fetch();

                    if ($variant) {
                        $current_stock = (int)$variant['stock'];

                        if ($adjustment_mode === 'exact') {
                            $new_stock = (int)($_POST['new_stock_total'] ?? 0);
                            $qty_change = $new_stock - $current_stock;
                            if ($qty_change > 0 && ($change_type === 'adjustment' || empty($change_type))) {
                                $change_type = 'restock';
                            }
                        } else {
                            $qty_change = (int)($_POST['quantity_changed'] ?? 0);
                            $new_stock = $current_stock + $qty_change;
                        }

                        if ($qty_change == 0) {
                            $error = 'No change in quantity was specified (stock is already ' . $current_stock . ').';
                        } elseif ($new_stock < 0) {
                            $error = 'Negative stock level is not allowed. Adjust quantity accordingly.';
                        } else {
                            $pdo->beginTransaction();

                            // Update stock count
                            $update = $pdo->prepare("UPDATE product_variants SET stock = ? WHERE id = ?");
                            $update->execute([$new_stock, $variant_id]);

                            // Log transaction
                            $log_stmt = $pdo->prepare("
                                INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) 
                                VALUES (?, ?, ?, ?)
                            ");
                            $log_stmt->execute([$variant_id, $qty_change, $change_type, $notes]);

                            $pdo->commit();
                            $change_str = ($qty_change > 0 ? "+{$qty_change}" : (string)$qty_change);
                            log_admin_action($current_admin_id, "Adjusted stock for variant {$variant['sku']} (Change: {$change_str}, New Stock: {$new_stock})");
                            set_flash_message('success', "Stock for {$variant['sku']} successfully updated to {$new_stock} units ({$change_str}).");
                            header('Location: inventory.php');
                            exit;
                        }
                    } else {
                        $error = 'Target product variant not found.';
                    }
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = 'Inventory adjust failed: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all product variants with details including category
$all_variants = $pdo->query("
    SELECT v.id, v.sku, v.stock, p.name as product_name, p.category_id, cat.name as category_name, 
           s.size_code, s.size_name, c.color_name, c.color_code 
    FROM product_variants v 
    JOIN products p ON v.product_id = p.id 
    LEFT JOIN categories cat ON p.category_id = cat.id 
    JOIN sizes s ON v.size_id = s.id 
    JOIN colors c ON v.color_id = c.id 
    ORDER BY p.name ASC, s.id ASC
")->fetchAll();

// Compute inventory metrics
$total_variants = count($all_variants);
$total_units = 0;
$low_stock_count = 0;
$out_of_stock_count = 0;
$in_stock_count = 0;
$categories = [];

foreach ($all_variants as $v) {
    $stk = (int)$v['stock'];
    $total_units += $stk;
    if ($stk <= 0) {
        $out_of_stock_count++;
    } elseif ($stk <= 5) {
        $low_stock_count++;
    } else {
        $in_stock_count++;
    }

    $cname = trim($v['category_name'] ?: 'Uncategorized');
    $categories[$cname] = ($categories[$cname] ?? 0) + 1;
}
ksort($categories);

// Fetch historical inventory logs
$logs = $pdo->query("
    SELECT l.*, v.sku as variant_sku, p.name as product_name 
    FROM inventory_logs l 
    JOIN product_variants v ON l.variant_id = v.id 
    JOIN products p ON v.product_id = p.id 
    ORDER BY l.logged_at DESC 
    LIMIT 30
")->fetchAll();
?>

<!-- KPI Metric Cards (Clickable Quick Filters) -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 bg-white h-100 kpi-stat-card" data-status="all" style="border-radius: 12px; cursor: pointer;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-medium">Total Variants</span>
                <span class="p-2 bg-light rounded-circle text-primary"><i class="bi bi-box-seam fs-5"></i></span>
            </div>
            <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($total_variants); ?></h3>
            <small class="text-muted">Total SKUs in store</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 bg-white h-100" style="border-radius: 12px;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-medium">Total In Stock</span>
                <span class="p-2 bg-light rounded-circle text-success"><i class="bi bi-stack fs-5"></i></span>
            </div>
            <h3 class="fw-bold mb-0 text-success"><?php echo number_format($total_units); ?></h3>
            <small class="text-muted">Physical warehouse units</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 bg-white h-100 kpi-stat-card" data-status="low" style="border-radius: 12px; cursor: pointer; border-left: 4px solid #ffc107 !important;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-medium">Low Stock</span>
                <span class="p-2 bg-warning-subtle rounded-circle text-warning"><i class="bi bi-exclamation-triangle fs-5"></i></span>
            </div>
            <h3 class="fw-bold mb-0 text-warning"><?php echo $low_stock_count; ?></h3>
            <small class="text-muted">&le; 5 units remaining (Click to filter)</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 bg-white h-100 kpi-stat-card" data-status="out" style="border-radius: 12px; cursor: pointer; border-left: 4px solid #dc3545 !important;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-medium">Out of Stock</span>
                <span class="p-2 bg-danger-subtle rounded-circle text-danger"><i class="bi bi-x-circle fs-5"></i></span>
            </div>
            <h3 class="fw-bold mb-0 text-danger"><?php echo $out_of_stock_count; ?></h3>
            <small class="text-muted">0 units remaining (Click to filter)</small>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger p-3 mb-4 rounded-3 shadow-sm">
        <i class="bi bi-exclamation-octagon me-2"></i><?php echo e($error); ?>
    </div>
<?php endif; ?>

<!-- Main Inventory Ledger Matrix Card -->
<div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="fw-bold mb-0">Inventory Ledger Matrix</h5>
            <small class="text-muted" id="inventoryCountBadge">Showing <?php echo $total_variants; ?> of <?php echo $total_variants; ?> variants</small>
        </div>
        <div>
            <button type="button" class="btn btn-sm btn-brand rounded-pill px-3 py-2" id="openManualAdjustBtn">
                <i class="bi bi-plus-circle me-1"></i> Quick Adjust by SKU
            </button>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-light p-3 rounded-3 mb-3 border">
        <div class="row g-2 align-items-center">
            <!-- Live Search -->
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="inventorySearch" class="form-control border-start-0 ps-0" placeholder="Search product, SKU, size, color...">
                    <button class="btn btn-outline-secondary border-start-0" type="button" id="clearInventorySearch" style="display:none;" title="Clear">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Category Filter Dropdown -->
            <div class="col-6 col-md-3">
                <select id="inventoryCategoryFilter" class="form-select">
                    <option value="all">All Categories (<?php echo $total_variants; ?>)</option>
                    <?php foreach ($categories as $cat => $cnt): ?>
                        <option value="<?php echo e(strtolower($cat)); ?>"><?php echo e($cat); ?> (<?php echo $cnt; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Stock Status Filter Buttons -->
            <div class="col-12 col-md-5 d-flex flex-wrap gap-1 justify-content-md-end align-items-center">
                <span class="small text-muted me-1 d-none d-lg-inline">Stock:</span>
                <button type="button" class="btn btn-sm btn-dark rounded-pill px-3 py-1 stock-status-btn active" data-status="all">All</button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 stock-status-btn" data-status="in">In Stock (>5)</button>
                <button type="button" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3 py-1 stock-status-btn" data-status="low">Low (&le;5)</button>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 stock-status-btn" data-status="out">Out of Stock (0)</button>
                <button type="button" class="btn btn-sm btn-link text-decoration-none text-muted p-1 ms-1" id="resetInventoryFiltersBtn" title="Reset all filters">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </button>
            </div>
        </div>
    </div>

    <!-- Inventory Matrix Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="inventoryTable">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 140px;">SKU</th>
                    <th>Product & Category</th>
                    <th style="min-width: 150px;">Attributes</th>
                    <th style="min-width: 130px;">Current Stock</th>
                    <th class="text-end" style="min-width: 110px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_variants)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="bi bi-box-seam fs-3 d-block mb-2"></i>
                            No product variants configured yet. Add products and variants to start tracking stock.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($all_variants as $v): ?>
                        <?php 
                        $stk = (int)$v['stock'];
                        $stk_status = ($stk <= 0 ? 'out' : ($stk <= 5 ? 'low' : 'in'));
                        ?>
                        <tr class="variant-table-row" 
                            data-sku="<?php echo e($v['sku']); ?>"
                            data-product="<?php echo e($v['product_name']); ?>"
                            data-category="<?php echo e(strtolower($v['category_name'] ?: 'uncategorized')); ?>"
                            data-size="<?php echo e($v['size_code']); ?>"
                            data-color="<?php echo e($v['color_name']); ?>"
                            data-stock="<?php echo $stk; ?>"
                            data-status="<?php echo $stk_status; ?>"
                            data-search="<?php echo e(strtolower($v['sku'] . ' ' . $v['product_name'] . ' ' . ($v['category_name'] ?: '') . ' ' . $v['size_code'] . ' ' . ($v['size_name'] ?: '') . ' ' . $v['color_name'])); ?>">
                            <td>
                                <code><?php echo e($v['sku']); ?></code>
                            </td>
                            <td>
                                <span class="fw-bold text-dark d-block"><?php echo e($v['product_name']); ?></span>
                                <span class="badge bg-light text-secondary border small"><?php echo e($v['category_name'] ?: 'General'); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-dark border me-1">Size: <?php echo e($v['size_code']); ?></span>
                                <span class="d-inline-flex align-items-center gap-1 small text-muted">
                                    <span class="d-inline-block rounded-circle border" style="width: 10px; height: 10px; background-color: <?php echo e($v['color_code'] ?: '#ccc'); ?>;"></span>
                                    <?php echo e($v['color_name']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($stk > 5): ?>
                                    <span class="badge bg-success-subtle text-success border border-success px-2 py-1">
                                        <i class="bi bi-check-circle me-1"></i><?php echo $stk; ?> units
                                    </span>
                                <?php elseif ($stk > 0): ?>
                                    <span class="badge bg-warning-subtle text-dark border border-warning px-2 py-1">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Low (<?php echo $stk; ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1">
                                        <i class="bi bi-x-circle me-1"></i>Out of stock
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button type="button" 
                                        class="btn btn-sm btn-outline-brand rounded-pill px-3 py-1 btn-quick-adjust"
                                        data-id="<?php echo $v['id']; ?>"
                                        data-sku="<?php echo e($v['sku']); ?>"
                                        data-product="<?php echo e($v['product_name']); ?>"
                                        data-category="<?php echo e($v['category_name'] ?: 'General'); ?>"
                                        data-size="<?php echo e($v['size_code']); ?>"
                                        data-color="<?php echo e($v['color_name']); ?>"
                                        data-stock="<?php echo $stk; ?>"
                                        title="Adjust Stock for this SKU">
                                    <i class="bi bi-sliders me-1"></i>Adjust
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="noInventoryMatchRow" style="display: none;">
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="bi bi-funnel text-secondary fs-3 d-block mb-2"></i>
                            No product variants matched your search or filters.
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" id="resetInventoryEmptyBtn">
                                    Clear Filters
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Historical Stock Transaction Log -->
<div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
    <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i> Inventory Adjustments Log History (Recent 30 entries)</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date / Time</th>
                    <th>Product / Variant</th>
                    <th>SKU</th>
                    <th>Change</th>
                    <th>Transaction Type</th>
                    <th>Reference Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No inventory adjustments logged yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="small text-muted"><?php echo date('d-M-Y H:i:s', strtotime($log['logged_at'])); ?></td>
                            <td><span class="fw-bold text-dark"><?php echo e($log['product_name']); ?></span></td>
                            <td><code><?php echo e($log['variant_sku']); ?></code></td>
                            <td>
                                <?php if ($log['quantity_changed'] > 0): ?>
                                    <span class="badge bg-success-subtle text-success border border-success fw-bold">+<?php echo $log['quantity_changed']; ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger fw-bold"><?php echo $log['quantity_changed']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $lbl = 'bg-secondary';
                                if($log['change_type'] === 'restock') $lbl = 'bg-success';
                                else if($log['change_type'] === 'sale') $lbl = 'bg-primary';
                                else if($log['change_type'] === 'return') $lbl = 'bg-info';
                                else if($log['change_type'] === 'adjustment') $lbl = 'bg-warning text-dark';
                                ?>
                                <span class="badge <?php echo $lbl; ?>"><?php echo strtoupper($log['change_type']); ?></span>
                            </td>
                            <td class="small text-muted"><?php echo e($log['notes'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Quick Stock Adjustment Modal -->
<div class="modal fade" id="quickAdjustModal" tabindex="-1" aria-labelledby="quickAdjustModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 14px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="quickAdjustModalLabel">
                    <i class="bi bi-sliders text-primary me-2"></i>Adjust Stock Level
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="inventory.php" method="POST" id="quickAdjustForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="variant_id" id="modal_variant_id" value="">
                <input type="hidden" name="adjustment_mode" id="modal_adjustment_mode" value="exact">
                
                <div class="modal-body p-4">
                    <!-- Variant Selector (Active when opened via manual adjust button) -->
                    <div id="modal_sku_selector_container" class="mb-3" style="display:none;">
                        <label for="modal_variant_select" class="form-label fw-bold small text-muted">Choose Variant to Adjust <span class="text-danger">*</span></label>
                        <select class="form-select" id="modal_variant_select">
                            <option value="">-- Search & Choose SKU Variant --</option>
                            <?php foreach ($all_variants as $v): ?>
                                <option value="<?php echo $v['id']; ?>"
                                        data-sku="<?php echo e($v['sku']); ?>"
                                        data-product="<?php echo e($v['product_name']); ?>"
                                        data-category="<?php echo e($v['category_name'] ?: 'General'); ?>"
                                        data-size="<?php echo e($v['size_code']); ?>"
                                        data-color="<?php echo e($v['color_name']); ?>"
                                        data-stock="<?php echo (int)$v['stock']; ?>">
                                    <?php echo e($v['sku']); ?> — <?php echo e($v['product_name']); ?> (Size: <?php echo e($v['size_code']); ?>, Color: <?php echo e($v['color_name']); ?>) [Current Stock: <?php echo $v['stock']; ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Target SKU Details Card -->
                    <div class="p-3 bg-light rounded-3 mb-3 border" id="modal_variant_card">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="fw-bold mb-1 text-dark" id="modal_product_name">-</h6>
                                <span class="small text-muted">SKU: <code id="modal_sku" class="fs-6">-</code></span>
                            </div>
                            <span class="badge bg-dark fs-6 px-3 py-2" id="modal_current_stock_badge">Current: 0 units</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2 small">
                            <span class="badge bg-light text-dark border" id="modal_category_badge">-</span>
                            <span class="badge bg-secondary" id="modal_size_badge">Size: -</span>
                            <span class="badge bg-light text-dark border" id="modal_color_badge">Color: -</span>
                        </div>
                    </div>

                    <!-- Mode Toggle Buttons -->
                    <label class="form-label fw-bold small text-muted mb-2">Adjustment Method</label>
                    <div class="btn-group w-100 mb-3" role="group">
                        <button type="button" class="btn btn-outline-primary active mode-pill-btn" data-mode="exact" id="modeExactBtn">
                            <i class="bi bi-bullseye me-1"></i> Set Exact Stock Count
                        </button>
                        <button type="button" class="btn btn-outline-primary mode-pill-btn" data-mode="relative" id="modeRelativeBtn">
                            <i class="bi bi-plus-slash-minus me-1"></i> Add / Deduct (+/-)
                        </button>
                    </div>

                    <!-- Mode 1: Exact Stock Count -->
                    <div id="modeExactContainer" class="mb-3">
                        <div class="form-floating">
                            <input type="number" min="0" class="form-control" id="modal_new_stock_total" name="new_stock_total" placeholder="Exact Physical Stock">
                            <label for="modal_new_stock_total">Enter Actual Stock Count on Shelf <span class="text-danger">*</span></label>
                        </div>
                        <div class="form-text small mt-1" id="exactCalculationNote">
                            Enter the physical quantity counted. The system will automatically compute the difference.
                        </div>
                    </div>

                    <!-- Mode 2: Relative Quantity Change -->
                    <div id="modeRelativeContainer" class="mb-3" style="display:none;">
                        <div class="form-floating">
                            <input type="number" class="form-control" id="modal_quantity_changed" name="quantity_changed" placeholder="Quantity (+ or -)">
                            <label for="modal_quantity_changed">Quantity Difference (+ or -) <span class="text-danger">*</span></label>
                        </div>
                        <div class="form-text small mt-1" id="relativeCalculationNote">
                            Enter positive numbers (e.g. <code>10</code>) to add stock, or negative numbers (e.g. <code>-3</code>) to deduct stock.
                        </div>
                    </div>

                    <!-- Transaction Reason -->
                    <div class="form-floating mb-3">
                        <select class="form-select" id="modal_change_type" name="change_type" required>
                            <option value="restock">Restock (Received new shipment / supplier)</option>
                            <option value="adjustment" selected>Physical Count Adjustment / Correction</option>
                            <option value="sale">Manual Offline Sale</option>
                            <option value="return">Customer Return / Exchange Restock</option>
                        </select>
                        <label for="modal_change_type">Transaction Reason <span class="text-danger">*</span></label>
                    </div>

                    <!-- Notes -->
                    <div class="form-floating mb-1">
                        <input type="text" class="form-control" id="modal_notes" name="notes" placeholder="Internal notes (optional)">
                        <label for="modal_notes">Internal Reference / Notes (Optional)</label>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light p-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="adjust_stock" class="btn btn-brand rounded-pill px-4" id="submitAdjustBtn">
                        <i class="bi bi-check2-circle me-1"></i> Update Stock Level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Table Filtering & Search ---
    const searchInput = document.getElementById('inventorySearch');
    const clearSearchBtn = document.getElementById('clearInventorySearch');
    const categoryFilter = document.getElementById('inventoryCategoryFilter');
    const statusButtons = document.querySelectorAll('.stock-status-btn');
    const variantRows = document.querySelectorAll('.variant-table-row');
    const noMatchRow = document.getElementById('noInventoryMatchRow');
    const countBadge = document.getElementById('inventoryCountBadge');
    const resetBtn = document.getElementById('resetInventoryFiltersBtn');
    const resetEmptyBtn = document.getElementById('resetInventoryEmptyBtn');
    const kpiCards = document.querySelectorAll('.kpi-stat-card');

    let currentSearch = '';
    let currentCategory = 'all';
    let currentStatus = 'all';

    function filterInventory() {
        let visibleCount = 0;
        const search = (currentSearch || '').toLowerCase().trim();
        const cat = (currentCategory || 'all').toLowerCase().trim();
        const stat = (currentStatus || 'all').toLowerCase().trim();

        variantRows.forEach(row => {
            const rowCat = (row.getAttribute('data-category') || '').toLowerCase().trim();
            const rowStat = (row.getAttribute('data-status') || '').toLowerCase().trim();
            const rowSearch = (row.getAttribute('data-search') || '').toLowerCase();

            const matchesCategory = (cat === 'all') || (rowCat === cat);
            const matchesStatus = (stat === 'all') || (rowStat === stat);
            const matchesSearch = !search || rowSearch.includes(search);

            if (matchesCategory && matchesStatus && matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (noMatchRow) {
            noMatchRow.style.display = (visibleCount === 0 && variantRows.length > 0) ? '' : 'none';
        }

        if (countBadge) {
            countBadge.textContent = `Showing ${visibleCount} of ${variantRows.length} variants`;
        }

        if (clearSearchBtn) {
            clearSearchBtn.style.display = search ? '' : 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            currentSearch = this.value;
            filterInventory();
        });
    }

    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            currentSearch = '';
            filterInventory();
            searchInput.focus();
        });
    }

    if (categoryFilter) {
        categoryFilter.addEventListener('change', function() {
            currentCategory = this.value;
            filterInventory();
        });
    }

    function setStatusFilter(status) {
        currentStatus = status;
        statusButtons.forEach(btn => {
            if (btn.getAttribute('data-status') === status) {
                btn.classList.remove('btn-outline-secondary', 'btn-outline-warning', 'btn-outline-danger');
                if (status === 'low') btn.classList.add('btn-warning');
                else if (status === 'out') btn.classList.add('btn-danger');
                else btn.classList.add('btn-dark', 'active');
            } else {
                btn.classList.remove('btn-dark', 'btn-warning', 'btn-danger', 'active');
                const btnStatus = btn.getAttribute('data-status');
                if (btnStatus === 'low') btn.classList.add('btn-outline-warning');
                else if (btnStatus === 'out') btn.classList.add('btn-outline-danger');
                else btn.classList.add('btn-outline-secondary');
            }
        });
        filterInventory();
    }

    statusButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            setStatusFilter(this.getAttribute('data-status') || 'all');
        });
    });

    kpiCards.forEach(card => {
        card.addEventListener('click', function() {
            const status = this.getAttribute('data-status');
            if (status) {
                setStatusFilter(status);
                // Scroll smoothly to table
                const tableCard = document.getElementById('inventoryTable');
                if (tableCard) {
                    tableCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    });

    function resetFilters() {
        if (searchInput) searchInput.value = '';
        currentSearch = '';
        if (categoryFilter) categoryFilter.value = 'all';
        currentCategory = 'all';
        setStatusFilter('all');
    }

    if (resetBtn) resetBtn.addEventListener('click', resetFilters);
    if (resetEmptyBtn) resetEmptyBtn.addEventListener('click', resetFilters);


    // --- Quick Stock Adjustment Modal ---
    const adjustModalEl = document.getElementById('quickAdjustModal');
    if (!adjustModalEl) return;
    const adjustModal = new bootstrap.Modal(adjustModalEl);

    const modalVariantId = document.getElementById('modal_variant_id');
    const modalAdjustmentMode = document.getElementById('modal_adjustment_mode');
    const modalSku = document.getElementById('modal_sku');
    const modalProductName = document.getElementById('modal_product_name');
    const modalCurrentStockBadge = document.getElementById('modal_current_stock_badge');
    const modalCategoryBadge = document.getElementById('modal_category_badge');
    const modalSizeBadge = document.getElementById('modal_size_badge');
    const modalColorBadge = document.getElementById('modal_color_badge');
    const modalSelectorContainer = document.getElementById('modal_sku_selector_container');
    const modalVariantSelect = document.getElementById('modal_variant_select');
    const modalVariantCard = document.getElementById('modal_variant_card');

    const modeExactBtn = document.getElementById('modeExactBtn');
    const modeRelativeBtn = document.getElementById('modeRelativeBtn');
    const modeExactContainer = document.getElementById('modeExactContainer');
    const modeRelativeContainer = document.getElementById('modeRelativeContainer');
    const inputExact = document.getElementById('modal_new_stock_total');
    const inputRelative = document.getElementById('modal_quantity_changed');
    const exactNote = document.getElementById('exactCalculationNote');
    const relativeNote = document.getElementById('relativeCalculationNote');
    const selectChangeType = document.getElementById('modal_change_type');

    let currentModalStock = 0;

    function populateModalWithVariant(id, sku, product, category, size, color, stock) {
        currentModalStock = parseInt(stock, 10) || 0;
        modalVariantId.value = id;
        modalSku.textContent = sku;
        modalProductName.textContent = product;
        modalCategoryBadge.textContent = category || 'General';
        modalSizeBadge.textContent = 'Size: ' + (size || '-');
        modalColorBadge.textContent = 'Color: ' + (color || '-');

        modalCurrentStockBadge.textContent = 'Current: ' + currentModalStock + ' units';
        modalCurrentStockBadge.className = 'badge fs-6 px-3 py-2 ' + (currentModalStock <= 0 ? 'bg-danger' : (currentModalStock <= 5 ? 'bg-warning text-dark' : 'bg-success'));

        inputExact.value = currentModalStock;
        inputRelative.value = '';
        updateExactPreview();
        updateRelativePreview();
    }

    function setModalMode(mode) {
        modalAdjustmentMode.value = mode;
        if (mode === 'exact') {
            modeExactBtn.classList.add('active', 'btn-primary');
            modeExactBtn.classList.remove('btn-outline-primary');
            modeRelativeBtn.classList.remove('active', 'btn-primary');
            modeRelativeBtn.classList.add('btn-outline-primary');

            modeExactContainer.style.display = '';
            modeRelativeContainer.style.display = 'none';
            inputExact.required = true;
            inputRelative.required = false;
            inputExact.focus();
        } else {
            modeRelativeBtn.classList.add('active', 'btn-primary');
            modeRelativeBtn.classList.remove('btn-outline-primary');
            modeExactBtn.classList.remove('active', 'btn-primary');
            modeExactBtn.classList.add('btn-outline-primary');

            modeRelativeContainer.style.display = '';
            modeExactContainer.style.display = 'none';
            inputRelative.required = true;
            inputExact.required = false;
            inputRelative.focus();
        }
    }

    modeExactBtn.addEventListener('click', () => setModalMode('exact'));
    modeRelativeBtn.addEventListener('click', () => setModalMode('relative'));

    function updateExactPreview() {
        if (!exactNote) return;
        const val = inputExact.value.trim();
        if (val === '') {
            exactNote.innerHTML = '<span class="text-muted">Enter physical count on shelf.</span>';
            return;
        }
        const newTotal = parseInt(val, 10);
        if (isNaN(newTotal) || newTotal < 0) {
            exactNote.innerHTML = '<span class="text-danger">Stock count cannot be negative.</span>';
            return;
        }
        const diff = newTotal - currentModalStock;
        if (diff === 0) {
            exactNote.innerHTML = '<span class="text-muted">No change in stock (already ' + currentModalStock + ' units).</span>';
        } else if (diff > 0) {
            exactNote.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-arrow-up-circle me-1"></i>Current: ' + currentModalStock + ' &rarr; New: ' + newTotal + ' (+ ' + diff + ' units restock)</span>';
            if (selectChangeType && selectChangeType.value === 'adjustment') {
                selectChangeType.value = 'restock';
            }
        } else {
            exactNote.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-arrow-down-circle me-1"></i>Current: ' + currentModalStock + ' &rarr; New: ' + newTotal + ' (' + diff + ' units deduction)</span>';
            if (selectChangeType && selectChangeType.value === 'restock') {
                selectChangeType.value = 'adjustment';
            }
        }
    }

    function updateRelativePreview() {
        if (!relativeNote) return;
        const val = inputRelative.value.trim();
        if (val === '') {
            relativeNote.innerHTML = '<span class="text-muted">Enter positive number to add, negative to deduct.</span>';
            return;
        }
        const diff = parseInt(val, 10);
        if (isNaN(diff)) {
            relativeNote.innerHTML = '<span class="text-danger">Invalid quantity.</span>';
            return;
        }
        const newTotal = currentModalStock + diff;
        if (newTotal < 0) {
            relativeNote.innerHTML = '<span class="text-danger fw-bold">Error: resulting stock would be negative (' + newTotal + ').</span>';
        } else {
            const colorClass = diff >= 0 ? 'text-success' : 'text-danger';
            relativeNote.innerHTML = '<span class="' + colorClass + ' fw-bold"><i class="bi bi-arrow-right-circle me-1"></i>Resulting stock will be: ' + newTotal + ' units (Current ' + currentModalStock + ' ' + (diff >= 0 ? '+' : '') + diff + ')</span>';
        }
    }

    inputExact.addEventListener('input', updateExactPreview);
    inputRelative.addEventListener('input', updateRelativePreview);

    // Row "Adjust" button click
    document.querySelectorAll('.btn-quick-adjust').forEach(btn => {
        btn.addEventListener('click', function() {
            modalSelectorContainer.style.display = 'none';
            modalVariantCard.style.display = '';

            const id = this.getAttribute('data-id');
            const sku = this.getAttribute('data-sku');
            const product = this.getAttribute('data-product');
            const category = this.getAttribute('data-category');
            const size = this.getAttribute('data-size');
            const color = this.getAttribute('data-color');
            const stock = this.getAttribute('data-stock');

            populateModalWithVariant(id, sku, product, category, size, color, stock);
            setModalMode('exact');
            adjustModal.show();
        });
    });

    // Top "+ Quick Adjust by SKU" button click
    const openManualBtn = document.getElementById('openManualAdjustBtn');
    if (openManualBtn) {
        openManualBtn.addEventListener('click', function() {
            modalSelectorContainer.style.display = '';
            modalVariantSelect.selectedIndex = 0;
            modalVariantId.value = '';
            modalSku.textContent = 'None selected';
            modalProductName.textContent = 'Please choose a variant above';
            modalCurrentStockBadge.textContent = 'Current: -';
            modalCurrentStockBadge.className = 'badge bg-secondary fs-6 px-3 py-2';
            modalCategoryBadge.textContent = '-';
            modalSizeBadge.textContent = 'Size: -';
            modalColorBadge.textContent = 'Color: -';

            setModalMode('exact');
            adjustModal.show();
        });
    }

    // Modal variant select change
    if (modalVariantSelect) {
        modalVariantSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if (!opt || !opt.value) {
                modalVariantId.value = '';
                return;
            }
            populateModalWithVariant(
                opt.value,
                opt.getAttribute('data-sku'),
                opt.getAttribute('data-product'),
                opt.getAttribute('data-category'),
                opt.getAttribute('data-size'),
                opt.getAttribute('data-color'),
                opt.getAttribute('data-stock')
            );
        });
    }

    // Modal form submission validation
    const adjustForm = document.getElementById('quickAdjustForm');
    if (adjustForm) {
        adjustForm.addEventListener('submit', function(e) {
            if (!modalVariantId.value) {
                e.preventDefault();
                alert('Please select a target SKU variant to adjust.');
                if (modalVariantSelect) modalVariantSelect.focus();
                return false;
            }
            const mode = modalAdjustmentMode.value;
            if (mode === 'exact') {
                const exactVal = parseInt(inputExact.value, 10);
                if (isNaN(exactVal) || exactVal < 0) {
                    e.preventDefault();
                    alert('Please enter a valid non-negative stock count.');
                    inputExact.focus();
                    return false;
                }
                if (exactVal === currentModalStock) {
                    e.preventDefault();
                    alert('The entered stock count (' + exactVal + ') is identical to the current stock. No change needed.');
                    inputExact.focus();
                    return false;
                }
            } else {
                const relVal = parseInt(inputRelative.value, 10);
                if (isNaN(relVal) || relVal === 0) {
                    e.preventDefault();
                    alert('Please enter a valid non-zero quantity change (+ or -).');
                    inputRelative.focus();
                    return false;
                }
                if (currentModalStock + relVal < 0) {
                    e.preventDefault();
                    alert('This deduction would result in negative stock. Please adjust quantity.');
                    inputRelative.focus();
                    return false;
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
