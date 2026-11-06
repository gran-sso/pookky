<?php
// sitemap.php
// Dynamic XML Sitemap Generator for Google Search Console & Search Engines
// Automatically includes all active products, categories, and core storefront pages

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

// Detect protocol and host dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'pookky.com';

// If deployed on production domain, enforce pookky.com URL structure for Google
if (strpos($host, 'pookky.com') !== false) {
    $site_url = 'https://pookky.com';
} else {
    $site_url = rtrim($protocol . $host, '/');
}

// Clean any previous output buffers
if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

    <!-- 1. Core Static Storefront Pages -->
    <url>
        <loc><?php echo $site_url; ?>/</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?php echo $site_url; ?>/products.php</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?php echo $site_url; ?>/size-guide.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    <url>
        <loc><?php echo $site_url; ?>/contact.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    <url>
        <loc><?php echo $site_url; ?>/about-us.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    <url>
        <loc><?php echo $site_url; ?>/faq.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    <url>
        <loc><?php echo $site_url; ?>/return-and-exchange.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    <url>
        <loc><?php echo $site_url; ?>/shipping-policy.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    <url>
        <loc><?php echo $site_url; ?>/privacy-policy.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    <url>
        <loc><?php echo $site_url; ?>/terms-and-conditions.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>

    <!-- 2. Active Catalog Categories -->
    <?php
    try {
        $cat_stmt = $pdo->query("SELECT id, name, slug FROM categories WHERE status = 1 ORDER BY sort_order ASC, name ASC");
        while ($cat = $cat_stmt->fetch(PDO::FETCH_ASSOC)):
            $cat_url = $site_url . '/products.php?category=' . urlencode($cat['slug']);
    ?>
    <url>
        <loc><?php echo htmlspecialchars($cat_url, ENT_XML1, 'UTF-8'); ?></loc>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <?php
        endwhile;
    } catch (Exception $e) {}
    ?>

    <!-- 3. Active Products & Value Bundles with Image Extensions -->
    <?php
    try {
        $prod_stmt = $pdo->query("
            SELECT p.id, p.name, p.slug, p.updated_at, p.created_at,
                   (SELECT pi.image_path 
                    FROM product_images pi 
                    WHERE pi.product_id = p.id 
                    ORDER BY pi.is_featured DESC, pi.sort_order ASC, pi.id ASC 
                    LIMIT 1) as main_image
            FROM products p 
            WHERE p.status = 1 
            ORDER BY p.updated_at DESC
        ");

        while ($prod = $prod_stmt->fetch(PDO::FETCH_ASSOC)):
            $prod_url = $site_url . '/product-detail.php?slug=' . urlencode($prod['slug']);
            $date_str = !empty($prod['updated_at']) ? $prod['updated_at'] : $prod['created_at'];
            $last_mod = !empty($date_str) ? date('Y-m-d', strtotime($date_str)) : date('Y-m-d');
    ?>
    <url>
        <loc><?php echo htmlspecialchars($prod_url, ENT_XML1, 'UTF-8'); ?></loc>
        <lastmod><?php echo $last_mod; ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
        <?php if (!empty($prod['main_image'])): 
            $img_url = $site_url . '/uploads/' . $prod['main_image'];
        ?>
        <image:image>
            <image:loc><?php echo htmlspecialchars($img_url, ENT_XML1, 'UTF-8'); ?></image:loc>
            <image:title><?php echo htmlspecialchars($prod['name'], ENT_XML1, 'UTF-8'); ?></image:title>
        </image:image>
        <?php endif; ?>
    </url>
    <?php
        endwhile;
    } catch (Exception $e) {}
    ?>

</urlset>

