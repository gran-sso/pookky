<?php
// includes/header.php
// Public website header navigation layout

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';

// Cart counts aggregator
$header_cart_count = 0;
$session_id = session_id();
$customer_id = $_SESSION['customer_id'] ?? null;

try {
    if ($customer_id) {
        $cart_stmt = $pdo->prepare("SELECT COUNT(id) as count FROM cart WHERE customer_id = ?");
        $cart_stmt->execute([$customer_id]);
    } else {
        $cart_stmt = $pdo->prepare("SELECT COUNT(id) as count FROM cart WHERE session_id = ? AND customer_id IS NULL");
        $cart_stmt->execute([$session_id]);
    }
    $cart_row = $cart_stmt->fetch();
    $header_cart_count = (int)($cart_row['count'] ?? 0);
} catch (PDOException $e) {
    $header_cart_count = 0;
}

// Wishlist counts
$header_wishlist_count = 0;
if ($customer_id) {
    try {
        $wish_stmt = $pdo->prepare("SELECT COUNT(id) as count FROM wishlist WHERE customer_id = ?");
        $wish_stmt->execute([$customer_id]);
        $wish_row = $wish_stmt->fetch();
        $header_wishlist_count = (int)($wish_row['count'] ?? 0);
    } catch (PDOException $e) {
        $header_wishlist_count = 0;
    }
}

// Fetch configured categories for navigation menu
$menu_categories = [];
try {
    $nav_cat_setting = get_setting('nav_menu_categories', '');
    if (!empty($nav_cat_setting)) {
        $selected_ids = array_filter(array_map('intval', explode(',', $nav_cat_setting)));
        if (!empty($selected_ids)) {
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE id IN ($placeholders) AND status = 1 ORDER BY FIELD(id, " . implode(',', $selected_ids) . ")");
            $stmt->execute($selected_ids);
            $menu_categories = $stmt->fetchAll();
        }
    }
    
    // Fallback if no specific categories selected
    if (empty($menu_categories)) {
        $menu_categories = $pdo->query("SELECT * FROM categories WHERE status = 1 AND parent_id IS NULL ORDER BY id ASC LIMIT 4")->fetchAll();
    }
} catch (PDOException $e) {
    $menu_categories = [];
}

// Load Dynamic Website Theme Settings
$theme_primary_color = get_setting('theme_primary_color', '#E12B56');
$theme_primary_hover = get_setting('theme_primary_hover', '#C21845');
$theme_bg_canvas = get_setting('theme_bg_canvas', '#FFFFFF');
$theme_bg_surface = get_setting('theme_bg_surface', '#FFF1F5');
$theme_heading_color = get_setting('theme_heading_color', '#18181B');
$theme_body_color = get_setting('theme_body_color', '#374151');
$theme_card_border = get_setting('theme_card_border', '#E5E7EB');
$theme_font_pairing = get_setting('theme_font_pairing', 'modern_jakarta');
$theme_font_base_size = get_setting('theme_font_base_size', '16px');
$theme_navbar_font_size = get_setting('theme_navbar_font_size', '15px');
$theme_button_radius = get_setting('theme_button_radius', 'pill');

$btn_radius_css = '50px';
if ($theme_button_radius === 'rounded') $btn_radius_css = '10px';
if ($theme_button_radius === 'square') $btn_radius_css = '4px';

// Font Configuration
switch ($theme_font_pairing) {
    case 'modern_outfit':
        $google_font_url = 'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap';
        $font_display_css = "'CurrencySafe', 'Outfit', 'Plus Jakarta Sans', -apple-system, sans-serif";
        $font_ui_css = "'CurrencySafe', 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        break;
    case 'editorial_playfair':
        $google_font_url = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,500;0,600;0,700;0,800;1,500;1,700&display=swap';
        $font_display_css = "'CurrencySafe', 'Playfair Display', Georgia, serif";
        $font_ui_css = "'CurrencySafe', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        break;
    case 'classic_bodoni':
        $google_font_url = 'https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400;0,6..96,500;0,6..96,600;0,6..96,700;1,6..96,400;1,6..96,500&family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap';
        $font_display_css = "'CurrencySafe', 'Bodoni Moda', Didot, Georgia, serif";
        $font_ui_css = "'CurrencySafe', 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        break;
    case 'clean_inter':
        $google_font_url = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap';
        $font_display_css = "'CurrencySafe', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        $font_ui_css = "'CurrencySafe', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        break;
    case 'modern_jakarta':
    default:
        $google_font_url = 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&display=swap';
        $font_display_css = "'CurrencySafe', 'Plus Jakarta Sans', 'Montserrat', sans-serif";
        $font_ui_css = "'CurrencySafe', 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($meta_title) ? e($meta_title) : SITE_NAME . ' | Premium Ladies\' Innerwear'; ?></title>
    <meta name="description" content="<?php echo isset($meta_desc) ? e($meta_desc) : 'Discover premium comfort, elegant fits, and luxurious fabrics with Pookky innerwear.'; ?>">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Dynamic Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?php echo $google_font_url; ?>" rel="stylesheet">
    <!-- Global Style -->
    <link rel="stylesheet" href="assets/css/style.css?v=2.5">
    
    <!-- Dynamic Theme Custom Properties & Overrides -->
    <style>
        :root {
            --brand-rose-gold: <?php echo e($theme_primary_color); ?>;
            --brand-primary: <?php echo e($theme_primary_color); ?>;
            --brand-primary-hover: <?php echo e($theme_primary_hover); ?>;
            --brand-cream: <?php echo e($theme_bg_canvas); ?>;
            --brand-mauve: <?php echo e($theme_heading_color); ?>;
            --brand-charcoal: <?php echo e($theme_body_color); ?>;
            --brand-blush: <?php echo e($theme_bg_surface); ?>;
            --brand-card-border: <?php echo e($theme_card_border); ?>;
            --font-display: <?php echo $font_display_css; ?>;
            --font-ui: <?php echo $font_ui_css; ?>;
            --font-heading: <?php echo $font_display_css; ?>;
            --font-body: <?php echo $font_ui_css; ?>;
            --btn-radius: <?php echo $btn_radius_css; ?>;
        }
        body {
            background-color: var(--brand-cream) !important;
            color: var(--brand-charcoal) !important;
            font-family: var(--font-ui) !important;
            font-size: <?php echo e($theme_font_base_size); ?> !important;
            -webkit-font-smoothing: antialiased;
        }
        h1, h2, h3, .font-display, .display-hero, .page-title, .section-title {
            font-family: var(--font-display) !important;
            color: var(--brand-mauve) !important;
            font-weight: 700 !important;
        }
        .hero-heading {
            font-family: var(--font-display) !important;
            color: var(--brand-mauve);
            font-weight: 700 !important;
        }
        @media (max-width: 768px) {
            .hero-heading,
            .hero-content h1,
            .hero-content h2 {
                color: #ffffff !important;
                text-shadow: 0 2px 10px rgba(0, 0, 0, 0.75) !important;
            }
            .hero-content .brand-accent-tag {
                color: #ffd2dc !important;
                text-shadow: 0 1px 4px rgba(0, 0, 0, 0.6) !important;
            }
        }
        h4, h5, h6 {
            font-family: var(--font-ui) !important;
            color: var(--brand-mauve) !important;
            font-weight: 600 !important;
        }
        .btn-brand, .btn.btn-brand {
            background-color: var(--brand-primary) !important;
            border-color: var(--brand-primary) !important;
            color: #FFFFFF !important;
            border-radius: var(--btn-radius) !important;
        }
        .btn-brand:hover, .btn.btn-brand:hover {
            background-color: var(--brand-primary-hover) !important;
            border-color: var(--brand-primary-hover) !important;
            color: #FFFFFF !important;
        }
        .btn-brand-outline, .btn.btn-brand-outline {
            border-color: var(--brand-primary) !important;
            color: var(--brand-primary) !important;
            border-radius: var(--btn-radius) !important;
        }
        .btn-brand-outline:hover, .btn.btn-brand-outline:hover {
            background-color: var(--brand-primary) !important;
            color: #FFFFFF !important;
        }
        .text-rose {
            color: var(--brand-primary) !important;
        }
        .border-rose {
            border-color: var(--brand-primary) !important;
        }
        .bg-brand-blush, .alert-rose-subtle {
            background-color: var(--brand-blush) !important;
        }
        .navbar-veesta {
            background-color: #FFFFFF;
            box-shadow: 0 2px 15px rgba(0,0,0,0.03);
            padding: 10px 0;
        }
        .navbar-veesta .navbar-nav .nav-link {
            font-size: <?php echo e($theme_navbar_font_size); ?> !important;
            font-weight: 600 !important;
            color: var(--brand-mauve) !important;
            white-space: nowrap;
            padding: 0.4rem 0.65rem !important;
            letter-spacing: 0.1px;
        }
        .navbar-veesta .navbar-nav .nav-link:hover,
        .navbar-veesta .navbar-nav .nav-link.active {
            color: var(--brand-primary) !important;
        }
        .badge.bg-rose, .badge-brand, #cart-badge, #wishlist-badge, #mobile-header-cart-badge, #mobile-nav-cart-badge {
            background-color: var(--brand-primary) !important;
            color: #FFFFFF !important;
        }
        .search-input-header {
            width: 140px;
            font-size: 13px;
            transition: width 0.25s ease;
        }
        .search-input-header:focus {
            width: 190px;
        }
    </style>
</head>
<body>

    <!-- Main Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-veesta sticky-top">
        <div class="container">
            <a class="navbar-brand py-0 d-flex align-items-center me-3 flex-shrink-0" href="index.php">
                <img src="assets/images/logo.png" alt="<?php echo e(SITE_NAME); ?>" height="36" class="d-inline-block">
            </a>

            <!-- Mobile Quick Header Action Icons (Visible only on mobile/tablet) -->
            <div class="d-flex align-items-center gap-3 d-lg-none ms-auto me-2">
                <!-- Mobile Search Trigger -->
                <a href="#" class="text-dark" data-bs-toggle="modal" data-bs-target="#mobileSearchModal" title="Search">
                    <i class="bi bi-search fs-5"></i>
                </a>

                <!-- Mobile Wishlist Link -->
                <a href="wishlist.php" class="text-dark position-relative" title="Wishlist">
                    <i class="bi bi-heart fs-5"></i>
                    <span class="cart-badge <?php echo $header_wishlist_count === 0 ? 'd-none' : ''; ?>" id="mobile-header-wishlist-badge"><?php echo $header_wishlist_count; ?></span>
                </a>

                <!-- Mobile Cart Link -->
                <a href="cart.php" class="text-dark position-relative" title="Shopping Cart">
                    <i class="bi bi-bag fs-5"></i>
                    <span class="cart-badge <?php echo $header_cart_count === 0 ? 'd-none' : ''; ?>" id="mobile-header-cart-badge"><?php echo $header_cart_count; ?></span>
                </a>
            </div>

            <button class="navbar-toggler border-0 p-1" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <!-- Clean, compact primary menu -->
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 mt-2 mt-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active text-rose fw-semibold' : ''; ?>" href="index.php">Home</a>
                    </li>
                    <!-- Dynamic Categories -->
                    <?php foreach ($menu_categories as $cat): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (($_GET['category'] ?? '') === $cat['slug']) ? 'active text-rose fw-semibold' : ''; ?>" href="products.php?category=<?php echo e($cat['slug']); ?>"><?php echo e($cat['name']); ?></a>
                        </li>
                    <?php endforeach; ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active text-rose fw-semibold' : ''; ?>" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active text-rose fw-semibold' : ''; ?>" href="contact.php">Contact</a>
                    </li>
                </ul>

                <!-- Right Utility Menus (Desktop - Always compact, visible, and never wraps) -->
                <div class="d-none d-lg-flex align-items-center gap-2 gap-xl-3 flex-shrink-0 ms-lg-2">
                    <!-- Search Form -->
                    <form action="products.php" method="GET" class="position-relative">
                        <input class="form-control form-control-sm rounded-pill pe-4 ps-3 search-input-header" type="search" placeholder="Search..." aria-label="Search" name="search">
                        <button class="btn btn-sm btn-link text-muted position-absolute end-0 top-0 me-1 py-1 px-2" type="submit"><i class="bi bi-search"></i></button>
                    </form>

                    <!-- Wishlist Link -->
                    <a href="wishlist.php" class="text-dark position-relative p-1" title="Wishlist">
                        <i class="bi bi-heart fs-5"></i>
                        <span class="cart-badge <?php echo $header_wishlist_count === 0 ? 'd-none' : ''; ?>" id="wishlist-badge"><?php echo $header_wishlist_count; ?></span>
                    </a>

                    <!-- Cart Link -->
                    <a href="cart.php" class="text-dark position-relative p-1" title="Shopping Cart">
                        <i class="bi bi-bag fs-5"></i>
                        <span class="cart-badge <?php echo $header_cart_count === 0 ? 'd-none' : ''; ?>" id="cart-badge"><?php echo $header_cart_count; ?></span>
                    </a>

                    <!-- Account / Login -->
                    <?php if (is_customer_logged_in()): ?>
                        <div class="dropdown">
                            <a class="text-dark d-flex align-items-center gap-1 dropdown-toggle text-decoration-none" href="#" role="button" id="userMenuLink" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle fs-5 text-rose"></i>
                                <span class="small fw-semibold text-truncate d-none d-xl-inline" style="max-width: 90px;"><?php echo e(explode(' ', $_SESSION['customer_name'] ?? 'User')[0]); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm" aria-labelledby="userMenuLink">
                                <li><a class="dropdown-menu-item dropdown-item small" href="dashboard.php"><i class="bi bi-person me-2"></i> My Profile</a></li>
                                <li><a class="dropdown-menu-item dropdown-item small" href="addresses.php"><i class="bi bi-geo-alt me-2"></i> Shipping Books</a></li>
                                <li><a class="dropdown-menu-item dropdown-item small" href="orders.php"><i class="bi bi-box me-2"></i> Order History</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-menu-item dropdown-item small text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-sm btn-brand-outline rounded-pill px-3 py-1 text-nowrap flex-shrink-0" title="Login / Register">
                            <i class="bi bi-person me-1"></i> Login
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Account Links inside Collapsed Menu -->
                <div class="d-lg-none border-top pt-3 mt-2 pb-2">
                    <?php if (is_customer_logged_in()): ?>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="bi bi-person-circle fs-3 text-rose"></i>
                            <div>
                                <div class="fw-semibold text-dark"><?php echo e($_SESSION['customer_name'] ?? 'My Account'); ?></div>
                                <small class="text-muted"><?php echo e($_SESSION['customer_email'] ?? ''); ?></small>
                            </div>
                        </div>
                        <div class="d-grid gap-1">
                            <a href="dashboard.php" class="btn btn-light btn-sm text-start"><i class="bi bi-person me-2"></i> My Profile</a>
                            <a href="orders.php" class="btn btn-light btn-sm text-start"><i class="bi bi-box me-2"></i> Order History</a>
                            <a href="addresses.php" class="btn btn-light btn-sm text-start"><i class="bi bi-geo-alt me-2"></i> Addresses</a>
                            <a href="logout.php" class="btn btn-outline-danger btn-sm text-start mt-2"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
                        </div>
                    <?php else: ?>
                        <div class="d-grid gap-2">
                            <a href="login.php" class="btn btn-brand btn-sm rounded-pill"><i class="bi bi-box-arrow-in-right me-1"></i> Login / Register</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Top flash alerts wrapper -->
    <div class="container mt-3">
        <?php display_flash_messages(); ?>
    </div>
