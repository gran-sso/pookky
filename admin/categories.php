<?php
// admin/categories.php
// Categories Management CRUD with Drag & Drop Sorting

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

// Guarantee admin login & product permission
require_admin_permission('products');

// Handle AJAX Reordering Request before HTML headers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_category_order') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        json_response('error', 'Invalid security token.');
    }
    $order_data = $_POST['order'] ?? [];
    if (!empty($order_data) && is_array($order_data)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");
            foreach ($order_data as $idx => $cid) {
                $stmt->execute([$idx + 1, (int)$cid]);
            }
            $pdo->commit();
            log_admin_action($_SESSION['admin_id'] ?? 1, "Updated categories sort order via drag-and-drop");
            json_response('success', 'Categories order saved successfully!');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            json_response('error', 'Failed to update sort order: ' . $e->getMessage());
        }
    }
    json_response('error', 'No order items received.');
}

$page_title = 'Categories Management';
$active_menu = 'categories';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle standard form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        // 1. ADD Category
        if (isset($_POST['add_category'])) {
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $description = trim($_POST['description'] ?? '');
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $status = isset($_POST['status']) ? 1 : 0;
            
            if (empty($slug)) {
                $slug = slugify($name);
            } else {
                $slug = slugify($slug);
            }

            if (empty($name)) {
                $error = 'Category Name is required.';
            } else {
                try {
                    // Check duplicate slug
                    $check = $pdo->prepare("SELECT id FROM categories WHERE slug = ? LIMIT 1");
                    $check->execute([$slug]);
                    if ($check->fetch()) {
                        $error = 'Slug already exists. Please choose a unique one.';
                    } else {
                        // Handle image upload if provided
                        $image_path = null;
                        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                            $upload_res = validate_image_upload($_FILES['image'], '../uploads/');
                            if ($upload_res['success']) {
                                $image_path = $upload_res['filename'];
                            } else {
                                $error = $upload_res['error'];
                            }
                        }

                        if (empty($error)) {
                            // If sort_order not provided, assign max + 1
                            if ($sort_order === 0) {
                                $max_stmt = $pdo->query("SELECT MAX(sort_order) as max_ord FROM categories");
                                $sort_order = (int)($max_stmt->fetch()['max_ord'] ?? 0) + 1;
                            }

                            $stmt = $pdo->prepare("INSERT INTO categories (parent_id, name, slug, description, image_path, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$parent_id, $name, $slug, $description, $image_path, $sort_order, $status]);
                            log_admin_action($current_admin_id, "Added category: $name");
                            set_flash_message('success', 'Category added successfully!');
                            header('Location: categories.php');
                            exit;
                        }
                    }
                } catch (PDOException $e) {
                    $error = 'Failed to insert category: ' . $e->getMessage();
                }
            }
        }

        // 2. EDIT Category
        if (isset($_POST['edit_category'])) {
            $id = (int)$_POST['category_id'];
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $description = trim($_POST['description'] ?? '');
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $status = isset($_POST['status']) ? 1 : 0;

            if (empty($slug)) {
                $slug = slugify($name);
            } else {
                $slug = slugify($slug);
            }

            if (empty($name)) {
                $error = 'Category Name is required.';
            } else {
                try {
                    // Check duplicate slug
                    $check = $pdo->prepare("SELECT id FROM categories WHERE slug = ? AND id != ? LIMIT 1");
                    $check->execute([$slug, $id]);
                    if ($check->fetch()) {
                        $error = 'Slug already exists. Please choose a unique one.';
                    } else {
                        // Handle image upload if provided
                        $image_path = $_POST['existing_image'] ?? null;
                        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                            $upload_res = validate_image_upload($_FILES['image'], '../uploads/');
                            if ($upload_res['success']) {
                                // Delete old file if exists
                                if ($image_path && file_exists('../uploads/' . $image_path)) {
                                    @unlink('../uploads/' . $image_path);
                                }
                                $image_path = $upload_res['filename'];
                            } else {
                                $error = $upload_res['error'];
                            }
                        }

                        if (empty($error)) {
                            $stmt = $pdo->prepare("UPDATE categories SET parent_id = ?, name = ?, slug = ?, description = ?, image_path = ?, sort_order = ?, status = ? WHERE id = ?");
                            $stmt->execute([$parent_id, $name, $slug, $description, $image_path, $sort_order, $status, $id]);
                            log_admin_action($current_admin_id, "Updated category id: $id");
                            set_flash_message('success', 'Category updated successfully!');
                            header('Location: categories.php');
                            exit;
                        }
                    }
                } catch (PDOException $e) {
                    $error = 'Failed to update category: ' . $e->getMessage();
                }
            }
        }
    }
}

// Render views based on action
if ($action === 'add' || $action === 'edit'):
    $category = null;
    if ($action === 'edit') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        if (!$category) {
            set_flash_message('error', 'Category not found.');
            header('Location: categories.php');
            exit;
        }
    }
    
    // Fetch categories for parent options
    $parents = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL AND id != " . ($category['id'] ?? 0) . " ORDER BY sort_order ASC, name ASC")->fetchAll();
?>

    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0"><?php echo $action === 'add' ? 'Add New Category' : 'Edit Category: ' . e($category['name']); ?></h5>
            <a href="categories.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Back to Categories
            </a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form action="categories.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $category['id'] : ''; ?>" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                <input type="hidden" name="existing_image" value="<?php echo e($category['image_path']); ?>">
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-5">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo e($category['name'] ?? ''); ?>" required placeholder="Category Name *">
                        <label for="name">Category Name <span class="text-danger">*</span></label>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="slug" name="slug" value="<?php echo e($category['slug'] ?? ''); ?>" placeholder="URL Slug (auto-generated if empty)">
                        <label for="slug">URL Slug (leave empty to auto-generate)</label>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="form-floating">
                        <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?php echo e($category['sort_order'] ?? 0); ?>" placeholder="Display Priority">
                        <label for="sort_order">Sort Order Priority</label>
                    </div>
                    <div class="form-text small text-muted">Lower numbers appear first.</div>
                </div>
                
                <div class="col-12 col-md-6">
                    <div class="form-floating">
                        <select class="form-select" id="parent_id" name="parent_id">
                            <option value="">None (Top Level)</option>
                            <?php foreach ($parents as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo (isset($category['parent_id']) && $category['parent_id'] == $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="parent_id">Parent Category</label>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <label for="image" class="form-label fw-bold small text-muted">
                        Category Card Image (Optional)
                        <span class="badge bg-light text-primary border ms-1"><i class="bi bi-aspect-ratio me-1"></i>600 × 600 px (1:1)</span>
                    </label>
                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                    <div class="form-text small text-muted">
                        <i class="bi bi-info-circle me-1 text-primary"></i> Recommended size: <strong>600 × 600 px</strong> (Square 1:1 ratio) &bull; Max: 10MB (JPG, PNG, WEBP)
                    </div>
                    <?php if (isset($category['image_path']) && $category['image_path']): ?>
                        <div class="mt-2">
                            <span class="small text-muted">Current image:</span><br>
                            <img src="../uploads/<?php echo e($category['image_path']); ?>" alt="Category Image" class="img-thumbnail mt-1" style="max-height: 80px;">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <div class="form-floating">
                        <textarea class="form-control" id="description" name="description" placeholder="Description for catalog cards & SEO" style="height: 100px;"><?php echo e($category['description'] ?? ''); ?></textarea>
                        <label for="description">Description (for catalog cards & SEO)</label>
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="status" name="status" <?php echo (!isset($category['status']) || $category['status'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label small fw-semibold" for="status">Active & Published on Storefront</label>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="submit" name="<?php echo $action === 'add' ? 'add_category' : 'edit_category'; ?>" class="btn btn-brand px-4 rounded-pill">
                    <i class="bi bi-check2-circle me-1"></i> Save Category
                </button>
                <a href="categories.php" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
            </div>
        </form>
    </div>

<?php else:
    // List Categories sorted by sort_order
    try {
        $stmt = $pdo->query("
            SELECT c1.*, c2.name as parent_name,
            (SELECT COUNT(*) FROM products WHERE category_id = c1.id) as product_count
            FROM categories c1 
            LEFT JOIN categories c2 ON c1.parent_id = c2.id 
            ORDER BY c1.sort_order ASC, c1.id ASC
        ");
        $categories_list = $stmt->fetchAll();
    } catch (PDOException $e) {
        $categories_list = [];
        $error = 'Error listing categories: ' . $e->getMessage();
    }
?>

    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-tags me-2 text-brand"></i> Product Categories</h5>
                <p class="text-muted small mb-0">
                    <i class="bi bi-grip-vertical text-primary me-1"></i> <strong>Drag and drop</strong> any row using the grip handle to reorder how categories display across the storefront.
                </p>
            </div>
            <div class="d-flex gap-2">
                <span id="reorderStatus" class="badge bg-light text-muted border px-3 py-2 d-none align-items-center">
                    <span class="spinner-border spinner-border-sm me-2 text-primary" role="status"></span> Saving order...
                </span>
                <a href="categories.php?action=add" class="btn btn-brand rounded-pill px-3">
                    <i class="bi bi-plus-circle me-1"></i> Add Category
                </a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle" id="categoriesSortableTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;" class="text-center" title="Drag to reorder"><i class="bi bi-arrows-expand"></i></th>
                        <th style="width: 70px;" class="text-center">Order</th>
                        <th style="width: 75px;">Image</th>
                        <th>Category Name</th>
                        <th>URL Slug</th>
                        <th>Parent</th>
                        <th>Products</th>
                        <th>Status</th>
                        <th class="text-end" style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="categoriesSortableBody">
                    <?php if (empty($categories_list)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No categories configured yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories_list as $index => $cat): ?>
                            <tr data-id="<?php echo $cat['id']; ?>" class="category-row">
                                <td class="text-center">
                                    <div class="drag-handle d-inline-flex align-items-center justify-content-center p-2 rounded" style="cursor: grab; user-select: none;" title="Click and drag to reorder">
                                        <i class="bi bi-grip-vertical text-muted fs-5"></i>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border rank-badge fw-bold" style="font-size: 11px;">
                                        #<?php echo $index + 1; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($cat['image_path']): ?>
                                        <img src="../uploads/<?php echo e($cat['image_path']); ?>" alt="Banner" class="img-thumbnail rounded" style="width: 44px; height: 44px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light text-muted d-flex align-items-center justify-content-center border rounded" style="width: 44px; height: 44px;">
                                            <i class="bi bi-tag text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark d-block"><?php echo e($cat['name']); ?></span>
                                    <?php if (!empty($cat['description'])): ?>
                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 260px;"><?php echo e($cat['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo e($cat['slug']); ?></code></td>
                                <td>
                                    <?php if ($cat['parent_name']): ?>
                                        <span class="badge bg-secondary"><?php echo e($cat['parent_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">Top Level</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?php echo (int)$cat['product_count']; ?> items
                                    </span>
                                </td>
                                <td>
                                    <div class="form-check form-switch p-0 m-0">
                                        <input class="form-check-input status-toggle ms-0" type="checkbox" role="switch" 
                                               data-table="categories" data-id="<?php echo $cat['id']; ?>" <?php echo $cat['status'] == 1 ? 'checked' : ''; ?> style="cursor: pointer;">
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-secondary btn-move-up" title="Move Up">
                                            <i class="bi bi-chevron-up"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-move-down" title="Move Down">
                                            <i class="bi bi-chevron-down"></i>
                                        </button>
                                        <a href="categories.php?action=edit&id=<?php echo $cat['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Include SortableJS for smooth drag and drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        .category-row.sortable-ghost {
            opacity: 0.4;
            background-color: var(--brand-blush, #FFF0F2) !important;
            border: 2px dashed var(--brand-rose-gold, #B76E79);
        }
        .category-row.sortable-chosen {
            background-color: #f8fafc;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .drag-handle:hover {
            background-color: #f1f5f9;
        }
        .drag-handle:active {
            cursor: grabbing !important;
        }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const tbody = document.getElementById('categoriesSortableBody');
        const reorderStatus = document.getElementById('reorderStatus');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        function updateRanks() {
            const rows = tbody.querySelectorAll('.category-row');
            rows.forEach((row, index) => {
                const rankBadge = row.querySelector('.rank-badge');
                if (rankBadge) {
                    rankBadge.textContent = '#' + (index + 1);
                }
            });
        }

        function saveOrder() {
            const rows = tbody.querySelectorAll('.category-row');
            const order = Array.from(rows).map(row => row.getAttribute('data-id'));

            if (reorderStatus) {
                reorderStatus.classList.remove('d-none');
                reorderStatus.classList.add('d-inline-flex');
                reorderStatus.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary" role="status"></span> Saving order...';
            }

            const formData = new FormData();
            formData.append('action', 'update_category_order');
            formData.append('csrf_token', csrfToken);
            order.forEach(id => formData.append('order[]', id));

            fetch('categories.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (reorderStatus) {
                        reorderStatus.innerHTML = '<i class="bi bi-check2-circle text-success me-1"></i> Order saved!';
                        setTimeout(() => {
                            reorderStatus.classList.add('d-none');
                            reorderStatus.classList.remove('d-inline-flex');
                        }, 2500);
                    }
                } else {
                    alert('Failed to save sort order: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                alert('Network error saving order: ' + err.message);
            });
        }

        if (tbody && typeof Sortable !== 'undefined') {
            new Sortable(tbody, {
                handle: '.drag-handle',
                animation: 200,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd: function() {
                    updateRanks();
                    saveOrder();
                }
            });
        }

        // Quick Move Up / Down Button Handlers
        tbody.addEventListener('click', function(e) {
            const upBtn = e.target.closest('.btn-move-up');
            const downBtn = e.target.closest('.btn-move-down');

            if (upBtn) {
                const row = upBtn.closest('.category-row');
                const prev = row.previousElementSibling;
                if (prev && prev.classList.contains('category-row')) {
                    tbody.insertBefore(row, prev);
                    updateRanks();
                    saveOrder();
                }
            } else if (downBtn) {
                const row = downBtn.closest('.category-row');
                const next = row.nextElementSibling;
                if (next && next.classList.contains('category-row')) {
                    tbody.insertBefore(next, row);
                    updateRanks();
                    saveOrder();
                }
            }
        });
    });
    </script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
