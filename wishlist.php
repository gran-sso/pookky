<?php
// wishlist.php
// Customer Wishlist Manager

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';

// Requires customer login
require_customer_login();

$meta_title = 'My Wishlist | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';

$customer_id = $_SESSION['customer_id'];
$error = '';
$success = '';

// Fetch customer details
$customer = get_logged_in_customer();

// Fetch wishlist items
try {
    $stmt = $pdo->prepare("
        SELECT w.id as wish_id, p.*, c.name as category_name,
        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_featured DESC LIMIT 1) as main_image 
        FROM wishlist w 
        JOIN products p ON w.product_id = p.id 
        JOIN categories c ON p.category_id = c.id 
        WHERE w.customer_id = ? 
        ORDER BY w.id DESC
    ");
    $stmt->execute([$customer_id]);
    $wishlist = $stmt->fetchAll();
} catch (PDOException $e) {
    $wishlist = [];
    $error = 'Failed to load wishlist details.';
}
?>

<div class="container py-5">
    <div class="row g-4">
        <!-- Dashboard Sidebar Nav -->
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
                    <a href="addresses.php" class="list-group-item list-group-item-action border-0 rounded p-2 mb-1"><i class="bi bi-geo-alt me-2"></i> Address Book</a>
                    <a href="orders.php" class="list-group-item list-group-item-action border-0 rounded p-2 mb-1"><i class="bi bi-box me-2"></i> Order History</a>
                    <a href="wishlist.php" class="list-group-item list-group-item-action border-0 active bg-brand-blush text-rose fw-bold rounded p-2 mb-1"><i class="bi bi-heart me-2"></i> My Wishlist</a>
                    <a href="logout.php" class="list-group-item list-group-item-action border-0 text-danger rounded p-2"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
                </div>
            </div>
        </div>

        <!-- Main content block -->
        <div class="col-12 col-md-9">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger p-2 small"><?php echo e($error); ?></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius:12px;">
                <h5 class="fw-bold mb-4"><i class="bi bi-heart-fill text-danger me-2"></i> My Wishlist</h5>
                
                <?php if (empty($wishlist)): ?>
                    <div class="text-center py-5">
                        <div class="fs-1 text-muted"><i class="bi bi-heart"></i></div>
                        <h6 class="fw-bold text-dark mt-3">Your Wishlist is Empty</h6>
                        <p class="text-muted small">You haven't favorited any products yet.</p>
                        <a href="products.php" class="btn btn-brand btn-sm mt-2">Browse Catalog</a>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($wishlist as $item): ?>
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="card product-card h-100 border">
                                    <div class="img-container">
                                        <!-- Trash remover option -->
                                        <div class="wishlist-overlay wishlist-toggle" data-product-id="<?php echo $item['id']; ?>" title="Remove from Wishlist">
                                            <i class="bi bi-heart-fill"></i>
                                        </div>
                                        <a href="product-detail.php?slug=<?php echo e($item['slug']); ?>">
                                            <?php if ($item['main_image']): ?>
                                                <img src="uploads/<?php echo e($item['main_image']); ?>" alt="<?php echo e($item['name']); ?>">
                                            <?php else: ?>
                                                <div class="w-100 h-100 bg-secondary d-flex align-items-center justify-content-center text-white"><i class="bi bi-image fs-1"></i></div>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                    <div class="product-details text-center d-flex flex-column justify-content-between" style="height: 140px;">
                                        <div>
                                            <span class="product-category"><?php echo e($item['category_name']); ?></span>
                                            <a href="product-detail.php?slug=<?php echo e($item['slug']); ?>">
                                                <h6 class="product-title"><?php echo e($item['name']); ?></h6>
                                            </a>
                                        </div>
                                        <div>
                                            <div class="price-container justify-content-center mb-2">
                                                <span class="price-regular"><?php echo format_price($item['offer_price'] ?? $item['regular_price']); ?></span>
                                            </div>
                                            <a href="product-detail.php?slug=<?php echo e($item['slug']); ?>" class="btn btn-sm btn-brand w-100 rounded-pill">Choose Options</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
