<?php
// order-detail.php
// Customer Order Details, Tracking and Returns system

$meta_title = 'Order Details & Tracking | Pookky';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/security.php';

// Requires customer login
require_customer_login();

$customer_id = $_SESSION['customer_id'];
$customer = get_logged_in_customer();
$order_id = (int)($_GET['id'] ?? 0);

$error = '';
$success = '';

try {
    // Fetch Order details
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ? LIMIT 1");
    $stmt->execute([$order_id, $customer_id]);
    $order = $stmt->fetch();

    if (!$order) {
        set_flash_message('error', 'Order not found.');
        header('Location: orders.php');
        exit;
    }

    // Fetch order line items
    $items_stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $items_stmt->execute([$order_id]);
    $order_items = $items_stmt->fetchAll();

    // Fetch existing return requests for this order
    $ret_stmt = $pdo->prepare("SELECT * FROM returns WHERE order_id = ?");
    $ret_stmt->execute([$order_id]);
    $existing_returns = $ret_stmt->fetchAll();
    
    // Index returns by order_item_id to quickly disable duplicate requests in UI
    $returned_items = [];
    foreach ($existing_returns as $r) {
        $returned_items[$r['order_item_id']] = $r['status'];
    }

} catch (PDOException $e) {
    die("Error retrieving order details.");
}

// ----------------------------------------------------
// PROCESS ORDER CANCELLATION BY CUSTOMER
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed.';
    } elseif ($order['order_status'] !== 'pending') {
        $error = 'This order cannot be cancelled as it is already being processed or shipped.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Update Order Status
            $up_order = $pdo->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ?");
            $up_order->execute([$order_id]);

            // 2. Return product variants stock
            foreach ($order_items as $item) {
                if (!empty($item['combo_config'])) {
                    $c_config = json_decode($item['combo_config'], true) ?? [];
                    foreach ($c_config as $sub_var) {
                        $ref_stock = $pdo->prepare("UPDATE product_variants SET stock = stock + ? WHERE id = ?");
                        $ref_stock->execute([$item['quantity'], $sub_var['variant_id']]);

                        $log_inv = $pdo->prepare("
                            INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) 
                            VALUES (?, ?, 'return', ?)
                        ");
                        $log_notes = "Restock from cancelled combo order: " . $order['order_number'];
                        $log_inv->execute([$sub_var['variant_id'], $item['quantity'], $log_notes]);
                    }
                } elseif (!empty($item['variant_id'])) {
                    $ref_stock = $pdo->prepare("UPDATE product_variants SET stock = stock + ? WHERE id = ?");
                    $ref_stock->execute([$item['quantity'], $item['variant_id']]);

                    // 3. Log stock restock in inventory logs
                    $log_inv = $pdo->prepare("
                        INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) 
                        VALUES (?, ?, 'return', ?)
                    ");
                    $log_notes = "Restock from cancelled order number: " . $order['order_number'];
                    $log_inv->execute([$item['variant_id'], $item['quantity'], $log_notes]);
                }
            }

            $pdo->commit();
            set_flash_message('success', 'Your order has been cancelled successfully.');
            header('Location: order-detail.php?id=' . $order_id);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Cancellation failed: ' . $e->getMessage();
        }
    }
}

// ----------------------------------------------------
// PROCESS RETURN / EXCHANGE SUBMISSION
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_return'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed.';
    } elseif ($order['order_status'] !== 'delivered') {
        $error = 'Returns or exchanges are only allowed for delivered orders.';
    } else {
        $order_item_id = (int)$_POST['order_item_id'];
        $request_type = $_POST['request_type']; // return or exchange
        $reason = $_POST['reason']; // damaged, wrong_product, wrong_size, missing_item
        $description = trim($_POST['description'] ?? '');

        if ($order_item_id <= 0 || empty($request_type) || empty($reason)) {
            $error = 'Please fill in all return request fields.';
        } elseif (isset($returned_items[$order_item_id])) {
            $error = 'A return or exchange request is already active for this item.';
        } else {
            try {
                // Verify item belongs to this order
                $chk_item = $pdo->prepare("SELECT id FROM order_items WHERE id = ? AND order_id = ? LIMIT 1");
                $chk_item->execute([$order_item_id, $order_id]);
                
                if (!$chk_item->fetch()) {
                    $error = 'Invalid order item selected.';
                } else {
                    $ins_ret = $pdo->prepare("
                        INSERT INTO returns (order_id, order_item_id, customer_id, request_type, reason, description, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $ins_ret->execute([$order_id, $order_item_id, $customer_id, $request_type, $reason, $description]);

                    set_flash_message('success', 'Your return/exchange request has been submitted successfully! Admin will moderate.');
                    header('Location: order-detail.php?id=' . $order_id);
                    exit;
                }
            } catch (PDOException $e) {
                $error = 'Submission failed: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="container py-5">
    <div class="row g-4">
        <!-- Sidebar Navigation -->
        <div class="col-12 col-md-3">
            <div class="card glass-card p-3 border-0 bg-white" style="border-radius: 12px;">
                <div class="text-center py-3 border-bottom mb-3">
                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white mx-auto mb-2 fw-bold" style="width: 60px; height: 60px; font-size:24px;">
                        <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                    </div>
                    <h6 class="fw-bold text-dark mb-0"><?php echo e($customer['name']); ?></h6>
                    <small class="text-muted"><?php echo e($customer['email']); ?></small>
                </div>
                <div class="list-group list-group-flush small">
                    <a href="dashboard.php" class="list-group-item list-group-item-action border-0 rounded p-2 mb-1"><i class="bi bi-person me-2"></i> Profile Dashboard</a>
                    <a href="addresses.php" class="list-group-item list-group-item-action border-0 rounded p-2 mb-1"><i class="bi bi-geo-alt me-2"></i> Shipping Addresses</a>
                    <a href="orders.php" class="list-group-item list-group-item-action border-0 active bg-brand-blush text-rose fw-bold rounded p-2 mb-1"><i class="bi bi-box me-2"></i> Order History</a>
                    <a href="wishlist.php" class="list-group-item list-group-item-action border-0 rounded p-2 mb-1"><i class="bi bi-heart me-2"></i> My Wishlist</a>
                    <a href="logout.php" class="list-group-item list-group-item-action border-0 text-danger rounded p-2"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
                </div>
            </div>
        </div>

        <!-- Main Tracking details -->
        <div class="col-12 col-md-9">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger p-2 small"><?php echo e($error); ?></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius:12px;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Order ID: <?php echo e($order['order_number']); ?></h5>
                    <a href="invoice.php?order_id=<?php echo $order['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i> View Invoice</a>
                </div>
                <div class="text-muted small mb-4">Placed: <?php echo date('d-M-Y H:i', strtotime($order['created_at'])); ?></div>

                <!-- Order Status Tracker bar -->
                <div class="bg-light p-4 rounded mb-4 border">
                    <div class="row g-2 text-center text-uppercase fw-bold small">
                        <div class="col-4 col-sm-2">
                            <div class="mb-1 text-success"><i class="bi bi-check-circle-fill"></i></div>
                            <span class="small text-muted" style="font-size:10px;">Placed</span>
                        </div>
                        <div class="col-4 col-sm-2">
                            <div class="mb-1 <?php echo in_array($order['order_status'], ['processing', 'shipped', 'delivered']) ? 'text-success' : 'text-muted'; ?>">
                                <i class="bi <?php echo in_array($order['order_status'], ['processing', 'shipped', 'delivered']) ? 'bi-check-circle-fill' : 'bi-circle'; ?>"></i>
                            </div>
                            <span class="small text-muted" style="font-size:10px;">Processing</span>
                        </div>
                        <div class="col-4 col-sm-2">
                            <div class="mb-1 <?php echo in_array($order['order_status'], ['shipped', 'delivered']) ? 'text-success' : 'text-muted'; ?>">
                                <i class="bi <?php echo in_array($order['order_status'], ['shipped', 'delivered']) ? 'bi-check-circle-fill' : 'bi-circle'; ?>"></i>
                            </div>
                            <span class="small text-muted" style="font-size:10px;">Shipped</span>
                        </div>
                        <div class="col-4 col-sm-2">
                            <div class="mb-1 <?php echo $order['order_status'] === 'delivered' ? 'text-success' : 'text-muted'; ?>">
                                <i class="bi <?php echo $order['order_status'] === 'delivered' ? 'bi-check-circle-fill' : 'bi-circle'; ?>"></i>
                            </div>
                            <span class="small text-muted" style="font-size:10px;">Delivered</span>
                        </div>
                        
                        <?php if ($order['order_status'] === 'cancelled'): ?>
                            <div class="col-4 col-sm-2 text-danger">
                                <div class="mb-1"><i class="bi bi-x-circle-fill"></i></div>
                                <span class="small text-danger" style="font-size:10px;">Cancelled</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($order['order_status'] === 'returned'): ?>
                            <div class="col-4 col-sm-2 text-danger">
                                <div class="mb-1"><i class="bi bi-arrow-left-right"></i></div>
                                <span class="small text-danger" style="font-size:10px;">Returned</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Courier Tracking specs -->
                <?php if ($order['tracking_number']): ?>
                    <div class="alert alert-info p-3 mb-4 small">
                        <i class="bi bi-truck me-2"></i> Shipment Dispatched via <strong><?php echo e($order['courier_name']); ?></strong>. 
                        AWB Tracking No: <strong><code><?php echo e($order['tracking_number']); ?></code></strong>
                    </div>
                <?php endif; ?>

                <h6 class="fw-bold mb-3 border-bottom pb-2">Line Items</h6>
                <div class="table-responsive small">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Product Details</th>
                                <th>Attributes</th>
                                <th class="text-center">Qty</th>
                                <th>Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <?php 
                                $is_combo = !empty($item['combo_product_id']) || !empty($item['combo_config']);
                                $parsed_cfg = $is_combo ? json_decode($item['combo_config'], true) : null;
                                ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold text-dark d-block"><?php echo e($item['product_name']); ?></span>
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
                                            <small class="text-muted">SKU: <?php echo e($item['sku']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_combo): ?>
                                            <span class="badge bg-purple-subtle text-purple border"><i class="bi bi-stars"></i> Custom Combo</span>
                                        <?php else: ?>
                                            Size: <?php echo e($item['size_name']); ?> | Color: <?php echo e($item['color_name']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                                    <td><?php echo format_price($item['price']); ?></td>
                                    <td class="text-end fw-bold"><?php echo format_price($item['total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row pt-3 border-top justify-content-end small">
                    <div class="col-12 col-md-5">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Subtotal:</span>
                            <span class="fw-bold"><?php echo format_price($order['subtotal']); ?></span>
                        </div>
                        <?php if ($order['discount'] > 0): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Promo Discount:</span>
                                <span class="fw-bold text-danger">-<?php echo format_price($order['discount']); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Shipping Flat Charges:</span>
                            <span class="fw-bold"><?php echo format_price($order['shipping_charge']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-2">
                            <span class="fw-bold text-dark fs-6">Grand Total paid:</span>
                            <span class="fw-bold text-rose fs-6"><?php echo format_price($order['grand_total']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Cancel Order button -->
                <?php if ($order['order_status'] === 'pending'): ?>
                    <div class="mt-4 border-top pt-3 text-end">
                        <form action="order-detail.php?id=<?php echo $order_id; ?>" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order? This will restock items.');">
                            <?php echo csrf_field(); ?>
                            <button type="submit" name="cancel_order" class="btn btn-sm btn-outline-danger px-4 rounded-pill">Cancel My Order</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Returns and exchanges module -->
            <?php if ($order['order_status'] === 'delivered'): ?>
                <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius:12px;">
                    <h5 class="fw-bold mb-3 text-danger"><i class="bi bi-arrow-left-right"></i> Return or Exchange Requests</h5>
                    
                    <form action="order-detail.php?id=<?php echo $order_id; ?>" method="POST" class="mb-4 bg-light p-3 rounded border">
                        <?php echo csrf_field(); ?>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="order_item_id" class="form-label small fw-bold">1. Select Item to Return/Exchange</label>
                                <select class="form-select form-select-sm" name="order_item_id" id="order_item_id" required>
                                    <option value="">-- Choose Item --</option>
                                    <?php foreach ($order_items as $item): ?>
                                        <!-- Disable if request already submitted -->
                                        <?php if (!isset($returned_items[$item['id']])): ?>
                                            <option value="<?php echo $item['id']; ?>">
                                                <?php echo e($item['product_name']); ?> (Size: <?php echo e($item['size_name']); ?>, Color: <?php echo e($item['color_name']); ?>)
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12 col-md-3">
                                <label for="request_type" class="form-label small fw-bold">2. Request Type</label>
                                <select class="form-select form-select-sm" name="request_type" id="request_type" required>
                                    <option value="return">Return & Refund</option>
                                    <option value="exchange">Size Exchange</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-3">
                                <label for="reason" class="form-label small fw-bold">3. Allowed Case Reason</label>
                                <select class="form-select form-select-sm" name="reason" id="reason" required>
                                    <option value="damaged">Damaged Product received</option>
                                    <option value="wrong_product">Wrong Product received</option>
                                    <option value="wrong_size">Wrong Size received</option>
                                    <option value="missing_item">Missing Item in shipment</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label small fw-bold">4. Describe issue details</label>
                                <textarea class="form-control form-control-sm" name="description" id="description" rows="2" placeholder="Describe the size mismatch or damage description..." required></textarea>
                            </div>

                            <div class="col-12 text-end">
                                <button type="submit" name="request_return" class="btn btn-sm btn-brand">Submit Return Request</button>
                            </div>
                        </div>
                    </form>

                    <!-- List existing returns -->
                    <?php if (!empty($existing_returns)): ?>
                        <div class="border-top pt-3">
                            <h6 class="fw-bold mb-3 text-secondary">Active Request Logs</h6>
                            <div class="list-group list-group-flush">
                                <?php foreach ($existing_returns as $r): ?>
                                    <?php 
                                    // Fetch item name
                                    $item_name = 'Selected Item';
                                    foreach ($order_items as $oi) {
                                        if ($oi['id'] == $r['order_item_id']) {
                                            $item_name = $oi['product_name'] . ' (' . $oi['size_name'] . ', ' . $oi['color_name'] . ')';
                                            break;
                                        }
                                    }
                                    ?>
                                    <div class="list-group-item px-0 py-2 small d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo e($item_name); ?></strong>
                                            <div class="text-muted" style="font-size:11px;">
                                                Type: <?php echo strtoupper($r['request_type']); ?> | Reason: <?php echo strtoupper(str_replace('_', ' ', $r['reason'])); ?>
                                            </div>
                                            <div class="text-secondary mt-1"><em>"<?php echo e($r['description']); ?>"</em></div>
                                            <?php if($r['admin_notes']): ?>
                                                <div class="text-muted mt-1" style="font-size:11px;">Admin notes: <?php echo e($r['admin_notes']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge <?php echo $r['status'] === 'approved' ? 'bg-success' : ($r['status'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                                            <?php echo strtoupper($r['status']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
