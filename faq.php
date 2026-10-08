<?php
// faq.php
// Public Frequently Asked Questions Page

$meta_title = 'Frequently Asked Questions (FAQ) | Pookky';
require_once __DIR__ . '/includes/header.php';

// Fetch FAQs from database
$faqs_list = [];
try {
    $stmt = $pdo->query("SELECT * FROM faqs WHERE status = 1 ORDER BY sort_order ASC, id ASC");
    $faqs_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $faqs_list = [];
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-9">
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white" style="border-radius: 16px;">
                <h1 class="page-title text-center mb-4">Frequently Asked Questions</h1>
                <p class="text-center text-muted mb-5">Have questions? Find quick answers below on fitting, shipping, returns, and ordering.</p>

                <!-- Bootstrap Accordion Grid -->
                <div class="accordion accordion-flush" id="faqAccordion">
                    <?php if (empty($faqs_list)): ?>
                        <div class="text-center text-muted py-4">No FAQs published yet. Check back soon!</div>
                    <?php else: ?>
                        <?php foreach ($faqs_list as $index => $faq): 
                            // Replace dynamic placeholder tokens with active settings
                            $ans = $faq['answer'];
                            $ans = str_replace('{SHIPPING_FLAT_RATE}', format_price(SHIPPING_FLAT_RATE), $ans);
                            $ans = str_replace('{FREE_SHIPPING_THRESHOLD}', format_price(FREE_SHIPPING_THRESHOLD), $ans);
                            $ans = str_replace('{RETURN_WINDOW_DAYS}', (string)RETURN_WINDOW_DAYS, $ans);
                            $ans = str_replace('{EST_DELIVERY_DAYS}', EST_DELIVERY_MIN_DAYS . '-' . EST_DELIVERY_MAX_DAYS, $ans);
                            $ans = str_replace('{COURIER_PARTNERS}', COURIER_PARTNER_NAME, $ans);
                            $ans = str_replace('{SITE_NAME}', SITE_NAME, $ans);
                        ?>
                            <div class="accordion-item bg-transparent py-2 border-bottom">
                                <h2 class="accordion-header" id="heading_<?php echo $faq['id']; ?>">
                                    <button class="accordion-button <?php echo $index === 0 ? '' : 'collapsed'; ?> bg-transparent fw-bold text-dark fs-6" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_<?php echo $faq['id']; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="collapse_<?php echo $faq['id']; ?>">
                                        <?php echo e($faq['question']); ?>
                                    </button>
                                </h2>
                                <div id="collapse_<?php echo $faq['id']; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="heading_<?php echo $faq['id']; ?>" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body text-secondary small">
                                        <?php echo $ans; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
