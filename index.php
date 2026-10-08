<?php
// index.php
// Public Homepage

$meta_title = 'Pookky | Elegant Ladies\' Lingerie & Innerwear';
$meta_desc = 'Shop premium comfort bras, panties, sleepwear, and shapewear directly from the manufacturer. Perfect fit guaranteed.';
require_once __DIR__ . '/includes/header.php';

// Fetch active homepage banners
$banners = [];
try {
    $banners = $pdo->query("SELECT * FROM banners WHERE status = 1 ORDER BY sort_order ASC, id DESC")->fetchAll();
} catch (PDOException $e) {
    $banners = [];
}

// Fetch active categories
$categories = [];
try {
    $categories = $pdo->query("SELECT * FROM categories WHERE status = 1 AND parent_id IS NULL ORDER BY sort_order ASC, id ASC LIMIT 4")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Fetch featured/new arrival/best seller products
$featured_products = [];
$new_arrivals = [];
$best_sellers = [];
try {
    // 1. Featured
    $f_stmt = $pdo->query("
        SELECT p.*, c.name as category_name, 
        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_featured DESC LIMIT 1) as main_image 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 1 AND p.is_featured = 1 
        ORDER BY p.id DESC LIMIT 4
    ");
    $featured_products = $f_stmt->fetchAll();

    // 2. New Arrivals
    $n_stmt = $pdo->query("
        SELECT p.*, c.name as category_name, 
        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_featured DESC LIMIT 1) as main_image 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 1 AND p.is_new_arrival = 1 
        ORDER BY p.id DESC LIMIT 4
    ");
    $new_arrivals = $n_stmt->fetchAll();

    // 3. Best Sellers
    $b_stmt = $pdo->query("
        SELECT p.*, c.name as category_name, 
        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_featured DESC LIMIT 1) as main_image 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 1 AND p.is_best_seller = 1 
        ORDER BY p.id DESC LIMIT 4
    ");
    $best_sellers = $b_stmt->fetchAll();

} catch (PDOException $e) {
    // Fail silently
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

<!-- Brand highlights -->
<section class="py-5 bg-white border-bottom">
    <div class="container text-center">
        <div class="row g-4 justify-content-center">
            <div class="col-6 col-md-3">
                <div class="fs-2 text-rose mb-2"><i class="bi bi-heart-pulse"></i></div>
                <h6 class="fw-bold mb-1">Premium Fabric Comfort</h6>
                <p class="text-muted small mb-0">Breathable cotton blends & smooth satin touch</p>
            </div>
            <div class="col-6 col-md-3">
                <div class="fs-2 text-rose mb-2"><i class="bi bi-scissors"></i></div>
                <h6 class="fw-bold mb-1">Tailored Fits</h6>
                <p class="text-muted small mb-0">Extensive variant cups size matrix</p>
            </div>
            <div class="col-6 col-md-3">
                <div class="fs-2 text-rose mb-2"><i class="bi bi-truck"></i></div>
                <h6 class="fw-bold mb-1">Free Delivery</h6>
                <p class="text-muted small mb-0">Flat rate <?php echo format_price(SHIPPING_FLAT_RATE); ?>, FREE for orders above <?php echo format_price(FREE_SHIPPING_THRESHOLD); ?></p>
            </div>
            <div class="col-6 col-md-3">
                <div class="fs-2 text-rose mb-2"><i class="bi bi-arrow-repeat"></i></div>
                <h6 class="fw-bold mb-1">Easy Returns</h6>
                <p class="text-muted small mb-0"><?php echo RETURN_WINDOW_DAYS; ?> days returns on damaged or wrong sizes</p>
            </div>
        </div>
    </div>
</section>

<!-- Category Highlights -->
<section class="py-4 py-md-5 bg-light-subtle">
    <div class="container">
        <h2 class="section-title text-center mb-1 mb-md-2">Shop by Category</h2>
        <p class="text-center text-muted mb-3 mb-md-4 brand-accent-tag"><?php echo e(get_setting('home_tagline_categories', 'Selected Collection Blocks')); ?></p>
        
        <div class="row g-3 g-md-4">
            <?php foreach ($categories as $cat): ?>
                <div class="col-6 col-md-3">
                    <div class="card glass-card text-center p-2 p-md-3 border-0 h-100" style="border-radius: 12px; overflow: hidden; background-color: var(--brand-white);">
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
                        <a href="products.php?category=<?php echo e($cat['slug']); ?>" class="btn btn-sm btn-brand-outline rounded-pill w-100 mt-auto py-1 py-md-2" style="font-size: 13px;">Explore</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Products section -->
<?php if (!empty($featured_products)): ?>
<section class="py-4 py-md-5 bg-white">
    <div class="container">
        <h2 class="section-title text-center mb-1 mb-md-2">Featured Products</h2>
        <p class="text-center text-muted mb-3 mb-md-4 brand-accent-tag"><?php echo e(get_setting('home_tagline_featured', 'Exclusive Picks of the Season')); ?></p>
        
        <div class="row g-3 g-md-4">
            <?php foreach ($featured_products as $prod): ?>
                <div class="col-6 col-md-3">
                    <div class="card product-card h-100">
                        <div class="img-container">
                            <div class="badge-overlay d-flex flex-column gap-1 align-items-start">
                                <?php if (($prod['product_type'] ?? '') === 'combo'): ?>
                                    <span class="badge bg-purple rounded-pill px-2 py-1 shadow-sm" style="font-size:10px;"><i class="bi bi-stars"></i> COMBO</span>
                                <?php endif; ?>
                                <span class="badge bg-danger rounded-pill px-2 py-1 shadow-sm" style="font-size:10px;">Featured</span>
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
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Best Sellers & New Arrivals Sections (Side by Side or grids) -->
<section class="py-4 py-md-5 bg-light-subtle">
    <div class="container">
        <!-- New Arrivals -->
        <div class="mb-5">
            <h2 class="section-title text-center mb-1 mb-md-2">New Arrivals</h2>
            <p class="text-center text-muted mb-3 mb-md-4 brand-accent-tag"><?php echo e(get_setting('home_tagline_new_arrivals', 'Fresh off the Looms')); ?></p>
            
            <div class="row g-3 g-md-4">
                <?php if (empty($new_arrivals)): ?>
                    <div class="col-12 text-center text-muted">No new arrivals available. Check back soon!</div>
                <?php else: ?>
                    <?php foreach ($new_arrivals as $prod): ?>
                        <div class="col-6 col-md-3">
                            <div class="card product-card h-100">
                                <div class="img-container">
                                    <?php if (($prod['product_type'] ?? '') === 'combo'): ?>
                                        <div class="badge-overlay">
                                            <span class="badge bg-purple rounded-pill px-2 py-1 shadow-sm" style="font-size:10px;"><i class="bi bi-stars"></i> COMBO</span>
                                        </div>
                                    <?php endif; ?>
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
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Best Sellers -->
        <div>
            <h2 class="section-title text-center mb-1 mb-md-2">Our Best Sellers</h2>
            <p class="text-center text-muted mb-3 mb-md-4 brand-accent-tag"><?php echo e(get_setting('home_tagline_best_sellers', 'Customer Favorites')); ?></p>
            
            <div class="row g-3 g-md-4">
                <?php if (empty($best_sellers)): ?>
                    <div class="col-12 text-center text-muted">No best sellers populated yet.</div>
                <?php else: ?>
                    <?php foreach ($best_sellers as $prod): ?>
                        <div class="col-6 col-md-3">
                            <div class="card product-card h-100">
                                <div class="img-container">
                                    <?php if (($prod['product_type'] ?? '') === 'combo'): ?>
                                        <div class="badge-overlay">
                                            <span class="badge bg-purple rounded-pill px-2 py-1 shadow-sm" style="font-size:10px;"><i class="bi bi-stars"></i> COMBO</span>
                                        </div>
                                    <?php endif; ?>
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
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
