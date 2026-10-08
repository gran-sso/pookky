<?php
// includes/footer.php
// Public website common footer layout

// Fetch policy pages for footer links
$footer_pages = [];
try {
    $footer_pages = $pdo->query("SELECT slug, title FROM pages ORDER BY id ASC LIMIT 10")->fetchAll();
} catch (PDOException $e) {
    $footer_pages = [];
}
?>
    <!-- Footer Section -->
    <footer class="footer-veesta pt-5 mt-5 pb-5 pb-lg-3">
        <div class="container">
            <div class="row g-4 mb-4 mb-lg-5">
                <!-- Brand Info Column -->
                <div class="col-12 col-lg-4 col-md-6">
                    <div class="mb-3">
                        <img src="assets/images/pookky-logo-white.png" alt="Pookky" height="40" class="d-inline-block">
                    </div>
                    <p class="mb-3 mb-md-4"><?php echo e(FOOTER_ABOUT_TEXT); ?></p>
                    <div class="d-flex gap-2">
                        <?php if (SOCIAL_FACEBOOK && SOCIAL_FACEBOOK !== '#'): ?>
                            <a href="<?php echo e(SOCIAL_FACEBOOK); ?>" target="_blank" rel="noopener noreferrer" class="social-icon" title="Facebook"><i class="bi bi-facebook"></i></a>
                        <?php endif; ?>
                        <?php if (SOCIAL_INSTAGRAM && SOCIAL_INSTAGRAM !== '#'): ?>
                            <a href="<?php echo e(SOCIAL_INSTAGRAM); ?>" target="_blank" rel="noopener noreferrer" class="social-icon" title="Instagram"><i class="bi bi-instagram"></i></a>
                        <?php endif; ?>
                        <?php if (SOCIAL_PINTEREST && SOCIAL_PINTEREST !== '#'): ?>
                            <a href="<?php echo e(SOCIAL_PINTEREST); ?>" target="_blank" rel="noopener noreferrer" class="social-icon" title="Pinterest"><i class="bi bi-pinterest"></i></a>
                        <?php endif; ?>
                        <?php if (SOCIAL_YOUTUBE && SOCIAL_YOUTUBE !== '#'): ?>
                            <a href="<?php echo e(SOCIAL_YOUTUBE); ?>" target="_blank" rel="noopener noreferrer" class="social-icon" title="YouTube"><i class="bi bi-youtube"></i></a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Shop Columns -->
                <div class="col-6 col-lg-2 col-md-3">
                    <h6 class="text-uppercase fw-bold mb-3 mb-md-4">Shop Collections</h6>
                    <ul class="list-unstyled">
                        <?php foreach ($menu_categories as $cat): ?>
                            <li class="mb-2">
                                <a href="products.php?category=<?php echo e($cat['slug']); ?>" class="footer-link">
                                    <?php echo e($cat['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <li class="mb-2"><a href="products.php" class="footer-link">All Products</a></li>
                        <li class="mb-2"><a href="size-guide.php" class="footer-link">Size Guide</a></li>
                    </ul>
                </div>

                <!-- Policies / Help Column -->
                <div class="col-6 col-lg-2 col-md-3">
                    <h6 class="text-uppercase fw-bold mb-3 mb-md-4">Customer Care</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="faq.php" class="footer-link">FAQs & Help</a></li>
                        <li class="mb-2"><a href="contact.php" class="footer-link">Contact Us</a></li>
                        <li class="mb-2"><a href="orders.php" class="footer-link">Track Order</a></li>
                        <?php foreach ($footer_pages as $page_item): ?>
                            <li class="mb-2">
                                <a href="<?php echo e($page_item['slug']); ?>.php" class="footer-link">
                                    <?php echo e($page_item['title']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Newsletter Subscriber & Contact Info Column -->
                <div class="col-12 col-lg-4 col-md-12">
                    <h6 class="text-uppercase fw-bold mb-3 mb-md-4">Newsletter Signup</h6>
                    <p class="mb-3">Subscribe to get notifications on new arrivals, secret offers, and size updates.</p>
                    <form id="newsletter-form" class="d-flex mb-4">
                        <input type="email" class="form-control newsletter-input" placeholder="Your Email Address" required>
                        <button type="submit" class="btn newsletter-btn">Join</button>
                    </form>
                    
                    <h6 class="text-uppercase fw-bold mb-3 small" style="letter-spacing: 0.5px; opacity: 0.8;">Get in Touch</h6>
                    <div class="contact-item">
                        <i class="bi bi-telephone-fill"></i>
                        <span><?php echo e(SITE_PHONE); ?></span>
                    </div>
                    <div class="contact-item">
                        <i class="bi bi-envelope-fill"></i>
                        <span><?php echo e(SITE_EMAIL); ?></span>
                    </div>
                </div>
            </div>

            <!-- Bottom Copyright & Payments Details -->
            <div class="footer-bottom d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <div class="text-center text-md-start">
                    &copy; <?php echo date('Y'); ?> <?php echo e(SITE_NAME); ?> Lingerie. All Rights Reserved. Manufactured by <?php echo e(COMPANY_LEGAL_NAME); ?>.
                    <span class="d-block d-md-inline ms-md-2 text-nowrap">
                        <span class="d-none d-md-inline text-muted mx-1">&bull;</span>
                        Powered by <a href="https://www.skysysonline.com/" target="_blank" rel="noopener noreferrer" style="color: var(--brand-accent) !important; text-decoration: underline; font-weight: 500;">Skysys</a>
                    </span>
                </div>
                <div class="d-flex gap-3 fs-3" style="opacity: 0.65; color: var(--brand-white);">
                    <i class="bi bi-credit-card-2-front" title="Card Payments"></i>
                    <i class="bi bi-wallet2" title="Net Banking"></i>
                    <i class="bi bi-shield-check" title="100% Secure Checkout"></i>
                </div>
            </div>
        </div>
    </footer>

    <!-- Fixed App-like Mobile Bottom Navigation Bar (d-lg-none) -->
    <?php
    $current_script = basename($_SERVER['PHP_SELF'] ?? '');
    ?>
    <nav class="mobile-bottom-nav d-lg-none fixed-bottom bg-white border-top shadow">
        <div class="container-fluid px-0">
            <div class="row g-0 text-center">
                <!-- 1. Home -->
                <div class="col">
                    <a href="index.php" class="mobile-nav-link <?php echo $current_script === 'index.php' ? 'active' : ''; ?>">
                        <i class="bi <?php echo $current_script === 'index.php' ? 'bi-house-door-fill text-rose' : 'bi-house-door'; ?>"></i>
                        <span>Home</span>
                    </a>
                </div>

                <!-- 2. Shop / Catalog -->
                <div class="col">
                    <a href="products.php" class="mobile-nav-link <?php echo $current_script === 'products.php' ? 'active' : ''; ?>">
                        <i class="bi <?php echo $current_script === 'products.php' ? 'bi-grid-fill text-rose' : 'bi-grid'; ?>"></i>
                        <span>Shop</span>
                    </a>
                </div>

                <!-- 3. Search Trigger -->
                <div class="col">
                    <a href="#" class="mobile-nav-link" data-bs-toggle="modal" data-bs-target="#mobileSearchModal">
                        <i class="bi bi-search"></i>
                        <span>Search</span>
                    </a>
                </div>

                <!-- 4. Shopping Cart -->
                <div class="col">
                    <a href="cart.php" class="mobile-nav-link position-relative <?php echo $current_script === 'cart.php' ? 'active' : ''; ?>">
                        <i class="bi <?php echo $current_script === 'cart.php' ? 'bi-bag-fill text-rose' : 'bi-bag'; ?>"></i>
                        <span class="mobile-nav-badge <?php echo $header_cart_count === 0 ? 'd-none' : ''; ?>" id="mobile-nav-cart-badge"><?php echo $header_cart_count; ?></span>
                        <span>Cart</span>
                    </a>
                </div>

                <!-- 5. Profile / Account -->
                <div class="col">
                    <?php if (is_customer_logged_in()): ?>
                        <a href="dashboard.php" class="mobile-nav-link <?php echo in_array($current_script, ['dashboard.php', 'orders.php', 'addresses.php', 'order-detail.php']) ? 'active' : ''; ?>">
                            <i class="bi <?php echo in_array($current_script, ['dashboard.php', 'orders.php', 'addresses.php']) ? 'bi-person-circle text-rose' : 'bi-person-circle'; ?>"></i>
                            <span>Account</span>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="mobile-nav-link <?php echo in_array($current_script, ['login.php', 'register.php']) ? 'active' : ''; ?>">
                            <i class="bi <?php echo in_array($current_script, ['login.php', 'register.php']) ? 'bi-person-fill text-rose' : 'bi-person'; ?>"></i>
                            <span>Login</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Quick Search Modal -->
    <div class="modal fade" id="mobileSearchModal" tabindex="-1" aria-labelledby="mobileSearchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-top">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title fw-semibold text-dark" id="mobileSearchModalLabel"><i class="bi bi-search text-rose me-2"></i> Search Pookky</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-2 pb-4 px-4">
                    <form action="products.php" method="GET" class="position-relative mb-3">
                        <div class="input-group">
                            <input type="search" name="search" class="form-control rounded-start-pill ps-3" placeholder="Search bras, panties, sleepwear..." autofocus required>
                            <button type="submit" class="btn btn-brand rounded-end-pill px-4">Search</button>
                        </div>
                    </form>
                    <div>
                        <div class="small text-muted fw-bold text-uppercase mb-2" style="font-size: 11px; letter-spacing: 0.5px;">Popular Categories</div>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($menu_categories as $m_cat): ?>
                                <a href="products.php?category=<?php echo e($m_cat['slug']); ?>" class="badge bg-light text-dark border rounded-pill text-decoration-none px-3 py-2">
                                    <?php echo e($m_cat['name']); ?>
                                </a>
                            <?php endforeach; ?>
                            <a href="products.php?fabrics[]=Cotton" class="badge bg-light text-dark border rounded-pill text-decoration-none px-3 py-2">Pure Cotton</a>
                            <a href="products.php?fabrics[]=Lace" class="badge bg-light text-dark border rounded-pill text-decoration-none px-3 py-2">Lace Collection</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div class="toast-container"></div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom Lingerie operation scripts -->
    <script src="assets/js/main.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/main.js'); ?>"></script>
</body>
</html>
