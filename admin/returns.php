<?php
// admin/returns.php
// Global Customer Returns & Exchanges Moderation Dashboard

$page_title = 'Return & Exchange Requests';
$active_menu = 'returns';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Check permissions
require_admin_permission('orders');

$error = '';
$success = '';

// Handle Return Approvals or Rejections
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['moderate_return'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        $return_id = (int)$_POST['return_id'];
        $status = $_POST['return_status']; // approved or rejected
        $admin_notes = trim($_POST['admin_notes'] ?? '');

        if ($status !== 'approved' && $status !== 'rejected') {
            $error = 'Invalid return moderation status.';
        } else {
            try {
                $pdo->beginTransaction();

                // Fetch return request info
                $ret_stmt = $pdo->prepare("SELECT * FROM returns WHERE id = ? LIMIT 1");
                $ret_stmt->execute([$return_id]);
                $ret = $ret_stmt->fetch();

                if ($ret) {
                    if ($ret['status'] !== 'pending') {
                        $error = 'This return request has already been moderated.';
                        $pdo->rollBack();
                    } else {
                        // Update return request status
                        $up_ret = $pdo->prepare("UPDATE returns SET status = ?, admin_notes = ? WHERE id = ?");
                        $up_ret->execute([$status, $admin_notes, $return_id]);

                        // If approved and return type is return (refund/restock), adjust product stock
                        if ($status === 'approved') {
                            if ($ret['request_type'] === 'return') {
                                // Mark order item as returned and increment product_variants stock
                                $item_up = $pdo->prepare("
                                    UPDATE product_variants 
                                    SET stock = stock + (SELECT quantity FROM order_items WHERE id = ?) 
                                    WHERE id = (SELECT variant_id FROM order_items WHERE id = ?)
                                ");
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
                        
                        // Preserve filter params in redirect
                        $redirect_query = [];
                        if (isset($_GET['status'])) $redirect_query['status'] = $_GET['status'];
                        if (isset($_GET['type'])) $redirect_query['type'] = $_GET['type'];
                        $q_str = !empty($redirect_query) ? '?' . http_build_query($redirect_query) : '';
                        
                        header('Location: returns.php' . $q_str);
                        exit;
                    }
                } else {
                    $error = 'Return request not found.';
                    $pdo->rollBack();
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Failed to moderate return: ' . $e->getMessage();
            }
        }
    }
}

// Fetch return requests with filters
try {
    $status_filter = $_GET['status'] ?? '';
    $type_filter = $_GET['type'] ?? '';
    
    $query_str = "
        SELECT r.*, oi.product_name, oi.sku as variant_sku, oi.size_name, oi.color_name, 
               oi.quantity, oi.price, o.order_number, c.name as customer_name, c.email as customer_email
        FROM returns r 
        JOIN order_items oi ON r.order_item_id = oi.id 
        JOIN orders o ON r.order_id = o.id
        JOIN customers c ON r.customer_id = c.id
    ";
    
    $where_clauses = [];
    $params = [];
    
    if (!empty($status_filter)) {
        $where_clauses[] = "r.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($type_filter)) {
        $where_clauses[] = "r.request_type = ?";
        $params[] = $type_filter;
    }
    
    if (!empty($where_clauses)) {
        $query_str .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    $query_str .= " ORDER BY r.created_at DESC";
    
    $stmt = $pdo->prepare($query_str);
    $stmt->execute($params);
    $returns_list = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $returns_list = [];
    $error = 'Failed to fetch returns: ' . $e->getMessage();
}
?>

<div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <h5 class="fw-bold mb-0">Customer Returns & Exchanges Ledger</h5>
        
        <!-- Filters panel -->
        <div class="d-flex flex-wrap gap-2">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="filterStatusDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    Status: <?php echo !empty($status_filter) ? ucfirst($status_filter) : 'All'; ?>
                </button>
                <ul class="dropdown-menu" aria-labelledby="filterStatusDropdown">
                    <li><a class="dropdown-item <?php echo empty($status_filter) ? 'active' : ''; ?>" href="returns.php?type=<?php echo urlencode($type_filter); ?>">All Statuses</a></li>
                    <li><a class="dropdown-item <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" href="returns.php?status=pending&type=<?php echo urlencode($type_filter); ?>">Pending</a></li>
                    <li><a class="dropdown-item <?php echo $status_filter === 'approved' ? 'active' : ''; ?>" href="returns.php?status=approved&type=<?php echo urlencode($type_filter); ?>">Approved</a></li>
                    <li><a class="dropdown-item <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" href="returns.php?status=rejected&type=<?php echo urlencode($type_filter); ?>">Rejected</a></li>
                </ul>
            </div>
            
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="filterTypeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    Type: <?php echo !empty($type_filter) ? ucfirst($type_filter) : 'All'; ?>
                </button>
                <ul class="dropdown-menu" aria-labelledby="filterTypeDropdown">
                    <li><a class="dropdown-item <?php echo empty($type_filter) ? 'active' : ''; ?>" href="returns.php?status=urlencode($status_filter);">All Types</a></li>
                    <li><a class="dropdown-item <?php echo $type_filter === 'return' ? 'active' : ''; ?>" href="returns.php?type=return&status=<?php echo urlencode($status_filter); ?>">Return & Refund</a></li>
                    <li><a class="dropdown-item <?php echo $type_filter === 'exchange' ? 'active' : ''; ?>" href="returns.php?type=exchange&status=<?php echo urlencode($status_filter); ?>">Size Exchange</a></li>
                </ul>
            </div>
            
            <?php if (!empty($status_filter) || !empty($type_filter)): ?>
                <a href="returns.php" class="btn btn-sm btn-link text-danger text-decoration-none d-flex align-items-center">
                    <i class="bi bi-x-circle me-1"></i> Clear Filters
                </a>
            <?php endif; ?>
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
                    <th>Product / Sizing</th>
                    <th>Qty / Value</th>
                    <th>Request Info</th>
                    <th>Reason / Details</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 140px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($returns_list)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No matching return or exchange requests found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($returns_list as $row): ?>
                        <tr class="<?php echo $row['status'] === 'pending' ? 'table-warning-subtle fw-semibold' : ''; ?>">
                            <td>
                                <a href="orders.php?view=<?php echo $row['order_id']; ?>" class="fw-bold text-decoration-none">
                                    <?php echo e($row['order_number']); ?>
                                </a>
                            </td>
                            <td>
                                <span class="d-block text-dark fw-bold"><?php echo e($row['customer_name']); ?></span>
                                <span class="text-muted small"><?php echo e($row['customer_email']); ?></span>
                            </td>
                            <td>
                                <span class="fw-bold text-dark d-block"><?php echo e($row['product_name']); ?></span>
                                <small class="text-muted">SKU: <?php echo e($row['variant_sku']); ?> | Size: <?php echo e($row['size_name']); ?> | Color: <?php echo e($row['color_name']); ?></small>
                            </td>
                            <td>
                                <span class="d-block"><?php echo $row['quantity']; ?> pcs</span>
                                <span class="fw-bold text-rose small"><?php echo format_price($row['price'] * $row['quantity']); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo strtoupper($row['request_type']); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-dark mb-1 d-inline-block"><?php echo strtoupper(str_replace('_', ' ', $row['reason'])); ?></span>
                                <div class="small text-secondary" style="max-width: 250px; overflow-wrap: break-word;">
                                    <em>"<?php echo e($row['description']); ?>"</em>
                                </div>
                                <?php if ($row['admin_notes']): ?>
                                    <div class="mt-1 p-1 bg-light border rounded small text-muted">
                                        <strong>Admin:</strong> <?php echo e($row['admin_notes']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php elseif ($row['status'] === 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($row['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-outline-primary px-3 py-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#moderateReturnModal" 
                                            data-return-id="<?php echo $row['id']; ?>"
                                            data-customer="<?php echo e($row['customer_name']); ?>"
                                            data-order="<?php echo e($row['order_number']); ?>"
                                            data-product="<?php echo e($row['product_name']); ?> (<?php echo e($row['size_name']); ?>, <?php echo e($row['color_name']); ?>)"
                                            data-type="<?php echo e($row['request_type']); ?>"
                                            data-reason="<?php echo e(str_replace('_', ' ', $row['reason'])); ?>"
                                            data-description="<?php echo e($row['description']); ?>">
                                        Moderate
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small">Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Moderation Modal -->
<div class="modal fade" id="moderateReturnModal" tabindex="-1" aria-labelledby="moderateReturnModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius:16px;">
            <div class="modal-header border-0 bg-light" style="border-top-left-radius:16px; border-top-right-radius:16px;">
                <h5 class="modal-title fw-bold" id="moderateReturnModalLabel">Moderate Return Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="returns.php<?php 
                $query_params = [];
                if (!empty($status_filter)) $query_params['status'] = $status_filter;
                if (!empty($type_filter)) $query_params['type'] = $type_filter;
                echo !empty($query_params) ? '?' . http_build_query($query_params) : '';
            ?>" method="POST">
                <div class="modal-body p-4">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="return_id" id="modal-return-id">
                    <input type="hidden" name="moderate_return" value="1">
                    
                    <div class="mb-2 text-secondary small">
                        <div>Customer: <strong class="text-dark" id="modal-info-customer"></strong></div>
                        <div>Order No: <strong class="text-dark" id="modal-info-order"></strong></div>
                        <div>Product: <strong class="text-dark" id="modal-info-product"></strong></div>
                        <div>Type: <span class="badge bg-secondary" id="modal-info-type"></span></div>
                        <div>Reason: <span class="badge bg-dark" id="modal-info-reason"></span></div>
                    </div>
                    
                    <div class="my-3 p-2 bg-light border rounded small text-secondary">
                        <strong>Issue description:</strong>
                        <div id="modal-info-description" class="fst-italic mt-1"></div>
                    </div>

                    <div class="mb-3">
                        <label for="admin_notes" class="form-label small fw-bold">Internal Moderation Notes / Explanation <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" name="admin_notes" id="admin_notes" required rows="3" placeholder="Enter reason for approval or rejection (e.g. Received damaged, restocking items...)"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="return_status" value="rejected" class="btn btn-danger rounded-pill px-3">Reject Request</button>
                    <button type="submit" name="return_status" value="approved" class="btn btn-success rounded-pill px-3">Approve & Restock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const moderateModal = document.getElementById('moderateReturnModal');
    if (!moderateModal) return;

    moderateModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-return-id');
        const customer = button.getAttribute('data-customer');
        const order = button.getAttribute('data-order');
        const product = button.getAttribute('data-product');
        const r_type = button.getAttribute('data-type');
        const reason = button.getAttribute('data-reason');
        const description = button.getAttribute('data-description');

        // Populate modal text/fields
        moderateModal.querySelector('#modal-return-id').value = id;
        moderateModal.querySelector('#modal-info-customer').textContent = customer;
        moderateModal.querySelector('#modal-info-order').textContent = order;
        moderateModal.querySelector('#modal-info-product').textContent = product;
        
        const typeBadge = moderateModal.querySelector('#modal-info-type');
        typeBadge.textContent = r_type.toUpperCase();
        
        const reasonBadge = moderateModal.querySelector('#modal-info-reason');
        reasonBadge.textContent = reason.toUpperCase();
        
        moderateModal.querySelector('#modal-info-description').textContent = '"' + description + '"';
        
        // Reset notes textarea
        moderateModal.querySelector('#admin_notes').value = '';
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
