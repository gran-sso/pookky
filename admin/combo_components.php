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
