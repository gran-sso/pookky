<?php
// admin/banners.php
// Hero Sliders & Banners Management

$page_title = 'Banners Management';
$active_menu = 'banners';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Check permissions
if (!has_admin_permission('products') && !has_admin_permission('all')) {
    set_flash_message('error', 'Unauthorized access! You do not have permission to access that module.');
    header('Location: index.php');
    exit;
}

// Auto-check/migrate column if not present
try {
    $col_check = $pdo->query("SHOW COLUMNS FROM banners LIKE 'mobile_image'")->fetch();
    if (!$col_check) {
        $pdo->exec("ALTER TABLE banners ADD COLUMN mobile_image VARCHAR(255) NULL AFTER image_path");
    }
} catch (PDOException $e) {
    // Fail silently if already exists or permission restricted
}

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle insertions & updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        // ADD BANNER
        if (isset($_POST['add_banner'])) {
            $title = trim($_POST['title'] ?? '');
            $tagline = trim($_POST['tagline'] ?? 'Featured Collection');
            $subtitle = trim($_POST['subtitle'] ?? 'Elegant designs, soft fabrics, and customizable sizing matrices.');
            $link_url = trim($_POST['link_url'] ?? '');
            $btn_text = trim($_POST['btn_text'] ?? 'Shop Collection');
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $status = isset($_POST['status']) ? 1 : 0;

            if (empty($title) || !isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Title and Desktop Banner Image are required.';
            } else {
                try {
                    $upload_res = validate_image_upload($_FILES['image'], '../uploads/');
                    if ($upload_res['success']) {
                        $image_path = $upload_res['filename'];
                        $mobile_image = null;

                        // Optional Mobile Banner Upload
                        if (isset($_FILES['mobile_image']) && $_FILES['mobile_image']['error'] === UPLOAD_ERR_OK) {
                            $mob_res = validate_image_upload($_FILES['mobile_image'], '../uploads/');
                            if ($mob_res['success']) {
                                $mobile_image = $mob_res['filename'];
                            } else {
                                $error = 'Mobile Image: ' . $mob_res['error'];
                            }
                        }

                        if (empty($error)) {
                            $stmt = $pdo->prepare("INSERT INTO banners (title, tagline, subtitle, image_path, mobile_image, link_url, btn_text, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$title, $tagline, $subtitle, $image_path, $mobile_image, $link_url, $btn_text, $sort_order, $status]);
                            log_admin_action($current_admin_id, "Added banner: $title");
                            set_flash_message('success', 'Banner slider created successfully!');
                            header('Location: banners.php');
                            exit;
                        }
                    } else {
                        $error = $upload_res['error'];
                    }
                } catch (PDOException $e) {
                    $error = 'Database insert failed: ' . $e->getMessage();
                }
            }
        }

        // EDIT BANNER
        if (isset($_POST['edit_banner'])) {
            $id = (int)$_POST['banner_id'];
            $title = trim($_POST['title'] ?? '');
            $tagline = trim($_POST['tagline'] ?? 'Featured Collection');
            $subtitle = trim($_POST['subtitle'] ?? 'Elegant designs, soft fabrics, and customizable sizing matrices.');
            $link_url = trim($_POST['link_url'] ?? '');
            $btn_text = trim($_POST['btn_text'] ?? 'Shop Collection');
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $status = isset($_POST['status']) ? 1 : 0;
            $remove_mobile_image = isset($_POST['remove_mobile_image']);

            if (empty($title)) {
                $error = 'Title is required.';
            } else {
                try {
                    $image_path = $_POST['existing_image'] ?? '';
                    $mobile_image = $_POST['existing_mobile_image'] ?? null;

                    // Desktop image replacement
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $upload_res = validate_image_upload($_FILES['image'], '../uploads/');
                        if ($upload_res['success']) {
                            if ($image_path && file_exists('../uploads/' . $image_path)) {
                                @unlink('../uploads/' . $image_path);
                            }
                            $image_path = $upload_res['filename'];
                        } else {
                            $error = $upload_res['error'];
                        }
                    }

                    // Mobile image handling
                    if ($remove_mobile_image) {
                        if ($mobile_image && file_exists('../uploads/' . $mobile_image)) {
                            @unlink('../uploads/' . $mobile_image);
                        }
                        $mobile_image = null;
                    } elseif (isset($_FILES['mobile_image']) && $_FILES['mobile_image']['error'] === UPLOAD_ERR_OK) {
                        $mob_res = validate_image_upload($_FILES['mobile_image'], '../uploads/');
                        if ($mob_res['success']) {
                            if ($mobile_image && file_exists('../uploads/' . $mobile_image)) {
                                @unlink('../uploads/' . $mobile_image);
                            }
                            $mobile_image = $mob_res['filename'];
                        } else {
                            $error = 'Mobile Image: ' . $mob_res['error'];
                        }
                    }

                    if (empty($error)) {
                        $stmt = $pdo->prepare("UPDATE banners SET title = ?, tagline = ?, subtitle = ?, image_path = ?, mobile_image = ?, link_url = ?, btn_text = ?, sort_order = ?, status = ? WHERE id = ?");
                        $stmt->execute([$title, $tagline, $subtitle, $image_path, $mobile_image, $link_url, $btn_text, $sort_order, $status, $id]);
                        log_admin_action($current_admin_id, "Updated banner: $title");
                        set_flash_message('success', 'Banner updated successfully!');
                        header('Location: banners.php');
                        exit;
                    }
                } catch (PDOException $e) {
                    $error = 'Update failed: ' . $e->getMessage();
                }
            }
        }

        // DELETE BANNER
        if (isset($_POST['delete_banner'])) {
            $id = (int)$_POST['banner_id'];
            try {
                // Fetch images to delete
                $g_stmt = $pdo->prepare("SELECT image_path, mobile_image FROM banners WHERE id = ? LIMIT 1");
                $g_stmt->execute([$id]);
                $banner = $g_stmt->fetch();

                if ($banner) {
                    $stmt = $pdo->prepare("DELETE FROM banners WHERE id = ?");
                    $stmt->execute([$id]);
                    if ($banner['image_path'] && file_exists('../uploads/' . $banner['image_path'])) {
                        @unlink('../uploads/' . $banner['image_path']);
                    }
                    if (!empty($banner['mobile_image']) && file_exists('../uploads/' . $banner['mobile_image'])) {
                        @unlink('../uploads/' . $banner['mobile_image']);
                    }
                    log_admin_action($current_admin_id, "Deleted banner id: $id");
                    set_flash_message('success', 'Banner deleted successfully!');
                }
                header('Location: banners.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Failed to delete banner.';
            }
        }
    }
}

// Render views based on action
if ($action === 'add' || $action === 'edit'):
    $banner = null;
    if ($action === 'edit') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM banners WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $banner = $stmt->fetch();
        if (!$banner) {
            set_flash_message('error', 'Banner not found.');
            header('Location: banners.php');
            exit;
        }
    }
?>

    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
        <h5 class="fw-bold mb-4"><?php echo $action === 'add' ? 'Add New Slider Banner' : 'Edit Banner: ' . e($banner['title']); ?></h5>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form action="banners.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $banner['id'] : ''; ?>" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="banner_id" value="<?php echo $banner['id']; ?>">
                <input type="hidden" name="existing_image" value="<?php echo e($banner['image_path']); ?>">
                <input type="hidden" name="existing_mobile_image" value="<?php echo e($banner['mobile_image'] ?? ''); ?>">
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <label for="title" class="form-label fw-bold small">Banner Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" value="<?php echo e($banner['title'] ?? ''); ?>" required placeholder="e.g. Autumn Silk Lingerie Collection">
                </div>
                <div class="col-12 col-md-6">
                    <label for="tagline" class="form-label fw-bold small">Banner Top Tagline / Category Chip</label>
                    <input type="text" class="form-control" id="tagline" name="tagline" value="<?php echo e($banner['tagline'] ?? 'Featured Collection'); ?>" placeholder="e.g. Featured Collection, New Season">
                </div>
                <div class="col-12 col-md-6">
                    <label for="subtitle" class="form-label fw-bold small">Banner Subtitle / Promotional Note</label>
                    <input type="text" class="form-control" id="subtitle" name="subtitle" value="<?php echo e($banner['subtitle'] ?? 'Elegant designs, soft fabrics, and customizable sizing matrices.'); ?>" placeholder="e.g. Elegant designs, soft fabrics, and customizable sizing matrices.">
                </div>
                <div class="col-12 col-md-3">
                    <label for="link_url" class="form-label fw-bold small">Target Link URL (Optional)</label>
                    <input type="text" class="form-control" id="link_url" name="link_url" value="<?php echo e($banner['link_url'] ?? ''); ?>" placeholder="e.g. products.php?category=bras">
                </div>
                <div class="col-12 col-md-3">
                    <label for="btn_text" class="form-label fw-bold small">Button Text</label>
                    <input type="text" class="form-control" id="btn_text" name="btn_text" value="<?php echo e($banner['btn_text'] ?? 'Shop Collection'); ?>" placeholder="e.g. Shop Collection">
                </div>

                <!-- Desktop Banner Upload -->
                <div class="col-12 col-md-6">
                    <label for="image" class="form-label fw-bold small">
                        <i class="bi bi-display me-1 text-primary"></i> Desktop Banner Image <?php echo $action === 'add' ? '<span class="text-danger">*</span>' : '(Optional to change)'; ?>
                        <span class="badge bg-light text-primary border ms-1"><i class="bi bi-aspect-ratio me-1"></i>1920 × 600 px (Landscape)</span>
                    </label>
                    <input type="file" class="form-control" id="image" name="image" accept="image/*" <?php echo $action === 'add' ? 'required' : ''; ?>>
                    <div class="form-text small text-muted">
                        Targeted for Laptops & Desktops &bull; Max: 10MB.
                    </div>
                    <?php if (isset($banner['image_path']) && $banner['image_path']): ?>
                        <div class="mt-2 p-2 bg-light rounded border">
                            <span class="small text-muted fw-bold d-block mb-1">Current Desktop Banner:</span>
                            <img src="../uploads/<?php echo e($banner['image_path']); ?>" alt="Desktop Banner" class="img-thumbnail" style="max-height: 100px; width: auto;">
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Mobile Banner Upload -->
                <div class="col-12 col-md-6">
                    <label for="mobile_image" class="form-label fw-bold small">
                        <i class="bi bi-phone me-1 text-success"></i> Mobile Banner Image (Optional)
                        <span class="badge bg-light text-success border ms-1"><i class="bi bi-aspect-ratio me-1"></i>750 × 950 px (Portrait 4:5)</span>
                    </label>
                    <input type="file" class="form-control" id="mobile_image" name="mobile_image" accept="image/*">
                    <div class="form-text small text-muted">
                        Optimized for smartphones. If not uploaded, the desktop banner will automatically be used as fallback.
                    </div>
                    <?php if (isset($banner['mobile_image']) && $banner['mobile_image']): ?>
                        <div class="mt-2 p-2 bg-light rounded border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-muted fw-bold">Current Mobile Banner:</span>
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="checkbox" id="remove_mobile_image" name="remove_mobile_image" value="1">
                                    <label class="form-check-label small text-danger" for="remove_mobile_image">Remove</label>
                                </div>
                            </div>
                            <img src="../uploads/<?php echo e($banner['mobile_image']); ?>" alt="Mobile Banner" class="img-thumbnail" style="max-height: 100px; width: auto;">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-12 col-md-6">
                    <label for="sort_order" class="form-label fw-bold small">Sort Positioning Order (lower values show first)</label>
                    <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?php echo e($banner['sort_order'] ?? '0'); ?>">
                </div>

                <div class="col-12 col-md-6 d-flex align-items-center">
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" id="status" name="status" <?php echo (!isset($banner['status']) || $banner['status'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label small fw-bold" for="status">Active & Visible in Homepage Slider</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" name="<?php echo $action === 'add' ? 'add_banner' : 'edit_banner'; ?>" class="btn btn-brand">Save Banner Slide</button>
                <a href="banners.php" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
            </div>
        </form>
    </div>

<?php else:
    // List Banners
    try {
        $banners_list = $pdo->query("SELECT * FROM banners ORDER BY sort_order ASC, id DESC")->fetchAll();
    } catch (PDOException $e) {
        $banners_list = [];
        $error = 'Error listing banners: ' . $e->getMessage();
    }
?>

    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="fw-bold mb-1">Homepage Banner Sliders</h5>
                <p class="text-muted small mb-0">Manage full-width desktop and optimized portrait mobile promotional slides.</p>
            </div>
            <a href="banners.php?action=add" class="btn btn-brand">
                <i class="bi bi-plus-circle me-1"></i> Add Banner
            </a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 140px;">Desktop Slide</th>
                        <th style="width: 110px;">Mobile Slide</th>
                        <th>Banner Title</th>
                        <th>Redirect URL</th>
                        <th>Sort Order</th>
                        <th>Status</th>
                        <th class="text-end" style="width: 140px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($banners_list)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No banners configured yet. Banners show as hero promo blocks on client storefront.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($banners_list as $bn): ?>
                            <tr>
                                <td>
                                    <img src="../uploads/<?php echo e($bn['image_path']); ?>" alt="Desktop Banner" class="img-thumbnail" style="max-height: 50px; max-width: 110px; object-fit: cover;">
                                </td>
                                <td>
                                    <?php if (!empty($bn['mobile_image'])): ?>
                                        <img src="../uploads/<?php echo e($bn['mobile_image']); ?>" alt="Mobile Banner" class="img-thumbnail" style="max-height: 50px; max-width: 60px; object-fit: cover;">
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Desktop fallback</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="fw-bold text-dark"><?php echo e($bn['title']); ?></span></td>
                                <td><code><?php echo e($bn['link_url'] ?: 'None'); ?></code></td>
                                <td><?php echo $bn['sort_order']; ?></td>
                                <td>
                                    <div class="form-check form-switch p-0 m-0">
                                        <input class="form-check-input status-toggle ms-0" type="checkbox" role="switch" 
                                               data-table="banners" data-id="<?php echo $bn['id']; ?>" <?php echo $bn['status'] == 1 ? 'checked' : ''; ?>>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="banners.php?action=edit&id=<?php echo $bn['id']; ?>" class="btn btn-sm btn-outline-secondary p-1 px-2" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form action="banners.php" method="POST" onsubmit="return confirm('Delete this banner slide?');" style="display:inline;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="banner_id" value="<?php echo $bn['id']; ?>">
                                            <button type="submit" name="delete_banner" class="btn btn-sm btn-outline-danger p-1 px-2" title="Delete">
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

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
