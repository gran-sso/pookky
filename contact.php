<?php
// contact.php
// Public Contact Us Page

$meta_title = 'Contact Us Support | Pookky';
$meta_desc = 'Contact the Pookky customer support team for inquiries on shipping, bulk innerwear orders, or return queries.';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/security.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed. Please refresh page.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $error = 'Please fill in all required fields (Name, Email, Subject, and Message).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO enquiries (name, email, phone, subject, message, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$name, $email, $phone, $subject, $message]);
                set_flash_message('success', 'Your inquiry has been sent successfully! Our customer support team will reply back to your email shortly.');
                header('Location: contact.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Failed to submit enquiry. Try again later.';
            }
        }
    }
}
?>

<div class="container py-5">
    <div class="row g-5">
        <!-- Contact info -->
        <div class="col-12 col-md-5">
            <h1 class="page-title mb-4">Get in Touch</h1>
            <p class="text-secondary mb-4">Have questions about sizes, fabric blends, or bulk orders? Reach out directly. Our support staff is available <?php echo e(SUPPORT_HOURS); ?>.</p>
            
            <div class="d-flex align-items-start gap-3 mb-4">
                <div class="fs-4 text-rose"><i class="bi bi-geo-alt-fill"></i></div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Corporate HQ Address</h6>
                    <span class="small text-muted"><?php echo e(COMPANY_ADDRESS); ?></span>
                </div>
            </div>

            <div class="d-flex align-items-start gap-3 mb-4">
                <div class="fs-4 text-rose"><i class="bi bi-telephone-fill"></i></div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Customer Support Phone</h6>
                    <span class="small text-muted"><?php echo e(SITE_PHONE); ?></span>
                </div>
            </div>

            <div class="d-flex align-items-start gap-3 mb-4">
                <div class="fs-4 text-rose"><i class="bi bi-envelope-fill"></i></div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Email Coordinates</h6>
                    <span class="small text-muted"><?php echo e(SITE_EMAIL); ?></span>
                </div>
            </div>
        </div>

        <!-- Contact form -->
        <div class="col-12 col-md-7">
            <div class="card glass-card p-4 p-md-5 border-0 bg-white" style="border-radius:16px;">
                <h4 class="fw-bold text-dark mb-4">Send Us a Message</h4>
                
                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger p-2 small"><?php echo e($error); ?></div>
                <?php endif; ?>

                <form action="contact.php" method="POST">
                    <?php echo csrf_field(); ?>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="name" name="name" required placeholder="Full Name" autocomplete="name">
                                <label for="name">Full Name <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="form-floating">
                                <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone Number" autocomplete="tel">
                                <label for="phone">Phone Number (Optional)</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" name="email" required placeholder="Email Address" autocomplete="email">
                        <label for="email">Email Address <span class="text-danger">*</span></label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="subject" name="subject" required placeholder="Subject">
                        <label for="subject">Subject <span class="text-danger">*</span></label>
                    </div>

                    <div class="form-floating mb-4">
                        <textarea class="form-control" id="message" name="message" style="height: 120px;" required placeholder="Detailed Message"></textarea>
                        <label for="message">Detailed Message <span class="text-danger">*</span></label>
                    </div>

                    <button type="submit" class="btn btn-brand w-100">Send Enquiry Message</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
