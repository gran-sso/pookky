<?php
// admin/products.php
// Products Base CRUD Management

$page_title = 'Products Management';
$active_menu = 'products';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Check permissions
require_admin_permission('products');

// Configure larger execution limits for high-resolution photo uploads
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '300');
@ini_set('max_input_time', '300');

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle insertions & updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $error = 'The total upload size exceeded the server limit (' . ini_get('post_max_size') . '). Please upload fewer or smaller images.';
    } else {
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($csrf_token)) {
            $error = 'Invalid security token. Please refresh and try submitting again.';
        } else {
        // 1. ADD PRODUCT
        if (isset($_POST['add_product'])) {
            $category_id = (int)$_POST['category_id'];
            $product_type = in_array($_POST['product_type'] ?? '', ['single', 'combo']) ? $_POST['product_type'] : 'single';
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $fabric_details = trim($_POST['fabric_details'] ?? '');
            $fit_type = trim($_POST['fit_type'] ?? '');
            $care_instructions = trim($_POST['care_instructions'] ?? '');
            $regular_price = (float)$_POST['regular_price'];
            $offer_price = !empty($_POST['offer_price']) ? (float)$_POST['offer_price'] : null;
            $gst_percentage = (isset($_POST['gst_percentage']) && $_POST['gst_percentage'] !== '') ? (float)$_POST['gst_percentage'] : DEFAULT_GST_RATE;
            
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $is_new_arrival = isset($_POST['is_new_arrival']) ? 1 : 0;
            $is_best_seller = isset($_POST['is_best_seller']) ? 1 : 0;
            $status = isset($_POST['status']) ? 1 : 0;

            $seo_title = trim($_POST['seo_title'] ?? '');
            $seo_description = trim($_POST['seo_description'] ?? '');

            if (empty($slug)) {
                $slug = slugify($name);
            } else {
                $slug = slugify($slug);
            }

            if (empty($name) || empty($sku) || empty($category_id) || $regular_price <= 0) {
                $error = 'Please fill in all required fields (Name, SKU, Category, and Regular Price).';
            } else {
                try {
                    // Check duplicate SKU or slug
                    $check = $pdo->prepare("SELECT id FROM products WHERE sku = ? OR slug = ? LIMIT 1");
                    $check->execute([$sku, $slug]);
                    if ($check->fetch()) {
                        $error = 'A product with this SKU or Slug already exists.';
                    } else {
                        // Start transaction to insert product and images together
                        $pdo->beginTransaction();

                        $stmt = $pdo->prepare("
                            INSERT INTO products 
                            (category_id, product_type, name, slug, sku, description, fabric_details, fit_type, care_instructions, regular_price, offer_price, gst_percentage, is_featured, is_new_arrival, is_best_seller, status, seo_title, seo_description) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$category_id, $product_type, $name, $slug, $sku, $description, $fabric_details, $fit_type, $care_instructions, $regular_price, $offer_price, $gst_percentage, $is_featured, $is_new_arrival, $is_best_seller, $status, $seo_title, $seo_description]);
                        
                        $product_id = $pdo->lastInsertId();

                        // Process Primary/Featured Image
                        $primary_color_id = !empty($_POST['primary_image_color']) ? (int)$_POST['primary_image_color'] : null;
                        if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] === UPLOAD_ERR_OK) {
                            $upload_res = validate_image_upload($_FILES['primary_image'], '../uploads/');
                            if ($upload_res['success']) {
                                $img_stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, color_id, is_featured) VALUES (?, ?, ?, 1)");
                                $img_stmt->execute([$product_id, $upload_res['filename'], $primary_color_id]);
                            } else {
                                throw new Exception("Primary Image: " . $upload_res['error']);
                            }
                        }

                        // Process Secondary Images (if any)
                        $secondary_color_id = !empty($_POST['secondary_images_color']) ? (int)$_POST['secondary_images_color'] : null;
                        if (isset($_FILES['secondary_images'])) {
                            $files = $_FILES['secondary_images'];
                            for ($i = 0; $i < count($files['name']); $i++) {
                                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                                    $single_file = [
                                        'name' => $files['name'][$i],
                                        'type' => $files['type'][$i],
                                        'tmp_name' => $files['tmp_name'][$i],
                                        'error' => $files['error'][$i],
                                        'size' => $files['size'][$i]
                                    ];
                                    $upload_res = validate_image_upload($single_file, '../uploads/');
                                    if ($upload_res['success']) {
                                        $img_stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, color_id, is_featured) VALUES (?, ?, ?, 0)");
                                        $img_stmt->execute([$product_id, $upload_res['filename'], $secondary_color_id]);
                                    } else {
                                        throw new Exception("Additional Image " . ($i + 1) . ": " . $upload_res['error']);
                                    }
                                }
                            }
                        }

                        // Process Color-Specific Images (if any)
                        if (isset($_FILES['color_images']) && is_array($_FILES['color_images']['name'])) {
                            foreach ($_FILES['color_images']['name'] as $c_id => $file_names) {
                                $c_id = (int)$c_id;
                                if ($c_id <= 0) continue;
                                if (is_array($file_names)) {
                                    for ($i = 0; $i < count($file_names); $i++) {
                                        if ($_FILES['color_images']['error'][$c_id][$i] === UPLOAD_ERR_OK) {
                                            $single_file = [
                                                'name' => $_FILES['color_images']['name'][$c_id][$i],
                                                'type' => $_FILES['color_images']['type'][$c_id][$i],
                                                'tmp_name' => $_FILES['color_images']['tmp_name'][$c_id][$i],
                                                'error' => $_FILES['color_images']['error'][$c_id][$i],
                                                'size' => $_FILES['color_images']['size'][$c_id][$i]
                                            ];
                                            $upload_res = validate_image_upload($single_file, '../uploads/');
                                            if ($upload_res['success']) {
                                                $img_stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, color_id, is_featured) VALUES (?, ?, ?, 0)");
                                                $img_stmt->execute([$product_id, $upload_res['filename'], $c_id]);
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // Process Mapped Fabrics (if any)
                        if (isset($_POST['selected_fabrics']) && is_array($_POST['selected_fabrics'])) {
                            $pf_stmt = $pdo->prepare("INSERT IGNORE INTO product_fabrics (product_id, fabric_id) VALUES (?, ?)");
                            foreach ($_POST['selected_fabrics'] as $fab_id) {
                                $fab_id = (int)$fab_id;
                                if ($fab_id > 0) {
                                    $pf_stmt->execute([$product_id, $fab_id]);
                                }
                            }
                        }

                        $pdo->commit();
                        log_admin_action($current_admin_id, "Added product: $name ($sku)");

                        // Handle Stock & Initial Variants for Single Product
                        $initial_stock = max(0, (int)($_POST['initial_stock'] ?? 0));
                        $selected_sizes = isset($_POST['selected_sizes']) && is_array($_POST['selected_sizes']) ? array_map('intval', $_POST['selected_sizes']) : [];
                        $selected_colors = isset($_POST['selected_colors']) && is_array($_POST['selected_colors']) ? array_map('intval', $_POST['selected_colors']) : [];

                        if ($product_type === 'single') {
                            $variants_created = 0;
                            if (!empty($selected_sizes) && !empty($selected_colors)) {
                                foreach ($selected_sizes as $s_id) {
                                    $s_stmt = $pdo->prepare("SELECT size_code FROM sizes WHERE id = ? LIMIT 1");
                                    $s_stmt->execute([$s_id]);
                                    $s_row = $s_stmt->fetch();
                                    $s_code = $s_row ? $s_row['size_code'] : $s_id;

                                    foreach ($selected_colors as $c_id) {
                                        $c_stmt = $pdo->prepare("SELECT * FROM colors WHERE id = ? LIMIT 1");
                                        $c_stmt->execute([$c_id]);
                                        $c_row = $c_stmt->fetch();
                                        $c_short = $c_row ? get_color_short_code($c_row['color_name'], $c_row['short_code'] ?? null) : $c_id;

                                        $v_sku = $sku . '-' . $s_code . '-' . $c_short;
                                        $v_stmt = $pdo->prepare("INSERT INTO product_variants (product_id, size_id, color_id, sku, price, stock) VALUES (?, ?, ?, ?, NULL, ?)");
                                        $v_stmt->execute([$product_id, $s_id, $c_id, $v_sku, $initial_stock]);
                                        $v_id = $pdo->lastInsertId();

                                        if ($initial_stock > 0) {
                                            $log_stmt = $pdo->prepare("INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) VALUES (?, ?, 'restock', 'Initial stock on product creation')");
                                            $log_stmt->execute([$v_id, $initial_stock]);
                                        }
                                        $variants_created++;
                                    }
                                }
                            } elseif (!empty($selected_colors)) {
                                // Colors were chosen, default size used
                                $def_size = $pdo->query("SELECT id, size_code FROM sizes LIMIT 1")->fetch();
                                if ($def_size) {
                                    foreach ($selected_colors as $c_id) {
                                        $c_stmt = $pdo->prepare("SELECT * FROM colors WHERE id = ? LIMIT 1");
                                        $c_stmt->execute([$c_id]);
                                        $c_row = $c_stmt->fetch();
                                        $c_short = $c_row ? get_color_short_code($c_row['color_name'], $c_row['short_code'] ?? null) : $c_id;

                                        $v_sku = $sku . '-' . $def_size['size_code'] . '-' . $c_short;
                                        $v_stmt = $pdo->prepare("INSERT INTO product_variants (product_id, size_id, color_id, sku, price, stock) VALUES (?, ?, ?, ?, NULL, ?)");
                                        $v_stmt->execute([$product_id, $def_size['id'], $c_id, $v_sku, $initial_stock]);
                                        $v_id = $pdo->lastInsertId();

                                        if ($initial_stock > 0) {
                                            $log_stmt = $pdo->prepare("INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) VALUES (?, ?, 'restock', 'Initial stock on product creation')");
                                            $log_stmt->execute([$v_id, $initial_stock]);
                                        }
                                        $variants_created++;
                                    }
                                }
                            } elseif (!empty($selected_sizes)) {
                                // Sizes were chosen, default color used
                                $def_color = $pdo->query("SELECT * FROM colors LIMIT 1")->fetch();
                                if ($def_color) {
                                    foreach ($selected_sizes as $s_id) {
                                        $s_stmt = $pdo->prepare("SELECT size_code FROM sizes WHERE id = ? LIMIT 1");
                                        $s_stmt->execute([$s_id]);
                                        $s_row = $s_stmt->fetch();
                                        $s_code = $s_row ? $s_row['size_code'] : $s_id;

                                        $v_sku = $sku . '-' . $s_code . '-' . get_color_short_code($def_color['color_name'], $def_color['short_code'] ?? null);
                                        $v_stmt = $pdo->prepare("INSERT INTO product_variants (product_id, size_id, color_id, sku, price, stock) VALUES (?, ?, ?, ?, NULL, ?)");
                                        $v_stmt->execute([$product_id, $s_id, $def_color['id'], $v_sku, $initial_stock]);
                                        $v_id = $pdo->lastInsertId();

                                        if ($initial_stock > 0) {
                                            $log_stmt = $pdo->prepare("INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) VALUES (?, ?, 'restock', 'Initial stock on product creation')");
                                            $log_stmt->execute([$v_id, $initial_stock]);
                                        }
                                        $variants_created++;
                                    }
                                }
                            } else {
                                // Create default initial variant so the product is immediately in stock
                                $def_size = $pdo->query("SELECT id, size_code FROM sizes LIMIT 1")->fetch();
                                $def_color = $pdo->query("SELECT * FROM colors LIMIT 1")->fetch();
                                if ($def_size && $def_color) {
                                    $v_sku = $sku . '-' . $def_size['size_code'] . '-' . get_color_short_code($def_color['color_name'], $def_color['short_code'] ?? null);
                                    $v_stmt = $pdo->prepare("INSERT INTO product_variants (product_id, size_id, color_id, sku, price, stock) VALUES (?, ?, ?, ?, NULL, ?)");
                                    $v_stmt->execute([$product_id, $def_size['id'], $def_color['id'], $v_sku, $initial_stock]);
                                    $v_id = $pdo->lastInsertId();

                                    if ($initial_stock > 0) {
                                        $log_stmt = $pdo->prepare("INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) VALUES (?, ?, 'restock', 'Initial stock on product creation')");
                                        $log_stmt->execute([$v_id, $initial_stock]);
                                    }
                                    $variants_created++;
                                }
                            }
                        }

                        if ($product_type === 'combo') {
                            set_flash_message('success', 'Combo product created! Now configure its set components.');
                            header('Location: combo_components.php?combo_id=' . $product_id);
                        } else {
                            set_flash_message('success', "Product added successfully with $initial_stock units initial stock!");
                            header('Location: products.php');
                        }
                        exit;
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Failed to create product: ' . $e->getMessage();
                }
            }
        }

        // 2. EDIT PRODUCT
        if (isset($_POST['edit_product'])) {
            $product_id = (int)$_POST['product_id'];
            $category_id = (int)$_POST['category_id'];
            $product_type = in_array($_POST['product_type'] ?? '', ['single', 'combo']) ? $_POST['product_type'] : 'single';
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $fabric_details = trim($_POST['fabric_details'] ?? '');
            $fit_type = trim($_POST['fit_type'] ?? '');
            $care_instructions = trim($_POST['care_instructions'] ?? '');
            $regular_price = (float)$_POST['regular_price'];
            $offer_price = !empty($_POST['offer_price']) ? (float)$_POST['offer_price'] : null;
            $gst_percentage = (isset($_POST['gst_percentage']) && $_POST['gst_percentage'] !== '') ? (float)$_POST['gst_percentage'] : DEFAULT_GST_RATE;
            
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $is_new_arrival = isset($_POST['is_new_arrival']) ? 1 : 0;
            $is_best_seller = isset($_POST['is_best_seller']) ? 1 : 0;
            $status = isset($_POST['status']) ? 1 : 0;

            $seo_title = trim($_POST['seo_title'] ?? '');
            $seo_description = trim($_POST['seo_description'] ?? '');

            if (empty($slug)) {
                $slug = slugify($name);
            } else {
                $slug = slugify($slug);
            }

            if (empty($name) || empty($sku) || empty($category_id) || $regular_price <= 0) {
                $error = 'Please fill in all required fields.';
            } else {
                try {
                    // Check duplicate SKU or slug
                    $check = $pdo->prepare("SELECT id FROM products WHERE (sku = ? OR slug = ?) AND id != ? LIMIT 1");
                    $check->execute([$sku, $slug, $product_id]);
                    if ($check->fetch()) {
                        $error = 'A product with this SKU or Slug already exists.';
                    } else {
                        $pdo->beginTransaction();

                        $stmt = $pdo->prepare("
                            UPDATE products SET 
                            category_id = ?, product_type = ?, name = ?, slug = ?, sku = ?, description = ?, fabric_details = ?, fit_type = ?, care_instructions = ?, 
                            regular_price = ?, offer_price = ?, gst_percentage = ?, is_featured = ?, is_new_arrival = ?, is_best_seller = ?, status = ?, 
                            seo_title = ?, seo_description = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$category_id, $product_type, $name, $slug, $sku, $description, $fabric_details, $fit_type, $care_instructions, $regular_price, $offer_price, $gst_percentage, $is_featured, $is_new_arrival, $is_best_seller, $status, $seo_title, $seo_description, $product_id]);

                        // Handle new primary image upload
                        $primary_color_id = !empty($_POST['primary_image_color']) ? (int)$_POST['primary_image_color'] : null;
                        if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] === UPLOAD_ERR_OK) {
                            $upload_res = validate_image_upload($_FILES['primary_image'], '../uploads/');
                            if ($upload_res['success']) {
                                // De-feature old ones
                                $pdo->prepare("UPDATE product_images SET is_featured = 0 WHERE product_id = ?")->execute([$product_id]);
                                // Insert new featured
                                $img_stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, color_id, is_featured) VALUES (?, ?, ?, 1)");
                                $img_stmt->execute([$product_id, $upload_res['filename'], $primary_color_id]);
                            } else {
                                throw new Exception("Primary Image: " . $upload_res['error']);
                            }
                        }

                        // Process additional secondary images
                        $secondary_color_id = !empty($_POST['secondary_images_color']) ? (int)$_POST['secondary_images_color'] : null;
                        if (isset($_FILES['secondary_images'])) {
                            $files = $_FILES['secondary_images'];
                            for ($i = 0; $i < count($files['name']); $i++) {
                                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                                    $single_file = [
                                        'name' => $files['name'][$i],
                                        'type' => $files['type'][$i],
                                        'tmp_name' => $files['tmp_name'][$i],
                                        'error' => $files['error'][$i],
                                        'size' => $files['size'][$i]
                                    ];
                                    $upload_res = validate_image_upload($single_file, '../uploads/');
                                    if ($upload_res['success']) {
                                        $img_stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, color_id, is_featured) VALUES (?, ?, ?, 0)");
                                        $img_stmt->execute([$product_id, $upload_res['filename'], $secondary_color_id]);
                                    } else {
                                        throw new Exception("Additional Image " . ($i + 1) . ": " . $upload_res['error']);
                                    }
                                }
                            }
                        }

                        // Process Color-Specific Images on Edit
                        if (isset($_FILES['color_images']) && is_array($_FILES['color_images']['name'])) {
                            foreach ($_FILES['color_images']['name'] as $c_id => $file_names) {
                                $c_id = (int)$c_id;
                                if ($c_id <= 0) continue;
                                if (is_array($file_names)) {
                                    for ($i = 0; $i < count($file_names); $i++) {
                                        if ($_FILES['color_images']['error'][$c_id][$i] === UPLOAD_ERR_OK) {
                                            $single_file = [
                                                'name' => $_FILES['color_images']['name'][$c_id][$i],
                                                'type' => $_FILES['color_images']['type'][$c_id][$i],
                                                'tmp_name' => $_FILES['color_images']['tmp_name'][$c_id][$i],
                                                'error' => $_FILES['color_images']['error'][$c_id][$i],
                                                'size' => $_FILES['color_images']['size'][$c_id][$i]
                                            ];
                                            $upload_res = validate_image_upload($single_file, '../uploads/');
                                            if ($upload_res['success']) {
                                                $img_stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, color_id, is_featured) VALUES (?, ?, ?, 0)");
                                                $img_stmt->execute([$product_id, $upload_res['filename'], $c_id]);
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // Update existing images color assignments
                        if (isset($_POST['image_color']) && is_array($_POST['image_color'])) {
                            $update_color_stmt = $pdo->prepare("UPDATE product_images SET color_id = ? WHERE id = ? AND product_id = ?");
                            foreach ($_POST['image_color'] as $img_id => $c_val) {
                                $img_id = (int)$img_id;
                                $c_val = !empty($c_val) ? (int)$c_val : null;
                                $update_color_stmt->execute([$c_val, $img_id, $product_id]);
                            }
                        }

                        // Process Mapped Fabrics on Edit
                        if (isset($_POST['selected_fabrics'])) {
                            $pdo->prepare("DELETE FROM product_fabrics WHERE product_id = ?")->execute([$product_id]);
                            if (is_array($_POST['selected_fabrics'])) {
                                $pf_stmt = $pdo->prepare("INSERT IGNORE INTO product_fabrics (product_id, fabric_id) VALUES (?, ?)");
                                foreach ($_POST['selected_fabrics'] as $fab_id) {
                                    $fab_id = (int)$fab_id;
                                    if ($fab_id > 0) {
                                        $pf_stmt->execute([$product_id, $fab_id]);
                                    }
                                }
                            }
                        }

                        // Handle variant matrix sync / updates on edit
                        $initial_stock = max(0, (int)($_POST['initial_stock'] ?? 0));
                        if ($product_type === 'single') {
                            $selected_sizes = isset($_POST['selected_sizes']) && is_array($_POST['selected_sizes']) ? array_map('intval', $_POST['selected_sizes']) : [];
                            $selected_colors = isset($_POST['selected_colors']) && is_array($_POST['selected_colors']) ? array_map('intval', $_POST['selected_colors']) : [];

                            $var_check = $pdo->prepare("SELECT id, size_id, color_id, sku, stock FROM product_variants WHERE product_id = ?");
                            $var_check->execute([$product_id]);
                            $existing_vars = $var_check->fetchAll();

                            if (!empty($selected_sizes) || !empty($selected_colors)) {
                                $effective_sizes = !empty($selected_sizes) ? $selected_sizes : [];
                                $effective_colors = !empty($selected_colors) ? $selected_colors : [];

                                if (empty($effective_sizes)) {
                                    $def_size = $pdo->query("SELECT id FROM sizes LIMIT 1")->fetch();
                                    if ($def_size) $effective_sizes = [(int)$def_size['id']];
                                }
                                if (empty($effective_colors)) {
                                    $def_color = $pdo->query("SELECT id FROM colors LIMIT 1")->fetch();
                                    if ($def_color) $effective_colors = [(int)$def_color['id']];
                                }

                                $existing_map = [];
                                foreach ($existing_vars as $ev) {
                                    $key = $ev['size_id'] . '_' . $ev['color_id'];
                                    $existing_map[$key] = $ev;
                                }

                                $valid_keys = [];

                                foreach ($effective_sizes as $s_id) {
                                    $s_stmt = $pdo->prepare("SELECT size_code FROM sizes WHERE id = ? LIMIT 1");
                                    $s_stmt->execute([$s_id]);
                                    $s_row = $s_stmt->fetch();
                                    $s_code = $s_row ? $s_row['size_code'] : $s_id;

                                    foreach ($effective_colors as $c_id) {
                                        $c_stmt = $pdo->prepare("SELECT * FROM colors WHERE id = ? LIMIT 1");
                                        $c_stmt->execute([$c_id]);
                                        $c_row = $c_stmt->fetch();
                                        $c_short = $c_row ? get_color_short_code($c_row['color_name'], $c_row['short_code'] ?? null) : $c_id;

                                        $key = $s_id . '_' . $c_id;
                                        $valid_keys[$key] = true;

                                        if (!isset($existing_map[$key])) {
                                            $v_sku = $sku . '-' . $s_code . '-' . $c_short;
                                            $chk_sku = $pdo->prepare("SELECT id FROM product_variants WHERE sku = ? LIMIT 1");
                                            $chk_sku->execute([$v_sku]);
                                            if ($chk_sku->fetch()) {
                                                $v_sku .= '-' . time();
                                            }

                                            $v_stmt = $pdo->prepare("INSERT INTO product_variants (product_id, size_id, color_id, sku, price, stock) VALUES (?, ?, ?, ?, NULL, ?)");
                                            $v_stmt->execute([$product_id, $s_id, $c_id, $v_sku, $initial_stock]);
                                            $v_id = $pdo->lastInsertId();

                                            if ($initial_stock > 0) {
                                                $log_stmt = $pdo->prepare("INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) VALUES (?, ?, 'restock', 'Initial stock created on variant update')");
                                                $log_stmt->execute([$v_id, $initial_stock]);
                                            }
                                        }
                                    }
                                }

                                // Remove any existing variants that are no longer part of the selected sizes and colors
                                foreach ($existing_vars as $ev) {
                                    $ev_key = $ev['size_id'] . '_' . $ev['color_id'];
                                    if (!isset($valid_keys[$ev_key])) {
                                        $pdo->prepare("DELETE FROM product_variants WHERE id = ?")->execute([$ev['id']]);
                                    }
                                }

                                // Disassociate unchecked colors from existing uploaded images (make them general photos)
                                if (!empty($selected_colors)) {
                                    $in_placeholders = implode(',', array_fill(0, count($selected_colors), '?'));
                                    $pdo->prepare("UPDATE product_images SET color_id = NULL WHERE product_id = ? AND color_id IS NOT NULL AND color_id NOT IN ($in_placeholders)")
                                        ->execute(array_merge([$product_id], $selected_colors));
                                } else {
                                    $pdo->prepare("UPDATE product_images SET color_id = NULL WHERE product_id = ?")->execute([$product_id]);
                                }
                            } elseif (count($existing_vars) === 1) {
                                $old_stock = (int)$existing_vars[0]['stock'];
                                $diff = $initial_stock - $old_stock;
                                $pdo->prepare("UPDATE product_variants SET stock = ? WHERE id = ?")->execute([$initial_stock, $existing_vars[0]['id']]);
                                if ($diff != 0) {
                                    $pdo->prepare("INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) VALUES (?, ?, ?, 'Stock updated via product edit')")
                                        ->execute([$existing_vars[0]['id'], abs($diff), $diff > 0 ? 'restock' : 'adjustment']);
                                }
                            } elseif (count($existing_vars) === 0) {
                                $def_size = $pdo->query("SELECT id, size_code FROM sizes LIMIT 1")->fetch();
                                $def_color = $pdo->query("SELECT * FROM colors LIMIT 1")->fetch();
                                if ($def_size && $def_color) {
                                    $v_sku = $sku . '-' . $def_size['size_code'] . '-' . get_color_short_code($def_color['color_name'], $def_color['short_code'] ?? null);
                                    $v_stmt = $pdo->prepare("INSERT INTO product_variants (product_id, size_id, color_id, sku, price, stock) VALUES (?, ?, ?, ?, NULL, ?)");
                                    $v_stmt->execute([$product_id, $def_size['id'], $def_color['id'], $v_sku, $initial_stock]);
                                    $v_id = $pdo->lastInsertId();
                                    if ($initial_stock > 0) {
                                        $pdo->prepare("INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) VALUES (?, ?, 'restock', 'Initial stock created on product edit')")
                                            ->execute([$v_id, $initial_stock]);
                                    }
                                }
                            }
                        }

                        $pdo->commit();
                        log_admin_action($current_admin_id, "Updated product: $name ($sku)");
                        set_flash_message('success', 'Product updated successfully!');
                        header('Location: products.php');
                        exit;
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Failed to update product: ' . $e->getMessage();
                }
            }
        }
    }
}
}

// Handle Set Primary/Featured Image
if (isset($_GET['set_featured_image_id'])) {
    $img_id = (int)$_GET['set_featured_image_id'];
    $p_id = (int)($_GET['product_id'] ?? 0);
    try {
        if ($img_id > 0 && $p_id > 0) {
            $pdo->prepare("UPDATE product_images SET is_featured = 0 WHERE product_id = ?")->execute([$p_id]);
            $pdo->prepare("UPDATE product_images SET is_featured = 1 WHERE id = ? AND product_id = ?")->execute([$img_id, $p_id]);
            log_admin_action($current_admin_id, "Set product image $img_id as primary for product $p_id");
            set_flash_message('success', 'Primary featured image updated!');
        }
    } catch (PDOException $e) {
        set_flash_message('error', 'Failed to update featured image.');
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'products.php'));
    exit;
}

// Handle delete image
if (isset($_GET['delete_image_id'])) {
    $img_id = (int)$_GET['delete_image_id'];
    try {
        $img_stmt = $pdo->prepare("SELECT * FROM product_images WHERE id = ? LIMIT 1");
        $img_stmt->execute([$img_id]);
        $img_data = $img_stmt->fetch();
        if ($img_data) {
            // Delete record
            $del_stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
            $del_stmt->execute([$img_id]);
            // Delete file
            if (file_exists('../uploads/' . $img_data['image_path'])) {
                @unlink('../uploads/' . $img_data['image_path']);
            }
            log_admin_action($current_admin_id, "Deleted product image id: $img_id");
            set_flash_message('success', 'Image deleted successfully!');
        }
    } catch (PDOException $e) {
        set_flash_message('error', 'Failed to delete image.');
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'products.php'));
    exit;
}

// Handle delete product
if (isset($_GET['delete_product_id'])) {
    $del_id = (int)$_GET['delete_product_id'];
    try {
        $p_stmt = $pdo->prepare("SELECT id, name, sku FROM products WHERE id = ? LIMIT 1");
        $p_stmt->execute([$del_id]);
        $prod_to_del = $p_stmt->fetch();

        if (!$prod_to_del) {
            set_flash_message('error', 'Product not found.');
        } else {
            // Check if product is referenced in customer orders
            $order_check = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ? OR combo_product_id = ?");
            $order_check->execute([$del_id, $del_id]);
            $order_count = (int)$order_check->fetchColumn();

            if ($order_count > 0) {
                set_flash_message('error', 'Cannot delete product "' . $prod_to_del['name'] . '" because it is linked to ' . $order_count . ' customer order item(s). To remove it from customer view, please toggle its status to Unpublished.');
            } else {
                $pdo->beginTransaction();

                // Fetch and delete physical image files
                $imgs = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
                $imgs->execute([$del_id]);
                $images_to_remove = $imgs->fetchAll();
                foreach ($images_to_remove as $img_row) {
                    if (!empty($img_row['image_path']) && file_exists('../uploads/' . $img_row['image_path'])) {
                        @unlink('../uploads/' . $img_row['image_path']);
                    }
                }

                // Delete product record (FK cascades handle variants, images, combo components, wishlist, reviews)
                $del_stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $del_stmt->execute([$del_id]);

                $pdo->commit();
                log_admin_action($current_admin_id, "Permanently deleted product: " . $prod_to_del['name'] . " (" . $prod_to_del['sku'] . ")");
                set_flash_message('success', 'Product "' . $prod_to_del['name'] . '" has been permanently deleted.');
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash_message('error', 'Failed to delete product: ' . $e->getMessage());
    }
    header('Location: products.php');
    exit;
}

// Render views based on action
if ($action === 'add' || $action === 'edit'):
    $product = null;
    $product_images_list = [];
    $selected_sizes = [];
    $selected_colors = [];
    $product_total_stock = 50;
    $product_var_count = 0;
    $product_color_ids = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Retain submitted form values in case of error
        $product = [
            'id' => $_POST['product_id'] ?? null,
            'category_id' => $_POST['category_id'] ?? null,
            'product_type' => $_POST['product_type'] ?? 'single',
            'name' => $_POST['name'] ?? '',
            'slug' => $_POST['slug'] ?? '',
            'sku' => $_POST['sku'] ?? '',
            'description' => $_POST['description'] ?? '',
            'fabric_details' => $_POST['fabric_details'] ?? '',
            'fit_type' => $_POST['fit_type'] ?? '',
            'care_instructions' => $_POST['care_instructions'] ?? '',
            'regular_price' => $_POST['regular_price'] ?? '',
            'offer_price' => $_POST['offer_price'] ?? '',
            'gst_percentage' => (isset($_POST['gst_percentage']) && $_POST['gst_percentage'] !== '') ? $_POST['gst_percentage'] : DEFAULT_GST_RATE,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'is_new_arrival' => isset($_POST['is_new_arrival']) ? 1 : 0,
            'is_best_seller' => isset($_POST['is_best_seller']) ? 1 : 0,
            'status' => isset($_POST['status']) ? 1 : 0,
            'seo_title' => $_POST['seo_title'] ?? '',
            'seo_description' => $_POST['seo_description'] ?? ''
        ];
        $product_total_stock = isset($_POST['initial_stock']) ? (int)$_POST['initial_stock'] : 50;
        $selected_sizes = isset($_POST['selected_sizes']) && is_array($_POST['selected_sizes']) ? array_map('intval', $_POST['selected_sizes']) : [];
        $selected_colors = isset($_POST['selected_colors']) && is_array($_POST['selected_colors']) ? array_map('intval', $_POST['selected_colors']) : [];
        $product_color_ids = $selected_colors;
    }

    if ($action === 'edit') {
        $id = (int)($_GET['id'] ?? ($_POST['product_id'] ?? 0));
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($product['name'])) {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            if (!$product) {
                set_flash_message('error', 'Product not found.');
                header('Location: products.php');
                exit;
            }
        }
        // Fetch existing images with color info
        $product_images_list = $pdo->prepare("
            SELECT pi.*, cl.color_name, cl.color_code 
            FROM product_images pi 
            LEFT JOIN colors cl ON pi.color_id = cl.id 
            WHERE pi.product_id = ? 
            ORDER BY pi.is_featured DESC, pi.id ASC
        ");
        $product_images_list->execute([$id]);
        $product_images_list = $product_images_list->fetchAll();
    }
    
    // Fetch categories options
    $categories = $pdo->query("SELECT * FROM categories WHERE status = 1 ORDER BY name ASC")->fetchAll();
    $all_sizes = $pdo->query("SELECT * FROM sizes ORDER BY category_type ASC, id ASC")->fetchAll();
    sort_sizes($all_sizes, true);
    $all_colors = $pdo->query("SELECT * FROM colors ORDER BY id ASC")->fetchAll();

    // Fetch fabrics
    try {
        $all_fabrics = $pdo->query("SELECT * FROM fabrics WHERE status = 1 ORDER BY name ASC")->fetchAll();
    } catch (PDOException $e) {
        $all_fabrics = [];
    }

    $product_fabric_ids = [];
    if ($action === 'edit' && isset($id) && $id > 0) {
        try {
            $fab_stmt = $pdo->prepare("SELECT fabric_id FROM product_fabrics WHERE product_id = ?");
            $fab_stmt->execute([$id]);
            $product_fabric_ids = $fab_stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            $product_fabric_ids = [];
        }
    } elseif ($action === 'add' && isset($_POST['selected_fabrics'])) {
        $product_fabric_ids = is_array($_POST['selected_fabrics']) ? array_map('intval', $_POST['selected_fabrics']) : [];
    }

    if ($action === 'edit' && isset($id) && $id > 0) {
        $curr_stock_stmt = $pdo->prepare("SELECT SUM(stock) as total_stock, COUNT(*) as var_count FROM product_variants WHERE product_id = ?");
        $curr_stock_stmt->execute([$id]);
        $curr_stock_info = $curr_stock_stmt->fetch();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $product_total_stock = $curr_stock_info ? (int)$curr_stock_info['total_stock'] : 0;
        }
        $product_var_count = $curr_stock_info ? (int)$curr_stock_info['var_count'] : 0;

        $c_stmt = $pdo->prepare("
            SELECT DISTINCT color_id FROM product_variants WHERE product_id = ? AND color_id IS NOT NULL
        ");
        $c_stmt->execute([$id]);
        $product_color_ids = array_map('intval', $c_stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $selected_colors = $product_color_ids;
        }

        $s_stmt = $pdo->prepare("
            SELECT DISTINCT size_id FROM product_variants WHERE product_id = ? AND size_id IS NOT NULL
        ");
        $s_stmt->execute([$id]);
        $curr_size_ids = array_map('intval', $s_stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $selected_sizes = $curr_size_ids;
        }
    }
?>

    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
        <h5 class="fw-bold mb-4"><?php echo $action === 'add' ? 'Add New Product' : 'Edit Product: ' . e($product['name']); ?></h5>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form action="products.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $product['id'] : ''; ?>" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <!-- Core info -->
                <div class="col-12 col-md-4">
                    <label for="name" class="form-label fw-bold small">Product Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo e($product['name'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label for="product_type" class="form-label fw-bold small">Product Type <span class="text-danger">*</span></label>
                    <select class="form-select" id="product_type" name="product_type" required>
                        <option value="single" <?php echo (!isset($product['product_type']) || $product['product_type'] === 'single') ? 'selected' : ''; ?>>Single Product</option>
                        <option value="combo" <?php echo (isset($product['product_type']) && $product['product_type'] === 'combo') ? 'selected' : ''; ?>>Combo / Bundle Set</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label for="sku" class="form-label fw-bold small">SKU <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="sku" name="sku" value="<?php echo e($product['sku'] ?? ''); ?>" placeholder="e.g. BR-ELG-001" required>
                </div>
                <div class="col-12 col-md-3">
                    <label for="category_id" class="form-label fw-bold small">Category <span class="text-danger">*</span></label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">-- Choose Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo (isset($product['category_id']) && $product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo e($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Pricing & Stock -->
                <div class="col-12 col-md-3">
                    <label for="regular_price" class="form-label fw-bold small">Regular Price (INR) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" class="form-control" id="regular_price" name="regular_price" value="<?php echo e($product['regular_price'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label for="offer_price" class="form-label fw-bold small">Offer Price (INR) (Optional)</label>
                    <input type="number" step="0.01" class="form-control" id="offer_price" name="offer_price" value="<?php echo e($product['offer_price'] ?? ''); ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label for="gst_percentage" class="form-label fw-bold small">GST Rate (%)</label>
                    <input type="number" step="0.1" class="form-control" id="gst_percentage" name="gst_percentage" value="<?php echo e($product['gst_percentage'] ?? DEFAULT_GST_RATE); ?>">
                </div>
                <div class="col-12 col-md-3" id="stock_field_wrapper">
                    <label for="initial_stock" class="form-label fw-bold small">
                        <?php echo $action === 'add' ? 'Initial Stock Quantity' : 'Current Stock Units'; ?> <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-box-seam"></i></span>
                        <input type="number" min="0" class="form-control" id="initial_stock" name="initial_stock" value="<?php echo e($product_total_stock); ?>" placeholder="e.g. 50" required>
                    </div>
                    <div class="form-text small text-muted">
                        <?php if ($action === 'edit' && $product_var_count > 0): ?>
                            <span>Total across <?php echo $product_var_count; ?> variants. <a href="variants.php?product_id=<?php echo $product['id']; ?>" class="fw-semibold text-rose">Manage matrix</a></span>
                        <?php else: ?>
                            <span>Available inventory units for this product.</span>
                        <?php endif; ?>
                    </div>
                </div>

                    <!-- Sizes & Colors Quick Selector (Add & Edit Product modes) -->
                    <div class="col-12 border-top pt-3" id="variant_quick_setup">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-grid-3x3-gap me-2 text-rose"></i> Available Sizes & Colors (Optional)</h6>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($action === 'edit' && !empty($product['id'])): ?>
                                    <a href="variants.php?product_id=<?php echo $product['id']; ?>" class="btn btn-xs btn-outline-primary rounded-pill py-1 px-3 small" style="font-size: 11px;">
                                        <i class="bi bi-gear-wide-connected me-1"></i> Advanced Matrix & Stock Manager
                                    </a>
                                <?php endif; ?>
                                <span class="badge bg-light text-dark border py-1 px-2 small shadow-sm" id="variant_matrix_summary_badge">
                                    Default SKU will be created
                                </span>
                            </div>
                        </div>
                        <p class="text-muted small mb-3">
                            <?php if ($action === 'edit'): ?>
                                Select or modify the sizes and colors available for this product. Checked combinations will be synchronized with this product's variant matrix.
                            <?php else: ?>
                                Select the sizes and colors available for this product to automatically generate variant SKUs with the initial stock specified above.
                            <?php endif; ?>
                        </p>
                        
                        <div class="row g-3">
                            <!-- 1. SIZES SELECTOR -->
                            <div class="col-12 col-md-6">
                                <div class="card border bg-light h-100 shadow-sm" style="border-radius: 10px;">
                                    <div class="card-header bg-white border-bottom p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label fw-bold small text-uppercase text-dark mb-0">
                                                <i class="bi bi-rulers me-1 text-primary"></i> Sizes
                                                <span class="badge bg-light text-secondary border ms-1" id="sizes_count_badge">0 Selected</span>
                                            </label>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary py-0 px-2 small" id="btn_select_all_sizes" style="font-size: 11px;">Select Visible</button>
                                                <button type="button" class="btn btn-outline-secondary py-0 px-2 small" id="btn_clear_sizes" style="font-size: 11px;">Clear</button>
                                            </div>
                                        </div>

                                        <!-- Search input for Sizes -->
                                        <div class="input-group input-group-sm mb-2">
                                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                                            <input type="text" class="form-control border-start-0" id="size_search_input" placeholder="Search size (e.g. 32B, M, 34)...">
                                            <button class="btn btn-outline-secondary border" type="button" id="size_search_clear" title="Clear search" style="display:none;"><i class="bi bi-x"></i></button>
                                        </div>

                                        <!-- Category filter pills for Sizes -->
                                        <?php 
                                        $sizes_by_type = [];
                                        foreach ($all_sizes as $sz) {
                                            $sizes_by_type[$sz['category_type']][] = $sz;
                                        }
                                        $cat_types = array_keys($sizes_by_type);
                                        ?>
                                        <?php if (count($cat_types) > 1): ?>
                                            <div class="d-flex flex-wrap gap-1" id="size_category_tabs">
                                                <button type="button" class="btn btn-xs btn-primary rounded-pill py-0 px-2 size-cat-tab active" data-cat="all" style="font-size: 11px;">All</button>
                                                <?php foreach ($cat_types as $c_type): ?>
                                                    <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill py-0 px-2 size-cat-tab" data-cat="<?php echo e($c_type); ?>" style="font-size: 11px;">
                                                        <?php echo e($c_type); ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="card-body p-3 bg-light overflow-y-auto" id="sizes_items_container" style="max-height: 220px;">
                                        <?php foreach ($sizes_by_type as $cat_type => $sz_list): ?>
                                            <div class="size-group-wrapper mb-3" data-cat-type="<?php echo e($cat_type); ?>">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="text-dark small fw-bold text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;"><?php echo e($cat_type); ?></span>
                                                    <a href="javascript:void(0);" class="small text-muted text-decoration-none group-select-link" data-cat-type="<?php echo e($cat_type); ?>" style="font-size: 10px;">Select group</a>
                                                </div>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php foreach ($sz_list as $sz): 
                                                        $is_sz_checked = in_array((int)$sz['id'], $selected_sizes);
                                                    ?>
                                                        <label class="btn btn-sm btn-outline-secondary py-1 px-2 rounded-pill small size-choice-pill <?php echo $is_sz_checked ? 'border-primary bg-white shadow-sm fw-bold active text-primary' : ''; ?>" 
                                                               data-size-code="<?php echo strtolower(e($sz['size_code'])); ?>" 
                                                               data-size-name="<?php echo strtolower(e($sz['size_name'])); ?>"
                                                               data-cat-type="<?php echo e($cat_type); ?>"
                                                               style="font-size: 12px; cursor: pointer;">
                                                            <input type="checkbox" name="selected_sizes[]" value="<?php echo $sz['id']; ?>" class="form-check-input me-1" <?php echo $is_sz_checked ? 'checked' : ''; ?>>
                                                            <?php echo e($sz['size_code']); ?>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <div id="no_sizes_match_msg" class="text-center text-muted small py-3" style="display:none;">
                                            <i class="bi bi-search me-1"></i> No matching sizes found
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. COLORS SELECTOR -->
                            <div class="col-12 col-md-6">
                                <div class="card border bg-light h-100 shadow-sm" style="border-radius: 10px;">
                                    <div class="card-header bg-white border-bottom p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label fw-bold small text-uppercase text-dark mb-0">
                                                <i class="bi bi-palette me-1 text-primary"></i> Colors
                                                <span class="badge bg-light text-secondary border ms-1" id="colors_count_badge">0 Selected</span>
                                            </label>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary py-0 px-2 small" id="btn_select_all_colors" style="font-size: 11px;">Select Visible</button>
                                                <button type="button" class="btn btn-outline-secondary py-0 px-2 small" id="btn_clear_colors" style="font-size: 11px;">Clear</button>
                                            </div>
                                        </div>

                                        <!-- Search input for Colors -->
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                                            <input type="text" class="form-control border-start-0" id="color_search_input" placeholder="Search color (e.g. Pink, Black, Blue)...">
                                            <button class="btn btn-outline-secondary border" type="button" id="color_search_clear" title="Clear search" style="display:none;"><i class="bi bi-x"></i></button>
                                        </div>
                                    </div>

                                    <div class="card-body p-3 bg-light overflow-y-auto" id="colors_items_container" style="max-height: 220px;">
                                        <div class="d-flex flex-wrap gap-2" id="colors_list_wrapper">
                                            <?php foreach ($all_colors as $clr): 
                                                $is_clr_checked = in_array((int)$clr['id'], $selected_colors);
                                            ?>
                                                <label class="btn btn-sm btn-outline-secondary py-1 px-2 rounded-pill small color-choice-pill <?php echo $is_clr_checked ? 'border-primary bg-white shadow-sm fw-bold active text-primary' : ''; ?>" 
                                                       data-color-name="<?php echo strtolower(e($clr['color_name'])); ?>"
                                                       style="font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                                    <input type="checkbox" name="selected_colors[]" value="<?php echo $clr['id']; ?>" class="form-check-input me-1 color-selection-checkbox" data-color-name="<?php echo e($clr['color_name']); ?>" <?php echo $is_clr_checked ? 'checked' : ''; ?>>
                                                    <span style="display:inline-block; width:13px; height:13px; border-radius:50%; background-color:<?php echo e($clr['color_code']); ?>; border:1px solid rgba(0,0,0,0.15); flex-shrink:0;"></span>
                                                    <span><?php echo e($clr['color_name']); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <div id="no_colors_match_msg" class="text-center text-muted small py-3" style="display:none;">
                                            <i class="bi bi-search me-1"></i> No matching colors found
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- Product Specifications -->
                <div class="col-12 col-md-4">
                    <label for="fit_type" class="form-label fw-bold small">Fit/Style Type</label>
                    <input type="text" class="form-control" id="fit_type" name="fit_type" value="<?php echo e($product['fit_type'] ?? ''); ?>" placeholder="e.g. Underwired full coverage">
                </div>
                <div class="col-12 col-md-8">
                    <label for="fabric_details" class="form-label fw-bold small">Fabric Details (Text Description)</label>
                    <input type="text" class="form-control" id="fabric_details" name="fabric_details" value="<?php echo e($product['fabric_details'] ?? ''); ?>" placeholder="e.g. 80% Nylon, 20% Elastane">
                </div>

                <?php if (!empty($all_fabrics)): ?>
                <div class="col-12">
                    <div class="p-3 bg-light rounded border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold small mb-0">
                                <i class="bi bi-layers text-primary me-1"></i> Fabric Materials (Catalog Filter Tagging)
                            </label>
                            <a href="fabrics.php" target="_blank" class="small text-decoration-none text-muted">
                                <i class="bi bi-gear me-1"></i>Manage Fabric Materials
                            </a>
                        </div>
                        <p class="text-muted small mb-2" style="font-size: 11px;">
                            Select which fabric materials apply to this product. Customers will be able to filter by these materials in the storefront catalog.
                        </p>
                        <div class="d-flex flex-wrap gap-2" id="fabricBadgesContainer">
                            <?php foreach ($all_fabrics as $fab): 
                                $is_fab_checked = in_array((int)$fab['id'], array_map('intval', $product_fabric_ids ?? []));
                            ?>
                                <div>
                                    <input class="btn-check fabric-checkbox" type="checkbox" name="selected_fabrics[]" id="fab_chk_<?php echo $fab['id']; ?>" value="<?php echo $fab['id']; ?>" <?php echo $is_fab_checked ? 'checked' : ''; ?> autocomplete="off">
                                    <label class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1" for="fab_chk_<?php echo $fab['id']; ?>" style="font-size: 12px;">
                                        <?php echo e($fab['name']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="col-12">
                    <label for="description" class="form-label fw-bold small">Product Description <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="description" name="description" rows="4" required><?php echo e($product['description'] ?? ''); ?></textarea>
                </div>

                <div class="col-12">
                    <label for="care_instructions" class="form-label fw-bold small">Care Instructions</label>
                    <textarea class="form-control" id="care_instructions" name="care_instructions" rows="2" placeholder="e.g. Hand wash cold, dry flat in shade."><?php echo e($product['care_instructions'] ?? ''); ?></textarea>
                </div>

                <!-- Product Images & Gallery Section -->
                <div class="col-12 border-top pt-3">
                    <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-images me-2 text-rose"></i> Product Photos & Color Gallery</h6>
                    <p class="text-muted small mb-3">Upload high-resolution photography. You can upload general photos or assign photos to specific color swatches so customers see the exact color they select.</p>
                </div>

                <div class="col-12 col-md-6">
                    <label for="primary_image" class="form-label fw-bold small">
                        Primary / Default Featured Image <?php echo $action === 'add' ? '<span class="text-danger">*</span>' : '(Select only to replace)'; ?>
                        <span class="badge bg-light text-primary border ms-1"><i class="bi bi-aspect-ratio me-1"></i>800 × 1067 px (3:4)</span>
                    </label>
                    <input type="file" class="form-control" id="primary_image" name="primary_image" accept="image/*" <?php echo $action === 'add' ? 'required' : ''; ?>>
                    <div class="form-text small text-muted mb-2">
                        <i class="bi bi-info-circle me-1 text-primary"></i> Main catalog thumbnail &bull; Max: 50MB (JPG, PNG, WEBP)
                    </div>
                    <div>
                        <label class="small text-muted fw-semibold mb-1" style="font-size: 11px;">Assign Primary Image to Color (Optional):</label>
                        <select name="primary_image_color" class="form-select form-select-sm" style="font-size: 12px;">
                            <option value="">-- General (All Colors) --</option>
                            <?php foreach ($all_colors as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo e($c['color_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <label for="secondary_images" class="form-label fw-bold small">
                        Additional General Gallery Images (All Colors)
                        <span class="badge bg-light text-primary border ms-1"><i class="bi bi-aspect-ratio me-1"></i>800 × 1067 px (3:4)</span>
                    </label>
                    <input type="file" class="form-control" id="secondary_images" name="secondary_images[]" accept="image/*" multiple>
                    <div class="form-text small text-muted mb-2">
                        <i class="bi bi-info-circle me-1 text-primary"></i> General multi-angle photos shown for all colors
                    </div>
                    <div>
                        <label class="small text-muted fw-semibold mb-1" style="font-size: 11px;">Assign These Additional Images to Color (Optional):</label>
                        <select name="secondary_images_color" class="form-select form-select-sm" style="font-size: 12px;">
                            <option value="">-- General (All Colors) --</option>
                            <?php foreach ($all_colors as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo e($c['color_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Color-Specific Uploads -->
                <div class="col-12" id="color_photos_wrapper">
                    <div class="card border bg-light p-3 rounded">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold small text-dark"><i class="bi bi-palette me-1 text-rose"></i> Upload Color-Specific Photos (Optional)</span>
                            <span id="color_photos_badge" class="badge bg-white text-secondary border small">
                                <?php if ($action === 'add'): ?>
                                    0 Colors Selected
                                <?php else: ?>
                                    <?php echo count($product_color_ids); ?> Colors
                                <?php endif; ?>
                            </span>
                        </div>

                        <!-- Placeholder when no colors are selected in Add mode or found in Edit mode -->
                        <div id="no_colors_selected_msg" class="text-center py-4 bg-white rounded border text-muted" <?php echo ($action === 'edit' && !empty($product_color_ids)) ? 'style="display:none;"' : ''; ?>>
                            <i class="bi bi-palette fs-4 text-secondary d-block mb-1"></i>
                            <span class="small fw-medium">
                                <?php if ($action === 'add'): ?>
                                    No colors selected yet. Check the boxes under <strong>"Select Colors"</strong> above to upload photos for those specific color swatches.
                                <?php else: ?>
                                    No color variants or tagged images found for this product. You can manage color variants in the <a href="variants.php?product_id=<?php echo $product['id']; ?>" class="text-rose fw-semibold">Variants Matrix</a>.
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="row g-2" id="color_photos_container">
                            <?php foreach ($all_colors as $clr): 
                                $is_visible = false;
                                if ($action === 'edit' && in_array($clr['id'], $product_color_ids)) {
                                    $is_visible = true;
                                } elseif ($action === 'add' && in_array((int)$clr['id'], $selected_colors)) {
                                    $is_visible = true;
                                }
                            ?>
                                <div class="col-12 col-md-6 col-lg-4 color-photo-box" id="color_box_<?php echo $clr['id']; ?>" data-color-id="<?php echo $clr['id']; ?>" <?php echo !$is_visible ? 'style="display: none;"' : ''; ?>>
                                    <div class="bg-white p-2 rounded border h-100 shadow-sm">
                                        <label class="form-label small fw-semibold d-flex align-items-center justify-content-between gap-1 mb-1">
                                            <span class="d-flex align-items-center gap-1">
                                                <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background-color:<?php echo e($clr['color_code']); ?>; border:1px solid #ccc;"></span>
                                                <?php echo e($clr['color_name']); ?> Photos:
                                            </span>
                                            <span class="badge bg-light text-muted border" style="font-size: 10px;">Color Swatch</span>
                                        </label>
                                        <input type="file" class="form-control form-control-sm" name="color_images[<?php echo $clr['id']; ?>][]" accept="image/*" multiple <?php echo !$is_visible ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Show and manage existing uploaded images in Edit mode -->
                <?php if ($action === 'edit' && !empty($product_images_list)): ?>
                    <div class="col-12">
                        <div class="card border p-3 rounded bg-white">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label class="form-label fw-bold small mb-0"><i class="bi bi-grid me-1 text-rose"></i> Existing Gallery & Color Tag Management</label>
                                <span class="badge bg-light text-muted border"><?php echo count($product_images_list); ?> Photos Uploaded</span>
                            </div>
                            <div class="row g-3">
                                <?php foreach ($product_images_list as $img): ?>
                                    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                                        <div class="border rounded p-2 text-center bg-light position-relative">
                                            <div class="position-relative mb-2" style="height: 140px; overflow: hidden; border-radius: 6px;">
                                                <img src="../uploads/<?php echo e($img['image_path']); ?>" alt="Gallery" style="height: 100%; width: 100%; object-fit: cover;">
                                                <?php if ($img['is_featured']): ?>
                                                    <span class="badge bg-success position-absolute top-0 start-0 m-1 shadow-sm"><i class="bi bi-star-fill me-1"></i>Primary</span>
                                                <?php endif; ?>
                                                <a href="products.php?delete_image_id=<?php echo $img['id']; ?>" class="btn btn-sm btn-danger py-0 px-1 position-absolute top-0 end-0 m-1 shadow-sm" title="Delete image" onclick="return confirm('Delete this image permanently?');">
                                                    <i class="bi bi-trash-fill"></i>
                                                </a>
                                            </div>
                                            
                                            <!-- Color tag selector -->
                                            <div class="text-start">
                                                <label class="small text-muted fw-semibold mb-1" style="font-size: 11px;">Assigned Color:</label>
                                                <select name="image_color[<?php echo $img['id']; ?>]" class="form-select form-select-sm mb-2" style="font-size: 12px;">
                                                    <option value="" <?php echo empty($img['color_id']) ? 'selected' : ''; ?>>-- All Colors / General --</option>
                                                    <?php foreach ($all_colors as $c): ?>
                                                        <option value="<?php echo $c['id']; ?>" <?php echo ($img['color_id'] == $c['id']) ? 'selected' : ''; ?>>
                                                            <?php echo e($c['color_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                
                                                <?php if (!$img['is_featured']): ?>
                                                    <a href="products.php?set_featured_image_id=<?php echo $img['id']; ?>&product_id=<?php echo $product['id']; ?>" class="btn btn-outline-secondary btn-sm w-100 py-0" style="font-size: 11px;">
                                                        <i class="bi bi-star me-1"></i> Set as Primary
                                                    </a>
                                                <?php else: ?>
                                                    <div class="text-success small fw-semibold text-center" style="font-size: 11px;">
                                                        <i class="bi bi-check-circle-fill me-1"></i> Main Featured Image
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Flags -->
                <div class="col-12 my-3">
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" <?php echo (isset($product['is_featured']) && $product['is_featured'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="is_featured">Featured Product</label>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_new_arrival" name="is_new_arrival" <?php echo (isset($product['is_new_arrival']) && $product['is_new_arrival'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="is_new_arrival">New Arrival</label>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_best_seller" name="is_best_seller" <?php echo (isset($product['is_best_seller']) && $product['is_best_seller'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="is_best_seller">Best Seller</label>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="status" name="status" <?php echo (!isset($product['status']) || $product['status'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="status">Active & Published</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SEO Details -->
                <div class="col-12 pt-3 border-top">
                    <h6 class="fw-bold mb-3"><i class="bi bi-search me-2"></i> SEO Configurations (Optional)</h6>
                </div>
                <div class="col-12 col-md-6">
                    <label for="seo_title" class="form-label small">SEO Browser Title</label>
                    <input type="text" class="form-control" id="seo_title" name="seo_title" value="<?php echo e($product['seo_title'] ?? ''); ?>" placeholder="e.g. Elegance Lace Bra | Brand Name">
                </div>
                <div class="col-12 col-md-6">
                    <label for="slug_override" class="form-label small">URL Slug</label>
                    <input type="text" class="form-control" id="slug" name="slug" value="<?php echo e($product['slug'] ?? ''); ?>" placeholder="e.g. elegance-lace-underwire-bra">
                </div>
                <div class="col-12">
                    <label for="seo_description" class="form-label small">SEO Search Meta Description</label>
                    <textarea class="form-control" id="seo_description" name="seo_description" rows="2" placeholder="Brief summary of product shown in search results."><?php echo e($product['seo_description'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex gap-2 align-items-center">
                    <button type="submit" name="<?php echo $action === 'add' ? 'add_product' : 'edit_product'; ?>" class="btn btn-brand">Save Product</button>
                    <a href="products.php" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                    <button type="button" id="btn_clear_draft" class="btn btn-outline-danger btn-sm rounded-pill px-3" style="display:none;" title="Clear unsaved draft">
                        <i class="bi bi-trash3 me-1"></i> Clear Draft
                    </button>
                </div>
                <?php if ($action === 'edit'): ?>
                    <a href="products.php?delete_product_id=<?php echo $product['id']; ?>" 
                       class="btn btn-outline-danger rounded-pill px-3" 
                       onclick="return confirm('Are you sure you want to permanently delete this product? All unpurchased stock records, images and data for this product will be removed. This cannot be undone.');">
                        <i class="bi bi-trash me-1"></i> Delete Product
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const colorCheckboxes = document.querySelectorAll('.color-selection-checkbox');
        const sizeCheckboxes = document.querySelectorAll('input[name="selected_sizes[]"]');
        const fabricCheckboxes = document.querySelectorAll('input[name="selected_fabrics[]"]');
        const colorPhotoBoxes = document.querySelectorAll('.color-photo-box');
        const noColorsMsg = document.getElementById('no_colors_selected_msg');
        const badge = document.getElementById('color_photos_badge');
        const draftKey = 'veesta_product_draft_<?php echo $action; ?><?php echo ($action === "edit" && !empty($product["id"])) ? "_" . $product["id"] : ""; ?>';

        // 1. DRAFT STORAGE HANDLERS
        function saveDraft() {
            if (!form) return;
            const draft = {
                name: document.getElementById('name')?.value || '',
                product_type: document.getElementById('product_type')?.value || 'single',
                sku: document.getElementById('sku')?.value || '',
                category_id: document.getElementById('category_id')?.value || '',
                regular_price: document.getElementById('regular_price')?.value || '',
                offer_price: document.getElementById('offer_price')?.value || '',
                gst_percentage: document.getElementById('gst_percentage')?.value || '',
                initial_stock: document.getElementById('initial_stock')?.value || '',
                fit_type: document.getElementById('fit_type')?.value || '',
                fabric_details: document.getElementById('fabric_details')?.value || '',
                description: document.getElementById('description')?.value || '',
                care_instructions: document.getElementById('care_instructions')?.value || '',
                is_featured: document.getElementById('is_featured')?.checked || false,
                is_new_arrival: document.getElementById('is_new_arrival')?.checked || false,
                is_best_seller: document.getElementById('is_best_seller')?.checked || false,
                status: document.getElementById('status')?.checked || true,
                seo_title: document.getElementById('seo_title')?.value || '',
                slug: document.getElementById('slug')?.value || '',
                seo_description: document.getElementById('seo_description')?.value || '',
                selected_sizes: Array.from(sizeCheckboxes).filter(cb => cb.checked).map(cb => cb.value),
                selected_colors: Array.from(colorCheckboxes).filter(cb => cb.checked).map(cb => cb.value),
                selected_fabrics: Array.from(fabricCheckboxes).filter(cb => cb.checked).map(cb => cb.value)
            };
            try {
                sessionStorage.setItem(draftKey, JSON.stringify(draft));
                updateClearDraftButton(true);
            } catch (e) {}
        }

        function restoreDraft() {
            <?php if ($action === 'edit'): ?>
            // On product edit forms, server-rendered database state is authoritative
            return;
            <?php endif; ?>
            try {
                const raw = sessionStorage.getItem(draftKey);
                if (!raw) return;
                const draft = JSON.parse(raw);
                if (!draft) return;

                const setIfEmpty = (id, val) => {
                    const el = document.getElementById(id);
                    if (el && (!el.value || el.value.trim() === '')) {
                        if (val !== undefined && val !== null) el.value = val;
                    }
                };

                setIfEmpty('name', draft.name);
                setIfEmpty('sku', draft.sku);
                setIfEmpty('regular_price', draft.regular_price);
                setIfEmpty('offer_price', draft.offer_price);
                setIfEmpty('gst_percentage', draft.gst_percentage);
                setIfEmpty('fit_type', draft.fit_type);
                setIfEmpty('fabric_details', draft.fabric_details);
                setIfEmpty('description', draft.description);
                setIfEmpty('care_instructions', draft.care_instructions);
                setIfEmpty('seo_title', draft.seo_title);
                setIfEmpty('slug', draft.slug);
                setIfEmpty('seo_description', draft.seo_description);

                if (draft.category_id && document.getElementById('category_id') && !document.getElementById('category_id').value) {
                    document.getElementById('category_id').value = draft.category_id;
                }
                if (draft.product_type && document.getElementById('product_type')) {
                    document.getElementById('product_type').value = draft.product_type;
                }
                if (draft.initial_stock && document.getElementById('initial_stock') && document.getElementById('initial_stock').value === '50') {
                    document.getElementById('initial_stock').value = draft.initial_stock;
                }

                // Restore sizes
                if (Array.isArray(draft.selected_sizes) && draft.selected_sizes.length > 0) {
                    sizeCheckboxes.forEach(cb => {
                        if (draft.selected_sizes.includes(cb.value)) {
                            cb.checked = true;
                            const parentLabel = cb.closest('label');
                            if (parentLabel) parentLabel.classList.add('border-primary', 'bg-white', 'shadow-sm', 'fw-bold');
                        }
                    });
                }

                // Restore colors
                if (Array.isArray(draft.selected_colors) && draft.selected_colors.length > 0) {
                    colorCheckboxes.forEach(cb => {
                        if (draft.selected_colors.includes(cb.value)) {
                            cb.checked = true;
                            const parentLabel = cb.closest('label');
                            if (parentLabel) parentLabel.classList.add('border-primary', 'bg-white', 'shadow-sm', 'fw-bold');
                        }
                    });
                }

                // Restore fabrics
                if (Array.isArray(draft.selected_fabrics) && draft.selected_fabrics.length > 0) {
                    fabricCheckboxes.forEach(cb => {
                        if (draft.selected_fabrics.includes(cb.value)) {
                            cb.checked = true;
                        }
                    });
                }

                if (draft.is_featured !== undefined && document.getElementById('is_featured')) {
                    document.getElementById('is_featured').checked = draft.is_featured;
                }
                if (draft.is_new_arrival !== undefined && document.getElementById('is_new_arrival')) {
                    document.getElementById('is_new_arrival').checked = draft.is_new_arrival;
                }
                if (draft.is_best_seller !== undefined && document.getElementById('is_best_seller')) {
                    document.getElementById('is_best_seller').checked = draft.is_best_seller;
                }
                if (draft.status !== undefined && document.getElementById('status')) {
                    document.getElementById('status').checked = draft.status;
                }

                updateClearDraftButton(true);
            } catch (e) {}
        }

        function updateClearDraftButton(hasDraft) {
            const btn = document.getElementById('btn_clear_draft');
            if (btn) {
                btn.style.display = hasDraft ? '' : 'none';
            }
        }

        const clearDraftBtn = document.getElementById('btn_clear_draft');
        if (clearDraftBtn) {
            clearDraftBtn.addEventListener('click', function() {
                if (confirm('Discard all saved form entries and start fresh?')) {
                    sessionStorage.removeItem(draftKey);
                    window.location.href = 'products.php?action=<?php echo $action; ?>';
                }
            });
        }

        // Attach real-time draft saving
        if (form) {
            form.addEventListener('input', saveDraft);
            form.addEventListener('change', saveDraft);
        }

        // 2. COLOR SELECTION SYNC
        function syncColorPhotoBoxes() {
            if (!colorCheckboxes.length) return;

            const checkedIds = Array.from(colorCheckboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.value);

            let visibleCount = 0;
            colorPhotoBoxes.forEach(box => {
                const colorId = box.getAttribute('data-color-id');
                const fileInput = box.querySelector('input[type="file"]');
                if (checkedIds.includes(colorId)) {
                    box.style.display = '';
                    if (fileInput) {
                        fileInput.disabled = false;
                    }
                    visibleCount++;
                } else {
                    box.style.display = 'none';
                    if (fileInput) {
                        fileInput.value = '';
                        fileInput.disabled = true;
                    }
                }
            });

            if (noColorsMsg) {
                noColorsMsg.style.display = (visibleCount === 0) ? '' : 'none';
            }

            if (badge) {
                badge.textContent = visibleCount === 1 ? '1 Color Selected' : visibleCount + ' Colors Selected';
                if (visibleCount > 0) {
                    badge.className = 'badge bg-primary text-white border-0 small';
                } else {
                    badge.className = 'badge bg-white text-secondary border small';
                }
            }
        }

        // Size checkboxes toggle feedback & counter
        function updateMatrixSummary() {
            const checkedSizes = Array.from(sizeCheckboxes).filter(cb => cb.checked);
            const checkedColors = Array.from(colorCheckboxes).filter(cb => cb.checked);
            const numSizes = checkedSizes.length;
            const numColors = checkedColors.length;

            const sizeCountBadge = document.getElementById('sizes_count_badge');
            if (sizeCountBadge) {
                sizeCountBadge.textContent = numSizes === 1 ? '1 Selected' : numSizes + ' Selected';
                sizeCountBadge.className = numSizes > 0 ? 'badge bg-primary text-white ms-1' : 'badge bg-light text-secondary border ms-1';
            }

            const colorCountBadge = document.getElementById('colors_count_badge');
            if (colorCountBadge) {
                colorCountBadge.textContent = numColors === 1 ? '1 Selected' : numColors + ' Selected';
                colorCountBadge.className = numColors > 0 ? 'badge bg-primary text-white ms-1' : 'badge bg-light text-secondary border ms-1';
            }

            const matrixBadge = document.getElementById('variant_matrix_summary_badge');
            if (matrixBadge) {
                if (numSizes > 0 && numColors > 0) {
                    const totalVariants = numSizes * numColors;
                    matrixBadge.textContent = `${numSizes} Sizes × ${numColors} Colors = ${totalVariants} Variant SKUs`;
                    matrixBadge.className = 'badge bg-primary text-white border-0 py-1 px-2 small shadow-sm';
                } else if (numSizes > 0) {
                    matrixBadge.textContent = `${numSizes} Sizes selected (Default color)`;
                    matrixBadge.className = 'badge bg-info text-dark border-0 py-1 px-2 small shadow-sm';
                } else if (numColors > 0) {
                    matrixBadge.textContent = `${numColors} Colors selected (Default size)`;
                    matrixBadge.className = 'badge bg-info text-dark border-0 py-1 px-2 small shadow-sm';
                } else {
                    matrixBadge.textContent = 'Default SKU will be created';
                    matrixBadge.className = 'badge bg-light text-dark border py-1 px-2 small shadow-sm';
                }
            }
        }

        sizeCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                const parentLabel = this.closest('label');
                if (parentLabel) {
                    if (this.checked) {
                        parentLabel.classList.add('border-primary', 'bg-white', 'shadow-sm', 'fw-bold', 'active', 'text-primary');
                    } else {
                        parentLabel.classList.remove('border-primary', 'bg-white', 'shadow-sm', 'fw-bold', 'active', 'text-primary');
                    }
                }
                updateMatrixSummary();
                saveDraft();
            });
        });

        if (colorCheckboxes.length > 0) {
            colorCheckboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    const parentLabel = this.closest('label');
                    if (parentLabel) {
                        if (this.checked) {
                            parentLabel.classList.add('border-primary', 'bg-white', 'shadow-sm', 'fw-bold', 'active', 'text-primary');
                        } else {
                            parentLabel.classList.remove('border-primary', 'bg-white', 'shadow-sm', 'fw-bold', 'active', 'text-primary');
                        }
                    }
                    syncColorPhotoBoxes();
                    updateMatrixSummary();
                    saveDraft();
                });
            });
        }

        // ==========================================
        // 3. SIZES LIVE SEARCH & CATEGORY FILTERING
        // ==========================================
        const sizeSearchInput = document.getElementById('size_search_input');
        const sizeSearchClear = document.getElementById('size_search_clear');
        const sizeCatTabs = document.querySelectorAll('.size-cat-tab');
        const sizeGroupWrappers = document.querySelectorAll('.size-group-wrapper');
        const sizeChoicePills = document.querySelectorAll('.size-choice-pill');
        const noSizesMsg = document.getElementById('no_sizes_match_msg');
        let currentSizeCat = 'all';

        function filterSizes() {
            const query = (sizeSearchInput ? sizeSearchInput.value.trim().toLowerCase() : '');
            if (sizeSearchClear) {
                sizeSearchClear.style.display = query ? '' : 'none';
            }

            let totalVisible = 0;

            sizeGroupWrappers.forEach(group => {
                const groupCat = group.getAttribute('data-cat-type') || '';
                const catMatches = (currentSizeCat === 'all' || currentSizeCat.toLowerCase() === groupCat.toLowerCase());

                if (!catMatches) {
                    group.style.setProperty('display', 'none', 'important');
                    group.classList.add('d-none');
                    return;
                }

                let groupVisibleCount = 0;
                const pillsInGroup = group.querySelectorAll('.size-choice-pill');
                pillsInGroup.forEach(pill => {
                    const sCode = (pill.getAttribute('data-size-code') || '').toLowerCase();
                    const sName = (pill.getAttribute('data-size-name') || '').toLowerCase();
                    const pillText = (pill.textContent || '').toLowerCase();
                    const textMatches = !query || sCode.includes(query) || sName.includes(query) || pillText.includes(query);

                    if (textMatches) {
                        pill.style.setProperty('display', 'inline-block', 'important');
                        pill.classList.remove('d-none');
                        groupVisibleCount++;
                        totalVisible++;
                    } else {
                        pill.style.setProperty('display', 'none', 'important');
                        pill.classList.add('d-none');
                    }
                });

                if (groupVisibleCount > 0) {
                    group.style.removeProperty('display');
                    group.classList.remove('d-none');
                } else {
                    group.style.setProperty('display', 'none', 'important');
                    group.classList.add('d-none');
                }
            });

            if (noSizesMsg) {
                if (totalVisible === 0) {
                    noSizesMsg.style.setProperty('display', 'block', 'important');
                    noSizesMsg.classList.remove('d-none');
                } else {
                    noSizesMsg.style.setProperty('display', 'none', 'important');
                    noSizesMsg.classList.add('d-none');
                }
            }
        }

        if (sizeSearchInput) {
            sizeSearchInput.addEventListener('input', filterSizes);
            sizeSearchInput.addEventListener('keyup', filterSizes);
            sizeSearchInput.addEventListener('change', filterSizes);
        }
        if (sizeSearchClear) {
            sizeSearchClear.addEventListener('click', function() {
                sizeSearchInput.value = '';
                filterSizes();
                sizeSearchInput.focus();
            });
        }

        sizeCatTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                sizeCatTabs.forEach(t => {
                    t.classList.remove('btn-primary', 'active');
                    t.classList.add('btn-outline-secondary');
                });
                this.classList.add('btn-primary', 'active');
                this.classList.remove('btn-outline-secondary');
                currentSizeCat = this.getAttribute('data-cat') || 'all';
                filterSizes();
            });
        });

        // Auto-switch size category tab when main Category dropdown changes
        const mainCategorySelect = document.getElementById('category_id');
        if (mainCategorySelect) {
            mainCategorySelect.addEventListener('change', function() {
                const selectedText = this.options[this.selectedIndex]?.text?.trim() || '';
                if (!selectedText) return;

                sizeCatTabs.forEach(tab => {
                    const catVal = tab.getAttribute('data-cat') || '';
                    if (catVal !== 'all' && (selectedText.toLowerCase().includes(catVal.toLowerCase()) || catVal.toLowerCase().includes(selectedText.toLowerCase()))) {
                        tab.click();
                    }
                });
            });
        }

        // Quick group & batch buttons for Sizes
        const btnSelectAllSizes = document.getElementById('btn_select_all_sizes');
        if (btnSelectAllSizes) {
            btnSelectAllSizes.addEventListener('click', function() {
                sizeChoicePills.forEach(pill => {
                    const isVisible = pill.style.display !== 'none' && !pill.classList.contains('d-none') && pill.closest('.size-group-wrapper')?.style.display !== 'none';
                    if (isVisible) {
                        const cb = pill.querySelector('input[type="checkbox"]');
                        if (cb && !cb.checked) {
                            cb.checked = true;
                            pill.classList.add('border-primary', 'bg-white', 'shadow-sm', 'fw-bold', 'active', 'text-primary');
                        }
                    }
                });
                updateMatrixSummary();
                saveDraft();
            });
        }

        const btnClearSizes = document.getElementById('btn_clear_sizes');
        if (btnClearSizes) {
            btnClearSizes.addEventListener('click', function() {
                sizeChoicePills.forEach(pill => {
                    const cb = pill.querySelector('input[type="checkbox"]');
                    if (cb && cb.checked) {
                        cb.checked = false;
                        pill.classList.remove('border-primary', 'bg-white', 'shadow-sm', 'fw-bold', 'active', 'text-primary');
                    }
                });
                updateMatrixSummary();
                saveDraft();
            });
        }

        document.querySelectorAll('.group-select-link').forEach(link => {
            link.addEventListener('click', function() {
                const groupCat = this.getAttribute('data-cat-type');
                const group = document.querySelector(`.size-group-wrapper[data-cat-type="${groupCat}"]`);
                if (!group) return;

                const pills = group.querySelectorAll('.size-choice-pill input[type="checkbox"]');
                const anyUnchecked = Array.from(pills).some(cb => !cb.checked);

                pills.forEach(cb => {
                    cb.checked = anyUnchecked;
                    const parentLabel = cb.closest('label');
                    if (parentLabel) {
                        if (anyUnchecked) {
                            parentLabel.classList.add('border-primary', 'bg-white', 'shadow-sm', 'fw-bold', 'active', 'text-primary');
                        } else {
                            parentLabel.classList.remove('border-primary', 'bg-white', 'shadow-sm', 'fw-bold', 'active', 'text-primary');
                        }
                    }
                });
                updateMatrixSummary();
                saveDraft();
            });
        });

        // ==========================================
        // 4. COLORS LIVE SEARCH & BATCH SELECTORS
        // ==========================================
        const colorSearchInput = document.getElementById('color_search_input');
        const colorSearchClear = document.getElementById('color_search_clear');
        const colorChoicePills = document.querySelectorAll('.color-choice-pill');
        const noColorsMatchMsg = document.getElementById('no_colors_match_msg');

        function filterColors() {
            const query = (colorSearchInput ? colorSearchInput.value.trim().toLowerCase() : '');
            if (colorSearchClear) {
                colorSearchClear.style.display = query ? '' : 'none';
            }

            let visibleCount = 0;
            colorChoicePills.forEach(pill => {
                const cName = (pill.getAttribute('data-color-name') || '').toLowerCase();
                const pillText = (pill.textContent || '').toLowerCase();
                const matches = !query || cName.includes(query) || pillText.includes(query);

                if (matches) {
                    pill.style.setProperty('display', 'inline-flex', 'important');
                    pill.classList.remove('d-none');
                    visibleCount++;
                } else {
                    pill.style.setProperty('display', 'none', 'important');
                    pill.classList.add('d-none');
                }
            });

            if (noColorsMatchMsg) {
                if (visibleCount === 0) {
                    noColorsMatchMsg.style.setProperty('display', 'block', 'important');
                    noColorsMatchMsg.classList.remove('d-none');
                } else {
                    noColorsMatchMsg.style.setProperty('display', 'none', 'important');
                    noColorsMatchMsg.classList.add('d-none');
                }
            }
        }

        if (colorSearchInput) {
            colorSearchInput.addEventListener('input', filterColors);
            colorSearchInput.addEventListener('keyup', filterColors);
            colorSearchInput.addEventListener('change', filterColors);
        }
        if (colorSearchClear) {
            colorSearchClear.addEventListener('click', function() {
                colorSearchInput.value = '';
                filterColors();
                colorSearchInput.focus();
            });
        }

        const btnSelectAllColors = document.getElementById('btn_select_all_colors');
        if (btnSelectAllColors) {
            btnSelectAllColors.addEventListener('click', function() {
                colorChoicePills.forEach(pill => {
                    const isVisible = pill.style.display !== 'none' && !pill.classList.contains('d-none');
                    if (isVisible) {
                        const cb = pill.querySelector('input[type="checkbox"]');
                        if (cb && !cb.checked) {
                            cb.checked = true;
                            pill.classList.add('border-primary', 'bg-white', 'shadow-sm', 'fw-bold', 'active', 'text-primary');
                        }
                    }
                });
                syncColorPhotoBoxes();
                updateMatrixSummary();
                saveDraft();
            });
        }

        const btnClearColors = document.getElementById('btn_clear_colors');
        if (btnClearColors) {
            btnClearColors.addEventListener('click', function() {
                colorChoicePills.forEach(pill => {
                    const cb = pill.querySelector('input[type="checkbox"]');
                    if (cb && cb.checked) {
                        cb.checked = false;
                        pill.classList.remove('border-primary', 'bg-white', 'shadow-sm', 'fw-bold', 'active', 'text-primary');
                    }
                });
                syncColorPhotoBoxes();
                updateMatrixSummary();
                saveDraft();
            });
        }

        // ==========================================
        // 5. PRE-FLIGHT FILE SIZE VALIDATION BEFORE SUBMIT
        // ==========================================
        if (form) {
            form.addEventListener('submit', function(e) {
                const MAX_SINGLE_FILE = 50 * 1024 * 1024;  // 50MB
                const MAX_TOTAL_SIZE = 250 * 1024 * 1024; // 250MB total payload safe limit

                let totalBytes = 0;
                const fileInputs = form.querySelectorAll('input[type="file"]:not([disabled])');

                for (const input of fileInputs) {
                    if (input.files && input.files.length > 0) {
                        for (let i = 0; i < input.files.length; i++) {
                            const file = input.files[i];
                            if (file.size > MAX_SINGLE_FILE) {
                                e.preventDefault();
                                const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
                                alert(`Image "${file.name}" is ${sizeMb} MB, which exceeds the 50MB limit per photo. Please choose a smaller or compressed image.`);
                                return false;
                            }
                            totalBytes += file.size;
                        }
                    }
                }

                if (totalBytes > MAX_TOTAL_SIZE) {
                    e.preventDefault();
                    const totalMb = (totalBytes / (1024 * 1024)).toFixed(1);
                    alert(`The total size of your uploaded photos is ${totalMb} MB, which exceeds the safe upload limit (250MB). Please upload compressed images or fewer images at once.`);
                    return false;
                }

                // If form is valid and submitting to server, remove the draft so it won't persist after save
                try {
                    sessionStorage.removeItem(draftKey);
                } catch (err) {}
            });
        }

        // Initial restore, sync & summary calculation
        restoreDraft();
        syncColorPhotoBoxes();
        updateMatrixSummary();
    });
    </script>

<?php else:
    // List Products with Search & Filter
    $search_query = trim($_GET['search'] ?? $_GET['q'] ?? '');
    $filter_category = (int)($_GET['category_id'] ?? 0);
    $filter_stock = trim($_GET['stock_filter'] ?? 'all');
    $filter_type = trim($_GET['product_type'] ?? 'all');
    $filter_status = trim($_GET['status_filter'] ?? 'all');

    try {
        $where = ["1=1"];
        $params = [];

        if ($search_query !== '') {
            $sp = '%' . $search_query . '%';
            $where[] = "(
                p.name LIKE ? 
                OR p.sku LIKE ? 
                OR c.name LIKE ? 
                OR p.description LIKE ? 
                OR p.fabric_details LIKE ? 
                OR EXISTS (SELECT 1 FROM product_variants pv WHERE pv.product_id = p.id AND pv.sku LIKE ?)
            )";
            $params[] = $sp;
            $params[] = $sp;
            $params[] = $sp;
            $params[] = $sp;
            $params[] = $sp;
            $params[] = $sp;
        }

        if ($filter_category > 0) {
            $where[] = "p.category_id = ?";
            $params[] = $filter_category;
        }

        if (in_array($filter_type, ['single', 'combo'])) {
            $where[] = "p.product_type = ?";
            $params[] = $filter_type;
        }

        if ($filter_status === 'active') {
            $where[] = "p.status = 1";
        } elseif ($filter_status === 'inactive') {
            $where[] = "p.status = 0";
        }

        if ($filter_stock === 'out_of_stock') {
            $where[] = "(p.product_type = 'single' AND COALESCE((SELECT SUM(stock) FROM product_variants WHERE product_id = p.id), 0) <= 0)";
        } elseif ($filter_stock === 'low_stock') {
            $where[] = "(p.product_type = 'single' AND (
                EXISTS (SELECT 1 FROM product_variants pv_ls WHERE pv_ls.product_id = p.id AND pv_ls.stock <= 5) 
                OR COALESCE((SELECT SUM(stock) FROM product_variants WHERE product_id = p.id), 0) <= 5
            ))";
        } elseif ($filter_stock === 'in_stock') {
            $where[] = "(p.product_type = 'combo' OR COALESCE((SELECT SUM(stock) FROM product_variants WHERE product_id = p.id), 0) > 5)";
        }

        $where_sql = implode(' AND ', $where);

        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name, 
            (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_featured DESC LIMIT 1) as main_image,
            (SELECT SUM(stock) FROM product_variants WHERE product_id = p.id) as total_stock 
            FROM products p 
            JOIN categories c ON p.category_id = c.id 
            WHERE {$where_sql}
            ORDER BY p.id DESC
        ");
        $stmt->execute($params);
        $products_list = $stmt->fetchAll();

        // Fetch categories for filter dropdown
        $filter_categories = $pdo->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name ASC")->fetchAll();
    } catch (PDOException $e) {
        $products_list = [];
        $filter_categories = [];
        $error = 'Error listing products: ' . $e->getMessage();
    }
?>

    <script>
    (function() {
        try {
            // Automatically clear all product draft items when navigating to or landing on the products catalog view
            const keysToRemove = [];
            for (let i = 0; i < sessionStorage.length; i++) {
                const key = sessionStorage.key(i);
                if (key && key.startsWith('veesta_product_draft_')) {
                    keysToRemove.push(key);
                }
            }
            keysToRemove.forEach(function(k) { sessionStorage.removeItem(k); });
        } catch(e) {}
    })();
    </script>

    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h5 class="fw-bold mb-0">Products Catalog</h5>
                <small class="text-muted">Showing <?php echo count($products_list); ?> products</small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="bulk-upload.php" class="btn btn-outline-dark">
                    <i class="bi bi-cloud-arrow-up me-1"></i> Bulk Import / Export
                </a>
                <a href="products.php?action=add" class="btn btn-brand">
                    <i class="bi bi-plus-circle me-1"></i> Add Product
                </a>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <form method="GET" action="products.php" class="row g-2 mb-4 p-3 bg-light rounded-3 border">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, SKU, details..." value="<?php echo e($search_query); ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="category_id" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($filter_categories as $fcat): ?>
                        <option value="<?php echo $fcat['id']; ?>" <?php echo $filter_category == $fcat['id'] ? 'selected' : ''; ?>>
                            <?php echo e($fcat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="stock_filter" class="form-select">
                    <option value="all" <?php echo $filter_stock === 'all' ? 'selected' : ''; ?>>All Stock</option>
                    <option value="in_stock" <?php echo $filter_stock === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                    <option value="low_stock" <?php echo $filter_stock === 'low_stock' ? 'selected' : ''; ?>>Low Stock (≤5)</option>
                    <option value="out_of_stock" <?php echo $filter_stock === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock (0)</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="product_type" class="form-select">
                    <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="single" <?php echo $filter_type === 'single' ? 'selected' : ''; ?>>Single Products</option>
                    <option value="combo" <?php echo $filter_type === 'combo' ? 'selected' : ''; ?>>Combo Sets</option>
                </select>
            </div>
            <div class="col-6 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-dark w-100" title="Filter"><i class="bi bi-funnel"></i></button>
                <?php if ($search_query !== '' || $filter_category > 0 || $filter_stock !== 'all' || $filter_type !== 'all' || $filter_status !== 'all'): ?>
                    <a href="products.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 70px;">Image</th>
                        <th>Product Details</th>
                        <th>Category</th>
                        <th>Price (Regular / Offer)</th>
                        <th>Stock</th>
                        <th>Badges</th>
                        <th>Status</th>
                        <th class="text-end" style="width: 170px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products_list)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No products in catalog yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products_list as $prod): ?>
                            <tr>
                                <td>
                                    <?php if ($prod['main_image']): ?>
                                        <img src="../uploads/<?php echo e($prod['main_image']); ?>" alt="product" class="img-thumbnail" style="height: 55px; width: 55px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light text-muted d-flex align-items-center justify-content-center border" style="width: 55px; height: 55px; border-radius: 4px;">
                                            <i class="bi bi-image text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold text-dark"><?php echo e($prod['name']); ?></span>
                                        <?php if (($prod['product_type'] ?? '') === 'combo'): ?>
                                            <span class="badge bg-purple-subtle text-purple border" style="font-size:10px;"><i class="bi bi-stars"></i> Combo Set</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">SKU: <?php echo e($prod['sku']); ?></small>
                                </td>
                                <td><?php echo e($prod['category_name']); ?></td>
                                <td>
                                    <span class="fw-bold text-rose"><?php echo format_price($prod['offer_price'] ?? $prod['regular_price']); ?></span>
                                    <?php if ($prod['offer_price']): ?>
                                        <br><span class="text-muted small text-decoration-line-through"><?php echo format_price($prod['regular_price']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($prod['product_type'] ?? '') === 'combo'): ?>
                                        <span class="badge bg-light text-dark border">Dynamic Stock</span>
                                    <?php else: ?>
                                        <?php $t_stock = (int)$prod['total_stock']; ?>
                                        <?php if ($t_stock > 5): ?>
                                            <span class="badge bg-success"><?php echo $t_stock; ?> units</span>
                                        <?php elseif ($t_stock > 0): ?>
                                            <span class="badge bg-warning text-dark">Low stock (<?php echo $t_stock; ?>)</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Out of stock</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php if ($prod['is_featured']): ?><span class="badge bg-info" style="font-size:10px;">Featured</span><?php endif; ?>
                                        <?php if ($prod['is_new_arrival']): ?><span class="badge bg-primary" style="font-size:10px;">New</span><?php endif; ?>
                                        <?php if ($prod['is_best_seller']): ?><span class="badge bg-dark" style="font-size:10px;">Best</span><?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-check form-switch p-0 m-0">
                                        <input class="form-check-input status-toggle ms-0" type="checkbox" role="switch" 
                                               data-table="products" data-id="<?php echo $prod['id']; ?>" <?php echo $prod['status'] == 1 ? 'checked' : ''; ?>>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <?php if (($prod['product_type'] ?? '') === 'combo'): ?>
                                            <a href="combo_components.php?combo_id=<?php echo $prod['id']; ?>" class="btn btn-sm btn-outline-purple p-1 px-2" title="Manage Combo Components">
                                                <i class="bi bi-sliders"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="variants.php?product_id=<?php echo $prod['id']; ?>" class="btn btn-sm btn-outline-primary p-1 px-2" title="Manage Variants Matrix">
                                                <i class="bi bi-grid-3x3-gap"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="products.php?action=edit&id=<?php echo $prod['id']; ?>" class="btn btn-sm btn-outline-secondary p-1 px-2" title="Edit Product Info">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <a href="products.php?delete_product_id=<?php echo $prod['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger p-1 px-2" 
                                           title="Delete Product" 
                                           onclick="return confirm('Are you sure you want to permanently delete \'<?php echo addslashes(e($prod['name'])); ?>\'? This cannot be undone.');">
                                            <i class="bi bi-trash"></i>
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

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
