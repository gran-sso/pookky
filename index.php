<?php
// index.php
// Public Homepage - Modern High-Converting Fashion & Innerwear Layout

$meta_title = 'Pookky | Elegant Ladies\' Lingerie & Innerwear';
$meta_desc = 'Shop premium comfort bras, panties, sleepwear, and shapewear directly from the manufacturer. Perfect fit guaranteed.';
require_once __DIR__ . '/includes/header.php';

// 1. Fetch active homepage banners
$banners = [];
try {
    $banners = $pdo->query("SELECT * FROM banners WHERE status = 1 ORDER BY sort_order ASC, id DESC")->fetchAll();
} catch (PDOException $e) {
    $banners = [];
}

// 2. Fetch active categories (up to 8 for rich responsive grid)
$categories = [];
try {
    $categories = $pdo->query("SELECT * FROM categories WHERE status = 1 AND parent_id IS NULL ORDER BY sort_order ASC, id ASC LIMIT 8")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// 3. Fetch products for Tabbed Showcase & Combos
$featured_products = [];
$new_arrivals = [];
$best_sellers = [];
$combo_products = [];
$home_reviews = [];

try {
    // New Arrivals (up to 8)
    $n_stmt = $pdo->query("
        SELECT p.*, c.name as category_name, 
        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_featured DESC LIMIT 1) as main_image 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 1 AND p.is_new_arrival = 1 
        ORDER BY p.id DESC LIMIT 8
    ");
    $new_arrivals = $n_stmt->fetchAll();

    // Best Sellers (up to 8)
    $b_stmt = $pdo->query("
        SELECT p.*, c.name as category_name, 
        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_featured DESC LIMIT 1) as main_image 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 1 AND p.is_best_seller = 1 
        ORDER BY p.id DESC LIMIT 8
    ");
    $best_sellers = $b_stmt->fetchAll();

    // Featured Picks (up to 8)
    $f_stmt = $pdo->query("
        SELECT p.*, c.name as category_name, 
        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_featured DESC LIMIT 1) as main_image 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 1 AND p.is_featured = 1 
        ORDER BY p.id DESC LIMIT 8
    ");
    $featured_products = $f_stmt->fetchAll();

    // Value Bundles & Combos Spotlight (up to 12)
    $c_stmt = $pdo->query("
        SELECT p.*, c.name as category_name, 
        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_featured DESC LIMIT 1) as main_image,
        (SELECT COUNT(*) FROM combo_components WHERE combo_product_id = p.id) as component_count
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 1 AND p.product_type = 'combo'
        ORDER BY p.is_featured DESC, p.id DESC LIMIT 12
    ");
    $combo_products = $c_stmt->fetchAll();

    // Customer Reviews from Database (only queried if enabled via Admin Settings)
    $reviews_enabled = get_setting('home_reviews_enabled', '0') === '1';
    if ($reviews_enabled) {
        $r_stmt = $pdo->query("
            SELECT r.*, c.name as customer_name, p.name as product_name, p.slug as product_slug 
            FROM reviews r 
            JOIN customers c ON r.customer_id = c.id 
            JOIN products p ON r.product_id = p.id 
            WHERE r.status = 1 
            ORDER BY r.rating DESC, r.id DESC 
            LIMIT 3
        ");
        $home_reviews = $r_stmt->fetchAll();

        if (empty($home_reviews)) {
            $home_reviews = [
                [
                    'customer_name' => 'Ananya S.',
                    'city' => 'Bangalore',
                    'product_name' => 'Elegance Lace Bra (34B)',
                    'rating' => 5,
                    'comment' => 'The fit is absolutely spot on! French lace is so gentle on the skin, zero digging or chafing even through long 10-hour work days.'
                ],
                [
                    'customer_name' => 'Meera R.',
                    'city' => 'Kochi',
                    'product_name' => 'Super Saver Combo Set',
                    'rating' => 5,
                    'comment' => 'Customising different sizes for the bra and panty in the bundle was a lifesaver. The breathable cotton feels wonderfully luxurious.'
                ],
                [
                    'customer_name' => 'Pooja M.',
                    'city' => 'Mumbai',
                    'product_name' => 'Satin Nightgown & Robe Set',
                    'rating' => 5,
                    'comment' => 'Arrived in discreet, beautiful packaging. The satin drape is so flattering and elegant. Truly happy with my purchase!'
                ]
            ];
        }
    }

} catch (PDOException $e) {
    // Fail gracefully
}

// Helper to render responsive product grid cards
if (!function_exists('render_home_product_card')) {
    function render_home_product_card($prod) {
        $savings_pct = 0;
        if (!empty($prod['offer_price']) && !empty($prod['regular_price']) && $prod['regular_price'] > $prod['offer_price']) {
            $savings_pct = round((($prod['regular_price'] - $prod['offer_price']) / $prod['regular_price']) * 100);
        }
        ?>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card product-card h-100 shadow-sm border-0">
                <div class="img-container position-relative">
                    <div class="badge-overlay d-flex flex-column gap-1 align-items-start">
                        <?php if (($prod['product_type'] ?? '') === 'combo'): ?>
                            <span class="badge bg-purple rounded-pill px-2 py-1 shadow-sm" style="font-size:10px;"><i class="bi bi-stars"></i> COMBO</span>
                        <?php endif; ?>
                        <?php if ($savings_pct > 0): ?>
                            <span class="badge bg-danger rounded-pill px-2 py-1 shadow-sm" style="font-size:10px;">SAVE <?php echo $savings_pct; ?>%</span>
                        <?php elseif (!empty($prod['is_new_arrival'])): ?>
                            <span class="badge bg-dark rounded-pill px-2 py-1 shadow-sm" style="font-size:10px;">NEW</span>
                        <?php elseif (!empty($prod['is_featured'])): ?>
                            <span class="badge bg-danger rounded-pill px-2 py-1 shadow-sm" style="font-size:10px;">FEATURED</span>
                        <?php endif; ?>
                    </div>
                    <div class="wishlist-overlay wishlist-toggle" data-product-id="<?php echo $prod['id']; ?>" title="Add to Wishlist">
                        <i class="bi bi-heart"></i>
                    </div>
                    <a href="product-detail.php?slug=<?php echo e($prod['slug']); ?>">
                        <?php if ($prod['main_image']): ?>
                            <img src="uploads/<?php echo e($prod['main_image']); ?>" alt="<?php echo e($prod['name']); ?>" loading="lazy">
                        <?php else: ?>
                            <div class="w-100 h-100 bg-secondary d-flex align-items-center justify-content-center text-white"><i class="bi bi-image fs-1"></i></div>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="product-details text-center">
                    <span class="product-category"><?php echo e($prod['category_name']); ?></span>
                    <a href="product-detail.php?slug=<?php echo e($prod['slug']); ?>">
                        <h6 class="product-title text-truncate-2"><?php echo e($prod['name']); ?></h6>
                    </a>
                    <div class="price-container justify-content-center">
                        <span class="price-regular"><?php echo format_price($prod['offer_price'] ?? $prod['regular_price']); ?></span>
                        <?php if ($prod['offer_price']): ?>
                            <span class="price-original"><?php echo format_price($prod['regular_price']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
?>

<!-- 1. Hero Promo Banner Slider -->
<div id="heroBannerCarousel" class="carousel slide hero-slider" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <?php foreach ($banners as $index => $banner): ?>
            <button type="button" data-bs-target="#heroBannerCarousel" data-bs-slide-to="<?php echo $index; ?>" class="<?php echo $index === 0 ? 'active' : ''; ?>" aria-current="true" aria-label="Slide <?php echo $index + 1; ?>"></button>
        <?php endforeach; ?>
    </div>
    <div class="carousel-inner">
        <?php if (empty($banners)): ?>
            <!-- Fallback Slide if no banners configured -->
            <div class="carousel-item active hero-item bg-brand-blush">
                <div class="hero-item-inner d-flex align-items-center w-100">
                    <div class="container">
                        <div class="hero-content mx-auto mx-md-0 text-center text-md-start">
                            <span class="brand-accent-tag mb-2 d-block">Premium Comfort</span>
                            <h1 class="hero-heading mb-3">Designed for the Modern Woman</h1>
                            <p class="text-muted mb-4 lead">Experience luxury materials and absolute support designed to fit you perfectly.</p>
                            <a href="products.php" class="btn btn-brand">Shop Lingerie Collection</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($banners as $index => $banner): ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?> hero-item">
                    <picture class="hero-picture">
                        <?php if (!empty($banner['mobile_image'])): ?>
                            <source media="(max-width: 768px)" srcset="uploads/<?php echo e($banner['mobile_image']); ?>">
                        <?php endif; ?>
                        <img src="uploads/<?php echo e($banner['image_path']); ?>" alt="<?php echo e($banner['title']); ?>" class="hero-bg-img" <?php echo $index === 0 ? 'loading="eager" fetchpriority="high"' : 'loading="lazy"'; ?>>
                    </picture>
                    <div class="hero-item-inner d-flex align-items-center w-100">
                        <div class="container">
                            <div class="hero-content">
                                <span class="brand-accent-tag mb-2 d-block"><?php echo e(!empty($banner['tagline']) ? $banner['tagline'] : 'Featured Collection'); ?></span>
                                <h2 class="hero-heading mb-3"><?php echo e($banner['title']); ?></h2>
                                <p class="text-muted mb-4 lead"><?php echo e(!empty($banner['subtitle']) ? $banner['subtitle'] : 'Elegant designs, soft fabrics, and customizable sizing matrices.'); ?></p>
                                <?php if (!empty($banner['link_url'])): ?>
                                    <a href="<?php echo e($banner['link_url']); ?>" class="btn btn-brand"><?php echo e(!empty($banner['btn_text']) ? $banner['btn_text'] : 'Shop Collection'); ?></a>
                                <?php else: ?>
                                    <a href="products.php" class="btn btn-brand"><?php echo e(!empty($banner['btn_text']) ? $banner['btn_text'] : 'View Shop'); ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button class="carousel-control-prev d-none d-md-flex" type="button" data-bs-target="#heroBannerCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next d-none d-md-flex" type="button" data-bs-target="#heroBannerCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
    </button>
</div>

<!-- 2. Quick Category Stories / Circle Navigation Strip (Modern Mobile First) -->
<section class="quick-category-strip py-3">
    <div class="container">
        <div class="quick-category-track d-flex gap-3 gap-md-4 align-items-center justify-content-start justify-content-md-center">
            <?php foreach ($categories as $cat): ?>
                <a href="products.php?category=<?php echo e($cat['slug']); ?>" class="quick-category-item flex-shrink-0">
                    <div class="quick-category-avatar-wrap mb-1">
                        <?php if (!empty($cat['image_path'])): ?>
                            <img src="uploads/<?php echo e($cat['image_path']); ?>" alt="<?php echo e($cat['name']); ?>" class="quick-category-img" loading="eager">
                        <?php else: ?>
                            <div class="quick-category-placeholder d-flex align-items-center justify-content-center bg-brand-blush text-rose">
                                <i class="bi bi-tag-fill"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="quick-category-name"><?php echo e($cat['name']); ?></span>
                </a>
            <?php endforeach; ?>

            <?php if (!empty($has_active_combos)): ?>
                <a href="products.php?type=combo" class="quick-category-item flex-shrink-0">
                    <div class="quick-category-avatar-wrap mb-1 combo-avatar-wrap">
                        <div class="quick-category-placeholder d-flex align-items-center justify-content-center bg-purple text-white">
                            <i class="bi bi-stars"></i>
                        </div>
                    </div>
                    <span class="quick-category-name text-purple">Combos</span>
                </a>
            <?php endif; ?>

            <a href="products.php" class="quick-category-item flex-shrink-0">
                <div class="quick-category-avatar-wrap mb-1 all-avatar-wrap">
                    <div class="quick-category-placeholder d-flex align-items-center justify-content-center bg-light text-secondary">
                        <i class="bi bi-grid-3x3-gap-fill"></i>
                    </div>
                </div>
                <span class="quick-category-name text-muted">All Styles</span>
            </a>
        </div>
    </div>
</section>

<!-- 3. Sleek Brand Trust & Value Prop Bar -->
<section class="trust-highlights-strip py-3">
    <div class="container">
        <div class="row g-2 g-md-0 align-items-center">
            <div class="col-6 col-md-3">
                <div class="trust-card-item justify-content-center justify-content-md-start">
                    <div class="trust-icon-wrap"><i class="bi bi-patch-check-fill"></i></div>
                    <div class="text-start">
                        <div class="fw-bold text-dark" style="font-size: 13px; line-height: 1.2;">Premium Fabric</div>
                        <small class="text-muted d-none d-sm-block" style="font-size: 11px;">Breathable modal & cotton</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="trust-card-item justify-content-center justify-content-md-start">
                    <div class="trust-icon-wrap"><i class="bi bi-truck"></i></div>
                    <div class="text-start">
                        <div class="fw-bold text-dark" style="font-size: 13px; line-height: 1.2;">Free Shipping</div>
                        <small class="text-muted d-none d-sm-block" style="font-size: 11px;">On orders > <?php echo format_price(FREE_SHIPPING_THRESHOLD); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="trust-card-item justify-content-center justify-content-md-start">
                    <div class="trust-icon-wrap"><i class="bi bi-arrow-repeat"></i></div>
                    <div class="text-start">
                        <div class="fw-bold text-dark" style="font-size: 13px; line-height: 1.2;"><?php echo RETURN_WINDOW_DAYS; ?> Days Return</div>
                        <small class="text-muted d-none d-sm-block" style="font-size: 11px;">Hassle-free size exchange</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="trust-card-item justify-content-center justify-content-md-start">
                    <div class="trust-icon-wrap"><i class="bi bi-shield-lock-fill"></i></div>
                    <div class="text-start">
                        <div class="fw-bold text-dark" style="font-size: 13px; line-height: 1.2;">Discreet Packing</div>
                        <small class="text-muted d-none d-sm-block" style="font-size: 11px;">100% private delivery</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 4. Value Bundles & Super Saver Combos Spotlight (Horizontal Scroll Rail) -->
<?php if (!empty($combo_products)): ?>
<section class="py-4 py-md-5 combo-spotlight-section border-bottom" style="background: linear-gradient(180deg, #fff5f8 0%, #ffffff 100%);">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-end mb-4 gap-3">
            <div>
                <span class="badge bg-purple rounded-pill px-3 py-1 mb-2 shadow-sm" style="font-size: 11px; letter-spacing: 0.5px;">
                    <i class="bi bi-stars me-1"></i> SUPER SAVER DEALS
                </span>
                <h2 class="section-title mb-1">Value Bundles & Combo Sets</h2>
                <p class="text-muted mb-0 brand-accent-tag"><?php echo e(get_setting('home_tagline_combos', 'Curate your favorite matching pieces & save more together')); ?></p>
            </div>
            
            <div class="d-flex align-items-center gap-2 gap-md-3 align-self-stretch align-self-md-auto justify-content-between justify-content-md-end">
                <div class="slider-nav-arrows d-flex align-items-center gap-2">
                    <button type="button" class="slider-arrow-btn slider-prev" data-target="#comboScrollTrack" aria-label="Previous combos" title="Previous" disabled>
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button type="button" class="slider-arrow-btn slider-next" data-target="#comboScrollTrack" aria-label="Next combos" title="Next">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
                <a href="products.php?type=combo" class="btn btn-sm btn-outline-dark rounded-pill px-3 py-2">
                    Explore All Bundles <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
        
        <style>
            #comboScrollTrack {
                display: flex !important;
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                align-items: stretch !important;
                gap: 1.5rem !important;
                overflow-x: auto !important;
                overflow-y: hidden !important;
                scroll-behavior: smooth !important;
                scroll-snap-type: x mandatory !important;
                -webkit-overflow-scrolling: touch !important;
                padding: 6px 2px 14px 2px !important;
                width: 100% !important;
                scrollbar-width: none !important;
                -ms-overflow-style: none !important;
            }
            #comboScrollTrack::-webkit-scrollbar { display: none !important; }
            #comboScrollTrack .product-scroll-card {
                scroll-snap-align: start !important;
                flex: 0 0 calc(25% - 1.125rem) !important;
                width: calc(25% - 1.125rem) !important;
                min-width: 250px !important;
                max-width: 320px !important;
                flex-shrink: 0 !important;
                box-sizing: border-box !important;
            }
            @media (max-width: 1199.98px) {
                #comboScrollTrack .product-scroll-card {
                    flex: 0 0 calc(33.333% - 1rem) !important;
                    width: calc(33.333% - 1rem) !important;
                    min-width: 230px !important;
                    max-width: 300px !important;
                }
            }
            @media (max-width: 767.98px) {
                #comboScrollTrack { gap: 0.85rem !important; padding-bottom: 10px !important; }
                #comboScrollTrack .product-scroll-card {
                    flex: 0 0 calc(55% - 0.425rem) !important;
                    width: calc(55% - 0.425rem) !important;
                    min-width: 180px !important;
                    max-width: 260px !important;
                }
            }
            @media (max-width: 480px) {
                #comboScrollTrack .product-scroll-card {
                    flex: 0 0 calc(75% - 0.425rem) !important;
                    width: calc(75% - 0.425rem) !important;
                    min-width: 200px !important;
                    max-width: 280px !important;
                }
            }
            @keyframes railPeekNudge {
                0% { transform: translateX(0); }
                35% { transform: translateX(-70px); }
                65% { transform: translateX(-70px); }
                100% { transform: translateX(0); }
            }
            @keyframes railPeekNudgeMobile {
                0% { transform: translateX(0); }
                35% { transform: translateX(-45px); }
                65% { transform: translateX(-45px); }
                100% { transform: translateX(0); }
            }
            .product-scroll-track.rail-peek-active .product-scroll-card {
                animation: railPeekNudge 1.1s cubic-bezier(0.25, 1, 0.5, 1) !important;
                will-change: transform;
            }
            @media (max-width: 767.98px) {
                .product-scroll-track.rail-peek-active .product-scroll-card {
                    animation-name: railPeekNudgeMobile !important;
                }
            }
            .combo-spotlight-section .product-scroll-wrapper {
                overflow: hidden !important;
            }
        </style>

        <div class="product-scroll-wrapper position-relative">
            <div class="product-scroll-track" id="comboScrollTrack">
                <?php foreach ($combo_products as $prod): ?>
                    <?php 
                        $savings_pct = 0;
                        if (!empty($prod['offer_price']) && !empty($prod['regular_price']) && $prod['regular_price'] > $prod['offer_price']) {
                            $savings_pct = round((($prod['regular_price'] - $prod['offer_price']) / $prod['regular_price']) * 100);
                        }
                    ?>
                    <div class="product-scroll-card">
                        <div class="card product-card h-100 combo-product-card shadow-sm border-0" style="background: #ffffff; border-radius: 12px; overflow: hidden;">
                            <div class="img-container position-relative">
                                <div class="badge-overlay d-flex flex-column gap-1 align-items-start">
                                    <span class="badge bg-purple rounded-pill px-2 py-1 shadow-sm" style="font-size:10px;">
                                        <i class="bi bi-stars"></i> <?php echo !empty($prod['component_count']) ? intval($prod['component_count']) . '-Piece Set' : 'COMBO SET'; ?>
                                    </span>
                                    <?php if ($savings_pct > 0): ?>
                                        <span class="badge bg-danger rounded-pill px-2 py-1 shadow-sm" style="font-size:10px;">
                                            SAVE <?php echo $savings_pct; ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="wishlist-overlay wishlist-toggle" data-product-id="<?php echo $prod['id']; ?>">
                                    <i class="bi bi-heart"></i>
                                </div>
                                <a href="product-detail.php?slug=<?php echo e($prod['slug']); ?>">
                                    <?php if ($prod['main_image']): ?>
                                        <img src="uploads/<?php echo e($prod['main_image']); ?>" alt="<?php echo e($prod['name']); ?>" loading="lazy">
                                    <?php else: ?>
                                        <div class="w-100 h-100 bg-secondary d-flex align-items-center justify-content-center text-white"><i class="bi bi-image fs-1"></i></div>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="product-details text-center d-flex flex-column p-3">
                                <span class="product-category text-purple fw-semibold"><i class="bi bi-layers me-1"></i><?php echo e($prod['category_name']); ?></span>
                                <a href="product-detail.php?slug=<?php echo e($prod['slug']); ?>">
                                    <h6 class="product-title text-truncate-2"><?php echo e($prod['name']); ?></h6>
                                </a>
                                <div class="price-container justify-content-center mb-3">
                                    <span class="price-regular"><?php echo format_price($prod['offer_price'] ?? $prod['regular_price']); ?></span>
                                    <?php if ($prod['offer_price']): ?>
                                        <span class="price-original"><?php echo format_price($prod['regular_price']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <a href="product-detail.php?slug=<?php echo e($prod['slug']); ?>" class="btn btn-sm btn-brand-outline rounded-pill w-100 mt-auto py-2" style="font-size: 12px; font-weight: 600;">
                                    <i class="bi bi-sliders2-vertical me-1"></i> Customise Bundle
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- 5. Interactive Tabbed Product Showcase (Replaces repetitive static 4-grids) -->
<section class="py-4 py-md-5 bg-white">
    <div class="container">
        <!-- Section Header with Tabs & CTA -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-end mb-4 gap-3">
            <div>
                <span class="brand-accent-tag mb-1 d-block text-uppercase" style="letter-spacing: 1px; font-size: 11px;">Curated Collection</span>
                <h2 class="section-title mb-0">Trending Collections</h2>
            </div>

            <!-- Tab Pills Selector -->
            <ul class="nav nav-pills custom-pills-nav" id="showcasePills" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active rounded-pill px-3 py-2" id="pill-new-btn" data-bs-toggle="pill" data-bs-target="#pill-new" type="button" role="tab" aria-controls="pill-new" aria-selected="true">
                        <i class="bi bi-sparkles me-1 text-rose"></i> New Arrivals
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2" id="pill-best-btn" data-bs-toggle="pill" data-bs-target="#pill-best" type="button" role="tab" aria-controls="pill-best" aria-selected="false">
                        <i class="bi bi-fire me-1 text-danger"></i> Best Sellers
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2" id="pill-feat-btn" data-bs-toggle="pill" data-bs-target="#pill-feat" type="button" role="tab" aria-controls="pill-feat" aria-selected="false">
                        <i class="bi bi-star-fill me-1 text-warning"></i> Featured Picks
                    </button>
                </li>
            </ul>

            <a href="products.php" class="btn btn-sm btn-outline-dark rounded-pill px-3 py-2 d-none d-md-inline-flex align-items-center">
                Explore All Products <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <!-- Tab Content Panes -->
        <div class="tab-content" id="showcasePillsContent">
            <!-- 1. New Arrivals Pane -->
            <div class="tab-pane fade show active" id="pill-new" role="tabpanel" aria-labelledby="pill-new-btn">
                <div class="row g-3 g-md-4">
                    <?php if (empty($new_arrivals)): ?>
                        <div class="col-12 text-center text-muted py-4">No new arrivals available right now.</div>
                    <?php else: ?>
                        <?php foreach ($new_arrivals as $prod) render_home_product_card($prod); ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. Best Sellers Pane -->
            <div class="tab-pane fade" id="pill-best" role="tabpanel" aria-labelledby="pill-best-btn">
                <div class="row g-3 g-md-4">
                    <?php if (empty($best_sellers)): ?>
                        <div class="col-12 text-center text-muted py-4">No best sellers populated yet.</div>
                    <?php else: ?>
                        <?php foreach ($best_sellers as $prod) render_home_product_card($prod); ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 3. Featured Picks Pane -->
            <div class="tab-pane fade" id="pill-feat" role="tabpanel" aria-labelledby="pill-feat-btn">
                <div class="row g-3 g-md-4">
                    <?php if (empty($featured_products)): ?>
                        <div class="col-12 text-center text-muted py-4">No featured products selected.</div>
                    <?php else: ?>
                        <?php foreach ($featured_products as $prod) render_home_product_card($prod); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Mobile View All CTA -->
        <div class="text-center mt-4 d-md-none">
            <a href="products.php" class="btn btn-brand-outline rounded-pill px-4 py-2 w-100" style="font-size: 13px;">
                View All Products <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</section>

<!-- 6. Editorial Lifestyle Banner ("The Perfect Fit Guarantee") -->
<section class="py-4 py-md-5 my-2">
    <div class="container">
        <div class="card editorial-fit-card p-4 p-md-5 shadow-sm">
            <div class="row g-4 align-items-center">
                <div class="col-12 col-md-7">
                    <span class="badge bg-white text-rose rounded-pill px-3 py-1 mb-3 shadow-sm fw-bold" style="font-size: 11px; letter-spacing: 0.5px;">
                        <i class="bi bi-heart-fill text-rose me-1"></i> THE PERFECT FIT GUARANTEE
                    </span>
                    <h2 class="display-6 fw-bold text-dark mb-3" style="font-family: var(--font-display); line-height: 1.2;">
                        Comfort That Feels Tailored For You
                    </h2>
                    <p class="text-muted lead mb-4" style="font-size: 1rem; line-height: 1.6;">
                        No uncomfortable pinching. No awkward riding up. From 30B to 46B precision cup sizing and breathable cotton blends, our garments move gracefully with you all day long.
                    </p>
                    <div class="d-flex flex-wrap gap-2 gap-md-3">
                        <a href="size-guide.php" class="btn btn-brand rounded-pill px-4 py-2">
                            <i class="bi bi-rulers me-1"></i> View Interactive Size Guide
                        </a>
                        <a href="products.php" class="btn btn-outline-dark rounded-pill px-4 py-2">
                            Shop All Collections <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <div class="col-12 col-md-5 text-center d-none d-md-block">
                    <div class="p-4 bg-white rounded-4 shadow-sm text-center mx-auto" style="max-width: 310px; border: 1.5px dashed rgba(225, 43, 86, 0.35);">
                        <div class="fs-1 text-rose mb-2"><i class="bi bi-shield-check"></i></div>
                        <h5 class="fw-bold text-dark mb-1">Easy Size Exchange</h5>
                        <p class="text-muted small mb-3">If your cup or waist size doesn't feel 100% ideal, we'll swap it swiftly with zero hassle.</p>
                        <span class="badge bg-brand-blush text-rose fw-semibold px-3 py-2 rounded-pill" style="font-size: 11px;">
                            <i class="bi bi-stars me-1"></i> 100% Fit Satisfaction
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 7. Shop by Category (Uncapped responsive grid) -->
<section class="py-4 py-md-5 bg-light-subtle">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <span class="brand-accent-tag mb-1 d-block text-uppercase" style="letter-spacing: 1px; font-size: 11px;">Categories</span>
                <h2 class="section-title mb-0">Shop by Category</h2>
            </div>
            <a href="products.php" class="btn btn-sm btn-outline-dark rounded-pill px-3 py-2 d-none d-md-inline-flex">
                All Collections <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        
        <div class="row g-3 g-md-4">
            <?php foreach ($categories as $cat): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card glass-card text-center p-2 p-md-3 border-0 h-100 shadow-sm" style="border-radius: 14px; overflow: hidden; background-color: var(--brand-white); transition: transform 0.25s ease;">
                        <a href="products.php?category=<?php echo e($cat['slug']); ?>" class="d-block text-decoration-none">
                            <?php if ($cat['image_path']): ?>
                                <img src="uploads/<?php echo e($cat['image_path']); ?>" alt="<?php echo e($cat['name']); ?>" class="img-fluid rounded mb-2 mb-md-3" style="aspect-ratio: 1/1; object-fit: cover; width: 100%;">
                            <?php else: ?>
                                <div class="bg-light text-muted rounded mb-2 mb-md-3 d-flex align-items-center justify-content-center" style="aspect-ratio: 1/1; width: 100%;">
                                    <i class="bi bi-tag fs-2 text-secondary"></i>
                                </div>
                            <?php endif; ?>
                            <h3 class="category-card-title mb-1 mb-md-2 text-truncate"><?php echo e($cat['name']); ?></h3>
                        </a>
                        <p class="text-muted small mb-2 mb-md-3 text-truncate d-none d-sm-block"><?php echo e($cat['description']); ?></p>
                        <a href="products.php?category=<?php echo e($cat['slug']); ?>" class="btn btn-sm btn-brand-outline rounded-pill w-100 mt-auto py-1 py-md-2" style="font-size: 12px; font-weight: 600;">
                            Explore <?php echo e($cat['name']); ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 8. Customer Testimonials / Social Proof Strip (Toggleable via Admin Settings) -->
<?php if (!empty($reviews_enabled) && !empty($home_reviews)): ?>
<section class="py-4 py-md-5 bg-white border-top">
    <div class="container">
        <div class="text-center mb-4">
            <span class="brand-accent-tag d-block mb-1 text-uppercase" style="letter-spacing: 1px; font-size: 11px;">Real Reviews &bull; Real Women</span>
            <h2 class="section-title mb-2">Loved for Comfort & Fit</h2>
            <div class="d-flex align-items-center justify-content-center gap-1 text-warning mb-1">
                <i class="bi bi-star-fill"></i>
                <i class="bi bi-star-fill"></i>
                <i class="bi bi-star-fill"></i>
                <i class="bi bi-star-fill"></i>
                <i class="bi bi-star-fill"></i>
                <span class="text-dark fw-bold ms-2" style="font-size: 14px;">4.9 / 5.0</span>
            </div>
            <small class="text-muted">Over 1,200+ satisfied customers across India</small>
        </div>

        <div class="row g-3 g-md-4">
            <?php foreach ($home_reviews as $rev): ?>
                <div class="col-12 col-md-4">
                    <div class="card h-100 p-3 p-md-4 review-testimonial-card shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="text-warning small">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <i class="bi bi-star-fill <?php echo $i <= $rev['rating'] ? '' : 'text-muted'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill" style="font-size: 10px;">
                                <i class="bi bi-patch-check-fill me-1"></i>Verified Buyer
                            </span>
                        </div>
                        <p class="text-secondary mb-3 small" style="line-height: 1.6; font-style: italic;">
                            "<?php echo e($rev['comment']); ?>"
                        </p>
                        <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="d-block text-dark small"><?php echo e($rev['customer_name']); ?></strong>
                                <?php if (!empty($rev['city'])): ?>
                                    <small class="text-muted" style="font-size: 10px;"><?php echo e($rev['city']); ?></small>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($rev['product_name'])): ?>
                                <small class="text-rose text-truncate ms-2 fw-medium" style="max-width: 140px; font-size: 11px;">
                                    <?php echo e($rev['product_name']); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
