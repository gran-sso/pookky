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

// Compute category counts for filter tabs
$category_counts = [];
foreach ($sizes as $s) {
    $c_type = trim($s['category_type'] ?: 'Other');
    $category_counts[$c_type] = ($category_counts[$c_type] ?? 0) + 1;
}
ksort($category_counts);
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
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h5 class="fw-bold mb-0">Configured Size Templates</h5>
                    <small class="text-muted" id="sizeCountBadge">Showing <?php echo count($sizes); ?> of <?php echo count($sizes); ?> sizes</small>
                </div>
                <div class="input-group" style="max-width: 260px;">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="sizeSearchInput" class="form-control border-start-0 ps-0" placeholder="Search size or code...">
                    <button class="btn btn-outline-secondary border-start-0" type="button" id="clearSizeSearch" style="display:none;" title="Clear search">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Category Filter Pills -->
            <div class="d-flex flex-wrap gap-1 mb-3 pb-2 border-bottom" id="sizeCategoryFilter">
                <button type="button" class="btn btn-sm btn-dark rounded-pill px-3 py-1 size-cat-btn active" data-category="all">
                    All <span class="badge bg-white text-dark ms-1"><?php echo count($sizes); ?></span>
                </button>
                <?php foreach ($category_counts as $cat_name => $count): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 size-cat-btn" data-category="<?php echo e($cat_name); ?>">
                        <?php echo e($cat_name); ?> <span class="badge bg-secondary ms-1"><?php echo $count; ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            
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
                                <tr class="size-table-row" 
                                    data-category="<?php echo e(strtolower($sz['category_type'])); ?>" 
                                    data-search="<?php echo e(strtolower($sz['category_type'] . ' ' . $sz['size_name'] . ' ' . $sz['size_code'] . ' ' . $sz['id'])); ?>">
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
                            <tr id="noSizesMatchRow" style="display: none;">
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-funnel text-secondary fs-4 d-block mb-2"></i>
                                    No sizes found matching your filter or search.
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" id="resetSizeFilterBtn">Reset Filters</button>
                                    </div>
                                </td>
                            </tr>
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

    // Live filtering and search for sizes
    const searchInput = document.getElementById('sizeSearchInput');
    const clearSearchBtn = document.getElementById('clearSizeSearch');
    const catButtons = document.querySelectorAll('.size-cat-btn');
    const sizeRows = document.querySelectorAll('.size-table-row');
    const noMatchRow = document.getElementById('noSizesMatchRow');
    const countBadge = document.getElementById('sizeCountBadge');
    const resetBtn = document.getElementById('resetSizeFilterBtn');

    let currentCategory = 'all';
    let currentSearch = '';

    function filterSizes() {
        let visibleCount = 0;
        const search = (currentSearch || '').toLowerCase().trim();
        const cat = (currentCategory || 'all').toLowerCase().trim();

        sizeRows.forEach(row => {
            const rowCat = (row.getAttribute('data-category') || '').toLowerCase().trim();
            const rowSearch = (row.getAttribute('data-search') || '').toLowerCase();

            const matchesCat = (cat === 'all') || (rowCat === cat);
            const matchesSearch = !search || rowSearch.includes(search);

            if (matchesCat && matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (noMatchRow) {
            noMatchRow.style.display = (visibleCount === 0 && sizeRows.length > 0) ? '' : 'none';
        }

        if (countBadge) {
            countBadge.textContent = `Showing ${visibleCount} of ${sizeRows.length} sizes`;
        }

        if (clearSearchBtn) {
            clearSearchBtn.style.display = search ? '' : 'none';
        }
    }

    catButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            catButtons.forEach(b => {
                b.classList.remove('btn-dark', 'active');
                b.classList.add('btn-outline-secondary');
            });
            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-dark', 'active');
            currentCategory = this.getAttribute('data-category') || 'all';
            filterSizes();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            currentSearch = this.value;
            filterSizes();
        });
    }

    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            currentSearch = '';
            filterSizes();
            if (searchInput) searchInput.focus();
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            currentSearch = '';
            currentCategory = 'all';
            catButtons.forEach(b => {
                if (b.getAttribute('data-category') === 'all') {
                    b.classList.remove('btn-outline-secondary');
                    b.classList.add('btn-dark', 'active');
                } else {
                    b.classList.remove('btn-dark', 'active');
                    b.classList.add('btn-outline-secondary');
                }
            });
            filterSizes();
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
