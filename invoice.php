<?php
// invoice.php
// Printable HTML Invoice sheet

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) {
    die("Invalid invoice reference.");
}

try {
    // Fetch Order details
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        die("Invoice not found.");
    }

    // Security check: Only the owning customer or an administrator can print
    $authorized = false;
    if (is_admin_logged_in()) {
        $authorized = true;
    } elseif (is_customer_logged_in() && $_SESSION['customer_id'] == $order['customer_id']) {
        $authorized = true;
    }

    if (!$authorized) {
        die("Unauthorized access to this invoice.");
    }

    // Fetch order line items
    $items_stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $items_stmt->execute([$order_id]);
    $order_items = $items_stmt->fetchAll();

} catch (PDOException $e) {
    die("A database error occurred loading invoice.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo e($order['order_number']); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Bodoni Moda & Montserrat -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:wght@500;600&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #ffffff;
            font-family: 'Montserrat', sans-serif;
            color: #2E2E2E;
        }
        .invoice-card {
            border: 1px solid #e0e0e0;
            padding: 40px;
            margin-top: 30px;
            background-color: #ffffff;
        }
        .invoice-title {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #5D3A42;
        }
        @media print {
            body {
                background-color: #ffffff;
            }
            .invoice-card {
                border: none;
                padding: 0;
                margin-top: 0;
            }
            .print-btn {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="container pb-5">
    <div class="d-flex justify-content-end print-btn mt-4">
        <button onclick="window.print();" class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> Print Invoice</button>
    </div>

    <div class="invoice-card rounded shadow-sm">
        <!-- Header -->
        <div class="row g-4 border-bottom pb-4 mb-4">
            <div class="col-7">
                <img src="assets/images/logo.png" alt="<?php echo e(SITE_NAME); ?>" height="42" class="mb-2 d-block">
                <div class="small text-muted mt-2">
                    <h5 class="fw-bold text-dark mb-0"><?php echo e(COMPANY_LEGAL_NAME); ?></h5>
                    <div class="small text-secondary mb-1">Brand: <strong><?php echo e(SITE_NAME); ?></strong></div>
                    <div><?php echo nl2br(e(COMPANY_ADDRESS)); ?></div>
                    <?php if (!empty(COMPANY_GSTIN)): ?>
                        <div class="mt-1"><strong>GSTIN:</strong> <?php echo e(COMPANY_GSTIN); ?></div>
                    <?php endif; ?>
                    <?php if (!empty(COMPANY_PAN)): ?>
                        <div><strong>PAN:</strong> <?php echo e(COMPANY_PAN); ?></div>
                    <?php endif; ?>
                    <?php if (!empty(COMPANY_REG_NUMBER)): ?>
                        <div><strong>Reg No:</strong> <?php echo e(COMPANY_REG_NUMBER); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-5 text-end">
                <h4 class="fw-bold text-secondary">TAX INVOICE</h4>
                <div class="small text-muted mt-2">
                    Invoice No: <strong><?php echo e($order['order_number']); ?></strong><br>
                    Date: <strong><?php echo date('d-M-Y', strtotime($order['created_at'])); ?></strong><br>
                    Payment Mode: <strong><?php echo e($order['payment_method']); ?></strong>
                </div>
            </div>
        </div>

        <!-- Addresses details -->
        <div class="row g-4 mb-5">
            <div class="col-6">
                <h6 class="fw-bold text-muted text-uppercase mb-2" style="font-size: 11px; letter-spacing: 0.5px;">Billed To:</h6>
                <div class="fw-bold text-dark mb-1"><?php echo e($order['receiver_name']); ?></div>
                <div class="small text-muted">Phone: <?php echo e($order['receiver_phone']); ?></div>
            </div>
            <div class="col-6 text-end">
                <h6 class="fw-bold text-muted text-uppercase mb-2" style="font-size: 11px; letter-spacing: 0.5px;">Shipped To Address:</h6>
                <div class="small text-muted">
                    <?php echo e($order['address_line1']); ?><br>
                    <?php if($order['address_line2']) echo e($order['address_line2']) . '<br>'; ?>
                    <?php echo e($order['city']); ?>, <?php echo e($order['state']); ?> - <?php echo e($order['pincode']); ?>
                </div>
            </div>
        </div>

        <!-- Table Grid details -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle small">
                <thead class="table-light text-secondary">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Product / Variant SKU</th>
                        <th>Attributes</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">GST %</th>
                        <th class="text-end">GST Amount</th>
                        <th class="text-end">Total Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $count = 1; ?>
                    <?php foreach ($order_items as $item): ?>
                        <?php 
                        $is_combo = !empty($item['combo_product_id']) || !empty($item['combo_config']);
                        $parsed_cfg = $is_combo ? json_decode($item['combo_config'], true) : null;
                        ?>
                        <tr>
                            <td><?php echo $count++; ?></td>
                            <td>
                                <strong><?php echo e($item['product_name']); ?></strong>
                                <?php if ($is_combo && !empty($parsed_cfg)): ?>
                                    <div class="mt-1 small text-muted">
                                        <em>Curated Combo Set:</em>
                                        <ul class="mb-0 ps-3">
                                            <?php foreach ($parsed_cfg as $p_comp): ?>
                                                <li><?php echo e($p_comp['product_name']); ?> (Size: <?php echo e($p_comp['size_code']); ?>, Color: <?php echo e($p_comp['color_name']); ?>)</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php else: ?>
                                    <br><code class="small text-muted">SKU: <?php echo e($item['sku']); ?></code>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_combo): ?>
                                    <span class="badge bg-light text-dark border">Combo Bundle</span>
                                <?php else: ?>
                                    Size: <?php echo e($item['size_name']); ?> | Color: <?php echo e($item['color_name']); ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?php echo format_price($item['price']); ?></td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="text-end"><?php echo $item['tax_percentage']; ?>%</td>
                            <td class="text-end text-muted"><?php echo format_price($item['tax_amount']); ?></td>
                            <td class="text-end fw-bold"><?php echo format_price($item['total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals summary block -->
        <div class="row g-4 justify-content-end">
            <div class="col-12 col-md-5">
                <table class="table table-sm table-borderless small">
                    <tbody>
                        <tr>
                            <td class="text-muted">Subtotal:</td>
                            <td class="text-end fw-bold"><?php echo format_price($order['subtotal']); ?></td>
                        </tr>
                        <?php if ($order['discount'] > 0): ?>
                            <tr>
                                <td class="text-muted">Discount Coupon (<?php echo e($order['coupon_code']); ?>):</td>
                                <td class="text-end fw-bold text-danger">-<?php echo format_price($order['discount']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="text-muted">Shipping Charges:</td>
                            <td class="text-end fw-bold"><?php echo format_price($order['shipping_charge']); ?></td>
                        </tr>
                        <tr class="border-top">
                            <td class="text-muted">GST Tax Amount Included:</td>
                            <td class="text-end text-muted"><?php echo format_price($order['tax_amount']); ?></td>
                        </tr>
                        <tr class="border-top fs-5">
                            <td class="fw-bold text-dark">Grand Net Payable:</td>
                            <td class="text-end fw-bold text-rose"><?php echo format_price($order['grand_total']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sign signatures footer -->
        <div class="row g-4 mt-5 pt-4 border-top">
            <div class="col-7 small text-muted">
                <strong>Important Information / Terms & Conditions:</strong><br>
                <?php echo nl2br(e(INVOICE_TERMS_NOTES)); ?>
            </div>
            <div class="col-5 text-end small">
                <div class="mt-4">For <strong><?php echo e(COMPANY_LEGAL_NAME); ?></strong></div>
                <div class="fw-bold text-dark mt-2 border-top pt-1 d-inline-block text-center" style="min-width: 190px;">
                    <?php echo e(INVOICE_AUTHORIZED_SIGNATORY ?: 'Authorized Signatory'); ?>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
