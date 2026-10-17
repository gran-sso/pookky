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

// Handle restock/adjustment post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        if (isset($_POST['adjust_stock'])) {
            $variant_id = (int)$_POST['variant_id'];
            $qty_change = (int)$_POST['quantity_changed'];
            $change_type = $_POST['change_type']; // restock or adjustment
            $notes = trim($_POST['notes'] ?? '');

            if (empty($variant_id) || $qty_change == 0 || empty($change_type)) {
                $error = 'Please fill in all adjustment fields correctly.';
            } else {
                try {
                    // Fetch current stock
                    $v_stmt = $pdo->prepare("SELECT stock, sku FROM product_variants WHERE id = ? LIMIT 1");
                    $v_stmt->execute([$variant_id]);
                    $variant = $v_stmt->fetch();

                    if ($variant) {
                        $current_stock = (int)$variant['stock'];
                        $new_stock = $current_stock + $qty_change;
                        if ($new_stock < 0) {
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
                            log_admin_action($current_admin_id, "Adjusted stock for variant " . $variant['sku'] . " (Change: $qty_change)");
                            set_flash_message('success', 'Stock levels adjusted and logged successfully!');
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

// Fetch all product variants with details
$all_variants = $pdo->query("
    SELECT v.id, v.sku, v.stock, p.name as product_name, s.size_code, c.color_name 
    FROM product_variants v 
    JOIN products p ON v.product_id = p.id 
    JOIN sizes s ON v.size_id = s.id 
    JOIN colors c ON v.color_id = c.id 
    ORDER BY p.name ASC, s.id ASC
")->fetchAll();

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

<div class="row g-4">
    <!-- Quick Stock Adjustment Form -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
            <h5 class="fw-bold mb-3">Adjust Stock Count</h5>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger p-2 small"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form action="inventory.php" method="POST">
                <?php echo csrf_field(); ?>
                
                <div class="form-floating mb-3">
                    <select class="form-select" id="variant_id" name="variant_id" required>
                        <option value="">-- Choose SKU Variant --</option>
                        <?php foreach ($all_variants as $v): ?>
                            <option value="<?php echo $v['id']; ?>">
                                <?php echo e($v['sku']); ?> (<?php echo e($v['product_name']); ?> - Size: <?php echo e($v['size_code']); ?>, Color: <?php echo e($v['color_name']); ?>) [Stock: <?php echo $v['stock']; ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="variant_id">Target SKU Variant <span class="text-danger">*</span></label>
                </div>

                <div class="form-floating mb-3">
                    <select class="form-select" id="change_type" name="change_type" required>
                        <option value="restock">Restock (Adding stock)</option>
                        <option value="adjustment">Adjustment (Stock correction)</option>
                    </select>
                    <label for="change_type">Adjustment Type <span class="text-danger">*</span></label>
                </div>

                <div class="form-floating mb-3">
                    <input type="number" class="form-control" id="quantity_changed" name="quantity_changed" required placeholder="Quantity Difference *">
                    <label for="quantity_changed">Quantity (+ or -) <span class="text-danger">*</span></label>
                </div>

                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="notes" name="notes" placeholder="Internal Ledger Notes">
                    <label for="notes">Internal Ledger Notes</label>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" name="adjust_stock" class="btn btn-brand">Log Adjustment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Stock Matrix & Alerts -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <h5 class="fw-bold mb-3">Inventory Ledger Matrix</h5>
            <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th>Attributes</th>
                            <th>Current Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_variants)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No product variants configured. Add product variants to track stock levels.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_variants as $v): ?>
                                <tr>
                                    <td><code><?php echo e($v['sku']); ?></code></td>
                                    <td><span class="fw-bold text-dark"><?php echo e($v['product_name']); ?></span></td>
                                    <td>
                                        <span class="small text-muted">Size: <?php echo e($v['size_code']); ?> | Color: <?php echo e($v['color_name']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($v['stock'] > 5): ?>
                                            <span class="badge bg-success"><?php echo $v['stock']; ?> units</span>
                                        <?php elseif ($v['stock'] > 0): ?>
                                            <span class="badge bg-warning text-dark">Low stock (<?php echo $v['stock']; ?>)</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Out of stock</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Historical Stock Transaction Log -->
    <div class="col-12">
        <div class="card border-0 shadow-sm p-4 bg-white mt-3" style="border-radius: 12px;">
            <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i> Inventory Adjustments Log History (Recent 30 entries)</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
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
                                <td colspan="6" class="text-center text-muted py-4">No inventory logs logged yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="small"><?php echo date('d-M-Y H:i:s', strtotime($log['logged_at'])); ?></td>
                                    <td><span class="fw-bold text-dark"><?php echo e($log['product_name']); ?></span></td>
                                    <td><code><?php echo e($log['variant_sku']); ?></code></td>
                                    <td>
                                        <?php if ($log['quantity_changed'] > 0): ?>
                                            <span class="text-success fw-bold">+<?php echo $log['quantity_changed']; ?></span>
                                        <?php else: ?>
                                            <span class="text-danger fw-bold"><?php echo $log['quantity_changed']; ?></span>
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
                                    <td class="small text-muted"><?php echo e($log['notes']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
