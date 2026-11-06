<?php
// admin/sizes.php
// Size Configuration Management

$page_title = 'Sizes Configuration';
$active_menu = 'sizes';
require_once __DIR__ . '/includes/header.php';

// Check permissions
require_admin_permission('products');

$error = '';
$success = '';

// Handle insertions & deletions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        // ADD SIZE
        if (isset($_POST['add_size'])) {
            $size_name = trim($_POST['size_name'] ?? '');
            $size_code = trim($_POST['size_code'] ?? '');
            $category_type = trim($_POST['category_type'] ?? '');

            if (empty($size_name) || empty($size_code) || empty($category_type)) {
                $error = 'All fields are required.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO sizes (size_name, size_code, category_type) VALUES (?, ?, ?)");
                    $stmt->execute([$size_name, $size_code, $category_type]);
                    log_admin_action($current_admin_id, "Added size option: $size_code ($category_type)");
                    set_flash_message('success', 'Size option configured successfully!');
                    header('Location: sizes.php');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Failed to configure size: ' . $e->getMessage();
                }
            }
        }

        // EDIT / UPDATE SIZE
        if (isset($_POST['edit_size'])) {
            $id = (int)($_POST['size_id'] ?? 0);
            $size_name = trim($_POST['size_name'] ?? '');
            $size_code = trim($_POST['size_code'] ?? '');
            $category_type = trim($_POST['category_type'] ?? '');

            if ($id <= 0 || empty($size_name) || empty($size_code) || empty($category_type)) {
                $error = 'All fields are required.';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE sizes SET size_name = ?, size_code = ?, category_type = ? WHERE id = ?");
                    $stmt->execute([$size_name, $size_code, $category_type, $id]);
                    log_admin_action($current_admin_id, "Updated size option #$id: $size_code ($category_type)");
                    set_flash_message('success', "Size option '$size_code' updated successfully!");
                    header('Location: sizes.php');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Failed to update size: ' . $e->getMessage();
                }
            }
        }

        // DELETE SIZE
        if (isset($_POST['delete_size'])) {
            $id = (int)$_POST['size_id'];
            try {
                // Check if currently used in product variants
                $check = $pdo->prepare("SELECT id FROM product_variants WHERE size_id = ? LIMIT 1");
                $check->execute([$id]);
                if ($check->fetch()) {
                    $error = 'Cannot delete this size option. It is currently linked to product variants.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM sizes WHERE id = ?");
                    $stmt->execute([$id]);
                    log_admin_action($current_admin_id, "Deleted size option id: $id");
                    set_flash_message('success', 'Size option deleted successfully!');
                    header('Location: sizes.php');
                    exit;
                }
            } catch (PDOException $e) {
                $error = 'Deletion failed: ' . $e->getMessage();
            }
        }
    }
}

// Fetch sizes configured
$sizes = $pdo->query("SELECT * FROM sizes ORDER BY category_type ASC, id ASC")->fetchAll();
sort_sizes($sizes, true);
?>

<div class="row g-4">
    <!-- Form to Add Size -->
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <h5 class="fw-bold mb-3">Add Size Option</h5>
            
            <?php if (!empty($error) && isset($_POST['add_size'])): ?>
                <div class="alert alert-danger p-2 small"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form action="sizes.php" method="POST">
                <?php echo csrf_field(); ?>
                
                <div class="form-floating mb-3">
                    <select class="form-select" id="category_type" name="category_type" required>
                        <option value="">-- Select Type --</option>
                        <option value="Bras">Bras</option>
                        <option value="Panties">Panties</option>
                        <option value="Nighties">Nighties</option>
                        <option value="Sleepwear">Sleepwear</option>
                        <option value="Shapewear">Shapewear</option>
                        <option value="Activewear">Activewear</option>
                    </select>
                    <label for="category_type">Product Type Category <span class="text-danger">*</span></label>
                </div>

                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="size_name" name="size_name" placeholder="Size Label Name *" required>
                    <label for="size_name">Size Label Name <span class="text-danger">*</span></label>
                </div>

                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="size_code" name="size_code" placeholder="Short Size Code *" required>
                    <label for="size_code">Short Size Code <span class="text-danger">*</span></label>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" name="add_size" class="btn btn-brand">Configure Size</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sizes Listing Table -->
    <div class="col-12 col-md-8">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <h5 class="fw-bold mb-3">Configured Size Templates</h5>
            
            <?php if (!empty($error) && (isset($_POST['delete_size']) || isset($_POST['edit_size']))): ?>
                <div class="alert alert-danger p-2 small"><?php echo e($error); ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product Type</th>
                            <th>Size Name</th>
                            <th>Size Code</th>
                            <th class="text-end" style="width: 110px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sizes)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No sizes configured yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sizes as $sz): ?>
                                <tr>
                                    <td><?php echo $sz['id']; ?></td>
                                    <td><span class="badge bg-secondary"><?php echo e($sz['category_type']); ?></span></td>
                                    <td><span class="fw-bold text-dark"><?php echo e($sz['size_name']); ?></span></td>
                                    <td><code><?php echo e($sz['size_code']); ?></code></td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary p-1 px-2 btn-edit-size" 
                                                    data-id="<?php echo $sz['id']; ?>"
                                                    data-name="<?php echo e($sz['size_name']); ?>"
                                                    data-code="<?php echo e($sz['size_code']); ?>"
                                                    data-type="<?php echo e($sz['category_type']); ?>"
                                                    title="Edit Size">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form action="sizes.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this size?');" class="d-inline mb-0">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="size_id" value="<?php echo $sz['id']; ?>">
                                                <button type="submit" name="delete_size" class="btn btn-sm btn-outline-danger p-1 px-2" title="Delete">
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

<!-- Edit Size Modal -->
<div class="modal fade" id="editSizeModal" tabindex="-1" aria-labelledby="editSizeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 12px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="editSizeModalLabel">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Edit Size Option
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="sizes.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="size_id" id="edit_size_id" value="">
                
                <div class="modal-body p-4">
                    <div class="form-floating mb-3">
                        <select class="form-select" id="edit_category_type" name="category_type" required>
                            <option value="">-- Select Type --</option>
                            <option value="Bras">Bras</option>
                            <option value="Panties">Panties</option>
                            <option value="Nighties">Nighties</option>
                            <option value="Sleepwear">Sleepwear</option>
                            <option value="Shapewear">Shapewear</option>
                            <option value="Activewear">Activewear</option>
                        </select>
                        <label for="edit_category_type">Product Type Category <span class="text-danger">*</span></label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="edit_size_name" name="size_name" placeholder="Size Label Name *" required>
                        <label for="edit_size_name">Size Label Name <span class="text-danger">*</span></label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="edit_size_code" name="size_code" placeholder="Short Size Code *" required>
                        <label for="edit_size_code">Short Size Code <span class="text-danger">*</span></label>
                    </div>

                    <div class="alert alert-info py-2 px-3 small rounded-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Updating this size template will automatically update all linked product variants and size swatches across the storefront.
                    </div>
                </div>
                <div class="modal-footer border-top bg-light p-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_size" class="btn btn-brand rounded-pill px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModalEl = document.getElementById('editSizeModal');
    if (!editModalEl) return;
    const editModal = new bootstrap.Modal(editModalEl);

    document.querySelectorAll('.btn-edit-size').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const code = this.getAttribute('data-code');
            const type = this.getAttribute('data-type');

            document.getElementById('edit_size_id').value = id || '';
            document.getElementById('edit_size_name').value = name || '';
            document.getElementById('edit_size_code').value = code || '';
            
            const typeSelect = document.getElementById('edit_category_type');
            if (typeSelect) {
                let found = false;
                for (let i = 0; i < typeSelect.options.length; i++) {
                    if (typeSelect.options[i].value.toLowerCase() === (type || '').toLowerCase()) {
                        typeSelect.selectedIndex = i;
                        found = true;
                        break;
                    }
                }
                if (!found && type) {
                    const opt = new Option(type, type, true, true);
                    typeSelect.add(opt);
                }
            }

            editModal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
