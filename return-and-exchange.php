<?php
// return-and-exchange.php
// Public Return & Exchange Policy page loaded dynamically from DB

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/constants.php";
require_once __DIR__ . "/includes/functions.php";

try {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = \"return-and-exchange\" LIMIT 1");
    $stmt->execute();
    $page = $stmt->fetch();
} catch (PDOException $e) {
    $page = null;
}

$title = $page ? $page["title"] : "Return & Exchange Policy";
$meta_title = !empty($page["seo_title"]) ? $page["seo_title"] : $title . " | " . SITE_NAME;
$meta_desc = !empty($page["seo_description"]) ? $page["seo_description"] : "Official " . strtolower($title) . " of " . SITE_NAME . " - Luxury Ladies\" Innerwear & Lingerie.";
$content = $page ? $page["content"] : "<div class=\"text-center py-5 text-muted\">Page content currently updating. Check back soon.</div>";
$updated_at = $page ? $page["updated_at"] : null;

require_once __DIR__ . "/includes/header.php";
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
                <i class="bi bi-clock-history me-1"></i> Last updated: <?php echo date("F d, Y", strtotime($updated_at)); ?>
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

<?php require_once __DIR__ . "/includes/footer.php"; ?>
