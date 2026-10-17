<?php
// admin/orders.php
// Order & Shipment Management CRUD with Return Actions

$page_title = 'Orders Management';
$active_menu = 'orders';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Check permissions
require_admin_permission('orders');

$view_id = (int)($_GET['view'] ?? 0);
$error = '';
$success = '';

// Handle Status or Shipment Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        $order_id = (int)$_POST['order_id'];
        $order_status = $_POST['order_status'];
        $payment_status = $_POST['payment_status'];
        $courier_name = trim($_POST['courier_name'] ?? '');
        $tracking_number = trim($_POST['tracking_number'] ?? '');

        try {
            $pdo->beginTransaction();

            // Fetch previous details
            $prev_stmt = $pdo->prepare("SELECT order_status, tracking_number FROM orders WHERE id = ? LIMIT 1");
            $prev_stmt->execute([$order_id]);
            $prev = $prev_stmt->fetch();

            // Update order core details
            $up_stmt = $pdo->prepare("
                UPDATE orders SET 
                order_status = ?, payment_status = ?, courier_name = ?, tracking_number = ? 
                WHERE id = ?
            ");
            $up_stmt->execute([$order_status, $payment_status, $courier_name, $tracking_number, $order_id]);

            // Adjust stock back if status was changed to cancelled or returned, and it wasn't previously cancelled/returned
            if (($order_status === 'cancelled' || $order_status === 'returned') && ($prev && $prev['order_status'] !== 'cancelled' && $prev['order_status'] !== 'returned')) {
                // Return stock of items
                $items_stmt = $pdo->prepare("SELECT variant_id, combo_config, quantity FROM order_items WHERE order_id = ?");
                $items_stmt->execute([$order_id]);
                $items = $items_stmt->fetchAll();

                foreach ($items as $item) {
                    if (!empty($item['combo_config'])) {
                        $c_config = json_decode($item['combo_config'], true) ?? [];
                        foreach ($c_config as $sub_var) {
                            $ref_stmt = $pdo->prepare("UPDATE product_variants SET stock = stock + ? WHERE id = ?");
                            $ref_stmt->execute([$item['quantity'], $sub_var['variant_id']]);

                            $log_stmt = $pdo->prepare("
                                INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) 
                                VALUES (?, ?, 'return', ?)
                            ");
                            $notes = "Stock returned due to combo order $order_status: " . $order_id;
                            $log_stmt->execute([$sub_var['variant_id'], $item['quantity'], $notes]);
                        }
                    } elseif (!empty($item['variant_id'])) {
                        $ref_stmt = $pdo->prepare("UPDATE product_variants SET stock = stock + ? WHERE id = ?");
                        $ref_stmt->execute([$item['quantity'], $item['variant_id']]);

                        // Log inventory return
                        $log_stmt = $pdo->prepare("
                            INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) 
                            VALUES (?, ?, 'return', ?)
                        ");
                        $notes = "Stock returned due to order $order_status: " . $order_id;
                        $log_stmt->execute([$item['variant_id'], $item['quantity'], $notes]);
                    }
                }
            }

            // Create or update shipments tracking details
            if (!empty($courier_name) && !empty($tracking_number)) {
                $ship_check = $pdo->prepare("SELECT id FROM shipments WHERE order_id = ? LIMIT 1");
                $ship_check->execute([$order_id]);
                
                if ($ship_check->fetch()) {
                    $ship_up = $pdo->prepare("UPDATE shipments SET courier_name = ?, tracking_number = ?, status = ? WHERE order_id = ?");
                    $ship_up->execute([$courier_name, $tracking_number, $order_status, $order_id]);
                } else {
                    $ship_in = $pdo->prepare("INSERT INTO shipments (order_id, courier_name, tracking_number, status) VALUES (?, ?, ?, ?)");
                    $ship_in->execute([$order_id, $courier_name, $tracking_number, $order_status]);
                }
            }

            $pdo->commit();
            log_admin_action($current_admin_id, "Updated order status for order id: $order_id to $order_status");
            set_flash_message('success', 'Order status details updated successfully!');
            header('Location: orders.php?view=' . $order_id);
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Failed to update order status: ' . $e->getMessage();
        }
    }
}

// Handle Return Approvals or Rejections
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['moderate_return'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        $return_id = (int)$_POST['return_id'];
        $status = $_POST['return_status']; // approved or rejected
        $admin_notes = trim($_POST['admin_notes'] ?? '');

        try {
            $pdo->beginTransaction();

            // Fetch return request info
            $ret_stmt = $pdo->prepare("SELECT * FROM returns WHERE id = ? LIMIT 1");
            $ret_stmt->execute([$return_id]);
            $ret = $ret_stmt->fetch();

            if ($ret) {
                // Update return request status
                $up_ret = $pdo->prepare("UPDATE returns SET status = ?, admin_notes = ? WHERE id = ?");
                $up_ret->execute([$status, $admin_notes, $return_id]);

                // If approved and return type is return (refund/restock), adjust product stock
                if ($status === 'approved') {
                    // Update order status/return references if required
                    if ($ret['request_type'] === 'return') {
                        // Mark order item as returned
                        $item_up = $pdo->prepare("UPDATE product_variants SET stock = stock + (SELECT quantity FROM order_items WHERE id = ?) WHERE id = (SELECT variant_id FROM order_items WHERE id = ?)");
                        $item_up->execute([$ret['order_item_id'], $ret['order_item_id']]);

                        // Log inventory restock from return
                        $qty_stmt = $pdo->prepare("SELECT variant_id, quantity FROM order_items WHERE id = ? LIMIT 1");
                        $qty_stmt->execute([$ret['order_item_id']]);
                        $ord_item = $qty_stmt->fetch();
                        if ($ord_item) {
                            $log_stmt = $pdo->prepare("
                                INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) 
                                VALUES (?, ?, 'return', ?)
                            ");
                            $log_notes = "Restock from approved return id: " . $return_id;
                            $log_stmt->execute([$ord_item['variant_id'], $ord_item['quantity'], $log_notes]);
                        }
                    }
                }

                $pdo->commit();
                log_admin_action($current_admin_id, "Moderated return request id: $return_id as $status");
                set_flash_message('success', 'Return request has been ' . $status . '!');
                header('Location: orders.php?view=' . $ret['order_id']);
                exit;
            } else {
                $error = 'Return request not found.';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Failed to moderate return: ' . $e->getMessage();
        }
    }
}

// ----------------------------------------------------
// VIEW SINGLE ORDER DETAILS
// ----------------------------------------------------
if ($view_id):
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone 
            FROM orders o 
            JOIN customers c ON o.customer_id = c.id 
            WHERE o.id = ? LIMIT 1
        ");
        $stmt->execute([$view_id]);
        $order = $stmt->fetch();

        if (!$order) {
            set_flash_message('error', 'Order not found.');
            header('Location: orders.php');
            exit;
        }

        // Fetch ordered items
        $items_stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $items_stmt->execute([$view_id]);
        $order_items = $items_stmt->fetchAll();

        // Fetch return requests
        $returns_stmt = $pdo->prepare("
            SELECT r.*, oi.product_name, oi.sku as variant_sku, oi.size_name, oi.color_name 
            FROM returns r 
            JOIN order_items oi ON r.order_item_id = oi.id 
            WHERE r.order_id = ?
        ");
        $returns_stmt->execute([$view_id]);
        $return_requests = $returns_stmt->fetchAll();

    } catch (PDOException $e) {
        $error = 'Failed to fetch details: ' . $e->getMessage();
    }
?>

    <div class="mb-4">
        <a href="orders.php" class="btn btn-outline-secondary btn-sm rounded-pill">
            <i class="bi bi-arrow-left"></i> Back to Orders list
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Order details card -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Order: <?php echo e($order['order_number']); ?></h5>
                    <a href="../invoice.php?order_id=<?php echo $order['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-printer"></i> Print Invoice
                    </a>
                </div>
                <div class="text-muted small mb-4">Placed on: <?php echo date('d-M-Y H:i:s', strtotime($order['created_at'])); ?></div>

                <h6 class="fw-bold text-secondary border-bottom pb-2 mb-3">Ordered Items</h6>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Product / SKU</th>
                                <th>Attributes</th>
                                <th class="text-center">Qty</th>
                                <th>Price</th>
                                <th>GST (Tax)</th>
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
                                                <div class="fw-bold small text-rose mb-1"><i class="bi bi-stars"></i> Curated Combo Set Includes:</div>
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
                                            <code class="small text-muted">SKU: <?php echo e($item['sku']); ?></code>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_combo): ?>
                                            <span class="badge bg-purple-subtle text-purple border"><i class="bi bi-stars"></i> Custom Combo</span>
                                        <?php else: ?>
                                            <span class="small text-muted">Size: <?php echo e($item['size_name']); ?> | Color: <?php echo e($item['color_name']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                                    <td><?php echo format_price($item['price']); ?></td>
                                    <td><?php echo format_price($item['tax_amount']); ?> <span class="text-muted small">(<?php echo $item['tax_percentage']; ?>%)</span></td>
                                    <td class="text-end fw-bold"><?php echo format_price($item['total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Totals row -->
                <div class="row mt-4 pt-3 border-top justify-content-end">
                    <div class="col-12 col-md-5">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Subtotal:</span>
                            <span class="fw-bold"><?php echo format_price($order['subtotal']); ?></span>
                        </div>
                        <?php if ($order['discount'] > 0): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Discount (<?php echo e($order['coupon_code']); ?>):</span>
                                <span class="fw-bold text-danger">-<?php echo format_price($order['discount']); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Shipping Charges:</span>
                            <span class="fw-bold"><?php echo format_price($order['shipping_charge']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">GST Total included:</span>
                            <span class="fw-bold"><?php echo format_price($order['tax_amount']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-2">
                            <span class="fw-bold text-dark fs-5">Grand Total:</span>
                            <span class="fw-bold text-rose fs-5"><?php echo format_price($order['grand_total']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer & Shipping details -->
            <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                <h6 class="fw-bold mb-3"><i class="bi bi-geo-alt text-primary me-2"></i> Shipping & Contact Details</h6>
                <div class="row g-3">
                    <div class="col-12 col-md-6 border-end">
                        <div class="fw-bold text-dark mb-2">Customer Profile</div>
                        <div>Name: <strong><?php echo e($order['customer_name']); ?></strong></div>
                        <div>Email: <?php echo e($order['customer_email']); ?></div>
                        <div>Phone: <?php echo e($order['customer_phone']); ?></div>
                    </div>
                    <div class="col-12 col-md-6 ps-md-4">
                        <div class="fw-bold text-dark mb-2">Delivery Address</div>
                        <div>Receiver: <strong><?php echo e($order['receiver_name']); ?></strong></div>
                        <div>Phone: <?php echo e($order['receiver_phone']); ?></div>
                        <div class="text-muted small">
                            <?php echo e($order['address_line1']); ?><br>
                            <?php if($order['address_line2']) echo e($order['address_line2']) . '<br>'; ?>
                            <?php echo e($order['city']); ?>, <?php echo e($order['state']); ?> - <?php echo e($order['pincode']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order actions forms -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
                <h5 class="fw-bold mb-4">Manage Order Status</h5>
                <form action="orders.php?view=<?php echo $view_id; ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">

                    <div class="form-floating mb-3">
                        <select class="form-select" id="order_status" name="order_status">
                            <option value="pending" <?php echo $order['order_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $order['order_status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $order['order_status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $order['order_status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $order['order_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="returned" <?php echo $order['order_status'] === 'returned' ? 'selected' : ''; ?>>Returned</option>
                        </select>
                        <label for="order_status">Order Lifecycle Status</label>
                    </div>

                    <div class="form-floating mb-3">
                        <select class="form-select" id="payment_status" name="payment_status">
                            <option value="pending" <?php echo $order['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="paid" <?php echo $order['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="refunded" <?php echo $order['payment_status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            <option value="failed" <?php echo $order['payment_status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                        <label for="payment_status">Payment Status</label>
                    </div>

                    <div class="form-floating mb-3 pt-1">
                        <input type="text" class="form-control" id="courier_name" name="courier_name" value="<?php echo e($order['courier_name'] ?? ''); ?>" placeholder="Courier Partner Name">
                        <label for="courier_name">Courier Partner Name</label>
                    </div>

                    <div class="form-floating mb-4">
                        <input type="text" class="form-control" id="tracking_number" name="tracking_number" value="<?php echo e($order['tracking_number'] ?? ''); ?>" placeholder="AWB Courier Tracking ID">
                        <label for="tracking_number">AWB Courier Tracking ID</label>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="update_order" class="btn btn-brand w-100">Update Order Details</button>
                    </div>
                </form>
            </div>

            <!-- Returns panel if requests exist -->
            <?php if (!empty($return_requests)): ?>
                <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                    <h5 class="fw-bold mb-3 text-danger"><i class="bi bi-arrow-left-right"></i> Return Requests</h5>
                    <?php foreach ($return_requests as $req): ?>
                        <div class="p-3 border rounded mb-3 bg-light-subtle">
                            <div class="fw-bold mb-1"><?php echo e($req['product_name']); ?></div>
                            <small class="text-muted d-block">SKU: <?php echo e($req['variant_sku']); ?> | Style: <?php echo e($req['size_name']); ?>, <?php echo e($req['color_name']); ?></small>
                            <div class="my-2 small">
                                <span class="badge bg-secondary"><?php echo strtoupper($req['request_type']); ?></span>
                                <span class="badge bg-dark"><?php echo strtoupper(str_replace('_', ' ', $req['reason'])); ?></span>
                            </div>
                            <p class="small text-dark mb-2"><em>"<?php echo e($req['description']); ?>"</em></p>

                            <?php if ($req['status'] === 'pending'): ?>
                                <form action="orders.php?view=<?php echo $view_id; ?>" method="POST" class="mt-2">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="return_id" value="<?php echo $req['id']; ?>">
                                    <input type="hidden" name="moderate_return" value="1">
                                    
                                    <div class="form-floating mb-3">
                                        <textarea class="form-control" name="admin_notes" id="admin_notes_<?php echo $req['id']; ?>" placeholder="Admin Remarks (Reason for Decision)" style="height: 85px;" required></textarea>
                                        <label for="admin_notes_<?php echo $req['id']; ?>">Admin Remarks (Reason for Decision)</label>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" name="return_status" value="approved" class="btn btn-sm btn-success flex-grow-1">Approve</button>
                                        <button type="submit" name="return_status" value="rejected" class="btn btn-sm btn-danger flex-grow-1">Reject</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="mt-2 border-top pt-2 small">
                                    Status: 
                                    <span class="fw-bold <?php echo $req['status'] === 'approved' ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo strtoupper($req['status']); ?>
                                    </span>
                                    <?php if ($req['admin_notes']): ?>
                                        <div class="text-muted mt-1 small">Notes: <?php echo e($req['admin_notes']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else:
    // LIST ALL ORDERS
    try {
        $status_filter = $_GET['status'] ?? '';
        $query_str = "
            SELECT o.*, c.name as customer_name 
            FROM orders o 
            JOIN customers c ON o.customer_id = c.id 
        ";
        $params = [];
        if (!empty($status_filter)) {
            $query_str .= " WHERE o.order_status = ? ";
            $params[] = $status_filter;
        }
        $query_str .= " ORDER BY o.created_at DESC ";
        
        $stmt = $pdo->prepare($query_str);
        $stmt->execute($params);
        $orders_list = $stmt->fetchAll();
    } catch (PDOException $e) {
        $orders_list = [];
        $error = 'Failed to fetch orders: ' . $e->getMessage();
    }
?>

    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0">Customer Orders Dashboard</h5>
            <div class="d-flex gap-2">
                <a href="orders.php" class="btn btn-sm btn-outline-secondary <?php echo empty($status_filter) ? 'active' : ''; ?>">All</a>
                <a href="orders.php?status=pending" class="btn btn-sm btn-outline-warning <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="orders.php?status=processing" class="btn btn-sm btn-outline-primary <?php echo $status_filter === 'processing' ? 'active' : ''; ?>">Processing</a>
                <a href="orders.php?status=shipped" class="btn btn-sm btn-outline-info <?php echo $status_filter === 'shipped' ? 'active' : ''; ?>">Shipped</a>
                <a href="orders.php?status=delivered" class="btn btn-sm btn-outline-success <?php echo $status_filter === 'delivered' ? 'active' : ''; ?>">Delivered</a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Order No</th>
                        <th>Customer</th>
                        <th>Subtotal</th>
                        <th>Shipping</th>
                        <th>Grand Total</th>
                        <th>Payment Status</th>
                        <th>Order Status</th>
                        <th>Date Placed</th>
                        <th class="text-end" style="width: 100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders_list)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No matching orders found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders_list as $ord): ?>
                            <tr>
                                <td class="fw-bold"><?php echo e($ord['order_number']); ?></td>
                                <td><?php echo e($ord['customer_name']); ?></td>
                                <td><?php echo format_price($ord['subtotal']); ?></td>
                                <td><?php echo format_price($ord['shipping_charge']); ?></td>
                                <td class="fw-bold text-rose"><?php echo format_price($ord['grand_total']); ?></td>
                                <td>
                                    <?php 
                                    $pay_badge = 'bg-secondary';
                                    if($ord['payment_status'] === 'pending') $pay_badge = 'bg-warning';
                                    else if($ord['payment_status'] === 'paid') $pay_badge = 'bg-success';
                                    else if($ord['payment_status'] === 'failed') $pay_badge = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $pay_badge; ?>"><?php echo ucfirst($ord['payment_status']); ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $ord_badge = 'bg-secondary';
                                    if($ord['order_status'] === 'pending') $ord_badge = 'bg-warning';
                                    else if($ord['order_status'] === 'processing') $ord_badge = 'bg-primary';
                                    else if($ord['order_status'] === 'shipped') $ord_badge = 'bg-info';
                                    else if($ord['order_status'] === 'delivered') $ord_badge = 'bg-success';
                                    else if($ord['order_status'] === 'cancelled') $ord_badge = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $ord_badge; ?>"><?php echo ucfirst($ord['order_status']); ?></span>
                                </td>
                                <td class="small text-muted"><?php echo date('d-M-Y H:i', strtotime($ord['created_at'])); ?></td>
                                <td class="text-end">
                                    <a href="orders.php?view=<?php echo $ord['id']; ?>" class="btn btn-sm btn-outline-primary px-3 py-1">
                                        <i class="bi bi-eye"></i> Open
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
