<?php
// admin/pages.php
// Dynamic Content Pages Management CRUD

$page_title = 'Content Pages Management';
$active_menu = 'pages';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Check permissions
require_admin_permission('all');

// Helper to generate the public storefront boilerplate PHP file for a page slug
function generate_dynamic_page_php_code($slug, $title) {
    $escaped_title = addslashes($title);
    $escaped_slug = addslashes($slug);
    return '<?php
// ' . $escaped_slug . '.php
// Public ' . $escaped_title . ' page loaded dynamically from DB

require_once __DIR__ . \'/config/database.php\';
require_once __DIR__ . \'/config/constants.php\';
require_once __DIR__ . \'/includes/functions.php\';

try {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = \'' . $escaped_slug . '\' LIMIT 1");
    $stmt->execute();
    $page = $stmt->fetch();
} catch (PDOException $e) {
    $page = null;
}

$title = $page ? $page[\'title\'] : \'' . $escaped_title . '\';
$meta_title = !empty($page[\'seo_title\']) ? $page[\'seo_title\'] : $title . \' | \' . SITE_NAME;
$meta_desc = !empty($page[\'seo_description\']) ? $page[\'seo_description\'] : \'Official \' . strtolower($title) . \' of \' . SITE_NAME . \' - Luxury Ladies\\\' Innerwear & Lingerie.\';
$content = $page ? $page[\'content\'] : \'<div class="text-center py-5 text-muted">Page content currently updating. Check back soon.</div>\';
$updated_at = $page ? $page[\'updated_at\'] : null;

require_once __DIR__ . \'/includes/header.php\';
?>

<div class="page-header py-5 mb-4" style="background: linear-gradient(135deg, #FFF0F5 0%, #FFF8FA 100%); border-bottom: 1px solid rgba(230,0,102,0.08);">
    <div class="container text-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb justify-content-center mb-2 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-secondary text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><span class="text-muted">Customer Care</span></li>
                <li class="breadcrumb-item active text-brand fw-semibold" aria-current="page"><?php echo e($title); ?></li>
            </ol>
        </nav>
        <h1 class="display-6 fw-bold font-family-heading text-dark mb-2"><?php echo e($title); ?></h1>
        <?php if ($updated_at): ?>
            <div class="small text-muted">
                <i class="bi bi-clock-history me-1"></i> Last updated: <?php echo date(\'F d, Y\', strtotime($updated_at)); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white" style="border-radius: 16px; border: 1px solid rgba(0,0,0,0.04) !important;">
                <div class="page-content legal-content text-secondary" style="font-size: 0.98rem; line-height: 1.8;">
                    <?php echo $content; ?>
                </div>
            </div>
            
            <div class="d-flex flex-wrap justify-content-between align-items-center mt-4 p-3 rounded-3 bg-light text-muted small">
                <div>
                    Have questions regarding our policies? <a href="contact.php" class="text-brand fw-semibold ms-1">Contact Customer Support</a>
                </div>
                <div class="d-flex flex-wrap gap-2 gap-md-3 mt-2 mt-md-0">
                    <a href="privacy-policy.php" class="text-secondary text-decoration-none hover-brand">Privacy Policy</a>
                    <span class="text-muted d-none d-sm-inline">&bull;</span>
                    <a href="terms-and-conditions.php" class="text-secondary text-decoration-none hover-brand">Terms & Conditions</a>
                    <span class="text-muted d-none d-sm-inline">&bull;</span>
                    <a href="return-and-exchange.php" class="text-secondary text-decoration-none hover-brand">Return & Exchange</a>
                    <span class="text-muted d-none d-sm-inline">&bull;</span>
                    <a href="shipping-policy.php" class="text-secondary text-decoration-none hover-brand">Shipping Policy</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . \'/includes/footer.php\'; ?>
';
}

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle additions & updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        // REGENERATE ALL FILES ACTION
        if (isset($_POST['regenerate_all'])) {
            try {
                $all_pages = $pdo->query("SELECT slug, title FROM pages")->fetchAll();
                $count = 0;
                foreach ($all_pages as $p_item) {
                    $file_code = generate_dynamic_page_php_code($p_item['slug'], $p_item['title']);
                    @file_put_contents(__DIR__ . '/../' . $p_item['slug'] . '.php', $file_code);
                    $count++;
                }
                log_admin_action($current_admin_id, "Regenerated $count storefront pages");
                set_flash_message('success', "Successfully regenerated $count storefront pages.");
                header('Location: pages.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Regeneration failed: ' . $e->getMessage();
            }
        }

        // ADD / CREATE PAGE
        if (isset($_POST['add_page'])) {
            $title = trim($_POST['title'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $content = $_POST['content'] ?? '';
            $seo_title = trim($_POST['seo_title'] ?? '');
            $seo_description = trim($_POST['seo_description'] ?? '');

            if (empty($slug)) {
                $slug = slugify($title);
            } else {
                $slug = slugify($slug);
            }

            if (empty($title) || empty($content)) {
                $error = 'Title and Page Content are required.';
            } else {
                try {
                    $check = $pdo->prepare("SELECT id FROM pages WHERE slug = ? LIMIT 1");
                    $check->execute([$slug]);
                    if ($check->fetch()) {
                        $error = 'A page with this URL slug already exists.';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO pages (slug, title, content, seo_title, seo_description, updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$slug, $title, $content, $seo_title, $seo_description]);
                        
                        // Dynamically create physical page file in the root directory
                        $file_content = generate_dynamic_page_php_code($slug, $title);
                        @file_put_contents(__DIR__ . '/../' . $slug . '.php', $file_content);

                        log_admin_action($current_admin_id, "Created content page: $title");
                        set_flash_message('success', 'Content page created successfully!');
                        header('Location: pages.php');
                        exit;
                    }
                } catch (PDOException $e) {
                    $error = 'Create page failed: ' . $e->getMessage();
                }
            }
        }

        // EDIT PAGE
        if (isset($_POST['edit_page'])) {
            $id = (int)$_POST['page_id'];
            $title = trim($_POST['title'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $content = $_POST['content'] ?? '';
            $seo_title = trim($_POST['seo_title'] ?? '');
            $seo_description = trim($_POST['seo_description'] ?? '');

            if (empty($slug)) {
                $slug = slugify($title);
            } else {
                $slug = slugify($slug);
            }

            if (empty($title) || empty($content)) {
                $error = 'Title and Page Content are required.';
            } else {
                try {
                    $check = $pdo->prepare("SELECT id FROM pages WHERE slug = ? AND id != ? LIMIT 1");
                    $check->execute([$slug, $id]);
                    if ($check->fetch()) {
                        $error = 'A page with this URL slug already exists.';
                    } else {
                        // Fetch old slug to clean up file if the slug changes
                        $old_stmt = $pdo->prepare("SELECT slug FROM pages WHERE id = ? LIMIT 1");
                        $old_stmt->execute([$id]);
                        $old_page = $old_stmt->fetch();

                        $stmt = $pdo->prepare("UPDATE pages SET slug = ?, title = ?, content = ?, seo_title = ?, seo_description = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$slug, $title, $content, $seo_title, $seo_description, $id]);
                        
                        // If slug changed, delete the old file
                        if ($old_page && $old_page['slug'] !== $slug) {
                            $old_file = __DIR__ . '/../' . $old_page['slug'] . '.php';
                            if (file_exists($old_file)) {
                                @unlink($old_file);
                            }
                        }
                        
                        // Dynamically write updated physical page file in the root directory
                        $file_content = generate_dynamic_page_php_code($slug, $title);
                        @file_put_contents(__DIR__ . '/../' . $slug . '.php', $file_content);

                        log_admin_action($current_admin_id, "Updated content page: $title");
                        set_flash_message('success', 'Page updated successfully!');
                        header('Location: pages.php');
                        exit;
                    }
                } catch (PDOException $e) {
                    $error = 'Update page failed: ' . $e->getMessage();
                }
            }
        }

        // DELETE PAGE
        if (isset($_POST['delete_page'])) {
            $id = (int)$_POST['page_id'];
            try {
                // Fetch the slug before deleting the database record
                $old_stmt = $pdo->prepare("SELECT slug FROM pages WHERE id = ? LIMIT 1");
                $old_stmt->execute([$id]);
                $old_page = $old_stmt->fetch();

                $stmt = $pdo->prepare("DELETE FROM pages WHERE id = ?");
                $stmt->execute([$id]);
                
                // Delete the physical file
                if ($old_page) {
                    $file_path = __DIR__ . '/../' . $old_page['slug'] . '.php';
                    if (file_exists($file_path)) {
                        @unlink($file_path);
                    }
                }

                log_admin_action($current_admin_id, "Deleted content page id: $id");
                set_flash_message('success', 'Page deleted successfully!');
                header('Location: pages.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Failed to delete page.';
            }
        }
    }
}

// Render views based on action
if ($action === 'add' || $action === 'edit'):
    $page = null;
    if ($action === 'edit') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $page = $stmt->fetch();
        if (!$page) {
            set_flash_message('error', 'Page not found.');
            header('Location: pages.php');
            exit;
        }
    }
?>

    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px; max-width: 950px; margin: 0 auto;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-file-earmark-richtext me-2 text-brand"></i>
                <?php echo $action === 'add' ? 'Create CMS Dynamic Page' : 'Edit Page: ' . e($page['title']); ?>
            </h5>
            <a href="pages.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Back to Pages
            </a>
        </div>

        <!-- Quick Template Insert Toolbar -->
        <div class="p-3 bg-light rounded-3 mb-4 border">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="small fw-bold text-muted">
                    <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Load Default Policy Template:
                </div>
                <div class="d-flex flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadPolicyTemplate('privacy')">Privacy Policy</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadPolicyTemplate('terms')">Terms & Conditions</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadPolicyTemplate('returns')">Return & Exchange</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadPolicyTemplate('shipping')">Shipping Policy</button>
                </div>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger shadow-sm"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form action="pages.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $page['id'] : ''; ?>" method="POST">
            <?php echo csrf_field(); ?>
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="page_id" value="<?php echo $page['id']; ?>">
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <label for="title" class="form-label fw-bold small">Page Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" value="<?php echo e($page['title'] ?? ''); ?>" required placeholder="e.g. Privacy Policy">
                </div>
                <div class="col-12 col-md-6">
                    <label for="slug" class="form-label fw-bold small">URL Slug / Identifier <span class="text-danger">*</span></label>
                    <input type="text" class="form-control font-monospace" id="slug" name="slug" value="<?php echo e($page['slug'] ?? ''); ?>" placeholder="e.g. privacy-policy" required>
                    <div class="form-text small text-muted">Public URL will be: <code><?php echo BASE_URL; ?><span id="slug-preview"><?php echo e($page['slug'] ?? 'page-slug'); ?></span>.php</code></div>
                </div>

                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="content" class="form-label fw-bold small mb-0">HTML Page Content <span class="text-danger">*</span></label>
                        <span class="badge bg-light text-muted border small">HTML & Formatting Supported</span>
                    </div>
                    <textarea class="form-control font-monospace" id="content" name="content" rows="16" required style="font-size: 0.9rem;" placeholder="Write formatted HTML body content here..."><?php echo e($page['content'] ?? ''); ?></textarea>
                    <div class="form-text small text-muted">You can write standard HTML tags (e.g. &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;table&gt;, &lt;div class="alert"&gt;, etc.) directly.</div>
                </div>

                <!-- SEO Details -->
                <div class="col-12 pt-3 border-top mt-4">
                    <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-search me-2 text-primary"></i> Page SEO Metadata (Optional)</h6>
                </div>
                <div class="col-12 col-md-6">
                    <label for="seo_title" class="form-label small fw-semibold">Browser Title Tag (&lt;title&gt;)</label>
                    <input type="text" class="form-control form-control-sm" id="seo_title" name="seo_title" value="<?php echo e($page['seo_title'] ?? ''); ?>" placeholder="e.g. Privacy Policy | Pookky Innerwear">
                </div>
                <div class="col-12 col-md-6">
                    <label for="seo_description" class="form-label small fw-semibold">Meta Search Description</label>
                    <textarea class="form-control form-control-sm" id="seo_description" name="seo_description" rows="2" placeholder="Brief summary of policy page shown in search engine results."><?php echo e($page['seo_description'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" name="<?php echo $action === 'add' ? 'add_page' : 'edit_page'; ?>" class="btn btn-brand px-4">
                    <i class="bi bi-check2-circle me-1"></i> Save Page Content
                </button>
                <a href="pages.php" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
            </div>
        </form>
    </div>

    <!-- Pre-stored templates Javascript for instant insertion -->
    <script>
    const templates = {
        privacy: {
            title: "Privacy Policy",
            slug: "privacy-policy",
            seo_title: "Privacy Policy | Pookky Innerwear",
            seo_description: "Learn how Pookky protects your personal data, ensures 100% discreet packaging, and complies with industry-leading privacy and security standards.",
            content: `<div class="policy-section mb-5">
    <p class="lead text-dark mb-4">At <strong>Pookky</strong>, we recognize the deeply personal nature of intimate apparel shopping and are uncompromisingly committed to protecting your personal privacy, data confidentiality, and transactional security.</p>
    <div class="p-3 mb-4 rounded-3" style="background: linear-gradient(135deg, rgba(230, 0, 102, 0.05) 0%, rgba(255, 107, 129, 0.08) 100%); border-left: 4px solid var(--brand-pink, #E60066);">
        <div class="d-flex align-items-center">
            <i class="bi bi-shield-lock-fill fs-3 text-brand me-3"></i>
            <div>
                <strong class="text-dark">Discreet Packaging & Billing Guarantee</strong>
                <p class="mb-0 text-muted small">All orders are shipped in 100% unmarked, tamper-proof outer boxes without any brand names, product titles, or intimate references on the shipping label.</p>
            </div>
        </div>
    </div>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">1. Information We Collect</h3>
    <p>To provide you with seamless service, accurate sizing recommendations, and prompt delivery, we collect the following categories of information:</p>
    <ul class="list-unstyled ps-2">
        <li class="mb-2"><i class="bi bi-check-circle-fill text-brand me-2"></i><strong>Personal Identification & Contact:</strong> Name, email address, phone/mobile number, and account login credentials.</li>
        <li class="mb-2"><i class="bi bi-check-circle-fill text-brand me-2"></i><strong>Delivery & Billing Details:</strong> Shipping address, billing address, postal/PIN code, and recipient contact info.</li>
        <li class="mb-2"><i class="bi bi-check-circle-fill text-brand me-2"></i><strong>Payment Information:</strong> Transaction identifiers and payment method selection. (Note: Sensitive card details and net banking passwords are processed directly by RBI-authorized PCI-DSS Level 1 compliant payment gateways and are never stored on our servers.)</li>
        <li class="mb-2"><i class="bi bi-check-circle-fill text-brand me-2"></i><strong>Device & Browsing Data:</strong> IP address, browser type, operating system, pages visited, and referring URLs to optimize performance.</li>
    </ul>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">2. How We Use Your Information</h3>
    <p>We process your data strictly for legitimate operational purposes:</p>
    <ul>
        <li>Processing, fulfilling, and dispatching your orders.</li>
        <li>Sending real-time order confirmation, dispatch notifications, and live tracking links via SMS and email.</li>
        <li>Facilitating doorstep reverse pickups for eligible returns and exchanges.</li>
        <li>Providing dedicated customer care and resolving queries regarding fit, fabric, and sizing.</li>
        <li>Detecting, preventing, and mitigating fraudulent transactions or unauthorized access.</li>
        <li>Improving our website navigation, product catalog, and personalized shopping experience.</li>
    </ul>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">3. Cookies & Tracking Technologies</h3>
    <p>We use session and persistent cookies to remember items in your shopping bag, preserve your login session, remember your regional currency preference, and evaluate aggregate store traffic. You may disable cookies through your browser settings, although certain interactive features may be affected.</p>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">4. Information Sharing & Third Parties</h3>
    <p><strong>We do not sell, rent, or trade your personal information to third parties for marketing purposes under any circumstance.</strong></p>
    <p>Your data is shared solely with trusted third-party service providers bound by strict confidentiality agreements (e.g. logistics carriers, encrypted payment gateways, automated SMS/email gateways).</p>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">5. Data Security & Storage</h3>
    <p>We implement industry-standard 256-bit SSL (Secure Sockets Layer) encryption across our entire platform. All administrative access is strictly role-restricted with multi-factor safeguards and continuous audit logging.</p>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">6. Your Rights & Account Control</h3>
    <p>You can view, edit, or remove your saved shipping addresses and profile details at any time from your account dashboard, or request complete account deletion by emailing our support team.</p>
</div>

<div class="policy-section">
    <h3 class="fw-bold text-dark mb-3">7. Contact Our Privacy Officer</h3>
    <p>If you have any questions, feedback, or concerns regarding our privacy practices, please reach out to us:</p>
    <div class="card bg-light border-0 p-3 rounded-3">
        <div class="row g-2">
            <div class="col-12 col-md-6"><strong>Support Email:</strong> <a href="mailto:info@pookky.com" class="text-brand">info@pookky.com</a></div>
            <div class="col-12 col-md-6"><strong>Phone / WhatsApp:</strong> +91 98765 43210</div>
            <div class="col-12"><strong>Operating Address:</strong> Pookky Innerwear Mfg Ltd, Phase 2, Industrial Area, Bangalore, Karnataka - 560001</div>
        </div>
    </div>
</div>`
        },
        terms: {
            title: "Terms & Conditions",
            slug: "terms-and-conditions",
            seo_title: "Terms and Conditions | Pookky Official Store",
            seo_description: "Review the official terms and conditions for browsing, purchasing, and placing orders on Pookky.",
            content: `<div class="policy-section mb-5">
    <p class="lead text-dark mb-4">Welcome to <strong>Pookky</strong>. These Terms & Conditions constitute a legally binding agreement between you ("Customer", "User", or "You") and Pookky ("Company", "We", "Us", or "Our") governing your access to and purchase of products on this website.</p>
    <p>By accessing, browsing, registering an account, or placing an order on Pookky, you acknowledge that you have read, understood, and agreed to be bound by these Terms.</p>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">1. Account Registration & User Security</h3>
    <ul>
        <li>You may browse and purchase items either as a registered customer or via guest checkout.</li>
        <li>You agree to provide accurate, current, and complete delivery and contact information.</li>
        <li>You are responsible for maintaining the confidentiality of your account credentials and password. Any activity occurring under your account is your sole responsibility.</li>
    </ul>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">2. Products, Fabric Descriptions & Sizing</h3>
    <ul>
        <li>We make every effort to display the colors, fabrics, cup designs, and dimensions of our lingerie collection with maximum photographic accuracy.</li>
        <li>Please refer to our comprehensive Size Guide before purchasing to ensure an optimal fit.</li>
        <li>All product offerings are subject to inventory availability.</li>
    </ul>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">3. Pricing, Taxes & Payment</h3>
    <ul>
        <li>All prices are quoted in Indian Rupees (INR) and include applicable Goods and Services Tax (GST).</li>
        <li>We accept UPI, Credit Cards, Debit Cards, Net Banking, and Cash on Delivery (COD) for eligible pin codes.</li>
        <li>Pookky reserves the right to revise product prices and shipping charges at any time without affecting confirmed orders.</li>
    </ul>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">4. Order Verification & Cancellation</h3>
    <ul>
        <li>You may cancel an order free of charge before it has been dispatched from our warehouse by contacting customer support.</li>
        <li>Once an order is handed over to our courier partner, standard return policies apply upon receipt.</li>
    </ul>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">5. Shipping, Delivery & Discreet Packaging</h3>
    <ul>
        <li>Orders are processed within 24-48 business hours with estimated delivery of 2-6 business days.</li>
        <li>All consignments are packed in discreet, unbranded packaging to safeguard personal privacy.</li>
    </ul>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">6. Returns, Hygiene Regulations & Exchanges</h3>
    <ul>
        <li>All returns and exchanges are strictly subject to our Return & Exchange Policy.</li>
        <li>Due to mandatory sanitary regulations for intimate apparel, certain categories (such as individual panties) cannot be returned once opened unless defective.</li>
    </ul>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">7. Governing Law & Dispute Jurisdiction</h3>
    <p>These Terms & Conditions shall be governed by and construed in accordance with the laws of the Republic of India. Any disputes arising in connection with these terms shall be subject to the exclusive jurisdiction of the competent courts in Bengaluru, Karnataka, India.</p>
</div>

<div class="policy-section">
    <h3 class="fw-bold text-dark mb-3">8. Contact & Grievance Redressal</h3>
    <p>For inquiries, clarification, or grievances concerning these terms, please contact:</p>
    <div class="card bg-light border-0 p-3 rounded-3">
        <p class="mb-1"><strong>Grievance Officer:</strong> Compliance Team, Pookky Innerwear</p>
        <p class="mb-1"><strong>Email:</strong> <a href="mailto:info@pookky.com" class="text-brand">info@pookky.com</a></p>
        <p class="mb-0"><strong>Helpline:</strong> +91 98765 43210 (Mon - Sat, 9:30 AM to 6:30 PM IST)</p>
    </div>
</div>`
        },
        returns: {
            title: "Return & Exchange Policy",
            slug: "return-and-exchange",
            seo_title: "Return & Exchange Policy (7 Days) | Pookky",
            seo_description: "Explore Pookky's 7-day hassle-free return and size exchange policy for bras, nightwear, and intimate apparel.",
            content: `<div class="policy-section mb-5">
    <p class="lead text-dark mb-4">At <strong>Pookky</strong>, we want you to love your intimate wear. We offer a hassle-free <strong>7-Day Return & Exchange Policy</strong> designed with your comfort and hygiene in mind.</p>
    
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card h-100 p-3 border-0 bg-light rounded-3 text-center">
                <i class="bi bi-calendar-check text-brand fs-2 mb-2"></i>
                <h6 class="fw-bold text-dark">7 Days Window</h6>
                <p class="small text-muted mb-0">Request return or size exchange within 7 days of delivery.</p>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100 p-3 border-0 bg-light rounded-3 text-center">
                <i class="bi bi-shield-check text-brand fs-2 mb-2"></i>
                <h6 class="fw-bold text-dark">Hygiene Guaranteed</h6>
                <p class="small text-muted mb-0">Original tags intact, unwashed, and unworn condition.</p>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100 p-3 border-0 bg-light rounded-3 text-center">
                <i class="bi bi-truck text-brand fs-2 mb-2"></i>
                <h6 class="fw-bold text-dark">Doorstep Pickup</h6>
                <p class="small text-muted mb-0">Free reverse pickup arranged directly from your address.</p>
            </div>
        </div>
    </div>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">1. Hygiene & Category Guidelines</h3>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Product Category</th>
                    <th>Eligible for Return?</th>
                    <th>Eligible for Size Exchange?</th>
                    <th>Condition Criteria</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Bras & Bralettes</strong></td>
                    <td><span class="badge bg-success">Yes (7 Days)</span></td>
                    <td><span class="badge bg-success">Yes (Free)</span></td>
                    <td>Tags attached, unworn, original polybag packaging intact.</td>
                </tr>
                <tr>
                    <td><strong>Nightwear & Camisoles</strong></td>
                    <td><span class="badge bg-success">Yes (7 Days)</span></td>
                    <td><span class="badge bg-success">Yes (Free)</span></td>
                    <td>Unwashed, unworn, original tags attached.</td>
                </tr>
                <tr>
                    <td><strong>Panties & Shapewear Bottoms</strong></td>
                    <td><span class="badge bg-danger">Non-Returnable*</span></td>
                    <td><span class="badge bg-danger">Non-Exchangeable*</span></td>
                    <td>Due to intimate sanitary standards. (*Fully covered if damaged or wrong item delivered).</td>
                </tr>
                <tr>
                    <td><strong>Combo Sets & Multipacks</strong></td>
                    <td><span class="badge bg-success">Yes (Complete Set)</span></td>
                    <td><span class="badge bg-success">Yes (Size Adjustment)</span></td>
                    <td>Must be returned as a complete set with all constituent items.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">2. Accepted Reasons for Return / Exchange</h3>
    <ul class="list-unstyled ps-2">
        <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i><strong>Size & Fit Issues:</strong> Need a different band, cup, or bottom size (eligible categories).</li>
        <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i><strong>Damaged or Defective Item:</strong> Manufacturing flaw, stitching defect, or transit damage.</li>
        <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i><strong>Wrong Product Delivered:</strong> Incorrect size, color, or model received.</li>
    </ul>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">3. Step-by-Step Return & Exchange Procedure</h3>
    <ol class="ps-3">
        <li class="mb-2"><strong>Initiate Request:</strong> Email us at <a href="mailto:info@pookky.com" class="text-brand">info@pookky.com</a> or WhatsApp <strong>+91 98765 43210</strong> with your Order ID and photos.</li>
        <li class="mb-2"><strong>Reverse Pickup:</strong> Our logistics partner will pick up the package from your doorstep within 24-72 hours.</li>
        <li class="mb-2"><strong>Quality Inspection:</strong> Once received, our QC team inspects the item for hygiene compliance.</li>
        <li class="mb-2"><strong>Replacement Dispatch or Refund:</strong> Your exchange item is dispatched immediately upon passing inspection, or your refund is initiated.</li>
    </ol>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">4. Refund Methods & Timelines</h3>
    <ul>
        <li><strong>Prepaid Orders:</strong> Credited directly back to the original payment source within <strong>5-7 business days</strong>.</li>
        <li><strong>Cash on Delivery (COD) Orders:</strong> Processed via NEFT/IMPS bank transfer or instant Pookky Store Credit.</li>
    </ul>
</div>

<div class="policy-section">
    <h3 class="fw-bold text-dark mb-3">5. Need Assistance with Sizing?</h3>
    <div class="card bg-light border-0 p-3 rounded-3">
        <div class="d-flex flex-wrap gap-4 align-items-center">
            <div><i class="bi bi-headset fs-3 text-brand me-2"></i> <strong>Support Helpline:</strong> +91 98765 43210</div>
            <div><i class="bi bi-envelope fs-3 text-brand me-2"></i> <strong>Email:</strong> info@pookky.com</div>
        </div>
    </div>
</div>`
        },
        shipping: {
            title: "Shipping & Delivery Policy",
            slug: "shipping-policy",
            seo_title: "Shipping & Delivery Policy | Pookky",
            seo_description: "Fast delivery across India with FREE shipping on orders above ₹999 and 100% discreet unmarked packaging.",
            content: `<div class="policy-section mb-5">
    <p class="lead text-dark mb-4">At <strong>Pookky</strong>, we are committed to delivering your favorite intimate wear swiftly, securely, and with complete confidentiality across all serviceable pin codes in India.</p>
    
    <div class="p-3 mb-4 rounded-3" style="background: linear-gradient(135deg, rgba(230, 0, 102, 0.05) 0%, rgba(255, 107, 129, 0.08) 100%); border-left: 4px solid var(--brand-pink, #E60066);">
        <div class="d-flex align-items-center">
            <i class="bi bi-box-seam-fill fs-3 text-brand me-3"></i>
            <div>
                <strong class="text-dark">100% Discreet Packaging Commitment</strong>
                <p class="mb-0 text-muted small">We value your privacy. All parcels are shipped in plain, unmarked, tamper-evident boxes. No brand keywords, lingerie images, or product descriptions appear on the outside of your package.</p>
            </div>
        </div>
    </div>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">1. Shipping Charges & Free Delivery</h3>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Order Total</th>
                    <th>Payment Mode</th>
                    <th>Shipping Charges</th>
                    <th>Estimated Delivery</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>₹999 and above</strong></td>
                    <td>Prepaid & COD</td>
                    <td><strong class="text-success">FREE Shipping</strong></td>
                    <td>3 - 5 Business Days</td>
                </tr>
                <tr>
                    <td><strong>Below ₹999</strong></td>
                    <td>Prepaid & COD</td>
                    <td><strong>₹99 Flat Rate</strong></td>
                    <td>3 - 5 Business Days</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">2. Processing & Dispatch Timelines</h3>
    <ul>
        <li>Orders are processed and dispatched within <strong>24 to 48 business hours</strong>.</li>
        <li>Once dispatched, an automated tracking notification with your consignment AWB number will be sent via SMS and Email.</li>
    </ul>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">3. Estimated Transit Times Across India</h3>
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <div class="card h-100 p-3 border-0 bg-light rounded-3">
                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-buildings text-brand me-2"></i> Metro Cities</h6>
                <p class="small text-muted mb-0">Bangalore, Mumbai, Delhi-NCR, Chennai, Hyderabad: <strong>2 - 4 Business Days</strong></p>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100 p-3 border-0 bg-light rounded-3">
                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-geo-alt text-brand me-2"></i> Tier 2 & Tier 3 Cities</h6>
                <p class="small text-muted mb-0">State capitals and major regional towns: <strong>3 - 5 Business Days</strong></p>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100 p-3 border-0 bg-light rounded-3">
                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-compass text-brand me-2"></i> Remote Regions</h6>
                <p class="small text-muted mb-0">Special postal circles and rural locations: <strong>5 - 8 Business Days</strong></p>
            </div>
        </div>
    </div>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">4. Courier & Logistics Partners</h3>
    <p>We partner with India's premier logistics carriers: <strong>BlueDart Express, Delhivery, DTDC, Xpressbees, India Post</strong>.</p>
</div>

<div class="policy-section mb-5">
    <h3 class="fw-bold text-dark mb-3">5. Cash on Delivery (COD) Guidelines</h3>
    <ul>
        <li>Cash on Delivery is available across 18,000+ serviceable PIN codes in India.</li>
        <li>Please keep exact cash ready upon delivery for a smooth handover.</li>
    </ul>
</div>

<div class="policy-section">
    <h3 class="fw-bold text-dark mb-3">6. Shipping Queries & Delivery Support</h3>
    <div class="card bg-light border-0 p-3 rounded-3">
        <div class="d-flex flex-wrap gap-4 align-items-center">
            <div><i class="bi bi-telephone-fill text-brand me-2"></i> <strong>Call / WhatsApp:</strong> +91 98765 43210</div>
            <div><i class="bi bi-envelope-fill text-brand me-2"></i> <strong>Email:</strong> info@pookky.com</div>
        </div>
    </div>
</div>`
        }
    };

    function loadPolicyTemplate(key) {
        if (!templates[key]) return;
        const tpl = templates[key];
        if (confirm('Load standard "' + tpl.title + '" template? (This will overwrite form fields)')) {
            document.getElementById('title').value = tpl.title;
            document.getElementById('slug').value = tpl.slug;
            document.getElementById('content').value = tpl.content;
            document.getElementById('seo_title').value = tpl.seo_title;
            document.getElementById('seo_description').value = tpl.seo_description;
            const slugPreview = document.getElementById('slug-preview');
            if (slugPreview) slugPreview.innerText = tpl.slug;
        }
    }

    document.getElementById('slug')?.addEventListener('input', function(e) {
        const preview = document.getElementById('slug-preview');
        if (preview) preview.innerText = e.target.value.trim() || 'page-slug';
    });
    </script>

<?php else:
    // List Pages
    try {
        $pages_list = $pdo->query("SELECT * FROM pages ORDER BY title ASC")->fetchAll();
    } catch (PDOException $e) {
        $pages_list = [];
        $error = 'Error listing pages: ' . $e->getMessage();
    }
?>

    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h5 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-file-earmark-richtext me-2 text-brand"></i> Dynamic Website Content & Policy Pages
                </h5>
                <p class="text-muted small mb-0">Manage Privacy Policy, Terms & Conditions, Return & Exchange, Shipping Policy, and custom CMS pages.</p>
            </div>
            <div class="d-flex gap-2">
                <form action="pages.php" method="POST" onsubmit="return confirm('Regenerate all public storefront PHP files from current database content?');">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="regenerate_all" class="btn btn-outline-secondary btn-sm rounded-pill px-3" title="Rebuild storefront files">
                        <i class="bi bi-arrow-repeat me-1"></i> Sync & Rebuild Files
                    </button>
                </form>
                <a href="pages.php?action=add" class="btn btn-brand btn-sm rounded-pill px-3">
                    <i class="bi bi-plus-circle me-1"></i> Add New Page
                </a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger shadow-sm"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 250px;">Page Title</th>
                        <th>Public URL / Slug</th>
                        <th>Browser Title Tag</th>
                        <th>Last Updated</th>
                        <th class="text-end" style="width: 160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pages_list)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-file-earmark-x fs-1 d-block mb-2 text-muted"></i>
                                No content pages found in database.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pages_list as $pg): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo e($pg['title']); ?></div>
                                    <div class="text-muted small"><?php echo strlen(strip_tags($pg['content'])); ?> chars</div>
                                </td>
                                <td>
                                    <code class="text-brand">/<?php echo e($pg['slug']); ?>.php</code>
                                </td>
                                <td>
                                    <span class="small text-muted"><?php echo e($pg['seo_title'] ?: 'Default site title'); ?></span>
                                </td>
                                <td class="small text-muted">
                                    <i class="bi bi-calendar3 me-1"></i> <?php echo date('d-M-Y H:i', strtotime($pg['updated_at'])); ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="../<?php echo e($pg['slug']); ?>.php" target="_blank" class="btn btn-sm btn-outline-info p-1 px-2" title="Preview public live page">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <a href="pages.php?action=edit&id=<?php echo $pg['id']; ?>" class="btn btn-sm btn-outline-secondary p-1 px-2" title="Edit content">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <form action="pages.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this page (<?php echo e($pg['title']); ?>)?');" style="display:inline;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="page_id" value="<?php echo $pg['id']; ?>">
                                            <button type="submit" name="delete_page" class="btn btn-sm btn-outline-danger p-1 px-2" title="Delete page">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
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
