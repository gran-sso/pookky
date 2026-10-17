<?php
// admin/fabrics.php
// Fabric Materials Master Configuration & Product Mapping

$page_title = 'Fabrics Configuration';
$active_menu = 'fabrics';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Check permissions
require_admin_permission('products');

// Auto-provision tables if they do not exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fabrics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT DEFAULT NULL,
            status TINYINT DEFAULT 1 COMMENT '1=Active, 0=Inactive',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_fabrics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            fabric_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_product_fabric (product_id, fabric_id),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (fabric_id) REFERENCES fabrics(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Seed standard fabrics if empty
    $count_stmt = $pdo->query("SELECT COUNT(*) FROM fabrics");
    if ($count_stmt && (int)$count_stmt->fetchColumn() === 0) {
        $default_fabrics = [
            ['Cotton', 'Soft, breathable, natural absorbent fabric ideal for everyday comfort.'],
            ['Lace', 'Delicate, patterned ornamental openwork fabric adding luxury aesthetics.'],
            ['Satin', 'Smooth, glossy fabric with a lustrous surface and fluid drape.'],
            ['Nylon', 'Strong, lightweight, quick-drying microfiber offering sleek elasticity.'],
            ['Elastane', 'Highly stretchable synthetic fiber providing resilient shape retention.'],
            ['Spandex', 'Flexible stretch fiber allowing flexible freedom of movement.'],
            ['Polyester', 'Durable, moisture-wicking and wrinkle-resistant fabric.'],
            ['Silk', 'Pure premium natural protein fiber with ultra-soft tactile feel.'],
            ['Microfiber', 'Ultra-fine synthetic fiber delivering seamless softness.'],
            ['Modal', 'Eco-friendly bio-based textile made from beech tree cellulose.'],
            ['Viscose', 'Silky lightweight fabric with graceful drape and soft breathability.'],
            ['Mesh', 'Breathable sheer open-weave fabric for ventilation and modern styling.']
        ];
        $ins = $pdo->prepare("INSERT INTO fabrics (name, description, status) VALUES (?, ?, 1)");
        foreach ($default_fabrics as $df) {
            $ins->execute([$df[0], $df[1]]);
        }

        // Auto-map existing products
        $all_prods = $pdo->query("SELECT id, fabric_details, description FROM products")->fetchAll();
        $all_fabs = $pdo->query("SELECT id, name FROM fabrics")->fetchAll();
        $map_ins = $pdo->prepare("INSERT IGNORE INTO product_fabrics (product_id, fabric_id) VALUES (?, ?)");

        foreach ($all_prods as $p) {
            $text = strtolower(($p['fabric_details'] ?? '') . ' ' . ($p['description'] ?? ''));
            foreach ($all_fabs as $f) {
                if (stripos($text, strtolower($f['name'])) !== false) {
                    $map_ins->execute([$p['id'], $f['id']]);
                }
            }
        }
    }
} catch (PDOException $e) {
    // Continue
}

$error = '';
$success = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // 1. ADD FABRIC
        if (isset($_POST['add_fabric'])) {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $status = isset($_POST['status']) ? 1 : 0;

            if (empty($name)) {
                $error = 'Fabric name is required.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO fabrics (name, description, status) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $description, $status]);
                    log_admin_action($current_admin_id, "Added fabric material: $name");
                    set_flash_message('success', 'Fabric material "' . e($name) . '" added successfully!');
                    header('Location: fabrics.php');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Failed to add fabric: ' . $e->getMessage();
                }
            }
        }

        // 2. EDIT FABRIC
        if (isset($_POST['edit_fabric'])) {
            $id = (int)$_POST['fabric_id'];
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $status = isset($_POST['status']) ? 1 : 0;

            if (empty($name)) {
                $error = 'Fabric name is required.';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE fabrics SET name = ?, description = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $status, $id]);
                    log_admin_action($current_admin_id, "Updated fabric material id: $id ($name)");
                    set_flash_message('success', 'Fabric material updated successfully!');
                    header('Location: fabrics.php');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Failed to update fabric: ' . $e->getMessage();
                }
            }
        }

        // 3. DELETE FABRIC
        if (isset($_POST['delete_fabric'])) {
            $id = (int)$_POST['fabric_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM fabrics WHERE id = ?");
                $stmt->execute([$id]);
                log_admin_action($current_admin_id, "Deleted fabric material id: $id");
                set_flash_message('success', 'Fabric material deleted successfully!');
                header('Location: fabrics.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Deletion failed: ' . $e->getMessage();
            }
        }

        // 4. MAP PRODUCTS TO FABRIC
        if (isset($_POST['save_product_mappings'])) {
            $fabric_id = (int)$_POST['map_fabric_id'];
            $selected_product_ids = isset($_POST['product_ids']) && is_array($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];

            try {
                $pdo->beginTransaction();
                // Remove existing mappings for this fabric
                $del = $pdo->prepare("DELETE FROM product_fabrics WHERE fabric_id = ?");
                $del->execute([$fabric_id]);

                // Insert updated mappings
                if (!empty($selected_product_ids)) {
                    $ins = $pdo->prepare("INSERT INTO product_fabrics (product_id, fabric_id) VALUES (?, ?)");
                    foreach ($selected_product_ids as $p_id) {
                        if ($p_id > 0) {
                            $ins->execute([$p_id, $fabric_id]);
                        }
                    }
                }
                $pdo->commit();
                log_admin_action($current_admin_id, "Updated product mappings for fabric id: $fabric_id (" . count($selected_product_ids) . " products)");
                set_flash_message('success', 'Product mappings updated successfully (' . count($selected_product_ids) . ' products linked)!');
                header('Location: fabrics.php');
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Failed to save product mappings: ' . $e->getMessage();
            }
        }

        // 5. BULK AUTO-SCAN & MAP
        if (isset($_POST['auto_map_all'])) {
            try {
                $all_prods = $pdo->query("SELECT id, name, fabric_details, description FROM products")->fetchAll();
                $all_fabs = $pdo->query("SELECT id, name FROM fabrics WHERE status = 1")->fetchAll();
                $map_ins = $pdo->prepare("INSERT IGNORE INTO product_fabrics (product_id, fabric_id) VALUES (?, ?)");
                $mapped_count = 0;

                foreach ($all_prods as $p) {
                    $text = strtolower(($p['name'] ?? '') . ' ' . ($p['fabric_details'] ?? '') . ' ' . ($p['description'] ?? ''));
                    foreach ($all_fabs as $f) {
                        $f_name = strtolower($f['name']);
                        if (stripos($text, $f_name) !== false) {
                            $map_ins->execute([$p['id'], $f['id']]);
                            $mapped_count++;
                        }
                    }
                }
                set_flash_message('success', "Auto-mapped fabric materials across all existing products! ($mapped_count link connections processed)");
                header('Location: fabrics.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Auto-mapping error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all fabrics with mapped product count
$stmt = $pdo->query("
    SELECT f.*, 
           (SELECT COUNT(DISTINCT pf.product_id) FROM product_fabrics pf WHERE pf.fabric_id = f.id) as mapped_products_count
    FROM fabrics f 
    ORDER BY f.name ASC
");
$fabrics = $stmt->fetchAll();

// Fetch all products for the mapping modal
$products_list = $pdo->query("
    SELECT p.id, p.name, p.sku, c.name as category_name, p.fabric_details 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    ORDER BY p.name ASC
")->fetchAll();

// Fetch mapping dictionary [fabric_id => [product_ids]]
$mappings_raw = $pdo->query("SELECT fabric_id, product_id FROM product_fabrics")->fetchAll();
$fabric_products_map = [];
foreach ($mappings_raw as $m) {
    $fabric_products_map[$m['fabric_id']][] = (int)$m['product_id'];
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-layers text-rose me-2"></i> Fabrics & Materials Configuration</h4>
        <p class="text-muted small mb-0">Configure master fabric materials and map products so customers can filter by material on the website.</p>
    </div>
    <div class="d-flex gap-2">
        <form action="fabrics.php" method="POST" onsubmit="return confirm('Scan all products and automatically link matching fabric materials based on their details?');">
            <?php echo csrf_field(); ?>
            <button type="submit" name="auto_map_all" class="btn btn-outline-primary btn-sm rounded-pill shadow-sm">
                <i class="bi bi-magic me-1"></i> Auto-Map From Product Details
            </button>
        </form>
        <button type="button" class="btn btn-brand btn-sm rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addFabricModal">
            <i class="bi bi-plus-circle me-1"></i> Add Fabric
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo e($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Fabrics Master Table -->
    <div class="col-12">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3 gap-2">
                <h5 class="fw-bold mb-0">Configured Materials (<?php echo count($fabrics); ?>)</h5>
                <div class="input-group input-group-sm" style="max-width: 260px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" id="fabric_table_search" placeholder="Filter fabrics...">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="fabrics_table">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th>#</th>
                            <th>Fabric Name</th>
                            <th>Description</th>
                            <th>Mapped Products</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fabrics)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No fabrics configured yet. Click "Add Fabric" or "Auto-Map" above.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fabrics as $idx => $f): 
                                $mapped_count = (int)$f['mapped_products_count'];
                                $mapped_pids = $fabric_products_map[$f['id']] ?? [];
                            ?>
                                <tr class="fabric-row" data-fabric-name="<?php echo strtolower(e($f['name'])); ?>">
                                    <td class="text-muted small"><?php echo $idx + 1; ?></td>
                                    <td>
                                        <strong class="text-dark"><?php echo e($f['name']); ?></strong>
                                    </td>
                                    <td class="small text-muted" style="max-width: 320px;">
                                        <?php echo e($f['description'] ?: '—'); ?>
                                    </td>
                                    <td>
                                        <button type="button" 
                                                class="btn btn-sm btn-light border rounded-pill px-3 py-1 small btn-open-mapping"
                                                data-fabric-id="<?php echo $f['id']; ?>"
                                                data-fabric-name="<?php echo e($f['name']); ?>"
                                                data-mapped-ids="<?php echo htmlspecialchars(json_encode($mapped_pids)); ?>"
                                                title="Click to map/unmap products">
                                            <i class="bi bi-link-45deg text-primary me-1"></i>
                                            <strong class="<?php echo $mapped_count > 0 ? 'text-primary' : 'text-muted'; ?>"><?php echo $mapped_count; ?></strong> Products Linked
                                        </button>
                                    </td>
                                    <td>
                                        <?php if ($f['status'] == 1): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill small">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-1 rounded-pill small">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" 
                                                    class="btn btn-outline-primary btn-edit-fabric"
                                                    data-fabric-id="<?php echo $f['id']; ?>"
                                                    data-fabric-name="<?php echo e($f['name']); ?>"
                                                    data-fabric-desc="<?php echo e($f['description'] ?? ''); ?>"
                                                    data-fabric-status="<?php echo $f['status']; ?>"
                                                    title="Edit Fabric">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form action="fabrics.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete fabric &quot;<?php echo e($f['name']); ?>&quot;?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="fabric_id" value="<?php echo $f['id']; ?>">
                                                <button type="submit" name="delete_fabric" class="btn btn-outline-danger" title="Delete Fabric">
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

<!-- Modal 1: Add Fabric -->
<div class="modal fade" id="addFabricModal" tabindex="-1" aria-labelledby="addFabricModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 12px;">
            <div class="modal-header border-bottom bg-light">
                <h6 class="modal-title fw-bold" id="addFabricModalLabel"><i class="bi bi-plus-circle me-1 text-rose"></i> Add New Fabric Material</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="fabrics.php" method="POST">
                <?php echo csrf_field(); ?>
                <div class="modal-body p-4">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="add_fabric_name" name="name" placeholder="Fabric Material Name *" required>
                        <label for="add_fabric_name">Fabric Material Name <span class="text-danger">*</span></label>
                    </div>
                    <div class="form-floating mb-3">
                        <textarea class="form-control" id="add_fabric_desc" name="description" placeholder="Description (Optional)" style="height: 100px;"></textarea>
                        <label for="add_fabric_desc">Description (Optional)</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="add_fabric_status" name="status" value="1" checked>
                        <label class="form-check-label small fw-bold" for="add_fabric_status">Active (Visible in Store Filters)</label>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_fabric" class="btn btn-brand btn-sm rounded-pill px-3">Save Fabric</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Edit Fabric -->
<div class="modal fade" id="editFabricModal" tabindex="-1" aria-labelledby="editFabricModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 12px;">
            <div class="modal-header border-bottom bg-light">
                <h6 class="modal-title fw-bold" id="editFabricModalLabel"><i class="bi bi-pencil me-1 text-primary"></i> Edit Fabric Material</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="fabrics.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="fabric_id" id="edit_fabric_id" value="">
                <div class="modal-body p-4">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="edit_fabric_name" name="name" placeholder="Fabric Material Name *" required>
                        <label for="edit_fabric_name">Fabric Material Name <span class="text-danger">*</span></label>
                    </div>
                    <div class="form-floating mb-3">
                        <textarea class="form-control" id="edit_fabric_desc" name="description" placeholder="Description (Optional)" style="height: 100px;"></textarea>
                        <label for="edit_fabric_desc">Description (Optional)</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="edit_fabric_status" name="status" value="1">
                        <label class="form-check-label small fw-bold" for="edit_fabric_status">Active (Visible in Store Filters)</label>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_fabric" class="btn btn-primary btn-sm rounded-pill px-3">Update Fabric</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Map Products to Fabric -->
<div class="modal fade" id="mappingModal" tabindex="-1" aria-labelledby="mappingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow" style="border-radius: 12px;">
            <div class="modal-header border-bottom bg-light">
                <div>
                    <h6 class="modal-title fw-bold" id="mappingModalLabel">
                        <i class="bi bi-link-45deg me-1 text-primary"></i> Map Products to Fabric: <span id="mapping_fabric_name_title" class="text-rose"></span>
                    </h6>
                    <span class="text-muted small">Select all products that are made with or include this fabric material.</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="fabrics.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="map_fabric_id" id="map_fabric_id" value="">
                
                <div class="p-3 bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div class="input-group input-group-sm" style="max-width: 320px;">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control border-start-0" id="mapping_product_search" placeholder="Search product name or SKU...">
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary py-0 px-2" id="btn_map_select_all" style="font-size: 11px;">Select All Visible</button>
                            <button type="button" class="btn btn-outline-secondary py-0 px-2" id="btn_map_clear_all" style="font-size: 11px;">Deselect All</button>
                        </div>
                    </div>
                </div>

                <div class="modal-body p-3 bg-light" style="max-height: 400px; overflow-y: auto;">
                    <div class="row g-2" id="mapping_products_container">
                        <?php foreach ($products_list as $prod): ?>
                            <div class="col-12 col-md-6 map-product-item" 
                                 data-product-name="<?php echo strtolower(e($prod['name'])); ?>"
                                 data-product-sku="<?php echo strtolower(e($prod['sku'])); ?>"
                                 data-product-fab="<?php echo strtolower(e($prod['fabric_details'] ?? '')); ?>">
                                <label class="card border p-2 bg-white h-100 shadow-sm d-flex flex-row align-items-start gap-2 map-prod-label" style="cursor: pointer; border-radius: 8px;">
                                    <input type="checkbox" name="product_ids[]" value="<?php echo $prod['id']; ?>" class="form-check-input mt-1 map-prod-checkbox" id="map_p_<?php echo $prod['id']; ?>">
                                    <div class="small flex-grow-1">
                                        <strong class="d-block text-dark text-truncate" style="max-width: 280px;"><?php echo e($prod['name']); ?></strong>
                                        <div class="text-muted" style="font-size: 11px;">
                                            <code><?php echo e($prod['sku']); ?></code> &bull; <?php echo e($prod['category_name']); ?>
                                        </div>
                                        <?php if (!empty($prod['fabric_details'])): ?>
                                            <div class="text-secondary mt-1 text-truncate" style="font-size: 10px;" title="<?php echo e($prod['fabric_details']); ?>">
                                                <i class="bi bi-tag me-1"></i><?php echo e($prod['fabric_details']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="no_map_products_match" class="text-center text-muted small py-4" style="display:none;">
                        <i class="bi bi-search me-1"></i> No matching products found.
                    </div>
                </div>

                <div class="modal-footer border-top bg-light justify-content-between">
                    <span class="small text-muted" id="mapping_selected_count">0 products selected</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_product_mappings" class="btn btn-primary btn-sm rounded-pill px-4">Save Mappings</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Table Search
    const searchInput = document.getElementById('fabric_table_search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('.fabric-row').forEach(row => {
                const name = row.getAttribute('data-fabric-name') || '';
                row.style.display = (!q || name.includes(q)) ? '' : 'none';
            });
        });
    }

    // 2. Edit Fabric Modal population
    const editModal = new bootstrap.Modal(document.getElementById('editFabricModal'));
    document.querySelectorAll('.btn-edit-fabric').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_fabric_id').value = this.getAttribute('data-fabric-id');
            document.getElementById('edit_fabric_name').value = this.getAttribute('data-fabric-name');
            document.getElementById('edit_fabric_desc').value = this.getAttribute('data-fabric-desc');
            document.getElementById('edit_fabric_status').checked = (this.getAttribute('data-fabric-status') == '1');
            editModal.show();
        });
    });

    // 3. Product Mapping Modal
    const mapModal = new bootstrap.Modal(document.getElementById('mappingModal'));
    const mapCheckboxes = document.querySelectorAll('.map-prod-checkbox');
    const mapProductItems = document.querySelectorAll('.map-product-item');
    const mapCountSpan = document.getElementById('mapping_selected_count');

    function updateMapCount() {
        const checkedCount = Array.from(mapCheckboxes).filter(cb => cb.checked).length;
        if (mapCountSpan) {
            mapCountSpan.textContent = `${checkedCount} product${checkedCount === 1 ? '' : 's'} selected`;
        }
    }

    document.querySelectorAll('.btn-open-mapping').forEach(btn => {
        btn.addEventListener('click', function() {
            const fabricId = this.getAttribute('data-fabric-id');
            const fabricName = this.getAttribute('data-fabric-name');
            const mappedIds = JSON.parse(this.getAttribute('data-mapped-ids') || '[]');

            document.getElementById('map_fabric_id').value = fabricId;
            document.getElementById('mapping_fabric_name_title').textContent = fabricName;

            // Reset search
            const prodSearch = document.getElementById('mapping_product_search');
            if (prodSearch) prodSearch.value = '';
            mapProductItems.forEach(item => item.style.display = '');

            // Set checkboxes
            mapCheckboxes.forEach(cb => {
                const pId = parseInt(cb.value);
                const isChecked = mappedIds.includes(pId);
                cb.checked = isChecked;
                const card = cb.closest('.map-prod-label');
                if (card) {
                    if (isChecked) {
                        card.classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
                    } else {
                        card.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
                    }
                }
            });

            updateMapCount();
            mapModal.show();
        });
    });

    // Toggle card border on checkbox change
    mapCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const card = this.closest('.map-prod-label');
            if (card) {
                if (this.checked) {
                    card.classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
                } else {
                    card.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
                }
            }
            updateMapCount();
        });
    });

    // Product search inside mapping modal
    const prodSearch = document.getElementById('mapping_product_search');
    if (prodSearch) {
        prodSearch.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            let visible = 0;
            mapProductItems.forEach(item => {
                const name = item.getAttribute('data-product-name') || '';
                const sku = item.getAttribute('data-product-sku') || '';
                const fab = item.getAttribute('data-product-fab') || '';
                const matches = !q || name.includes(q) || sku.includes(q) || fab.includes(q);

                item.style.display = matches ? '' : 'none';
                if (matches) visible++;
            });
            const noMatch = document.getElementById('no_map_products_match');
            if (noMatch) noMatch.style.display = visible === 0 ? '' : 'none';
        });
    }

    // Select All / Deselect All visible in modal
    const btnMapSelectAll = document.getElementById('btn_map_select_all');
    if (btnMapSelectAll) {
        btnMapSelectAll.addEventListener('click', function() {
            mapProductItems.forEach(item => {
                if (item.style.display !== 'none') {
                    const cb = item.querySelector('.map-prod-checkbox');
                    if (cb) {
                        cb.checked = true;
                        const card = cb.closest('.map-prod-label');
                        if (card) card.classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
                    }
                }
            });
            updateMapCount();
        });
    }

    const btnMapClearAll = document.getElementById('btn_map_clear_all');
    if (btnMapClearAll) {
        btnMapClearAll.addEventListener('click', function() {
            mapCheckboxes.forEach(cb => {
                cb.checked = false;
                const card = cb.closest('.map-prod-label');
                if (card) card.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
            });
            updateMapCount();
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
