<?php
// cart.php
// Public Customer Shopping Cart review

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';

$session_id = session_id();
$customer_id = $_SESSION['customer_id'] ?? null;
$error = '';
$success = '';

// Handle direct item deletions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_cart_item'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed.';
    } else {
        $cart_id = (int)($_POST['cart_id'] ?? 0);
        try {
            if ($customer_id) {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND customer_id = ?");
                $stmt->execute([$cart_id, $customer_id]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND session_id = ? AND customer_id IS NULL");
                $stmt->execute([$cart_id, $session_id]);
            }
            set_flash_message('success', 'Item removed from shopping cart!');
            header('Location: cart.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to remove item.';
        }
    }
}

// Fetch all items in cart (both single variants and combo products)
try {
    $cart_sql = "
        SELECT c.id as cart_id, c.quantity, c.variant_id, c.combo_product_id, c.combo_config,
               v.sku as variant_sku, v.regular_price as variant_regular_price, v.offer_price as variant_offer_price, v.price as variant_price, v.stock as variant_stock,
               p.id as product_id, p.product_type, p.name as product_name, p.slug as product_slug, 
               p.sku as base_sku, p.regular_price, p.offer_price, p.gst_percentage,
               sz.size_code, cl.color_name, cl.color_code,
               (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_featured DESC LIMIT 1) as main_image 
        FROM cart c 
        LEFT JOIN product_variants v ON c.variant_id = v.id 
        LEFT JOIN products p ON COALESCE(c.combo_product_id, v.product_id) = p.id 
        LEFT JOIN sizes sz ON v.size_id = sz.id 
        LEFT JOIN colors cl ON v.color_id = cl.id 
        WHERE " . ($customer_id ? "c.customer_id = ?" : "c.session_id = ? AND c.customer_id IS NULL");

    $stmt = $pdo->prepare($cart_sql);
    $stmt->execute([$customer_id ? $customer_id : $session_id]);
    $cart_items = $stmt->fetchAll();

    // Process combo details and stock levels
    foreach ($cart_items as &$c_item) {
        if (!empty($c_item['combo_product_id']) && !empty($c_item['combo_config'])) {
            $config_array = json_decode($c_item['combo_config'], true) ?? [];
            $c_item['parsed_combo_config'] = $config_array;
            
            // Calculate minimum stock across all chosen variants in combo
            $min_combo_stock = 999999;
            foreach ($config_array as $sub_item) {
                $v_chk = $pdo->prepare("SELECT stock FROM product_variants WHERE id = ?");
                $v_chk->execute([$sub_item['variant_id']]);
                $stk_row = $v_chk->fetch();
                $v_stock = (int)($stk_row['stock'] ?? 0);
                if ($v_stock < $min_combo_stock) {
                    $min_combo_stock = $v_stock;
                }
            }
            $c_item['computed_stock'] = $min_combo_stock;
            $c_item['unit_price'] = $c_item['offer_price'] ? (float)$c_item['offer_price'] : (float)$c_item['regular_price'];
        } else {
            $c_item['parsed_combo_config'] = null;
            $c_item['computed_stock'] = (int)($c_item['variant_stock'] ?? 0);
            
            // Dual-tier price resolution
            $v_off = ($c_item['variant_offer_price'] !== null && $c_item['variant_offer_price'] > 0) ? (float)$c_item['variant_offer_price'] : null;
            $v_reg = ($c_item['variant_regular_price'] !== null && $c_item['variant_regular_price'] > 0) ? (float)$c_item['variant_regular_price'] : (float)$c_item['regular_price'];
            $v_legacy = ($c_item['variant_price'] !== null && $c_item['variant_price'] > 0 && !$c_item['variant_regular_price']) ? (float)$c_item['variant_price'] : null;
            
            if ($v_off !== null) {
                $c_item['unit_price'] = $v_off;
            } elseif ($v_legacy !== null) {
                $c_item['unit_price'] = $v_legacy;
            } elseif (!empty($c_item['offer_price']) && (float)$c_item['offer_price'] > 0) {
                $c_item['unit_price'] = (float)$c_item['offer_price'];
            } else {
                $c_item['unit_price'] = $v_reg;
            }
        }
    }
    unset($c_item);

} catch (PDOException $e) {
    $cart_items = [];
    $error = 'Failed to fetch shopping cart details: ' . $e->getMessage();
}

// Calculate summary details
$subtotal = 0.00;
$tax_amount = 0.00;

foreach ($cart_items as $item) {
    $unit_price = $item['unit_price'];
    $item_total = $unit_price * $item['quantity'];
    
    // Taxes calculation (GST included in retail price)
    $gst_rate = (float)$item['gst_percentage'];
    $tax_included = $item_total - ($item_total / (1 + ($gst_rate / 100)));
    
    $subtotal += $item_total;
    $tax_amount += $tax_included;
}

// Determine shipping
$shipping = 0.00;
if ($subtotal > 0) {
    $shipping = $subtotal >= FREE_SHIPPING_THRESHOLD ? 0.00 : SHIPPING_FLAT_RATE;
}
$grand_total = $subtotal + $shipping;

$meta_title = 'Shopping Cart | Pookky';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <h1 class="page-title mb-4">Shopping Cart</h1>

    <?php if(!empty($error)): ?>
        <div class="alert alert-danger p-2 small"><?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <!-- Centered Empty Cart Card (Option A) -->
        <div class="row justify-content-center py-4">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card border-0 shadow-sm p-4 p-md-5 bg-white text-center rounded-4">
                    <div class="mb-3">
                        <div class="bg-rose-subtle text-rose rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="bi bi-cart-x fs-1"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold mt-2 text-dark">Your Cart is Empty</h4>
                    <p class="text-muted small mb-4">Looks like you haven't added anything to your cart yet. Explore our latest collections and find something you love!</p>
                    <div>
                        <a href="products.php" class="btn btn-brand rounded-pill px-4 py-2 fw-semibold shadow-sm">
                            <i class="bi bi-bag me-1"></i> Start Shopping
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Cart Items list -->
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius:12px;">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Item</th>
                                    <th>Product Details</th>
                                    <th>Price</th>
                                    <th class="text-center" style="width: 140px;">Quantity</th>
                                    <th class="text-end">Subtotal</th>
                                    <th class="text-end" style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_items as $item): ?>
                                    <?php 
                                    $unit_price = $item['unit_price'];
                                    $item_subtotal = $unit_price * $item['quantity'];
                                    $is_item_combo = !empty($item['combo_product_id']);
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if ($item['main_image']): ?>
                                                <img src="uploads/<?php echo e($item['main_image']); ?>" alt="cart item" class="img-thumbnail" style="height: 60px; width: 60px; object-fit: cover;" onerror="this.src='assets/images/logo.png';">
                                            <?php else: ?>
                                                <div class="bg-light text-muted d-flex align-items-center justify-content-center border" style="width: 60px; height: 60px;">
                                                    <i class="bi bi-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="product-detail.php?slug=<?php echo e($item['product_slug']); ?>" class="fw-bold text-dark d-block"><?php echo e($item['product_name']); ?></a>
                                            
                                            <?php if ($is_item_combo && !empty($item['parsed_combo_config'])): ?>
                                                <div class="combo-cart-tag">
                                                    <div class="fw-bold small text-rose mb-1"><i class="bi bi-stars"></i> Curated Combo Set Includes:</div>
                                                    <ul class="mb-0 small">
                                                        <?php foreach ($item['parsed_combo_config'] as $p_comp): ?>
                                                            <li>
                                                                <strong><?php echo e($p_comp['product_name']); ?>:</strong> 
                                                                Size: <span class="badge bg-secondary-subtle text-dark border"><?php echo e($p_comp['size_code']); ?></span> | 
                                                                Color: <span class="badge bg-secondary-subtle text-dark border"><?php echo e($p_comp['color_name']); ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php else: ?>
                                                <small class="text-muted d-block">Size: <?php echo e($item['size_code']); ?> | Color: <?php echo e($item['color_name']); ?></small>
                                                <code class="small text-muted" style="font-size:10px;">SKU: <?php echo e($item['variant_sku']); ?></code>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo format_price($unit_price); ?></td>
                                        <td class="text-center">
                                            <div class="input-group input-group-sm border rounded-pill overflow-hidden mx-auto" style="max-width: 110px;">
                                                <button type="button" class="btn btn-link text-dark qty-btn qty-minus" style="text-decoration:none;"><i class="bi bi-minus"></i></button>
                                                <input type="text" class="form-control text-center bg-transparent border-0 small qty-input" 
                                                       data-cart-id="<?php echo $item['cart_id']; ?>" 
                                                       value="<?php echo $item['quantity']; ?>" 
                                                       min="1" 
                                                       max="<?php echo max(1, $item['computed_stock']); ?>" 
                                                       readonly style="width:30px;">
                                                <button type="button" class="btn btn-link text-dark qty-btn qty-plus" style="text-decoration:none;"><i class="bi bi-plus"></i></button>
                                            </div>
                                            <small class="text-muted" style="font-size:10px;">Stock: <?php echo $item['computed_stock']; ?></small>
                                        </td>
                                        <td class="text-end fw-bold text-dark"><?php echo format_price($item_subtotal); ?></td>
                                        <td class="text-end">
                                            <form action="cart.php" method="POST" class="form-delete-cart-item" onsubmit="return confirm('Remove item from cart?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                                <button type="submit" name="delete_cart_item" class="btn btn-sm btn-link text-danger p-0 btn-remove-cart-item" data-cart-id="<?php echo $item['cart_id']; ?>" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Summary Totals Card -->
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius:12px;">
                    <h5 class="fw-bold mb-4 text-dark border-bottom pb-2">Order Summary</h5>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Subtotal:</span>
                        <span class="fw-bold text-dark"><?php echo format_price($subtotal); ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Shipping Charges:</span>
                        <span class="fw-bold text-dark">
                            <?php echo $shipping == 0 ? '<span class="text-success fw-bold">FREE</span>' : format_price($shipping); ?>
                        </span>
                    </div>

                    <?php if ($shipping > 0): ?>
                        <div class="alert alert-warning p-2 small mt-2" style="font-size:11px;">
                            <i class="bi bi-info-circle me-1"></i> Add <strong><?php echo format_price(FREE_SHIPPING_THRESHOLD - $subtotal); ?></strong> more to get <strong>FREE SHIPPING</strong>!
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between mb-3 border-top pt-2 mt-2">
                        <span class="text-muted small">GST included:</span>
                        <span class="text-muted small"><?php echo format_price($tax_amount); ?></span>
                    </div>

                    <div class="d-flex justify-content-between border-top pt-3 mb-4">
                        <span class="fw-bold text-dark fs-5">Estimated Total:</span>
                        <span class="fw-bold text-rose fs-5"><?php echo format_price($grand_total); ?></span>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="checkout.php" class="btn btn-brand">Proceed to Checkout</a>
                        <a href="products.php" class="btn btn-brand-outline rounded-pill text-center">Continue Shopping</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
