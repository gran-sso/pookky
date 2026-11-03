<?php
// admin/shipping-label.php
// Professional E-Commerce 4x6" Shipping Label & Dispatch Slip for Pookky

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Guarantee admin login & order permissions
require_admin_permission('orders');

$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) {
    die("Invalid order ID for shipping label.");
}

try {
    // Fetch Order details with customer contact fallback
    $stmt = $pdo->prepare("
        SELECT o.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone 
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.id 
        WHERE o.id = ? LIMIT 1
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        die("Order not found.");
    }

    // Fetch order line items
    $items_stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $items_stmt->execute([$order_id]);
    $order_items = $items_stmt->fetchAll();

    // Calculate total quantity and piece count
    $total_qty = 0;
    foreach ($order_items as $item) {
        $total_qty += (int)$item['quantity'];
    }

    // Determine payment details
    $is_cod = (strtoupper($order['payment_method']) === 'COD' || stripos($order['payment_method'], 'cash') !== false);
    $is_paid = (strtolower($order['payment_status']) === 'paid');

} catch (PDOException $e) {
    die("Database error while loading order details.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Label - <?php echo e($order['order_number']); ?> | Pookky Logistics</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- JsBarcode for crisp vector barcodes -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

    <style>
        /* Base Screen Styling */
        body {
            background-color: #f1f3f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #111111;
            margin: 0;
            padding: 0;
        }

        /* Screen Top Controls Bar */
        #label-toolbar {
            background: #1f242b;
            color: #ffffff;
            padding: 12px 20px;
            position: sticky;
            top: 0;
            z-index: 9999;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }

        .label-stage {
            display: flex;
            justify-content: center;
            padding: 30px 15px 60px;
            min-height: calc(100vh - 70px);
        }

        /* 4" x 6" Standard Shipping Label Geometry (approx 100mm x 150mm) */
        .shipping-label-card {
            width: 100mm;
            min-height: 150mm;
            max-width: 100%;
            background: #ffffff;
            border: 2px solid #000000;
            padding: 4mm 5mm;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            font-size: 11px;
            line-height: 1.35;
        }

        /* A4 Mode Styling (cutout guides) */
        .mode-a4 .shipping-label-card {
            border: 1.5px dashed #666666;
            margin: 20px auto;
        }
        .cut-guide-notice {
            display: none;
            text-align: center;
            font-size: 10px;
            color: #777777;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }
        .mode-a4 .cut-guide-notice {
            display: block;
        }

        /* Internal Sections */
        .label-header {
            border-bottom: 1.5px solid #000000;
            padding-bottom: 4px;
            margin-bottom: 5px;
        }
        .brand-title {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #000000;
            margin: 0;
            line-height: 1.1;
        }
        .brand-subtitle {
            font-size: 9px;
            color: #555555;
            letter-spacing: 0.2px;
        }

        /* Barcode Area */
        .barcode-box {
            text-align: center;
            padding: 2px 0 4px;
            border-bottom: 1.5px solid #000000;
            margin-bottom: 5px;
        }
        .barcode-svg {
            max-width: 100%;
            height: 42px;
            display: block;
            margin: 0 auto;
        }
        .barcode-text {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-top: 1px;
        }

        /* Payment Status Banner */
        .payment-banner {
            text-align: center;
            padding: 4px 6px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border: 1.5px solid #000000;
            margin-bottom: 6px;
        }
        .payment-banner.cod {
            background-color: #000000;
            color: #ffffff;
        }
        .payment-banner.prepaid {
            background-color: #ffffff;
            color: #000000;
        }

        /* Deliver To Box (Hero Section) */
        .deliver-box {
            border: 1.5px solid #000000;
            padding: 6px 8px;
            margin-bottom: 6px;
            background: #ffffff;
        }
        .section-tag {
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #444444;
            margin-bottom: 2px;
        }
        .receiver-name {
            font-size: 14px;
            font-weight: 800;
            color: #000000;
            margin-bottom: 2px;
        }
        .receiver-phone {
            font-size: 12px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 4px;
        }
        .receiver-address {
            font-size: 11px;
            color: #111111;
            line-height: 1.3;
        }
        .pincode-highlight {
            display: inline-block;
            margin-top: 5px;
            padding: 3px 8px;
            border: 2px solid #000000;
            background: #000000;
            color: #ffffff;
            font-family: 'Courier New', Courier, monospace;
            font-size: 15px;
            font-weight: 900;
            letter-spacing: 1.5px;
        }

        /* Dispatch & Logistics Box */
        .dispatch-meta-grid {
            display: flex;
            border: 1px solid #000000;
            margin-bottom: 6px;
        }
        .dispatch-cell {
            flex: 1;
            padding: 4px 6px;
        }
        .dispatch-cell:first-child {
            border-right: 1px solid #000000;
        }

        /* Item Checklist / Manifest Summary */
        .manifest-box {
            border: 1px solid #000000;
            padding: 4px 6px;
            font-size: 10px;
            margin-bottom: 5px;
        }
        .manifest-title {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9px;
            border-bottom: 1px dashed #777;
            padding-bottom: 2px;
            margin-bottom: 3px;
            display: flex;
            justify-content: space-between;
        }
        .manifest-list {
            list-style: none;
            padding-left: 0;
            margin-bottom: 0;
        }
        .manifest-list li {
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .manifest-sub {
            padding-left: 8px;
            color: #444444;
            font-size: 9px;
        }

        /* Label Footer / Disclaimers */
        .label-footer {
            font-size: 8px;
            color: #555555;
            text-align: center;
            border-top: 1px solid #999;
            padding-top: 3px;
            margin-top: auto;
        }

        /* Print Media Queries */
        @media print {
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            #label-toolbar {
                display: none !important;
            }
            .label-stage {
                padding: 0 !important;
                margin: 0 !important;
                min-height: auto !important;
                display: block !important;
            }
            .shipping-label-card {
                box-shadow: none !important;
                border: 2px solid #000000 !important;
                width: 100mm !important;
                min-height: 150mm !important;
                margin: 0 auto !important;
                page-break-inside: avoid !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .mode-a4 .shipping-label-card {
                border: 1.5px dashed #000000 !important;
                margin-top: 10mm !important;
            }
            .cut-guide-notice {
                display: block !important;
            }
            @page {
                size: 100mm 150mm;
                margin: 0;
            }
        }
    </style>
</head>
<body>

<!-- Fixed Top Toolbar for Admin Actions -->
<div id="label-toolbar">
    <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-3">
            <a href="orders.php?view=<?php echo $order['id']; ?>" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Order
            </a>
            <span class="fw-bold d-none d-sm-inline">
                <i class="bi bi-tag-fill me-1 text-warning"></i> Dispatch Label: <?php echo e($order['order_number']); ?>
            </span>
        </div>

        <div class="d-flex align-items-center gap-2">
            <!-- Format Switcher: 4x6 Thermal vs A4 Desktop -->
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-secondary active" id="btn-thermal" onclick="setLabelMode('thermal')">
                    <i class="bi bi-aspect-ratio me-1"></i> 4" x 6" Thermal
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btn-a4" onclick="setLabelMode('a4')">
                    <i class="bi bi-file-earmark me-1"></i> A4 Cutout
                </button>
            </div>

            <!-- Packing Manifest Toggle -->
            <button type="button" class="btn btn-sm btn-outline-light" id="btn-toggle-manifest" onclick="toggleManifest()">
                <i class="bi bi-list-check me-1"></i> <span id="manifest-btn-text">Hide Items</span>
            </button>

            <!-- One-Click Print -->
            <button type="button" class="btn btn-warning btn-sm fw-bold px-3" onclick="window.print();">
                <i class="bi bi-printer-fill me-1"></i> Print Label (Ctrl+P)
            </button>
        </div>
    </div>
</div>

<!-- Main Printable Label Container -->
<div class="label-stage" id="stage-container">
    <div class="mode-container" id="mode-wrapper">
        <div class="cut-guide-notice">
            <i class="bi bi-scissors"></i> Cut or fold along the dashed border for standard parcel placement
        </div>

        <div class="shipping-label-card" id="printable-label">
            <!-- Header Section -->
            <div class="label-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="brand-title"><?php echo e(SITE_NAME); ?></h1>
                        <div class="brand-subtitle">Official Premium Store &bull; www.pookky.com</div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold" style="font-size: 11px;">STANDARD SURFACE / AIR</div>
                        <div style="font-size: 9px; color: #555;">
                            Date: <strong><?php echo date('d-M-Y', strtotime($order['created_at'])); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scannable Order Barcode -->
            <div class="barcode-box">
                <svg id="order-barcode" class="barcode-svg"></svg>
                <div class="barcode-text">ORDER # <?php echo e($order['order_number']); ?></div>
            </div>

            <!-- Payment Type Warning Banner -->
            <?php if ($is_cod): ?>
                <div class="payment-banner cod">
                    &#9889; CASH ON DELIVERY (COD) &bull; COLLECT: ₹<?php echo number_format($order['grand_total'], 2); ?>
                </div>
            <?php else: ?>
                <div class="payment-banner prepaid">
                    &#9733; PREPAID ORDER &bull; DO NOT COLLECT CASH &#9733;
                </div>
            <?php endif; ?>

            <!-- Deliver To / Ship To Hero Box -->
            <div class="deliver-box">
                <div class="section-tag"><i class="bi bi-geo-alt-fill me-1"></i> DELIVER TO / SHIP TO:</div>
                <div class="receiver-name"><?php echo e($order['receiver_name']); ?></div>
                <div class="receiver-phone">
                    <i class="bi bi-telephone-fill me-1"></i> <?php echo e($order['receiver_phone']); ?>
                    <?php if (!empty($order['customer_phone']) && $order['customer_phone'] !== $order['receiver_phone']): ?>
                        <span class="text-muted fw-normal" style="font-size: 10px;">(Alt: <?php echo e($order['customer_phone']); ?>)</span>
                    <?php endif; ?>
                </div>
                <div class="receiver-address">
                    <?php echo e($order['address_line1']); ?>
                    <?php if (!empty($order['address_line2'])): ?>
                        <br><?php echo e($order['address_line2']); ?>
                    <?php endif; ?>
                    <br><?php echo e($order['city']); ?>, <?php echo e($order['state']); ?>
                </div>
                <div>
                    <span class="pincode-highlight">PIN: <?php echo e($order['pincode']); ?></span>
                </div>
            </div>

            <!-- Dispatch & Seller Details (Return To) -->
            <div class="dispatch-meta-grid">
                <div class="dispatch-cell">
                    <div class="section-tag">RETURN TO / DISPATCH FROM:</div>
                    <div class="fw-bold" style="font-size: 10px;"><?php echo e(COMPANY_LEGAL_NAME); ?> (<?php echo e(SITE_NAME); ?>)</div>
                    <div style="font-size: 9px; color: #222; line-height: 1.25;">
                        <?php echo nl2br(e(COMPANY_ADDRESS)); ?><br>
                        Help: <?php echo e(SITE_PHONE); ?><br>
                        <?php if (!empty(COMPANY_GSTIN)): ?>
                            GSTIN: <strong><?php echo e(COMPANY_GSTIN); ?></strong>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dispatch-cell" style="background: #fafafa;">
                    <div class="section-tag">PARCEL DETAILS:</div>
                    <div style="font-size: 10px; line-height: 1.35;">
                        <div>Total Pieces: <strong><?php echo $total_qty; ?> item(s)</strong></div>
                        <div>Category: <strong>Apparel / Innerwear</strong></div>
                        <?php if (!empty($order['courier_name'])): ?>
                            <div class="mt-1">Carrier: <strong><?php echo e($order['courier_name']); ?></strong></div>
                        <?php endif; ?>
                        <?php if (!empty($order['tracking_number'])): ?>
                            <div>AWB / Track: <strong style="font-family: monospace;"><?php echo e($order['tracking_number']); ?></strong></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Packing Checklist / Manifest (Cross-check before seal) -->
            <div class="manifest-box" id="manifest-section">
                <div class="manifest-title">
                    <span>Packing Manifest Checklist</span>
                    <span>Total Pcs: <?php echo $total_qty; ?></span>
                </div>
                <ul class="manifest-list">
                    <?php foreach ($order_items as $item): ?>
                        <?php 
                        $is_combo = !empty($item['combo_product_id']) || !empty($item['combo_config']);
                        $parsed_cfg = $is_combo ? json_decode($item['combo_config'], true) : null;
                        ?>
                        <li>
                            <strong>&bull; <?php echo e($item['product_name']); ?></strong> 
                            <?php if ($is_combo): ?>
                                <span class="badge bg-light text-dark border" style="font-size: 8px;">Combo</span>
                            <?php else: ?>
                                [Sz: <?php echo e($item['size_name'] ?: 'STD'); ?>, Col: <?php echo e($item['color_name'] ?: 'STD'); ?>]
                            <?php endif; ?>
                            &times; <strong><?php echo (int)$item['quantity']; ?></strong>

                            <?php if ($is_combo && !empty($parsed_cfg)): ?>
                                <div class="manifest-sub">
                                    <?php foreach ($parsed_cfg as $p_c): ?>
                                        &rsaquo; <?php echo e($p_c['product_name']); ?> (Sz: <?php echo e($p_c['size_code']); ?>, Col: <?php echo e($p_c['color_name']); ?>)<br>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Footer / Disclaimers -->
            <div class="label-footer">
                Packaged with care. If seal is broken or tampered with, do not accept.<br>
                For delivery queries or returns, visit <strong>www.pookky.com</strong> or call <strong><?php echo e(SITE_PHONE); ?></strong>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize Code128 Vector Barcode via JsBarcode
    document.addEventListener("DOMContentLoaded", function () {
        try {
            JsBarcode("#order-barcode", "<?php echo addslashes($order['order_number']); ?>", {
                format: "CODE128",
                width: 1.6,
                height: 38,
                displayValue: false,
                margin: 0,
                lineColor: "#000000"
            });
        } catch (e) {
            console.warn("Barcode rendering fallback:", e);
        }
    });

    // Toggle 4x6 Thermal vs A4 View
    function setLabelMode(mode) {
        var wrapper = document.getElementById('mode-wrapper');
        var btnThermal = document.getElementById('btn-thermal');
        var btnA4 = document.getElementById('btn-a4');

        if (mode === 'a4') {
            wrapper.classList.add('mode-a4');
            btnA4.classList.add('btn-secondary', 'active');
            btnA4.classList.remove('btn-outline-secondary');
            btnThermal.classList.remove('btn-secondary', 'active');
            btnThermal.classList.add('btn-outline-secondary');
        } else {
            wrapper.classList.remove('mode-a4');
            btnThermal.classList.add('btn-secondary', 'active');
            btnThermal.classList.remove('btn-outline-secondary');
            btnA4.classList.remove('btn-secondary', 'active');
            btnA4.classList.add('btn-outline-secondary');
        }
    }

    // Toggle Packing Manifest Checklist Visibility
    function toggleManifest() {
        var section = document.getElementById('manifest-section');
        var btnText = document.getElementById('manifest-btn-text');
        if (section.style.display === 'none') {
            section.style.display = 'block';
            btnText.textContent = 'Hide Items';
        } else {
            section.style.display = 'none';
            btnText.textContent = 'Show Items';
        }
    }

    // Keyboard shortcut: Ctrl+P / Cmd+P prints smoothly
    window.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
    });
</script>

</body>
</html>

