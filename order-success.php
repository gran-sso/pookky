<?php
// order-success.php
// Modern State-of-the-Art Order Confirmation & Tracking Screen

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

// Check order number parameter
$order_number = trim($_GET['order_num'] ?? '');
if (empty($order_number)) {
    header('Location: index.php');
    exit;
}

try {
    // Fetch Order details
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
    $stmt->execute([$order_number]);
    $order = $stmt->fetch();

    if (!$order) {
        header('Location: index.php');
        exit;
    }

    // If customer is logged in, verify ownership
    if (isset($_SESSION['customer_id']) && $order['customer_id'] != $_SESSION['customer_id']) {
        set_flash_message('error', 'You do not have permission to view this order.');
        header('Location: index.php');
        exit;
    }

    // Fetch line items with images and slugs
    $items_stmt = $pdo->prepare("
        SELECT oi.*, p.slug as product_slug,
               (SELECT image_path FROM product_images WHERE product_id = oi.product_id ORDER BY is_featured DESC LIMIT 1) as main_image
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $items_stmt->execute([$order['id']]);
    $order_items = $items_stmt->fetchAll();

} catch (PDOException $e) {
    die("Error retrieving order confirmation details.");
}

$meta_title = 'Order #' . e($order['order_number']) . ' Confirmed | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';

// Compute estimated delivery dates (4-6 days from order date)
$order_timestamp = !empty($order['created_at']) ? strtotime($order['created_at']) : time();
$est_start = date('D, M j', strtotime('+4 days', $order_timestamp));
$est_end = date('D, M j', strtotime('+6 days', $order_timestamp));
?>

<div class="order-confirmation-wrapper py-5">
    <div class="container">
        
        <!-- Celebration Hero Section -->
        <div class="card glass-card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4 text-center bg-white position-relative overflow-hidden">
            <div class="order-success-decoration"></div>
            
            <div class="mb-3">
                <div class="success-icon-pulse d-inline-flex align-items-center justify-content-center">
                    <i class="bi bi-check2-circle text-success fs-1"></i>
                </div>
            </div>

            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-semibold mb-3 d-inline-block">
                <i class="bi bi-shield-check me-1"></i> Order Confirmed & Secured
            </span>

            <h1 class="font-display h2 fw-bold text-dark mb-2">
                Thank You, <?php echo e(explode(' ', trim($order['receiver_name']))[0]); ?>!
            </h1>
            
            <p class="text-muted fs-6 mb-3 max-w-600 mx-auto">
                We've received your order and our fulfillment team is preparing it with discreet, luxury packaging.
            </p>

            <div class="d-flex flex-wrap justify-content-center align-items-center gap-3 mt-2">
                <div class="order-ref-pill d-inline-flex align-items-center bg-light border rounded-pill px-3 py-2">
                    <span class="text-muted small me-2">Order Reference:</span>
                    <strong class="text-dark me-2 font-monospace" id="orderNumberRef"><?php echo e($order['order_number']); ?></strong>
                    <button type="button" class="btn btn-sm btn-link p-0 text-muted copy-order-btn" data-clipboard-text="<?php echo e($order['order_number']); ?>" title="Copy Order Number">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>

                <div class="delivery-est-pill d-inline-flex align-items-center bg-brand-blush text-rose border border-rose rounded-pill px-3 py-2 small fw-semibold">
                    <i class="bi bi-truck me-2"></i>
                    Estimated Delivery: <?php echo $est_start; ?> &ndash; <?php echo $est_end; ?>
                </div>
            </div>
        </div>

        <!-- Visual Order Progress Stepper -->
        <div class="card glass-card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
            <h6 class="text-uppercase fw-bold text-muted small mb-4 tracking-wide">
                <i class="bi bi-clock-history me-1 text-rose"></i> Order Fulfillment Journey
            </h6>
            
            <div class="order-stepper-container">
                <div class="stepper-step completed">
                    <div class="stepper-icon">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <div class="stepper-label">
                        <strong>Order Placed</strong>
                        <span class="text-muted small d-block"><?php echo date('M d, Y • h:i A', $order_timestamp); ?></span>
                    </div>
                </div>

                <div class="stepper-divider completed"></div>

                <div class="stepper-step active">
                    <div class="stepper-icon">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="stepper-label">
                        <strong>Processing</strong>
                        <span class="text-muted small d-block">Quality check & packing</span>
                    </div>
                </div>

                <div class="stepper-divider"></div>

                <div class="stepper-step">
                    <div class="stepper-icon">
                        <i class="bi bi-truck"></i>
                    </div>
                    <div class="stepper-label">
                        <strong>Dispatched</strong>
                        <span class="text-muted small d-block">With courier partner</span>
                    </div>
                </div>

                <div class="stepper-divider"></div>

                <div class="stepper-step">
                    <div class="stepper-icon">
                        <i class="bi bi-house-door"></i>
                    </div>
                    <div class="stepper-label">
                        <strong>Delivery</strong>
                        <span class="text-muted small d-block">Discreet box to doorstep</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column: Ordered Items and Cost Breakdown -->
            <div class="col-12 col-lg-7">
                <div class="card glass-card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="bi bi-bag-check me-2 text-rose"></i> Order Items (<?php echo count($order_items); ?>)
                        </h5>
                        <span class="badge bg-light text-dark border">
                            Status: <span class="text-capitalize fw-semibold text-primary"><?php echo e($order['order_status']); ?></span>
                        </span>
                    </div>

                    <div class="order-items-list">
                        <?php foreach ($order_items as $item): ?>
                            <?php 
                            $is_combo = !empty($item['combo_product_id']) || !empty($item['combo_config']);
                            $parsed_cfg = $is_combo ? json_decode($item['combo_config'], true) : null;
                            $img_src = !empty($item['main_image']) ? 'uploads/' . $item['main_image'] : 'assets/images/logo.png';
                            ?>
                            <div class="order-item-card d-flex gap-3 py-3 border-bottom align-items-center">
                                <div class="order-item-thumb position-relative flex-shrink-0">
                                    <img src="<?php echo e($img_src); ?>" alt="<?php echo e($item['product_name']); ?>" class="img-fluid rounded-3 object-fit-cover" style="width: 72px; height: 86px; background-color: #fcf8f7;" onerror="this.src='assets/images/logo.png';">
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark text-white shadow-sm" style="font-size: 11px;">
                                        ×<?php echo (int)$item['quantity']; ?>
                                    </span>
                                </div>

                                <div class="order-item-info flex-grow-1">
                                    <?php if (!empty($item['product_slug'])): ?>
                                        <a href="product-detail.php?slug=<?php echo e($item['product_slug']); ?>" class="text-dark fw-bold text-decoration-none d-block mb-1 hover-rose">
                                            <?php echo e($item['product_name']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-dark fw-bold d-block mb-1"><?php echo e($item['product_name']); ?></span>
                                    <?php endif; ?>

                                    <?php if ($is_combo && !empty($parsed_cfg)): ?>
                                        <div class="combo-cart-tag p-2 my-1">
                                            <div class="fw-bold small text-rose mb-1"><i class="bi bi-stars"></i> Curated Set Components:</div>
                                            <ul class="mb-0 small ps-3">
                                                <?php foreach ($parsed_cfg as $p_comp): ?>
                                                    <li>
                                                        <strong><?php echo e($p_comp['product_name']); ?>:</strong> 
                                                        Size: <span class="badge bg-secondary-subtle text-dark border"><?php echo e($p_comp['size_code']); ?></span> | 
                                                        Color: <span class="badge bg-secondary-subtle text-dark border"><?php echo e($p_comp['color_name']); ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php else: ?>
                                        <div class="d-flex flex-wrap gap-2 align-items-center small text-muted mb-1">
                                            <span>Size: <strong class="text-dark"><?php echo e($item['size_name']); ?></strong></span>
                                            <span>&bull;</span>
                                            <span>Color: <strong class="text-dark"><?php echo e($item['color_name']); ?></strong></span>
                                            <span>&bull;</span>
                                            <span>SKU: <span class="font-monospace"><?php echo e($item['sku']); ?></span></span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="small text-muted">
                                        Unit Price: <?php echo format_price($item['price']); ?>
                                    </div>
                                </div>

                                <div class="order-item-total text-end flex-shrink-0">
                                    <div class="fw-bold text-dark fs-6">
                                        <?php echo format_price($item['total']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Financial Summary Breakdown -->
                    <div class="order-cost-breakdown pt-3 mt-2">
                        <div class="d-flex justify-content-between text-muted small mb-2">
                            <span>Subtotal:</span>
                            <span class="text-dark fw-semibold"><?php echo format_price($order['subtotal']); ?></span>
                        </div>

                        <?php if ($order['discount'] > 0): ?>
                            <div class="d-flex justify-content-between text-success small mb-2">
                                <span>
                                    <i class="bi bi-tag-fill me-1"></i> Discount Applied
                                    <?php if (!empty($order['coupon_code'])): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-1 font-monospace"><?php echo e($order['coupon_code']); ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="fw-semibold">-<?php echo format_price($order['discount']); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between text-muted small mb-2">
                            <span>Shipping & Handling:</span>
                            <?php if ($order['shipping_charge'] == 0): ?>
                                <span class="badge bg-success-subtle text-success">FREE Shipping</span>
                            <?php else: ?>
                                <span class="text-dark fw-semibold"><?php echo format_price($order['shipping_charge']); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($order['tax_amount'] > 0): ?>
                            <div class="d-flex justify-content-between text-muted small mb-2">
                                <span>Taxes & GST (Included):</span>
                                <span class="text-dark"><?php echo format_price($order['tax_amount']); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center pt-3 mt-2 border-top">
                            <span class="fw-bold text-dark fs-5">Grand Total:</span>
                            <span class="fw-bold text-rose fs-4 font-display"><?php echo format_price($order['grand_total']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Delivery, Payment & Quick CTAs -->
            <div class="col-12 col-lg-5">
                <!-- Delivery Details Card -->
                <div class="card glass-card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                    <h6 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                        <i class="bi bi-geo-alt-fill text-rose me-2"></i> Delivery Address
                    </h6>
                    <div class="text-dark small lh-lg">
                        <div class="fw-bold fs-6 text-dark mb-1"><?php echo e($order['receiver_name']); ?></div>
                        <div class="text-muted mb-1"><?php echo e($order['address_line1']); ?></div>
                        <?php if (!empty($order['address_line2'])): ?>
                            <div class="text-muted mb-1"><?php echo e($order['address_line2']); ?></div>
                        <?php endif; ?>
                        <div class="text-muted mb-2">
                            <?php echo e($order['city']); ?>, <?php echo e($order['state']); ?> - <span class="fw-semibold text-dark"><?php echo e($order['pincode']); ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-2 text-dark pt-1">
                            <i class="bi bi-telephone text-muted"></i>
                            <span class="fw-semibold"><?php echo e($order['receiver_phone']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Payment Summary Card -->
                <div class="card glass-card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                    <h6 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                        <i class="bi bi-credit-card-2-front-fill text-rose me-2"></i> Payment Details
                    </h6>
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span class="text-muted">Payment Mode:</span>
                        <span class="fw-bold text-dark"><?php echo e($order['payment_method']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3 small">
                        <span class="text-muted">Payment Status:</span>
                        <?php if ($order['payment_status'] === 'paid'): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                <i class="bi bi-check-circle me-1"></i> Paid Online
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                <i class="bi bi-hourglass-split me-1"></i> Pay on Delivery
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-3 bg-light rounded-3 border small text-muted d-flex align-items-center gap-2">
                        <i class="bi bi-box-seam-fill text-rose fs-5"></i>
                        <span>Discreet Shipping: Shipped in non-branded outer parcel boxes for full privacy.</span>
                    </div>
                </div>

                <!-- Action Hub Card -->
                <div class="card glass-card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                    <h6 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                        <i class="bi bi-lightning-charge-fill text-rose me-2"></i> Quick Actions
                    </h6>
                    <div class="d-grid gap-2">
                        <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-brand py-2 rounded-pill fw-semibold shadow-sm">
                            <i class="bi bi-truck me-2"></i> Live Order Tracking
                        </a>
                        <a href="invoice.php?order_id=<?php echo $order['id']; ?>" target="_blank" class="btn btn-outline-dark py-2 rounded-pill fw-semibold">
                            <i class="bi bi-file-earmark-pdf me-2"></i> View & Print Invoice
                        </a>
                        <a href="products.php" class="btn btn-outline-secondary py-2 rounded-pill">
                            <i class="bi bi-arrow-left me-2"></i> Continue Shopping
                        </a>
                    </div>
                </div>

                <!-- Customer Support Banner -->
                <div class="card border-0 rounded-4 p-3 bg-brand-blush text-center">
                    <div class="small text-muted mb-1">Need help with your order?</div>
                    <div class="fw-semibold small">
                        <a href="contact.php" class="text-rose text-decoration-none me-3">
                            <i class="bi bi-chat-dots me-1"></i> Contact Support
                        </a>
                        <a href="faq.php" class="text-rose text-decoration-none">
                            <i class="bi bi-question-circle me-1"></i> FAQs & Returns
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
