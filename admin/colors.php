<?php
// admin/colors.php
// Color Configuration Management

$page_title = 'Colors Configuration';
$active_menu = 'colors';
require_once __DIR__ . '/includes/header.php';

// Check permissions
require_admin_permission('products');

$error = '';
$success = '';

// Handle additions & deletions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        // ADD COLOR
        if (isset($_POST['add_color'])) {
            $color_name = trim($_POST['color_name'] ?? '');
            $color_code = trim($_POST['color_code'] ?? '#FFFFFF');

            if (empty($color_name) || empty($color_code)) {
                $error = 'All fields are required.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO colors (color_name, color_code) VALUES (?, ?)");
                    $stmt->execute([$color_name, $color_code]);
                    log_admin_action($current_admin_id, "Added color swatch: $color_name ($color_code)");
                    set_flash_message('success', 'Color swatch configured successfully!');
                    header('Location: colors.php');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Failed to configure color: ' . $e->getMessage();
                }
            }
        }

        // DELETE COLOR
        if (isset($_POST['delete_color'])) {
            $id = (int)$_POST['color_id'];
            try {
                // Check if currently used in product variants
                $check = $pdo->prepare("SELECT id FROM product_variants WHERE color_id = ? LIMIT 1");
                $check->execute([$id]);
                if ($check->fetch()) {
                    $error = 'Cannot delete this color swatch. It is currently linked to product variants.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM colors WHERE id = ?");
                    $stmt->execute([$id]);
                    log_admin_action($current_admin_id, "Deleted color swatch id: $id");
                    set_flash_message('success', 'Color swatch deleted successfully!');
                    header('Location: colors.php');
                    exit;
                }
            } catch (PDOException $e) {
                $error = 'Deletion failed: ' . $e->getMessage();
            }
        }
    }
}

// Fetch colors configured
$colors = $pdo->query("SELECT * FROM colors ORDER BY color_name ASC")->fetchAll();
?>

<div class="row g-4">
    <!-- Form to Add Color -->
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <h5 class="fw-bold mb-3">Add Color Swatch</h5>
            
            <?php if (!empty($error) && isset($_POST['add_color'])): ?>
                <div class="alert alert-danger p-2 small"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form action="colors.php" method="POST">
                <?php echo csrf_field(); ?>
                
                <div class="mb-3">
                    <label for="color_name" class="form-label small">Color Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="color_name" name="color_name" placeholder="e.g. Chocolate Brown" required>
                    <small class="text-muted d-block mt-1" id="color-short-preview">SKU Abbreviation: <code>--</code></small>
                </div>

                <div class="mb-3">
                    <label for="color_code" class="form-label small">Hex Color Palette <span class="text-danger">*</span></label>
                    <div class="d-flex align-items-center gap-2">
                        <input type="color" class="form-control form-control-color" id="color_code" name="color_code" value="#B76E79" title="Choose color" required style="width: 50px; height: 38px;">
                        <input type="text" class="form-control text-uppercase" id="color_code_text" placeholder="#B76E79" maxlength="7" pattern="^#([A-Fa-f0-9]{6})$" required value="#B76E79">
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" name="add_color" class="btn btn-brand">Configure Color</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Colors Listing Table -->
    <div class="col-12 col-md-8">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <h5 class="fw-bold mb-3">Configured Color Swatches</h5>
            
            <?php if (!empty($error) && isset($_POST['delete_color'])): ?>
                <div class="alert alert-danger p-2 small"><?php echo e($error); ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th style="width: 70px;">Swatch</th>
                            <th>Color Name</th>
                            <th>SKU Short Code</th>
                            <th>Hex Code</th>
                            <th class="text-end" style="width: 80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($colors)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No colors configured yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($colors as $cl): 
                                $short_code = get_color_short_code($cl['color_name'], $cl['short_code'] ?? null);
                            ?>
                                <tr>
                                    <td><?php echo $cl['id']; ?></td>
                                    <td>
                                        <div class="border rounded-circle" style="background-color: <?php echo e($cl['color_code']); ?>; width: 32px; height: 32px;"></div>
                                    </td>
                                    <td><span class="fw-bold text-dark"><?php echo e($cl['color_name']); ?></span></td>
                                    <td><span class="badge bg-dark-subtle text-dark border font-monospace px-2 py-1"><?php echo e($short_code); ?></span></td>
                                    <td><code><?php echo strtoupper(e($cl['color_code'])); ?></code></td>
                                    <td class="text-end">
                                        <form action="colors.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this color?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="color_id" value="<?php echo $cl['id']; ?>">
                                            <button type="submit" name="delete_color" class="btn btn-sm btn-outline-danger p-1 px-2" title="Delete">
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

<script>
    // Keep color input and text input synced
    const colorPicker = document.getElementById('color_code');
    const colorText = document.getElementById('color_code_text');
    const colorNameInput = document.getElementById('color_name');
    const shortPreview = document.getElementById('color-short-preview');
    
    if (colorPicker && colorText) {
        colorPicker.addEventListener('input', function() {
            colorText.value = this.value.toUpperCase();
        });
        colorText.addEventListener('input', function() {
            if(this.value.match(/^#[A-Fa-f0-9]{6}$/)) {
                colorPicker.value = this.value;
            }
        });
    }

    if (colorNameInput && shortPreview) {
        colorNameInput.addEventListener('input', function() {
            const val = this.value.trim().toUpperCase();
            if (!val) {
                shortPreview.innerHTML = 'SKU Abbreviation: <code>--</code>';
                return;
            }
            const words = val.replace(/[^A-Z0-9\s]/g, '').split(/\s+/).filter(Boolean);
            let code = 'CLR';
            if (words.length >= 2) {
                code = words[0].substring(0, 2) + words[1].substring(0, 2);
            } else if (words.length === 1) {
                const single = words[0];
                if (single.length <= 4) {
                    code = single;
                } else {
                    const first = single[0];
                    const restConsonants = single.substring(1).replace(/[AEIOU]/g, '');
                    const abbr = first + restConsonants;
                    code = abbr.length >= 3 ? abbr.substring(0, 4) : single.substring(0, 4);
                }
            }
            shortPreview.innerHTML = 'SKU Abbreviation: <code>' + code + '</code>';
        });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
