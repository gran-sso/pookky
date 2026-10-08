<?php
// Start output buffering to prevent headers already sent warnings on redirects
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/auth.php';

// Guarantee user is an active administrator
require_admin_login();

// Fetch current administrator profile
$current_admin_id = $_SESSION['admin_id'];
$admin_stmt = $pdo->prepare("
    SELECT a.*, r.name as role_name 
    FROM admins a 
    JOIN roles r ON a.role_id = r.id 
    WHERE a.id = ? LIMIT 1
");
$admin_stmt->execute([$current_admin_id]);
$admin_profile = $admin_stmt->fetch();

if (!$admin_profile || $admin_profile['status'] != 1) {
    // Session invalid or suspended
    unset($_SESSION['admin_id']);
    header('Location: login.php');
    exit;
}

$page_title = $page_title ?? 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?> | Pookky Admin</title>
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts: Bodoni Moda (Display) & Montserrat (UI & Body) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400;0,6..96,500;0,6..96,600;0,6..96,700;1,6..96,400;1,6..96,500&family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&subset=latin,latin-ext,devanagari&display=swap" rel="stylesheet">
    <!-- Core custom styling -->
    <link rel="stylesheet" href="../assets/css/style.css?v=2.3">
    <style>
        body {
            background-color: #f7f6f2;
        }
        .admin-sidebar {
            width: 250px;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 0 0 60px 0;
            background-color: var(--brand-charcoal);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
        }
        .admin-sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .admin-sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        .admin-sidebar::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.25);
            border-radius: 3px;
        }
        .admin-sidebar::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255, 255, 255, 0.4);
        }
        .admin-main {
            margin-left: 250px;
            padding: 30px;
        }
        @media (max-width: 991.98px) {
            .admin-sidebar {
                margin-left: -250px;
                transition: transform 0.3s ease-in-out;
            }
            .admin-sidebar.show {
                margin-left: 0;
                transform: translateX(250px);
            }
            .admin-main {
                margin-left: 0;
                padding: 15px;
            }
        }
        .sidebar-brand {
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.2);
            color: var(--brand-white);
            font-family: var(--font-heading);
            font-size: 24px;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .admin-card-stat {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            transition: transform 0.3s ease;
        }
        .admin-card-stat:hover {
            transform: translateY(-3px);
        }
    </style>
</head>
<body>

    <!-- Admin Sidebar Navigation -->
    <div class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-brand d-flex align-items-center justify-content-between">
            <a href="index.php" class="d-flex align-items-center text-decoration-none">
                <img src="../assets/images/pookky-logo-white.png" alt="Pookky" height="30" class="me-2">
                <span class="badge bg-danger ms-1" style="font-size: 10px; letter-spacing: 0.5px;">ADMIN</span>
            </a>
            <button class="btn btn-link text-white d-lg-none p-0" id="sidebarClose">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="py-3 px-3 border-bottom border-secondary mb-3 d-flex align-items-center gap-2">
            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white font-weight-bold" style="width: 40px; height: 40px;">
                <?php echo strtoupper(substr($admin_profile['name'], 0, 1)); ?>
            </div>
            <div class="text-truncate">
                <div class="text-white small fw-bold"><?php echo e($admin_profile['name']); ?></div>
                <div class="text-muted" style="font-size: 11px;"><?php echo e($admin_profile['role_name']); ?></div>
            </div>
        </div>
        <ul class="nav flex-column mb-auto">
            <li class="nav-item">
                <a href="index.php" class="nav-link <?php echo ($active_menu ?? '') === 'dashboard' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>
            
            <?php if (has_admin_permission('products')): ?>
            <li class="nav-item">
                <a href="products.php" class="nav-link <?php echo ($active_menu ?? '') === 'products' ? 'active' : ''; ?>">
                    <i class="bi bi-box-seam me-2"></i> Products
                </a>
            </li>
            <li class="nav-item">
                <a href="bulk-upload.php" class="nav-link <?php echo ($active_menu ?? '') === 'bulk_upload' ? 'active' : ''; ?>">
                    <i class="bi bi-cloud-arrow-up me-2"></i> Bulk Import / Export
                </a>
            </li>
            <li class="nav-item">
                <a href="categories.php" class="nav-link <?php echo ($active_menu ?? '') === 'categories' ? 'active' : ''; ?>">
                    <i class="bi bi-tags me-2"></i> Categories
                </a>
            </li>
            <li class="nav-item">
                <a href="sizes.php" class="nav-link <?php echo ($active_menu ?? '') === 'sizes' ? 'active' : ''; ?>">
                    <i class="bi bi-arrows-angle-expand me-2"></i> Sizes Config
                </a>
            </li>
            <li class="nav-item">
                <a href="colors.php" class="nav-link <?php echo ($active_menu ?? '') === 'colors' ? 'active' : ''; ?>">
                    <i class="bi bi-palette me-2"></i> Colors Config
                </a>
            </li>
            <li class="nav-item">
                <a href="fabrics.php" class="nav-link <?php echo ($active_menu ?? '') === 'fabrics' ? 'active' : ''; ?>">
                    <i class="bi bi-layers me-2"></i> Fabrics Config
                </a>
            </li>
            <li class="nav-item">
                <a href="inventory.php" class="nav-link <?php echo ($active_menu ?? '') === 'inventory' ? 'active' : ''; ?>">
                    <i class="bi bi-boxes me-2"></i> Stock Ledger
                </a>
            </li>
            <?php endif; ?>

            <?php if (has_admin_permission('orders')): ?>
            <li class="nav-item">
                <a href="orders.php" class="nav-link <?php echo ($active_menu ?? '') === 'orders' ? 'active' : ''; ?>">
                    <i class="bi bi-cart3 me-2"></i> Orders
                </a>
            </li>
            <li class="nav-item">
                <a href="returns.php" class="nav-link <?php echo ($active_menu ?? '') === 'returns' ? 'active' : ''; ?>">
                    <i class="bi bi-arrow-left-right me-2"></i> Return Requests
                </a>
            </li>
            <?php endif; ?>

            <?php if (has_admin_permission('customers')): ?>
            <li class="nav-item">
                <a href="customers.php" class="nav-link <?php echo ($active_menu ?? '') === 'customers' ? 'active' : ''; ?>">
                    <i class="bi bi-people me-2"></i> Customers
                </a>
            </li>
            <?php endif; ?>

            <?php if (has_admin_permission('all')): ?>
            <li class="nav-item">
                <a href="coupons.php" class="nav-link <?php echo ($active_menu ?? '') === 'coupons' ? 'active' : ''; ?>">
                    <i class="bi bi-percent me-2"></i> Coupons
                </a>
            </li>
            <?php endif; ?>

            <?php if (has_admin_permission('products') || has_admin_permission('all')): ?>
            <li class="nav-item">
                <a href="banners.php" class="nav-link <?php echo ($active_menu ?? '') === 'banners' ? 'active' : ''; ?>">
                    <i class="bi bi-image me-2"></i> Banner Sliders
                </a>
            </li>
            <li class="nav-item">
                <a href="theme-settings.php" class="nav-link <?php echo ($active_menu ?? '') === 'theme' ? 'active' : ''; ?>">
                    <i class="bi bi-palette2 me-2"></i> Theme & Appearance
                </a>
            </li>
            <?php endif; ?>

            <?php if (has_admin_permission('all')): ?>
            <li class="nav-item">
                <a href="pages.php" class="nav-link <?php echo ($active_menu ?? '') === 'pages' ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-richtext me-2"></i> Pages Content
                </a>
            </li>
            <li class="nav-item">
                <a href="faqs.php" class="nav-link <?php echo ($active_menu ?? '') === 'faqs' ? 'active' : ''; ?>">
                    <i class="bi bi-question-circle me-2"></i> FAQs Manager
                </a>
            </li>
            <li class="nav-item">
                <a href="reviews.php" class="nav-link <?php echo ($active_menu ?? '') === 'reviews' ? 'active' : ''; ?>">
                    <i class="bi bi-star me-2"></i> Product Reviews
                </a>
            </li>
            <li class="nav-item">
                <a href="enquiries.php" class="nav-link <?php echo ($active_menu ?? '') === 'enquiries' ? 'active' : ''; ?>">
                    <i class="bi bi-envelope me-2"></i> Contact Inbox
                </a>
            </li>
            <li class="nav-item">
                <a href="subscribers.php" class="nav-link <?php echo ($active_menu ?? '') === 'subscribers' ? 'active' : ''; ?>">
                    <i class="bi bi-bell-fill me-2"></i> Subscribers
                </a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="nav-link <?php echo ($active_menu ?? '') === 'reports' ? 'active' : ''; ?>">
                    <i class="bi bi-graph-up-arrow me-2"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a href="users.php" class="nav-link <?php echo ($active_menu ?? '') === 'users' ? 'active' : ''; ?>">
                    <i class="bi bi-shield-lock me-2"></i> Staff Permissions
                </a>
            </li>
            <li class="nav-item">
                <a href="settings.php" class="nav-link <?php echo ($active_menu ?? '') === 'settings' ? 'active' : ''; ?>">
                    <i class="bi bi-gear me-2"></i> Global Settings
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-item mt-4 pt-3 border-top border-secondary">
                <a href="logout.php" class="nav-link text-danger">
                    <i class="bi bi-box-arrow-right me-2"></i> Sign Out
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="admin-main">
        <!-- Top bar layout -->
        <header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary d-lg-none" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="h3 mb-0"><?php echo e($page_title); ?></h1>
            </div>
            <div>
                <a href="../index.php" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-globe"></i> View Website
                </a>
            </div>
        </header>

        <!-- Flash alerts display -->
        <div class="container-fluid p-0">
            <?php display_flash_messages(); ?>
        </div>
