<?php
// product-detail.php
// Product Detailed Page with Variant Matrix swatches & Reviews

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

$slug = trim($_GET['slug'] ?? '');

try {
    // Fetch product details
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.slug = ? AND p.status = 1 LIMIT 1
    ");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();

    if (!$product) {
        set_flash_message('error', 'Product not found.');
        header('Location: products.php');
        exit;
    }

    $product_id = $product['id'];

    // Dynamic SEO details
    $meta_title = !empty($product['seo_title']) ? $product['seo_title'] : ($product['name'] . ' | ' . APP_NAME);
    $meta_desc = !empty($product['seo_description']) ? $product['seo_description'] : substr(strip_tags($product['description'] ?? ''), 0, 160);

    require_once __DIR__ . '/includes/header.php';

    // Update SEO details dynamically
    // Fetch product images with color tagging
    $images_stmt = $pdo->prepare("
        SELECT pi.*, cl.color_name, cl.color_code 
        FROM product_images pi 
        LEFT JOIN colors cl ON pi.color_id = cl.id 
        WHERE pi.product_id = ? 
        ORDER BY pi.is_featured DESC, pi.sort_order ASC, pi.id ASC
    ");
    $images_stmt->execute([$product_id]);
    $gallery = $images_stmt->fetchAll();

    // Prepare gallery maps for client-side dynamic color switching
    $gallery_by_color = [];
    $gallery_general = [];
    foreach ($gallery as $img) {
        $img_item = [
            'id' => (int)$img['id'],
            'src' => 'uploads/' . $img['image_path'],
            'color_id' => $img['color_id'] ? (int)$img['color_id'] : null,
            'color_name' => $img['color_name'] ?? null,
            'color_code' => $img['color_code'] ?? null,
            'is_featured' => (int)$img['is_featured']
        ];
        if (!empty($img['color_id'])) {
            $gallery_by_color[(int)$img['color_id']][] = $img_item;
        } else {
            $gallery_general[] = $img_item;
        }
    }

    // Check if combo product
    $is_combo = ($product['product_type'] ?? 'single') === 'combo';
    $combo_components = [];

    if ($is_combo) {
        $comp_stmt = $pdo->prepare("
            SELECT cc.*, 
                   p.name as specific_product_name, p.slug as specific_product_slug,
                   c.name as category_name
            FROM combo_components cc
            LEFT JOIN products p ON cc.allowed_product_id = p.id
            LEFT JOIN categories c ON cc.allowed_category_id = c.id
            WHERE cc.combo_product_id = ?
            ORDER BY cc.sort_order ASC, cc.id ASC
        ");
        $comp_stmt->execute([$product_id]);
        $components_raw = $comp_stmt->fetchAll();

        foreach ($components_raw as $comp) {
            // Determine eligible products
            if (!empty($comp['allowed_product_id'])) {
                $p_sql = "SELECT id, name, slug FROM products WHERE id = ? AND status = 1";
                $p_stmt = $pdo->prepare($p_sql);
                $p_stmt->execute([$comp['allowed_product_id']]);
                $comp_products = $p_stmt->fetchAll();
            } elseif (!empty($comp['allowed_category_id'])) {
                $p_sql = "SELECT id, name, slug FROM products WHERE category_id = ? AND product_type = 'single' AND status = 1";
                $p_stmt = $pdo->prepare($p_sql);
                $p_stmt->execute([$comp['allowed_category_id']]);
                $comp_products = $p_stmt->fetchAll();
            } else {
                $p_sql = "SELECT id, name, slug FROM products WHERE product_type = 'single' AND status = 1 LIMIT 20";
                $p_stmt = $pdo->query($p_sql);
                $comp_products = $p_stmt->fetchAll();
            }

            // For each eligible product, load variants
            $loaded_products = [];
            foreach ($comp_products as $cp) {
                $v_stmt = $pdo->prepare("
                    SELECT pv.id as variant_id, pv.sku, pv.stock, pv.price,
                           s.id as size_id, s.size_code, s.size_name,
                           cl.id as color_id, cl.color_name, cl.color_code
                    FROM product_variants pv
                    JOIN sizes s ON pv.size_id = s.id
                    JOIN colors cl ON pv.color_id = cl.id
                    WHERE pv.product_id = ? AND pv.stock > 0
                    ORDER BY s.id ASC, cl.id ASC
                ");
                $v_stmt->execute([$cp['id']]);
                $var_rows = $v_stmt->fetchAll();

                if (!empty($var_rows)) {
                    $cp['variants'] = $var_rows;
                    $loaded_products[] = $cp;
                }
            }

            $comp['products'] = $loaded_products;
            $combo_components[] = $comp;
        }
    }

    // Fetch variants (for single product AJAX variant matrix checks)
    $variants_stmt = $pdo->prepare("
        SELECT pv.id, pv.size_id, pv.color_id, pv.sku, pv.regular_price, pv.offer_price, pv.price, pv.stock 
        FROM product_variants pv 
        WHERE pv.product_id = ? AND pv.stock >= 0
    ");
    $variants_stmt->execute([$product_id]);
    $variants = $variants_stmt->fetchAll();

    // Fetch list of distinct sizes and colors available for this product
    $sizes_stmt = $pdo->prepare("
        SELECT DISTINCT s.* FROM sizes s 
        JOIN product_variants pv ON s.id = pv.size_id 
        WHERE pv.product_id = ?
    ");
    $sizes_stmt->execute([$product_id]);
    $available_sizes = $sizes_stmt->fetchAll();
    sort_sizes($available_sizes);

    $colors_stmt = $pdo->prepare("
        SELECT DISTINCT c.* FROM colors c 
        JOIN product_variants pv ON c.id = pv.color_id 
        WHERE pv.product_id = ?
        UNION
        SELECT DISTINCT c.* FROM colors c
        JOIN product_images pi ON c.id = pi.color_id
        WHERE pi.product_id = ?
    ");
    $colors_stmt->execute([$product_id, $product_id]);
    $available_colors = $colors_stmt->fetchAll();

    // Determine initial color to display (first in-stock variant color or first variant color)
    $initial_color_id = null;
    if (!empty($variants)) {
        foreach ($variants as $v) {
            if ((int)$v['stock'] > 0 && !empty($v['color_id'])) {
                $initial_color_id = (int)$v['color_id'];
                break;
            }
        }
        if (!$initial_color_id && !empty($variants[0]['color_id'])) {
            $initial_color_id = (int)$variants[0]['color_id'];
        }
    }

    // Determine initial gallery images to render in HTML
    $initial_gallery = [];
    if ($initial_color_id && !empty($gallery_by_color[$initial_color_id])) {
        $initial_gallery = $gallery_by_color[$initial_color_id];
    } elseif (!empty($gallery_general)) {
        $initial_gallery = $gallery_general;
    } else {
        $initial_gallery = array_map(function($img) {
            return [
                'id' => (int)$img['id'],
                'src' => 'uploads/' . $img['image_path'],
                'color_id' => $img['color_id'] ? (int)$img['color_id'] : null,
                'color_name' => $img['color_name'] ?? null,
                'color_code' => $img['color_code'] ?? null,
                'is_featured' => (int)$img['is_featured']
            ];
        }, $gallery);
    }

    // Fetch related products
    $rel_stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name,
        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_featured DESC LIMIT 1) as main_image 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.category_id = ? AND p.id != ? AND p.status = 1 
        ORDER BY RAND() LIMIT 4
    ");
    $rel_stmt->execute([$product['category_id'], $product_id]);
    $related_products = $rel_stmt->fetchAll();

    // Fetch approved customer reviews
    $rev_stmt = $pdo->prepare("
        SELECT r.*, c.name as customer_name 
        FROM reviews r 
        JOIN customers c ON r.customer_id = c.id 
        WHERE r.product_id = ? AND r.status = 1 
        ORDER BY r.created_at DESC
    ");
    $rev_stmt->execute([$product_id]);
    $reviews_list = $rev_stmt->fetchAll();

} catch (PDOException $e) {
    die("Error retrieving product details: " . $e->getMessage());
}

// Customer Purchase & Review Status Check
$is_customer_auth = is_customer_logged_in();
$has_purchased_product = $is_customer_auth ? has_customer_purchased_product($_SESSION['customer_id'], $product_id) : false;
$existing_user_review = $is_customer_auth ? get_customer_product_review($_SESSION['customer_id'], $product_id) : null;

// Handle Customer Review Post submissions
$review_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!$is_customer_auth) {
        $review_error = 'Please log in to submit a review.';
    } elseif (!$has_purchased_product) {
        $review_error = 'Only verified customers who have purchased this product can write a review.';
    } else {
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($csrf_token)) {
            $review_error = 'Security check failed. Try again.';
        } else {
            $rating = (int)($_POST['rating'] ?? 5);
            $comment = trim($_POST['comment'] ?? '');
            
            if ($rating < 1 || $rating > 5 || empty($comment)) {
                $review_error = 'Please provide a valid rating (1-5) and a written review.';
            } else {
                try {
                    if ($existing_user_review) {
                        // Update existing review and place back in moderation
                        $up_stmt = $pdo->prepare("UPDATE reviews SET rating = ?, comment = ?, status = 0, created_at = CURRENT_TIMESTAMP WHERE id = ? AND customer_id = ?");
                        $up_stmt->execute([$rating, $comment, $existing_user_review['id'], $_SESSION['customer_id']]);
                        set_flash_message('success', 'Your review has been updated and submitted for moderation! Thank you.');
                    } else {
                        $ins_stmt = $pdo->prepare("INSERT INTO reviews (product_id, customer_id, rating, comment, status) VALUES (?, ?, ?, ?, 0)");
                        $ins_stmt->execute([$product_id, $_SESSION['customer_id'], $rating, $comment]);
                        set_flash_message('success', 'Your review has been submitted for moderation! Thank you.');
                    }
                    header('Location: product-detail.php?slug=' . $slug . '#reviews');
                    exit;
                } catch (PDOException $e) {
                    $review_error = 'Failed to submit review: ' . $e->getMessage();
                }
            }
        }
    }
}
?>

<div class="container py-5 pdp-page-container">
    <div class="row g-5">
        <!-- 1. Product Images Gallery with Interactive Zoom -->
        <div class="col-12 col-md-6">
            <div class="row g-2">
                <!-- Large active image preview with Zoom -->
                <div class="col-12">
                    <div class="product-gallery-main position-relative border rounded p-2 text-center" id="product-zoom-container">
                        <?php if(!empty($initial_gallery)): ?>
                            <img src="<?php echo e($initial_gallery[0]['src']); ?>" 
                                 alt="<?php echo e($product['name']); ?>" 
                                 id="main-product-gallery-img" 
                                 class="img-fluid"
                                 data-zoom-src="<?php echo e($initial_gallery[0]['src']); ?>">
                            
                            <!-- Floating Expand / Fullscreen Button -->
                            <button type="button" class="btn btn-sm btn-light rounded-circle shadow-sm position-absolute top-0 end-0 m-3 d-flex align-items-center justify-content-center" id="btn-open-lightbox" title="Fullscreen View" style="width: 38px; height: 38px; z-index: 5;">
                                <i class="bi bi-arrows-fullscreen text-dark"></i>
                            </button>

                            <!-- Hover / Click Zoom Hint Badge -->
                            <div class="zoom-hint-badge position-absolute bottom-0 start-50 translate-middle-x mb-3 px-3 py-1 rounded-pill bg-dark bg-opacity-75 text-white small" id="zoom-hint-badge" style="font-size: 11px;">
                                <i class="bi bi-zoom-in me-1"></i> Hover to zoom &bull; Click to expand
                            </div>
                        <?php else: ?>
                            <div class="bg-light text-muted d-flex align-items-center justify-content-center w-100" style="height: 380px;">
                                <i class="bi bi-image fs-1 text-secondary"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Thumbnails Strip -->
                <div class="col-12 mt-2 <?php echo count($initial_gallery) <= 1 ? 'd-none' : ''; ?>" id="gallery-thumbnails-container">
                    <div class="d-flex gap-2 overflow-x-auto pb-2" id="gallery-thumbnails-strip">
                        <?php foreach($initial_gallery as $idx => $img): ?>
                            <div class="gallery-thumb-item <?php echo $idx === 0 ? 'active' : ''; ?>" 
                                 data-index="<?php echo $idx; ?>"
                                 data-src="<?php echo e($img['src']); ?>">
                                <img src="<?php echo e($img['src']); ?>" alt="thumbnail <?php echo $idx + 1; ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Product Specifications & Purchase options -->
        <div class="col-12 col-md-6">
            <?php if ($is_combo): ?>
                <div class="mb-2">
                    <span class="combo-badge"><i class="bi bi-stars"></i> Curated Matching Combo Set</span>
                </div>
            <?php else: ?>
                <span class="badge bg-secondary mb-2 text-uppercase"><?php echo e($product['category_name']); ?></span>
            <?php endif; ?>

            <h1 class="product-detail-title mb-2"><?php echo e($product['name']); ?></h1>
            <p class="text-muted small">SKU: <strong class="text-dark"><?php echo e($product['sku']); ?></strong></p>

            <div class="d-flex align-items-center gap-3 my-3" id="product-price-container">
                <h3 class="fw-bold text-rose mb-0" id="product-price-span">
                    <?php echo format_price($product['offer_price'] ?? $product['regular_price']); ?>
                </h3>
                <span class="text-muted text-decoration-line-through fs-5 <?php echo empty($product['offer_price']) ? 'd-none' : ''; ?>" id="product-regular-price-span">
                    <?php echo format_price($product['regular_price']); ?>
                </span>
                <?php 
                    $savings = !empty($product['offer_price']) ? ($product['regular_price'] - $product['offer_price']) : 0;
                    $save_pct = (!empty($product['offer_price']) && $product['regular_price'] > 0) ? round(($savings / $product['regular_price']) * 100) : 0;
                ?>
                <span class="badge bg-danger <?php echo empty($product['offer_price']) ? 'd-none' : ''; ?>" id="product-save-badge">
                    Save <?php echo format_price($savings); ?> (<?php echo $save_pct; ?>% OFF)
                </span>
            </div>

            <p class="text-secondary small mb-4"><?php echo nl2br(e($product['description'])); ?></p>

            <?php if ($is_combo): ?>
                <!-- =========================================
                     DYNAMIC COMBO SET BUILDER
                     ========================================= -->
                <div class="border-top pt-4 mb-4" id="combo-builder-container">
                    <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-sliders me-2 text-rose"></i> Customise Your Set Pieces</h5>
                    <p class="small text-muted mb-4">Select your tailored fit for each piece in this luxury set:</p>

                    <form id="combo-purchase-form">
                        <input type="hidden" name="combo_product_id" id="combo-product-id" value="<?php echo $product_id; ?>">

                        <?php foreach ($combo_components as $c_idx => $comp): ?>
                            <div class="combo-step-card" data-component-id="<?php echo $comp['id']; ?>" id="step-card-<?php echo $comp['id']; ?>">
                                <div class="combo-step-header">
                                    <div class="d-flex align-items-center">
                                        <span class="combo-step-num"><?php echo $c_idx + 1; ?></span>
                                        <h5 class="combo-step-title"><?php echo e($comp['component_title']); ?></h5>
                                    </div>
                                    <span class="badge bg-light text-secondary border small">Step <?php echo $c_idx + 1; ?> of <?php echo count($combo_components); ?></span>
                                </div>

                                <?php foreach ($comp['products'] as $prod_idx => $c_prod): ?>
                                    <div class="combo-subproduct-container" data-product-id="<?php echo $c_prod['id']; ?>">
                                        <?php if (count($comp['products']) > 1): ?>
                                            <h6 class="fw-bold small text-dark mb-2"><?php echo e($c_prod['name']); ?></h6>
                                        <?php endif; ?>

                                        <!-- Hidden input to store chosen variant for this component -->
                                        <input type="hidden" class="combo-component-variant-input" data-component-id="<?php echo $comp['id']; ?>" name="component_variant[<?php echo $comp['id']; ?>]" value="">

                                        <!-- Colors -->
                                        <?php 
                                            // Extract unique colors for this subproduct
                                            $u_colors = [];
                                            $u_sizes = [];
                                            foreach ($c_prod['variants'] as $v) {
                                                $u_colors[$v['color_id']] = ['id' => $v['color_id'], 'name' => $v['color_name'], 'code' => $v['color_code']];
                                                $u_sizes[$v['size_id']] = ['id' => $v['size_id'], 'code' => $v['size_code'], 'name' => $v['size_name']];
                                            }
                                            sort_sizes($u_sizes);
                                        ?>
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-muted text-uppercase mb-1">Color: <span class="selected-color-label text-dark fw-normal"></span></label>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php foreach ($u_colors as $clr): ?>
                                                    <span class="swatch-color combo-swatch-color" 
                                                          data-component-id="<?php echo $comp['id']; ?>"
                                                          data-product-id="<?php echo $c_prod['id']; ?>"
                                                          data-color-id="<?php echo $clr['id']; ?>" 
                                                          data-color-name="<?php echo e($clr['name']); ?>"
                                                          style="background-color: <?php echo e($clr['code']); ?>;" 
                                                          title="<?php echo e($clr['name']); ?>"></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <!-- Sizes -->
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="form-label small fw-bold text-muted text-uppercase mb-0">Size: <span class="selected-size-label text-dark fw-normal"></span></label>
                                                <a href="#" class="small text-rose" data-bs-toggle="modal" data-bs-target="#sizeGuideModal"><i class="bi bi-rulers"></i> Size Chart</a>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <?php foreach ($u_sizes as $sz): ?>
                                                    <span class="swatch-size combo-swatch-size" 
                                                          data-component-id="<?php echo $comp['id']; ?>"
                                                          data-product-id="<?php echo $c_prod['id']; ?>"
                                                          data-size-id="<?php echo $sz['id']; ?>"
                                                          data-size-code="<?php echo e($sz['code']); ?>">
                                                        <?php echo e($sz['code']); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <!-- Live Item Status Feedback -->
                                        <div class="combo-item-status small mt-2" id="status-comp-<?php echo $comp['id']; ?>">
                                            <span class="text-muted"><i class="bi bi-info-circle me-1"></i> Choose color and size above</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>

                        <!-- Live Summary Box -->
                        <div class="combo-summary-box mb-4">
                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-bag-check me-2 text-rose"></i> Bundle Configuration Summary</h6>
                            <?php foreach ($combo_components as $comp): ?>
                                <div class="combo-summary-item" id="summary-row-<?php echo $comp['id']; ?>">
                                    <span class="item-name"><?php echo e($comp['component_title']); ?>:</span>
                                    <span class="item-choice text-muted" id="summary-text-<?php echo $comp['id']; ?>">Pending Selection</span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Quantities & Cart Action Buttons -->
                        <div class="d-flex flex-column gap-2 mt-4">
                            <div class="d-flex align-items-center gap-2">
                                <div class="input-group input-group-sm border rounded-pill overflow-hidden bg-white" style="width: 105px; height: 44px; flex-shrink: 0;">
                                    <button type="button" class="btn btn-link text-dark py-1 qty-btn qty-minus px-2" style="text-decoration:none;"><i class="bi bi-minus"></i></button>
                                    <input type="text" class="form-control text-center bg-transparent border-0 small qty-input fw-bold" id="combo-qty" name="quantity" value="1" readonly style="width:30px;">
                                    <button type="button" class="btn btn-link text-dark py-1 qty-btn qty-plus px-2" style="text-decoration:none;"><i class="bi bi-plus"></i></button>
                                </div>
                                <button type="button" class="btn btn-outline-secondary rounded-pill wishlist-toggle d-flex align-items-center justify-content-center" data-product-id="<?php echo $product_id; ?>" title="Add to Wishlist" style="width: 44px; height: 44px; flex-shrink: 0;">
                                    <i class="bi bi-heart fs-6"></i>
                                </button>
                                <button type="button" id="add-combo-to-cart-btn" class="btn btn-brand-outline rounded-pill flex-grow-1 fw-semibold d-flex align-items-center justify-content-center" disabled style="height: 44px;">
                                    <i class="bi bi-bag-plus me-1"></i> Add Set to Bag
                                </button>
                            </div>
                            <div>
                                <button type="button" id="buy-combo-now-btn" class="btn btn-dark w-100 rounded-pill fw-bold shadow-sm d-flex align-items-center justify-content-center" disabled style="height: 46px; background-color: #18181b; border: none; letter-spacing: 0.3px;">
                                    <i class="bi bi-lightning-charge-fill me-1 text-warning"></i> Buy Set Now
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <!-- Single Product Custom Swatches Matrix -->
                <form id="purchase-form" class="border-top pt-4 mb-4">
                    <input type="hidden" id="selected-variant-id" name="variant_id" value="">
                    
                    <!-- Colors -->
                    <?php if (!empty($available_colors)): ?>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted text-uppercase mb-2">
                                1. Choose Color: <span id="selected-color-name" class="text-dark fw-bold ms-1"></span>
                            </label>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <?php foreach ($available_colors as $color_item): ?>
                                    <span class="swatch-color" 
                                          data-color-id="<?php echo $color_item['id']; ?>" 
                                          data-color-name="<?php echo e($color_item['color_name']); ?>"
                                          style="background-color: <?php echo e($color_item['color_code']); ?>;" 
                                          title="<?php echo e($color_item['color_name']); ?>"></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Sizes -->
                    <?php if (!empty($available_sizes)): ?>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <label class="form-label small fw-bold text-muted text-uppercase"><?php echo !empty($available_colors) ? '2. Choose Size' : 'Choose Size'; ?></label>
                                <a href="#" class="small text-rose" data-bs-toggle="modal" data-bs-target="#sizeGuideModal"><i class="bi bi-rulers"></i> Size Guide</a>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <?php foreach ($available_sizes as $size_item): ?>
                                    <span class="swatch-size" data-size-id="<?php echo $size_item['id']; ?>">
                                        <?php echo e($size_item['size_code']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Stock availability -->
                    <div class="mb-4" id="variant-stock-display">
                        <span class="text-muted small">Please choose Color and Size to view stock availability.</span>
                    </div>

                    <!-- Quantities & Cart Action Buttons -->
                    <div class="d-flex flex-column gap-2 mt-4">
                        <div class="d-flex align-items-center gap-2">
                            <div class="input-group input-group-sm border rounded-pill overflow-hidden bg-white" style="width: 105px; height: 44px; flex-shrink: 0;">
                                <button type="button" class="btn btn-link text-dark py-1 qty-btn qty-minus px-2" style="text-decoration:none;"><i class="bi bi-minus"></i></button>
                                <input type="text" class="form-control text-center bg-transparent border-0 small qty-input fw-bold" id="product-qty" name="quantity" value="1" readonly style="width:30px;">
                                <button type="button" class="btn btn-link text-dark py-1 qty-btn qty-plus px-2" style="text-decoration:none;"><i class="bi bi-plus"></i></button>
                            </div>
                            <button type="button" class="btn btn-outline-secondary rounded-pill wishlist-toggle d-flex align-items-center justify-content-center" data-product-id="<?php echo $product_id; ?>" title="Add to Wishlist" style="width: 44px; height: 44px; flex-shrink: 0;">
                                <i class="bi bi-heart fs-6"></i>
                            </button>
                            <button type="button" id="add-to-cart-btn" class="btn btn-brand-outline rounded-pill flex-grow-1 fw-semibold d-flex align-items-center justify-content-center" disabled style="height: 44px;">
                                <i class="bi bi-bag-plus me-1"></i> Add to Bag
                            </button>
                        </div>
                        <div>
                            <button type="button" id="buy-now-btn" class="btn btn-dark w-100 rounded-pill fw-bold shadow-sm d-flex align-items-center justify-content-center" disabled style="height: 46px; background-color: #18181b; border: none; letter-spacing: 0.3px;">
                                <i class="bi bi-lightning-charge-fill me-1 text-warning"></i> Buy Now
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Fabric care specs -->
            <div class="accordion accordion-flush border-top" id="specsAccordion">
                <?php if ($product['fabric_details']): ?>
                    <div class="accordion-item bg-transparent">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-transparent fw-bold small text-secondary text-uppercase" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFabric">
                                Fabric Details & Composition
                            </button>
                        </h2>
                        <div id="collapseFabric" class="accordion-collapse collapse" data-bs-parent="#specsAccordion">
                            <div class="accordion-body small text-muted"><?php echo e($product['fabric_details']); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($product['fit_type']): ?>
                    <div class="accordion-item bg-transparent">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-transparent fw-bold small text-secondary text-uppercase" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFit">
                                Fit & Style Description
                            </button>
                        </h2>
                        <div id="collapseFit" class="accordion-collapse collapse" data-bs-parent="#specsAccordion">
                            <div class="accordion-body small text-muted"><?php echo e($product['fit_type']); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($product['care_instructions']): ?>
                    <div class="accordion-item bg-transparent">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-transparent fw-bold small text-secondary text-uppercase" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCare">
                                Care Guidelines
                            </button>
                        </h2>
                        <div id="collapseCare" class="accordion-collapse collapse" data-bs-parent="#specsAccordion">
                            <div class="accordion-body small text-muted"><?php echo e($product['care_instructions']); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 3. Reviews & Ratings Module -->
    <div class="row mt-5 pt-5 border-top" id="reviews">
        <div class="col-12 col-md-6">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h3 class="section-title mb-0">Customer Reviews</h3>
                <?php if (!empty($reviews_list)): ?>
                    <?php 
                        $total_reviews = count($reviews_list);
                        $avg_rating = array_sum(array_column($reviews_list, 'rating')) / $total_reviews;
                    ?>
                    <div class="text-end">
                        <span class="fw-bold text-dark fs-5"><?php echo number_format($avg_rating, 1); ?></span>
                        <div class="text-warning small" style="font-size: 11px;">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="bi <?php echo $i <= round($avg_rating) ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                            <?php endfor; ?>
                            <span class="text-muted ms-1">(<?php echo $total_reviews; ?>)</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($reviews_list)): ?>
                <div class="p-4 bg-light rounded text-center text-muted border">
                    <i class="bi bi-chat-heart fs-3 d-block mb-2 text-rose"></i>
                    <p class="small mb-0">No reviews for this product yet. Verified buyers can share their experience!</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush mb-4">
                    <?php foreach ($reviews_list as $rev): ?>
                        <div class="list-group-item px-0 py-3 bg-transparent border-0 border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div class="d-flex align-items-center">
                                    <span class="fw-bold small text-dark me-2"><?php echo e($rev['customer_name']); ?></span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill" style="font-size: 10px;">
                                        <i class="bi bi-patch-check-fill me-1"></i>Verified Buyer
                                    </span>
                                </div>
                                <span class="text-muted small" style="font-size: 11px;"><?php echo date('d-M-Y', strtotime($rev['created_at'])); ?></span>
                            </div>
                            <div class="text-warning mb-2" style="font-size:12px;">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <i class="bi <?php echo $i <= $rev['rating'] ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <p class="small text-secondary mb-0">"<?php echo e($rev['comment']); ?>"</p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-md-6 ps-md-5 mt-4 mt-md-0">
            <h3 class="section-title mb-4">Write a Review</h3>

            <?php if (!$is_customer_auth): ?>
                <!-- Not Logged In -->
                <div class="p-4 bg-white shadow-sm rounded border text-center">
                    <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3 text-secondary" style="width: 54px; height: 54px;">
                        <i class="bi bi-shield-lock fs-3"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Verified Buyers Only</h6>
                    <p class="text-muted small mb-3">To ensure 100% genuine feedback, reviews can only be submitted by verified customers who have purchased this product.</p>
                    <a href="login.php?redirect=<?php echo urlencode('product-detail.php?slug=' . $slug . '#reviews'); ?>" class="btn btn-sm btn-brand rounded-pill px-4">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Review
                    </a>
                </div>

            <?php elseif (!$has_purchased_product): ?>
                <!-- Logged In but Has Not Purchased -->
                <div class="p-4 bg-white shadow-sm rounded border text-center">
                    <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3 text-muted" style="width: 54px; height: 54px;">
                        <i class="bi bi-bag-check fs-3"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Purchase Required to Review</h6>
                    <p class="text-muted small mb-0">Only customers who have purchased this product with their account can leave a review. Once you order this item, you'll be able to share your feedback!</p>
                </div>

            <?php else: ?>
                <!-- Logged In AND Has Purchased -->
                <div class="p-4 bg-white shadow-sm rounded border">
                    <div class="d-flex align-items-center justify-content-between p-2 mb-3 bg-success bg-opacity-10 text-success rounded border border-success-subtle small">
                        <div>
                            <i class="bi bi-patch-check-fill me-1"></i>
                            <strong>Verified Purchaser</strong>
                        </div>
                        <?php if ($existing_user_review): ?>
                            <?php if ($existing_user_review['status'] == 0): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Under Moderation</span>
                            <?php elseif ($existing_user_review['status'] == 1): ?>
                                <span class="badge bg-success"><i class="bi bi-check2-circle me-1"></i>Review Published</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Needs Revision</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php if(!empty($review_error)): ?>
                        <div class="alert alert-danger p-2 small mb-3"><?php echo e($review_error); ?></div>
                    <?php endif; ?>

                    <form action="product-detail.php?slug=<?php echo $slug; ?>#reviews" method="POST">
                        <?php echo csrf_field(); ?>
                        <div class="form-floating mb-3">
                            <select class="form-select" id="rating" name="rating" required>
                                <?php 
                                    $cur_rating = (int)($existing_user_review['rating'] ?? 5);
                                ?>
                                <option value="5" <?php echo $cur_rating === 5 ? 'selected' : ''; ?>>★★★★★ (5 Stars - Excellent)</option>
                                <option value="4" <?php echo $cur_rating === 4 ? 'selected' : ''; ?>>★★★★☆ (4 Stars - Good)</option>
                                <option value="3" <?php echo $cur_rating === 3 ? 'selected' : ''; ?>>★★★☆☆ (3 Stars - Average)</option>
                                <option value="2" <?php echo $cur_rating === 2 ? 'selected' : ''; ?>>★★☆☆☆ (2 Stars - Poor)</option>
                                <option value="1" <?php echo $cur_rating === 1 ? 'selected' : ''; ?>>★☆☆☆☆ (1 Star - Terrible)</option>
                            </select>
                            <label for="rating">Star Rating <span class="text-danger">*</span></label>
                        </div>
                        <div class="form-floating mb-4">
                            <textarea class="form-control" id="comment" name="comment" style="height: 120px;" required placeholder="Tell us about the fabric quality, comfort, fit, and feel..."><?php echo e($existing_user_review['comment'] ?? ''); ?></textarea>
                            <label for="comment">Your Review <span class="text-danger">*</span></label>
                        </div>
                        <button type="submit" name="submit_review" class="btn btn-brand w-100">
                            <?php echo $existing_user_review ? 'Update My Review' : 'Submit Review'; ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Related Products Grid -->
    <?php if(!empty($related_products)): ?>
        <div class="mt-5 pt-5 border-top">
            <h2 class="section-title text-center mb-4">You May Also Like</h2>
            <div class="row g-4">
                <?php foreach ($related_products as $prod): ?>
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="card product-card">
                            <div class="img-container">
                                <div class="wishlist-overlay wishlist-toggle" data-product-id="<?php echo $prod['id']; ?>">
                                    <i class="bi bi-heart"></i>
                                </div>
                                <a href="product-detail.php?slug=<?php echo e($prod['slug']); ?>">
                                    <?php if ($prod['main_image']): ?>
                                        <img src="uploads/<?php echo e($prod['main_image']); ?>" alt="<?php echo e($prod['name']); ?>">
                                    <?php else: ?>
                                        <div class="w-100 h-100 bg-secondary d-flex align-items-center justify-content-center text-white"><i class="bi bi-image fs-1"></i></div>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="product-details text-center">
                                <span class="product-category"><?php echo e($prod['category_name']); ?></span>
                                <a href="product-detail.php?slug=<?php echo e($prod['slug']); ?>">
                                    <h6 class="product-title"><?php echo e($prod['name']); ?></h6>
                                </a>
                                <div class="price-container justify-content-center">
                                    <span class="price-regular"><?php echo format_price($prod['offer_price'] ?? $prod['regular_price']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Size Guide Modal -->
<div class="modal fade" id="sizeGuideModal" tabindex="-1" aria-labelledby="sizeGuideModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius:16px;">
            <div class="modal-header border-0 bg-light" style="border-top-left-radius:16px; border-top-right-radius:16px;">
                <h5 class="modal-title fw-bold text-dark" id="sizeGuideModalLabel">Size Guide & Measurements</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <h6 class="fw-bold text-rose">Bras Sizing Chart (in Inches)</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered size-guide-table small">
                        <thead>
                            <tr>
                                <th>Size Code</th>
                                <th>Underbust</th>
                                <th>Overbust</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>32B</td><td>28-30</td><td>32-33</td></tr>
                            <tr><td>34B</td><td>30-32</td><td>34-35</td></tr>
                            <tr><td>36B</td><td>32-34</td><td>36-37</td></tr>
                            <tr><td>38B</td><td>34-36</td><td>38-39</td></tr>
                        </tbody>
                    </table>
                </div>

                <h6 class="fw-bold text-rose">Panties Sizing Chart (in Inches)</h6>
                <div class="table-responsive">
                    <table class="table table-bordered size-guide-table small">
                        <thead>
                            <tr>
                                <th>Size Code</th>
                                <th>Waist</th>
                                <th>Hip</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>S</td><td>26-27</td><td>36-37</td></tr>
                            <tr><td>M</td><td>28-29</td><td>38-39</td></tr>
                            <tr><td>L</td><td>30-32</td><td>40-42</td></tr>
                            <tr><td>XL</td><td>33-35</td><td>43-45</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Fullscreen Lightbox Modal -->
<div class="modal fade" id="lightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content bg-dark bg-opacity-95 text-white border-0">
            <!-- Modal Header -->
            <div class="modal-header border-0 p-3 d-flex justify-content-between align-items-center position-absolute top-0 start-0 end-0 z-3" style="background: linear-gradient(180deg, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0) 100%);">
                <div class="small text-white-50">
                    <span id="lightbox-counter" class="text-white fw-semibold">1 / <?php echo max(1, count($initial_gallery)); ?></span> &bull; <?php echo e($product['name']); ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-light rounded-circle d-flex align-items-center justify-content-center" id="lightbox-zoom-toggle" title="Toggle 2x Zoom" style="width: 40px; height: 40px;">
                        <i class="bi bi-zoom-in fs-5" id="lightbox-zoom-icon"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light rounded-circle d-flex align-items-center justify-content-center" data-bs-dismiss="modal" aria-label="Close" style="width: 40px; height: 40px;">
                        <i class="bi bi-x-lg fs-5"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body Image Viewport -->
            <div class="modal-body p-0 d-flex align-items-center justify-content-center position-relative overflow-hidden" id="lightbox-viewport" style="user-select: none;">
                <!-- Nav Prev Button -->
                <button type="button" class="btn btn-dark bg-opacity-50 text-white rounded-circle position-absolute start-0 ms-3 top-50 translate-middle-y z-3 d-flex align-items-center justify-content-center <?php echo count($initial_gallery) <= 1 ? 'd-none' : ''; ?>" id="lightbox-prev-btn" style="width: 48px; height: 48px;" aria-label="Previous">
                    <i class="bi bi-chevron-left fs-4"></i>
                </button>

                <!-- Centered Zoomable Lightbox Image -->
                <img src="<?php echo !empty($initial_gallery) ? e($initial_gallery[0]['src']) : ''; ?>" 
                     alt="<?php echo e($product['name']); ?>" 
                     id="lightbox-img" 
                     class="img-fluid" 
                     style="max-height: 82vh; max-width: 88vw; object-fit: contain; transition: transform 0.2s ease; transform-origin: center center; cursor: zoom-in;">

                <!-- Nav Next Button -->
                <button type="button" class="btn btn-dark bg-opacity-50 text-white rounded-circle position-absolute end-0 me-3 top-50 translate-middle-y z-3 d-flex align-items-center justify-content-center <?php echo count($initial_gallery) <= 1 ? 'd-none' : ''; ?>" id="lightbox-next-btn" style="width: 48px; height: 48px;" aria-label="Next">
                    <i class="bi bi-chevron-right fs-4"></i>
                </button>
            </div>

            <!-- Modal Bottom Thumbnail Ribbon -->
            <div class="modal-footer border-0 p-3 justify-content-center position-absolute bottom-0 start-0 end-0 z-3 <?php echo count($initial_gallery) <= 1 ? 'd-none' : ''; ?>" id="lightbox-footer-ribbon" style="background: linear-gradient(0deg, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0) 100%);">
                <div class="d-flex gap-2 overflow-x-auto pb-1" id="lightbox-thumbs-strip">
                    <?php foreach($initial_gallery as $idx => $img): ?>
                        <img src="<?php echo e($img['src']); ?>" 
                             data-index="<?php echo $idx; ?>" 
                             alt="thumb" 
                             class="lightbox-thumb rounded <?php echo $idx === 0 ? 'active' : 'opacity-75'; ?>" 
                             style="width: 48px; height: 62px; object-fit: cover;">
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Sticky Mobile Action Bar (100% Mobile Optimized) -->
<div id="pdp-sticky-mobile-bar" class="d-lg-none fixed-bottom bg-white border-top shadow-lg py-2 px-3" style="z-index: 1045; transform: translateY(115%); transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
    <div class="d-flex align-items-center gap-2 justify-content-between">
        <div class="small lh-sm flex-shrink-0" style="max-width: 110px;">
            <div class="fw-bold text-dark text-truncate" style="font-size: 13px;"><?php echo e($product['name']); ?></div>
            <div class="text-rose fw-bold small font-display mt-0" id="mobile-bar-price">
                <?php echo format_price(!empty($product['offer_price']) ? $product['offer_price'] : $product['regular_price']); ?>
            </div>
        </div>
        <div class="d-flex gap-2 flex-grow-1 justify-content-end">
            <button type="button" id="mobile-sticky-add-cart-btn" class="btn btn-sm btn-brand-outline rounded-pill fw-semibold py-2 px-2 flex-grow-1 d-flex align-items-center justify-content-center" style="font-size: 12.5px; height: 42px;">
                <i class="bi bi-bag-plus me-1"></i> Add
            </button>
            <button type="button" id="mobile-sticky-buy-now-btn" class="btn btn-sm btn-dark rounded-pill fw-bold py-2 px-3 flex-grow-1 shadow-sm text-nowrap d-flex align-items-center justify-content-center" style="font-size: 12.5px; height: 42px; background-color: #18181b; border: none;">
                <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Buy Now
            </button>
        </div>
    </div>
</div>

<!-- Inject variant details for JS swatches & combo logic -->
<script>
    window.isComboProduct = <?php echo $is_combo ? 'true' : 'false'; ?>;
    window.defaultProductPrice = "<?php echo format_price($product['offer_price'] ?? $product['regular_price']); ?>";
    window.productMasterPricing = {
        regular_price: <?php echo (float)$product['regular_price']; ?>,
        offer_price: <?php echo !empty($product['offer_price']) ? (float)$product['offer_price'] : 'null'; ?>
    };
    window.productVariants = <?php echo json_encode($variants); ?>;
    window.comboComponentsData = <?php echo json_encode($combo_components); ?>;
    window.productGalleryImages = <?php echo json_encode(array_map(function($g) { return $g['src']; }, $initial_gallery)); ?>;
    window.productGalleryMap = {
        general: <?php echo json_encode($gallery_general); ?>,
        byColor: <?php echo json_encode($gallery_by_color); ?>,
        all: <?php echo json_encode(array_map(function($g) { return 'uploads/' . $g['image_path']; }, $gallery)); ?>
    };

    const isComboProduct = window.isComboProduct;
    const defaultProductPrice = window.defaultProductPrice;
    const productVariants = window.productVariants;
    const comboComponentsData = window.comboComponentsData;
    const productGalleryImages = window.productGalleryImages;
    const productGalleryMap = window.productGalleryMap;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
