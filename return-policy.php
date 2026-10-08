<?php
// return-policy.php
// Public returns policy page loaded dynamically from DB

require_once __DIR__ . '/includes/header.php';

try {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = 'return-and-exchange' LIMIT 1");
    $stmt->execute();
    $page = $stmt->fetch();
} catch (PDOException $e) {
    $page = null;
}

$title = $page ? $page['title'] : 'Return & Exchange Policy';
$content = $page ? $page['content'] : '<div class="text-center py-5 text-muted">Page content currently updating. Check back soon.</div>';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-9">
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white" style="border-radius: 16px;">
                <h1 class="fw-bold mb-4 font-family-heading text-dark"><?php echo e($title); ?></h1>
                <hr class="mb-4">
                <div class="page-content text-secondary">
                    <?php echo $content; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
