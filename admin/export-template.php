<?php
// admin/export-template.php
// Generates CSV templates, system references, and catalog exports for Bulk Import

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Guarantee admin login & product permissions
require_admin_permission('products');

$type = $_GET['type'] ?? 'sample';

// Helper function to output CSV
function output_csv($filename, $headers, $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output UTF-8 BOM so Excel opens it with proper character encoding
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);

    foreach ($rows as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

// 1. SAMPLE TEMPLATE WITH DEMO ROWS
if ($type === 'sample') {
    $filename = 'products_bulk_import_template.csv';
    $default_gst = number_format(DEFAULT_GST_RATE, 2, '.', '');
    $headers = [
        'action',
        'product_type',
        'category',
        'product_sku',
        'name',
        'regular_price',
        'offer_price',
        'gst_percentage',
        'description',
        'fabric_details',
        'fit_type',
        'care_instructions',
        'variant_sku',
        'size',
        'color',
        'stock',
        'variant_regular_price',
        'variant_offer_price',
        'image_filenames',
        'is_featured',
        'is_new_arrival',
        'is_best_seller',
        'status',
        'seo_title',
        'seo_description'
    ];

    $sample_rows = [
        [
            'create',
            'single',
            'Bras',
            'BR-LUX-101',
            'Velvet Whisper Plunge Bra',
            '1499.00',
            '1299.00',
            $default_gst,
            'Luxurious velvet overlay plunge bra designed for deep necklines with supreme support.',
            '85% Polyamide, 15% Elastane with soft microfiber lining',
            'Plunge neckline, wired, lightly contoured cups',
            'Hand wash cold only. Do not tumble dry.',
            'BR-LUX-101-34B-BLK',
            '34B',
            'Midnight Black',
            '25',
            '',
            'bra_velvet_black_1.jpg, bra_velvet_black_2.jpg',
            '1',
            '1',
            '0',
            '1',
            'Velvet Whisper Plunge Bra | Pookky',
            'Shop luxury velvet plunge bra for women online.'
        ],
        [
            'create',
            'single',
            'Bras',
            'BR-LUX-101',
            'Velvet Whisper Plunge Bra',
            '1499.00',
            '1299.00',
            $default_gst,
            'Luxurious velvet overlay plunge bra designed for deep necklines with supreme support.',
            '85% Polyamide, 15% Elastane with soft microfiber lining',
            'Plunge neckline, wired, lightly contoured cups',
            'Hand wash cold only. Do not tumble dry.',
            'BR-LUX-101-36B-BLK',
            '36B',
            'Midnight Black',
            '18',
            '',
            'bra_velvet_black_1.jpg',
            '1',
            '1',
            '0',
            '1',
            '',
            ''
        ],
        [
            'create',
            'single',
            'Bras',
            'BR-LUX-101',
            'Velvet Whisper Plunge Bra',
            '1499.00',
            '1299.00',
            $default_gst,
            'Luxurious velvet overlay plunge bra designed for deep necklines with supreme support.',
            '85% Polyamide, 15% Elastane with soft microfiber lining',
            'Plunge neckline, wired, lightly contoured cups',
            'Hand wash cold only. Do not tumble dry.',
            'BR-LUX-101-34B-BLUSH',
            '34B',
            'Blush Pink',
            '30',
            '',
            'bra_velvet_pink_1.jpg, bra_velvet_pink_2.jpg',
            '1',
            '1',
            '0',
            '1',
            '',
            ''
        ],
        [
            'create',
            'single',
            'Panties',
            'PN-SILK-202',
            'Pure Silk Seamless Bikini Brief',
            '599.00',
            '499.00',
            $default_gst,
            'Ultra smooth seamless bikini brief with breathable cotton gusset.',
            '92% Silk, 8% Spandex, 100% Cotton Gusset',
            'Low rise, bikini cut, invisible edges',
            'Machine wash delicate with mesh laundry bag.',
            'PN-SILK-202-M-BLUSH',
            'Medium',
            'Blush Pink',
            '50',
            '',
            'panty_silk_pink.jpg',
            '0',
            '1',
            '1',
            '1',
            'Pure Silk Seamless Bikini Brief | Pookky',
            'Seamless silk bikini panties for everyday luxury.'
        ],
        [
            'create',
            'single',
            'Panties',
            'PN-SILK-202',
            'Pure Silk Seamless Bikini Brief',
            '599.00',
            '499.00',
            $default_gst,
            'Ultra smooth seamless bikini brief with breathable cotton gusset.',
            '92% Silk, 8% Spandex, 100% Cotton Gusset',
            'Low rise, bikini cut, invisible edges',
            'Machine wash delicate with mesh laundry bag.',
            'PN-SILK-202-L-BLUSH',
            'Large',
            'Blush Pink',
            '45',
            '',
            'panty_silk_pink.jpg',
            '0',
            '1',
            '1',
            '1',
            '',
            ''
        ]
    ];

    output_csv($filename, $headers, $sample_rows);
}

// 2. REFERENCE DICTIONARY (Categories, Sizes, Colors)
if ($type === 'reference') {
    $filename = 'system_attributes_reference.csv';
    $headers = ['Type', 'Name', 'Code / Slug', 'Extra Details'];
    $rows = [];

    // Categories
    $cats = $pdo->query("SELECT name, slug, description FROM categories ORDER BY name ASC")->fetchAll();
    foreach ($cats as $c) {
        $rows[] = ['Category', $c['name'], $c['slug'], $c['description'] ?? ''];
    }

    // Sizes
    $sizes = $pdo->query("SELECT size_name, size_code, category_type FROM sizes ORDER BY category_type ASC, id ASC")->fetchAll();
    foreach ($sizes as $s) {
        $rows[] = ['Size', $s['size_name'], $s['size_code'], 'Category: ' . $s['category_type']];
    }

    // Colors
    $colors = $pdo->query("SELECT color_name, color_code FROM colors ORDER BY color_name ASC")->fetchAll();
    foreach ($colors as $cl) {
        $rows[] = ['Color', $cl['color_name'], $cl['color_code'], 'Hex: ' . $cl['color_code']];
    }

    output_csv($filename, $headers, $rows);
}

// 3. EXPORT ENTIRE CATALOG (For Bulk Edit & Re-import)
if ($type === 'catalog') {
    $filename = 'catalog_export_' . date('Y-m-d_His') . '.csv';
    $headers = [
        'action',
        'product_type',
        'category',
        'product_sku',
        'name',
        'regular_price',
        'offer_price',
        'gst_percentage',
        'description',
        'fabric_details',
        'fit_type',
        'care_instructions',
        'variant_sku',
        'size',
        'color',
        'stock',
        'variant_regular_price',
        'variant_offer_price',
        'image_filenames',
        'is_featured',
        'is_new_arrival',
        'is_best_seller',
        'status',
        'seo_title',
        'seo_description'
    ];

    $query = "
        SELECT 
            p.product_type,
            c.name as category_name,
            p.sku as product_sku,
            p.name as product_name,
            p.regular_price,
            p.offer_price,
            p.gst_percentage,
            p.description,
            p.fabric_details,
            p.fit_type,
            p.care_instructions,
            pv.sku as variant_sku,
            s.size_name,
            s.size_code,
            cl.color_name,
            pv.stock,
            pv.regular_price as variant_regular_price,
            pv.offer_price as variant_offer_price,
            pv.price as variant_price,
            p.is_featured,
            p.is_new_arrival,
            p.is_best_seller,
            p.status,
            p.seo_title,
            p.seo_description,
            (
                SELECT GROUP_CONCAT(pi.image_path SEPARATOR ', ')
                FROM product_images pi
                WHERE pi.product_id = p.id AND (pi.color_id = pv.color_id OR (pi.color_id IS NULL AND pv.color_id IS NULL))
            ) as color_images
        FROM products p
        JOIN categories c ON p.category_id = c.id
        LEFT JOIN product_variants pv ON pv.product_id = p.id
        LEFT JOIN sizes s ON pv.size_id = s.id
        LEFT JOIN colors cl ON pv.color_id = cl.id
        ORDER BY p.id DESC, pv.id ASC
    ";

    $stmt = $pdo->query($query);
    $rows = [];

    while ($r = $stmt->fetch()) {
        $rows[] = [
            'update',
            $r['product_type'] ?? 'single',
            $r['category_name'] ?? '',
            $r['product_sku'] ?? '',
            $r['product_name'] ?? '',
            $r['regular_price'] ?? '0.00',
            $r['offer_price'] ?? '',
            $r['gst_percentage'] ?? number_format(DEFAULT_GST_RATE, 2, '.', ''),
            $r['description'] ?? '',
            $r['fabric_details'] ?? '',
            $r['fit_type'] ?? '',
            $r['care_instructions'] ?? '',
            $r['variant_sku'] ?? '',
            $r['size_code'] ?? ($r['size_name'] ?? ''),
            $r['color_name'] ?? '',
            $r['stock'] ?? '0',
            $r['variant_regular_price'] ?? '',
            $r['variant_offer_price'] ?? ($r['variant_price'] ?? ''),
            $r['color_images'] ?? '',
            $r['is_featured'] ?? '0',
            $r['is_new_arrival'] ?? '0',
            $r['is_best_seller'] ?? '0',
            $r['status'] ?? '1',
            $r['seo_title'] ?? '',
            $r['seo_description'] ?? ''
        ];
    }

    output_csv($filename, $headers, $rows);
}

exit;
