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
            
            <?php if (!empty($error) && isset($_POST['delete_size'])): ?>
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
                            <th class="text-end" style="width: 80px;">Action</th>
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
                                        <form action="sizes.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this size?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="size_id" value="<?php echo $sz['id']; ?>">
                                            <button type="submit" name="delete_size" class="btn btn-sm btn-outline-danger p-1 px-2" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
