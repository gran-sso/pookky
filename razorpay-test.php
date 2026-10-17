<?php
// razorpay-test.php
// Standalone Razorpay Standard Checkout Test Page & Verification Dashboard

$page_title = 'Razorpay Integration Test';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5 bg-white">
                <div class="text-center mb-4">
                    <div class="d-inline-flex p-3 rounded-circle bg-danger-subtle text-danger mb-3">
                        <i class="bi bi-credit-card-2-front fs-1"></i>
                    </div>
                    <h2 class="fw-bold text-dark">Razorpay Standard Checkout</h2>
                    <p class="text-muted small">Test the full end-to-end payment creation, checkout modal, and cryptographic signature verification flow.</p>
                </div>

                <!-- Gateway Status Alert -->
                <div class="alert <?php echo RAZORPAY_ENABLED ? 'alert-success' : 'alert-warning'; ?> d-flex align-items-center gap-2 mb-4 py-2 px-3 small rounded-3">
                    <i class="bi <?php echo RAZORPAY_ENABLED ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> fs-5"></i>
                    <div>
                        <strong>Gateway Status:</strong> <?php echo RAZORPAY_ENABLED ? 'Active & Ready' : 'Disabled / Missing Credentials'; ?>
                        <div class="text-muted" style="font-size: 11px;">Key ID: <code><?php echo e(substr(RAZORPAY_KEY_ID, 0, 12) . '...'); ?></code></div>
                    </div>
                </div>

                <!-- Test Form -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark" for="testAmount">Test Payment Amount (₹ INR):</label>
                    <div class="input-group input-group-lg mb-2">
                        <span class="input-group-text bg-light text-dark fw-bold">₹</span>
                        <input type="number" id="testAmount" class="form-control" value="10.00" min="1.00" step="1.00" placeholder="10.00">
                    </div>
                    <div class="text-muted small">Minimum amount: ₹1.00 (100 paise). Default: ₹10.00 (1000 paise).</div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-dark" for="testCustomerName">Customer Details (Prefill):</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="text" id="testCustomerName" class="form-control" placeholder="Full Name" value="Pookky Tester">
                        </div>
                        <div class="col-6">
                            <input type="tel" id="testCustomerPhone" class="form-control" placeholder="Phone" value="9876543210">
                        </div>
                    </div>
                    <input type="email" id="testCustomerEmail" class="form-control mt-2" placeholder="Email" value="tester@pookky.com">
                </div>

                <!-- Trigger Button -->
                <button id="rzpTestPayBtn" class="btn btn-brand w-100 py-3 rounded-pill fw-bold shadow fs-5 d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-shield-lock-fill"></i> Pay With Razorpay
                </button>

                <!-- Status Console / Output -->
                <div id="rzpConsoleBox" class="mt-4 p-3 rounded-3 bg-dark text-light small d-none" style="font-family: monospace; max-height: 250px; overflow-y: auto;">
                    <div class="text-secondary mb-1 border-bottom border-secondary pb-1 d-flex justify-content-between">
                        <span>Integration Diagnostics</span>
                        <span id="rzpStepBadge" class="badge bg-secondary">Idle</span>
                    </div>
                    <div id="rzpConsoleLog"></div>
                </div>

                <div class="text-center mt-4 pt-3 border-top">
                    <a href="checkout.php" class="text-decoration-none small text-muted">
                        <i class="bi bi-arrow-left me-1"></i> Return to Store Checkout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Razorpay Checkout JS SDK -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const payBtn = document.getElementById('rzpTestPayBtn');
    const amountInput = document.getElementById('testAmount');
    const nameInput = document.getElementById('testCustomerName');
    const emailInput = document.getElementById('testCustomerEmail');
    const phoneInput = document.getElementById('testCustomerPhone');
    const consoleBox = document.getElementById('rzpConsoleBox');
    const consoleLog = document.getElementById('rzpConsoleLog');
    const stepBadge = document.getElementById('rzpStepBadge');

    function log(msg, type = 'info') {
        consoleBox.classList.remove('d-none');
        const color = type === 'error' ? 'text-danger' : (type === 'success' ? 'text-success' : (type === 'warn' ? 'text-warning' : 'text-info'));
        const time = new Date().toLocaleTimeString();
        consoleLog.innerHTML += `<div class="${color}">[${time}] ${msg}</div>`;
        consoleBox.scrollTop = consoleBox.scrollHeight;
    }

    payBtn.addEventListener('click', async function(e) {
        e.preventDefault();
        consoleLog.innerHTML = '';
        stepBadge.className = 'badge bg-primary';
        stepBadge.textContent = 'Step 1: Create Order';

        const amountRupees = parseFloat(amountInput.value);
        if (isNaN(amountRupees) || amountRupees < 1.0) {
            alert('Please enter a valid amount of at least ₹1.00');
            return;
        }

        const amountPaise = Math.round(amountRupees * 100);
        log(`Initiating order creation for ₹${amountRupees.toFixed(2)} (${amountPaise} paise)...`);

        payBtn.disabled = true;
        payBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating Order...';

        try {
            // STEP 1: Call Backend to Create Razorpay Order
            const createResp = await fetch('api/create-order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    amount: amountPaise,
                    currency: 'INR',
                    receipt: 'test_web_' + Date.now()
                })
            });

            const orderData = await createResp.json();
            if (!createResp.ok || !orderData.order_id) {
                throw new Error(orderData.error || 'Failed to create Razorpay order.');
            }

            log(`Razorpay order created successfully: ${orderData.order_id}`, 'success');
            stepBadge.className = 'badge bg-warning text-dark';
            stepBadge.textContent = 'Step 2: Modal Checkout';

            // STEP 2: Configure & Open Razorpay Standard Checkout Modal
            const options = {
                key: orderData.key_id || '<?php echo RAZORPAY_KEY_ID; ?>',
                amount: orderData.amount,
                currency: orderData.currency,
                name: '<?php echo e(SITE_NAME); ?>',
                description: 'Test Checkout Demonstration',
                order_id: orderData.order_id,
                prefill: {
                    name: nameInput.value.trim(),
                    email: emailInput.value.trim(),
                    contact: phoneInput.value.trim()
                },
                theme: {
                    color: '#E12B56'
                },
                modal: {
                    ondismiss: function() {
                        stepBadge.className = 'badge bg-secondary';
                        stepBadge.textContent = 'Dismissed';
                        log('Checkout modal was closed by user without completing payment.', 'warn');
                        payBtn.disabled = false;
                        payBtn.innerHTML = '<i class="bi bi-shield-lock-fill"></i> Pay With Razorpay';
                    }
                },
                handler: async function(paymentResponse) {
                    // STEP 3: Receive payment response and send to backend verify endpoint
                    stepBadge.className = 'badge bg-info text-dark';
                    stepBadge.textContent = 'Step 3: Signature Verification';

                    log('Payment successful in modal. Verifying cryptographic signature...', 'info');
                    log(`Payment ID: ${paymentResponse.razorpay_payment_id}`);
                    log(`Order ID: ${paymentResponse.razorpay_order_id}`);
                    log(`Signature: ${paymentResponse.razorpay_signature}`);

                    try {
                        const verifyResp = await fetch('api/verify-payment.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                razorpay_order_id: paymentResponse.razorpay_order_id,
                                razorpay_payment_id: paymentResponse.razorpay_payment_id,
                                razorpay_signature: paymentResponse.razorpay_signature
                            })
                        });

                        const verifyData = await verifyResp.json();
                        if (verifyResp.ok && verifyData.success) {
                            stepBadge.className = 'badge bg-success';
                            stepBadge.textContent = 'Verified (200 OK)';
                            log('SUCCESS! HMAC-SHA256 signature verified by backend.', 'success');
                            alert('Payment and HMAC-SHA256 signature verified successfully!');
                        } else {
                            stepBadge.className = 'badge bg-danger';
                            stepBadge.textContent = 'Verification Failed';
                            log('Signature verification FAILED: ' + (verifyData.error || 'Invalid signature'), 'error');
                            alert('Signature verification failed: ' + (verifyData.error || 'Invalid signature'));
                        }
                    } catch (vErr) {
                        stepBadge.className = 'badge bg-danger';
                        stepBadge.textContent = 'Network Error';
                        log('Error calling verify endpoint: ' + vErr.message, 'error');
                    } finally {
                        payBtn.disabled = false;
                        payBtn.innerHTML = '<i class="bi bi-shield-lock-fill"></i> Pay With Razorpay';
                    }
                }
            };

            const rzpInstance = new Razorpay(options);
            rzpInstance.on('payment.failed', function(resp) {
                stepBadge.className = 'badge bg-danger';
                stepBadge.textContent = 'Payment Failed';
                log(`Payment failed: ${resp.error.code} - ${resp.error.description}`, 'error');
                alert(`Payment Failed: ${resp.error.description}`);
                payBtn.disabled = false;
                payBtn.innerHTML = '<i class="bi bi-shield-lock-fill"></i> Pay With Razorpay';
            });

            rzpInstance.open();

        } catch (err) {
            stepBadge.className = 'badge bg-danger';
            stepBadge.textContent = 'Error';
            log('Error: ' + err.message, 'error');
            alert('Could not start checkout: ' + err.message);
            payBtn.disabled = false;
            payBtn.innerHTML = '<i class="bi bi-shield-lock-fill"></i> Pay With Razorpay';
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
