<?php
// admin/process-bulk-upload.php
// Public / Admin AJAX & POST Controller for Bulk Product Importing

ob_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

// Guarantee admin login & product management permission
require_admin_permission('products');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response('error', 'Invalid request method.');
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    json_response('error', 'Security session expired. Please refresh the page and try again.');
}

// 1. VERIFY CSV FILE UPLOAD
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $err_code = $_FILES['csv_file']['error'] ?? 'Unknown';
    json_response('error', 'Please choose a valid CSV or Excel spreadsheet file. (Upload error code: ' . $err_code . ')');
}

$csv_tmp_path = $_FILES['csv_file']['tmp_name'];
$csv_filename = $_FILES['csv_file']['name'];
$csv_ext = strtolower(pathinfo($csv_filename, PATHINFO_EXTENSION));

if (!in_array($csv_ext, ['csv', 'txt'])) {
    json_response('error', 'Invalid file type. Please upload a standard .csv file.');
}

// Configuration options from form
$import_mode = $_POST['import_mode'] ?? 'create_and_update'; // 'create_and_update' or 'create_only'
$auto_create_attributes = isset($_POST['auto_create_attributes']) && ($_POST['auto_create_attributes'] == '1' || $_POST['auto_create_attributes'] === 'on');

// 2. HANDLE OPTIONAL ZIP ARCHIVE OF IMAGES
$uploads_dir = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
}

$extracted_images = [];
if (isset($_FILES['zip_file']) && $_FILES['zip_file']['error'] === UPLOAD_ERR_OK) {
    $zip_tmp = $_FILES['zip_file']['tmp_name'];
    $zip_name = $_FILES['zip_file']['name'];
    $zip_ext = strtolower(pathinfo($zip_name, PATHINFO_EXTENSION));

    if ($zip_ext === 'zip' && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zip_tmp) === true) {
            $allowed_img_exts = ['jpg', 'jpeg', 'png', 'webp'];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry_name = $zip->getNameIndex($i);
                // Skip directories or hidden mac __MACOSX files
                if (substr($entry_name, -1) === '/' || strpos($entry_name, '__MACOSX') !== false) {
                    continue;
                }
                $basename = basename($entry_name);
                $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
                if (in_array($ext, $allowed_img_exts)) {
                    $target_file = $uploads_dir . '/' . $basename;
                    $stream = $zip->getStream($entry_name);
                    if ($stream) {
                        file_put_contents($target_file, stream_get_contents($stream));
                        fclose($stream);
                        $extracted_images[] = $basename;
                    }
                }
            }
            $zip->close();
        }
    }
}

// 3. PARSE CSV FILE
$handle = fopen($csv_tmp_path, 'r');
if (!$handle) {
    json_response('error', 'Could not open the uploaded CSV file for reading.');
}

// Read first line to detect delimiter and strip BOM
$first_line = fgets($handle);
if ($first_line === false) {
    fclose($handle);
    json_response('error', 'The uploaded CSV file is completely empty.');
}

// Remove UTF-8 BOM if present
$first_line = preg_replace('/^\xEF\xBB\xBF/', '', $first_line);

// Detect delimiter (comma, semicolon, tab)
$delimiters = [',', ';', "\t"];
$delimiter = ',';
$max_count = 0;
foreach ($delimiters as $d) {
    $count = count(str_getcsv($first_line, $d));
    if ($count > $max_count) {
        $max_count = $count;
        $delimiter = $d;
    }
}

// Parse headers
$headers = str_getcsv($first_line, $delimiter);
$headers = array_map(function($h) {
    return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', $h)));
}, $headers);

// Ensure essential headers exist
$required_headers = ['name', 'category', 'regular_price'];
foreach ($required_headers as $req) {
    if (!in_array($req, $headers) && !in_array('product_sku', $headers) && !in_array('sku', $headers)) {
        fclose($handle);
        json_response('error', 'Missing critical column: "' . $req . '" in CSV header. Please download the sample template.');
    }
}

// Map column names to indexes
$col_map = array_flip($headers);

$rows = [];
$line_number = 1;
while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
    $line_number++;
    if (empty(array_filter($data, 'strlen'))) {
        continue; // Skip blank lines
    }

    $row = [];
    foreach ($col_map as $col_name => $index) {
        $row[$col_name] = isset($data[$index]) ? trim($data[$index]) : '';
    }
    $row['_line'] = $line_number;
    $rows[] = $row;
}
fclose($handle);

if (empty($rows)) {
    json_response('error', 'No data rows found in the uploaded file.');
}

// 4. PRE-CACHE ATTRIBUTES (Categories, Sizes, Colors)
$category_map = []; // Lowercase name/slug -> ID
$cats = $pdo->query("SELECT id, name, slug FROM categories")->fetchAll();
foreach ($cats as $c) {
    $category_map[strtolower($c['name'])] = (int)$c['id'];
    $category_map[strtolower($c['slug'])] = (int)$c['id'];
}

$size_map = []; // Lowercase code/name -> ID
$sizes = $pdo->query("SELECT id, size_name, size_code FROM sizes")->fetchAll();
foreach ($sizes as $s) {
    $size_map[strtolower($s['size_name'])] = (int)$s['id'];
    $size_map[strtolower($s['size_code'])] = (int)$s['id'];
}

$color_map = []; // Lowercase name -> ID
$colors = $pdo->query("SELECT id, color_name FROM colors")->fetchAll();
foreach ($colors as $cl) {
    $color_map[strtolower($cl['color_name'])] = (int)$cl['id'];
}

// 5. GROUP ROWS BY PRODUCT (product_sku or name)
$grouped_products = [];
foreach ($rows as $r) {
    $parent_key = !empty($r['product_sku']) ? $r['product_sku'] : (!empty($r['sku']) ? $r['sku'] : slugify($r['name']));
    if (empty($parent_key)) {
        $parent_key = 'UNNAMED_PROD_' . $r['_line'];
    }
    $grouped_products[$parent_key][] = $r;
}

// 6. PROCESS IMPORT IN A TRANSACTION
$stats = [
    'total_rows' => count($rows),
    'products_created' => 0,
    'products_updated' => 0,
    'variants_created' => 0,
    'variants_updated' => 0,
    'images_attached' => 0,
    'attributes_created' => 0,
    'warnings' => [],
    'errors' => []
];

// Helper to fetch/download image URL safely
function download_image_from_url($url, $uploads_dir) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 8,
            'user_agent' => 'Mozilla/5.0 (BulkProductImporter)'
        ]
    ]);
    $img_data = @file_get_contents($url, false, $ctx);
    if (!$img_data || strlen($img_data) < 100) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($img_data);
    $ext_map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($ext_map[$mime])) {
        return null;
    }

    $ext = $ext_map[$mime];
    $new_name = 'bulk_' . uniqid('', true) . '.' . $ext;
    file_put_contents($uploads_dir . '/' . $new_name, $img_data);
    return $new_name;
}

try {
    $pdo->beginTransaction();

    foreach ($grouped_products as $group_key => $prod_rows) {
        $first_row = $prod_rows[0];
        $prod_name = $first_row['name'] ?? '';
        $prod_sku = !empty($first_row['product_sku']) ? $first_row['product_sku'] : (!empty($first_row['sku']) ? $first_row['sku'] : '');
        $cat_val = $first_row['category'] ?? '';
        $regular_price = (float)($first_row['regular_price'] ?? 0);
        $offer_price = !empty($first_row['offer_price']) ? (float)$first_row['offer_price'] : null;
        $gst_percentage = !empty($first_row['gst_percentage']) ? (float)$first_row['gst_percentage'] : DEFAULT_GST_RATE;
        $product_type = in_array(strtolower($first_row['product_type'] ?? ''), ['single', 'combo']) ? strtolower($first_row['product_type']) : 'single';
        $description = $first_row['description'] ?? ($prod_name . ' - Premium Quality');
        $fabric = $first_row['fabric_details'] ?? '';
        $fit_type = $first_row['fit_type'] ?? '';
        $care = $first_row['care_instructions'] ?? '';
        $is_featured = isset($first_row['is_featured']) && ($first_row['is_featured'] == '1' || strtolower($first_row['is_featured']) === 'yes') ? 1 : 0;
        $is_new_arrival = isset($first_row['is_new_arrival']) && ($first_row['is_new_arrival'] == '1' || strtolower($first_row['is_new_arrival']) === 'yes') ? 1 : 0;
        $is_best_seller = isset($first_row['is_best_seller']) && ($first_row['is_best_seller'] == '1' || strtolower($first_row['is_best_seller']) === 'yes') ? 1 : 0;
        $status = (!isset($first_row['status']) || $first_row['status'] == '1' || strtolower($first_row['status']) === 'active') ? 1 : 0;
        $seo_title = !empty($first_row['seo_title']) ? $first_row['seo_title'] : ($prod_name . ' | ' . SITE_NAME);
        $seo_desc = !empty($first_row['seo_description']) ? $first_row['seo_description'] : substr(strip_tags($description), 0, 150);

        if (empty($prod_name)) {
            $stats['errors'][] = "Line {$first_row['_line']}: Product title cannot be empty. Skipping product group [{$group_key}].";
            continue;
        }

        if (empty($prod_sku)) {
            $prod_sku = 'SKU-' . strtoupper(substr(slugify($prod_name), 0, 8)) . '-' . rand(100, 999);
        }

        // Resolve Category
        $cat_key = strtolower($cat_val);
        $category_id = null;
        if (!empty($cat_key) && isset($category_map[$cat_key])) {
            $category_id = $category_map[$cat_key];
        } elseif (!empty($cat_val)) {
            if ($auto_create_attributes) {
                $new_cat_slug = slugify($cat_val);
                $ins_cat = $pdo->prepare("INSERT INTO categories (name, slug, description, status) VALUES (?, ?, ?, 1)");
                $ins_cat->execute([$cat_val, $new_cat_slug, $cat_val . ' collection']);
                $category_id = (int)$pdo->lastInsertId();
                $category_map[strtolower($cat_val)] = $category_id;
                $category_map[strtolower($new_cat_slug)] = $category_id;
                $stats['attributes_created']++;
            } else {
                $stats['errors'][] = "Line {$first_row['_line']}: Category '{$cat_val}' does not exist. (Enable 'Auto-create attributes' to add automatically).";
                continue;
            }
        } else {
            // Default to first category if none provided
            $first_cat = reset($category_map);
            $category_id = $first_cat ? (int)$first_cat : 1;
        }

        // Check if product exists by SKU
        $chk_prod = $pdo->prepare("SELECT id, slug FROM products WHERE sku = ? LIMIT 1");
        $chk_prod->execute([$prod_sku]);
        $existing_product = $chk_prod->fetch();

        $product_id = null;
        $slug = slugify($prod_name);

        if ($existing_product) {
            $product_id = (int)$existing_product['id'];
            if ($import_mode === 'create_and_update') {
                $up_stmt = $pdo->prepare("
                    UPDATE products SET 
                        category_id = ?, product_type = ?, name = ?, description = ?, 
                        fabric_details = ?, fit_type = ?, care_instructions = ?, 
                        regular_price = ?, offer_price = ?, gst_percentage = ?, 
                        is_featured = ?, is_new_arrival = ?, is_best_seller = ?, 
                        status = ?, seo_title = ?, seo_description = ? 
                    WHERE id = ?
                ");
                $up_stmt->execute([
                    $category_id, $product_type, $prod_name, $description,
                    $fabric, $fit_type, $care,
                    $regular_price, $offer_price, $gst_percentage,
                    $is_featured, $is_new_arrival, $is_best_seller,
                    $status, $seo_title, $seo_desc,
                    $product_id
                ]);
                $stats['products_updated']++;
            }
        } else {
            // Generate unique slug
            $base_slug = $slug;
            $slug_counter = 1;
            while (true) {
                $chk_slug = $pdo->prepare("SELECT id FROM products WHERE slug = ? LIMIT 1");
                $chk_slug->execute([$slug]);
                if (!$chk_slug->fetch()) {
                    break;
                }
                $slug = $base_slug . '-' . $slug_counter++;
            }

            $ins_prod = $pdo->prepare("
                INSERT INTO products (
                    category_id, product_type, name, slug, sku, description, 
                    fabric_details, fit_type, care_instructions, regular_price, 
                    offer_price, gst_percentage, is_featured, is_new_arrival, 
                    is_best_seller, status, seo_title, seo_description
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins_prod->execute([
                $category_id, $product_type, $prod_name, $slug, $prod_sku, $description,
                $fabric, $fit_type, $care, $regular_price,
                $offer_price, $gst_percentage, $is_featured, $is_new_arrival,
                $is_best_seller, $status, $seo_title, $seo_desc
            ]);
            $product_id = (int)$pdo->lastInsertId();
            $stats['products_created']++;
        }

        if (!$product_id) {
            continue;
        }

        // Process Variants and Images for this product group
        $has_existing_featured_img = false;
        $chk_feat = $pdo->prepare("SELECT id FROM product_images WHERE product_id = ? AND is_featured = 1 LIMIT 1");
        $chk_feat->execute([$product_id]);
        if ($chk_feat->fetch()) {
            $has_existing_featured_img = true;
        }

        foreach ($prod_rows as $v_row) {
            $size_val = trim($v_row['size'] ?? '');
            $color_val = trim($v_row['color'] ?? '');
            $variant_sku = trim($v_row['variant_sku'] ?? '');
            $stock = (int)($v_row['stock'] ?? 0);
            $variant_regular_price = !empty($v_row['variant_regular_price']) ? (float)$v_row['variant_regular_price'] : null;
            $variant_offer_price = !empty($v_row['variant_offer_price']) ? (float)$v_row['variant_offer_price'] : (!empty($v_row['variant_price']) ? (float)$v_row['variant_price'] : null);
            $variant_price = $variant_offer_price !== null ? $variant_offer_price : $variant_regular_price;
            $image_str = trim($v_row['image_filenames'] ?? ($v_row['images'] ?? ''));

            // If no size or color specified, assign default standard
            if (empty($size_val)) {
                $size_val = 'Free Size';
            }
            if (empty($color_val)) {
                $color_val = 'Standard';
            }

            // Resolve Size ID
            $size_key = strtolower($size_val);
            $size_id = null;
            if (isset($size_map[$size_key])) {
                $size_id = $size_map[$size_key];
            } else {
                if ($auto_create_attributes) {
                    $ins_sz = $pdo->prepare("INSERT INTO sizes (size_name, size_code, category_type) VALUES (?, ?, ?)");
                    $ins_sz->execute([$size_val, strtoupper(substr($size_val, 0, 10)), 'General']);
                    $size_id = (int)$pdo->lastInsertId();
                    $size_map[strtolower($size_val)] = $size_id;
                    $size_map[strtolower(substr($size_val, 0, 10))] = $size_id;
                    $stats['attributes_created']++;
                } else {
                    $first_sz = reset($size_map);
                    $size_id = $first_sz ? (int)$first_sz : 1;
                    $stats['warnings'][] = "Line {$v_row['_line']}: Size '{$size_val}' not found. Defaulted to system size ID {$size_id}.";
                }
            }

            // Resolve Color ID
            $color_key = strtolower($color_val);
            $color_id = null;
            if (isset($color_map[$color_key])) {
                $color_id = $color_map[$color_key];
            } else {
                if ($auto_create_attributes) {
                    // Generate random attractive pastel hex if creating new color
                    $rand_hex = '#' . substr(md5($color_val), 0, 6);
                    $ins_cl = $pdo->prepare("INSERT INTO colors (color_name, color_code) VALUES (?, ?)");
                    $ins_cl->execute([$color_val, $rand_hex]);
                    $color_id = (int)$pdo->lastInsertId();
                    $color_map[strtolower($color_val)] = $color_id;
                    $stats['attributes_created']++;
                } else {
                    $first_cl = reset($color_map);
                    $color_id = $first_cl ? (int)$first_cl : 1;
                    $stats['warnings'][] = "Line {$v_row['_line']}: Color '{$color_val}' not found. Defaulted to system color ID {$color_id}.";
                }
            }

            // Generate Variant SKU if missing
            if (empty($variant_sku)) {
                $clean_sz = preg_replace('/[^a-zA-Z0-9]/', '', $size_val);
                $clean_cl = preg_replace('/[^a-zA-Z0-9]/', '', $color_val);
                $variant_sku = $prod_sku . '-' . strtoupper(substr($clean_sz, 0, 4)) . '-' . strtoupper(substr($clean_cl, 0, 4));
            }

            // Check if variant exists
            $chk_v = $pdo->prepare("SELECT id, stock FROM product_variants WHERE sku = ? OR (product_id = ? AND size_id = ? AND color_id = ?) LIMIT 1");
            $chk_v->execute([$variant_sku, $product_id, $size_id, $color_id]);
            $existing_variant = $chk_v->fetch();

            if ($existing_variant) {
                $variant_id = (int)$existing_variant['id'];
                if ($import_mode === 'create_and_update') {
                    $up_v = $pdo->prepare("UPDATE product_variants SET regular_price = ?, offer_price = ?, price = ?, stock = ? WHERE id = ?");
                    $up_v->execute([$variant_regular_price, $variant_offer_price, $variant_price, $stock, $variant_id]);

                    // Log stock adjustment
                    $old_stock = (int)$existing_variant['stock'];
                    $stock_diff = $stock - $old_stock;
                    if ($stock_diff != 0) {
                        $log_st = $pdo->prepare("INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) VALUES (?, ?, 'adjustment', 'Bulk CSV update')");
                        $log_st->execute([$variant_id, $stock_diff]);
                    }
                    $stats['variants_updated']++;
                }
            } else {
                $ins_v = $pdo->prepare("INSERT INTO product_variants (product_id, size_id, color_id, sku, regular_price, offer_price, price, stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $ins_v->execute([$product_id, $size_id, $color_id, $variant_sku, $variant_regular_price, $variant_offer_price, $variant_price, $stock]);
                $variant_id = (int)$pdo->lastInsertId();

                // Initial inventory log
                if ($stock > 0) {
                    $log_st = $pdo->prepare("INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) VALUES (?, ?, 'restock', 'Initial bulk import')");
                    $log_st->execute([$variant_id, $stock]);
                }
                $stats['variants_created']++;
            }

            // Process Images for this row
            if (!empty($image_str)) {
                $raw_images = array_map('trim', explode(',', $image_str));
                foreach ($raw_images as $raw_img) {
                    if (empty($raw_img)) continue;

                    $final_filename = null;

                    // If URL
                    if (strpos($raw_img, 'http://') === 0 || strpos($raw_img, 'https://') === 0) {
                        $final_filename = download_image_from_url($raw_img, $uploads_dir);
                        if (!$final_filename) {
                            $stats['warnings'][] = "Line {$v_row['_line']}: Could not download image URL '{$raw_img}'.";
                            continue;
                        }
                    } else {
                        // Local filename
                        $cleaned_name = basename($raw_img);
                        if (file_exists($uploads_dir . '/' . $cleaned_name)) {
                            $final_filename = $cleaned_name;
                        } else {
                            $stats['warnings'][] = "Line {$v_row['_line']}: Image file '{$cleaned_name}' not found in uploads folder or ZIP archive.";
                            continue;
                        }
                    }

                    if ($final_filename) {
                        // Check if already attached to this product & color
                        $chk_img = $pdo->prepare("SELECT id FROM product_images WHERE product_id = ? AND image_path = ? LIMIT 1");
                        $chk_img->execute([$product_id, $final_filename]);
                        if (!$chk_img->fetch()) {
                            $is_featured_img = (!$has_existing_featured_img) ? 1 : 0;
                            $ins_img = $pdo->prepare("INSERT INTO product_images (product_id, color_id, image_path, is_featured, sort_order) VALUES (?, ?, ?, ?, 0)");
                            $ins_img->execute([$product_id, $color_id, $final_filename, $is_featured_img]);
                            $stats['images_attached']++;
                            if ($is_featured_img) {
                                $has_existing_featured_img = true;
                            }
                        }
                    }
                }
            }
        }
    }

    $pdo->commit();

    // Log admin action
    log_admin_action($_SESSION['admin_id'], "Bulk Imported products: {$stats['products_created']} created, {$stats['products_updated']} updated.");

    json_response('success', 'Bulk upload processed successfully!', $stats);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response('error', 'Database execution error: ' . $e->getMessage(), ['stats' => $stats]);
}
